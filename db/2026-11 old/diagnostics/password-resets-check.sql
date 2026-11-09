-- Запросы восстановления пароля. Токены не выводятся. Только чтение.
SET PAGESIZE 300
SET LINESIZE 240

PROMPT === Состояние запросов восстановления ===
SELECT p.id, p.user_id, u.username, p.created_at, p.expires_at, p.used_at,
       p.request_ip,
       CASE WHEN p.used_at IS NOT NULL THEN 'USED'
            WHEN p.expires_at < SYSDATE THEN 'EXPIRED'
            ELSE 'ACTIVE' END AS state
  FROM xx_rjd_password_resets p
  JOIN xx_rjd_users u ON u.id = p.user_id
 ORDER BY p.created_at DESC;

PROMPT === Несколько активных запросов одного пользователя ===
SELECT p.user_id, u.username, COUNT(*) AS active_requests,
       MIN(p.created_at) AS first_created_at,
       MAX(p.created_at) AS last_created_at
  FROM xx_rjd_password_resets p
  JOIN xx_rjd_users u ON u.id = p.user_id
 WHERE p.used_at IS NULL AND p.expires_at >= SYSDATE
 GROUP BY p.user_id, u.username
HAVING COUNT(*) > 1
 ORDER BY active_requests DESC, u.username;

PROMPT === Запросы для отключённых пользователей ===
SELECT p.id, u.id AS user_id, u.username, p.created_at, p.expires_at
  FROM xx_rjd_password_resets p
  JOIN xx_rjd_users u ON u.id = p.user_id
 WHERE u.is_active = 0
   AND p.used_at IS NULL
   AND p.expires_at >= SYSDATE
 ORDER BY p.created_at DESC;
