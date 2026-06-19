<?php
// Переменные из вызывающего шаблона:
//   $appName     (string)       — название приложения
//   $basePath    (string)       — базовый путь
//   $headerSub   (string|null)  — HTML-подпись под названием (.brand-date-sub / .brand-sub)
//   $headerRight (string|null)  — HTML внутри .header-meta (кнопки, имя пользователя)
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
    <?php if ($headerRight !== ''): ?>
    <div class="header-meta">
      <?= $headerRight ?>
    </div>
    <?php endif; ?>
  </div>
</header>
