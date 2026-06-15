
-- ================================================================

CREATE TABLE xx_users_rjd (
    id            NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username      VARCHAR2(100) UNIQUE NOT NULL,
    display_name  VARCHAR2(255) NOT NULL,
    email         VARCHAR2(255) DEFAULT '',
    password_hash VARCHAR2(255) DEFAULT '',
    is_active     NUMBER(1) DEFAULT 1 NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- администратор (пароль admin123)
INSERT INTO xx_users_rjd (username, display_name, email, password_hash)
  VALUES ('admin', 'Администратор', 'admin@company.local',
          '$2y$12$iWucZnPNQhLMzJkstBvMm.xOReRLhoYPVHco9pm5L4WyyhlZzUOP6');
COMMIT;
/
