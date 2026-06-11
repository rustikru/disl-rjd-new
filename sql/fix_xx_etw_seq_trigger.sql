-- ================================================================
-- Исправление ORA-01400: создаём последовательность и триггер
-- для xx_etw.xx_dislocation_rjd (CTAS не копирует их автоматически)
--
-- Запустить как пользователь xx_etw (или SYSTEM с правом на xx_etw):
--   docker cp fix_xx_etw_seq_trigger.sql disl_oracle:/tmp/
--   docker exec -it disl_oracle sqlplus 'xx_etw/xx_etw123@FREEPDB1' '@/tmp/fix_xx_etw_seq_trigger.sql'
-- ================================================================

-- Определяем начальное значение из текущего MAX(id) таблицы
-- (если таблица пустая — начнём с 1)
DECLARE
  v_max NUMBER := 1;
BEGIN
  SELECT NVL(MAX(id), 0) + 1 INTO v_max FROM xx_dislocation_rjd;
  BEGIN
    EXECUTE IMMEDIATE 'DROP SEQUENCE xx_dislocation_rjd_seq';
  EXCEPTION WHEN OTHERS THEN NULL;
  END;
  EXECUTE IMMEDIATE 'CREATE SEQUENCE xx_dislocation_rjd_seq START WITH ' || v_max || ' INCREMENT BY 1 NOCACHE NOCYCLE';
END;
/

CREATE OR REPLACE TRIGGER xx_dislocation_rjd_bi
BEFORE INSERT ON xx_dislocation_rjd
FOR EACH ROW
BEGIN
  IF :NEW.id IS NULL THEN
    :NEW.id := xx_dislocation_rjd_seq.NEXTVAL;
  END IF;
END;
/

-- Проверка
SELECT sequence_name, last_number FROM user_sequences
WHERE sequence_name = 'XX_DISLOCATION_RJD_SEQ';
