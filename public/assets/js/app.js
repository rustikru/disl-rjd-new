/* ================================================================
 * app.js — Основная логика приложения
 * Добавить новый раздел:
 *   1. Добавить запись в TAB_GROUPS
 *   2. Добавить панель <div id="panel-xxx"> в templates/app.php
 *   3. Добавить маршрут /api/xxx в src/routes.php
 *   4. Добавить метод в src/Controllers/ApiController.php
 * ================================================================ */

'use strict';

// Структура навигации. Для ссылок (url) вместо вкладок страница открывается в том же окне.
var TAB_GROUPS = [
  {
    label: '',
    tabs: [{ id: 'dashboard', label: 'Dashboard' }]
  },
  {
    label: 'Движение вагонов',
    tabs: [
      { id: 'dislocation', label: 'Дислокация' },
      { id: 'approach',    label: 'Подход вагонов' },
      { id: 'arrived',     label: 'Прибыло за сутки' },
      { id: 'trains',      label: 'Бросание поездов' }
    ]
  },
  {
    label: 'Аналитика',
    tabs: [
      { id: 'analysis',   label: 'Анализ за период' },
      { id: 'recipients', label: 'Вагоны у получателя' }
    ]
  },
  {
    label: 'Простои и оборот',
    tabs: [
      { id: 'downtime',     label: 'Простои' },
      { id: 'downtime-sum', label: 'Простои (Сводный)' },
      { id: 'turnover',     label: 'Оборот' }
    ]
  },
  {
    label: 'Управление',
    tabs: [
      { id: 'import', label: '↑ Загрузка справок', url: '/import' }
    ]
  }
];

// Сайдбар
function initSidebar() {
  var sidebar = document.getElementById('sidebar');
  TAB_GROUPS.forEach(function (group) {
    var groupEl = document.createElement('div');
    groupEl.className = 'nav-group' + (!group.label ? ' nav-group--top' : '');

    var labelEl = document.createElement('span');
    labelEl.className = 'nav-group-label';
    labelEl.textContent = group.label;
    groupEl.appendChild(labelEl);

    group.tabs.forEach(function (tab) {
      var btn = document.createElement('button');
      btn.className = 'nav-item' + (tab.id === 'dashboard' ? ' active' : '');
      btn.textContent = tab.label;
      btn.dataset.tab = tab.id;
      if (tab.url) {
        btn.addEventListener('click', function () { window.location.href = tab.url; });
      } else {
        btn.addEventListener('click', function () { switchTab(tab.id); });
      }
      groupEl.appendChild(btn);
    });

    sidebar.appendChild(groupEl);
  });
}

// Переключение вкладок
function switchTab(tabId) {
  document.querySelectorAll('.nav-item').forEach(function (btn) {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
  });
  document.querySelectorAll('.tab-panel').forEach(function (panel) {
    panel.classList.toggle('active', panel.id === 'panel-' + tabId);
  });
  if (tabId === 'dislocation' && !window._dislLoaded)    { loadDislocation(); }
  if (tabId === 'approach'    && !window._approachLoaded) { loadApproachInit(); }
}

// Внутренние вкладки (pill)
function initInnerTabs() {
  document.querySelectorAll('.inner-tabs').forEach(function (tabsEl) {
    tabsEl.querySelectorAll('.inner-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var innerId = btn.dataset.inner;
        tabsEl.querySelectorAll('.inner-tab').forEach(function (b) {
          b.classList.toggle('active', b.dataset.inner === innerId);
        });
        var panel = document.getElementById(innerId);
        if (panel) {
          panel.parentElement.querySelectorAll('.inner-panel').forEach(function (p) {
            p.classList.toggle('active', p.id === innerId);
          });
          if (innerId === 'disl-extended'  && !window._extLoaded)       { loadDislocationExtended(); }
          if (innerId === 'approach-detail' && !window._approachDetLoaded) { window._approachDetLoaded = true; loadApproachDetail(); }
        }
      });
    });
  });
}

// Загрузить список справок в дропдаун
function loadReports() {
  $.getJSON('/api/reports')
    .done(function (data) {
      var sel = $('#fReportDt');
      sel.find('option:not(:first)').remove();
      (data.reports || []).forEach(function (r) {
        sel.append($('<option>').val(r.report_dt).text(r.label + ' (' + r.cnt + ' ваг.)'));
      });
    });
}

// Dashboard
function loadDashboard() {
  $.getJSON('/api/dashboard')
    .done(function (data) {
      var label = data.updated_at || '—';
      $('#headerDate').text(label);
      $('#dashboardSub').text('Справка: ' + label + ' · РЖД');
      renderKPI(data.sections);
      renderBarChart(data.sections);
      renderDonutChart(data.sections);
    })
    .fail(function () {
      $('#dashboardSub').text('Ошибка загрузки данных');
    });
}

// KPI карточки
function renderKPI(sections) {
  var grandTotal = sections.reduce(function (s, x) { return s + x.total; }, 0);
  var tankTotal  = sections.reduce(function (s, x) { return s + (x.tank_total || 0); }, 0);

  var kpis = [
    { label: 'Общий парк',         value: grandTotal, accent: true },
    { label: 'Цистерны',            value: tankTotal },
    { label: 'Прочие вагоны',      value: grandTotal - tankTotal },
    { label: 'Типов парка',       value: sections.length, sub: 'разновидностей' },
  ];

  $('#kpiGrid').html(kpis.map(function (k) {
    return '<div class="kpi-card' + (k.accent ? ' accent' : '') + '">' +
      '<div class="kpi-value">' + k.value.toLocaleString('ru-RU') + '</div>' +
      '<div class="kpi-label">' + esc(k.label) + '</div>' +
      (k.sub ? '<div class="kpi-delta">' + esc(k.sub) + '</div>' : '') +
      '</div>';
  }).join(''));
}

// SVG столбчатый график
var BAR_COLORS = ['#4A7FCB', '#5B9E6B', '#8B62C4', '#3B9EAF', '#6B7EC4', '#D4622A', '#E8A530'];

function renderBarChart(sections) {
  if (!sections.length) { $('#sectionsChart').html('<p style="color:#9DA5B0;padding:20px">Нет данных</p>'); return; }
  var values = sections.map(function (s) { return s.total; });
  var labels = sections.map(function (s) { return s.name; });
  var max = Math.max.apply(null, values) || 1;
  var barH = 28, gap = 9, lw = 190, vw = 56, bw = 290;
  var svgH = sections.length * (barH + gap) - gap;

  var svg = '<svg width="100%" height="' + svgH + '" viewBox="0 0 ' + (lw + bw + vw) + ' ' + svgH +
    '" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible">';

  values.forEach(function (v, i) {
    var y      = i * (barH + gap);
    var barPx  = Math.max(4, Math.round((v / max) * bw));
    var color  = BAR_COLORS[i % BAR_COLORS.length];
    svg += '<text x="' + (lw - 8) + '" y="' + (y + barH / 2 + 4) + '" font-family="PT Sans,sans-serif" font-size="11" fill="#5C6370" text-anchor="end">' + esc(labels[i]) + '</text>';
    svg += '<rect x="' + lw + '" y="' + y + '" width="' + barPx + '" height="' + barH + '" rx="3" fill="' + color + '"/>';
    svg += '<text x="' + (lw + barPx + 6) + '" y="' + (y + barH / 2 + 4) + '" font-family="PT Sans,sans-serif" font-size="12" font-weight="700" fill="#1C2128">' + v.toLocaleString('ru-RU') + '</text>';
  });

  svg += '</svg>';
  $('#sectionsChart').html(svg);
}

// SVG донат
function renderDonutChart(sections) {
  var grandTotal = sections.reduce(function (s, x) { return s + x.total; }, 0);
  var cis        = sections.reduce(function (s, x) { return s + (x.tank_total || 0); }, 0);
  var oth        = grandTotal - cis;
  var r = 60, sw = 22;
  var circ = 2 * Math.PI * r;
  var dash = grandTotal > 0 ? (cis / grandTotal) * circ : 0;
  var pctC = grandTotal > 0 ? Math.round((cis / grandTotal) * 100) : 0;

  $('#typesChart').html(
    '<svg width="100%" height="170" viewBox="0 0 340 170" xmlns="http://www.w3.org/2000/svg">' +
      '<circle cx="85" cy="85" r="' + r + '" fill="none" stroke="#B8C8E8" stroke-width="' + sw + '"/>' +
      '<circle cx="85" cy="85" r="' + r + '" fill="none" stroke="#4A7FCB" stroke-width="' + sw + '"' +
        ' stroke-dasharray="' + dash.toFixed(2) + ' ' + circ.toFixed(2) + '" transform="rotate(-90 85 85)"/>' +
      '<text x="85" y="79" text-anchor="middle" font-family="PT Sans,sans-serif" font-size="21" font-weight="700" fill="#1C2128">' + grandTotal.toLocaleString('ru-RU') + '</text>' +
      '<text x="85" y="97" text-anchor="middle" font-family="PT Sans,sans-serif" font-size="11" fill="#9DA5B0">вагонов</text>' +
      '<rect x="180" y="54" width="12" height="12" rx="2" fill="#4A7FCB"/>' +
      '<text x="198" y="65" font-family="PT Sans,sans-serif" font-size="13" font-weight="700" fill="#1C2128">Цистерны</text>' +
      '<text x="198" y="82" font-family="PT Sans,sans-serif" font-size="12" fill="#5C6370">' + cis.toLocaleString('ru-RU') + ' ваг. · ' + pctC + '%</text>' +
      '<rect x="180" y="100" width="12" height="12" rx="2" fill="#B8C8E8"/>' +
      '<text x="198" y="111" font-family="PT Sans,sans-serif" font-size="13" font-weight="700" fill="#1C2128">Прочие</text>' +
      '<text x="198" y="128" font-family="PT Sans,sans-serif" font-size="12" fill="#5C6370">' + oth.toLocaleString('ru-RU') + ' ваг. · ' + (100 - pctC) + '%</text>' +
    '</svg>'
  );
}

// Сводная дислокация
function loadDislocation() {
  window._dislLoaded = true;
  var reportDt = $('#fReportDt').val();
  $('#mainTableSub').text('Загрузка...');

  var params = reportDt ? { report_dt: reportDt } : {};
  $.getJSON('/api/dislocation/summary', params)
    .done(function (data) {
      if (!data.cols || !data.cols.length) {
        $('#mainTable').html('<tbody><tr><td style="text-align:center;padding:40px;color:#9DA5B0">' +
          (data.report_dt_label || 'Нет данных. Загрузите справку через «Управление → Загрузка справок»') +
          '</td></tr></tbody>');
        $('#mainTableSub').text(data.report_dt_label || '');
        return;
      }
      renderMainTable(data.sections, data.cols);
      $('#mainTableSub').text((data.report_dt_label || data.date || '') + ' · РЖД');
    })
    .fail(function () {
      $('#mainTable').html('<tbody><tr><td style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки</td></tr></tbody>');
    });
}

function renderMainTable(sections, cols) {
  function fmt(v) { return v || ''; }

  var h = '<thead><tr>';
  h += '<th class="col-meta">Раздел</th>';
  h += '<th class="col-meta">Тип парка</th>';
  cols.forEach(function (c) { h += '<th>' + esc(c.label) + '</th>'; });
  h += '<th class="col-total-col">Итого</th>';
  h += '</tr></thead><tbody>';

  sections.forEach(function (section) {
    var firstRow = true;
    section.rows.forEach(function (row) {
      var rowSum = row.v.reduce(function (a, b) { return a + b; }, 0);
      h += '<tr class="row-data">';
      h += '<td class="col-meta' + (firstRow ? ' cell-section' : '') + '">' + (firstRow ? esc(section.name) : '') + '</td>';
      h += '<td class="col-meta">' + esc(row.sub || '') + '</td>';
      row.v.forEach(function (v) { h += '<td>' + fmt(v) + '</td>'; });
      h += '<td class="col-total-col">' + fmt(rowSum) + '</td></tr>';
      firstRow = false;
    });
    h += '<tr class="row-total"><td class="col-meta" colspan="2">' + esc(section.name) + ' — итого</td>';
    section.total.forEach(function (v) { h += '<td>' + fmt(v) + '</td>'; });
    h += '<td class="col-total-col">' + section.grand_total.toLocaleString('ru-RU') + '</td></tr>';
  });

  var grandTotals = cols.map(function (_, ci) {
    return sections.reduce(function (s, sec) { return s + (sec.total[ci] || 0); }, 0);
  });
  var grandSum = grandTotals.reduce(function (a, b) { return a + b; }, 0);
  h += '<tr class="row-total row-grand"><td class="col-meta" colspan="2">Общий итог</td>';
  grandTotals.forEach(function (v) { h += '<td>' + (v || '') + '</td>'; });
  h += '<td class="col-total-col">' + grandSum.toLocaleString('ru-RU') + '</td></tr></tbody>';

  $('#mainTable').html(h);
}

// Расширенная дислокация
function loadDislocationExtended() {
  window._extLoaded = true;
  $.getJSON('/api/dislocation/extended')
    .done(function (data) { renderExtendedTable(data.rows); })
    .fail(function () {
      $('#dislExtTable').html('<tbody><tr><td colspan="10" style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки</td></tr></tbody>');
    });
}

function renderExtendedTable(rows) {
  var h = '<thead><tr>' +
    '<th class="col-meta">№ вагона</th>' +
    '<th class="col-meta">Поезд №</th>' +
    '<th class="col-meta">Тек. станция</th>' +
    '<th class="col-meta">Ст. отправл.</th>' +
    '<th class="col-meta">Ст. назнач.</th>' +
    '<th class="col-meta">Груз</th>' +
    '<th class="col-meta">Тип парка</th>' +
    '<th class="col-meta">Операция</th>' +
    '<th>Простой (дн)</th>' +
    '<th class="col-meta">Приб. (АСОУП)</th>' +
    '</tr></thead><tbody>';

  (rows || []).forEach(function (r) {
    h += '<tr class="row-data">' +
      '<td class="col-meta" style="font-family:monospace;font-size:11px">' + esc(r.wagon_no) + '</td>' +
      '<td class="col-meta">' + esc(r.train_no) + '</td>' +
      '<td class="col-meta">' + esc(r.oper_station) + '</td>' +
      '<td class="col-meta">' + esc(r.depart_station) + '</td>' +
      '<td class="col-meta">' + esc(r.dest_station) + '</td>' +
      '<td class="col-meta">' + esc(r.cargo_name) + '</td>' +
      '<td class="col-meta">' + esc(r.park_type) + '</td>' +
      '<td class="col-meta">' + esc(r.oper_mnemonic) + '</td>' +
      '<td>' + esc(r.idle_time_days) + '</td>' +
      '<td class="col-meta">' + esc(r.asoup_arrive_dt) + '</td>' +
      '</tr>';
  });

  $('#dislExtTable').html(h + '</tbody>');
}

// ── Подход вагонов ──────────────────────────────────────────────

function approachParams() {
  return {
    cargo:      $('#fApproachCargo').val()     || undefined,
    prev_cargo: $('#fApproachPrevCargo').val() || undefined
  };
}

function loadApproachInit() {
  window._approachLoaded = true;
  loadApproachFilters();
  loadApproachSummary();
}

function loadApproachFilters() {
  $.getJSON('/api/approach/filters')
    .done(function (data) {
      var cSel  = $('#fApproachCargo');
      var pcSel = $('#fApproachPrevCargo');
      cSel.find('option:not(:first)').remove();
      pcSel.find('option:not(:first)').remove();
      (data.cargo || []).forEach(function (v) { cSel.append($('<option>').val(v).text(v)); });
      (data.prev_cargo || []).forEach(function (v) { pcSel.append($('<option>').val(v).text(v)); });
    });
}

function loadApproachSummary() {
  $('#approachSumSub').text('Загрузка...');
  $('#approachSumTable').html('<tbody><tr><td colspan="5" style="text-align:center;padding:40px;color:#9DA5B0">Загрузка...</td></tr></tbody>');
  $.getJSON('/api/approach/summary', approachParams())
    .done(function (data) {
      renderApproachMetrics(data.metrics, data.total);
      renderApproachSummaryTable(data.roads, data.cols);
      $('#approachSumSub').text('Всего в подходе: ' + (data.total || 0).toLocaleString('ru-RU') + ' ваг.');
    })
    .fail(function () {
      $('#approachSumTable').html('<tbody><tr><td colspan="5" style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки</td></tr></tbody>');
      $('#approachSumSub').text('');
    });
}

function loadApproachDetail() {
  $('#approachDetSub').text('Загрузка...');
  $.getJSON('/api/approach/detail', approachParams())
    .done(function (data) {
      renderApproachDetailTable(data.rows);
      $('#approachDetSub').text('Строк: ' + (data.rows || []).length.toLocaleString('ru-RU'));
    })
    .fail(function () {
      $('#approachDetTable').html('<tbody><tr><td colspan="10" style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки</td></tr></tbody>');
    });
}

function renderApproachMetrics(metrics, total) {
  var all = [{ road: 'Всего в подходе', total: total, accent: true }].concat(metrics || []);
  $('#approachMetrics').html(all.map(function (m) {
    return '<div class="kpi-card' + (m.accent ? ' accent' : '') + '">' +
      '<div class="kpi-value">' + (m.total || 0).toLocaleString('ru-RU') + '</div>' +
      '<div class="kpi-label">' + esc(m.road) + '</div>' +
      '</div>';
  }).join(''));
}

function renderApproachSummaryTable(roads, cols) {
  if (!roads || !roads.length) {
    $('#approachSumTable').html('<tbody><tr><td colspan="5" style="text-align:center;padding:40px;color:#9DA5B0">Нет данных. Загрузите справку.</td></tr></tbody>');
    return;
  }

  function fmt(v) { return v || ''; }

  var h = '<thead><tr>';
  h += '<th class="col-meta" style="min-width:160px">Дорога назначения</th>';
  h += '<th class="col-meta" style="min-width:180px">Станция назначения</th>';
  (cols || []).forEach(function (c) { h += '<th>' + esc(c) + '</th>'; });
  h += '<th class="col-total-col">Итого</th>';
  h += '</tr></thead><tbody>';

  var grandTotals = (cols || []).map(function () { return 0; });
  var grandSum    = 0;

  (roads || []).forEach(function (road) {
    var firstRow = true;
    (road.stations || []).forEach(function (st) {
      var rowSum = (st.v || []).reduce(function (a, b) { return a + b; }, 0);
      h += '<tr class="row-data">';
      h += '<td class="col-meta cell-section">' + (firstRow ? esc(road.road) : '') + '</td>';
      h += '<td class="col-meta">' + esc(st.station) + '</td>';
      (st.v || []).forEach(function (v) { h += '<td>' + fmt(v) + '</td>'; });
      h += '<td class="col-total-col">' + fmt(rowSum) + '</td></tr>';
      firstRow = false;
    });
    // Итоговая строка дороги
    h += '<tr class="row-total"><td class="col-meta" colspan="2">' + esc(road.road) + ' — итого</td>';
    (road.total || []).forEach(function (v, i) {
      grandTotals[i] = (grandTotals[i] || 0) + (v || 0);
      h += '<td>' + fmt(v) + '</td>';
    });
    h += '<td class="col-total-col">' + (road.grand_total || 0).toLocaleString('ru-RU') + '</td></tr>';
    grandSum += (road.grand_total || 0);
  });

  // Общий итог
  h += '<tr class="row-total row-grand"><td class="col-meta" colspan="2">Общий итог</td>';
  grandTotals.forEach(function (v) { h += '<td>' + (v || '') + '</td>'; });
  h += '<td class="col-total-col">' + grandSum.toLocaleString('ru-RU') + '</td></tr></tbody>';

  $('#approachSumTable').html(h);
}

function renderApproachDetailTable(rows) {
  var h = '<thead><tr>' +
    '<th class="col-meta">№ вагона</th>' +
    '<th class="col-meta">Род вагона</th>' +
    '<th class="col-meta">Груз</th>' +
    '<th class="col-meta">Ранее выгружен</th>' +
    '<th>Ост. расстояние</th>' +
    '<th class="col-meta">Ст. отправл.</th>' +
    '<th class="col-meta">Тек. станция</th>' +
    '<th class="col-meta">Ст. назнач.</th>' +
    '<th class="col-meta">Дорога назнач.</th>' +
    '<th class="col-meta">Норм. дата дост.</th>' +
    '</tr></thead><tbody>';

  (rows || []).forEach(function (r) {
    var dist = parseInt(r.dist_remain_km) || '';
    h += '<tr class="row-data">' +
      '<td class="col-meta" style="font-family:monospace;font-size:11px">' + esc(r.wagon_no) + '</td>' +
      '<td class="col-meta">' + esc(r.wagon_type_code) + '</td>' +
      '<td class="col-meta">' + esc(r.cargo_name) + '</td>' +
      '<td class="col-meta">' + esc(r.prev_cargo) + '</td>' +
      '<td style="text-align:right">' + (dist ? dist.toLocaleString('ru-RU') + ' км' : '') + '</td>' +
      '<td class="col-meta">' + esc(r.depart_station) + '</td>' +
      '<td class="col-meta">' + esc(r.oper_station) + '</td>' +
      '<td class="col-meta">' + esc(r.dest_station) + '</td>' +
      '<td class="col-meta">' + esc(r.dest_road) + '</td>' +
      '<td class="col-meta">' + esc(r.norm_delivery_dt) + '</td>' +
      '</tr>';
  });

  $('#approachDetTable').html(h + '</tbody>');
}

// CSV-экспорт
function exportToCSV() {
  var table = document.getElementById('mainTable');
  if (!table) return;
  var rows = [];
  table.querySelectorAll('tr').forEach(function (tr) {
    var cells = [];
    tr.querySelectorAll('th, td').forEach(function (cell) {
      cells.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
    });
    rows.push(cells.join(','));
  });
  var csv = '﻿' + rows.join('\n');
  var a   = document.createElement('a');
  a.href     = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
  a.download = 'дислокация_' + new Date().toISOString().slice(0, 10) + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

// Экранирование HTML
function esc(str) {
  if (!str && str !== 0) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Старт
$(function () {
  initSidebar();
  initInnerTabs();
  loadDashboard();
  loadReports();

  $('#btnApply').on('click', function () {
    window._dislLoaded = false;
    loadDislocation();
  });

  $('#btnReset').on('click', function () {
    $('#fReportDt').val('');
    window._dislLoaded = false;
    loadDislocation();
  });

  $('#btnExportCSV').on('click', exportToCSV);

  // Подход — фильтры
  $('#btnApproachApply').on('click', function () {
    window._approachDetLoaded = false;
    loadApproachSummary();
    if ($('#approach-detail').hasClass('active')) { window._approachDetLoaded = true; loadApproachDetail(); }
  });

  $('#btnApproachReset').on('click', function () {
    $('#fApproachCargo').val('');
    $('#fApproachPrevCargo').val('');
    window._approachDetLoaded = false;
    loadApproachSummary();
  });
});
