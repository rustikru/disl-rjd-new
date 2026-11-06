-- Управление пользовательскими KPI: удаление и клонирование.
-- Совместимо с Oracle 11g.
--
-- ВАЖНО:
-- 1. Сначала выполните блок ПРОВЕРКА, чтобы убедиться, что выбран нужный KPI.
-- 2. Скрипт намеренно запрещает удалять и клонировать системные KPI (IS_SYSTEM = 'Y').
-- 3. Клон принадлежит тому же владельцу и размещается на той же странице.
-- 4. Для клона задаются новый CODE и LABEL; остальные настройки копируются.
-- 5. Блоки КЛОНИРОВАНИЕ и УДАЛЕНИЕ независимы. Выполняйте только нужный.
--
-- Запуск в SQL Developer: Run Script (F5).
-- Перед запуском замените значения DEFINE ниже.

DEFINE KPI_ID = 123
DEFINE NEW_KPI_CODE = 'MY_KPI_COPY'
DEFINE NEW_KPI_LABEL = 'Копия KPI'

SET SERVEROUTPUT ON
SET VERIFY OFF

PROMPT === ПРОВЕРКА ВЫБРАННОГО KPI ===

SELECT
    id,
    type,
    code,
    label,
    page_code,
    owner_id,
    is_system,
    is_active,
    order_by,
    trend_period,
    trend_unit,
    DBMS_LOB.GETLENGTH(where_config) AS where_config_length
FROM XX_ETW.XX_RJD_KPI_TBL
WHERE id = &KPI_ID;

SELECT
    k.id AS kpi_id,
    d.detail_type,
    d.detail_profile,
    d.group_field,
    d.group_title,
    d.count_label,
    d.empty_label,
    d.is_active AS detail_is_active
FROM XX_ETW.XX_RJD_KPI_TBL k
LEFT JOIN XX_ETW.XX_RJD_KPI_DETAIL_CFG d ON d.kpi_id = k.id
WHERE k.id = &KPI_ID;

PROMPT
PROMPT === КЛОНИРОВАНИЕ ПОЛЬЗОВАТЕЛЬСКОГО KPI ===
PROMPT Если клонирование не требуется, пропустите этот блок.

DECLARE
    l_source_id   NUMBER := &KPI_ID;
    l_new_id      NUMBER;
    l_new_code    VARCHAR2(50) := &NEW_KPI_CODE;
    l_new_label   VARCHAR2(150) := &NEW_KPI_LABEL;
    l_source      XX_ETW.XX_RJD_KPI_TBL%ROWTYPE;
    l_code_count  NUMBER;
BEGIN
    IF TRIM(l_new_code) IS NULL OR TRIM(l_new_label) IS NULL THEN
        RAISE_APPLICATION_ERROR(-20001, 'Новый CODE и LABEL должны быть заполнены.');
    END IF;

    IF LENGTH(l_new_code) > 50 OR LENGTH(l_new_label) > 150 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Новый CODE или LABEL превышает допустимую длину.');
    END IF;

    SELECT *
      INTO l_source
      FROM XX_ETW.XX_RJD_KPI_TBL
     WHERE id = l_source_id
       FOR UPDATE;

    IF l_source.is_system <> 'N' THEN
        RAISE_APPLICATION_ERROR(-20003, 'Клонировать этим скриптом можно только пользовательские KPI.');
    END IF;

    SELECT COUNT(*)
      INTO l_code_count
      FROM XX_ETW.XX_RJD_KPI_TBL
     WHERE type = l_source.type
       AND code = TRIM(l_new_code);

    IF l_code_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20004, 'KPI с таким TYPE и CODE уже существует.');
    END IF;

    SELECT XX_ETW.XX_RJD_KPI_TBL_SEQ.NEXTVAL
      INTO l_new_id
      FROM dual;

    INSERT INTO XX_ETW.XX_RJD_KPI_TBL (
        id,
        type,
        code,
        label,
        order_by,
        trend_period,
        trend_unit,
        page_code,
        owner_id,
        is_system,
        where_config,
        is_active
    ) VALUES (
        l_new_id,
        l_source.type,
        TRIM(l_new_code),
        TRIM(l_new_label),
        l_source.order_by,
        l_source.trend_period,
        l_source.trend_unit,
        l_source.page_code,
        l_source.owner_id,
        'N',
        l_source.where_config,
        l_source.is_active
    );

    INSERT INTO XX_ETW.XX_RJD_KPI_DETAIL_CFG (
        kpi_id,
        detail_type,
        detail_profile,
        group_field,
        group_title,
        count_label,
        empty_label,
        is_active,
        created_at,
        updated_at
    )
    SELECT
        l_new_id,
        detail_type,
        detail_profile,
        group_field,
        group_title,
        count_label,
        empty_label,
        is_active,
        SYSDATE,
        NULL
    FROM XX_ETW.XX_RJD_KPI_DETAIL_CFG
    WHERE kpi_id = l_source_id;

    DBMS_OUTPUT.PUT_LINE('Создан KPI ID=' || l_new_id);
    DBMS_OUTPUT.PUT_LINE('CODE=' || TRIM(l_new_code) || ', LABEL=' || TRIM(l_new_label));
    DBMS_OUTPUT.PUT_LINE('PAGE_CODE=' || l_source.page_code || ', OWNER_ID=' || l_source.owner_id);
    COMMIT;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        ROLLBACK;
        RAISE_APPLICATION_ERROR(-20005, 'Исходный KPI не найден.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

PROMPT
PROMPT === УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЬСКОГО KPI ===
PROMPT Этот блок удаляет KPI с ID из DEFINE KPI_ID. Выполняйте его отдельно и только после проверки.

DECLARE
    l_id        NUMBER := &KPI_ID;
    l_is_system CHAR(1);
BEGIN
    SELECT is_system
      INTO l_is_system
      FROM XX_ETW.XX_RJD_KPI_TBL
     WHERE id = l_id
       FOR UPDATE;

    IF l_is_system <> 'N' THEN
        RAISE_APPLICATION_ERROR(-20006, 'Удаление системного KPI запрещено.');
    END IF;

    DELETE FROM XX_ETW.XX_RJD_KPI_DETAIL_CFG
     WHERE kpi_id = l_id;

    DELETE FROM XX_ETW.XX_RJD_KPI_TBL
     WHERE id = l_id
       AND is_system = 'N';

    IF SQL%ROWCOUNT <> 1 THEN
        RAISE_APPLICATION_ERROR(-20007, 'KPI не удалён: запись не найдена или изменилась.');
    END IF;

    DBMS_OUTPUT.PUT_LINE('Удалён пользовательский KPI ID=' || l_id);
    COMMIT;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        ROLLBACK;
        RAISE_APPLICATION_ERROR(-20008, 'KPI с указанным ID не найден.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

UNDEFINE KPI_ID
UNDEFINE NEW_KPI_CODE
UNDEFINE NEW_KPI_LABEL
