;(function ($) {
  'use strict'

  var form = window.USER_KPI_FORM || {}
  var fields = form.fields || []
  var config = window.USER_KPI_CONFIG || {}

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(
      /[&<>"']/g,
      function (char) {
        return {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;',
        }[char]
      },
    )
  }

  function operators(type) {
    return type === 'string'
      ? [
          ['LIKE', 'Содержит'],
          ['=', 'Равно'],
          ['!=', 'Не равно'],
        ]
      : [
          ['=', 'Равно'],
          ['!=', 'Не равно'],
          ['>', 'Больше'],
          ['<', 'Меньше'],
          ['>=', 'Не меньше'],
          ['<=', 'Не больше'],
        ]
  }

  function updateCondition($row) {
    var type = $row.find('.user-kpi-field option:selected').data('type')
    var current = $row.find('.user-kpi-operator').val()
    var html = operators(type)
      .map(function (item) {
        return '<option value="' + item[0] + '">' + item[1] + '</option>'
      })
      .join('')
    $row.find('.user-kpi-operator').html(html)
    if (
      operators(type).some(function (item) {
        return item[0] === current
      })
    )
      $row.find('.user-kpi-operator').val(current)
    $row
      .find('.user-kpi-value')
      .attr(
        'type',
        type === 'number' ? 'number' : type === 'date' ? 'date' : 'text',
      )
  }

  function addCondition(condition) {
    condition = condition || {}
    var options = fields
      .map(function (field) {
        return (
          '<option value="' +
          escapeHtml(field.key) +
          '" data-type="' +
          escapeHtml(field.type) +
          '" ' +
          (condition.field === field.key ? 'selected' : '') +
          '>' +
          escapeHtml(field.label) +
          '</option>'
        )
      })
      .join('')
    var $row = $(
      '<div class="user-kpi-condition-row"><select class="user-kpi-field" required>' +
        options +
        '</select><select class="user-kpi-operator"></select><input class="user-kpi-value" maxlength="200" placeholder="Значение" required><button type="button" class="btn btn-ghost btn-sm user-kpi-remove" aria-label="Удалить условие">×</button></div>',
    )
    $('#userKpiConditions').append($row)
    updateCondition($row)
    $row.find('.user-kpi-operator').val(condition.operator || '=')
    $row.find('.user-kpi-value').val(condition.value || '')
  }

  function collectConditions() {
    var conditions = []
    $('#userKpiConditions .user-kpi-condition-row').each(function () {
      conditions.push({
        field: $(this).find('.user-kpi-field').val(),
        operator: $(this).find('.user-kpi-operator').val(),
        value: $(this).find('.user-kpi-value').val(),
      })
    })
    return conditions
  }

  function save() {
    var detailChoice = $('#userKpiDetailType').val()
    var detail = {
      type: detailChoice === 'TABLE' ? 'TABLE' : 'GROUPED',
      template: detailChoice === 'TABLE' ? '' : detailChoice,
    }
    var payload = {
      csrf_token: window.APP_CSRF || '',
      id: form.id || '',
      admin: form.admin ? '1' : '',
      kpi_type: form.type,
      label: $('#userKpiLabel').val().trim(),
      visibility: form.canSetGlobal ? $('#userKpiVisibility').val() : 'PRIVATE',
      config: {
        operator: $('#userKpiConditionOperator').val(),
        conditions: collectConditions(),
        detail: detail,
      },
    }
    $('#userKpiError').text('')
    $('#userKpiSave').prop('disabled', true)
    $.ajax({
      url: (window.APP_BASE || '') + '/api/kpi/user',
      method: 'POST',
      dataType: 'json',
      data: payload,
    })
      .done(function (result) {
        if (form.modal && window.parent !== window) {
          window.parent.postMessage({ type: 'kpi:saved' }, window.location.origin)
          return
        }
        window.location.href =
          (window.APP_BASE || '') + (result.redirect || '/')
      })
      .fail(function (xhr) {
        $('#userKpiError').text(
          (xhr.responseJSON && xhr.responseJSON.error) ||
            'Не удалось сохранить KPI',
        )
      })
      .always(function () {
        $('#userKpiSave').prop('disabled', false)
      })
  }

  function removeKpi() {
    if (
      !form.id ||
      !window.confirm('Удалить эту KPI-карточку без возможности восстановления?')
    )
      return
    $('#userKpiError').text('')
    $('#userKpiDelete, #userKpiSave').prop('disabled', true)
    $.ajax({
      url: (window.APP_BASE || '') + '/api/kpi/user/' + Number(form.id),
      method: 'DELETE',
      dataType: 'json',
      data: { csrf_token: window.APP_CSRF || '' },
    })
      .done(function () {
        if (form.modal && window.parent !== window) {
          window.parent.postMessage({ type: 'kpi:deleted' }, window.location.origin)
          return
        }
        window.location.href = (window.APP_BASE || '') + '/'
      })
      .fail(function (xhr) {
        $('#userKpiError').text(
          (xhr.responseJSON && xhr.responseJSON.error) ||
            'Не удалось удалить KPI',
        )
        $('#userKpiDelete, #userKpiSave').prop('disabled', false)
      })
  }

  $(function () {
    $('#userKpiConditions .user-kpi-condition-row').each(function (index) {
      updateCondition($(this))
      var saved = (config.conditions || [])[index]
      if (saved && saved.operator)
        $(this).find('.user-kpi-operator').val(saved.operator)
    })
    $('#userKpiConditions').on('change', '.user-kpi-field', function () {
      updateCondition($(this).closest('.user-kpi-condition-row'))
    })
    $('#userKpiConditions').on('click', '.user-kpi-remove', function () {
      if ($('#userKpiConditions .user-kpi-condition-row').length > 1)
        $(this).closest('.user-kpi-condition-row').remove()
    })
    $('#userKpiAddCondition').on('click', function () {
      if ($('#userKpiConditions .user-kpi-condition-row').length < 10)
        addCondition()
    })
    $('#userKpiSave').on('click', save)
    $('#userKpiDelete').on('click', removeKpi)
    $('#userKpiCancel').on('click', function () {
      if (form.modal && window.parent !== window) {
        window.parent.postMessage({ type: 'kpi:cancel' }, window.location.origin)
        return
      }
      window.location.href = (window.APP_BASE || '') + '/'
    })
  })
})(jQuery)
