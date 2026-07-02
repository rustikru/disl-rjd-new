<?php
$basePath = $basePath ?? '';
$search = $search ?? '';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$totalStations = $totalStations ?? count($stations ?? []);
$perPage = $perPage ?? 50;
$fromRow = $totalStations > 0 ? (($page - 1) * $perPage + 1) : 0;
$toRow = min($totalStations, $page * $perPage);
$pageUrl = function (int $targetPage) use ($basePath, $search): string {
    $params = ['page' => $targetPage];
    if ($search !== '') {
        $params['q'] = $search;
    }
    return $basePath . '/admin/directories/stations?' . http_build_query($params);
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Станции</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .admin-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
    .admin-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .admin-title small { display:block; font-size:12px; font-weight:500; color:var(--text-3); margin-top:2px; }
    .flash { padding:10px 14px; border-radius:9px; font-size:13px; margin-bottom:16px; }
    .flash-ok { background:#e8f6ef; color:var(--brand-green); border:1px solid #bfe6d2; }
    .flash-err { background:#fbecec; color:var(--brand-neg); border:1px solid #f0c9c9; }
    .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .panel + .panel { margin-top:18px; }
    .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .panel-title { font-size:14px; font-weight:600; }
    .station-form { display:grid; grid-template-columns:130px minmax(220px, 1fr) 140px 140px auto; gap:10px; padding:16px 18px; align-items:end; }
    .freicon-form { display:grid; grid-template-columns:180px auto 1fr; gap:10px; padding:16px 18px; align-items:end; }
    .fg { display:flex; flex-direction:column; gap:5px; }
    .fg label { font-size:12px; font-weight:600; color:var(--text-2); }
    .fg input { border:1px solid var(--border); border-radius:8px; padding:8px 10px; font-family:inherit; font-size:13px; color:var(--text-1); outline:none; width:100%; box-sizing:border-box; }
    .fg input:focus { border-color:var(--accent); }
    .search-row { display:flex; gap:8px; align-items:center; }
    .search-input { border:1px solid var(--border); border-radius:9px; padding:7px 12px; font-family:inherit; font-size:13px; outline:none; color:var(--text-1); width:300px; max-width:55vw; }
    .search-input:focus { border-color:var(--accent); }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { padding:9px 14px; text-align:left; font-size:11px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table td { padding:9px 14px; border-bottom:1px solid var(--border); font-size:13px; vertical-align:middle; color:var(--text-1); }
    .data-table tbody tr:hover td { background:var(--hover-green,#f5f4f9); }
    .data-table input { border:1px solid var(--border); border-radius:8px; padding:6px 8px; font-family:inherit; font-size:12.5px; color:var(--text-1); width:100%; box-sizing:border-box; }
    .coord-input { max-width:130px; }
    .actions-cell { display:flex; justify-content:flex-end; gap:6px; align-items:center; }
    .inline-form { display:contents; }
    .empty { padding:28px; text-align:center; color:var(--text-3); font-size:13px; }
    .pager { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 18px; border-top:1px solid var(--border); color:var(--text-2); font-size:12.5px; }
    .pager-actions { display:flex; gap:8px; align-items:center; }
    .pager .btn[aria-disabled="true"] { opacity:.45; pointer-events:none; }
    @media (max-width: 980px) {
      .station-form { grid-template-columns:1fr 1fr; }
      .freicon-form { grid-template-columns:1fr; }
      .station-form .btn { align-self:end; }
    }
  </style>
</head>
<body>

<?php
  $headerSub   = '<div class="brand-sub">Администрирование</div>';
  $headerLeft  = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" id="backBtn" title="На главную"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
  $headerRight = '';
  include __DIR__ . '/../partials/header.php';
?>

<div class="app-body">
  <?php $activeAdminPage = 'stations'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>

  <main class="main-content">
    <div class="admin-head">
      <div class="admin-title">
        Станции и координаты
        <small>Справочник для карты</small>
      </div>
      <form method="GET" action="<?= htmlspecialchars($basePath) ?>/admin/directories/stations" class="search-row">
        <input type="search" class="search-input" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Код ЕСР или название станции">
        <button type="submit" class="btn btn-ghost">Найти</button>
      </form>
    </div>

    <?php if (!empty($flashOk)): ?>
      <div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashErr)): ?>
      <div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Добавить станцию</span>
      </div>
      <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/stations/save" class="station-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="fg">
          <label for="new_esr">Код ЕСР</label>
          <input id="new_esr" name="esr_code" required maxlength="20">
        </div>
        <div class="fg">
          <label for="new_name">Станция</label>
          <input id="new_name" name="station_name" required maxlength="255">
        </div>
        <div class="fg">
          <label for="new_lat">Широта</label>
          <input id="new_lat" name="latitude" inputmode="decimal" placeholder="58.0105">
        </div>
        <div class="fg">
          <label for="new_lon">Долгота</label>
          <input id="new_lon" name="longitude" inputmode="decimal" placeholder="56.2502">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Загрузить из FreiCON</span>
      </div>
      <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/stations/import-freicon" class="freicon-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="fg">
          <label for="freicon_esr">Код ЕСР</label>
          <input id="freicon_esr" name="esr_code" required maxlength="20" placeholder="000010">
        </div>
        <button type="submit" class="btn btn-primary">Загрузить</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Станции (<?= (int) $totalStations ?>)</span>
      </div>

      <?php if (empty($stations)): ?>
        <div class="empty">Записей нет</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:130px">Код ЕСР</th>
              <th>Станция</th>
              <th style="width:150px">Широта</th>
              <th style="width:150px">Долгота</th>
              <th style="width:1%"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($stations as $s): ?>
              <?php
                $code = (string) ($s['esr_code'] ?? '');
                $name = (string) ($s['station_name'] ?? '');
                $lat = $s['latitude'] ?? '';
                $lon = $s['longitude'] ?? '';
                $formId = 'station-save-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $code);
                $deleteFormId = 'station-delete-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $code);
                $importFormId = 'station-import-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $code);
              ?>
              <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/stations/save" id="<?= htmlspecialchars($formId) ?>"></form>
              <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/stations/delete" id="<?= htmlspecialchars($deleteFormId) ?>"></form>
              <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/stations/import-freicon" id="<?= htmlspecialchars($importFormId) ?>"></form>
              <tr>
                <td>
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>" form="<?= htmlspecialchars($formId) ?>">
                  <input name="esr_code" value="<?= htmlspecialchars($code) ?>" maxlength="20" required form="<?= htmlspecialchars($formId) ?>">
                </td>
                <td>
                  <input name="station_name" value="<?= htmlspecialchars($name) ?>" maxlength="255" required form="<?= htmlspecialchars($formId) ?>">
                </td>
                <td>
                  <input class="coord-input" name="latitude" inputmode="decimal" value="<?= htmlspecialchars((string) $lat) ?>" form="<?= htmlspecialchars($formId) ?>">
                </td>
                <td>
                  <input class="coord-input" name="longitude" inputmode="decimal" value="<?= htmlspecialchars((string) $lon) ?>" form="<?= htmlspecialchars($formId) ?>">
                </td>
                <td>
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>" form="<?= htmlspecialchars($deleteFormId) ?>">
                  <input type="hidden" name="esr_code" value="<?= htmlspecialchars($code) ?>" form="<?= htmlspecialchars($deleteFormId) ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>" form="<?= htmlspecialchars($importFormId) ?>">
                  <input type="hidden" name="esr_code" value="<?= htmlspecialchars($code) ?>" form="<?= htmlspecialchars($importFormId) ?>">
                  <div class="actions-cell">
                    <button type="submit" class="btn btn-primary btn-sm" form="<?= htmlspecialchars($formId) ?>">Сохранить</button>
                    <button type="submit" class="btn btn-ghost btn-sm" form="<?= htmlspecialchars($importFormId) ?>">FreiCON</button>
                    <button type="submit" class="btn btn-ghost btn-sm" form="<?= htmlspecialchars($deleteFormId) ?>" onclick="return confirm('Удалить станцию «<?= htmlspecialchars(addslashes($name), ENT_QUOTES) ?>»?')">Удалить</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="pager">
          <span>
            Показаны <?= (int) $fromRow ?>-<?= (int) $toRow ?> из <?= (int) $totalStations ?>,
            страница <?= (int) $page ?> из <?= (int) $totalPages ?>
          </span>
          <div class="pager-actions">
            <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($pageUrl(max(1, $page - 1))) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Назад</a>
            <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($pageUrl(min($totalPages, $page + 1))) ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Вперед</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

</body>
</html>
