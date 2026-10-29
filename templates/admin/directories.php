<?php $basePath = $basePath ?? ''; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Справочники</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .directory-title { margin-bottom:8px; font-size:18px; font-weight:700; color:var(--text-1); }
    .directory-note { color:var(--text-3); font-size:13px; }
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Справочники</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную" aria-label="На главную"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = ''; include __DIR__ . '/../partials/directories-sidebar.php'; ?>
  <main class="main-content">
    <div class="directory-title">Справочники</div>
    <div class="directory-note">Выберите нужный справочник в меню слева.</div>
  </main>
</div>
</body>
</html>
