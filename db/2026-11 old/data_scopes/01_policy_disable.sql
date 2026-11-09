BEGIN
    DBMS_RLS.ENABLE_POLICY(
        object_schema => USER,
        object_name   => 'XX_DISLOCATION_RJD',
        policy_name   => 'RJD_DATA_SCOPES',
        enable        => FALSE
    );
END;
/