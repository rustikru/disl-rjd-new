<?php
$activeAdminPage = $activeAdminPage ?? '';
$directoryAvailability = new \App\Services\PageAvailability((array)(\App\Services\ConfigSchema::current()['disabled_pages']??[]));
$directoryDisabled = $directoryAvailability->isDisabled('directories');
$directoryNavItems = \App\Services\PageRegistry::all()['directories']['directory_navigation'] ?? [];
if ($directoryDisabled) $directoryNavItems = [];
usort($directoryNavItems, static fn(array $a,array $b): int => ($a['order']??0) <=> ($b['order']??0));
foreach ($directoryNavItems as &$item) $item['url'] = $basePath . $item['url']; unset($item);
?>
<aside class="sidebar">
  <div class="nav-group">
    <span class="nav-group-label">Справочники</span>
    <?php foreach ($directoryNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</aside>
