-- Оставляет первую запись каждого состояния движения.
DELETE FROM XX_ETW.XX_RJD_DISLOCATION_ARCHIVE
 WHERE ID IN
       (SELECT ID
          FROM (SELECT ID,
                       ROW_NUMBER ()
                           OVER (
                               PARTITION BY ORGANIZATION_ID,
                                            TYPE_REFERENCE,
                                            WAGON_NO,
                                            WAYBILL_NO,
                                            OPERATION,
                                            OPER_MNEMONIC,
                                            OPER_DT,
                                            OPER_STATION,
                                            TRAIN_NO,
                                            TRAIN_INDEX
                               ORDER BY ID) as row_no
                  FROM XX_ETW.XX_RJD_DISLOCATION_ARCHIVE)
         WHERE row_no > 1);

COMMIT;
