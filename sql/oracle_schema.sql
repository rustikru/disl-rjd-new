-- ================================================================
-- Схема Oracle — Система дислокации вагонов
-- Выполнить: sqlplus user/pass@db @oracle_schema.sql
-- ================================================================

CREATE TABLE users (
    id            NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username      VARCHAR2(100) UNIQUE NOT NULL,
    display_name  VARCHAR2(255) NOT NULL,
    email         VARCHAR2(255) DEFAULT '',
    password_hash VARCHAR2(255) DEFAULT '',
    is_active     NUMBER(1) DEFAULT 1 NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE wagon_dislocation (
    id            NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_date   DATE NOT NULL,
    section_id    VARCHAR2(50) NOT NULL,
    section_name  VARCHAR2(255) NOT NULL,
    subsection    VARCHAR2(255),
    park          VARCHAR2(255),
    wagon_type    VARCHAR2(100) NOT NULL,
    wagon_group   VARCHAR2(50) NOT NULL,
    wagon_count   NUMBER DEFAULT 0 NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_disloc_date    ON wagon_dislocation(report_date);
CREATE INDEX idx_disloc_section ON wagon_dislocation(report_date, section_id);

CREATE TABLE wagon_extended (
    id               NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_date      DATE NOT NULL,
    wagon_no         VARCHAR2(20) NOT NULL,
    train            VARCHAR2(20),
    current_station  VARCHAR2(255),
    from_station     VARCHAR2(255),
    to_station       VARCHAR2(255),
    cargo            VARCHAR2(100),
    wagon_count      NUMBER DEFAULT 1,
    status           VARCHAR2(20) DEFAULT 'empty',
    status_label     VARCHAR2(50),
    days_en_route    NUMBER DEFAULT 0,
    expected_arrival VARCHAR2(20),
    park             VARCHAR2(50)
);

CREATE TABLE wagon_approach (
    id                  NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_date         DATE NOT NULL,
    road                VARCHAR2(255) NOT NULL,
    direction           VARCHAR2(10) NOT NULL CHECK (direction IN ('arrive', 'depart')),
    wagon_count         NUMBER DEFAULT 0 NOT NULL,
    wagon_type          VARCHAR2(100) NOT NULL,
    destination_station VARCHAR2(255),
    expected_time       VARCHAR2(20)
);
/

-- Демо: администратор (пароль admin123)
INSERT INTO users (username, display_name, email, password_hash)
  VALUES ('admin', 'Администратор', 'admin@company.local',
          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
COMMIT;
/
