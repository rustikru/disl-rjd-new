<?php
declare(strict_types=1);

namespace App\Reports;

final class KpiCondition
{
    private const HIDDEN_FIELDS = [
        'wagon_no',
        'wagon_state',
        'cargo_weight_kg',
        'oper_dt',
        'norm_delivery_dt',
    ];
    // Поля для конструктора условий KPI. Ключи массива - имена полей в БД, значения - типы данных.
    private const FIELDS = [
        // Подход
        'approach_kpi' => [
            'wagon_no' => 'string', 'depart_station' => 'string', 'dest_station' => 'string',
            'oper_station' => 'string', 'cargo_name' => 'string',
            'wagon_type_code' => 'string', 'wagon_state' => 'string',
            'train_index' => 'string', 'train_no' => 'string',
            'cargo_weight_kg' => 'number', 'oper_dt' => 'date',
            'norm_delivery_dt' => 'date',
        ],
        // Отправка 
        'departue_kpi' => [
            'wagon_no' => 'string', 'depart_station' => 'string', 'dest_station' => 'string',
            'oper_station' => 'string', 'cargo_name' => 'string',
            'wagon_type_code' => 'string', 'wagon_state' => 'string',
            'train_index' => 'string', 'train_no' => 'string',
            'cargo_weight_kg' => 'number', 'oper_dt' => 'date',
        
        ],
    ];

    public static function fields(string $type, bool $includeHidden = false): array
    {
        $labels = [
            'wagon_no' => 'Номер вагона', 'depart_station' => 'Станция отправления', 'dest_station' => 'Станция назначения',
            'oper_station' => 'Станция операции', 'cargo_name' => 'Груз',
            'wagon_type_code' => 'Род вагона', 'wagon_state' => 'Состояние вагона',
            'train_index' => 'Индекс поезда', 'train_no' => 'Номер поезда',
            'cargo_weight_kg' => 'Вес груза, кг', 'oper_dt' => 'Дата операции',
            'norm_delivery_dt' => 'Нормативная дата доставки', 
        ];
        $result = [];
        foreach (self::FIELDS[$type] ?? [] as $key => $kind) {
            if (!$includeHidden && in_array($key, self::HIDDEN_FIELDS, true)) {
                continue;
            }
            $result[] = ['key' => $key, 'label' => $labels[$key], 'type' => $kind];
        }
        return $result;
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

        $allowed = self::FIELDS[$type] ?? [];
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
            $column = strtoupper($field);
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
