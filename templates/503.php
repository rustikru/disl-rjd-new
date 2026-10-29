<?php
$basePath = $basePath ?? '';
$appName = $appName ?? '';
$user = $user ?? [];
$headerSub = $headerSub ?? '';
$headerRight = $headerRight ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Раздел временно недоступен</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .unavailable-block {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: calc(100vh - 56px);
      padding: 40px 20px;
      text-align: center;
    }
    .unavailable-code {
      color: var(--accent, #46297f);
      font-size: 72px;
      font-weight: 800;
      line-height: 1;
      opacity: .16;
    }
    .unavailable-title {
      margin: -8px 0 8px;
      color: var(--text-1, #1b1726);
      font-size: 20px;
    }
    .unavailable-text {
      margin: 0;
      color: var(--text-3, #6b667a);
      font-size: 14px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/header.php'; ?>
  <main class="unavailable-block">
    <div class="unavailable-code">503</div>
    <h1 class="unavailable-title">Раздел временно недоступен</h1>
    <p class="unavailable-text">Раздел отключён на время тестирования.</p>
  </main>
</body>
</html>
