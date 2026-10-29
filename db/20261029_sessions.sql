-- Реестр авторизованных входов. Время — Unix timestamp, идентификатор — SHA-256.
BEGIN
    EXECUTE IMMEDIATE 'CREATE TABLE xx_rjd_sessions (
        session_key VARCHAR2(64) PRIMARY KEY,
        user_id VARCHAR2(150) NOT NULL,
        username VARCHAR2(150),
        display_name VARCHAR2(500),
        ip VARCHAR2(64),
        agent VARCHAR2(1000),
        started NUMBER(19) NOT NULL,
        seen NUMBER(19) NOT NULL,
        revoked NUMBER(1) DEFAULT 0 NOT NULL CHECK (revoked IN (0,1))
    )';
EXCEPTION WHEN OTHERS THEN
    IF SQLCODE != -955 THEN RAISE; END IF;
END;
/
