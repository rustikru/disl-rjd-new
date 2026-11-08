<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($appName) ?> — Управление KPI</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .kpi-admin-panel{min-width:0}
    .kpi-admin-filters{display:grid;grid-template-columns:minmax(220px,1.4fr) minmax(150px,.7fr) minmax(180px,.8fr);gap:10px;padding:14px 16px;border-bottom:1px solid var(--border)}
    .kpi-admin-filters input,.kpi-admin-filters select{box-sizing:border-box;width:100%;min-width:0;height:36px}
    .kpi-admin-code{display:block;color:var(--text-3);font:11px ui-monospace,monospace;margin-top:3px}
    .kpi-admin-actions{display:flex;gap:6px;justify-content:flex-end;align-items:center;white-space:nowrap}
    .kpi-admin-actions .btn{padding:6px 9px;font-size:11px}
    .kpi-admin-edit{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;min-width:440px;padding:4px}
    .kpi-admin-edit .wide{grid-column:1/-1}
    .kpi-admin-edit .ui-field{min-width:0}
    .kpi-admin-table{min-width:1080px}
    .kpi-admin-table th,.kpi-admin-table td{padding:10px 12px}
    .kpi-admin-table th:last-child,.kpi-admin-table td:last-child{position:sticky;right:0;min-width:245px;background:var(--surface)}
    .kpi-admin-table th:last-child{background:var(--row-head)}
    .kpi-admin-table tbody tr:hover td{background:var(--surface)}
    .kpi-admin-table tbody tr:hover td:last-child{background:var(--surface)}
    .kpi-admin-kind,.kpi-admin-detail,.kpi-admin-state{color:var(--text-2);white-space:nowrap}
    .kpi-admin-name{font-weight:700;overflow-wrap:anywhere}
    .kpi-admin-empty{padding:24px!important;text-align:center;color:var(--text-3)}
    @media(max-width:800px){.kpi-admin-filters{grid-template-columns:1fr 1fr}.kpi-admin-filters input{grid-column:1/-1}}
    @media(max-width:700px){.kpi-admin-edit{grid-template-columns:1fr;min-width:0}.kpi-admin-edit .wide{grid-column:auto}}
    @media(max-width:480px){.kpi-admin-filters{grid-template-columns:1fr}.kpi-admin-filters input{grid-column:auto}}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Администрирование</div>';
$headerLeft = '';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'kpi'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content">
    <div class="ui-page-head"><div class="ui-page-title">Управление KPI<small>Системные, общие и личные показатели</small></div><div class="ui-page-actions"><a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/kpi/create?kpi_type=approach_kpi&amp;admin=1">Новый KPI подхода</a><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/kpi/create?kpi_type=departue_kpi&amp;admin=1">Новый KPI отправления</a></div></div>
    <?php if (!empty($_GET['saved'])): ?><div class="ui-notice ui-notice--success">Изменения сохранены.</div><?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?><div class="ui-notice ui-notice--success">KPI удалён.</div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="ui-notice ui-notice--error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <section class="ui-panel kpi-admin-panel">
      <div class="ui-panel-head"><span class="ui-panel-title">KPI (<?= count($rows) ?>)</span></div>
      <div class="kpi-admin-filters"><input class="ui-search-input" id="kpiSearch" type="search" placeholder="Поиск по названию или коду"><select class="filter-select" id="kpiType"><option value="">Все типы</option><option value="SYSTEM">Системные</option><option value="GLOBAL">Общие</option><option value="PRIVATE">Личные</option></select><select class="filter-select" id="kpiPage"><option value="">Все страницы</option><option value="approach">Подход вагонов</option><option value="departure">Отправление вагонов</option></select></div>
      <div class="ui-table-wrap"><table class="ui-table kpi-admin-table"><thead><tr><th>KPI / Код</th><th>Тип</th><th>Страница</th><th>Детализация</th><th>Владелец</th><th>Статус</th><th></th></tr></thead><tbody id="kpiRows">
      <?php foreach ($rows as $row): $system = ($row['is_system'] ?? 'N') === 'Y'; $kind = $system ? 'SYSTEM' : (($row['visibility'] ?? 'PRIVATE') === 'GLOBAL' ? 'GLOBAL' : 'PRIVATE'); $page = ($row['page_code'] ?? '') === 'approach' ? 'Подход вагонов' : (($row['page_code'] ?? '') === 'departure' ? 'Отправление вагонов' : '—'); $detail = 'Таблица'; if (!$system) { $cfg = json_decode((string) ($row['where_config'] ?? ''), true); $template = strtoupper((string) ($cfg['detail']['template'] ?? '')); $detail = $template === 'TRAIN_NUMBER' ? 'Номер поезда' : ($template === 'TRAIN_INDEX' ? 'Индекс поезда' : 'Таблица'); } ?>
        <tr data-search="<?= htmlspecialchars(mb_strtolower((string) $row['label'] . ' ' . (string) $row['code'])) ?>" data-type="<?= $kind ?>" data-page="<?= htmlspecialchars((string) ($row['page_code'] ?? '')) ?>">
          <td><strong class="kpi-admin-name"><?= htmlspecialchars((string) ($row['label'] ?? '')) ?></strong><span class="kpi-admin-code"><?= htmlspecialchars((string) $row['code']) ?></span></td>
          <td class="kpi-admin-kind"><?= $kind === 'SYSTEM' ? 'Системная' : ($kind === 'GLOBAL' ? 'Общая' : 'Личная') ?></td><td><?= htmlspecialchars($page) ?></td><td class="kpi-admin-detail"><?= htmlspecialchars($system ? 'Системная детализация' : $detail) ?></td><td><?= htmlspecialchars((string) ($system ? 'Система' : ($row['owner_name'] ?? '—'))) ?></td>
          <td class="kpi-admin-state"><?= ($row['is_active'] ?? 'Y') === 'Y' ? 'Активен' : 'Отключён' ?></td>
          <td><div class="kpi-admin-actions">
            <?php if ($system): ?><button class="btn btn-ghost btn-sm" type="button" onclick="document.getElementById('edit-<?= (int) $row['id'] ?>').hidden=false">Изменить</button>
            <?php else: ?><a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($basePath) ?>/kpi/edit?kpi_type=<?= rawurlencode((string) $row['type']) ?>&id=<?= (int) $row['id'] ?>&admin=1">Изменить</a><?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($basePath) ?>/admin/kpi/save"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="label" value="<?= htmlspecialchars((string) $row['label']) ?>"><input type="hidden" name="order_by" value="<?= (int) ($row['order_by'] ?? 0) ?>"><input type="hidden" name="trend_period" value="<?= htmlspecialchars((string) ($row['trend_period'] ?? '')) ?>"><input type="hidden" name="trend_unit" value="<?= htmlspecialchars((string) ($row['trend_unit'] ?? 'шт.')) ?>"><input type="hidden" name="is_active" value="<?= ($row['is_active'] ?? 'Y') === 'Y' ? 'N' : 'Y' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= ($row['is_active'] ?? 'Y') === 'Y' ? 'Отключить' : 'Включить' ?></button></form>
            <?php if (!$system): ?><form method="post" action="<?= htmlspecialchars($basePath) ?>/admin/kpi/delete" onsubmit="return confirm('Удалить KPI «<?= htmlspecialchars((string) $row['label'], ENT_QUOTES) ?>»?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Удалить</button></form><?php endif; ?>
          </div></td>
        </tr>
        <?php if ($system): ?><tr id="edit-<?= (int) $row['id'] ?>" hidden><td colspan="7"><form class="kpi-admin-edit" method="post" action="<?= htmlspecialchars($basePath) ?>/admin/kpi/save"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><div class="ui-field"><label>Название</label><input name="label" maxlength="150" required value="<?= htmlspecialchars((string) $row['label']) ?>"></div><div class="ui-field"><label>Порядок</label><input name="order_by" type="number" min="0" value="<?= (int) ($row['order_by'] ?? 0) ?>"></div><div class="ui-field"><label>Период динамики</label><input name="trend_period" maxlength="10" value="<?= htmlspecialchars((string) ($row['trend_period'] ?? '')) ?>"></div><div class="ui-field"><label>Единица измерения</label><input name="trend_unit" maxlength="100" value="<?= htmlspecialchars((string) ($row['trend_unit'] ?? 'шт.')) ?>"></div><div class="ui-field wide"><label>Статус</label><select name="is_active"><option value="Y" <?= ($row['is_active'] ?? 'Y') === 'Y' ? 'selected' : '' ?>>Активен</option><option value="N" <?= ($row['is_active'] ?? 'Y') !== 'Y' ? 'selected' : '' ?>>Отключён</option></select></div><div class="ui-form-actions wide"><button class="btn btn-primary" type="submit">Сохранить</button><button class="btn btn-ghost" type="button" onclick="this.closest('tr').hidden=true">Отмена</button></div></form></td></tr><?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="kpi-admin-empty">KPI не найдены</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
  </main>
</div>
<script>
(function(){var q=document.getElementById('kpiSearch'),t=document.getElementById('kpiType'),p=document.getElementById('kpiPage'),key='kpi-admin-filters';function save(){sessionStorage.setItem(key,JSON.stringify({q:q.value,t:t.value,p:p.value}))}function filter(){var s=q.value.toLowerCase().trim();document.querySelectorAll('#kpiRows tr[data-search]').forEach(function(r){r.hidden=!(r.dataset.search.indexOf(s)>=0&&(!t.value||r.dataset.type===t.value)&&(!p.value||r.dataset.page===p.value))});save()}try{var state=JSON.parse(sessionStorage.getItem(key)||'{}');q.value=state.q||'';t.value=state.t||'';p.value=state.p||''}catch(e){}q.addEventListener('input',filter);t.addEventListener('change',filter);p.addEventListener('change',filter);filter()})();
</script>
</body></html>
