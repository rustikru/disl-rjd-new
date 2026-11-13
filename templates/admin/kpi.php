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
    .kpi-admin-table th,.kpi-admin-table td{padding:10px 12px;font-size:12px}
    .kpi-admin-table th:last-child,.kpi-admin-table td:last-child{position:sticky;right:0;min-width:245px;background:var(--surface)}
    .kpi-admin-table th:last-child{background:var(--row-head)}
    .kpi-admin-table tbody tr:hover td{background:var(--surface)}
    .kpi-admin-table tbody tr:hover td:last-child{background:var(--surface)}
    .kpi-admin-kind,.kpi-admin-detail,.kpi-admin-state{color:var(--text-2);white-space:nowrap}
    .kpi-admin-name{font-weight:700;overflow-wrap:anywhere}
    .kpi-admin-empty{padding:24px!important;text-align:center;color:var(--text-3)}
    .kpi-admin-page-group td{padding:0!important;background:var(--surface);border-top:1px solid var(--border);color:var(--text-1)}
    .kpi-admin-page-name{display:flex;flex-direction:column;gap:2px;min-width:0}
    .kpi-admin-page-name .kpi-admin-code{font-size:12px;margin-top:0}
    .kpi-admin-page-group strong{font-size:12px}
    .kpi-admin-page-toggle{display:flex;width:100%;align-items:center;gap:10px;border:0;background:transparent;color:var(--accent);padding:12px 14px;text-align:left;font:inherit;font-size:12px;cursor:pointer}
    .kpi-admin-page-toggle::before{content:none}
    .kpi-admin-page-count{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 8px;border-radius:999px;background:color-mix(in srgb,var(--accent) 12%,transparent);color:var(--accent);font-size:12px;font-weight:700}
    .kpi-admin-panel .ui-panel-title{font-size:16px}
    .kpi-page-settings-table{min-width:0;width:100%;table-layout:fixed}
    .kpi-page-settings-table th,.kpi-page-settings-table td{vertical-align:middle}
    .kpi-page-settings-table th:nth-child(1){width:34%}
    .kpi-page-settings-table th:nth-child(2){width:22%}
    .kpi-page-settings-table th:nth-child(3){width:18%}
    .kpi-page-settings-table th:nth-child(4){width:14%}
    .kpi-page-settings-table th:nth-child(5){width:150px}
    .kpi-page-settings-table th:last-child,.kpi-page-settings-table td:last-child{position:static;min-width:0;background:transparent}
    .kpi-page-settings-table .kpi-admin-actions{justify-content:flex-end}
    .kpi-page-settings-table .kpi-admin-actions .btn{min-width:112px;white-space:nowrap}
    .settings-tabs{display:flex;gap:4px;padding:6px 18px;border-bottom:1px solid var(--border);overflow-x:auto;background:var(--surface-2)}
    .settings-tab{flex:0 0 auto;border:0;border-radius:7px;padding:8px 12px;background:transparent;color:var(--text-3);font:inherit;font-size:12px;font-weight:650;cursor:pointer;white-space:nowrap}
    .settings-tab:hover{background:var(--surface);color:var(--text-1)}
    .settings-tab.is-active{background:var(--accent-lt);color:var(--accent)}
    .kpi-admin-shell{padding:0;overflow:hidden}
    .kpi-admin-shell>.kpi-admin-tab-panel{padding:0 18px 18px}
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
    <div class="ui-page-head"><div class="ui-page-title">Управление KPI<small>Системные, общие и личные показатели</small></div></div>
    <?php if (!empty($_GET['saved'])): ?><div class="ui-notice ui-notice--success">Изменения сохранены.</div><?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?><div class="ui-notice ui-notice--success">KPI удалён.</div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="ui-notice ui-notice--error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <section class="ui-panel kpi-admin-shell">
    <div class="settings-tabs" role="tablist" aria-label="Разделы управления KPI">
      <button class="settings-tab is-active" type="button" data-kpi-admin-tab="cards">KPI</button>
      <button class="settings-tab" type="button" data-kpi-admin-tab="pages">Страницы</button>
    </div>
    <div class="kpi-admin-tab-panel" data-kpi-admin-panel="cards">
    <section class="kpi-admin-panel">
      <div class="kpi-admin-filters"><input class="ui-search-input" id="kpiSearch" type="search" placeholder="Поиск по названию или коду"><select class="filter-select" id="kpiType"><option value="">Все типы</option><option value="SYSTEM">Системные</option><option value="GLOBAL">Общие</option><option value="PRIVATE">Личные</option></select><select class="filter-select" id="kpiPage"><option value="">Все страницы</option><?php foreach (\App\Reports\KpiTypeRegistry::all() as $definition): ?><option value="<?= htmlspecialchars((string) $definition['page']) ?>"><?= htmlspecialchars((string) $definition['label']) ?></option><?php endforeach; ?></select></div>
      <div class="ui-table-wrap"><table class="ui-table kpi-admin-table"><thead><tr><th>KPI / Код</th><th>Тип</th><th>Страница</th><th>Детализация</th><th>Владелец</th><th>Статус</th><th></th></tr></thead><tbody id="kpiRows">
      <?php $pageCounts = []; foreach ($rows as $countRow) { $countLabel = \App\Reports\KpiTypeRegistry::pageLabel((string) ($countRow['page_code'] ?? '')) ?? '—'; $pageCounts[$countLabel] = ($pageCounts[$countLabel] ?? 0) + 1; } $lastPage = null; foreach ($rows as $row): $system = ($row['is_system'] ?? 'N') === 'Y'; $kind = $system ? 'SYSTEM' : (($row['visibility'] ?? 'PRIVATE') === 'GLOBAL' ? 'GLOBAL' : 'PRIVATE'); $pageCode = (string) ($row['page_code'] ?? ''); $page = \App\Reports\KpiTypeRegistry::pageLabel($pageCode) ?? '—'; $detail = 'Таблица'; if (!$system) { $cfg = json_decode((string) ($row['where_config'] ?? ''), true); $template = strtoupper((string) ($cfg['detail']['template'] ?? '')); $detail = $template === 'TRAIN_NUMBER' ? 'Номер поезда' : ($template === 'TRAIN_INDEX' ? 'Индекс поезда' : ($template === 'WAGON_TYPE' ? 'По типу вагона' : 'Таблица')); } if ($page !== $lastPage): $lastPage = $page; ?>
        <tr class="kpi-admin-page-group is-collapsed" data-search="" data-type="" data-page="<?= htmlspecialchars($pageCode) ?>"><td colspan="7"><button class="kpi-admin-page-toggle" type="button"><span class="kpi-admin-page-name"><strong><?= htmlspecialchars($page) ?></strong><span class="kpi-admin-code"><?= htmlspecialchars($pageCode) ?></span></span><span class="kpi-admin-page-count"><?= (int) ($pageCounts[$page] ?? 0) ?></span></button></td></tr>
      <?php endif; ?>
        <tr data-search="<?= htmlspecialchars(mb_strtolower((string) $row['label'] . ' ' . (string) $row['code'])) ?>" data-type="<?= $kind ?>" data-page="<?= htmlspecialchars($pageCode) ?>">
          <td><strong class="kpi-admin-name"><?= htmlspecialchars((string) ($row['label'] ?? '')) ?></strong><span class="kpi-admin-code"><?= htmlspecialchars((string) $row['code']) ?></span></td>
          <td class="kpi-admin-kind"><?= $kind === 'SYSTEM' ? 'Системная' : ($kind === 'GLOBAL' ? 'Общая' : 'Личная') ?></td><td><?= htmlspecialchars($page) ?></td><td class="kpi-admin-detail"><?= htmlspecialchars($system ? 'Системная детализация' : $detail) ?></td><td><?= htmlspecialchars((string) ($system ? 'Система' : ($row['owner_name'] ?? '—'))) ?></td>
          <td class="kpi-admin-state"><?= ($row['is_active'] ?? 'Y') === 'Y' ? 'Активен' : 'Отключён' ?></td>
          <td><div class="kpi-admin-actions">
            <?php if ($system): ?><button class="btn btn-ghost btn-sm" type="button" onclick="document.getElementById('edit-<?= (int) $row['id'] ?>').hidden=false">Изменить</button>
            <?php elseif ($userKpiManagementEnabled): ?><a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($basePath) ?>/kpi/edit?kpi_type=<?= rawurlencode((string) $row['type']) ?>&id=<?= (int) $row['id'] ?>&admin=1">Изменить</a><?php endif; ?>
            <?php if ($system || $userKpiManagementEnabled): ?><form method="post" action="<?= htmlspecialchars($basePath) ?>/admin/kpi/save"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="label" value="<?= htmlspecialchars((string) $row['label']) ?>"><input type="hidden" name="order_by" value="<?= (int) ($row['order_by'] ?? 0) ?>"><input type="hidden" name="trend_period" value="<?= htmlspecialchars((string) ($row['trend_period'] ?? '')) ?>"><input type="hidden" name="trend_unit" value="<?= htmlspecialchars((string) ($row['trend_unit'] ?? 'шт.')) ?>"><input type="hidden" name="is_active" value="<?= ($row['is_active'] ?? 'Y') === 'Y' ? 'N' : 'Y' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= ($row['is_active'] ?? 'Y') === 'Y' ? 'Отключить' : 'Включить' ?></button></form><?php endif; ?>
            <?php if (!$system && $userKpiManagementEnabled): ?><form method="post" action="<?= htmlspecialchars($basePath) ?>/admin/kpi/delete" onsubmit="return confirm('Удалить KPI «<?= htmlspecialchars((string) $row['label'], ENT_QUOTES) ?>»?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Удалить</button></form><?php endif; ?>
          </div></td>
        </tr>
        <?php if ($system): ?><tr id="edit-<?= (int) $row['id'] ?>" hidden><td colspan="7"><form class="kpi-admin-edit" method="post" action="<?= htmlspecialchars($basePath) ?>/admin/kpi/save"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><div class="ui-field"><label>Название</label><input name="label" maxlength="150" required value="<?= htmlspecialchars((string) $row['label']) ?>"></div><div class="ui-field"><label>Порядок</label><input name="order_by" type="number" min="0" value="<?= (int) ($row['order_by'] ?? 0) ?>"></div><div class="ui-field"><label>Период динамики</label><input name="trend_period" maxlength="10" value="<?= htmlspecialchars((string) ($row['trend_period'] ?? '')) ?>"></div><div class="ui-field"><label>Единица измерения</label><input name="trend_unit" maxlength="100" value="<?= htmlspecialchars((string) ($row['trend_unit'] ?? 'шт.')) ?>"></div><div class="ui-field wide"><label>Статус</label><select name="is_active"><option value="Y" <?= ($row['is_active'] ?? 'Y') === 'Y' ? 'selected' : '' ?>>Активен</option><option value="N" <?= ($row['is_active'] ?? 'Y') !== 'Y' ? 'selected' : '' ?>>Отключён</option></select></div><div class="ui-form-actions wide"><button class="btn btn-primary" type="submit">Сохранить</button><button class="btn btn-ghost" type="button" onclick="this.closest('tr').hidden=true">Отмена</button></div></form></td></tr><?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="kpi-admin-empty">KPI не найдены</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
    </div>
<div class="kpi-admin-tab-panel" data-kpi-admin-panel="pages" hidden>
    <section class="kpi-admin-panel">
      <div class="ui-table-wrap"><table class="ui-table kpi-admin-table kpi-page-settings-table"><thead><tr><th>Страница</th><th>Тип KPI</th><th>Поддержка</th><th>Создание</th><th></th></tr></thead><tbody>
      <?php foreach ($pageSettings as $page => $setting): $definition = null; foreach (\App\Reports\KpiTypeRegistry::all() as $type => $item) { if ($item['page'] === $page && !empty($item['user_creatable'])) { $definition = [$type, $item]; break; } } if (!$definition) continue; ?>
        <tr><td><?= htmlspecialchars((string) $definition[1]['label']) ?><span class="kpi-admin-code"><?= htmlspecialchars($page) ?></span></td><td><?= htmlspecialchars($definition[0]) ?></td><td><?= !empty($setting['supported']) ? 'Подготовлена' : 'Не подготовлена' ?></td><td><?= !empty($setting['enabled']) ? 'Разрешено' : 'Отключено' ?></td><td><div class="kpi-admin-actions"><form method="post" action="<?= htmlspecialchars($basePath) ?>/admin/kpi/pages/save"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="page_code" value="<?= htmlspecialchars($page) ?>"><input type="hidden" name="create_enabled" value="<?= !empty($setting['enabled']) ? 'N' : 'Y' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= !empty($setting['enabled']) ? 'Отключить' : 'Включить' ?></button></form></div></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </section>
    </div>
    </section>
  </main>
</div>
<script>
(function(){var q=document.getElementById('kpiSearch'),t=document.getElementById('kpiType'),p=document.getElementById('kpiPage'),key='kpi-admin-filters';function save(){sessionStorage.setItem(key,JSON.stringify({q:q.value,t:t.value,p:p.value}))}function filter(){var s=q.value.toLowerCase().trim();document.querySelectorAll('#kpiRows tr[data-search]').forEach(function(r){r.hidden=!(r.dataset.search.indexOf(s)>=0&&(!t.value||r.dataset.type===t.value)&&(!p.value||r.dataset.page===p.value))});document.querySelectorAll('#kpiRows .kpi-admin-page-group').forEach(function(group){if(group.classList.contains('is-collapsed'))return;var row=group.nextElementSibling;while(row&&!row.classList.contains('kpi-admin-page-group')){if(!row.hidden)break;row=row.nextElementSibling}group.hidden=!row});save()}document.querySelectorAll('#kpiRows .kpi-admin-page-toggle').forEach(function(button){button.addEventListener('click',function(){var group=button.closest('.kpi-admin-page-group');var collapsed=group.classList.toggle('is-collapsed');var row=group.nextElementSibling;while(row&&!row.classList.contains('kpi-admin-page-group')){row.hidden=row.id.indexOf('edit-')===0||collapsed;row=row.nextElementSibling}})});try{var state=JSON.parse(sessionStorage.getItem(key)||'{}');q.value=state.q||'';t.value=state.t||'';p.value=state.p||''}catch(e){}q.addEventListener('input',filter);t.addEventListener('change',filter);p.addEventListener('change',filter);filter()})();
</script>
<script>
document.querySelectorAll('#kpiRows .kpi-admin-page-group.is-collapsed').forEach(function (group) {
  var row = group.nextElementSibling
  while (row && !row.classList.contains('kpi-admin-page-group')) {
    row.hidden = true
    row = row.nextElementSibling
  }
})
</script>
<script>
document.querySelectorAll('[data-kpi-admin-tab]').forEach(function (button) {
  button.addEventListener('click', function () {
    var tab = button.getAttribute('data-kpi-admin-tab')
    sessionStorage.setItem('kpi-admin-tab', tab)
    document.querySelectorAll('[data-kpi-admin-tab]').forEach(function (item) {
      item.classList.toggle('is-active', item === button)
    })
    document.querySelectorAll('[data-kpi-admin-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-kpi-admin-panel') !== tab
    })
  })
})
var savedTab = sessionStorage.getItem('kpi-admin-tab')
if (savedTab) {
  var savedButton = document.querySelector('[data-kpi-admin-tab="' + savedTab + '"]')
  if (savedButton) savedButton.click()
}
</script>
</body></html>
