(function () {
  'use strict'

  var catalog = window.MAILING_CATALOG || {}
  var initial = window.MAILING_VALUES || { report_view: 'DETAIL', filters: {} }
  var reportCode = document.getElementById('reportCode')
  if (!reportCode) return

  var filterValues = Object.assign({}, initial.filters || {})
  var optionData = {}

  function report() {
    return catalog[reportCode.value] || { filters: [], views: ['DETAIL'] }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
  }

  function optionValues(filter) {
    var key = filter.options_key
    if (!key || !optionData) return []
    if (key === 'reports') {
      return (optionData.reports || []).map(function (item) {
        return {
          value: item.report_dt || '',
          label: item.label || item.report_dt || '',
        }
      })
    }
    return (optionData[key] || []).map(function (value) {
      return { value: value, label: value }
    })
  }

  function filterHtml(filter) {
    var name = 'filters[' + filter.name + ']'
    var value = filterValues[filter.name] || ''
    var required = filter.required ? ' required' : ''
    var control = ''
    if (filter.type === 'select') {
      var firstLabel = '— Все —'
      control = '<select name="' + escapeHtml(name) + '" data-filter-name="' + escapeHtml(filter.name) + '"' + required + '>'
        + '<option value="">' + firstLabel + '</option>'
        + optionValues(filter).map(function (option) {
          return '<option value="' + escapeHtml(option.value) + '"' + (String(option.value) === String(value) ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>'
        }).join('') + '</select>'
    } else {
      control = '<input type="' + (filter.type === 'date' ? 'date' : 'text') + '" name="' + escapeHtml(name)
        + '" data-filter-name="' + escapeHtml(filter.name) + '" value="' + escapeHtml(value) + '"' + required + '>'
    }
    return '<div class="mailing-field"><label>' + escapeHtml(filter.label) + (filter.required ? ' *' : '') + '</label>' + control + '</div>'
  }

  function drawFilters() {
    document.getElementById('mailingFilters').innerHTML = (report().filters || [])
      .filter(function (filter) { return !filter.show || filter.show.indexOf('mailing') !== -1 })
      .map(filterHtml).join('')
  }

  function drawViews() {
    var select = document.getElementById('reportView')
    var current = initial.report_view
    select.innerHTML = (report().views || ['DETAIL']).map(function (view) {
      var label = view === 'SUMMARY' ? 'Сводный' : 'Подробный'
      return '<option value="' + view + '"' + (view === current ? ' selected' : '') + '>' + label + '</option>'
    }).join('')
    if (!select.value) select.value = report().default_view || 'DETAIL'
  }

  function loadOptions() {
    optionData = {}
    var url = report().options_url
    if (!url) {
      drawFilters()
      return
    }
    fetch((window.APP_BASE || '') + url, { credentials: 'same-origin' })
      .then(function (response) { return response.ok ? response.json() : {} })
      .then(function (data) { optionData = data || {}; drawFilters() })
      .catch(drawFilters)
  }

  function storeVisibleFilters() {
    document.querySelectorAll('[data-filter-name]').forEach(function (field) {
      filterValues[field.dataset.filterName] = field.value
    })
  }

  function drawOrganization() {
    var field = document.getElementById('organizationField')
    var select = document.getElementById('organizationId')
    var required = report().organization_required !== false
    field.hidden = !required
    select.required = required
  }

  function drawSchedule() {
    var type = document.getElementById('scheduleType').value
    document.getElementById('runTimeField').hidden = type === 'MANUAL'
    document.getElementById('weekDaysField').hidden = type !== 'WEEKLY'
    document.getElementById('monthDayField').hidden = type !== 'MONTHLY'
  }

  function summary(name, value) {
    var element = document.querySelector('[data-mailing-summary="' + name + '"]')
    if (element) element.textContent = value || '—'
  }

  function updateSummary() {
    var recipients = Array.from(document.querySelectorAll('input[name="recipient_email[]"]'))
      .map(function (input) { return input.value.trim() }).filter(Boolean)
    var schedule = document.getElementById('scheduleType')
    var scheduleLabel = schedule.selectedOptions[0].text
    if (schedule.value !== 'MANUAL') scheduleLabel += ', ' + document.getElementById('runTime').value
    summary('name', document.getElementById('mailingName').value)
    summary('report', report().name + ' · ' + document.getElementById('reportView').selectedOptions[0].text)
    summary('organization', report().organization_required === false ? 'Общий отчёт' : document.getElementById('organizationId').selectedOptions[0]?.text)
    summary('format', document.getElementById('fileFormat').selectedOptions[0].text)
    summary('schedule', scheduleLabel)
    summary('recipients', recipients.join(', '))
  }

  reportCode.addEventListener('change', function () {
    storeVisibleFilters()
    filterValues = {}
    initial.report_view = report().default_view || 'DETAIL'
    drawViews(); drawOrganization(); loadOptions(); updateSummary()
  })

  document.getElementById('scheduleType').addEventListener('change', function () { drawSchedule(); updateSummary() })
  document.getElementById('resetMailingFilters').addEventListener('click', function () { filterValues = {}; drawFilters() })
  document.getElementById('addMailingRecipient').addEventListener('click', function () {
    var row = document.createElement('div')
    row.className = 'mailing-recipient-row'
    row.innerHTML = '<input type="email" name="recipient_email[]" placeholder="mail@example.ru"><select name="recipient_type[]"><option value="TO">Кому</option><option value="CC">Копия</option></select><button type="button" class="mailing-remove-recipient" title="Удалить">×</button>'
    document.getElementById('mailingRecipients').appendChild(row)
    updateSummary()
  })
  document.getElementById('mailingRecipients').addEventListener('click', function (event) {
    if (event.target.matches('.mailing-remove-recipient')) {
      event.target.closest('.mailing-recipient-row').remove()
      updateSummary()
    }
  })
  document.addEventListener('input', updateSummary)
  document.addEventListener('change', updateSummary)

  drawViews(); drawOrganization(); drawSchedule(); loadOptions(); updateSummary()
})()
