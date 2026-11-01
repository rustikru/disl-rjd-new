<?php
$activeAdminPage = $activeAdminPage ?? '';
$directoryUser = $user ?? ($_SESSION['user'] ?? []);
$isDirectorySuper = \App\Services\Authorization::isSuper($directoryUser);
$directoryAvailability = new \App\Services\PageAvailability((array)(\App\Services\ConfigSchema::current()['disabled_pages']??[]));
$directoryNavItems = [];
foreach (\App\Services\PageRegistry::all() as $pageCode => $definition) {
    if (!$isDirectorySuper && $directoryAvailability->isDisabled($pageCode)) continue;
    foreach ((array) ($definition['directory_navigation'] ?? []) as $item) {
        $directoryNavItems[] = $item + ['label' => $definition['label'], 'page' => $pageCode];
    }
}
if (!$isDirectorySuper && $directoryAvailability->isDisabled('directories')) $directoryNavItems = [];
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
