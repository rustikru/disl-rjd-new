<?php
declare(strict_types=1);

namespace App\Database;

class QueryLogger
{
    private static string $logFile = '';

    public static function enable(string $logFile): void
    {
        self::$logFile = $logFile;
    }

    public static function isEnabled(): bool
    {
        return self::$logFile !== '';
    }

    /**
     * Записывает SQL-запрос с временем выполнения в лог-файл.
     *
     * @param string $driver  'Oracle' | 'Postgres'
     * @param string $method  'fetchAll' | 'fetchOne' | 'execute'
     * @param string $sql     Оригинальный SQL с bind-плейсхолдерами
     * @param array  $params  Параметры запроса
     * @param float  $ms      Время выполнения в миллисекундах
     */
    public static function log(string $driver, string $method, string $sql, array $params, float $ms): void
    {
        if (!self::$logFile) {
            return;
        }

        $ts          = date('Y-m-d H:i:s');
        $duration    = number_format($ms, 1);
        $interpolated = self::interpolate($sql, $params);

        $line = "[$ts] [{$duration}ms] [$driver/$method]\n$interpolated\n\n";

        @mkdir(dirname(self::$logFile), 0777, true);
        file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /** Подставляет bind-значения в SQL для читаемого лога (только для отладки). */
    public static function interpolate(string $sql, array $params): string
    {
        if (!$params) {
            return $sql;
        }

        // Сортируем по убыванию длины ключа, чтобы :gf_10 заменялся раньше :gf_1
        $keys = array_keys($params);
        usort($keys, fn($a, $b) => strlen((string) $b) - strlen((string) $a));

        foreach ($keys as $k) {
            $v      = $params[$k];
            $key    = ltrim((string) $k, ':');
            $quoted = $v === null
                ? 'NULL'
                : (is_numeric($v) ? (string) $v : "'" . str_replace("'", "''", (string) $v) . "'");
            $sql = preg_replace('/:' . preg_quote($key, '/') . '\b/', $quoted, $sql);
        }

        return $sql;
    }
}
