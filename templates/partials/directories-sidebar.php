<?php
$activeAdminPage = $activeAdminPage ?? '';
$directoryUser = $user ?? ($_SESSION['user'] ?? []);
$directoryAvailability = new \App\Services\Navigation\PageAvailability((array)(\App\Services\Config\ConfigSchema::current()['disabled_pages']??[]));
$directoryNavItems = [];
$directoryPages = (array) ($directoryUser['allowed_pages'] ?? []);
$canShowDirectories = \App\Services\Navigation\PageRegistry::canShowInMenu('directories', $directoryPages, $directoryUser, $directoryAvailability);
foreach (\App\Services\Navigation\PageRegistry::all() as $pageCode => $definition) {
    if (!$canShowDirectories || !\App\Services\Navigation\PageRegistry::canShowInMenu($pageCode, $directoryPages, $directoryUser, $directoryAvailability)) continue;
    foreach ((array) ($definition['directory_navigation'] ?? []) as $item) {
        $directoryNavItems[] = $item + ['label' => $definition['label'], 'page' => $pageCode];
    }
}
if (!$canShowDirectories) $directoryNavItems = [];
usort($directoryNavItems, static fn(array $a,array $b): int => ($a['order']??0) <=> ($b['order']??0));
foreach ($directoryNavItems as &$item) $item['url'] = $basePath . $item['url']; unset($item);
?>
<?php if ($directoryNavItems !== []): ?>
<aside class="sidebar">
  <div class="nav-group">
    <span class="nav-group-label">Справочники</span>
    <?php foreach ($directoryNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</aside>
<?php endif; ?>
