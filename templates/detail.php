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
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
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
    .detail-breadcrumb .bc-sep { color: var(--border); }
    .detail-breadcrumb .bc-item { color: var(--text-2); }
    .detail-breadcrumb .bc-item.bc-active { color: var(--text-1); font-weight: 700; }
    .detail-page-body { padding: 16px 20px 40px; max-width: 100%; }
    .detail-header-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }

    /* ── Виртуальная таблица ── */
    #detailTable { overflow: hidden; }
    .vt-viewport {
      height: calc(100vh - 210px);
      min-height: 300px;
      overflow: auto;
      position: relative;
    }
    .vt-content { position: relative; }
    .vt-head, .vt-filter, .vt-row { display: grid; }
    .vt-head {
      position: sticky; top: 0; z-index: 3;
      background: #f0f2f5;
      border-bottom: 2px solid var(--border, #dde0e6);
    }
    .vt-filter {
      position: sticky; top: 34px; z-index: 2;
      background: #fafbfc;
      border-bottom: 1px solid var(--border, #dde0e6);
    }
    .vt-th {
      padding: 0 10px; height: 34px;
      display: flex; align-items: center;
      font-size: 11px; font-weight: 600;
      letter-spacing: .04em; text-transform: uppercase;
      color: var(--text-2, #6b7682);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      user-select: none;
    }
    .vt-th.col-meta { color: var(--text-3, #9DA5B0); }
    .vt-fc { padding: 3px 6px; }
    .vt-fc input {
      width: 100%; padding: 3px 6px;
      border: 1px solid var(--border, #dde0e6);
      border-radius: 4px; font-size: 11px;
      background: var(--bg, #f5f6fa);
      color: var(--text-1, #1b2127);
    }
    .vt-row {
      height: 34px;
      border-bottom: 1px solid #eef1f3;
    }
    .vt-row:hover { background: rgba(0,0,0,.025); }
    .vt-cell {
      padding: 0 10px; height: 34px;
      display: flex; align-items: center;
      font-size: 13px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .vt-cell.col-meta { color: var(--text-3, #9DA5B0); }
    .vt-cell.vt-right { justify-content: flex-end; }
    .vt-empty {
      padding: 40px; text-align: center;
      color: var(--text-3, #9DA5B0); font-size: 14px;
    }
  </style>
</head>

<body>

  <header class="site-header">
    <div class="header-inner">
      <div class="brand">
        <div class="brand-icon"></div>
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
      <div id="detailTable"></div>
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

    function attachFloatScrollbar(scrollEl) {
      if (scrollEl._floatScrollbar) scrollEl._floatScrollbar.remove();
      var floater = document.createElement('div');
      floater.className = 'float-scrollbar';
      var inner = document.createElement('div');
      inner.className = 'float-scrollbar-inner';
      floater.appendChild(inner);
      document.body.appendChild(floater);
      scrollEl._floatScrollbar = floater;
      var syncing = false;
      floater.addEventListener('scroll', function () { if (syncing) return; syncing = true; scrollEl.scrollLeft = floater.scrollLeft; syncing = false; });
      scrollEl.addEventListener('scroll', function () { if (syncing) return; syncing = true; floater.scrollLeft = scrollEl.scrollLeft; syncing = false; });
      function update() {
        var rect = scrollEl.getBoundingClientRect();
        var needsScroll = scrollEl.scrollWidth > scrollEl.clientWidth;
        if (needsScroll && rect.top < window.innerHeight && rect.bottom > window.innerHeight) {
          floater.style.display = 'block';
          floater.style.left = rect.left + 'px';
          floater.style.width = rect.width + 'px';
          inner.style.width = scrollEl.scrollWidth + 'px';
        } else { floater.style.display = 'none'; }
      }
      window.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update, { passive: true });
      update();
    }

    /* Данные для CSV — обновляются при каждом showTable */
    var _vtAllData = [], _vtFiltered = [], _vtCols = [];

    $(function () {
      var params = new URLSearchParams(window.location.search);
      var ctx      = params.get('ctx')         || '';
      var road     = params.get('road')        || '';
      var station  = params.get('station')     || '';
      var col      = params.get('col')         || '';
      var cargoState = params.get('cargo_state') || '';

      var ctxDef = null;
      var def = DETAIL_CONTEXTS[ctx];
      if (def) {
        ctxDef = { label: def.label, endpoint: BASE + def.endpoint, cols: def.cols, sort: def.sort || null };
      }

      $('#breadcrumb').html('<span class="bc-item"><a href="' + (window.APP_BASE || '') + '/" style="color:inherit;text-decoration:none">← Вернуться</a></span>');

      var bcpathRaw = params.get('_bcpath') || '';
      var bcpathParts = [];
      try { if (bcpathRaw) bcpathParts = JSON.parse(bcpathRaw); } catch (e) {}
      var titleParts = [];
      if (ctxDef) { titleParts.push(ctxDef.label); }
      if (road) { titleParts.push(road); }
      else if (bcpathParts.length) { bcpathParts.forEach(function (p) { if (p) titleParts.push(p); }); }
      if (station)   { titleParts.push(station); }
      if (col)       { titleParts.push(col); }
      if (cargoState){ titleParts.push(cargoState); }
      $('#detailTitle').text(titleParts.join(' › ') || 'Детализация');

      if (!ctxDef) {
        $('#detailTable').html('<div style="text-align:center;padding:40px;color:#9DA5B0">Неизвестный контекст</div>');
        return;
      }

      var apiParams = new URLSearchParams();
      if (road)    { apiParams.set('road', road); }
      if (station) { apiParams.set('station', station); }
      if (col)     { apiParams.set('wagon_type', col); }
      var handled = { ctx: 1, road: 1, station: 1, col: 1, _bcpath: 1 };
      params.forEach(function (v, k) { if (!handled[k] && v) { apiParams.set(k, v); } });
      apiParams.set('fields', ctxDef.cols.map(function (c) { return c.key; }).join(','));

      if (ctxDef.sort && !apiParams.has('sort')) {
        var sortArr = Array.isArray(ctxDef.sort) ? ctxDef.sort : [ctxDef.sort];
        sortArr = sortArr.filter(function (s) { return s && s.field; });
        if (sortArr.length) {
          apiParams.set('sort',     sortArr.map(function (s) { return s.field; }).join(','));
          apiParams.set('sort_dir', sortArr.map(function (s) { return s.dir || 'asc'; }).join(','));
          var types = sortArr.map(function (s) { return s.type || ''; }).join(',');
          if (types.replace(/,/g, '')) apiParams.set('sort_type', types);
        }
      }

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
          var msg = 'Ошибка загрузки данных' + status + (detail ? ': ' + detail : '');
          $('#detailTable').html('<div style="text-align:center;padding:40px;color:#9DA5B0">' + esc(msg) + '</div>');
          $('#detailSub').text(msg);
        });
    });

    function showTable(rows, cols) {
      _vtAllData  = rows || [];
      _vtFiltered = _vtAllData.slice();
      _vtCols     = cols;

      var ROW_H  = 34;
      var BUFFER = 8;
      var DEF_W  = 130;

      var template = cols.map(function (c) { return (c.w || DEF_W) + 'px'; }).join(' ');
      var totalW   = cols.reduce(function (s, c) { return s + (c.w || DEF_W); }, 0);

      $('#detailTable').html(
        '<div class="vt-viewport" id="vtVp">' +
          '<div class="vt-content" style="width:' + totalW + 'px">' +
            '<div class="vt-head"   id="vtHead"   style="grid-template-columns:' + template + ';width:' + totalW + 'px"></div>' +
            '<div class="vt-filter" id="vtFilter" style="grid-template-columns:' + template + ';width:' + totalW + 'px"></div>' +
            '<div id="vtRows"></div>' +
          '</div>' +
        '</div>'
      );

      var hHtml = '', fHtml = '';
      cols.forEach(function (c) {
        hHtml += '<div class="vt-th' + (c.meta ? ' col-meta' : '') + '">' + esc(c.label) + '</div>';
        fHtml += '<div class="vt-fc"><input data-k="' + c.key + '" type="text" placeholder=""></div>';
      });
      document.getElementById('vtHead').innerHTML   = hHtml;
      document.getElementById('vtFilter').innerHTML = fHtml;

      function cellHtml(c, row) {
        var v = row[c.key];
        var display = (v !== null && v !== undefined && v !== '') ? v : '';
        if (c.fmt) display = c.fmt(v);
        var cls = 'vt-cell' + (c.meta ? ' col-meta' : '') + (c.right ? ' vt-right' : '');
        var style = '';
        if (c.danger) {
          var d = parseFloat(display) || 0;
          if (d >= 7)      style = ' style="color:#E8392A;font-weight:700"';
          else if (d >= 3) style = ' style="color:#E8A530;font-weight:600"';
        }
        return '<div class="' + cls + '"' + style + '>' + esc(String(display)) + '</div>';
      }

      var vp      = document.getElementById('vtVp');
      var rowsEl  = document.getElementById('vtRows');
      var lastFirst = -1, lastLast = -1;

      function render(force) {
        var scrollTop = vp.scrollTop;
        var total = _vtFiltered.length;
        var viewRows = Math.ceil(vp.clientHeight / ROW_H);
        var first = Math.max(0, Math.floor(scrollTop / ROW_H) - BUFFER);
        var last  = Math.min(total, first + viewRows + BUFFER * 2);
        if (!force && first === lastFirst && last === lastLast) return;
        lastFirst = first; lastLast = last;

        if (!total) {
          rowsEl.style.paddingTop    = '0';
          rowsEl.style.paddingBottom = '0';
          rowsEl.innerHTML = '<div class="vt-empty">Нет данных</div>';
          return;
        }
        var html = '';
        for (var i = first; i < last; i++) {
          html += '<div class="vt-row" style="grid-template-columns:' + template + ';width:' + totalW + 'px">';
          cols.forEach(function (c) { html += cellHtml(c, _vtFiltered[i]); });
          html += '</div>';
        }
        rowsEl.style.paddingTop    = (first * ROW_H) + 'px';
        rowsEl.style.paddingBottom = ((total - last) * ROW_H) + 'px';
        rowsEl.innerHTML = html;
      }

      document.getElementById('vtFilter').addEventListener('input', function () {
        var inputs = this.querySelectorAll('input');
        var terms  = [];
        for (var i = 0; i < inputs.length; i++) {
          var v = inputs[i].value.trim().toLowerCase();
          if (v) terms.push({ k: inputs[i].getAttribute('data-k'), v: v });
        }
        _vtFiltered = !terms.length ? _vtAllData.slice() : _vtAllData.filter(function (row) {
          for (var t = 0; t < terms.length; t++) {
            if (String(row[terms[t].k] == null ? '' : row[terms[t].k]).toLowerCase().indexOf(terms[t].v) === -1) return false;
          }
          return true;
        });
        lastFirst = lastLast = -1;
        render(true);
        $('#detailSub').text('Строк: ' + _vtFiltered.length.toLocaleString('ru-RU') +
          (_vtFiltered.length < _vtAllData.length ? ' (отфильтровано из ' + _vtAllData.length.toLocaleString('ru-RU') + ')' : ''));
      });

      var ticking = false;
      vp.addEventListener('scroll', function () {
        if (ticking) return; ticking = true;
        requestAnimationFrame(function () { render(false); ticking = false; });
      });

      render(true);
      attachFloatScrollbar(vp);
    }

    function saveCSV(filename) {
      function cleanCell(v) {
        return '"' + String(v == null ? '' : v).trim().replace(/\r?\n|\r/g, ' ').replace(/"/g, '""') + '"';
      }
      var lines = [];
      lines.push(_vtCols.map(function (c) { return cleanCell(c.label); }).join(';'));
      _vtAllData.forEach(function (row) {
        lines.push(_vtCols.map(function (c) {
          var v = row[c.key];
          return cleanCell(c.fmt ? c.fmt(v) : v);
        }).join(';'));
      });
      var blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (filename || 'детализация') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
      a.click();
      URL.revokeObjectURL(a.href);
    }

    $('#btnDetailCSV').on('click', function () {
      var title = $('#detailTitle').text().replace(/[\\/:*?"<>|]/g, '_');
      saveCSV(title);
    });
  </script>
</body>

</html>
