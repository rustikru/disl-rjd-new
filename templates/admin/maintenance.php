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
    .maintenance-layout{max-width:900px}.maintenance-layout .ui-card-body{padding:4px 24px 8px}.maintenance-row{display:grid;grid-template-columns:minmax(210px,38%) minmax(260px,1fr);gap:14px;align-items:center;min-height:43px;padding:4px 0;border-bottom:1px solid var(--border-lt)}.maintenance-row:last-child{border-bottom:0}.maintenance-name{color:var(--text-2);font-size:12px;font-weight:650;line-height:16px}.maintenance-control{min-width:0}.maintenance-control input,.maintenance-control select,.maintenance-control textarea{box-sizing:border-box;width:100%;min-width:0;padding:5px 9px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text-1);font:inherit;font-size:12px;outline:none;box-shadow:none}.maintenance-control input,.maintenance-control select{height:32px}.maintenance-control textarea{min-height:52px;max-height:120px;resize:vertical}.maintenance-control input:focus,.maintenance-control select:focus,.maintenance-control textarea:focus{border-color:#c8bedb;box-shadow:0 0 0 2px var(--accent-lt)}.maintenance-control .ui-help{margin-top:2px;font-size:10px;line-height:13px;color:var(--text-3)}
    @media(max-width:650px){.maintenance-row{grid-template-columns:1fr;gap:3px;padding:7px 0}.maintenance-control input,.maintenance-control select{height:34px}}
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
          <div class="maintenance-row">
            <label class="maintenance-name" for="maintenance-enabled">Режим технических работ</label>
            <div class="maintenance-control">
              <select id="maintenance-enabled" name="settings[maintenance_enabled]">
                <option value="1" <?= !empty($values['maintenance_enabled']) ? 'selected' : '' ?>>Включено</option>
                <option value="0" <?= empty($values['maintenance_enabled']) ? 'selected' : '' ?>>Выключено</option>
              </select>
            </div>
          </div>
          <div class="maintenance-row">
            <label class="maintenance-name" for="maintenance-start">Начало</label>
            <div class="maintenance-control">
              <input id="maintenance-start" type="datetime-local" name="settings[maintenance_start]" value="<?= htmlspecialchars((string) ($values['maintenance_start'] ?? '')) ?>">
              <div class="ui-help">Пустое значение — включить сразу.</div>
            </div>
          </div>
          <div class="maintenance-row">
            <label class="maintenance-name" for="maintenance-end">Окончание</label>
            <div class="maintenance-control">
              <input id="maintenance-end" type="datetime-local" name="settings[maintenance_end]" value="<?= htmlspecialchars((string) ($values['maintenance_end'] ?? '')) ?>">
              <div class="ui-help">Пустое значение — без ограничения времени.</div>
            </div>
          </div>
          <div class="maintenance-row">
            <label class="maintenance-name" for="maintenance-title">Заголовок</label>
            <div class="maintenance-control">
              <input id="maintenance-title" type="text" name="settings[maintenance_title]" maxlength="160" required value="<?= htmlspecialchars((string) ($values['maintenance_title'] ?? '')) ?>">
            </div>
          </div>
          <div class="maintenance-row">
            <label class="maintenance-name" for="maintenance-message">Текст сообщения</label>
            <div class="maintenance-control">
              <textarea id="maintenance-message" name="settings[maintenance_message]" maxlength="1000" required><?= htmlspecialchars((string) ($values['maintenance_message'] ?? '')) ?></textarea>
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
