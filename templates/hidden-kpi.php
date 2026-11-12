<?php require_once __DIR__ . '/partials/ui.php'; ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($appName) ?> — Скрытые KPI</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Настройки KPI</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
$headerRight = '';
include __DIR__ . '/partials/header.php';
?>
<div class="app-body">
  <main class="main-content ui-form-page">
    <div class="ui-page-head ui-page-head--form">
      <a class="ui-back-link" href="<?= htmlspecialchars($basePath) ?>/">← К карточкам KPI</a>
      <h1>Скрытые KPI</h1>
    </div>
    <?php if (!empty($flashErr)): ?><div class="ui-notice ui-notice--error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <section class="ui-panel">
      <div class="ui-panel-head"><span class="ui-panel-title">Скрытые карточки</span></div>
      <?php if ($rows): ?>
        <div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>Название</th><th>Страница</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
          <?php $page = (string) ($row['page_code'] ?? ''); if ($page === '') $page = (string) (\App\Services\Navigation\PageRegistry::kpiSourcePage((string) ($row['type'] ?? '')) ?? ''); $pageLabel = \App\Reports\KpiTypeRegistry::pageLabel($page) ?? 'KPI'; ?>
          <tr><td><?= htmlspecialchars((string) $row['label']) ?></td><td><?= htmlspecialchars($pageLabel) ?> · <?= ($row['is_system'] ?? 'N') === 'Y' ? 'Системная' : 'Общая' ?></td><td><button class="btn btn-ghost btn-sm kpi-restore" type="button" data-kpi-id="<?= (int) $row['id'] ?>">Вернуть</button></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php else: ?>
        <div class="ui-card-body">У вас нет скрытых KPI.</div>
      <?php endif; ?>
    </section>
  </main>
</div>
<script>
window.APP_BASE = <?= json_encode($basePath, JSON_UNESCAPED_UNICODE) ?>;
window.APP_CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
<script>
$(document).on('click', '.kpi-restore', function () {
  var button = this;
  $.post(window.APP_BASE + '/api/kpi/card/' + Number($(button).data('kpi-id')) + '/restore', {csrf_token: window.APP_CSRF})
    .done(function () { window.location.reload(); })
    .fail(function (xhr) { window.alert((xhr.responseJSON && xhr.responseJSON.error) || 'Не удалось вернуть KPI'); });
});
</script>
</body>
</html>
