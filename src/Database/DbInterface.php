<?php
declare(strict_types=1);

namespace App\Database;

/**
 * Единый контракт для работы с базой данных.
 * Реализуется PostgresDb и OracleDb.
 */
interface DbInterface
{
    /**
     * Выполнить SELECT, вернуть все строки как массивы.
     *
     * @param  array<string, mixed> $params  именованные параметры (:name => value)
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * Выполнить SELECT, вернуть первую строку или null.
     *
     * @param  array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array;

    /**
     * Выполнить INSERT / UPDATE / DELETE, вернуть количество затронутых строк.
     *
     * @param  array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int;
}
