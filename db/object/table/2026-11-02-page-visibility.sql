-- Глобально отключённые страницы. Таблица общая для всех экземпляров приложения,
-- подключённых к этой схеме Oracle.

create table xx_rjd_disabled_pages (
   page_code   varchar2(200 char) not null,
   disabled_at date default sysdate not null,
   disabled_by number,
   constraint xx_rjd_disabled_pages_pk primary key ( page_code )
);

comment on table xx_rjd_disabled_pages is 'Страницы, глобально отключённые для всех пользователей, кроме SUPER';
comment on column xx_rjd_disabled_pages.page_code is 'Код страницы из PageRegistry или URL-префикс';
comment on column xx_rjd_disabled_pages.disabled_at is 'Дата отключения';
comment on column xx_rjd_disabled_pages.disabled_by is 'ID пользователя, сохранившего настройку';

commit;
