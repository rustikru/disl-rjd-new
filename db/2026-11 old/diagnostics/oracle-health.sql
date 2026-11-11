-- Общая проверка состояния объектов проекта. Только чтение.
SET PAGESIZE 200
SET LINESIZE 240
SET LONG 20000

PROMPT === Невалидные объекты XX_RJD и XX_DISL ===
SELECT object_type, object_name, status, last_ddl_time
  FROM user_objects
 WHERE status <> 'VALID'
   AND (object_name LIKE 'XX_RJD%' OR object_name LIKE 'XX_DISL%')
 ORDER BY object_type, object_name;

PROMPT === Ошибки компиляции ===
SELECT name, type, line, position, text
  FROM user_errors
 WHERE name LIKE 'XX_RJD%'
    OR name LIKE 'XX_DISL%'
 ORDER BY name, type, sequence;

PROMPT === Основные объекты приложения ===
SELECT expected.object_type,
       expected.object_name,
       NVL(actual.status, 'ОТСУТСТВУЕТ') AS status,
       actual.last_ddl_time
  FROM (
        SELECT 'PACKAGE' object_type, 'XX_RJD_DISLOCATION_NEW_PKG' object_name FROM dual
        UNION ALL SELECT 'PACKAGE BODY', 'XX_RJD_DISLOCATION_NEW_PKG' FROM dual
        UNION ALL SELECT 'TABLE', 'XX_RJD_KPI_TBL' FROM dual
        UNION ALL SELECT 'TABLE', 'XX_DISLOCATION_RJD' FROM dual
        UNION ALL SELECT 'VIEW', 'XX_RJD_KPI_TBL_V' FROM dual
       ) expected
  LEFT JOIN user_objects actual
    ON actual.object_type = expected.object_type
   AND actual.object_name = expected.object_name
 ORDER BY expected.object_type, expected.object_name;

PROMPT === Объекты, изменённые за последние семь дней ===
SELECT object_type, object_name, status, last_ddl_time
  FROM user_objects
 WHERE last_ddl_time >= SYSDATE - 7
 ORDER BY last_ddl_time DESC, object_type, object_name;
