-- =====================================================================
-- Подготовка структуры БД для поддержки нескольких организаций
-- Oracle Database 11g и выше
--
-- Совместимость с Oracle 11g:
--   - идентификаторы не превышают 30 символов;
--   - автоинкремент реализован через SEQUENCE + TRIGGER;
--   - IDENTITY, BOOLEAN и другие возможности новых версий не используются.
--
-- Модель доступа:
--   xx_rjd_users.organization_id
--     основная организация пользователя и организация по умолчанию;
--
--   xx_rjd_user_organizations
--     дополнительные организации, доступные пользователю;
--
--   xx_dislocation_rjd.organization_id
--     организация, из личного кабинета которой получена справка.
--
-- ВАЖНО:
--   organization_id в существующих таблицах на этом этапе допускает NULL.
--   Ограничения NOT NULL следует добавить отдельной миграцией после:
--     1. создания первой организации;
--     2. привязки существующих пользователей;
--     3. заполнения organization_id в существующей дислокации;
--     4. выкладки организационно-зависимого импорта.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Справочник организаций
-- ---------------------------------------------------------------------
CREATE TABLE xx_rjd_organizations (
    id          NUMBER         NOT NULL,
    code        VARCHAR2(50)   NOT NULL,
    name        VARCHAR2(300)  NOT NULL,
    short_name  VARCHAR2(150),
    is_active   NUMBER(1)      DEFAULT 1 NOT NULL,
    created_at  DATE           DEFAULT SYSDATE NOT NULL,
    updated_at  DATE,

    CONSTRAINT pk_xx_rjd_organizations
        PRIMARY KEY (id),

    CONSTRAINT uq_xx_rjd_organizations_code
        UNIQUE (code),

    CONSTRAINT ck_xx_rjd_organizations_active
        CHECK (is_active IN (0, 1))
);

CREATE SEQUENCE xx_rjd_organizations_seq
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_organizations_bi
BEFORE INSERT ON xx_rjd_organizations
FOR EACH ROW
WHEN (NEW.id IS NULL)
BEGIN
    :NEW.id := xx_rjd_organizations_seq.NEXTVAL;
END;
/

COMMENT ON TABLE xx_rjd_organizations IS
    'Справочник организаций, данные которых доступны в приложении';

COMMENT ON COLUMN xx_rjd_organizations.code IS
    'Стабильный уникальный код организации для настроек и интеграций';

COMMENT ON COLUMN xx_rjd_organizations.name IS
    'Полное наименование организации';

COMMENT ON COLUMN xx_rjd_organizations.short_name IS
    'Краткое наименование для интерфейса';

COMMENT ON COLUMN xx_rjd_organizations.is_active IS
    'Признак активности: 1 — активна, 0 — отключена';

-- ---------------------------------------------------------------------
-- 2. Основная организация пользователя
--
-- Поле определяет организацию пользователя по умолчанию.
-- Дополнительные доступы хранятся отдельно.
-- ---------------------------------------------------------------------
ALTER TABLE xx_rjd_users
    ADD (organization_id NUMBER);

ALTER TABLE xx_rjd_users
    ADD CONSTRAINT fk_xx_rjd_users_org
    FOREIGN KEY (organization_id)
    REFERENCES xx_rjd_organizations (id);

CREATE INDEX ix_xx_rjd_users_org
    ON xx_rjd_users (organization_id);

COMMENT ON COLUMN xx_rjd_users.organization_id IS
    'Основная организация пользователя и организация по умолчанию';

-- ---------------------------------------------------------------------
-- 3. Дополнительные организации пользователя
--
-- Основную организацию пользователя в эту таблицу не добавляем.
-- Наличие строки означает право просмотра дополнительной организации.
-- ---------------------------------------------------------------------
-- В существующей схеме ID пользователя может не иметь ограничения
-- PRIMARY KEY или UNIQUE. Без него Oracle не разрешит создать внешний
-- ключ xx_rjd_user_organizations.user_id -> xx_rjd_users.id.
--
-- Блок добавляет UNIQUE только при отсутствии подходящего ограничения.
DECLARE
    l_constraint_count NUMBER;
BEGIN
    SELECT COUNT(*)
      INTO l_constraint_count
      FROM (
          SELECT c.constraint_name
            FROM user_constraints c
            JOIN user_cons_columns cc
              ON cc.constraint_name = c.constraint_name
             AND cc.table_name = c.table_name
           WHERE c.table_name = 'XX_RJD_USERS'
             AND c.constraint_type IN ('P', 'U')
             AND c.status = 'ENABLED'
           GROUP BY c.constraint_name
          HAVING COUNT(*) = 1
             AND MAX(cc.column_name) = 'ID'
      );

    IF l_constraint_count = 0 THEN
        EXECUTE IMMEDIATE
            'ALTER TABLE xx_rjd_users '
            || 'ADD CONSTRAINT uq_xx_rjd_users_id UNIQUE (id)';
    END IF;
END;
/

CREATE TABLE xx_rjd_user_organizations (
    user_id          NUMBER       NOT NULL,
    organization_id  NUMBER       NOT NULL,
    created_at       DATE         DEFAULT SYSDATE NOT NULL,

    CONSTRAINT pk_xx_rjd_user_orgs
        PRIMARY KEY (user_id, organization_id),

    CONSTRAINT fk_xx_rjd_user_orgs_user
        FOREIGN KEY (user_id)
        REFERENCES xx_rjd_users (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_xx_rjd_user_orgs_org
        FOREIGN KEY (organization_id)
        REFERENCES xx_rjd_organizations (id)
        ON DELETE CASCADE
);

CREATE INDEX ix_xx_rjd_user_orgs_org
    ON xx_rjd_user_organizations (organization_id);

COMMENT ON TABLE xx_rjd_user_organizations IS
    'Дополнительные организации, доступные пользователям';

-- ---------------------------------------------------------------------
-- 4. Организация-владелец данных дислокации
--
-- Значение назначается всей справке при импорте и не вычисляется
-- отдельно по грузоотправителю или грузополучателю.
-- ---------------------------------------------------------------------
ALTER TABLE xx_dislocation_rjd
    ADD (organization_id NUMBER);

ALTER TABLE xx_dislocation_rjd
    ADD CONSTRAINT fk_xx_dislocation_rjd_org
    FOREIGN KEY (organization_id)
    REFERENCES xx_rjd_organizations (id);

CREATE INDEX ix_xx_disl_org_type_dt
    ON xx_dislocation_rjd (
        organization_id,
        type_reference,
        report_dt
    );

COMMENT ON COLUMN xx_dislocation_rjd.organization_id IS
    'Организация, из личного кабинета которой получена справка';

COMMIT;

-- =====================================================================
-- Следующий этап выполняется отдельной миграцией:
--
--   1. INSERT первой организации в xx_rjd_organizations.
--   2. UPDATE xx_rjd_users SET organization_id = ...
--   3. UPDATE xx_dislocation_rjd SET organization_id = ...
--   4. Проверка отсутствия NULL.
--   5. ALTER ... MODIFY ... NOT NULL после готовности приложения.
-- =====================================================================
