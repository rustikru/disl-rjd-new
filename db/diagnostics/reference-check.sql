-- Проверка загруженных справок РЖД и исходных данных. Только чтение.
SET PAGESIZE 300
SET LINESIZE 260

PROMPT === Организации: выберите нужный ID ===
SELECT id, code, name, short_name, is_active
  FROM xx_rjd_organizations
 ORDER BY id;

DEFINE organization_id = 1

PROMPT === Загруженные справки организации ===
SELECT type_reference,
       TO_CHAR(report_dt, 'DD.MM.YYYY HH24:MI:SS') AS report_dt,
       COUNT(*) AS row_count,
       COUNT(DISTINCT wagon_no) AS wagon_count
  FROM xx_dislocation_rjd
 WHERE organization_id = &organization_id
 GROUP BY type_reference, report_dt
 ORDER BY report_dt DESC, type_reference;

PROMPT === Последняя справка каждого типа ===
SELECT d.type_reference,
       TO_CHAR(d.report_dt, 'DD.MM.YYYY HH24:MI:SS') AS report_dt,
       COUNT(*) AS row_count,
       COUNT(DISTINCT d.wagon_no) AS wagon_count
  FROM xx_dislocation_rjd d
 WHERE d.organization_id = &organization_id
   AND d.report_dt = (
       SELECT MAX(latest.report_dt)
         FROM xx_dislocation_rjd latest
        WHERE latest.organization_id = d.organization_id
          AND latest.type_reference = d.type_reference
   )
 GROUP BY d.type_reference, d.report_dt
 ORDER BY d.type_reference;

PROMPT === Дубли вагонов в одной справке ===
SELECT type_reference, report_dt, wagon_no, COUNT(*) AS row_count
  FROM xx_dislocation_rjd
 WHERE organization_id = &organization_id
 GROUP BY type_reference, report_dt, wagon_no
HAVING COUNT(*) > 1
 ORDER BY report_dt DESC, type_reference, wagon_no;

PROMPT === Строки без основных идентификаторов ===
SELECT type_reference, report_dt,
       SUM(CASE WHEN wagon_no IS NULL THEN 1 ELSE 0 END) AS no_wagon_no,
       SUM(CASE WHEN type_reference IS NULL THEN 1 ELSE 0 END) AS no_type,
       SUM(CASE WHEN report_dt IS NULL THEN 1 ELSE 0 END) AS no_report_dt
  FROM xx_dislocation_rjd
 WHERE organization_id = &organization_id
 GROUP BY type_reference, report_dt
HAVING SUM(CASE WHEN wagon_no IS NULL THEN 1 ELSE 0 END) > 0
    OR SUM(CASE WHEN type_reference IS NULL THEN 1 ELSE 0 END) > 0
    OR SUM(CASE WHEN report_dt IS NULL THEN 1 ELSE 0 END) > 0
 ORDER BY report_dt DESC;

PROMPT === Проверка гружёных вагонов последней справки «Отправка» ===
SELECT COUNT(*) AS loaded_wagons
  FROM xx_dislocation_rjd d
 WHERE d.organization_id = &organization_id
   AND d.type_reference = 'Отправка'
   AND d.report_dt = (
       SELECT MAX(latest.report_dt)
         FROM xx_dislocation_rjd latest
        WHERE latest.organization_id = d.organization_id
          AND latest.type_reference = 'Отправка'
   )
   AND UPPER(TRIM(d.depart_station)) LIKE '%УГЛ%768207%'
   AND d.dist_remain_km > 0
   AND NVL(d.cargo_weight_kg, 0) >
       CASE WHEN UPPER(NVL(d.wagon_type_code, ' ')) LIKE '%ПЛАТФОРМ%'
            THEN 12000 ELSE 0 END;
