'use strict'

var BASE = window.APP_BASE || ''

// наивигация (Боковое меню)
var TAB_GROUPS = [
  {
    label: '',
    tabs: [
      //{ id: 'main', label: 'Главная' },
      { id: 'dashboard', label: 'Дашборд' },
    ],
  },
  {
    label: 'Движение вагонов',
    tabs: [
      { id: 'dislocation', label: 'Дислокация' },
      { id: 'approach', label: 'Подход вагонов' },
      { id: 'departure', label: 'Отправление вагонов' },
      { id: 'loading', label: 'Погрузка' },
      { id: 'raw-material', label: 'Сырьё' },
    ],
  },
  {
    label: 'Аналитика',
    tabs: [{ id: 'analysis', label: 'Анализ за период' }],
  },
  {
    label: 'Простои и оборот',
    tabs: [{ id: 'downtime', label: 'Простои' }],
  },
  {
    label: 'Импорт',
    tabs: [
      { id: 'import', label: ' Загрузка справок ', url: BASE + '/import' },
    ],
  },
]

// Сайдбар
function initSidebar() {
  var sidebar = document.getElementById('sidebar')
  TAB_GROUPS.forEach(function (group) {
    var groupEl = document.createElement('div')
    groupEl.className = 'nav-group' + (!group.label ? ' nav-group--top' : '')

    var labelEl = document.createElement('span')
    labelEl.className = 'nav-group-label'
    labelEl.textContent = group.label
    groupEl.appendChild(labelEl)

    group.tabs.forEach(function (tab) {
      var btn = document.createElement('button')
      btn.className = 'nav-item' + (tab.id === 'dashboard' ? ' active' : '')
      btn.textContent = tab.label
      btn.dataset.tab = tab.id
      if (tab.url) {
        btn.addEventListener('click', function () {
          window.location.href = tab.url
        })
      } else {
        btn.addEventListener('click', function () {
          switchTab(tab.id)
        })
      }
      groupEl.appendChild(btn)
    })

    sidebar.appendChild(groupEl)
  })
}

// Переключение вкладок
function switchTab(tabId) {
  document.querySelectorAll('.nav-item').forEach(function (btn) {
    btn.classList.toggle('active', btn.dataset.tab === tabId)
  })
  document.querySelectorAll('.tab-panel').forEach(function (panel) {
    panel.classList.toggle('active', panel.id === 'panel-' + tabId)
  })
  Object.keys(WAGON_TABS).forEach(function (k) {
    var cfg = WAGON_TABS[k]
    if (tabId === k && !window[cfg.loadedKey]) {
      initTab(cfg)
    }
  })
}

// Внутренние вкладки (pill)
function initInnerTabs() {
  document.querySelectorAll('.inner-tabs').forEach(function (tabsEl) {
    tabsEl.querySelectorAll('.inner-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var innerId = btn.dataset.inner
        tabsEl.querySelectorAll('.inner-tab').forEach(function (b) {
          b.classList.toggle('active', b.dataset.inner === innerId)
        })
        var panel = document.getElementById(innerId)
        if (panel) {
          panel.parentElement
            .querySelectorAll('.inner-panel')
            .forEach(function (p) {
              p.classList.toggle('active', p.id === innerId)
            })
          Object.keys(WAGON_TABS).forEach(function (k) {
            var cfg = WAGON_TABS[k]
            if (innerId === cfg.detPanelId && !window[cfg.loadedDetKey]) {
              window[cfg.loadedDetKey] = true
              loadDetail(cfg)
            }
          })
        }
      })
    })
  })
}

// Dashboard
function loadDashboard() {
  $.getJSON(BASE + '/api/dashboard')
    .done(function (data) {
      var label = data.updated_at || '—'
      $('#brandDateSub').text('Дислокация РЖД ' + label)
      $('#headerDate').text(label)
      $('#dashboardSub').text('Справка: ' + label + ' · РЖД')
      showDashKpi(data.sections)
      drawBar(data.sections)
      drawDonut(data.sections)
    })
    .fail(function (jqXHR) {
      $('#dashboardSub').text(ajaxErr(jqXHR))
    })
}

// KPI карточки
function showDashKpi(sections) {
  var grandTotal = sections.reduce(function (s, x) {
    return s + x.total
  }, 0)
  var tankTotal = sections.reduce(function (s, x) {
    return s + (x.tank_total || 0)
  }, 0)

  var kpis = [
    { label: 'Общий парк', value: grandTotal, accent: true },
    { label: 'Цистерны', value: tankTotal },
    { label: 'Прочие вагоны', value: grandTotal - tankTotal },
    { label: 'Типов парка', value: sections.length, sub: 'разновидностей' },
  ]

  $('#kpiGrid').html(kpis.map(kpiCard).join(''))
}

// SVG
var BAR_COLORS = [
  '#4A7FCB',
  '#5B9E6B',
  '#8B62C4',
  '#3B9EAF',
  '#6B7EC4',
  '#D4622A',
  '#E8A530',
]

function drawBar(sections) {
  if (!sections.length) {
    $('#sectionsChart').html(
      '<p style="color:#9DA5B0;padding:20px">Нет данных</p>',
    )
    return
  }
  var values = sections.map(function (s) {
    return s.total
  })
  var labels = sections.map(function (s) {
    return s.name
  })
  var max = Math.max.apply(null, values) || 1
  var barH = 28,
    gap = 9,
    lw = 190,
    vw = 56,
    bw = 290
  var svgH = sections.length * (barH + gap) - gap

  var svg =
    '<svg width="100%" height="' +
    svgH +
    '" viewBox="0 0 ' +
    (lw + bw + vw) +
    ' ' +
    svgH +
    '" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible">'

  values.forEach(function (v, i) {
    var y = i * (barH + gap)
    var barPx = Math.max(4, Math.round((v / max) * bw))
    var color = BAR_COLORS[i % BAR_COLORS.length]
    svg +=
      '<text x="' +
      (lw - 8) +
      '" y="' +
      (y + barH / 2 + 4) +
      '" font-family="PT Sans,sans-serif" font-size="11" fill="#5C6370" text-anchor="end">' +
      esc(labels[i]) +
      '</text>'
    svg +=
      '<rect x="' +
      lw +
      '" y="' +
      y +
      '" width="' +
      barPx +
      '" height="' +
      barH +
      '" rx="3" fill="' +
      color +
      '"/>'
    svg +=
      '<text x="' +
      (lw + barPx + 6) +
      '" y="' +
      (y + barH / 2 + 4) +
      '" font-family="PT Sans,sans-serif" font-size="12" font-weight="700" fill="#1C2128">' +
      v.toLocaleString('ru-RU') +
      '</text>'
  })

  svg += '</svg>'
  $('#sectionsChart').html(svg)
}

// SVG донат с вагончиками
function drawDonut(sections) {
  var grandTotal = sections.reduce(function (s, x) {
    return s + x.total
  }, 0)
  var cis = sections.reduce(function (s, x) {
    return s + (x.tank_total || 0)
  }, 0)
  var oth = grandTotal - cis
  var r = 60,
    sw = 22
  var circ = 2 * Math.PI * r
  var dash = grandTotal > 0 ? (cis / grandTotal) * circ : 0
  var pctC = grandTotal > 0 ? Math.round((cis / grandTotal) * 100) : 0

  $('#typesChart').html(
    '<svg width="100%" height="170" viewBox="0 0 340 170" xmlns="http://www.w3.org/2000/svg">' +
      '<circle cx="85" cy="85" r="' +
      r +
      '" fill="none" stroke="#B8C8E8" stroke-width="' +
      sw +
      '"/>' +
      '<circle cx="85" cy="85" r="' +
      r +
      '" fill="none" stroke="#4A7FCB" stroke-width="' +
      sw +
      '"' +
      ' stroke-dasharray="' +
      dash.toFixed(2) +
      ' ' +
      circ.toFixed(2) +
      '" transform="rotate(-90 85 85)"/>' +
      '<text x="85" y="79" text-anchor="middle" font-family="PT Sans,sans-serif" font-size="21" font-weight="700" fill="#1C2128">' +
      grandTotal.toLocaleString('ru-RU') +
      '</text>' +
      '<text x="85" y="97" text-anchor="middle" font-family="PT Sans,sans-serif" font-size="11" fill="#9DA5B0">вагонов</text>' +
      '<rect x="180" y="54" width="12" height="12" rx="2" fill="#4A7FCB"/>' +
      '<text x="198" y="65" font-family="PT Sans,sans-serif" font-size="13" font-weight="700" fill="#1C2128">Цистерны</text>' +
      '<text x="198" y="82" font-family="PT Sans,sans-serif" font-size="12" fill="#5C6370">' +
      cis.toLocaleString('ru-RU') +
      ' ваг. · ' +
      pctC +
      '%</text>' +
      '<rect x="180" y="100" width="12" height="12" rx="2" fill="#B8C8E8"/>' +
      '<text x="198" y="111" font-family="PT Sans,sans-serif" font-size="13" font-weight="700" fill="#1C2128">Прочие</text>' +
      '<text x="198" y="128" font-family="PT Sans,sans-serif" font-size="12" fill="#5C6370">' +
      oth.toLocaleString('ru-RU') +
      ' ваг. · ' +
      (100 - pctC) +
      '%</text>' +
      '</svg>',
  )
}

// Сводная дислокация

function drawMain(sections, cols) {
  function fmt(v) {
    return v || ''
  }

  var h = '<thead><tr>'
  h += '<th class="col-meta" style="min-width:160px">Раздел</th>'
  h += '<th class="col-meta" style="min-width:130px">Тип парка</th>'
  cols.forEach(function (c) {
    h += '<th>' + esc(c.label) + '</th>'
  })
  h += '<th class="col-total-col">Итого</th>'
  h += '</tr></thead><tbody>'

  sections.forEach(function (section, si) {
    var sectionExtra = esc(JSON.stringify({ section: section.name }))
    var hasChildren = section.rows.length > 1
    h += '<tr class="row-road-parent" data-road-id="' + si + '">'
    h +=
      '<td class="col-meta" colspan="2">' +
      (hasChildren ? '<span class="toggle-icon">▼</span>' : '') +
      esc(section.name) +
      '</td>'
    section.total.forEach(function (v, ci) {
      if (v) {
        h +=
          '<td class="cell-link" data-ctx="dislocation" data-col="' +
          esc(cols[ci].label) +
          '" data-extra="' +
          sectionExtra +
          '">' +
          fmt(v) +
          '</td>'
      } else {
        h += '<td></td>'
      }
    })
    h +=
      '<td class="col-total-col cell-link" data-ctx="dislocation" data-extra="' +
      sectionExtra +
      '">' +
      section.grand_total.toLocaleString('ru-RU') +
      '</td></tr>'

    if (hasChildren) {
      section.rows.forEach(function (row) {
        var rowSum = row.v.reduce(function (a, b) {
          return a + b
        }, 0)
        h += '<tr class="row-data row-child" data-parent-road="' + si + '">'
        h += '<td class="col-meta"></td>'
        h += '<td class="col-meta">' + esc(row.sub || '') + '</td>'
        var extraAttr = esc(JSON.stringify({ park_type: row.sub }))
        row.v.forEach(function (v, ci) {
          if (v) {
            h +=
              '<td class="cell-link" data-ctx="dislocation" data-col="' +
              esc(cols[ci].label) +
              '" data-extra="' +
              extraAttr +
              '">' +
              fmt(v) +
              '</td>'
          } else {
            h += '<td></td>'
          }
        })
        h +=
          '<td class="col-total-col cell-link" data-ctx="dislocation" data-extra="' +
          extraAttr +
          '">' +
          fmt(rowSum) +
          '</td></tr>'
      })
    }
  })

  var grandTotals = cols.map(function (_, ci) {
    return sections.reduce(function (s, sec) {
      return s + (sec.total[ci] || 0)
    }, 0)
  })
  var grandSum = grandTotals.reduce(function (a, b) {
    return a + b
  }, 0)
  h +=
    '<tr class="row-total row-grand"><td class="col-meta" colspan="2">Общий итог</td>'
  grandTotals.forEach(function (v, ci) {
    if (v) {
      h +=
        '<td class="cell-link" data-ctx="dislocation" data-col="' +
        esc(cols[ci].label) +
        '">' +
        v +
        '</td>'
    } else {
      h += '<td></td>'
    }
  })
  h +=
    '<td class="col-total-col cell-link" data-ctx="dislocation">' +
    grandSum.toLocaleString('ru-RU') +
    '</td></tr></tbody>'

  $('#mainTable').html(h)
}

// Конфиг для общео построения сводных вкладок и детализаций
// Подход / Отправление / Погрузка — описание всех полей

/******** Вагон конфиг начало ********/
var WAGON_TABS = {
  // Дислокация
  dislocation: {
    ctx: 'dislocation',
    summaryUrl: BASE + '/api/dislocation/summary',
    detailUrl: BASE + '/api/dislocation/detail',
    csvFilename: 'дислокация',
    sumTableId: 'mainTable',
    sumSubId: 'mainTableSub',
    detTableId: 'dislExtTable',
    detPanelId: 'disl-extended',
    loadedKey: '_dislLoaded',
    loadedDetKey: '_extLoaded',
    sumSubLabel: '',
    groupCols: [
      { key: 'dest_state', label: 'Страна назначения' },
      { key: 'dest_road', label: 'Дорога назначения' },
    ],
    getParams: function () {
      return {}
    },
  },

  // Подход
  approach: {
    ctx: 'approach',
    filtersUrl: BASE + '/api/approach/filters',
    summaryUrl: BASE + '/api/approach/summary',
    detailUrl: BASE + '/api/approach/detail',
    metricsId: 'approachMetrics',
    metricsLabel: 'Всего в подходе',
    csvFilename: 'подход',
    sumTableId: 'approachSumTable',
    sumSubId: 'approachSumSub',
    detTableId: 'approachDetTable',
    detSubId: 'approachDetSub',
    detPanelId: 'approach-detail',
    loadedKey: '_approachLoaded',
    loadedDetKey: '_approachDetLoaded',
    sumSubLabel: 'Всего в подходе',
    groupCols: [
      { key: 'oper_road', label: 'Дорога операции' },
      { key: 'oper_station', label: 'Станция операции' },
    ],
    getParams: function () {
      return {
        cargo: $('#fApproachCargo').val() || undefined,
        prev_cargo: $('#fApproachPrevCargo').val() || undefined,
      }
    },
    fillFilters: function (data) {
      fillSelect('#fApproachCargo', data.cargo || [])
      fillSelect('#fApproachPrevCargo', data.prev_cargo || [])
    },
    resetFilters: function () {
      $('#fApproachCargo').val('')
      $('#fApproachPrevCargo').val('')
    },
  },

  // Отправление
  departure: {
    ctx: 'departure',
    filtersUrl: BASE + '/api/departure/filters',
    summaryUrl: BASE + '/api/departure/summary',
    detailUrl: BASE + '/api/departure/detail',
    metricsId: 'departureMetrics',
    metricsLabel: 'Всего отправлено',
    csvFilename: 'отправление',
    sumTableId: 'departureSumTable',
    sumSubId: 'departureSumSub',
    detTableId: 'departureDetTable',
    detSubId: 'departureDetSub',
    detPanelId: 'departure-detail',
    loadedKey: '_departureLoaded',
    loadedDetKey: '_departureDetLoaded',
    sumSubLabel: 'Всего',
    groupCols: [
      { key: 'dest_road', label: 'Дорога назначения' },
      { key: 'dest_station', label: 'Станция назначения' },
    ],
    getParams: function () {
      return {
        cargo: $('#fDepartureCargo').val() || undefined,
        dest_station: $('#fDestStation').val() || undefined,
      }
    },
    fillFilters: function (data) {
      fillSelect('#fDepartureCargo', data.cargo || [])
      fillSelect('#fDestStation', data.dest_station || [])
    },
    resetFilters: function () {
      $('#fDepartureCargo').val('')
      $('#fDestStation').val('')
    },
  },

  // Погрузка
  loading: {
    ctx: 'loading',
    filtersUrl: BASE + '/api/approach/filters',
    summaryUrl: BASE + '/api/loading/summary',
    detailUrl: BASE + '/api/loading/detail',
    metricsId: 'loadingMetrics',
    metricsLabel: 'Всего погружено',
    csvFilename: 'погрузка',
    sumTableId: 'loadingSumTable',
    sumSubId: 'loadingSumSub',
    detTableId: 'loadingDetTable',
    detSubId: 'loadingDetSub',
    detPanelId: 'loading-detail',
    loadedKey: '_loadingLoaded',
    loadedDetKey: '_loadingDetLoaded',
    sumSubLabel: 'Всего',
    groupCols: [
      { key: 'depart_road', label: 'Дорога' },
      { key: 'depart_station', label: 'Станция' },
    ],
    getParams: function () {
      return { cargo: $('#fLoadingCargo').val() || undefined }
    },
    fillFilters: function (data) {
      fillSelect('#fLoadingCargo', data.cargo || [])
    },
    resetFilters: function () {
      $('#fLoadingCargo').val('')
    },
  },
  // Простои
  downtime: {
    ctx: 'downtime',
    summaryUrl: BASE + '/api/downtime/summary',
    detailUrl: BASE + '/api/downtime/detail',
    sumTableId: 'downtimeSumTable',
    sumSubId: 'downtimeSumSub',
    detTableId: 'downtimeDetTable',
    detSubId: 'downtimeDetSub',
    detPanelId: 'downtime-detail',
    loadedKey: '_downtimeLoaded',
    loadedDetKey: '_downtimeDetLoaded',
    sumSubLabel: 'Вагонов с простоем',
    groupCols: [
      { key: 'oper_road', label: 'Дорога' },
      { key: 'oper_station', label: 'Станция' },
    ],
    applyBtnId: 'btnDowntimeApply',
    getParams: function () {
      var min = $('#fDowntimeMinDays').val()
      var max = $('#fDowntimeMaxDays').val()
      return {
        min_days: min !== '' ? min : 1,
        max_days: max !== '' ? max : undefined,
      }
    },
    resetFilters: function () {
      $('#fDowntimeMinDays').val('1')
      $('#fDowntimeMaxDays').val('')
    },
  },
  // Сырьё
  'raw-material': {
    ctx: 'raw-material',
    summaryUrl: BASE + '/api/raw-material/summary',
    detailUrl: BASE + '/api/raw-material/detail',
    metricsId: 'rawMetrics',
    kpi: function (data) {
      return [{ label: 'Гружёных вагонов', value: data.total, accent: true }]
    },
    sumTableId: 'rawSumTable',
    sumSubId: 'rawSumSub',
    detTableId: 'rawDetTable',
    detSubId: 'rawDetSub',
    detPanelId: 'raw-detail',
    loadedKey: '_rawLoaded',
    loadedDetKey: '_rawDetLoaded',
    sumSubLabel: 'Гружёных вагонов',
    groupCols: [
      { key: 'cargo_name', label: 'Груз' },
      //{ key: 'consignee', label: 'Грузополучатель' },
    ],
    getParams: function () {
      return {}
    },
  },
}

/******** Вагон конфиг конец ********/

function fillSelect(selector, values) {
  var $sel = $(selector)
  $sel.find('option:not(:first)').remove()
  values.forEach(function (v) {
    $sel.append($('<option>').val(v).text(v))
  })
}

function initTab(cfg) {
  window[cfg.loadedKey] = true
  if (cfg.csvFilename) {
    var $acts = $('#' + cfg.sumTableId)
      .closest('.table-section')
      .find('.table-acts')
    if ($acts.length && !$acts.find('.btn-csv-tab').length) {
      var $btn = $(
        '<button class="btn btn-ghost btn-sm btn-csv-tab">Скачать CSV</button>',
      )
      $btn.on('click', function () {
        saveCSV(cfg.sumTableId, cfg.csvFilename)
      })
      $acts.append($btn)
    }
  }
  loadFilters(cfg)
  loadSummary(cfg)
}

function loadFilters(cfg) {
  if (!cfg.filtersUrl) return
  $.getJSON(cfg.filtersUrl).done(function (data) {
    if (cfg.fillFilters) cfg.fillFilters(data)
  })
}

function loadSummary(cfg) {
  var $sub = $('#' + cfg.sumSubId)
  var $table = $('#' + cfg.sumTableId)
  $sub.text('Загрузка...')
  $table.html(
    '<tbody><tr><td colspan="5" style="text-align:center;padding:40px;color:#9DA5B0">Загрузка...</td></tr></tbody>',
  )
  var summaryParams = Object.assign({}, cfg.getParams())
  var gby = (cfg.groupCols || [])
    .map(function (g) {
      return g.key
    })
    .join(',')
  if (gby) summaryParams.group_by = gby
  $.getJSON(cfg.summaryUrl, summaryParams)
    .done(function (data) {
      if (cfg.metricsId) {
        var items = cfg.kpi
          ? cfg.kpi(data)
          : [
              { label: cfg.metricsLabel, value: data.total, accent: true },
            ].concat(data.metrics || [])
        $('#' + cfg.metricsId).html(items.map(kpiCard).join(''))
      }
      if (cfg.draw) {
        cfg.draw(data, cfg)
        return
      }
      drawSummary(
        '#' + cfg.sumTableId,
        data.roads,
        data,
        cfg.ctx,
        cfg.groupCols,
      )
      $sub.text(
        cfg.sumSubLabel +
          ': ' +
          (data.total || 0).toLocaleString('ru-RU') +
          ' ваг.',
      )
    })
    .fail(function (jqXHR) {
      var msg = ajaxErr(jqXHR)
      $table.html(
        '<tbody><tr><td colspan="5" style="text-align:center;padding:40px;color:#9DA5B0">' +
          esc(msg) +
          '</td></tr></tbody>',
      )
      $sub.text(msg)
    })
}

function loadDetail(cfg) {
  var $sub = $('#' + cfg.detSubId)
  var $table = $('#' + cfg.detTableId)
  var cols = DETAIL_CONTEXTS[cfg.ctx] ? DETAIL_CONTEXTS[cfg.ctx].cols : []
  $sub.text('Загрузка...')
  var listParams = cfg.listParams
    ? cfg.listParams()
    : Object.assign({}, cfg.getParams(), {
        fields: cols
          .map(function (c) {
            return c.key
          })
          .join(','),
        group_by: (cfg.groupCols || [])
          .map(function (g) {
            return g.key
          })
          .join(','),
      })
  $.getJSON(cfg.detailUrl, listParams)
    .done(function (data) {
      if (cfg.showList) {
        cfg.showList(data, cfg)
      } else {
        showTable($table, data.rows, cols)
      }
      $sub.text('Строк: ' + (data.rows || []).length.toLocaleString('ru-RU'))
    })
    .fail(function (jqXHR) {
      $table.html(
        '<tbody><tr><td colspan="' +
          cols.length +
          '" style="text-align:center;padding:40px;color:#9DA5B0">' +
          esc(ajaxErr(jqXHR)) +
          '</td></tr></tbody>',
      )
    })
}

function showTable($table, rows, colDefs) {
  var h = '<thead><tr>'
  colDefs.forEach(function (c) {
    h +=
      '<th' + (c.meta ? ' class="col-meta"' : '') + '>' + esc(c.label) + '</th>'
  })
  h += '</tr></thead><tbody>'
  ;(rows || []).forEach(function (r) {
    h += '<tr class="row-data">'
    colDefs.forEach(function (c) {
      var val = c.fmt ? c.fmt(r[c.key]) : esc(r[c.key] != null ? r[c.key] : '')
      var attrs = ''
      if (c.meta) {
        attrs = ' class="col-meta"'
        if (c.mono) attrs += ' style="font-family:monospace;font-size:11px"'
      } else if (c.danger) {
        attrs = idleStyle(parseFloat(r[c.key]) || 0)
      } else if (c.right) {
        attrs = ' style="text-align:right"'
      }
      h += '<td' + attrs + '>' + (val || '') + '</td>'
    })
    h += '</tr>'
  })
  $table.html(h + '</tbody>')
  addSearch($table)
}

/******** cols config ********/

/******** summary / kpi renders ********/

function drawSummary(selector, roads, data, ctx, groupCols) {
  if (!roads || !roads.length) {
    $(selector).html(
      '<tbody><tr><td colspan="5" style="text-align:center;padding:40px;color:#9DA5B0">Нет данных по данным параметрам.</td></tr></tbody>',
    )
    return
  }
  var colGroups = data.col_groups || null
  var flatCols = data.cols || []

  // flatCells: [{col, subs:[]}] — один элемент на каждую фактическую колонку значений;
  // col — тип вагона (1-й уровень), subs — значения вложенных уровней по порядку
  var flatCells = []
  var levels = []
  if (colGroups) {
    ;(function walk(nodes, d, path) {
      if (!levels[d]) levels[d] = []
      var count = 0
      nodes.forEach(function (n) {
        if (typeof n === 'string') {
          levels[d].push({ label: n, span: 1 })
          var full = path.concat([n])
          flatCells.push({ col: full[0], subs: full.slice(1) })
          count += 1
        } else {
          var pos = levels[d].length
          levels[d].push(null) // span известен только после обхода детей
          var c = walk(n.subs, d + 1, path.concat([n.label]))
          levels[d][pos] = { label: n.label, span: c }
          count += c
        }
      })
      return count
    })(colGroups, 0, [])
  } else {
    levels[0] = flatCols.map(function (c) {
      return { label: c, span: 1 }
    })
    flatCols.forEach(function (c) {
      flatCells.push({ col: c, subs: [] })
    })
  }
  var depth = levels.length

  var nGroup = groupCols.length
  var groupBy = groupCols
    .map(function (g) {
      return g.key
    })
    .join(',')

  function fmt(v) {
    return v || ''
  }

  // data-sub, data-sub2, data-sub3..
  function subAttrs(subs) {
    var s = ''
    ;(subs || []).forEach(function (v, i) {
      if (v) s += ' data-sub' + (i ? i + 1 : '') + '="' + esc(v) + '"'
    })
    return s
  }

  function cellLink(v, dataCtx, dataRoad, dataSt, cell) {
    if (!v || !dataCtx) return '<td>' + fmt(v) + '</td>'
    return (
      '<td class="cell-link" data-ctx="' +
      esc(dataCtx) +
      '" data-road="' +
      esc(dataRoad) +
      '" data-station="' +
      esc(dataSt) +
      '" data-col="' +
      esc(cell.col) +
      '" data-group-by="' +
      esc(groupBy) +
      '"' +
      subAttrs(cell.subs) +
      '>' +
      v +
      '</td>'
    )
  }
  function totalLink(v, dataCtx, dataRoad, dataSt) {
    var cls = 'col-total-col'
    if (!v || !dataCtx) return '<td class="' + cls + '">' + fmt(v) + '</td>'
    return (
      '<td class="' +
      cls +
      ' cell-link" data-ctx="' +
      esc(dataCtx) +
      '" data-road="' +
      esc(dataRoad) +
      '" data-station="' +
      esc(dataSt) +
      '" data-col="" data-group-by="' +
      esc(groupBy) +
      '">' +
      (typeof v === 'number' ? v.toLocaleString('ru-RU') : v) +
      '</td>'
    )
  }

  // thead: depth строк; groupCols и «Итого» растянуты на всю высоту через rowspan
  var rowspan = depth > 1 ? ' rowspan="' + depth + '"' : ''
  var h = '<thead><tr>'
  groupCols.forEach(function (gc, i) {
    var w = i === 0 ? ' style="min-width:160px"' : ' style="min-width:180px"'
    h += '<th class="col-meta"' + rowspan + w + '>' + esc(gc.label) + '</th>'
  })
  levels[0].forEach(function (c) {
    h +=
      '<th' +
      (c.span > 1 ? ' colspan="' + c.span + '"' : '') +
      (depth > 1 ? ' style="text-align:center"' : '') +
      '>' +
      esc(c.label) +
      '</th>'
  })
  h += '<th class="col-total-col"' + rowspan + '>Итого</th></tr>'
  for (var d = 1; d < depth; d++) {
    h += '<tr>'
    levels[d].forEach(function (c) {
      h +=
        '<th' +
        (c.span > 1 ? ' colspan="' + c.span + '"' : '') +
        ' style="text-align:center">' +
        esc(c.label) +
        '</th>'
    })
    h += '</tr>'
  }
  h += '</thead><tbody>'

  var grandTotals = flatCells.map(function () {
    return 0
  })
  var grandSum = 0
  ;(roads || []).forEach(function (road, ri) {
    var roadVal = road[groupCols[0].key] || ''
    var stations = road.stations || []
    var hasChildren = nGroup > 1 && stations.length > 0
    h += '<tr class="row-road-parent" data-road-id="' + ri + '">'
    h +=
      '<td class="col-meta" colspan="' +
      nGroup +
      '">' +
      (hasChildren ? '<span class="toggle-icon">▼</span>' : '') +
      esc(roadVal) +
      '</td>'
    ;(road.total || []).forEach(function (v, i) {
      grandTotals[i] += v || 0
      h += cellLink(v, ctx, roadVal, '', flatCells[i])
    })
    h += totalLink(road.grand_total || 0, ctx, roadVal, '')
    h += '</tr>'
    grandSum += road.grand_total || 0
    if (hasChildren) {
      stations.forEach(function (st) {
        var stVal = st[groupCols[nGroup - 1].key] || ''
        var rowSum = (st.v || []).reduce(function (a, b) {
          return a + b
        }, 0)
        h += '<tr class="row-data row-child" data-parent-road="' + ri + '">'
        for (var j = 0; j < nGroup - 1; j++) {
          h += '<td class="col-meta"></td>'
        }
        h += '<td class="col-meta">' + esc(stVal) + '</td>'
        ;(st.v || []).forEach(function (v, i) {
          h += cellLink(v, ctx, roadVal, stVal, flatCells[i])
        })
        h += totalLink(rowSum, ctx, roadVal, stVal)
        h += '</tr>'
      })
    }
  })
  h +=
    '<tr class="row-total row-grand"><td class="col-meta" colspan="' +
    nGroup +
    '">Общий итог</td>'

  grandTotals.forEach(function (v, i) {
    if (v && ctx) {
      h +=
        '<td class="cell-link" data-ctx="' +
        esc(ctx) +
        '" data-road="" data-station="" data-col="' +
        esc(flatCells[i].col) +
        '"' +
        subAttrs(flatCells[i].subs) +
        '>' +
        v +
        '</td>'
    } else {
      h += '<td>' + (v || '') + '</td>'
    }
  })
  if (grandSum && ctx) {
    h +=
      '<td class="col-total-col cell-link" data-ctx="' +
      esc(ctx) +
      '" data-road="" data-station="" data-col="">' +
      grandSum.toLocaleString('ru-RU') +
      '</td>'
  } else {
    h +=
      '<td class="col-total-col">' + grandSum.toLocaleString('ru-RU') + '</td>'
  }
  h += '</tr></tbody>'
  $(selector).html(h)
}

// CSV-экспорт таблицы по id и имени файла (без даты-суффикса в имени не нужна)
function saveCSV(tableId, filename) {
  var table = document.getElementById(tableId)
  if (!table) return
  var rows = []
  table.querySelectorAll('tr').forEach(function (tr) {
    var cells = []
    tr.querySelectorAll('th, td').forEach(function (cell) {
      cells.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"')
    })
    rows.push(cells.join(','))
  })
  var csv = '\uFEFF' + rows.join('\n') // BOM для корректного открытия в Excel
  var a = document.createElement('a')
  a.href = URL.createObjectURL(
    new Blob([csv], { type: 'text/csv;charset=utf-8' }),
  )
  a.download =
    (filename || 'таблица') +
    '_' +
    new Date().toISOString().slice(0, 10) +
    '.csv'
  a.click()
  URL.revokeObjectURL(a.href)
}
// Простоая выгрузка в Excel активной таблицы (указаывается ID таблицы в настроках при построении сводной таблицы)
// Текст ошибки из jQuery
function ajaxErr(jqXHR) {
  var status = jqXHR.status ? ' (' + jqXHR.status + ')' : ''
  var detail = ''
  try {
    var json = JSON.parse(jqXHR.responseText)
    detail = json.error || json.message || ''
  } catch (e) {
    detail = jqXHR.responseText || ''
  }
  return 'Ошибка загрузки данных: ' + status + (detail ? ': ' + detail : '')
}

// Экранирование HTML
function esc(str) {
  if (!str && str !== 0) return ''
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

// Цветовой стиль простоя:
function idleStyle(days) {
  // красный ≥7 сут.,
  if (days >= 7) return ' style="color:#E8392A;font-weight:700"'

  //оранжевый ≥3 сут.
  if (days >= 3) return ' style="color:#E8A530;font-weight:600"'
  return ''
}

// HTML одной KPI-карточки. Поля: label, value (или total), accent, sub.
function kpiCard(item) {
  var val = item.value != null ? item.value : item.total || 0
  return (
    '<div class="kpi-card' +
    (item.accent ? ' accent' : '') +
    '">' +
    '<div class="kpi-value">' +
    (typeof val === 'number' ? val.toLocaleString('ru-RU') : esc(String(val))) +
    '</div>' +
    '<div class="kpi-label">' +
    esc(item.label) +
    '</div>' +
    (item.sub ? '<div class="kpi-delta">' + esc(item.sub) + '</div>' : '') +
    '</div>'
  )
}

// Свернуть / Отобразить все строки в таблице
function collapseAll($table) {
  $table.find('.row-road-parent').each(function () {
    var ri = $(this).data('road-id')
    $table.find('tr[data-parent-road="' + ri + '"]').addClass('row-hidden')
    $(this).find('.toggle-icon').text('▶')
  })
}
function expandAll($table) {
  $table.find('.row-road-parent').each(function () {
    var ri = $(this).data('road-id')
    $table.find('tr[data-parent-road="' + ri + '"]').removeClass('row-hidden')
    $(this).find('.toggle-icon').text('▼')
  })
}

$(document).on('click', '[data-collapse-table]', function () {
  collapseAll($('#' + $(this).data('collapse-table')))
})
$(document).on('click', '[data-expand-table]', function () {
  expandAll($('#' + $(this).data('expand-table')))
})

// Поиск по столбцам:  строку-фильтр под заголовком таблицы
function addSearch($table) {
  var cells = ''
  $table.find('thead tr:first th').each(function () {
    cells +=
      '<td><input class="col-search-input" type="text" placeholder=""></td>'
  })
  $table.find('tbody').prepend('<tr class="search-row">' + cells + '</tr>')
}

$(document).on('input', '.col-search-input', function () {
  var $row = $(this).closest('tr.search-row')
  var $table = $row.closest('table')
  var filters = $row
    .find('.col-search-input')
    .map(function () {
      return $(this).val().toLowerCase().trim()
    })
    .get()
  $table.find('tbody tr:not(.search-row)').each(function () {
    var $cells = $(this).find('td')
    var show = filters.every(function (q, ci) {
      return !q || $cells.eq(ci).text().toLowerCase().indexOf(q) !== -1
    })
    $(this).toggle(show)
  })
})

// Имена URL-параметров для уровней подзаголовка колонок (после типа вагона).
// Новый уровень шапки → добавить имя параметра сюда + фильтр в *Detail на бэке
// + проброс параметра в detail.php.
var SUB_PARAM_NAMES = ['cargo_state']

// Drill-down: открыть страницу детализации в новой вкладке.
// extra — активные фильтры вкладки (cfg.getParams()), уходят в URL как есть
function openDetail(ctx, road, station, col, groupBy, subs, extra) {
  var p = new URLSearchParams()
  p.set('ctx', ctx)
  if (road) p.set('road', road)
  if (station) p.set('station', station)
  if (col) p.set('col', col)
  if (groupBy) p.set('group_by', groupBy)
  ;(subs || []).forEach(function (s, i) {
    if (s && SUB_PARAM_NAMES[i]) p.set(SUB_PARAM_NAMES[i], s)
  })
  Object.keys(extra || {}).forEach(function (k) {
    if (extra[k] !== undefined && extra[k] !== null && extra[k] !== '') {
      p.set(k, extra[k])
    }
  })
  window.open(BASE + '/detail?' + p.toString(), '_blank')
}

$(document).on('click', '.cell-link', function (e) {
  e.stopPropagation()
  var ctx = $(this).data('ctx') || ''
  var road = $(this).data('road') || ''
  var station = $(this).data('station') || ''
  var col = $(this).data('col') || ''
  var groupBy = $(this).data('group-by') || ''
  // data-sub, data-sub2, data-sub3... → массив значений уровней
  var subs = []
  for (var i = 1; ; i++) {
    var v = $(this).data(i === 1 ? 'sub' : 'sub' + i)
    if (v === undefined || v === null || v === '') break
    subs.push(v)
  }
  var tabCfg = WAGON_TABS[ctx]
  var extra = tabCfg && tabCfg.getParams ? tabCfg.getParams() : {}
  var dataExtra = $(this).data('extra')
  if (dataExtra && typeof dataExtra === 'object')
    Object.assign(extra, dataExtra)
  if (ctx) openDetail(ctx, road, station, col, groupBy, subs, extra)
})

// Сворачивание/разворачивание
$(document).on('click', '.row-road-parent', function (e) {
  if ($(e.target).closest('.cell-link').length) return
  var ri = $(this).data('road-id')
  var $table = $(this).closest('table')
  var $children = $table.find('tr[data-parent-road="' + ri + '"]')
  var collapsed = $children.first().hasClass('row-hidden')
  $children.toggleClass('row-hidden', !collapsed)
  $(this)
    .find('.toggle-icon')
    .text(collapsed ? '▼' : '▶')
})
/******** НАЧАЛО ЗАПУСКА САЙТА ********/
// Начало всего и конец тоже
$(function () {
  initSidebar()
  initInnerTabs()
  loadDashboard()

  Object.keys(WAGON_TABS).forEach(function (k) {
    var cfg = WAGON_TABS[k]
    var capKey = k.charAt(0).toUpperCase() + k.slice(1)
    var applyId = cfg.applyBtnId || 'btn' + capKey + 'Apply'
    var resetId = cfg.resetBtnId || 'btn' + capKey + 'Reset'
    $('#' + applyId).on('click', function () {
      window[cfg.loadedDetKey] = false
      loadSummary(cfg)
      if ($('#' + cfg.detPanelId).hasClass('active')) {
        window[cfg.loadedDetKey] = true
        loadDetail(cfg)
      }
    })
    $('#' + resetId).on('click', function () {
      if (cfg.resetFilters) cfg.resetFilters()
      window[cfg.loadedDetKey] = false
      loadSummary(cfg)
    })
  })
})
