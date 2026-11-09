-- Системный KPI гружёных вагонов на странице «Отправление вагонов».
-- Условие отбора находится в XX_RJD_DISLOCATION_NEW_PKG.GET_KPI_WHERE.

MERGE INTO XX_ETW.XX_RJD_KPI_TBL target
USING (
    SELECT 'departue_kpi' AS type,
           'loaded-wagons' AS code,
           'Груженные' AS label,
           70 AS order_by,
           'departure' AS page_code
      FROM dual
) source
ON (target.type = source.type AND target.code = source.code)
WHEN MATCHED THEN
    UPDATE SET target.label = source.label,
               target.order_by = source.order_by,
               target.page_code = source.page_code,
               target.is_system = 'Y',
               target.is_active = 'Y',
               target.trend_period = NULL,
               target.trend_unit = 'шт.'
WHEN NOT MATCHED THEN
    INSERT (type, code, label, order_by, trend_period, trend_unit,
            page_code, owner_id, is_system, where_config, is_active)
    VALUES (source.type, source.code, source.label, source.order_by, NULL, 'шт.',
            source.page_code, NULL, 'Y', NULL, 'Y');

COMMIT;
