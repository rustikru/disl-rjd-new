<?php
$e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$dateLabel = static fn($value): string => $value ? date('d.m.Y', strtotime($value)) : 'Бессрочно';
$listUrl = $basePath . '/directories/norms';
$editUrl = static function ($id) use ($listUrl, $filterType, $filterDate, $filterStation): string {
    return $listUrl . '?' . http_build_query(['edit' => $id, 'type' => $filterType, 'date' => $filterDate, 'station' => $filterStation]);
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $e($appName) ?> — Нормативы</title>
  <link rel="icon" type="image/x-icon" href="<?= $e($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= $e($basePath) ?>/assets/css/app.css">
  <style>
    .admin-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .admin-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .flash { padding:8px 12px; border-radius:8px; font-size:12px; margin-bottom:10px; }
    .flash-ok { background:#e8f6ef; color:var(--brand-green); border:1px solid #bfe6d2; }
    .flash-err { background:#fbecec; color:var(--brand-neg); border:1px solid #f0c9c9; }
    .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .panel + .panel { margin-top:12px; }
    .panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 14px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .panel-title { font-size:13px; font-weight:600; }
    .norm-form { padding:10px 14px; }
    .norm-fields { display:grid; grid-template-columns:minmax(200px, 1.2fr) minmax(170px, 1fr) minmax(230px, 1.5fr); gap:8px; margin-bottom:8px; }
    .norm-terms { display:grid; grid-template-columns:repeat(4,minmax(120px,1fr)) auto auto; gap:8px; align-items:end; }
    .fg { display:flex; flex-direction:column; gap:4px; min-width:0; }
    .fg label { font-size:11px; font-weight:600; color:var(--text-2); }
    .fg input, .fg select { border:1px solid var(--border); border-radius:7px; padding:6px 9px; font-family:inherit; font-size:12px; color:var(--text-1); outline:none; width:100%; background:var(--surface); }
    .fg input, .fg select { height:34px; min-height:34px; box-sizing:border-box; line-height:normal; -webkit-appearance:none; appearance:none; }
    .fg select { padding-right:32px; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='m3 4.5 3 3 3-3' fill='none' stroke='%236b667a' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 11px center; }
    .norm-terms > .btn, .norm-filter > .btn { height:34px; min-height:34px; padding:6px 11px; font-size:12px; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; text-decoration:none; }
    .fg input:focus, .fg select:focus { border-color:var(--accent); }
    .norm-filter { display:flex; gap:8px; align-items:end; padding:10px 14px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .norm-filter .fg { width:220px; }
    .icon-btn { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border:none; border-radius:6px; background:transparent; cursor:pointer; color:var(--text-3); transition:background .15s, color .15s; padding:0; flex-shrink:0; text-decoration:none; }
    .icon-btn:hover { background:var(--accent-lt); color:var(--accent); }
    .icon-btn--delete:hover { color:var(--brand-neg); background:#fbecec; }
    .norm-table { width:100%; border-collapse:collapse; }
    .norm-table th { padding:7px 11px; text-align:left; font-size:10px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--border); white-space:nowrap; background:var(--row-head); }
    .norm-table td { padding:6px 11px; border-bottom:1px solid var(--border); font-size:11.5px; line-height:1.25; vertical-align:middle; color:var(--text-1); }
    .norm-table tr:last-child td { border-bottom:none; }
    .norm-table tbody tr:hover td { background:var(--hover-green); }
    .norm-table .num { text-align:right; white-space:nowrap; }
    .norm-table .dates { white-space:nowrap; }
    .empty { padding:20px!important; text-align:center; color:var(--text-3)!important; font-size:12px; }
    .norm-count { font-size:11px; color:var(--text-3); }
    .norm-value-hint { font-size:11px; color:var(--text-3); min-height:0; }
    .panel { overflow:visible; }
    .station-field { position:relative; }
    .station-options { position:absolute; left:0; right:0; top:calc(100% + 4px); z-index:40; max-height:240px; overflow:auto; background:var(--surface); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 20px rgba(27,23,38,.12); }
    .station-options[hidden] { display:none; }
    .station-option { display:block; width:100%; border:0; background:transparent; color:var(--text-1); text-align:left; padding:7px 9px; font-size:12px; font-family:inherit; cursor:pointer; }
    .station-option:hover, .station-option[aria-selected="true"] { background:var(--accent-lt); color:var(--accent); }
    .station-empty { padding:10px; color:var(--text-3); font-size:12px; }
    .norm-form.is-editing { border-left:3px solid var(--accent); }
    @media (max-width:1200px) { .norm-terms { grid-template-columns:repeat(4,minmax(100px,1fr)); } }
    @media (max-width:760px) { .norm-fields { grid-template-columns:1fr; } .norm-terms { grid-template-columns:1fr 1fr; } .norm-filter .fg { width:100%; } }
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Справочники</div>';
$headerLeft  = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" id="backBtn" title="На главную"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'norms'; include __DIR__ . '/../partials/directories-sidebar.php'; ?>
  <main class="main-content">
    <div class="admin-head"><div class="admin-title">Нормативы</div></div>
    <?php if ($flashOk): ?><div class="flash flash-ok" role="status"><?= $e($flashOk) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="flash flash-err" role="alert"><?= $e($flashErr) ?></div><?php endif; ?>
    <?php if ($canEdit): ?>
    <section class="panel">
      <div class="panel-head"><span class="panel-title"><?= $form['id'] ? 'Изменить норматив' : 'Добавить норматив' ?></span></div>
      <form method="POST" action="<?= $e($listUrl) ?>/save" class="norm-form<?= $form['id'] ? ' is-editing' : '' ?>" id="normForm">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" value="<?= $e($form['id']) ?>">
        <div class="norm-fields">
          <div class="fg station-field"><label for="normStation">Станция (необязательно)</label><input id="normStation" name="station_code" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="normStations" value="<?= $e(!empty($form['station_name']) ? $form['station_name'] . ' (' . $form['station_code'] . ')' : $form['station_code']) ?>" maxlength="200" autocomplete="off" placeholder="Все станции"><div id="normStations" class="station-options" role="listbox" hidden></div></div>
          <div class="fg"><label for="normType">Тип норматива</label><select id="normType" name="rule_type" required>
            <?php foreach ($types as $code => $label): ?><option value="<?= $e($code) ?>" <?= $form['rule_type'] === $code ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?>
          </select></div>
          <div class="fg"><label for="normValue" id="normValueLabel"><?= $e($types[$form['rule_type']] ?? 'Значение') ?></label><input id="normValue" name="rule_value" list="normValues" value="<?= $e($form['rule_value']) ?>" maxlength="500" autocomplete="off" placeholder="Начните вводить название" required><datalist id="normValues"></datalist></div>
        </div>
        <div class="norm-value-hint">Если станция не указана, норматив действует для всех станций.</div>
        <div class="norm-terms">
          <div class="fg"><label for="normDays">Бесплатно, сут.</label><input id="normDays" name="free_days" type="number" min="0" max="999999" step="1" value="<?= $e($form['free_days']) ?>" required></div>
          <div class="fg"><label for="normRate">Ставка, ₽/сут.</label><input id="normRate" name="daily_rate" type="number" min="0" max="999999999999.99" step="0.01" value="<?= $e($form['daily_rate']) ?>" required></div>
          <div class="fg"><label for="normFrom">Действует с</label><input id="normFrom" name="valid_from" type="date" min="1900-01-01" max="9999-12-31" value="<?= $e($form['valid_from']) ?>" required></div>
          <div class="fg"><label for="normTo">Действует по</label><input id="normTo" name="valid_to" type="date" min="1900-01-01" max="9999-12-31" value="<?= $e($form['valid_to']) ?>" title="Оставьте пустым для бессрочного действия"></div>
          <button class="btn btn-primary" type="submit">Сохранить</button>
          <a class="btn btn-ghost" href="<?= $e($listUrl) ?>"><?= $form['id'] ? 'Отмена' : 'Сбросить' ?></a>
        </div>
        <div class="norm-value-hint" id="normValueHint" role="status"></div>
      </form>
    </section>
    <?php endif; ?>
    <section class="panel">
      <div class="panel-head"><span class="panel-title">Нормативы</span><span class="norm-count">Записей: <?= count($norms) ?></span></div>
      <form method="GET" action="<?= $e($listUrl) ?>" class="norm-filter">
        <div class="fg station-field"><label for="filterStation">Станция</label><input id="filterStation" name="station" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="filterStations" value="<?= $e($filterStation) ?>" maxlength="200" autocomplete="off" placeholder="Все станции"><div id="filterStations" class="station-options" role="listbox" hidden></div></div>
        <div class="fg"><label for="filterType">Тип норматива</label><select id="filterType" name="type"><option value="">Все</option><?php foreach ($types as $code => $label): ?><option value="<?= $e($code) ?>" <?= $filterType === $code ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
        <div class="fg"><label for="filterDate">Действует на дату</label><input id="filterDate" name="date" type="date" min="1900-01-01" max="9999-12-31" value="<?= $e($filterDate) ?>"></div>
        <button class="btn btn-primary" type="submit">Применить</button><a class="btn btn-ghost" href="<?= $e($listUrl) ?>">Сбросить</a>
      </form>
      <div class="table-scroll"><table class="norm-table"><thead><tr><th>Станция</th><th>Тип норматива</th><th>Значение</th><th class="num">Бесплатно, сут.</th><th class="num">Ставка, ₽/сут.</th><th>Действует с</th><th>Действует по</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($norms as $row): ?><tr><td><?= $row['station_code'] ? $e(($row['station_name'] ?? '') . ' (' . $row['station_code'] . ')') : 'Все станции' ?></td><td><?= $e($types[$row['rule_type']] ?? $row['rule_type']) ?></td><td><?= $e($row['rule_value']) ?></td><td class="num"><?= (int) $row['free_days'] ?></td><td class="num"><?= number_format((float) $row['daily_rate'], 2, ',', ' ') ?></td><td class="dates"><?= $e($dateLabel($row['valid_from'])) ?></td><td class="dates"><?= $e($dateLabel($row['valid_to'])) ?></td><?php if ($canEdit): ?><td><a class="icon-btn" href="<?= $e($editUrl($row['id'])) ?>" title="Изменить" aria-label="Изменить норматив"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 1H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1M8 1v3h3L8 1Z"/><path d="M4 5h2M4 7h1m6-1 2 2-5 5H6v-2Z"/></svg></a><form method="POST" action="<?= $e($listUrl) ?>/delete" style="display:inline-flex" onsubmit="return confirm('Удалить норматив?')"><input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token'] ?? '') ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button type="submit" class="icon-btn icon-btn--delete" title="Удалить" aria-label="Удалить норматив"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button></form></td><?php endif; ?></tr><?php endforeach; ?>
        <?php if (!$norms): ?><tr><td colspan="<?= $canEdit ? 8 : 7 ?>" class="empty">Нормативы не найдены</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
  </main>
</div>
<script>
(function () {
  var type = document.getElementById('normType')
  var value = document.getElementById('normValue')
  var list = document.getElementById('normValues')
  var hint = document.getElementById('normValueHint') || { textContent: '' }
  var from = document.getElementById('normFrom')
  var to = document.getElementById('normTo')
  var timer, request, version = 0
  var url = <?= json_encode($listUrl . '/values', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  function loadValues() {
    if (!type) return
    var current = ++version
    if (request) request.abort()
    request = new AbortController()
    var params = new URLSearchParams({ type: type.value, q: value.value })
    fetch(url + '?' + params.toString(), { signal: request.signal, headers: { Accept: 'application/json' } })
      .then(function (response) { if (!response.ok) throw new Error(); return response.json() })
      .then(function (data) {
        if (current !== version) return
        list.replaceChildren()
        data.values.forEach(function (text) { var option = document.createElement('option'); option.value = text; list.appendChild(option) })
        hint.textContent = ''
      }).catch(function (error) { if (error.name !== 'AbortError' && current === version) hint.textContent = 'Подсказки недоступны. Значение можно ввести вручную.' })
  }
  if (type) type.addEventListener('change', function () {
    value.value = ''; list.replaceChildren()
    document.getElementById('normValueLabel').textContent = type.options[type.selectedIndex].text
    clearTimeout(timer); loadValues()
  })
  if (value) value.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(loadValues, 250) })
  function checkDates() { to.setCustomValidity(to.value && to.value < from.value ? 'Дата окончания не может быть раньше даты начала' : '') }
  if (from) from.addEventListener('input', checkDates); if (to) to.addEventListener('input', checkDates)
  function stationSearch(inputId, listId) {
    var input = document.getElementById(inputId)
    if (!input) return
    var options = document.getElementById(listId)
    var timeout, controller, sequence = 0, active = -1, items = []
    function close() {
      options.hidden = true
      input.setAttribute('aria-expanded', 'false')
      input.removeAttribute('aria-activedescendant')
      active = -1
    }
    function select(index) {
      if (!items[index]) return
      input.value = items[index].station_name + ' (' + items[index].esr_code + ')'
      clearTimeout(timeout)
      sequence++
      if (controller) controller.abort()
      close()
    }
    function highlight(index) {
      active = index
      Array.from(options.querySelectorAll('button')).forEach(function (button, i) {
        button.setAttribute('aria-selected', String(i === active))
        if (i === active) {
          input.setAttribute('aria-activedescendant', button.id)
          button.scrollIntoView({ block: 'nearest' })
        }
      })
    }
    function loadStations() {
      var current = ++sequence
      if (controller) controller.abort()
      controller = new AbortController()
      var search = input.value.replace(/\s*\(\d{6}\)$/, '')
      var address = url.replace(/\/values$/, '/stations') + '?' + new URLSearchParams({ q: search })
      fetch(address, { signal: controller.signal, headers: { Accept: 'application/json' } })
        .then(function (response) { if (!response.ok) throw new Error(); return response.json() })
        .then(function (data) {
          if (sequence !== current || document.activeElement !== input) return
          items = data.stations
          options.replaceChildren()
          active = -1
          input.removeAttribute('aria-activedescendant')
          items.forEach(function (station, i) {
            var button = document.createElement('button')
            button.type = 'button'
            button.className = 'station-option'
            button.id = listId + '-' + i
            button.tabIndex = -1
            button.setAttribute('role', 'option')
            button.setAttribute('aria-selected', 'false')
            button.textContent = station.station_name + ' (' + station.esr_code + ')'
            button.addEventListener('mousedown', function (event) { event.preventDefault() })
            button.addEventListener('click', function () { select(i); input.focus() })
            options.appendChild(button)
          })
          if (!items.length) {
            var empty = document.createElement('div')
            empty.className = 'station-empty'
            empty.textContent = 'Станции не найдены'
            options.appendChild(empty)
          }
          options.hidden = false
          input.setAttribute('aria-expanded', 'true')
          hint.textContent = ''
        }).catch(function (error) {
          if (error.name !== 'AbortError' && sequence === current) {
            close()
            hint.textContent = 'Поиск станций недоступен. Введите код ЕСР.'
          }
        })
    }
    input.addEventListener('input', function () {
      clearTimeout(timeout)
      sequence++
      if (controller) controller.abort()
      close()
      timeout = setTimeout(loadStations, 250)
    })
    input.addEventListener('focus', loadStations)
    input.addEventListener('blur', function () {
      clearTimeout(timeout)
      sequence++
      if (controller) controller.abort()
      close()
    })
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        clearTimeout(timeout); sequence++
        if (controller) controller.abort()
        close()
      } else if (!options.hidden && items.length) {
        if (event.key === 'ArrowDown') { event.preventDefault(); highlight((active + 1) % items.length) }
        if (event.key === 'ArrowUp') { event.preventDefault(); highlight(active <= 0 ? items.length - 1 : active - 1) }
        if (event.key === 'Enter') { event.preventDefault(); select(active < 0 ? 0 : active) }
      }
    })
  }
  stationSearch('normStation', 'normStations')
  stationSearch('filterStation', 'filterStations')
  loadValues()
})()
</script>
</body>
</html>
