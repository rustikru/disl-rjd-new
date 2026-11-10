CREATE OR REPLACE package body XX_ETW.xx_rjd_dislocation_new_pkg
as
    /******************************************************************************
        NAME:  xx_etw.xx_rjd_dislocation_new_pkg
        PURPOSE:   Дислокация РЖД (справка из кабинета)
        REVISIONS:
        Ver        Date        Author           Description
        ---------  ----------  ---------------  ------------------------------------
        1.0        15.06.2026  BekmansurovRR    1. Created this package.
     ******************************************************************************/
    function get_organization_id
        return number
    is
        v_org_id number;
    begin
        return TO_NUMBER (SYS_CONTEXT ('USERENV', 'CLIENT_IDENTIFIER'));
    end get_organization_id;

    function get_latest_reports (p_type_1       in varchar2 default 'Подход',
                                 p_type_2       in varchar2 default 'Отправка',
                                 p_report_day   in date default null)
        return latest_report_tab
        pipelined
    is
        l_row         latest_report_rec;
        l_report_dt   date;
    begin
        if TRIM (p_type_1) is not null
        then
            if p_report_day is null
            then
                select MAX (report_dt)
                  into l_report_dt
                  from xx_dislocation_rjd
                 where organization_id = get_organization_id ()
                   and type_reference = TRIM (p_type_1);
            else
                select MAX (report_dt)
                  into l_report_dt
                  from xx_dislocation_rjd
                 where organization_id = get_organization_id ()
                   and type_reference = TRIM (p_type_1)
                   and report_dt >= TRUNC (p_report_day)
                   and report_dt < TRUNC (p_report_day) + 1;
            end if;

            if l_report_dt is not null
            then
                l_row.type_reference := TRIM (p_type_1);
                l_row.report_dt := l_report_dt;
                pipe row (l_row);
            end if;
        end if;

        if     TRIM (p_type_2) is not null
           and (TRIM (p_type_1) is null or TRIM (p_type_2) != TRIM (p_type_1))
        then
            if p_report_day is null
            then
                select MAX (report_dt)
                  into l_report_dt
                  from xx_dislocation_rjd
                 where organization_id = get_organization_id ()
                   and type_reference = TRIM (p_type_2);
            else
                select MAX (report_dt)
                  into l_report_dt
                  from xx_dislocation_rjd
                 where organization_id = get_organization_id ()
                   and type_reference = TRIM (p_type_2)
                   and report_dt >= TRUNC (p_report_day)
                   and report_dt < TRUNC (p_report_day) + 1;
            end if;

            if l_report_dt is not null
            then
                l_row.type_reference := TRIM (p_type_2);
                l_row.report_dt := l_report_dt;
                pipe row (l_row);
            end if;
        end if;

        return;
    end get_latest_reports;
    
    procedure set_org_id (p_org_code varchar2)
    is
        v_org_id number;
    begin
        begin
            select id into v_org_id
              from xx_rjd_organizations
            where is_active = 1
                  and code = p_org_code;
        exception when no_data_found then v_org_id := null;
        end;
        DBMS_SESSION.SET_IDENTIFIER(v_org_id);
    end;
    
    function fnc_mapping_wag_type (p_wag_name in varchar2)
        return varchar2
    is
        v_wag_name_up   varchar2 (4000);
    begin
        v_wag_name_up := UPPER (p_wag_name);

        case
            when v_wag_name_up like '%ЦИСТЕРНЫ%'
            then
                return 'ЦС';
            when v_wag_name_up like '%КРЫТЫЕ%'
            then
                return 'КР';
            when v_wag_name_up like '%ПОЛУВАГОНЫ%'
            then
                return 'ПВ';
            when     v_wag_name_up like '%ПЛАТФОРМЫ%'
                 and v_wag_name_up not like '%ФИТИНГОВЫЕ%'
            then
                return 'ПЛ';
            when v_wag_name_up like '%ФИТИНГОВЫЕ%'
            then
                return 'ФТГ';
            when v_wag_name_up like '%ПРОЧИЕ%'
            then
                return 'ПР';
            when v_wag_name_up like '%МИНЕРАЛОВОЗЫ%'
            then
                return 'МВЗ';
            when v_wag_name_up like '%ЗЕРНОВОЗЫ%'
            then
                return 'ЗРВ';
            when v_wag_name_up like '%ЦЕМЕНТОВОЗЫ%'
            then
                return 'ЦМВ';
            else
                return p_wag_name;
        end case;
    end;

    function fnc_get_downtime_wagon (p_downtime   in number,
                                     p_type       in varchar2 default 'name')
        return varchar2
    is
        l_name       varchar2 (150) := 'простой';
        l_order_by   number := 0;
        l_downtime   number;
    begin
        l_downtime := p_downtime;

        if l_downtime is null
        then
            l_name := 'Недостаточно данных о начале простоя';
            l_order_by := 70;
        elsif l_downtime between 0 and 4
        then
            l_name := l_name || ' до 5 сут.';
            l_order_by := 10;
        elsif l_downtime between 5 and 6
        then
            l_name := l_name || ' от 5 до 7 сут.';
            l_order_by := 20;
        elsif l_downtime between 7 and 9
        then
            l_name := l_name || ' от 7 до 10 сут.';
            l_order_by := 30;
        elsif l_downtime between 10 and 19
        then
            l_name := l_name || ' от 10 до 20 сут.';
            l_order_by := 40;
        elsif l_downtime between 20 and 29
        then
            l_name := l_name || ' от 20 до 30 сут.';
            l_order_by := 50;
        elsif l_downtime > 29
        then
            l_name := l_name || ' от 30 и более сут.';
            l_order_by := 60;
        else
            l_name := l_name || ' *';
            l_order_by := 999;
        end if;

        if p_type = 'name'
        then
            return l_name;
        elsif p_type = 'order_by'
        then
            return TO_CHAR (l_order_by);
        else
            return l_name;
        end if;
    end;

    function fnc_get_state_wagon (p_weight in number)
        return varchar2
    is
    begin
        if NVL (p_weight, 0) = 0
        then
            return 'пор.';
        else
            return 'гр.';
        end if;
    end;

    -- вынесена на уровень пакета, чтобы использовать в тренде
    procedure calculate_change (
        p_yesterday_value   in     number,
        p_today_value       in     number,
        p_return            in     varchar2 default 'percent',
        p_percent              out varchar2,
        p_direction            out varchar2)
    is
        v_yesterday    number := NVL (p_yesterday_value, 0);
        v_today        number := NVL (p_today_value, 0);
        v_return_txt   varchar2 (150);
    begin
        if p_return = 'percent'
        then
            v_return_txt := '%';
        else
            v_return_txt := 'Вчера: ';
        end if;

        if v_yesterday = 0 and v_today > 0
        then
            if p_return = 'percent'
            then
                p_percent := '100' || v_return_txt;
            else
                p_percent := '0';
            end if;

            p_direction := 'up';
        elsif v_yesterday != 0 and v_today = 0
        then
            if p_return = 'percent'
            then
                p_percent := '100' || v_return_txt;
            else
                p_percent := TO_CHAR (v_yesterday);
            end if;

            p_direction := 'down';
        else
            if v_yesterday != 0
            then
                --p_percent := 'Вчера:'||to_char(v_yesterday);
                if p_return = 'percent'
                then
                    p_percent :=
                           TO_CHAR (
                               ROUND (
                                     ((v_today - v_yesterday) / v_yesterday)
                                   * 100,
                                   2))
                        || v_return_txt;
                else
                    p_percent := v_return_txt || TO_CHAR (v_yesterday);
                end if;
            else
                if p_return = 'percent'
                then
                    p_percent := '0' || v_return_txt;
                else
                    p_percent := TO_CHAR (0);
                end if;
            end if;

            if v_today > v_yesterday
            then
                p_direction := 'up';
            elsif v_today < v_yesterday
            then
                p_direction := 'down';
            else
                p_direction := '-';
            end if;
        end if;
    exception
        when others
        then
            p_percent := '0';
            p_direction := '';
    end calculate_change;

    -- Возвращает условие отбора для KPI.
    function get_kpi_where (p_kpi_id in number)
        return varchar2
    is
        l_kpi        xx_rjd_kpi_tbl_v%rowtype;
        l_org_code   xx_rjd_organizations.code%type;
    begin
        begin
            select code
              into l_org_code
              from xx_rjd_organizations
             where id = get_organization_id ();
        exception
            when NO_DATA_FOUND
            then
                return '1=0';
        end;

        if UPPER (l_org_code) != 'MTF'
        then
            return '1=0';
        end if;

        select *
          into l_kpi
          from xx_rjd_kpi_tbl_v
         where id = p_kpi_id;

        -- KPI для страницы "Отправление вагонов"
        if l_kpi.TYPE = 'departue_kpi'
        then
            if l_kpi.code = 'eysk'
            then
                return 'UPPER(dest_station) LIKE UPPER(''%Ейск%515405%'')';
            elsif l_kpi.code = 'zabaykalsk'
            then
                return 'UPPER(dest_station) LIKE UPPER(''%Забайкальск%'')';
            elsif l_kpi.code = 'luzskaya'
            then
                return 'UPPER(dest_station) LIKE UPPER(''%Лужская%'')';
            elsif l_kpi.code = 'abandoned_trains'
            then
                return 'type_reference = ''Отправка'' AND (UPPER(NVL(operation, '' '')) LIKE ''%БРОСАНИЕ ПОЕЗДА НА СТАНЦИИ%'' OR (TRIM(operation) IS NULL AND UPPER(NVL(wagon_state, '' '')) LIKE ''%БРОШЕН%''))';
            elsif l_kpi.code = 'departed-today'
            then
                return 'type_reference = ''Отправка'' AND asoup_depart_dt IS NOT NULL AND UPPER(TRIM(depart_station)) LIKE ''%УГЛ%768207%'' AND TRUNC(asoup_depart_dt) = TRUNC(CAST(SYSTIMESTAMP AT TIME ZONE ''Europe/Moscow'' AS DATE))';
            elsif l_kpi.code = 'departed-yesterday'
            then
                return 'type_reference = ''Отправка'' AND asoup_depart_dt IS NOT NULL AND UPPER(TRIM(depart_station)) LIKE ''%УГЛ%768207%'' AND TRUNC(asoup_depart_dt) = TRUNC(CAST(SYSTIMESTAMP AT TIME ZONE ''Europe/Moscow'' AS DATE)) - 1';
            elsif l_kpi.code = 'loaded-wagons'
            then
                return 'type_reference = ''Отправка'' AND UPPER(TRIM(depart_station)) LIKE ''%УГЛ%768207%'' AND dist_remain_km > 0 AND NVL(cargo_weight_kg, 0) > CASE WHEN UPPER(NVL(wagon_type_code, '' '')) LIKE ''%ПЛАТФОРМ%'' THEN 12000 ELSE 0 END';
            end if;
        -- KPI для страницы "Дислокация/Дашборд"
        elsif l_kpi.TYPE = 'dashboard_kpi'
        then
            if l_kpi.code = 'wag_cistern'
            then
                return 'xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code) = ''ЦС''';
            elsif l_kpi.code = 'wag_others'
            then
                return 'xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code) != ''ЦС''';
            elsif l_kpi.code = 'loaded_transit'
            then
                return 'UPPER(TRIM(dest_station)) NOT LIKE ''%УГЛ%768207%'' AND dest_station != oper_station AND cargo_weight_kg > 0';
            elsif l_kpi.code = 'comming_to_ugl'
            then
                return 'UPPER(TRIM(dest_station)) LIKE ''%УГЛ%768207%'' AND oper_station != dest_station';
            elsif l_kpi.code = 'arrived_today_ugl'
            then
                return 'UPPER(TRIM(dest_station)) LIKE ''%УГЛ%768207%'' AND oper_station = dest_station AND TRUNC(oper_dt) = TRUNC(SYSDATE)';
            end if;
        -- KPI для страницы "Подход вагонов"
        elsif l_kpi.TYPE = 'approach_kpi'
        then
            if l_kpi.code = 'eysk'
            then
                return 'UPPER(depart_station) LIKE UPPER(''%Ейск%515405%'')';
            elsif l_kpi.code = 'zabaykalsk'
            then
                return 'UPPER(depart_station) LIKE UPPER(''%Забайкальск%'')';
            elsif l_kpi.code = 'luzskaya'
            then
                return 'UPPER(depart_station) LIKE UPPER(''%Лужская%'')';
            elsif l_kpi.code = 'delivery_time'
            then
                return 'type_reference = ''Подход'' AND TRUNC(report_dt) > TRUNC(norm_delivery_dt) AND trip_end_dt IS NULL AND UPPER(oper_station) NOT LIKE UPPER(''%Угле%'')';
            elsif l_kpi.code = 'abandoned_wagons'
            then
                return 'type_reference = ''Подход'' AND (UPPER(NVL(operation, '' '')) LIKE ''%БРОСАНИЕ ПОЕЗДА НА СТАНЦИИ%'' OR (TRIM(operation) IS NULL AND UPPER(NVL(wagon_state, '' '')) LIKE ''%БРОШЕН%''))';
            elsif l_kpi.code = 'formed_trains'
            then
                return 'type_reference = ''Подход'' AND (REGEXP_SUBSTR(TRIM(train_index), ''^[0-9]+'') LIKE ''%68207'' OR UPPER(train_index) LIKE ''%УГЛЕУРАЛЬСКАЯ%'' OR UPPER(train_index) LIKE ''%БЕРЕЗНИКИ%'' OR UPPER(train_index) LIKE ''%КИЗЕЛ%'' OR UPPER(train_index) LIKE ''%ЗАЯЧЬЯ ГОРКА%'')';
            -- прибыло сегодня
            elsif l_kpi.code = 'arrived-today'
            then
                return 'type_reference = ''Подход'' AND NVL(asoup_arrive_dt, trip_end_dt) IS NOT NULL AND UPPER(TRIM(dest_station)) LIKE ''%УГЛ%768207%'' AND TRUNC(NVL(asoup_arrive_dt, trip_end_dt)) = TRUNC(CAST(SYSTIMESTAMP AT TIME ZONE ''Europe/Moscow'' AS DATE))';
            -- прибыло вчера
            elsif l_kpi.code = 'arrived-yesterday'
            then
                return 'type_reference = ''Подход'' AND NVL(asoup_arrive_dt, trip_end_dt) IS NOT NULL AND UPPER(TRIM(dest_station)) LIKE ''%УГЛ%768207%'' AND TRUNC(NVL(asoup_arrive_dt, trip_end_dt)) = TRUNC(CAST(SYSTIMESTAMP AT TIME ZONE ''Europe/Moscow'' AS DATE)) - 1';
            end if;
        end if;

        raise_application_error (-20001, 'Условие для KPI не определено.');
    end get_kpi_where;

    function fnc_check_kpi (p_kpi_id in number, p_disl_rjd_id in number)
        return number
    is
        l_dummy   number;
    begin
        execute immediate   'SELECT 1 FROM xx_dislocation_rjd'
                         || ' WHERE id = :id'
                         || ' AND organization_id = XX_RJD_DISLOCATION_NEW_PKG.GET_ORGANIZATION_ID()'
                         || ' AND '
                         || get_kpi_where (p_kpi_id)
            into l_dummy
            using p_disl_rjd_id;

        return 1;
    exception
        when NO_DATA_FOUND
        then
            return 0;
    end fnc_check_kpi;

    -- Формирует подпись KPI по текущей и предыдущей справке.
    function set_kpi_label (p_kpi_id in number, p_date in date default null)
        return varchar2
    is
        l_kpi_where    varchar2 (2000);
        l_sql          varchar2 (4000);
        l_value        number;
        l_kpi_code     xx_rjd_kpi_tbl_v.code%type;
        l_count_expr   varchar2 (300) := 'COUNT(*)';
    begin
        select code
          into l_kpi_code
          from xx_rjd_kpi_tbl_v
         where id = p_kpi_id;

        l_kpi_where := get_kpi_where (p_kpi_id);

        -- Неизвестный KPI
        if l_kpi_where = '1=0'
        then
            return '';
        end if;

        if l_kpi_code = 'formed_trains'
        then
            l_count_expr := 'COUNT(DISTINCT TRIM(train_no))';
        elsif l_kpi_code = 'abandoned_trains'
        then
            l_count_expr :=
                'COUNT(DISTINCT NVL(TRIM(train_index), NVL(TRIM(train_no), TRIM(TO_CHAR(wagon_no)))) || '':'' || TRIM(oper_station))';
        end if;

        l_kpi_where :=
               l_kpi_where
            || ' AND NOT EXISTS (SELECT 1 FROM xx_disl_idle_control_v dic WHERE TRIM(dic.car_number) = TRIM(TO_CHAR(wagon_no)) AND UPPER(TRIM(dic.is_excluded)) = ''Y'' AND UPPER(REPLACE(TRIM(dic.idle_reasons_name), ''Ё'', ''Е'')) IN (''ЛОМ'', ''МЕТАЛЛОЛОМ'', ''СНЯТЬ с АВТОСЛЕЖЕНИЯ''))';

        if p_date is null
        then
            -- Текущие данные из последней справки каждого типа.
            l_sql :=
                   'SELECT '
                || l_count_expr
                || ' FROM xx_dislocation_rjd'
                || ' WHERE organization_id = XX_RJD_DISLOCATION_NEW_PKG.GET_ORGANIZATION_ID()'
                || ' AND (report_dt, type_reference) IN ('
                || '   SELECT report_dt, type_reference'
                || '     FROM TABLE(XX_RJD_DISLOCATION_NEW_PKG.GET_LATEST_REPORTS())'
                || ' ) AND '
                || l_kpi_where;

            --dbms_output.put_line(' 1_l_sql =' || l_sql);
            execute immediate l_sql
                into l_value;
        else
            -- Последняя справка каждого типа за указанные сутки.
            l_sql :=
                   'SELECT '
                || l_count_expr
                || ' FROM xx_dislocation_rjd'
                || ' WHERE organization_id = XX_RJD_DISLOCATION_NEW_PKG.GET_ORGANIZATION_ID()'
                || ' AND (report_dt, type_reference) IN ('
                || '   SELECT report_dt, type_reference'
                || '     FROM TABLE(XX_RJD_DISLOCATION_NEW_PKG.GET_LATEST_REPORTS(''Подход'', ''Отправка'', :d1))'
                || ' ) AND '
                || l_kpi_where;

            --dbms_output.put_line(' 2_l_sql=' || l_sql);
            execute immediate l_sql
                into l_value
                using p_date;
        end if;

        return TO_CHAR (l_value);
    exception
        when others
        then
            if SQLCODE = -20001
            then
                raise;
            end if;

            return '';
    end set_kpi_label;

    -- Изменение по сравнению с прошлым днем/есяцем
    procedure prv_kpi_trend (p_id        in     number,
                             p_pct_out      out varchar2,
                             p_dir_out      out varchar2)
    is
        v_period        varchar2 (10);
        v_metric_type   varchar2 (20);
        v_current       number := 0;
        v_previous      number := 0;
    begin
        select NVL (trend_period, 'Day'), NVL (trend_unit, 'шт.')
          into v_period, v_metric_type
          from xx_rjd_kpi_tbl_v
         where id = p_id;

        v_current := TO_NUMBER (NVL (set_kpi_label (p_id), '0'));

        if v_period = 'Day'
        then
            v_previous :=
                TO_NUMBER (NVL (set_kpi_label (p_id, SYSDATE - 1), '0'));
        elsif v_period = 'Month'
        then
            v_previous :=
                TO_NUMBER (
                    NVL (set_kpi_label (p_id, ADD_MONTHS (SYSDATE, -1)), '0'));
        end if;

        calculate_change (p_yesterday_value   => v_previous,
                          p_today_value       => v_current,
                          p_return            => v_metric_type,
                          p_percent           => p_pct_out,
                          p_direction         => p_dir_out);
    exception
        when others
        then
            p_pct_out := null;
            p_dir_out := '';
    end prv_kpi_trend;

    function fnc_get_kpi_trend_pct (p_id in number)
        return varchar2
    is
        v_pct   varchar2 (100);
        v_dir   varchar2 (10);
    begin
        prv_kpi_trend (p_id, v_pct, v_dir);
        return v_pct;
    exception
        when others
        then
            return null;
    end fnc_get_kpi_trend_pct;

    function fnc_get_kpi_trend_dir (p_id in number)
        return varchar2
    is
        v_pct   varchar2 (100);
        v_dir   varchar2 (10);
    begin
        prv_kpi_trend (p_id, v_pct, v_dir);
        return v_dir;
    exception
        when others
        then
            return '';
    end fnc_get_kpi_trend_dir;

    function get_kpi_row (p_kpi_id in number)
        return t_xx_rjd_kpi_tab
        pipelined
    is
        v_value   varchar2 (200);
        v_pct     varchar2 (50);
        v_dir     varchar2 (10);
    begin
        v_value := set_kpi_label (p_kpi_id);
        prv_kpi_trend (p_kpi_id, v_pct, v_dir);
        pipe row (t_xx_rjd_kpi_row (v_value, v_pct, v_dir));
        return;
    end get_kpi_row;

    procedure archive_rjd_movement (p_report_dt        in date,
                                    p_type_reference   in varchar2)
    is
        l_organization_id   number := get_organization_id ();
        l_row               xx_rjd_dislocation_archive%rowtype;
        l_exists            number;
    begin
        if p_report_dt is null or TRIM (p_type_reference) is null
        then
            raise_application_error (-20031,
                                     'Не заданы параметры архива движения');
        end if;

        -- Параллельные загрузки не должны создавать одинаковые состояния.
        lock table xx_rjd_dislocation_archive in share row exclusive mode;

        for c
            in (  select d.id
                    from xx_dislocation_rjd d
                   where     d.organization_id = l_organization_id
                         and d.report_dt = p_report_dt
                         and d.type_reference = TRIM (p_type_reference)
                         and TRIM (d.wagon_no) is not null
                order by d.id)
        loop
            select *
              into l_row
              from xx_dislocation_rjd
             where id = c.id;

            select COUNT (*)
              into l_exists
              from xx_rjd_dislocation_archive a
             where     a.organization_id = l_organization_id
                   and a.type_reference = l_row.type_reference
                   and a.wagon_no = l_row.wagon_no
                   and DECODE (TRIM (a.waybill_no),
                               TRIM (l_row.waybill_no), 1,
                               0) =
                       1
                   and DECODE (TRIM (a.operation),
                               TRIM (l_row.operation), 1,
                               0) =
                       1
                   and DECODE (TRIM (a.oper_mnemonic),
                               TRIM (l_row.oper_mnemonic), 1,
                               0) =
                       1
                   and DECODE (a.oper_dt, l_row.oper_dt, 1, 0) = 1
                   and DECODE (TRIM (a.oper_station),
                               TRIM (l_row.oper_station), 1,
                               0) =
                       1
                   and DECODE (TRIM (a.train_no),
                               TRIM (l_row.train_no), 1,
                               0) =
                       1
                   and DECODE (TRIM (a.train_index),
                               TRIM (l_row.train_index), 1,
                               0) =
                       1;

            if l_exists = 0
            then
                l_row.id := xx_rjd_disl_archive_seq.NEXTVAL;

                insert into xx_rjd_dislocation_archive
                     values l_row;
            end if;
        end loop;
    end archive_rjd_movement;

    procedure parse_rjd_excel (p_data in clob)
    is
        l_latest_report_dt   date;
    begin
        for c
            in (           select distinct
                                  TO_DATE (r.report_dt, 'YYYY-MM-DD HH24:MI:SS')
                                      as report_dt,
                                  TRIM (r.type_reference)
                                      as type_reference
                             from XMLTABLE (
                                      '/rjd/rjd_row'
                                      passing xmltype (p_data)
                                      columns report_dt         varchar2 (30) path '@report_dt',
                                              type_reference    varchar2 (50) path '@type_reference')
                                  r
                            where TRIM (r.type_reference) is not null)
        loop
            select MAX (report_dt)
              into l_latest_report_dt
              from xx_dislocation_rjd
             where     report_dt >= TRUNC (c.report_dt)
                   and report_dt < TRUNC (c.report_dt) + 1
                   and type_reference = c.type_reference
                   and organization_id = get_organization_id ();

            if    l_latest_report_dt is null
               or c.report_dt >= l_latest_report_dt
            then
                delete xx_dislocation_rjd
                 where     report_dt >= TRUNC (c.report_dt)
                       and report_dt < TRUNC (c.report_dt) + 1
                       and type_reference = c.type_reference
                       and organization_id = get_organization_id ();
            else
                -- Старая справка нужна только для пополнения архива.
                delete xx_dislocation_rjd
                 where     report_dt = c.report_dt
                       and type_reference = c.type_reference
                       and organization_id = get_organization_id ();
            end if;
        end loop;

        insert into xx_dislocation_rjd (organization_id,
                                        report_dt,
                                        type_reference,
                                        wagon_no,
                                        waybill_no,
                                        wagon_type_code,
                                        owner_admin,
                                        trip_start_dt,
                                        depart_state,
                                        depart_road,
                                        depart_station,
                                        trip_end_dt,
                                        dest_state,
                                        dest_road,
                                        dest_station,
                                        consignor_tgnl,
                                        consignor,
                                        consignor_okpo,
                                        consignor_name,
                                        consignee_tgnl,
                                        consignee,
                                        consignee_okpo,
                                        consignee_name,
                                        cargo_name,
                                        cargo_gng,
                                        cargo_weight_kg,
                                        mileage_loaded_km,
                                        mileage_empty_km,
                                        mileage_total_km,
                                        mileage_norm_km,
                                        mileage_remain_km,
                                        mileage_sign,
                                        special_marks,
                                        prev_cargo,
                                        oper_station,
                                        oper_road,
                                        operation,
                                        oper_mnemonic,
                                        oper_dt,
                                        park_type,
                                        handover_road,
                                        receive_road,
                                        train_index,
                                        train_no,
                                        wagon_in_train,
                                        park_no,
                                        track_no,
                                        seals_count,
                                        loaded_containers,
                                        empty_containers,
                                        container_nos,
                                        norm_delivery_dt,
                                        dist_passed_km,
                                        dist_remain_km,
                                        dist_total_km,
                                        idle_time_hhmmss,
                                        idle_time_days,
                                        extra_waybill_no,
                                        extra_send_id,
                                        asoup_depart_dt,
                                        asoup_arrive_dt,
                                        send_id,
                                        waybill_id,
                                        wagon_no2,
                                        quality_sign,
                                        state_assign_dt,
                                        wagon_state,
                                        state_reason,
                                        state_station,
                                        reg_date,
                                        build_date,
                                        next_repair_dt,
                                        next_repair_type,
                                        factory_no,
                                        manufacturer,
                                        wagon_type_name,
                                        wagon_model,
                                        tare_weight,
                                        load_capacity,
                                        length_mm,
                                        last_cap_repair_depot,
                                        last_cap_repair_dt,
                                        last_dep_repair_depot,
                                        last_dep_repair_dt,
                                        home_road,
                                        home_depot,
                                        exclude_date,
                                        no_transit_reason,
                                        prev_wagon_no,
                                        owner,
                                        owner_okpo,
                                        owner_local_code,
                                        home_station,
                                        threshold_sign,
                                        lease_sign,
                                        life_ext_date,
                                        lessee,
                                        lessee_okpo,
                                        lessee_local_code,
                                        lease_home_station,
                                        lease_end_date,
                                        service_life,
                                        body_material_code,
                                        body_material_name,
                                        body_volume,
                                        clearance,
                                        air_dist_type,
                                        automode,
                                        auto_lever,
                                        brake_type,
                                        coupler_type,
                                        bogie_model,
                                        shock_absorber,
                                        life_ext_sign,
                                        boiler_caliber,
                                        drain_device,
                                        lever_gear,
                                        wagon_model_code,
                                        repair_by_mileage,
                                        proxy_operator,
                                        proxy_operator_okpo,
                                        wagon_type_code2,
                                        wagon_type_cond,
                                        axles_count,
                                        exclude_depot,
                                        exclude_reason,
                                        days_to_repair,
                                        days_no_oper,
                                        days_no_move)
                              select get_organization_id (),
                                     TO_DATE (r.report_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.type_reference,
                                     r.wagon_no,
                                     r.waybill_no,
                                     r.wagon_type_code,
                                     r.owner_admin,
                                     TO_DATE (r.trip_start_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.depart_state,
                                     r.depart_road,
                                     r.depart_station,
                                     TO_DATE (r.trip_end_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.dest_state,
                                     r.dest_road,
                                     r.dest_station,
                                     r.consignor_tgnl,
                                     r.consignor,
                                     r.consignor_okpo,
                                     r.consignor_name,
                                     r.consignee_tgnl,
                                     r.consignee,
                                     r.consignee_okpo,
                                     r.consignee_name,
                                     r.cargo_name,
                                     r.cargo_gng,
                                     TO_NUMBER (r.cargo_weight_kg),
                                     TO_NUMBER (r.mileage_loaded_km),
                                     TO_NUMBER (r.mileage_empty_km),
                                     TO_NUMBER (r.mileage_total_km),
                                     TO_NUMBER (r.mileage_norm_km),
                                     TO_NUMBER (r.mileage_remain_km),
                                     r.mileage_sign,
                                     r.special_marks,
                                     r.prev_cargo,
                                     r.oper_station,
                                     r.oper_road,
                                     r.operation,
                                     r.oper_mnemonic,
                                     TO_DATE (r.oper_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.park_type,
                                     r.handover_road,
                                     r.receive_road,
                                     r.train_index,
                                     r.train_no,
                                     TO_NUMBER (r.wagon_in_train),
                                     r.park_no,
                                     r.track_no,
                                     TO_NUMBER (r.seals_count),
                                     TO_NUMBER (r.loaded_containers),
                                     TO_NUMBER (r.empty_containers),
                                     r.container_nos,
                                     TO_DATE (r.norm_delivery_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     TO_NUMBER (r.dist_passed_km),
                                     TO_NUMBER (r.dist_remain_km),
                                     TO_NUMBER (r.dist_total_km),
                                     r.idle_time_hhmmss,
                                     TO_NUMBER (r.idle_time_days),
                                     r.extra_waybill_no,
                                     r.extra_send_id,
                                     TO_DATE (r.asoup_depart_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     TO_DATE (r.asoup_arrive_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.send_id,
                                     r.waybill_id,
                                     r.wagon_no2,
                                     r.quality_sign,
                                     TO_DATE (r.state_assign_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.wagon_state,
                                     r.state_reason,
                                     r.state_station,
                                     TO_DATE (r.reg_date, 'YYYY-MM-DD HH24:MI:SS'),
                                     TO_DATE (r.build_date, 'YYYY-MM-DD HH24:MI:SS'),
                                     TO_DATE (r.next_repair_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.next_repair_type,
                                     r.factory_no,
                                     r.manufacturer,
                                     r.wagon_type_name,
                                     r.wagon_model,
                                     TO_NUMBER (r.tare_weight),
                                     TO_NUMBER (r.load_capacity),
                                     TO_NUMBER (r.length_mm),
                                     r.last_cap_repair_depot,
                                     TO_DATE (r.last_cap_repair_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.last_dep_repair_depot,
                                     TO_DATE (r.last_dep_repair_dt, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.home_road,
                                     r.home_depot,
                                     TO_DATE (r.exclude_date, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.no_transit_reason,
                                     r.prev_wagon_no,
                                     r.owner,
                                     r.owner_okpo,
                                     r.owner_local_code,
                                     r.home_station,
                                     r.threshold_sign,
                                     TO_NUMBER (r.lease_sign),
                                     TO_DATE (r.life_ext_date, 'YYYY-MM-DD HH24:MI:SS'),
                                     r.lessee,
                                     r.lessee_okpo,
                                     r.lessee_local_code,
                                     r.lease_home_station,
                                     TO_DATE (r.lease_end_date, 'YYYY-MM-DD HH24:MI:SS'),
                                     TO_DATE (r.service_life, 'YYYY-MM-DD HH24:MI:SS'),
                                     TO_NUMBER (r.body_material_code),
                                     r.body_material_name,
                                     TO_NUMBER (r.body_volume),
                                     r.clearance,
                                     r.air_dist_type,
                                     r.automode,
                                     r.auto_lever,
                                     r.brake_type,
                                     r.coupler_type,
                                     r.bogie_model,
                                     r.shock_absorber,
                                     TO_NUMBER (r.life_ext_sign),
                                     TO_NUMBER (r.boiler_caliber),
                                     r.drain_device,
                                     r.lever_gear,
                                     r.wagon_model_code,
                                     TO_NUMBER (r.repair_by_mileage),
                                     r.proxy_operator,
                                     r.proxy_operator_okpo,
                                     r.wagon_type_code2,
                                     r.wagon_type_cond,
                                     TO_NUMBER (r.axles_count),
                                     r.exclude_depot,
                                     r.exclude_reason,
                                     TO_NUMBER (r.days_to_repair),
                                     TO_NUMBER (r.days_no_oper),
                                     TO_NUMBER (r.days_no_move)
                                from XMLTABLE (
                                         '/rjd/rjd_row'
                                         passing xmltype (p_data)
                                         columns report_dt                varchar2 (30) path '@report_dt',
                                                 type_reference           varchar2 (50) path '@type_reference',
                                                 wagon_no                 varchar2 (4000) path '@wagon_no',
                                                 waybill_no               varchar2 (4000) path '@waybill_no',
                                                 wagon_type_code          varchar2 (4000) path '@wagon_type_code',
                                                 owner_admin              varchar2 (4000) path '@owner_admin',
                                                 trip_start_dt            varchar2 (4000) path '@trip_start_dt',
                                                 depart_state             varchar2 (4000) path '@depart_state',
                                                 depart_road              varchar2 (4000) path '@depart_road',
                                                 depart_station           varchar2 (4000) path '@depart_station',
                                                 trip_end_dt              varchar2 (4000) path '@trip_end_dt',
                                                 dest_state               varchar2 (4000) path '@dest_state',
                                                 dest_road                varchar2 (4000) path '@dest_road',
                                                 dest_station             varchar2 (4000) path '@dest_station',
                                                 consignor_tgnl           varchar2 (4000) path '@consignor_tgnl',
                                                 consignor                varchar2 (4000) path '@consignor',
                                                 consignor_okpo           varchar2 (4000) path '@consignor_okpo',
                                                 consignor_name           varchar2 (4000) path '@consignor_name',
                                                 consignee_tgnl           varchar2 (4000) path '@consignee_tgnl',
                                                 consignee                varchar2 (4000) path '@consignee',
                                                 consignee_okpo           varchar2 (4000) path '@consignee_okpo',
                                                 consignee_name           varchar2 (4000) path '@consignee_name',
                                                 cargo_name               varchar2 (4000) path '@cargo_name',
                                                 cargo_gng                varchar2 (4000) path '@cargo_gng',
                                                 cargo_weight_kg          varchar2 (4000) path '@cargo_weight_kg',
                                                 mileage_loaded_km        varchar2 (4000) path '@mileage_loaded_km',
                                                 mileage_empty_km         varchar2 (4000) path '@mileage_empty_km',
                                                 mileage_total_km         varchar2 (4000) path '@mileage_total_km',
                                                 mileage_norm_km          varchar2 (4000) path '@mileage_norm_km',
                                                 mileage_remain_km        varchar2 (4000) path '@mileage_remain_km',
                                                 mileage_sign             varchar2 (4000) path '@mileage_sign',
                                                 special_marks            varchar2 (4000) path '@special_marks',
                                                 prev_cargo               varchar2 (4000) path '@prev_cargo',
                                                 oper_station             varchar2 (4000) path '@oper_station',
                                                 oper_road                varchar2 (4000) path '@oper_road',
                                                 operation                varchar2 (4000) path '@operation',
                                                 oper_mnemonic            varchar2 (4000) path '@oper_mnemonic',
                                                 oper_dt                  varchar2 (4000) path '@oper_dt',
                                                 park_type                varchar2 (4000) path '@park_type',
                                                 handover_road            varchar2 (4000) path '@handover_road',
                                                 receive_road             varchar2 (4000) path '@receive_road',
                                                 train_index              varchar2 (4000) path '@train_index',
                                                 train_no                 varchar2 (4000) path '@train_no',
                                                 wagon_in_train           varchar2 (4000) path '@wagon_in_train',
                                                 park_no                  varchar2 (4000) path '@park_no',
                                                 track_no                 varchar2 (4000) path '@track_no',
                                                 seals_count              varchar2 (4000) path '@seals_count',
                                                 loaded_containers        varchar2 (4000) path '@loaded_containers',
                                                 empty_containers         varchar2 (4000) path '@empty_containers',
                                                 container_nos            varchar2 (4000) path '@container_nos',
                                                 norm_delivery_dt         varchar2 (4000) path '@norm_delivery_dt',
                                                 dist_passed_km           varchar2 (4000) path '@dist_passed_km',
                                                 dist_remain_km           varchar2 (4000) path '@dist_remain_km',
                                                 dist_total_km            varchar2 (4000) path '@dist_total_km',
                                                 idle_time_hhmmss         varchar2 (4000) path '@idle_time_hhmmss',
                                                 idle_time_days           varchar2 (4000) path '@idle_time_days',
                                                 extra_waybill_no         varchar2 (4000) path '@extra_waybill_no',
                                                 extra_send_id            varchar2 (4000) path '@extra_send_id',
                                                 asoup_depart_dt          varchar2 (4000) path '@asoup_depart_dt',
                                                 asoup_arrive_dt          varchar2 (4000) path '@asoup_arrive_dt',
                                                 send_id                  varchar2 (4000) path '@send_id',
                                                 waybill_id               varchar2 (4000) path '@waybill_id',
                                                 wagon_no2                varchar2 (4000) path '@wagon_no2',
                                                 quality_sign             varchar2 (4000) path '@quality_sign',
                                                 state_assign_dt          varchar2 (4000) path '@state_assign_dt',
                                                 wagon_state              varchar2 (4000) path '@wagon_state',
                                                 state_reason             varchar2 (4000) path '@state_reason',
                                                 state_station            varchar2 (4000) path '@state_station',
                                                 reg_date                 varchar2 (4000) path '@reg_date',
                                                 build_date               varchar2 (4000) path '@build_date',
                                                 next_repair_dt           varchar2 (4000) path '@next_repair_dt',
                                                 next_repair_type         varchar2 (4000) path '@next_repair_type',
                                                 factory_no               varchar2 (4000) path '@factory_no',
                                                 manufacturer             varchar2 (4000) path '@manufacturer',
                                                 wagon_type_name          varchar2 (4000) path '@wagon_type_name',
                                                 wagon_model              varchar2 (4000) path '@wagon_model',
                                                 tare_weight              varchar2 (4000) path '@tare_weight',
                                                 load_capacity            varchar2 (4000) path '@load_capacity',
                                                 length_mm                varchar2 (4000) path '@length_mm',
                                                 last_cap_repair_depot    varchar2 (4000) path '@last_cap_repair_depot',
                                                 last_cap_repair_dt       varchar2 (4000) path '@last_cap_repair_dt',
                                                 last_dep_repair_depot    varchar2 (4000) path '@last_dep_repair_depot',
                                                 last_dep_repair_dt       varchar2 (4000) path '@last_dep_repair_dt',
                                                 home_road                varchar2 (4000) path '@home_road',
                                                 home_depot               varchar2 (4000) path '@home_depot',
                                                 exclude_date             varchar2 (4000) path '@exclude_date',
                                                 no_transit_reason        varchar2 (4000) path '@no_transit_reason',
                                                 prev_wagon_no            varchar2 (4000) path '@prev_wagon_no',
                                                 owner                    varchar2 (4000) path '@owner',
                                                 owner_okpo               varchar2 (4000) path '@owner_okpo',
                                                 owner_local_code         varchar2 (4000) path '@owner_local_code',
                                                 home_station             varchar2 (4000) path '@home_station',
                                                 threshold_sign           varchar2 (4000) path '@threshold_sign',
                                                 lease_sign               varchar2 (4000) path '@lease_sign',
                                                 life_ext_date            varchar2 (4000) path '@life_ext_date',
                                                 lessee                   varchar2 (4000) path '@lessee',
                                                 lessee_okpo              varchar2 (4000) path '@lessee_okpo',
                                                 lessee_local_code        varchar2 (4000) path '@lessee_local_code',
                                                 lease_home_station       varchar2 (4000) path '@lease_home_station',
                                                 lease_end_date           varchar2 (4000) path '@lease_end_date',
                                                 service_life             varchar2 (4000) path '@service_life',
                                                 body_material_code       varchar2 (4000) path '@body_material_code',
                                                 body_material_name       varchar2 (4000) path '@body_material_name',
                                                 body_volume              varchar2 (4000) path '@body_volume',
                                                 clearance                varchar2 (4000) path '@clearance',
                                                 air_dist_type            varchar2 (4000) path '@air_dist_type',
                                                 automode                 varchar2 (4000) path '@automode',
                                                 auto_lever               varchar2 (4000) path '@auto_lever',
                                                 brake_type               varchar2 (4000) path '@brake_type',
                                                 coupler_type             varchar2 (4000) path '@coupler_type',
                                                 bogie_model              varchar2 (4000) path '@bogie_model',
                                                 shock_absorber           varchar2 (4000) path '@shock_absorber',
                                                 life_ext_sign            varchar2 (4000) path '@life_ext_sign',
                                                 boiler_caliber           varchar2 (4000) path '@boiler_caliber',
                                                 drain_device             varchar2 (4000) path '@drain_device',
                                                 lever_gear               varchar2 (4000) path '@lever_gear',
                                                 wagon_model_code         varchar2 (4000) path '@wagon_model_code',
                                                 repair_by_mileage        varchar2 (4000) path '@repair_by_mileage',
                                                 proxy_operator           varchar2 (4000) path '@proxy_operator',
                                                 proxy_operator_okpo      varchar2 (4000) path '@proxy_operator_okpo',
                                                 wagon_type_code2         varchar2 (4000) path '@wagon_type_code2',
                                                 wagon_type_cond          varchar2 (4000) path '@wagon_type_cond',
                                                 axles_count              varchar2 (4000) path '@axles_count',
                                                 exclude_depot            varchar2 (4000) path '@exclude_depot',
                                                 exclude_reason           varchar2 (4000) path '@exclude_reason',
                                                 days_to_repair           varchar2 (4000) path '@days_to_repair',
                                                 days_no_oper             varchar2 (4000) path '@days_no_oper',
                                                 days_no_move             varchar2 (4000) path '@days_no_move')
                                     r;

        for c
            in (           select distinct
                                  TO_DATE (r.report_dt, 'YYYY-MM-DD HH24:MI:SS')
                                      as report_dt,
                                  TRIM (r.type_reference)
                                      as type_reference
                             from XMLTABLE (
                                      '/rjd/rjd_row'
                                      passing xmltype (p_data)
                                      columns report_dt         varchar2 (30) path '@report_dt',
                                              type_reference    varchar2 (50) path '@type_reference')
                                  r)
        loop
            archive_rjd_movement (c.report_dt, c.type_reference);
        end loop;

        for c
            in (           select distinct
                                  TO_DATE (r.report_dt, 'YYYY-MM-DD HH24:MI:SS')
                                      as report_dt,
                                  TRIM (r.type_reference)
                                      as type_reference
                             from XMLTABLE (
                                      '/rjd/rjd_row'
                                      passing xmltype (p_data)
                                      columns report_dt         varchar2 (30) path '@report_dt',
                                              type_reference    varchar2 (50) path '@type_reference')
                                  r
                            where TRIM (r.type_reference) is not null)
        loop
            select MAX (report_dt)
              into l_latest_report_dt
              from xx_dislocation_rjd
             where     report_dt >= TRUNC (c.report_dt)
                   and report_dt < TRUNC (c.report_dt) + 1
                   and type_reference = c.type_reference
                   and organization_id = get_organization_id ();

            if c.report_dt < l_latest_report_dt
            then
                delete xx_dislocation_rjd
                 where     report_dt = c.report_dt
                       and type_reference = c.type_reference
                       and organization_id = get_organization_id ();
            end if;
        end loop;

        commit;
    end parse_rjd_excel;

    procedure delete_reference (p_report_dt        in date,
                                p_type_reference   in varchar2)
    is
    begin
        if p_report_dt is null or TRIM (p_type_reference) is null
        then
            raise_application_error (-20030,
                                     'Не заданы параметры удаляемой справки');
        end if;

        delete from xx_dislocation_rjd
              where     organization_id = get_organization_id ()
                    and report_dt = p_report_dt
                    and type_reference = TRIM (p_type_reference);
    end delete_reference;

    procedure get_rjd_excel_file (p_directory   in     varchar2,
                                  p_file_name      out varchar2,
                                  p_file_data      out blob)
    is
        l_bfile         bfile;
        l_dest_offset   integer := 1;
        l_src_offset    integer := 1;
    begin
        p_file_name := null;
        p_file_data := null;

        /*
        apps.xx_get_dir_list (p_directory);

        for f
            in (  select filename
                    from xx_dir_list
                   where     LOWER (filename) like '%.xlsx'
                         and LOWER (filename) not like '%done%'
                order by filename)
        loop
            p_file_name := f.filename;
            exit;
        end loop;*/

        if p_file_name is null
        then
            return;
        end if;

        l_bfile := BFILENAME (p_directory, p_file_name);

        if DBMS_LOB.fileexists (l_bfile) = 1
        then
            DBMS_LOB.createtemporary (p_file_data, false);
            DBMS_LOB.fileopen (l_bfile, DBMS_LOB.file_readonly);
            DBMS_LOB.loadblobfromfile (dest_lob      => p_file_data,
                                       src_bfile     => l_bfile,
                                       amount        => DBMS_LOB.lobmaxsize,
                                       dest_offset   => l_dest_offset,
                                       src_offset    => l_src_offset);

            DBMS_LOB.fileclose (l_bfile);
        end if;
    --delete xx_dir_list;
    exception
        when others
        then
            if DBMS_LOB.fileisopen (l_bfile) = 1
            then
                DBMS_LOB.fileclose (l_bfile);
            end if;

            --delete xx_dir_list;

            raise;
    end get_rjd_excel_file;

    function stations (p_search   in varchar2 default null,
                       p_offset   in number default 0,
                       p_limit    in number default 50)
        return station_tab
        pipelined
    is
        l_row   station_rec;
    begin
        for station_row
            in (  select esr_code,
                         station_name,
                         latitude,
                         longitude
                    from (select s.esr_code,
                                 s.station_name,
                                 s.latitude,
                                 s.longitude,
                                 ROW_NUMBER ()
                                     over (
                                         order by
                                             NLSSORT (s.station_name,
                                                      'NLS_SORT=RUSSIAN'),
                                             s.esr_code)    as rn
                            from xx_rjd_stations s
                           where    p_search is null
                                 or UPPER (s.esr_code) like
                                        '%' || UPPER (TRIM (p_search)) || '%'
                                 or UPPER (s.station_name) like
                                        '%' || UPPER (TRIM (p_search)) || '%')
                   where     rn > NVL (p_offset, 0)
                         and rn <= NVL (p_offset, 0) + NVL (p_limit, 50)
                order by rn)
        loop
            l_row.esr_code := station_row.esr_code;
            l_row.station_name := station_row.station_name;
            l_row.latitude := station_row.latitude;
            l_row.longitude := station_row.longitude;
            pipe row (l_row);
        end loop;

        return;
    end stations;

    function stations_count (p_search in varchar2 default null)
        return number
    is
        l_count   number;
    begin
        select COUNT (*)
          into l_count
          from xx_rjd_stations
         where    p_search is null
               or UPPER (esr_code) like '%' || UPPER (TRIM (p_search)) || '%'
               or UPPER (station_name) like
                      '%' || UPPER (TRIM (p_search)) || '%';

        return l_count;
    end stations_count;

    function station (p_esr_code in varchar2)
        return station_tab
        pipelined
    is
        l_row   station_rec;
    begin
        for station_row in (select esr_code,
                                   station_name,
                                   latitude,
                                   longitude
                              from xx_rjd_stations
                             where esr_code = TRIM (p_esr_code))
        loop
            l_row.esr_code := station_row.esr_code;
            l_row.station_name := station_row.station_name;
            l_row.latitude := station_row.latitude;
            l_row.longitude := station_row.longitude;
            pipe row (l_row);
        end loop;

        return;
    end station;

    function stations_no_coord
        return station_no_coord_tab
        pipelined
    is
        l_row   station_no_coord_rec;
    begin
        for station_row
            in (  select xdr.oper_station_esr_code
                             as esr_code,
                         MAX (NVL (rs.station_name, xdr.oper_station))
                             as station_name,
                         COUNT (*)
                             as wagon_count
                    from xx_dislocation_rjd xdr
                         left join xx_rjd_stations rs
                             on rs.esr_code = xdr.oper_station_esr_code
                   where     xdr.oper_station_esr_code is not null
                         and xdr.organization_id = get_organization_id ()
                         and xdr.type_reference in ('Подход', 'Отправка')
                         and xdr.report_dt =
                             (select MAX (latest_rows.report_dt)
                                from xx_dislocation_rjd latest_rows
                               where     latest_rows.type_reference =
                                         xdr.type_reference
                                     and latest_rows.organization_id =
                                         xdr.organization_id)
                         and (   rs.esr_code is null
                              or rs.latitude is null
                              or rs.longitude is null)
                group by xdr.oper_station_esr_code
                order by MAX (NVL (rs.station_name, xdr.oper_station)))
        loop
            l_row.esr_code := station_row.esr_code;
            l_row.station_name := station_row.station_name;
            l_row.wagon_count := station_row.wagon_count;
            pipe row (l_row);
        end loop;

        return;
    end stations_no_coord;

    procedure validate_station (p_esr_code       in varchar2,
                                p_station_name   in varchar2,
                                p_latitude       in number,
                                p_longitude      in number)
    is
    begin
        if TRIM (p_esr_code) is null
        then
            raise_application_error (-20001, 'Не указан код ЕСР');
        end if;

        if TRIM (p_station_name) is null
        then
            raise_application_error (-20002, 'Не указано название станции');
        end if;

        if p_latitude is not null and (p_latitude < -90 or p_latitude > 90)
        then
            raise_application_error (
                -20003,
                'Широта должна быть в диапазоне от -90 до 90');
        end if;

        if     p_longitude is not null
           and (p_longitude < -180 or p_longitude > 180)
        then
            raise_application_error (
                -20004,
                'Долгота должна быть в диапазоне от -180 до 180');
        end if;
    end validate_station;

    procedure save_station (p_esr_code       in varchar2,
                            p_station_name   in varchar2,
                            p_latitude       in number,
                            p_longitude      in number)
    is
    begin
        validate_station (p_esr_code,
                          p_station_name,
                          p_latitude,
                          p_longitude);

        merge into xx_rjd_stations s
             using (select TRIM (p_esr_code)         as esr_code,
                           TRIM (p_station_name)     as station_name,
                           p_latitude                as latitude,
                           p_longitude               as longitude
                      from DUAL) src
                on (s.esr_code = src.esr_code)
        when matched
        then
            update set
                s.station_name = src.station_name,
                s.latitude = src.latitude,
                s.longitude = src.longitude
        when not matched
        then
            insert     (esr_code,
                        station_name,
                        latitude,
                        longitude)
                values (src.esr_code,
                        src.station_name,
                        src.latitude,
                        src.longitude);
    end save_station;

    procedure delete_station (p_esr_code in varchar2)
    is
    begin
        if TRIM (p_esr_code) is null
        then
            raise_application_error (-20005, 'Не указан код ЕСР');
        end if;

        delete xx_rjd_stations
         where esr_code = TRIM (p_esr_code);
    end delete_station;

    procedure save_idle_control (p_id_control        in number,
                                 p_created_by        in number,
                                 p_car_number        in varchar2,
                                 p_start_date        in date,
                                 p_end_date          in date,
                                 p_note              in varchar2,
                                 p_is_excluded       in varchar2,
                                 p_idle_reasons_id   in number)
    is
        l_id        number := p_id_control;
        l_overlap   number;
    begin
        if    p_car_number is null
           or p_start_date is null
           or p_idle_reasons_id is null
        then
            raise_application_error (-20001,
                                     'Не заполнены обязательные поля');
        end if;

        if p_end_date is not null and p_end_date < p_start_date
        then
            raise_application_error (-20002,
                                     'Дата окончания раньше даты начала');
        end if;

        select COUNT (*)
          into l_overlap
          from xx_etw.xx_disl_idle_control
         where     UPPER (TRIM (car_number)) = UPPER (TRIM (p_car_number))
               and NVL (deleted, 'N') = 'N'
               and (p_id_control is null or id_control <> p_id_control)
               and start_date <= NVL (p_end_date, date '9999-12-31')
               and NVL (end_date, date '9999-12-31') >= p_start_date;

        if l_overlap > 0
        then
            raise_application_error (
                -20003,
                'Для вагона уже есть пересекающийся период');
        end if;

        if l_id is null
        then
            select xx_etw.xx_disl_idle_control_seq.NEXTVAL
              into l_id
              from DUAL;

            insert into xx_etw.xx_disl_idle_control (id_control,
                                                     created_by,
                                                     creation_date,
                                                     last_updated_by,
                                                     last_update_date,
                                                     car_number,
                                                     is_excluded,
                                                     note,
                                                     start_date,
                                                     end_date,
                                                     deleted,
                                                     idle_reasons_id)
                 values (l_id,
                         p_created_by,
                         SYSDATE,
                         p_created_by,
                         SYSDATE,
                         TRIM (p_car_number),
                         NVL (UPPER (p_is_excluded), 'N'),
                         p_note,
                         p_start_date,
                         p_end_date,
                         'N',
                         p_idle_reasons_id);
        else
            update xx_etw.xx_disl_idle_control
               set last_updated_by = p_created_by,
                   last_update_date = SYSDATE,
                   car_number = TRIM (p_car_number),
                   is_excluded = NVL (UPPER (p_is_excluded), 'N'),
                   note = p_note,
                   start_date = p_start_date,
                   end_date = p_end_date,
                   idle_reasons_id = p_idle_reasons_id
             where id_control = l_id and NVL (deleted, 'N') = 'N';
        end if;
    end save_idle_control;

    procedure delete_idle_control (p_id_control   in number,
                                   p_deleted_by   in number)
    is
    begin
        update xx_etw.xx_disl_idle_control
           set deleted = 'Y',
               deleted_by = p_deleted_by,
               deleted_date = SYSDATE,
               last_updated_by = p_deleted_by,
               last_update_date = SYSDATE
         where id_control = p_id_control and NVL (deleted, 'N') = 'N';
    end delete_idle_control;

    procedure save_idle_reason (p_id      in number,
                                p_name    in varchar2,
                                p_descr   in varchar2)
    is
        l_id      number := p_id;
        l_count   number;
    begin
        if TRIM (p_name) is null
        then
            raise_application_error (-20001, 'Укажите название причины');
        end if;

        select COUNT (*)
          into l_count
          from xx_etw.xx_disl_idle_reasons
         where     UPPER (TRIM (name)) = UPPER (TRIM (p_name))
               and (p_id is null or id <> p_id);

        if l_count > 0
        then
            raise_application_error (
                -20002,
                'Причина с таким названием уже существует');
        end if;

        if l_id is null
        then
            select xx_etw.xx_disl_idle_reasons_seq.NEXTVAL
              into l_id
              from DUAL;

            insert into xx_etw.xx_disl_idle_reasons (id, name, descr)
                 values (l_id, TRIM (p_name), TRIM (p_descr));
        else
            update xx_etw.xx_disl_idle_reasons
               set name = TRIM (p_name), descr = TRIM (p_descr)
             where id = l_id;
        end if;
    end save_idle_reason;

    procedure delete_idle_reason (p_id in number)
    is
        l_count   number;
    begin
        select COUNT (*)
          into l_count
          from xx_etw.xx_disl_idle_control
         where idle_reasons_id = p_id and NVL (deleted, 'N') = 'N';

        if l_count > 0
        then
            raise_application_error (
                -20003,
                'Причина используется в контроле простоев');
        end if;

        delete from xx_etw.xx_disl_idle_reasons
              where id = p_id;
    end delete_idle_reason;


    procedure mail_start
    is
    begin
        --apps.xx_mtf_send_mail_pkg.attachments := apps.xx_mtf_send_mail_pkg.attach_list ();
        null;
    end mail_start;

    procedure mail_add_file (p_file        in blob,
                             p_file_name   in varchar2,
                             p_mime_type   in varchar2)
    is
    begin
        /*apps.xx_mtf_send_mail_pkg.add_attachment (
            p_blob          => p_file,
            p_filename      => p_file_name,
            p_attach_mime   => p_mime_type);*/
        null;
    end mail_add_file;

    procedure mail_send (p_sender      in varchar2,
                         p_recipient   in varchar2,
                         p_subject     in varchar2,
                         p_text        in clob)
    is
        l_recipient   varchar2 (240) := 'Rustam.Bekmansurov@ruschem.ru';
    begin
        /*apps.xx_mtf_send_mail_pkg.send_mail (p_sender      => p_sender,
                                             p_recipient   => l_recipient,--p_recipient,
                                             p_subject     => p_subject,
                                             p_text_clob   => p_text);*/
        null;
    end mail_send;

    -- Формирует общий фильтр SELECT при подготовке запроса Oracle VPD.
    function data_scope_predicate (p_schema varchar2, p_object varchar2)
        return varchar2
    is
        l_user    number;
        l_super   number;
        l_rules   varchar2 (12000);
        l_match   varchar2 (2000);
    begin
        l_user := TO_NUMBER (SYS_CONTEXT ('USERENV', 'CLIENT_INFO'));

        if l_user is null or l_user <= 0
        then
            return '1=0';
        end if;

        select COUNT (*)
          into l_super
          from xx_rjd_user_roles  ur
               join xx_rjd_roles r on r.id = ur.role_id
               join xx_rjd_users u on u.id = ur.user_id
         where ur.user_id = l_user and r.code = 'SUPER' and u.is_active = 1;

        if l_super > 0
        then
            return '1=1';
        end if;

        -- Переименование исключает совпадение с колонками внешнего запроса.
        l_rules :=
               'select r.organization_id rule_org, r.cargo_code rule_cargo,
                        r.station_code rule_station, r.effect rule_effect
                   from xx_rjd_user_data_scopes us
                   join xx_rjd_data_scopes s on s.id = us.scope_id
                   join xx_rjd_data_scope_rules r on r.scope_id = s.id
                   join xx_rjd_users u on u.id = us.user_id
                  where us.user_id = '
            || TO_CHAR (l_user, 'FM99999999999999999999')
            || '
                    and u.is_active = 1 and s.is_active = 1
                    and (s.start_date is null or s.start_date < trunc(sysdate) + 1)
                    and (s.end_date is null or s.end_date >= trunc(sysdate))';
        l_match :=
            q'[rule_org = organization_id
                   and (rule_cargo is null or rule_cargo =
                        rtrim(regexp_substr(cargo_name, '[0-9]+\)$'), ')'))
                   and (rule_station is null or rule_station =
                        rtrim(regexp_substr(dest_station, '[0-9]+\)$'), ')'))]';
        return    'exists (select 1 from ('
               || l_rules
               || ') allowed_rules
                       where rule_effect = ''ALLOW'' and '
               || l_match
               || ')
              and not exists (select 1 from ('
               || l_rules
               || ') denied_rules
                       where rule_effect = ''DENY'' and '
               || l_match
               || ')';
    end data_scope_predicate;
end xx_rjd_dislocation_new_pkg;
/
