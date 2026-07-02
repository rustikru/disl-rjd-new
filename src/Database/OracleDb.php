<?php
declare(strict_types=1);

namespace App\Database;


class OracleDb implements DbInterface
{
    /** @var resource */
    private $connection;

    private bool $inTransaction = false;

    public function __construct(array $config)
    {
        if (!extension_loaded('oci8')) {
            throw new \RuntimeException('Расширение PHP oci8 не установлено.');
        }

        $dsn = sprintf('//%s:%s/%s', $config['db_host'], $config['db_port'] ?: 1521, $config['db_name']);

        $this->connection = @oci_connect($config['db_user'], $config['db_pass'], $dsn, 'AL32UTF8');

        if (!$this->connection) {
            $err = oci_error();
            throw new \RuntimeException('Oracle: ошибка подключения — ' . $err['message']);
        }

        // Устанавливаем формат дат чтобы строки 'YYYY-MM-DD HH24:MI:SS' корректно парсились
        $stmt = oci_parse(
            $this->connection,
            "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS' " .
            "NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS.FF'"
        );
        oci_execute($stmt);
        oci_free_statement($stmt);
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $t0   = QueryLogger::isEnabled() ? microtime(true) : 0.0;
        $stmt = oci_parse($this->connection, $sql);
        if (!$stmt) {
            $err = oci_error($this->connection);
            throw new \RuntimeException('Oracle oci_parse: ' . ($err['message'] ?? 'unknown error'));
        }
        $binds = $params;

        foreach (array_keys($binds) as $key) {
            $bindKey = ':' . ltrim((string) $key, ':');
            if ($binds[$key] === null) {
                oci_bind_by_name($stmt, $bindKey, $binds[$key], 4000, SQLT_CHR);
            } else {
                oci_bind_by_name($stmt, $bindKey, $binds[$key]);
            }
        }

        oci_set_prefetch($stmt, 5000);

        $ok = @oci_execute($stmt, OCI_DEFAULT);
        if (!$ok) {
            $err = oci_error($stmt);
            oci_free_statement($stmt);
            throw new \RuntimeException('Oracle fetchAll: ' . ($err['message'] ?? 'unknown error'));
        }

        $data = [];
        oci_fetch_all($stmt, $data, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC);
        oci_free_statement($stmt);

        $rows = array_map(
            static fn(array $row) => array_change_key_case($row, CASE_LOWER),
            $data
        );

        if ($t0 > 0.0) {
            QueryLogger::log('Oracle', 'fetchAll', $sql, $params, (microtime(true) - $t0) * 1000);
        }

        return $rows;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $t0   = QueryLogger::isEnabled() ? microtime(true) : 0.0;
        $stmt = oci_parse($this->connection, $sql);
        if (!$stmt) {
            $err = oci_error($this->connection);
            throw new \RuntimeException('Oracle oci_parse: ' . ($err['message'] ?? 'unknown error'));
        }
        $binds = $params;

        foreach (array_keys($binds) as $key) {
            $bindKey = ':' . ltrim((string) $key, ':');
            // NULL-значение: OCI8 не может определить длину по умолчанию (-1),
            // поэтому явно указываем SQLT_CHR с ограничением 4000.
            if ($binds[$key] === null) {
                oci_bind_by_name($stmt, $bindKey, $binds[$key], 4000, SQLT_CHR);
            } else {
                oci_bind_by_name($stmt, $bindKey, $binds[$key]);
            }
        }

        $mode = $this->inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS;
        $ok = @oci_execute($stmt, $mode);
        if (!$ok) {
            $err = oci_error($stmt);
            oci_free_statement($stmt);
            throw new \RuntimeException('Oracle execute: ' . ($err['message'] ?? 'unknown error'));
        }
        $count = oci_num_rows($stmt);
        oci_free_statement($stmt);

        if ($t0 > 0.0) {
            QueryLogger::log('Oracle', 'execute', $sql, $params, (microtime(true) - $t0) * 1000);
        }

        return $count;
    }

    public function beginTransaction(): void
    {
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        oci_commit($this->connection);
        $this->inTransaction = false;
    }

    public function rollback(): void
    {
        oci_rollback($this->connection);
        $this->inTransaction = false;
    }

    public function limit(int $n): string
    {
        return "FETCH FIRST $n ROWS ONLY";
    }

    public function __destruct()
    {
        if ($this->connection) {
            if ($this->inTransaction) {
                oci_rollback($this->connection);
            }
            oci_close($this->connection);
        }
    }
}
