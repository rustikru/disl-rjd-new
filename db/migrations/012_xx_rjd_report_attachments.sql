-- =====================================================================
-- Несколько отчётов в одной рассылке
-- Oracle Database 11g
-- =====================================================================

CREATE TABLE xx_rjd_report_attachments (
    id                NUMBER        NOT NULL,
    mailing_id        NUMBER        NOT NULL,
    position_no       NUMBER(3)     NOT NULL,
    organization_id   NUMBER,
    report_code       VARCHAR2(100) NOT NULL,
    report_view       VARCHAR2(20)  DEFAULT 'DETAIL' NOT NULL,
    filters_json      CLOB,
    created_at        DATE          DEFAULT SYSDATE NOT NULL,
    updated_at        DATE,

    CONSTRAINT pk_xx_rjd_report_attach
        PRIMARY KEY (id),

    CONSTRAINT fk_xx_rjd_attach_mailing
        FOREIGN KEY (mailing_id)
        REFERENCES xx_rjd_report_mailings (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_xx_rjd_attach_org
        FOREIGN KEY (organization_id)
        REFERENCES xx_rjd_organizations (id),

    CONSTRAINT uq_xx_rjd_attach_position
        UNIQUE (mailing_id, position_no),

    CONSTRAINT ck_xx_rjd_attach_view
        CHECK (report_view IN ('SUMMARY', 'DETAIL'))
);

CREATE SEQUENCE xx_rjd_report_attach_seq
    START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_report_attach_bi
BEFORE INSERT ON xx_rjd_report_attachments
FOR EACH ROW
WHEN (NEW.id IS NULL)
BEGIN
    :NEW.id := xx_rjd_report_attach_seq.NEXTVAL;
END;
/

CREATE INDEX ix_xx_rjd_attach_mailing
    ON xx_rjd_report_attachments (mailing_id, position_no);

CREATE INDEX ix_xx_rjd_attach_org
    ON xx_rjd_report_attachments (organization_id);

COMMENT ON TABLE xx_rjd_report_attachments IS
    'Отчёты, прикладываемые к одному письму рассылки';

COMMENT ON COLUMN xx_rjd_report_attachments.position_no IS
    'Порядок вложения в письме';

COMMENT ON COLUMN xx_rjd_report_attachments.filters_json IS
    'Фильтры отдельного отчёта в формате JSON';

-- Существующие настройки становятся первым вложением.
INSERT INTO xx_rjd_report_attachments (
    mailing_id, position_no, organization_id,
    report_code, report_view, filters_json
)
SELECT id, 1, organization_id,
       report_code, report_view, filters_json
  FROM xx_rjd_report_mailings;

ALTER TABLE xx_rjd_report_runs
    MODIFY (file_name VARCHAR2(2000));

COMMIT;
