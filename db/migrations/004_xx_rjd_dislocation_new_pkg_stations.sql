-- =====================================================================
--  Справочник станций РЖД в основном package xx_rjd_dislocation_new_pkg.
--  PHP читает справочник только через:
--    SELECT * FROM TABLE(xx_rjd_dislocation_new_pkg.stations_pipe(:p_search))
-- =====================================================================

BEGIN
  EXECUTE IMMEDIATE q'[
    CREATE TABLE xx_rjd_stations (
      esr_code      VARCHAR2(20)  NOT NULL,
      station_name  VARCHAR2(255) NOT NULL,
      latitude      NUMBER,
      longitude     NUMBER,
      CONSTRAINT pk_xx_rjd_stations PRIMARY KEY (esr_code)
    )
  ]';
EXCEPTION
  WHEN OTHERS THEN
    IF SQLCODE != -955 THEN
      RAISE;
    END IF;
END;
/

DECLARE
  l_cnt NUMBER;
BEGIN
  SELECT COUNT(*)
    INTO l_cnt
    FROM user_constraints c
    JOIN user_cons_columns cc
      ON cc.constraint_name = c.constraint_name
   WHERE c.table_name = 'XX_RJD_STATIONS'
     AND cc.column_name = 'ESR_CODE'
     AND c.constraint_type IN ('P', 'U');

  IF l_cnt = 0 THEN
    EXECUTE IMMEDIATE 'ALTER TABLE xx_rjd_stations ADD CONSTRAINT pk_xx_rjd_stations PRIMARY KEY (esr_code)';
  END IF;
END;
/

create or replace package xx_rjd_dislocation_new_pkg as
    /******************************************************************************
    NAME:  xx_etw.xx_rjd_dislocation_new_pkg
    PURPOSE:   Метафракс: Дислокация РЖД (справка из кабинета)
    REVISIONS:
    Ver        Date        Author           Description
    ---------  ----------  ---------------  ------------------------------------
    1.0        15.06.2026  BekmansurovRR    1. Created this package.
 ******************************************************************************/
   function fnc_mapping_wag_type (
      p_wag_name in varchar2
   ) return varchar2;

   function fnc_get_downtime_wagon (
      p_downtime in number,
      p_type     in varchar2 default 'name'
   ) return varchar2;

   function fnc_get_state_wagon (
      p_weight in number
   ) return varchar2;

   function set_kpi_label (
      p_kpi_id in number,
      p_date   in date default null
   ) return varchar2;

   function fnc_check_kpi (
      p_kpi_id      in number,
      p_disl_rjd_id in number
   ) return number;

   function fnc_get_kpi_trend_pct (
      p_id in number
   ) return varchar2;

   function fnc_get_kpi_trend_dir (
      p_id in number
   ) return varchar2;

   function get_kpi_row (
      p_kpi_id in number
   ) return xx_etw.t_xx_rjd_kpi_tab
      pipelined;
   function get_kpi_where (
      p_kpi_id in number
   ) return varchar2;

   procedure parse_rjd_excel (
      p_data in clob
   );

   procedure get_rjd_excel_file (
      p_directory      in varchar2,
      p_file_name      out varchar2,
      p_file_data      out blob
   );
   type station_rec is record (
      esr_code     varchar2(20),
      station_name varchar2(255),
      latitude     number,
      longitude    number
   );

   type station_tab is table of station_rec;

   function stations_pipe (
      p_search in varchar2 default null,
      p_offset in number default 0,
      p_limit  in number default 50
   ) return station_tab
      pipelined;

   function stations_count (
      p_search in varchar2 default null
   ) return number;

   procedure save_station (
      p_esr_code     in varchar2,
      p_station_name in varchar2,
      p_latitude     in number,
      p_longitude    in number
   );

   procedure delete_station (
      p_esr_code in varchar2
   );
end xx_rjd_dislocation_new_pkg;
/

create or replace package body xx_rjd_dislocation_new_pkg as
    /******************************************************************************
        NAME:  xx_etw.xx_rjd_dislocation_new_pkg
        PURPOSE:   Метафракс: Дислокация РЖД (справка из кабинета)
        REVISIONS:
        Ver        Date        Author           Description
        ---------  ----------  ---------------  ------------------------------------
        1.0        15.06.2026  BekmansurovRR    1. Created this package.
     ******************************************************************************/
   function fnc_mapping_wag_type (
      p_wag_name in varchar2
   ) return varchar2 is
      v_wag_name_up varchar2(4000);
   begin
      v_wag_name_up := upper(p_wag_name);
      case
         when v_wag_name_up like '%ЦИСТЕРНЫ%' then
            return 'ЦС';
         when v_wag_name_up like '%КРЫТЫЕ%' then
            return 'КР';
         when v_wag_name_up like '%ПОЛУВАГОНЫ%' then
            return 'ПВ';
         when
            v_wag_name_up like '%ПЛАТФОРМЫ%'
            and v_wag_name_up not like '%ФИТИНГОВЫЕ%'
         then
            return 'ПЛ';
         when v_wag_name_up like '%ФИТИНГОВЫЕ%' then
            return 'ФТГ';
         when v_wag_name_up like '%ПРОЧИЕ%' then
            return 'ПР';
         when v_wag_name_up like '%МИНЕРАЛОВОЗЫ%' then
            return 'МВЗ';
         when v_wag_name_up like '%ЗЕРНОВОЗЫ%' then
            return 'ЗРВ';
         when v_wag_name_up like '%ЦЕМЕНТОВОЗЫ%' then
            return 'ЦМВ';
         else
            return p_wag_name;
      end case;
   end;

   function fnc_get_downtime_wagon (
      p_downtime in number,
      p_type     in varchar2 default 'name'
   ) return varchar2 is
      l_name     varchar2(150) := 'простой';
      l_order_by number := 0;
      l_downtime number;
   begin
      l_downtime := nvl(
         p_downtime,
         0
      );
      if l_downtime between 0 and 4 then
         l_name := l_name || ' до 5 сут.';
         l_order_by := 10;
      elsif l_downtime between 5 and 6 then
         l_name := l_name || ' от 5 до 7 сут.';
         l_order_by := 20;
      elsif l_downtime between 7 and 9 then
         l_name := l_name || ' от 7 до 10 сут.';
         l_order_by := 30;
      elsif l_downtime between 10 and 19 then
         l_name := l_name || ' от 10 до 20 сут.';
         l_order_by := 40;
      elsif l_downtime between 20 and 29 then
         l_name := l_name || ' от 20 до 30 сут.';
         l_order_by := 50;
      elsif l_downtime > 29 then
         l_name := l_name || ' от 30 и более сут.';
         l_order_by := 60;
      else
         l_name := l_name || ' *';
         l_order_by := 999;
      end if;

      if p_type = 'name' then
         return l_name;
      elsif p_type = 'order_by' then
         return to_char(l_order_by);
      else
         return l_name;
      end if;
   end;

   function fnc_get_state_wagon (
      p_weight in number
   ) return varchar2 is
   begin
      if nvl(
         p_weight,
         0
      ) = 0 then
         return 'пор.';
      else
         return 'гр.';
      end if;
   end;

    -- вынесена на уровень пакета, чтобы использовать в тренде
   procedure calculate_change (
      p_yesterday_value in number,
      p_today_value     in number,
      p_return          in varchar2 default 'percent',
      p_percent         out varchar2,
      p_direction       out varchar2
   ) is
      v_yesterday  number := nvl(
         p_yesterday_value,
         0
      );
      v_today      number := nvl(
         p_today_value,
         0
      );
      v_return_txt varchar2(150);
   begin
      if p_return = 'percent' then
         v_return_txt := '%';
      else
         v_return_txt := 'Вчера: ';
      end if;

      if
         v_yesterday = 0
         and v_today > 0
      then
         if p_return = 'percent' then
            p_percent := '100' || v_return_txt;
         else
            p_percent := '0';
         end if;

         p_direction := 'up';
      elsif
         v_yesterday != 0
         and v_today = 0
      then
         if p_return = 'percent' then
            p_percent := '100' || v_return_txt;
         else
            p_percent := to_char(v_yesterday);
         end if;

         p_direction := 'down';
      else
         if v_yesterday != 0 then
                --p_percent := 'Вчера:'||to_char(v_yesterday);
            if p_return = 'percent' then
               p_percent := to_char(round(
                  ((v_today - v_yesterday) / v_yesterday) * 100,
                  2
               ))
                            || v_return_txt;
            else
               p_percent := v_return_txt || to_char(v_yesterday);
            end if;
         else
            if p_return = 'percent' then
               p_percent := '0' || v_return_txt;
            else
               p_percent := to_char(0);
            end if;
         end if;

         if v_today > v_yesterday then
            p_direction := 'up';
         elsif v_today < v_yesterday then
            p_direction := 'down';
         else
            p_direction := '-';
         end if;
      end if;
   exception
      when others then
         p_percent := '0';
         p_direction := '';
   end calculate_change;

   -- Условие для каждой карточки
   function get_kpi_where (
      p_kpi_id in number
   ) return varchar2 is
      l_kpi xx_rjd_kpi_tbl_v%rowtype;
   begin
      select *
        into l_kpi
        from xx_rjd_kpi_tbl_v
       where id = p_kpi_id;

	    -- KPI для страницы "Отправление вагонов"
      if l_kpi.type = 'departue_kpi' then
         if l_kpi.code = 'eysk' then
            return 'UPPER(dest_station) LIKE UPPER(''%Ейск%'')';
         elsif l_kpi.code = 'zabaykalsk' then
            return 'UPPER(dest_station) LIKE UPPER(''%Забайкальск%'')';
         elsif l_kpi.code = 'luzskaya' then
            return 'UPPER(dest_station) LIKE UPPER(''%Лужская%'')';
         end if;

	    -- KPI для страницы "Дислокация/Дашборд"
      elsif l_kpi.type = 'dashboard_kpi' then
         if l_kpi.code = 'wag_cistern' then
            return 'xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code) = ''ЦС''';
         elsif l_kpi.code = 'wag_others' then
            return 'xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code) != ''ЦС''';
         elsif l_kpi.code = 'loaded_transit' then
            return 'dest_station NOT LIKE ''%УГЛ%'' AND dest_station != oper_station AND cargo_weight_kg > 0';
         elsif l_kpi.code = 'comming_to_ugl' then
            return 'UPPER(dest_station) LIKE ''%УГЛ%'' AND oper_station != dest_station';
         elsif l_kpi.code = 'arrived_today_ugl' then
            return 'UPPER(dest_station) LIKE ''%УГЛ%'' AND oper_station = dest_station AND TRUNC(oper_dt) = TRUNC(SYSDATE)';
         end if;

	    -- KPI для страницы "Подход вагонов"
      elsif l_kpi.type = 'approach_kpi' then
         if l_kpi.code = 'eysk' then
            return 'UPPER(depart_station) LIKE UPPER(''%Ейск%'')';
         elsif l_kpi.code = 'zabaykalsk' then
            return 'UPPER(depart_station) LIKE UPPER(''%Забайкальск%'')';
         elsif l_kpi.code = 'luzskaya' then
            return 'UPPER(depart_station) LIKE UPPER(''%Лужская%'')';
         elsif l_kpi.code = 'delivery_time' then
            return 'oper_dt > norm_delivery_dt AND UPPER(oper_station) NOT LIKE UPPER(''%Угле%'') AND type_reference = ''Подход'''
            ;
         end if;
      end if;

      return '1=0'; -- неизвестный KPI
   end get_kpi_where;

   function fnc_check_kpi (
      p_kpi_id      in number,
      p_disl_rjd_id in number
   ) return number is
      l_dummy number;
   begin
      execute immediate 'SELECT 1 FROM xx_dislocation_rjd WHERE id = :id AND ' || get_kpi_where(p_kpi_id)
        into l_dummy
         using p_disl_rjd_id;
      return 1;
   exception
      when no_data_found then
         return 0;
   end fnc_check_kpi;

   -- Собираем данные для каждой карточки (текущие данные и тренд)
   function set_kpi_label (
      p_kpi_id in number,
      p_date   in date default null
   ) return varchar2 is
      l_kpi_where varchar2(2000);
      l_sql       varchar2(4000);
      l_value     number;
   begin
      l_kpi_where := get_kpi_where(p_kpi_id);

	    -- Неизвестный KPI
      if l_kpi_where = '1=0' then
         return '';
      end if;
      if p_date is null then
	        -- Текущие данные (последний report_dt по каждому type_reference)
         l_sql := 'SELECT COUNT(*) FROM xx_dislocation_rjd'
                  || ' WHERE (report_dt, type_reference) IN ('
                  || '   SELECT MAX(report_dt), type_reference'
                  || '   FROM xx_dislocation_rjd GROUP BY type_reference'
                  || ' ) AND '
                  || l_kpi_where;
         execute immediate l_sql
           into l_value;
      else
	        -- Данные за конкретную дату (диапазон вместо TRUNC = TRUNC для индекса)
           -- Последняя максимальная справка за вчера
         l_sql := 'SELECT COUNT(*) FROM xx_dislocation_rjd'
                  || ' WHERE (report_dt, type_reference) IN ('
                  || '   SELECT MAX(report_dt), type_reference'
                  || '     FROM xx_dislocation_rjd'
                  || '    WHERE report_dt >= TRUNC(:d1)'
                  || '      AND report_dt <  TRUNC(:d2) + 1'
                  || '    GROUP BY type_reference'
                  || ' ) AND '
                  || l_kpi_where;
         execute immediate l_sql
           into l_value
            using p_date,p_date;
      end if;

      return to_char(l_value);
   exception
      when others then
         return '';
   end set_kpi_label;
    -- Изменение по сравнению с прошлым днем/есяцем 
   procedure prv_kpi_trend (
      p_id      in number,
      p_pct_out out varchar2,
      p_dir_out out varchar2
   ) is
      v_period      varchar2(10);
      v_metric_type varchar2(20);
      v_current     number := 0;
      v_previous    number := 0;
   begin
      select nvl(
         trend_period,
         'Day'
      ),
             nvl(
                trend_unit,
                'шт.'
             )
        into
         v_period,
         v_metric_type
        from xx_rjd_kpi_tbl_v
       where id = p_id;

      v_current := to_number ( nvl(
         set_kpi_label(p_id),
         '0'
      ) );
      if v_period = 'Day' then
         v_previous := to_number ( nvl(
            set_kpi_label(
               p_id,
               sysdate - 1
            ),
            '0'
         ) );
      elsif v_period = 'Month' then
         v_previous := to_number ( nvl(
            set_kpi_label(
               p_id,
               add_months(
                  sysdate,
                  -1
               )
            ),
            '0'
         ) );
      end if;

      calculate_change(
         p_yesterday_value => v_previous,
         p_today_value     => v_current,
         p_return          => v_metric_type,
         p_percent         => p_pct_out,
         p_direction       => p_dir_out
      );
   exception
      when others then
         p_pct_out := null;
         p_dir_out := '';
   end prv_kpi_trend;

   function fnc_get_kpi_trend_pct (
      p_id in number
   ) return varchar2 is
      v_pct varchar2(100);
      v_dir varchar2(10);
   begin
      prv_kpi_trend(
         p_id,
         v_pct,
         v_dir
      );
      return v_pct;
   exception
      when others then
         return null;
   end fnc_get_kpi_trend_pct;

   function fnc_get_kpi_trend_dir (
      p_id in number
   ) return varchar2 is
      v_pct varchar2(100);
      v_dir varchar2(10);
   begin
      prv_kpi_trend(
         p_id,
         v_pct,
         v_dir
      );
      return v_dir;
   exception
      when others then
         return '';
   end fnc_get_kpi_trend_dir;

   function get_kpi_row (
      p_kpi_id in number
   ) return xx_etw.t_xx_rjd_kpi_tab
      pipelined
   is
      v_value varchar2(200);
      v_pct   varchar2(50);
      v_dir   varchar2(10);
   begin
      v_value := set_kpi_label(p_kpi_id);
      prv_kpi_trend(
         p_kpi_id,
         v_pct,
         v_dir
      );
      pipe row ( xx_etw.t_xx_rjd_kpi_row(
         v_value,
         v_pct,
         v_dir
      ) );
      return;
   end get_kpi_row;

   procedure parse_rjd_excel (
      p_data in clob
   ) is
   begin
      for c in (
         select distinct trunc(to_date(r.report_dt,
      'YYYY-MM-DD HH24:MI:SS')) as report_dt,
                         r.type_reference
           from xmltable ( '/rjd/rjd_row'
               passing xmltype(p_data)
            columns
               report_dt varchar2(30) path '@report_dt',
               type_reference varchar2(50) path '@type_reference'
         ) r
      ) loop
         delete xx_dislocation_rjd
          where report_dt >= c.report_dt
            and report_dt < c.report_dt + 1
            and type_reference = c.type_reference;
      end loop;

      insert into xx_dislocation_rjd (
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
         days_no_move
      )
         select to_date(r.report_dt,
        'YYYY-MM-DD HH24:MI:SS'),
                r.type_reference,
                r.wagon_no,
                r.waybill_no,
                r.wagon_type_code,
                r.owner_admin,
                to_date(r.trip_start_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.depart_state,
                r.depart_road,
                r.depart_station,
                to_date(r.trip_end_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
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
                to_number(r.cargo_weight_kg),
                to_number(r.mileage_loaded_km),
                to_number(r.mileage_empty_km),
                to_number(r.mileage_total_km),
                to_number(r.mileage_norm_km),
                to_number(r.mileage_remain_km),
                r.mileage_sign,
                r.special_marks,
                r.prev_cargo,
                r.oper_station,
                r.oper_road,
                r.operation,
                r.oper_mnemonic,
                to_date(r.oper_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.park_type,
                r.handover_road,
                r.receive_road,
                r.train_index,
                r.train_no,
                to_number(r.wagon_in_train),
                r.park_no,
                r.track_no,
                to_number(r.seals_count),
                to_number(r.loaded_containers),
                to_number(r.empty_containers),
                r.container_nos,
                to_date(r.norm_delivery_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                to_number(r.dist_passed_km),
                to_number(r.dist_remain_km),
                to_number(r.dist_total_km),
                r.idle_time_hhmmss,
                to_number(r.idle_time_days),
                r.extra_waybill_no,
                r.extra_send_id,
                to_date(r.asoup_depart_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                to_date(r.asoup_arrive_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.send_id,
                r.waybill_id,
                r.wagon_no2,
                r.quality_sign,
                to_date(r.state_assign_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.wagon_state,
                r.state_reason,
                r.state_station,
                to_date(r.reg_date,
                        'YYYY-MM-DD HH24:MI:SS'),
                to_date(r.build_date,
                        'YYYY-MM-DD HH24:MI:SS'),
                to_date(r.next_repair_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.next_repair_type,
                r.factory_no,
                r.manufacturer,
                r.wagon_type_name,
                r.wagon_model,
                to_number(r.tare_weight),
                to_number(r.load_capacity),
                to_number(r.length_mm),
                r.last_cap_repair_depot,
                to_date(r.last_cap_repair_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.last_dep_repair_depot,
                to_date(r.last_dep_repair_dt,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.home_road,
                r.home_depot,
                to_date(r.exclude_date,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.no_transit_reason,
                r.prev_wagon_no,
                r.owner,
                r.owner_okpo,
                r.owner_local_code,
                r.home_station,
                r.threshold_sign,
                to_number(r.lease_sign),
                to_date(r.life_ext_date,
                        'YYYY-MM-DD HH24:MI:SS'),
                r.lessee,
                r.lessee_okpo,
                r.lessee_local_code,
                r.lease_home_station,
                to_date(r.lease_end_date,
                        'YYYY-MM-DD HH24:MI:SS'),
                to_date(r.service_life,
                        'YYYY-MM-DD HH24:MI:SS'),
                to_number(r.body_material_code),
                r.body_material_name,
                to_number(r.body_volume),
                r.clearance,
                r.air_dist_type,
                r.automode,
                r.auto_lever,
                r.brake_type,
                r.coupler_type,
                r.bogie_model,
                r.shock_absorber,
                to_number(r.life_ext_sign),
                to_number(r.boiler_caliber),
                r.drain_device,
                r.lever_gear,
                r.wagon_model_code,
                to_number(r.repair_by_mileage),
                r.proxy_operator,
                r.proxy_operator_okpo,
                r.wagon_type_code2,
                r.wagon_type_cond,
                to_number(r.axles_count),
                r.exclude_depot,
                r.exclude_reason,
                to_number(r.days_to_repair),
                to_number(r.days_no_oper),
                to_number(r.days_no_move)
           from xmltable ( '/rjd/rjd_row'
               passing xmltype(p_data)
            columns
               report_dt varchar2(30) path '@report_dt',
               type_reference varchar2(50) path '@type_reference',
               wagon_no varchar2(4000) path '@wagon_no',
               waybill_no varchar2(4000) path '@waybill_no',
               wagon_type_code varchar2(4000) path '@wagon_type_code',
               owner_admin varchar2(4000) path '@owner_admin',
               trip_start_dt varchar2(4000) path '@trip_start_dt',
               depart_state varchar2(4000) path '@depart_state',
               depart_road varchar2(4000) path '@depart_road',
               depart_station varchar2(4000) path '@depart_station',
               trip_end_dt varchar2(4000) path '@trip_end_dt',
               dest_state varchar2(4000) path '@dest_state',
               dest_road varchar2(4000) path '@dest_road',
               dest_station varchar2(4000) path '@dest_station',
               consignor_tgnl varchar2(4000) path '@consignor_tgnl',
               consignor varchar2(4000) path '@consignor',
               consignor_okpo varchar2(4000) path '@consignor_okpo',
               consignor_name varchar2(4000) path '@consignor_name',
               consignee_tgnl varchar2(4000) path '@consignee_tgnl',
               consignee varchar2(4000) path '@consignee',
               consignee_okpo varchar2(4000) path '@consignee_okpo',
               consignee_name varchar2(4000) path '@consignee_name',
               cargo_name varchar2(4000) path '@cargo_name',
               cargo_gng varchar2(4000) path '@cargo_gng',
               cargo_weight_kg varchar2(4000) path '@cargo_weight_kg',
               mileage_loaded_km varchar2(4000) path '@mileage_loaded_km',
               mileage_empty_km varchar2(4000) path '@mileage_empty_km',
               mileage_total_km varchar2(4000) path '@mileage_total_km',
               mileage_norm_km varchar2(4000) path '@mileage_norm_km',
               mileage_remain_km varchar2(4000) path '@mileage_remain_km',
               mileage_sign varchar2(4000) path '@mileage_sign',
               special_marks varchar2(4000) path '@special_marks',
               prev_cargo varchar2(4000) path '@prev_cargo',
               oper_station varchar2(4000) path '@oper_station',
               oper_road varchar2(4000) path '@oper_road',
               operation varchar2(4000) path '@operation',
               oper_mnemonic varchar2(4000) path '@oper_mnemonic',
               oper_dt varchar2(4000) path '@oper_dt',
               park_type varchar2(4000) path '@park_type',
               handover_road varchar2(4000) path '@handover_road',
               receive_road varchar2(4000) path '@receive_road',
               train_index varchar2(4000) path '@train_index',
               train_no varchar2(4000) path '@train_no',
               wagon_in_train varchar2(4000) path '@wagon_in_train',
               park_no varchar2(4000) path '@park_no',
               track_no varchar2(4000) path '@track_no',
               seals_count varchar2(4000) path '@seals_count',
               loaded_containers varchar2(4000) path '@loaded_containers',
               empty_containers varchar2(4000) path '@empty_containers',
               container_nos varchar2(4000) path '@container_nos',
               norm_delivery_dt varchar2(4000) path '@norm_delivery_dt',
               dist_passed_km varchar2(4000) path '@dist_passed_km',
               dist_remain_km varchar2(4000) path '@dist_remain_km',
               dist_total_km varchar2(4000) path '@dist_total_km',
               idle_time_hhmmss varchar2(4000) path '@idle_time_hhmmss',
               idle_time_days varchar2(4000) path '@idle_time_days',
               extra_waybill_no varchar2(4000) path '@extra_waybill_no',
               extra_send_id varchar2(4000) path '@extra_send_id',
               asoup_depart_dt varchar2(4000) path '@asoup_depart_dt',
               asoup_arrive_dt varchar2(4000) path '@asoup_arrive_dt',
               send_id varchar2(4000) path '@send_id',
               waybill_id varchar2(4000) path '@waybill_id',
               wagon_no2 varchar2(4000) path '@wagon_no2',
               quality_sign varchar2(4000) path '@quality_sign',
               state_assign_dt varchar2(4000) path '@state_assign_dt',
               wagon_state varchar2(4000) path '@wagon_state',
               state_reason varchar2(4000) path '@state_reason',
               state_station varchar2(4000) path '@state_station',
               reg_date varchar2(4000) path '@reg_date',
               build_date varchar2(4000) path '@build_date',
               next_repair_dt varchar2(4000) path '@next_repair_dt',
               next_repair_type varchar2(4000) path '@next_repair_type',
               factory_no varchar2(4000) path '@factory_no',
               manufacturer varchar2(4000) path '@manufacturer',
               wagon_type_name varchar2(4000) path '@wagon_type_name',
               wagon_model varchar2(4000) path '@wagon_model',
               tare_weight varchar2(4000) path '@tare_weight',
               load_capacity varchar2(4000) path '@load_capacity',
               length_mm varchar2(4000) path '@length_mm',
               last_cap_repair_depot varchar2(4000) path '@last_cap_repair_depot',
               last_cap_repair_dt varchar2(4000) path '@last_cap_repair_dt',
               last_dep_repair_depot varchar2(4000) path '@last_dep_repair_depot',
               last_dep_repair_dt varchar2(4000) path '@last_dep_repair_dt',
               home_road varchar2(4000) path '@home_road',
               home_depot varchar2(4000) path '@home_depot',
               exclude_date varchar2(4000) path '@exclude_date',
               no_transit_reason varchar2(4000) path '@no_transit_reason',
               prev_wagon_no varchar2(4000) path '@prev_wagon_no',
               owner varchar2(4000) path '@owner',
               owner_okpo varchar2(4000) path '@owner_okpo',
               owner_local_code varchar2(4000) path '@owner_local_code',
               home_station varchar2(4000) path '@home_station',
               threshold_sign varchar2(4000) path '@threshold_sign',
               lease_sign varchar2(4000) path '@lease_sign',
               life_ext_date varchar2(4000) path '@life_ext_date',
               lessee varchar2(4000) path '@lessee',
               lessee_okpo varchar2(4000) path '@lessee_okpo',
               lessee_local_code varchar2(4000) path '@lessee_local_code',
               lease_home_station varchar2(4000) path '@lease_home_station',
               lease_end_date varchar2(4000) path '@lease_end_date',
               service_life varchar2(4000) path '@service_life',
               body_material_code varchar2(4000) path '@body_material_code',
               body_material_name varchar2(4000) path '@body_material_name',
               body_volume varchar2(4000) path '@body_volume',
               clearance varchar2(4000) path '@clearance',
               air_dist_type varchar2(4000) path '@air_dist_type',
               automode varchar2(4000) path '@automode',
               auto_lever varchar2(4000) path '@auto_lever',
               brake_type varchar2(4000) path '@brake_type',
               coupler_type varchar2(4000) path '@coupler_type',
               bogie_model varchar2(4000) path '@bogie_model',
               shock_absorber varchar2(4000) path '@shock_absorber',
               life_ext_sign varchar2(4000) path '@life_ext_sign',
               boiler_caliber varchar2(4000) path '@boiler_caliber',
               drain_device varchar2(4000) path '@drain_device',
               lever_gear varchar2(4000) path '@lever_gear',
               wagon_model_code varchar2(4000) path '@wagon_model_code',
               repair_by_mileage varchar2(4000) path '@repair_by_mileage',
               proxy_operator varchar2(4000) path '@proxy_operator',
               proxy_operator_okpo varchar2(4000) path '@proxy_operator_okpo',
               wagon_type_code2 varchar2(4000) path '@wagon_type_code2',
               wagon_type_cond varchar2(4000) path '@wagon_type_cond',
               axles_count varchar2(4000) path '@axles_count',
               exclude_depot varchar2(4000) path '@exclude_depot',
               exclude_reason varchar2(4000) path '@exclude_reason',
               days_to_repair varchar2(4000) path '@days_to_repair',
               days_no_oper varchar2(4000) path '@days_no_oper',
               days_no_move varchar2(4000) path '@days_no_move'
         ) r;

      commit;
   end parse_rjd_excel;

   procedure get_rjd_excel_file (
      p_directory in varchar2,
      p_file_name out varchar2,
      p_file_data out blob
   ) is
      l_bfile       bfile;
      l_dest_offset integer := 1;
      l_src_offset  integer := 1;
   begin
      p_file_name := null;
      p_file_data := null;
      xx_get_dir_list(p_directory);
      for f in (
         select filename
           from xx_dir_list
          where lower(filename) like '%.xlsx'
            and lower(filename) not like '%done%'
          order by filename
      ) loop
         p_file_name := f.filename;
         exit;
      end loop;

      if p_file_name is null then
         return;
      end if;
      l_bfile := bfilename(
         p_directory,
         p_file_name
      );
      if dbms_lob.fileexists(l_bfile) = 1 then
         dbms_lob.createtemporary(
            p_file_data,
            false
         );
         dbms_lob.fileopen(
            l_bfile,
            dbms_lob.file_readonly
         );
         dbms_lob.loadblobfromfile(
            dest_lob    => p_file_data,
            src_bfile   => l_bfile,
            amount      => dbms_lob.lobmaxsize,
            dest_offset => l_dest_offset,
            src_offset  => l_src_offset
         );

         dbms_lob.fileclose(l_bfile);
      end if;

      delete xx_dir_list;
   exception
      when others then
         if dbms_lob.fileisopen(l_bfile) = 1 then
            dbms_lob.fileclose(l_bfile);
         end if;
         delete xx_dir_list;
         raise;
   end get_rjd_excel_file;

   function stations_pipe (
      p_search in varchar2 default null,
      p_offset in number default 0,
      p_limit  in number default 50
   ) return station_tab
      pipelined is
   begin
      for r in (
         select esr_code,
                station_name,
                latitude,
                longitude
           from (
              select s.esr_code,
                     s.station_name,
                     s.latitude,
                     s.longitude,
                     row_number() over (
                        order by nlssort(s.station_name, 'NLS_SORT=RUSSIAN'), s.esr_code
                     ) as rn
                from xx_rjd_stations s
               where p_search is null
                  or upper(s.esr_code) like '%' || upper(trim(p_search)) || '%'
                  or upper(s.station_name) like '%' || upper(trim(p_search)) || '%'
           )
          where rn > nvl(p_offset, 0)
            and rn <= nvl(p_offset, 0) + nvl(p_limit, 50)
          order by rn
      ) loop
         pipe row (station_rec(r.esr_code, r.station_name, r.latitude, r.longitude));
      end loop;

      return;
   end stations_pipe;

   function stations_count (
      p_search in varchar2 default null
   ) return number is
      l_count number;
   begin
      select count(*)
        into l_count
        from xx_rjd_stations
       where p_search is null
          or upper(esr_code) like '%' || upper(trim(p_search)) || '%'
          or upper(station_name) like '%' || upper(trim(p_search)) || '%';

      return l_count;
   end stations_count;

   procedure validate_station (
      p_esr_code     in varchar2,
      p_station_name in varchar2,
      p_latitude     in number,
      p_longitude    in number
   ) is
   begin
      if trim(p_esr_code) is null then
         raise_application_error(-20001, 'Не указан код ЕСР');
      end if;

      if trim(p_station_name) is null then
         raise_application_error(-20002, 'Не указано название станции');
      end if;

      if p_latitude is not null and (p_latitude < -90 or p_latitude > 90) then
         raise_application_error(-20003, 'Широта должна быть в диапазоне от -90 до 90');
      end if;

      if p_longitude is not null and (p_longitude < -180 or p_longitude > 180) then
         raise_application_error(-20004, 'Долгота должна быть в диапазоне от -180 до 180');
      end if;
   end validate_station;

   procedure save_station (
      p_esr_code     in varchar2,
      p_station_name in varchar2,
      p_latitude     in number,
      p_longitude    in number
   ) is
   begin
      validate_station(p_esr_code, p_station_name, p_latitude, p_longitude);

      merge into xx_rjd_stations s
      using (
         select trim(p_esr_code) as esr_code,
                trim(p_station_name) as station_name,
                p_latitude as latitude,
                p_longitude as longitude
           from dual
      ) src
         on (s.esr_code = src.esr_code)
       when matched then update set
         s.station_name = src.station_name,
         s.latitude = src.latitude,
         s.longitude = src.longitude
       when not matched then insert (
         esr_code,
         station_name,
         latitude,
         longitude
       ) values (
         src.esr_code,
         src.station_name,
         src.latitude,
         src.longitude
       );
   end save_station;

   procedure delete_station (
      p_esr_code in varchar2
   ) is
   begin
      if trim(p_esr_code) is null then
         raise_application_error(-20005, 'Не указан код ЕСР');
      end if;

      delete xx_rjd_stations
       where esr_code = trim(p_esr_code);
   end delete_station;
end xx_rjd_dislocation_new_pkg;
/
