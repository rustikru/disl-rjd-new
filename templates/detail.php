<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $user  ['username', 'display_name', 'auth_source'] */
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Детализация</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=PT+Sans:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <script>window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';</script>
  <style>
    .detail-breadcrumb {
      font-size: 12px; color: var(--text-3);
      display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
    }
    .detail-breadcrumb .bc-sep { color: var(--border); }
    .detail-breadcrumb .bc-item { color: var(--text-2); }
    .detail-breadcrumb .bc-item.bc-active { color: var(--text-1); font-weight: 700; }
    .detail-page-body { padding: 16px 20px 40px; max-width: 100%; }
    .detail-header-row {
      display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
    }
  </style>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <div class="brand">
      <div class="brand-icon">
        <svg width="28" height="28" viewBox="0 0 30 30" fill="none">
          <rect x="1" y="10" width="28" height="12" rx="2" fill="currentColor" opacity=".9"/>
          <circle cx="7.5"  cy="24" r="3" fill="currentColor"/>
          <circle cx="22.5" cy="24" r="3" fill="currentColor"/>
          <rect x="6"  y="7" width="7" height="5" rx="1" fill="currentColor" opacity=".5"/>
          <rect x="17" y="7" width="7" height="5" rx="1" fill="currentColor" opacity=".5"/>
        </svg>
      </div>
      <div class="brand-text">
        <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
        <div class="brand-sub">Дислокация парка вагонов</div>
      </div>
    </div>
    <div class="header-meta">
      <span class="meta-badge">РЖД</span>
      <div class="user-info">
        <span class="user-name" title="<?= htmlspecialchars($user['auth_source'] ?? '') ?>">
          <?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? '') ?>
        </span>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.history.back()">← Назад</button>
      </div>
    </div>
  </div>
</header>

<div class="detail-page-body">

  <div class="detail-header-row">
    <div class="detail-breadcrumb" id="breadcrumb"></div>
  </div>

  <section class="table-section">
    <div class="table-toolbar">
      <div class="table-info">
        <span class="table-title" id="detailTitle">Загрузка...</span>
        <span class="table-sub" id="detailSub"></span>
      </div>
    </div>
    <div class="table-scroll">
      <table class="data-table" id="detailTable"></table>
    </div>
  </section>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"
  integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script>
'use strict';

var BASE = window.APP_BASE || '';
var CONTEXTS = {
  approach: {
    label: 'Подход вагонов', endpoint: BASE + '/api/approach/detail',
    cols: [
      {key:'wagon_no',         label:'№ вагона',        meta:true, mono:true},
      {key:'wagon_type_code',  label:'Тип',             meta:true},
      {key:'cargo_name',       label:'Груз',            meta:true},
      {key:'prev_cargo',       label:'Ранее выгружен',  meta:true},
      {key:'dist_remain_km',   label:'Ост. км',         right:true},
      {key:'depart_station',   label:'Ст. отправл.',    meta:true},
      {key:'oper_station',     label:'Тек. станция',    meta:true},
      {key:'dest_station',     label:'Ст. назнач.',     meta:true},
      {key:'dest_road',        label:'Дорога назнач.',  meta:true},
      {key:'norm_delivery_dt', label:'Норм. дата дост.',meta:true}
    ]
  },
  departure: {
    label: 'Отправление вагонов', endpoint: BASE + '/api/departure/detail',
    cols: [
      {key:'wagon_no',         label:'№ вагона',        meta:true, mono:true},
      {key:'wagon_type_code',  label:'Тип',             meta:true},
      {key:'cargo_name',       label:'Груз',            meta:true},
      {key:'cargo_weight_kg',  label:'Вес (кг)',        right:true},
      {key:'depart_station',   label:'Ст. отправл.',    meta:true},
      {key:'depart_road',      label:'Дорога отпр.',    meta:true},
      {key:'dest_station',     label:'Ст. назнач.',     meta:true},
      {key:'dest_road',        label:'Дорога назнач.',  meta:true},
      {key:'dist_remain_km',   label:'Ост. км',         right:true},
      {key:'norm_delivery_dt', label:'Норм. дата дост.',meta:true}
    ]
  },
  loading: {
    label: 'Погрузка', endpoint: BASE + '/api/loading/detail',
    cols: [
      {key:'wagon_no',         label:'№ вагона',    meta:true, mono:true},
      {key:'wagon_type_code',  label:'Тип',         meta:true},
      {key:'cargo_name',       label:'Груз',        meta:true},
      {key:'cargo_weight_kg',  label:'Вес (кг)',    right:true},
      {key:'depart_station',   label:'Ст. отправл.',meta:true},
      {key:'depart_road',      label:'Дорога',      meta:true},
      {key:'dest_station',     label:'Ст. назнач.', meta:true},
      {key:'oper_mnemonic',    label:'Операция',    meta:true},
      {key:'oper_dt',          label:'Дата опер.',  meta:true}
    ]
  },
  downtime: {
    label: 'Простои', endpoint: BASE + '/api/downtime/detail',
    cols: [
      {key:'wagon_no',        label:'№ вагона',       meta:true, mono:true},
      {key:'wagon_type_code', label:'Тип',            meta:true},
      {key:'cargo_name',      label:'Груз',           meta:true},
      {key:'oper_station',    label:'Тек. станция',   meta:true},
      {key:'oper_road',       label:'Дорога',         meta:true},
      {key:'idle_time_days',  label:'Простой (сут.)', right:true, danger:true},
      {key:'owner',           label:'Владелец',       meta:true},
      {key:'lessee',          label:'Арендатор',      meta:true}
    ]
  }
};

function esc(str) {
  if (!str && str !== 0) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function addColumnSearch($table) {
  var cells = '';
  $table.find('thead tr:first th').each(function () {
    cells += '<td><input class="col-search-input" type="text" placeholder="⌕"></td>';
  });
  $table.find('tbody').prepend('<tr class="search-row">' + cells + '</tr>');
}

$(document).on('input', '.col-search-input', function () {
  var $row    = $(this).closest('tr.search-row');
  var $table  = $row.closest('table');
  var filters = $row.find('.col-search-input').map(function () {
    return $(this).val().toLowerCase().trim();
  }).get();
  $table.find('tbody tr:not(.search-row)').each(function () {
    var $cells = $(this).find('td');
    var show   = filters.every(function (q, ci) {
      return !q || $cells.eq(ci).text().toLowerCase().indexOf(q) !== -1;
    });
    $(this).toggle(show);
  });
});

$(function () {
  var params  = new URLSearchParams(window.location.search);
  var ctx     = params.get('context') || '';
  var road    = params.get('road')    || '';
  var station = params.get('station') || '';
  var col     = params.get('col')     || '';

  var ctxDef = CONTEXTS[ctx];

  // Breadcrumb
  var bcParts = [];
  bcParts.push('<span class="bc-item"><a href="' + (window.APP_BASE || '') + '/" style="color:inherit;text-decoration:none">← Вернуться</a></span>');
  if (ctxDef) {
    bcParts.push('<span class="bc-sep">›</span>');
    bcParts.push('<span class="bc-item">' + esc(ctxDef.label) + '</span>');
  }
  if (road) {
    bcParts.push('<span class="bc-sep">›</span>');
    bcParts.push('<span class="bc-item">' + esc(road) + '</span>');
  }
  if (station) {
    bcParts.push('<span class="bc-sep">›</span>');
    bcParts.push('<span class="bc-item">' + esc(station) + '</span>');
  }
  if (col) {
    bcParts.push('<span class="bc-sep">›</span>');
    bcParts.push('<span class="bc-item bc-active">' + esc(col) + '</span>');
  }
  $('#breadcrumb').html(bcParts.join(''));

  // Title
  var titleParts = [];
  if (ctxDef) { titleParts.push(ctxDef.label); }
  if (road)    { titleParts.push(road); }
  if (station) { titleParts.push(station); }
  if (col)     { titleParts.push(col); }
  $('#detailTitle').text(titleParts.join(' › ') || 'Детализация');

  if (!ctxDef) {
    $('#detailTable').html('<tbody><tr><td style="text-align:center;padding:40px;color:#9DA5B0">Неизвестный контекст</td></tr></tbody>');
    return;
  }

  // Build API URL
  var apiParams = new URLSearchParams();
  if (road)    { apiParams.set('road',       road); }
  if (station) { apiParams.set('station',    station); }
  if (col)     { apiParams.set('wagon_type', col); }

  $('#detailSub').text('Загрузка...');

  $.getJSON(ctxDef.endpoint + (apiParams.toString() ? '?' + apiParams.toString() : ''))
    .done(function (data) {
      var rows = data.rows || [];
      $('#detailSub').text('Строк: ' + rows.length.toLocaleString('ru-RU'));
      renderDetailTable(rows, ctxDef.cols);
    })
    .fail(function () {
      $('#detailTable').html('<tbody><tr><td style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки данных</td></tr></tbody>');
      $('#detailSub').text('');
    });
});

function renderDetailTable(rows, cols) {
  var h = '<thead><tr>';
  cols.forEach(function (c) {
    h += '<th' + (c.meta ? ' class="col-meta"' : '') + '>' + esc(c.label) + '</th>';
  });
  h += '</tr></thead><tbody>';

  if (!rows.length) {
    h += '<tr><td colspan="' + cols.length + '" style="text-align:center;padding:40px;color:#9DA5B0">Нет данных</td></tr>';
  } else {
    rows.forEach(function (r) {
      h += '<tr class="row-data">';
      cols.forEach(function (c) {
        var val = r[c.key];
        var display = (val !== null && val !== undefined && val !== '') ? val : '';
        var tdClass = c.meta ? ' class="col-meta' + (c.mono ? '' : '') + '"' : '';
        var tdStyle = '';
        if (c.mono && c.meta) {
          tdClass = ' class="col-meta"';
          tdStyle = ' style="font-family:monospace;font-size:11px"';
        } else if (c.right) {
          tdStyle = ' style="text-align:right"';
        }
        if (c.danger) {
          var days = parseFloat(display) || 0;
          if (days >= 7) {
            tdStyle = ' style="text-align:right;color:#E8392A;font-weight:700"';
          } else if (days >= 3) {
            tdStyle = ' style="text-align:right;color:#E8A530;font-weight:600"';
          } else if (c.right) {
            tdStyle = ' style="text-align:right"';
          }
        }
        h += '<td' + tdClass + tdStyle + '>' + esc(display) + '</td>';
      });
      h += '</tr>';
    });
  }

  h += '</tbody>';
  $('#detailTable').html(h);
  addColumnSearch($('#detailTable'));
}
</script>
</body>
</html>
