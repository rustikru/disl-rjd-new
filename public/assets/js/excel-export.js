/**
 * 1. Выгрузка плоских таблиц детализации (из виртуального массива данных)
 */
function saveExcelfromVT(tableId, filename) {
  // Проверяем, существует ли глобальный объект с данными виртуальных таблиц
  if (typeof _vtInline === 'undefined' || !_vtInline[tableId]) {
    console.error(
      'Ошибка экспорта: Данные для таблицы "' +
        tableId +
        '" не найдены в _vtInline.',
    )
    return
  }

  var vt = _vtInline[tableId]
  var cols = vt.cols || []
  var rows = vt.filtered || []  // было: vt.rows (неверно — поле называется filtered)

  // Упаковываем только нужные для бэкенда поля: ключ колонки и её название
  var cleanCols = cols.map(function (c) {
    return { key: c.key, label: c.label || c.title || '' }
  })

  // Отправляем данные POST-запросом через скрытую форму
  var BASE = window.APP_BASE || ''
  var form = document.createElement('form')
  form.method = 'POST'
  form.action = BASE + '/api/export/vt-table'
  form.style.display = 'none'

  var dataInput = document.createElement('input')
  dataInput.type = 'hidden'
  dataInput.name = 'export_data'
  dataInput.value = JSON.stringify({ cols: cleanCols, rows: rows })
  form.appendChild(dataInput)

  var nameInput = document.createElement('input')
  nameInput.type = 'hidden'
  nameInput.name = 'filename'
  nameInput.value = filename || 'детализация'
  form.appendChild(nameInput)

  document.body.appendChild(form)
  form.submit()
  form.remove()
}

/**
 * 2. Выгрузка сложных многоуровневых матриц (шахматок дашборда)
 *    Принимает tableId (DOM id сводной таблицы) и filename.
 *    Данные берёт из глобального _matrixData, который заполняется при загрузке сводной.
 */
function saveExcelMatrix(tableId, filename) {
  if (typeof _matrixData === 'undefined' || !_matrixData[tableId]) {
    console.error(
      'Ошибка экспорта матрицы: Данные для таблицы "' +
        tableId +
        '" не найдены в _matrixData. Попробуйте обновить данные.',
    )
    return
  }

  var data = _matrixData[tableId]
  var colGroups = data.col_groups
  var roads = data.roads

  // Создаем форму для отправки структуры на новый эндпоинт
  var BASE = window.APP_BASE || ''
  var form = document.createElement('form')
  form.method = 'POST'
  form.action = BASE + '/api/export/matrix'
  form.style.display = 'none'

  var dataInput = document.createElement('input')
  dataInput.type = 'hidden'
  dataInput.name = 'matrix_data'
  // Передаем исходное дерево объектов (группы колонок, дороги и вложенные станции)
  dataInput.value = JSON.stringify({ col_groups: colGroups, roads: roads })
  form.appendChild(dataInput)

  var nameInput = document.createElement('input')
  nameInput.type = 'hidden'
  nameInput.name = 'filename'
  nameInput.value = filename || 'Сводная_таблица'
  form.appendChild(nameInput)

  document.body.appendChild(form)
  form.submit()
  form.remove()
}
