<?php
$headerSub = $headerSub ?? '';
$headerRight = $headerRight ?? '';
$headerLeft = $headerLeft ?? '';
$headerSimple = $headerSimple ?? false;
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
      <?php if (!$headerSimple && count($headerOrganizations) > 1): ?>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/organization/select"
          class="organization-select-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">
          <label for="headerOrganization" class="organization-select-label">Организация</label>
          <select id="headerOrganization" name="organization_id" class="organization-select"
            onchange="this.form.submit()">
            <?php foreach ($headerOrganizations as $headerOrganization): ?>
              <option value="<?= (int) $headerOrganization['id'] ?>" <?= (int) $headerOrganization['id'] === $headerOrganizationId ? 'selected' : '' ?>>
                <?= htmlspecialchars($headerOrganization['short_name'] ?: $headerOrganization['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <?php if ($user !== []): ?>
        <div class="user-info">
          <?= $headerRight ?>
          <details class="user-menu">
            <summary class="user-menu-toggle" aria-label="Меню пользователя">
              <img src="<?= htmlspecialchars($basePath) ?>/assets/img/icon/profile.svg" class="user-menu-item-icon"">  
            
              <span class=" user-name">
              <?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? '') ?>
              </span>
              <svg class="user-menu-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m6 9 6 6 6-6" />
              </svg>
            </summary>
            <div class="user-menu-panel">
              <?php if (!$headerSimple): ?>
                <?php $userMenu = [];
                $headerAvailability = new \App\Services\PageAvailability((array) (\App\Services\ConfigSchema::current()['disabled_pages'] ?? []));
                $headerPages = \App\Services\Authorization::isSuper($user) ? array_keys(\App\Services\PageRegistry::labels()) : (array) ($user['allowed_pages'] ?? []);
                foreach (\App\Services\PageRegistry::all() as $pageCode => $pageDefinition) {
                  if (!empty($pageDefinition['user_menu']) && in_array($pageCode, $headerPages, true) && !$headerAvailability->isDisabled($pageCode))
                    $userMenu[] = $pageDefinition['user_menu'] + ['page' => $pageCode];
                }
                usort($userMenu, static fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
                foreach ($userMenu as $menuItem): ?>
                  <a href="<?= htmlspecialchars($basePath . $menuItem['url']) ?>">
                    <?php if (($menuItem['page'] ?? '') === 'profile'): ?>
                      <img src="<?= htmlspecialchars($basePath) ?>/assets/img/icon/profile.svg" class="user-menu-item-icon"">
                    <?php elseif (($menuItem['page'] ?? '') === 'user-report-mailings'): ?>
                      <img src=" <?= htmlspecialchars($basePath) ?>/assets/img/icon/mail.svg"
                        class="user-menu-item-icon"">
                    <?php elseif (($menuItem['page'] ?? '') === 'admin'): ?>
                      <img src=" <?= htmlspecialchars($basePath) ?>/assets/img/icon/admin-lock.svg"
                        class="user-menu-item-icon"">
                    <?php endif; ?>
                    <span><?= htmlspecialchars($menuItem['label']) ?></span>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
              <form method="POST" action="<?= htmlspecialchars($basePath) ?>/logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit">
                  <img src=" <?= htmlspecialchars($basePath) ?>/assets/img/icon/logout.svg" class="user-menu-item-icon"">
                  </svg><span>Выйти</span></button>
                </form>
            </div>
          </details>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<style>
  .user-info {
    background: transparent;
    border: 0;
    border-radius: 0;
    padding: 0;
    height: auto;
  }

  .user-menu {
    position: relative;
    width: 196px;
  }

  .user-menu summary {
    list-style: none;
  }

  .user-menu summary::-webkit-details-marker {
    display: none;
  }

  .user-menu-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-height: 36px;
    box-sizing: border-box;
    padding: 0 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-1);
    cursor: pointer;
    transition: background .15s, border-color .15s, border-radius .15s;
  }

  .user-menu-toggle:hover,
  .user-menu[open] .user-menu-toggle {
    background: var(--accent-lt);
    border-color: var(--accent);
  }

  .user-menu[open] .user-menu-toggle {
    border-radius: 9px 9px 0 0;
    border-bottom-color: transparent;
  }

  .user-menu-toggle:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 3px;
  }

  .user-menu-toggle .user-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0;
    color: var(--text-1);
    text-align: left;
  }

  .user-menu-toggle .user-info-icon {
    flex-shrink: 0;
    margin: 0;
    color: var(--accent);
  }

  .user-menu-chevron {
    flex-shrink: 0;
    color: var(--text-2);
    transition: transform .15s;
  }

  .user-menu[open] .user-menu-chevron {
    transform: rotate(180deg);
  }

  .user-menu-panel {
    position: absolute;
    top: 100%;
    right: 0;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 5px;
    background: var(--surface);
    border: 1px solid var(--accent);
    border-top: 0;
    border-radius: 0 0 9px 9px;
    box-shadow: 0 10px 22px rgba(35, 24, 65, .12);
    z-index: 1000;
  }

  .user-menu-panel a,
  .user-menu-panel button {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    box-sizing: border-box;
    min-height: 34px;
    padding: 7px 9px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--text-1);
    font: inherit;
    font-size: 12px;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
  }

  .user-menu-panel a:hover,
  .user-menu-panel button:hover {
    color: var(--accent);
    background: var(--accent-lt);
  }

  .user-menu-item-icon {
    display: block;
    width: 16px;
    height: 16px;
    flex: 0 0 16px;
    color: var(--accent);
  }

  .user-menu-panel form {
    margin-top: 4px;
    padding-top: 4px;
    border-top: 1px solid var(--border);
  }

  .user-menu-panel form:first-child {
    margin-top: 0;
    padding-top: 0;
    border-top: 0;
  }

  @media (max-width:600px) {
    .user-menu {
      width: 184px;
    }
  }

  .user-menu.just-opened .user-menu-panel a:hover,
  .user-menu.just-opened .user-menu-panel button:hover {
    color: var(--text-1);
    background: transparent;
  }
</style>
<script>
  (function () {
    const menu = document.querySelector('.user-menu');
    if (!menu) return;

    menu.addEventListener('toggle', function () {
      if (menu.open) {
        menu.classList.add('just-opened');
      } else {
        menu.classList.remove('just-opened');
      }
    });

    menu.addEventListener('pointermove', function () {
      menu.classList.remove('just-opened');
    }, { once: false });

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