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
  /*{
    label: 'Аналитика',
    tabs: [{ id: 'analysis', label: 'Анализ за период' }],
  },*/
  {
    label: 'Простои и оборот',
    tabs: [{ id: 'downtime', label: 'Простои' }],
  },
  {
    label: 'Импорт',
    tabs: [
      { id: 'import', label: ' Загрузка справки РЖД ', url: BASE + '/import' },
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
  $.getJSON(KPI_BOARDS.dashboard.dataUrl).done(function (data) {
    var label = data.updated_at || '—'
    $('#brandDateSub').text('Дислокация РЖД на ' + label)
    $('#headerDate').text(label)
    showDashKpi(data)
    drawBar(data.sections)
    drawDonut(data.sections)
  })
}

// KPI карточки дашборда
function showDashKpi(data) {
  var cards = KPI_BOARDS.dashboard.cards(data)
  $('#kpiGrid').html(cards.map(kpiCard).join(''))
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
/* 
Структура WAGON_TABS:
  ctx — контекст для детализации (параметр data-ctx в ссылках)
  summaryUrl — URL для получения данных сводной таблицы
  detailUrl — URL для получения данных детализации
  metricsId — id элемента для отображения KPI (если нет, KPI не отображаются)
  kpi(data) — функция для генерации массива KPI карточек из данных сводной (если не указано, используется всего один KPI с total)
  csvFilename — имя для скачиваемого CSV (если не указано, CSV не поддерживается)
  sumTableId, sumSubId — id таблицы и элемента для отображения подвала со сводной
  detTableId, detSubId — id таблицы и элемента для отображения подвала с детализацией
  detPanelId — id панели с детализацией (для инициализации загрузки при открытии)
  loadedKey, loadedDetKey — ключи для отметки загрузки сводной и детализации (в window)
  sumSubLabel — шаблон для текста подвала сводной (используется как prefix + total)
  groupCols — массив колонок для группировки (по ним строятся ссылки в сводной и фильтруется детализация)
  getParams() — функция для получения дополнительных параметров для запросов сводной и детализации (по умолчанию {})
  filtersUrl — URL для получения данных для заполнения фильтров (если не указано, фильтры не поддерживаются)
  fillFilters(data) — функция для заполнения фильтров данными из filtersUrl (если не указано, данные не используются)
  resetFilters() — функция для сброса фильтров (если не указано, фильтры не поддерживаются)
*/
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
    sumSubLabel: 'Итого по дислокации',
    groupCols: [
      { key: 'dest_state', label: 'Страна назначения' },
      { key: 'dest_road', label: 'Дорога назначения' },
      { key: 'dest_station', label: 'Станция назначения' },
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
    kpi: function (data) {
      var groupBy = this.groupCols
        .map(function (g) {
          return g.key
        })
        .join(',')
      var main = {
        label: 'Всего в подходе',
        value: data.total,
        accent: true,
        detail: { ctx: 'approach' },
      }
      var rows = (data.metrics || []).map(function (m) {
        return {
          label: m.label,
          value: m.total,
          detail: { ctx: 'approach', road: m.label, groupBy: groupBy },
        }
      })
      return [main].concat(rows)
    },
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
    kpi: function (data) {
      var groupBy = this.groupCols
        .map(function (g) {
          return g.key
        })
        .join(',')
      var main = {
        label: 'Всего отправлено',
        value: data.total,
        accent: true,
        detail: { ctx: 'departure' },
      }
      var rows = (data.metrics || []).map(function (m) {
        return {
          label: m.label,
          value: m.total,
          detail: { ctx: 'departure', road: m.label, groupBy: groupBy },
        }
      })
      return [main].concat(rows)
    },
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
    kpi: function (data) {
      var groupBy = this.groupCols
        .map(function (g) {
          return g.key
        })
        .join(',')
      var main = {
        label: 'Всего погружено',
        value: data.total,
        accent: true,
        detail: { ctx: 'loading' },
      }
      var rows = (data.metrics || []).map(function (m) {
        return {
          label: m.label,
          value: m.total,
          detail: { ctx: 'loading', road: m.label, groupBy: groupBy },
        }
      })
      return [main].concat(rows)
    },
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
    colLabel: 'Вагонов', // метка единственного столбца сводной
    groupCols: [
      { key: 'idle_time_name', label: 'Простой' },
      //{ key: 'oper_station', label: 'Станция' },
    ],
    applyBtnId: 'btnDowntimeApply',
    filtersUrl: BASE + '/api/downtime/filters',
    fillFilters: function (data) {
      fillSelect('#fDowntimeDestStation', data.dest_station || [])
    },
    getParams: function () {
      var destStation = $('#fDowntimeDestStation').val()
      return {
        dest_station: destStation !== '' ? destStation : undefined,
        col_label: this.colLabel,
      }
    },
    resetFilters: function () {
      $('#fDowntimeDestStation').val('')
    },
  },
  // Сырьё
  'raw-material': {
    ctx: 'raw-material',
    summaryUrl: BASE + '/api/raw-material/summary',
    detailUrl: BASE + '/api/raw-material/detail',
    metricsId: 'rawMetrics',
    kpi: function (data) {
      var groupBy = this.groupCols
        .map(function (g) {
          return g.key
        })
        .join(',')
      var main = {
        label: 'Гружёных вагонов',
        value: data.total,
        accent: true,
        detail: { ctx: 'raw-material' },
      }
      var rows = (data.metrics || []).map(function (m) {
        return {
          label: m.label,
          value: m.total,
          detail: { ctx: 'raw-material', road: m.label, groupBy: groupBy },
        }
      })
      return [main].concat(rows)
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

// Настройка KPI-карточек для дашборда и других блоков.
var KPI_BOARDS = {
  // GET /api/dashboard — KPI-сводка
  dashboard: {
    dataUrl: BASE + '/api/dashboard',
    cards: function (data) {
      var grandTotal = data.sections.reduce(function (s, x) {
        return s + x.total
      }, 0)
      var tankTotal = data.sections.reduce(function (s, x) {
        return s + (x.tank_total || 0)
      }, 0)
      var commingToUgl = data.sections.reduce(function (s, x) { return s + x.comming_to_ugl }, 0)
      return [
        {
          label: 'Всего вагонов',
          value: grandTotal,
          accent: true,
          detail: { ctx: 'dislocation' },
        },
        {
          label: 'Цистерны',
          value: tankTotal,
        },
        {
          label: 'Прочие вагоны',
          value: grandTotal - tankTotal,
        },
        {
          label: 'Едут на УГЛ',
          value: commingToUgl,
        },
      ]
    },
  },
}

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
/* Загрузка сводной таблицы и KPI, если они есть настроены */
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

  /* Получаем сводную информацию  */
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
      /* Итоги по таблице */
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
  var ctxSortRaw = DETAIL_CONTEXTS[cfg.ctx] ? DETAIL_CONTEXTS[cfg.ctx].sort : null
  var ctxSortArr = ctxSortRaw
    ? (Array.isArray(ctxSortRaw) ? ctxSortRaw : [ctxSortRaw]).filter(function (s) { return s && s.field })
    : []
  var sortExtra = ctxSortArr.length
    ? {
        sort:     ctxSortArr.map(function (s) { return s.field }).join(','),
        sort_dir: ctxSortArr.map(function (s) { return s.dir || 'asc' }).join(','),
        sort_type: ctxSortArr.map(function (s) { return s.type || '' }).join(','),
      }
    : {}
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
      }, sortExtra)
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
      $table.html('<div style="text-align:center;padding:40px;color:#9DA5B0">' + esc(ajaxErr(jqXHR)) + '</div>')
    })
}
/* Детализация — виртуальная таблица */
var _vtInline = {}

function showTable($container, rows, colDefs) {
  var id      = $container.attr('id')
  var ROW_H   = 28
  var BUFFER  = 8
  var DEF_W   = 130
  var allData = rows || []

  _vtInline[id] = { all: allData, filtered: allData.slice(), cols: colDefs }

  var template = colDefs.map(function (c) { return (c.w || DEF_W) + 'px' }).join(' ')
  var totalW   = colDefs.reduce(function (s, c) { return s + (c.w || DEF_W); }, 0)

  $container.html(
    '<div class="vt-viewport" id="ivp-' + id + '">' +
      '<div class="vt-content" style="width:' + totalW + 'px">' +
        '<div class="vt-head"   id="ivh-' + id + '" style="grid-template-columns:' + template + ';width:' + totalW + 'px"></div>' +
        '<div class="vt-filter" id="ivf-' + id + '" style="grid-template-columns:' + template + ';width:' + totalW + 'px"></div>' +
        '<div id="ivr-' + id + '"></div>' +
      '</div>' +
    '</div>'
  )

  var hHtml = '', fHtml = ''
  colDefs.forEach(function (c) {
    hHtml += '<div class="vt-th' + (c.meta ? ' col-meta' : '') + '">' + esc(c.label) + '</div>'
    fHtml += '<div class="vt-fc"><input data-k="' + c.key + '" type="text" placeholder=""></div>'
  })
  document.getElementById('ivh-' + id).innerHTML = hHtml
  document.getElementById('ivf-' + id).innerHTML = fHtml

  function cellHtml(c, row) {
    var v       = row[c.key]
    var display = (v !== null && v !== undefined && v !== '') ? v : ''
    if (c.fmt) display = c.fmt(v)
    var cls   = 'vt-cell' + (c.meta ? ' col-meta' : '') + (c.right ? ' vt-right' : '')
    var style = ''
    if (c.danger) {
      var d = parseFloat(display) || 0
      if (d >= 7)      style = ' style="color:#E8392A;font-weight:700"'
      else if (d >= 3) style = ' style="color:#E8A530;font-weight:600"'
    }
    return '<div class="' + cls + '"' + style + '>' + esc(String(display)) + '</div>'
  }

  var vp     = document.getElementById('ivp-' + id)
  var rowsEl = document.getElementById('ivr-' + id)
  var lastFirst = -1, lastLast = -1

  function render(force) {
    var data     = _vtInline[id]
    var total    = data.filtered.length
    var scrollTop = vp.scrollTop
    var viewRows = Math.ceil(vp.clientHeight / ROW_H)
    var first    = Math.max(0, Math.floor(scrollTop / ROW_H) - BUFFER)
    var last     = Math.min(total, first + viewRows + BUFFER * 2)
    if (!force && first === lastFirst && last === lastLast) return
    lastFirst = first; lastLast = last

    if (!total) {
      rowsEl.style.paddingTop    = '0'
      rowsEl.style.paddingBottom = '0'
      rowsEl.innerHTML = '<div class="vt-empty">Нет данных</div>'
      return
    }
    var html = ''
    for (var i = first; i < last; i++) {
      html += '<div class="vt-row" style="grid-template-columns:' + template + ';width:' + totalW + 'px">'
      colDefs.forEach(function (c) { html += cellHtml(c, data.filtered[i]) })
      html += '</div>'
    }
    rowsEl.style.paddingTop    = (first * ROW_H) + 'px'
    rowsEl.style.paddingBottom = ((total - last) * ROW_H) + 'px'
    rowsEl.innerHTML = html
  }

  document.getElementById('ivf-' + id).addEventListener('input', function () {
    var inputs = this.querySelectorAll('input')
    var terms  = []
    for (var i = 0; i < inputs.length; i++) {
      var v = inputs[i].value.trim().toLowerCase()
      if (v) terms.push({ k: inputs[i].getAttribute('data-k'), v: v })
    }
    var data = _vtInline[id]
    data.filtered = !terms.length ? data.all.slice() : data.all.filter(function (row) {
      for (var t = 0; t < terms.length; t++) {
        if (String(row[terms[t].k] == null ? '' : row[terms[t].k]).toLowerCase().indexOf(terms[t].v) === -1) return false
      }
      return true
    })
    lastFirst = lastLast = -1
    render(true)
  })

  var ticking = false
  vp.addEventListener('scroll', function () {
    if (ticking) return; ticking = true
    requestAnimationFrame(function () { render(false); ticking = false })
  })

  render(true)
  attachFloatScrollbar(vp)
}

/******** cols config ********/

/******** Сводная и KPI ********/

function drawSummary(selector, roads, data, ctx, groupCols) {
  if (!roads || !roads.length) {
    $(selector).html(
      '<tbody><tr><td colspan="5" style="text-align:center;padding:40px;color:#9DA5B0">Нет данных по данным параметрам.</td></tr></tbody>',
    )
    return
  }
  var colGroups = data.col_groups || null
  var flatCols = data.cols || []

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
          levels[d].push(null)
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

  function subAttrs(subs) {
    var s = []
    ;(subs || []).forEach(function (v, i) {
      if (v) s.push(' data-sub' + (i ? i + 1 : '') + '="' + esc(v) + '"')
    })
    return s.join('')
  }

  function cellLink(v, dataCtx, dataRoad, dataSt, cell, dataExtra) {
    if (!v || !dataCtx) return '<td>' + fmt(v) + '</td>'
    var extra = dataExtra
      ? ' data-extra="' + esc(JSON.stringify(dataExtra)) + '"'
      : ''
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
      extra +
      '>' +
      v +
      '</td>'
    )
  }

  function totalLink(v, dataCtx, dataRoad, dataSt, dataExtra) {
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
      '"' +
      (dataExtra
        ? ' data-extra="' + esc(JSON.stringify(dataExtra)) + '"'
        : '') +
      '>' +
      (typeof v === 'number' ? v.toLocaleString('ru-RU') : v) +
      '</td>'
    )
  }

  // Переходим на массив строк вместо конкатенации строк
  var h = []
  var rowspan = depth > 1 ? ' rowspan="' + depth + '"' : ''

  h.push('<thead><tr>')
  groupCols.forEach(function (gc, i) {
    var w = i === 0 ? ' style="min-width:160px"' : ' style="min-width:180px"'
    h.push('<th class="col-meta"' + rowspan + w + '>' + esc(gc.label) + '</th>')
  })

  levels[0].forEach(function (c) {
    h.push(
      '<th' +
        (c.span > 1 ? ' colspan="' + c.span + '"' : '') +
        (depth > 1 ? ' style="text-align:center"' : '') +
        '>' +
        esc(c.label) +
        '</th>',
    )
  })
  h.push('<th class="col-total-col"' + rowspan + '>Итого</th></tr>')

  for (var d = 1; d < depth; d++) {
    h.push('<tr>')
    levels[d].forEach(function (c) {
      h.push(
        '<th' +
          (c.span > 1 ? ' colspan="' + c.span + '"' : '') +
          ' style="text-align:center">' +
          esc(c.label) +
          '</th>',
      )
    })
    h.push('</tr>')
  }
  h.push('</thead><tbody>')

  var grandTotals = flatCells.map(function () {
    return 0
  })
  var grandSum = 0

  ;(roads || []).forEach(function (road, ri) {
    var roadVal = road[groupCols[0].key] || ''
    var stations = road.stations || [] // жесткая привязка к .stations
    var hasChildren = nGroup > 1 && stations.length > 0

    h.push(
      '<tr class="row-road-parent" data-road-id="' +
        ri +
        '" data-node-id="' +
        ri +
        '">',
    )
    h.push(
      '<td class="col-meta" colspan="' +
        nGroup +
        '">' +
        (hasChildren ? '<span class="toggle-icon">▼</span>' : '') +
        esc(roadVal) +
        '</td>',
    )
    ;(road.total || []).forEach(function (v, i) {
      grandTotals[i] += v || 0
      h.push(cellLink(v, ctx, roadVal, '', flatCells[i]))
    })
    h.push(totalLink(road.grand_total || 0, ctx, roadVal, ''))
    h.push('</tr>')

    grandSum += road.grand_total || 0

    if (hasChildren) {
      var buildRows = function (level, items, parentNodeId, ancestorFilters) {
        var out = []
        var isLeaf = level === nGroup - 1
        var levelKey = groupCols[level].key

        if (isLeaf) {
          items.forEach(function (st) {
            var stVal = st[levelKey] || ''
            var rowSum = (st.v || []).reduce(function (a, b) {
              return a + b
            }, 0)

            out.push(
              '<tr class="row-data row-child" data-parent-id="' +
                esc(parentNodeId) +
                '">',
            )
            out.push('<td class="col-meta"></td>')
            for (var j = 1; j < nGroup - 1; j++)
              out.push('<td class="col-meta"></td>')
            out.push('<td class="col-meta">' + esc(stVal) + '</td>')
            ;(st.v || []).forEach(function (v, i) {
              out.push(cellLink(v, ctx, roadVal, stVal, flatCells[i]))
            })
            out.push(totalLink(rowSum, ctx, roadVal, stVal))
            out.push('</tr>')
          })
        } else {
          var groups = {},
            order = []
          items.forEach(function (st) {
            var val = st[levelKey] || ''
            if (!groups[val]) {
              groups[val] = []
              order.push(val)
            }
            groups[val].push(st)
          })

          order.forEach(function (groupVal, gi) {
            var nodeId = parentNodeId + ':' + gi
            var gItems = groups[groupVal]
            var subTotal = flatCells.map(function () {
              return 0
            })
            var subSum = 0

            gItems.forEach(function (st) {
              ;(st.v || []).forEach(function (v, i) {
                subTotal[i] += v || 0
              })
              subSum += (st.v || []).reduce(function (a, b) {
                return a + b
              }, 0)
            })

            var curFilters = Object.assign({}, ancestorFilters)
            curFilters[levelKey] = groupVal
            var bcVals = Object.keys(curFilters).map(function (k) {
              return curFilters[k]
            })
            var curFiltersWithPath = Object.assign({}, curFilters, {
              _bcpath: JSON.stringify(bcVals),
            })

            out.push(
              '<tr class="row-data row-child row-sub-parent" data-parent-id="' +
                esc(parentNodeId) +
                '" data-node-id="' +
                esc(nodeId) +
                '">',
            )
            out.push('<td class="col-meta"></td>')
            for (var j = 1; j < level; j++)
              out.push('<td class="col-meta"></td>')
            out.push(
              '<td class="col-meta"><span class="toggle-icon">▼</span>' +
                esc(groupVal) +
                '</td>',
            )
            for (var j = level + 1; j < nGroup; j++)
              out.push('<td class="col-meta"></td>')

            subTotal.forEach(function (v, i) {
              out.push(
                cellLink(v, ctx, '', '', flatCells[i], curFiltersWithPath),
              )
            })
            out.push(totalLink(subSum, ctx, '', '', curFiltersWithPath))
            out.push('</tr>')

            out.push(buildRows(level + 1, gItems, nodeId, curFilters))
          })
        }
        return out.join('')
      }

      var rootFilters = {}
      rootFilters[groupCols[0].key] = roadVal
      h.push(buildRows(1, stations, '' + ri, rootFilters))
    }
  })

  h.push(
    '<tr class="row-total row-grand"><td class="col-meta" colspan="' +
      nGroup +
      '">Общий итог</td>',
  )

  grandTotals.forEach(function (v, i) {
    if (v && ctx) {
      h.push(
        '<td class="cell-link" data-ctx="' +
          esc(ctx) +
          '" data-road="" data-station="" data-col="' +
          esc(flatCells[i].col) +
          '"' +
          subAttrs(flatCells[i].subs) +
          '>' +
          v +
          '</td>',
      )
    } else {
      h.push('<td>' + (v || '') + '</td>')
    }
  })

  if (grandSum && ctx) {
    h.push(
      '<td class="col-total-col cell-link" data-ctx="' +
        esc(ctx) +
        '" data-road="" data-station="" data-col="">' +
        grandSum.toLocaleString('ru-RU') +
        '</td>',
    )
  } else {
    h.push(
      '<td class="col-total-col">' + grandSum.toLocaleString('ru-RU') + '</td>',
    )
  }
  h.push('</tr></tbody>')

  // Итоговый единый рендеринг в DOM
  $(selector).html(h.join(''))
}

// CSV-экспорт таблицы по id и имени файла
function saveCSV(tableId, filename) {
  var table = document.getElementById(tableId)
  if (!table) return
  var rows = []
  table.querySelectorAll('tr').forEach(function (tr) {
    if (tr.offsetParent === null || getComputedStyle(tr).display === 'none') return
    var cells = []
    tr.querySelectorAll('th, td').forEach(function (cell) {
      var clone = cell.cloneNode(true)
      clone.querySelectorAll('.toggle-icon').forEach(function (el) { el.remove() })
      var val = clone.textContent.trim().replace(/\r?\n|\r/g, ' ').replace(/"/g, '""')
      cells.push('"' + val + '"')
    })
    rows.push(cells.join(';'))
  })
  var csv = '\uFEFF' + rows.join('\n')
  var a = document.createElement('a')
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
  a.download = (filename || 'таблица') + '_' + new Date().toISOString().slice(0, 10) + '.csv'
  a.click()
  URL.revokeObjectURL(a.href)
}
// Простоая выгрузка в Excel активной таблицы (указаывается ID таблицы в настроках при построении сводной таблицы)
// Текст ошибки из jQuery
// Плавающий горизонтальный скролл для широких таблиц
function attachFloatScrollbar(scrollEl) {
  var existing = scrollEl._floatScrollbar
  if (existing) existing.remove()

  var floater = document.createElement('div')
  floater.className = 'float-scrollbar'
  var inner = document.createElement('div')
  inner.className = 'float-scrollbar-inner'
  floater.appendChild(inner)
  document.body.appendChild(floater)
  scrollEl._floatScrollbar = floater

  var syncing = false
  floater.addEventListener('scroll', function () {
    if (syncing) return
    syncing = true
    scrollEl.scrollLeft = floater.scrollLeft
    syncing = false
  })
  scrollEl.addEventListener('scroll', function () {
    if (syncing) return
    syncing = true
    floater.scrollLeft = scrollEl.scrollLeft
    syncing = false
  })

  function update() {
    var rect = scrollEl.getBoundingClientRect()
    var visible = rect.top < window.innerHeight && rect.bottom > 0
    var needsScroll = scrollEl.scrollWidth > scrollEl.clientWidth
    if (visible && needsScroll && rect.bottom > window.innerHeight) {
      floater.style.display = 'block'
      floater.style.left = rect.left + 'px'
      floater.style.width = rect.width + 'px'
      inner.style.width = scrollEl.scrollWidth + 'px'
    } else {
      floater.style.display = 'none'
    }
  }

  window.addEventListener('scroll', update, { passive: true })
  window.addEventListener('resize', update, { passive: true })
  update()
}

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

// HTML одной KPI-карточки. Поля: label, value (или total), accent, sub, detail.
// detail: { ctx, road, station, col, groupBy, subs, params } — открыть детализацию по клику
// detail: { url } — открыть произвольный URL по клику
function kpiCard(item) {
  var val = item.value != null ? item.value : item.total || 0
  var hasDetail = !!item.detail
  var cls =
    'kpi-card' +
    (item.accent ? ' accent' : '') +
    (hasDetail ? ' kpi-card--link' : '')
  var detAttr = hasDetail
    ? " data-detail='" +
      JSON.stringify(item.detail).replace(/'/g, '&#39;') +
      "'"
    : ''
  return (
    '<div class="' +
    cls +
    '"' +
    detAttr +
    '>' +
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
  $table.find('.row-road-parent, .row-sub-parent').each(function () {
    $(this).data('node-collapsed', true).find('.toggle-icon').text('▶')
  })
  $table.find('.row-child').addClass('row-hidden')
}
function expandAll($table) {
  $table.find('.row-road-parent, .row-sub-parent').each(function () {
    $(this).data('node-collapsed', false).find('.toggle-icon').text('▼')
  })
  $table.find('.row-child').removeClass('row-hidden')
}

// Схлопнуть узел дерева: скрыть всех потомков (BFS по data-parent-id)
function collapseNode($row, $table) {
  $row.data('node-collapsed', true).find('.toggle-icon').text('▶')
  var nodeId = $row.data('node-id')
  var queue = [$table.find('tr[data-parent-id="' + nodeId + '"]')]
  while (queue.length) {
    var $set = queue.shift()
    $set.addClass('row-hidden')
    $set.each(function () {
      var cid = $(this).data('node-id')
      if (cid !== undefined)
        queue.push($table.find('tr[data-parent-id="' + cid + '"]'))
    })
  }
}
// Раскрыть узел дерева: показать дочерние строки
function expandNode($row, $table) {
  $row.data('node-collapsed', false).find('.toggle-icon').text('▼')
  var nodeId = $row.data('node-id')
  ;(function showChildren(pid) {
    $table.find('tr[data-parent-id="' + pid + '"]').each(function () {
      $(this).removeClass('row-hidden')
      if (!$(this).data('node-collapsed')) {
        var cid = $(this).data('node-id')
        if (cid !== undefined) showChildren(cid)
      }
    })
  })(nodeId)
}

$(document).on('click', '[data-collapse-table]', function () {
  collapseAll($('#' + $(this).data('collapse-table')))
})
$(document).on('click', '[data-expand-table]', function () {
  expandAll($('#' + $(this).data('expand-table')))
})

// Поиск по столбцам:  строку-фильтр под заголовком таблицы
function matchFilter(q, text) {
  if (q.indexOf('%') === -1) return text.indexOf(q) !== -1
  var pattern = q
    .split('%')
    .map(function (s) {
      return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    })
    .join('.*')
  return new RegExp(pattern).test(text)
}
function addSearch($table) {
  var cells = ''
  $table.find('thead tr:first th').each(function () {
    cells +=
      '<td><input class="col-search-input" type="text" placeholder=""></td>'
  })
  $table.find('tbody').prepend('<tr class="search-row">' + cells + '</tr>')
  var scrollEl = $table.closest('.table-scroll')[0]
  if (scrollEl) attachFloatScrollbar(scrollEl)
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
      return !q || matchFilter(q, $cells.eq(ci).text().toLowerCase())
    })
    $(this).toggle(show)
  })
})

// Имена URL-параметров для уровней подзаголовка колонок (после типа вагона).
// Новый уровень шапки → добавить имя параметра сюда + фильтр в *Detail на бэке
// + проброс параметра в detail.php.
var SUB_PARAM_NAMES = ['cargo_state']

// Drill-down: открыть страницу детализации в новой вкладке.
// extra — активные фильтры вкладки (cfg.getParams()) передаются в URL как есть
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

// Клик по KPI-карточке с детализацией
$(document).on('click', '.kpi-card--link', function () {
  var raw = $(this).attr('data-detail')
  if (!raw) return
  var d
  try {
    d = JSON.parse(raw)
  } catch (e) {
    return
  }
  if (d.url) {
    window.open(d.url, '_blank')
    return
  }
  var extra = d.params || {}
  var tabCfg = d.ctx && WAGON_TABS[d.ctx]
  if (tabCfg && tabCfg.getParams) Object.assign(extra, tabCfg.getParams())
  openDetail(
    d.ctx || '',
    d.road || '',
    d.station || '',
    d.col || '',
    d.groupBy || '',
    d.subs || [],
    extra,
  )
})

// Сворачивание/разворачивание
$(document).on('click', '.row-road-parent', function (e) {
  if ($(e.target).closest('.cell-link').length) return
  var $table = $(this).closest('table')
  if ($(this).data('node-collapsed')) expandNode($(this), $table)
  else collapseNode($(this), $table)
})
$(document).on('click', '.row-sub-parent', function (e) {
  if ($(e.target).closest('.cell-link').length) return
  e.stopPropagation()
  var $table = $(this).closest('table')
  if ($(this).data('node-collapsed')) expandNode($(this), $table)
  else collapseNode($(this), $table)
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
