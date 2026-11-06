-- Системные KPI фактического прибытия и отправления по московской дате.
-- Условия отбора находятся в XX_RJD_DISLOCATION_NEW_PKG.GET_KPI_WHERE.

MERGE INTO XX_ETW.XX_RJD_KPI_TBL target
USING (
    SELECT 'approach_kpi' AS type, 'arrived-today' AS code,
           'Прибыло сегодня' AS label, 60 AS order_by, 'approach' AS page_code
      FROM dual
    UNION ALL
    SELECT 'approach_kpi', 'arrived-yesterday',
           'Прибыло вчера', 70, 'approach'
      FROM dual
    UNION ALL
    SELECT 'departue_kpi', 'departed-today',
           'Отправлено сегодня', 50, 'departure'
      FROM dual
    UNION ALL
    SELECT 'departue_kpi', 'departed-yesterday',
           'Отправлено вчера', 60, 'departure'
      FROM dual
) source
ON (target.type = source.type AND target.code = source.code)
WHEN MATCHED THEN
    UPDATE SET target.label = source.label,
               target.order_by = source.order_by,
               target.page_code = source.page_code,
               target.is_system = 'Y',
               target.trend_period = NULL,
               target.trend_unit = 'шт.'
WHEN NOT MATCHED THEN
    INSERT (type, code, label, order_by, trend_period, trend_unit,
            page_code, owner_id, is_system, where_config, is_active)
    VALUES (source.type, source.code, source.label, source.order_by, NULL, 'шт.',
            source.page_code, NULL, 'Y', NULL, 'Y');

COMMIT;
