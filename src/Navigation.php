<?php
declare(strict_types=1);

// Вся навигация в одном месте. Менять только здесь — app.js подхватит сам.
return [
    [
        'group' => 'Движение вагонов',
        'items' => [
            ['id' => 'dislocation',  'label' => 'Дислокация',          'page' => 'dashboard'],
            ['id' => 'approach',     'label' => 'Подход вагонов',      'page' => 'dashboard'],
            ['id' => 'departure',    'label' => 'Отправление вагонов', 'page' => 'dashboard'],
            ['id' => 'loading',      'label' => 'Погрузка',            'page' => 'dashboard'],
        ],
    ],
    [
        'group' => 'Аналитика',
        'items' => [
            ['id' => 'technical-state', 'label' => 'Техническое состояние', 'page' => 'dashboard'],
            ['id' => 'analysis-period', 'label' => 'Анализ данных за период', 'page' => 'dashboard'],
            ['id' => 'maps',            'label' => 'Карта',                   'page' => 'maps',   'url' => '/maps',   'target' => '_blank'],
        ],
    ],
    [
        'group' => 'Простои и оборот',
        'items' => [
            ['id' => 'downtime', 'label' => 'Простои', 'page' => 'dashboard'],
            ['id' => 'raw-material', 'label' => 'Сырьё', 'page' => 'dashboard'],
            ['id' => 'claims', 'label' => 'Претензии', 'page' => 'dashboard'],
        ],
    ],
    [
        'group' => 'Реестр',
        'items' => [
            ['id' => 'downtime-control', 'label' => 'Контроль простоев', 'page' => 'dashboard'],
        ],
    ],
    [
        'group' => 'Справочники',
        'items' => [
            ['id' => 'norms', 'label' => 'Нормативы', 'page' => 'directories', 'url' => '/directories/norms'],
            ['id' => 'stations', 'label' => 'Станции и координаты', 'page' => 'directories', 'url' => '/directories/stations'],
        ],
    ],
    [
        'group' => 'Импорт',
        'items' => [
            ['id' => 'import', 'label' => 'Загрузка справки РЖД', 'page' => 'import', 'url' => '/import'],
        ],
    ],
];
