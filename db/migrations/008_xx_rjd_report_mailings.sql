-- =====================================================================
-- Модуль пользовательских рассылок отчётов
-- Oracle Database 11g
-- =====================================================================

CREATE TABLE xx_rjd_report_mailings (
    id                NUMBER         NOT NULL,
    user_id           NUMBER         NOT NULL,
    organization_id   NUMBER,
    name              VARCHAR2(200)  NOT NULL,
    report_code       VARCHAR2(100)  NOT NULL,
    report_view       VARCHAR2(20)   DEFAULT 'DETAIL' NOT NULL,
    file_format       VARCHAR2(10)   DEFAULT 'XLSX' NOT NULL,
    filters_json      CLOB,
    subject_text      VARCHAR2(500),
    body_text         VARCHAR2(2000),
    schedule_type     VARCHAR2(20)   DEFAULT 'DAILY' NOT NULL,
    run_time          VARCHAR2(5)    DEFAULT '08:00' NOT NULL,
    week_days         VARCHAR2(20),
    month_day         NUMBER(2),
    skip_empty        NUMBER(1)      DEFAULT 1 NOT NULL,
    is_active         NUMBER(1)      DEFAULT 1 NOT NULL,
    last_run_at       DATE,
    next_run_at       DATE,
    created_at        DATE           DEFAULT SYSDATE NOT NULL,
    updated_at        DATE,

    CONSTRAINT pk_xx_rjd_report_mailings
        PRIMARY KEY (id),

    CONSTRAINT fk_xx_rjd_mailings_user
        FOREIGN KEY (user_id)
        REFERENCES xx_rjd_users (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_xx_rjd_mailings_org
        FOREIGN KEY (organization_id)
        REFERENCES xx_rjd_organizations (id),

    CONSTRAINT ck_xx_rjd_mailings_view
        CHECK (report_view IN ('SUMMARY', 'DETAIL')),

    CONSTRAINT ck_xx_rjd_mailings_format
        CHECK (file_format IN ('XLSX', 'CSV')),

    CONSTRAINT ck_xx_rjd_mailings_schedule
        CHECK (schedule_type IN ('MANUAL', 'DAILY', 'WEEKLY', 'MONTHLY')),

    CONSTRAINT ck_xx_rjd_mailings_month_day
        CHECK (month_day IS NULL OR month_day BETWEEN 1 AND 31),

    CONSTRAINT ck_xx_rjd_mailings_empty
        CHECK (skip_empty IN (0, 1)),

    CONSTRAINT ck_xx_rjd_mailings_active
        CHECK (is_active IN (0, 1))
);

CREATE SEQUENCE xx_rjd_report_mailings_seq
    START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_report_mailings_bi
BEFORE INSERT ON xx_rjd_report_mailings
FOR EACH ROW
WHEN (NEW.id IS NULL)
BEGIN
    :NEW.id := xx_rjd_report_mailings_seq.NEXTVAL;
END;
/

CREATE INDEX ix_xx_rjd_mailings_user
    ON xx_rjd_report_mailings (user_id, is_active);

CREATE INDEX ix_xx_rjd_mailings_due
    ON xx_rjd_report_mailings (is_active, next_run_at);

CREATE INDEX ix_xx_rjd_mailings_org
    ON xx_rjd_report_mailings (organization_id);

COMMENT ON TABLE xx_rjd_report_mailings IS
    'Пользовательские настройки рассылок отчётов';

COMMENT ON COLUMN xx_rjd_report_mailings.filters_json IS
    'Фильтры отчёта в формате JSON';

COMMENT ON COLUMN xx_rjd_report_mailings.organization_id IS
    'Организация отчёта; NULL для общих отчётов';

-- ---------------------------------------------------------------------
-- Получатели
-- ---------------------------------------------------------------------
CREATE TABLE xx_rjd_report_recipients (
    id          NUMBER        NOT NULL,
    mailing_id  NUMBER        NOT NULL,
    email       VARCHAR2(320) NOT NULL,
    send_type   VARCHAR2(10)  DEFAULT 'TO' NOT NULL,
    created_at  DATE          DEFAULT SYSDATE NOT NULL,

    CONSTRAINT pk_xx_rjd_report_recipients
        PRIMARY KEY (id),

    CONSTRAINT fk_xx_rjd_recipients_mail
        FOREIGN KEY (mailing_id)
        REFERENCES xx_rjd_report_mailings (id)
        ON DELETE CASCADE,

    CONSTRAINT uq_xx_rjd_recipients_mail
        UNIQUE (mailing_id, email, send_type),

    CONSTRAINT ck_xx_rjd_recipients_type
        CHECK (send_type IN ('TO', 'CC', 'BCC'))
);

CREATE SEQUENCE xx_rjd_report_recip_seq
    START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_report_recipients_bi
BEFORE INSERT ON xx_rjd_report_recipients
FOR EACH ROW
WHEN (NEW.id IS NULL)
BEGIN
    :NEW.id := xx_rjd_report_recip_seq.NEXTVAL;
END;
/

CREATE INDEX ix_xx_rjd_recipients_mail
    ON xx_rjd_report_recipients (mailing_id);

COMMENT ON TABLE xx_rjd_report_recipients IS
    'Получатели пользовательских рассылок';

-- ---------------------------------------------------------------------
-- История и очередь запусков
-- ---------------------------------------------------------------------
CREATE TABLE xx_rjd_report_runs (
    id             NUMBER         NOT NULL,
    mailing_id     NUMBER         NOT NULL,
    started_at     DATE           DEFAULT SYSDATE NOT NULL,
    finished_at    DATE,
    status         VARCHAR2(20)   DEFAULT 'PENDING' NOT NULL,
    report_dt      DATE,
    rows_count     NUMBER,
    file_name      VARCHAR2(500),
    error_message  VARCHAR2(2000),
    created_at     DATE           DEFAULT SYSDATE NOT NULL,

    CONSTRAINT pk_xx_rjd_report_runs
        PRIMARY KEY (id),

    CONSTRAINT fk_xx_rjd_runs_mailing
        FOREIGN KEY (mailing_id)
        REFERENCES xx_rjd_report_mailings (id)
        ON DELETE CASCADE,

    CONSTRAINT ck_xx_rjd_runs_status
        CHECK (status IN ('PENDING', 'RUNNING', 'SENT', 'SKIPPED', 'ERROR'))
);

CREATE SEQUENCE xx_rjd_report_runs_seq
    START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_report_runs_bi
BEFORE INSERT ON xx_rjd_report_runs
FOR EACH ROW
WHEN (NEW.id IS NULL)
BEGIN
    :NEW.id := xx_rjd_report_runs_seq.NEXTVAL;
END;
/

CREATE INDEX ix_xx_rjd_runs_mail
    ON xx_rjd_report_runs (mailing_id, started_at);

CREATE INDEX ix_xx_rjd_runs_status
    ON xx_rjd_report_runs (status, started_at);

-- Не допускает две одновременно открытые задачи одной рассылки.
CREATE UNIQUE INDEX uq_xx_rjd_runs_open
    ON xx_rjd_report_runs (
        CASE WHEN status IN ('PENDING', 'RUNNING') THEN mailing_id END
    );

COMMENT ON TABLE xx_rjd_report_runs IS
    'Очередь и история выполнения рассылок';

COMMIT;
