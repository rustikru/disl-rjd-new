-- Одноразово убирает 5 часов, добавленных PHP при разборе числовых дат Excel.
-- REPORT_DT не изменяется: он читается из A2 как текст и не был затронут ошибкой.
-- Повторный запуск ещё раз сдвинет даты на 5 часов.
-- Запустить до первой загрузки, выполненной уже исправленным PHP-кодом.

SET SERVEROUTPUT ON

DECLARE
    l_current_rows NUMBER;
    l_archive_rows NUMBER;
BEGIN
    UPDATE XX_ETW.XX_DISLOCATION_RJD
       SET TRIP_START_DT       = TRIP_START_DT - (5 / 24),
           TRIP_END_DT         = TRIP_END_DT - (5 / 24),
           OPER_DT             = OPER_DT - (5 / 24),
           ASOUP_DEPART_DT     = ASOUP_DEPART_DT - (5 / 24),
           ASOUP_ARRIVE_DT     = ASOUP_ARRIVE_DT - (5 / 24),
           STATE_ASSIGN_DT     = STATE_ASSIGN_DT - (5 / 24),
           NORM_DELIVERY_DT    = NORM_DELIVERY_DT - (5 / 24),
           REG_DATE            = REG_DATE - (5 / 24),
           BUILD_DATE          = BUILD_DATE - (5 / 24),
           NEXT_REPAIR_DT      = NEXT_REPAIR_DT - (5 / 24),
           LAST_CAP_REPAIR_DT  = LAST_CAP_REPAIR_DT - (5 / 24),
           LAST_DEP_REPAIR_DT  = LAST_DEP_REPAIR_DT - (5 / 24),
           EXCLUDE_DATE        = EXCLUDE_DATE - (5 / 24),
           LIFE_EXT_DATE       = LIFE_EXT_DATE - (5 / 24),
           LEASE_END_DATE      = LEASE_END_DATE - (5 / 24),
           SERVICE_LIFE        = SERVICE_LIFE - (5 / 24)
     WHERE TRIP_START_DT IS NOT NULL
        OR TRIP_END_DT IS NOT NULL
        OR OPER_DT IS NOT NULL
        OR ASOUP_DEPART_DT IS NOT NULL
        OR ASOUP_ARRIVE_DT IS NOT NULL
        OR STATE_ASSIGN_DT IS NOT NULL
        OR NORM_DELIVERY_DT IS NOT NULL
        OR REG_DATE IS NOT NULL
        OR BUILD_DATE IS NOT NULL
        OR NEXT_REPAIR_DT IS NOT NULL
        OR LAST_CAP_REPAIR_DT IS NOT NULL
        OR LAST_DEP_REPAIR_DT IS NOT NULL
        OR EXCLUDE_DATE IS NOT NULL
        OR LIFE_EXT_DATE IS NOT NULL
        OR LEASE_END_DATE IS NOT NULL
        OR SERVICE_LIFE IS NOT NULL;

    l_current_rows := SQL%ROWCOUNT;

    UPDATE XX_ETW.XX_RJD_DISLOCATION_ARCHIVE
       SET TRIP_START_DT       = TRIP_START_DT - (5 / 24),
           TRIP_END_DT         = TRIP_END_DT - (5 / 24),
           OPER_DT             = OPER_DT - (5 / 24),
           ASOUP_DEPART_DT     = ASOUP_DEPART_DT - (5 / 24),
           ASOUP_ARRIVE_DT     = ASOUP_ARRIVE_DT - (5 / 24),
           STATE_ASSIGN_DT     = STATE_ASSIGN_DT - (5 / 24),
           NORM_DELIVERY_DT    = NORM_DELIVERY_DT - (5 / 24),
           REG_DATE            = REG_DATE - (5 / 24),
           BUILD_DATE          = BUILD_DATE - (5 / 24),
           NEXT_REPAIR_DT      = NEXT_REPAIR_DT - (5 / 24),
           LAST_CAP_REPAIR_DT  = LAST_CAP_REPAIR_DT - (5 / 24),
           LAST_DEP_REPAIR_DT  = LAST_DEP_REPAIR_DT - (5 / 24),
           EXCLUDE_DATE        = EXCLUDE_DATE - (5 / 24),
           LIFE_EXT_DATE       = LIFE_EXT_DATE - (5 / 24),
           LEASE_END_DATE      = LEASE_END_DATE - (5 / 24),
           SERVICE_LIFE        = SERVICE_LIFE - (5 / 24)
     WHERE TRIP_START_DT IS NOT NULL
        OR TRIP_END_DT IS NOT NULL
        OR OPER_DT IS NOT NULL
        OR ASOUP_DEPART_DT IS NOT NULL
        OR ASOUP_ARRIVE_DT IS NOT NULL
        OR STATE_ASSIGN_DT IS NOT NULL
        OR NORM_DELIVERY_DT IS NOT NULL
        OR REG_DATE IS NOT NULL
        OR BUILD_DATE IS NOT NULL
        OR NEXT_REPAIR_DT IS NOT NULL
        OR LAST_CAP_REPAIR_DT IS NOT NULL
        OR LAST_DEP_REPAIR_DT IS NOT NULL
        OR EXCLUDE_DATE IS NOT NULL
        OR LIFE_EXT_DATE IS NOT NULL
        OR LEASE_END_DATE IS NOT NULL
        OR SERVICE_LIFE IS NOT NULL;

    l_archive_rows := SQL%ROWCOUNT;

    COMMIT;
    DBMS_OUTPUT.PUT_LINE ('Исправлено текущих строк: ' || l_current_rows);
    DBMS_OUTPUT.PUT_LINE ('Исправлено архивных строк: ' || l_archive_rows);
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
