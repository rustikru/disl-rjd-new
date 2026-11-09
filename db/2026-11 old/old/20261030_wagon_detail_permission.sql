-- Сохраняет доступ к детализации для ролей, которым до разделения прав
-- была доступна детализация хотя бы одного отчёта.
INSERT INTO xx_rjd_role_pages (role_id, page)
SELECT DISTINCT source.role_id, 'wagon-detail'
  FROM xx_rjd_role_pages source
 WHERE source.page IN (
       'dislocation', 'approach', 'departure', 'loading', 'downtime',
       'technical-state', 'claims', 'raw-material', 'analysis-period',
       'downtime-control'
 )
   AND NOT EXISTS (
       SELECT 1
         FROM xx_rjd_role_pages target
        WHERE target.role_id = source.role_id
          AND target.page = 'wagon-detail'
   );

COMMIT;
