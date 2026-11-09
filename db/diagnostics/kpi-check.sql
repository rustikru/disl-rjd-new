-- Диагностика KPI. Только чтение, кроме CLIENT_IDENTIFIER текущей сессии.
SET PAGESIZE 300
SET LINESIZE 260
SET LONG 20000

PROMPT === Организации: выберите нужный ID ===
SELECT id, code, name, short_name, is_active
  FROM xx_rjd_organizations
 ORDER BY id;

DEFINE organization_id = 1

BEGIN
   DBMS_SESSION.SET_IDENTIFIER('&organization_id');
END;
/

PROMPT === Текущий CLIENT_IDENTIFIER ===
SELECT SYS_CONTEXT('USERENV', 'CLIENT_IDENTIFIER') AS organization_id FROM dual;

PROMPT === Все карточки KPI ===
SELECT id, type, code, label, page_code, is_system, visibility, is_active,
       owner_id, order_by, trend_period, trend_unit
  FROM xx_rjd_kpi_tbl
 ORDER BY type, order_by, id;

PROMPT === Дубли TYPE + CODE ===
SELECT type, code, COUNT(*) AS row_count, MIN(id) AS first_id, MAX(id) AS last_id
  FROM xx_rjd_kpi_tbl
 GROUP BY type, code
HAVING COUNT(*) > 1
 ORDER BY type, code;

PROMPT === Некорректные системные и пользовательские карточки ===
SELECT id, type, code, is_system, owner_id, visibility,
       CASE
         WHEN is_system = 'Y' AND owner_id IS NOT NULL THEN 'У системного KPI задан владелец'
         WHEN is_system = 'N' AND owner_id IS NULL THEN 'У пользовательского KPI нет владельца'
         WHEN is_system = 'N' AND where_config IS NULL THEN 'Нет JSON-условий'
         WHEN is_system = 'Y' AND where_config IS NOT NULL THEN 'У системного KPI заполнен WHERE_CONFIG'
         ELSE 'Проверить вручную'
       END AS problem
  FROM xx_rjd_kpi_tbl
 WHERE (is_system = 'Y' AND (owner_id IS NOT NULL OR where_config IS NOT NULL))
    OR (is_system = 'N' AND (owner_id IS NULL OR where_config IS NULL))
 ORDER BY id;

PROMPT === Сколько пользователей скрыли каждую карточку ===
SELECT k.id, k.type, k.code, k.label, COUNT(h.user_id) AS hidden_by_users
  FROM xx_rjd_kpi_tbl k
  LEFT JOIN xx_rjd_kpi_hidden h ON h.kpi_id = k.id
 GROUP BY k.id, k.type, k.code, k.label
HAVING COUNT(h.user_id) > 0
 ORDER BY hidden_by_users DESC, k.id;

PROMPT === Расчёт активных системных KPI ===
SELECT k.id, k.type, k.code, k.label,
       xx_rjd_dislocation_new_pkg.set_kpi_label(k.id) AS value
  FROM xx_rjd_kpi_tbl k
 WHERE k.is_system = 'Y'
   AND k.is_active = 'Y'
 ORDER BY k.type, k.order_by, k.id;

PROMPT === Условие системного KPI: задайте ID при необходимости ===
DEFINE kpi_id = 1
SELECT xx_rjd_dislocation_new_pkg.get_kpi_where(&kpi_id) AS kpi_condition
  FROM dual;
