<?php
declare(strict_types=1);

// Единое описание порядка, ключей и исходных подписей 126 колонок справки РЖД.
return array(
  0 =>
    array(
      'key' => 'wagon_no',
      'label' => 'Номер вагона',
      'w' => 180,
      'showInline' => true,
    ),
  1 =>
    array(
      'key' => 'waybill_no',
      'label' => 'Номер накладной',
      'w' => 180,
      'showInline' => true,
    ),
  2 =>
    array(
      'key' => 'wagon_type_code',
      'label' => 'Род вагона',
      'w' => 180,
      'showInline' => true,
    ),
  3 =>
    array(
      'key' => 'owner_admin',
      'label' => 'Администрация собственника',
      'w' => 180,
      'showInline' => true,
    ),
  4 =>
    array(
      'key' => 'trip_start_dt',
      'label' => 'Дата и время начала рейса',
      'w' => 180,
      'showInline' => true,
    ),
  5 =>
    array(
      'key' => 'depart_state',
      'label' => 'Государство отправления',
      'w' => 180,
      'showInline' => true,
    ),
  6 =>
    array(
      'key' => 'depart_road',
      'label' => 'Дорога отправления',
      'w' => 180,
      'showInline' => true,
    ),
  7 =>
    array(
      'key' => 'depart_station',
      'label' => 'Станция отправления',
      'w' => 180,
      'showInline' => true,
    ),
  8 =>
    array(
      'key' => 'trip_end_dt',
      'label' => 'Дата и время окончания рейса',
      'w' => 180,
      'showInline' => true,
    ),
  9 =>
    array(
      'key' => 'dest_state',
      'label' => 'Государство назначения',
      'w' => 180,
      'showInline' => true,
    ),
  10 =>
    array(
      'key' => 'dest_road',
      'label' => 'Дорога назначения',
      'w' => 180,
      'showInline' => true,
    ),
  11 =>
    array(
      'key' => 'dest_station',
      'label' => 'Станция назначения',
      'w' => 180,
      'showInline' => true,
    ),
  12 =>
    array(
      'key' => 'consignor_tgnl',
      'label' => 'Грузоотправитель (ТГНЛ)',
      'w' => 180,
      'showInline' => true,
    ),
  13 =>
    array(
      'key' => 'consignor',
      'label' => 'Грузоотправитель',
      'w' => 180,
      'showInline' => true,
    ),
  14 =>
    array(
      'key' => 'consignor_okpo',
      'label' => 'Грузоотправитель (ОКПО)',
      'w' => 180,
      'showInline' => true,
    ),
  15 =>
    array(
      'key' => 'consignor_name',
      'label' => 'Грузоотправитель (наим)',
      'w' => 180,
      'showInline' => true,
    ),
  16 =>
    array(
      'key' => 'consignee_tgnl',
      'label' => 'Грузополучатель (ТГНЛ)',
      'w' => 180,
      'showInline' => true,
    ),
  17 =>
    array(
      'key' => 'consignee',
      'label' => 'Грузополучатель',
      'w' => 180,
      'showInline' => true,
    ),
  18 =>
    array(
      'key' => 'consignee_okpo',
      'label' => 'Грузополучатель (ОКПО)',
      'w' => 180,
      'showInline' => true,
    ),
  19 =>
    array(
      'key' => 'consignee_name',
      'label' => 'Грузополучатель (наим)',
      'w' => 180,
      'showInline' => true,
    ),
  20 =>
    array(
      'key' => 'cargo_name',
      'label' => 'Наименование груза',
      'w' => 180,
      'showInline' => true,
    ),
  21 =>
    array(
      'key' => 'cargo_gng',
      'label' => 'Код груза ГНГ',
      'w' => 180,
      'showInline' => true,
    ),
  22 =>
    array(
      'key' => 'cargo_weight_kg',
      'label' => 'Вес груза (кг)',
      'w' => 180,
      'showInline' => true,
    ),
  23 =>
    array(
      'key' => 'mileage_loaded_km',
      'label' => 'Пробег в груженом состоянии (км)',
      'w' => 180,
      'showInline' => true,
    ),
  24 =>
    array(
      'key' => 'mileage_empty_km',
      'label' => 'Пробег в порожнем состоянии (км)',
      'w' => 180,
      'showInline' => true,
    ),
  25 =>
    array(
      'key' => 'mileage_total_km',
      'label' => 'Пробег общий (км)',
      'w' => 180,
      'showInline' => true,
    ),
  26 =>
    array(
      'key' => 'mileage_norm_km',
      'label' => 'Норматив величины пробега (км)',
      'w' => 180,
      'showInline' => true,
    ),
  27 =>
    array(
      'key' => 'mileage_remain_km',
      'label' => 'Остаток пробега (км)',
      'w' => 180,
      'showInline' => true,
    ),
  28 =>
    array(
      'key' => 'mileage_sign',
      'label' => 'Признак пробега',
      'w' => 180,
      'showInline' => true,
    ),
  29 =>
    array(
      'key' => 'special_marks',
      'label' => 'Особые отметки',
      'w' => 180,
      'showInline' => true,
    ),
  30 =>
    array(
      'key' => 'prev_cargo',
      'label' => 'Ранее выгруженный груз',
      'w' => 180,
      'showInline' => true,
    ),
  31 =>
    array(
      'key' => 'oper_station',
      'label' => 'Станция операции',
      'w' => 180,
      'showInline' => true,
    ),
  32 =>
    array(
      'key' => 'oper_road',
      'label' => 'Дорога операции',
      'w' => 180,
      'showInline' => true,
    ),
  33 =>
    array(
      'key' => 'operation',
      'label' => 'Операция',
      'w' => 180,
      'showInline' => true,
    ),
  34 =>
    array(
      'key' => 'oper_mnemonic',
      'label' => 'Мнемокод операции',
      'w' => 180,
      'showInline' => true,
    ),
  35 =>
    array(
      'key' => 'oper_dt',
      'label' => 'Дата и время операции',
      'w' => 180,
      'showInline' => true,
    ),
  36 =>
    array(
      'key' => 'park_type',
      'label' => 'Тип парка',
      'w' => 180,
      'showInline' => true,
    ),
  37 =>
    array(
      'key' => 'handover_road',
      'label' => 'Дорога сдачи',
      'w' => 180,
      'showInline' => true,
    ),
  38 =>
    array(
      'key' => 'receive_road',
      'label' => 'Дорога приема',
      'w' => 180,
      'showInline' => true,
    ),
  39 =>
    array(
      'key' => 'train_index',
      'label' => 'Индекс поезда',
      'w' => 180,
      'showInline' => true,
    ),
  40 =>
    array(
      'key' => 'train_no',
      'label' => 'Номер поезда',
      'w' => 180,
      'showInline' => true,
    ),
  41 =>
    array(
      'key' => 'wagon_in_train',
      'label' => 'Номер вагона в составе поезда',
      'w' => 180,
      'showInline' => true,
    ),
  42 =>
    array(
      'key' => 'park_no',
      'label' => 'Номер парка',
      'w' => 180,
      'showInline' => true,
    ),
  43 =>
    array(
      'key' => 'track_no',
      'label' => 'Номер пути',
      'w' => 180,
      'showInline' => true,
    ),
  44 =>
    array(
      'key' => 'seals_count',
      'label' => 'Количество пломб',
      'w' => 180,
      'showInline' => true,
    ),
  45 =>
    array(
      'key' => 'loaded_containers',
      'label' => 'Количество груженых контейнеров',
      'w' => 180,
      'showInline' => true,
    ),
  46 =>
    array(
      'key' => 'empty_containers',
      'label' => 'Количество порожних контейнеров',
      'w' => 180,
      'showInline' => true,
    ),
  47 =>
    array(
      'key' => 'container_nos',
      'label' => 'Номера контейнеров на вагоне',
      'w' => 180,
      'showInline' => true,
    ),
  48 =>
    array(
      'key' => 'norm_delivery_dt',
      'label' => 'Нормативный срок доставки',
      'w' => 180,
      'showInline' => true,
    ),
  49 =>
    array(
      'key' => 'dist_passed_km',
      'label' => 'Расстояние пройденное (км)',
      'w' => 180,
      'showInline' => true,
    ),
  50 =>
    array(
      'key' => 'dist_remain_km',
      'label' => 'Расстояние оставшееся (км)',
      'w' => 180,
      'showInline' => true,
    ),
  51 =>
    array(
      'key' => 'dist_total_km',
      'label' => 'Расстояние общее (км)',
      'w' => 180,
      'showInline' => true,
    ),
  52 =>
    array(
      'key' => 'idle_time_hhmmss',
      'label' => 'Время простоя под последней операцией (сутки:часы:минуты)',
      'w' => 180,
      'showInline' => true,
    ),
  53 =>
    array(
      'key' => 'idle_time_days',
      'label' => 'Время простоя под последней операцией (сутки)',
      'w' => 180,
      'showInline' => true,
    ),
  54 =>
    array(
      'key' => 'extra_waybill_no',
      'label' => 'Номер накладной по досылке',
      'w' => 180,
      'showInline' => true,
    ),
  55 =>
    array(
      'key' => 'extra_send_id',
      'label' => 'Уникальный номер отправки по досылке',
      'w' => 180,
      'showInline' => true,
    ),
  56 =>
    array(
      'key' => 'asoup_depart_dt',
      'label' => 'Дата и время отправления (АСОУП) со станции приема груза к перевозке',
      'w' => 180,
      'showInline' => true,
    ),
  57 =>
    array(
      'key' => 'asoup_arrive_dt',
      'label' => 'Дата и время прибытия (АСОУП) на станцию назначения',
      'w' => 180,
      'showInline' => true,
    ),
  58 =>
    array(
      'key' => 'send_id',
      'label' => 'Идентификатор отправки',
      'w' => 180,
      'showInline' => true,
    ),
  59 =>
    array(
      'key' => 'waybill_id',
      'label' => 'Идентификатор накладной',
      'w' => 180,
      'showInline' => true,
    ),
  60 =>
    array(
      'key' => 'wagon_no2',
      'label' => 'Номер вагона',
      'w' => 180,
      'showInline' => true,
    ),
  61 =>
    array(
      'key' => 'quality_sign',
      'label' => 'Признак качества',
      'w' => 180,
      'showInline' => true,
    ),
  62 =>
    array(
      'key' => 'state_assign_dt',
      'label' => 'Дата и время назначения состояния',
      'w' => 180,
      'showInline' => true,
    ),
  63 =>
    array(
      'key' => 'wagon_state',
      'label' => 'Состояние вагона',
      'w' => 180,
      'showInline' => true,
    ),
  64 =>
    array(
      'key' => 'state_reason',
      'label' => 'Причина назначения состояния',
      'w' => 180,
      'showInline' => true,
    ),
  65 =>
    array(
      'key' => 'state_station',
      'label' => 'Станция назначения состояния',
      'w' => 180,
      'showInline' => true,
    ),
  66 =>
    array(
      'key' => 'reg_date',
      'label' => 'Дата регистрации',
      'w' => 180,
      'showInline' => true,
    ),
  67 =>
    array(
      'key' => 'build_date',
      'label' => 'Дата постройки',
      'w' => 180,
      'showInline' => true,
    ),
  68 =>
    array(
      'key' => 'next_repair_dt',
      'label' => 'Дата следующего планового ремонта',
      'w' => 180,
      'showInline' => true,
    ),
  69 =>
    array(
      'key' => 'next_repair_type',
      'label' => 'Вид следующего планового ремонта',
      'w' => 180,
      'showInline' => true,
    ),
  70 =>
    array(
      'key' => 'factory_no',
      'label' => 'Заводской номер',
      'w' => 180,
      'showInline' => true,
    ),
  71 =>
    array(
      'key' => 'manufacturer',
      'label' => 'Завод-изготовитель',
      'w' => 180,
      'showInline' => true,
    ),
  72 =>
    array(
      'key' => 'wagon_type_name',
      'label' => 'Тип вагона',
      'w' => 180,
      'showInline' => true,
    ),
  73 =>
    array(
      'key' => 'wagon_model',
      'label' => 'Модель вагона',
      'w' => 180,
      'showInline' => true,
    ),
  74 =>
    array(
      'key' => 'tare_weight',
      'label' => 'Тара вагона',
      'w' => 180,
      'showInline' => true,
    ),
  75 =>
    array(
      'key' => 'load_capacity',
      'label' => 'Грузоподъемность вагона',
      'w' => 180,
      'showInline' => true,
    ),
  76 =>
    array(
      'key' => 'length_mm',
      'label' => 'Длина по осям автосцепки',
      'w' => 180,
      'showInline' => true,
    ),
  77 =>
    array(
      'key' => 'last_cap_repair_depot',
      'label' => 'Депо последнего кап. ремонта',
      'w' => 180,
      'showInline' => true,
    ),
  78 =>
    array(
      'key' => 'last_cap_repair_dt',
      'label' => 'Дата последнего кап. ремонта',
      'w' => 180,
      'showInline' => true,
    ),
  79 =>
    array(
      'key' => 'last_dep_repair_depot',
      'label' => 'Депо последнего деп. ремонта',
      'w' => 180,
      'showInline' => true,
    ),
  80 =>
    array(
      'key' => 'last_dep_repair_dt',
      'label' => 'Дата последнего деп. ремонта',
      'w' => 180,
      'showInline' => true,
    ),
  81 =>
    array(
      'key' => 'home_road',
      'label' => 'Дорога приписки',
      'w' => 180,
      'showInline' => true,
    ),
  82 =>
    array(
      'key' => 'home_depot',
      'label' => 'Депо приписки',
      'w' => 180,
      'showInline' => true,
    ),
  83 =>
    array(
      'key' => 'exclude_date',
      'label' => 'Дата исключения',
      'w' => 180,
      'showInline' => true,
    ),
  84 =>
    array(
      'key' => 'no_transit_reason',
      'label' => 'Причина запрета на курсирование',
      'w' => 180,
      'showInline' => true,
    ),
  85 =>
    array(
      'key' => 'prev_wagon_no',
      'label' => 'Номер вагона до перенумерации',
      'w' => 180,
      'showInline' => true,
    ),
  86 =>
    array(
      'key' => 'owner',
      'label' => 'Собственник',
      'w' => 180,
      'showInline' => true,
    ),
  87 =>
    array(
      'key' => 'owner_okpo',
      'label' => 'Собственник (ОКПО)',
      'w' => 180,
      'showInline' => true,
    ),
  88 =>
    array(
      'key' => 'owner_local_code',
      'label' => 'Собственник (локальный код)',
      'w' => 180,
      'showInline' => true,
    ),
  89 =>
    array(
      'key' => 'home_station',
      'label' => 'Станция приписки',
      'w' => 180,
      'showInline' => true,
    ),
  90 =>
    array(
      'key' => 'threshold_sign',
      'label' => 'Признак порога',
      'w' => 180,
      'showInline' => true,
    ),
  91 =>
    array(
      'key' => 'lease_sign',
      'label' => 'Признак аренды',
      'w' => 180,
      'showInline' => true,
    ),
  92 =>
    array(
      'key' => 'life_ext_date',
      'label' => 'Утв. дата продления срока службы',
      'w' => 180,
      'showInline' => true,
    ),
  93 =>
    array(
      'key' => 'lessee',
      'label' => 'Арендатор',
      'w' => 180,
      'showInline' => true,
    ),
  94 =>
    array(
      'key' => 'lessee_okpo',
      'label' => 'Арендатор (ОКПО)',
      'w' => 180,
      'showInline' => true,
    ),
  95 =>
    array(
      'key' => 'lessee_local_code',
      'label' => 'Арендатор (локальный код)',
      'w' => 180,
      'showInline' => true,
    ),
  96 =>
    array(
      'key' => 'lease_home_station',
      'label' => 'Станция приписки аренды',
      'w' => 180,
      'showInline' => true,
    ),
  97 =>
    array(
      'key' => 'lease_end_date',
      'label' => 'Дата окончания аренды',
      'w' => 180,
      'showInline' => true,
    ),
  98 =>
    array(
      'key' => 'service_life',
      'label' => 'Срок службы вагона',
      'w' => 180,
      'showInline' => true,
    ),
  99 =>
    array(
      'key' => 'body_material_code',
      'label' => 'Материал кузова (код)',
      'w' => 180,
      'showInline' => true,
    ),
  100 =>
    array(
      'key' => 'body_material_name',
      'label' => 'Наименование материала кузова',
      'w' => 180,
      'showInline' => true,
    ),
  101 =>
    array(
      'key' => 'body_volume',
      'label' => 'Объём кузова',
      'w' => 180,
      'showInline' => true,
    ),
  102 =>
    array(
      'key' => 'clearance',
      'label' => 'Габарит',
      'w' => 180,
      'showInline' => true,
    ),
  103 =>
    array(
      'key' => 'air_dist_type',
      'label' => 'Тип воздухораспределителя (код)',
      'w' => 180,
      'showInline' => true,
    ),
  104 =>
    array(
      'key' => 'automode',
      'label' => 'Авторежим',
      'w' => 180,
      'showInline' => true,
    ),
  105 =>
    array(
      'key' => 'auto_lever',
      'label' => 'Авторегулятор рычажной передачи',
      'w' => 180,
      'showInline' => true,
    ),
  106 =>
    array(
      'key' => 'brake_type',
      'label' => 'Тип тормоза',
      'w' => 180,
      'showInline' => true,
    ),
  107 =>
    array(
      'key' => 'coupler_type',
      'label' => 'Тип автосцепки',
      'w' => 180,
      'showInline' => true,
    ),
  108 =>
    array(
      'key' => 'bogie_model',
      'label' => 'Модель тележки',
      'w' => 180,
      'showInline' => true,
    ),
  109 =>
    array(
      'key' => 'shock_absorber',
      'label' => 'Тип поглощающего аппарата',
      'w' => 180,
      'showInline' => true,
    ),
  110 =>
    array(
      'key' => 'life_ext_sign',
      'label' => 'Признак продления срока службы вагона',
      'w' => 180,
      'showInline' => true,
    ),
  111 =>
    array(
      'key' => 'boiler_caliber',
      'label' => 'Калибр котла',
      'w' => 180,
      'showInline' => true,
    ),
  112 =>
    array(
      'key' => 'drain_device',
      'label' => 'Наличие сливного прибора',
      'w' => 180,
      'showInline' => true,
    ),
  113 =>
    array(
      'key' => 'lever_gear',
      'label' => 'Рычажная передача',
      'w' => 180,
      'showInline' => true,
    ),
  114 =>
    array(
      'key' => 'wagon_model_code',
      'label' => 'Код модели вагона',
      'w' => 180,
      'showInline' => true,
    ),
  115 =>
    array(
      'key' => 'repair_by_mileage',
      'label' => 'Признак ремонта по пробегу',
      'w' => 180,
      'showInline' => true,
    ),
  116 =>
    array(
      'key' => 'proxy_operator',
      'label' => 'Оператор по доверенности',
      'w' => 180,
      'showInline' => true,
    ),
  117 =>
    array(
      'key' => 'proxy_operator_okpo',
      'label' => 'Оператор по доверенности (ОКПО)',
      'w' => 180,
      'showInline' => true,
    ),
  118 =>
    array(
      'key' => 'wagon_type_code2',
      'label' => 'Род вагона',
      'w' => 180,
      'showInline' => true,
    ),
  119 =>
    array(
      'key' => 'wagon_type_cond',
      'label' => 'Условный тип вагона',
      'w' => 180,
      'showInline' => true,
    ),
  120 =>
    array(
      'key' => 'axles_count',
      'label' => 'Количество осей вагона',
      'w' => 180,
      'showInline' => true,
    ),
  121 =>
    array(
      'key' => 'exclude_depot',
      'label' => 'Депо исключения вагона',
      'w' => 180,
      'showInline' => true,
    ),
  122 =>
    array(
      'key' => 'exclude_reason',
      'label' => 'Причина исключения вагона',
      'w' => 180,
      'showInline' => true,
    ),
  123 =>
    array(
      'key' => 'days_to_repair',
      'label' => 'Дней до следующего ремонта',
      'w' => 180,
      'showInline' => true,
    ),
  124 =>
    array(
      'key' => 'days_no_oper',
      'label' => 'Дней без операций',
      'w' => 180,
      'showInline' => true,
    ),
  125 =>
    array(
      'key' => 'days_no_move',
      'label' => 'Дней без движения',
      'w' => 180,
      'showInline' => true,
    ),
);
