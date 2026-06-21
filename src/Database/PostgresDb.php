<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

class PostgresDb implements DbInterface
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['db_host'],
            $config['db_port'],
            $config['db_name']
        );

        $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $t0   = QueryLogger::isEnabled() ? microtime(true) : 0.0;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if ($t0 > 0.0) {
            QueryLogger::log('Postgres', 'fetchAll', $sql, $params, (microtime(true) - $t0) * 1000);
        }
        return $rows;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $t0   = QueryLogger::isEnabled() ? microtime(true) : 0.0;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($t0 > 0.0) {
            QueryLogger::log('Postgres', 'fetchOne', $sql, $params, (microtime(true) - $t0) * 1000);
        }
        return $row ?: null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $t0   = QueryLogger::isEnabled() ? microtime(true) : 0.0;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        if ($t0 > 0.0) {
            QueryLogger::log('Postgres', 'execute', $sql, $params, (microtime(true) - $t0) * 1000);
        }
        return $count;
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function limit(int $n): string
    {
        return "LIMIT $n";
    }
}
