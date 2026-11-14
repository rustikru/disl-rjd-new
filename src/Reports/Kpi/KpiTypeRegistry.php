<?php
declare(strict_types=1);

namespace App\Reports\Kpi;


final class KpiTypeRegistry
{
    private const HIDDEN_FIELDS = [
        'wagon_no',
        'wagon_state',
        'cargo_weight_kg',
        'oper_dt',
        'norm_delivery_dt',
    ];

    public static function dashboardKpi(): array
    {
        return array (
  'type' => 'dashboard_kpi',
  'page' => 'dashboard',
  'tab' => 'dislocation',
  'label' => 'Дашборд',
  'container_key' => 'dashboard_kpi',
  'user_creatable' => false,
  'source' =>
  array (
    'type' => 'oracle_function',
    'package' => 'XX_RJD_DISLOCATION_NEW_PKG',
    'name' => 'GET_PAGE_KPI',
    'params' =>
    array (
      'kpi_type' => 'dashboard_kpi',
      'user_id' => 'session.user_id',
    ),
  ),
  'reference_type' => NULL,
  'fields' =>
  array (
  ),
);
    }

    public static function approachKpi(): array
    {
        return array (
  'type' => 'approach_kpi',
  'page' => 'approach',
  'tab' => 'approach',
  'label' => 'Подход вагонов',
  'container_key' => 'approach_kpi',
  'user_creatable' => true,
  'source' =>
  array (
    'type' => 'oracle_function',
    'package' => 'XX_RJD_DISLOCATION_NEW_PKG',
    'name' => 'GET_PAGE_KPI',
    'params' =>
    array (
      'kpi_type' => 'approach_kpi',
      'user_id' => 'session.user_id',
    ),
  ),
  'reference_type' => 'Подход',
  'fields' =>
  array (
    'wagon_no' => 'string',
    'depart_station' => 'string',
    'dest_station' => 'string',
    'oper_station' => 'string',
    'cargo_name' => 'string',
    'consignor' => 'string',
    'consignee' => 'string',
    'wagon_type_code' => 'string',
    'wagon_state' => 'string',
    'train_index' => 'string',
    'train_no' => 'string',
    'cargo_weight_kg' => 'number',
    'oper_dt' => 'date',
    'norm_delivery_dt' => 'date',
  ),
);
    }

    public static function departureKpi(): array
    {
        return array (
  'type' => 'departue_kpi',
  'page' => 'departureKpi',
  'tab' => 'departure',
  'label' => 'Отправление вагонов',
  'container_key' => 'departue_kpi',
  'user_creatable' => true,
  'source' =>
  array (
    'type' => 'oracle_function',
    'package' => 'XX_RJD_DISLOCATION_NEW_PKG',
    'name' => 'GET_PAGE_KPI',
    'params' =>
    array (
      'kpi_type' => 'departue_kpi',
      'user_id' => 'session.user_id',
    ),
  ),
  'reference_type' => 'Отправка',
  'fields' =>
  array (
    'wagon_no' => 'string',
    'depart_station' => 'string',
    'dest_station' => 'string',
    'oper_station' => 'string',
    'cargo_name' => 'string',
    'consignor' => 'string',
    'consignee' => 'string',
    'wagon_type_code' => 'string',
    'wagon_state' => 'string',
    'train_index' => 'string',
    'train_no' => 'string',
    'cargo_weight_kg' => 'number',
    'oper_dt' => 'date',
  ),
);
    }

    public static function loadingKpi(): array
    {
        return array (
  'type' => 'loading_kpi',
  'page' => 'loading',
  'tab' => 'loading',
  'label' => 'Погрузка',
  'container_key' => 'loading_kpi',
  'user_creatable' => false,
  'source' =>
  array (
    'type' => 'oracle_function',
    'package' => 'XX_RJD_DISLOCATION_NEW_PKG',
    'name' => 'GET_PAGE_KPI',
    'params' =>
    array (
      'kpi_type' => 'loading_kpi',
      'user_id' => 'session.user_id',
    ),
  ),
  'reference_type' => 'Отправка',
  'fields' =>
  array (
  ),
);
    }

    public static function downtimeKpi(): array
    {
        return array (
  'type' => 'downtime_kpi',
  'page' => 'downtime',
  'tab' => 'downtime',
  'label' => 'Простои вагонов',
  'container_key' => 'downtime_kpi',
  'user_creatable' => true,
  'source' =>
  array (
    'type' => 'query_profile',
    'name' => 'downtime',
  ),
  'actions' =>
  array (
    'create' => true,
    'edit' => true,
    'hide' => true,
    'restore' => true,
  ),
  'reference_type' => 'Подход',
  'fields' =>
  array (
    'wagon_no' => 'string',
    'cargo_name' => 'string',
    'dest_station' => 'string',
    'oper_station' => 'string',
    'wagon_type_code' => 'string',
    'wagon_state' => 'string',
    'idle_time_days' => 'number',
    'idle_time_hhmmss' => 'string',
  ),
);
    }

    private static function typeMethods(): array
    {
        return ['dashboardKpi', 'approachKpi', 'departureKpi', 'loadingKpi', 'downtimeKpi'];
    }

    public static function all(): array
    {
        $types = [];
        foreach (self::typeMethods() as $method) {
            $definition = self::$method();
            $code = $definition['type'];
            unset($definition['type']);
            $types[$code] = $definition;
        }
        return $types;
    }

    private const FIELD_LABELS = [
        'wagon_no' => 'Номер вагона',
        'depart_station' => 'Станция отправления',
        'dest_station' => 'Станция назначения',
        'oper_station' => 'Станция операции',
        'cargo_name' => 'Груз',
        'consignor' => 'Грузоотправитель',
        'consignee' => 'Грузополучатель',
        'wagon_type_code' => 'Род вагона',
        'wagon_state' => 'Состояние вагона',
        'train_index' => 'Индекс поезда',
        'train_no' => 'Номер поезда',
        'cargo_weight_kg' => 'Вес груза, кг',
        'oper_dt' => 'Дата операции',
        'norm_delivery_dt' => 'Нормативная дата доставки',
        'idle_time_days' => 'Простой, сут.',
        'idle_time_hhmmss' => 'Простой (ЧЧ:ММ:СС)',
    ];

    public static function get(string $type): ?array
    {
        return self::all()[trim($type)] ?? null;
    }

    public static function forPage(string $page): ?array
    {
        foreach (self::all() as $type => $definition) {
            if (($definition['page'] ?? null) === trim($page)) {
                return array_merge(['type' => $type], $definition);
            }
        }
        return null;
    }

    public static function dashboard(): ?array { return self::forPage('dashboard'); }
    public static function approach(): ?array { return self::forPage('approach'); }
    public static function departure(): ?array { return self::forPage('departure'); }
    public static function loading(): ?array { return self::forPage('loading'); }
    public static function downtime(): ?array { return self::forPage('downtime'); }

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
        foreach (self::all() as $definition) {
            if ($definition['page'] === trim($page)) {
                return $definition['label'];
            }
        }
        return null;
    }

    public static function userTypes(): array
    {
        return array_filter(
            self::all(),
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
        foreach (self::all() as $type => $definition) {
            $result[$type] = [
                'page' => $definition['page'],
                'tab' => $definition['tab'],
                'label' => $definition['label'],
                'container_key' => $definition['container_key'],
                'user_creatable' => $definition['user_creatable'],
                'source' => $definition['source'] ?? null,
                'actions' => $definition['actions'] ?? [],
            ];
        }
        return $result;
    }
}
