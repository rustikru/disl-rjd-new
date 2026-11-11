SELECT object_name, object_type, status
  FROM user_objects
 WHERE object_name IN ('XX_RJD_DISLOCATION_ARCHIVE',
                       'XX_RJD_DISL_ARCHIVE_SEQ',
                       'XX_RJD_DISLOCATION_NEW_PKG')
 ORDER BY object_type, object_name;

SELECT column_id, column_name, data_type, data_length, data_precision, data_scale
  FROM user_tab_columns
 WHERE table_name = 'XX_DISLOCATION_RJD'
MINUS
SELECT column_id, column_name, data_type, data_length, data_precision, data_scale
  FROM user_tab_columns
 WHERE table_name = 'XX_RJD_DISLOCATION_ARCHIVE';

SELECT column_id, column_name, data_type, data_length, data_precision, data_scale
  FROM user_tab_columns
 WHERE table_name = 'XX_RJD_DISLOCATION_ARCHIVE'
MINUS
SELECT column_id, column_name, data_type, data_length, data_precision, data_scale
  FROM user_tab_columns
 WHERE table_name = 'XX_DISLOCATION_RJD';

SELECT organization_id,
       type_reference,
       COUNT (*) as archive_rows,
       COUNT (DISTINCT wagon_no) as wagons,
       MIN (report_dt) as first_report_dt,
       MAX (report_dt) as last_report_dt
  FROM xx_rjd_dislocation_archive
 GROUP BY organization_id, type_reference
 ORDER BY organization_id, type_reference;

SELECT organization_id,
       type_reference,
       wagon_no,
       waybill_no,
       operation,
       oper_mnemonic,
       oper_dt,
       oper_station,
       train_no,
       train_index,
       COUNT (*) as duplicate_count
  FROM xx_rjd_dislocation_archive
 GROUP BY organization_id,
          type_reference,
          wagon_no,
          waybill_no,
          operation,
          oper_mnemonic,
          oper_dt,
          oper_station,
          train_no,
          train_index
HAVING COUNT (*) > 1
 ORDER BY duplicate_count DESC, wagon_no, oper_dt;

SELECT name, type, line, position, text
  FROM user_errors
 WHERE name = 'XX_RJD_DISLOCATION_NEW_PKG'
 ORDER BY type, sequence;
