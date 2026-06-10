/* ================================================================
 * app.js — Основная логика приложения
 * Загружает данные из API, строит таблицы, графики и навигацию.
 * Добавить новый раздел:
 *   1. Добавить запись в TAB_GROUPS
 *   2. Добавить панель <div id="panel-xxx"> в templates/app.php
 *   3. Добавить маршрут /api/xxx в src/routes.php
 *   4. Добавить метод в src/Controllers/ApiController.php
 * ================================================================ */

'use strict';

// ── Структура навигации (сайдбар) ────────────────────────────────
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
  }
];

// ── Сайдбар ──────────────────────────────────────────────────────
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
      btn.addEventListener('click', function () { switchTab(tab.id); });
      groupEl.appendChild(btn);
    });

    sidebar.appendChild(groupEl);
  });
}

// ── Переключение вкладок ──────────────────────────────────────────
function switchTab(tabId) {
  document.querySelectorAll('.nav-item').forEach(function (btn) {
    btn.classList.toggle('active', btn.dataset.tab === tabId);
  });
  document.querySelectorAll('.tab-panel').forEach(function (panel) {
    panel.classList.toggle('active', panel.id === 'panel-' + tabId);
  });

  // Загружаем данные при первом открытии вкладки
  if (tabId === 'dislocation' && !window._dislLoaded) { loadDislocation(); }
  if (tabId === 'approach'    && !window._approachLoaded) { loadApproach(); }
}

// ── Внутренние вкладки (pill) ─────────────────────────────────────
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
          if (innerId === 'disl-extended' && !window._extLoaded) {
            loadDislocationExtended();
          }
        }
      });
    });
  });
}

// ── Dashboard ─────────────────────────────────────────────────────
function loadDashboard() {
  $.getJSON('/api/dashboard')
    .done(function (data) {
      $('#headerDate').text(data.updated_at || '—');
      $('#dashboardSub').text('Обновлено: ' + (data.updated_at || '—') + ' · РЖД');
      renderKPI(data.sections);
      renderBarChart(data.sections);
      renderDonutChart(data.sections);
    })
    .fail(function () {
      $('#dashboardSub').text('Ошибка загрузки данных');
    });
}

// ── KPI карточки ──────────────────────────────────────────────────
function renderKPI(sections) {
  function get(id) {
    var s = sections.find(function (x) { return x.id === id; });
    return s ? s.total : 0;
  }
  var grandTotal = sections.reduce(function (s, x) { return s + x.total; }, 0);

  var kpis = [
    { label: 'Общий парк',            value: grandTotal,            accent: true },
    { label: 'В пути к потребителям', value: get('transit'),        sub: 'в движении' },
    { label: 'На подъездных путях',   value: get('siding'),         sub: 'груж. и пор.' },
    { label: 'Подход порожних',       value: get('empty_approach'), sub: 'на подходе' },
    { label: 'В ремонте',             value: get('repair'),         sub: 'деповской / план.' }
  ];

  $('#kpiGrid').html(kpis.map(function (k) {
    return '<div class="kpi-card' + (k.accent ? ' accent' : '') + '">' +
      '<div class="kpi-value">' + k.value.toLocaleString('ru-RU') + '</div>' +
      '<div class="kpi-label">' + esc(k.label) + '</div>' +
      (k.sub ? '<div class="kpi-delta">' + esc(k.sub) + '</div>' : '') +
      '</div>';
  }).join(''));
}

// ── SVG столбчатый график ─────────────────────────────────────────
var BAR_COLORS = ['#4A7FCB', '#5B9E6B', '#8B62C4', '#3B9EAF', '#6B7EC4'];

function renderBarChart(sections) {
  var values = sections.map(function (s) { return s.total; });
  var labels = sections.map(function (s) { return s.name; });
  var max = Math.max.apply(null, values) || 1;
  var barH = 28, gap = 9, lw = 190, vw = 56, bw = 290;
  var svgH = sections.length * (barH + gap) - gap;

  var svg = '<svg width="100%" height="' + svgH + '" viewBox="0 0 ' + (lw + bw + vw) + ' ' + svgH +
    '" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible">';

  values.forEach(function (v, i) {
    var y = i * (barH + gap);
    var barPx = Math.max(4, Math.round((v / max) * bw));
    var color = BAR_COLORS[i % BAR_COLORS.length];
    svg += '<text x="' + (lw - 8) + '" y="' + (y + barH / 2 + 4) + '" font-family="PT Sans,sans-serif" font-size="11" fill="#5C6370" text-anchor="end">' + esc(labels[i]) + '</text>';
    svg += '<rect x="' + lw + '" y="' + y + '" width="' + barPx + '" height="' + barH + '" rx="3" fill="' + color + '"/>';
    svg += '<text x="' + (lw + barPx + 6) + '" y="' + (y + barH / 2 + 4) + '" font-family="PT Sans,sans-serif" font-size="12" font-weight="700" fill="#1C2128">' + v.toLocaleString('ru-RU') + '</text>';
  });

  svg += '</svg>';
  $('#sectionsChart').html(svg);
}

// ── SVG донат ─────────────────────────────────────────────────────
function renderDonutChart(sections) {
  var grandTotal = sections.reduce(function (s, x) { return s + x.total; }, 0);
  var cis  = sections.reduce(function (s, x) { return s + (x.tank_total || 0); }, 0);
  var oth  = grandTotal - cis;
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

// ── Сводная дислокация ────────────────────────────────────────────
function loadDislocation() {
  window._dislLoaded = true;
  var date = $('#fDate').val();
  $('#mainTableSub').text('Загрузка...');

  $.getJSON('/api/dislocation/summary', { date: date })
    .done(function (data) {
      renderMainTable(data.sections, data.cols);
      $('#mainTableSub').text(data.date + ' · РЖД');
    })
    .fail(function () {
      $('#mainTable').html('<tbody><tr><td colspan="20" style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки данных</td></tr></tbody>');
    });
}

function renderMainTable(sections, cols) {
  // Группировка заголовков столбцов
  var groups = [];
  cols.forEach(function (c) {
    if (!groups.length || groups[groups.length - 1].name !== c.group) {
      groups.push({ name: c.group, count: 1 });
    } else {
      groups[groups.length - 1].count++;
    }
  });

  function fmt(v) { return v ? v : ''; }

  var h = '<thead><tr>';
  h += '<th class="col-meta" rowspan="2">Раздел</th>';
  h += '<th class="col-meta" rowspan="2">Подраздел</th>';
  h += '<th class="col-meta" rowspan="2">Парк</th>';
  groups.forEach(function (g) { h += '<th class="th-group" colspan="' + g.count + '">' + esc(g.name) + '</th>'; });
  h += '<th class="col-total-col" rowspan="2">Итого</th>';
  h += '</tr><tr>';
  cols.forEach(function (c) { h += '<th>' + esc(c.label) + '</th>'; });
  h += '</tr></thead><tbody>';

  sections.forEach(function (section) {
    var prevSub = null, firstRow = true;
    section.rows.forEach(function (row) {
      var newSub = row.sub !== prevSub;
      var rowSum = row.v.reduce(function (a, b) { return a + b; }, 0);
      h += '<tr class="row-data">';
      h += '<td class="col-meta' + (firstRow ? ' cell-section' : '') + '">' + (firstRow ? esc(section.name) : '') + '</td>';
      h += '<td class="col-meta">' + (newSub ? esc(row.sub || '') : '') + '</td>';
      h += '<td class="col-meta">' + esc(row.park || '') + '</td>';
      row.v.forEach(function (v) { h += '<td>' + fmt(v) + '</td>'; });
      h += '<td class="col-total-col">' + fmt(rowSum) + '</td></tr>';
      if (newSub) prevSub = row.sub;
      firstRow = false;
    });
    h += '<tr class="row-total"><td class="col-meta" colspan="3">' + esc(section.name) + ' — итого</td>';
    section.total.forEach(function (v) { h += '<td>' + fmt(v) + '</td>'; });
    h += '<td class="col-total-col">' + section.grand_total.toLocaleString('ru-RU') + '</td></tr>';
  });

  // Общий итог
  var grandTotals = cols.map(function (_, ci) {
    return sections.reduce(function (s, sec) { return s + (sec.total[ci] || 0); }, 0);
  });
  var grandSum = grandTotals.reduce(function (a, b) { return a + b; }, 0);
  h += '<tr class="row-total row-grand"><td class="col-meta" colspan="3">Общий итог</td>';
  grandTotals.forEach(function (v) { h += '<td>' + (v || '') + '</td>'; });
  h += '<td class="col-total-col">' + grandSum.toLocaleString('ru-RU') + '</td></tr></tbody>';

  $('#mainTable').html(h);
}

// ── Расширенная дислокация ────────────────────────────────────────
function loadDislocationExtended() {
  window._extLoaded = true;
  $.getJSON('/api/dislocation/extended')
    .done(function (data) { renderExtendedTable(data.rows); })
    .fail(function () {
      $('#dislExtTable').html('<tbody><tr><td colspan="11" style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки</td></tr></tbody>');
    });
}

function renderExtendedTable(rows) {
  var h = '<thead><tr>' +
    '<th class="col-meta">№ вагона</th><th class="col-meta">Поезд</th>' +
    '<th class="col-meta">Тек. станция</th><th class="col-meta">Ст. отправл.</th>' +
    '<th class="col-meta">Ст. назнач.</th><th class="col-meta">Груз</th>' +
    '<th>Ваг.</th><th class="col-meta">Статус</th>' +
    '<th>Дней в пути</th><th class="col-meta">Ожид. приб.</th>' +
    '<th class="col-meta">Парк</th></tr></thead><tbody>';

  rows.forEach(function (r) {
    var badge = r.status === 'loaded' ? 'badge-loaded' : 'badge-empty';
    h += '<tr class="row-data">' +
      '<td class="col-meta" style="font-family:monospace;font-size:11px">' + esc(r.wagon_no) + '</td>' +
      '<td class="col-meta">' + esc(r.train) + '</td>' +
      '<td class="col-meta">' + esc(r.current_station) + '</td>' +
      '<td class="col-meta">' + esc(r.from_station) + '</td>' +
      '<td class="col-meta">' + esc(r.to_station) + '</td>' +
      '<td class="col-meta">' + esc(r.cargo) + '</td>' +
      '<td>' + r.wagon_count + '</td>' +
      '<td class="col-meta"><span class="badge ' + badge + '">' + esc(r.status_label) + '</span></td>' +
      '<td>' + r.days_en_route + '</td>' +
      '<td class="col-meta">' + esc(r.expected_arrival) + '</td>' +
      '<td class="col-meta">' + esc(r.park) + '</td>' +
      '</tr>';
  });

  $('#dislExtTable').html(h + '</tbody>');
}

// ── Подход вагонов ────────────────────────────────────────────────
function loadApproach() {
  window._approachLoaded = true;
  $.getJSON('/api/approach')
    .done(function (data) { renderApproachTable(data.rows); })
    .fail(function () {
      $('#approachTable').html('<tbody><tr><td colspan="6" style="text-align:center;padding:40px;color:#9DA5B0">Ошибка загрузки</td></tr></tbody>');
    });
}

function renderApproachTable(rows) {
  var dirLabel = { arrive: 'прибытие', depart: 'отправление' };
  var h = '<thead><tr>' +
    '<th class="col-meta">Дорога</th><th class="col-meta">Направление</th>' +
    '<th>Вагонов</th><th class="col-meta">Тип вагона</th>' +
    '<th class="col-meta">Станция назн.</th><th class="col-meta">Ожид. время</th>' +
    '</tr></thead><tbody>';

  rows.forEach(function (r) {
    var dir   = r.direction || 'arrive';
    var badge = dir === 'arrive' ? 'badge-arrive' : 'badge-depart';
    h += '<tr class="row-data">' +
      '<td class="col-meta">' + esc(r.road) + '</td>' +
      '<td class="col-meta"><span class="badge ' + badge + '">' + (dirLabel[dir] || dir) + '</span></td>' +
      '<td>' + r.wagon_count + '</td>' +
      '<td class="col-meta">' + esc(r.wagon_type) + '</td>' +
      '<td class="col-meta">' + esc(r.destination_station) + '</td>' +
      '<td class="col-meta">' + esc(r.expected_time) + '</td>' +
      '</tr>';
  });

  $('#approachTable').html(h + '</tbody>');
}

// ── CSV-экспорт из отрисованной таблицы ──────────────────────────
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
  a.download = 'дислокация_' + ($('#fDate').val() || 'export') + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

// ── Утилита: экранирование HTML ───────────────────────────────────
function esc(str) {
  if (!str && str !== 0) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Старт ─────────────────────────────────────────────────────────
$(function () {
  initSidebar();
  initInnerTabs();
  loadDashboard();

  $('#btnApply').on('click', function () {
    window._dislLoaded = false;
    loadDislocation();
  });

  $('#btnReset').on('click', function () {
    $('#fDate').val(new Date().toISOString().slice(0, 10));
    window._dislLoaded = false;
    loadDislocation();
  });

  $('#btnExportCSV').on('click', exportToCSV);
});
