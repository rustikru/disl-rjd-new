-- =====================================================================
-- Создание организации MTF и привязка существующих данных
-- Oracle Database 11g и выше
--
-- Выполнять после успешного завершения миграции
-- 006_xx_rjd_organizations.sql.
-- =====================================================================

MERGE INTO xx_rjd_organizations target
USING (
    SELECT
        'MTF' AS code,
        'АО "МЕТАФРАКС КЕМИКАЛС"' AS name,
        'Метафракс Кемикалс' AS short_name
    FROM dual
) source
ON (target.code = source.code)
WHEN NOT MATCHED THEN
    INSERT (code, name, short_name, is_active)
    VALUES (source.code, source.name, source.short_name, 1);

UPDATE xx_rjd_users
   SET organization_id = (
       SELECT id
         FROM xx_rjd_organizations
        WHERE code = 'MTF'
   )
 WHERE organization_id IS NULL;

UPDATE xx_dislocation_rjd
   SET organization_id = (
       SELECT id
         FROM xx_rjd_organizations
        WHERE code = 'MTF'
   )
 WHERE organization_id IS NULL;

COMMIT;

-- Контроль после выполнения:
--
-- SELECT COUNT(*) AS users_without_org
--   FROM xx_rjd_users
--  WHERE organization_id IS NULL;
--
-- SELECT COUNT(*) AS rows_without_org
--   FROM xx_dislocation_rjd
--  WHERE organization_id IS NULL;
