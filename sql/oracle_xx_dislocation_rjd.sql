-- ================================================================
-- Таблица дислокации вагонов РЖД — Oracle
-- Типы основаны на анализе реальных данных из Excel-выгрузки
-- ID управляется через XX_DISLOCATION_RJD_SEQ + триггер
-- ================================================================

CREATE TABLE IF NOT EXISTS xx_dislocation_rjd (
    id                     NUMBER         NOT NULL PRIMARY KEY,
    report_dt              TIMESTAMP      NOT NULL,

    -- Кол 1-2: идентификаторы
    wagon_no               VARCHAR2(10),   -- 8-значный номер: '50447762'
    waybill_no             VARCHAR2(15),   -- Кирилл.+цифры: 'ЭФ038927'

    -- Кол 3-4: тип и администрация
    wagon_type_code        VARCHAR2(50),   -- 'Цистерны (70)'
    owner_admin            VARCHAR2(30),   -- 'РЖД (20)'

    -- Кол 5-8: отправление
    trip_start_dt          DATE,           -- '01.06.2026 12:42'
    depart_state           VARCHAR2(50),   -- 'Российская Федерация (643)'
    depart_road            VARCHAR2(100),  -- 'МОСКОВСКАЯ (17)'
    depart_station         VARCHAR2(100),  -- 'ПОТОЧИНО (237609)'

    -- Кол 9-12: назначение
    trip_end_dt            DATE,
    dest_state             VARCHAR2(50),
    dest_road              VARCHAR2(100),
    dest_station           VARCHAR2(100),

    -- Кол 13-16: грузоотправитель
    consignor_tgnl         VARCHAR2(10),   -- '3034'
    consignor              VARCHAR2(200),  -- 'ООО "МЕТАДИНЕА" (72149825)'
    consignor_okpo         VARCHAR2(10),   -- '72149825'
    consignor_name         VARCHAR2(200),  -- 'ООО "МЕТАДИНЕА"'

    -- Кол 17-20: грузополучатель
    consignee_tgnl         VARCHAR2(10),
    consignee              VARCHAR2(200),
    consignee_okpo         VARCHAR2(10),
    consignee_name         VARCHAR2(200),

    -- Кол 21-22: груз
    cargo_name             VARCHAR2(500),  -- длинные описания с кодом
    cargo_gng              VARCHAR2(10),   -- '0', '421034' (код ГНГ)

    -- Кол 23-29: вес и пробег
    cargo_weight_kg        NUMBER,
    mileage_loaded_km      NUMBER,
    mileage_empty_km       NUMBER,
    mileage_total_km       NUMBER,
    mileage_norm_km        NUMBER,
    mileage_remain_km      NUMBER,
    mileage_sign           VARCHAR2(10),   -- знак/направление

    -- Кол 30-31: особые отметки
    special_marks          VARCHAR2(50),   -- '3, 6, 7'
    prev_cargo             VARCHAR2(200),  -- 'Спирт метиловый (метанол) (721484)'

    -- Кол 32-36: текущая операция
    oper_station           VARCHAR2(100),
    oper_road              VARCHAR2(100),
    operation              VARCHAR2(200),  -- 'Корректировка сведений о вагоне в составе поезда (7)'
    oper_mnemonic          VARCHAR2(10),   -- 'ОТПР', 'ПРИБ', 'КОРВ', 'ИСКП'
    oper_dt                DATE,           -- '02.06.2026 14:47'

    -- Кол 37-39: парк и дороги сдачи/приёма
    park_type              VARCHAR2(200),  -- 'Транзитный, Порожний, Вагон рабочего парка'
    handover_road          VARCHAR2(100),
    receive_road           VARCHAR2(100),

    -- Кол 40-44: поезд и путь
    train_index            VARCHAR2(100),  -- '237609052230008 (ПОТОЧИНО+052+ОРЕХОВО-ЗУЕВО)'
    train_no               VARCHAR2(10),   -- '9999' (идентификатор поезда)
    wagon_in_train         NUMBER(5),      -- 37 (позиция в составе)
    park_no                VARCHAR2(10),   -- 'I (1)' (содержит текст)
    track_no               VARCHAR2(10),   -- ' (5)' (содержит скобки)

    -- Кол 45-48: контейнеры
    seals_count            NUMBER(3),
    loaded_containers      NUMBER(3),
    empty_containers       NUMBER(3),
    container_nos          VARCHAR2(200),

    -- Кол 49-54: доставка и простой
    norm_delivery_dt       DATE,           -- '09.06.2026'
    dist_passed_km         NUMBER,
    dist_remain_km         NUMBER,
    dist_total_km          NUMBER,
    idle_time_hhmmss       VARCHAR2(20),   -- '0: 15: 18' (строковый формат времени)
    idle_time_days         NUMBER,

    -- Кол 55-60: досылка и АСОУП
    extra_waybill_no       VARCHAR2(20),
    extra_send_id          VARCHAR2(30),
    asoup_depart_dt        DATE,
    asoup_arrive_dt        DATE,
    send_id                VARCHAR2(30),   -- '2017ЭФ038927'
    waybill_id             VARCHAR2(15),   -- '1751007050' (идентификатор)

    -- Кол 61-66: состояние
    wagon_no2              VARCHAR2(10),
    quality_sign           VARCHAR2(100),  -- 'НАЛИЧИЕ ТЕХНИЧЕСКОГО ПАСПОРТА'
    state_assign_dt        DATE,
    wagon_state            VARCHAR2(10),   -- 'РП'
    state_reason           VARCHAR2(100),
    state_station          VARCHAR2(100),

    -- Кол 67-70: даты ремонтов
    reg_date               DATE,
    build_date             DATE,
    next_repair_dt         DATE,
    next_repair_type       VARCHAR2(50),   -- 'Капитальный ремонт (2)'

    -- Кол 71-77: завод и модель
    factory_no             VARCHAR2(15),   -- '794230' (серийный номер)
    manufacturer           VARCHAR2(100),
    wagon_type_name        VARCHAR2(200),
    wagon_model            VARCHAR2(20),   -- '15-1610-02'
    tare_weight            NUMBER,         -- 275
    load_capacity          NUMBER,         -- 650
    length_mm              NUMBER,         -- 12020

    -- Кол 78-83: депо ремонтов и приписки
    last_cap_repair_depot  VARCHAR2(200),
    last_cap_repair_dt     DATE,
    last_dep_repair_depot  VARCHAR2(200),
    last_dep_repair_dt     DATE,
    home_road              VARCHAR2(100),
    home_depot             VARCHAR2(100),

    -- Кол 84-86: исключение и предыдущий номер
    exclude_date           DATE,
    no_transit_reason      VARCHAR2(200),
    prev_wagon_no          VARCHAR2(15),

    -- Кол 87-90: собственник
    owner                  VARCHAR2(200),
    owner_okpo             VARCHAR2(10),
    owner_local_code       VARCHAR2(10),   -- '760643' (локальный код)
    home_station           VARCHAR2(100),

    -- Кол 91-98: аренда
    threshold_sign         VARCHAR2(100),  -- 'ВАГОН РЕМОНТИРУЕТСЯ ПО ПРОБЕГУ'
    lease_sign             NUMBER(1),      -- 0 / 1
    life_ext_date          DATE,
    lessee                 VARCHAR2(200),
    lessee_okpo            VARCHAR2(10),
    lessee_local_code      VARCHAR2(10),
    lease_home_station     VARCHAR2(100),
    lease_end_date         DATE,

    -- Кол 99: срок службы — дата окончания
    service_life           DATE,           -- '30.03.2031' — дата, не количество лет

    -- Кол 100-103: материал и объём кузова
    body_material_code     NUMBER(2),      -- 2
    body_material_name     VARCHAR2(200),  -- '09Г2С, 09Г2Д, 09Г2...'
    body_volume            NUMBER,         -- 86, 88 (м³)
    clearance              VARCHAR2(20),   -- '1-Т (3)'

    -- Кол 104-110: техническое оснащение
    air_dist_type          VARCHAR2(30),   -- '483М-000 (4)'
    automode               VARCHAR2(50),   -- 'Не оборудован (2)'
    auto_lever             VARCHAR2(30),   -- '574-Б (2)', 'РТРП-300 (5)'
    brake_type             VARCHAR2(30),
    coupler_type           VARCHAR2(20),
    bogie_model            VARCHAR2(1000), -- список из 20+ моделей через запятую
    shock_absorber         VARCHAR2(50),

    -- Кол 111-116: признаки и коды
    life_ext_sign          NUMBER(1),      -- 0 / 1
    boiler_caliber         NUMBER,         -- 0
    drain_device           VARCHAR2(10),
    lever_gear             VARCHAR2(10),
    wagon_model_code       VARCHAR2(10),   -- '903' (код модели)
    repair_by_mileage      NUMBER(1),      -- 0 / 1

    -- Кол 117-118: оператор по доверенности
    proxy_operator         VARCHAR2(200),
    proxy_operator_okpo    VARCHAR2(10),

    -- Кол 119-123: прочие классификаторы
    wagon_type_code2       VARCHAR2(50),
    wagon_type_cond        VARCHAR2(20),
    axles_count            NUMBER(2),      -- 4, 8
    exclude_depot          VARCHAR2(100),
    exclude_reason         VARCHAR2(100),

    -- Кол 124-126: дни
    days_to_repair         NUMBER,
    days_no_oper           NUMBER,
    days_no_move           NUMBER
);
/

-- Триггер для автоматического использования XX_DISLOCATION_RJD_SEQ
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
CREATE INDEX IF NOT EXISTS idx_xx_rjd_report_dt   ON xx_dislocation_rjd (report_dt);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_wagon_no    ON xx_dislocation_rjd (wagon_no);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_dest        ON xx_dislocation_rjd (report_dt, dest_road, dest_station);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_depart      ON xx_dislocation_rjd (report_dt, depart_road, depart_station);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_oper        ON xx_dislocation_rjd (report_dt, oper_road, oper_station);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_mnemonic    ON xx_dislocation_rjd (report_dt, oper_mnemonic);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_cargo       ON xx_dislocation_rjd (report_dt, cargo_name);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_park_type   ON xx_dislocation_rjd (report_dt, park_type);
/
CREATE INDEX IF NOT EXISTS idx_xx_rjd_wagon_type  ON xx_dislocation_rjd (report_dt, wagon_type_code);
/

COMMENT ON TABLE  xx_dislocation_rjd IS 'Дислокация вагонов РЖД — 126 колонок из ЛК клиента РЖД';
COMMENT ON COLUMN xx_dislocation_rjd.bogie_model  IS 'Список моделей тележек через запятую, VARCHAR2(1000)';
COMMENT ON COLUMN xx_dislocation_rjd.service_life IS 'Дата окончания срока службы';
COMMENT ON COLUMN xx_dislocation_rjd.threshold_sign IS 'Признак порога — может быть длинной строкой';
/
