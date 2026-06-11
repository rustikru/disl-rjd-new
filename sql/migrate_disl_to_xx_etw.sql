-- ================================================================
-- Миграция схемы: disl -> xx_etw
-- Выполнить от имени SYS или SYSTEM:
--   docker cp migrate_disl_to_xx_etw.sql disl_oracle:/tmp/
--   docker exec -it disl_oracle sqlplus system/Oracle123!@FREEPDB1 @/tmp/migrate_disl_to_xx_etw.sql
-- ================================================================

-- ── 1. Создаём нового пользователя ───────────────────────────────
CREATE USER xx_etw IDENTIFIED BY xx_etw123;
GRANT CONNECT, RESOURCE, UNLIMITED TABLESPACE TO xx_etw;
/

-- ── 2. Создаём структуру таблиц в схеме xx_etw ───────────────────

CREATE TABLE xx_etw.users (
    id            NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username      VARCHAR2(100) UNIQUE NOT NULL,
    display_name  VARCHAR2(255) NOT NULL,
    email         VARCHAR2(255) DEFAULT '',
    password_hash VARCHAR2(255) DEFAULT '',
    is_active     NUMBER(1) DEFAULT 1 NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE xx_etw.wagon_dislocation (
    id            NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_date   DATE NOT NULL,
    section_id    VARCHAR2(50) NOT NULL,
    section_name  VARCHAR2(255) NOT NULL,
    subsection    VARCHAR2(255),
    park          VARCHAR2(255),
    wagon_type    VARCHAR2(100) NOT NULL,
    wagon_group   VARCHAR2(50) NOT NULL,
    wagon_count   NUMBER DEFAULT 0 NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX xx_etw.idx_disloc_date    ON xx_etw.wagon_dislocation(report_date);
CREATE INDEX xx_etw.idx_disloc_section ON xx_etw.wagon_dislocation(report_date, section_id);

CREATE TABLE xx_etw.wagon_extended (
    id               NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_date      DATE NOT NULL,
    wagon_no         VARCHAR2(20) NOT NULL,
    train            VARCHAR2(20),
    current_station  VARCHAR2(255),
    from_station     VARCHAR2(255),
    to_station       VARCHAR2(255),
    cargo            VARCHAR2(100),
    wagon_count      NUMBER DEFAULT 1,
    status           VARCHAR2(20) DEFAULT 'empty',
    status_label     VARCHAR2(50),
    days_en_route    NUMBER DEFAULT 0,
    expected_arrival VARCHAR2(20),
    park             VARCHAR2(50)
);

CREATE TABLE xx_etw.wagon_approach (
    id                  NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_date         DATE NOT NULL,
    road                VARCHAR2(255) NOT NULL,
    direction           VARCHAR2(10) NOT NULL CHECK (direction IN ('arrive', 'depart')),
    wagon_count         NUMBER DEFAULT 0 NOT NULL,
    wagon_type          VARCHAR2(100) NOT NULL,
    destination_station VARCHAR2(255),
    expected_time       VARCHAR2(20)
);

CREATE TABLE IF NOT EXISTS xx_etw.xx_dislocation_rjd (
    id                     NUMBER          GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_dt              TIMESTAMP       NOT NULL,
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

CREATE INDEX IF NOT EXISTS xx_etw.idx_xx_dislocn_report_dt ON xx_etw.xx_dislocation_rjd (report_dt);
CREATE INDEX IF NOT EXISTS xx_etw.idx_xx_dislocn_wagon_no  ON xx_etw.xx_dislocation_rjd (wagon_no);
/

-- ── 3. Копируем данные (OVERRIDING SYSTEM VALUE сохраняет оригинальные ID) ──

INSERT INTO xx_etw.users
    (id, username, display_name, email, password_hash, is_active, created_at)
OVERRIDING SYSTEM VALUE
SELECT id, username, display_name, email, password_hash, is_active, created_at
FROM disl.users;

INSERT INTO xx_etw.wagon_dislocation
    (id, report_date, section_id, section_name, subsection, park,
     wagon_type, wagon_group, wagon_count, created_at)
OVERRIDING SYSTEM VALUE
SELECT id, report_date, section_id, section_name, subsection, park,
       wagon_type, wagon_group, wagon_count, created_at
FROM disl.wagon_dislocation;

INSERT INTO xx_etw.wagon_extended
    (id, report_date, wagon_no, train, current_station, from_station,
     to_station, cargo, wagon_count, status, status_label,
     days_en_route, expected_arrival, park)
OVERRIDING SYSTEM VALUE
SELECT id, report_date, wagon_no, train, current_station, from_station,
       to_station, cargo, wagon_count, status, status_label,
       days_en_route, expected_arrival, park
FROM disl.wagon_extended;

INSERT INTO xx_etw.wagon_approach
    (id, report_date, road, direction, wagon_count,
     wagon_type, destination_station, expected_time)
OVERRIDING SYSTEM VALUE
SELECT id, report_date, road, direction, wagon_count,
       wagon_type, destination_station, expected_time
FROM disl.wagon_approach;

INSERT INTO xx_etw.xx_dislocation_rjd
    (id, report_dt, wagon_no, waybill_no, wagon_type_code, owner_admin,
     trip_start_dt, depart_state, depart_road, depart_station,
     trip_end_dt, dest_state, dest_road, dest_station,
     consignor_tgnl, consignor, consignor_okpo, consignor_name,
     consignee_tgnl, consignee, consignee_okpo, consignee_name,
     cargo_name, cargo_gng, cargo_weight_kg,
     mileage_loaded_km, mileage_empty_km, mileage_total_km,
     mileage_norm_km, mileage_remain_km, mileage_sign,
     special_marks, prev_cargo, oper_station, oper_road,
     operation, oper_mnemonic, oper_dt, park_type,
     handover_road, receive_road, train_index, train_no,
     wagon_in_train, park_no, track_no, seals_count,
     loaded_containers, empty_containers, container_nos,
     norm_delivery_dt, dist_passed_km, dist_remain_km, dist_total_km,
     idle_time_hhmmss, idle_time_days, extra_waybill_no, extra_send_id,
     asoup_depart_dt, asoup_arrive_dt, send_id, waybill_id, wagon_no2,
     quality_sign, state_assign_dt, wagon_state, state_reason, state_station,
     reg_date, build_date, next_repair_dt, next_repair_type,
     factory_no, manufacturer, wagon_type_name, wagon_model,
     tare_weight, load_capacity, length_mm,
     last_cap_repair_depot, last_cap_repair_dt,
     last_dep_repair_depot, last_dep_repair_dt,
     home_road, home_depot, exclude_date, no_transit_reason,
     prev_wagon_no, owner, owner_okpo, owner_local_code, home_station,
     threshold_sign, lease_sign, life_ext_date,
     lessee, lessee_okpo, lessee_local_code, lease_home_station, lease_end_date,
     service_life, body_material_code, body_material_name, body_volume,
     clearance, air_dist_type, automode, auto_lever, brake_type,
     coupler_type, bogie_model, shock_absorber, life_ext_sign,
     boiler_caliber, drain_device, lever_gear, wagon_model_code,
     repair_by_mileage, proxy_operator, proxy_operator_okpo,
     wagon_type_code2, wagon_type_cond, axles_count,
     exclude_depot, exclude_reason,
     days_to_repair, days_no_oper, days_no_move)
OVERRIDING SYSTEM VALUE
SELECT id, report_dt, wagon_no, waybill_no, wagon_type_code, owner_admin,
     trip_start_dt, depart_state, depart_road, depart_station,
     trip_end_dt, dest_state, dest_road, dest_station,
     consignor_tgnl, consignor, consignor_okpo, consignor_name,
     consignee_tgnl, consignee, consignee_okpo, consignee_name,
     cargo_name, cargo_gng, cargo_weight_kg,
     mileage_loaded_km, mileage_empty_km, mileage_total_km,
     mileage_norm_km, mileage_remain_km, mileage_sign,
     special_marks, prev_cargo, oper_station, oper_road,
     operation, oper_mnemonic, oper_dt, park_type,
     handover_road, receive_road, train_index, train_no,
     wagon_in_train, park_no, track_no, seals_count,
     loaded_containers, empty_containers, container_nos,
     norm_delivery_dt, dist_passed_km, dist_remain_km, dist_total_km,
     idle_time_hhmmss, idle_time_days, extra_waybill_no, extra_send_id,
     asoup_depart_dt, asoup_arrive_dt, send_id, waybill_id, wagon_no2,
     quality_sign, state_assign_dt, wagon_state, state_reason, state_station,
     reg_date, build_date, next_repair_dt, next_repair_type,
     factory_no, manufacturer, wagon_type_name, wagon_model,
     tare_weight, load_capacity, length_mm,
     last_cap_repair_depot, last_cap_repair_dt,
     last_dep_repair_depot, last_dep_repair_dt,
     home_road, home_depot, exclude_date, no_transit_reason,
     prev_wagon_no, owner, owner_okpo, owner_local_code, home_station,
     threshold_sign, lease_sign, life_ext_date,
     lessee, lessee_okpo, lessee_local_code, lease_home_station, lease_end_date,
     service_life, body_material_code, body_material_name, body_volume,
     clearance, air_dist_type, automode, auto_lever, brake_type,
     coupler_type, bogie_model, shock_absorber, life_ext_sign,
     boiler_caliber, drain_device, lever_gear, wagon_model_code,
     repair_by_mileage, proxy_operator, proxy_operator_okpo,
     wagon_type_code2, wagon_type_cond, axles_count,
     exclude_depot, exclude_reason,
     days_to_repair, days_no_oper, days_no_move
FROM disl.xx_dislocation_rjd;

COMMIT;
/

-- ── 4. Проверка (убедись что строки совпадают перед удалением) ────
SELECT 'users' AS tbl,
       (SELECT COUNT(*) FROM disl.users) AS old_cnt,
       (SELECT COUNT(*) FROM xx_etw.users) AS new_cnt FROM dual;

SELECT 'xx_dislocation_rjd' AS tbl,
       (SELECT COUNT(*) FROM disl.xx_dislocation_rjd) AS old_cnt,
       (SELECT COUNT(*) FROM xx_etw.xx_dislocation_rjd) AS new_cnt FROM dual;
/

-- ── 5. Удалить старую схему (выполнить ТОЛЬКО после проверки!) ────
-- DROP USER disl CASCADE;
-- COMMIT;
/
