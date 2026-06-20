<?php
declare(strict_types=1);

/**
 * Единый источник навигации приложения.
 *
 * Структура элемента:
 *   id      — идентификатор вкладки / якорь панели на главной странице
 *   label   — отображаемый текст
 *   page    — код страницы из PageAccessMiddleware::PAGES для проверки прав
 *   url     — если задан, ссылка (иначе переключает панель на главной)
 *   target  — '_blank' для внешних ссылок
 *
 * Используется:
 *   - DashboardController → экспортируется в window.APP_NAV_CONFIG (→ app.js)
 *   - templates/partials/admin-sidebar.php → PHP-рендеринг боковой панели
 */
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
