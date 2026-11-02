-- KPI для справок «Подход» и «Отправление».
-- Условия отбора находятся в XX_RJD_DISLOCATION_NEW_PKG.GET_KPI_WHERE.

MERGE INTO XX_ETW.XX_RJD_KPI_TBL target
USING (
    SELECT 'approach_kpi' AS type, 'abandoned_wagons' AS code, 'Брошенные вагоны' AS label, 40 AS order_by FROM dual
    UNION ALL
    SELECT 'approach_kpi', 'formed_trains', 'Сформированные поезда', 50 FROM dual
    UNION ALL
    SELECT 'departue_kpi', 'abandoned_trains', 'Брошенные поезда', 40 FROM dual
) source
ON (target.type = source.type AND target.code = source.code)
WHEN NOT MATCHED THEN
    INSERT (type, code, label, order_by, trend_period, trend_unit)
    VALUES (source.type, source.code, source.label, source.order_by, NULL, 'шт.');

COMMIT;
