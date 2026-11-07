;(function ($) {
  'use strict'

  var boards = [
    { id: 'approachMetrics', type: 'approach_kpi' },
    { id: 'departureMetrics', type: 'departue_kpi' },
  ]

  function openForm(type, id) {
    var url =
      (window.APP_BASE || '') +
      (id ? '/kpi/edit' : '/kpi/create') +
      '?kpi_type=' +
      encodeURIComponent(type)
    if (id) url += '&id=' + encodeURIComponent(id)
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
      openForm(type, Number($button.data('kpi-id')))
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
  })
})(jQuery)
