-- Пользовательские рассылки и история запусков. Только чтение.
SET PAGESIZE 300
SET LINESIZE 280

PROMPT === Все рассылки ===
SELECT m.id, m.name, m.is_active, m.schedule_type, m.send_time,
       m.week_days, m.month_day, m.last_run_at, m.last_run_status,
       u.username, o.code AS organization_code
  FROM xx_rjd_user_mailings m
  JOIN xx_rjd_users u ON u.id = m.user_id
  JOIN xx_rjd_organizations o ON o.id = m.organization_id
 ORDER BY m.is_active DESC, m.name;

PROMPT === Активные рассылки с некорректным расписанием ===
SELECT id, name, schedule_type, send_time, week_days, month_day
  FROM xx_rjd_user_mailings
 WHERE is_active = 1
   AND (NOT REGEXP_LIKE(send_time, '^([01][0-9]|2[0-3]):[0-5][0-9]$')
     OR schedule_type = 'weekly' AND TRIM(week_days) IS NULL
     OR schedule_type = 'monthly' AND month_day IS NULL)
 ORDER BY id;

PROMPT === Рассылки без отчётов ===
SELECT m.id, m.name, u.username
  FROM xx_rjd_user_mailings m
  JOIN xx_rjd_users u ON u.id = m.user_id
 WHERE NOT EXISTS (
       SELECT 1 FROM xx_rjd_user_mailing_reports r WHERE r.mailing_id = m.id
   )
 ORDER BY m.id;

PROMPT === Отчёты в составе рассылок ===
SELECT m.id AS mailing_id, m.name, r.position, r.report_code,
       r.layout_mode, LENGTH(r.filters_json) AS filters_length
  FROM xx_rjd_user_mailings m
  JOIN xx_rjd_user_mailing_reports r ON r.mailing_id = m.id
 ORDER BY m.id, r.position, r.id;

PROMPT === Запуски, зависшие в статусе RUNNING более часа ===
SELECT r.id, r.mailing_id, m.name, r.started_at, r.run_mode, r.message
  FROM xx_rjd_user_mailing_runs r
  JOIN xx_rjd_user_mailings m ON m.id = r.mailing_id
 WHERE r.status = 'running'
   AND r.started_at < SYSDATE - 1 / 24
 ORDER BY r.started_at;

PROMPT === Ошибки рассылок за семь дней ===
SELECT r.id, r.mailing_id, m.name, r.started_at, r.finished_at,
       r.run_mode, r.message
  FROM xx_rjd_user_mailing_runs r
  JOIN xx_rjd_user_mailings m ON m.id = r.mailing_id
 WHERE r.status = 'error'
   AND r.started_at >= SYSDATE - 7
 ORDER BY r.started_at DESC;
