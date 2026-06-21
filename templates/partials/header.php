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
          <button type="submit" class="btn btn-ghost btn-icon btn-sm" title="Выйти">
            &#8594;
          </button>
        </form>
      </div>
    </div>
  </div>
</header>
