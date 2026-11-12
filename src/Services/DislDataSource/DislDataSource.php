<?php
declare(strict_types=1);

namespace App\Services\DislDataSource;

/** Общие источники дополнительных данных для отчётов дислокации. */
final class DislDataSource
{
    private const SOURCES = [
        'pn' => [
            'sql' => "SELECT dr.railcar_id,
                             MAX(rai.car_type) AS pn_car_type,
                             SUM(NVL(dcai.weight_net, rai.weight_net)) AS pn_weight_net
                        FROM xx_etw.xx_disl_round dr
                        LEFT JOIN xx_etw.xx_disl_railcar_add_info rai
                          ON rai.round_id = dr.round_id AND rai.last_record = 'Y'
                        LEFT JOIN xx_etw.xx_disl_cont_history dch
                          ON dch.place_id = dr.round_id AND dch.place_type = 'railcar'
                        LEFT JOIN xx_etw.xx_disl_cont_round dcr
                          ON dcr.cont_round_id = dch.cont_round_id
                        LEFT JOIN xx_etw.xx_disl_cont_add_info dcai
                          ON dcai.cont_round_id = dcr.cont_round_id AND dcai.last_record = 'Y'
                       WHERE dr.station_hist_id_out IS NULL
                       GROUP BY dr.railcar_id",
            'key' => 'railcar_id',
            'fields' => ['pn_car_type', 'pn_weight_net'],
        ],
    ];

    public function build(string $baseSql, array $config): string
    {
        $baseAlias = (string) ($config['alias'] ?? 'd');
        $fields = [$baseAlias . '.*'];
        $joins = [];
        $aliases = [$baseAlias];

        foreach ((array) ($config['joins'] ?? []) as $join) {
            $sourceCode = (string) ($join['source'] ?? '');
            $source = self::SOURCES[$sourceCode] ?? throw new \InvalidArgumentException('Неизвестный источник дислокации: ' . $sourceCode);
            $alias = (string) ($join['alias'] ?? '');
            if ($alias === '' || in_array($alias, $aliases, true)) throw new \InvalidArgumentException('Повторяющийся SQL-алиас источника');
            $aliases[] = $alias;
            $type = strtoupper((string) ($join['type'] ?? 'LEFT'));
            if (!in_array($type, ['LEFT', 'INNER'], true)) throw new \InvalidArgumentException('Недопустимый тип JOIN');
            $allowed = array_fill_keys($source['fields'], true);
            $selected = $join['fields'] ?? array_fill_keys($source['fields'], null);
            foreach ((array) $selected as $field => $output) {
                if (is_int($field)) { $field = (string) $output; $output = null; }
                if (!isset($allowed[$field])) throw new \InvalidArgumentException('Недоступное поле источника: ' . $field);
                $fields[] = $alias . '.' . $field . ' AS ' . ($output ?: $field);
            }
            foreach ((array) ($config['computed'] ?? []) as $name => $expression) {
                if (!preg_match('/^[a-z][a-z0-9_]*$/i', (string) $name)) throw new \InvalidArgumentException('Недопустимый алиас вычисляемого поля');
                $fields[] = '(' . $expression . ') AS ' . $name;
            }
            $on = [];
            foreach ((array) ($join['on'] ?? []) as $left => $right) $on[] = $left . ' = ' . $right;
            if (!$on) throw new \InvalidArgumentException('Для источника не задано условие JOIN');
            $joins[] = $type . ' JOIN (' . $source['sql'] . ') ' . $alias . ' ON ' . implode(' AND ', $on);
        }

        return '(SELECT ' . implode(', ', $fields) . ' FROM (' . $baseSql . ') ' . $baseAlias . ' ' . implode(' ', $joins) . ')';
    }

    public function approachSource(string $whereSql): string
    {
        $baseSql = "SELECT d.* FROM xx_dislocation_rjd d WHERE $whereSql";
        return $this->build($baseSql, [
            'alias' => 'd',
            'joins' => [[
                'source' => 'pn', 'alias' => 'pn', 'type' => 'LEFT',
                'on' => ['d.wagon_no' => 'pn.railcar_id'],
                'fields' => ['pn_car_type', 'pn_weight_net'],
            ]],
            'computed' => [
                'pn_cargo_state' => "XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_APPROACH_CARGO_STATE(d.oper_station, d.wagon_no, d.wagon_type_code, d.cargo_weight_kg)",
            ],
        ]);
    }
}
