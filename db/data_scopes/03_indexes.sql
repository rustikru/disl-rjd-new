-- Выполнить до включения политики из 02_access_policy.sql.
create index rjd_scope_rules_match_ix on xx_rjd_data_scope_rules
    (scope_id, organization_id, effect, cargo_code, station_code);
