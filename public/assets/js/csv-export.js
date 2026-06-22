// Утилиты экспорта в CSV — подключается в app.php и detail.php

// Базовый экспорт: массив колонок {key, label, fmt?} + массив строк → CSV-файл
function saveCSVFromData(cols, rows, filename) {
  function cleanCell(v) {
    return '"' + String(v == null ? '' : v).trim().replace(/\r?\n|\r/g, ' ').replace(/"/g, '""') + '"';
  }
  var lines = [cols.map(function (c) { return cleanCell(c.label); }).join(';')];
  rows.forEach(function (row) {
    lines.push(cols.map(function (c) {
      var v = row[c.key];
      return cleanCell(c.fmt ? c.fmt(v) : v);
    }).join(';'));
  });
  var blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = (filename || 'данные') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

// Экспорт inline-виртуальной таблицы (app.js) — использует глобальный _vtInline
function saveCSVfromVT(tableId, filename) {
  var vt = typeof _vtInline !== 'undefined' && _vtInline[tableId];
  if (!vt || !vt.filtered.length) return;
  saveCSVFromData(vt.cols, vt.filtered, filename);
}

// Экспорт обычной HTML-таблицы по её DOM id
function saveCSV(tableId, filename) {
  var table = document.getElementById(tableId);
  if (!table) return;
  var rows = [];
  table.querySelectorAll('tr').forEach(function (tr) {
    if (tr.offsetParent === null || getComputedStyle(tr).display === 'none') return;
    var cells = [];
    tr.querySelectorAll('th, td').forEach(function (cell) {
      var clone = cell.cloneNode(true);
      clone.querySelectorAll('.toggle-icon').forEach(function (el) { el.remove(); });
      var val = clone.textContent.trim().replace(/\r?\n|\r/g, ' ').replace(/"/g, '""');
      cells.push('"' + val + '"');
    });
    rows.push(cells.join(';'));
  });
  var blob = new Blob(['﻿' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = (filename || 'таблица') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}
