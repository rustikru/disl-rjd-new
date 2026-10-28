-- Однократное исправление записей, которые были сохранены в UTC до добавления app_timezone.
-- Локальная зона приложения: Asia/Yekaterinburg (UTC+5).

UPDATE XX_ETW.XX_RJD_USER_MAILINGS
   SET LAST_RUN_AT = LAST_RUN_AT + INTERVAL '5' HOUR
 WHERE LAST_RUN_AT IS NOT NULL;

UPDATE XX_ETW.XX_RJD_USER_MAILING_RUNS
   SET STARTED_AT = STARTED_AT + INTERVAL '5' HOUR,
       FINISHED_AT = CASE
           WHEN FINISHED_AT IS NULL THEN NULL
           ELSE FINISHED_AT + INTERVAL '5' HOUR
       END;

COMMIT;
