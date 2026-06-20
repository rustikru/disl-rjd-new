<?php
$basePath    = $basePath    ?? '';
$appName     = $appName     ?? '';
$user        = $user        ?? [];
$headerSub   = $headerSub   ?? '';
$headerRight = $headerRight ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Страница не найдена</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .error-block {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: calc(100vh - 56px);
      text-align: center;
      padding: 40px 20px;
    }
    .error-num {
      font-size: 96px;
      font-weight: 800;
      color: var(--accent, #46297f);
      line-height: 1;
      opacity: .18;
    }
    .error-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-1, #1b1726);
      margin: -12px 0 8px;
    }
    .error-desc {
      font-size: 14px;
      color: var(--text-3, #6b667a);
      max-width: 400px;
      line-height: 1.6;
      margin-bottom: 24px;
    }
  </style>
</head>
<body>

<?php if (!empty($user)): ?>
  <?php include __DIR__ . '/partials/header.php'; ?>
<?php else: ?>
  <header class="site-header">
    <div class="header-inner">
      <div class="brand">
        <div class="brand-icon">
          <img src="<?= htmlspecialchars($basePath) ?>/assets/img/meta-logo.png" alt="" class="brand-logo">
        </div>
        <div class="brand-text">
          <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
        </div>
      </div>
    </div>
  </header>
<?php endif; ?>

<div class="error-block">
  <div class="error-num">404</div>
  <h1 class="error-title">Страница не найдена</h1>
  <p class="error-desc">Запрашиваемый адрес не существует или был перемещён.</p>
  <?php if (!empty($user)): ?>
    <a href="<?= htmlspecialchars($basePath) ?>/" class="btn btn-primary">Вернуться на главную</a>
  <?php else: ?>
    <a href="<?= htmlspecialchars($basePath) ?>/login" class="btn btn-primary">Войти в систему</a>
  <?php endif; ?>
</div>

</body>
</html>
