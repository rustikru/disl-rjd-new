'use strict'

var BASE = window.APP_BASE || ''
var ALLOWED_PAGES = window.APP_ALLOWED_PAGES || []
var DISABLED_PAGES = window.APP_DISABLED_PAGES || []
var CAN_OPEN_DETAIL = ALLOWED_PAGES.indexOf('wagon-detail') !== -1

// Проверка доступа к вкладке
function canSeeTab(tab) {
  if (!tab.page) return true
  return ALLOWED_PAGES.indexOf(tab.page) !== -1
}

// Навигация строится
var TAB_GROUPS = (window.APP_NAV_CONFIG || []).map(function (group) {
  return {
    label: group.group,
    tabs: group.items.map(function (item) {
      return {
        id: item.id,
        label: item.label,
        page: item.page || null,
        url: item.url ? BASE + item.url : null,
        target: item.target || null,
      }
    }),
  }
})

// Сайдбар
function initSidebar() {
  var sidebar = document.getElementById('sidebar')
  TAB_GROUPS.forEach(function (group) {
    // Отфильтровываем вкладки, к которым нет доступа
    var visibleTabs = group.tabs.filter(canSeeTab)
    // Если ни одной вкладки не осталось — группу не показываем
    if (visibleTabs.length === 0) return

    var groupEl = document.createElement('div')
    groupEl.className = 'nav-group'

    var labelEl = document.createElement('span')
    labelEl.className = 'nav-group-label'
    labelEl.textContent = group.label
    groupEl.appendChild(labelEl)

    visibleTabs.forEach(function (tab) {
      var el
      if (tab.url) {
        el = document.createElement('a')
        el.href = tab.url
        if (tab.target) el.target = tab.target
        el.rel = 'noopener noreferrer'
      } else {
        el = document.createElement('button')
        el.addEventListener('click', function () {
          switchTab(tab.id)
        })
      }
      el.className = 'nav-item'
      el.textContent = tab.label
      el.dataset.tab = tab.id
      groupEl.appendChild(el)
    })

    sidebar.appendChild(groupEl)
  })
}

// Переключение вкладок
function switchTab(tabId) {
  var tabXhr = null
  document.querySelectorAll('.nav-item').forEach(function (btn) {
    btn.classList.toggle('active', btn.dataset.tab === tabId)
  })
  document.querySelectorAll('.tab-panel').forEach(function (panel) {
    panel.classList.toggle('active', panel.id === 'panel-' + tabId)
  })
  history.replaceState(null, '', '#' + tabId)
  Object.keys(WAGON_TABS).forEach(function (k) {
    var cfg = WAGON_TABS[k]
    if (tabId === k && !window[cfg.loadedKey]) {
      tabXhr = initTab(cfg)
    }
  })
  var requests = tabXhr ? [tabXhr] : []
  requests = requests.concat(loadKpiForTab(tabId))
  return requests.length ? $.when.apply($, requests) : null
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

var KPI_LOADED = {}

// Загрузка KPI выбранных блоков карточек.
function loadKPI(keys, force) {
  return (keys || []).filter(function (key) {
    return KPI_BOARDS[key] && (force || !KPI_LOADED[key])
  }).map(function (key) {
    var board = KPI_BOARDS[key]
    KPI_LOADED[key] = true
    return $.getJSON(board.dataUrl, board.params ? board.params() : {}).done(
      function (data) {
        //console.log('Ключ:', data)
        if (data.updated_at) {
          showDislocationDate(data.updated_at)
          $('#headerDate').text(data.updated_at)
        }
        showKpi(data, board.containerId)
      },
    ).fail(function () {
      delete KPI_LOADED[key]
    })
  })
}

function loadKpiForTab(tabId) {
  if (ALLOWED_PAGES.indexOf('dashboard') === -1) return []
  var kpiTypes = window.APP_KPI_TYPES || {}
  var type = Object.keys(kpiTypes).find(function (key) {
    return kpiTypes[key].tab === tabId
  })
  var board = type && kpiTypes[type].page
  return board ? loadKPI([board]) : []
}

// Дата в API приходит в формате DD.MM.YYYY HH:MM.
// Если она старше заданного срока, рядом показываем заметное предупреждение.
function showDislocationDate(value) {
  var container = document.getElementById('brandDateSub')
  if (!container) return

  container.replaceChildren()
  container.append(document.createTextNode('Дислокация РЖД на ' + value))

  var match = String(value).match(
    /^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})$/,
  )
  if (!match) return

  var reportTime = new Date(
    Number(match[3]),
    Number(match[2]) - 1,
    Number(match[1]),
    Number(match[4]),
    Number(match[5]),
  ).getTime()
  var staleHours = Math.max(1, Number(window.DISLOCATION_STALE_HOURS) || 8)
  if (Date.now() - reportTime <= staleHours * 60 * 60 * 1000) return

  var warning = document.createElement('span')
  warning.className = 'brand-date-warning'
  warning.textContent = 'НЕАКТУАЛЬНАЯ ДИСЛОКАЦИЯ'
  container.append(document.createTextNode(' · '))
  container.append(warning)
}

// KPI карточки
function showKpi(data, containerId) {
  // 1. Если containerId не передан, берем дефолтный от dashboard
  var targetContainer = containerId || KPI_BOARDS.dashboard.containerId

  // в объекте KPI_BOARDS  ищем конфигурацию
  var currentBoard = Object.values(KPI_BOARDS).find(function (board) {
    return board.containerId === targetContainer
  })

  // Если вдруг не нашли
  if (!currentBoard) {
    return
  }

  var cardsHtml = currentBoard.cards(data).map(kpiCard).join('')

  $('#' + targetContainer).html(cardsHtml).trigger('kpi:rendered')
}

// Конфиг вкладок

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

var WAGON_TABS = {}

function showTechnicalStateRows(data, tabSettings) {
  showTable(
    $('#' + tabSettings.detTableId),
    data.rows || [],
    DETAIL_CONTEXTS[tabSettings.ctx].cols,
  )
}

function claimsKpiCards(data) {
  var totals = data.totals || {}
  return [
    {
      label: 'Вагонов в расчёте',
      value: data.total || 0,
      variant: 'pill',
      detail: { ctx: 'claims' },
    },
    {
      label: 'Со штрафом',
      value: totals.penalty_count || 0,
      variant: 'pill',
      detail: { ctx: 'claims', params: { claim_filter: 'penalty' } },
    },
    {
      label: 'Расчётная сумма',
      value:
        Number(totals.amount || 0).toLocaleString('ru-RU', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }) + ' ₽',
      variant: 'pill',
      detail: { ctx: 'claims', params: { claim_filter: 'penalty' } },
    },
  ]
}

function showClaimsSummary(data, tabSettings) {
  var columns = tabSettings.summaryColumns || []
  var rows = (data.rows || []).slice()
  if (rows.length) rows.push(data.totals)
  _vtInline[tabSettings.sumTableId] = {
    all: rows,
    filtered: rows,
    cols: columns,
  }
  var html =
    '<thead><tr>' +
    columns
      .map(function (column, columnIndex) {
        return (
          '<th' +
          (columnIndex ? ' style="text-align:right"' : '') +
          '>' +
          esc(column.label) +
          '</th>'
        )
      })
      .join('') +
    '</tr></thead><tbody>'
  rows.forEach(function (row, rowIndex) {
    var total = rowIndex === rows.length - 1
    var reportFilters = Object.assign(
      {},
      tabSettings.getParams(),
      total ? {} : { wagon_type: row.wagon_type_code },
    )
    html += '<tr class="' + (total ? 'row-total' : 'row-data') + '">'
    columns.forEach(function (column, columnIndex) {
      var value = row[column.key]
      var text = columnIndex
        ? Number(value || 0).toLocaleString(
            'ru-RU',
            column.key === 'amount'
              ? { minimumFractionDigits: 2, maximumFractionDigits: 2 }
              : {},
          )
        : value
      var detailFilters = Object.assign({}, reportFilters)
      if (column.key === 'penalty_count' || column.key === 'amount')
        detailFilters.claim_filter = 'penalty'
      if (column.key === 'payable_days') detailFilters.claim_filter = 'payable'
      var content = esc(String(text))
      if (CAN_OPEN_DETAIL && (!columnIndex || Number(value) > 0)) {
        var query = new URLSearchParams({ ctx: 'claims' })
        Object.keys(detailFilters).forEach(function (filterKey) {
          if (
            detailFilters[filterKey] !== undefined &&
            detailFilters[filterKey] !== null &&
            detailFilters[filterKey] !== ''
          ) {
            query.set(filterKey, detailFilters[filterKey])
          }
        })
        content =
          '<a href="' +
          esc(BASE + '/detail?' + query.toString()) +
          '" target="_blank" rel="noopener noreferrer" class="cell-link claims-detail-link">' +
          content +
          '</a>'
      }
      html +=
        '<td' +
        (!columnIndex
          ? ' class="col-meta"'
          : Number(value) === 0
            ? ' class="dash"'
            : '') +
        '>' +
        content +
        '</td>'
    })
    html += '</tr>'
  })
  if (!rows.length)
    html +=
      '<tr><td colspan="5" style="text-align:center;padding:32px">Нет данных по выбранным условиям</td></tr>'
  $('#' + tabSettings.sumTableId).html(html + '</tbody>')
  $('#' + tabSettings.sumSubId).text(
    'Вагонов: ' + Number(data.total || 0).toLocaleString('ru-RU'),
  )
}

var REPORT_PAGE_BEHAVIORS = {
  technical_state: { showList: showTechnicalStateRows },
  claims: { kpi: claimsKpiCards, draw: showClaimsSummary },
}

function reportFilterValues(reportDefinition) {
  var values = {}
  ;(reportDefinition.filters || []).forEach(function (filterDefinition) {
    var $field = $('#' + filterDefinition.field_id)
    if (!$field.length) return

    var value
    if (filterDefinition.multiple) {
      var $widget = $field.siblings('.filter-multiselect')
      var selected = $widget.length
        ? $widget.find('input[type="checkbox"]:checked').map(function () {
            return String(this.value || '').trim()
          }).get()
        : ($field.val() || [])

      selected = Array.from(new Set(selected.filter(function (item) {
        return item !== ''
      })))
      value = selected.length ? JSON.stringify(selected) : ''
    } else {
      value = String($field.val() || '').trim()
    }

    if (value !== '') values[filterDefinition.key] = value
  })
  return values
}

function updateMultiSelectLabel($field) {
  var $widget = $field.siblings('.filter-multiselect')
  var selected = $widget.find('input[type="checkbox"]:checked').map(function () {
    return String(this.value || '').trim()
  }).get()
  selected = Array.from(new Set(selected.filter(function (value) {
    return value !== ''
  })))

  var labels = selected.map(function (value) {
    var option = $field.find('option').filter(function () {
      return String(this.value || '').trim() === value
    }).first()
    return option.length ? option.text() : value
  })

  $widget.find('.filter-multiselect-button').text(
    labels.length ? (labels.length === 1 ? labels[0] : 'Выбрано: ' + labels.length) : '— Все —',
  )
}

function refreshMultiSelect($field) {
  var $widget = $field.siblings('.filter-multiselect')
  if (!$widget.length) {
    $widget = $('<div>').addClass('filter-multiselect')
    var $button = $('<button type="button">').addClass('filter-multiselect-button')
    var $options = $('<div>').addClass('filter-multiselect-options').prop('hidden', true)
    $button.on('click', function () {
      var open = $options.prop('hidden')
      $('.filter-multiselect-options').prop('hidden', true)
      $options.prop('hidden', !open)
      if (open) $options.find('.filter-multiselect-search').trigger('focus')
    })
    $widget.append($button, $options)
    $field.after($widget).hide()
  }

  var selected = Array.isArray($field.val()) ? $field.val() : []
  selected = Array.from(new Set(selected.map(function (value) {
    return String(value || '').trim()
  }).filter(function (value) {
    return value !== ''
  })))

  var $options = $widget.find('.filter-multiselect-options').empty()
  var $search = $('<input type="search" placeholder="Поиск...">').addClass('filter-multiselect-search')
  var $list = $('<div>').addClass('filter-multiselect-list')
  var renderedValues = new Set()

  $search.on('input', function () {
    var query = this.value.toLocaleLowerCase('ru-RU').trim()
    $list.children('label').each(function () {
      this.hidden = query !== '' && this.textContent.toLocaleLowerCase('ru-RU').indexOf(query) < 0
    })
  })
  $options.append($search, $list)

  $field.find('option').each(function () {
    var option = this
    var value = String(option.value || '').trim()
    // Пустое значение «Все» не является вариантом мультивыбора.
    if (!value || renderedValues.has(value)) return
    renderedValues.add(value)

    var $check = $('<input type="checkbox">')
      .val(value)
      .prop('checked', selected.indexOf(value) >= 0)

    $check.on('change', function () {
      var checked = new Set($list.find('input[type="checkbox"]:checked').map(function () {
        return String(this.value || '').trim()
      }).get())

      $field.find('option').each(function () {
        var optionValue = String(this.value || '').trim()
        this.selected = optionValue !== '' && checked.has(optionValue)
      })
      $field.trigger('change')
      updateMultiSelectLabel($field)
    })

    $list.append($('<label>').append($check, $('<span>').text(option.text)))
  })

  updateMultiSelectLabel($field)
}

$(document).on('click', function (event) {
  if (!$(event.target).closest('.filter-multiselect').length) {
    $('.filter-multiselect-options').prop('hidden', true)
  }
})

function fillReportFilterOptions(reportDefinition, optionLists) {
  ;(reportDefinition.filters || []).forEach(function (filterDefinition) {
    if (
      filterDefinition.value_type !== 'select' ||
      !filterDefinition.options_key
    )
      return
    var options = optionLists[filterDefinition.options_key] || []
    var $field = $('#' + filterDefinition.field_id)
    var selectedValue = $field.val()
    var renderedValues = new Set()

    if (filterDefinition.multiple) {
      $field.empty()
      selectedValue = Array.isArray(selectedValue) ? selectedValue : []
    } else {
      $field.find('option.report-filter-option').remove()
    }

    options.forEach(function (option) {
      var optionValue
      var optionLabel
      if (option && typeof option === 'object') {
        optionValue =
          option.value || option.station_code || option.code || option.id || ''
        optionLabel =
          option.label || option.station_name || option.name || optionValue
      } else {
        optionValue = option
        optionLabel = option
      }

      optionValue = String(optionValue || '').trim()
      if (!optionValue || renderedValues.has(optionValue)) return
      renderedValues.add(optionValue)
      $field.append(
        $('<option>')
          .addClass('report-filter-option')
          .val(optionValue)
          .text(optionLabel),
      )
    })

    if (filterDefinition.multiple) {
      selectedValue = Array.from(new Set(selectedValue.map(function (value) {
        return String(value || '').trim()
      }).filter(function (value) {
        return value !== '' && renderedValues.has(value)
      })))
      $field.val(selectedValue)
      refreshMultiSelect($field)
    } else {
      $field.val(selectedValue || '')
    }
  })
}

function clearReportFilterValues(reportDefinition) {
  ;(reportDefinition.filters || []).forEach(function (filterDefinition) {
    var $field = $('#' + filterDefinition.field_id)
    $field.val(filterDefinition.multiple ? [] : '')
    if (filterDefinition.multiple) refreshMultiSelect($field)
  })
}

function reportInitialFilterValue(initialValue) {
  if (!initialValue) return ''
  var date = new Date()
  var year = String(date.getFullYear())
  var month = String(date.getMonth() + 1).padStart(2, '0')
  var day =
    initialValue === 'month_start'
      ? '01'
      : String(date.getDate()).padStart(2, '0')
  return year + '-' + month + '-' + day
}

function buildReportFilterFields(reportCode, reportDefinition) {
  var $filterFields = $('#panel-' + reportCode + ' .filters-inner').first()
  if (!$filterFields.length) return
  $filterFields.find('.filter-item').remove()
  $filterFields.find('.filter-range').remove()
  var $filterActions = $filterFields.find('.filter-actions').first()
  var $dateRange = null

  ;(reportDefinition.filters || []).forEach(function (filterDefinition) {
    var $filterItem = $('<div>').addClass('filter-item')
    if (filterDefinition.multiple) $filterItem.addClass('filter-item--multi')
    $filterItem.attr('data-filter', filterDefinition.key)
    var $label = $('<label>')
      .addClass('filter-label')
      .attr('for', filterDefinition.field_id)
      .text(filterDefinition.label)
    var $field

    if (filterDefinition.value_type === 'select') {
      $field = $('<select>')
      if (filterDefinition.multiple) {
        $field.attr('multiple', 'multiple')
      } else {
        $field.append($('<option>').val('').text('— Все —'))
      }
      ;(filterDefinition.choices || []).forEach(function (choice) {
        $field.append($('<option>').val(choice.value).text(choice.label))
      })
    } else {
      $field = $('<input>').attr(
        'type',
        filterDefinition.value_type === 'date' ? 'date' : 'text',
      )
      if (filterDefinition.key === 'wagon_no') {
        $field.attr('placeholder', 'Вагон1; Вагон2; …')
        $filterItem.addClass('filter-item--wagon')
      }
    }

    $field
      .addClass('filter-input')
      .attr('id', filterDefinition.field_id)
      .val(reportInitialFilterValue(filterDefinition.initial_value))
    $filterItem.append($label, $field)
    if (filterDefinition.multiple) refreshMultiSelect($field)
    if (
      reportCode === 'analysis-period' &&
      ['date_from', 'date_to'].indexOf(filterDefinition.key) >= 0
    ) {
      if (!$dateRange) {
        $dateRange = $('<div>').addClass('filter-range')
        if ($filterActions.length) $dateRange.insertBefore($filterActions)
        else $filterFields.append($dateRange)
      }
      $dateRange.append($filterItem)
    } else if ($filterActions.length) $filterItem.insertBefore($filterActions)
    else $filterFields.append($filterItem)
  })
}

function connectReportDefinitions() {
  var reportDefinitions = window.APP_REPORT_DEFINITIONS || {}
  var pagePropertyNames = {
    summary_file: 'csvFilename',
    detail_file: 'csvDetFilename',
    total_text: 'totalText',
    summary_table_id: 'sumTableId',
    summary_subtitle_id: 'sumSubId',
    summary_subtitle: 'sumSubLabel',
    detail_table_id: 'detTableId',
    detail_subtitle_id: 'detSubId',
    detail_panel_id: 'detPanelId',
    summary_loaded_key: 'loadedKey',
    detail_loaded_key: 'loadedDetKey',
    apply_button_id: 'applyBtnId',
    reset_button_id: 'resetBtnId',
    total_column_dimensions: 'totalColDims',
    metrics_id: 'metricsId',
    summary_columns: 'summaryColumns',
    pinned_row_label: 'pinnedRowLabel',
    pinned_station_key: 'pinnedStationKey',
    first_road_key: 'firstRoadKey',
  }
  Object.keys(reportDefinitions).forEach(function (reportCode) {
    var reportDefinition = reportDefinitions[reportCode]
    var pageSettings = Object.assign({}, reportDefinition.page || {})
    var organizationSettings =
      (pageSettings.organization_overrides || {})[
        window.APP_ORGANIZATION_CODE
      ] || {}
    Object.assign(pageSettings, organizationSettings)
    var tabSettings = { ctx: reportCode }

    Object.keys(pagePropertyNames).forEach(function (settingName) {
      if (pageSettings[settingName] !== undefined) {
        tabSettings[pagePropertyNames[settingName]] = pageSettings[settingName]
      }
    })

    var pageBehavior = REPORT_PAGE_BEHAVIORS[pageSettings.page_behavior]
    if (pageBehavior) Object.assign(tabSettings, pageBehavior)
    WAGON_TABS[reportCode] = tabSettings

    buildReportFilterFields(reportCode, reportDefinition)
    tabSettings.summaryUrl = reportDefinition.summary_endpoint
      ? BASE + reportDefinition.summary_endpoint
      : null
    tabSettings.detailUrl = reportDefinition.detail_endpoint
      ? BASE + reportDefinition.detail_endpoint
      : null
    tabSettings.filtersUrl = reportDefinition.filters_endpoint
      ? BASE + reportDefinition.filters_endpoint
      : null
    tabSettings.groupCols = reportDefinition.row_dimensions || []
    tabSettings.colDims = (reportDefinition.column_dimensions || []).map(
      function (columnDimension) {
        return {
          key: columnDimension.key,
          paramName: columnDimension.parameter,
        }
      },
    )
    tabSettings.getParams = function () {
      return reportFilterValues(reportDefinition)
    }
    tabSettings.fillFilters = function (optionLists) {
      fillReportFilterOptions(reportDefinition, optionLists)
    }
    tabSettings.resetFilters = function () {
      clearReportFilterValues(reportDefinition)
    }
    tabSettings.validate = function () {
      var missingFilter = (reportDefinition.filters || []).find(
        function (filterDefinition) {
          return (
            filterDefinition.required &&
            !String($('#' + filterDefinition.field_id).val() || '').trim()
          )
        },
      )
      return missingFilter ? 'Укажите поле «' + missingFilter.label + '»' : null
    }
  })
}

connectReportDefinitions()

function makeTrend(pct, dir) {
  return pct ? { pct: pct, dir: dir || 'neutral' } : null
}
// для сводных карточек с разбивкой по groupBy (дорогам и т.д)
function metricsCards(data, mainLabel, ctx, groupBy) {
  return [
    {
      label: mainLabel,
      value: data.total,
      accent: true,
      detail: ctx ? { ctx: ctx } : null,
    },
  ].concat(
    (data.metrics || []).map(function (m) {
      return {
        label: m.label,
        value: m.total,
        detail: ctx ? { ctx: ctx, road: m.label, groupBy: groupBy } : null,
      }
    }),
  )
}
function kpiCards(data) {
  var valuesArray =
    (data.sections && data.sections[0] && data.sections[0].values) || []

  return valuesArray.map(function (item) {
    var detail =
      item.is_error === 'Y'
        ? null
        : item.detail ||
          (item.id ? { ctx: 'dislocation', params: { kpi_id: item.id } } : null)
    var kpiTypes = window.APP_KPI_TYPES || {}
    var sourcePage = kpiTypes[item.type] && kpiTypes[item.type].page

    // Контекст страницы задаём по фактическому типу KPI из ответа API.
    if (detail && detail.ctx === 'dislocation' && sourcePage) {
      detail = Object.assign({}, detail, {
        params: Object.assign({}, detail.params || {}, {
          source_page: sourcePage,
        }),
      })
    }

    return {
      id: item.id,
      type: item.type,
      label: item.label,
      value: item.is_error === 'Y' ? '—' : item.value,
      sub: item.is_error === 'Y' ? item.value : null,
      isError: item.is_error === 'Y',
      isUser: item.is_user === 'Y',
      isGlobal: item.is_global === 'Y',
      canEdit: !!window.APP_USER_KPI_MANAGEMENT_ENABLED && item.can_edit === 'Y',
      canHide: !!window.APP_USER_KPI_MANAGEMENT_ENABLED && item.can_hide === 'Y',
      config: item.config || null,
      variant: 'pill',
      trend: makeTrend(item.change, item.trend),
      detail: detail,
    }
  })
}

// KPI-карточки для дашборда и отдельных блоков
var KPI_BOARDS = {}
Object.keys(window.APP_KPI_TYPES || {}).forEach(function (type) {
  var definition = window.APP_KPI_TYPES[type]
  KPI_BOARDS[definition.page] = {
    containerId: definition.container_id,
    dataUrl: BASE + '/api/kpi/summary',
    params: function () {
      var params = { kpi_type: type }
      return definition.page === 'loading' && WAGON_TABS.loading
        ? Object.assign(params, WAGON_TABS.loading.getParams())
        : params
    },
    cards: kpiCards,
  }
})

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
        '<button class="btn btn-ghost btn-sm btn-csv-tab">Выгрузить в Excel</button>',
      )
      $btn.on('click', function () {
        //saveCSV(cfg.sumTableId, cfg.csvFilename)
        saveExcelMatrix(cfg.sumTableId, cfg.csvFilename)
      })
      $acts.append($btn)
    }
  }
  loadFilters(cfg)
  var _sumXhr = loadSummary(cfg)
  if (!cfg.summaryUrl && cfg.detailUrl && !window[cfg.loadedDetKey]) {
    var initHint = cfg.validate ? cfg.validate() : null
    if (initHint) {
      $('#' + cfg.detTableId).html(
        '<div style="text-align:center;padding:60px;color:#E8392A;font-size:15px;font-weight:600">' +
          esc(initHint) +
          '</div>',
      )
    } else {
      window[cfg.loadedDetKey] = true
      loadDetail(cfg)
    }
  }
  return _sumXhr
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
  if (!cfg.summaryUrl) return $.when()
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
  return $.getJSON(cfg.summaryUrl, summaryParams)
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
      if (
        cfg.totalColDims &&
        cfg.totalColDims.length &&
        cfg.colDims &&
        cfg.colDims.length
      ) {
        var colDimKeys = cfg.colDims.map(function (c) {
          return c.key
        })
        cfg.totalColDims.forEach(function (key) {
          var idx = colDimKeys.indexOf(key)
          if (idx !== -1 && (subtotalDepth === null || idx < subtotalDepth))
            subtotalDepth = idx
        })
      }
      var pinnedStation = null
      if (cfg.pinnedStationKey && data.roads) {
        var stationField = (cfg.groupCols[cfg.groupCols.length - 1] || {}).key
        var pinKey = cfg.pinnedStationKey.toUpperCase()
        var pinVals = []
        var pinTotal = 0
        var pinName = ''
        data.roads.forEach(function (road) {
          var kept = []
          ;(road.stations || []).forEach(function (st) {
            if ((st[stationField] || '').toUpperCase().indexOf(pinKey) !== -1) {
              if (!pinName) pinName = st[stationField] || ''
              ;(st.v || []).forEach(function (val, i) {
                pinVals[i] = (pinVals[i] || 0) + (val || 0)
              })
              var stSum = (st.v || []).reduce(function (a, b) {
                return a + (b || 0)
              }, 0)
              pinTotal += stSum
              ;(st.v || []).forEach(function (val, i) {
                road.total[i] = (road.total[i] || 0) - (val || 0)
              })
              road.grand_total = (road.grand_total || 0) - stSum
            } else {
              kept.push(st)
            }
          })
          road.stations = kept
        })
        if (pinTotal > 0) {
          pinnedStation = { v: pinVals, grand_total: pinTotal, name: pinName }
          var totalBeforePin = data.total || 0
          data.total = totalBeforePin - pinTotal
          data._totalWithPin = totalBeforePin
          data.roads = data.roads.filter(function (road) {
            return (
              (road.stations && road.stations.length > 0) ||
              road.grand_total > 0
            )
          })
        }
      }

      if (cfg.firstRoadKey && data.roads && data.roads.length > 1) {
        var roadField = (cfg.groupCols[0] || {}).key
        var firstKey = cfg.firstRoadKey.toUpperCase()
        data.roads.sort(function (a, b) {
          var aFirst =
            (a[roadField] || '').toUpperCase().indexOf(firstKey) !== -1 ? 0 : 1
          var bFirst =
            (b[roadField] || '').toUpperCase().indexOf(firstKey) !== -1 ? 0 : 1
          return aFirst - bFirst
        })
      }

      _matrixData[cfg.sumTableId] = {
        col_groups: data.col_groups || null,
        cols: data.cols || [],
        group_cols: cfg.groupCols || [],
        roads: data.roads || [],
      }

      var cells = drawSummary(
        '#' + cfg.sumTableId,
        data.roads,
        data,
        cfg.ctx,
        cfg.groupCols,
        subtotalDepth,
        cfg.totalText,
      )

      if (pinnedStation && cells && cells.length) {
        var $grandRow = $table.find('tr.row-grand').first()
        if ($grandRow.length) {
          var hasSubtotals = cells[0] && cells[0].isSubtotal
          var linkAttrs =
            ' data-ctx="' +
            esc(cfg.ctx || '') +
            '" data-road="" data-station="' +
            esc(pinnedStation.name) +
            '" data-group-by="' +
            esc(
              (cfg.groupCols || [])
                .map(function (g) {
                  return g.key
                })
                .join(','),
            ) +
            '"'
          var pinnedCells = [
            '<td class="col-meta col-meta--l0">' +
              esc(cfg.pinnedRowLabel) +
              '</td>',
          ]
          if (!hasSubtotals) {
            pinnedCells.push(
              '<td class="col-total-col cell-link"' +
                linkAttrs +
                ' data-col="">' +
                pinnedStation.grand_total.toLocaleString('ru-RU') +
                '</td>',
            )
          }
          cells.forEach(function (dc) {
            var v
            if (dc.isSubtotal) {
              v = 0
              ;(dc.indices || []).forEach(function (i) {
                v += pinnedStation.v[i] || 0
              })
            } else {
              v = pinnedStation.v[dc.dataIdx] || 0
            }
            var disp = v ? v.toLocaleString('ru-RU') : ''
            var subAttrs = ''
            ;(dc.subs || []).forEach(function (sv, si) {
              if (sv)
                subAttrs +=
                  ' data-sub' + (si ? si + 1 : '') + '="' + esc(sv) + '"'
            })
            pinnedCells.push(
              dc.isSubtotal
                ? '<td class="col-subtotal cell-link"' +
                    linkAttrs +
                    ' data-col=""' +
                    subAttrs +
                    '>' +
                    disp +
                    '</td>'
                : '<td class="cell-link"' +
                    linkAttrs +
                    ' data-col="' +
                    esc(dc.col) +
                    '"' +
                    subAttrs +
                    '>' +
                    disp +
                    '</td>',
            )
          })
          $grandRow.before(
            $('<tr class="row-pinned">' + pinnedCells.join('') + '</tr>'),
          )
        }
      }

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
  $table.attr('data-layout-report', cfg.ctx)
  // showInline: false — поле скрыто из inline-детализации (но видно на странице detail.php)
  var cols = DETAIL_CONTEXTS[cfg.ctx]
    ? DETAIL_CONTEXTS[cfg.ctx].cols.filter(function (c) {
        return c.showInline !== false
      })
    : []
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
      if (Array.isArray(data.columns) && data.columns.length) cols = data.columns
      if (data.group_config && Array.isArray(data.rows)) {
        var groupCols = data.columns || []
        var groupHtml = '<table class="ui-table user-kpi-group-table"><thead><tr>' + groupCols.map(function (col) { return '<th>' + esc(col.label) + '</th>' }).join('') + '</tr></thead><tbody>'
        data.rows.forEach(function (row, index) {
          groupHtml += '<tr class="user-kpi-group-row" data-group-index="' + index + '">' + groupCols.map(function (col) { return '<td>' + esc(row[col.key] == null ? '' : row[col.key]) + '</td>' }).join('') + '</tr>'
          groupHtml += '<tr class="user-kpi-group-expanded" data-expanded-index="' + index + '" hidden><td colspan="' + groupCols.length + '"><div class="user-kpi-group-detail">Загрузка...</div></td></tr>'
        })
        if (!data.rows.length) groupHtml += '<tr><td colspan="' + Math.max(groupCols.length, 1) + '">' + esc(data.group_config.empty_label || 'Нет данных') + '</td></tr>'
        groupHtml += '</tbody></table>'
        $table.html(groupHtml)
        $table.off('click.userKpiGroup').on('click.userKpiGroup', '.user-kpi-group-row', function () {
          var index = Number($(this).attr('data-group-index'))
          var row = data.rows[index]
          var $expanded = $table.find('[data-expanded-index="' + index + '"]')
          if (!$expanded.prop('hidden')) { $expanded.prop('hidden', true); return }
          $expanded.prop('hidden', false)
          var $detailHost = $expanded.find('.user-kpi-group-detail')
          if (!$detailHost.attr('id')) $detailHost.attr('id', 'user-kpi-group-' + index + '-' + String(cfg.detTableId || 'detail').replace(/[^a-z0-9_-]/gi, ''))
          if ($detailHost.data('loaded')) return
          var groupValue = row.group_value
          var requestParams = Object.assign({}, listParams, { group_value: groupValue == null ? '' : groupValue, group_value_is_null: groupValue == null ? 'Y' : 'N' })
          $.getJSON(cfg.detailUrl, requestParams).done(function (detailData) {
            if (detailData.rows && detailData.rows.length) {
              showTable($detailHost, detailData.rows, detailData.columns || [])
            } else {
              $detailHost.text(data.group_config.empty_label || 'Нет данных')
            }
            $detailHost.data('loaded', true)
          }).fail(function (xhr) { $detailHost.text(ajaxErr(xhr)) })
        })
      } else if (cfg.showList) {
        cfg.showList(data, cfg)
      } else {
        showTable($table, data.rows, cols)
      }
      $sub.text('Строк: ' + (data.rows || []).length.toLocaleString('ru-RU'))
      if (cfg.csvDetFilename) {
        var $acts = $table.closest('.table-section').find('.table-acts')
        if ($acts.length && !$acts.find('.btn-csv-det').length) {
          var $btn = $(
            '<button class="btn btn-ghost btn-sm btn-csv-det">Выгрузить в Excel</button>',
          )
          $btn.on('click', function () {
            //saveCSVfromVT(cfg.detTableId, cfg.csvDetFilename)
            saveExcelfromVT(cfg.detTableId, cfg.csvDetFilename)
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
var _vtTableLayouts = {}
/* Матричные данные сводных таблиц (для Excel-экспорта) */
var _matrixData = {}

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
    MIN = 70,
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
function applyInlineTableLayout(columns, layout) {
  if (!layout || !Array.isArray(layout.columns)) return columns.slice()
  var byKey = {}
  columns.forEach(function (column) {
    delete column.width
    byKey[column.key] = column
  })
  var ordered = []
  layout.columns.forEach(function (item) {
    if (!byKey[item.key]) return
    var width = Number(item.width)
    if (width >= 70 && width <= 800) byKey[item.key].width = width
    else delete byKey[item.key].width
    ordered.push(byKey[item.key])
    delete byKey[item.key]
  })
  Object.keys(byKey).forEach(function (key) { ordered.push(byKey[key]) })
  return ordered
}

/* Рендер таблицы с виртуальным скроллом.*/
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
  var layoutReport = $container.attr('data-layout-report') || ''
  if ($container[0]._vtCleanup) $container[0]._vtCleanup()
  if (layoutReport && !Object.prototype.hasOwnProperty.call(_vtTableLayouts, layoutReport)) {
    _vtTableLayouts[layoutReport] = false
    var allRowsForLayout = rows || []
    $.getJSON(BASE + '/api/table-layout/' + encodeURIComponent(layoutReport))
      .done(function (layout) {
        _vtTableLayouts[layoutReport] = layout
        showTable($container, allRowsForLayout, applyInlineTableLayout(colDefs, layout))
      })
      .fail(function () {
        _vtTableLayouts[layoutReport] = { columns: [] }
        showTable($container, allRowsForLayout, colDefs)
      })
    return
  }
  if (layoutReport && _vtTableLayouts[layoutReport] && Array.isArray(_vtTableLayouts[layoutReport].columns)) {
    colDefs = applyInlineTableLayout(colDefs, _vtTableLayouts[layoutReport])
  }
  $container.addClass('vt-host')
  var ROW_H = 28 // высота строки, используется для расчёта виртуального скролла
  var BUFFER = 8 // кол-во строк в буфере до и после видимой области, для предотвращения мерцания при скролле
  var allData = rows || []
  var filterState = {}

  function filterDisplayValue(column, row) {
    var value = row[column.key]
    if (column.fmt) value = column.fmt(value)
    return value == null || value === '' ? '(Пустые)' : String(value)
  }

  _vtInline[id] = { all: allData, filtered: allData.slice(), cols: colDefs }

  var measured = vtMeasureCols(colDefs, allData)
  var availW = $container[0].offsetWidth || window.innerWidth - 260
  var savedTotal = 0, defaultTotal = 0
  colDefs.forEach(function (column, index) {
    var savedWidth = Number(column.width)
    if (savedWidth >= 70 && savedWidth <= 800) savedTotal += savedWidth
    else defaultTotal += measured[index]
  })
  var defaultScale = defaultTotal > 0 && availW - savedTotal > defaultTotal
    ? (availW - savedTotal) / defaultTotal : 1
  var widths = measured.map(function (width, index) {
    var savedWidth = Number(colDefs[index].width)
    if (savedWidth >= 70 && savedWidth <= 800) return savedWidth
    return Math.max(70, Math.min(800, Math.floor(width * defaultScale)))
  })
  var template = widths.map(function (width) { return width + 'px' }).join(' ')
  var totalW = widths.reduce(function (sum, width) { return sum + width }, 0)

  $container.html(
    '<div class="vt-viewport" id="ivp-' +
      id +
      '">' +
      '<div class="vt-content" style="width:' +
      totalW +
      'px">' +
      '<div class="vt-sticky">' +
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
      'px"></div></div>' +
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
      (c.pinned ? ' col-pinned' : '') +
      (c.meta ? ' col-meta' : '') +
      '">' +
      esc(c.label) +
      '<span class="vt-resize-handle" data-key="' + esc(c.key) + '" title="Изменить ширину столбца"></span></div>'
    fHtml +=
      '<div class="vt-fc' +
      (c.pinned ? ' col-pinned' : '') +
      '"><button type="button" class="vt-filter-button" data-k="' +
      c.key +
      '" title="Фильтр по столбцу">Все ▾</button></div>'
  })
  document.getElementById('ivh-' + id).innerHTML = hHtml
  document.getElementById('ivf-' + id).innerHTML = fHtml

  var head = document.getElementById('ivh-' + id)
  var sortState = { key: '', dir: 0 }
  head.addEventListener('click', function (event) {
    var cell = event.target.closest('.vt-th')
    if (!cell || event.target.closest('.sortable-ghost, .vt-resize-handle')) return
    var key = cell.dataset.key
    if (!key) return
    sortState.dir = sortState.key === key ? (sortState.dir + 1) % 3 : 1
    sortState.key = sortState.dir === 0 ? '' : key
    head.querySelectorAll('.vt-th').forEach(function (item) {
      item.classList.remove('vt-th--sort-asc', 'vt-th--sort-desc')
    })
    if (sortState.dir) {
      cell.classList.add(sortState.dir === 1 ? 'vt-th--sort-asc' : 'vt-th--sort-desc')
    }
    applyTableState()
  })
  head.querySelectorAll('.vt-th').forEach(function (cell, index) {
    cell.dataset.key = colDefs[index].key
  })
  var resizeMove = null
  var resizeEnd = null
  var resizeSaveTimer = null
  head.addEventListener('mousedown', function (event) {
    var handle = event.target.closest('.vt-resize-handle')
    if (!handle || event.button !== 0) return
    var key = handle.getAttribute('data-key')
    var index = colDefs.findIndex(function (column) { return column.key === key })
    if (index < 0) return
    event.preventDefault()
    event.stopPropagation()
    var startX = event.clientX
    var startWidth = widths[index]
    head.classList.add('vt-head--resizing')
    resizeMove = function (moveEvent) {
      widths[index] = Math.max(70, Math.min(800, Math.round(startWidth + moveEvent.clientX - startX)))
      template = widths.map(function (width) { return width + 'px' }).join(' ')
      totalW = widths.reduce(function (sum, width) { return sum + width }, 0)
      $container.find('.vt-head, .vt-filter, .vt-row').css({ gridTemplateColumns: template, width: totalW + 'px' })
      $container.find('.vt-content').css('width', totalW + 'px')
    }
    resizeEnd = function () {
      document.removeEventListener('mousemove', resizeMove)
      document.removeEventListener('mouseup', resizeEnd)
      head.classList.remove('vt-head--resizing')
      resizeMove = resizeEnd = null
      if (!layoutReport) return
      colDefs[index].width = widths[index]
      var widthData = {}
      colDefs.forEach(function (column, columnIndex) { widthData[column.key] = widths[columnIndex] })
      var cachedLayout = _vtTableLayouts[layoutReport] || { columns: [] }
      var cachedByKey = {}
      ;(cachedLayout.columns || []).forEach(function (item) { cachedByKey[item.key] = item })
      Object.keys(widthData).forEach(function (columnKey) {
        cachedByKey[columnKey] = Object.assign({}, cachedByKey[columnKey] || {}, { key: columnKey, width: widthData[columnKey] })
      })
      _vtTableLayouts[layoutReport] = { columns: Object.keys(cachedByKey).map(function (columnKey) { return cachedByKey[columnKey] }) }
      if (resizeSaveTimer) clearTimeout(resizeSaveTimer)
      resizeSaveTimer = setTimeout(function () {
        resizeSaveTimer = null
        $.ajax({
          url: BASE + '/api/table-layout/' + encodeURIComponent(layoutReport),
          method: 'POST',
          data: { csrf_token: window.APP_CSRF || '', widths: JSON.stringify(widthData) },
        }).done(function (layout) {
          _vtTableLayouts[layoutReport] = layout
        })
      }, 250)
    }
    document.addEventListener('mousemove', resizeMove)
    document.addEventListener('mouseup', resizeEnd)
  })

  head.addEventListener('dblclick', function (event) {
    var handle = event.target.closest('.vt-resize-handle')
    if (!handle) return
    event.preventDefault()
    event.stopPropagation()
    var key = handle.getAttribute('data-key')
    var index = colDefs.findIndex(function (column) { return column.key === key })
    if (index < 0) return
    widths[index] = measured[index]
    colDefs[index].width = measured[index]
    template = widths.map(function (width) { return width + 'px' }).join(' ')
    totalW = widths.reduce(function (sum, width) { return sum + width }, 0)
    $container.find('.vt-head, .vt-filter, .vt-row').css({ gridTemplateColumns: template, width: totalW + 'px' })
    $container.find('.vt-content').css('width', totalW + 'px')
    if (layoutReport) {
      if (resizeSaveTimer) {
        clearTimeout(resizeSaveTimer)
        resizeSaveTimer = null
      }
      var resetWidth = {}; resetWidth[key] = null
      var cachedLayout = _vtTableLayouts[layoutReport] || { columns: [] }
      ;(cachedLayout.columns || []).forEach(function (item) { if (item.key === key) item.width = null })
      $.ajax({
        url: BASE + '/api/table-layout/' + encodeURIComponent(layoutReport),
        method: 'POST',
        data: { csrf_token: window.APP_CSRF || '', widths: JSON.stringify(resetWidth) },
      }).done(function (layout) { _vtTableLayouts[layoutReport] = layout })
    }
  })

  if (typeof Sortable !== 'undefined' && !head._sortable) {
    head._sortable = Sortable.create(head, {
      animation: 150,
      draggable: '.vt-th:not(.col-pinned)',
      filter: '.col-pinned, .vt-resize-handle',
      preventOnFilter: false,
      ghostClass: 'vt-th--ghost',
      onStart: function () { head.classList.add('vt-head--dragging') },
      onEnd: function () {
        head.classList.remove('vt-head--dragging')
        var keys = Array.prototype.map.call(head.querySelectorAll('.vt-th'), function (cell) { return cell.dataset.key })
        var byKey = {}
        colDefs.forEach(function (column) { byKey[column.key] = column })
        var reordered = keys.map(function (key) { return byKey[key] }).filter(Boolean)
        if (layoutReport) {
          var oldLayout = _vtTableLayouts[layoutReport] || { columns: [] }
          var oldByKey = {}
          ;(oldLayout.columns || []).forEach(function (item) { oldByKey[item.key] = item })
          _vtTableLayouts[layoutReport] = {
            columns: keys.map(function (key) { return Object.assign({}, oldByKey[key] || {}, { key: key }) }),
          }
          $.ajax({
            url: BASE + '/api/table-layout/' + encodeURIComponent(layoutReport),
            method: 'POST',
            data: { csrf_token: window.APP_CSRF || '', columns: JSON.stringify(keys) },
          }).done(function (layout) { _vtTableLayouts[layoutReport] = layout })
        }
        setTimeout(function () { showTable($container, allData, reordered) }, 0)
      },
    })
  }

  function cellHtml(c, row) {
    var v = row[c.key]
    var display = v !== null && v !== undefined && v !== '' ? v : ''
    if (c.fmt) display = c.fmt(v)
    var cls =
      'vt-cell' +
      (c.pinned ? ' col-pinned' : '') +
      (c.meta ? ' col-meta' : '') +
      (c.right ? ' vt-right' : '')
    var style = ''
    if (c.danger) {
      var d = parseFloat(display) || 0
      if (d >= 7) style = ' style="color:#E8392A;font-weight:700"'
      else if (d >= 3) style = ' style="color:#E8A530;font-weight:600"'
    }
    var content = esc(String(display))
    if (
      (c.key === 'wagon_no' || c.key === 'car_number') &&
      /^\d{8}$/.test(String(v)) &&
      ALLOWED_PAGES.indexOf('wagon-card') !== -1
    ) {
      content =
        '<a class="wagon-link" href="' +
        BASE +
        '/wagon?wagon_no=' +
        encodeURIComponent(v) +
        '" style="color:var(--accent);text-decoration:none" title="Карточка вагона">' +
        content +
        '</a>'
    }
    return '<div class="' + cls + '"' + style + '>' + content + '</div>'
  }

  var vp = document.getElementById('ivp-' + id)
  var rowsEl = document.getElementById('ivr-' + id)
  var lastFirst = -1,
    lastLast = -1

  function updateFilteredCount() {
    var $subEl = $container.closest('.table-section').find('.table-sub')
    if ($subEl.length) {
      $subEl.text('Строк: ' + _vtInline[id].filtered.length.toLocaleString('ru-RU'))
    }
  }

  function getFilterValues(key, state) {
    var column = colDefs.find(function (item) { return item.key === key })
    if (!column) return []
    state = state || filterState
    return Array.from(new Set(allData.filter(function (row) {
      return Object.keys(state).every(function (activeKey) {
        if (activeKey === key) return true
        var activeColumn = colDefs.find(function (item) { return item.key === activeKey })
        return activeColumn && state[activeKey].has(filterDisplayValue(activeColumn, row))
      })
    }).map(function (row) {
      return filterDisplayValue(column, row)
    }))).sort(function (a, b) {
      return a.localeCompare(b, 'ru', { numeric: true, sensitivity: 'base' })
    })
  }

  function updateFilterButtons() {
    $container.find('.vt-filter-button').each(function () {
      var key = this.getAttribute('data-k')
      var selected = filterState[key]
      this.classList.toggle('is-active', !!selected)
      this.textContent = selected ? selected.size + ' выбрано ▾' : 'Все ▾'
    })
  }

  function normalizeColumnFilters(changedKey) {
    var changed = true
    while (changed) {
      changed = false
      var snapshot = {}
      Object.keys(filterState).forEach(function (key) { snapshot[key] = new Set(filterState[key]) })
      var nextState = {}
      Object.keys(snapshot).forEach(function (key) {
        if (key === changedKey) {
          nextState[key] = snapshot[key]
          return
        }
        var available = new Set(getFilterValues(key, snapshot))
        var selected = new Set(Array.from(snapshot[key]).filter(function (value) { return available.has(value) }))
        if (selected.size || snapshot[key].size === 0) nextState[key] = selected
        if (selected.size !== snapshot[key].size) changed = true
      })
      if (Object.keys(nextState).length !== Object.keys(filterState).length) changed = true
      filterState = nextState
    }
    updateFilterButtons()
  }

  function applyTableState(changedKey) {
    if (changedKey) normalizeColumnFilters(changedKey)
    var data = _vtInline[id]
    var activeKeys = Object.keys(filterState)
    data.filtered = activeKeys.length
      ? data.all.filter(function (row) {
          return activeKeys.every(function (key) {
            var column = colDefs.find(function (item) { return item.key === key })
            return column && filterState[key].has(filterDisplayValue(column, row))
          })
        })
      : data.all.slice()
    if (sortState.key) {
      data.filtered.sort(function (a, b) {
        var av = a[sortState.key] == null ? '' : a[sortState.key]
        var bv = b[sortState.key] == null ? '' : b[sortState.key]
        var an = Number(av), bn = Number(bv)
        var result = !isNaN(an) && !isNaN(bn)
          ? an - bn
          : String(av).localeCompare(String(bv), 'ru', { numeric: true, sensitivity: 'base' })
        return sortState.dir === 1 ? result : -result
      })
    }
    lastFirst = lastLast = -1
    render(true)
    updateFilteredCount()
  }

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
    rowsEl.style.paddingBottom = Math.max(0, (total - last) * ROW_H) + 'px'
    rowsEl.innerHTML = html
  }

  var filterPopup = null

  function closeFilterPopup() {
    if (filterPopup) filterPopup.remove()
    filterPopup = null
  }

  function openColumnFilter(button) {
    closeFilterPopup()
    var key = button.getAttribute('data-k')
    var column = colDefs.find(function (item) { return item.key === key })
    if (!column) return
    var values = getFilterValues(key)
    var draft = filterState[key]
      ? new Set(Array.from(filterState[key]).filter(function (value) { return values.indexOf(value) !== -1; }))
      : new Set(values)
    var popup = document.createElement('div')
    popup.className = 'vt-column-filter'
    popup.innerHTML = '<input class="vt-column-filter-search" type="search" placeholder="Поиск...">'
      + '<div class="vt-column-filter-tools"><button type="button" data-action="all">Выбрать все</button><button type="button" data-action="none">Снять все</button></div>'
      + '<div class="vt-column-filter-list"></div>'
      + '<div class="vt-column-filter-actions"><button type="button" class="btn btn-ghost btn-sm" data-action="cancel">Отмена</button><button type="button" class="btn btn-primary btn-sm" data-action="apply">Применить</button></div>'
    document.body.appendChild(popup)
    filterPopup = popup
    var search = popup.querySelector('.vt-column-filter-search')
    var list = popup.querySelector('.vt-column-filter-list')

    function renderOptions() {
      var query = search.value.toLocaleLowerCase('ru-RU').trim()
      list.innerHTML = ''
      values.forEach(function (value, index) {
        if (query && value.toLocaleLowerCase('ru-RU').indexOf(query) < 0) return
        var label = document.createElement('label')
        var check = document.createElement('input')
        var text = document.createElement('span')
        check.type = 'checkbox'
        check.checked = draft.has(value)
        check.dataset.index = String(index)
        check.addEventListener('change', function () {
          if (check.checked) draft.add(value)
          else draft.delete(value)
        })
        text.textContent = value
        label.append(check, text)
        list.appendChild(label)
      })
    }

    popup.addEventListener('click', function (event) {
      var action = event.target.getAttribute('data-action')
      if (action === 'all') {
        values.forEach(function (value) { draft.add(value) })
        renderOptions()
      } else if (action === 'none') {
        draft.clear()
        renderOptions()
      } else if (action === 'cancel') {
        closeFilterPopup()
      } else if (action === 'apply') {
        if (draft.size === values.length) delete filterState[key]
        else filterState[key] = new Set(draft)
        button.classList.toggle('is-active', !!filterState[key])
        button.textContent = filterState[key] ? draft.size + ' выбрано ▾' : 'Все ▾'
        closeFilterPopup()
        applyTableState(key)
      }
    })
    search.addEventListener('input', renderOptions)
    renderOptions()
    var rect = button.getBoundingClientRect()
    popup.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - popup.offsetWidth - 8)) + 'px'
    popup.style.top = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - popup.offsetHeight - 8)) + 'px'
    search.focus()
  }

  document.getElementById('ivf-' + id).addEventListener('click', function (event) {
    var button = event.target.closest('.vt-filter-button')
    if (button) openColumnFilter(button)
  })

  function filterDocumentClick(event) {
    if (filterPopup && !filterPopup.contains(event.target) && !event.target.closest('.vt-filter-button')) {
      closeFilterPopup()
    }
  }
  document.addEventListener('click', filterDocumentClick)

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
  $container[0]._vtCleanup = function () {
    if (resizeMove) document.removeEventListener('mousemove', resizeMove)
    if (resizeEnd) document.removeEventListener('mouseup', resizeEnd)
    if (head._sortable) head._sortable.destroy()
    closeFilterPopup()
    document.removeEventListener('click', filterDocumentClick)
    window.removeEventListener('resize', fitVpHeight)
    window.removeEventListener('scroll', fitVpHeight)
  }
}

/******** cols config ********/

/******** Сводная и KPI ********/
function drawSummary(
  selector,
  roads,
  data,
  ctx,
  groupCols,
  subtotalDepth,
  totalText,
) {
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
  var hasSubtotals =
    subtotalDepth !== null &&
    subtotalDepth !== undefined &&
    depth > subtotalDepth + 1

  if (hasSubtotals) {
    // Prepend an 'Итого' group at the left that sums across all top-level groups,
    // keeping the sub-column breakdown (e.g. гр./пор. per cargo state)
    var subColCount = levels[subtotalDepth][0].span
    var topGroupCount = levels[subtotalDepth].length
    var dlvl

    for (dlvl = 0; dlvl < depth; dlvl++) {
      displayLevels.push([])
    }

    // Level subtotalDepth: prepend 'Итого' group, then original groups
    displayLevels[subtotalDepth].push({
      label: 'Итого',
      span: subColCount,
      isSubtotal: true,
    })
    levels[subtotalDepth].forEach(function (g) {
      displayLevels[subtotalDepth].push(g)
    })

    // Level subtotalDepth+1: prepend sub-column labels from first group, then all original
    for (var j = 0; j < subColCount; j++) {
      displayLevels[subtotalDepth + 1].push({
        label: levels[subtotalDepth + 1][j].label,
        span: 1,
        isSubtotal: true,
      })
    }
    levels[subtotalDepth + 1].forEach(function (c) {
      displayLevels[subtotalDepth + 1].push(c)
    })

    // displayCells: subtotal entries (one per sub-column position), then regular cells
    for (var si = 0; si < subColCount; si++) {
      var idxs = []
      for (var gi = 0; gi < topGroupCount; gi++) {
        idxs.push(gi * subColCount + si)
      }
      var stSubs = flatCells[si].subs.slice()
      if (subtotalDepth > 0) stSubs[subtotalDepth - 1] = ''
      displayCells.push({
        isSubtotal: true,
        col: 'Итого',
        subs: stSubs,
        indices: idxs,
      })
    }
    flatCells.forEach(function (fc, fi) {
      displayCells.push({
        isSubtotal: false,
        col: fc.col,
        subs: fc.subs,
        dataIdx: fi,
      })
    })

    // Copy other levels unchanged
    for (dlvl = 0; dlvl < depth; dlvl++) {
      if (dlvl !== subtotalDepth && dlvl !== subtotalDepth + 1) {
        displayLevels[dlvl] = levels[dlvl].slice()
      }
    }
  } else {
    flatCells.forEach(function (fc, fi) {
      displayCells.push({
        isSubtotal: false,
        col: fc.col,
        subs: fc.subs,
        dataIdx: fi,
      })
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
      ;(dc.indices || []).forEach(function (i) {
        s += valArray[i] || 0
      })
      return s
    }
    return valArray[dc.dataIdx]
  }

  function subtotalCell(v, dataCtx, dataRoad, dataSt, subs, dataExtra) {
    var disp = typeof v === 'number' ? v.toLocaleString('ru-RU') : v || ''
    if (!v || !dataCtx) return '<td class="col-subtotal">' + disp + '</td>'
    var extra = dataExtra
      ? ' data-extra="' + esc(JSON.stringify(dataExtra)) + '"'
      : ''
    return (
      '<td class="col-subtotal cell-link" data-ctx="' +
      esc(dataCtx) +
      '" data-road="' +
      esc(dataRoad) +
      '" data-station="' +
      esc(dataSt) +
      '" data-col=""' +
      ' data-group-by="' +
      esc(groupBy) +
      '"' +
      subAttrs(subs) +
      extra +
      '>' +
      disp +
      '</td>'
    )
  }

  function renderDisplayCell(dc, v, dataCtx, dataRoad, dataSt, dataExtra) {
    if (dc.isSubtotal)
      return subtotalCell(v, dataCtx, dataRoad, dataSt, dc.subs, dataExtra)
    return cellLink(
      v,
      dataCtx,
      dataRoad,
      dataSt,
      { col: dc.col, subs: dc.subs },
      dataExtra,
    )
  }

  var h = []
  var rowspan = depth > 1 ? ' rowspan="' + depth + '"' : ''

  // Один столбец иерархии: «Страна / Дорога / Станция»
  var groupHeader = groupCols
    .map(function (gc) {
      return esc(gc.label)
    })
    .join(' / ')
  h.push('<thead><tr>')
  h.push(
    '<th class="col-meta" style="min-width:200px"' +
      rowspan +
      '>' +
      groupHeader +
      '</th>',
  )
  if (!hasSubtotals)
    h.push('<th class="col-total-col"' + rowspan + '>Итого</th>')

  displayLevels[0].forEach(function (c) {
    h.push(
      '<th' +
        (c.span > 1 ? ' colspan="' + c.span + '"' : '') +
        (depth > 1 ? ' style="text-align:center"' : '') +
        //(c.isSubtotal ? ' class="col-subtotal-hd"' : '') +
        (c.isSubtotal ? '' : '') +
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
          //(c.isSubtotal ? ' class="col-subtotal-hd"' : '') +
          (c.isSubtotal ? '' : '') +
          '>' +
          esc(c.label) +
          '</th>',
      )
    })
    h.push('</tr>')
  }
  h.push('</thead>')

  var grandTotals = displayCells.map(function () {
    return 0
  })
  var grandSum = 0
  var bodyH = []

  ;(roads || []).forEach(function (road, ri) {
    var roadVal = road[groupCols[0].key] || ''
    var stations = road.stations || []
    var hasChildren = nGroup > 1 && stations.length > 0

    bodyH.push(
      '<tr class="row-road-parent" data-road-id="' +
        ri +
        '" data-node-id="' +
        ri +
        '">',
    )
    bodyH.push(
      '<td class="col-meta col-meta--l0">' +
        (hasChildren ? '<span class="toggle-icon">▼</span>' : '') +
        esc(roadVal) +
        '</td>',
    )
    if (!hasSubtotals)
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
            var rowSum = (st.v || []).reduce(function (a, b) {
              return a + b
            }, 0)

            // При 3+ уровнях ancestorFilters содержит промежуточные ключи (напр. dest_road).
            // Передаём их через data-extra, чтобы openDetail добавил их в URL и бэкенд
            // применил в WHERE. gc[0] и gc[last] попадают через road/station как обычно.
            var bcVals = Object.keys(ancestorFilters)
              .map(function (k) {
                return ancestorFilters[k]
              })
              .concat([stVal])
            var leafExtra = Object.assign({}, ancestorFilters, {
              _bcpath: JSON.stringify(bcVals),
            })

            out.push(
              '<tr class="row-data row-child" data-parent-id="' +
                esc(parentNodeId) +
                '">',
            )
            out.push(
              '<td class="col-meta col-meta--l' +
                level +
                '">' +
                esc(stVal) +
                '</td>',
            )
            if (!hasSubtotals)
              out.push(totalLink(rowSum, ctx, roadVal, stVal, leafExtra))
            displayCells.forEach(function (dc) {
              out.push(
                renderDisplayCell(
                  dc,
                  getDisplayVal(dc, st.v || []),
                  ctx,
                  roadVal,
                  stVal,
                  leafExtra,
                ),
              )
            })
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
            out.push(
              '<td class="col-meta col-meta--l' +
                level +
                '">' +
                '<span class="toggle-icon">▼</span>' +
                esc(groupVal) +
                '</td>',
            )
            if (!hasSubtotals)
              out.push(totalLink(subSum, ctx, '', '', curFiltersWithPath))
            displayCells.forEach(function (dc) {
              out.push(
                renderDisplayCell(
                  dc,
                  getDisplayVal(dc, subTotal),
                  ctx,
                  '',
                  '',
                  curFiltersWithPath,
                ),
              )
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
    '<tr class="row-total row-grand"><td class="col-meta col-meta--l0">' +
      esc(totalText || 'Общий итог') +
      '</td>',
  ]

  if (!hasSubtotals) {
    totalH.push(totalLink(grandSum, ctx, '', '', null))
  }

  displayCells.forEach(function (dc, di) {
    totalH.push(renderDisplayCell(dc, grandTotals[di], ctx, '', '', null))
  })

  totalH.push('</tr>')

  // Вывод таблицы
  $(selector).html(
    h.join('') + '<tbody>' + totalH.join('') + bodyH.join('') + '</tbody>',
  )
  return displayCells
}

//  горизонтальный скролл для таблиц
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
      scrollEl.style.overflowX = 'hidden'
    } else {
      floater.style.display = 'none'
      scrollEl.style.overflowX = 'auto'
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

function kpiCard(item) {
  var val = item.value != null ? item.value : item.total || 0
  var hasDetail = !!item.detail
  var cls =
    'kpi-card' +
    (item.accent ? ' accent' : '') +
    (item.isError ? ' kpi-card--error' : '') +
    (hasDetail ? ' kpi-card--link' : '') +
    (item.isUser || item.canHide ? ' kpi-card--user' : '') +
    (item.canHide && !item.isGlobal && !item.isUser ? ' kpi-card--hide-only' : '')
  var detAttr = hasDetail
    ? " data-detail='" +
      JSON.stringify(item.detail).replace(/'/g, '&#39;') +
      "'"
    : ''
  var valStr =
    typeof val === 'number' ? val.toLocaleString('ru-RU') : esc(String(val))
  var valueLength = String(val).replace(/\s/g, '').length
  var valueClass =
    'kpi-value' +
    (valueLength > 5
      ? ' kpi-value--very-long'
      : valueLength > 3
        ? ' kpi-value--long'
        : '')
  var actionHtml = ''
  if (item.canHide) {
    actionHtml =
      '<div class="kpi-user-actions">' +
      (item.canEdit ? '<button type="button" class="kpi-user-edit" data-kpi-id="' + Number(item.id) + '" data-kpi-type="' + esc(item.type || '') + '" data-kpi-admin="' + (item.isGlobal ? '1' : '0') + '" aria-label="Редактировать KPI" title="Редактировать"><svg class="kpi-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h3.5L18 9.5 14.5 6 4 16.5zM20.7 6.8a1 1 0 0 0 0-1.4l-2.1-2.1a1 1 0 0 0-1.4 0l-1.7 1.7 3.5 3.5 1.7-1.7z"/></svg></button>' : '') +
      '<button type="button" class="kpi-global-hide" data-kpi-id="' + Number(item.id) + '" aria-label="Скрыть KPI" title="Скрыть"><svg class="kpi-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18-1.4 1.4-3.1-3.1A11.7 11.7 0 0 1 12 20C5 20 1 12 1 12a20 20 0 0 1 4.2-5.4L1.6 3 3 3zm4.3 5.7A6.8 6.8 0 0 0 3.3 12c1.2 1.8 4.2 6 8.7 6 1 0 2-.2 2.8-.5l-2-2A4 4 0 0 1 8.5 11l-1.2-1.3zM12 4c7 0 11 8 11 8a19 19 0 0 1-3.1 4.4L18.5 15a15.8 15.8 0 0 0 2.2-3c-1.2-1.8-4.2-6-8.7-6-.7 0-1.4.1-2 .2L8.4 4.6C9.5 4.2 10.7 4 12 4zm0 4a4 4 0 0 1 4 4v.3l-4.3-4.3h.3z"/></svg></button></div>'
  } else if (item.canEdit) {
    actionHtml = '<div class="kpi-user-actions"><button type="button" class="kpi-user-edit" data-kpi-id="' + Number(item.id) + '" data-kpi-type="' + esc(item.type || '') + '" aria-label="Редактировать KPI" title="Редактировать"><svg class="kpi-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h3.5L18 9.5 14.5 6 4 16.5zM20.7 6.8a1 1 0 0 0 0-1.4l-2.1-2.1a1 1 0 0 0-1.4 0l-1.7 1.7 3.5 3.5 1.7-1.7z"/></svg></button><button type="button" class="kpi-global-hide" data-kpi-id="' + Number(item.id) + '" aria-label="Скрыть KPI" title="Скрыть"><svg class="kpi-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18-1.4 1.4-3.1-3.1A11.7 11.7 0 0 1 12 20C5 20 1 12 1 12a20 20 0 0 1 4.2-5.4L1.6 3 3 3zm4.3 5.7A6.8 6.8 0 0 0 3.3 12c1.2 1.8 4.2 6 8.7 6 1 0 2-.2 2.8-.5l-2-2A4 4 0 0 1 8.5 11l-1.2-1.3zM12 4c7 0 11 8 11 8a19 19 0 0 1-3.1 4.4L18.5 15a15.8 15.8 0 0 0 2.2-3c-1.2-1.8-4.2-6-8.7-6-.7 0-1.4.1-2 .2L8.4 4.6C9.5 4.2 10.7 4 12 4zm0 4a4 4 0 0 1 4 4v.3l-4.3-4.3h.3z"/></svg></button></div>'
  }

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
      '<div class="kpi-card-main">' +
      '<div class="kpi-label">' +
      esc(item.label) +
      '</div>' +
      '<div class="kpi-value-row"><span class="' + valueClass + '">' +
      valStr +
      '</span>' +
      badgeHtml +
      '</div>' +
      (item.sub ? '<div class="kpi-delta">' + esc(item.sub) + '</div>' : '') +
      '</div>' +
      actionHtml +
      '</div>'
    )
  }

  return (
    '<div class="' +
    cls +
    '"' +
    detAttr +
    '>' +
    '<div class="kpi-card-main">' +
    '<div class="kpi-value">' +
    valStr +
    '</div>' +
    '<div class="kpi-label">' +
    esc(item.label) +
    '</div>' +
    (item.sub ? '<div class="kpi-delta">' + esc(item.sub) + '</div>' : '') +
    '</div>' +
    actionHtml +
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
$(document).on('click', '[data-toggle-table]', function () {
  var $btn = $(this)
  var $table = $('#' + $btn.data('toggle-table'))
  if ($btn.data('collapsed')) {
    expandAll($table)
    $btn.text('Свернуть все').data('collapsed', false)
  } else {
    collapseAll($table)
    $btn.text('Отобразить все').data('collapsed', true)
  }
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
  var visible = 0
  $table.find('tbody tr:not(.search-row)').each(function () {
    var $cells = $(this).find('td')
    var show = filters.every(function (q, ci) {
      return !q || matchFilter(q, $cells.eq(ci).text().toLowerCase())
    })
    $(this).toggle(show)
    if (show) visible++
  })
  var $sub = $table.closest('.table-section').find('.table-sub')
  if ($sub.length) $sub.text('Строк: ' + visible.toLocaleString('ru-RU'))
})

// <a>.click() вместо window.open
function navNewTab(url) {
  var a = document.createElement('a')
  a.href = url
  a.target = '_blank'
  a.rel = 'noopener noreferrer'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

// Служебные колонки не передаём в параметры детализации.
function canOpenDetailFor(extra) {
  var sourcePage = extra && extra.source_page
  return sourcePage
    ? ALLOWED_PAGES.indexOf(sourcePage) !== -1
    : CAN_OPEN_DETAIL
}

function openDetail(ctx, road, station, col, groupBy, subs, extra) {
  if (!canOpenDetailFor(extra)) return
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
  if (tabCfg && tabCfg.pinnedStationKey && !p.has('exclude_station')) {
    var stKey = (station || '').toUpperCase()
    var pinKey = tabCfg.pinnedStationKey.toUpperCase()
    if (stKey.indexOf(pinKey) === -1)
      p.set('exclude_station', tabCfg.pinnedStationKey)
  }
  navNewTab(BASE + '/detail?' + p.toString())
}

// Переход к детализации из ячейки сводной.
$(document).on('click', '.cell-link', function (e) {
  if (!CAN_OPEN_DETAIL) {
    e.preventDefault()
    e.stopPropagation()
    return
  }
  if ($(this).hasClass('claims-detail-link')) return
  e.preventDefault()
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
$(document).on('click', '.kpi-card--link', function (event) {
  if ($(event.target).closest('.kpi-user-actions').length) return
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
  if (!canOpenDetailFor(extra)) return
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
  var reportCodes = Object.keys(WAGON_TABS)
  var visibleReports = reportCodes.filter(function (code) {
    return ALLOWED_PAGES.indexOf(code) !== -1
  })
  initSidebar()
  initInnerTabs()

  var hasReports = visibleReports.length > 0

  // Если отчёты не назначены — скрываем панели и показываем заглушку.
  if (!hasReports) {
    var allPanels = document.querySelectorAll('.tab-panel')
    for (var pi = 0; pi < allPanels.length; pi++) {
      allPanels[pi].style.display = 'none'
    }
    var mainContent = document.querySelector('.main-content')
    if (mainContent) {
      var noAccess = document.createElement('div')
      noAccess.style.cssText =
        'padding:80px 24px;text-align:center;color:#888;font-size:16px'
      if (ALLOWED_PAGES.length === 0) {
        // Совсем нет ролей
        noAccess.innerHTML =
          '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin-bottom:16px;opacity:.35"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-width="2"/><path stroke-width="2" d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>' +
          '<div style="font-weight:600;font-size:18px;margin-bottom:8px;color:#555">Нет доступных разделов</div>' +
          '<div>Обратитесь к администратору для получения доступа</div>'
      } else {
        // Есть роли, но не дашборд — выбрать раздел из меню
        noAccess.innerHTML =
          '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin-bottom:16px;opacity:.35"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>' +
          '<div style="font-weight:600;font-size:18px;margin-bottom:8px;color:#555">Выберите раздел</div>' +
          '<div>Используйте меню навигации слева</div>'
      }
      mainContent.appendChild(noAccess)
    }
  }

  // Восстанавливаем активную вкладку из URL hash (при возврате с детализации)
  var hashTab = (location.hash || '').replace('#', '')
  var startTab =
    hashTab && visibleReports.indexOf(hashTab) !== -1
      ? hashTab
      : visibleReports.indexOf('dislocation') !== -1
        ? 'dislocation'
        : visibleReports[0] || ''
  var startXhr = startTab ? switchTab(startTab) : null

  // Скрываем оверлей когда готовы и KPI, и сводная таблица
  var allXhrs = startXhr ? [startXhr] : []
  $.when.apply($, allXhrs).always(function () {
    var el = document.getElementById('pageLoadOverlay')
    if (el && el.parentNode) el.parentNode.removeChild(el)
  })
  // Страховка: показать страницу не позже чем через 1.5 секунды
  setTimeout(function () {
    var el = document.getElementById('pageLoadOverlay')
    if (el && el.parentNode) el.parentNode.removeChild(el)
  }, 1500)

  // Выгрузка сводной претензий в Excel.
  $('#btnClaimsExportSummary').on('click', function () {
    saveExcelfromVT('claimsSumTable', 'претензии-сводная', 'summary')
  })

  Object.keys(WAGON_TABS).forEach(function (k) {
    var cfg = WAGON_TABS[k]
    var capKey = k.charAt(0).toUpperCase() + k.slice(1)
    var applyId = cfg.applyBtnId || 'btn' + capKey + 'Apply'
    var resetId = cfg.resetBtnId || 'btn' + capKey + 'Reset'
    $('#' + applyId).on('click', function () {
      var applyHint = cfg.validate ? cfg.validate() : null
      if (applyHint) {
        $('#' + cfg.detTableId).html(
          '<div style="text-align:center;padding:60px;color:#E8392A;font-size:15px;font-weight:600">' +
            esc(applyHint) +
            '</div>',
        )
        return
      }
      window[cfg.loadedDetKey] = false
      loadSummary(cfg)
      if (k === 'loading') loadKPI(['loading'], true)
      if ($('#' + cfg.detPanelId).hasClass('active')) {
        window[cfg.loadedDetKey] = true
        loadDetail(cfg)
      }
    })
    $('#' + resetId).on('click', function () {
      if (cfg.resetFilters) cfg.resetFilters()
      window[cfg.loadedDetKey] = false
      loadSummary(cfg)
      if (k === 'loading') loadKPI(['loading'], true)
      if (
        (k === 'claims' || !cfg.summaryUrl) &&
        $('#' + cfg.detPanelId).hasClass('active')
      ) {
        window[cfg.loadedDetKey] = true
        loadDetail(cfg)
      }
    })
  })
})
