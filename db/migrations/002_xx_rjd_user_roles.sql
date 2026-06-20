-- Убираем role_id из пользователей
ALTER TABLE xx_rjd_users DROP COLUMN role_id;

-- Промежуточная таблица пользователь ↔ роль
CREATE TABLE xx_rjd_user_roles (
    user_id  NUMBER NOT NULL,
    role_id  NUMBER NOT NULL,
    CONSTRAINT xx_rjd_user_roles_pk  PRIMARY KEY (user_id, role_id),
    CONSTRAINT xx_rjd_user_roles_u   FOREIGN KEY (user_id) REFERENCES xx_rjd_users(id)  ON DELETE CASCADE,
    CONSTRAINT xx_rjd_user_roles_r   FOREIGN KEY (role_id) REFERENCES xx_rjd_roles(id)  ON DELETE CASCADE
);
