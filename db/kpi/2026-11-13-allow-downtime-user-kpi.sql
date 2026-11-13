-- Полный список страниц, для которых сейчас разрешено создание пользовательских KPI:
--   approach  — «Подход вагонов» (approach_kpi)
--   departure — «Отправление вагонов» (departue_kpi)
--   downtime  — «Простои» (downtime_kpi)
-- Остальные страницы имеют user_creatable = false в KpiTypeRegistry.
ALTER TABLE XX_ETW.XX_RJD_KPI_TBL
    DROP CONSTRAINT XX_RJD_KPI_TBL_PAGE_CK;

ALTER TABLE XX_ETW.XX_RJD_KPI_TBL
    ADD CONSTRAINT XX_RJD_KPI_TBL_PAGE_CK
    CHECK (IS_SYSTEM = 'Y' OR PAGE_CODE IN ('approach', 'departure', 'downtime'));
