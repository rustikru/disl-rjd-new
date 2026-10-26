<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class ClaimNormService
{
    public const TYPES = [
        'SHIPPER' => 'Грузоотправитель',
        'OWNER' => 'Собственник',
        'CARGO' => 'Груз',
        'CONSIGNEE' => 'Грузополучатель',
        'PREV_CARGO' => 'Ранее выгруженный груз',
        'WAGON_TYPE' => 'Род вагона',
    ];

    public function __construct(private DbInterface $db, private int $organizationId) {}

    public function list(array $filter = []): array
    {
        $where = 'organization_id = :organization_id';
        $bindings = ['organization_id' => $this->organizationId];
        if (($filter['station'] ?? '') !== '') {
            $station = $this->station((string) $filter['station']);
            $where .= ' AND station_code = :station_code';
            $bindings['station_code'] = $station['esr_code'];
        }
        if (($filter['type'] ?? '') !== '') {
            if (!isset(self::TYPES[$filter['type']])) {
                throw new \InvalidArgumentException('Выберите тип норматива');
            }
            $where .= ' AND rule_type = :rule_type';
            $bindings['rule_type'] = $filter['type'];
        }
        if (($filter['date'] ?? '') !== '') {
            $date = $this->date($filter['date']);
            $where .= " AND valid_from <= TO_DATE(:valid_date, 'YYYY-MM-DD')"
                . " AND (valid_to IS NULL OR valid_to >= TO_DATE(:valid_date, 'YYYY-MM-DD'))";
            $bindings['valid_date'] = $date;
        }
        return $this->db->fetchAll(
            "SELECT id, station_code,
                    (SELECT MIN(station_name) FROM xx_rjd_stations s WHERE s.esr_code = n.station_code) AS station_name,
                    rule_type, rule_value, free_days, daily_rate,
                    TO_CHAR(valid_from, 'YYYY-MM-DD') AS valid_from,
                    TO_CHAR(valid_to, 'YYYY-MM-DD') AS valid_to
               FROM xx_rjd_claim_norms n WHERE $where
              ORDER BY station_name, station_code, rule_type, rule_value, valid_from DESC, id DESC",
            $bindings
        );
    }

    public function stations(string $search): array
    {
        return $this->db->fetchAll(
            'SELECT esr_code, station_name FROM TABLE(xx_rjd_dislocation_new_pkg.stations(:p_search, :p_offset, :p_limit))',
            ['p_search' => $search !== '' ? $search : null, 'p_offset' => 0, 'p_limit' => 30]
        );
    }

    private function station(string $value): array
    {
        $value = trim($value);
        if (preg_match('/^\d{6}$/D', $value)) {
            $code = $value;
        } elseif (preg_match('/\((\d{6})\)$/D', $value, $match)) {
            $code = $match[1];
        } else {
            throw new \InvalidArgumentException('Выберите станцию из справочника или введите шестизначный код ЕСР');
        }
        $station = $this->db->fetchOne(
            'SELECT esr_code, station_name FROM xx_rjd_stations WHERE esr_code = :code',
            ['code' => $code]
        );
        if (!$station) {
            throw new \InvalidArgumentException('Станция не найдена в справочнике');
        }
        return $station;
    }

    public function values(string $type, string $search): array
    {
        $columns = [
            'SHIPPER' => 'consignor', 'OWNER' => 'owner', 'CARGO' => 'cargo_name',
            'CONSIGNEE' => 'consignee', 'PREV_CARGO' => 'prev_cargo',
            'WAGON_TYPE' => 'xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code)',
        ];
        if (!isset($columns[$type])) {
            throw new \InvalidArgumentException('Выберите тип норматива');
        }
        $column = $columns[$type];
        $rows = $this->db->fetchAll(
            "SELECT value FROM (
                SELECT value FROM (
                    SELECT DISTINCT TRIM($column) AS value FROM xx_dislocation_rjd
                     WHERE organization_id = :organization_id
                    UNION
                    SELECT rule_value AS value FROM xx_rjd_claim_norms
                     WHERE organization_id = :organization_id AND rule_type = :rule_type
                ) WHERE value IS NOT NULL AND (:search IS NULL OR INSTR(UPPER(value), UPPER(:search)) > 0)
                ORDER BY value
            ) WHERE ROWNUM <= 50",
            ['organization_id' => $this->organizationId, 'rule_type' => $type,
             'search' => $search !== '' ? $search : null]
        );
        return array_column($rows, 'value');
    }

    public function save(array $data, int $userId): void
    {
        $station = $this->station((string) ($data['station_code'] ?? ''));
        $stationCode = $station['esr_code'];
        $type = (string) ($data['rule_type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('Выберите тип норматива');
        }
        $value = preg_replace('/\s+/u', ' ', trim((string) ($data['rule_value'] ?? '')));
        if ($value === '' || mb_strlen($value) > 500) {
            throw new \InvalidArgumentException('Укажите значение норматива длиной до 500 символов');
        }
        $key = str_replace('Ё', 'Е', mb_strtoupper($value));
        $days = (string) ($data['free_days'] ?? '');
        if (!preg_match('/^\d{1,6}$/D', $days)) {
            throw new \InvalidArgumentException('Бесплатный простой: целое число от 0 до 999999');
        }
        $rate = str_replace(',', '.', trim((string) ($data['daily_rate'] ?? '')));
        if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $rate)) {
            throw new \InvalidArgumentException('Укажите неотрицательную ставку с точностью до копеек');
        }
        $from = $this->date((string) ($data['valid_from'] ?? ''));
        $to = trim((string) ($data['valid_to'] ?? ''));
        $to = $to !== '' ? $this->date($to) : null;
        if ($to !== null && $to < $from) {
            throw new \InvalidArgumentException('Дата окончания не может быть раньше даты начала');
        }
        $idText = (string) ($data['id'] ?? '');
        if ($idText !== '' && (!ctype_digit($idText) || (int) $idText < 1)) {
            throw new \InvalidArgumentException('Норматив не найден');
        }
        $id = (int) $idText;
        $this->db->beginTransaction();
        try {
            $organization = $this->db->fetchOne(
                'SELECT id FROM xx_rjd_organizations WHERE id = :id FOR UPDATE',
                ['id' => $this->organizationId]
            );
            if (!$organization) {
                throw new \InvalidArgumentException('Организация не найдена');
            }
            if ($id && !$this->db->fetchOne(
                'SELECT id FROM xx_rjd_claim_norms WHERE id = :id AND organization_id = :organization_id',
                ['id' => $id, 'organization_id' => $this->organizationId]
            )) {
                throw new \InvalidArgumentException('Норматив не найден');
            }
            $overlap = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt FROM xx_rjd_claim_norms
                  WHERE organization_id = :organization_id AND station_code = :station_code AND rule_type = :rule_type AND rule_key = :rule_key
                    AND id <> :id
                    AND valid_from <= NVL(TO_DATE(:valid_to, 'YYYY-MM-DD'), DATE '9999-12-31')
                    AND NVL(valid_to, DATE '9999-12-31') >= TO_DATE(:valid_from, 'YYYY-MM-DD')",
                ['organization_id' => $this->organizationId, 'station_code' => $stationCode, 'rule_type' => $type, 'rule_key' => $key,
                 'id' => $id, 'valid_from' => $from, 'valid_to' => $to]
            );
            if ((int) $overlap['cnt'] > 0) {
                throw new \InvalidArgumentException('Для этой станции и значения уже есть норматив с пересекающимся сроком действия');
            }
            $bindings = ['organization_id' => $this->organizationId, 'station_code' => $stationCode, 'rule_type' => $type,
                'rule_value' => $value, 'rule_key' => $key, 'free_days' => (int) $days,
                'daily_rate' => $rate, 'valid_from' => $from, 'valid_to' => $to, 'user_id' => $userId];
            if ($id) {
                $bindings['id'] = $id;
                $this->db->execute(
                    "UPDATE xx_rjd_claim_norms SET station_code = :station_code, rule_type = :rule_type, rule_value = :rule_value,
                            rule_key = :rule_key, free_days = :free_days, daily_rate = :daily_rate,
                            valid_from = TO_DATE(:valid_from, 'YYYY-MM-DD'), valid_to = TO_DATE(:valid_to, 'YYYY-MM-DD'),
                            updated_at = SYSDATE, updated_by = :user_id
                      WHERE id = :id AND organization_id = :organization_id", $bindings
                );
            } else {
                $this->db->execute(
                    "INSERT INTO xx_rjd_claim_norms
                        (organization_id, station_code, rule_type, rule_value, rule_key, free_days, daily_rate,
                         valid_from, valid_to, created_by, updated_by)
                     VALUES (:organization_id, :station_code, :rule_type, :rule_value, :rule_key, :free_days, :daily_rate,
                             TO_DATE(:valid_from, 'YYYY-MM-DD'), TO_DATE(:valid_to, 'YYYY-MM-DD'), :user_id, :user_id)",
                    $bindings
                );
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function date(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '9999-12-31') {
            throw new \InvalidArgumentException('Укажите корректную дату');
        }
        return $value;
    }
}
