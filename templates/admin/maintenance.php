<?php $basePath = $basePath ?? ''; require_once __DIR__ . '/../partials/ui.php'; ?>
<?php $statusLabels = ['off' => 'Выключен', 'scheduled' => 'Запланирован', 'active' => 'Активен', 'finished' => 'Завершён']; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Технические работы</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .maintenance-layout{max-width:900px}.maintenance-fields{grid-template-columns:repeat(3,minmax(0,1fr));align-items:start}
    .maintenance-fields input,.maintenance-fields select{box-sizing:border-box;width:100%;height:34px;padding:6px 9px}
    .maintenance-fields textarea{min-height:56px;max-height:120px;padding:7px 9px}
    @media(max-width:900px){.maintenance-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:650px){.maintenance-fields{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Администрирование</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'maintenance'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content ui-form-page">
    <div class="maintenance-layout">
    <div class="ui-page-head">
      <div class="ui-page-title">Технические работы<small>Временное ограничение доступа к сайту</small></div>
      <span class="ui-status<?= $maintenanceStatus === 'active' ? ' ui-status--on' : '' ?>"><?= htmlspecialchars($statusLabels[$maintenanceStatus] ?? 'Выключен') ?></span>
    </div>
    <?php uiNotices($success, $error); ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
      <section class="ui-card">
        <div class="ui-card-head">Параметры</div>
        <div class="ui-card-body">
          <div class="ui-fields maintenance-fields">
            <div class="ui-field">
              <label>Режим технических работ</label>
              <select name="settings[maintenance_enabled]">
                <option value="1" <?= !empty($values['maintenance_enabled']) ? 'selected' : '' ?>>Включено</option>
                <option value="0" <?= empty($values['maintenance_enabled']) ? 'selected' : '' ?>>Выключено</option>
              </select>
            </div>
            <div class="ui-field">
              <label>Начало</label>
              <input type="datetime-local" name="settings[maintenance_start]" value="<?= htmlspecialchars((string) ($values['maintenance_start'] ?? '')) ?>">
              <div class="ui-help">Пустое значение — включить сразу.</div>
            </div>
            <div class="ui-field">
              <label>Окончание</label>
              <input type="datetime-local" name="settings[maintenance_end]" value="<?= htmlspecialchars((string) ($values['maintenance_end'] ?? '')) ?>">
              <div class="ui-help">Пустое значение — без ограничения времени.</div>
            </div>
            <div class="ui-field ui-field--full">
              <label>Заголовок</label>
              <input type="text" name="settings[maintenance_title]" maxlength="160" required value="<?= htmlspecialchars((string) ($values['maintenance_title'] ?? '')) ?>">
            </div>
            <div class="ui-field ui-field--full">
              <label>Текст сообщения</label>
              <textarea name="settings[maintenance_message]" maxlength="1000" required><?= htmlspecialchars((string) ($values['maintenance_message'] ?? '')) ?></textarea>
            </div>
          </div>
        </div>
      </section>
      <div class="ui-form-actions"><button class="btn btn-primary" type="submit">Сохранить</button></div>
    </form>
    </div>
  </main>
</div>
</body>
</html>
