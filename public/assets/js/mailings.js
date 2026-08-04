(function () {
  'use strict'

  var catalog = window.MAILING_CATALOG || {}
  var organizations = window.MAILING_ORGANIZATIONS || []
  var initialAttachments = window.MAILING_ATTACHMENTS || []
  var attachmentsRoot = document.getElementById('mailingAttachments')
  if (!attachmentsRoot) return

  var nextAttachment = 0
  var searchTimers = {}

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
  }

  function report(code) {
    return catalog[code] || catalog[Object.keys(catalog)[0]] || { filters: [], views: ['DETAIL'] }
  }

  function reportOptions(selected) {
    return Object.keys(catalog).map(function (code) {
      return '<option value="' + escapeHtml(code) + '"' + (code === selected ? ' selected' : '') + '>' + escapeHtml(catalog[code].name) + '</option>'
    }).join('')
  }

  function organizationOptions(selected) {
    return organizations.map(function (organization) {
      return '<option value="' + organization.id + '"' + (Number(selected) === Number(organization.id) ? ' selected' : '') + '>' + escapeHtml(organization.name) + '</option>'
    }).join('')
  }

  function viewOptions(settings, selected) {
    return (settings.views || ['DETAIL']).map(function (view) {
      var label = view === 'SUMMARY' ? 'Сводный' : 'Подробный'
      return '<option value="' + view + '"' + (view === selected ? ' selected' : '') + '>' + label + '</option>'
    }).join('')
  }

  function filterHtml(index, filter, values) {
    var name = 'attachments[' + index + '][filters][' + filter.name + ']' + (filter.multiple ? '[]' : '')
    var value = values[filter.name] || (filter.multiple ? [] : '')
    var selected = Array.isArray(value) ? value.map(String) : [String(value)]
    var required = filter.required ? ' required' : ''
    var control = ''
    if (filter.type === 'select' && filter.multiple) {
      var optionHtml = selected.filter(Boolean).map(function (item) {
        return '<option value="' + escapeHtml(item) + '" selected>' + escapeHtml(item) + '</option>'
      }).join('')
      control = '<div class="mailing-multi" data-multi data-options-key="' + escapeHtml(filter.options_key || filter.name) + '">'
        + '<select class="mailing-multi-source" name="' + escapeHtml(name) + '" data-filter-name="' + escapeHtml(filter.name) + '" multiple>' + optionHtml + '</select>'
        + '<button class="mailing-multi-button" type="button" aria-expanded="false"><span data-multi-title>' + (selected.filter(Boolean).length ? 'Выбрано: ' + selected.filter(Boolean).length : 'Все значения') + '</span><span class="mailing-multi-arrow">⌄</span></button>'
        + '<div class="mailing-multi-panel" hidden><input class="mailing-multi-search" type="search" placeholder="Поиск…" autocomplete="off">'
        + '<div class="mailing-multi-actions"><button type="button" data-multi-all>Выбрать показанные</button><button type="button" data-multi-clear>Очистить</button></div>'
        + '<div class="mailing-multi-options"></div><div class="mailing-multi-empty" hidden>Ничего не найдено</div>'
        + '<div class="mailing-multi-note">Показано до 50 значений. Используйте поиск</div></div></div>'
    } else if (filter.type === 'select') {
      control = '<select name="' + escapeHtml(name) + '" data-filter-name="' + escapeHtml(filter.name) + '"' + required + '><option value="">— Все —</option></select>'
    } else {
      control = '<input type="' + (filter.type === 'date' ? 'date' : 'text') + '" name="' + escapeHtml(name) + '" data-filter-name="' + escapeHtml(filter.name) + '" value="' + escapeHtml(value) + '"' + required + '>'
    }
    return '<div class="mailing-field"><label>' + escapeHtml(filter.label) + (filter.required ? ' *' : '') + '</label>' + control + '</div>'
  }

  function drawFilters(block, values) {
    var index = block.dataset.attachmentIndex
    var settings = report(block.querySelector('[data-attachment-report]').value)
    block.querySelector('[data-attachment-filters]').innerHTML = (settings.filters || [])
      .filter(function (filter) { return !filter.show || filter.show.indexOf('mailing') !== -1 })
      .map(function (filter) { return filterHtml(index, filter, values || {}) }).join('')
  }

  function addAttachment(values) {
    if (attachmentsRoot.children.length >= 10) return
    values = values || {}
    var index = nextAttachment++
    var code = catalog[values.report_code] ? values.report_code : (Object.keys(catalog)[0] || '')
    var settings = report(code)
    var selectedView = (settings.views || []).indexOf(values.report_view) !== -1 ? values.report_view : settings.default_view
    var block = document.createElement('section')
    block.className = 'mailing-attachment'
    block.dataset.attachmentIndex = String(index)
    block.innerHTML = '<div class="mailing-attachment-head"><strong data-attachment-title></strong><button type="button" class="mailing-icon-button is-delete" data-remove-attachment title="Удалить отчёт" aria-label="Удалить отчёт">×</button></div>'
      + '<div class="mailing-fields">'
      + '<div class="mailing-field"><label>Отчёт</label><select name="attachments[' + index + '][report_code]" data-attachment-report required>' + reportOptions(code) + '</select></div>'
      + '<div class="mailing-field" data-attachment-organization-field><label>Организация</label><select name="attachments[' + index + '][organization_id]" data-attachment-organization>' + organizationOptions(values.organization_id) + '</select></div>'
      + '<div class="mailing-field"><label>Вид отчёта</label><select name="attachments[' + index + '][report_view]" data-attachment-view>' + viewOptions(settings, selectedView) + '</select></div>'
      + '<div class="mailing-field"><label>Формат файла</label><input value="Excel (.xlsx)" disabled></div>'
      + '<div class="mailing-field is-full mailing-filter-box"><div class="mailing-filter-head"><span class="mailing-card-title">Фильтры отчёта</span><button type="button" class="mailing-link-button" data-reset-attachment>Сбросить</button></div><div class="mailing-fields" data-attachment-filters></div></div>'
      + '</div>'
    attachmentsRoot.appendChild(block)
    updateAttachment(block, values.filters || {}, selectedView)
    updateSummary()
  }

  function updateAttachment(block, filters, selectedView) {
    var code = block.querySelector('[data-attachment-report]').value
    var settings = report(code)
    var view = block.querySelector('[data-attachment-view]')
    view.innerHTML = viewOptions(settings, selectedView || settings.default_view)
    var organizationField = block.querySelector('[data-attachment-organization-field]')
    organizationField.hidden = settings.organization_required === false
    block.querySelector('[data-attachment-organization]').required = settings.organization_required !== false
    drawFilters(block, filters || {})
    numberAttachments()
  }

  function numberAttachments() {
    Array.from(attachmentsRoot.children).forEach(function (block, index) {
      block.querySelector('[data-attachment-title]').textContent = 'Вложение №' + (index + 1)
      block.querySelector('[data-remove-attachment]').hidden = attachmentsRoot.children.length === 1
    })
  }

  function selectedValues(widget) {
    return Array.from(widget.querySelector('.mailing-multi-source').selectedOptions).map(function (option) { return option.value })
  }

  function syncMulti(widget) {
    var select = widget.querySelector('.mailing-multi-source')
    var selected = new Set(selectedValues(widget))
    widget.querySelectorAll('.mailing-multi-option input').forEach(function (input) {
      if (input.checked) selected.add(input.value)
      else selected.delete(input.value)
    })
    Array.from(select.options).forEach(function (option) { option.selected = selected.has(option.value) })
    widget.querySelector('[data-multi-title]').textContent = selected.size ? 'Выбрано: ' + selected.size : 'Все значения'
  }

  function clearMulti(widget) {
    Array.from(widget.querySelector('.mailing-multi-source').options).forEach(function (option) { option.selected = false })
    widget.querySelectorAll('.mailing-multi-option input').forEach(function (input) { input.checked = false })
    widget.querySelector('[data-multi-title]').textContent = 'Все значения'
  }

  function unique(values) {
    return Array.from(new Set(values.map(String).filter(Boolean)))
  }

  function drawMultiOptions(widget, values, query) {
    var select = widget.querySelector('.mailing-multi-source')
    var selected = selectedValues(widget)
    var allValues = unique(selected.concat(values))
    select.innerHTML = ''
    allValues.forEach(function (value) { select.add(new Option(value, value, false, selected.indexOf(value) !== -1)) })
    var shown = query ? unique(values) : allValues
    var list = widget.querySelector('.mailing-multi-options')
    list.innerHTML = ''
    shown.forEach(function (value) {
      var label = document.createElement('label')
      label.className = 'mailing-multi-option'
      var input = document.createElement('input')
      input.type = 'checkbox'; input.value = value; input.checked = selected.indexOf(value) !== -1
      var text = document.createElement('span'); text.textContent = value
      label.appendChild(input); label.appendChild(text); list.appendChild(label)
    })
    var empty = widget.querySelector('.mailing-multi-empty')
    empty.textContent = 'Ничего не найдено'; empty.hidden = shown.length !== 0
  }

  function loadMultiOptions(widget, query) {
    var block = widget.closest('.mailing-attachment')
    var settings = report(block.querySelector('[data-attachment-report]').value)
    if (!settings.options_url) return
    var requestNumber = Number(widget.dataset.requestNumber || 0) + 1
    widget.dataset.requestNumber = String(requestNumber)
    var params = new URLSearchParams({ option: widget.dataset.optionsKey, q: query })
    var organization = block.querySelector('[data-attachment-organization]')
    if (organization && !organization.closest('[hidden]') && organization.value) params.set('organization_id', organization.value)
    block.querySelectorAll('[data-filter-name]').forEach(function (field) {
      if (!field.multiple && field.value) params.set(field.dataset.filterName, field.value)
    })
    var empty = widget.querySelector('.mailing-multi-empty')
    empty.textContent = 'Загрузка…'; empty.hidden = false
    fetch((window.APP_BASE || '') + settings.options_url + '?' + params.toString(), { credentials: 'same-origin' })
      .then(function (response) { return response.ok ? response.json() : { values: [] } })
      .then(function (data) {
        if (Number(widget.dataset.requestNumber) === requestNumber) drawMultiOptions(widget, Array.isArray(data.values) ? data.values : [], query)
      })
      .catch(function () { empty.textContent = 'Не удалось загрузить значения'; empty.hidden = false })
  }

  function closeMulti(except) {
    document.querySelectorAll('[data-multi]').forEach(function (widget) {
      if (widget === except) return
      widget.querySelector('.mailing-multi-panel').hidden = true
      widget.querySelector('.mailing-multi-button').setAttribute('aria-expanded', 'false')
    })
  }

  function drawSchedule() {
    var type = document.getElementById('scheduleType').value
    document.getElementById('runTimeField').hidden = type === 'MANUAL'
    document.getElementById('weekDaysField').hidden = type !== 'WEEKLY' && type !== 'HOURLY'
    document.getElementById('intervalHoursField').hidden = type !== 'HOURLY'
    document.getElementById('intervalHours').required = type === 'HOURLY'
    document.getElementById('monthDayField').hidden = type !== 'MONTHLY'
  }

  function summary(name, value) {
    var element = document.querySelector('[data-mailing-summary="' + name + '"]')
    if (element) element.textContent = value || '—'
  }

  function updateSummary() {
    var blocks = Array.from(attachmentsRoot.children)
    var reportNames = blocks.map(function (block) { return report(block.querySelector('[data-attachment-report]').value).name })
    var organizationNames = blocks.map(function (block) {
      var field = block.querySelector('[data-attachment-organization-field]')
      var select = block.querySelector('[data-attachment-organization]')
      return field.hidden ? 'Общий отчёт' : (select.selectedOptions[0] ? select.selectedOptions[0].text : '')
    })
    var recipients = Array.from(document.querySelectorAll('input[name="recipient_email[]"]')).map(function (input) { return input.value.trim() }).filter(Boolean)
    var schedule = document.getElementById('scheduleType')
    var scheduleLabel = schedule.selectedOptions[0].text
    var weekDays = Array.from(document.querySelectorAll('input[name="week_days[]"]:checked')).map(function (input) { return input.nextElementSibling.textContent }).join(', ')
    if (schedule.value === 'HOURLY') scheduleLabel = 'Каждые ' + document.getElementById('intervalHours').value + ' ч., с ' + document.getElementById('runTime').value + (weekDays ? ' (' + weekDays + ')' : '')
    else if (schedule.value === 'WEEKLY') scheduleLabel = 'По дням недели: ' + (weekDays || 'не выбраны') + ', ' + document.getElementById('runTime').value
    else if (schedule.value !== 'MANUAL') scheduleLabel += ', ' + document.getElementById('runTime').value
    summary('name', document.getElementById('mailingName').value)
    summary('reports', reportNames.join(', '))
    summary('organizations', unique(organizationNames).join(', '))
    summary('format', blocks.length + ' × Excel (.xlsx)')
    summary('schedule', scheduleLabel)
    summary('recipients', recipients.join(', '))
  }

  attachmentsRoot.addEventListener('change', function (event) {
    var block = event.target.closest('.mailing-attachment')
    if (!block) return
    if (event.target.matches('[data-attachment-report]')) updateAttachment(block, {}, null)
    if (event.target.matches('.mailing-multi-option input')) syncMulti(event.target.closest('[data-multi]'))
    updateSummary()
  })

  attachmentsRoot.addEventListener('click', function (event) {
    var block = event.target.closest('.mailing-attachment')
    if (event.target.closest('[data-remove-attachment]') && block) {
      block.remove(); numberAttachments(); updateSummary(); return
    }
    if (event.target.closest('[data-reset-attachment]') && block) {
      drawFilters(block, {}); return
    }
    var widget = event.target.closest('[data-multi]')
    if (!widget) return
    if (event.target.closest('.mailing-multi-button')) {
      var panel = widget.querySelector('.mailing-multi-panel')
      var opening = panel.hidden
      closeMulti(opening ? widget : null); panel.hidden = !opening
      widget.querySelector('.mailing-multi-button').setAttribute('aria-expanded', opening ? 'true' : 'false')
      if (opening) { var search = widget.querySelector('.mailing-multi-search'); search.value = ''; search.focus(); loadMultiOptions(widget, '') }
      return
    }
    if (event.target.closest('[data-multi-all]')) {
      widget.querySelectorAll('.mailing-multi-option input').forEach(function (input) { input.checked = true }); syncMulti(widget); return
    }
    if (event.target.closest('[data-multi-clear]')) clearMulti(widget)
  })

  attachmentsRoot.addEventListener('input', function (event) {
    if (!event.target.matches('.mailing-multi-search')) return
    var widget = event.target.closest('[data-multi]')
    var key = widget.closest('.mailing-attachment').dataset.attachmentIndex + '_' + widget.dataset.optionsKey
    clearTimeout(searchTimers[key])
    searchTimers[key] = setTimeout(function () { loadMultiOptions(widget, event.target.value.trim()) }, 300)
  })

  document.getElementById('addMailingAttachment').addEventListener('click', function () { addAttachment({}) })
  document.getElementById('scheduleType').addEventListener('change', function () { drawSchedule(); updateSummary() })
  document.getElementById('addMailingRecipient').addEventListener('click', function () {
    var row = document.createElement('div'); row.className = 'mailing-recipient-row'
    row.innerHTML = '<input type="email" name="recipient_email[]" placeholder="mail@example.ru"><select name="recipient_type[]"><option value="TO">Кому</option><option value="CC">Копия</option></select><button type="button" class="mailing-remove-recipient" title="Удалить">×</button>'
    document.getElementById('mailingRecipients').appendChild(row); updateSummary()
  })
  document.getElementById('mailingRecipients').addEventListener('click', function (event) {
    if (event.target.matches('.mailing-remove-recipient')) { event.target.closest('.mailing-recipient-row').remove(); updateSummary() }
  })
  document.addEventListener('click', function (event) { if (!event.target.closest('[data-multi]')) closeMulti(null) })
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeMulti(null) })
  document.addEventListener('input', updateSummary)
  document.addEventListener('change', updateSummary)

  ;(initialAttachments.length ? initialAttachments : [{}]).forEach(addAttachment)
  drawSchedule()
  updateSummary()
})()
