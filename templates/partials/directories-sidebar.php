<?php
$activeAdminPage = $activeAdminPage ?? '';
$directoryNavItems = [
    ['key' => 'norms', 'label' => 'Нормативы', 'url' => $basePath . '/directories/norms'],
    ['key' => 'stations', 'label' => 'Станции и координаты', 'url' => $basePath . '/directories/stations'],
];
?>
<aside class="sidebar">
  <div class="nav-group">
    <span class="nav-group-label">Справочники</span>
    <?php foreach ($directoryNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</aside>
