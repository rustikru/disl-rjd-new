CREATE OR REPLACE package body APPS.xx_mtf_send_mail_pkg
is

--**Пример использования см в спецификации пакета**--

--**Процедура возвращает файл отчёта**--

procedure get_report_file(p_request_id             number
                         ,p_dir                    varchar2
                         ,l_filename    out nocopy varchar2
                         ,l_file        out nocopy blob
                         ,l_filetype    out nocopy varchar2)
as

l_bfile          BFILE;
l_outfile_path   VARCHAR2 (1000);
l_outfile_name   VARCHAR2 (1000);
l_outfile_type   VARCHAR2 (5);

begin

    begin
    select --substr (outfile_name, instr (outfile_name, '/', -1) + 1)
           case when OUTPUT_FILE_TYPE = 'XML' then (select substr (file_name, instr (outfile_name, '/', -1) + 1) from apps.fnd_conc_req_outputs where concurrent_request_id = p_request_id)
           else substr (outfile_name, instr (outfile_name, '/', -1) + 1)
           end
          ,substr (outfile_name, 0,instr(outfile_name,'/', -1,1)-1)
          ,OUTPUT_FILE_TYPE
    into l_outfile_name  ,l_outfile_path, l_outfile_type
    from FND_CONCURRENT_REQUESTS--apps.fnd_conc_req_outputs
    where /*concurrent_*/request_id = p_request_id
    and status_code = 'C';
    exception when NO_DATA_FOUND then null;
    end;

    if l_outfile_type = 'XML' then l_filename := replace(l_outfile_name, '.XML', '.xls'); --l_filetype := 'application/xml';
    elsif l_outfile_type = 'PDF' then l_filename := replace(l_outfile_name, '.out', '.pdf'); --l_filetype := 'application/pdf';
    elsif l_outfile_type = 'TXT' then l_filename := replace(l_outfile_name, '.out', '.txt'); --l_filetype := 'text/plain';
    else l_filename := l_outfile_name; --l_filetype := 'text/plain';
    end if; --Возвращаем имя файла в нужном расширении

    l_filetype := l_outfile_type; --возвращаем тип файла

           /*
           if (g_ip_server ='10.0.0.40') then --m5000
               l_bfile := BFILENAME ('XX_LOB_REQUEST_DIR', l_outfile_name);
           else
               l_bfile := BFILENAME ('XX_LOB_TEST_DIR', l_outfile_name);
           end if;
            */
    l_bfile := BFILENAME (p_dir, l_outfile_name);

    DBMS_LOB.fileopen (l_bfile);

    DBMS_LOB.createtemporary (l_file, FALSE);

    DBMS_LOB.loadfromfile (l_file, l_bfile, DBMS_LOB.getlength (l_bfile));

    DBMS_LOB.fileclose (l_bfile);


end get_report_file;

--**Функция возвращает Content-type: "(p_attach_mime)" для процдуры send_attach_mail
function content_type (p_filetype varchar2) return varchar2
is

l_attach_mime varchar2(50);

begin

    if p_filetype = 'XML' then l_attach_mime := 'application/xml';
    elsif p_filetype = 'PDF' then l_attach_mime := 'application/pdf';
    elsif p_filetype = 'TXT' then l_attach_mime := 'text/plain';
    else l_attach_mime := 'text/plain';
    end if;

    return(l_attach_mime);

end;

--**Процедура отправки письма с вложением**--

procedure send_attach_mail (p_sender      IN VARCHAR2 DEFAULT 'noreply@metafrax.ru',
                            p_recipient   IN VARCHAR2,
                            p_subject     IN VARCHAR2,
                            --p_text_msg    IN VARCHAR2 DEFAULT NULL,
                            p_text_msg      in clob default null,
                            p_attach_name IN VARCHAR2 DEFAULT NULL,
                            p_attach_mime IN VARCHAR2 DEFAULT NULL,
                            p_attach_blob IN BLOB DEFAULT NULL)

as

l_mail_conn        UTL_SMTP.connection;
l_boundary         VARCHAR2(50) := '----=*#abc1234321cba#*=';
l_step             PLS_INTEGER  := 57;
l_smtp_host        CONSTANT VARCHAR2 (20) := apps.fnd_profile.VALUE('FND_SMTP_HOST');
cnst_dest_char_set CONSTANT VARCHAR2 (20) := 'UTF8';
cnst_sbj_prt       CONSTANT NUMBER := 24;
l_subject          VARCHAR2 (2000);
tmp_varchar2       VARCHAR2 (2000);
buffer             varchar2(16384);
offset             pls_integer := 1;
begin

  l_subject := '';

  if p_subject is not null
    then
        for i in 1 .. CEIL (LENGTH (p_subject) / cnst_sbj_prt)
            loop
                tmp_varchar2 := CONVERT (SUBSTR (p_subject, (i - 1) * cnst_sbj_prt + 1, cnst_sbj_prt), cnst_dest_char_set);
                tmp_varchar2 := UTL_RAW.cast_to_varchar2 (UTL_ENCODE.base64_encode (UTL_RAW.cast_to_raw (tmp_varchar2)));
                l_subject := l_subject || ' =?UTF-8?B?' || tmp_varchar2 || '?=';
            end loop;
  end if;

  l_mail_conn := UTL_SMTP.open_connection(l_smtp_host);
  UTL_SMTP.helo(l_mail_conn, l_smtp_host);
  UTL_SMTP.mail(l_mail_conn, p_sender);
  UTL_SMTP.rcpt(l_mail_conn, p_recipient);

  UTL_SMTP.open_data(l_mail_conn);

  UTL_SMTP.write_data(l_mail_conn, 'Date: ' || TO_CHAR(SYSDATE, 'DD-MON-YYYY HH24:MI:SS') || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'From: ' || p_sender || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'To: ' || p_recipient || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'Subject: ' || l_subject || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'Reply-To: ' || p_sender || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'MIME-Version: 1.0' || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'Content-Type: multipart/mixed; boundary="' || l_boundary || '"' || UTL_TCP.crlf || UTL_TCP.crlf);

  if p_text_msg is not null then
    offset := 1;
    UTL_SMTP.write_data(l_mail_conn, '--' || l_boundary || UTL_TCP.crlf);
    UTL_SMTP.write_data(l_mail_conn, 'Content-Type: text/html; charset="utf-8"' || UTL_TCP.crlf || UTL_TCP.crlf);

    --UTL_SMTP.write_raw_data (l_mail_conn, UTL_RAW.cast_to_raw (UTL_TCP.crlf || CONVERT (p_text_msg, cnst_dest_char_set)));
    loop
        exit when offset > dbms_lob.getlength(p_text_msg);
        utl_smtp.write_raw_data ( l_mail_conn,  utl_raw.cast_to_raw(convert ( dbms_lob.substr( p_text_msg, 255, offset ) , 'UTF8')));
        offset := offset + 255;
        end loop;
    UTL_SMTP.write_data(l_mail_conn, UTL_TCP.crlf || UTL_TCP.crlf);
  end if;

  if p_attach_name is not null then
    UTL_SMTP.write_data(l_mail_conn, '--' || l_boundary || UTL_TCP.crlf);
    UTL_SMTP.write_data(l_mail_conn, 'Content-Type: ' || p_attach_mime || '; name="' || p_attach_name || '"' || UTL_TCP.crlf);
    UTL_SMTP.write_data(l_mail_conn, 'Content-Transfer-Encoding: base64' || UTL_TCP.crlf);
    UTL_SMTP.write_data(l_mail_conn, 'Content-Disposition: attachment; filename="' || p_attach_name || '"' || UTL_TCP.crlf || UTL_TCP.crlf);

    for i in 0 .. TRUNC((DBMS_LOB.getlength(p_attach_blob) - 1 )/l_step)
        loop
            UTL_SMTP.write_data(l_mail_conn, UTL_RAW.cast_to_varchar2(UTL_ENCODE.base64_encode(DBMS_LOB.substr(p_attach_blob, l_step, i * l_step + 1))) || UTL_TCP.crlf);
        end loop;

    UTL_SMTP.write_data(l_mail_conn, UTL_TCP.crlf);
  end if;

  UTL_SMTP.write_data(l_mail_conn, '--' || l_boundary || '--' || UTL_TCP.crlf);
  UTL_SMTP.close_data(l_mail_conn);
  UTL_SMTP.quit(l_mail_conn);

end;


-- add 18.12.2024 BekmansurovRR
PROCEDURE ADD_ATTACHMENT ( p_blob in blob, p_filename in varchar2, p_attach_mime in varchar2)
    IS
      --l_file BFILE :=BFILENAME(dirname,filename);
     BEGIN
       IF DBMS_LOB.getlength (p_blob)>0 THEN
          APPS.xx_mtf_send_mail_pkg.attachments.extend;
          APPS.xx_mtf_send_mail_pkg.attachments(APPS.xx_mtf_send_mail_pkg.attachments.count).v_file:=p_blob;
          APPS.xx_mtf_send_mail_pkg.attachments(APPS.xx_mtf_send_mail_pkg.attachments.count).v_filename:=p_filename;
          APPS.xx_mtf_send_mail_pkg.attachments(APPS.xx_mtf_send_mail_pkg.attachments.count).v_attach_mime:=p_attach_mime;
       END IF;
    END;
procedure send_mail (p_sender      IN VARCHAR2 DEFAULT 'noreply@metafrax.ru',
                            p_recipient   IN VARCHAR2,
                            p_subject     IN VARCHAR2,
                            p_text_msg    IN VARCHAR2 DEFAULT NULL,
                            p_text_clob   IN CLOB DEFAULT NULL -- rem 24.09.2025 BekmansurovRR
                            
                            )

as

    l_mail_conn        UTL_SMTP.connection;
    l_boundary         VARCHAR2(50) := '----=*#abc1234321cba#*=';
    l_step             PLS_INTEGER  := 57;
    l_smtp_host        CONSTANT VARCHAR2 (20) := apps.fnd_profile.VALUE('FND_SMTP_HOST');
    cnst_dest_char_set CONSTANT VARCHAR2 (20) := 'UTF8';
    cnst_sbj_prt       CONSTANT NUMBER := 24;
    l_subject          VARCHAR2 (2000);
    tmp_varchar2       VARCHAR2 (2000);
    offset             pls_integer:=1;
    
    FUNCTION subj_encode (subj_str VARCHAR2)
          RETURN VARCHAR2
    -- функция для перекодировки заголовка письма в utf-8
    AS
          a        VARCHAR2 (1000);
          b        VARCHAR2 (24);
          result   VARCHAR2 (4000);
    BEGIN
          a := subj_str;
          WHILE LENGTH (a) > 24
          LOOP
             b := SUBSTR (a, 1, 24);
             a := SUBSTR (a, 25);
             result := result || '=?UTF-8?B?'|| UTL_RAW.cast_to_varchar2(UTL_ENCODE.base64_encode(UTL_RAW.cast_to_raw(CONVERT (b,'utf8'))))|| '?=';
          END LOOP;

          IF LENGTH (a) > 0
          THEN
             result :=
                result || '=?UTF-8?B?' || UTL_RAW.cast_to_varchar2(UTL_ENCODE.base64_encode(UTL_RAW.cast_to_raw(CONVERT (a,'utf8'))))|| '?=';
          END IF;

          RETURN result;
    END;

begin

  l_subject := '';

  if p_subject is not null
    then
        for i in 1 .. CEIL (LENGTH (p_subject) / cnst_sbj_prt)
            loop
                tmp_varchar2 := CONVERT (SUBSTR (p_subject, (i - 1) * cnst_sbj_prt + 1, cnst_sbj_prt), cnst_dest_char_set);
                tmp_varchar2 := UTL_RAW.cast_to_varchar2 (UTL_ENCODE.base64_encode (UTL_RAW.cast_to_raw (tmp_varchar2)));
                l_subject := l_subject || ' =?UTF-8?B?' || tmp_varchar2 || '?=';
            end loop;
  end if;

  l_mail_conn := UTL_SMTP.open_connection(l_smtp_host);
  UTL_SMTP.helo(l_mail_conn, l_smtp_host);
  UTL_SMTP.mail(l_mail_conn, p_sender);
  UTL_SMTP.rcpt(l_mail_conn, p_recipient);

  UTL_SMTP.open_data(l_mail_conn);

  UTL_SMTP.write_data(l_mail_conn, 'Date: ' || TO_CHAR(SYSDATE, 'DD-MON-YYYY HH24:MI:SS') || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'From: ' || p_sender || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'To: ' || p_recipient || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'Subject: ' || l_subject || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'Reply-To: ' || p_sender || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'MIME-Version: 1.0' || UTL_TCP.crlf);
  UTL_SMTP.write_data(l_mail_conn, 'Content-Type: multipart/mixed; boundary="' || l_boundary || '"' || UTL_TCP.crlf || UTL_TCP.crlf);

  if p_text_msg is not null then
    UTL_SMTP.write_data(l_mail_conn, '--' || l_boundary || UTL_TCP.crlf);
    UTL_SMTP.write_data(l_mail_conn, 'Content-Type: text/html; charset="utf-8"' || UTL_TCP.crlf || UTL_TCP.crlf);

    UTL_SMTP.write_raw_data (l_mail_conn, UTL_RAW.cast_to_raw (UTL_TCP.crlf || CONVERT (p_text_msg, cnst_dest_char_set)));
    UTL_SMTP.write_data(l_mail_conn, UTL_TCP.crlf || UTL_TCP.crlf);
  end if;
  -- add 24.09.2025 BekmansurovRR
  if dbms_lob.getlength(p_text_clob) > 0 then
    offset := 1;
    UTL_SMTP.write_data(l_mail_conn, '--' || l_boundary || UTL_TCP.crlf);
    UTL_SMTP.write_data(l_mail_conn, 'Content-Type: text/html; charset="utf-8"' || UTL_TCP.crlf || UTL_TCP.crlf);

    --UTL_SMTP.write_raw_data (l_mail_conn, UTL_RAW.cast_to_raw (UTL_TCP.crlf || CONVERT (p_text_msg, cnst_dest_char_set)));
    loop
        exit when offset > dbms_lob.getlength(p_text_clob);
        utl_smtp.write_raw_data ( l_mail_conn,  utl_raw.cast_to_raw(convert ( dbms_lob.substr( p_text_clob, 255, offset ) , 'UTF8')));
        offset := offset + 255;
        end loop;
    UTL_SMTP.write_data(l_mail_conn, UTL_TCP.crlf || UTL_TCP.crlf);
  end if;

  IF xx_mtf_send_mail_pkg.attachments is not null THEN
    FOR x IN 1 .. xx_mtf_send_mail_pkg.attachments.count LOOP
        UTL_SMTP.write_data(l_mail_conn, '--' || l_boundary || UTL_TCP.crlf);
        UTL_SMTP.write_data(l_mail_conn, 'Content-Type: ' || xx_mtf_send_mail_pkg.attachments(x).v_attach_mime || '; name="' || xx_mtf_send_mail_pkg.attachments(x).v_filename || '"' || UTL_TCP.crlf);
        UTL_SMTP.write_data(l_mail_conn, 'Content-Transfer-Encoding: base64' || UTL_TCP.crlf);
        UTL_SMTP.write_data(l_mail_conn, 'Content-Disposition: attachment; filename="' || subj_encode(xx_mtf_send_mail_pkg.attachments(x).v_filename) || '"' || UTL_TCP.crlf || UTL_TCP.crlf);

        FOR i IN 0 .. TRUNC((DBMS_LOB.getlength(xx_mtf_send_mail_pkg.attachments(x).v_file) - 1 )/l_step) LOOP
          UTL_SMTP.write_data(l_mail_conn, UTL_RAW.cast_to_varchar2(UTL_ENCODE.base64_encode(DBMS_LOB.substr(xx_mtf_send_mail_pkg.attachments(x).v_file, l_step, i * l_step + 1))));
        END LOOP;

        UTL_SMTP.write_data(l_mail_conn, UTL_TCP.crlf || UTL_TCP.crlf);
    END LOOP;
  END IF;

  UTL_SMTP.write_data(l_mail_conn, '--' || l_boundary || '--' || UTL_TCP.crlf);
  UTL_SMTP.close_data(l_mail_conn);
  UTL_SMTP.quit(l_mail_conn);

end;

end; 
/
