-- Скрытая системная роль с полным доступом и единственный пользователь этой роли.
-- Скрипт можно выполнять повторно: существующие записи не дублируются.

declare
   l_role_id number;
   l_user_id number;
   l_org_id  number;
begin
   select min(id) keep(dense_rank first order by is_active desc,
    id)
     into l_org_id
     from xx_rjd_organizations;

   if l_org_id is null then
      raise_application_error(
         -20001,
         'Сначала создайте хотя бы одну организацию'
      );
   end if;
   begin
      select id
        into l_role_id
        from xx_rjd_roles
       where code = 'SUPER';
   exception
      when no_data_found then
         insert into xx_rjd_roles (
            code,
            name,
            description,
            is_system
         ) values
            ( 'SUPER',
              'Полный администратор',
              'Полный доступ к системе',
              1 );

         select id
           into l_role_id
           from xx_rjd_roles
          where code = 'SUPER';
   end;

   begin
      select id
        into l_user_id
        from xx_rjd_users
       where username = 'super_user';

      update xx_rjd_users
         set
         is_active = 1
       where id = l_user_id;
   exception
      when no_data_found then
         insert into xx_rjd_users (
            username,
            display_name,
            email,
            password_hash,
            is_active,
            organization_id
         ) values
            ( 'super_user',
              'Полный администратор',
              null,
              '$2y$12$Yn4W1jHPEX.vq4UJSUgGxuvmY6rdxmbufMp/35cw.I3XJhDeWVXn.',
              1,
              l_org_id );

         select id
           into l_user_id
           from xx_rjd_users
          where username = 'super_user';
   end;

    -- Роль SUPER может принадлежать только системному пользователю.
   delete from xx_rjd_user_roles
    where role_id = l_role_id
      and user_id <> l_user_id;

   merge into xx_rjd_user_roles target
   using (
      select l_user_id as user_id,
             l_role_id as role_id
        from dual
   ) source on ( target.user_id = source.user_id
      and target.role_id = source.role_id )
   when not matched then
   insert (
      user_id,
      role_id )
   values
      ( source.user_id,
        source.role_id );

   commit;
end;
/