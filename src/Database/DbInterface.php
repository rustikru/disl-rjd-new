<?php
declare(strict_types=1);

namespace App\Database;

/**
 * Единый контракт для работы с базой данных.
 * Реализуется PostgresDb и OracleDb.
 */
interface DbInterface
{
    /** Выполнить SELECT, вернуть все строки. */
    public function fetchAll(string $sql, array $params = []): array;

    /** Выполнить SELECT, вернуть первую строку или null. */
    public function fetchOne(string $sql, array $params = []): ?array;

    /** Выполнить INSERT / UPDATE / DELETE, вернуть количество затронутых строк. */
    public function execute(string $sql, array $params = []): int;

    /** Начать транзакцию. */
    public function beginTransaction(): void;

    /** Зафиксировать транзакцию. */
    public function commit(): void;

    /** Откатить транзакцию. */
    public function rollback(): void;

    /** Вернуть SQL-фрагмент ограничения количества строк (LIMIT/FETCH FIRST). */
    public function limit(int $n): string;
}
