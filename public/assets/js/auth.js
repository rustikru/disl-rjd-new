'use strict';

// Показываем спиннер при отправке формы
var form = document.getElementById('loginForm');
if (form) {
  form.addEventListener('submit', function () {
    var btn = document.getElementById('submitBtn');
    if (btn) btn.classList.add('loading');
  });
}

// Enter → submit
document.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') {
    var f = document.getElementById('loginForm');
    if (f) f.submit();
  }
});
