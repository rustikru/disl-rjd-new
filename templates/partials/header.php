<?php
$headerSub   = $headerSub   ?? '';
$headerRight = $headerRight ?? '';
$headerLeft  = $headerLeft  ?? '';
$headerOrganizations = $_SESSION['organizations'] ?? [];
$headerOrganizationId = (int) ($_SESSION['organization_id'] ?? 0);
$selectedOrganizationName = '';
foreach ($headerOrganizations as $headerOrganization) {
    if ((int) ($headerOrganization['id'] ?? 0) === $headerOrganizationId) {
        $selectedOrganizationName = (string) (
            ($headerOrganization['name'] ?? '') ?: ($headerOrganization['short_name'] ?? '')
        );
        break;
    }
}
$headerName = $headerName ?? ($appName . ($selectedOrganizationName !== '' ? ' - ' . $selectedOrganizationName : ''));
$returnUrl = (string) ($_SERVER['REQUEST_URI'] ?? ($basePath . '/'));
?>
<header class="site-header">
  <div class="header-inner">
    <div class="header-left">
      <?= $headerLeft ?>
      <div class="brand">
        <div class="brand-icon">
          <img src="<?= htmlspecialchars($basePath) ?>/assets/img/meta-logo.png" alt="" class="brand-logo">
        </div>
        <div class="brand-text">
          <div class="brand-name"><?= htmlspecialchars($headerName) ?></div>
          <?= $headerSub ?>
        </div>
      </div>
    </div>
    <div class="header-meta">
      <?php if (count($headerOrganizations) > 1): ?>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/organization/select" class="organization-select-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">
          <label for="headerOrganization" class="organization-select-label">Организация</label>
          <select id="headerOrganization" name="organization_id" class="organization-select" onchange="this.form.submit()">
            <?php foreach ($headerOrganizations as $headerOrganization): ?>
              <option value="<?= (int) $headerOrganization['id'] ?>" <?= (int) $headerOrganization['id'] === $headerOrganizationId ? 'selected' : '' ?>>
                <?= htmlspecialchars($headerOrganization['short_name'] ?: $headerOrganization['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <div class="user-info">
        <?= $headerRight ?>
        <details class="user-menu">
          <summary class="user-menu-toggle" title="Меню пользователя">
            <svg class="user-info-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
            <span class="user-name"><?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? '') ?></span>
            <svg class="user-menu-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </summary>
          <div class="user-menu-panel">
            <?php $userMenu=[]; $headerAvailability=new \App\Services\PageAvailability((array)(\App\Services\ConfigSchema::current()['disabled_pages']??[])); $headerPages=\App\Services\Authorization::isSuper($user)?array_keys(\App\Services\PageRegistry::labels()):(array)($user['allowed_pages']??[]); foreach (\App\Services\PageRegistry::all() as $pageCode=>$pageDefinition) { if (!empty($pageDefinition['user_menu']) && in_array($pageCode,$headerPages,true) && !$headerAvailability->isDisabled($pageCode)) $userMenu[]=$pageDefinition['user_menu']+['page'=>$pageCode]; } usort($userMenu,static fn($a,$b)=>($a['order']??0)<=>($b['order']??0)); foreach($userMenu as $menuItem): ?>
            <a href="<?= htmlspecialchars($basePath . $menuItem['url']) ?>"><?= htmlspecialchars($menuItem['label']) ?></a>
            <?php endforeach; ?>
            <form method="POST" action="<?= htmlspecialchars($basePath) ?>/logout">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
              <button type="submit">Выйти</button>
            </form>
          </div>
        </details>
      </div>
    </div>
  </div>
</header>

<style>
.user-menu { position:relative; }
.user-info { background:transparent; padding:0; height:auto; }
.user-menu summary { list-style:none; }
.user-menu-toggle { display:flex; align-items:center; gap:8px; min-height:34px; padding:0 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text-1); cursor:pointer; transition:background .15s,border-color .15s; }
.user-menu-toggle:hover, .user-menu[open] .user-menu-toggle { background:var(--bg); border-color:var(--accent); }
.user-menu-toggle:focus-visible { outline:2px solid var(--accent); outline-offset:3px; }
.user-menu-toggle .user-name { padding:0; color:var(--text-1); }
.user-menu-toggle .user-info-icon { margin:0; color:var(--text-2); }
.user-menu-chevron { color:var(--text-2); transition:transform .15s; }
.user-menu summary::-webkit-details-marker { display:none; }
.user-menu[open] .user-menu-chevron { transform:rotate(180deg); }
.user-menu-panel { position:absolute; top:calc(100% + 10px); right:0; min-width:190px; padding:6px; background:var(--surface); border:1px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(35,24,65,.12); z-index:1000; }
.user-menu-panel a, .user-menu-panel button { display:block; width:100%; box-sizing:border-box; padding:10px 12px; border:0; border-radius:6px; background:transparent; color:var(--text-1); font:inherit; text-align:left; text-decoration:none; cursor:pointer; }
.user-menu-panel a:hover, .user-menu-panel button:hover { color:var(--accent); background:var(--accent-lt); }
.user-menu-panel form { margin-top:4px; padding-top:4px; border-top:1px solid var(--border); }
</style>
<script>
(function () {
  const menu = document.querySelector('.user-menu');
  if (!menu) return;
  document.addEventListener('click', function (event) {
    if (!menu.contains(event.target)) menu.open = false;
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && menu.open) {
      menu.open = false;
      menu.querySelector('summary').focus();
    }
  });
})();
</script>
