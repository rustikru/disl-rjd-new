-- Единая точка отправки писем для приложения.
-- PHP вызывает только XX_RJD_DISLOCATION_NEW_PKG, а этот пакет обращается
-- к общему корпоративному пакету APPS.XX_MTF_SEND_MAIL_PKG.
DECLARE
    l_spec       CLOB;
    l_body       CLOB;
    l_cursor     INTEGER;
    l_result     INTEGER;

    FUNCTION package_source(p_type VARCHAR2) RETURN CLOB IS
        l_source CLOB;
    BEGIN
        DBMS_LOB.createtemporary(l_source, TRUE);
        FOR line_record IN (
            SELECT text
              FROM user_source
             WHERE name = 'XX_RJD_DISLOCATION_NEW_PKG'
               AND type = p_type
             ORDER BY line
        ) LOOP
            DBMS_LOB.writeappend(l_source, LENGTH(line_record.text), line_record.text);
        END LOOP;
        RETURN l_source;
    END;

    PROCEDURE compile_source(p_source CLOB) IS
    BEGIN
        l_cursor := DBMS_SQL.open_cursor;
        DBMS_SQL.parse(l_cursor, 'CREATE OR REPLACE ' || p_source, DBMS_SQL.native);
        l_result := DBMS_SQL.execute(l_cursor);
        DBMS_SQL.close_cursor(l_cursor);
    EXCEPTION
        WHEN OTHERS THEN
            IF DBMS_SQL.is_open(l_cursor) THEN
                DBMS_SQL.close_cursor(l_cursor);
            END IF;
            RAISE;
    END;
BEGIN
    l_spec := package_source('PACKAGE');
    l_body := package_source('PACKAGE BODY');

    IF DBMS_LOB.getlength(l_spec) = 0 OR DBMS_LOB.getlength(l_body) = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Пакет XX_RJD_DISLOCATION_NEW_PKG не найден');
    END IF;

    IF DBMS_LOB.instr(LOWER(l_spec), 'procedure mail_start') = 0 THEN
        l_spec := REGEXP_REPLACE(
            l_spec,
            'end[[:space:]]+xx_rjd_dislocation_new_pkg;[[:space:]]*$',
            q'~
   procedure mail_start;

   procedure mail_add_file (
      p_file      in blob,
      p_file_name in varchar2,
      p_mime_type in varchar2
   );

   procedure mail_send (
      p_sender    in varchar2,
      p_recipient in varchar2,
      p_subject   in varchar2,
      p_text      in clob
   );
end xx_rjd_dislocation_new_pkg;~',
            1,
            0,
            'in'
        );
    END IF;

    l_body := REGEXP_REPLACE(
        l_body,
        CASE
            WHEN DBMS_LOB.instr(LOWER(l_body), 'procedure mail_start') = 0
                THEN 'end[[:space:]]+xx_rjd_dislocation_new_pkg;[[:space:]]*$'
            ELSE 'procedure[[:space:]]+mail_start.*end[[:space:]]+mail_send;[[:space:]]*end[[:space:]]+xx_rjd_dislocation_new_pkg;[[:space:]]*$'
        END,
        q'~
   procedure mail_start is
   begin
      -- Раскомментировать после установки APPS.XX_MTF_SEND_MAIL_PKG и выдачи прав:
      -- apps.xx_mtf_send_mail_pkg.attachments := apps.xx_mtf_send_mail_pkg.attach_list();
      null;
   end mail_start;

   procedure mail_add_file (
      p_file      in blob,
      p_file_name in varchar2,
      p_mime_type in varchar2
   ) is
   begin
      -- Раскомментировать после установки APPS.XX_MTF_SEND_MAIL_PKG и выдачи прав:
      -- apps.xx_mtf_send_mail_pkg.add_attachment(
      --    p_blob        => p_file,
      --    p_filename    => p_file_name,
      --    p_attach_mime => p_mime_type
      -- );
      null;
   end mail_add_file;

   procedure mail_send (
      p_sender    in varchar2,
      p_recipient in varchar2,
      p_subject   in varchar2,
      p_text      in clob
   ) is
   begin
      -- Раскомментировать после установки APPS.XX_MTF_SEND_MAIL_PKG и выдачи прав:
      -- apps.xx_mtf_send_mail_pkg.send_mail(
      --    p_sender    => p_sender,
      --    p_recipient => p_recipient,
      --    p_subject   => p_subject,
      --    p_text_clob => p_text
      -- );
      raise_application_error(-20020, 'Отправка почты через Oracle пока не подключена');
   end mail_send;
end xx_rjd_dislocation_new_pkg;~',
        1,
        0,
        'in'
    );

    compile_source(l_spec);
    compile_source(l_body);

    DBMS_LOB.freetemporary(l_spec);
    DBMS_LOB.freetemporary(l_body);
END;
/

