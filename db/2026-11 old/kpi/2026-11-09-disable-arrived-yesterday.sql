-- Карточка остаётся в справочнике и может быть снова включена через /admin/kpi.
UPDATE XX_ETW.XX_RJD_KPI_TBL
   SET IS_ACTIVE = 'N'
 WHERE TYPE = 'approach_kpi'
   AND CODE = 'arrived-yesterday'
   AND IS_SYSTEM = 'Y';

COMMIT;
