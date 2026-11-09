-- Дозагружает отсутствующие состояния движения из основной таблицы.
DECLARE
    l_row        XX_ETW.XX_RJD_DISLOCATION_ARCHIVE%ROWTYPE;
    l_exists     NUMBER;
    l_inserted   NUMBER := 0;
BEGIN
    -- Загрузка справки не должна одновременно проверять и добавлять те же состояния.
    LOCK TABLE XX_ETW.XX_RJD_DISLOCATION_ARCHIVE IN SHARE ROW EXCLUSIVE MODE;

    FOR c IN
        (SELECT d.ID
           FROM XX_ETW.XX_DISLOCATION_RJD d
          WHERE d.TYPE_REFERENCE = 'Отправка'
            AND TRIM (d.WAGON_NO) IS NOT NULL
          ORDER BY d.REPORT_DT, d.ID)
    LOOP
        SELECT *
          INTO l_row
          FROM XX_ETW.XX_DISLOCATION_RJD
         WHERE ID = c.ID;

        SELECT COUNT (*)
          INTO l_exists
          FROM XX_ETW.XX_RJD_DISLOCATION_ARCHIVE a
         WHERE a.ORGANIZATION_ID = l_row.ORGANIZATION_ID
           AND a.TYPE_REFERENCE = l_row.TYPE_REFERENCE
           AND a.WAGON_NO = l_row.WAGON_NO
           AND DECODE (TRIM (a.WAYBILL_NO),
                       TRIM (l_row.WAYBILL_NO), 1, 0) = 1
           AND DECODE (TRIM (a.OPERATION),
                       TRIM (l_row.OPERATION), 1, 0) = 1
           AND DECODE (TRIM (a.OPER_MNEMONIC),
                       TRIM (l_row.OPER_MNEMONIC), 1, 0) = 1
           AND DECODE (a.OPER_DT, l_row.OPER_DT, 1, 0) = 1
           AND DECODE (TRIM (a.OPER_STATION),
                       TRIM (l_row.OPER_STATION), 1, 0) = 1
           AND DECODE (TRIM (a.TRAIN_NO),
                       TRIM (l_row.TRAIN_NO), 1, 0) = 1
           AND DECODE (TRIM (a.TRAIN_INDEX),
                       TRIM (l_row.TRAIN_INDEX), 1, 0) = 1;

        IF l_exists = 0
        THEN
            l_row.ID := XX_ETW.XX_RJD_DISL_ARCHIVE_SEQ.NEXTVAL;
            INSERT INTO XX_ETW.XX_RJD_DISLOCATION_ARCHIVE VALUES l_row;
            l_inserted := l_inserted + 1;
        END IF;
    END LOOP;

    COMMIT;
    DBMS_OUTPUT.PUT_LINE ('Добавлено строк: ' || l_inserted);
END;
/
