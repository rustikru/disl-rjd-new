<?php
$activeAdminPage = $activeAdminPage ?? '';
$adminUser = $user ?? ($_SESSION['user'] ?? []);
$isSuperAdmin = \App\Services\Auth\Authorization::isSuper($adminUser);
$adminPages = $isSuperAdmin ? array_keys(\App\Services\Navigation\PageRegistry::labels()) : (array) ($adminUser['allowed_pages'] ?? []);
$sidebarAvailability = new \App\Services\Navigation\PageAvailability((array)(\App\Services\Config\ConfigSchema::current()['disabled_pages']??[]));

$adminNavGroups = [];
foreach (\App\Services\Navigation\PageRegistry::all() as $key => $definition) {
    $item = $definition['admin_navigation'] ?? null;
    if (!$item || !\App\Services\Navigation\PageRegistry::canShowInMenu($key, $adminPages, $adminUser, $sidebarAvailability) || (!$isSuperAdmin && $sidebarAvailability->isDisabled('admin')) || ((!empty($definition['super_only']) || !empty($item['super_only'])) && !$isSuperAdmin)) continue;
    $adminNavGroups[$item['group']][] = ['key'=>$item['active_key']??$key,'label'=>$item['label']??$definition['label'],'url'=>$basePath.$item['url'],'order'=>(int)($item['order']??0)];
}
foreach ($adminNavGroups as &$items) { usort($items, static fn(array $a,array $b): int => $a['order'] <=> $b['order']); } unset($items);


?>
<script>
  (function () {
    var theme = localStorage.getItem('admin-sidebar-theme') || 'light'
    if (theme === 'dark') theme = 'corporate'
    document.documentElement.classList.toggle('admin-sidebar-corporate', theme === 'corporate')
  })()
</script>
<aside class="sidebar admin-sidebar">
  <?php foreach ($adminNavGroups as $groupLabel => $adminNavItems): ?>
  <div class="nav-group">
    <span class="nav-group-label"><?= htmlspecialchars($groupLabel) ?></span>
    <?php foreach ($adminNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>"
         href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <div class="admin-sidebar-theme" aria-label="Оформление меню">
    <span>Оформление</span>
    <div class="admin-sidebar-theme-options">
      <button type="button" data-admin-theme="light">Светлое</button>
      <button type="button" data-admin-theme="corporate">Тёмное</button>
    </div>
  </div>
</aside>
<script>
  (function () {
    var buttons = document.querySelectorAll('[data-admin-theme]')

    function selectTheme(theme) {
      if (theme === 'dark') theme = 'corporate'
      document.documentElement.classList.toggle('admin-sidebar-corporate', theme === 'corporate')
      localStorage.setItem('admin-sidebar-theme', theme)
      buttons.forEach(function (button) {
        button.classList.toggle('active', button.dataset.adminTheme === theme)
      })
    }

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        selectTheme(button.dataset.adminTheme)
      })
    })

    selectTheme(localStorage.getItem('admin-sidebar-theme') || 'light')
  })()
</script>
