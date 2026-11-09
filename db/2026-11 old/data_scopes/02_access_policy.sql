-- После таблиц, индексов и компиляции основного пакета (pks, затем pkb).
-- Требуется EXECUTE на DBMS_RLS. Выполнить один раз.
begin
    dbms_rls.add_policy(object_schema => user,
        object_name => 'XX_DISLOCATION_RJD', policy_name => 'RJD_DATA_SCOPES',
        function_schema => user,
        policy_function => 'XX_RJD_DISLOCATION_NEW_PKG.DATA_SCOPE_PREDICATE',
        statement_types => 'SELECT', policy_type => dbms_rls.dynamic);
end;
/
