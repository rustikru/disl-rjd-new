-- ================================================================
-- Таблица дислокации вагонов РЖД (детальные данные из отчёта)
-- Источник: «Личный кабинет клиента» РЖД, 126 колонок
-- Выполнить:
--   docker cp oracle_xx_dislocation_rjd.sql disl_oracle:/tmp/
--   docker exec -it disl_oracle sqlplus disl/disl123@FREEPDB1 @/tmp/oracle_xx_dislocation_rjd.sql
-- ================================================================

CREATE TABLE IF NOT EXISTS xx_dislocation_rjd (
    id                     NUMBER          GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_dt              TIMESTAMP       NOT NULL,

    -- Колонки 1-126 исходного отчёта
    wagon_no               VARCHAR2(4000),
    waybill_no             VARCHAR2(4000),
    wagon_type_code        VARCHAR2(4000),
    owner_admin            VARCHAR2(4000),
    trip_start_dt          VARCHAR2(4000),
    depart_state           VARCHAR2(4000),
    depart_road            VARCHAR2(4000),
    depart_station         VARCHAR2(4000),
    trip_end_dt            VARCHAR2(4000),
    dest_state             VARCHAR2(4000),
    dest_road              VARCHAR2(4000),
    dest_station           VARCHAR2(4000),
    consignor_tgnl         VARCHAR2(4000),
    consignor              VARCHAR2(4000),
    consignor_okpo         VARCHAR2(4000),
    consignor_name         VARCHAR2(4000),
    consignee_tgnl         VARCHAR2(4000),
    consignee              VARCHAR2(4000),
    consignee_okpo         VARCHAR2(4000),
    consignee_name         VARCHAR2(4000),
    cargo_name             VARCHAR2(4000),
    cargo_gng              VARCHAR2(4000),
    cargo_weight_kg        VARCHAR2(4000),
    mileage_loaded_km      VARCHAR2(4000),
    mileage_empty_km       VARCHAR2(4000),
    mileage_total_km       VARCHAR2(4000),
    mileage_norm_km        VARCHAR2(4000),
    mileage_remain_km      VARCHAR2(4000),
    mileage_sign           VARCHAR2(4000),
    special_marks          VARCHAR2(4000),
    prev_cargo             VARCHAR2(4000),
    oper_station           VARCHAR2(4000),
    oper_road              VARCHAR2(4000),
    operation              VARCHAR2(4000),
    oper_mnemonic          VARCHAR2(4000),
    oper_dt                VARCHAR2(4000),
    park_type              VARCHAR2(4000),
    handover_road          VARCHAR2(4000),
    receive_road           VARCHAR2(4000),
    train_index            VARCHAR2(4000),
    train_no               VARCHAR2(4000),
    wagon_in_train         VARCHAR2(4000),
    park_no                VARCHAR2(4000),
    track_no               VARCHAR2(4000),
    seals_count            VARCHAR2(4000),
    loaded_containers      VARCHAR2(4000),
    empty_containers       VARCHAR2(4000),
    container_nos          VARCHAR2(4000),
    norm_delivery_dt       VARCHAR2(4000),
    dist_passed_km         VARCHAR2(4000),
    dist_remain_km         VARCHAR2(4000),
    dist_total_km          VARCHAR2(4000),
    idle_time_hhmmss       VARCHAR2(4000),
    idle_time_days         VARCHAR2(4000),
    extra_waybill_no       VARCHAR2(4000),
    extra_send_id          VARCHAR2(4000),
    asoup_depart_dt        VARCHAR2(4000),
    asoup_arrive_dt        VARCHAR2(4000),
    send_id                VARCHAR2(4000),
    waybill_id             VARCHAR2(4000),
    wagon_no2              VARCHAR2(4000),
    quality_sign           VARCHAR2(4000),
    state_assign_dt        VARCHAR2(4000),
    wagon_state            VARCHAR2(4000),
    state_reason           VARCHAR2(4000),
    state_station          VARCHAR2(4000),
    reg_date               VARCHAR2(4000),
    build_date             VARCHAR2(4000),
    next_repair_dt         VARCHAR2(4000),
    next_repair_type       VARCHAR2(4000),
    factory_no             VARCHAR2(4000),
    manufacturer           VARCHAR2(4000),
    wagon_type_name        VARCHAR2(4000),
    wagon_model            VARCHAR2(4000),
    tare_weight            VARCHAR2(4000),
    load_capacity          VARCHAR2(4000),
    length_mm              VARCHAR2(4000),
    last_cap_repair_depot  VARCHAR2(4000),
    last_cap_repair_dt     VARCHAR2(4000),
    last_dep_repair_depot  VARCHAR2(4000),
    last_dep_repair_dt     VARCHAR2(4000),
    home_road              VARCHAR2(4000),
    home_depot             VARCHAR2(4000),
    exclude_date           VARCHAR2(4000),
    no_transit_reason      VARCHAR2(4000),
    prev_wagon_no          VARCHAR2(4000),
    owner                  VARCHAR2(4000),
    owner_okpo             VARCHAR2(4000),
    owner_local_code       VARCHAR2(4000),
    home_station           VARCHAR2(4000),
    threshold_sign         VARCHAR2(4000),
    lease_sign             VARCHAR2(4000),
    life_ext_date          VARCHAR2(4000),
    lessee                 VARCHAR2(4000),
    lessee_okpo            VARCHAR2(4000),
    lessee_local_code      VARCHAR2(4000),
    lease_home_station     VARCHAR2(4000),
    lease_end_date         VARCHAR2(4000),
    service_life           VARCHAR2(4000),
    body_material_code     VARCHAR2(4000),
    body_material_name     VARCHAR2(4000),
    body_volume            VARCHAR2(4000),
    clearance              VARCHAR2(4000),
    air_dist_type          VARCHAR2(4000),
    automode               VARCHAR2(4000),
    auto_lever             VARCHAR2(4000),
    brake_type             VARCHAR2(4000),
    coupler_type           VARCHAR2(4000),
    bogie_model            VARCHAR2(4000),
    shock_absorber         VARCHAR2(4000),
    life_ext_sign          VARCHAR2(4000),
    boiler_caliber         VARCHAR2(4000),
    drain_device           VARCHAR2(4000),
    lever_gear             VARCHAR2(4000),
    wagon_model_code       VARCHAR2(4000),
    repair_by_mileage      VARCHAR2(4000),
    proxy_operator         VARCHAR2(4000),
    proxy_operator_okpo    VARCHAR2(4000),
    wagon_type_code2       VARCHAR2(4000),
    wagon_type_cond        VARCHAR2(4000),
    axles_count            VARCHAR2(4000),
    exclude_depot          VARCHAR2(4000),
    exclude_reason         VARCHAR2(4000),
    days_to_repair         VARCHAR2(4000),
    days_no_oper           VARCHAR2(4000),
    days_no_move           VARCHAR2(4000)
);
/

CREATE INDEX IF NOT EXISTS idx_xx_dislocn_report_dt ON xx_dislocation_rjd (report_dt);
/
CREATE INDEX IF NOT EXISTS idx_xx_dislocn_wagon_no  ON xx_dislocation_rjd (wagon_no);
/

COMMENT ON TABLE xx_dislocation_rjd IS 'Дислокация вагонов РЖД — детальные данные из отчёта «Личный кабинет клиента» (126 исходных колонок)';
COMMENT ON COLUMN xx_dislocation_rjd.id        IS 'Суррогатный первичный ключ';
COMMENT ON COLUMN xx_dislocation_rjd.report_dt IS 'Дата и время формирования справки (из ячейки A2 файла XLSX)';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_no  IS 'Колонка 1 — Номер вагона';
COMMENT ON COLUMN xx_dislocation_rjd.waybill_no IS 'Колонка 2 — Номер накладной';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_code IS 'Колонка 3 — Род вагона';
COMMENT ON COLUMN xx_dislocation_rjd.owner_admin IS 'Колонка 4 — Администрация собственника';
COMMENT ON COLUMN xx_dislocation_rjd.trip_start_dt IS 'Колонка 5 — Дата и время начала рейса';
COMMENT ON COLUMN xx_dislocation_rjd.depart_state IS 'Колонка 6 — Государство отправления';
COMMENT ON COLUMN xx_dislocation_rjd.depart_road IS 'Колонка 7 — Дорога отправления';
COMMENT ON COLUMN xx_dislocation_rjd.depart_station IS 'Колонка 8 — Станция отправления';
COMMENT ON COLUMN xx_dislocation_rjd.trip_end_dt IS 'Колонка 9 — Дата и время окончания рейса';
COMMENT ON COLUMN xx_dislocation_rjd.dest_state IS 'Колонка 10 — Государство назначения';
COMMENT ON COLUMN xx_dislocation_rjd.dest_road IS 'Колонка 11 — Дорога назначения';
COMMENT ON COLUMN xx_dislocation_rjd.dest_station IS 'Колонка 12 — Станция назначения';
COMMENT ON COLUMN xx_dislocation_rjd.consignor_tgnl IS 'Колонка 13 — Грузоотправитель (ТГНЛ)';
COMMENT ON COLUMN xx_dislocation_rjd.consignor IS 'Колонка 14 — Грузоотправитель';
COMMENT ON COLUMN xx_dislocation_rjd.consignor_okpo IS 'Колонка 15 — Грузоотправитель (ОКПО)';
COMMENT ON COLUMN xx_dislocation_rjd.consignor_name IS 'Колонка 16 — Грузоотправитель (наименование)';
COMMENT ON COLUMN xx_dislocation_rjd.consignee_tgnl IS 'Колонка 17 — Грузополучатель (ТГНЛ)';
COMMENT ON COLUMN xx_dislocation_rjd.consignee IS 'Колонка 18 — Грузополучатель';
COMMENT ON COLUMN xx_dislocation_rjd.consignee_okpo IS 'Колонка 19 — Грузополучатель (ОКПО)';
COMMENT ON COLUMN xx_dislocation_rjd.consignee_name IS 'Колонка 20 — Грузополучатель (наименование)';
COMMENT ON COLUMN xx_dislocation_rjd.cargo_name IS 'Колонка 21 — Наименование груза';
COMMENT ON COLUMN xx_dislocation_rjd.cargo_gng IS 'Колонка 22 — Код груза ГНГ';
COMMENT ON COLUMN xx_dislocation_rjd.cargo_weight_kg IS 'Колонка 23 — Вес груза (кг)';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_loaded_km IS 'Колонка 24 — Пробег в груженом состоянии (км)';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_empty_km IS 'Колонка 25 — Пробег в порожнем состоянии (км)';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_total_km IS 'Колонка 26 — Пробег общий (км)';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_norm_km IS 'Колонка 27 — Норматив величины пробега (км)';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_remain_km IS 'Колонка 28 — Остаток пробега (км)';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_sign IS 'Колонка 29 — Признак пробега';
COMMENT ON COLUMN xx_dislocation_rjd.special_marks IS 'Колонка 30 — Особые отметки';
COMMENT ON COLUMN xx_dislocation_rjd.prev_cargo IS 'Колонка 31 — Ранее выгруженный груз';
COMMENT ON COLUMN xx_dislocation_rjd.oper_station IS 'Колонка 32 — Станция операции';
COMMENT ON COLUMN xx_dislocation_rjd.oper_road IS 'Колонка 33 — Дорога операции';
COMMENT ON COLUMN xx_dislocation_rjd.operation IS 'Колонка 34 — Операция';
COMMENT ON COLUMN xx_dislocation_rjd.oper_mnemonic IS 'Колонка 35 — Мнемокод операции';
COMMENT ON COLUMN xx_dislocation_rjd.oper_dt IS 'Колонка 36 — Дата и время операции';
COMMENT ON COLUMN xx_dislocation_rjd.park_type IS 'Колонка 37 — Тип парка';
COMMENT ON COLUMN xx_dislocation_rjd.handover_road IS 'Колонка 38 — Дорога сдачи';
COMMENT ON COLUMN xx_dislocation_rjd.receive_road IS 'Колонка 39 — Дорога приема';
COMMENT ON COLUMN xx_dislocation_rjd.train_index IS 'Колонка 40 — Индекс поезда';
COMMENT ON COLUMN xx_dislocation_rjd.train_no IS 'Колонка 41 — Номер поезда';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_in_train IS 'Колонка 42 — Номер вагона в составе';
COMMENT ON COLUMN xx_dislocation_rjd.park_no IS 'Колонка 43 — Номер парка';
COMMENT ON COLUMN xx_dislocation_rjd.track_no IS 'Колонка 44 — Номер пути';
COMMENT ON COLUMN xx_dislocation_rjd.seals_count IS 'Колонка 45 — Количество пломб';
COMMENT ON COLUMN xx_dislocation_rjd.loaded_containers IS 'Колонка 46 — Количество груженых контейнеров';
COMMENT ON COLUMN xx_dislocation_rjd.empty_containers IS 'Колонка 47 — Количество порожних контейнеров';
COMMENT ON COLUMN xx_dislocation_rjd.container_nos IS 'Колонка 48 — Номера контейнеров на вагоне';
COMMENT ON COLUMN xx_dislocation_rjd.norm_delivery_dt IS 'Колонка 49 — Нормативный срок доставки';
COMMENT ON COLUMN xx_dislocation_rjd.dist_passed_km IS 'Колонка 50 — Расстояние пройденное (км)';
COMMENT ON COLUMN xx_dislocation_rjd.dist_remain_km IS 'Колонка 51 — Расстояние оставшееся (км)';
COMMENT ON COLUMN xx_dislocation_rjd.dist_total_km IS 'Колонка 52 — Расстояние общее (км)';
COMMENT ON COLUMN xx_dislocation_rjd.idle_time_hhmmss IS 'Колонка 53 — Время простоя (сутки:часы:минуты)';
COMMENT ON COLUMN xx_dislocation_rjd.idle_time_days IS 'Колонка 54 — Время простоя (сутки)';
COMMENT ON COLUMN xx_dislocation_rjd.extra_waybill_no IS 'Колонка 55 — Номер накладной по досылке';
COMMENT ON COLUMN xx_dislocation_rjd.extra_send_id IS 'Колонка 56 — Уникальный номер отправки по досылке';
COMMENT ON COLUMN xx_dislocation_rjd.asoup_depart_dt IS 'Колонка 57 — Дата отправления (АСОУП)';
COMMENT ON COLUMN xx_dislocation_rjd.asoup_arrive_dt IS 'Колонка 58 — Дата прибытия (АСОУП)';
COMMENT ON COLUMN xx_dislocation_rjd.send_id IS 'Колонка 59 — Идентификатор отправки';
COMMENT ON COLUMN xx_dislocation_rjd.waybill_id IS 'Колонка 60 — Идентификатор накладной';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_no2 IS 'Колонка 61 — Номер вагона (дублирующий)';
COMMENT ON COLUMN xx_dislocation_rjd.quality_sign IS 'Колонка 62 — Признак качества';
COMMENT ON COLUMN xx_dislocation_rjd.state_assign_dt IS 'Колонка 63 — Дата назначения состояния';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_state IS 'Колонка 64 — Состояние вагона';
COMMENT ON COLUMN xx_dislocation_rjd.state_reason IS 'Колонка 65 — Причина назначения состояния';
COMMENT ON COLUMN xx_dislocation_rjd.state_station IS 'Колонка 66 — Станция назначения состояния';
COMMENT ON COLUMN xx_dislocation_rjd.reg_date IS 'Колонка 67 — Дата регистрации';
COMMENT ON COLUMN xx_dislocation_rjd.build_date IS 'Колонка 68 — Дата постройки';
COMMENT ON COLUMN xx_dislocation_rjd.next_repair_dt IS 'Колонка 69 — Дата следующего планового ремонта';
COMMENT ON COLUMN xx_dislocation_rjd.next_repair_type IS 'Колонка 70 — Вид следующего планового ремонта';
COMMENT ON COLUMN xx_dislocation_rjd.factory_no IS 'Колонка 71 — Заводской номер';
COMMENT ON COLUMN xx_dislocation_rjd.manufacturer IS 'Колонка 72 — Завод-изготовитель';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_name IS 'Колонка 73 — Тип вагона';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_model IS 'Колонка 74 — Модель вагона';
COMMENT ON COLUMN xx_dislocation_rjd.tare_weight IS 'Колонка 75 — Тара вагона';
COMMENT ON COLUMN xx_dislocation_rjd.load_capacity IS 'Колонка 76 — Грузоподъемность вагона';
COMMENT ON COLUMN xx_dislocation_rjd.length_mm IS 'Колонка 77 — Длина по осям автосцепки';
COMMENT ON COLUMN xx_dislocation_rjd.last_cap_repair_depot IS 'Колонка 78 — Депо последнего капитального ремонта';
COMMENT ON COLUMN xx_dislocation_rjd.last_cap_repair_dt IS 'Колонка 79 — Дата последнего капитального ремонта';
COMMENT ON COLUMN xx_dislocation_rjd.last_dep_repair_depot IS 'Колонка 80 — Депо последнего деповского ремонта';
COMMENT ON COLUMN xx_dislocation_rjd.last_dep_repair_dt IS 'Колонка 81 — Дата последнего деповского ремонта';
COMMENT ON COLUMN xx_dislocation_rjd.home_road IS 'Колонка 82 — Дорога приписки';
COMMENT ON COLUMN xx_dislocation_rjd.home_depot IS 'Колонка 83 — Депо приписки';
COMMENT ON COLUMN xx_dislocation_rjd.exclude_date IS 'Колонка 84 — Дата исключения';
COMMENT ON COLUMN xx_dislocation_rjd.no_transit_reason IS 'Колонка 85 — Причина запрета на курсирование';
COMMENT ON COLUMN xx_dislocation_rjd.prev_wagon_no IS 'Колонка 86 — Номер вагона до перенумерации';
COMMENT ON COLUMN xx_dislocation_rjd.owner IS 'Колонка 87 — Собственник';
COMMENT ON COLUMN xx_dislocation_rjd.owner_okpo IS 'Колонка 88 — Собственник (ОКПО)';
COMMENT ON COLUMN xx_dislocation_rjd.owner_local_code IS 'Колонка 89 — Собственник (локальный код)';
COMMENT ON COLUMN xx_dislocation_rjd.home_station IS 'Колонка 90 — Станция приписки';
COMMENT ON COLUMN xx_dislocation_rjd.threshold_sign IS 'Колонка 91 — Признак порога';
COMMENT ON COLUMN xx_dislocation_rjd.lease_sign IS 'Колонка 92 — Признак аренды';
COMMENT ON COLUMN xx_dislocation_rjd.life_ext_date IS 'Колонка 93 — Утверждённая дата продления срока службы';
COMMENT ON COLUMN xx_dislocation_rjd.lessee IS 'Колонка 94 — Арендатор';
COMMENT ON COLUMN xx_dislocation_rjd.lessee_okpo IS 'Колонка 95 — Арендатор (ОКПО)';
COMMENT ON COLUMN xx_dislocation_rjd.lessee_local_code IS 'Колонка 96 — Арендатор (локальный код)';
COMMENT ON COLUMN xx_dislocation_rjd.lease_home_station IS 'Колонка 97 — Станция приписки аренды';
COMMENT ON COLUMN xx_dislocation_rjd.lease_end_date IS 'Колонка 98 — Дата окончания аренды';
COMMENT ON COLUMN xx_dislocation_rjd.service_life IS 'Колонка 99 — Срок службы вагона';
COMMENT ON COLUMN xx_dislocation_rjd.body_material_code IS 'Колонка 100 — Материал кузова (код)';
COMMENT ON COLUMN xx_dislocation_rjd.body_material_name IS 'Колонка 101 — Наименование материала кузова';
COMMENT ON COLUMN xx_dislocation_rjd.body_volume IS 'Колонка 102 — Объём кузова';
COMMENT ON COLUMN xx_dislocation_rjd.clearance IS 'Колонка 103 — Габарит';
COMMENT ON COLUMN xx_dislocation_rjd.air_dist_type IS 'Колонка 104 — Тип воздухораспределителя';
COMMENT ON COLUMN xx_dislocation_rjd.automode IS 'Колонка 105 — Авторежим';
COMMENT ON COLUMN xx_dislocation_rjd.auto_lever IS 'Колонка 106 — Авторегулятор рычажной передачи';
COMMENT ON COLUMN xx_dislocation_rjd.brake_type IS 'Колонка 107 — Тип тормоза';
COMMENT ON COLUMN xx_dislocation_rjd.coupler_type IS 'Колонка 108 — Тип автосцепки';
COMMENT ON COLUMN xx_dislocation_rjd.bogie_model IS 'Колонка 109 — Модель тележки';
COMMENT ON COLUMN xx_dislocation_rjd.shock_absorber IS 'Колонка 110 — Тип поглощающего аппарата';
COMMENT ON COLUMN xx_dislocation_rjd.life_ext_sign IS 'Колонка 111 — Признак продления срока службы';
COMMENT ON COLUMN xx_dislocation_rjd.boiler_caliber IS 'Колонка 112 — Калибр котла';
COMMENT ON COLUMN xx_dislocation_rjd.drain_device IS 'Колонка 113 — Наличие сливного прибора';
COMMENT ON COLUMN xx_dislocation_rjd.lever_gear IS 'Колонка 114 — Рычажная передача';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_model_code IS 'Колонка 115 — Код модели вагона';
COMMENT ON COLUMN xx_dislocation_rjd.repair_by_mileage IS 'Колонка 116 — Признак ремонта по пробегу';
COMMENT ON COLUMN xx_dislocation_rjd.proxy_operator IS 'Колонка 117 — Оператор по доверенности';
COMMENT ON COLUMN xx_dislocation_rjd.proxy_operator_okpo IS 'Колонка 118 — Оператор по доверенности (ОКПО)';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_code2 IS 'Колонка 119 — Род вагона (повторный)';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_cond IS 'Колонка 120 — Условный тип вагона';
COMMENT ON COLUMN xx_dislocation_rjd.axles_count IS 'Колонка 121 — Количество осей вагона';
COMMENT ON COLUMN xx_dislocation_rjd.exclude_depot IS 'Колонка 122 — Депо исключения вагона';
COMMENT ON COLUMN xx_dislocation_rjd.exclude_reason IS 'Колонка 123 — Причина исключения вагона';
COMMENT ON COLUMN xx_dislocation_rjd.days_to_repair IS 'Колонка 124 — Дней до следующего ремонта';
COMMENT ON COLUMN xx_dislocation_rjd.days_no_oper IS 'Колонка 125 — Дней без операций';
COMMENT ON COLUMN xx_dislocation_rjd.days_no_move IS 'Колонка 126 — Дней без движения';
/

COMMIT;
/
