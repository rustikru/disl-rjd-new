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
// drillDown: показывать поле на вкладке «расширенная дислокация» или нет
//            (по умолчанию true). На странице детализации (detail.php) поле
//            показывается независимо от этого флага.
//
// ПРАВИЛО ПО drillDown:
//   drillDown: false — только ключевые оперативные поля (с ними работают постоянно).
//   drillDown: false— справочные/технические реквизиты: видны в детализации,
//                      но НЕ выводятся на «расширенную дислокацию».

/* prettier-ignore */
var BASE_COLS = [
  // ============================================================
  // ВКЛАДКА: ОСНОВНАЯ ИНФОРМАЦИЯ (main) — ключевые оперативные поля
  // ============================================================
  { key: 'wagon_no', label: '№ вагона', meta: true, type: 'number', w: 110, tab: 'main', drillDown: false},
  { key: 'wagon_type_code', label: 'Тип вагона', meta: true, w: 120, tab: 'main', drillDown: false},
  { key: 'wagon_state', label: 'Состояние вагона', meta: true, w: 130, tab: 'main', drillDown: false},
  { key: 'oper_station', label: 'Тек. станция', meta: true, w: 150, tab: 'main', drillDown: false},
  { key: 'oper_mnemonic', label: 'Операция', meta: true, w: 90, tab: 'main', drillDown: false},
  { key: 'idle_time_days', label: 'Простой (сут.)', right: true, w: 100, tab: 'main', drillDown: false},
  { key: 'dest_station', label: 'Ст. назнач.', meta: true, w: 145, tab: 'main', drillDown: false},
  { key: 'cargo_name', label: 'Груз', meta: true, w: 150, tab: 'main', drillDown: false},
  { key: 'norm_delivery_dt', label: 'Срок доставки', meta: true, w: 130, tab: 'main', drillDown: false},

  // ============================================================
  // ВКЛАДКА: ДАННЫЕ О ВАГОНЕ (data-wagon) — раздел 1 справки (кол. 1–31)
  // ============================================================
  // — оперативные (drillDown: true) —
  { key: 'waybill_no', label: '№ накладной', meta: true, w: 100, tab: 'data-wagon', drillDown: false},
  { key: 'cargo_weight_kg', label: 'Вес (кг)', right: true, w: 100, tab: 'data-wagon', drillDown: false},
  { key: 'container_nos', label: 'Номера контейнеров', right: true, w: 120, tab: 'data-wagon', drillDown: false},
  { key: 'consignor_name', label: 'Грузоотправитель', meta: true, w: 150, tab: 'data-wagon', drillDown: false},
  { key: 'consignee_name', label: 'Грузополучатель', meta: true, w: 150, tab: 'data-wagon', drillDown: false},
  // — идентификаторы документов (скрыты на «расширенной дислокации») —
  { key: 'waybill_id', label: 'ID накладной', meta: true, w: 100, tab: 'data-wagon', drillDown: false},
  { key: 'send_id', label: 'ID отправки', meta: true, w: 100, tab: 'data-wagon', drillDown: false},
  { key: 'extra_waybill_no', label: '№ досыл. накладной', meta: true, w: 120, tab: 'data-wagon', drillDown: false},
  { key: 'extra_send_id', label: 'ID досылки', meta: true, w: 100, tab: 'data-wagon', drillDown: false},
  // — справочные реквизиты раздела 1 —
  { key: 'owner_admin', label: 'Адм. собственника', meta: true, w: 150, tab: 'data-wagon', drillDown: false},
  { key: 'trip_start_dt', label: 'Дата начала рейса', meta: true, w: 130, formatData: 'DD.MM.YYYY HH24:MI', tab: 'data-wagon', drillDown: false},
  { key: 'trip_end_dt', label: 'Дата оконч. рейса', meta: true, w: 130, formatData: 'DD.MM.YYYY HH24:MI', tab: 'data-wagon', drillDown: false},
  { key: 'depart_state', label: 'Гос-во отправл.', meta: true, w: 150, tab: 'data-wagon', drillDown: false},
  { key: 'dest_state', label: 'Гос-во назнач.', meta: true, w: 150, tab: 'data-wagon', drillDown: false},
  { key: 'consignor', label: 'Грузоотправитель (полн.)', meta: true, w: 180, tab: 'data-wagon', drillDown: false},
  { key: 'consignor_tgnl', label: 'ТГНЛ грузоотпр.', meta: true, w: 110, tab: 'data-wagon', drillDown: false},
  { key: 'consignor_okpo', label: 'ОКПО грузоотпр.', meta: true, w: 120, tab: 'data-wagon', drillDown: false},
  { key: 'consignee', label: 'Грузополучатель (полн.)', meta: true, w: 180, tab: 'data-wagon', drillDown: false},
  { key: 'consignee_tgnl', label: 'ТГНЛ грузопол.', meta: true, w: 110, tab: 'data-wagon', drillDown: false},
  { key: 'consignee_okpo', label: 'ОКПО грузопол.', meta: true, w: 120, tab: 'data-wagon', drillDown: false},
  { key: 'cargo_gng', label: 'Код ГНГ', meta: true, w: 100, tab: 'data-wagon', drillDown: false},
  { key: 'mileage_loaded_km', label: 'Пробег гружёный (км)', right: true, w: 130, tab: 'data-wagon', drillDown: false},
  { key: 'mileage_empty_km', label: 'Пробег порожний (км)', right: true, w: 130, tab: 'data-wagon', drillDown: false},
  { key: 'mileage_total_km', label: 'Пробег общий (км)', right: true, w: 120, tab: 'data-wagon', drillDown: false},
  { key: 'mileage_norm_km', label: 'Пробег норм. (км)', right: true, w: 120, tab: 'data-wagon', drillDown: false},
  { key: 'mileage_remain_km', label: 'Пробег остаток (км)', right: true, w: 130, tab: 'data-wagon', drillDown: false},
  { key: 'mileage_sign', label: 'Знак пробега', meta: true, w: 110, tab: 'data-wagon', drillDown: false},
  { key: 'special_marks', label: 'Особые отметки', meta: true, w: 130, tab: 'data-wagon', drillDown: false},
  { key: 'prev_cargo', label: 'Предыдущий груз', meta: true, w: 180, tab: 'data-wagon', drillDown: false},

  // ============================================================
  // ВКЛАДКА: ДИСЛОКАЦИЯ ВАГОНА (disl-wagon) — раздел 2 справки (кол. 32–60)
  // ============================================================
  // — оперативные (drillDown: true) —
  { key: 'depart_station', label: 'Ст. отправл.', meta: true, w: 145, tab: 'disl-wagon', drillDown: false},
  { key: 'oper_road', label: 'Дорога опер.', meta: true, w: 120, tab: 'disl-wagon', drillDown: false},
  { key: 'oper_dt', label: 'Дата операции', meta: true, w: 130, formatData: 'DD.MM.YYYY HH24:MI:SS', tab: 'disl-wagon', drillDown: false},
  { key: 'train_index', label: 'Индекс поезда', meta: true, w: 130, tab: 'disl-wagon', drillDown: false},
  { key: 'train_no', label: 'Поезд №', meta: true, w: 90, tab: 'disl-wagon', drillDown: false},
  { key: 'dist_remain_km', label: 'Остаток км', right: true, w: 100, tab: 'disl-wagon', drillDown: false},
  // — справочные реквизиты раздела 2 —
  { key: 'operation', label: 'Операция (полн.)', meta: true, w: 180, tab: 'disl-wagon', drillDown: false},
  { key: 'depart_road', label: 'Дорога отправл.', meta: true, w: 130, tab: 'disl-wagon', drillDown: false},
  { key: 'dest_road', label: 'Дорога назнач.', meta: true, w: 130, tab: 'disl-wagon', drillDown: false},
  { key: 'park_type', label: 'Признак парка', meta: true, w: 180, tab: 'disl-wagon', drillDown: false},
  { key: 'handover_road', label: 'Дорога сдачи', meta: true, w: 120, tab: 'disl-wagon', drillDown: false},
  { key: 'receive_road', label: 'Дорога приёма', meta: true, w: 120, tab: 'disl-wagon', drillDown: false},
  { key: 'wagon_in_train', label: 'Позиция в составе', right: true, w: 120, tab: 'disl-wagon', drillDown: false},
  { key: 'park_no', label: '№ парка', meta: true, w: 90, tab: 'disl-wagon', drillDown: false},
  { key: 'track_no', label: '№ пути', meta: true, w: 90, tab: 'disl-wagon', drillDown: false},
  { key: 'seals_count', label: 'Пломб', right: true, w: 80, tab: 'disl-wagon', drillDown: false},
  { key: 'loaded_containers', label: 'Гружёных конт.', right: true, w: 110, tab: 'disl-wagon', drillDown: false},
  { key: 'empty_containers', label: 'Порожних конт.', right: true, w: 110, tab: 'disl-wagon', drillDown: false},
  { key: 'dist_passed_km', label: 'Пройдено (км)', right: true, w: 110, tab: 'disl-wagon', drillDown: false},
  { key: 'dist_total_km', label: 'Всего (км)', right: true, w: 100, tab: 'disl-wagon', drillDown: false},
  { key: 'idle_time_hhmmss', label: 'Простой (ЧЧ:ММ:СС)', meta: true, w: 130, tab: 'disl-wagon', drillDown: false},
  { key: 'asoup_depart_dt', label: 'Отпр. (АСОУП)', meta: true, w: 130, formatData: 'DD.MM.YYYY HH24:MI:SS', tab: 'disl-wagon', drillDown: false},
  { key: 'asoup_arrive_dt', label: 'Приб. (АСОУП)', meta: true, w: 130, formatData: 'DD.MM.YYYY HH24:MI:SS', tab: 'disl-wagon', drillDown: false},

  // ============================================================
  // ВКЛАДКА: ТЕХН. СОСТОЯНИЕ (techn-state-wagon) — раздел 3 справки (кол. 61–126)
  // ============================================================
  // — оперативные (drillDown: true) —
  { key: 'days_no_move', label: 'Дней без движ.', right: true, w: 110, tab: 'techn-state-wagon', drillDown: false},
  // — собственник / владелец —
  { key: 'owner', label: 'Собственник', meta: true, w: 160, tab: 'techn-state-wagon', drillDown: false},
  { key: 'owner_okpo', label: 'ОКПО собственника', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'owner_local_code', label: 'Местный код собств.', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'home_road', label: 'Дорога приписки', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'home_depot', label: 'Депо приписки', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  { key: 'home_station', label: 'Станция приписки', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  // — состояние —
  { key: 'quality_sign', label: 'Признак качества', meta: true, w: 180, tab: 'techn-state-wagon', drillDown: false},
  { key: 'state_assign_dt', label: 'Дата присв. состояния', meta: true, w: 140, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'state_reason', label: 'Причина состояния', meta: true, w: 170, tab: 'techn-state-wagon', drillDown: false},
  { key: 'state_station', label: 'Станция состояния', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  { key: 'no_transit_reason', label: 'Причина запрета транзита', meta: true, w: 180, tab: 'techn-state-wagon', drillDown: false},
  // — постройка / ремонты —
  { key: 'reg_date', label: 'Дата регистрации', meta: true, w: 130, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'build_date', label: 'Дата постройки', meta: true, w: 130, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'next_repair_dt', label: 'След. ремонт (дата)', meta: true, w: 130, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'next_repair_type', label: 'След. ремонт (тип)', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  { key: 'last_cap_repair_depot', label: 'Депо посл. кап. рем.', meta: true, w: 160, tab: 'techn-state-wagon', drillDown: false},
  { key: 'last_cap_repair_dt', label: 'Дата посл. кап. рем.', meta: true, w: 140, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'last_dep_repair_depot', label: 'Депо посл. деп. рем.', meta: true, w: 160, tab: 'techn-state-wagon', drillDown: false},
  { key: 'last_dep_repair_dt', label: 'Дата посл. деп. рем.', meta: true, w: 140, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'days_to_repair', label: 'Дней до ремонта', right: true, w: 120, tab: 'techn-state-wagon', drillDown: false},
  { key: 'days_no_oper', label: 'Дней без операций', right: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'repair_by_mileage', label: 'Ремонт по пробегу', right: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'threshold_sign', label: 'Признак порога', meta: true, w: 180, tab: 'techn-state-wagon', drillDown: false},
  // — паспорт / модель —
  { key: 'factory_no', label: 'Заводской №', meta: true, w: 110, tab: 'techn-state-wagon', drillDown: false},
  { key: 'manufacturer', label: 'Завод-изготовитель', meta: true, w: 170, tab: 'techn-state-wagon', drillDown: false},
  { key: 'wagon_type_name', label: 'Наим. типа вагона', meta: true, w: 160, tab: 'techn-state-wagon', drillDown: false},
  { key: 'wagon_model', label: 'Модель вагона', meta: true, w: 120, tab: 'techn-state-wagon', drillDown: false},
  { key: 'wagon_model_code', label: 'Код модели', meta: true, w: 100, tab: 'techn-state-wagon', drillDown: false},
  { key: 'wagon_type_cond', label: 'Условный тип вагона', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  { key: 'tare_weight', label: 'Масса тары', right: true, w: 100, tab: 'techn-state-wagon', drillDown: false},
  { key: 'load_capacity', label: 'Грузоподъёмность', right: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'length_mm', label: 'Длина (мм)', right: true, w: 100, tab: 'techn-state-wagon', drillDown: false},
  { key: 'axles_count', label: 'Кол-во осей', right: true, w: 100, tab: 'techn-state-wagon', drillDown: false},
  { key: 'body_material_code', label: 'Код материала кузова', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'body_material_name', label: 'Материал кузова', meta: true, w: 160, tab: 'techn-state-wagon', drillDown: false},
  { key: 'body_volume', label: 'Объём кузова (м³)', right: true, w: 120, tab: 'techn-state-wagon', drillDown: false},
  { key: 'clearance', label: 'Габарит', meta: true, w: 100, tab: 'techn-state-wagon', drillDown: false},
  { key: 'boiler_caliber', label: 'Калибр котла', right: true, w: 110, tab: 'techn-state-wagon', drillDown: false},
  // — техническое оснащение —
  { key: 'air_dist_type', label: 'Воздухораспред.', meta: true, w: 140, tab: 'techn-state-wagon', drillDown: false},
  { key: 'automode', label: 'Авторежим', meta: true, w: 140, tab: 'techn-state-wagon', drillDown: false},
  { key: 'auto_lever', label: 'Авторычаг', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'brake_type', label: 'Тип тормоза', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'coupler_type', label: 'Тип автосцепки', meta: true, w: 140, tab: 'techn-state-wagon', drillDown: false},
  { key: 'bogie_model', label: 'Модели тележек', meta: true, w: 170, tab: 'techn-state-wagon', drillDown: false},
  { key: 'shock_absorber', label: 'Поглощ. аппарат', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  { key: 'drain_device', label: 'Сливной прибор', meta: true, w: 140, tab: 'techn-state-wagon', drillDown: false},
  { key: 'lever_gear', label: 'Рычажная передача', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  // — срок службы / аренда (поля) —
  { key: 'service_life', label: 'Срок службы (до)', meta: true, w: 130, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'life_ext_sign', label: 'Признак продл. срока', right: true, w: 140, tab: 'techn-state-wagon', drillDown: false},
  { key: 'life_ext_date', label: 'Дата продл. срока', meta: true, w: 130, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'lease_sign', label: 'Признак аренды', right: true, w: 120, tab: 'techn-state-wagon', drillDown: false},
  { key: 'lessee_okpo', label: 'ОКПО арендатора', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'lessee_local_code', label: 'Местный код аренд.', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  { key: 'lease_end_date', label: 'Дата оконч. аренды', meta: true, w: 140, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  // — оператор по доверенности —
  { key: 'proxy_operator', label: 'Оператор по дов.', meta: true, w: 160, tab: 'techn-state-wagon', drillDown: false},
  { key: 'proxy_operator_okpo', label: 'ОКПО оператора', meta: true, w: 130, tab: 'techn-state-wagon', drillDown: false},
  // — исключение / прочее —
  { key: 'exclude_date', label: 'Дата исключения', meta: true, w: 130, formatData: 'DD.MM.YYYY', tab: 'techn-state-wagon', drillDown: false},
  { key: 'exclude_depot', label: 'Депо исключения', meta: true, w: 150, tab: 'techn-state-wagon', drillDown: false},
  { key: 'exclude_reason', label: 'Причина исключения', meta: true, w: 170, tab: 'techn-state-wagon', drillDown: false},
  { key: 'prev_wagon_no', label: 'Предыдущий № вагона', meta: true, w: 140, tab: 'techn-state-wagon', drillDown: false},
  // — дубли из справки (кол. 61 и 119), оставлены для полноты —
  { key: 'wagon_no2', label: '№ вагона (дубль)', meta: true, w: 110, tab: 'techn-state-wagon', drillDown: false},
  { key: 'wagon_type_code2', label: 'Тип вагона (дубль)', meta: true, w: 120, tab: 'techn-state-wagon', drillDown: false}
]

// Общие поля — конец таблицы (арендатор → вкладка «Техн. состояние», подсветка danger)
var LESSEE_COLS = [
  {
    key: 'lessee',
    label: 'Арендатор',
    danger: true,
    w: 105,
    tab: 'techn-state-wagon',
    drillDown: true,
  },
  {
    key: 'lease_home_station',
    label: 'Станция приписки арендатора',
    danger: true,
    w: 105,
    tab: 'techn-state-wagon',
    drillDown: true,
  },
]

// Собирает колонки без дублей по key.
// ВНИМАНИЕ: при дубле побеждает первое вхождение, т.е. BASE_COLS.
// Поэтому если в specific нужно переопределить формат/стиль базового поля —
// его сначала надо убрать из BASE_COLS, иначе specific-вариант будет проигнорирован.
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
    // Все нужные поля теперь в BASE_COLS, поэтому специфичных колонок нет.
    cols: buildCols([]),
  },

  /**** Подход вагонов */
  approach: {
    label: 'Подход вагонов',
    endpoint: '/api/approach/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([
      // Переопределяем формат остатка расстояния (km с разделителями).
      // dist_remain_km есть в BASE_COLS → этот вариант дедупликация отбросит.
      // Если формат нужен именно здесь — убрать dist_remain_km из BASE_COLS.
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
    ]),
  },

  /**** Отправление вагонов */
  departure: {
    label: 'Отправление вагонов',
    endpoint: '/api/departure/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([]),
  },

  /**** Погрузка */
  loading: {
    label: 'Погрузка',
    endpoint: '/api/loading/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([]),
  },

  /**** Простои */
  downtime: {
    label: 'Простои',
    endpoint: '/api/downtime/detail',
    sort: { field: 'idle_time_days', dir: 'desc' },
    cols: buildCols([]),
  },

  /**** Сырьё */
  'raw-material': {
    label: 'Сырьё',
    endpoint: '/api/raw-material/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([]),
  },

  /**** Анализ за период */
  'analysis-period': {
    label: 'Анализ за период',
    endpoint: '/api/analysis/period/detail',
    sort: { field: 'wagon_no', type: 'number', dir: 'asc' },
    cols: buildCols([]),
  },
}
