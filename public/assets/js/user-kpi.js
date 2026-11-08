;(function ($) {
  'use strict'

  var boards = [
    { id: 'approachMetrics', type: 'approach_kpi' },
    { id: 'departureMetrics', type: 'departue_kpi' },
  ]

  function openForm(type, id, admin) {
    var url =
      (window.APP_BASE || '') +
      (id ? '/kpi/edit' : '/kpi/create') +
      '?kpi_type=' +
      encodeURIComponent(type)
    if (id) url += '&id=' + encodeURIComponent(id)
    if (admin) url += '&admin=1'
    window.location.href = url
  }

  function initBoard(board) {
    var $grid = $('#' + board.id)
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
        var page = row.page_code || ({ dashboard_kpi: 'dashboard', approach_kpi: 'approach', departue_kpi: 'departure', loading_kpi: 'loading' }[row.type] || '')
        var pageLabel = { dashboard: 'Дашборд', approach: 'Подход вагонов', departure: 'Отправление вагонов', loading: 'Погрузка' }[page] || 'KPI'
        return '<div class="kpi-hidden-row"><div><strong>' + $('<div>').text(row.label || '').html() + '</strong><span>' + pageLabel + (row.is_system === 'Y' ? ' · Системная' : ' · Общая') + '</span></div><button type="button" class="btn btn-ghost btn-sm kpi-hidden-restore" data-kpi-id="' + Number(row.id) + '">Вернуть</button></div>'
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
      var $grid = $card.closest('#approachMetrics, #departureMetrics')
      var board = boards.find(function (item) {
        return item.id === $grid.attr('id')
      })
      var type = $button.attr('data-kpi-type') || (board ? board.type : '')
      openForm(type, Number($button.data('kpi-id')), $button.data('kpi-admin') === 1)
    })
    $(document).on('click', '.kpi-user-delete', function (event) {
      event.preventDefault()
      event.stopPropagation()
      var id = Number($(this).data('kpi-id'))
      if (!id || !window.confirm('Удалить эту KPI-карточку?')) return
      var button = this
      $.ajax({
        url: (window.APP_BASE || '') + '/api/kpi/user/' + id,
        method: 'DELETE',
        data: { csrf_token: window.APP_CSRF || '' },
      })
        .done(function () {
          $(button).closest('.kpi-card').remove()
        })
        .fail(function (xhr) {
          window.alert(
            (xhr.responseJSON && xhr.responseJSON.error) ||
              'Не удалось удалить KPI',
          )
        })
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
  })
})(jQuery)
