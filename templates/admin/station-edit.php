<?php $basePath = $basePath ?? ''; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — <?= $isNewStation ? 'Новая станция' : 'Редактирование станции' ?></title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .page-head{margin-bottom:16px}.page-head h1{font-size:19px;margin:10px 0 0}.back-link{display:inline-flex;padding:7px 11px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--accent);text-decoration:none;font-size:12px;font-weight:600}.back-link:hover{border-color:var(--accent);background:var(--accent-lt)}.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;max-width:900px}.card-head{padding:13px 17px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700}.card-body{padding:17px}.fields{display:grid;grid-template-columns:220px 1fr;gap:13px}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.04em}.field input{border:1px solid var(--border);border-radius:8px;padding:9px 11px;font:inherit;font-size:13px;background:var(--surface);outline:none}.field input:focus{border-color:var(--accent)}.field input[readonly]{background:var(--row-head);color:var(--text-2)}.help{font-size:10.5px;color:var(--text-3)}.flash{max-width:872px;padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px}.flash-ok{background:#e8f6ef;color:var(--brand-green);border:1px solid #bfe6d2}.flash-err{background:#fbecec;color:var(--brand-neg);border:1px solid #f0c9c9}.form-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:900px;margin-top:16px}.primary-actions{display:flex;align-items:center;gap:8px}.btn-danger{display:inline-flex;align-items:center;gap:6px;color:var(--brand-neg);border:1px solid #efc8c8;background:var(--surface);font-size:12px;padding:7px 11px}.btn-danger:hover{background:#fbecec;border-color:var(--brand-neg)}@media(max-width:650px){.fields{grid-template-columns:1fr}.form-actions{align-items:stretch}.primary-actions{justify-content:flex-end}}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Справочники</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/directories/stations" class="btn-nav-back" title="К станциям">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'stations'; include __DIR__ . '/../partials/directories-sidebar.php'; ?>
  <main class="main-content">
    <div class="page-head"><a class="back-link" href="<?= htmlspecialchars($basePath) ?>/directories/stations">← К списку станций</a><h1><?= $isNewStation ? 'Новая станция' : 'Редактирование станции' ?></h1></div>
    <?php if (!empty($flashOk)): ?><div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars($basePath) ?>/directories/stations/save">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="is_new" value="<?= $isNewStation ? '1' : '0' ?>">
      <section class="card">
        <div class="card-head">Станция</div>
        <div class="card-body"><div class="fields">
          <div class="field"><label>Код ЕСР</label><input type="text" name="esr_code" maxlength="20" value="<?= htmlspecialchars((string) ($editedStation['esr_code'] ?? '')) ?>" <?= $isNewStation ? '' : 'readonly' ?> required><?php if (!$isNewStation): ?><span class="help">Код является идентификатором станции</span><?php endif; ?></div>
          <div class="field"><label>Станция</label><input type="text" name="station_name" maxlength="255" value="<?= htmlspecialchars((string) ($editedStation['station_name'] ?? '')) ?>" required></div>
          <div class="field"><label>Широта</label><input type="text" name="latitude" inputmode="decimal" placeholder="58.0105" value="<?= htmlspecialchars((string) ($editedStation['latitude'] ?? '')) ?>"></div>
          <div class="field"><label>Долгота</label><input type="text" name="longitude" inputmode="decimal" placeholder="56.2502" value="<?= htmlspecialchars((string) ($editedStation['longitude'] ?? '')) ?>"></div>
        </div></div>
      </section>
      <div class="form-actions">
        <?php if (!$isNewStation): ?><button class="btn btn-danger" type="submit" form="deleteStationForm" onclick="return confirm('Удалить станцию?')" title="Удалить станцию"><svg width="14" height="14" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 3.5 14 3.5"/><path d="M5 3.5V2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v1"/><path d="M2.5 3.5l.9 9a1 1 0 0 0 1 .9h6.2a1 1 0 0 0 1-.9l.9-9"/></svg><span>Удалить</span></button><?php else: ?><span></span><?php endif; ?>
        <div class="primary-actions"><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/directories/stations">Отмена</a><button class="btn btn-primary" type="submit"><?= $isNewStation ? 'Создать' : 'Сохранить' ?></button></div>
      </div>
    </form>
    <?php if (!$isNewStation): ?><form id="deleteStationForm" method="POST" action="<?= htmlspecialchars($basePath) ?>/directories/stations/delete"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="esr_code" value="<?= htmlspecialchars((string) $editedStation['esr_code']) ?>"></form><?php endif; ?>
  </main>
</div>
</body>
</html>
