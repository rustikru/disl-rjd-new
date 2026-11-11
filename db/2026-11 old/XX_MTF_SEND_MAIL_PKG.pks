CREATE OR REPLACE package APPS.xx_mtf_send_mail_pkg
is
    /******************************************************************************
        name:       xx_mtf_send_mail_pkg
        purpose:    Пакет для отправки по e_mail

        revisions:
        ver        date        author             description
        ---------  ----------  ---------------  ------------------------------------
        1.0        08.11.2022  EliseevIS        1. created this package.
        2.0        12.12.2024  BekmansurovRR    1. Добавил возможность, отправлять письма с несколькими разными вложениями.
    ******************************************************************************/

    --******************************************************************************--
    --Пример использования--
    /*

    declare

    l_request_id number := 26566036;--26561633; --id-шка запроса из oebs
    l_dir varchar2(50) := 'XX_TEST0_REPORT_DIR'; --Директория файлов
    x_filename varchar2(100);
    x_file blob;
    p_filetype varchar2(20);
    x_filetype varchar2(50);
    begin

        apps.xx_mtf_send_mail_pkg.get_report_file (l_request_id, l_dir, l_filename => x_filename, l_file => x_file, l_filetype => p_filetype); --процедура возвращает файл запроса.

        x_filetype := apps.xx_mtf_send_mail_pkg.content_type (p_filetype);

        apps.xx_mtf_send_mail_pkg.send_attach_mail ('noreply@metafrax.ru',              --отправитель
                                                    'Igor.Eliseev@metafrax.ru',         --получатель
                                                    'Тест ф-ции отправки',              --Заголовок
                                                    'Тестовый текст ф-ции отправки',    --текст сообщения
                                                     x_filename,                        --имя файла   ___ .xls(pdf, txt)
                                                     x_filetype,                        --'Content-Type: ...
                                                     x_file);                           --Файл в формате blob
    end;

    */
    --******************************************************************************--
    --Список директорий (для параметра p_dir)
    --CONC_OUT - директория для отчетов
    --LOB_DIR - директория для загрузок со скрепки

    --Директория--              --Путь к файлу--
    --XX_C1S_OTHERS             /d00/home/oracle/c1s/others
    --XX_C1S_DEDUCTIONS         /d00/home/oracle/c1s/deductions
    --XX_C1S_RESERVE            /d00/home/oracle/c1s/reserve
    --XX_C1S_INVOICES           /d00/home/oracle/c1s/invoices
    --XX_C1S_EXCHANGE           /d00/home/oracle/c1s/exchange
    --LOB_DIR_OUT               /d00/home/oracle/prod_R12/inst/apps/PROD_m5000/logs/appl/conc/out/
    --XX_MTF_XXLD_ONT_DIR       /d00/home/oracle/prod_R12/inst/apps/PROD_m5000/logs/appl/conc/out/
    --LOB_DIR_LIB               /d00/home/oracle/prod_R12/MTF/itextpdf/
    --FND_DIAG_DIR              /d00/home/oracle/prod_R12/db/tech_st/11.2.0.4/admin/PROD_m5000/diag/rdbms/prod/PROD/trace
    --ORACLE_OCM_CONFIG_DIR2    /d00/home/oracle/prod_R12/db/tech_st/11.2.0.4/ccr/state
    --LOB_DIR_TEST              /d00/home/oracle/prod_R12/inst/apps/PROD_m5000/logs/appl/conc/log/
    --XX_1C_PATH                /d00/home/oracle/VR_1CTD
    --XX_LOB_REQUEST_DIR        /d00/home/oracle/prod_R12/inst/apps/PROD_m5000/logs/appl/conc/out
    --XX_LOB_KID_DIR            /s_file/prod_R12/mfdoc/kid/
    --XX_LOB_DOC_DIR            /s_file/prod_R12/mfdoc/doc/
    --CONC_OUT                  /d00/home/oracle/prod_R12/inst/apps/PROD_m5000/logs/appl/conc/out
    --XX_DISL_CONCURRENT_PATH   /d00/home/oracle/prod_R12/inst/apps/PROD_m5000/logs/appl/conc/out
    --LOB_DIR                   /s_file/prod_R12/scan/upload/
    --SKUD_AUTOUPD              /d00/home/oracle/skud_reverse/log
    --SAI_PS                    /d00/home/oracle/sai_ps
    --MILEAGE_SVRV              /d00/home/oracle/mileage_svrv
    --CARSIGN_SVRV              /d00/home/oracle/carsign_svrv
    --CG_MTF_DIR                /d00/home/oracle/corpus_group/mtf
    --ODPDIR                    /usr/tmp
    --CSR_XML_TOP               /d00/home/oracle/prod_R12/apps/apps_st/appl/csr/12.0.0/patch/115/xml
    --DATA_PUMP_DIR             /d00/home/oracle/prod_R12/db/tech_st/admin/PROD/dpdump/
    --CG_MIF_DIR                /d00/home/oracle/corpus_group/mif
    --ORACLE_OCM_CONFIG_DIR     /d00/home/oracle/prod_R12/db/tech_st/11.2.0.4/ccr/state
    --DISLOCATION_DAT_DIR       /d00/home/oracle/disloc_svrv
    --QUEST_SOO_UDUMP_DIR       /d00/home/oracle/prod_R12/db/tech_st/11.2.0.4/admin/PROD_m5000/diag/rdbms/prod/PROD/trace/
    --QUEST_SOO_CDUMP_DIR       /d00/home/oracle/prod_R12/db/tech_st/11.2.0.4/admin/PROD_m5000/diag/rdbms/prod/PROD/cdump/
    --QUEST_SOO_BDUMP_DIR       /d00/home/oracle/prod_R12/db/tech_st/11.2.0.4/admin/PROD_m5000/diag/rdbms/prod/PROD/trace/
    --QUEST_SOO_ADUMP_DIR       /d00/home/oracle/prod_R12/db/tech_st/admin/PROD/adump/
    --SOURCE_DIR                /d00/home/oracle/photo
    --ECX_UTL_XSLT_DIR_OBJ      /usr/tmp
    --APPS_DATA_FILE_DIR        /d00/home/oracle/prod_R12/db/tech_st/11.2.0/appsutil/outbound/PROD_m5000
    --ECX_UTL_LOG_DIR_OBJ       /usr/tmp

    --******************************************************************************--

    procedure get_report_file (p_request_id                 number,
                               p_dir                        varchar2,
                               l_filename        out nocopy varchar2,
                               l_file            out nocopy blob,
                               l_filetype        out nocopy varchar2);

    function content_type (p_filetype varchar2)
        return varchar2;

    procedure send_attach_mail (
        p_sender        in varchar2 default 'noreply@metafrax.ru',
        p_recipient     in varchar2,
        p_subject       in varchar2,
        --p_text_msg      in varchar2 default null,
        p_text_msg      in clob default null,
        p_attach_name   in varchar2 default null,
        p_attach_mime   in varchar2 default null,
        p_attach_blob   in blob default null);


    -- add 18.12.2024 BekmansurovRR
    -- Вложения для письма
    type attach_row is record
    (
        v_file           blob,
        v_filename       varchar2 (240),
        v_attach_mime    varchar2 (240)
    );

    type attach_list is table of attach_row;

    attachments   attach_list;

    procedure add_attachment (p_blob            in blob,
                              p_filename        in varchar2,
                              p_attach_mime     in varchar2);

    procedure send_mail (
        p_sender      in varchar2 default 'noreply@metafrax.ru',
        p_recipient   in varchar2,
        p_subject     in varchar2,
        p_text_msg    IN VARCHAR2 DEFAULT NULL,
        p_text_clob   IN CLOB DEFAULT NULL -- rem 24.09.2025 BekmansurovRR
        );
end;
/
