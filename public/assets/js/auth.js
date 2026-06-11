'use strict'

var form = document.getElementById('loginForm')
if (form) {
  form.addEventListener('submit', function () {
    var btn = document.getElementById('submitBtn')
    if (btn) btn.classList.add('loading')
  })
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') {
    var f = document.getElementById('loginForm')
    if (f) f.submit()
  }
})
