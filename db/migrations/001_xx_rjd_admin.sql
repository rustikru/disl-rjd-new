-- =====================================================================
--  Администрирование: переименование таблицы пользователей + роли
--  Oracle 23c
--
--  ВНИМАНИЕ: после переименования таблицы код приложения ожидает
--  имя xx_rjd_users. Применять этот скрипт нужно ВМЕСТЕ с выкладкой
--  кода (иначе вход в систему перестанет работать).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Переименование xx_users_rjd -> xx_rjd_users
-- ---------------------------------------------------------------------
ALTER TABLE xx_users_rjd RENAME TO xx_rjd_users;

-- ---------------------------------------------------------------------
-- 2. Справочник ролей
-- ---------------------------------------------------------------------
CREATE TABLE xx_rjd_roles (
  id           NUMBER        NOT NULL,
  code         VARCHAR2(30)  NOT NULL,          -- ADMIN / OPERATOR / VIEWER
  name         VARCHAR2(100) NOT NULL,          -- отображаемое название
  description  VARCHAR2(400),
  is_system    NUMBER(1) DEFAULT 0 NOT NULL,    -- 1 = системная роль, нельзя удалить
  created_at   DATE DEFAULT SYSDATE NOT NULL,
  CONSTRAINT pk_xx_rjd_roles PRIMARY KEY (id),
  CONSTRAINT uq_xx_rjd_roles_code UNIQUE (code)
);

-- Последовательность и триггер автоинкремента id
CREATE SEQUENCE xx_rjd_roles_seq START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_roles_bi
BEFORE INSERT ON xx_rjd_roles
FOR EACH ROW
WHEN (NEW.id IS NULL)
BEGIN
  :NEW.id := xx_rjd_roles_seq.NEXTVAL;
END;
/

-- ---------------------------------------------------------------------
-- 3. Доступ роли к страницам (постраничное разграничение)
--    Составной первичный ключ (role_id, page) — суррогатный id и
--    последовательность не нужны.
-- ---------------------------------------------------------------------
CREATE TABLE xx_rjd_role_pages (
  role_id  NUMBER       NOT NULL,
  page     VARCHAR2(40) NOT NULL,               -- dashboard / maps / import / admin
  CONSTRAINT pk_xx_rjd_role_pages PRIMARY KEY (role_id, page),
  CONSTRAINT fk_xx_rjd_role_pages_role
    FOREIGN KEY (role_id) REFERENCES xx_rjd_roles (id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 4. Привязка роли к пользователю
-- ---------------------------------------------------------------------
ALTER TABLE xx_rjd_users ADD (role_id NUMBER);
ALTER TABLE xx_rjd_users ADD CONSTRAINT fk_xx_rjd_users_role
  FOREIGN KEY (role_id) REFERENCES xx_rjd_roles (id);

-- ---------------------------------------------------------------------
-- 5. Системные роли
-- ---------------------------------------------------------------------
INSERT INTO xx_rjd_roles (code, name, description, is_system)
VALUES ('ADMIN', 'Администратор', 'Полный доступ ко всем разделам и управление пользователями', 1);
INSERT INTO xx_rjd_roles (code, name, description, is_system)
VALUES ('OPERATOR', 'Оператор', 'Просмотр данных и загрузка справок РЖД', 1);
INSERT INTO xx_rjd_roles (code, name, description, is_system)
VALUES ('VIEWER', 'Наблюдатель', 'Только просмотр данных', 1);

-- ---------------------------------------------------------------------
-- 6. Доступ ролей к страницам
--    Администратор — все страницы; Оператор — без админки; Наблюдатель — только просмотр
-- ---------------------------------------------------------------------
INSERT INTO xx_rjd_role_pages (role_id, page)
SELECT id, p.page FROM xx_rjd_roles r
CROSS JOIN (
  SELECT 'dashboard' AS page FROM dual UNION ALL
  SELECT 'maps'      FROM dual UNION ALL
  SELECT 'import'    FROM dual UNION ALL
  SELECT 'admin'     FROM dual
) p
WHERE r.code = 'ADMIN';

INSERT INTO xx_rjd_role_pages (role_id, page)
SELECT id, p.page FROM xx_rjd_roles r
CROSS JOIN (
  SELECT 'dashboard' AS page FROM dual UNION ALL
  SELECT 'maps'      FROM dual UNION ALL
  SELECT 'import'    FROM dual
) p
WHERE r.code = 'OPERATOR';

INSERT INTO xx_rjd_role_pages (role_id, page)
SELECT id, p.page FROM xx_rjd_roles r
CROSS JOIN (
  SELECT 'dashboard' AS page FROM dual UNION ALL
  SELECT 'maps'      FROM dual
) p
WHERE r.code = 'VIEWER';

COMMIT;

-- ---------------------------------------------------------------------
-- 7. Назначить администратора (ОБЯЗАТЕЛЬНО заменить логин на реальный!)
--    Пока ни один пользователь не имеет роли ADMIN, страница /admin
--    доступна любому авторизованному пользователю (режим первичной настройки).
-- ---------------------------------------------------------------------
-- UPDATE xx_rjd_users u
--   SET u.role_id = (SELECT id FROM xx_rjd_roles WHERE code = 'ADMIN')
-- WHERE u.username = 'ВАШ_ЛОГИН';
-- COMMIT;
