<?php
declare(strict_types=1);

namespace App\Services\DislDataSource;

use App\Database\QueryLogger;

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

    public function build(string $baseSql, array $config, array &$bindings = []): string
    {
        $baseAlias = (string) ($config['alias'] ?? 'd');
        $this->assertIdentifier($baseAlias, 'основного источника');
        $fields = [$baseAlias . '.*'];
        $outputFields = [];
        $joins = [];
        $aliases = [$baseAlias];
        $sourceFields = [$baseAlias => null];

        foreach ((array) ($config['joins'] ?? []) as $join) {
            $sourceCode = (string) ($join['source'] ?? '');
            $source = self::SOURCES[$sourceCode] ?? throw new \InvalidArgumentException('Неизвестный источник дислокации: ' . $sourceCode);
            $alias = (string) ($join['alias'] ?? '');
            $this->assertIdentifier($alias, 'источника');
            if (in_array($alias, $aliases, true)) throw new \InvalidArgumentException('Повторяющийся SQL-алиас источника');
            $aliases[] = $alias;
            $sourceFields[$alias] = array_fill_keys(array_merge([$source['key']], $source['fields']), true);
            $type = strtoupper((string) ($join['type'] ?? 'LEFT'));
            if (!in_array($type, ['LEFT', 'INNER'], true)) throw new \InvalidArgumentException('Недопустимый тип JOIN');
            $this->assertIdentifier((string) $source['key'], 'ключа источника');
            $allowed = array_fill_keys(array_merge([$source['key']], $source['fields']), true);
            $exclude = array_fill_keys(array_map('strval', (array) ($join['exclude'] ?? [])), true);
            $selected = $join['fields'] ?? array_fill_keys($source['fields'], null);
            foreach ((array) $selected as $field => $output) {
                if (is_int($field)) { $field = (string) $output; $output = null; }
                if (isset($exclude[$field])) continue;
                if (!isset($allowed[$field])) throw new \InvalidArgumentException('Недоступное поле источника: ' . $field);
                $output = (string) ($output ?: $field);
                $this->assertIdentifier($output, 'выходного поля');
                if (isset($outputFields[$output])) throw new \InvalidArgumentException('Повторяющееся выходное поле: ' . $output);
                $outputFields[$output] = true;
                $fields[] = $alias . '.' . $field . ' AS ' . $output;
            }
            $on = [];
            foreach ((array) ($join['on'] ?? []) as $left => $right) {
                $this->assertColumnReference((string) $left, $aliases, $sourceFields);
                $this->assertColumnReference((string) $right, $aliases, $sourceFields);
                $on[] = $left . ' = ' . $right;
            }
            if (!$on) throw new \InvalidArgumentException('Для источника не задано условие JOIN');
            $sourceSql = '(' . $source['sql'] . ')';
            $where = array_values(array_filter(array_map('strval', (array) ($join['where'] ?? []))));
            foreach ((array) ($join['whereBindings'] ?? []) as $name => $value) {
                $this->assertBindingName((string) $name);
                if (array_key_exists($name, $bindings)) throw new \InvalidArgumentException('Повторяющийся binding: ' . $name);
                $bindings[$name] = $value;
            }
            foreach ($where as $condition) {
                $check = str_replace('{{base_sql}}', 'BASE_SQL', $condition);
                if (str_contains($check, ';') || str_contains($check, '--') || str_contains($check, '/*')) throw new \InvalidArgumentException('Недопустимое условие источника');
            }
            if ($where) {
                $conditions = str_replace('{{base_sql}}', $baseSql, $where);
                $sourceSql = '(SELECT * FROM ' . $sourceSql . ' source_row WHERE ' . implode(' AND ', $conditions) . ')';
            }
            $joins[] = $type . ' JOIN ' . $sourceSql . ' ' . $alias . ' ON ' . implode(' AND ', $on);
        }

        foreach ((array) ($config['computed'] ?? []) as $name => $expression) {
            $this->assertIdentifier((string) $name, 'вычисляемого поля');
            if (!is_string($expression) || trim($expression) === '' || !preg_match('/^[\p{L}0-9_ .(),:+*\/%\'"=<>-]+$/u', $expression)) {
                if (QueryLogger::isEnabled()) {
                    QueryLogger::log(
                        'Oracle',
                        'build',
                        $baseSql,
                        $bindings,
                        0.0,
                        ['code' => 0, 'message' => 'Недопустимое вычисляемое выражение ' . (string) $name]
                    );
                }
                throw new \InvalidArgumentException('Недопустимое вычисляемое выражение');
            }
            if (isset($outputFields[$name])) throw new \InvalidArgumentException('Повторяющееся выходное поле: ' . $name);
            $outputFields[$name] = true;
            $fields[] = '(' . $expression . ') AS ' . $name;
        }

        return '(SELECT ' . implode(', ', $fields) . ' FROM (' . $baseSql . ') ' . $baseAlias . ' ' . implode(' ', $joins) . ')';
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value)) throw new \InvalidArgumentException('Недопустимый ' . $label);
    }

    private function assertColumnReference(string $value, array $aliases, array $sourceFields): void
    {
        if (!preg_match('/^([A-Za-z][A-Za-z0-9_]*)\.([A-Za-z][A-Za-z0-9_]*)$/', $value, $match)) throw new \InvalidArgumentException('Недопустимая ссылка в JOIN');
        if (!in_array($match[1], $aliases, true)) throw new \InvalidArgumentException('Неизвестный алиас в JOIN: ' . $match[1]);
        if ($sourceFields[$match[1]] !== null && !isset($sourceFields[$match[1]][$match[2]])) {
            throw new \InvalidArgumentException('Неизвестное поле в JOIN: ' . $value);
        }
    }

    private function assertBindingName(string $value): void
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value)) throw new \InvalidArgumentException('Недопустимое имя binding');
    }
    // Страница "Подход", метод для построения SQL-запроса
    public function approachSource(string $whereSql, array &$bindings = []): string
    {
        $baseSql = "SELECT d.* FROM xx_dislocation_rjd d WHERE $whereSql";
        return $this->build($baseSql, [
            'alias' => 'd',
            // Соединяем с источником данных по вагонным данным ПН, чтобы получить тип вагона и вес груза.
            'joins' => [[
                'source' => 'pn', 'alias' => 'pn', 'type' => 'LEFT',
                'on' => ['d.wagon_no' => 'pn.railcar_id'],
                'fields' => ['pn_car_type', 'pn_weight_net'],
                'where' => [
                    "source_row.railcar_id IN (SELECT wagon_no FROM ({{base_sql}}) report_rows WHERE UPPER(NVL(oper_station, :pn_station_marker)) LIKE :pn_station_pattern)",
                ],
                'whereBindings' => [
                    'pn_station_marker' => ' ',
                    'pn_station_pattern' => '%УГЛЕУРАЛ%',
                ],
            ]],
            // Вычисляемое поле, которое определяет состояние груза в подходе.
            'computed' => [
                'pn_cargo_state' => "CASE WHEN UPPER(NVL(d.oper_station, ' ')) LIKE '%УГЛЕУРАЛ%' THEN CASE WHEN pn.pn_weight_net IS NULL THEN 'неопр.' ELSE XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_CARGO_STATE(pn.pn_car_type, pn.pn_weight_net) END ELSE XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_CARGO_STATE(d.wagon_type_code, d.cargo_weight_kg) END",
            ],
        ], $bindings);
    }
}
