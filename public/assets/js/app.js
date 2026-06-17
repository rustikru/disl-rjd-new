'use strict'

var BASE = window.APP_BASE || ''

// наивигация (Боковое меню)
var TAB_GROUPS = [
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
    tabs: [
      { id: 'analysis-period', label: 'Анализ за период' },
      { id: 'maps', label: 'Карта', url: BASE + '/maps' },
    ],
  },
  {
    label: 'Простои и оборот',
    tabs: [{ id: 'downtime', label: 'Простои' }],
  },
  {
    label: 'Импорт',
    tabs: [
      {
        id: 'import',
        label: ' Загрузка справки РЖД ',
        url: BASE + '/import',
      },
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
      btn.className = 'nav-item' + (tab.id === 'dislocation' ? ' active' : '')
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

// Конфиг вкладок: сводные таблицы и детализации
/*
  Структура WAGON_TABS — поля в стандартном порядке:

  Обязательные:
    ctx            — ключ контекста (data-ctx в ссылках, ключ в DETAIL_CONTEXTS)
    summaryUrl     — URL сводной таблицы (отсутствует у detail-only вкладок)
    detailUrl      — URL расширенной/детализации
    csvFilename    — префикс CSV сводной (кнопка не появится, если не задан)
    csvDetFilename — префикс CSV расширенной
    sumTableId     — id <table> сводной
    sumSubId       — id подписи «Итого: N» под сводной
    sumSubLabel    — текст-префикс подписи
    detTableId     — id <table> расширенной
    detSubId       — id подписи «Строк: N» под расширенной (опционально)
    detPanelId     — id панели расширенной (для ленивой загрузки при открытии)
    loadedKey      — ключ window[...] — флаг загрузки сводной
    loadedDetKey   — ключ window[...] — флаг загрузки расширенной
    groupCols[]    — измерения строк сводной: [{key, label}]
    colDims[]      — измерения колонок: [{key, paramName}] или [{key, synthetic:true}]
    getParams()    — доп. параметры из формы фильтров → передаются в оба URL

  Опциональные:
    filtersUrl     — URL для заполнения <select> фильтров
    fillFilters(d) — заполняет <select> данными из filtersUrl
    resetFilters() — сбрасывает фильтры
    applyBtnId     — id кнопки «Применить» (без неё загрузка при открытии вкладки)
    resetBtnId     — id кнопки «Сбросить» (если не задан — вычисляется как btn{TabId}Reset)
    metricsId      — id контейнера KPI-карточек
    metricsLabel   — подпись главной KPI-карточки (если kpi() не задан)
    kpi(data)      — генерирует [{label, value, accent?, detail?}] из ответа сводной
*/

// Общий построитель KPI для табов с группировкой по дороге
function makeRoadKpi(cfg, data, mainLabel) {
  var groupBy = cfg.groupCols
    .map(function (g) {
      return g.key
    })
    .join(',')
  var main = {
    label: mainLabel,
    value: data.total,
    accent: true,
    detail: { ctx: cfg.ctx },
  }
  var rows = (data.metrics || []).map(function (m) {
    return {
      label: m.label,
      value: m.total,
      detail: { ctx: cfg.ctx, road: m.label, groupBy: groupBy },
    }
  })
  return [main].concat(rows)
}

var WAGON_TABS = {
  // Дислокация
  dislocation: {
    ctx: 'dislocation',
    summaryUrl: BASE + '/api/dislocation/summary',
    detailUrl: BASE + '/api/dislocation/detail',
    csvFilename: 'дислокация',
    csvDetFilename: 'дислокация-расширенная',
    sumTableId: 'mainTable',
    sumSubId: 'mainTableSub',
    sumSubLabel: 'Итого по дислокации',
    detTableId: 'dislExtTable',
    detPanelId: 'disl-extended',
    loadedKey: '_dislLoaded',
    loadedDetKey: '_extLoaded',
    groupCols: [
      { key: 'dest_state', label: 'Страна назначения' },
      { key: 'dest_road', label: 'Дорога назначения' },
      { key: 'dest_station', label: 'Станция назначения' },
    ],
    colDims: [
      { key: 'wagon_type_code', paramName: 'wagon_type' },
      { key: 'cargo_w_type', paramName: 'cargo_state' },
    ],
    getParams: function () {
      return {}
    },
  },

  // Подход
  approach: {
    ctx: 'approach',
    summaryUrl: BASE + '/api/approach/summary',
    detailUrl: BASE + '/api/approach/detail',
    filtersUrl: BASE + '/api/approach/filters',
    metricsId: 'approachMetrics',
    kpi: function (data) {
      return makeRoadKpi(this, data, 'Всего в подходе')
    },
    csvFilename: 'подход',
    csvDetFilename: 'подход-расширенная',
    sumTableId: 'approachSumTable',
    sumSubId: 'approachSumSub',
    sumSubLabel: 'Всего в подходе',
    detTableId: 'approachDetTable',
    detSubId: 'approachDetSub',
    detPanelId: 'approach-detail',
    loadedKey: '_approachLoaded',
    loadedDetKey: '_approachDetLoaded',
    groupCols: [
      { key: 'oper_road', label: 'Дорога операции' },
      { key: 'oper_station', label: 'Станция операции' },
    ],
    colDims: [
      { key: 'wagon_type_code', paramName: 'wagon_type' },
      { key: 'cargo_w_type', paramName: 'cargo_state' },
    ],
    getParams: function () {
      return {
        cargo: $('#fApproachCargo').val() || undefined,
        //prev_cargo: $('#fApproachPrevCargo').val() || undefined,
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
    summaryUrl: BASE + '/api/departure/summary',
    detailUrl: BASE + '/api/departure/detail',
    filtersUrl: BASE + '/api/departure/filters',
    metricsId: 'departureMetrics',
    kpi: function (data) {
      return makeRoadKpi(this, data, 'Всего отправлено')
    },
    csvFilename: 'отправление',
    csvDetFilename: 'отправление-расширенная',
    sumTableId: 'departureSumTable',
    sumSubId: 'departureSumSub',
    sumSubLabel: 'Всего',
    detTableId: 'departureDetTable',
    detSubId: 'departureDetSub',
    detPanelId: 'departure-detail',
    loadedKey: '_departureLoaded',
    loadedDetKey: '_departureDetLoaded',
    groupCols: [
      { key: 'dest_road', label: 'Дорога назначения' },
      { key: 'dest_station', label: 'Станция назначения' },
    ],
    colDims: [{ key: 'wagon_type_code', paramName: 'wagon_type' }],
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
    summaryUrl: BASE + '/api/loading/summary',
    detailUrl: BASE + '/api/loading/detail',
    filtersUrl: BASE + '/api/loading/filters',
    metricsId: 'loadingMetrics',
    kpi: function (data) {
      return makeRoadKpi(this, data, 'Всего погружено')
    },
    csvFilename: 'погрузка',
    csvDetFilename: 'погрузка-расширенная',
    sumTableId: 'loadingSumTable',
    sumSubId: 'loadingSumSub',
    sumSubLabel: 'Всего',
    detTableId: 'loadingDetTable',
    detSubId: 'loadingDetSub',
    detPanelId: 'loading-detail',
    loadedKey: '_loadingLoaded',
    loadedDetKey: '_loadingDetLoaded',
    groupCols: [
      { key: 'depart_road', label: 'Дорога' },
      { key: 'depart_station', label: 'Станция' },
    ],
    colDims: [{ key: 'wagon_type_code', paramName: 'wagon_type' }],
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
    filtersUrl: BASE + '/api/downtime/filters',
    csvFilename: 'простои',
    csvDetFilename: 'простои-расширенная',
    sumTableId: 'downtimeSumTable',
    sumSubId: 'downtimeSumSub',
    sumSubLabel: 'Вагонов с простоем',
    detTableId: 'downtimeDetTable',
    detSubId: 'downtimeDetSub',
    detPanelId: 'downtime-detail',
    loadedKey: '_downtimeLoaded',
    loadedDetKey: '_downtimeDetLoaded',
    applyBtnId: 'btnDowntimeApply',
    groupCols: [
      { key: 'cargo_name', label: 'Груз' },
      { key: 'idle_time_name', label: 'Простой' },
    ],
    colDims: [
      { key: 'fixed_col_label', synthetic: true },
      { key: 'm_wagon_type_code', paramName: 'wagon_type' },
      { key: 'm_wag_state', paramName: 'wag_state' },
    ],
    getParams: function () {
      var destStation = $('#fDowntimeDestStation').val()
      return { dest_station: destStation !== '' ? destStation : undefined }
    },
    fillFilters: function (data) {
      fillSelect('#fDowntimeDestStation', data.dest_station || [])
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
      return makeRoadKpi(this, data, 'Гружёных вагонов')
    },
    csvFilename: 'сырьё',
    csvDetFilename: 'сырьё-расширенная',
    sumTableId: 'rawSumTable',
    sumSubId: 'rawSumSub',
    sumSubLabel: 'Гружёных вагонов',
    detTableId: 'rawDetTable',
    detSubId: 'rawDetSub',
    detPanelId: 'raw-detail',
    loadedKey: '_rawLoaded',
    loadedDetKey: '_rawDetLoaded',
    groupCols: [
      { key: 'cargo_name', label: 'Груз' },
      //{ key: 'consignee', label: 'Грузополучатель' },
    ],
    colDims: [{ key: 'wagon_type_code', paramName: 'wagon_type' }],
    getParams: function () {
      return {}
    },
  },

  // Анализ за период
  'analysis-period': {
    ctx: 'analysis-period',
    detailUrl: BASE + '/api/analysis/period/detail',
    csvDetFilename: 'анализ-за-период',
    detTableId: 'analysisPeriodDetTable',
    detSubId: 'analysisPeriodDetSub',
    detPanelId: 'analysisPeriod-detail',
    loadedKey: '_analysisPeriodLoaded',
    loadedDetKey: '_analysisPeriodDetLoaded',
    applyBtnId: 'btnAnalysisPeriodApply',
    getParams: function () {
      return {
        wagon_no: $('#fAnalysisPeriodWagonNo').val().trim() || undefined,
        date_from: $('#fAnalysisPeriodDateFrom').val() || undefined,
        date_to: $('#fAnalysisPeriodDateTo').val() || undefined,
      }
    },
  },
}

// KPI-карточки для дашборда и отдельных блоков
var KPI_BOARDS = {
  // GET /api/dashboard
  dashboard: {
    dataUrl: BASE + '/api/dashboard',
    cards: function (data) {
      var grandTotal = 0,
        tankTotal = 0,
        commingToUgl = 0,
        arrivedTodayUgl = 0,
        loadedTransit = 0
      ;(data.sections || []).forEach(function (x) {
        grandTotal += x.total || 0
        tankTotal += x.tank_total || 0
        commingToUgl += x.comming_to_ugl || 0
        arrivedTodayUgl += x.arrived_today_ugl || 0
        loadedTransit += x.loaded_transit || 0
      })

      var tr = data.trends || {}
      function makeTrend(pct, dir) {
        return pct ? { pct: pct, dir: dir || 'neutral' } : null
      }

      return [
        {
          label: 'Груженые в пути c УГЛ',
          value: loadedTransit,
          variant: 'pill',
          trend: makeTrend(tr.met_loaded_transit, tr.met_loaded_transit_dir),
        },
        {
          label: 'Цистерны',
          value: tankTotal,
          variant: 'pill',
          trend: makeTrend(tr.tank, tr.tank_dir),
        },
        {
          label: 'Прочие вагоны',
          value: grandTotal - tankTotal,
          variant: 'pill',
          trend: makeTrend(tr.other, tr.other_dir),
        },
        {
          label: 'В пути на УГЛ',
          value: commingToUgl,
          variant: 'pill',
          trend: makeTrend(tr.met_comming_to_ugl, tr.met_comming_to_ugl_dir),
        },
        {
          label: 'Прибыло сегодня (УГЛ)',
          value: arrivedTodayUgl,
          variant: 'pill',
          trend: makeTrend(tr.met_arrived_ugl, tr.met_arrived_ugl_dir),
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
/* Инициализация вкладки: загрузка сводной, KPI, фильтров и кнопки CSV  */
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
  if (!cfg.summaryUrl && cfg.detailUrl && !window[cfg.loadedDetKey]) {
    window[cfg.loadedDetKey] = true
    loadDetail(cfg)
  }
}
/* Загрузка и отображение фильтров, если они есть настроены */
function loadFilters(cfg) {
  if (!cfg.filtersUrl) return
  $.getJSON(cfg.filtersUrl).done(function (data) {
    if (cfg.fillFilters) cfg.fillFilters(data)
  })
}
/* Загрузка сводной таблицы и KPI, если они есть настроены */
function loadSummary(cfg) {
  if (!cfg.summaryUrl) return
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
  var cby = (cfg.colDims || [])
    .map(function (f) {
      return f.key
    })
    .join(',')
  if (cby) summaryParams.col_by = cby

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
      var subtotalDepth = null
      if (cfg.totalColDims && cfg.totalColDims.length && cfg.colDims && cfg.colDims.length) {
        var colDimKeys = cfg.colDims.map(function (c) { return c.key })
        cfg.totalColDims.forEach(function (key) {
          var idx = colDimKeys.indexOf(key)
          if (idx !== -1 && (subtotalDepth === null || idx < subtotalDepth)) subtotalDepth = idx
        })
      }
      drawSummary(
        '#' + cfg.sumTableId,
        data.roads,
        data,
        cfg.ctx,
        cfg.groupCols,
        subtotalDepth,
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
/* Загрузка и отображение детализации по клику на строке или колонке сводной таблицы */
function loadDetail(cfg) {
  var $sub = $('#' + cfg.detSubId)
  var $table = $('#' + cfg.detTableId)
  var cols = DETAIL_CONTEXTS[cfg.ctx] ? DETAIL_CONTEXTS[cfg.ctx].cols : []
  $sub.text('Загрузка...')
  var ctxSortRaw = DETAIL_CONTEXTS[cfg.ctx]
    ? DETAIL_CONTEXTS[cfg.ctx].sort
    : null
  var ctxSortArr = ctxSortRaw
    ? (Array.isArray(ctxSortRaw) ? ctxSortRaw : [ctxSortRaw]).filter(
        function (s) {
          return s && s.field
        },
      )
    : []
  var sortExtra = ctxSortArr.length
    ? {
        sort: ctxSortArr
          .map(function (s) {
            return s.field
          })
          .join(','),
        sort_dir: ctxSortArr
          .map(function (s) {
            return s.dir || 'asc'
          })
          .join(','),
        sort_type: ctxSortArr
          .map(function (s) {
            return s.type || ''
          })
          .join(','),
      }
    : {}
  var listParams = cfg.listParams
    ? cfg.listParams()
    : Object.assign(
        {},
        cfg.getParams(),
        {
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
        },
        sortExtra,
      )
  $.getJSON(cfg.detailUrl, listParams)
    .done(function (data) {
      if (cfg.showList) {
        cfg.showList(data, cfg)
      } else {
        showTable($table, data.rows, cols)
      }
      $sub.text('Строк: ' + (data.rows || []).length.toLocaleString('ru-RU'))
      if (cfg.csvDetFilename) {
        var $acts = $table.closest('.table-section').find('.table-acts')
        if ($acts.length && !$acts.find('.btn-csv-det').length) {
          var $btn = $(
            '<button class="btn btn-ghost btn-sm btn-csv-det">Скачать CSV</button>',
          )
          $btn.on('click', function () {
            saveCSVfromVT(cfg.detTableId, cfg.csvDetFilename)
          })
          $acts.append($btn)
        }
      }
    })
    .fail(function (jqXHR) {
      $table.html(
        '<div style="text-align:center;padding:40px;color:#9DA5B0">' +
          esc(ajaxErr(jqXHR)) +
          '</div>',
      )
    })
}
/* Детализация — виртуальная таблица */
var _vtInline = {}

function oracleMaskFmt(v, mask) {
  if (v == null || v === '') return ''
  var s = String(v)
  var d = null
  var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):?(\d{2})?/)
  if (m)
    d = {
      DD: m[3],
      MM: m[2],
      YYYY: m[1],
      HH24: m[4],
      MI: m[5],
      SS: m[6] || '00',
    }
  if (!d) {
    m = s.match(/^(\d{2})\.(\d{2})\.(\d{4})[T ](\d{2}):(\d{2}):?(\d{2})?/)
    if (m)
      d = {
        DD: m[1],
        MM: m[2],
        YYYY: m[3],
        HH24: m[4],
        MI: m[5],
        SS: m[6] || '00',
      }
  }
  if (!d) return s
  return mask
    .replace('HH24', d.HH24)
    .replace('YYYY', d.YYYY)
    .replace('MM', d.MM)
    .replace('DD', d.DD)
    .replace('MI', d.MI)
    .replace('SS', d.SS)
}

function vtMeasureCols(colDefs, data) {
  var cv = document.createElement('canvas')
  var ctx = cv.getContext('2d')
  var PAD = 20,
    MIN = 50,
    MAX = 320
  ctx.font = 'bold 12px sans-serif'
  var widths = colDefs.map(function (c) {
    return Math.max(
      MIN,
      Math.min(MAX, Math.ceil(ctx.measureText(c.label).width) + PAD),
    )
  })
  ctx.font = '12px sans-serif'
  var sample = data.length > 300 ? data.slice(0, 300) : data
  sample.forEach(function (row) {
    colDefs.forEach(function (c, i) {
      var v = c.fmt
        ? c.fmt(row[c.key])
        : row[c.key] == null
          ? ''
          : String(row[c.key])
      var w = Math.ceil(ctx.measureText(String(v == null ? '' : v)).width) + PAD
      if (w > widths[i]) widths[i] = Math.min(MAX, w)
    })
  })
  return widths
}
/* Рендер таблицы с виртуальным скроллом. Рисует только видимую часть, рассчитывая её при скролле. */
function showTable($container, rows, colDefs) {
  colDefs = colDefs.map(function (c) {
    if (c.formatData && !c.fmt) {
      var mask = c.formatData
      return Object.assign({}, c, {
        fmt: function (v) {
          return oracleMaskFmt(v, mask)
        },
      })
    }
    return c
  })
  var id = $container.attr('id')
  var ROW_H = 28 // высота строки, используется для расчёта виртуального скролла
  var BUFFER = 8 // кол-во строк в буфере до и после видимой области, для предотвращения мерцания при скролле
  var allData = rows || []

  _vtInline[id] = { all: allData, filtered: allData.slice(), cols: colDefs }

  var measured = vtMeasureCols(colDefs, allData)
  var baseW = measured.reduce(function (s, w) {
    return s + w
  }, 0)
  var availW = $container[0].offsetWidth || window.innerWidth - 260
  var scale = availW > baseW ? availW / baseW : 1
  var template = measured
    .map(function (w) {
      return Math.floor(w * scale) + 'px'
    })
    .join(' ')
  var totalW = availW > baseW ? availW : baseW

  $container.html(
    '<div class="vt-viewport" id="ivp-' +
      id +
      '">' +
      '<div class="vt-content" style="width:' +
      totalW +
      'px">' +
      '<div class="vt-head"   id="ivh-' +
      id +
      '" style="grid-template-columns:' +
      template +
      ';width:' +
      totalW +
      'px"></div>' +
      '<div class="vt-filter" id="ivf-' +
      id +
      '" style="grid-template-columns:' +
      template +
      ';width:' +
      totalW +
      'px"></div>' +
      '<div id="ivr-' +
      id +
      '"></div>' +
      '</div>' +
      '</div>',
  )

  var hHtml = '',
    fHtml = ''
  colDefs.forEach(function (c) {
    hHtml +=
      '<div class="vt-th' +
      (c.meta ? ' col-meta' : '') +
      '">' +
      esc(c.label) +
      '</div>'
    fHtml +=
      '<div class="vt-fc"><input data-k="' +
      c.key +
      '" type="text" placeholder=""></div>'
  })
  document.getElementById('ivh-' + id).innerHTML = hHtml
  document.getElementById('ivf-' + id).innerHTML = fHtml

  function cellHtml(c, row) {
    var v = row[c.key]
    var display = v !== null && v !== undefined && v !== '' ? v : ''
    if (c.fmt) display = c.fmt(v)
    var cls =
      'vt-cell' + (c.meta ? ' col-meta' : '') + (c.right ? ' vt-right' : '')
    var style = ''
    if (c.danger) {
      var d = parseFloat(display) || 0
      if (d >= 7) style = ' style="color:#E8392A;font-weight:700"'
      else if (d >= 3) style = ' style="color:#E8A530;font-weight:600"'
    }
    return (
      '<div class="' + cls + '"' + style + '>' + esc(String(display)) + '</div>'
    )
  }

  var vp = document.getElementById('ivp-' + id)
  var rowsEl = document.getElementById('ivr-' + id)
  var lastFirst = -1,
    lastLast = -1

  function render(force) {
    var data = _vtInline[id]
    var total = data.filtered.length
    var scrollTop = vp.scrollTop
    var viewRows = Math.ceil(vp.clientHeight / ROW_H)
    var first = Math.max(0, Math.floor(scrollTop / ROW_H) - BUFFER)
    var last = Math.min(total, first + viewRows + BUFFER * 2)
    if (!force && first === lastFirst && last === lastLast) return
    lastFirst = first
    lastLast = last

    if (!total) {
      rowsEl.style.paddingTop = '0'
      rowsEl.style.paddingBottom = '0'
      rowsEl.innerHTML = '<div class="vt-empty">Нет данных</div>'
      return
    }
    var html = ''
    for (var i = first; i < last; i++) {
      html +=
        '<div class="vt-row" style="grid-template-columns:' +
        template +
        ';width:' +
        totalW +
        'px">'
      colDefs.forEach(function (c) {
        html += cellHtml(c, data.filtered[i])
      })
      html += '</div>'
    }
    rowsEl.style.paddingTop = first * ROW_H + 'px'
    rowsEl.style.paddingBottom = (total - last) * ROW_H + 'px'
    rowsEl.innerHTML = html
  }

  document.getElementById('ivf-' + id).addEventListener('input', function () {
    var inputs = this.querySelectorAll('input')
    var terms = []
    for (var i = 0; i < inputs.length; i++) {
      var v = inputs[i].value.trim().toLowerCase()
      if (v) terms.push({ k: inputs[i].getAttribute('data-k'), v: v })
    }
    var data = _vtInline[id]
    data.filtered = !terms.length
      ? data.all.slice()
      : data.all.filter(function (row) {
          for (var t = 0; t < terms.length; t++) {
            if (
              String(row[terms[t].k] == null ? '' : row[terms[t].k])
                .toLowerCase()
                .indexOf(terms[t].v) === -1
            )
              return false
          }
          return true
        })
    lastFirst = lastLast = -1
    render(true)
  })

  var ticking = false
  vp.addEventListener('scroll', function () {
    if (ticking) return
    ticking = true
    requestAnimationFrame(function () {
      render(false)
      ticking = false
    })
  })

  function fitVpHeight() {
    var top = vp.getBoundingClientRect().top
    var h = top > 0 ? window.innerHeight - top - 10 : window.innerHeight - 10
    vp.style.height = Math.max(300, h) + 'px'
    render(false)
  }

  requestAnimationFrame(function () {
    fitVpHeight()
    render(true)
  })
  window.addEventListener('resize', fitVpHeight, { passive: true })
  window.addEventListener('scroll', fitVpHeight, { passive: true })

  render(true)
  attachFloatScrollbar(vp)
}

/******** cols config ********/

/******** Сводная и KPI ********/

function drawSummary(selector, roads, data, ctx, groupCols, subtotalDepth) {
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

  // Build displayCells (flatCells + optional Σ entries) and displayLevels
  var displayCells = []
  var displayLevels = []
  var hasSubtotals = (subtotalDepth !== null && subtotalDepth !== undefined && depth > subtotalDepth + 1)

  if (hasSubtotals) {
    var dlvl
    for (dlvl = 0; dlvl < depth; dlvl++) { displayLevels.push([]) }
    var flatIdx = 0
    var subLvlIdx = 0
    levels[subtotalDepth].forEach(function (grp) {
      var span = grp.span
      displayLevels[subtotalDepth].push({ label: grp.label, span: span + 1 })
      displayLevels[subtotalDepth + 1].push({ label: 'Σ', span: 1, isSubtotal: true })
      for (var j = 0; j < span; j++) {
        displayLevels[subtotalDepth + 1].push(levels[subtotalDepth + 1][subLvlIdx + j])
      }
      displayCells.push({ isSubtotal: true, col: grp.label, dataFrom: flatIdx, dataTo: flatIdx + span - 1 })
      for (var fi = flatIdx; fi < flatIdx + span; fi++) {
        displayCells.push({ isSubtotal: false, col: flatCells[fi].col, subs: flatCells[fi].subs, dataIdx: fi })
      }
      flatIdx += span
      subLvlIdx += span
    })
    for (dlvl = 0; dlvl < depth; dlvl++) {
      if (dlvl !== subtotalDepth && dlvl !== subtotalDepth + 1) {
        displayLevels[dlvl] = levels[dlvl].slice()
      }
    }
  } else {
    flatCells.forEach(function (fc, fi) {
      displayCells.push({ isSubtotal: false, col: fc.col, subs: fc.subs, dataIdx: fi })
    })
    for (var dlvl2 = 0; dlvl2 < depth; dlvl2++) {
      displayLevels.push(levels[dlvl2].slice())
    }
  }

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

  function getDisplayVal(dc, valArray) {
    if (dc.isSubtotal) {
      var s = 0
      for (var si = dc.dataFrom; si <= dc.dataTo; si++) { s += valArray[si] || 0 }
      return s
    }
    return valArray[dc.dataIdx]
  }

  function subtotalCell(v) {
    var disp = typeof v === 'number' ? v.toLocaleString('ru-RU') : (v || '')
    return '<td class="col-subtotal">' + disp + '</td>'
  }

  function renderDisplayCell(dc, v, dataCtx, dataRoad, dataSt, dataExtra) {
    if (dc.isSubtotal) return subtotalCell(v)
    return cellLink(v, dataCtx, dataRoad, dataSt, { col: dc.col, subs: dc.subs }, dataExtra)
  }

  // Переходим на массив строк вместо конкатенации строк
  var h = []
  var rowspan = depth > 1 ? ' rowspan="' + depth + '"' : ''

  // Один заголовочный столбец иерархии: «Страна / Дорога / Станция»
  var groupHeader = groupCols.map(function (gc) { return esc(gc.label) }).join(' / ')
  h.push('<thead><tr>')
  h.push('<th class="col-meta" style="min-width:200px"' + rowspan + '>' + groupHeader + '</th>')
  h.push('<th class="col-total-col"' + rowspan + '>Итого</th>')

  displayLevels[0].forEach(function (c) {
    h.push(
      '<th' +
        (c.span > 1 ? ' colspan="' + c.span + '"' : '') +
        (depth > 1 ? ' style="text-align:center"' : '') +
        (c.isSubtotal ? ' class="col-subtotal-hd"' : '') +
        '>' +
        esc(c.label) +
        '</th>',
    )
  })
  h.push('</tr>')

  for (var d = 1; d < depth; d++) {
    h.push('<tr>')
    displayLevels[d].forEach(function (c) {
      h.push(
        '<th' +
          (c.span > 1 ? ' colspan="' + c.span + '"' : '') +
          ' style="text-align:center"' +
          (c.isSubtotal ? ' class="col-subtotal-hd"' : '') +
          '>' +
          esc(c.label) +
          '</th>',
      )
    })
    h.push('</tr>')
  }
  h.push('</thead>')

  var grandTotals = displayCells.map(function () { return 0 })
  var grandSum = 0
  var bodyH = []

  ;(roads || []).forEach(function (road, ri) {
    var roadVal = road[groupCols[0].key] || ''
    var stations = road.stations || []
    var hasChildren = nGroup > 1 && stations.length > 0

    bodyH.push(
      '<tr class="row-road-parent" data-road-id="' + ri + '" data-node-id="' + ri + '">',
    )
    bodyH.push(
      '<td class="col-meta col-meta--l0">' +
        (hasChildren ? '<span class="toggle-icon">▼</span>' : '') +
        esc(roadVal) +
        '</td>',
    )
    bodyH.push(totalLink(road.grand_total || 0, ctx, roadVal, ''))
    displayCells.forEach(function (dc, di) {
      var v = getDisplayVal(dc, road.total || [])
      grandTotals[di] += v || 0
      bodyH.push(renderDisplayCell(dc, v, ctx, roadVal, '', null))
    })
    bodyH.push('</tr>')

    grandSum += road.grand_total || 0

    if (hasChildren) {
      var buildRows = function (level, items, parentNodeId, ancestorFilters) {
        var out = []
        var isLeaf = level === nGroup - 1
        var levelKey = groupCols[level].key

        if (isLeaf) {
          items.forEach(function (st) {
            var stVal = st[levelKey] || ''
            var rowSum = (st.v || []).reduce(function (a, b) { return a + b }, 0)

            // При 3+ уровнях ancestorFilters содержит промежуточные ключи (напр. dest_road).
            // Передаём их через data-extra, чтобы openDetail добавил их в URL и бэкенд
            // применил в WHERE. gc[0] и gc[last] попадают через road/station как обычно.
            var bcVals = Object.keys(ancestorFilters)
              .map(function (k) { return ancestorFilters[k] })
              .concat([stVal])
            var leafExtra = Object.assign({}, ancestorFilters, {
              _bcpath: JSON.stringify(bcVals),
            })

            out.push(
              '<tr class="row-data row-child" data-parent-id="' + esc(parentNodeId) + '">',
            )
            out.push('<td class="col-meta col-meta--l' + level + '">' + esc(stVal) + '</td>')
            out.push(totalLink(rowSum, ctx, roadVal, stVal, leafExtra))
            displayCells.forEach(function (dc) {
              out.push(renderDisplayCell(dc, getDisplayVal(dc, st.v || []), ctx, roadVal, stVal, leafExtra))
            })
            out.push('</tr>')
          })
        } else {
          var groups = {}, order = []
          items.forEach(function (st) {
            var val = st[levelKey] || ''
            if (!groups[val]) { groups[val] = []; order.push(val) }
            groups[val].push(st)
          })

          order.forEach(function (groupVal, gi) {
            var nodeId = parentNodeId + ':' + gi
            var gItems = groups[groupVal]
            var subTotal = flatCells.map(function () { return 0 })
            var subSum = 0

            gItems.forEach(function (st) {
              ;(st.v || []).forEach(function (v, i) { subTotal[i] += v || 0 })
              subSum += (st.v || []).reduce(function (a, b) { return a + b }, 0)
            })

            var curFilters = Object.assign({}, ancestorFilters)
            curFilters[levelKey] = groupVal
            var bcVals = Object.keys(curFilters).map(function (k) { return curFilters[k] })
            var curFiltersWithPath = Object.assign({}, curFilters, {
              _bcpath: JSON.stringify(bcVals),
            })

            out.push(
              '<tr class="row-data row-child row-sub-parent" data-parent-id="' +
                esc(parentNodeId) + '" data-node-id="' + esc(nodeId) + '">',
            )
            out.push(
              '<td class="col-meta col-meta--l' + level + '">' +
                '<span class="toggle-icon">▼</span>' + esc(groupVal) + '</td>',
            )
            out.push(totalLink(subSum, ctx, '', '', curFiltersWithPath))
            displayCells.forEach(function (dc) {
              out.push(renderDisplayCell(dc, getDisplayVal(dc, subTotal), ctx, '', '', curFiltersWithPath))
            })
            out.push('</tr>')

            out.push(buildRows(level + 1, gItems, nodeId, curFilters))
          })
        }
        return out.join('')
      }

      var rootFilters = {}
      rootFilters[groupCols[0].key] = roadVal
      bodyH.push(buildRows(1, stations, '' + ri, rootFilters))
    }
  })

  // Строка «Общий итог» — первая в tbody
  var totalH = [
    '<tr class="row-total row-grand"><td class="col-meta col-meta--l0">Общий итог</td>',
  ]

  if (grandSum && ctx) {
    totalH.push(
      '<td class="col-total-col cell-link" data-ctx="' +
        esc(ctx) +
        '" data-road="" data-station="" data-col="">' +
        grandSum.toLocaleString('ru-RU') +
        '</td>',
    )
  } else {
    totalH.push(
      '<td class="col-total-col">' + grandSum.toLocaleString('ru-RU') + '</td>',
    )
  }

  displayCells.forEach(function (dc, di) {
    var v = grandTotals[di]
    if (dc.isSubtotal) {
      totalH.push(subtotalCell(v))
    } else if (v && ctx) {
      totalH.push(
        '<td class="cell-link" data-ctx="' +
          esc(ctx) +
          '" data-road="" data-station="" data-col="' +
          esc(dc.col) +
          '"' +
          subAttrs(dc.subs) +
          '>' +
          v +
          '</td>',
      )
    } else {
      totalH.push('<td>' + (v || '') + '</td>')
    }
  })

  totalH.push('</tr>')

  // Итоговый единый рендеринг в DOM
  $(selector).html(h.join('') + '<tbody>' + totalH.join('') + bodyH.join('') + '</tbody>')
}

// CSV-экспорт виртуальной таблицы (детализация) — берёт данные из _vtInline
function saveCSVfromVT(tableId, filename) {
  var vt = _vtInline[tableId]
  if (!vt || !vt.all.length) return
  var cols = vt.cols
  var rows = [
    cols
      .map(function (c) {
        return '"' + c.label + '"'
      })
      .join(';'),
  ]
  vt.all.forEach(function (row) {
    var cells = cols.map(function (c) {
      var v = c.fmt
        ? c.fmt(row[c.key])
        : row[c.key] == null
          ? ''
          : String(row[c.key])
      return '"' + String(v).replace(/"/g, '""') + '"'
    })
    rows.push(cells.join(';'))
  })
  var csv = '﻿' + rows.join('\n')
  var a = document.createElement('a')
  a.href = URL.createObjectURL(
    new Blob([csv], { type: 'text/csv;charset=utf-8' }),
  )
  a.download =
    (filename || 'данные') +
    '_' +
    new Date().toISOString().slice(0, 10) +
    '.csv'
  a.click()
  URL.revokeObjectURL(a.href)
}

// CSV-экспорт таблицы по id и имени файла
function saveCSV(tableId, filename) {
  var table = document.getElementById(tableId)
  if (!table) return
  var rows = []
  table.querySelectorAll('tr').forEach(function (tr) {
    if (tr.offsetParent === null || getComputedStyle(tr).display === 'none')
      return
    var cells = []
    tr.querySelectorAll('th, td').forEach(function (cell) {
      var clone = cell.cloneNode(true)
      clone.querySelectorAll('.toggle-icon').forEach(function (el) {
        el.remove()
      })
      var val = clone.textContent
        .trim()
        .replace(/\r?\n|\r/g, ' ')
        .replace(/"/g, '""')
      cells.push('"' + val + '"')
    })
    rows.push(cells.join(';'))
  })
  var csv = '\uFEFF' + rows.join('\n')
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
/*
  kpiCard — рендерит одну KPI-карточку.
  Атрибуты item:
    label   — подпись
    value   — число
    accent  — синий фон
    detail  — объект для перехода в детализацию
    sub     — мелкий текст под значением
    variant — 'pill': label сверху, value + бейдж в строку (для дашборда)
    trend   — { pct: '-8.7%', dir: 'up'|'down'|'neutral' } — бейдж с трендом
*/
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
  var valStr =
    typeof val === 'number' ? val.toLocaleString('ru-RU') : esc(String(val))

  if (item.variant === 'pill' || item.trend) {
    var badgeHtml = ''
    if (item.trend) {
      var t = item.trend
      var dir = t.dir || 'neutral'
      var arrow = dir === 'up' ? '▲ ' : dir === 'down' ? '▼ ' : ''
      var bc =
        'kpi-badge' +
        (dir === 'up'
          ? ' kpi-badge--up'
          : dir === 'down'
            ? ' kpi-badge--down'
            : '')
      badgeHtml = '<span class="' + bc + '">' + arrow + esc(t.pct) + '</span>'
    }
    return (
      '<div class="' +
      cls +
      '"' +
      detAttr +
      '>' +
      '<div class="kpi-label">' +
      esc(item.label) +
      '</div>' +
      '<div class="kpi-value-row"><span class="kpi-value">' +
      valStr +
      '</span>' +
      badgeHtml +
      '</div>' +
      (item.sub ? '<div class="kpi-delta">' + esc(item.sub) + '</div>' : '') +
      '</div>'
    )
  }

  return (
    '<div class="' +
    cls +
    '"' +
    detAttr +
    '>' +
    '<div class="kpi-value">' +
    valStr +
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

// Открыть URL в новой вкладке через <a>, а не window.open:
// jQuery-делегированные обработчики блокируются попап-блокировщиком при window.open,
// тогда как клик по якорю всегда проходит как пользовательский жест.
function navNewTab(url) {
  var a = document.createElement('a')
  a.href = url
  a.target = '_blank'
  a.rel = 'noopener noreferrer'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

// Drill-down: открыть страницу детализации в новой вкладке.
// road/station → заголовок страницы + реальные поля groupCols[0]/groupCols[last].
// col/subs → маппятся через colDims из WAGON_TABS (synthetic=true — не передаётся).
// extra — доп. фильтры из getParams(), не перетирают явно заданные.
function openDetail(ctx, road, station, col, groupBy, subs, extra) {
  var p = new URLSearchParams()
  p.set('ctx', ctx)
  if (groupBy) p.set('group_by', groupBy)
  if (road) p.set('road', road)
  if (station) p.set('station', station)

  var tabCfg = WAGON_TABS[ctx]
  if (tabCfg) {
    var gc = tabCfg.groupCols || []
    if (road && gc[0]) p.set(gc[0].key, road)
    if (station && gc.length > 1) p.set(gc[gc.length - 1].key, station)

    var cf = tabCfg.colDims || []
    var hdrVals = [col].concat(subs || [])
    var vi = 0
    cf.forEach(function (f) {
      var v = hdrVals[vi++]
      if (
        !f.synthetic &&
        f.paramName &&
        v !== undefined &&
        v !== null &&
        v !== ''
      )
        p.set(f.paramName, v)
    })
  }

  Object.keys(extra || {}).forEach(function (k) {
    if (
      !p.has(k) &&
      extra[k] !== undefined &&
      extra[k] !== null &&
      extra[k] !== ''
    )
      p.set(k, extra[k])
  })
  navNewTab(BASE + '/detail?' + p.toString())
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
    navNewTab(d.url)
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
  initTab(WAGON_TABS.dislocation)

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
