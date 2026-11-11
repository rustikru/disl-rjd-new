-- Справочники станций, нормативов претензий и контроля простоев. Только чтение.
SET PAGESIZE 300
SET LINESIZE 280

PROMPT === Дубли кодов ЕСР ===
SELECT esr_code, COUNT(*) AS row_count,
       LISTAGG(station_name, ' | ') WITHIN GROUP (ORDER BY station_name) AS station_names
  FROM xx_rjd_stations
 WHERE esr_code IS NOT NULL
 GROUP BY esr_code
HAVING COUNT(*) > 1
 ORDER BY esr_code;

PROMPT === Станции без координат ===
SELECT esr_code, station_name, railway_name, region_name
  FROM xx_rjd_stations
 WHERE latitude IS NULL OR longitude IS NULL
 ORDER BY railway_name, station_name;

PROMPT === Некорректные координаты станций ===
SELECT esr_code, station_name, latitude, longitude
  FROM xx_rjd_stations
 WHERE latitude IS NOT NULL AND longitude IS NOT NULL
   AND (NOT REGEXP_LIKE(REPLACE(latitude, ',', '.'), '^-?[0-9]+([.][0-9]+)?$')
     OR NOT REGEXP_LIKE(REPLACE(longitude, ',', '.'), '^-?[0-9]+([.][0-9]+)?$'))
 ORDER BY station_name;

PROMPT === Действующие нормативы претензий ===
SELECT n.id, o.code AS organization_code, n.station_code,
       n.rule_type, n.rule_value, n.free_days, n.daily_rate,
       n.valid_from, n.valid_to
  FROM xx_rjd_claim_norms n
  JOIN xx_rjd_organizations o ON o.id = n.organization_id
 WHERE n.valid_from <= TRUNC(SYSDATE)
   AND (n.valid_to IS NULL OR n.valid_to >= TRUNC(SYSDATE))
 ORDER BY o.code, n.station_code, n.rule_type, n.rule_key;

PROMPT === Пересекающиеся нормативы для одного правила ===
SELECT a.id AS first_id, b.id AS second_id, a.organization_id,
       a.station_code, a.rule_type, a.rule_key,
       a.valid_from AS first_from, a.valid_to AS first_to,
       b.valid_from AS second_from, b.valid_to AS second_to
  FROM xx_rjd_claim_norms a
  JOIN xx_rjd_claim_norms b
    ON b.id > a.id
   AND b.organization_id = a.organization_id
   AND NVL(b.station_code, '#') = NVL(a.station_code, '#')
   AND b.rule_type = a.rule_type
   AND b.rule_key = a.rule_key
   AND b.valid_from <= NVL(a.valid_to, DATE '2999-12-31')
   AND a.valid_from <= NVL(b.valid_to, DATE '2999-12-31')
 ORDER BY a.organization_id, a.rule_type, a.rule_key, a.id;

PROMPT === Активный контроль простоев ===
SELECT id_control, car_number, start_date, end_date,
       idle_reasons_name, is_excluded, note, created_name
  FROM xx_disl_idle_control_v
 WHERE end_date IS NULL OR end_date >= TRUNC(SYSDATE)
 ORDER BY start_date DESC, id_control DESC;

PROMPT === Повторяющиеся активные правила одного вагона ===
SELECT car_number, COUNT(*) AS active_rules,
       MIN(start_date) AS first_start_date,
       MAX(start_date) AS last_start_date
  FROM xx_disl_idle_control_v
 WHERE end_date IS NULL OR end_date >= TRUNC(SYSDATE)
 GROUP BY car_number
HAVING COUNT(*) > 1
 ORDER BY active_rules DESC, car_number;

PROMPT === Правила с некорректным периодом ===
SELECT id_control, car_number, start_date, end_date, idle_reasons_name
  FROM xx_disl_idle_control_v
 WHERE end_date IS NOT NULL AND end_date < start_date
 ORDER BY id_control;
