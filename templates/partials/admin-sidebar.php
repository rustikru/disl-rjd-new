<?php
$activeAdminPage = $activeAdminPage ?? '';
$adminUser = $user ?? ($_SESSION['user'] ?? []);
$isSuperAdmin = \App\Services\Authorization::isSuper($adminUser);
$adminPages = $isSuperAdmin ? array_keys(\App\Services\PageRegistry::labels()) : (array) ($adminUser['allowed_pages'] ?? []);
$sidebarAvailability = new \App\Services\PageAvailability((array)(\App\Services\ConfigSchema::current()['disabled_pages']??[]));

$adminNavGroups = [];
foreach (\App\Services\PageRegistry::all() as $key => $definition) {
    $item = $definition['admin_navigation'] ?? null;
    if (!$item || !in_array($key, $adminPages, true) || $sidebarAvailability->isDisabled('admin') || $sidebarAvailability->isDisabled($key) || ((!empty($definition['super_only']) || !empty($item['super_only'])) && !$isSuperAdmin)) continue;
    $adminNavGroups[$item['group']][] = ['key'=>$item['active_key']??$key,'label'=>$item['label']??$definition['label'],'url'=>$basePath.$item['url'],'order'=>(int)($item['order']??0)];
}
foreach ($adminNavGroups as &$items) { usort($items, static fn(array $a,array $b): int => $a['order'] <=> $b['order']); } unset($items);


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
