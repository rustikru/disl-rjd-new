-- Активные сессии и аудит. Только чтение.
SET PAGESIZE 300
SET LINESIZE 260

PROMPT === Активные сессии за последние восемь часов ===
SELECT session_key, user_id, username, display_name, ip,
       DATE '1970-01-01' + started / 86400 AS started_at,
       DATE '1970-01-01' + seen / 86400 AS seen_at,
       revoked
  FROM xx_rjd_sessions
 WHERE revoked = 0
   AND seen > (SYSDATE - DATE '1970-01-01' - 8 / 24) * 86400
 ORDER BY seen DESC;

PROMPT === Количество незавершённых сессий по пользователям ===
SELECT user_id, username, COUNT(*) AS session_count,
       MAX(DATE '1970-01-01' + seen / 86400) AS last_seen_at
  FROM xx_rjd_sessions
 WHERE revoked = 0
 GROUP BY user_id, username
HAVING COUNT(*) > 1
 ORDER BY session_count DESC, username;

PROMPT === Последние 200 действий аудита ===
SELECT *
  FROM (
        SELECT id, created_at, username, action_code, object_type,
               object_id, description, ip_address
          FROM xx_rjd_audit_log
         ORDER BY created_at DESC, id DESC
       )
 WHERE ROWNUM <= 200;

PROMPT === Ошибочные или чувствительные действия за семь дней ===
SELECT created_at, username, action_code, object_type, object_id, description
  FROM xx_rjd_audit_log
 WHERE created_at >= SYSDATE - 7
   AND (UPPER(action_code) LIKE '%DELETE%'
     OR UPPER(action_code) LIKE '%DISABLE%'
     OR UPPER(action_code) LIKE '%REVOKE%'
     OR UPPER(action_code) LIKE '%ERROR%')
 ORDER BY created_at DESC;
