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
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <script>window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';</script>
  <style>
    .detail-breadcrumb {
      font-size: 12px;
      color: var(--text-3);
      display: flex;
      align-items: center;
      gap: 4px;
      flex-wrap: wrap;
    }

    .detail-breadcrumb .bc-sep {
      color: var(--border);
    }

    .detail-breadcrumb .bc-item {
      color: var(--text-2);
    }

    .detail-breadcrumb .bc-item.bc-active {
      color: var(--text-1);
      font-weight: 700;
    }

    .detail-page-body {
      padding: 16px 20px 40px;
      max-width: 100%;
    }

    .detail-header-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }
  </style>
</head>

<body>

  <header class="site-header">
    <div class="header-inner">
      <div class="brand">
        <div class="brand-icon">
        </div>
        <div class="brand-text">
          <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
        </div>
      </div>
      <div class="header-meta">
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
        <div class="table-acts">
          <button class="btn btn-ghost btn-sm" id="btnDetailCSV">Скачать CSV</button>
        </div>
      </div>
      <div class="table-scroll">
        <table class="data-table" id="detailTable"></table>
      </div>
    </section>

  </div>

  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/detail-contexts.js"></script>
  <script>
    'use strict';

    var BASE = window.APP_BASE || '';

    function esc(str) {
      if (!str && str !== 0) return '';
      return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    // Добавляем строку для поиска по столбцам
    function addColumnSearch($table) {
      var cells = '';
      $table.find('thead tr:first th').each(function () {
        cells += '<td><input class="col-search-input" type="text" placeholder=""></td>';
      });
      $table.find('tbody').prepend('<tr class="search-row">' + cells + '</tr>');
    }

    $(document).on('input', '.col-search-input', function () {
      var $row = $(this).closest('tr.search-row');
      var $table = $row.closest('table');
      var filters = $row.find('.col-search-input').map(function () {
        return $(this).val().toLowerCase().trim();
      }).get();
      $table.find('tbody tr:not(.search-row)').each(function () {
        var $cells = $(this).find('td');
        var show = filters.every(function (q, ci) {
          return !q || $cells.eq(ci).text().toLowerCase().indexOf(q) !== -1;
        });
        $(this).toggle(show);
      });
    });

    $(function () {
      var params = new URLSearchParams(window.location.search);
      var ctx = params.get('ctx') || '';
      var road = params.get('road') || '';
      var station = params.get('station') || '';
      var col = params.get('col') || '';
      var cargoState = params.get('cargo_state') || '';

      // Конфиг DETAIL_CONTEXTS (detail-contexts.js)
      var ctxDef = null;
      var def = DETAIL_CONTEXTS[ctx];
      if (def) {
        ctxDef = { label: def.label, endpoint: BASE + def.endpoint, cols: def.cols };
      }

      // Вверзняя навигация
      var bcParts = [];
      bcParts.push('<span class="bc-item"><a href="' + (window.APP_BASE || '') + '/" style="color:inherit;text-decoration:none">← Вернуться</a></span>');

      $('#breadcrumb').html(bcParts.join(''));

      // Заголовок
      var bcpathRaw = params.get('_bcpath') || '';
      var bcpathParts = [];
      try { if (bcpathRaw) bcpathParts = JSON.parse(bcpathRaw); } catch (e) {}
      var titleParts = [];
      if (ctxDef) { titleParts.push(ctxDef.label); }
      if (road) {
        titleParts.push(road);
      } else if (bcpathParts.length) {
        bcpathParts.forEach(function (p) { if (p) titleParts.push(p); });
      }
      if (station) { titleParts.push(station); }
      if (col) { titleParts.push(col); }
      if (cargoState) { titleParts.push(cargoState); }
      $('#detailTitle').text(titleParts.join(' › ') || 'Детализация');

      if (!ctxDef) {
        $('#detailTable').html('<tbody><tr><td style="text-align:center;padding:40px;color:#9DA5B0">Неизвестный контекст</td></tr></tbody>');
        return;
      }

      // API URL
      var apiParams = new URLSearchParams();
      if (road) { apiParams.set('road', road); }
      if (station) { apiParams.set('station', station); }
      if (col) { apiParams.set('wagon_type', col); }
      // Остальные параметры URL (cargo_state, cargo, prev_cargo, group_by...)
      // передаем в API (активные фильтры)
      var handled = { ctx: 1, road: 1, station: 1, col: 1, _bcpath: 1 };
      params.forEach(function (v, k) {
        if (!handled[k] && v) { apiParams.set(k, v); }
      });
      // Поля из cols конфига 
      var fields = ctxDef.cols.map(function (c) { return c.key; }).join(',');
      apiParams.set('fields', fields);

      $('#detailSub').text('Загрузка...');

      $.getJSON(ctxDef.endpoint + (apiParams.toString() ? '?' + apiParams.toString() : ''))
        .done(function (data) {
          var rows = data.rows || [];
          $('#detailSub').text('Строк: ' + rows.length.toLocaleString('ru-RU'));
          showTable(rows, ctxDef.cols);
        })
        .fail(function (jqXHR) {
          var status = jqXHR.status ? ' (' + jqXHR.status + ')' : '';
          var detail = '';
          try { var j = JSON.parse(jqXHR.responseText); detail = j.error || j.message || ''; }
          catch (e) { detail = jqXHR.responseText || ''; }
          var msg = 'Ошибка загрузки данных: ' + status + (detail ? ': ' + detail : '');
          $('#detailTable').html('<tbody><tr><td style="text-align:center;padding:40px;color:#9DA5B0">' + esc(msg) + '</td></tr></tbody>');
          $('#detailSub').text(msg);
        });
    });

    function showTable(rows, cols) {
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

    function saveCSV(tableId, filename) {
      var rows = [];
      var $table = $('#' + tableId);
      $table.find('thead tr:first th').each(function () {
        rows.push ? null : (rows = []);
      });
      var headers = [];
      $table.find('thead tr:first th').each(function () { headers.push($(this).text()); });
      rows.push(headers.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(';'));
      $table.find('tbody tr:not(.search-row)').each(function () {
        if ($(this).is(':hidden')) return;
        var cells = [];
        $(this).find('td').each(function () { cells.push('"' + $(this).text().replace(/"/g, '""') + '"'); });
        rows.push(cells.join(';'));
      });
      var bom = '﻿';
      var blob = new Blob([bom + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (filename || 'детализация') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
      a.click();
      URL.revokeObjectURL(a.href);
    }

    $('#btnDetailCSV').on('click', function () {
      var title = $('#detailTitle').text().replace(/[\\/:*?"<>|]/g, '_');
      saveCSV('detailTable', title);
    });
  </script>
</body>

</html>