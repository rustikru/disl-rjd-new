-- Отдельное право на карточку вагона.
-- Существующие роли с доступом к дашборду сохраняют доступ к карточке.

insert into xx_rjd_role_pages (
   role_id,
   page
)
   select distinct
          source.role_id,
          'wagon-card'
     from xx_rjd_role_pages source
    where source.page = 'dashboard'
      and not exists (
       select 1
         from xx_rjd_role_pages target
        where target.role_id = source.role_id
          and target.page = 'wagon-card'
   );

commit;
