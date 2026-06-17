// Настройки для построения таблицы детализации

var DETAIL_CONTEXTS = {
  /**** Дислокация — расширенная */
  dislocation: {
    label: 'Расширенная дислокация',
    endpoint: '/api/dislocation/detail',
    cols: [
      {
        key: 'wagon_no',
        label: '№ вагона',
        meta: true,
        type: 'number',
        w: 110,
      },
      { key: 'wagon_type_code', label: 'Тип вагона', meta: true, w: 160 },
      { key: 'train_no', label: 'Поезд №', meta: true, w: 90 },
      { key: 'oper_station', label: 'Тек. станция', meta: true, w: 150 },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145 },
      { key: 'dest_station', label: 'Ст. назнач.', meta: true, w: 145 },
      { key: 'cargo_name', label: 'Груз', meta: true, w: 150 },
      { key: 'park_type', label: 'Тип парка', meta: true, w: 125 },
      { key: 'oper_mnemonic', label: 'Операция', meta: true, w: 90 },
      {
        key: 'oper_dt',
        label: 'Дата операции',
        meta: true,
        w: 90,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
      },
      { key: 'idle_time_days', label: 'Простой (дн)', danger: true, w: 105 },
      {
        key: 'asoup_arrive_dt',
        label: 'Приб. (АСОУП)',
        meta: true,
        w: 130,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
      },
    ],
    sort: [{ field: 'wagon_no', type: 'number' }],
  },
  /**** Подход вагонов */
  approach: {
    label: 'Подход вагонов',
    endpoint: '/api/approach/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      {
        key: 'wagon_no',
        label: '№ вагона',
        meta: true,
        type: 'number',
        w: 110,
      },
      { key: 'wagon_type_code', label: 'Род вагона', meta: true, w: 120 },
      { key: 'cargo_name', label: 'Груз', meta: true, w: 150 },
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
    ],
  },
  /**** Отправление вагонов */
  departure: {
    label: 'Отправление вагонов',
    endpoint: '/api/departure/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      {
        key: 'wagon_no',
        label: '№ вагона',
        meta: true,
        type: 'number',
        w: 110,
      },
      { key: 'wagon_type_code', label: 'Тип', meta: true, w: 90 },
      { key: 'cargo_name', label: 'Груз', meta: true, w: 150 },
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
    ],
  },

  /**** Погрузка */
  loading: {
    label: 'Погрузка',
    endpoint: '/api/loading/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      {
        key: 'wagon_no',
        label: '№ вагона',
        meta: true,
        type: 'number',
        w: 110,
      },
      { key: 'wagon_type_code', label: 'Тип', meta: true, w: 90 },
      { key: 'cargo_name', label: 'Груз', meta: true, w: 150 },
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true, w: 100 },
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
    ],
  },

  /**** Простои */
  downtime: {
    label: 'Простои',
    endpoint: '/api/downtime/detail',
    sort: { field: 'idle_time_days', dir: 'desc' },
    cols: [
      {
        key: 'wagon_no',
        label: '№ вагона',
        meta: true,
        type: 'number',
        w: 110,
      },
      { key: 'wagon_type_code', label: 'Тип', meta: true, w: 90 },
      { key: 'cargo_name', label: 'Груз', meta: true, w: 150 },
      { key: 'oper_station', label: 'Текущая станция', meta: true, w: 155 },
      { key: 'oper_road', label: 'Дорога', meta: true, w: 150 },
      { key: 'idle_time_days', label: 'Простой (сут.)', danger: true, w: 115 },
      { key: 'owner', label: 'Владелец', meta: true, w: 160 },
      { key: 'lessee', label: 'Арендатор', meta: true, w: 160 },
    ],
  },
  /**** Сырьё */
  'raw-material': {
    label: 'Сырьё',
    endpoint: '/api/raw-material/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      {
        key: 'wagon_no',
        label: '№ вагона',
        meta: true,
        type: 'number',
        w: 110,
      },
      { key: 'wagon_type_code', label: 'Тип', meta: true, w: 90 },
      { key: 'cargo_name', label: 'Груз', meta: true, w: 150 },
      { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true, w: 100 },
      { key: 'idle_time_days', label: 'Простой (сут.)', danger: true, w: 115 },
      { key: 'oper_station', label: 'Тек. станция', meta: true, w: 155 },
      { key: 'oper_road', label: 'Дорога', meta: true, w: 150 },
      { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145 },
      { key: 'owner', label: 'Владелец', meta: true, w: 160 },
    ],
  },

  /**** Анализ за период */
  'analysis-period': {
    label: 'Анализ за период',
    endpoint: '/api/analysis/period/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: [
      {
        key: 'wagon_no',
        label: '№ вагона',
        meta: true,
        type: 'number',
      },
      { key: 'wagon_type_code', label: 'Тип вагона', meta: true, w: 120 },
      {
        key: 'depart_station',
        label: 'Станция отправления',
        meta: true,
      },
      { key: 'dest_station', label: 'Станция назначения', meta: true },
      { key: 'oper_station', label: 'Текущая станция', meta: true },

      {
        key: 'oper_dt',
        label: 'Дата операции',
        meta: true,
        formatData: 'DD.MM.YYYY HH24:MI:SS',
      },
      { key: 'operation', label: 'Операция', meta: true, w: 90 },
      { key: 'operation', label: 'Мнемоника операции', meta: true, w: 90 },
      { key: 'cargo_name', label: 'Груз', meta: true, w: 150 },
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
    ],
  },
}
