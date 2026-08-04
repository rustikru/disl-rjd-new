-- Рассылки формируют вложения только в формате Excel.
-- Oracle 11g.

UPDATE xx_rjd_report_mailings
   SET file_format = 'XLSX'
 WHERE file_format <> 'XLSX';

ALTER TABLE xx_rjd_report_mailings
    DROP CONSTRAINT ck_xx_rjd_mailings_format;

ALTER TABLE xx_rjd_report_mailings
    ADD CONSTRAINT ck_xx_rjd_mailings_format
        CHECK (file_format = 'XLSX');

COMMIT;
