create or replace package xx_rjd_dislocation_new_pkg as
    /******************************************************************************
    NAME:  xx_etw.xx_rjd_dislocation_new_pkg
    PURPOSE:   Дислокация РЖД (справка из кабинета)
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
   ) return t_xx_rjd_kpi_tab
      pipelined;

   function get_kpi_where (
      p_kpi_id in number
   ) return varchar2;

   procedure parse_rjd_excel (
      p_data in clob
   );

   procedure get_rjd_excel_file (
      p_directory in varchar2,
      p_file_name out varchar2,
      p_file_data out blob
   );

   type station_rec is record (
         esr_code     varchar2(20),
         station_name varchar2(255),
         latitude     varchar2(255),
         longitude    varchar2(255)
   );
   type station_tab is
      table of station_rec;
   type station_no_coord_rec is record (
         esr_code     varchar2(20),
         station_name varchar2(255),
         wagon_count  number
   );
   type station_no_coord_tab is
      table of station_no_coord_rec;
   function stations (
      p_search in varchar2 default null,
      p_offset in number default 0,
      p_limit  in number default 50
   ) return station_tab
      pipelined;

   function stations_count (
      p_search in varchar2 default null
   ) return number;

   function stations_no_coord return station_no_coord_tab
      pipelined;

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
