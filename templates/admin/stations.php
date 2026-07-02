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
    .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:visible; }
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
    .data-table td { padding:10px 14px; border-bottom:1px solid var(--border); font-size:13px; vertical-align:middle; color:var(--text-1); }
    .data-table tbody tr:last-child td { border-bottom:none; }
    .data-table tbody tr:hover td { background:var(--hover-green,#f5f4f9); }
    .station-code { font-weight:700; font-family:var(--mono, monospace); color:var(--accent); }
    .station-muted { color:var(--text-3); }
    .actions-cell { white-space:nowrap; display:flex; justify-content:flex-end; gap:4px; align-items:center; }
    .inline-form { display:contents; }
    .icon-btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:30px;
      height:30px;
      border:none;
      border-radius:8px;
      background:transparent;
      cursor:pointer;
      color:var(--text-3);
      transition:background .15s, color .15s;
      padding:0;
      flex-shrink:0;
    }
    .icon-btn--edit:hover { background:var(--hover-green); color:var(--accent); }
    .icon-btn--sync:hover { background:#e4eefa; color:var(--brand-blue); }
    .btn-del {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:30px;
      height:30px;
      border:none;
      border-radius:8px;
      background:transparent;
      cursor:pointer;
      color:var(--text-3);
      transition:background .15s, color .15s;
      padding:0;
      flex-shrink:0;
    }
    .btn-del:hover { background:#fbeaea; color:var(--brand-neg,#d94040); }
    .empty { padding:28px; text-align:center; color:var(--text-3); font-size:13px; }
    .pager { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 18px; border-top:1px solid var(--border); color:var(--text-2); font-size:12.5px; }
    .pager-actions { display:flex; gap:8px; align-items:center; }
    .pager .btn[aria-disabled="true"] { opacity:.45; pointer-events:none; }
    .modal-wrap { position:fixed; inset:0; background:rgba(27,23,38,.45); display:none; align-items:center; justify-content:center; z-index:200; }
    .modal-wrap.open { display:flex; }
    .modal { background:var(--surface); border-radius:14px; width:520px; max-width:92vw; overflow:hidden; box-shadow:0 24px 60px rgba(27,23,38,.25); }
    .modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
    .modal-head .t { font-size:15px; font-weight:700; }
    .modal-x { cursor:pointer; color:var(--text-3); font-size:18px; line-height:1; background:none; border:none; }
    .modal-x:hover { color:var(--text-1); }
    .modal-body { padding:20px; display:flex; flex-direction:column; gap:14px; }
    .fg2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .modal-foot { padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }
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
                  <span class="station-code"><?= htmlspecialchars($code) ?></span>
                </td>
                <td><?= htmlspecialchars($name) ?></td>
                <td><?= $lat !== null && $lat !== '' ? htmlspecialchars((string) $lat) : '<span class="station-muted">—</span>' ?></td>
                <td><?= $lon !== null && $lon !== '' ? htmlspecialchars((string) $lon) : '<span class="station-muted">—</span>' ?></td>
                <td>
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>" form="<?= htmlspecialchars($deleteFormId) ?>">
                  <input type="hidden" name="esr_code" value="<?= htmlspecialchars($code) ?>" form="<?= htmlspecialchars($deleteFormId) ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>" form="<?= htmlspecialchars($importFormId) ?>">
                  <input type="hidden" name="esr_code" value="<?= htmlspecialchars($code) ?>" form="<?= htmlspecialchars($importFormId) ?>">
                  <div class="actions-cell">
                    <button type="button"
                            class="icon-btn icon-btn--edit"
                            title="Редактировать"
                            data-edit-station
                            data-code="<?= htmlspecialchars($code) ?>"
                            data-name="<?= htmlspecialchars($name) ?>"
                            data-latitude="<?= htmlspecialchars((string) $lat) ?>"
                            data-longitude="<?= htmlspecialchars((string) $lon) ?>">
                      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.5 1.5a1.414 1.414 0 0 1 2 2L4 11l-3 1 1-3Z"/>
                      </svg>
                    </button>
                    <button type="submit" class="icon-btn icon-btn--sync" form="<?= htmlspecialchars($importFormId) ?>" title="Загрузить из FreiCON">
                      <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.5 2.5v3h-3"/>
                        <path d="M2.7 6a4.5 4.5 0 0 1 7.8-2.2l2 1.7"/>
                        <path d="M2.5 12.5v-3h3"/>
                        <path d="M12.3 9a4.5 4.5 0 0 1-7.8 2.2l-2-1.7"/>
                      </svg>
                    </button>
                    <button type="submit" class="btn-del" form="<?= htmlspecialchars($deleteFormId) ?>" title="Удалить" onclick="return confirm('Удалить станцию «<?= htmlspecialchars(addslashes($name), ENT_QUOTES) ?>»?')">
                      <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 3.5 14 3.5"/>
                        <path d="M5 3.5V2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v1"/>
                        <path d="M2.5 3.5l.9 9a1 1 0 0 0 1 .9h6.2a1 1 0 0 0 1-.9l.9-9"/>
                      </svg>
                    </button>
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

<div class="modal-wrap" id="stationModal">
  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/stations/save" class="modal">
    <div class="modal-head">
      <span class="t">Редактирование станции</span>
      <button type="button" class="modal-x" data-close-modal="stationModal">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="fg2">
        <div class="fg">
          <label for="edit_esr">Код ЕСР</label>
          <input id="edit_esr" name="esr_code" required maxlength="20">
        </div>
        <div class="fg">
          <label for="edit_name">Станция</label>
          <input id="edit_name" name="station_name" required maxlength="255">
        </div>
      </div>
      <div class="fg2">
        <div class="fg">
          <label for="edit_lat">Широта</label>
          <input id="edit_lat" name="latitude" inputmode="decimal">
        </div>
        <div class="fg">
          <label for="edit_lon">Долгота</label>
          <input id="edit_lon" name="longitude" inputmode="decimal">
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" data-close-modal="stationModal">Отмена</button>
      <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
  </form>
</div>

<script>
  function openStationModal(button) {
    document.getElementById('edit_esr').value = button.dataset.code || '';
    document.getElementById('edit_name').value = button.dataset.name || '';
    document.getElementById('edit_lat').value = button.dataset.latitude || '';
    document.getElementById('edit_lon').value = button.dataset.longitude || '';
    document.getElementById('stationModal').classList.add('open');
  }

  function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('open');
    }
  }

  document.querySelectorAll('[data-edit-station]').forEach(function (button) {
    button.addEventListener('click', function () {
      openStationModal(button);
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      closeModal(button.dataset.closeModal);
    });
  });

  document.querySelectorAll('.modal-wrap').forEach(function (modal) {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        modal.classList.remove('open');
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      document.querySelectorAll('.modal-wrap').forEach(function (modal) {
        modal.classList.remove('open');
      });
    }
  });
</script>

</body>
</html>
