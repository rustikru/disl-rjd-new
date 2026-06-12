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

        $this->connection = oci_connect($config['db_user'], $config['db_pass'], $dsn, 'AL32UTF8');

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

    /** Подставляет bind-значения в SQL для читаемого лога (только для отладки). */
    private static function interpolate(string $sql, array $params): string
    {
        // Сортируем по убыванию длины ключа, чтобы :gf_10 заменялся раньше :gf_1
        $keys = array_keys($params);
        usort($keys, fn($a, $b) => strlen((string)$b) - strlen((string)$a));
        foreach ($keys as $k) {
            $v = $params[$k];
            $key = ltrim((string)$k, ':');
            $quoted = $v === null ? 'NULL'
                : (is_numeric($v) ? (string)$v : "'" . str_replace("'", "''", (string)$v) . "'");
            $sql = preg_replace('/:' . preg_quote($key, '/') . '\b/', $quoted, $sql);
        }
        return $sql;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        if (($_ENV['APP_DEBUG'] ?? '') === 'true') {
            $interpolated = self::interpolate($sql, $params);
            $ts = date('Y-m-d H:i:s');
            file_put_contents(__DIR__ . '/../../sql_debug.log', "[$ts]\n$interpolated\n\n", FILE_APPEND | LOCK_EX);
        }

        $stmt = oci_parse($this->connection, $sql);
        $binds = $params;

        foreach (array_keys($binds) as $key) {
            $bindKey = ':' . ltrim((string) $key, ':');
            oci_bind_by_name($stmt, $bindKey, $binds[$key]);
        }

        oci_execute($stmt, OCI_DEFAULT);

        $rows = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = array_change_key_case($row, CASE_LOWER);
        }
        oci_free_statement($stmt);

        return $rows;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = oci_parse($this->connection, $sql);
        $binds = $params;

        foreach (array_keys($binds) as $key) {
            $bindKey = ':' . ltrim((string) $key, ':');
            oci_bind_by_name($stmt, $bindKey, $binds[$key]);
        }

        $mode = $this->inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS;
        oci_execute($stmt, $mode);
        $count = oci_num_rows($stmt);
        oci_free_statement($stmt);

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
