// Колонки и вкладки приходят из ReportRegistry.php.
var DETAIL_TABS = []
var DETAIL_CONTEXTS = {}

function moneyReportValue(value) {
  return value == null
    ? '—'
    : Number(value).toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
}

Object.keys(window.APP_REPORT_DEFINITIONS || {}).forEach(function (reportCode) {
  var reportDefinition = window.APP_REPORT_DEFINITIONS[reportCode]
  if (!DETAIL_TABS.length && reportDefinition.detail_tabs) {
    DETAIL_TABS = reportDefinition.detail_tabs
  }
  var columns = (reportDefinition.detail_columns || []).map(function (columnDefinition) {
    var column = Object.assign({}, columnDefinition)
    if (column.value_format === 'money') column.fmt = moneyReportValue
    return column
  })
  DETAIL_CONTEXTS[reportCode] = {
    label: reportDefinition.detail_label || reportDefinition.name,
    endpoint: reportDefinition.detail_endpoint,
    sort: reportDefinition.detail_sort || { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: columns,
  }
})
