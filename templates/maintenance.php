<?php
$basePath = $basePath ?? '';
$appName = $appName ?? '';
$maintenanceEnd = $maintenanceEnd ?? '';
$maintenanceTitle = $maintenanceTitle ?? 'Проводятся технические работы';
$maintenanceMessage = $maintenanceMessage ?? '';
$user = $user ?? [];
$endLabel = $maintenanceEnd !== ''
  ? (new DateTimeImmutable($maintenanceEnd))->format('d.m.Y H:i')
  : '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($maintenanceTitle) ?></title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
</head>

<body>
  <?php $headerSimple = true; require __DIR__ . '/partials/header.php'; ?>
  <main class="maintenance-page">
    <div class="maintenance-code">503</div>
    <h1><?= htmlspecialchars($maintenanceTitle) ?></h1>
    <p><?= nl2br(htmlspecialchars($maintenanceMessage)) ?></p>
    <?php if ($endLabel !== ''): ?>
      <div class="maintenance-end">Планируемое окончание: <?= htmlspecialchars($endLabel) ?></div><?php endif; ?>
    <div class="maintenance-actions">
      <button type="button" class="btn btn-primary" onclick="window.location.reload()">Обновить страницу</button>
    </div>
  </main>
</body>

</html>
