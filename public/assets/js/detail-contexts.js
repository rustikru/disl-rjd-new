// Настройки для построения таблицы детализации

// Вкладки drill-down страницы. Используются только в detail.php.
// Inline-детализация в основном приложении этот массив игнорирует.
var DETAIL_TABS = [
  { key: 'main',              name: 'Основная информация' },
  { key: 'data-wagon',        name: 'Данные о вагоне' },
  { key: 'disl-wagon',        name: 'Дислокация вагона' },
  { key: 'techn-state-wagon', name: 'Техн. состояние' },
]

// Общие поля — начало (во всех детализациях)
// tab: к какой вкладке drill-down относится поле (по умолчанию 'main')
var BASE_COLS = [
  { key: 'wagon_no',        label: '№ вагона',           meta: true, type: 'number', w: 110, tab: 'main' },
  { key: 'wagon_type_code', label: 'Тип вагона',         meta: true, w: 120,  tab: 'data-wagon' },
  { key: 'park_type',       label: 'Тип парка',          meta: true, w: 125,  tab: 'data-wagon' },
  { key: 'cargo_name',      label: 'Груз',               meta: true, w: 150,  tab: 'main' },
  { key: 'cargo_weight_kg', label: 'Вес (кг)',           right: true, w: 100, tab: 'main' },
  { key: 'container_nos',   label: 'Номера контейнеров', right: true, w: 100, tab: 'data-wagon' },
  { key: 'waybill_no',      label: '№ накладной',        meta: true, w: 100,  tab: 'data-wagon' },
  { key: 'waybill_id',      label: 'ID накладной',       meta: true, w: 100,  tab: 'data-wagon' },
  { key: 'oper_station',    label: 'Тек. станция',       meta: true, w: 150,  tab: 'main' },
  { key: 'depart_station',  label: 'Ст. отправл.',       meta: true, w: 145,  tab: 'disl-wagon' },
  { key: 'dest_station',    label: 'Ст. назнач.',        meta: true, w: 145,  tab: 'main' },
  { key: 'oper_mnemonic',   label: 'Операция',           meta: true, w: 90,   tab: 'disl-wagon' },
  { key: 'train_index',     label: 'Индекс поезда',      meta: true, w: 90,   tab: 'disl-wagon' },
  { key: 'train_no',        label: 'Поезд №',            meta: true, w: 90,   tab: 'disl-wagon' },
]

// Общие поля — конец таблицы
var LESSEE_COLS = [
  { key: 'lessee', label: 'Арендатор', danger: true, w: 105 },
  {
    key: 'lease_home_station',
    label: 'Станция приписки арендатора',
    danger: true,
    w: 105,
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
      },
      { key: 'idle_time_days', label: 'Простой (дн)', danger: true, w: 105 },
      {
        key: 'norm_delivery_dt',
        label: 'Нормат-я дата доставки',
        meta: true,
        w: 50,
        formatData: 'DD.MM.YYYY',
      },
      {
        key: 'asoup_arrive_dt',
        label: 'Приб. (АСОУП)',
        meta: true,
        w: 130,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
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
