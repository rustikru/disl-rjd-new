<?php
// Переменные из вызывающего шаблона:
//   $appName     (string)       — название приложения
//   $basePath    (string)       — базовый путь
//   $user        (array)        — ['display_name', 'username', 'auth_source']
//   $headerSub   (string|null)  — HTML-подпись под названием (.brand-date-sub / .brand-sub)
//   $headerRight (string|null)  — дополнительные кнопки слева от имени (← Назад и т.п.)
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
