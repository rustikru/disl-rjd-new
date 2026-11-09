-- Подключает универсальную группировку поездов для KPI «Забайкальск» на странице «Подход вагонов».
-- Профиль abandoned повторяет группировку брошенных поездов.

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
      INTO v_count
      FROM ALL_TAB_COLUMNS
     WHERE OWNER = 'XX_ETW'
       AND TABLE_NAME = 'XX_RJD_KPI_DETAIL_CFG'
       AND COLUMN_NAME = 'DETAIL_PROFILE';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE XX_ETW.XX_RJD_KPI_DETAIL_CFG ADD (DETAIL_PROFILE VARCHAR2(50))';
    END IF;
END;
/

COMMENT ON COLUMN XX_ETW.XX_RJD_KPI_DETAIL_CFG.DETAIL_PROFILE IS
    'Разрешённый приложением профиль специальной детализации, например abandoned или formed';

DECLARE
    v_rows NUMBER;
BEGIN
    MERGE INTO XX_ETW.XX_RJD_KPI_DETAIL_CFG cfg
    USING (
        SELECT id AS kpi_id, label
          FROM XX_RJD_KPI_TBL_V
         WHERE code = 'zabaykalsk'
           AND type = 'approach_kpi'
    ) src
       ON (cfg.KPI_ID = src.KPI_ID)
    WHEN MATCHED THEN UPDATE SET
        cfg.DETAIL_TYPE = 'SPECIAL',
        cfg.DETAIL_PROFILE = 'abandoned',
        cfg.GROUP_FIELD = NULL,
        cfg.GROUP_TITLE = src.label,
        cfg.COUNT_LABEL = 'Групп поездов',
        cfg.EMPTY_LABEL = 'Поездов нет',
        cfg.IS_ACTIVE = 'Y',
        cfg.UPDATED_AT = SYSDATE
    WHEN NOT MATCHED THEN INSERT (
        KPI_ID, DETAIL_TYPE, DETAIL_PROFILE, GROUP_FIELD, GROUP_TITLE,
        COUNT_LABEL, EMPTY_LABEL, IS_ACTIVE, CREATED_AT
    ) VALUES (
        src.KPI_ID, 'SPECIAL', 'abandoned', NULL, src.label,
        'Групп поездов', 'Поездов нет', 'Y', SYSDATE
    );

    v_rows := SQL%ROWCOUNT;
    IF v_rows = 0 THEN
        RAISE_APPLICATION_ERROR(-20001,
            'Не найдена KPI-карточка zabaykalsk типа approach_kpi в XX_RJD_KPI_TBL_V');
    END IF;
END;
/

COMMIT;

-- Проверка настройки:
SELECT kpi.id, kpi.code, kpi.type, cfg.detail_type, cfg.detail_profile,
       cfg.group_title, cfg.count_label, cfg.empty_label, cfg.is_active
  FROM XX_RJD_KPI_TBL_V kpi
  JOIN XX_ETW.XX_RJD_KPI_DETAIL_CFG cfg ON cfg.kpi_id = kpi.id
 WHERE kpi.code = 'zabaykalsk'
   AND kpi.type = 'approach_kpi';
