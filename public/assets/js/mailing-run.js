(function () {
  'use strict'

  function resetRunForm(form) {
    var button = form.querySelector('[data-mailing-run-button]')
    form.dataset.mailingRunPending = '0'
    if (!button) return

    button.disabled = false
    button.classList.remove('is-loading')
    button.removeAttribute('aria-busy')
    button.setAttribute('aria-label', button.dataset.defaultLabel || 'Сформировать сейчас')
    button.title = button.dataset.defaultLabel || 'Сформировать сейчас'
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-mailing-run-form]')
    if (!form) return

    if (form.dataset.mailingRunPending === '1') {
      event.preventDefault()
      return
    }

    var button = form.querySelector('[data-mailing-run-button]')
    form.dataset.mailingRunPending = '1'
    if (!button) return

    button.dataset.defaultLabel = button.getAttribute('aria-label') || button.title || 'Сформировать сейчас'
    button.disabled = true
    button.classList.add('is-loading')
    button.setAttribute('aria-busy', 'true')
    button.setAttribute('aria-label', 'Рассылка запускается')
    button.title = 'Рассылка запускается'
  })

  window.addEventListener('pageshow', function () {
    document.querySelectorAll('[data-mailing-run-form]').forEach(resetRunForm)
  })
})()
