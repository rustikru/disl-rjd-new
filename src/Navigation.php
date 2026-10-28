<?php
declare(strict_types=1);
//
$reportGroups = [
    'movement' => ['name' => 'Движение вагонов', 'order' => 10],
    'analytics' => ['name' => 'Аналитика', 'order' => 20],
    'downtime' => ['name' => 'Простои и оборот', 'order' => 30],
    'registry' => ['name' => 'Реестр', 'order' => 40],
];
// Формируем навигацию по группам отчётов
$navigationGroups = [];
foreach ($reportGroups as $groupCode => $groupDefinition) {
    $navigationGroups[$groupCode] = [
        'group' => $groupDefinition['name'],
        'order' => $groupDefinition['order'],
        'items' => [],
    ];
}
// Формируем навигацию по отчётам
foreach (\App\Reports\ReportConfig::all() as $reportCode => $reportDefinition) {
    $navigation = $reportDefinition['navigation'] ?? null;
    $groupCode = $navigation['group'] ?? '';
    if (!isset($navigationGroups[$groupCode])) {
        continue;
    }
    $navigationGroups[$groupCode]['items'][] = [
        'id' => $reportCode,
        'label' => $reportDefinition['name'],
        'page' => 'dashboard',
        'order' => (int) ($navigation['order'] ?? 0),
    ];
}
// Сортируем группы и элементы навигации по порядку
foreach ($navigationGroups as &$navigationGroup) {
    usort(
        $navigationGroup['items'],
        static fn(array $leftItem, array $rightItem): int => $leftItem['order'] <=> $rightItem['order']
    );
    foreach ($navigationGroup['items'] as &$navigationItem) {
        unset($navigationItem['order']);
    }
    unset($navigationItem);
}
unset($navigationGroup);

// Обычные страницы не являются отчётами и остаются здесь.
$navigationGroups['analytics']['items'][] = [
    'id' => 'maps',
    'label' => 'Карта',
    'page' => 'maps',
    'url' => '/maps',
    'target' => '_blank',
];

$navigation = array_values($navigationGroups);
usort(
    $navigation,
    static fn(array $leftGroup, array $rightGroup): int => $leftGroup['order'] <=> $rightGroup['order']
);
foreach ($navigation as &$navigationGroup) {
    unset($navigationGroup['order']);
}
unset($navigationGroup);
// Добавляем дополнительные страницы, которые не относятся к отчётам
$navigation = array_merge($navigation, [
    [
        'group' => '',
        'items' => [
            ['id' => 'directories', 'label' => 'Справочники', 'page' => 'directories', 'url' => '/directories/'],
        ],
    ],
    [
        'group' => 'Импорт',
        'items' => [
            ['id' => 'import', 'label' => 'Загрузка справки РЖД', 'page' => 'import', 'url' => '/import'],
        ],
    ],
]);

return $navigation;
