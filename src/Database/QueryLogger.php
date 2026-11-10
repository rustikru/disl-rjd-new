<?php
declare(strict_types=1);

namespace App\Database;

class QueryLogger
{
    private static string $logFile = '';
    private static string $requestId = '';
    private static string $requestMethod = '';
    private static string $requestPath = '';
    private static int $queryCount = 0;
    private static float $queryMs = 0.0;

    public static function enable(string $logFile): void
    {
        self::$logFile = $logFile;
    }

    public static function isEnabled(): bool
    {
        return self::$logFile !== '';
    }

    public static function startRequest(string $method, string $path): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::$requestId = substr(bin2hex(random_bytes(8)), 0, 12);
        self::$requestMethod = strtoupper($method);
        self::$requestPath = $path;
        self::$queryCount = 0;
        self::$queryMs = 0.0;

        self::write(
            '[' . date('Y-m-d H:i:s') . ']'
            . ' [request:' . self::$requestId . ']'
            . ' [' . self::$requestMethod . ' ' . self::$requestPath . ']'
            . " [HTTP/start]\n\n"
        );
    }

    public static function finishRequest(int|string $status, float $ms, ?\Throwable $error = null): void
    {
        if (!self::isEnabled() || self::$requestId === '') {
            return;
        }

        $line =
            '[' . date('Y-m-d H:i:s') . ']'
            . ' [request:' . self::$requestId . ']'
            . ' [' . self::$requestMethod . ' ' . self::$requestPath . ']'
            . ' [HTTP/' . $status . ']'
            . ' [' . number_format($ms, 1, '.', '') . 'ms]'
            . ' [sql:' . self::$queryCount . ' queries, '
            . number_format(self::$queryMs, 1, '.', '') . "ms]\n";
        if ($error !== null) {
            $file = $error->getFile();
            $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
            if (str_starts_with($file, $root)) {
                $file = substr($file, strlen($root));
            }
            $line .= 'Exception: ' . $error::class . ' ' . $error->getMessage()
                . ' (' . $file . ':' . $error->getLine() . ")\n";
        }
        self::write($line . "\n");

        self::$requestId = '';
        self::$requestMethod = '';
        self::$requestPath = '';
    }

    /**
     * Записывает SQL-запрос с временем выполнения в лог-файл.
     *
     * @param string $driver  Драйвер БД (`Oracle`)
     * @param string $method  'fetchAll' | 'fetchOne' | 'execute'
     * @param string $sql     Оригинальный SQL с bind-плейсхолдерами
     * @param array  $params  Параметры запроса
     * @param float  $ms      Время выполнения в миллисекундах
     */
    public static function log(
        string $driver,
        string $method,
        string $sql,
        array $params,
        float $ms,
        array|false|null $error = null
    ): void
    {
        if (!self::$logFile) {
            return;
        }

        self::$queryCount++;
        self::$queryMs += $ms;

        $parts = [
            '[' . date('Y-m-d H:i:s') . ']',
            '[request:' . (self::$requestId ?: 'console') . ']',
        ];
        if (self::$requestPath !== '') {
            $parts[] = '[' . self::$requestMethod . ' ' . self::$requestPath . ']';
        }
        $parts[] = '[' . number_format($ms, 1, '.', '') . 'ms]';
        $parts[] = '[' . $driver . '/' . $method . ($error ? '/ERROR' : '') . ']';

        $line = implode(' ', $parts) . "\n";
        $line .= 'Call: ' . self::callPath() . "\n";
        $line .= trim($sql) . "\n";
        if ($params) {
            $line .= 'Params: ' . json_encode(self::safeParams($params), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
        if ($error) {
            $line .= 'Oracle error: ' . (int) ($error['code'] ?? 0)
                . ' ' . trim((string) ($error['message'] ?? 'unknown error')) . "\n";
        }
        $line .= "\n";

        self::write($line);
    }

    private static function callPath(): string
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $calls = [];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24) as $frame) {
            $class = (string) ($frame['class'] ?? '');
            if ($class === '' || !str_starts_with($class, 'App\\') || $class === self::class) {
                continue;
            }
            $file = (string) ($frame['file'] ?? '');
            if ($file !== '' && str_starts_with($file, $root)) {
                $file = substr($file, strlen($root));
            }
            $call = $class . ($frame['type'] ?? '::') . ($frame['function'] ?? '');
            if ($file !== '') {
                $call .= ' (' . $file . ':' . (int) ($frame['line'] ?? 0) . ')';
            }
            $calls[] = $call;
        }
        return $calls ? implode(' > ', array_reverse($calls)) : 'не определён';
    }

    private static function safeParams(array $params): array
    {
        $safe = [];
        foreach ($params as $key => $value) {
            $name = (string) $key;
            if (preg_match('/pass|password|token|secret|csrf|session|sid/i', $name)) {
                $safe[$name] = '***';
                continue;
            }
            if (is_string($value) && mb_strlen($value) > 300) {
                $safe[$name] = mb_substr($value, 0, 300) . '…';
                continue;
            }
            $safe[$name] = $value;
        }
        return $safe;
    }

    private static function write(string $line): void
    {
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
