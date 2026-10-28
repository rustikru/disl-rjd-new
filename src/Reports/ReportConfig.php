<?php
declare(strict_types=1);

namespace App\Reports;

final class ReportConfig
{
    public static function all(): array
    {
        // Здесь собраны настройки всех отчётов. Обычная страница, экспорт и в будущем
        // рассылка берут параметры отсюда, поэтому одинаковые настройки в других местах
        // добавлять не нужно.
        //
        // Для отчёта указываются название, адреса API, фильтры, группировка строк
        // и разбивка колонок. У фильтра задаются его ключ, подпись, тип поля, id поля
        // на странице и ключ списка значений, если это выпадающий список.
        //
        // Если появился новый фильтр или поменялся старый — править нужно здесь.
        // contexts определяет, где настройка доступна: page, mailing или export.
        $reports = [
            // Дислокация
            'dislocation' => self::report(
                'Общая дислокация',
                '/api/dislocation/filters',
                '/api/dislocation/summary',
                '/api/dislocation/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fDislocationWagonNo'),
                    self::filter('cargo', 'Груз', 'select', 'fDislocationCargo', 'cargo'),
                ],
                [
                    self::dimension('dest_state', 'Страна назначения'),
                    self::dimension('dest_road', 'Дорога назначения'),
                    self::dimension('dest_station', 'Станция назначения'),
                ],
                [
                    self::columnDimension('wagon_type_code', 'wagon_type'),
                    self::columnDimension('cargo_w_type', 'cargo_state'),
                ],
                true,
                true,
                false
            ),

            // Подход вагонов
            'approach' => self::report(
                'Подход вагонов',
                '/api/approach/filters',
                '/api/approach/summary',
                '/api/approach/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fApproachWagonNo'),
                    self::filter('cargo', 'Груз', 'select', 'fApproachCargo', 'cargo'),
                    self::filter('prev_cargo', 'Ранее выгружен', 'select', 'fApproachPrevCargo', 'prev_cargo', ['page']),
                ],
                [
                    self::dimension('oper_road', 'Дорога операции'),
                    self::dimension('oper_station', 'Станция операции'),
                ],
                [
                    self::columnDimension('wagon_type_code', 'wagon_type'),
                    self::columnDimension('cargo_w_type', 'cargo_state'),
                ]
            ),

            // Отправление вагонов
            'departure' => self::report(
                'Отправление вагонов',
                '/api/departure/filters',
                '/api/departure/summary',
                '/api/departure/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fDepartureWagonNo'),
                    self::filter('cargo', 'Груз', 'select', 'fDepartureCargo', 'cargo'),
                    self::filter('dest_station', 'Станция назначения', 'select', 'fDestStation', 'dest_station'),
                ],
                [
                    self::dimension('dest_road', 'Дорога назначения'),
                    self::dimension('dest_station', 'Станция назначения'),
                ],
                [
                    self::columnDimension('wagon_type_code', 'wagon_type'),
                    self::columnDimension('cargo_w_type', 'cargo_state'),
                ]
            ),

            // Погрузка
            'loading' => self::report(
                'Погрузка',
                '/api/loading/filters',
                '/api/loading/summary',
                '/api/loading/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fLoadingWagonNo'),
                    self::filter('cargo', 'Груз', 'select', 'fLoadingCargo', 'cargo'),
                ],
                [
                    self::dimension('dest_road', 'Дорога назначения'),
                    self::dimension('dest_station', 'Станция назначения'),
                ],
                [
                    self::columnDimension('wagon_type_code', 'wagon_type'),
                    self::columnDimension('cargo_w_type', 'cargo_state'),
                ]
            ),

            // Простои
            'downtime' => self::report(
                'Простои',
                '/api/downtime/filters',
                '/api/downtime/summary',
                '/api/downtime/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fDowntimeWagonNo'),
                    self::filter('dest_station', 'Станция назначения', 'select', 'fDowntimeDestStation', 'dest_station'),
                ],
                [
                    self::dimension('cargo_name', 'Груз'),
                    self::dimension('idle_time_name', 'Простой'),
                ],
                [
                    self::columnDimension('m_wagon_type_code', 'wagon_type'),
                    self::columnDimension('m_wag_state', 'cargo_state'),
                ]
            ),

            // Техническое состояние
            'technical-state' => self::report(
                'Техническое состояние',
                '/api/technical-state/filters',
                null,
                '/api/technical-state/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fTechnicalWagonNo'),
                    self::filter('wagon_type', 'Род вагона', 'select', 'fTechnicalWagonType', 'wagon_type'),
                ],
                [],
                [],
                true,
                true,
                false
            ),

            // Претензии
            'claims' => self::report(
                'Претензии',
                '/api/claims/filters',
                '/api/claims/summary',
                '/api/claims/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fClaimsWagonNo'),
                    self::filter('station_code', 'Станция', 'select', 'fClaimsStation', 'stations'),
                    self::filter('wagon_type', 'Род вагона', 'select', 'fClaimsWagonType', 'wagon_type'),
                ]
            ),

            // Сырьё
            'raw-material' => self::report(
                'Сырьё',
                null,
                '/api/raw-material/summary',
                '/api/raw-material/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fRawWagonNo'),
                ],
                [
                    self::dimension('raw_material_idle_label', 'Простой'),
                    self::dimension('cargo_name', 'Груз'),
                ],
                [self::columnDimension('wagon_type_code', 'wagon_type')]
            ),

            // Анализ данных за период
            'analysis-period' => self::report(
                'Анализ данных за период',
                '/api/analysis/filters',
                null,
                '/api/analysis/period/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fAnalysisPeriodWagonNo'),
                    self::filter('cargo', 'Груз', 'select', 'fAnalysisPeriodCargo', 'cargo'),
                    self::filter('date_from', 'Дата операции с', 'date', 'fAnalysisPeriodDateFrom', null, ['page', 'mailing'], true, [], 'month_start'),
                    self::filter('date_to', 'Дата операции по', 'date', 'fAnalysisPeriodDateTo', null, ['page', 'mailing'], false, [], 'today'),
                ],
                [],
                [],
                true,
                true,
                false
            ),

            // Контроль простоев
            'downtime-control' => self::report(
                'Контроль простоев',
                null,
                null,
                '/api/downtime-control/detail',
                [
                    self::filter('wagon_no', '№ вагона', 'text', 'fDowntimeControlWagonNo'),
                    self::filter('date_from', 'Дата действия с', 'date', 'fDowntimeControlDateFrom', null, ['page', 'mailing'], false, [], 'month_start'),
                    self::filter('date_to', 'Дата действия по', 'date', 'fDowntimeControlDateTo', null, ['page', 'mailing'], false, [], 'today'),
                ],
                [],
                [],
                false,
                false
            ),

            // Полные строки последней загруженной справки без условий экранных отчётов.
            'source-approach' => self::mailingReport('Справка РЖД - Подход'),
            'source-departure' => self::mailingReport('Справка РЖД - Отправка'),
        ];

        $detailSettings = self::detailSettings();
        $pageSettings = self::pageSettings();
        $navigationSettings = self::navigationSettings();
        $mailingHandlers = self::mailingHandlers();
        foreach ($reports as $reportCode => &$reportDefinition) {
            $reportDefinition['page'] = $pageSettings[$reportCode] ?? [];
            $reportDefinition['navigation'] = $navigationSettings[$reportCode] ?? null;
            $reportDefinition['mailing_handler'] = $mailingHandlers[$reportCode] ?? null;
            if (!isset($detailSettings[$reportCode])) {
                continue;
            }
            $reportDefinition['detail_tabs'] = self::detailTabs();
            $reportDefinition['detail_label'] = $detailSettings[$reportCode]['label'];
            $reportDefinition['detail_sort'] = $detailSettings[$reportCode]['sort'];
            $reportDefinition['detail_columns'] = $detailSettings[$reportCode]['columns'];
        }
        unset($reportDefinition);

        return $reports;
    }

    private static function mailingHandlers(): array
    {
        // Метод уже существующего API, который формирует строки отчёта для рассылки.
        return [
            'dislocation' => 'dislDetail',
            'approach' => 'approachDetail',
            'departure' => 'departureDetail',
            'loading' => 'loadingDetail',
            'downtime' => 'downtimeDetail',
            'technical-state' => 'technicalDetail',
            'claims' => 'claimsDetail',
            'raw-material' => 'rawDetail',
            'analysis-period' => 'analysisPeriod',
            'source-approach' => 'sourceApproach',
            'source-departure' => 'sourceDeparture',
        ];
    }

    private static function navigationSettings(): array
    {
        // определяется раздел меню и порядок отчётов внутри него.
        return [
            'dislocation' => ['group' => 'movement', 'order' => 10],
            'approach' => ['group' => 'movement', 'order' => 20],
            'departure' => ['group' => 'movement', 'order' => 30],
            'loading' => ['group' => 'movement', 'order' => 40],
            'technical-state' => ['group' => 'analytics', 'order' => 10],
            'analysis-period' => ['group' => 'analytics', 'order' => 20],
            'downtime' => ['group' => 'downtime', 'order' => 10],
            'raw-material' => ['group' => 'downtime', 'order' => 20],
            'claims' => ['group' => 'downtime', 'order' => 30],
            'downtime-control' => ['group' => 'registry', 'order' => 10],
        ];
    }

    private static function pageSettings(): array
    {
        return [
            'dislocation' => [
                'summary_file' => 'дислокация',
                'detail_file' => 'дислокация-расширенная',
                'total_text' => 'Общий итог',
                'summary_table_id' => 'mainTable',
                'summary_subtitle_id' => 'mainTableSub',
                'summary_subtitle' => 'Итого по дислокации',
                'detail_table_id' => 'dislExtTable',
                'detail_subtitle_id' => 'dislDetSub',
                'detail_panel_id' => 'disl-extended',
                'summary_loaded_key' => '_dislLoaded',
                'detail_loaded_key' => '_extLoaded',
                'apply_button_id' => 'btnDislocationApply',
                'total_column_dimensions' => ['wagon_type_code'],
            ],
            'approach' => [
                'summary_file' => 'подход',
                'detail_file' => 'подход-расширенная',
                'total_text' => 'Общий итог',
                'summary_table_id' => 'approachSumTable',
                'summary_subtitle_id' => 'approachSumSub',
                'summary_subtitle' => 'Всего в подходе',
                'detail_table_id' => 'approachDetTable',
                'detail_subtitle_id' => 'approachDetSub',
                'detail_panel_id' => 'approach-detail',
                'summary_loaded_key' => '_approachLoaded',
                'detail_loaded_key' => '_approachDetLoaded',
                'total_column_dimensions' => ['wagon_type_code'],
                'organization_overrides' => [
                    'MTF' => ['pinned_row_label' => 'ст. Углеуральская', 'pinned_station_key' => 'УГЛЕУР', 'first_road_key' => 'СВЕРДЛ'],
                ],
            ],
            'departure' => [
                'summary_file' => 'отправление',
                'detail_file' => 'отправление-расширенная',
                'total_text' => 'Всего отправлено',
                'summary_table_id' => 'departureSumTable',
                'summary_subtitle_id' => 'departureSumSub',
                'summary_subtitle' => 'Всего',
                'detail_table_id' => 'departureDetTable',
                'detail_subtitle_id' => 'departureDetSub',
                'detail_panel_id' => 'departure-detail',
                'summary_loaded_key' => '_departureLoaded',
                'detail_loaded_key' => '_departureDetLoaded',
                'total_column_dimensions' => ['wagon_type_code'],
                'organization_overrides' => ['MTF' => ['total_text' => 'Всего отправлено со ст. Углеуральская']],
            ],
            'loading' => [
                'summary_file' => 'погрузка',
                'detail_file' => 'погрузка-расширенная',
                'total_text' => 'Всего погружено',
                'summary_table_id' => 'loadingSumTable',
                'summary_subtitle_id' => 'loadingSumSub',
                'summary_subtitle' => 'Всего',
                'detail_table_id' => 'loadingDetTable',
                'detail_subtitle_id' => 'loadingDetSub',
                'detail_panel_id' => 'loading-detail',
                'summary_loaded_key' => '_loadingLoaded',
                'detail_loaded_key' => '_loadingDetLoaded',
                'total_column_dimensions' => ['wagon_type_code'],
            ],
            'downtime' => [
                'summary_file' => 'простои',
                'detail_file' => 'простои-расширенная',
                'total_text' => 'Вагонов с простоем',
                'summary_table_id' => 'downtimeSumTable',
                'summary_subtitle_id' => 'downtimeSumSub',
                'summary_subtitle' => 'Вагонов с простоем',
                'detail_table_id' => 'downtimeDetTable',
                'detail_subtitle_id' => 'downtimeDetSub',
                'detail_panel_id' => 'downtime-detail',
                'summary_loaded_key' => '_downtimeLoaded',
                'detail_loaded_key' => '_downtimeDetLoaded',
                'apply_button_id' => 'btnDowntimeApply',
                'total_column_dimensions' => ['m_wagon_type_code'],
            ],
            'downtime-control' => [
                'detail_file' => 'контроль-простоев',
                'detail_table_id' => 'downtimeControlTable',
                'detail_subtitle_id' => 'idleControlSub',
                'detail_panel_id' => 'panel-downtime-control',
                'summary_loaded_key' => '_downtimeControlLoaded',
                'detail_loaded_key' => '_downtimeControlDetLoaded',
                'apply_button_id' => 'btnDowntimeControlApply',
                'reset_button_id' => 'btnDowntimeControlReset',
            ],
            'technical-state' => [
                'detail_file' => 'техническое-состояние',
                'detail_table_id' => 'technicalDetTable',
                'detail_subtitle_id' => 'technicalDetSub',
                'detail_panel_id' => 'panel-technical-state',
                'summary_loaded_key' => '_technicalLoaded',
                'detail_loaded_key' => '_technicalDetLoaded',
                'apply_button_id' => 'btnTechnicalApply',
                'reset_button_id' => 'btnTechnicalReset',
                'page_behavior' => 'technical_state',
            ],
            'claims' => [
                'detail_file' => 'претензии-подробная',
                'metrics_id' => 'claimsMetrics',
                'summary_table_id' => 'claimsSumTable',
                'summary_subtitle_id' => 'claimsSumSub',
                'detail_table_id' => 'claimsDetTable',
                'detail_subtitle_id' => 'claimsDetSub',
                'detail_panel_id' => 'claims-detail',
                'summary_loaded_key' => '_claimsLoaded',
                'detail_loaded_key' => '_claimsDetLoaded',
                'apply_button_id' => 'btnClaimsApply',
                'reset_button_id' => 'btnClaimsReset',
                'page_behavior' => 'claims',
                'summary_columns' => [
                    ['key' => 'wagon_type_code', 'label' => 'Род вагона'],
                    ['key' => 'total', 'label' => 'Вагонов, шт.'],
                    ['key' => 'penalty_count', 'label' => 'Со штрафом, шт.'],
                    ['key' => 'payable_days', 'label' => 'Платно, сут.'],
                    ['key' => 'amount', 'label' => 'Сумма, ₽'],
                ],
            ],
            'raw-material' => [
                'summary_file' => 'сырьё',
                'detail_file' => 'сырьё-расширенная',
                'total_text' => 'Вагонов',
                'summary_table_id' => 'rawSumTable',
                'summary_subtitle_id' => 'rawSumSub',
                'summary_subtitle' => 'Вагонов',
                'detail_table_id' => 'rawDetTable',
                'detail_subtitle_id' => 'rawDetSub',
                'detail_panel_id' => 'raw-detail',
                'summary_loaded_key' => '_rawLoaded',
                'detail_loaded_key' => '_rawDetLoaded',
                'apply_button_id' => 'btnRawApply',
                'reset_button_id' => 'btnRawReset',
            ],
            'analysis-period' => [
                'detail_file' => 'анализ-за-период',
                'detail_table_id' => 'analysisPeriodDetTable',
                'detail_subtitle_id' => 'analysisPeriodDetSub',
                'detail_panel_id' => 'analysisPeriod-detail',
                'summary_loaded_key' => '_analysisPeriodLoaded',
                'detail_loaded_key' => '_analysisPeriodDetLoaded',
                'apply_button_id' => 'btnAnalysisPeriodApply',
                'reset_button_id' => 'btnAnalysisPeriodReset',
            ],
        ];
    }

    public static function forContext(string $context): array
    {
        $reports = [];
        foreach (self::all() as $reportCode => $reportDefinition) {
            if (!in_array($context, $reportDefinition['contexts'], true)) {
                continue;
            }
            $reportDefinition['filters'] = array_values(array_filter(
                $reportDefinition['filters'],
                static fn(array $filterDefinition): bool => in_array($context, $filterDefinition['contexts'], true)
            ));
            $reports[$reportCode] = $reportDefinition;
        }
        return $reports;
    }

    public static function find(string $reportCode): ?array
    {
        return self::all()[$reportCode] ?? null;
    }

    private static function report(
        string $name,
        ?string $filtersEndpoint,
        ?string $summaryEndpoint,
        ?string $detailEndpoint,
        array $filters,
        array $rowDimensions = [],
        array $columnDimensions = [],
        bool $organizationRequired = true,
        bool $mailingEnabled = true,
        bool $mailingSelectable = true
    ): array {
        $contexts = ['page', 'export'];
        if ($mailingEnabled) {
            $contexts[] = 'mailing';
        }
        return [
            'name' => $name,
            'contexts' => $contexts,
            'mailing_selectable' => $mailingSelectable,
            'organization_required' => $organizationRequired,
            'filters_endpoint' => $filtersEndpoint,
            'summary_endpoint' => $summaryEndpoint,
            'detail_endpoint' => $detailEndpoint,
            'filters' => $filters,
            'row_dimensions' => $rowDimensions,
            'column_dimensions' => $columnDimensions,
        ];
    }

    private static function mailingReport(string $name): array
    {
        return [
            'name' => $name,
            'contexts' => ['mailing'],
            'mailing_selectable' => true,
            'organization_required' => true,
            'filters_endpoint' => null,
            'summary_endpoint' => null,
            'detail_endpoint' => null,
            'filters' => [],
            'row_dimensions' => [],
            'column_dimensions' => [],
        ];
    }

    private static function filter(
        string $key,
        string $label,
        string $valueType,
        string $fieldId,
        ?string $optionsKey = null,
        array $contexts = ['page', 'mailing'],
        bool $required = false,
        array $choices = [],
        ?string $initialValue = null
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value_type' => $valueType,
            'field_id' => $fieldId,
            'options_key' => $optionsKey,
            'contexts' => $contexts,
            'required' => $required,
            'choices' => $choices,
            'initial_value' => $initialValue,
        ];
    }

    private static function dimension(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label];
    }

    private static function columnDimension(string $key, string $parameter): array
    {
        return ['key' => $key, 'parameter' => $parameter];
    }
    private static function detailTabs(): array
    {
        return [['key' => 'main', 'name' => 'Основная информация'], ['key' => 'data-wagon', 'name' => 'Данные о вагоне'], ['key' => 'disl-wagon', 'name' => 'Дислокация вагона'], ['key' => 'techn-state-wagon', 'name' => 'Техн. состояние']];
    }

    private static function detailSettings(): array
    {
        $standardColumns = self::uniqueColumns(array_merge(self::baseColumns(), self::lesseeColumns()));
        $approachColumns = self::uniqueColumns(array_merge(self::baseColumns(), self::approachExtraColumns(), self::lesseeColumns()));
        $rawMaterialColumns = array_map(
            static function (array $column): array {
                if ($column['key'] === 'idle_time_days')
                    $column['label'] = 'Простой на станции (сут.)';
                return $column;
            },
            $standardColumns
        );
        $technicalKeys = ['wagon_no', 'wagon_state', 'days_to_repair', 'next_repair_dt', 'next_repair_type', 'oper_station', 'dest_station', 'trip_end_dt', 'cargo_name', 'prev_cargo', 'wagon_type_code', 'build_date', 'factory_no', 'manufacturer', 'wagon_type_name', 'wagon_model', 'tare_weight', 'load_capacity', 'length_mm', 'last_cap_repair_depot', 'last_cap_repair_dt', 'last_dep_repair_depot', 'last_dep_repair_dt', 'home_road', 'owner', 'owner_okpo', 'owner_local_code', 'home_station', 'life_ext_date', 'lessee', 'lessee_okpo', 'lessee_local_code', 'lease_home_station', 'lease_end_date', 'service_life', 'body_volume', 'clearance', 'air_dist_type', 'automode', 'auto_lever', 'brake_type', 'bogie_model', 'shock_absorber', 'wagon_model_code', 'proxy_operator', 'proxy_operator_okpo', 'axles_count'];
        $technicalColumns = array_values(array_map(
            static function (string $columnKey) use ($approachColumns): array {
                foreach ($approachColumns as $column) {
                    if ($column['key'] !== $columnKey)
                        continue;
                    $column['showInline'] = true;
                    $column['tab'] = 'main';
                    $column['danger'] = false;
                    $column['pinned'] = $columnKey === 'wagon_no';
                    return $column;
                }
                return ['key' => $columnKey, 'label' => $columnKey];
            },
            $technicalKeys
        ));

        return [
            'dislocation' => ['label' => 'Детализация', 'sort' => [['field' => 'wagon_no', 'type' => 'number']], 'columns' => $standardColumns],
            'approach' => ['label' => 'Подход вагонов', 'sort' => ['field' => 'wagon_no', 'type' => 'number', 'dir' => 'asc'], 'columns' => $approachColumns],
            'departure' => ['label' => 'Отправление вагонов', 'sort' => ['field' => 'wagon_no', 'type' => 'number', 'dir' => 'asc'], 'columns' => $standardColumns],
            'loading' => ['label' => 'Погрузка', 'sort' => ['field' => 'wagon_no', 'type' => 'number', 'dir' => 'asc'], 'columns' => $standardColumns],
            'downtime' => ['label' => 'Простои', 'sort' => ['field' => 'idle_time_days', 'dir' => 'desc'], 'columns' => $standardColumns],
            'downtime-control' => ['label' => 'Контроль простоев', 'sort' => ['field' => 'id_control', 'type' => 'number', 'dir' => 'desc'], 'columns' => self::downtimeControlColumns()],
            'claims' => ['label' => 'Претензии', 'sort' => ['field' => 'amount', 'type' => 'number', 'dir' => 'desc'], 'columns' => self::claimsColumns()],
            'raw-material' => ['label' => 'Сырьё', 'sort' => ['field' => 'wagon_no', 'type' => 'number', 'dir' => 'asc'], 'columns' => $rawMaterialColumns],
            'analysis-period' => ['label' => 'Анализ за период', 'sort' => ['field' => 'wagon_no', 'type' => 'number', 'dir' => 'asc'], 'columns' => $standardColumns],
            'technical-state' => ['label' => 'Техническое состояние', 'sort' => ['field' => 'wagon_no', 'type' => 'number', 'dir' => 'asc'], 'columns' => $technicalColumns],
            'source-approach' => ['label' => 'Справка РЖД - Подход', 'sort' => [], 'columns' => self::sourceColumns()],
            'source-departure' => ['label' => 'Справка РЖД - Отправка', 'sort' => [], 'columns' => self::sourceColumns()],
        ];
    }

    /**
     * Поля полной справки в исходном порядке. Подписи дословно взяты из строки
     * заголовков загружаемого файла РЖД и используются также при импорте.
     */
    public static function sourceColumns(): array
    {
        $fields = [
            'wagon_no', 'waybill_no', 'wagon_type_code', 'owner_admin', 'trip_start_dt',
            'depart_state', 'depart_road', 'depart_station', 'trip_end_dt', 'dest_state',
            'dest_road', 'dest_station', 'consignor_tgnl', 'consignor', 'consignor_okpo',
            'consignor_name', 'consignee_tgnl', 'consignee', 'consignee_okpo', 'consignee_name',
            'cargo_name', 'cargo_gng', 'cargo_weight_kg', 'mileage_loaded_km', 'mileage_empty_km',
            'mileage_total_km', 'mileage_norm_km', 'mileage_remain_km', 'mileage_sign', 'special_marks',
            'prev_cargo', 'oper_station', 'oper_road', 'operation', 'oper_mnemonic', 'oper_dt',
            'park_type', 'handover_road', 'receive_road', 'train_index', 'train_no', 'wagon_in_train',
            'park_no', 'track_no', 'seals_count', 'loaded_containers', 'empty_containers', 'container_nos',
            'norm_delivery_dt', 'dist_passed_km', 'dist_remain_km', 'dist_total_km', 'idle_time_hhmmss',
            'idle_time_days', 'extra_waybill_no', 'extra_send_id', 'asoup_depart_dt', 'asoup_arrive_dt',
            'send_id', 'waybill_id', 'wagon_no2', 'quality_sign', 'state_assign_dt', 'wagon_state',
            'state_reason', 'state_station', 'reg_date', 'build_date', 'next_repair_dt', 'next_repair_type',
            'factory_no', 'manufacturer', 'wagon_type_name', 'wagon_model', 'tare_weight', 'load_capacity',
            'length_mm', 'last_cap_repair_depot', 'last_cap_repair_dt', 'last_dep_repair_depot',
            'last_dep_repair_dt', 'home_road', 'home_depot', 'exclude_date', 'no_transit_reason',
            'prev_wagon_no', 'owner', 'owner_okpo', 'owner_local_code', 'home_station', 'threshold_sign',
            'lease_sign', 'life_ext_date', 'lessee', 'lessee_okpo', 'lessee_local_code',
            'lease_home_station', 'lease_end_date', 'service_life', 'body_material_code', 'body_material_name',
            'body_volume', 'clearance', 'air_dist_type', 'automode', 'auto_lever', 'brake_type',
            'coupler_type', 'bogie_model', 'shock_absorber', 'life_ext_sign', 'boiler_caliber',
            'drain_device', 'lever_gear', 'wagon_model_code', 'repair_by_mileage', 'proxy_operator',
            'proxy_operator_okpo', 'wagon_type_code2', 'wagon_type_cond', 'axles_count', 'exclude_depot',
            'exclude_reason', 'days_to_repair', 'days_no_oper', 'days_no_move',
        ];

        $labels = [
            'Номер вагона',
            'Номер накладной',
            'Род вагона',
            'Администрация собственника',
            'Дата и время начала рейса',
            'Государство отправления',
            'Дорога отправления',
            'Станция отправления',
            'Дата и время окончания рейса',
            'Государство назначения',
            'Дорога назначения',
            'Станция назначения',
            'Грузоотправитель (ТГНЛ)',
            'Грузоотправитель',
            'Грузоотправитель (ОКПО)',
            'Грузоотправитель (наим)',
            'Грузополучатель (ТГНЛ)',
            'Грузополучатель',
            'Грузополучатель (ОКПО)',
            'Грузополучатель (наим)',
            'Наименование груза',
            'Код груза ГНГ',
            'Вес груза (кг)',
            'Пробег в груженом состоянии (км)',
            'Пробег в порожнем состоянии (км)',
            'Пробег общий (км)',
            'Норматив величины пробега (км)',
            'Остаток пробега (км)',
            'Признак пробега',
            'Особые отметки',
            'Ранее выгруженный груз',
            'Станция операции',
            'Дорога операции',
            'Операция',
            'Мнемокод операции',
            'Дата и время операции',
            'Тип парка',
            'Дорога сдачи',
            'Дорога приема',
            'Индекс поезда',
            'Номер поезда',
            'Номер вагона в составе поезда',
            'Номер парка',
            'Номер пути',
            'Количество пломб',
            'Количество груженых контейнеров',
            'Количество порожних контейнеров',
            'Номера контейнеров на вагоне',
            'Нормативный срок доставки',
            'Расстояние пройденное (км)',
            'Расстояние оставшееся (км)',
            'Расстояние общее (км)',
            'Время простоя под последней операцией (сутки:часы:минуты)',
            'Время простоя под последней операцией (сутки)',
            'Номер накладной по досылке',
            'Уникальный номер отправки по досылке',
            'Дата и время отправления (АСОУП) со станции приема груза к перевозке',
            'Дата и время прибытия (АСОУП) на станцию назначения',
            'Идентификатор отправки',
            'Идентификатор накладной',
            'Номер вагона',
            'Признак качества',
            'Дата и время назначения состояния',
            'Состояние вагона',
            'Причина назначения состояния',
            'Станция назначения состояния',
            'Дата регистрации',
            'Дата постройки',
            'Дата следующего планового ремонта',
            'Вид следующего планового ремонта',
            'Заводской номер',
            'Завод-изготовитель',
            'Тип вагона',
            'Модель вагона',
            'Тара вагона',
            'Грузоподъемность вагона',
            'Длина по осям автосцепки',
            'Депо последнего кап. ремонта',
            'Дата последнего кап. ремонта',
            'Депо последнего деп. ремонта',
            'Дата последнего деп. ремонта',
            'Дорога приписки',
            'Депо приписки',
            'Дата исключения',
            'Причина запрета на курсирование',
            'Номер вагона до перенумерации',
            'Собственник',
            'Собственник (ОКПО)',
            'Собственник (локальный код)',
            'Станция приписки',
            'Признак порога',
            'Признак аренды',
            'Утв. дата продления срока службы',
            'Арендатор',
            'Арендатор (ОКПО)',
            'Арендатор (локальный код)',
            'Станция приписки аренды',
            'Дата окончания аренды',
            'Срок службы вагона',
            'Материал кузова (код)',
            'Наименование материала кузова',
            'Объём кузова',
            'Габарит',
            'Тип воздухораспределителя (код)',
            'Авторежим',
            'Авторегулятор рычажной передачи',
            'Тип тормоза',
            'Тип автосцепки',
            'Модель тележки',
            'Тип поглощающего аппарата',
            'Признак продления срока службы вагона',
            'Калибр котла',
            'Наличие сливного прибора',
            'Рычажная передача',
            'Код модели вагона',
            'Признак ремонта по пробегу',
            'Оператор по доверенности',
            'Оператор по доверенности (ОКПО)',
            'Род вагона',
            'Условный тип вагона',
            'Количество осей вагона',
            'Депо исключения вагона',
            'Причина исключения вагона',
            'Дней до следующего ремонта',
            'Дней без операций',
            'Дней без движения',
        ];

        return array_map(
            static fn(string $field, string $label): array => [
                'key' => $field,
                'label' => $label,
                'w' => 180,
                'showInline' => true,
            ],
            $fields,
            $labels
        );
    }

    private static function baseColumns(): array
    {
        return [
            ['key' => 'wagon_no', 'label' => '№ вагона', 'meta' => true, 'type' => 'number', 'w' => 110, 'tab' => 'main', 'pinned' => true, 'showInline' => true],
            ['key' => 'wagon_type_code', 'label' => 'Тип вагона', 'meta' => true, 'w' => 120, 'tab' => 'main', 'showInline' => true],
            ['key' => 'idle_time_days', 'label' => 'Простой (сут.)', 'right' => true, 'w' => 100, 'tab' => 'main', 'showInline' => true],
            ['key' => 'park_type', 'label' => 'Признак парка', 'meta' => true, 'w' => 180, 'tab' => 'main', 'showInline' => false],
            ['key' => 'cargo_name', 'label' => 'Груз', 'meta' => true, 'w' => 150, 'tab' => 'main', 'showInline' => true],
            ['key' => 'cargo_weight_kg', 'label' => 'Вес(кг)', 'right' => true, 'w' => 100, 'tab' => 'main', 'showInline' => true],
            ['key' => 'oper_station', 'label' => 'Тек. станция', 'meta' => true, 'w' => 150, 'tab' => 'main', 'showInline' => true],
            ['key' => 'oper_mnemonic', 'label' => 'Операция', 'meta' => true, 'w' => 90, 'tab' => 'main', 'showInline' => true],
            ['key' => 'oper_dt', 'label' => 'Дата операции', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY HH24:MI:SS', 'tab' => 'main', 'showInline' => true],
            ['key' => 'dest_road', 'label' => 'Дорога назнач.', 'meta' => true, 'w' => 130, 'tab' => 'main', 'showInline' => true],
            ['key' => 'dest_station', 'label' => 'Ст. назнач.', 'meta' => true, 'w' => 145, 'tab' => 'main', 'showInline' => true],
            ['key' => 'depart_road', 'label' => 'Дорога отправл.', 'meta' => true, 'w' => 130, 'tab' => 'main', 'showInline' => true],
            ['key' => 'depart_station', 'label' => 'Ст. отправл.', 'meta' => true, 'w' => 145, 'tab' => 'main', 'showInline' => true],
            ['key' => 'train_index', 'label' => 'Индекс поезда', 'meta' => true, 'w' => 130, 'tab' => 'main', 'showInline' => true],
            ['key' => 'train_no', 'label' => 'Поезд №', 'meta' => true, 'w' => 90, 'tab' => 'main', 'showInline' => true],
            ['key' => 'waybill_no', 'label' => '№ накладной', 'meta' => true, 'w' => 100, 'tab' => 'main', 'showInline' => true],
            ['key' => 'waybill_id', 'label' => 'ID накладной', 'meta' => true, 'w' => 100, 'tab' => 'main', 'showInline' => false],
            ['key' => 'wagon_state', 'label' => 'Состояние вагона', 'meta' => true, 'w' => 130, 'tab' => 'main', 'showInline' => true],
            ['key' => 'norm_delivery_dt', 'label' => 'Срок доставки', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY', 'tab' => 'main', 'showInline' => true],
            ['key' => 'consignor_name', 'label' => 'Грузоотправитель', 'meta' => true, 'w' => 150, 'tab' => 'data-wagon', 'showInline' => true],
            ['key' => 'consignee_name', 'label' => 'Грузополучатель', 'meta' => true, 'w' => 150, 'tab' => 'data-wagon', 'showInline' => true],
            ['key' => 'container_nos', 'label' => 'Номера контейнеров', 'right' => true, 'w' => 120, 'tab' => 'data-wagon', 'showInline' => true],
            ['key' => 'send_id', 'label' => 'ID отправки', 'meta' => true, 'w' => 100, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'extra_waybill_no', 'label' => '№ досыл. накладной', 'meta' => true, 'w' => 120, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'extra_send_id', 'label' => 'ID досылки', 'meta' => true, 'w' => 100, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'owner_admin', 'label' => 'Адм. собственника', 'meta' => true, 'w' => 150, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'trip_start_dt', 'label' => 'Дата начала рейса', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY HH24:MI', 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'trip_end_dt', 'label' => 'Дата оконч. рейса', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY HH24:MI', 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'depart_state', 'label' => 'Гос-во отправл.', 'meta' => true, 'w' => 150, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'dest_state', 'label' => 'Гос-во назнач.', 'meta' => true, 'w' => 150, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'consignor', 'label' => 'Грузоотправитель (полн.)', 'meta' => true, 'w' => 180, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'consignor_tgnl', 'label' => 'ТГНЛ грузоотпр.', 'meta' => true, 'w' => 110, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'consignor_okpo', 'label' => 'ОКПО грузоотпр.', 'meta' => true, 'w' => 120, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'consignee', 'label' => 'Грузополучатель (полн.)', 'meta' => true, 'w' => 180, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'consignee_tgnl', 'label' => 'ТГНЛ грузопол.', 'meta' => true, 'w' => 110, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'consignee_okpo', 'label' => 'ОКПО грузопол.', 'meta' => true, 'w' => 120, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'cargo_gng', 'label' => 'Код ГНГ', 'meta' => true, 'w' => 100, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'mileage_loaded_km', 'label' => 'Пробег гружёный (км)', 'right' => true, 'w' => 130, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'mileage_empty_km', 'label' => 'Пробег порожний (км)', 'right' => true, 'w' => 130, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'mileage_total_km', 'label' => 'Пробег общий (км)', 'right' => true, 'w' => 120, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'mileage_norm_km', 'label' => 'Пробег норм. (км)', 'right' => true, 'w' => 120, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'mileage_remain_km', 'label' => 'Пробег остаток (км)', 'right' => true, 'w' => 130, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'mileage_sign', 'label' => 'Знак пробега', 'meta' => true, 'w' => 110, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'special_marks', 'label' => 'Особые отметки', 'meta' => true, 'w' => 130, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'prev_cargo', 'label' => 'Предыдущий груз', 'meta' => true, 'w' => 180, 'tab' => 'data-wagon', 'showInline' => false],
            ['key' => 'dist_remain_km', 'label' => 'Остаток км', 'right' => true, 'w' => 100, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'operation', 'label' => 'Операция (полн.)', 'meta' => true, 'w' => 180, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'oper_road', 'label' => 'Дорога опер.', 'meta' => true, 'w' => 120, 'tab' => 'disl-wagon', 'showInline' => true],
            ['key' => 'handover_road', 'label' => 'Дорога сдачи', 'meta' => true, 'w' => 120, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'receive_road', 'label' => 'Дорога приёма', 'meta' => true, 'w' => 120, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'wagon_in_train', 'label' => 'Позиция в составе', 'right' => true, 'w' => 120, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'park_no', 'label' => '№ парка', 'meta' => true, 'w' => 90, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'track_no', 'label' => '№ пути', 'meta' => true, 'w' => 90, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'seals_count', 'label' => 'Пломб', 'right' => true, 'w' => 80, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'loaded_containers', 'label' => 'Гружёных конт.', 'right' => true, 'w' => 110, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'empty_containers', 'label' => 'Порожних конт.', 'right' => true, 'w' => 110, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'dist_passed_km', 'label' => 'Пройдено (км)', 'right' => true, 'w' => 110, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'dist_total_km', 'label' => 'Всего (км)', 'right' => true, 'w' => 100, 'tab' => 'disl-wagon', 'showInline' => false],
            ['key' => 'idle_time_hhmmss', 'label' => 'Простой (ЧЧ:ММ:СС)', 'meta' => true, 'w' => 130, 'tab' => 'disl-wagon', 'showInline' => true],
            ['key' => 'asoup_depart_dt', 'label' => 'Отпр. (АСОУП)', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY HH24:MI:SS', 'tab' => 'disl-wagon', 'showInline' => true],
            ['key' => 'asoup_arrive_dt', 'label' => 'Приб. (АСОУП)', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY HH24:MI:SS', 'tab' => 'disl-wagon', 'showInline' => true],
        ];
    }

    private static function lesseeColumns(): array
    {
        return [
            ['key' => 'lessee', 'label' => 'Арендатор', 'danger' => true, 'w' => 105, 'tab' => 'main', 'showInline' => true],
            ['key' => 'lease_home_station', 'label' => 'Станция приписки арендатора', 'danger' => true, 'w' => 105, 'tab' => 'main', 'showInline' => true],
        ];
    }

    private static function approachExtraColumns(): array
    {
        return [
            ['key' => 'days_no_move', 'label' => 'Дней без движ.', 'right' => true, 'w' => 110, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'owner', 'label' => 'Собственник', 'meta' => true, 'w' => 160, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'owner_okpo', 'label' => 'ОКПО собственника', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'owner_local_code', 'label' => 'Местный код собств.', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'home_road', 'label' => 'Дорога приписки', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'home_depot', 'label' => 'Депо приписки', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'home_station', 'label' => 'Станция приписки', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'quality_sign', 'label' => 'Признак качества', 'meta' => true, 'w' => 180, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'state_assign_dt', 'label' => 'Дата присв. состояния', 'meta' => true, 'w' => 140, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'state_reason', 'label' => 'Причина состояния', 'meta' => true, 'w' => 170, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'state_station', 'label' => 'Станция состояния', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'no_transit_reason', 'label' => 'Причина запрета транзита', 'meta' => true, 'w' => 180, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'reg_date', 'label' => 'Дата регистрации', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'build_date', 'label' => 'Дата постройки', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'next_repair_dt', 'label' => 'След. ремонт (дата)', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'next_repair_type', 'label' => 'След. ремонт (тип)', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'last_cap_repair_depot', 'label' => 'Депо посл. кап. рем.', 'meta' => true, 'w' => 160, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'last_cap_repair_dt', 'label' => 'Дата посл. кап. рем.', 'meta' => true, 'w' => 140, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'last_dep_repair_depot', 'label' => 'Депо посл. деп. рем.', 'meta' => true, 'w' => 160, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'last_dep_repair_dt', 'label' => 'Дата посл. деп. рем.', 'meta' => true, 'w' => 140, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'days_to_repair', 'label' => 'Дней до ремонта', 'right' => true, 'w' => 120, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'days_no_oper', 'label' => 'Дней без операций', 'right' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'repair_by_mileage', 'label' => 'Ремонт по пробегу', 'right' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'threshold_sign', 'label' => 'Признак порога', 'meta' => true, 'w' => 180, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'factory_no', 'label' => 'Заводской №', 'meta' => true, 'w' => 110, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'manufacturer', 'label' => 'Завод-изготовитель', 'meta' => true, 'w' => 170, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'wagon_type_name', 'label' => 'Наим. типа вагона', 'meta' => true, 'w' => 160, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'wagon_model', 'label' => 'Модель вагона', 'meta' => true, 'w' => 120, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'wagon_model_code', 'label' => 'Код модели', 'meta' => true, 'w' => 100, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'wagon_type_cond', 'label' => 'Условный тип вагона', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'tare_weight', 'label' => 'Масса тары', 'right' => true, 'w' => 100, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'load_capacity', 'label' => 'Грузоподъёмность', 'right' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'length_mm', 'label' => 'Длина (мм)', 'right' => true, 'w' => 100, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'axles_count', 'label' => 'Кол-во осей', 'right' => true, 'w' => 100, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'body_material_code', 'label' => 'Код материала кузова', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'body_material_name', 'label' => 'Материал кузова', 'meta' => true, 'w' => 160, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'body_volume', 'label' => 'Объём кузова (м³)', 'right' => true, 'w' => 120, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'clearance', 'label' => 'Габарит', 'meta' => true, 'w' => 100, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'boiler_caliber', 'label' => 'Калибр котла', 'right' => true, 'w' => 110, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'air_dist_type', 'label' => 'Воздухораспред.', 'meta' => true, 'w' => 140, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'automode', 'label' => 'Авторежим', 'meta' => true, 'w' => 140, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'auto_lever', 'label' => 'Авторычаг', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'brake_type', 'label' => 'Тип тормоза', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'coupler_type', 'label' => 'Тип автосцепки', 'meta' => true, 'w' => 140, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'bogie_model', 'label' => 'Модели тележек', 'meta' => true, 'w' => 170, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'shock_absorber', 'label' => 'Поглощ. аппарат', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'drain_device', 'label' => 'Сливной прибор', 'meta' => true, 'w' => 140, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'lever_gear', 'label' => 'Рычажная передача', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'service_life', 'label' => 'Срок службы (до)', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'life_ext_sign', 'label' => 'Признак продл. срока', 'right' => true, 'w' => 140, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'life_ext_date', 'label' => 'Дата продл. срока', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'lease_sign', 'label' => 'Признак аренды', 'right' => true, 'w' => 120, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'lessee_okpo', 'label' => 'ОКПО арендатора', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'lessee_local_code', 'label' => 'Местный код аренд.', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'lease_end_date', 'label' => 'Дата оконч. аренды', 'meta' => true, 'w' => 140, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'proxy_operator', 'label' => 'Оператор по дов.', 'meta' => true, 'w' => 160, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'proxy_operator_okpo', 'label' => 'ОКПО оператора', 'meta' => true, 'w' => 130, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'exclude_date', 'label' => 'Дата исключения', 'meta' => true, 'w' => 130, 'formatData' => 'DD.MM.YYYY', 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'exclude_depot', 'label' => 'Депо исключения', 'meta' => true, 'w' => 150, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'exclude_reason', 'label' => 'Причина исключения', 'meta' => true, 'w' => 170, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'prev_wagon_no', 'label' => 'Предыдущий № вагона', 'meta' => true, 'w' => 140, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'wagon_no2', 'label' => '№ вагона (дубль)', 'meta' => true, 'w' => 110, 'tab' => 'techn-state-wagon', 'showInline' => false],
            ['key' => 'wagon_type_code2', 'label' => 'Тип вагона (дубль)', 'meta' => true, 'w' => 120, 'tab' => 'techn-state-wagon', 'showInline' => false],
        ];
    }

    private static function downtimeControlColumns(): array
    {
        return [
            ['key' => 'car_number', 'label' => '№ вагона', 'meta' => true],
            ['key' => 'start_date', 'label' => 'Дата начала', 'formatData' => 'DD.MM.YYYY'],
            ['key' => 'end_date', 'label' => 'Дата окончания', 'formatData' => 'DD.MM.YYYY'],
            ['key' => 'idle_reasons_name', 'label' => 'Причина простоя', 'meta' => true],
            ['key' => 'note', 'label' => 'Примечание', 'meta' => true],
            ['key' => 'is_excluded', 'label' => 'Исключён', 'meta' => true],
            ['key' => 'created_name', 'label' => 'Создал', 'meta' => true],
        ];
    }

    private static function claimsColumns(): array
    {
        return [
            ['key' => 'wagon_no', 'label' => '№ вагона', 'meta' => true, 'pinned' => true],
            ['key' => 'wagon_type_code', 'label' => 'Род вагона', 'meta' => true],
            ['key' => 'oper_station', 'label' => 'Станция', 'meta' => true],
            ['key' => 'cargo_name', 'label' => 'Груз', 'meta' => true],
            ['key' => 'prev_cargo', 'label' => 'Ранее выгруженный груз', 'meta' => true],
            ['key' => 'owner', 'label' => 'Собственник', 'meta' => true],
            ['key' => 'consignor', 'label' => 'Грузоотправитель', 'meta' => true],
            ['key' => 'consignee', 'label' => 'Грузополучатель', 'meta' => true],
            ['key' => 'waybill_no', 'label' => 'Накладная', 'meta' => true],
            ['key' => 'arrived_at', 'label' => 'Прибыл', 'meta' => true, 'formatData' => 'DD.MM.YYYY HH24:MI:SS'],
            ['key' => 'report_dt', 'label' => 'Дата расчёта', 'meta' => true, 'formatData' => 'DD.MM.YYYY HH24:MI:SS'],
            ['key' => 'total_days', 'label' => 'Простой, сут.', 'right' => true],
            ['key' => 'free_days', 'label' => 'Бесплатно, сут.', 'right' => true],
            ['key' => 'payable_days', 'label' => 'Платно, сут.', 'right' => true],
            ['key' => 'daily_rate', 'label' => 'Ставка, ₽/сут.', 'right' => true, 'value_format' => 'money'],
            ['key' => 'amount', 'label' => 'Сумма, ₽', 'right' => true, 'value_format' => 'money'],
        ];
    }

    private static function uniqueColumns(array $columns): array
    {
        $uniqueColumns = [];
        foreach ($columns as $column) {
            $columnKey = (string) $column['key'];
            if (!isset($uniqueColumns[$columnKey]))
                $uniqueColumns[$columnKey] = $column;
        }
        return array_values($uniqueColumns);
    }

}
