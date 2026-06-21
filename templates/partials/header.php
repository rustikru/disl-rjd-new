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
        <span class="user-name" title="<?= htmlspecialchars($user['auth_source'] ?? '') ?>">
          <?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? '') ?>
        </span>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/logout" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <button type="submit" class="btn btn-ghost btn-sm">Выйти</button>
        </form>
      </div>
    </div>
  </div>
</header>
