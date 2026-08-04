-- В рассылках используются только основные получатели и обычные копии.
-- Oracle 11g.

DELETE FROM xx_rjd_report_recipients hidden_recipient
 WHERE hidden_recipient.send_type = 'BCC'
   AND EXISTS (
       SELECT 1
         FROM xx_rjd_report_recipients copy_recipient
        WHERE copy_recipient.mailing_id = hidden_recipient.mailing_id
          AND copy_recipient.email = hidden_recipient.email
          AND copy_recipient.send_type = 'CC'
   );

UPDATE xx_rjd_report_recipients
   SET send_type = 'CC'
 WHERE send_type = 'BCC';

ALTER TABLE xx_rjd_report_recipients
    DROP CONSTRAINT ck_xx_rjd_recipients_type;

ALTER TABLE xx_rjd_report_recipients
    ADD CONSTRAINT ck_xx_rjd_recipients_type
        CHECK (send_type IN ('TO', 'CC'));

COMMIT;
