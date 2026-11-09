-- Права ролей на страницы и глобальная видимость. Только чтение.
SET PAGESIZE 300
SET LINESIZE 240

PROMPT === Страницы каждой роли ===
SELECT r.id AS role_id, r.code AS role_code, r.name AS role_name,
       rp.page AS page_code
  FROM xx_rjd_roles r
  LEFT JOIN xx_rjd_role_pages rp ON rp.role_id = r.id
 ORDER BY r.code, rp.page;

PROMPT === Роли без назначенных страниц ===
SELECT r.id, r.code, r.name, r.is_system
  FROM xx_rjd_roles r
 WHERE NOT EXISTS (SELECT 1 FROM xx_rjd_role_pages rp WHERE rp.role_id = r.id)
 ORDER BY r.code;

PROMPT === Дубли назначений страниц ===
SELECT role_id, page, COUNT(*) AS row_count
  FROM xx_rjd_role_pages
 GROUP BY role_id, page
HAVING COUNT(*) > 1
 ORDER BY role_id, page;

PROMPT === Назначения несуществующим ролям ===
SELECT rp.role_id, rp.page
  FROM xx_rjd_role_pages rp
  LEFT JOIN xx_rjd_roles r ON r.id = rp.role_id
 WHERE r.id IS NULL
 ORDER BY rp.role_id, rp.page;

PROMPT === Глобально отключённые страницы ===
SELECT p.page_code, p.disabled_at, p.disabled_by,
       NVL(u.display_name, u.username) AS disabled_by_name
  FROM xx_rjd_disabled_pages p
  LEFT JOIN xx_rjd_users u ON u.id = p.disabled_by
 ORDER BY p.disabled_at DESC;

PROMPT === Какие роли содержат отключённые страницы ===
SELECT p.page_code, r.code AS role_code, r.name AS role_name
  FROM xx_rjd_disabled_pages p
  JOIN xx_rjd_role_pages rp ON rp.page = p.page_code
  JOIN xx_rjd_roles r ON r.id = rp.role_id
 ORDER BY p.page_code, r.code;
