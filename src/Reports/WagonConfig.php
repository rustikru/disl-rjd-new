<?php
declare(strict_types=1);

namespace App\Reports;

/** Единая структура карточки вагона. */
final class WagonConfig
{
    private const TABS = [
        ['key' => 'overview', 'label' => 'Обзор', 'section' => 'overview'],
        ['key' => 'history', 'label' => 'Движение', 'history' => true],
        ['key' => 'movement', 'label' => 'Маршрут и операция', 'section' => 'movement'],
        ['key' => 'cargo', 'label' => 'Груз и документы', 'section' => 'cargo'],
        ['key' => 'technical', 'label' => 'Техническое состояние', 'section' => 'technical'],
    ];

    private const SECTIONS = [
        'overview' => [
            'title' => 'Основная информация',
            'fields' => [
                'wagon_type_code', 'wagon_state', 'park_type', 'owner', 'lessee',
                'lease_home_station', 'cargo_name', 'cargo_weight_kg', 'waybill_no',
                'oper_dt', 'train_no', 'next_repair_dt',
            ],
        ],
        'movement' => [
            'title' => 'Маршрут и последняя операция',
            'fields' => [
                'depart_station', 'dest_station', 'oper_station', 'oper_road',
                'operation', 'oper_dt', 'train_no', 'train_index',
                'trip_start_dt', 'trip_end_dt',
            ],
        ],
        'cargo' => [
            'title' => 'Груз и документы',
            'fields' => [
                'cargo_name', 'cargo_weight_kg', 'prev_cargo', 'waybill_no',
                'consignor_name', 'consignee_name',
            ],
        ],
        'technical' => [
            'title' => 'Технические данные',
            'fields' => [
                'last_dep_repair_dt', 'last_dep_repair_depot',
                'last_cap_repair_dt', 'next_repair_dt', 'next_repair_type',
                'home_road',
            ],
        ],
    ];

    private const HISTORY = [
        'oper_dt', 'operation', 'oper_station', 'oper_road', 'train_no', 'waybill_no',
    ];

    private const ROUTE = [
        ['label' => 'Отправление', 'station' => 'depart_station', 'road' => 'depart_road'],
        ['label' => 'Назначение', 'station' => 'dest_station', 'road' => 'dest_road'],
    ];

    private const HEADER = [
        'number' => 'wagon_no',
        'type' => 'wagon_type_code',
        'owner' => 'owner',
    ];

    private const PROGRESS = ['dist_passed_km', 'dist_remain_km'];

    private const DATE_ONLY = [
        'last_dep_repair_dt', 'last_cap_repair_dt', 'next_repair_dt',
    ];

    private const EXTRA_FIELDS = ['report_dt', 'type_reference'];

    public static function all(): array
    {
        $labels = self::labels();
        $sections = [];
        foreach (self::SECTIONS as $key => $section) {
            $sections[$key] = [
                'title' => $section['title'],
                'fields' => self::fieldsWithLabels($section['fields'], $labels),
            ];
        }

        return [
            'tabs' => self::TABS,
            'sections' => $sections,
            'route' => self::ROUTE,
            'header' => [
                'number' => ['key' => self::HEADER['number'], 'prefix' => 'Вагон № '],
                'type' => ['key' => self::HEADER['type'], 'empty' => 'Тип не указан'],
                'owner' => [
                    'key' => self::HEADER['owner'],
                    'label' => $labels[self::HEADER['owner']],
                    'empty' => 'Не указан',
                ],
            ],
            'progress' => self::fieldsWithLabels(self::PROGRESS, $labels),
            'date_only' => self::DATE_ONLY,
            'history' => self::fieldsWithLabels(self::HISTORY, $labels),
            'fields' => self::fields(),
        ];
    }

    public static function fields(): array
    {
        $fields = self::EXTRA_FIELDS;
        foreach (self::SECTIONS as $section)
            $fields = array_merge($fields, $section['fields']);
        foreach (self::ROUTE as $point)
            $fields = array_merge($fields, [$point['station'], $point['road']]);
        $fields = array_merge($fields, array_values(self::HEADER), self::PROGRESS);
        return array_values(array_unique($fields));
    }

    public static function historyFields(): array
    {
        return self::HISTORY;
    }

    private static function labels(): array
    {
        $labels = [];
        foreach (ReportConfig::sourceColumns() as $column)
            $labels[(string) $column['key']] = (string) $column['label'];
        return $labels;
    }

    private static function fieldsWithLabels(array $fields, array $labels): array
    {
        return array_map(
            static fn(string $key): array => ['key' => $key, 'label' => $labels[$key] ?? $key],
            $fields
        );
    }
}
