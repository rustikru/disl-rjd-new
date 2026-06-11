-- ================================================================
-- Таблица дислокации вагонов РЖД (детальные данные из отчёта)
-- Источник: «Личный кабинет клиента» РЖД, 126 колонок
-- Выполнить: psql -d disl_rzd -f xx_dislocation_rjd_schema.sql
-- ================================================================

CREATE TABLE IF NOT EXISTS xx_dislocation_rjd (
    id                     BIGSERIAL    PRIMARY KEY,
    report_dt              TIMESTAMP    NOT NULL,

    -- Колонки 1-126 исходного отчёта (все хранятся как TEXT)
    wagon_no               TEXT,
    waybill_no             TEXT,
    wagon_type_code        TEXT,
    owner_admin            TEXT,
    trip_start_dt          TEXT,
    depart_state           TEXT,
    depart_road            TEXT,
    depart_station         TEXT,
    trip_end_dt            TEXT,
    dest_state             TEXT,
    dest_road              TEXT,
    dest_station           TEXT,
    consignor_tgnl         TEXT,
    consignor              TEXT,
    consignor_okpo         TEXT,
    consignor_name         TEXT,
    consignee_tgnl         TEXT,
    consignee              TEXT,
    consignee_okpo         TEXT,
    consignee_name         TEXT,
    cargo_name             TEXT,
    cargo_gng              TEXT,
    cargo_weight_kg        TEXT,
    mileage_loaded_km      TEXT,
    mileage_empty_km       TEXT,
    mileage_total_km       TEXT,
    mileage_norm_km        TEXT,
    mileage_remain_km      TEXT,
    mileage_sign           TEXT,
    special_marks          TEXT,
    prev_cargo             TEXT,
    oper_station           TEXT,
    oper_road              TEXT,
    operation              TEXT,
    oper_mnemonic          TEXT,
    oper_dt                TEXT,
    park_type              TEXT,
    handover_road          TEXT,
    receive_road           TEXT,
    train_index            TEXT,
    train_no               TEXT,
    wagon_in_train         TEXT,
    park_no                TEXT,
    track_no               TEXT,
    seals_count            TEXT,
    loaded_containers      TEXT,
    empty_containers       TEXT,
    container_nos          TEXT,
    norm_delivery_dt       TEXT,
    dist_passed_km         TEXT,
    dist_remain_km         TEXT,
    dist_total_km          TEXT,
    idle_time_hhmmss       TEXT,
    idle_time_days         TEXT,
    extra_waybill_no       TEXT,
    extra_send_id          TEXT,
    asoup_depart_dt        TEXT,
    asoup_arrive_dt        TEXT,
    send_id                TEXT,
    waybill_id             TEXT,
    wagon_no2              TEXT,
    quality_sign           TEXT,
    state_assign_dt        TEXT,
    wagon_state            TEXT,
    state_reason           TEXT,
    state_station          TEXT,
    reg_date               TEXT,
    build_date             TEXT,
    next_repair_dt         TEXT,
    next_repair_type       TEXT,
    factory_no             TEXT,
    manufacturer           TEXT,
    wagon_type_name        TEXT,
    wagon_model            TEXT,
    tare_weight            TEXT,
    load_capacity          TEXT,
    length_mm              TEXT,
    last_cap_repair_depot  TEXT,
    last_cap_repair_dt     TEXT,
    last_dep_repair_depot  TEXT,
    last_dep_repair_dt     TEXT,
    home_road              TEXT,
    home_depot             TEXT,
    exclude_date           TEXT,
    no_transit_reason      TEXT,
    prev_wagon_no          TEXT,
    owner                  TEXT,
    owner_okpo             TEXT,
    owner_local_code       TEXT,
    home_station           TEXT,
    threshold_sign         TEXT,
    lease_sign             TEXT,
    life_ext_date          TEXT,
    lessee                 TEXT,
    lessee_okpo            TEXT,
    lessee_local_code      TEXT,
    lease_home_station     TEXT,
    lease_end_date         TEXT,
    service_life           TEXT,
    body_material_code     TEXT,
    body_material_name     TEXT,
    body_volume            TEXT,
    clearance              TEXT,
    air_dist_type          TEXT,
    automode               TEXT,
    auto_lever             TEXT,
    brake_type             TEXT,
    coupler_type           TEXT,
    bogie_model            TEXT,
    shock_absorber         TEXT,
    life_ext_sign          TEXT,
    boiler_caliber         TEXT,
    drain_device           TEXT,
    lever_gear             TEXT,
    wagon_model_code       TEXT,
    repair_by_mileage      TEXT,
    proxy_operator         TEXT,
    proxy_operator_okpo    TEXT,
    wagon_type_code2       TEXT,
    wagon_type_cond        TEXT,
    axles_count            TEXT,
    exclude_depot          TEXT,
    exclude_reason         TEXT,
    days_to_repair         TEXT,
    days_no_oper           TEXT,
    days_no_move           TEXT
);

CREATE INDEX IF NOT EXISTS idx_xx_dislocn_report_dt ON xx_dislocation_rjd (report_dt);
CREATE INDEX IF NOT EXISTS idx_xx_dislocn_wagon_no  ON xx_dislocation_rjd (wagon_no);

-- ── Комментарии к таблице и полям ────────────────────────────────
COMMENT ON TABLE  xx_dislocation_rjd IS 'Дислокация вагонов РЖД — детальные данные из отчёта «Личный кабинет клиента» (126 исходных колонок)';
COMMENT ON COLUMN xx_dislocation_rjd.id          IS 'Суррогатный первичный ключ';
COMMENT ON COLUMN xx_dislocation_rjd.report_dt   IS 'Дата и время формирования справки (из ячейки A2 файла XLSX, формат DD.MM.YYYY HH:MM)';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_no    IS 'Колонка 1 — Номер вагона';
COMMENT ON COLUMN xx_dislocation_rjd.waybill_no  IS 'Колонка 2 — Номер накладной';
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
COMMENT ON COLUMN xx_dislocation_rjd.park_type IS 'Колонка 37 — Тип парка (напр.: Местный, Порожний, Вагон рабочего парка)';
COMMENT ON COLUMN xx_dislocation_rjd.handover_road IS 'Колонка 38 — Дорога сдачи';
COMMENT ON COLUMN xx_dislocation_rjd.receive_road IS 'Колонка 39 — Дорога приема';
COMMENT ON COLUMN xx_dislocation_rjd.train_index IS 'Колонка 40 — Индекс поезда';
COMMENT ON COLUMN xx_dislocation_rjd.train_no IS 'Колонка 41 — Номер поезда';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_in_train IS 'Колонка 42 — Номер вагона в составе поезда';
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
COMMENT ON COLUMN xx_dislocation_rjd.idle_time_hhmmss IS 'Колонка 53 — Время простоя под последней операцией (сутки:часы:минуты)';
COMMENT ON COLUMN xx_dislocation_rjd.idle_time_days IS 'Колонка 54 — Время простоя под последней операцией (сутки)';
COMMENT ON COLUMN xx_dislocation_rjd.extra_waybill_no IS 'Колонка 55 — Номер накладной по досылке';
COMMENT ON COLUMN xx_dislocation_rjd.extra_send_id IS 'Колонка 56 — Уникальный номер отправки по досылке';
COMMENT ON COLUMN xx_dislocation_rjd.asoup_depart_dt IS 'Колонка 57 — Дата и время отправления (АСОУП) со станции приема груза';
COMMENT ON COLUMN xx_dislocation_rjd.asoup_arrive_dt IS 'Колонка 58 — Дата и время прибытия (АСОУП) на станцию назначения';
COMMENT ON COLUMN xx_dislocation_rjd.send_id IS 'Колонка 59 — Идентификатор отправки';
COMMENT ON COLUMN xx_dislocation_rjd.waybill_id IS 'Колонка 60 — Идентификатор накладной';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_no2 IS 'Колонка 61 — Номер вагона (дублирующий)';
COMMENT ON COLUMN xx_dislocation_rjd.quality_sign IS 'Колонка 62 — Признак качества';
COMMENT ON COLUMN xx_dislocation_rjd.state_assign_dt IS 'Колонка 63 — Дата и время назначения состояния';
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
COMMENT ON COLUMN xx_dislocation_rjd.air_dist_type IS 'Колонка 104 — Тип воздухораспределителя (код)';
COMMENT ON COLUMN xx_dislocation_rjd.automode IS 'Колонка 105 — Авторежим';
COMMENT ON COLUMN xx_dislocation_rjd.auto_lever IS 'Колонка 106 — Авторегулятор рычажной передачи';
COMMENT ON COLUMN xx_dislocation_rjd.brake_type IS 'Колонка 107 — Тип тормоза';
COMMENT ON COLUMN xx_dislocation_rjd.coupler_type IS 'Колонка 108 — Тип автосцепки';
COMMENT ON COLUMN xx_dislocation_rjd.bogie_model IS 'Колонка 109 — Модель тележки';
COMMENT ON COLUMN xx_dislocation_rjd.shock_absorber IS 'Колонка 110 — Тип поглощающего аппарата';
COMMENT ON COLUMN xx_dislocation_rjd.life_ext_sign IS 'Колонка 111 — Признак продления срока службы вагона';
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
