MERGE INTO XX_ETW.XX_RJD_KPI_TBL target
USING (
    SELECT 'loading_kpi' AS type, 'loading_today' AS code, 'Погрузка сегодня' AS label, 10 AS order_by FROM dual
    UNION ALL
    SELECT 'loading_kpi', 'loading_yesterday', 'Погрузка вчера', 20 FROM dual
    UNION ALL
    SELECT 'loading_kpi', 'loading_month', 'Погрузка с начала месяца', 30 FROM dual
) source
ON (target.type = source.type AND target.code = source.code)
WHEN NOT MATCHED THEN
    INSERT (type, code, label, order_by, trend_period, trend_unit)
    VALUES (source.type, source.code, source.label, source.order_by, NULL, 'шт.')
