-- =====================================================================
--  Нормализация координат станций.
--  Координаты храним строкой, десятичный разделитель приводим к точке.
--  Основные поля: latitude, longitude.
--
--  Если на проде есть старое поле coordinates со значением вида
--  58.0105, 56.2502, скрипт разложит его на latitude/longitude.
-- =====================================================================

ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

DECLARE
  l_count NUMBER;
  l_type  VARCHAR2(30);
BEGIN
  SELECT COUNT(*)
    INTO l_count
    FROM user_tab_columns
   WHERE table_name = 'XX_RJD_STATIONS'
     AND column_name = 'LATITUDE';

  IF l_count = 0 THEN
    EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations ADD latitude VARCHAR2(50)';
  ELSE
    SELECT data_type
      INTO l_type
      FROM user_tab_columns
     WHERE table_name = 'XX_RJD_STATIONS'
       AND column_name = 'LATITUDE';

    IF l_type <> 'VARCHAR2' THEN
      EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations ADD latitude_text VARCHAR2(50)';
      EXECUTE IMMEDIATE q'[
        UPDATE xx_rjd_stations
           SET latitude_text = to_char(latitude)
         WHERE latitude IS NOT NULL
      ]';
      EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations RENAME COLUMN latitude TO latitude_old';
      EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations RENAME COLUMN latitude_text TO latitude';
    END IF;
  END IF;

  SELECT COUNT(*)
    INTO l_count
    FROM user_tab_columns
   WHERE table_name = 'XX_RJD_STATIONS'
     AND column_name = 'LONGITUDE';

  IF l_count = 0 THEN
    EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations ADD longitude VARCHAR2(50)';
  ELSE
    SELECT data_type
      INTO l_type
      FROM user_tab_columns
     WHERE table_name = 'XX_RJD_STATIONS'
       AND column_name = 'LONGITUDE';

    IF l_type <> 'VARCHAR2' THEN
      EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations ADD longitude_text VARCHAR2(50)';
      EXECUTE IMMEDIATE q'[
        UPDATE xx_rjd_stations
           SET longitude_text = to_char(longitude)
         WHERE longitude IS NOT NULL
      ]';
      EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations RENAME COLUMN longitude TO longitude_old';
      EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations RENAME COLUMN longitude_text TO longitude';
    END IF;
  END IF;
END;
/

DECLARE
  l_count NUMBER;
BEGIN
  SELECT COUNT(*)
    INTO l_count
    FROM user_tab_columns
   WHERE table_name = 'XX_RJD_STATIONS'
     AND column_name = 'COORDINATES';

  IF l_count > 0 THEN
    EXECUTE IMMEDIATE q'[
      UPDATE xx_rjd_stations
         SET latitude = regexp_substr(trim(coordinates), '^-?[0-9]+([.][0-9]+)?'),
             longitude = regexp_substr(trim(coordinates), '-?[0-9]+([.][0-9]+)?$', 1, 1)
       WHERE coordinates IS NOT NULL
         AND (latitude IS NULL OR longitude IS NULL)
    ]';
  END IF;
END;
/

UPDATE xx_rjd_stations
   SET latitude = replace(trim(latitude), ',', '.')
 WHERE latitude IS NOT NULL;

UPDATE xx_rjd_stations
   SET longitude = replace(trim(longitude), ',', '.')
 WHERE longitude IS NOT NULL;

UPDATE xx_rjd_stations
   SET latitude = NULL
 WHERE trim(latitude) IS NULL;

UPDATE xx_rjd_stations
   SET longitude = NULL
 WHERE trim(longitude) IS NULL;

COMMIT;

-- Проверка строк, которые нужно поправить руками.
-- Запрос должен вернуть 0 строк.
SELECT esr_code,
       station_name,
       latitude,
       longitude
  FROM xx_rjd_stations
 WHERE (latitude IS NOT NULL AND NOT regexp_like(latitude, '^-?[0-9]+([.][0-9]+)?$'))
    OR (longitude IS NOT NULL AND NOT regexp_like(longitude, '^-?[0-9]+([.][0-9]+)?$'));

-- Проверка диапазонов.
-- Запрос должен вернуть 0 строк.
SELECT esr_code,
       station_name,
       latitude,
       longitude
  FROM xx_rjd_stations
 WHERE (latitude IS NOT NULL AND (
          CASE
            WHEN regexp_like(latitude, '^-?[0-9]+([.][0-9]+)?$') THEN to_number(latitude)
            ELSE NULL
          END < -90
       OR CASE
            WHEN regexp_like(latitude, '^-?[0-9]+([.][0-9]+)?$') THEN to_number(latitude)
            ELSE NULL
          END > 90
       ))
    OR (longitude IS NOT NULL AND (
          CASE
            WHEN regexp_like(longitude, '^-?[0-9]+([.][0-9]+)?$') THEN to_number(longitude)
            ELSE NULL
          END < -180
       OR CASE
            WHEN regexp_like(longitude, '^-?[0-9]+([.][0-9]+)?$') THEN to_number(longitude)
            ELSE NULL
          END > 180
       ));
