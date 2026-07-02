<?php
$activeAdminPage = $activeAdminPage ?? '';

$adminNavItems = [
    ['key' => 'users', 'label' => 'Пользователи', 'url' => $basePath . '/admin/users'],
    ['key' => 'roles', 'label' => 'Роли',         'url' => $basePath . '/admin/roles'],
];

$directoryNavItems = [
    ['key' => 'stations', 'label' => 'Станции и координаты', 'url' => $basePath . '/admin/directories/stations'],
];
?>
<aside class="sidebar">
  <div class="nav-group">
    <span class="nav-group-label">Администрирование</span>
    <?php foreach ($adminNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>"
         href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="nav-group">
    <span class="nav-group-label">Справочники</span>
    <?php foreach ($directoryNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>"
         href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</aside>
