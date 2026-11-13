<?php
declare(strict_types=1);

namespace App\Reports\Kpi;

use App\Reports\ReportConfig;

final class KpiGroupingTemplates
{
    private const COMPUTED_FIELDS = [
        'idle_duration' => 'Простой (ЧЧ:ММ:СС)',
        'loaded_count' => 'Гружёные',
        'empty_count' => 'Порожние',
    ];

    private const TEMPLATES = [
        'TRAIN_NUMBER' => [
            'label' => 'По номеру поезда',
            'group_field' => 'train_no',
            'group_prefix' => 'Поезд № ',
            'title' => 'Группы поездов по номеру',
            'fields' => ['train_index', 'oper_station', 'oper_road', 'operation', 'oper_dt', 'trip_start_dt'],
            'route_fields' => ['depart_station', 'dest_station'],
            'sections' => [
                ['label' => 'Гружёные', 'key' => 'loaded', 'filters' => ['cargo_state' => 'гр.']],
                ['label' => 'Порожние', 'key' => 'empty_count', 'filters' => ['cargo_state' => 'пор.']],
            ],
        ],
        'TRAIN_INDEX' => [
            'label' => 'По индексу поезда',
            'group_field' => 'train_index',
            'group_prefix' => 'Индекс поезда: ',
            'title' => 'Группы поездов по индексу',
            'fields' => ['train_no', 'oper_station', 'oper_road', 'operation', 'oper_dt', 'trip_start_dt'],
            'route_fields' => ['depart_station', 'dest_station'],
        ],
        'WAGON_TYPE' => [
            'label' => 'По типу вагона',
            'group_field' => 'wagon_type_code',
            'group_prefix' => '',
            'title' => 'Вагоны по типу',
            'fields' => ['idle_duration', 'loaded_count', 'empty_count'],
            'route_fields' => [],
            'sections' => [
                ['label' => 'Гружёные', 'key' => 'loaded', 'filters' => ['cargo_state' => 'гр.']],
                ['label' => 'Порожние', 'key' => 'empty_count', 'filters' => ['cargo_state' => 'пор.']],
            ],
        ],
    ];

    public static function options(): array
    {
        $options = ['TABLE' => 'Обычная таблица'];
        foreach (self::TEMPLATES as $code => $template) {
            $options[$code] = $template['label'];
        }
        return $options;
    }

    public static function get(string $code): ?array
    {
        $template = self::TEMPLATES[strtoupper(trim($code))] ?? null;
        if (!$template) {
            return null;
        }

        $source = array_column(ReportConfig::sourceColumns(), null, 'key');
        $fields = [];
        foreach ($template['fields'] as $key) {
            if (!isset($source[$key]) && !isset(self::COMPUTED_FIELDS[$key])) {
                return null;
            }
            $fields[] = [
                'key' => $key,
                'label' => $source[$key]['label'] ?? self::COMPUTED_FIELDS[$key],
            ];
        }

        $route = [];
        foreach ($template['route_fields'] as $key) {
            if (!isset($source[$key])) {
                return null;
            }
            $route[] = ['key' => $key, 'label' => $source[$key]['label']];
        }

        return [
            'code' => strtoupper(trim($code)),
            'label' => $template['label'],
            'group_field' => $template['group_field'],
            'group_prefix' => $template['group_prefix'],
            'title' => $template['title'],
            'fields' => $fields,
            'route_fields' => $route,
            'sections' => $template['sections'] ?? [],
            'count_label' => 'вагонов',
            'empty_label' => 'Нет данных',
        ];
    }

    public static function fromDetail(array $detail): ?array
    {
        $template = trim((string) ($detail['template'] ?? ''));
        if ($template === '') {
            $legacyField = (string) ($detail['group_field'] ?? '');
            $template = $legacyField === 'train_no' ? 'TRAIN_NUMBER' : ($legacyField === 'train_index' ? 'TRAIN_INDEX' : '');
        }
        return self::get($template);
    }
}
