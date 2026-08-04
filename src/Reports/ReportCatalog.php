<?php
declare(strict_types=1);

namespace App\Reports;

final class ReportCatalog
{
    public static function all(): array
    {
        $reports = [
            'dislocation' => self::report(
                'Дислокация',
                ['SUMMARY', 'DETAIL'],
                '/api/dislocation/filters',
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fDislocationWagonNo'),
                    self::filter('cargo', 'Груз', 'select', '#fDislocationCargo', 'cargo'),
                ]
            ),
            'approach' => self::report(
                'Подход вагонов',
                ['SUMMARY', 'DETAIL'],
                '/api/approach/filters',
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fApproachWagonNo'),
                    self::filter('cargo', 'Груз', 'select', '#fApproachCargo', 'cargo'),
                ]
            ),
            'departure' => self::report(
                'Отправление вагонов',
                ['SUMMARY', 'DETAIL'],
                '/api/departure/filters',
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fDepartureWagonNo'),
                    self::filter('cargo', 'Груз', 'select', '#fDepartureCargo', 'cargo'),
                    self::filter('dest_station', 'Станция назначения', 'select', '#fDestStation', 'dest_station'),
                ]
            ),
            'loading' => self::report(
                'Погрузка',
                ['SUMMARY', 'DETAIL'],
                '/api/loading/filters',
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fLoadingWagonNo'),
                    self::filter('cargo', 'Груз', 'select', '#fLoadingCargo', 'cargo'),
                ]
            ),
            'downtime' => self::report(
                'Простои',
                ['SUMMARY', 'DETAIL'],
                '/api/downtime/filters',
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fDowntimeWagonNo'),
                    self::filter('dest_station', 'Станция назначения', 'select', '#fDowntimeDestStation', 'dest_station'),
                ]
            ),
            'raw-material' => self::report(
                'Сырьё',
                ['SUMMARY', 'DETAIL'],
                null,
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fRawWagonNo'),
                ]
            ),
            'analysis-period' => self::report(
                'Анализ данных за период',
                ['DETAIL'],
                '/api/analysis/filters',
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fAnalysisPeriodWagonNo'),
                    self::filter('date_from', 'Дата операции с', 'date', '#fAnalysisPeriodDateFrom', null, true),
                    self::filter('date_to', 'Дата операции по', 'date', '#fAnalysisPeriodDateTo'),
                    self::filter('cargo', 'Груз', 'select', '#fAnalysisPeriodCargo', 'cargo'),
                ]
            ),
            'downtime-control' => self::report(
                'Контроль простоев',
                ['DETAIL'],
                null,
                [
                    self::filter('wagon_no', 'Номер вагона', 'text', '#fDowntimeControlWagonNo'),
                    self::filter('date_from', 'Период с', 'date', '#fDowntimeControlDateFrom'),
                    self::filter('date_to', 'Период по', 'date', '#fDowntimeControlDateTo'),
                ],
                false
            ),
        ];

        foreach ($reports as &$report) {
            $report['columns'] = self::defaultColumns();
        }
        unset($report);
        $reports['dislocation']['group_cols'] = [
            ['key' => 'dest_state', 'label' => 'Страна назначения'],
            ['key' => 'dest_road', 'label' => 'Дорога назначения'],
            ['key' => 'dest_station', 'label' => 'Станция назначения'],
        ];
        $reports['dislocation']['col_dims'] = ['wagon_type_code', 'cargo_w_type'];
        $reports['approach']['group_cols'] = [
            ['key' => 'oper_road', 'label' => 'Дорога операции'],
            ['key' => 'oper_station', 'label' => 'Станция операции'],
        ];
        $reports['approach']['col_dims'] = ['wagon_type_code', 'cargo_w_type'];
        $reports['departure']['group_cols'] = [
            ['key' => 'dest_road', 'label' => 'Дорога назначения'],
            ['key' => 'dest_station', 'label' => 'Станция назначения'],
        ];
        $reports['departure']['col_dims'] = ['wagon_type_code'];
        $reports['loading']['group_cols'] = [
            ['key' => 'depart_road', 'label' => 'Дорога'],
            ['key' => 'depart_station', 'label' => 'Станция'],
        ];
        $reports['loading']['col_dims'] = ['wagon_type_code'];
        $reports['downtime']['group_cols'] = [
            ['key' => 'cargo_name', 'label' => 'Груз'],
            ['key' => 'idle_time_name', 'label' => 'Простой'],
        ];
        $reports['downtime']['col_dims'] = ['m_wagon_type_code', 'm_wag_state'];
        $reports['raw-material']['group_cols'] = [
            ['key' => 'cargo_name', 'label' => 'Груз'],
        ];
        $reports['raw-material']['col_dims'] = ['wagon_type_code'];
        $reports['downtime-control']['columns'] = [
            ['key' => 'car_number', 'label' => '№ вагона'],
            ['key' => 'start_date', 'label' => 'Начало простоя'],
            ['key' => 'end_date', 'label' => 'Окончание простоя'],
            ['key' => 'idle_reasons_name', 'label' => 'Причина простоя'],
            ['key' => 'note', 'label' => 'Примечание'],
            ['key' => 'is_excluded', 'label' => 'Исключён'],
            ['key' => 'created_name', 'label' => 'Создал'],
        ];
        return $reports;
    }

    public static function find(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    public static function cleanFilters(string $reportCode, array $values): array
    {
        $report = self::find($reportCode);
        if (!$report) {
            return [];
        }

        $result = [];
        foreach ($report['filters'] as $filter) {
            $name = $filter['name'];
            if (!array_key_exists($name, $values)) {
                continue;
            }
            if (!empty($filter['multiple'])) {
                $items = is_array($values[$name]) ? $values[$name] : [$values[$name]];
                $items = array_values(array_unique(array_filter(array_map(
                    static fn($value): string => is_scalar($value) ? mb_substr(trim((string) $value), 0, 1000) : '',
                    $items
                ), static fn(string $value): bool => $value !== '')));
                if ($items !== []) {
                    $result[$name] = array_slice($items, 0, 100);
                }
                continue;
            }
            $value = is_scalar($values[$name]) ? trim((string) $values[$name]) : '';
            if ($value !== '') {
                $result[$name] = mb_substr($value, 0, 1000);
            }
        }
        return $result;
    }

    public static function missingRequiredFilters(string $reportCode, array $values): array
    {
        $report = self::find($reportCode);
        if (!$report) {
            return [];
        }

        $missing = [];
        foreach ($report['filters'] as $filter) {
            $value = $values[$filter['name']] ?? '';
            $empty = is_array($value) ? count(array_filter($value, static fn($item): bool => trim((string) $item) !== '')) === 0 : trim((string) $value) === '';
            if (!empty($filter['required']) && $empty) {
                $missing[] = $filter['label'];
            }
        }
        return $missing;
    }

    private static function report(
        string $name,
        array $views,
        ?string $optionsUrl,
        array $filters,
        bool $organizationRequired = true
    ): array {
        return [
            'name' => $name,
            'views' => $views,
            'default_view' => $views[0],
            'options_url' => $optionsUrl,
            'organization_required' => $organizationRequired,
            'filters' => $filters,
            'group_cols' => [],
            'col_dims' => [],
        ];
    }

    private static function filter(
        string $name,
        string $label,
        string $type,
        ?string $selector = null,
        ?string $optionsKey = null,
        bool $required = false
    ): array {
        return [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'selector' => $selector,
            'options_key' => $optionsKey,
            'required' => $required,
            'multiple' => $type === 'select',
            'show' => ['page', 'mailing'],
        ];
    }

    private static function defaultColumns(): array
    {
        return [
            ['key' => 'wagon_no', 'label' => '№ вагона'],
            ['key' => 'wagon_type_code', 'label' => 'Тип вагона'],
            ['key' => 'cargo_name', 'label' => 'Груз'],
            ['key' => 'cargo_weight_kg', 'label' => 'Вес, кг'],
            ['key' => 'oper_station', 'label' => 'Текущая станция'],
            ['key' => 'oper_road', 'label' => 'Дорога операции'],
            ['key' => 'oper_mnemonic', 'label' => 'Операция'],
            ['key' => 'oper_dt', 'label' => 'Дата операции'],
            ['key' => 'depart_station', 'label' => 'Станция отправления'],
            ['key' => 'dest_station', 'label' => 'Станция назначения'],
            ['key' => 'consignor_name', 'label' => 'Грузоотправитель'],
            ['key' => 'consignee_name', 'label' => 'Грузополучатель'],
            ['key' => 'idle_time_days', 'label' => 'Простой, суток'],
            ['key' => 'norm_delivery_dt', 'label' => 'Срок доставки'],
        ];
    }
}
