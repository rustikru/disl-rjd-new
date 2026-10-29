<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';
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
    .station-link{font-weight:400;color:var(--accent);text-decoration:none}.station-link:hover{text-decoration:underline}.station-table{min-width:650px}
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
    <div class="ui-page-head">
      <div class="ui-page-title">Станции и координаты<small>Справочник для карты</small></div>
      <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/directories/stations/new">+ Станция</a><?php endif; ?>
    </div>
    <?php uiNotices($flashOk ?? null, $flashErr ?? null); ?>
    <section class="ui-panel">
      <div class="ui-panel-head">
        <span class="ui-panel-title">Станции (<?= (int) $totalStations ?>)</span>
        <form method="GET" action="<?= htmlspecialchars($basePath) ?>/directories/stations" class="ui-search">
          <input type="search" class="ui-search-input" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Код ЕСР или название станции">
          <button type="submit" class="btn btn-ghost">Найти</button>
        </form>
      </div>
      <?php if (empty($stations)): ?>
        <div class="ui-empty">Станции не найдены</div>
      <?php else: ?>
        <div class="ui-table-wrap"><table class="ui-table station-table">
          <thead><tr><th>Код ЕСР</th><th>Станция</th><th>Широта</th><th>Долгота</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($stations as $station): ?>
            <?php $code = (string) ($station['esr_code'] ?? ''); ?>
            <tr>
              <td><span class="ui-code"><?= htmlspecialchars($code) ?></span></td>
              <td><?php if ($canEdit): ?><a class="station-link" href="<?= htmlspecialchars($basePath) ?>/directories/stations/<?= rawurlencode($code) ?>/edit"><?= htmlspecialchars((string) ($station['station_name'] ?? '')) ?></a><?php else: ?><?= htmlspecialchars((string) ($station['station_name'] ?? '')) ?><?php endif; ?></td>
              <td><?= $station['latitude'] !== null && $station['latitude'] !== '' ? htmlspecialchars((string) $station['latitude']) : '<span class="ui-muted">—</span>' ?></td>
              <td><?= $station['longitude'] !== null && $station['longitude'] !== '' ? htmlspecialchars((string) $station['longitude']) : '<span class="ui-muted">—</span>' ?></td>
              <?php if ($canEdit): ?><td><a class="ui-icon-button ui-icon-button--edit" href="<?= htmlspecialchars($basePath) ?>/directories/stations/<?= rawurlencode($code) ?>/edit" title="Редактировать" aria-label="Редактировать"><?= uiEditIcon() ?></a></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="ui-pagination-wrap"><div class="ui-pagination-info">Показаны <?= (int) $fromRow ?>–<?= (int) $toRow ?> из <?= (int) $totalStations ?></div><?php $paginationPage=$page;$paginationPages=$totalPages;$paginationUrl=$pageUrl;$paginationLabel='Страницы списка станций';include __DIR__ . '/../partials/pagination.php'; ?></div>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
