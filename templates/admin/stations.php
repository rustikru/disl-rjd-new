<?php
$basePath = $basePath ?? '';
$search = $search ?? '';
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$totalStations = $totalStations ?? count($stations ?? []);
$perPage = $perPage ?? 50;
$fromRow = $totalStations > 0 ? (($page - 1) * $perPage + 1) : 0;
$toRow = min($totalStations, $page * $perPage);
$pageUrl = static function (int $targetPage) use ($basePath, $search): string {
    $params = ['page' => $targetPage];
    if ($search !== '') {
        $params['q'] = $search;
    }
    return $basePath . '/directories/stations?' . http_build_query($params);
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Станции и координаты</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .page-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.page-title{font-size:18px;font-weight:700}.page-title small{display:block;font-size:12px;font-weight:500;color:var(--text-3);margin-top:2px}.flash{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px}.flash-ok{background:#e8f6ef;color:var(--brand-green);border:1px solid #bfe6d2}.flash-err{background:#fbecec;color:var(--brand-neg);border:1px solid #f0c9c9}.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}.panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);flex-wrap:wrap}.panel-title{font-size:14px;font-weight:600}.search-form{display:flex;align-items:center;gap:7px}.search-input{border:1px solid var(--border);border-radius:8px;padding:7px 11px;font:inherit;font-size:12px;width:260px;outline:none}.search-input:focus{border-color:var(--accent)}.search-form .btn{padding:7px 12px;font-size:12px}.table-wrap{overflow-x:auto}.data-table{width:100%;border-collapse:collapse;min-width:650px}.data-table th{padding:9px 11px;text-align:left;font-size:10px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;background:var(--row-head);border-bottom:1px solid var(--border);white-space:nowrap}.data-table td{padding:7px 11px;border-bottom:1px solid var(--border-lt);font-size:11.5px;vertical-align:middle}.data-table tbody tr:hover td{background:var(--hover-green)}.station-link{font-weight:650;color:var(--accent);text-decoration:none}.station-link:hover{text-decoration:underline}.station-code{font-family:var(--mono,monospace);font-weight:650}.muted{color:var(--text-3)}.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:0;border-radius:6px;background:transparent;color:var(--text-3);text-decoration:none}.icon-btn:hover{background:var(--hover-green);color:var(--accent)}.empty{padding:24px;text-align:center;color:var(--text-3);font-size:12px}.pagination-wrap{border-top:1px solid var(--border-lt)}.pagination-info{text-align:center;padding:9px 12px 0;color:var(--text-3);font-size:11px}@media(max-width:700px){.page-head{align-items:flex-start}.search-form{width:100%}.search-input{width:100%}}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Справочники</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/directories/" class="btn-nav-back" title="К справочникам">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'stations'; include __DIR__ . '/../partials/directories-sidebar.php'; ?>
  <main class="main-content">
    <div class="page-head">
      <div class="page-title">Станции и координаты<small>Справочник для карты</small></div>
      <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/directories/stations/new">+ Станция</a><?php endif; ?>
    </div>
    <?php if (!empty($flashOk)): ?><div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <section class="panel">
      <div class="panel-head">
        <span class="panel-title">Станции (<?= (int) $totalStations ?>)</span>
        <form method="GET" action="<?= htmlspecialchars($basePath) ?>/directories/stations" class="search-form">
          <input type="search" class="search-input" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Код ЕСР или название станции">
          <button type="submit" class="btn btn-ghost">Найти</button>
        </form>
      </div>
      <?php if (empty($stations)): ?>
        <div class="empty">Станции не найдены</div>
      <?php else: ?>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Код ЕСР</th><th>Станция</th><th>Широта</th><th>Долгота</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($stations as $station): ?>
            <?php $code = (string) ($station['esr_code'] ?? ''); ?>
            <tr>
              <td><span class="station-code"><?= htmlspecialchars($code) ?></span></td>
              <td><?php if ($canEdit): ?><a class="station-link" href="<?= htmlspecialchars($basePath) ?>/directories/stations/<?= rawurlencode($code) ?>/edit"><?= htmlspecialchars((string) ($station['station_name'] ?? '')) ?></a><?php else: ?><?= htmlspecialchars((string) ($station['station_name'] ?? '')) ?><?php endif; ?></td>
              <td><?= $station['latitude'] !== null && $station['latitude'] !== '' ? htmlspecialchars((string) $station['latitude']) : '<span class="muted">—</span>' ?></td>
              <td><?= $station['longitude'] !== null && $station['longitude'] !== '' ? htmlspecialchars((string) $station['longitude']) : '<span class="muted">—</span>' ?></td>
              <?php if ($canEdit): ?><td><a class="icon-btn" href="<?= htmlspecialchars($basePath) ?>/directories/stations/<?= rawurlencode($code) ?>/edit" title="Редактировать" aria-label="Редактировать"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 1H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1M8 1v3h3L8 1Z"/><path d="M4 5h2M4 7h1m6-1 2 2-5 5H6v-2Z"/></svg></a></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="pagination-wrap"><div class="pagination-info">Показаны <?= (int) $fromRow ?>–<?= (int) $toRow ?> из <?= (int) $totalStations ?></div><?php $paginationPage=$page;$paginationPages=$totalPages;$paginationUrl=$pageUrl;$paginationLabel='Страницы списка станций';include __DIR__ . '/../partials/pagination.php'; ?></div>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
