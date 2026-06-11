// Настройки для построения таблицы детализации
var DETAIL_CONTEXTS = {
  approach: {
    label: 'Подход вагонов',
    endpoint: '/api/approach/detail',
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, mono: true },
      { key: 'wagon_type_code', label: 'Род вагона', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      { key: 'prev_cargo', label: 'Ранее выгружен', meta: true },
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
  departure: {
    label: 'Отправление вагонов',
    endpoint: '/api/departure/detail',
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, mono: true },
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
  loading: {
    label: 'Погрузка',
    endpoint: '/api/loading/detail',
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, mono: true },
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
  downtime: {
    label: 'Простои',
    endpoint: '/api/downtime/detail',
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, mono: true },
      { key: 'wagon_type_code', label: 'Тип', meta: true },
      { key: 'cargo_name', label: 'Груз', meta: true },
      { key: 'oper_station', label: 'Текущая станция', meta: true },
      { key: 'oper_road', label: 'Дорога', meta: true },
      { key: 'idle_time_days', label: 'Простой (сут.)', danger: true },
      { key: 'owner', label: 'Владелец', meta: true },
      { key: 'lessee', label: 'Арендатор', meta: true },
    ],
  },
  'raw-material': {
    label: 'Сырьё',
    endpoint: '/api/raw-material/detail',
    cols: [
      { key: 'wagon_no', label: '№ вагона', meta: true, mono: true },
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
