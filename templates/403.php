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
  <title>403 — Нет доступа</title>
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
    }
  </style>
</head>

<body>

  <?php include __DIR__ . '/partials/header.php'; ?>

  <div class="error-block">
    <div class="error-num">403</div>
    <h1 class="error-title">Доступ ограничен</h1>

    <p class="error-desc">
      Доступ к запрашиваемому ресурсу ограничен.
      <?php if (!empty($userName)): ?>
        Ваша текущая учетная запись (<b><?= $userName ?></b>) не обладает необходимыми привилегиями для просмотра этой
        страницы.
      <?php else: ?>
        Ваша текущая учетная запись не обладает необходимыми привилегиями для просмотра этой страницы.
      <?php endif; ?>
      <br><br>
      Обратитесь в техподдержку.
    </p>

  </div>



</body>

</html>