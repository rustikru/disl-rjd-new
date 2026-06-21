<?php
$headerSub   = $headerSub   ?? '';
$headerRight = $headerRight ?? '';
?>
<header class="site-header">
  <div class="header-inner">
    <div class="brand">
      <div class="brand-icon">
        <img src="<?= htmlspecialchars($basePath) ?>/assets/img/meta-logo.png" alt="" class="brand-logo">
      </div>
      <div class="brand-text">
        <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
        <?= $headerSub ?>
      </div>
    </div>
    <div class="header-meta">
      <div class="user-info">
        <?= $headerRight ?>
        <svg class="user-info-icon" width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
          <circle cx="7.5" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M1.5 14c0-3 2.7-4.5 6-4.5s6 1.5 6 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <span class="user-name" title="<?= htmlspecialchars($user['auth_source'] ?? '') ?>">
          <?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? '') ?>
        </span>
        <span class="user-info-divider"></span>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/logout" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Выйти">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <path d="M9 2.5H12.5V12.5H9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M6 5.5L9.5 7.5L6 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              <line x1="2" y1="7.5" x2="9.5" y2="7.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </button>
        </form>
      </div>
    </div>
  </div>
</header>
