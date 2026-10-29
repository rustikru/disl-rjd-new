<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class Claims
{
    private const WAGON_TYPE = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code)";

    private ReportService $reports;

    public function __construct(private DbInterface $db, private Organizations $organizations)
    {
        $this->reports = new ReportService($db, $organizations);
    }

    public function filters(array $params): array
    {
        $source = $this->source($params);
        if ($source['from'] === '') {
            return ['stations' => [], 'wagon_type' => []];
        }

        $stations = $this->db->fetchAll(
            "SELECT station_code, MIN(dest_station) AS station_name FROM {$source['from']}
              WHERE station_code IS NOT NULL GROUP BY station_code ORDER BY station_name",
            $source['bindings']
        );
        $types = $this->db->fetchAll(
            "SELECT DISTINCT wagon_type_code FROM {$source['from']} ORDER BY wagon_type_code",
            $source['bindings']
        );

        return [
            'stations' => $stations,
            'wagon_type' => array_column($types, 'wagon_type_code'),
        ];
    }

    public function summary(array $params): array
    {
        $source = $this->source($params);
        $rows = $this->rows($source, $params);
        $empty = [
            'wagon_type_code' => 'Общий итог',
            'total' => 0,
            'penalty_count' => 0,
            'payable_days' => 0,
            'amount' => 0,
            'incomplete_count' => 0,
        ];
        $groups = [];
        $total = $empty;

        foreach ($rows as $row) {
            $type = $row['wagon_type_code'] ?: 'Прочие';
            if (!isset($groups[$type])) {
                $groups[$type] = array_replace($empty, ['wagon_type_code' => $type]);
            }
            foreach ([$type, null] as $key) {
                $group = &$total;
                if ($key !== null) {
                    $group = &$groups[$key];
                }
                $group['total']++;
                $group['penalty_count'] += (int) ($row['claim_status'] === 'penalty');
                $group['incomplete_count'] += (int) ($row['claim_status'] === 'incomplete');
                $group['payable_days'] += $row['payable_days'] ?? 0;
                $group['amount'] = round($group['amount'] + ($row['amount'] ?? 0), 2);
                unset($group);
            }
        }

        ksort($groups);
        return [
            'rows' => array_values($groups),
            'totals' => $total,
            'total' => count($rows),
            'reports' => $source['reports'],
        ];
    }

    public function detail(array $params): array
    {
        $rows = $this->rows($this->source($params), $params);
        $fields = array_values(array_filter(array_map('trim', explode(',', (string) ($params['fields'] ?? '')))));
        $sort = array_values(array_filter(array_map('trim', explode(',', (string) ($params['sort'] ?? '')))));
        $directions = explode(',', (string) ($params['sort_dir'] ?? ''));

        if ($sort && $rows) {
            $allowed = array_keys($rows[0]);
            $sort = array_values(array_filter($sort, static fn($key) => in_array($key, $allowed, true)));
            usort($rows, static function ($a, $b) use ($sort, $directions) {
                foreach ($sort as $index => $key) {
                    if ($a[$key] === $b[$key]) {
                        continue;
                    }
                    if ($a[$key] === null) {
                        return 1;
                    }
                    if ($b[$key] === null) {
                        return -1;
                    }
                    $result = is_numeric($a[$key]) && is_numeric($b[$key])
                        ? (float) $a[$key] <=> (float) $b[$key]
                        : strcmp((string) $a[$key], (string) $b[$key]);
                    if ($result !== 0) {
                        return strtolower($directions[$index] ?? 'asc') === 'desc' ? -$result : $result;
                    }
                }
                return strcmp($a['wagon_no'], $b['wagon_no']);
            });
        }
        if ($fields) {
            $keys = array_flip($fields);
            $rows = array_map(static fn($row) => array_intersect_key($row, $keys), $rows);
        }

        return ['rows' => $rows];
    }

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
        $norms = (new ClaimNorms($this->db, (int) $this->organizations->id()))->list();
        $rows = [];
        foreach ($data as $row) {
            if ($this->organizations->isCurrentOrganizationName((string) ($row['owner'] ?? ''))) {
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

    private function source(array $params): array
    {
        $reports = $this->reports->getLatestDtsByType(
            $params['report_dt'] ?? null,
            ['Подход', 'Отправка']
        );
        if (!$reports) {
            return ['from' => '', 'bindings' => [], 'reports' => []];
        }

        $condition = $this->reports->latestDtCondition($reports, 'xdr');
        $from = "(SELECT TRIM(wagon_no) AS wagon_no, wagon_type_code AS wagon_type_name,
                         " . self::WAGON_TYPE . " AS wagon_type_code,
                         cargo_name, prev_cargo, owner, consignor, consignor_name, consignee, consignee_name,
                         oper_station, dest_station, trip_end_dt, asoup_arrive_dt, report_dt, waybill_no, type_reference,
                         REGEXP_SUBSTR(dest_station, '[0-9]{6}') AS station_code
                  FROM (
                      SELECT xdr.*, ROW_NUMBER() OVER (
                          PARTITION BY TRIM(wagon_no)
                          ORDER BY oper_dt DESC NULLS LAST, report_dt DESC,
                                   CASE WHEN type_reference = 'Подход' THEN 0 ELSE 1 END, id DESC
                      ) AS claim_row
                        FROM xx_dislocation_rjd xdr WHERE {$condition['sql']}
                  )
                 WHERE claim_row = 1 AND dist_remain_km = 0
                   AND REGEXP_SUBSTR(oper_station, '[0-9]{6}') = REGEXP_SUBSTR(dest_station, '[0-9]{6}')
                   AND TRIM(wagon_no) NOT LIKE '0%'
                   AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                       NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%')";

        return ['from' => $from, 'bindings' => $condition['params'], 'reports' => $reports];
    }
}
