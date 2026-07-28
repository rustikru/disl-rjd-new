create table xx_etw.xx_disl_idle_control (
   id_control       number,
   created_by       number,
   creation_date    date,
   last_updated_by  number,
   last_update_date date,
   car_number       varchar2(50 byte),
   is_excluded      varchar2(1 byte) default 'N',
   note             varchar2(240 byte),
   start_date       date,
   end_date         date,
   deleted          varchar2(1 byte) default 'N',
   deleted_by       number,
   deleted_date     date,
   idle_reasons_id  number
);

create table xx_etw.xx_disl_idle_reasons (
   id    number not null,
   name  varchar2(240 byte),
   descr varchar2(500 byte)
);

insert into xx_disl_idle_reasons (
   id,
   name
) values
   ( 1,
     'Металлолом' );
insert into xx_disl_idle_reasons (
   id,
   name,
   descr
) values
   ( 2,
     'ИВМД',
     'Исключение вагона из вагонной модели дороги' );
insert into xx_disl_idle_reasons (
   id,
   name,
   descr
) values
   ( 3,
     'Лом',
     'Списан и разрезан в лом' );
commit;

insert into xx_disl_idle_control (
   id_control,
   created_by,
   creation_date,
   last_updated_by,
   last_update_date,
   car_number,
   is_excluded,
   start_date,
   idle_reasons_id
) values
   ( 1,
     1,
     to_date('18.05.2026 12:15:41','DD.MM.YYYY HH24:MI:SS'),
     1,
     to_date('18.05.2026 17:07:20','DD.MM.YYYY HH24:MI:SS'),
     '52573516',
     'Y',
     to_date('18.05.2026','DD.MM.YYYY'),
     1 );
insert into xx_disl_idle_control (
   id_control,
   created_by,
   creation_date,
   last_updated_by,
   last_update_date,
   car_number,
   is_excluded,
   note,
   start_date,
   idle_reasons_id
) values
   ( 2,
     1,
     to_date('27.07.2026 23:57:20','DD.MM.YYYY HH24:MI:SS'),
     1,
     to_date('28.07.2026 13:27:35','DD.MM.YYYY HH24:MI:SS'),
     '57047938',
     'Y',
     '20.04.2018_Списан разрезан в лом _ИВМД:Исключение вагона из вагонной модели дороги',
     to_date('22.03.2018','DD.MM.YYYY'),
     3 );
insert into xx_disl_idle_control (
   id_control,
   created_by,
   creation_date,
   last_updated_by,
   last_update_date,
   car_number,
   is_excluded,
   note,
   start_date,
   idle_reasons_id
) values
   ( 3,
     1,
     to_date('28.07.2026 11:24:43','DD.MM.YYYY HH24:MI:SS'),
     1,
     to_date('28.07.2026 11:24:43','DD.MM.YYYY HH24:MI:SS'),
     '57048431',
     'Y',
     '19.04.2019_Списан, разрезан в лом_',
     to_date('19.04.2019','DD.MM.YYYY'),
     3 );
insert into xx_disl_idle_control (
   id_control,
   created_by,
   creation_date,
   last_updated_by,
   last_update_date,
   car_number,
   is_excluded,
   note,
   start_date,
   idle_reasons_id
) values
   ( 4,
     1,
     to_date('28.07.2026 11:35:43','DD.MM.YYYY HH24:MI:SS'),
     1,
     to_date('28.07.2026 11:35:43','DD.MM.YYYY HH24:MI:SS'),
     '57049546',
     'Y',
     '19.04.2019_Списан, разрезан в лом_',
     to_date('19.04.2019','DD.MM.YYYY'),
     3 );
commit;

create or replace view xx_disl_idle_control_v as
   select x.car_number,
          x.start_date as start_date,
          x.end_date as end_date,
          x.note,
          x.is_excluded,
          du.full_name created_name,
          x.id_control,
          x.idle_reasons_id,
          ir.name as idle_reasons_name
     from xx_etw.xx_disl_idle_control x,
          xx_etw.xx_disl_users du,
          xx_etw.xx_disl_idle_reasons ir
    where 1 = 1
      and x.idle_reasons_id = ir.id (+)
      and x.created_by = du.id
      and nvl(
      x.deleted,
      'N'
   ) = 'N'