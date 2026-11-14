;(function ($) {
  'use strict'

  if (!window.APP_USER_KPI_MANAGEMENT_ENABLED) return

  var kpiTypes = window.APP_KPI_TYPES || {}
  var boards = Object.keys(kpiTypes).filter(function (type) {
    return kpiTypes[type].user_creatable && kpiTypes[type].user_kpi_enabled !== false
  }).map(function (type) {
    return { key: kpiTypes[type].container_key, type: type }
  })

  function openForm(type, id, admin) {
    var url =
      (window.APP_BASE || '') +
      (id ? '/kpi/edit' : '/kpi/create') +
      '?kpi_type=' +
      encodeURIComponent(type)
    if (id) url += '&id=' + encodeURIComponent(id)
    if (admin) url += '&admin=1'
    var dialog = document.getElementById('kpiFormDialog')
    var frame = document.getElementById('kpiFormFrame')
    if (dialog && frame && typeof dialog.showModal === 'function') {
      frame.src = url + '&modal=1'
      dialog.showModal()
      return
    }
    window.location.href = url
  }

  function closeCreateForm() {
    var dialog = document.getElementById('kpiFormDialog')
    var frame = document.getElementById('kpiFormFrame')
    if (dialog && dialog.open) dialog.close()
    if (frame) frame.removeAttribute('src')
  }

  function initBoard(board) {
    var $grid = $('.kpi-grid[data-kpi-container]').filter(function () {
      return $(this).attr('data-kpi-container') === board.key
    })
    if (!$grid.length || $grid.data('user-kpi-ready')) return
    $grid.data('user-kpi-ready', true).addClass('user-kpi-enabled')
    var $button = $(
      '<button type="button" class="user-kpi-create" data-kpi-type="' +
        board.type +
        '" aria-label="Создать KPI" title="Создать KPI"><span aria-hidden="true">+</span></button>',
    )
    $grid.append($button)
    $grid.on('kpi:rendered', function () {
      if (!$grid.find('.user-kpi-create').length) $grid.append($button)
    })
  }

  function renderHidden(rows) {
    var $list = $('#kpiHiddenList')
    if (!rows.length) {
      $list.html('<div class="kpi-hidden-empty">Нет скрытых KPI.</div>')
      return
    }
    $list.html(
      rows.map(function (row) {
        var typeDefinition = kpiTypes[row.type] || {}
        var page = row.page_code || typeDefinition.page || ''
        var pageType = Object.keys(kpiTypes).find(function (type) {
          return kpiTypes[type].page === page
        })
        var pageLabel = (pageType && kpiTypes[pageType].label) || 'KPI'
        var scope = row.is_system === 'Y' ? 'Системная' : row.visibility === 'PRIVATE' ? 'Личная' : 'Общая'
        return '<div class="kpi-hidden-row"><div><strong>' + $('<div>').text(row.label || '').html() + '</strong><span>' + pageLabel + ' · ' + scope + '</span></div><button type="button" class="btn btn-ghost btn-sm kpi-hidden-restore" data-kpi-id="' + Number(row.id) + '">Вернуть</button></div>'
      }).join(''),
    )
  }

  function loadHidden() {
    $('#kpiHiddenList').text('Загрузка…')
    $.getJSON((window.APP_BASE || '') + '/api/kpi/hidden')
      .done(function (result) {
        renderHidden(result.rows || [])
      })
      .fail(function (xhr) {
        $('#kpiHiddenList').html('<div class="kpi-hidden-error">' + $('<div>').text((xhr.responseJSON && xhr.responseJSON.error) || 'Не удалось загрузить скрытые KPI').html() + '</div>')
      })
  }

  $(function () {
    boards.forEach(initBoard)
    $(document).on('click', '.user-kpi-create', function (event) {
      event.preventDefault()
      event.stopPropagation()
      openForm($(this).attr('data-kpi-type'))
    })
    $(document).on('click', '.kpi-user-edit', function (event) {
      event.preventDefault()
      event.stopPropagation()
      var $button = $(this)
      var $card = $button.closest('.kpi-card')
      var $grid = $card.closest('.kpi-grid')
      var board = boards.find(function (item) {
        return item.key === $grid.attr('data-kpi-container')
      })
      var type = $button.attr('data-kpi-type') || (board ? board.type : '')
      openForm(type, Number($button.data('kpi-id')), $button.data('kpi-admin') === 1)
    })
    $(document).on('click', '.kpi-global-hide', function (event) {
      event.preventDefault()
      event.stopPropagation()
      var id = Number($(this).data('kpi-id'))
      if (!id || !window.confirm('Скрыть KPI-карточку?')) return
      var button = this
      $.post((window.APP_BASE || '') + '/api/kpi/card/' + id + '/hide', {
        csrf_token: window.APP_CSRF || '',
      })
        .done(function () {
          $(button).closest('.kpi-card').remove()
        })
        .fail(function (xhr) {
          window.alert((xhr.responseJSON && xhr.responseJSON.error) || 'Не удалось скрыть KPI')
        })
    })
    $(document).on('click', '.kpi-hidden-open', function (event) {
      var dialog = document.getElementById('kpiHiddenDialog')
      if (!dialog || typeof dialog.showModal !== 'function') return
      event.preventDefault()
      loadHidden()
      dialog.showModal()
    })
    $(document).on('click', '.kpi-hidden-close', function () {
      document.getElementById('kpiHiddenDialog').close()
    })
    $(document).on('click', '.kpi-hidden-restore', function () {
      var button = this
      var id = Number($(button).data('kpi-id'))
      if (!id) return
      $(button).prop('disabled', true)
      $.post((window.APP_BASE || '') + '/api/kpi/card/' + id + '/restore', {
        csrf_token: window.APP_CSRF || '',
      })
        .done(function () {
          $(button).closest('.kpi-hidden-row').remove()
          if (!$('#kpiHiddenList .kpi-hidden-row').length) renderHidden([])
          if (typeof window.loadKPI === 'function') window.loadKPI()
        })
        .fail(function (xhr) {
          window.alert((xhr.responseJSON && xhr.responseJSON.error) || 'Не удалось вернуть KPI')
          $(button).prop('disabled', false)
        })
    })
    $(document).on('click', '.kpi-form-close', closeCreateForm)
    $('#kpiFormDialog').on('click', function (event) {
      if (event.target === this) closeCreateForm()
    })
    $('#kpiFormDialog').on('cancel', function () {
      var frame = document.getElementById('kpiFormFrame')
      if (frame) frame.removeAttribute('src')
    })
    window.addEventListener('message', function (event) {
      if (event.origin !== window.location.origin || !event.data) return
      if (!['kpi:saved', 'kpi:deleted', 'kpi:cancel'].includes(event.data.type)) return
      closeCreateForm()
      if (event.data.type !== 'kpi:cancel' && typeof window.loadKPI === 'function') window.loadKPI()
    })
  })
})(jQuery)
