<?php
$basePath = $basePath ?? '';
$appName = $appName ?? '';
$user = $user ?? [];
$headerSub = $headerSub ?? '';
$headerRight = $headerRight ?? '';
$errorId = $errorId ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>База данных временно недоступна</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .database-error {
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      min-height:calc(100vh - 56px);
      padding:40px 20px;
      text-align:center;
    }
    .database-error-code {
      color:var(--accent, #46297f);
      font-size:72px;
      font-weight:800;
      line-height:1;
      opacity:.16;
    }
    .database-error-title {
      margin:-8px 0 8px;
      color:var(--text-1, #1b1726);
      font-size:20px;
    }
    .database-error-text {
      max-width:440px;
      margin:0 0 20px;
      color:var(--text-3, #6b667a);
      font-size:14px;
      line-height:1.6;
    }
    .database-error-id {
      margin-top:14px;
      color:var(--text-3, #6b667a);
      font-size:12px;
    }
  </style>
</head>
<body>
  <?php if ($user !== []): ?>
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

  <main class="database-error">
    <div class="database-error-code">503</div>
    <h1 class="database-error-title">База данных временно недоступна</h1>
    <p class="database-error-text">
      Не удалось получить данные. Попробуйте обновить страницу немного позже.
    </p>
    <button type="button" class="btn btn-primary" onclick="window.location.reload()">Обновить страницу</button>
    <?php if ($errorId !== ''): ?>
      <div class="database-error-id">Код ошибки: <?= htmlspecialchars($errorId) ?></div>
    <?php endif; ?>
  </main>
</body>
</html>
