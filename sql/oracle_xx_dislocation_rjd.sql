
-- ================================================================

CREATE TABLE xx_users_rjd (
    id            NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username      VARCHAR2(100) UNIQUE NOT NULL,
    display_name  VARCHAR2(255) NOT NULL,
    email         VARCHAR2(255) DEFAULT '',
    password_hash VARCHAR2(255) DEFAULT '',
    is_active     NUMBER(1) DEFAULT 1 NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- администратор (пароль user)
INSERT INTO xx_users_rjd (username, display_name, email, password_hash)
  VALUES ('user', 'Пользователь', 'user@local.ru',
          '$2y$10$1scGJYlRMvoeTRWdI2GH4.ubyq9Z7LoSNeJNffvGbDISeCZ/z1FzC');
COMMIT;
/

CREATE TABLE xx_dislocation_rjd (
    id                     NUMBER         NOT NULL PRIMARY KEY,
    report_dt              TIMESTAMP      NOT NULL,  -- дата справки из ячейки A2
    type_reference         VARCHAR2(50),   -- тип справки: 'Подход' / 'Отправка' (вычисляется при импорте по dest_station)

    -- ── Идентификаторы ───────────────────────────────────────────
    wagon_no               VARCHAR2(500),  -- кол.  1  '50447762'
    waybill_no             VARCHAR2(500),  -- кол.  2  'ЭФ038927'

    -- ── Тип и администрация ──────────────────────────────────────
    wagon_type_code        VARCHAR2(500),  -- кол.  3  'Цистерны (70)'
    owner_admin            VARCHAR2(500),  -- кол.  4  'РЖД (20)'

    -- ── Отправление ──────────────────────────────────────────────
    trip_start_dt          DATE,           -- кол.  5  '01.06.2026 12:42'
    depart_state           VARCHAR2(500),  -- кол.  6  'Российская Федерация (643)'
    depart_road            VARCHAR2(500),  -- кол.  7  'МОСКОВСКАЯ (17)'
    depart_station         VARCHAR2(500),  -- кол.  8  'ПОТОЧИНО (237609)'

    -- ── Назначение ───────────────────────────────────────────────
    trip_end_dt            DATE,           -- кол.  9
    dest_state             VARCHAR2(500),  -- кол. 10
    dest_road              VARCHAR2(500),  -- кол. 11
    dest_station           VARCHAR2(500),  -- кол. 12

    -- ── Грузоотправитель ─────────────────────────────────────────
    consignor_tgnl         VARCHAR2(500),  -- кол. 13  '3034'
    consignor              VARCHAR2(500),  -- кол. 14  'ООО "МЕТАДИНЕА" (72149825)'
    consignor_okpo         VARCHAR2(500),  -- кол. 15  '72149825'
    consignor_name         VARCHAR2(500),  -- кол. 16  'ООО "МЕТАДИНЕА"'

    -- ── Грузополучатель ──────────────────────────────────────────
    consignee_tgnl         VARCHAR2(500),  -- кол. 17
    consignee              VARCHAR2(500),  -- кол. 18
    consignee_okpo         VARCHAR2(500),  -- кол. 19
    consignee_name         VARCHAR2(500),  -- кол. 20

    -- ── Груз ─────────────────────────────────────────────────────
    cargo_name             VARCHAR2(500),  -- кол. 21  длинное описание с кодом
    cargo_gng              VARCHAR2(500),  -- кол. 22  '0', '421034' (код ГНГ)

    -- ── Вес и пробег ─────────────────────────────────────────────
    cargo_weight_kg        NUMBER,         -- кол. 23
    mileage_loaded_km      NUMBER,         -- кол. 24
    mileage_empty_km       NUMBER,         -- кол. 25
    mileage_total_km       NUMBER,         -- кол. 26
    mileage_norm_km        NUMBER,         -- кол. 27
    mileage_remain_km      NUMBER,         -- кол. 28
    mileage_sign           VARCHAR2(500),  -- кол. 29  знак/направление

    -- ── Особые отметки ───────────────────────────────────────────
    special_marks          VARCHAR2(500),  -- кол. 30  '3, 6, 7'
    prev_cargo             VARCHAR2(500),  -- кол. 31  'Спирт метиловый (метанол) (721484)'

    -- ── Текущая операция ─────────────────────────────────────────
    oper_station           VARCHAR2(500),  -- кол. 32
    oper_road              VARCHAR2(500),  -- кол. 33
    operation              VARCHAR2(500),  -- кол. 34  'Корректировка сведений о вагоне в составе поезда (7)'
    oper_mnemonic          VARCHAR2(500),  -- кол. 35  'ОТПР', 'ПРИБ', 'КОРВ', 'ИСКП'
    oper_dt                DATE,           -- кол. 36  '02.06.2026 14:47'

    -- ── Парк и дороги сдачи/приёма ───────────────────────────────
    park_type              VARCHAR2(500),  -- кол. 37  'Транзитный, Порожний, Вагон рабочего парка'
    handover_road          VARCHAR2(500),  -- кол. 38
    receive_road           VARCHAR2(500),  -- кол. 39

    -- ── Поезд и путь ─────────────────────────────────────────────
    train_index            VARCHAR2(500),  -- кол. 40  '237609052230008 (ПОТОЧИНО+052+ОРЕХОВО-ЗУЕВО)'
    train_no               VARCHAR2(500),  -- кол. 41  '9999'
    wagon_in_train         NUMBER(5),      -- кол. 42  37 (позиция в составе)
    park_no                VARCHAR2(500),  -- кол. 43  'I (1)'
    track_no               VARCHAR2(500),  -- кол. 44  ' (5)'

    -- ── Контейнеры ───────────────────────────────────────────────
    seals_count            NUMBER(3),      -- кол. 45
    loaded_containers      NUMBER(3),      -- кол. 46
    empty_containers       NUMBER(3),      -- кол. 47
    container_nos          VARCHAR2(500),  -- кол. 48

    -- ── Доставка и простой ───────────────────────────────────────
    norm_delivery_dt       DATE,           -- кол. 49  '09.06.2026'
    dist_passed_km         NUMBER,         -- кол. 50
    dist_remain_km         NUMBER,         -- кол. 51
    dist_total_km          NUMBER,         -- кол. 52
    idle_time_hhmmss       VARCHAR2(500),  -- кол. 53  '0: 15: 18' (строковый формат)
    idle_time_days         NUMBER,         -- кол. 54

    -- ── Досылка и АСОУП ──────────────────────────────────────────
    extra_waybill_no       VARCHAR2(500),  -- кол. 55
    extra_send_id          VARCHAR2(500),  -- кол. 56
    asoup_depart_dt        DATE,           -- кол. 57
    asoup_arrive_dt        DATE,           -- кол. 58
    send_id                VARCHAR2(500),  -- кол. 59  '2017ЭФ038927'
    waybill_id             VARCHAR2(500),  -- кол. 60  '1751007050'

    -- ── Состояние ────────────────────────────────────────────────
    wagon_no2              VARCHAR2(500),  -- кол. 61  дублирует wagon_no
    quality_sign           VARCHAR2(500),  -- кол. 62  'НАЛИЧИЕ ТЕХНИЧЕСКОГО ПАСПОРТА'
    state_assign_dt        DATE,           -- кол. 63
    wagon_state            VARCHAR2(500),  -- кол. 64  'РП'
    state_reason           VARCHAR2(500),  -- кол. 65  'Деповской ремонт (1)'
    state_station          VARCHAR2(500),  -- кол. 66

    -- ── Даты ремонтов ────────────────────────────────────────────
    reg_date               DATE,           -- кол. 67
    build_date             DATE,           -- кол. 68
    next_repair_dt         DATE,           -- кол. 69
    next_repair_type       VARCHAR2(500),  -- кол. 70  'Капитальный ремонт (2)'

    -- ── Завод и модель ───────────────────────────────────────────
    factory_no             VARCHAR2(500),  -- кол. 71  '794230'
    manufacturer           VARCHAR2(500),  -- кол. 72
    wagon_type_name        VARCHAR2(500),  -- кол. 73
    wagon_model            VARCHAR2(500),  -- кол. 74  '15-1610-02'
    tare_weight            NUMBER,         -- кол. 75  275
    load_capacity          NUMBER,         -- кол. 76  650
    length_mm              NUMBER,         -- кол. 77  12020

    -- ── Депо ремонтов и приписки ─────────────────────────────────
    last_cap_repair_depot  VARCHAR2(500),  -- кол. 78
    last_cap_repair_dt     DATE,           -- кол. 79
    last_dep_repair_depot  VARCHAR2(500),  -- кол. 80
    last_dep_repair_dt     DATE,           -- кол. 81
    home_road              VARCHAR2(500),  -- кол. 82
    home_depot             VARCHAR2(500),  -- кол. 83

    -- ── Исключение и предыдущий номер ────────────────────────────
    exclude_date           DATE,           -- кол. 84
    no_transit_reason      VARCHAR2(500),  -- кол. 85
    prev_wagon_no          VARCHAR2(500),  -- кол. 86  '000000000000'

    -- ── Собственник ──────────────────────────────────────────────
    owner                  VARCHAR2(500),  -- кол. 87
    owner_okpo             VARCHAR2(500),  -- кол. 88
    owner_local_code       VARCHAR2(500),  -- кол. 89  '760643'
    home_station           VARCHAR2(500),  -- кол. 90

    -- ── Аренда ───────────────────────────────────────────────────
    threshold_sign         VARCHAR2(500),  -- кол. 91  'ВАГОН РЕМОНТИРУЕТСЯ ПО ПРОБЕГУ'
    lease_sign             NUMBER(1),      -- кол. 92  0 / 1
    life_ext_date          DATE,           -- кол. 93
    lessee                 VARCHAR2(500),  -- кол. 94
    lessee_okpo            VARCHAR2(500),  -- кол. 95
    lessee_local_code      VARCHAR2(500),  -- кол. 96
    lease_home_station     VARCHAR2(500),  -- кол. 97
    lease_end_date         DATE,           -- кол. 98

    -- ── Срок службы ──────────────────────────────────────────────
    service_life           DATE,           -- кол. 99  дата окончания, не кол-во лет

    -- ── Материал и объём кузова ──────────────────────────────────
    body_material_code     NUMBER(2),      -- кол.100  2
    body_material_name     VARCHAR2(500),  -- кол.101  '09Г2С, 09Г2Д, 09Г2...'
    body_volume            NUMBER,         -- кол.102  86, 88 (м³)
    clearance              VARCHAR2(500),  -- кол.103  '1-Т (3)'

    -- ── Техническое оснащение ────────────────────────────────────
    air_dist_type          VARCHAR2(500),  -- кол.104  '483М-000 (4)'
    automode               VARCHAR2(500),  -- кол.105  'Не оборудован (2)'
    auto_lever             VARCHAR2(500),  -- кол.106  '574-Б (2)', 'РТРП-300 (5)'
    brake_type             VARCHAR2(500),  -- кол.107
    coupler_type           VARCHAR2(500),  -- кол.108
    bogie_model            VARCHAR2(500),  -- кол.109  список моделей тележек через запятую
    shock_absorber         VARCHAR2(500),  -- кол.110

    -- ── Признаки и коды ──────────────────────────────────────────
    life_ext_sign          NUMBER(1),      -- кол.111  0 / 1
    boiler_caliber         NUMBER,         -- кол.112  0
    drain_device           VARCHAR2(500),  -- кол.113
    lever_gear             VARCHAR2(500),  -- кол.114
    wagon_model_code       VARCHAR2(500),  -- кол.115  '903'
    repair_by_mileage      NUMBER(1),      -- кол.116  0 / 1

    -- ── Оператор по доверенности ─────────────────────────────────
    proxy_operator         VARCHAR2(500),  -- кол.117
    proxy_operator_okpo    VARCHAR2(500),  -- кол.118

    -- ── Прочие классификаторы ────────────────────────────────────
    wagon_type_code2       VARCHAR2(500),  -- кол.119  дублирует wagon_type_code
    wagon_type_cond        VARCHAR2(500),  -- кол.120
    axles_count            NUMBER(2),      -- кол.121  4, 8
    exclude_depot          VARCHAR2(500),  -- кол.122
    exclude_reason         VARCHAR2(500),  -- кол.123

    -- ── Дни ──────────────────────────────────────────────────────
    days_to_repair         NUMBER,         -- кол.124
    days_no_oper           NUMBER,         -- кол.125
    days_no_move           NUMBER          -- кол.126
);

-- Триггер для автоматического заполнения ID через XX_DISLOCATION_RJD_SEQ
-- PL/SQL-блок: нужен "/" в конце (в отличие от обычных DDL-операторов)
CREATE OR REPLACE TRIGGER xx_dislocation_rjd_bi
BEFORE INSERT ON xx_dislocation_rjd
FOR EACH ROW
BEGIN
  IF :NEW.id IS NULL THEN
    :NEW.id := xx_dislocation_rjd_seq.NEXTVAL;
  END IF;
END;
/

-- Индексы для всех полей используемых в WHERE / JOIN / ORDER
-- Примечание: Oracle не поддерживает IF NOT EXISTS для CREATE INDEX
CREATE INDEX idx_xx_rjd_report_dt   ON xx_dislocation_rjd (report_dt);
CREATE INDEX idx_xx_rjd_type_ref    ON xx_dislocation_rjd (report_dt, type_reference);
CREATE INDEX idx_xx_rjd_wagon_no    ON xx_dislocation_rjd (wagon_no);
CREATE INDEX idx_xx_rjd_dest        ON xx_dislocation_rjd (report_dt, dest_road, dest_station);
CREATE INDEX idx_xx_rjd_depart      ON xx_dislocation_rjd (report_dt, depart_road, depart_station);
CREATE INDEX idx_xx_rjd_oper        ON xx_dislocation_rjd (report_dt, oper_road, oper_station);
CREATE INDEX idx_xx_rjd_mnemonic    ON xx_dislocation_rjd (report_dt, oper_mnemonic);
CREATE INDEX idx_xx_rjd_cargo       ON xx_dislocation_rjd (report_dt, cargo_name);
CREATE INDEX idx_xx_rjd_park_type   ON xx_dislocation_rjd (report_dt, park_type);
CREATE INDEX idx_xx_rjd_wagon_type  ON xx_dislocation_rjd (report_dt, wagon_type_code);

COMMENT ON TABLE  xx_dislocation_rjd IS 'Дислокация вагонов РЖД — 126 колонок из ЛК клиента РЖД';
COMMENT ON COLUMN xx_dislocation_rjd.report_dt              IS 'Системное [Дата справки]';
COMMENT ON COLUMN xx_dislocation_rjd.type_reference         IS 'Системное [Тип справки]';

-- ── Раздел 1: Данные о вагоне (кол. 1–31) ───────────────────────────────────
COMMENT ON COLUMN xx_dislocation_rjd.wagon_no               IS 'Данные о вагоне [Номер вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.waybill_no             IS 'Данные о вагоне [Номер накладной]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_code        IS 'Данные о вагоне [Тип вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.owner_admin            IS 'Данные о вагоне [Администрация собственника]';
COMMENT ON COLUMN xx_dislocation_rjd.trip_start_dt          IS 'Данные о вагоне [Дата начала рейса]';
COMMENT ON COLUMN xx_dislocation_rjd.depart_state           IS 'Данные о вагоне [Государство отправления]';
COMMENT ON COLUMN xx_dislocation_rjd.depart_road            IS 'Данные о вагоне [Дорога отправления]';
COMMENT ON COLUMN xx_dislocation_rjd.depart_station         IS 'Данные о вагоне [Станция отправления]';
COMMENT ON COLUMN xx_dislocation_rjd.trip_end_dt            IS 'Данные о вагоне [Дата окончания рейса]';
COMMENT ON COLUMN xx_dislocation_rjd.dest_state             IS 'Данные о вагоне [Государство назначения]';
COMMENT ON COLUMN xx_dislocation_rjd.dest_road              IS 'Данные о вагоне [Дорога назначения]';
COMMENT ON COLUMN xx_dislocation_rjd.dest_station           IS 'Данные о вагоне [Станция назначения]';
COMMENT ON COLUMN xx_dislocation_rjd.consignor_tgnl         IS 'Данные о вагоне [ТГНЛ грузоотправителя]';
COMMENT ON COLUMN xx_dislocation_rjd.consignor              IS 'Данные о вагоне [Грузоотправитель]';
COMMENT ON COLUMN xx_dislocation_rjd.consignor_okpo         IS 'Данные о вагоне [ОКПО грузоотправителя]';
COMMENT ON COLUMN xx_dislocation_rjd.consignor_name         IS 'Данные о вагоне [Наименование грузоотправителя]';
COMMENT ON COLUMN xx_dislocation_rjd.consignee_tgnl         IS 'Данные о вагоне [ТГНЛ грузополучателя]';
COMMENT ON COLUMN xx_dislocation_rjd.consignee              IS 'Данные о вагоне [Грузополучатель]';
COMMENT ON COLUMN xx_dislocation_rjd.consignee_okpo         IS 'Данные о вагоне [ОКПО грузополучателя]';
COMMENT ON COLUMN xx_dislocation_rjd.consignee_name         IS 'Данные о вагоне [Наименование грузополучателя]';
COMMENT ON COLUMN xx_dislocation_rjd.cargo_name             IS 'Данные о вагоне [Наименование груза]';
COMMENT ON COLUMN xx_dislocation_rjd.cargo_gng              IS 'Данные о вагоне [Код ГНГ]';
COMMENT ON COLUMN xx_dislocation_rjd.cargo_weight_kg        IS 'Данные о вагоне [Масса груза (кг)]';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_loaded_km      IS 'Данные о вагоне [Пробег гружёный (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_empty_km       IS 'Данные о вагоне [Пробег порожний (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_total_km       IS 'Данные о вагоне [Пробег общий (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_norm_km        IS 'Данные о вагоне [Пробег нормативный (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_remain_km      IS 'Данные о вагоне [Пробег остаток (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.mileage_sign           IS 'Данные о вагоне [Знак пробега]';
COMMENT ON COLUMN xx_dislocation_rjd.special_marks          IS 'Данные о вагоне [Особые отметки]';
COMMENT ON COLUMN xx_dislocation_rjd.prev_cargo             IS 'Данные о вагоне [Предыдущий груз]';

-- ── Раздел 2: Дислокация вагона (кол. 32–60) ─────────────────────────────────
COMMENT ON COLUMN xx_dislocation_rjd.oper_station           IS 'Дислокация вагона [Станция операции]';
COMMENT ON COLUMN xx_dislocation_rjd.oper_road              IS 'Дислокация вагона [Дорога операции]';
COMMENT ON COLUMN xx_dislocation_rjd.operation              IS 'Дислокация вагона [Операция]';
COMMENT ON COLUMN xx_dislocation_rjd.oper_mnemonic          IS 'Дислокация вагона [Мнемоника операции]';
COMMENT ON COLUMN xx_dislocation_rjd.oper_dt                IS 'Дислокация вагона [Дата операции]';
COMMENT ON COLUMN xx_dislocation_rjd.park_type              IS 'Дислокация вагона [Признак парка]';
COMMENT ON COLUMN xx_dislocation_rjd.handover_road          IS 'Дислокация вагона [Дорога сдачи]';
COMMENT ON COLUMN xx_dislocation_rjd.receive_road           IS 'Дислокация вагона [Дорога приёма]';
COMMENT ON COLUMN xx_dislocation_rjd.train_index            IS 'Дислокация вагона [Индекс поезда]';
COMMENT ON COLUMN xx_dislocation_rjd.train_no               IS 'Дислокация вагона [Номер поезда]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_in_train         IS 'Дислокация вагона [Позиция вагона в составе]';
COMMENT ON COLUMN xx_dislocation_rjd.park_no                IS 'Дислокация вагона [Номер парка]';
COMMENT ON COLUMN xx_dislocation_rjd.track_no               IS 'Дислокация вагона [Номер пути]';
COMMENT ON COLUMN xx_dislocation_rjd.seals_count            IS 'Дислокация вагона [Количество пломб]';
COMMENT ON COLUMN xx_dislocation_rjd.loaded_containers      IS 'Дислокация вагона [Гружёные контейнеры]';
COMMENT ON COLUMN xx_dislocation_rjd.empty_containers       IS 'Дислокация вагона [Порожние контейнеры]';
COMMENT ON COLUMN xx_dislocation_rjd.container_nos          IS 'Дислокация вагона [Номера контейнеров]';
COMMENT ON COLUMN xx_dislocation_rjd.norm_delivery_dt       IS 'Дислокация вагона [Нормативная дата доставки]';
COMMENT ON COLUMN xx_dislocation_rjd.dist_passed_km         IS 'Дислокация вагона [Расстояние пройдено (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.dist_remain_km         IS 'Дислокация вагона [Расстояние остаток (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.dist_total_km          IS 'Дислокация вагона [Расстояние общее (км)]';
COMMENT ON COLUMN xx_dislocation_rjd.idle_time_hhmmss       IS 'Дислокация вагона [Простой (ЧЧ:ММ:СС)]';
COMMENT ON COLUMN xx_dislocation_rjd.idle_time_days         IS 'Дислокация вагона [Простой (сут.)]';
COMMENT ON COLUMN xx_dislocation_rjd.extra_waybill_no       IS 'Дислокация вагона [Номер досылочной накладной]';
COMMENT ON COLUMN xx_dislocation_rjd.extra_send_id          IS 'Дислокация вагона [Идентификатор досылки]';
COMMENT ON COLUMN xx_dislocation_rjd.asoup_depart_dt        IS 'Дислокация вагона [Дата отправления АСОУП]';
COMMENT ON COLUMN xx_dislocation_rjd.asoup_arrive_dt        IS 'Дислокация вагона [Дата прибытия АСОУП]';
COMMENT ON COLUMN xx_dislocation_rjd.send_id                IS 'Дислокация вагона [Идентификатор отправки]';
COMMENT ON COLUMN xx_dislocation_rjd.waybill_id             IS 'Дислокация вагона [Идентификатор накладной]';

-- ── Раздел 3: Техническое состояние вагона (кол. 61–126) ─────────────────────
COMMENT ON COLUMN xx_dislocation_rjd.wagon_no2              IS 'Техническое состояние вагона [Номер вагона (дубль)]';
COMMENT ON COLUMN xx_dislocation_rjd.quality_sign           IS 'Техническое состояние вагона [Признак качества]';
COMMENT ON COLUMN xx_dislocation_rjd.state_assign_dt        IS 'Техническое состояние вагона [Дата присвоения состояния]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_state            IS 'Техническое состояние вагона [Состояние вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.state_reason           IS 'Техническое состояние вагона [Причина состояния]';
COMMENT ON COLUMN xx_dislocation_rjd.state_station          IS 'Техническое состояние вагона [Станция состояния]';
COMMENT ON COLUMN xx_dislocation_rjd.reg_date               IS 'Техническое состояние вагона [Дата регистрации]';
COMMENT ON COLUMN xx_dislocation_rjd.build_date             IS 'Техническое состояние вагона [Дата постройки]';
COMMENT ON COLUMN xx_dislocation_rjd.next_repair_dt         IS 'Техническое состояние вагона [Дата следующего ремонта]';
COMMENT ON COLUMN xx_dislocation_rjd.next_repair_type       IS 'Техническое состояние вагона [Тип следующего ремонта]';
COMMENT ON COLUMN xx_dislocation_rjd.factory_no             IS 'Техническое состояние вагона [Заводской номер]';
COMMENT ON COLUMN xx_dislocation_rjd.manufacturer           IS 'Техническое состояние вагона [Завод-изготовитель]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_name        IS 'Техническое состояние вагона [Наименование типа вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_model            IS 'Техническое состояние вагона [Модель вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.tare_weight            IS 'Техническое состояние вагона [Масса тары]';
COMMENT ON COLUMN xx_dislocation_rjd.load_capacity          IS 'Техническое состояние вагона [Грузоподъёмность]';
COMMENT ON COLUMN xx_dislocation_rjd.length_mm              IS 'Техническое состояние вагона [Длина (мм)]';
COMMENT ON COLUMN xx_dislocation_rjd.last_cap_repair_depot  IS 'Техническое состояние вагона [Депо последнего капитального ремонта]';
COMMENT ON COLUMN xx_dislocation_rjd.last_cap_repair_dt     IS 'Техническое состояние вагона [Дата последнего капитального ремонта]';
COMMENT ON COLUMN xx_dislocation_rjd.last_dep_repair_depot  IS 'Техническое состояние вагона [Депо последнего деповского ремонта]';
COMMENT ON COLUMN xx_dislocation_rjd.last_dep_repair_dt     IS 'Техническое состояние вагона [Дата последнего деповского ремонта]';
COMMENT ON COLUMN xx_dislocation_rjd.home_road              IS 'Техническое состояние вагона [Дорога приписки]';
COMMENT ON COLUMN xx_dislocation_rjd.home_depot             IS 'Техническое состояние вагона [Депо приписки]';
COMMENT ON COLUMN xx_dislocation_rjd.exclude_date           IS 'Техническое состояние вагона [Дата исключения]';
COMMENT ON COLUMN xx_dislocation_rjd.no_transit_reason      IS 'Техническое состояние вагона [Причина запрета транзита]';
COMMENT ON COLUMN xx_dislocation_rjd.prev_wagon_no          IS 'Техническое состояние вагона [Предыдущий номер вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.owner                  IS 'Техническое состояние вагона [Собственник]';
COMMENT ON COLUMN xx_dislocation_rjd.owner_okpo             IS 'Техническое состояние вагона [ОКПО собственника]';
COMMENT ON COLUMN xx_dislocation_rjd.owner_local_code       IS 'Техническое состояние вагона [Местный код собственника]';
COMMENT ON COLUMN xx_dislocation_rjd.home_station           IS 'Техническое состояние вагона [Станция приписки]';
COMMENT ON COLUMN xx_dislocation_rjd.threshold_sign         IS 'Техническое состояние вагона [Признак порога]';
COMMENT ON COLUMN xx_dislocation_rjd.lease_sign             IS 'Техническое состояние вагона [Признак аренды]';
COMMENT ON COLUMN xx_dislocation_rjd.life_ext_date          IS 'Техническое состояние вагона [Дата продления срока службы]';
COMMENT ON COLUMN xx_dislocation_rjd.lessee                 IS 'Техническое состояние вагона [Арендатор]';
COMMENT ON COLUMN xx_dislocation_rjd.lessee_okpo            IS 'Техническое состояние вагона [ОКПО арендатора]';
COMMENT ON COLUMN xx_dislocation_rjd.lessee_local_code      IS 'Техническое состояние вагона [Местный код арендатора]';
COMMENT ON COLUMN xx_dislocation_rjd.lease_home_station     IS 'Техническое состояние вагона [Станция приписки арендатора]';
COMMENT ON COLUMN xx_dislocation_rjd.lease_end_date         IS 'Техническое состояние вагона [Дата окончания аренды]';
COMMENT ON COLUMN xx_dislocation_rjd.service_life           IS 'Техническое состояние вагона [Срок службы (дата окончания)]';
COMMENT ON COLUMN xx_dislocation_rjd.body_material_code     IS 'Техническое состояние вагона [Код материала кузова]';
COMMENT ON COLUMN xx_dislocation_rjd.body_material_name     IS 'Техническое состояние вагона [Материал кузова]';
COMMENT ON COLUMN xx_dislocation_rjd.body_volume            IS 'Техническое состояние вагона [Объём кузова (м³)]';
COMMENT ON COLUMN xx_dislocation_rjd.clearance              IS 'Техническое состояние вагона [Габарит]';
COMMENT ON COLUMN xx_dislocation_rjd.air_dist_type          IS 'Техническое состояние вагона [Тип воздухораспределителя]';
COMMENT ON COLUMN xx_dislocation_rjd.automode               IS 'Техническое состояние вагона [Авторежим]';
COMMENT ON COLUMN xx_dislocation_rjd.auto_lever             IS 'Техническое состояние вагона [Авторычаг]';
COMMENT ON COLUMN xx_dislocation_rjd.brake_type             IS 'Техническое состояние вагона [Тип тормоза]';
COMMENT ON COLUMN xx_dislocation_rjd.coupler_type           IS 'Техническое состояние вагона [Тип автосцепки]';
COMMENT ON COLUMN xx_dislocation_rjd.bogie_model            IS 'Техническое состояние вагона [Модели тележек]';
COMMENT ON COLUMN xx_dislocation_rjd.shock_absorber         IS 'Техническое состояние вагона [Поглощающий аппарат]';
COMMENT ON COLUMN xx_dislocation_rjd.life_ext_sign          IS 'Техническое состояние вагона [Признак продления срока службы]';
COMMENT ON COLUMN xx_dislocation_rjd.boiler_caliber         IS 'Техническое состояние вагона [Калибр котла]';
COMMENT ON COLUMN xx_dislocation_rjd.drain_device           IS 'Техническое состояние вагона [Сливной прибор]';
COMMENT ON COLUMN xx_dislocation_rjd.lever_gear             IS 'Техническое состояние вагона [Рычажная передача]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_model_code       IS 'Техническое состояние вагона [Код модели вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.repair_by_mileage      IS 'Техническое состояние вагона [Ремонт по пробегу]';
COMMENT ON COLUMN xx_dislocation_rjd.proxy_operator         IS 'Техническое состояние вагона [Оператор по доверенности]';
COMMENT ON COLUMN xx_dislocation_rjd.proxy_operator_okpo    IS 'Техническое состояние вагона [ОКПО оператора по доверенности]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_code2       IS 'Техническое состояние вагона [Тип вагона (дубль)]';
COMMENT ON COLUMN xx_dislocation_rjd.wagon_type_cond        IS 'Техническое состояние вагона [Условный тип вагона]';
COMMENT ON COLUMN xx_dislocation_rjd.axles_count            IS 'Техническое состояние вагона [Количество осей]';
COMMENT ON COLUMN xx_dislocation_rjd.exclude_depot          IS 'Техническое состояние вагона [Депо исключения]';
COMMENT ON COLUMN xx_dislocation_rjd.exclude_reason         IS 'Техническое состояние вагона [Причина исключения]';
COMMENT ON COLUMN xx_dislocation_rjd.days_to_repair         IS 'Техническое состояние вагона [Дней до ремонта]';
COMMENT ON COLUMN xx_dislocation_rjd.days_no_oper           IS 'Техническое состояние вагона [Дней без операций]';
COMMENT ON COLUMN xx_dislocation_rjd.days_no_move           IS 'Техническое состояние вагона [Дней без движения]';
