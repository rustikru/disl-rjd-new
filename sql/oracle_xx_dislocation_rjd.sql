-- ================================================================
-- Таблица дислокации вагонов РЖД — Oracle, типизированная схема
-- Числовые поля из Excel хранятся как VARCHAR2 для безопасного импорта
-- ================================================================

CREATE TABLE IF NOT EXISTS xx_dislocation_rjd (
    id                     NUMBER         GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_dt              TIMESTAMP      NOT NULL,

    -- Идентификаторы вагона и накладной
    wagon_no               VARCHAR2(10),   -- 8-значный номер вагона
    waybill_no             VARCHAR2(20),   -- номер накладной
    wagon_type_code        VARCHAR2(50),   -- род вагона (Цистерна, Крытый и т.д.)
    owner_admin            VARCHAR2(10),   -- код администрации-собственника

    -- Даты рейса
    trip_start_dt          VARCHAR2(25),   -- дата начала рейса (из Excel)
    trip_end_dt            VARCHAR2(25),   -- дата окончания рейса

    -- Отправление
    depart_state           VARCHAR2(100),  -- государство отправления
    depart_road            VARCHAR2(200),  -- дорога отправления
    depart_station         VARCHAR2(200),  -- станция отправления

    -- Назначение
    dest_state             VARCHAR2(100),  -- государство назначения
    dest_road              VARCHAR2(200),  -- дорога назначения
    dest_station           VARCHAR2(200),  -- станция назначения

    -- Грузоотправитель
    consignor_tgnl         VARCHAR2(50),
    consignor              VARCHAR2(300),
    consignor_okpo         VARCHAR2(10),   -- ОКПО: 8 цифр
    consignor_name         VARCHAR2(500),

    -- Грузополучатель
    consignee_tgnl         VARCHAR2(50),
    consignee              VARCHAR2(300),
    consignee_okpo         VARCHAR2(10),
    consignee_name         VARCHAR2(500),

    -- Груз
    cargo_name             VARCHAR2(200),  -- наименование груза (используется в фильтрах)
    cargo_gng              VARCHAR2(10),   -- код ГНГ
    cargo_weight_kg        VARCHAR2(15),   -- вес в кг (может быть пустым, "0")

    -- Пробег
    mileage_loaded_km      VARCHAR2(15),
    mileage_empty_km       VARCHAR2(15),
    mileage_total_km       VARCHAR2(15),
    mileage_norm_km        VARCHAR2(15),
    mileage_remain_km      VARCHAR2(15),
    mileage_sign           VARCHAR2(5),

    -- Прочие признаки
    special_marks          VARCHAR2(500),  -- особые отметки
    prev_cargo             VARCHAR2(200),  -- ранее выгруженный груз (используется в фильтрах)

    -- Текущая операция
    oper_station           VARCHAR2(200),  -- используется в запросах
    oper_road              VARCHAR2(200),  -- используется в запросах
    operation              VARCHAR2(200),
    oper_mnemonic          VARCHAR2(10),   -- ОТПР, ПРИБ, РОСПУСК и т.д.
    oper_dt                VARCHAR2(25),   -- дата/время операции

    -- Парк и поезд
    park_type              VARCHAR2(300),  -- тип парка (длинные описания)
    handover_road          VARCHAR2(200),
    receive_road           VARCHAR2(200),
    train_index            VARCHAR2(30),
    train_no               VARCHAR2(10),
    wagon_in_train         VARCHAR2(10),
    park_no                VARCHAR2(20),
    track_no               VARCHAR2(10),

    -- Контейнеры
    seals_count            VARCHAR2(10),
    loaded_containers      VARCHAR2(10),
    empty_containers       VARCHAR2(10),
    container_nos          VARCHAR2(500),  -- номера контейнеров — может быть несколько

    -- Доставка
    norm_delivery_dt       VARCHAR2(25),
    dist_passed_km         VARCHAR2(15),   -- используется в фильтрах
    dist_remain_km         VARCHAR2(15),   -- используется в фильтрах
    dist_total_km          VARCHAR2(15),
    idle_time_hhmmss       VARCHAR2(20),   -- формат ДД:ЧЧ:ММ
    idle_time_days         VARCHAR2(15),   -- используется в фильтрах

    -- Досылка / АСОУП
    extra_waybill_no       VARCHAR2(20),
    extra_send_id          VARCHAR2(30),
    asoup_depart_dt        VARCHAR2(25),
    asoup_arrive_dt        VARCHAR2(25),
    send_id                VARCHAR2(30),
    waybill_id             VARCHAR2(30),
    wagon_no2              VARCHAR2(10),

    -- Состояние вагона
    quality_sign           VARCHAR2(5),
    state_assign_dt        VARCHAR2(25),
    wagon_state            VARCHAR2(200),
    state_reason           VARCHAR2(300),
    state_station          VARCHAR2(200),

    -- Технические даты
    reg_date               VARCHAR2(25),
    build_date             VARCHAR2(25),
    next_repair_dt         VARCHAR2(25),
    next_repair_type       VARCHAR2(20),

    -- Завод
    factory_no             VARCHAR2(30),
    manufacturer           VARCHAR2(200),

    -- Тип и модель
    wagon_type_name        VARCHAR2(200),
    wagon_model            VARCHAR2(100),
    tare_weight            VARCHAR2(15),
    load_capacity          VARCHAR2(15),
    length_mm              VARCHAR2(15),

    -- Ремонты
    last_cap_repair_depot  VARCHAR2(200),
    last_cap_repair_dt     VARCHAR2(25),
    last_dep_repair_depot  VARCHAR2(200),
    last_dep_repair_dt     VARCHAR2(25),

    -- Приписка
    home_road              VARCHAR2(200),
    home_depot             VARCHAR2(200),
    exclude_date           VARCHAR2(25),
    no_transit_reason      VARCHAR2(300),
    prev_wagon_no          VARCHAR2(10),

    -- Собственник
    owner                  VARCHAR2(300),
    owner_okpo             VARCHAR2(10),
    owner_local_code       VARCHAR2(20),
    home_station           VARCHAR2(200),

    -- Аренда
    threshold_sign         VARCHAR2(5),
    lease_sign             VARCHAR2(5),
    life_ext_date          VARCHAR2(25),
    lessee                 VARCHAR2(300),
    lessee_okpo            VARCHAR2(10),
    lessee_local_code      VARCHAR2(20),
    lease_home_station     VARCHAR2(200),
    lease_end_date         VARCHAR2(25),
    service_life           VARCHAR2(10),   -- лет

    -- Характеристики кузова
    body_material_code     VARCHAR2(10),
    body_material_name     VARCHAR2(100),
    body_volume            VARCHAR2(15),
    clearance              VARCHAR2(20),   -- габарит

    -- Техническое оснащение
    air_dist_type          VARCHAR2(10),
    automode               VARCHAR2(5),
    auto_lever             VARCHAR2(5),
    brake_type             VARCHAR2(20),
    coupler_type           VARCHAR2(20),
    bogie_model            VARCHAR2(50),
    shock_absorber         VARCHAR2(50),
    life_ext_sign          VARCHAR2(5),
    boiler_caliber         VARCHAR2(20),
    drain_device           VARCHAR2(10),
    lever_gear             VARCHAR2(10),
    wagon_model_code       VARCHAR2(20),
    repair_by_mileage      VARCHAR2(5),

    -- Оператор по доверенности
    proxy_operator         VARCHAR2(300),
    proxy_operator_okpo    VARCHAR2(10),

    -- Прочее
    wagon_type_code2       VARCHAR2(50),
    wagon_type_cond        VARCHAR2(50),
    axles_count            VARCHAR2(5),    -- обычно 4 или 8
    exclude_depot          VARCHAR2(200),
    exclude_reason         VARCHAR2(300),
    days_to_repair         VARCHAR2(15),   -- используется в расчётах
    days_no_oper           VARCHAR2(15),
    days_no_move           VARCHAR2(15)
);
/

-- Основные индексы для поиска и фильтрации
CREATE INDEX IF NOT EXISTS idx_xx_rjd_report_dt    ON xx_dislocation_rjd (report_dt);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_wagon_no     ON xx_dislocation_rjd (wagon_no);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_dest_road    ON xx_dislocation_rjd (report_dt, dest_road);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_dest_st      ON xx_dislocation_rjd (report_dt, dest_station);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_depart_road  ON xx_dislocation_rjd (report_dt, depart_road);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_oper_road    ON xx_dislocation_rjd (report_dt, oper_road);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_oper_st      ON xx_dislocation_rjd (report_dt, oper_station);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_cargo        ON xx_dislocation_rjd (report_dt, cargo_name);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_mnemonic     ON xx_dislocation_rjd (report_dt, oper_mnemonic);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_park_type    ON xx_dislocation_rjd (report_dt, park_type);
/

COMMENT ON TABLE xx_dislocation_rjd IS 'Дислокация вагонов РЖД — 126 колонок из отчёта ЛК клиента';
/
