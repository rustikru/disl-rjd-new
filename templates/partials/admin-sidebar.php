<?php
$activeAdminPage = $activeAdminPage ?? '';
$adminUser = $user ?? ($_SESSION['user'] ?? []);
$isSuperAdmin = !empty($adminUser['is_super']);

$adminNavGroups = ['Пользователи и доступ' => [
    ['key' => 'users', 'label' => 'Пользователи', 'url' => $basePath . '/admin/users'],
    ['key' => 'roles', 'label' => 'Роли',         'url' => $basePath . '/admin/roles'],
], 'Справочники' => [
    ['key' => 'organizations', 'label' => 'Организации', 'url' => $basePath . '/admin/directories/organizations'],
]];

if ($isSuperAdmin) {
    $adminNavGroups['Настройки системы'] = [
        ['key' => 'settings', 'label' => 'Настройки приложения', 'url' => $basePath . '/admin/settings'],
        ['key' => 'visibility', 'label' => 'Видимость страниц', 'url' => $basePath . '/admin/page-visibility'],
    ];
    $adminNavGroups['Рассылки'] = [[
        'key' => 'report-mailings',
        'label' => 'Рассылки',
        'url' => $basePath . '/admin/report-mailings',
    ]];
    $adminNavGroups['Контроль и обслуживание'] = [
        ['key' => 'sessions', 'label' => 'Активные сессии', 'url' => $basePath . '/admin/sessions'],
        [
        'key' => 'audit',
        'label' => 'Журнал действий',
        'url' => $basePath . '/admin/audit',
        ],
    ];
}


?>
<aside class="sidebar">
  <?php foreach ($adminNavGroups as $groupLabel => $adminNavItems): ?>
  <div class="nav-group">
    <span class="nav-group-label"><?= htmlspecialchars($groupLabel) ?></span>
    <?php foreach ($adminNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>"
         href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

</aside>
