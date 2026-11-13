<?php
declare(strict_types=1);

namespace App\Reports;

final class KpiCondition
{
    public static function fields(string $type, bool $includeHidden = false): array
    {
        return KpiTypeRegistry::fields($type, $includeHidden);
    }

    public static function compile(string $type, string $json): array
    {
        $config = json_decode($json, true);
        if (!is_array($config) || !in_array($config['operator'] ?? '', ['AND', 'OR'], true)) {
            throw new \InvalidArgumentException('Некорректная конфигурация условий.');
        }
        $conditions = $config['conditions'] ?? null;
        if (!is_array($conditions) || count($conditions) < 1 || count($conditions) > 10) {
            throw new \InvalidArgumentException('Добавьте от 1 до 10 условий.');
        }

        $allowed = KpiTypeRegistry::fieldTypes($type);
        $parts = [];
        $bindings = [];
        foreach ($conditions as $index => $condition) {
            if (!is_array($condition)) {
                throw new \InvalidArgumentException('Некорректное условие.');
            }
            $field = strtolower((string) ($condition['field'] ?? ''));
            $operator = strtoupper((string) ($condition['operator'] ?? ''));
            $value = $condition['value'] ?? null;
            if (!isset($allowed[$field])) {
                throw new \InvalidArgumentException('Выбрано недоступное поле.');
            }
            $operators = $allowed[$field] === 'string'
                ? ['=', '!=', 'LIKE', 'NOT LIKE']
                : ['=', '!=', '>', '<', '>=', '<='];
            if (!in_array($operator, $operators, true)) {
                throw new \InvalidArgumentException('Выбран недопустимый оператор для этого поля.');
            }
            if (!is_scalar($value) || (string) $value === '' || strlen((string) $value) > 800) {
                throw new \InvalidArgumentException('Укажите значение условия (не более 200 символов).');
            }
            if ($allowed[$field] === 'number' && !is_numeric($value)) {
                throw new \InvalidArgumentException('Для выбранного поля требуется число.');
            }
            if ($allowed[$field] === 'date') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                if (!$date || $date->format('Y-m-d') !== (string) $value) {
                    throw new \InvalidArgumentException('Дата должна быть в формате ГГГГ-ММ-ДД.');
                }
                $value = $date->format('Y-m-d');
            }
            $name = 'user_kpi_' . $index;
            $column = ($type === 'downtime_kpi' && $field === 'idle_time_days')
                ? 'STATION_IDLE_DAYS'
                : strtoupper($field);
            if (in_array($operator, ['LIKE', 'NOT LIKE'], true)) {
                // % и _ пользователь может использовать как шаблоны поиска.
                $value = '%' . str_replace('\\', '\\\\', (string) $value) . '%';
                $expression = "UPPER($column) $operator UPPER(:$name) ESCAPE '\\'";
            } elseif ($allowed[$field] === 'date') {
                $expression = "$column $operator TO_DATE(:$name, 'YYYY-MM-DD')";
            } else {
                $expression = "$column $operator :$name";
            }
            $parts[] = $expression;
            $bindings[$name] = $value;
        }
        return ['sql' => '(' . implode(' ' . $config['operator'] . ' ', $parts) . ')', 'params' => $bindings];
    }
}
