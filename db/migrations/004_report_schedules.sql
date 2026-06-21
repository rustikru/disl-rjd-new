-- =====================================================================
--  Расписание автоматической выгрузки отчётов
--  Oracle 23c
-- =====================================================================

CREATE TABLE xx_rjd_report_schedules (
  id           NUMBER            NOT NULL,
  name         VARCHAR2(200 CHAR) NOT NULL,          -- отображаемое название
  report_type  VARCHAR2(100)     DEFAULT 'dislocation_summary' NOT NULL,
  frequency    VARCHAR2(20)      NOT NULL,            -- 'daily' | 'hourly' | 'weekly'
  run_hour     NUMBER(2,0)       DEFAULT 8,           -- час запуска (0-23), для daily/weekly
  run_minute   NUMBER(2,0)       DEFAULT 0,           -- минута запуска (0-59)
  day_of_week  NUMBER(1,0),                          -- 1=Пн..7=Вс (NULL для daily/hourly)
  output_dir   VARCHAR2(500)     NOT NULL,            -- путь к папке сохранения
  is_active    NUMBER(1,0)       DEFAULT 1 NOT NULL,
  last_run_at  DATE,                                  -- когда последний раз выполнялось
  created_by   NUMBER,                               -- id пользователя-создателя
  created_at   DATE DEFAULT SYSDATE NOT NULL,
  CONSTRAINT pk_xx_rjd_report_schedules PRIMARY KEY (id),
  CONSTRAINT ck_schedule_freq CHECK (frequency IN ('daily','hourly','weekly')),
  CONSTRAINT ck_schedule_hour CHECK (run_hour BETWEEN 0 AND 23),
  CONSTRAINT ck_schedule_min  CHECK (run_minute BETWEEN 0 AND 59),
  CONSTRAINT ck_schedule_dow  CHECK (day_of_week IS NULL OR day_of_week BETWEEN 1 AND 7)
);

COMMENT ON TABLE  xx_rjd_report_schedules              IS 'Расписание автоматической выгрузки отчётов';
COMMENT ON COLUMN xx_rjd_report_schedules.frequency    IS 'Частота: daily | hourly | weekly';
COMMENT ON COLUMN xx_rjd_report_schedules.run_hour     IS 'Час запуска (0-23) для daily/weekly';
COMMENT ON COLUMN xx_rjd_report_schedules.day_of_week  IS '1=Пн .. 7=Вс для weekly, NULL для остальных';
COMMENT ON COLUMN xx_rjd_report_schedules.output_dir   IS 'Полный путь к папке сохранения Excel-файлов';

CREATE SEQUENCE xx_rjd_schedules_seq START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_schedules_bi
BEFORE INSERT ON xx_rjd_report_schedules
FOR EACH ROW
WHEN (NEW.id IS NULL)
BEGIN
  :NEW.id := xx_rjd_schedules_seq.NEXTVAL;
END;
/
