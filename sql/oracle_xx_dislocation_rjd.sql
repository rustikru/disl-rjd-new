-- ================================================================

create table xx_users_rjd (
   id            number generated always as identity primary key,
   username      varchar2(100) unique not null,
   display_name  varchar2(255) not null,
   email         varchar2(255) default '',
   password_hash varchar2(255) default '',
   is_active     number(1) default 1 not null,
   created_at    timestamp default current_timestamp
);
-- администратор (пароль user)
insert into xx_users_rjd (
   username,
   display_name,
   email,
   password_hash
) values ( 'user',
           'Пользователь',
           'user@local.ru',
           '$2y$10$1scGJYlRMvoeTRWdI2GH4.ubyq9Z7LoSNeJNffvGbDISeCZ/z1FzC' );
commit;
/

create table xx_dislocation_rjd (
   id                    number not null primary key,
   report_dt             timestamp not null,  -- дата справки из ячейки A2
   type_reference        varchar2(50),   -- тип справки: 'Подход' / 'Отправка' (вычисляется при импорте по dest_station)

    -- ── Идентификаторы ───────────────────────────────────────────
   wagon_no              varchar2(500),  -- кол.  1  '50447762'
   waybill_no            varchar2(500),  -- кол.  2  'ЭФ038927'

    -- ── Тип и администрация ──────────────────────────────────────
   wagon_type_code       varchar2(500),  -- кол.  3  'Цистерны (70)'
   owner_admin           varchar2(500),  -- кол.  4  'РЖД (20)'

    -- ── Отправление ──────────────────────────────────────────────
   trip_start_dt         date,           -- кол.  5  '01.06.2026 12:42'
   depart_state          varchar2(500),  -- кол.  6  'Российская Федерация (643)'
   depart_road           varchar2(500),  -- кол.  7  'МОСКОВСКАЯ (17)'
   depart_station        varchar2(500),  -- кол.  8  'ПОТОЧИНО (237609)'

    -- ── Назначение ───────────────────────────────────────────────
   trip_end_dt           date,           -- кол.  9
   dest_state            varchar2(500),  -- кол. 10
   dest_road             varchar2(500),  -- кол. 11
   dest_station          varchar2(500),  -- кол. 12

    -- ── Грузоотправитель ─────────────────────────────────────────
   consignor_tgnl        varchar2(500),  -- кол. 13  '3034'
   consignor             varchar2(500),  -- кол. 14  'ООО "МЕТАДИНЕА" (72149825)'
   consignor_okpo        varchar2(500),  -- кол. 15  '72149825'
   consignor_name        varchar2(500),  -- кол. 16  'ООО "МЕТАДИНЕА"'

    -- ── Грузополучатель ──────────────────────────────────────────
   consignee_tgnl        varchar2(500),  -- кол. 17
   consignee             varchar2(500),  -- кол. 18
   consignee_okpo        varchar2(500),  -- кол. 19
   consignee_name        varchar2(500),  -- кол. 20

    -- ── Груз ─────────────────────────────────────────────────────
   cargo_name            varchar2(500),  -- кол. 21  длинное описание с кодом
   cargo_gng             varchar2(500),  -- кол. 22  '0', '421034' (код ГНГ)

    -- ── Вес и пробег ─────────────────────────────────────────────
   cargo_weight_kg       number,         -- кол. 23
   mileage_loaded_km     number,         -- кол. 24
   mileage_empty_km      number,         -- кол. 25
   mileage_total_km      number,         -- кол. 26
   mileage_norm_km       number,         -- кол. 27
   mileage_remain_km     number,         -- кол. 28
   mileage_sign          varchar2(500),  -- кол. 29  знак/направление

    -- ── Особые отметки ───────────────────────────────────────────
   special_marks         varchar2(500),  -- кол. 30  '3, 6, 7'
   prev_cargo            varchar2(500),  -- кол. 31  'Спирт метиловый (метанол) (721484)'

    -- ── Текущая операция ─────────────────────────────────────────
   oper_station          varchar2(500),  -- кол. 32
   oper_road             varchar2(500),  -- кол. 33
   operation             varchar2(500),  -- кол. 34  'Корректировка сведений о вагоне в составе поезда (7)'
   oper_mnemonic         varchar2(500),  -- кол. 35  'ОТПР', 'ПРИБ', 'КОРВ', 'ИСКП'
   oper_dt               date,           -- кол. 36  '02.06.2026 14:47'

    -- ── Парк и дороги сдачи/приёма ───────────────────────────────
   park_type             varchar2(500),  -- кол. 37  'Транзитный, Порожний, Вагон рабочего парка'
   handover_road         varchar2(500),  -- кол. 38
   receive_road          varchar2(500),  -- кол. 39

    -- ── Поезд и путь ─────────────────────────────────────────────
   train_index           varchar2(500),  -- кол. 40  '237609052230008 (ПОТОЧИНО+052+ОРЕХОВО-ЗУЕВО)'
   train_no              varchar2(500),  -- кол. 41  '9999'
   wagon_in_train        number(5),      -- кол. 42  37 (позиция в составе)
   park_no               varchar2(500),  -- кол. 43  'I (1)'
   track_no              varchar2(500),  -- кол. 44  ' (5)'

    -- ── Контейнеры ───────────────────────────────────────────────
   seals_count           number(3),      -- кол. 45
   loaded_containers     number(3),      -- кол. 46
   empty_containers      number(3),      -- кол. 47
   container_nos         varchar2(500),  -- кол. 48

    -- ── Доставка и простой ───────────────────────────────────────
   norm_delivery_dt      date,           -- кол. 49  '09.06.2026'
   dist_passed_km        number,         -- кол. 50
   dist_remain_km        number,         -- кол. 51
   dist_total_km         number,         -- кол. 52
   idle_time_hhmmss      varchar2(500),  -- кол. 53  '0: 15: 18' (строковый формат)
   idle_time_days        number,         -- кол. 54

    -- ── Досылка и АСОУП ──────────────────────────────────────────
   extra_waybill_no      varchar2(500),  -- кол. 55
   extra_send_id         varchar2(500),  -- кол. 56
   asoup_depart_dt       date,           -- кол. 57
   asoup_arrive_dt       date,           -- кол. 58
   send_id               varchar2(500),  -- кол. 59  '2017ЭФ038927'
   waybill_id            varchar2(500),  -- кол. 60  '1751007050'

    -- ── Состояние ────────────────────────────────────────────────
   wagon_no2             varchar2(500),  -- кол. 61  дублирует wagon_no
   quality_sign          varchar2(500),  -- кол. 62  'НАЛИЧИЕ ТЕХНИЧЕСКОГО ПАСПОРТА'
   state_assign_dt       date,           -- кол. 63
   wagon_state           varchar2(500),  -- кол. 64  'РП'
   state_reason          varchar2(500),  -- кол. 65  'Деповской ремонт (1)'
   state_station         varchar2(500),  -- кол. 66

    -- ── Даты ремонтов ────────────────────────────────────────────
   reg_date              date,           -- кол. 67
   build_date            date,           -- кол. 68
   next_repair_dt        date,           -- кол. 69
   next_repair_type      varchar2(500),  -- кол. 70  'Капитальный ремонт (2)'

    -- ── Завод и модель ───────────────────────────────────────────
   factory_no            varchar2(500),  -- кол. 71  '794230'
   manufacturer          varchar2(500),  -- кол. 72
   wagon_type_name       varchar2(500),  -- кол. 73
   wagon_model           varchar2(500),  -- кол. 74  '15-1610-02'
   tare_weight           number,         -- кол. 75  275
   load_capacity         number,         -- кол. 76  650
   length_mm             number,         -- кол. 77  12020

    -- ── Депо ремонтов и приписки ─────────────────────────────────
   last_cap_repair_depot varchar2(500),  -- кол. 78
   last_cap_repair_dt    date,           -- кол. 79
   last_dep_repair_depot varchar2(500),  -- кол. 80
   last_dep_repair_dt    date,           -- кол. 81
   home_road             varchar2(500),  -- кол. 82
   home_depot            varchar2(500),  -- кол. 83

    -- ── Исключение и предыдущий номер ────────────────────────────
   exclude_date          date,           -- кол. 84
   no_transit_reason     varchar2(500),  -- кол. 85
   prev_wagon_no         varchar2(500),  -- кол. 86  '000000000000'

    -- ── Собственник ──────────────────────────────────────────────
   owner                 varchar2(500),  -- кол. 87
   owner_okpo            varchar2(500),  -- кол. 88
   owner_local_code      varchar2(500),  -- кол. 89  '760643'
   home_station          varchar2(500),  -- кол. 90

    -- ── Аренда ───────────────────────────────────────────────────
   threshold_sign        varchar2(500),  -- кол. 91  'ВАГОН РЕМОНТИРУЕТСЯ ПО ПРОБЕГУ'
   lease_sign            number(1),      -- кол. 92  0 / 1
   life_ext_date         date,           -- кол. 93
   lessee                varchar2(500),  -- кол. 94
   lessee_okpo           varchar2(500),  -- кол. 95
   lessee_local_code     varchar2(500),  -- кол. 96
   lease_home_station    varchar2(500),  -- кол. 97
   lease_end_date        date,           -- кол. 98

    -- ── Срок службы ──────────────────────────────────────────────
   service_life          date,           -- кол. 99  дата окончания, не кол-во лет

    -- ── Материал и объём кузова ──────────────────────────────────
   body_material_code    number(2),      -- кол.100  2
   body_material_name    varchar2(500),  -- кол.101  '09Г2С, 09Г2Д, 09Г2...'
   body_volume           number,         -- кол.102  86, 88 (м³)
   clearance             varchar2(500),  -- кол.103  '1-Т (3)'

    -- ── Техническое оснащение ────────────────────────────────────
   air_dist_type         varchar2(500),  -- кол.104  '483М-000 (4)'
   automode              varchar2(500),  -- кол.105  'Не оборудован (2)'
   auto_lever            varchar2(500),  -- кол.106  '574-Б (2)', 'РТРП-300 (5)'
   brake_type            varchar2(500),  -- кол.107
   coupler_type          varchar2(500),  -- кол.108
   bogie_model           varchar2(500),  -- кол.109  список моделей тележек через запятую
   shock_absorber        varchar2(500),  -- кол.110

    -- ── Признаки и коды ──────────────────────────────────────────
   life_ext_sign         number(1),      -- кол.111  0 / 1
   boiler_caliber        number,         -- кол.112  0
   drain_device          varchar2(500),  -- кол.113
   lever_gear            varchar2(500),  -- кол.114
   wagon_model_code      varchar2(500),  -- кол.115  '903'
   repair_by_mileage     number(1),      -- кол.116  0 / 1

    -- ── Оператор по доверенности ─────────────────────────────────
   proxy_operator        varchar2(500),  -- кол.117
   proxy_operator_okpo   varchar2(500),  -- кол.118

    -- ── Прочие классификаторы ────────────────────────────────────
   wagon_type_code2      varchar2(500),  -- кол.119  дублирует wagon_type_code
   wagon_type_cond       varchar2(500),  -- кол.120
   axles_count           number(2),      -- кол.121  4, 8
   exclude_depot         varchar2(500),  -- кол.122
   exclude_reason        varchar2(500),  -- кол.123

    -- ── Дни ──────────────────────────────────────────────────────
   days_to_repair        number,         -- кол.124
   days_no_oper          number,         -- кол.125
   days_no_move          number          -- кол.126
);

-- Триггер для автоматического заполнения ID через XX_DISLOCATION_RJD_SEQ
-- PL/SQL-блок: нужен "/" в конце (в отличие от обычных DDL-операторов)
create or replace trigger xx_dislocation_rjd_bi before
   insert on xx_dislocation_rjd
   for each row
begin
   if :new.id is null then
      :new.id := xx_dislocation_rjd_seq.nextval;
   end if;
end;
/

create index idx_xx_rjd_report_dt on
   xx_dislocation_rjd (
      report_dt
   );
create index idx_xx_rjd_type_ref on
   xx_dislocation_rjd (
      report_dt,
      type_reference
   );
create index idx_xx_rjd_wagon_no on
   xx_dislocation_rjd (
      wagon_no
   );
create index idx_xx_rjd_dest on
   xx_dislocation_rjd (
      report_dt,
      dest_road,
      dest_station
   );
create index idx_xx_rjd_depart on
   xx_dislocation_rjd (
      report_dt,
      depart_road,
      depart_station
   );
create index idx_xx_rjd_oper on
   xx_dislocation_rjd (
      report_dt,
      oper_road,
      oper_station
   );
create index idx_xx_rjd_mnemonic on
   xx_dislocation_rjd (
      report_dt,
      oper_mnemonic
   );
create index idx_xx_rjd_cargo on
   xx_dislocation_rjd (
      report_dt,
      cargo_name
   );
create index idx_xx_rjd_park_type on
   xx_dislocation_rjd (
      report_dt,
      park_type
   );
create index idx_xx_rjd_wagon_type on
   xx_dislocation_rjd (
      report_dt,
      wagon_type_code
   );

comment on table xx_dislocation_rjd is
   'Дислокация вагонов РЖД — 126 колонок из ЛК клиента РЖД';
comment on column xx_dislocation_rjd.report_dt is
   'Системное [Дата справки]';
comment on column xx_dislocation_rjd.type_reference is
   'Системное [Тип справки]';

-- ── Раздел 1: Данные о вагоне (кол. 1–31) ───────────────────────────────────
comment on column xx_dislocation_rjd.wagon_no is
   'Данные о вагоне [Номер вагона]';
comment on column xx_dislocation_rjd.waybill_no is
   'Данные о вагоне [Номер накладной]';
comment on column xx_dislocation_rjd.wagon_type_code is
   'Данные о вагоне [Тип вагона]';
comment on column xx_dislocation_rjd.owner_admin is
   'Данные о вагоне [Администрация собственника]';
comment on column xx_dislocation_rjd.trip_start_dt is
   'Данные о вагоне [Дата начала рейса]';
comment on column xx_dislocation_rjd.depart_state is
   'Данные о вагоне [Государство отправления]';
comment on column xx_dislocation_rjd.depart_road is
   'Данные о вагоне [Дорога отправления]';
comment on column xx_dislocation_rjd.depart_station is
   'Данные о вагоне [Станция отправления]';
comment on column xx_dislocation_rjd.trip_end_dt is
   'Данные о вагоне [Дата окончания рейса]';
comment on column xx_dislocation_rjd.dest_state is
   'Данные о вагоне [Государство назначения]';
comment on column xx_dislocation_rjd.dest_road is
   'Данные о вагоне [Дорога назначения]';
comment on column xx_dislocation_rjd.dest_station is
   'Данные о вагоне [Станция назначения]';
comment on column xx_dislocation_rjd.consignor_tgnl is
   'Данные о вагоне [ТГНЛ грузоотправителя]';
comment on column xx_dislocation_rjd.consignor is
   'Данные о вагоне [Грузоотправитель]';
comment on column xx_dislocation_rjd.consignor_okpo is
   'Данные о вагоне [ОКПО грузоотправителя]';
comment on column xx_dislocation_rjd.consignor_name is
   'Данные о вагоне [Наименование грузоотправителя]';
comment on column xx_dislocation_rjd.consignee_tgnl is
   'Данные о вагоне [ТГНЛ грузополучателя]';
comment on column xx_dislocation_rjd.consignee is
   'Данные о вагоне [Грузополучатель]';
comment on column xx_dislocation_rjd.consignee_okpo is
   'Данные о вагоне [ОКПО грузополучателя]';
comment on column xx_dislocation_rjd.consignee_name is
   'Данные о вагоне [Наименование грузополучателя]';
comment on column xx_dislocation_rjd.cargo_name is
   'Данные о вагоне [Наименование груза]';
comment on column xx_dislocation_rjd.cargo_gng is
   'Данные о вагоне [Код ГНГ]';
comment on column xx_dislocation_rjd.cargo_weight_kg is
   'Данные о вагоне [Масса груза (кг)]';
comment on column xx_dislocation_rjd.mileage_loaded_km is
   'Данные о вагоне [Пробег гружёный (км)]';
comment on column xx_dislocation_rjd.mileage_empty_km is
   'Данные о вагоне [Пробег порожний (км)]';
comment on column xx_dislocation_rjd.mileage_total_km is
   'Данные о вагоне [Пробег общий (км)]';
comment on column xx_dislocation_rjd.mileage_norm_km is
   'Данные о вагоне [Пробег нормативный (км)]';
comment on column xx_dislocation_rjd.mileage_remain_km is
   'Данные о вагоне [Пробег остаток (км)]';
comment on column xx_dislocation_rjd.mileage_sign is
   'Данные о вагоне [Знак пробега]';
comment on column xx_dislocation_rjd.special_marks is
   'Данные о вагоне [Особые отметки]';
comment on column xx_dislocation_rjd.prev_cargo is
   'Данные о вагоне [Предыдущий груз]';

-- ── Раздел 2: Дислокация вагона (кол. 32–60) ─────────────────────────────────
comment on column xx_dislocation_rjd.oper_station is
   'Дислокация вагона [Станция операции]';
comment on column xx_dislocation_rjd.oper_road is
   'Дислокация вагона [Дорога операции]';
comment on column xx_dislocation_rjd.operation is
   'Дислокация вагона [Операция]';
comment on column xx_dislocation_rjd.oper_mnemonic is
   'Дислокация вагона [Мнемоника операции]';
comment on column xx_dislocation_rjd.oper_dt is
   'Дислокация вагона [Дата операции]';
comment on column xx_dislocation_rjd.park_type is
   'Дислокация вагона [Признак парка]';
comment on column xx_dislocation_rjd.handover_road is
   'Дислокация вагона [Дорога сдачи]';
comment on column xx_dislocation_rjd.receive_road is
   'Дислокация вагона [Дорога приёма]';
comment on column xx_dislocation_rjd.train_index is
   'Дислокация вагона [Индекс поезда]';
comment on column xx_dislocation_rjd.train_no is
   'Дислокация вагона [Номер поезда]';
comment on column xx_dislocation_rjd.wagon_in_train is
   'Дислокация вагона [Позиция вагона в составе]';
comment on column xx_dislocation_rjd.park_no is
   'Дислокация вагона [Номер парка]';
comment on column xx_dislocation_rjd.track_no is
   'Дислокация вагона [Номер пути]';
comment on column xx_dislocation_rjd.seals_count is
   'Дислокация вагона [Количество пломб]';
comment on column xx_dislocation_rjd.loaded_containers is
   'Дислокация вагона [Гружёные контейнеры]';
comment on column xx_dislocation_rjd.empty_containers is
   'Дислокация вагона [Порожние контейнеры]';
comment on column xx_dislocation_rjd.container_nos is
   'Дислокация вагона [Номера контейнеров]';
comment on column xx_dislocation_rjd.norm_delivery_dt is
   'Дислокация вагона [Нормативная дата доставки]';
comment on column xx_dislocation_rjd.dist_passed_km is
   'Дислокация вагона [Расстояние пройдено (км)]';
comment on column xx_dislocation_rjd.dist_remain_km is
   'Дислокация вагона [Расстояние остаток (км)]';
comment on column xx_dislocation_rjd.dist_total_km is
   'Дислокация вагона [Расстояние общее (км)]';
comment on column xx_dislocation_rjd.idle_time_hhmmss is
   'Дислокация вагона [Простой (ЧЧ:ММ:СС)]';
comment on column xx_dislocation_rjd.idle_time_days is
   'Дислокация вагона [Простой (сут.)]';
comment on column xx_dislocation_rjd.extra_waybill_no is
   'Дислокация вагона [Номер досылочной накладной]';
comment on column xx_dislocation_rjd.extra_send_id is
   'Дислокация вагона [Идентификатор досылки]';
comment on column xx_dislocation_rjd.asoup_depart_dt is
   'Дислокация вагона [Дата отправления АСОУП]';
comment on column xx_dislocation_rjd.asoup_arrive_dt is
   'Дислокация вагона [Дата прибытия АСОУП]';
comment on column xx_dislocation_rjd.send_id is
   'Дислокация вагона [Идентификатор отправки]';
comment on column xx_dislocation_rjd.waybill_id is
   'Дислокация вагона [Идентификатор накладной]';

-- ── Раздел 3: Техническое состояние вагона (кол. 61–126) ─────────────────────
comment on column xx_dislocation_rjd.wagon_no2 is
   'Техническое состояние вагона [Номер вагона (дубль)]';
comment on column xx_dislocation_rjd.quality_sign is
   'Техническое состояние вагона [Признак качества]';
comment on column xx_dislocation_rjd.state_assign_dt is
   'Техническое состояние вагона [Дата присвоения состояния]';
comment on column xx_dislocation_rjd.wagon_state is
   'Техническое состояние вагона [Состояние вагона]';
comment on column xx_dislocation_rjd.state_reason is
   'Техническое состояние вагона [Причина состояния]';
comment on column xx_dislocation_rjd.state_station is
   'Техническое состояние вагона [Станция состояния]';
comment on column xx_dislocation_rjd.reg_date is
   'Техническое состояние вагона [Дата регистрации]';
comment on column xx_dislocation_rjd.build_date is
   'Техническое состояние вагона [Дата постройки]';
comment on column xx_dislocation_rjd.next_repair_dt is
   'Техническое состояние вагона [Дата следующего ремонта]';
comment on column xx_dislocation_rjd.next_repair_type is
   'Техническое состояние вагона [Тип следующего ремонта]';
comment on column xx_dislocation_rjd.factory_no is
   'Техническое состояние вагона [Заводской номер]';
comment on column xx_dislocation_rjd.manufacturer is
   'Техническое состояние вагона [Завод-изготовитель]';
comment on column xx_dislocation_rjd.wagon_type_name is
   'Техническое состояние вагона [Наименование типа вагона]';
comment on column xx_dislocation_rjd.wagon_model is
   'Техническое состояние вагона [Модель вагона]';
comment on column xx_dislocation_rjd.tare_weight is
   'Техническое состояние вагона [Масса тары]';
comment on column xx_dislocation_rjd.load_capacity is
   'Техническое состояние вагона [Грузоподъёмность]';
comment on column xx_dislocation_rjd.length_mm is
   'Техническое состояние вагона [Длина (мм)]';
comment on column xx_dislocation_rjd.last_cap_repair_depot is
   'Техническое состояние вагона [Депо последнего капитального ремонта]';
comment on column xx_dislocation_rjd.last_cap_repair_dt is
   'Техническое состояние вагона [Дата последнего капитального ремонта]';
comment on column xx_dislocation_rjd.last_dep_repair_depot is
   'Техническое состояние вагона [Депо последнего деповского ремонта]';
comment on column xx_dislocation_rjd.last_dep_repair_dt is
   'Техническое состояние вагона [Дата последнего деповского ремонта]';
comment on column xx_dislocation_rjd.home_road is
   'Техническое состояние вагона [Дорога приписки]';
comment on column xx_dislocation_rjd.home_depot is
   'Техническое состояние вагона [Депо приписки]';
comment on column xx_dislocation_rjd.exclude_date is
   'Техническое состояние вагона [Дата исключения]';
comment on column xx_dislocation_rjd.no_transit_reason is
   'Техническое состояние вагона [Причина запрета транзита]';
comment on column xx_dislocation_rjd.prev_wagon_no is
   'Техническое состояние вагона [Предыдущий номер вагона]';
comment on column xx_dislocation_rjd.owner is
   'Техническое состояние вагона [Собственник]';
comment on column xx_dislocation_rjd.owner_okpo is
   'Техническое состояние вагона [ОКПО собственника]';
comment on column xx_dislocation_rjd.owner_local_code is
   'Техническое состояние вагона [Местный код собственника]';
comment on column xx_dislocation_rjd.home_station is
   'Техническое состояние вагона [Станция приписки]';
comment on column xx_dislocation_rjd.threshold_sign is
   'Техническое состояние вагона [Признак порога]';
comment on column xx_dislocation_rjd.lease_sign is
   'Техническое состояние вагона [Признак аренды]';
comment on column xx_dislocation_rjd.life_ext_date is
   'Техническое состояние вагона [Дата продления срока службы]';
comment on column xx_dislocation_rjd.lessee is
   'Техническое состояние вагона [Арендатор]';
comment on column xx_dislocation_rjd.lessee_okpo is
   'Техническое состояние вагона [ОКПО арендатора]';
comment on column xx_dislocation_rjd.lessee_local_code is
   'Техническое состояние вагона [Местный код арендатора]';
comment on column xx_dislocation_rjd.lease_home_station is
   'Техническое состояние вагона [Станция приписки арендатора]';
comment on column xx_dislocation_rjd.lease_end_date is
   'Техническое состояние вагона [Дата окончания аренды]';
comment on column xx_dislocation_rjd.service_life is
   'Техническое состояние вагона [Срок службы (дата окончания)]';
comment on column xx_dislocation_rjd.body_material_code is
   'Техническое состояние вагона [Код материала кузова]';
comment on column xx_dislocation_rjd.body_material_name is
   'Техническое состояние вагона [Материал кузова]';
comment on column xx_dislocation_rjd.body_volume is
   'Техническое состояние вагона [Объём кузова (м³)]';
comment on column xx_dislocation_rjd.clearance is
   'Техническое состояние вагона [Габарит]';
comment on column xx_dislocation_rjd.air_dist_type is
   'Техническое состояние вагона [Тип воздухораспределителя]';
comment on column xx_dislocation_rjd.automode is
   'Техническое состояние вагона [Авторежим]';
comment on column xx_dislocation_rjd.auto_lever is
   'Техническое состояние вагона [Авторычаг]';
comment on column xx_dislocation_rjd.brake_type is
   'Техническое состояние вагона [Тип тормоза]';
comment on column xx_dislocation_rjd.coupler_type is
   'Техническое состояние вагона [Тип автосцепки]';
comment on column xx_dislocation_rjd.bogie_model is
   'Техническое состояние вагона [Модели тележек]';
comment on column xx_dislocation_rjd.shock_absorber is
   'Техническое состояние вагона [Поглощающий аппарат]';
comment on column xx_dislocation_rjd.life_ext_sign is
   'Техническое состояние вагона [Признак продления срока службы]';
comment on column xx_dislocation_rjd.boiler_caliber is
   'Техническое состояние вагона [Калибр котла]';
comment on column xx_dislocation_rjd.drain_device is
   'Техническое состояние вагона [Сливной прибор]';
comment on column xx_dislocation_rjd.lever_gear is
   'Техническое состояние вагона [Рычажная передача]';
comment on column xx_dislocation_rjd.wagon_model_code is
   'Техническое состояние вагона [Код модели вагона]';
comment on column xx_dislocation_rjd.repair_by_mileage is
   'Техническое состояние вагона [Ремонт по пробегу]';
comment on column xx_dislocation_rjd.proxy_operator is
   'Техническое состояние вагона [Оператор по доверенности]';
comment on column xx_dislocation_rjd.proxy_operator_okpo is
   'Техническое состояние вагона [ОКПО оператора по доверенности]';
comment on column xx_dislocation_rjd.wagon_type_code2 is
   'Техническое состояние вагона [Тип вагона (дубль)]';
comment on column xx_dislocation_rjd.wagon_type_cond is
   'Техническое состояние вагона [Условный тип вагона]';
comment on column xx_dislocation_rjd.axles_count is
   'Техническое состояние вагона [Количество осей]';
comment on column xx_dislocation_rjd.exclude_depot is
   'Техническое состояние вагона [Депо исключения]';
comment on column xx_dislocation_rjd.exclude_reason is
   'Техническое состояние вагона [Причина исключения]';
comment on column xx_dislocation_rjd.days_to_repair is
   'Техническое состояние вагона [Дней до ремонта]';
comment on column xx_dislocation_rjd.days_no_oper is
   'Техническое состояние вагона [Дней без операций]';
comment on column xx_dislocation_rjd.days_no_move is
   'Техническое состояние вагона [Дней без движения]';