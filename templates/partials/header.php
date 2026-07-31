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
            ($headerOrganization['short_name'] ?? '') ?: ($headerOrganization['name'] ?? '')
        );
        break;
    }
}
$headerName = $headerName ?? ($selectedOrganizationName !== '' ? $selectedOrganizationName : $appName);
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
      <?php elseif (count($headerOrganizations) === 1): ?>
        <div class="organization-name" title="<?= htmlspecialchars($headerOrganizations[0]['name'] ?? '') ?>">
          <?= htmlspecialchars($headerOrganizations[0]['short_name'] ?: $headerOrganizations[0]['name']) ?>
        </div>
      <?php else: ?>
        <div class="organization-name organization-name--empty">Организация не назначена</div>
      <?php endif; ?>
      <div class="user-info">
        <?= $headerRight ?>
        <svg class="user-info-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="8" r="4" fill="currentColor"/>
          <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>
        </svg>
        <span class="user-name" title="<?= htmlspecialchars($user['auth_source'] ?? '') ?>">
          <?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? '') ?>
        </span>
        <span class="user-info-divider"></span>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/logout" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <button type="submit" class="btn btn-ghost btn-icon" title="Выйти">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </form>
      </div>
    </div>
  </div>
</header>
