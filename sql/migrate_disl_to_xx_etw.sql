-- ================================================================
-- Миграция схемы: disl -> xx_etw (через CTAS)
-- Выполнить:
--   docker cp migrate_disl_to_xx_etw.sql disl_oracle:/tmp/
--   docker exec -it disl_oracle sqlplus 'system/Oracle123!@FREEPDB1' '@/tmp/migrate_disl_to_xx_etw.sql'
--
-- PL/SQL-блоки (BEGIN...END) завершаются "/", обычные DDL только ";"
-- ================================================================

-- ── 1. Создаём пользователя (если уже есть — пропускаем ошибку) ──
DECLARE
  v_exists NUMBER;
BEGIN
  SELECT COUNT(*) INTO v_exists FROM dba_users WHERE username = 'XX_ETW';
  IF v_exists = 0 THEN
    EXECUTE IMMEDIATE 'CREATE USER xx_etw IDENTIFIED BY xx_etw123';
    EXECUTE IMMEDIATE 'GRANT CONNECT, RESOURCE, UNLIMITED TABLESPACE TO xx_etw';
  END IF;
END;
/

-- ── 2. Удаляем таблицы xx_etw если были созданы ранее ────────────
BEGIN EXECUTE IMMEDIATE 'DROP TABLE xx_etw.xx_dislocation_rjd'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE xx_etw.wagon_approach';      EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE xx_etw.wagon_extended';      EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE xx_etw.wagon_dislocation';   EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE xx_etw.users';               EXCEPTION WHEN OTHERS THEN NULL; END;
/

-- ── 3. Копируем таблицы с данными через CTAS ─────────────────────
CREATE TABLE xx_etw.users             AS SELECT * FROM disl.users;
CREATE TABLE xx_etw.wagon_dislocation AS SELECT * FROM disl.wagon_dislocation;
CREATE TABLE xx_etw.wagon_extended    AS SELECT * FROM disl.wagon_extended;
CREATE TABLE xx_etw.wagon_approach    AS SELECT * FROM disl.wagon_approach;
CREATE TABLE xx_etw.xx_dislocation_rjd AS SELECT * FROM disl.xx_dislocation_rjd;

-- ── 4. Воссоздаём индексы ─────────────────────────────────────────
CREATE INDEX xx_etw.idx_disloc_date          ON xx_etw.wagon_dislocation(report_date);
CREATE INDEX xx_etw.idx_disloc_section       ON xx_etw.wagon_dislocation(report_date, section_id);
CREATE INDEX xx_etw.idx_xx_dislocn_report_dt ON xx_etw.xx_dislocation_rjd(report_dt);
CREATE INDEX xx_etw.idx_xx_dislocn_wagon_no  ON xx_etw.xx_dislocation_rjd(wagon_no);

-- ── 5. Проверка ───────────────────────────────────────────────────
SELECT 'users' AS tbl,
       (SELECT COUNT(*) FROM disl.users) AS old_cnt,
       (SELECT COUNT(*) FROM xx_etw.users) AS new_cnt FROM dual
UNION ALL
SELECT 'wagon_dislocation',
       (SELECT COUNT(*) FROM disl.wagon_dislocation),
       (SELECT COUNT(*) FROM xx_etw.wagon_dislocation) FROM dual
UNION ALL
SELECT 'wagon_extended',
       (SELECT COUNT(*) FROM disl.wagon_extended),
       (SELECT COUNT(*) FROM xx_etw.wagon_extended) FROM dual
UNION ALL
SELECT 'wagon_approach',
       (SELECT COUNT(*) FROM disl.wagon_approach),
       (SELECT COUNT(*) FROM xx_etw.wagon_approach) FROM dual
UNION ALL
SELECT 'xx_dislocation_rjd',
       (SELECT COUNT(*) FROM disl.xx_dislocation_rjd),
       (SELECT COUNT(*) FROM xx_etw.xx_dislocation_rjd) FROM dual;

-- ── 6. Удалить старую схему (ТОЛЬКО после проверки old_cnt = new_cnt!) ──
-- DROP USER disl CASCADE;
