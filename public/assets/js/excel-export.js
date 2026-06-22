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
  var rows = vt.rows || []

  // Упаковываем только нужные для бэкенда поля: ключ колонки и её название
  var cleanCols = cols.map(function (c) {
    return { key: c.key, label: c.label || c.title || '' }
  })

  // Отправляем данные POST-запросом через скрытую форму
  var form = document.createElement('form')
  form.method = 'POST'
  form.action = '/api/export/vt-table'
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
 */
function saveExcelMatrix(colGroups, roads, filename) {
  if (!colGroups || !roads) {
    console.error(
      'Ошибка экспорта матрицы: Отсутствуют данные col_groups или roads.',
    )
    return
  }

  // Создаем форму для отправки структуры на новый эндпоинт
  var form = document.createElement('form')
  form.method = 'POST'
  form.action = '/api/export/matrix'
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
