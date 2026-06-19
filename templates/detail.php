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

    #detailTable {
      overflow: hidden;
    }

    .detail-tabs-bar {
      display: flex;
      gap: 2px;
      border-bottom: 2px solid var(--border);
      margin-bottom: 12px;
    }

    .detail-tab-btn {
      padding: 8px 16px;
      font-size: 12px;
      font-weight: 500;
      border: none;
      background: none;
      color: var(--text-2);
      cursor: pointer;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      border-radius: 6px 6px 0 0;
      transition: color .15s, border-color .15s;
      white-space: nowrap;
    }

    .detail-tab-btn:hover {
      color: var(--text-1);
      background: var(--hover-green);
    }

    .detail-tab-btn.active {
      color: var(--accent);
      border-bottom-color: var(--accent);
      font-weight: 600;
    }
  </style>
</head>

<body>

  <header class="site-header">
    <div class="header-inner">
      <div class="brand">
        <div class="brand-icon">
          <img src="<?= htmlspecialchars($basePath) ?>/assets/img/meta-logo.png" alt="" class="brand-logo">
        </div>
        <div class="brand-text">
          <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
          <div id="brandDateSub" class="brand-date-sub"></div>
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
      <div class="detail-tabs-bar" id="detailTabsBar" style="display:none"></div>
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
      var ctx = params.get('ctx') || '';
      var road = params.get('road') || '';
      var station = params.get('station') || '';
      var wagType = params.get('wagon_type') || '';
      var cargoState = params.get('cargo_state') || '';

      var ctxDef = null;
      var def = DETAIL_CONTEXTS[ctx];
      if (def) {
        var endpointOverride = params.get('_endpoint');
        ctxDef = {
          label: def.label,
          endpoint: BASE + (endpointOverride || def.endpoint),
          cols: def.cols,
          sort: def.sort || null,
        };
      }

      $('#breadcrumb').html('<span class="bc-item"><a href="' + (window.APP_BASE || '') + '/" style="color:inherit;text-decoration:none">← Вернуться</a></span>');

      var bcpathRaw = params.get('_bcpath') || '';
      var bcpathParts = [];
      try { if (bcpathRaw) bcpathParts = JSON.parse(bcpathRaw); } catch (e) { }
      var titleParts = [];
      if (ctxDef) { titleParts.push(ctxDef.label); }
      if (road) { titleParts.push(road); }
      else if (bcpathParts.length) { bcpathParts.forEach(function (p) { if (p) titleParts.push(p); }); }
      if (station) { titleParts.push(station); }
      if (wagType) { titleParts.push(wagType); }
      if (cargoState) { titleParts.push(cargoState); }
      $('#detailTitle').text(titleParts.join(' › ') || 'Детализация');

      if (!ctxDef) {
        $('#detailTable').html('<div style="text-align:center;padding:40px;color:#9DA5B0">Неизвестный контекст</div>');
        return;
      }

      // Все параметры детализации заданы в WAGON_TABS[ctx].mapDetailParams на фронтенде.
      // Здесь просто прокидываем их дальше на бэкенд (кроме ctx и _bcpath).
      var apiParams = new URLSearchParams();
      var skipKeys = { ctx: 1, _bcpath: 1, _endpoint: 1 };
      params.forEach(function (v, k) { if (!skipKeys[k] && v) { apiParams.set(k, v); } });
      apiParams.set('fields', ctxDef.cols.map(function (c) { return c.key; }).join(','));

      if (ctxDef.sort && !apiParams.has('sort')) {
        var sortArr = Array.isArray(ctxDef.sort) ? ctxDef.sort : [ctxDef.sort];
        sortArr = sortArr.filter(function (s) { return s && s.field; });
        if (sortArr.length) {
          apiParams.set('sort', sortArr.map(function (s) { return s.field; }).join(','));
          apiParams.set('sort_dir', sortArr.map(function (s) { return s.dir || 'asc'; }).join(','));
          var types = sortArr.map(function (s) { return s.type || ''; }).join(',');
          if (types.replace(/,/g, '')) apiParams.set('sort_type', types);
        }
      }

      // Вкладки drill-down (только если DETAIL_TABS определён и контекст их поддерживает)
      // drillDown !== false — фильтруем поля, скрытые для страницы детализации
      var allCols   = ctxDef.cols.filter(function (c) { return c.drillDown !== false })
      var hasTabs   = typeof DETAIL_TABS !== 'undefined' && DETAIL_TABS.length > 0
      // visTabs — только вкладки, у которых есть хотя бы одно поле
      var visTabs   = hasTabs ? DETAIL_TABS.filter(function (t) {
        return allCols.some(function (c) { return (c.tab || 'main') === t.key })
      }) : []
      var activeTab = visTabs.length ? visTabs[0].key : null

      function getTabCols(tab) {
        if (!tab) return allCols
        return allCols.filter(function (c) { return (c.tab || 'main') === tab })
      }

      function renderTabs() {
        var bar = document.getElementById('detailTabsBar')
        if (!visTabs.length) return
        bar.style.display = 'flex'
        bar.innerHTML = visTabs.map(function (t) {
          return '<button class="detail-tab-btn' + (t.key === activeTab ? ' active' : '') +
                 '" data-tab="' + t.key + '">' + esc(t.name) + '</button>'
        }).join('')
        bar.querySelectorAll('.detail-tab-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            activeTab = this.getAttribute('data-tab')
            renderTabs()
            showTable(_vtAllData, getTabCols(activeTab))
          })
        })
      }

      $('#detailSub').text('Загрузка...');

      $.getJSON(ctxDef.endpoint + (apiParams.toString() ? '?' + apiParams.toString() : ''))
        .done(function (data) {
          var rows = data.rows || [];
          $('#detailSub').text('Строк: ' + rows.length.toLocaleString('ru-RU'));
          showTable(rows, getTabCols(activeTab));
          renderTabs();
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

    function oracleMaskFmt(v, mask) {
      if (v == null || v === '') return '';
      var s = String(v);
      var d = null;
      var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):?(\d{2})?/);
      if (m) d = { DD: m[3], MM: m[2], YYYY: m[1], HH24: m[4], MI: m[5], SS: m[6] || '00' };
      if (!d) {
        m = s.match(/^(\d{2})\.(\d{2})\.(\d{4})[T ](\d{2}):(\d{2}):?(\d{2})?/);
        if (m) d = { DD: m[1], MM: m[2], YYYY: m[3], HH24: m[4], MI: m[5], SS: m[6] || '00' };
      }
      if (!d) return s;
      return mask.replace('HH24', d.HH24).replace('YYYY', d.YYYY)
        .replace('MM', d.MM).replace('DD', d.DD)
        .replace('MI', d.MI).replace('SS', d.SS);
    }

    function showTable(rows, cols) {
      cols = cols.map(function (c) {
        if (c.formatData && !c.fmt) {
          var mask = c.formatData;
          return Object.assign({}, c, { fmt: function (v) { return oracleMaskFmt(v, mask); } });
        }
        return c;
      });
      _vtAllData = rows || [];
      _vtFiltered = _vtAllData.slice();
      _vtCols = cols;

      var ROW_H = 28;
      var BUFFER = 8;
      var measured = [];

      (function measureCols() {
        var cv = document.createElement('canvas');
        var ctx = cv.getContext('2d');
        var PAD = 20, MIN = 50, MAX = 320;
        ctx.font = 'bold 12px sans-serif';
        measured = cols.map(function (c) {
          return Math.max(MIN, Math.min(MAX, Math.ceil(ctx.measureText(c.label).width) + PAD));
        });
        ctx.font = '12px sans-serif';
        var sample = _vtAllData.length > 300 ? _vtAllData.slice(0, 300) : _vtAllData;
        sample.forEach(function (row) {
          cols.forEach(function (c, i) {
            var v = c.fmt ? c.fmt(row[c.key]) : (row[c.key] == null ? '' : String(row[c.key]));
            var w = Math.ceil(ctx.measureText(String(v == null ? '' : v)).width) + PAD;
            if (w > measured[i]) measured[i] = Math.min(MAX, w);
          });
        });
      })();
      var availW = document.getElementById('detailTable').offsetWidth || (window.innerWidth - 40);
      var baseW = measured.reduce(function (s, w) { return s + w; }, 0);
      var scale = availW > baseW ? availW / baseW : 1;
      var template = measured.map(function (w) { return Math.floor(w * scale) + 'px'; }).join(' ');
      var totalW = availW > baseW ? availW : baseW;

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
      document.getElementById('vtHead').innerHTML = hHtml;
      document.getElementById('vtFilter').innerHTML = fHtml;

      function cellHtml(c, row) {
        var v = row[c.key];
        var display = (v !== null && v !== undefined && v !== '') ? v : '';
        if (c.fmt) display = c.fmt(v);
        var cls = 'vt-cell' + (c.meta ? ' col-meta' : '') + (c.right ? ' vt-right' : '');
        var style = '';
        if (c.danger) {
          var d = parseFloat(display) || 0;
          if (d >= 7) style = ' style="color:#E8392A;font-weight:700"';
          else if (d >= 3) style = ' style="color:#E8A530;font-weight:600"';
        }
        return '<div class="' + cls + '"' + style + '>' + esc(String(display)) + '</div>';
      }

      var vp = document.getElementById('vtVp');
      var rowsEl = document.getElementById('vtRows');
      var lastFirst = -1, lastLast = -1;

      function render(force) {
        var scrollTop = vp.scrollTop;
        var total = _vtFiltered.length;
        var viewRows = Math.ceil(vp.clientHeight / ROW_H);
        var first = Math.max(0, Math.floor(scrollTop / ROW_H) - BUFFER);
        var last = Math.min(total, first + viewRows + BUFFER * 2);
        if (!force && first === lastFirst && last === lastLast) return;
        lastFirst = first; lastLast = last;

        if (!total) {
          rowsEl.style.paddingTop = '0';
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
        rowsEl.style.paddingTop = (first * ROW_H) + 'px';
        rowsEl.style.paddingBottom = ((total - last) * ROW_H) + 'px';
        rowsEl.innerHTML = html;
      }

      document.getElementById('vtFilter').addEventListener('input', function () {
        var inputs = this.querySelectorAll('input');
        var terms = [];
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

      function fitVpHeight() {
        var top = vp.getBoundingClientRect().top;
        var h = top > 0 ? window.innerHeight - top - 10 : window.innerHeight - 10;
        vp.style.height = Math.max(300, h) + 'px';
        render(false);
      }

      requestAnimationFrame(function () { fitVpHeight(); render(true); });
      window.addEventListener('resize', fitVpHeight, { passive: true });
      window.addEventListener('scroll', fitVpHeight, { passive: true });

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