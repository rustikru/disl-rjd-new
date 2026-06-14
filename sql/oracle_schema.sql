
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
-- : администратор (пароль admin123)
INSERT INTO users (username, display_name, email, password_hash)
  VALUES ('admin', 'Администратор', 'admin@company.local',
          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC');
COMMIT;
/
