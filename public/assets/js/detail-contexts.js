// Настройки для построения таблицы детализации

// Вкладки drill-down страницы. Используются только в detail.php.
// Inline-детализация в основном приложении этот массив игнорирует.
var DETAIL_TABS = [
  { key: 'main', name: 'Основная информация' },
  { key: 'data-wagon', name: 'Данные о вагоне' },
  { key: 'disl-wagon', name: 'Дислокация вагона' },
  { key: 'techn-state-wagon', name: 'Техн. состояние' },
]

// Общие поля — начало (во всех детализациях)
// tab:       к какой вкладке drill-down относится поле (по умолчанию 'main')
// drillDown: показывать на странице drill-down или нет (по умолчанию true)

/* prettier-ignore */
var BASE_COLS = [
  // === ВКЛАДКА: ОСНОВНАЯ ИНФОРМАЦИЯ (main) ===
  { key: 'wagon_no', label: '№ вагона', meta: true, type: 'number', w: 110, tab: 'main', drillDown: true },
  { key: 'wagon_type_code', label: 'Тип вагона', meta: true, w: 120, tab: 'main', drillDown: true },
  { key: 'wagon_state', label: 'Состояние вагона', meta: true, w: 130, tab: 'main', drillDown: true }, // Добавлено
  { key: 'oper_station', label: 'Тек. станция', meta: true, w: 150, tab: 'main', drillDown: true },
  { key: 'oper_mnemonic', label: 'Операция', meta: true, w: 90, tab: 'main', drillDown: true },
  { key: 'idle_time_days', label: 'Простой (сут.)', right: true, w: 100, tab: 'main', drillDown: true }, // Добавлено
  { key: 'dest_station', label: 'Ст. назнач.', meta: true, w: 145, tab: 'main', drillDown: true },
  { key: 'cargo_name', label: 'Груз', meta: true, w: 150, tab: 'main', drillDown: true },
  { key: 'norm_delivery_dt', label: 'Срок доставки', meta: true, w: 130, tab: 'main', drillDown: true }, // Добавлено

  // === ВКЛАДКА: ДАННЫЕ О ВАГОНЕ (data-wagon) ===
  { key: 'waybill_no', label: '№ накладной', meta: true, w: 100, tab: 'data-wagon', drillDown: true },
  { key: 'waybill_id', label: 'ID накладной', meta: true, w: 100, tab: 'data-wagon', drillDown: false }, // Скрыт в drill-down
  { key: 'send_id', label: 'ID отправки', meta: true, w: 100, tab: 'data-wagon', drillDown: false }, // Добавлено
  { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true, w: 100, tab: 'data-wagon', drillDown: true },
  { key: 'container_nos', label: 'Номера контейнеров', right: true, w: 120, tab: 'data-wagon', drillDown: true },
  { key: 'consignor_name', label: 'Грузоотправитель', meta: true, w: 150, tab: 'data-wagon', drillDown: true }, // Добавлено
  { key: 'consignee_name', label: 'Грузополучатель', meta: true, w: 150, tab: 'data-wagon', drillDown: true }, // Добавлено

  // === ВКЛАДКА: ДИСЛОКАЦИЯ ВАГОНА (disl-wagon) ===
  { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145, tab: 'disl-wagon', drillDown: true },
  { key: 'oper_road', label: 'Дорога опер.', meta: true, w: 120, tab: 'disl-wagon', drillDown: true }, // Добавлено
  { key: 'oper_dt', label: 'Дата операции', meta: true, w: 130, tab: 'disl-wagon', drillDown: true }, // Добавлено
  { key: 'train_index', label: 'Индекс поезда', meta: true, w: 130, tab: 'disl-wagon', drillDown: true },
  { key: 'train_no', label: 'Поезд №', meta: true, w: 90, tab: 'disl-wagon', drillDown: true },
  { key: 'dist_remain_km', label: 'Остаток км', right: true, w: 100, tab: 'disl-wagon', drillDown: true }, // Добавлено
  { key: 'days_no_move', label: 'Дней без движ.', right: true, w: 110, tab: 'disl-wagon', drillDown: true } // Добавлено
]

// Общие поля — конец таблицы
var LESSEE_COLS = [
  {
    key: 'lessee',
    label: 'Арендатор',
    danger: true,
    w: 105,
    tab: 'data-wagon',
    drillDown: true,
  },
  {
    key: 'lease_home_station',
    label: 'Станция приписки арендатора',
    danger: true,
    w: 105,
    tab: 'data-wagon',
    drillDown: true,
  },
]

// Собирает колонки без дублей по key
function buildCols(specific) {
  var all = BASE_COLS.concat(specific || []).concat(LESSEE_COLS)
  var seen = {}
  return all.filter(function (col) {
    if (seen[col.key]) return false
    seen[col.key] = true
    return true
  })
}

var DETAIL_CONTEXTS = {
  /**** Дислокация — расширенная */
  dislocation: {
    label: 'Детализация',
    endpoint: '/api/dislocation/detail',
    sort: [{ field: 'wagon_no', type: 'number' }],
    cols: buildCols([
      {
        key: 'oper_dt',
        label: 'Дата операции',
        meta: true,
        w: 90,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
        tab: 'data-wagon',
      },
      {
        key: 'idle_time_days',
        label: 'Простой (дн)',
        danger: true,
        w: 105,
        tab: 'data-wagon',
      },
      {
        key: 'norm_delivery_dt',
        label: 'Нормат-я дата доставки',
        meta: true,
        w: 50,
        formatData: 'DD.MM.YYYY',
        tab: 'data-wagon',
      },
      {
        key: 'asoup_arrive_dt',
        label: 'Приб. (АСОУП)',
        meta: true,
        w: 130,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
        tab: 'data-wagon',
      },
    ]),
  },

  /**** Подход вагонов */
  approach: {
    label: 'Подход вагонов',
    endpoint: '/api/approach/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([
      {
        key: 'dist_remain_km',
        label: 'Ост. расстояние',
        right: true,
        w: 130,
        fmt: function (v) {
          var d = parseInt(v) || 0
          return d ? d.toLocaleString('ru-RU') + ' км' : ''
        },
      },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145 },
      { key: 'oper_station', label: 'Тек. станция', meta: true, w: 145 },
      { key: 'dest_station', label: 'Ст. назнач.', meta: true, w: 145 },
      { key: 'dest_road', label: 'Дорога назнач.', meta: true, w: 150 },
      {
        key: 'norm_delivery_dt',
        label: 'Норм. дата дост.',
        meta: true,
        w: 130,
      },
    ]),
  },

  /**** Отправление вагонов */
  departure: {
    label: 'Отправление вагонов',
    endpoint: '/api/departure/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true, w: 100 },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145 },
      { key: 'depart_road', label: 'Дорога отпр.', meta: true, w: 145 },
      { key: 'dest_station', label: 'Ст. назнач.', meta: true, w: 145 },
      { key: 'dest_road', label: 'Дорога назнач.', meta: true, w: 145 },
      { key: 'dist_remain_km', label: 'Ост. км', right: true, w: 100 },
      {
        key: 'norm_delivery_dt',
        label: 'Норм. дата дост.',
        meta: true,
        w: 125,
      },
    ]),
  },

  /**** Погрузка */
  loading: {
    label: 'Погрузка',
    endpoint: '/api/loading/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([
      { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145 },
      { key: 'depart_road', label: 'Дорога', meta: true, w: 140 },
      { key: 'dest_station', label: 'Ст. назнач.', meta: true, w: 145 },
      { key: 'oper_mnemonic', label: 'Операция', meta: true, w: 90 },
      {
        key: 'oper_dt',
        label: 'Дата опер.',
        meta: true,
        w: 110,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
      },
    ]),
  },

  /**** Простои */
  downtime: {
    label: 'Простои',
    endpoint: '/api/downtime/detail',
    sort: { field: 'idle_time_days', dir: 'desc' },
    cols: buildCols([
      { key: 'oper_station', label: 'Текущая станция', meta: true, w: 155 },
      { key: 'oper_road', label: 'Дорога', meta: true, w: 150 },

      {
        key: 'norm_delivery_dt',
        label: 'Нормат-я дата доставки',
        meta: true,
        w: 50,
        formatData: 'DD.MM.YYYY',
      },
      { key: 'idle_time_days', label: 'Простой (сут.)', danger: true, w: 115 },
      { key: 'owner', label: 'Владелец', meta: true, w: 160 },
    ]),
  },

  /**** Сырьё */
  'raw-material': {
    label: 'Сырьё',
    endpoint: '/api/raw-material/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true, w: 100 },
      { key: 'idle_time_days', label: 'Простой (сут.)', danger: true, w: 115 },
      { key: 'oper_station', label: 'Тек. станция', meta: true, w: 155 },
      { key: 'oper_road', label: 'Дорога', meta: true, w: 150 },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145 },
      { key: 'owner', label: 'Владелец', meta: true, w: 160 },
    ]),
  },

  /**** Анализ за период */
  'analysis-period': {
    label: 'Анализ за период',
    endpoint: '/api/analysis/period/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([
      { key: 'depart_station', label: 'Станция отправления', meta: true },
      { key: 'dest_station', label: 'Станция назначения', meta: true },
      { key: 'oper_station', label: 'Текущая станция', meta: true },
      {
        key: 'oper_dt',
        label: 'Дата операции',
        meta: true,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
      },
      { key: 'operation', label: 'Операция', meta: true, w: 90 },
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true, w: 100 },
      { key: 'waybill_no', label: '№ накладной', meta: true, w: 100 },
      { key: 'waybill_id', label: 'ID накладной', meta: true, w: 100 },
      {
        key: 'extra_waybill_no',
        label: '№ Досылочной накладной',
        meta: true,
        w: 100,
      },
      { key: 'train_index', label: '№ поезда', meta: true, w: 90 },
      { key: 'train_no', label: '№ поезда', meta: true, w: 90 },
      { key: 'consignor', label: 'Грузоотправитель', meta: true, w: 90 },
    ]),
  },
}
