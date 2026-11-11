<?php
declare(strict_types=1);

namespace App\Reports;

final class KpiTypeRegistry
{
    private const HIDDEN_FIELDS = [
        'wagon_no',
        'wagon_state',
        'cargo_weight_kg',
        'oper_dt',
        'norm_delivery_dt',
    ];

    private const TYPES = [
        'dashboard_kpi' => [
            'page' => 'dashboard',
            'tab' => 'dislocation',
            'label' => 'Дашборд',
            'container_id' => 'kpiGrid',
            'user_creatable' => false,
            'reference_type' => null,
            'fields' => [],
        ],
        'approach_kpi' => [
            'page' => 'approach',
            'tab' => 'approach',
            'label' => 'Подход вагонов',
            'container_id' => 'approachMetrics',
            'user_creatable' => true,
            'reference_type' => 'Подход',
            'fields' => [
                'wagon_no' => 'string', 'depart_station' => 'string', 'dest_station' => 'string',
                'oper_station' => 'string', 'cargo_name' => 'string',
                'wagon_type_code' => 'string', 'wagon_state' => 'string',
                'train_index' => 'string', 'train_no' => 'string',
                'cargo_weight_kg' => 'number', 'oper_dt' => 'date',
                'norm_delivery_dt' => 'date',
            ],
        ],
        'departue_kpi' => [
            'page' => 'departure',
            'tab' => 'departure',
            'label' => 'Отправление вагонов',
            'container_id' => 'departureMetrics',
            'user_creatable' => true,
            'reference_type' => 'Отправка',
            'fields' => [
                'wagon_no' => 'string', 'depart_station' => 'string', 'dest_station' => 'string',
                'oper_station' => 'string', 'cargo_name' => 'string',
                'wagon_type_code' => 'string', 'wagon_state' => 'string',
                'train_index' => 'string', 'train_no' => 'string',
                'cargo_weight_kg' => 'number', 'oper_dt' => 'date',
            ],
        ],
        'loading_kpi' => [
            'page' => 'loading',
            'tab' => 'loading',
            'label' => 'Погрузка',
            'container_id' => 'loadingMetrics',
            'user_creatable' => false,
            'reference_type' => 'Отправка',
            'fields' => [],
        ],
    ];

    private const FIELD_LABELS = [
        'wagon_no' => 'Номер вагона',
        'depart_station' => 'Станция отправления',
        'dest_station' => 'Станция назначения',
        'oper_station' => 'Станция операции',
        'cargo_name' => 'Груз',
        'wagon_type_code' => 'Род вагона',
        'wagon_state' => 'Состояние вагона',
        'train_index' => 'Индекс поезда',
        'train_no' => 'Номер поезда',
        'cargo_weight_kg' => 'Вес груза, кг',
        'oper_dt' => 'Дата операции',
        'norm_delivery_dt' => 'Нормативная дата доставки',
    ];

    public static function all(): array
    {
        return self::TYPES;
    }

    public static function get(string $type): ?array
    {
        return self::TYPES[trim($type)] ?? null;
    }

    public static function page(string $type): ?string
    {
        return self::get($type)['page'] ?? null;
    }

    public static function isUserCreatable(string $type): bool
    {
        return (bool) (self::get($type)['user_creatable'] ?? false);
    }

    public static function referenceType(string $type): ?string
    {
        return self::get($type)['reference_type'] ?? null;
    }

    public static function pageLabel(string $page): ?string
    {
        foreach (self::TYPES as $definition) {
            if ($definition['page'] === trim($page)) {
                return $definition['label'];
            }
        }
        return null;
    }

    public static function userTypes(): array
    {
        return array_filter(
            self::TYPES,
            static fn(array $definition): bool => $definition['user_creatable']
        );
    }

    public static function fields(string $type, bool $includeHidden = false): array
    {
        $result = [];
        foreach ((array) (self::get($type)['fields'] ?? []) as $key => $kind) {
            if (!$includeHidden && in_array($key, self::HIDDEN_FIELDS, true)) {
                continue;
            }
            $result[] = ['key' => $key, 'label' => self::FIELD_LABELS[$key], 'type' => $kind];
        }
        return $result;
    }

    public static function fieldTypes(string $type): array
    {
        return (array) (self::get($type)['fields'] ?? []);
    }

    public static function clientConfig(): array
    {
        $result = [];
        foreach (self::TYPES as $type => $definition) {
            $result[$type] = [
                'page' => $definition['page'],
                'tab' => $definition['tab'],
                'label' => $definition['label'],
                'container_id' => $definition['container_id'],
                'user_creatable' => $definition['user_creatable'],
            ];
        }
        return $result;
    }
}
