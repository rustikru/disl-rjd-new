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
            ['id' => 'raw-material', 'label' => 'Сырьё',               'page' => 'dashboard'],
        ],
    ],
    [
        'group' => 'Аналитика',
        'items' => [
            ['id' => 'analysis-period', 'label' => 'Анализ данных за период', 'page' => 'dashboard'],
            ['id' => 'maps',            'label' => 'Карта',                   'page' => 'maps',   'url' => '/maps',   'target' => '_blank'],
        ],
    ],
    [
        'group' => 'Простои и оборот',
        'items' => [
            ['id' => 'downtime', 'label' => 'Простои', 'page' => 'dashboard'],
        ],
    ],
    [
        'group' => 'Импорт',
        'items' => [
            ['id' => 'import', 'label' => 'Загрузка справки РЖД', 'page' => 'import', 'url' => '/import'],
        ],
    ],
];
