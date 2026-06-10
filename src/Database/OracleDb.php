<?php
declare(strict_types=1);

namespace App\Database;

/**
 * Реализация DbInterface для Oracle через расширение OCI8.
 * Требуется PHP-расширение oci8 (php-oci8).
 */
class OracleDb implements DbInterface
{
    /** @var resource */
    private $connection;

    public function __construct(array $config)
    {
        if (!extension_loaded('oci8')) {
            throw new \RuntimeException('Расширение PHP oci8 не установлено.');
        }

        // Формат строки подключения Oracle: //host:port/service_name
        $dsn = sprintf('//%s:%s/%s', $config['db_host'], $config['db_port'] ?: 1521, $config['db_name']);

        $this->connection = oci_connect($config['db_user'], $config['db_pass'], $dsn, 'AL32UTF8');

        if (!$this->connection) {
            $err = oci_error();
            throw new \RuntimeException('Oracle: ошибка подключения — ' . $err['message']);
        }
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt  = oci_parse($this->connection, $sql);
        $binds = $params; // копия для передачи по ссылке в oci_bind_by_name

        foreach (array_keys($binds) as $key) {
            $bindKey = ':' . ltrim((string) $key, ':');
            oci_bind_by_name($stmt, $bindKey, $binds[$key]);
        }

        oci_execute($stmt);

        $rows = [];
        while ($row = oci_fetch_assoc($stmt)) {
            // Oracle возвращает имена колонок в верхнем регистре → приводим к нижнему
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
        $stmt  = oci_parse($this->connection, $sql);
        $binds = $params;

        foreach (array_keys($binds) as $key) {
            $bindKey = ':' . ltrim((string) $key, ':');
            oci_bind_by_name($stmt, $bindKey, $binds[$key]);
        }

        oci_execute($stmt);
        $count = oci_num_rows($stmt);
        oci_free_statement($stmt);

        return $count;
    }

    public function __destruct()
    {
        if ($this->connection) {
            oci_close($this->connection);
        }
    }
}
