// Настройки для построения таблицы детализации
// Указываем поля из окончательного SELECT-a
var DETAIL_CONTEXTS = {
  /**** Дислокация — расширенная */
  dislocation: {
    label: 'Расширенная дислокация',
    endpoint: '/api/dislocation/detail',
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true },
      { key: 'wagon_type_code', label: 'Тип вагона', meta: true, mono: false },
      { key: 'train_index', label: 'Индекс поезда', meta: true },
      { key: 'train_no', label: '№ Поезда', meta: true },
      { key: 'oper_road', label: 'Дорога операции', meta: true },
      { key: 'oper_station', label: 'Станция операции', meta: true },
      { key: 'operation', label: 'Операция', meta: true },
      { key: 'oper_dt', label: 'Дата операции', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      { key: 'cargo_weight_kg', label: 'Масса груза (кг)', meta: true },

      { key: 'dest_road', label: 'Дорога назначения', meta: true },
      { key: 'dest_station', label: 'Станция назначения', meta: true },
      { key: 'consignor', label: 'Грузоотправитель', meta: true },
      { key: 'consignor_name', label: 'Грузоотправитель', meta: true },
    ],
    sort: [{ field: 'wagon_no', type: 'number' }],
  },
  /**** Подход вагонов */
  approach: {
    label: 'Подход вагонов',
    endpoint: '/api/approach/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, type: 'number' },
      { key: 'wagon_type_code', label: 'Род вагона', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      //{ key: 'prev_cargo', label: 'Ранее выгружен', meta: true },
      {
        key: 'dist_remain_km',
        label: 'Ост. расстояние',
        right: true,
        fmt: function (v) {
          var d = parseInt(v) || 0
          return d ? d.toLocaleString('ru-RU') + ' км' : ''
        },
      },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true },
      { key: 'oper_station', label: 'Тек. станция', meta: true },
      { key: 'dest_station', label: 'Ст. назнач.', meta: true },
      { key: 'dest_road', label: 'Дорога назнач.', meta: true },
      { key: 'norm_delivery_dt', label: 'Норм. дата дост.', meta: true },
    ],
  },
  /**** Отправление вагонов */
  departure: {
    label: 'Отправление вагонов',
    endpoint: '/api/departure/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, type: 'number' },
      { key: 'wagon_type_code', label: 'Тип', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true },
      { key: 'depart_road', label: 'Дорога отпр.', meta: true },
      { key: 'dest_station', label: 'Ст. назнач.', meta: true },
      { key: 'dest_road', label: 'Дорога назнач.', meta: true },
      { key: 'dist_remain_km', label: 'Ост. км', right: true },
      { key: 'norm_delivery_dt', label: 'Норм. дата дост.', meta: true },
    ],
  },

  /**** Погрузка */
  loading: {
    label: 'Погрузка',
    endpoint: '/api/loading/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, type: 'number' },
      { key: 'wagon_type_code', label: 'Тип', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true },
      { key: 'depart_road', label: 'Дорога', meta: true },
      { key: 'dest_station', label: 'Ст. назнач.', meta: true },
      { key: 'oper_mnemonic', label: 'Операция', meta: true },
      { key: 'oper_dt', label: 'Дата опер.', meta: true },
    ],
  },

  /**** Простои */
  downtime: {
    label: 'Простои',
    endpoint: '/api/downtime/detail',
    sort: { field: 'idle_time_days', dir: 'desc' },
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, type: 'number' },
      { key: 'wagon_type_code', label: 'Тип', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      { key: 'oper_station', label: 'Текущая станция', meta: true },
      { key: 'oper_road', label: 'Дорога', meta: true },
      { key: 'idle_time_days', label: 'Простой (сут.)', danger: true },
      { key: 'owner', label: 'Владелец', meta: true },
      { key: 'lessee', label: 'Арендатор', meta: true },
    ],
  },
  ///**** Сырьё */
  'raw-material': {
    label: 'Сырьё',
    endpoint: '/api/raw-material/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, type: 'number' },
      { key: 'wagon_type_code', label: 'Тип', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true },
      { key: 'idle_time_days', label: 'Простой (сут.)', danger: true },
      { key: 'oper_station', label: 'Тек. станция', meta: true },
      { key: 'oper_road', label: 'Дорога', meta: true },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true },
      { key: 'owner', label: 'Владелец', meta: true },
    ],
  },
}
