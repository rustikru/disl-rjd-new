-- Восстановление: если миграция 002 уже была запущена без переноса данных,
-- этот скрипт вручную назначает роль ADMIN нужному пользователю.
--
-- Шаг 1: Найти id пользователя и роли ADMIN
SELECT u.id AS user_id, u.username, r.id AS role_id, r.code
  FROM xx_rjd_users u
 CROSS JOIN xx_rjd_roles r
 WHERE r.code = 'ADMIN';

-- Шаг 2: Вставить нужную пару (подставить user_id из результата выше)
-- INSERT INTO xx_rjd_user_roles (user_id, role_id)
-- SELECT u.id, r.id
--   FROM xx_rjd_users u
--  CROSS JOIN xx_rjd_roles r
--  WHERE u.username = 'YOUR_USERNAME'   -- <-- заменить на свой логин
--    AND r.code = 'ADMIN';
