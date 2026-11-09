-- Пользователи, роли, группы доступа и организации. Только чтение.
SET PAGESIZE 300
SET LINESIZE 260

PROMPT === Пользователи и основная организация ===
SELECT u.id, u.username, u.display_name, u.email, u.is_active,
       o.code AS organization_code, o.name AS organization_name,
       u.created_at, u.updated_at, u.last_login_at
  FROM xx_rjd_users u
  LEFT JOIN xx_rjd_organizations o ON o.id = u.organization_id
 ORDER BY u.is_active DESC, u.username;

PROMPT === Роли каждого пользователя ===
SELECT u.id AS user_id, u.username, u.is_active,
       LISTAGG(r.code, ', ') WITHIN GROUP (ORDER BY r.code) AS roles
  FROM xx_rjd_users u
  LEFT JOIN xx_rjd_user_roles ur ON ur.user_id = u.id
  LEFT JOIN xx_rjd_roles r ON r.id = ur.role_id
 GROUP BY u.id, u.username, u.is_active
 ORDER BY u.username;

PROMPT === Активные пользователи без ролей ===
SELECT u.id, u.username, u.display_name
  FROM xx_rjd_users u
 WHERE u.is_active = 1
   AND NOT EXISTS (SELECT 1 FROM xx_rjd_user_roles ur WHERE ur.user_id = u.id)
 ORDER BY u.username;

PROMPT === Основная и дополнительные организации пользователей ===
SELECT u.id AS user_id, u.username, 'PRIMARY' AS organization_type,
       o.id AS organization_id, o.code, o.name, o.is_active
  FROM xx_rjd_users u
  LEFT JOIN xx_rjd_organizations o ON o.id = u.organization_id
UNION ALL
SELECT u.id, u.username, 'EXTRA', o.id, o.code, o.name, o.is_active
  FROM xx_rjd_user_organizations uo
  JOIN xx_rjd_users u ON u.id = uo.user_id
  JOIN xx_rjd_organizations o ON o.id = uo.organization_id
 ORDER BY user_id, organization_type DESC, organization_id;

PROMPT === Лишнее назначение основной организации как дополнительной ===
SELECT u.id AS user_id, u.username, u.organization_id
  FROM xx_rjd_users u
  JOIN xx_rjd_user_organizations uo
    ON uo.user_id = u.id AND uo.organization_id = u.organization_id
 ORDER BY u.username;

PROMPT === Повреждённые назначения ролей ===
SELECT ur.user_id, ur.role_id,
       CASE WHEN u.id IS NULL THEN 'Нет пользователя' ELSE 'Нет роли' END AS problem
  FROM xx_rjd_user_roles ur
  LEFT JOIN xx_rjd_users u ON u.id = ur.user_id
  LEFT JOIN xx_rjd_roles r ON r.id = ur.role_id
 WHERE u.id IS NULL OR r.id IS NULL
 ORDER BY ur.user_id, ur.role_id;

PROMPT === Группы доступа и входящие роли ===
SELECT g.id, g.name, g.description,
       LISTAGG(r.code, ', ') WITHIN GROUP (ORDER BY r.code) AS roles
  FROM xx_rjd_access_groups g
  LEFT JOIN xx_rjd_access_group_roles gr ON gr.access_group_id = g.id
  LEFT JOIN xx_rjd_roles r ON r.id = gr.role_id
 GROUP BY g.id, g.name, g.description
 ORDER BY g.name;
