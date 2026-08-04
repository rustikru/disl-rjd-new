-- Запуск рассылок через заданное количество часов в выбранные дни недели.
-- Oracle 11g.

ALTER TABLE xx_rjd_report_mailings
    ADD interval_hours NUMBER(2);

ALTER TABLE xx_rjd_report_mailings
    ADD CONSTRAINT ck_xx_rjd_mailings_hours
        CHECK (interval_hours IS NULL OR interval_hours BETWEEN 1 AND 24);

ALTER TABLE xx_rjd_report_mailings
    DROP CONSTRAINT ck_xx_rjd_mailings_schedule;

ALTER TABLE xx_rjd_report_mailings
    ADD CONSTRAINT ck_xx_rjd_mailings_schedule
        CHECK (schedule_type IN ('MANUAL', 'DAILY', 'WEEKLY', 'MONTHLY', 'HOURLY'));

COMMIT;
