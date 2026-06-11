-- ================================================================
-- Таблица дислокации вагонов РЖД — Oracle
-- Типы основаны на анализе реальных данных из Excel-выгрузки
-- ID управляется через XX_DISLOCATION_RJD_SEQ + триггер
-- Номера столбцов (кол. N) — позиция в Excel-файле выгрузки РЖД
--
-- ЗАПУСК: DBeaver / sqlcl — разделитель ";"
--   (без отдельных "/" — это только для SQL*Plus)
-- ================================================================

CREATE TABLE IF NOT EXISTS xx_dislocation_rjd (
    id                     NUMBER         NOT NULL PRIMARY KEY,
    report_dt              TIMESTAMP      NOT NULL,  -- дата справки из ячейки A2

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
CREATE OR REPLACE TRIGGER xx_dislocation_rjd_bi
BEFORE INSERT ON xx_dislocation_rjd
FOR EACH ROW
BEGIN
  IF :NEW.id IS NULL THEN
    :NEW.id := xx_dislocation_rjd_seq.NEXTVAL;
  END IF;
END;

-- Индексы для всех полей используемых в WHERE / JOIN / ORDER
CREATE INDEX IF NOT EXISTS idx_xx_rjd_report_dt   ON xx_dislocation_rjd (report_dt);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_wagon_no    ON xx_dislocation_rjd (wagon_no);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_dest        ON xx_dislocation_rjd (report_dt, dest_road, dest_station);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_depart      ON xx_dislocation_rjd (report_dt, depart_road, depart_station);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_oper        ON xx_dislocation_rjd (report_dt, oper_road, oper_station);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_mnemonic    ON xx_dislocation_rjd (report_dt, oper_mnemonic);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_cargo       ON xx_dislocation_rjd (report_dt, cargo_name);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_park_type   ON xx_dislocation_rjd (report_dt, park_type);
CREATE INDEX IF NOT EXISTS idx_xx_rjd_wagon_type  ON xx_dislocation_rjd (report_dt, wagon_type_code);

COMMENT ON TABLE  xx_dislocation_rjd IS 'Дислокация вагонов РЖД — 126 колонок из ЛК клиента РЖД';
COMMENT ON COLUMN xx_dislocation_rjd.bogie_model    IS 'Кол.109 — список моделей тележек через запятую';
COMMENT ON COLUMN xx_dislocation_rjd.service_life   IS 'Кол.99 — дата окончания срока службы (не количество лет)';
COMMENT ON COLUMN xx_dislocation_rjd.threshold_sign IS 'Кол.91 — признак порога — может быть длинной строкой';
