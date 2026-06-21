-- Промежуточная таблица пользователь ↔ роль (множественные роли)
CREATE TABLE xx_rjd_user_roles (
    user_id  NUMBER NOT NULL,
    role_id  NUMBER NOT NULL,
    CONSTRAINT xx_rjd_user_roles_pk  PRIMARY KEY (user_id, role_id),
    CONSTRAINT xx_rjd_user_roles_u   FOREIGN KEY (user_id) REFERENCES xx_rjd_users(id)  ON DELETE CASCADE,
    CONSTRAINT xx_rjd_user_roles_r   FOREIGN KEY (role_id) REFERENCES xx_rjd_roles(id)  ON DELETE CASCADE
);

-- Переносим существующие назначения из role_id до удаления колонки
INSERT INTO xx_rjd_user_roles (user_id, role_id)
SELECT id, role_id
  FROM xx_rjd_users
 WHERE role_id IS NOT NULL;

-- Только после переноса данных — удаляем старую колонку
ALTER TABLE xx_rjd_users DROP COLUMN role_id;
