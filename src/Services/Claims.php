<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class Claims
{
    public function __construct(private DbInterface $db, private int $organizationId) {}

    public function rows(array $source, array $params = []): array
    {
        $status = $params['claim_filter'] ?? $params['claim_status'] ?? '';
        if ($status !== '' && !in_array($status, ['penalty', 'payable', 'free', 'incomplete'], true)) {
            throw new \InvalidArgumentException('Выберите статус расчёта');
        }
        $where = '1=1';
        $bindings = $source['bindings'];
        $station = trim((string) ($params['station_code'] ?? ''));
        if ($station !== '') {
            if (!preg_match('/^\d{6}$/D', $station)) {
                throw new \InvalidArgumentException('Выберите станцию');
            }
            $where .= ' AND station_code = :station_code';
            $bindings['station_code'] = $station;
        }
        if (!$source['from']) {
            return [];
        }
        $wagonText = trim((string) ($params['wagon_no'] ?? ''));
        if ($wagonText !== '') {
            $wagons = array_values(array_filter(array_map('trim', explode(';', $wagonText))));
            $placeholders = [];
            foreach ($wagons as $i => $wagon) {
                $placeholders[] = ':claim_wagon_' . $i;
                $bindings['claim_wagon_' . $i] = $wagon;
            }
            if ($placeholders) {
                $where .= ' AND TRIM(wagon_no) IN (' . implode(', ', $placeholders) . ')';
            }
        }
        $data = $this->db->fetchAll("SELECT * FROM {$source['from']} WHERE $where", $bindings);
        $norms = (new ClaimNorms($this->db, $this->organizationId))->list();
        $rows = [];
        foreach ($data as $row) {
            if ($this->key($row['owner'] ?? '') === 'АО "МЕТАФРАКС КЕМИКАЛС"') {
                continue;
            }
            $row = $this->calculate($row, $norms);
            if (($params['wagon_type'] ?? '') !== '' && $row['wagon_type_code'] !== $params['wagon_type']) {
                continue;
            }
            if ($status === 'payable' && (int) ($row['payable_days'] ?? 0) <= 0) {
                continue;
            }
            if ($status !== '' && $status !== 'payable' && $row['claim_status'] !== $status) {
                continue;
            }
            $rows[] = $row;
        }
        usort($rows, static fn($a, $b) => ($b['amount'] ?? 0) <=> ($a['amount'] ?? 0)
            ?: ($b['total_days'] ?? 0) <=> ($a['total_days'] ?? 0)
            ?: strcmp($a['wagon_no'], $b['wagon_no']));
        return $rows;
    }

    public function calculate(array $row, array $norms): array
    {
        $report = new \DateTimeImmutable($row['report_dt'], new \DateTimeZone('Europe/Moscow'));
        $arrived = $row['trip_end_dt'] ?: ($row['asoup_arrive_dt'] ?? null);
        $norm = null;
        $fields = [
            'SHIPPER' => ['consignor', 'consignor_name'], 'OWNER' => ['owner'],
            'CARGO' => ['cargo_name'], 'CONSIGNEE' => ['consignee', 'consignee_name'],
            'PREV_CARGO' => ['prev_cargo'], 'WAGON_TYPE' => ['wagon_type_code', 'wagon_type_name'],
        ];
        $stationCode = trim((string) ($row['station_code'] ?? ''));
        $scopeStationCodes = $stationCode !== '' ? [$stationCode, null] : [null];
        foreach ($scopeStationCodes as $scopeStationCode) {
            foreach ($fields as $type => $names) {
                $keys = [];
                foreach ($names as $name) {
                    $value = (string) ($row[$name] ?? '');
                    $keys[] = $this->key($value);
                    $keys[] = $this->key(preg_replace('/\s*\(\d+\)\s*$/u', '', $value));
                }
                $keys = array_filter(array_unique($keys), static fn($key) => $key !== '');
                foreach ($keys as $key) {
                    foreach ($norms as $rule) {
                        $ruleStationCode = trim((string) ($rule['station_code'] ?? ''));
                        $stationMatches = $scopeStationCode === null
                            ? $ruleStationCode === ''
                            : $ruleStationCode === $scopeStationCode;
                        if (!$stationMatches || $rule['rule_type'] !== $type) {
                            continue;
                        }
                        if ($rule['valid_from'] > $report->format('Y-m-d')
                            || ($rule['valid_to'] && $rule['valid_to'] < $report->format('Y-m-d'))) {
                            continue;
                        }
                        if ($this->key($rule['rule_value']) === $key) {
                            $norm = $rule;
                            break 4;
                        }
                    }
                }
            }
        }
        $result = array_intersect_key($row, array_flip([
            'wagon_no', 'wagon_type_code', 'cargo_name', 'prev_cargo', 'owner', 'consignor',
            'consignee', 'oper_station', 'dest_station', 'station_code', 'report_dt', 'waybill_no',
            'type_reference',
        ]));
        $result += ['arrived_at' => $arrived, 'total_days' => null, 'free_days' => $norm ? (int) $norm['free_days'] : null,
                    'payable_days' => null, 'daily_rate' => $norm ? (float) $norm['daily_rate'] : null,
                    'amount' => null, 'claim_status' => 'incomplete', 'data_status' => 'Нет даты прибытия'];
        if (!$arrived) {
            return $result;
        }
        try {
            $arrival = new \DateTimeImmutable($arrived, new \DateTimeZone('Europe/Moscow'));
        } catch (\Exception $error) {
            return $result;
        }
        if ($arrival > $report) {
            $result['data_status'] = 'Дата прибытия позже справки';
            return $result;
        }
        $seconds = $report->getTimestamp() - $arrival->getTimestamp();
        $result['total_days'] = (int) ceil($seconds / 86400);
        if (!$norm) {
            $result['data_status'] = 'Норматив не задан';
            return $result;
        }
        $result['payable_days'] = (int) ceil(max(0, $seconds - $result['free_days'] * 86400) / 86400);
        $parts = explode('.', (string) $norm['daily_rate']);
        $kopecks = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        $result['amount'] = round($result['payable_days'] * $kopecks / 100, 2);
        $result['claim_status'] = $result['amount'] > 0 ? 'penalty' : 'free';
        $result['data_status'] = $result['amount'] > 0 ? 'Со штрафом' : 'Без начисления';
        return $result;
    }

    private function key(string $value): string
    {
        return str_replace('Ё', 'Е', mb_strtoupper(preg_replace('/\s+/u', ' ', trim($value))));
    }
}
