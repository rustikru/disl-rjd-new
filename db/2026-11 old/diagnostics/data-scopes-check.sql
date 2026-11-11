-- Области данных, правила и назначения. Только чтение.
SET PAGESIZE 300
SET LINESIZE 280

PROMPT === Области данных и количество правил/пользователей ===
SELECT s.id, s.name, s.is_active, s.start_date, s.end_date,
       (SELECT COUNT(*) FROM xx_rjd_data_scope_rules r WHERE r.scope_id = s.id) AS rule_count,
       (SELECT COUNT(*) FROM xx_rjd_user_data_scopes u WHERE u.scope_id = s.id) AS user_count
  FROM xx_rjd_data_scopes s
 ORDER BY s.is_active DESC, s.name;

PROMPT === Все правила областей ===
SELECT s.name AS scope_name, s.is_active, r.id AS rule_id,
       o.code AS organization_code, r.effect,
       r.cargo_code, r.station_code, r.car_number
  FROM xx_rjd_data_scope_rules r
  JOIN xx_rjd_data_scopes s ON s.id = r.scope_id
  JOIN xx_rjd_organizations o ON o.id = r.organization_id
 ORDER BY s.name, r.effect, r.id;

PROMPT === Активные области без правил ===
SELECT s.id, s.name
  FROM xx_rjd_data_scopes s
 WHERE s.is_active = 1
   AND NOT EXISTS (SELECT 1 FROM xx_rjd_data_scope_rules r WHERE r.scope_id = s.id)
 ORDER BY s.name;

PROMPT === Истёкшие или ещё не начавшиеся активные области ===
SELECT id, name, start_date, end_date,
       CASE WHEN start_date > TRUNC(SYSDATE) THEN 'Ещё не началась'
            WHEN end_date < TRUNC(SYSDATE) THEN 'Истекла' END AS state
  FROM xx_rjd_data_scopes
 WHERE is_active = 1
   AND (start_date > TRUNC(SYSDATE) OR end_date < TRUNC(SYSDATE))
 ORDER BY name;

PROMPT === Пользователи и назначенные области ===
SELECT u.id AS user_id, u.username, u.is_active,
       s.id AS scope_id, s.name AS scope_name, s.is_active AS scope_active
  FROM xx_rjd_user_data_scopes us
  JOIN xx_rjd_users u ON u.id = us.user_id
  JOIN xx_rjd_data_scopes s ON s.id = us.scope_id
 ORDER BY u.username, s.name;

PROMPT === Назначения отключённых областей активным пользователям ===
SELECT u.id AS user_id, u.username, s.id AS scope_id, s.name
  FROM xx_rjd_user_data_scopes us
  JOIN xx_rjd_users u ON u.id = us.user_id
  JOIN xx_rjd_data_scopes s ON s.id = us.scope_id
 WHERE u.is_active = 1 AND s.is_active = 0
 ORDER BY u.username, s.name;
