<?php
declare(strict_types=1);

namespace App\Logging;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ErrorLogger
{
    private const HIDDEN_KEYS = [
        'password',
        'password_hash',
        'db_pass',
        'token',
        'csrf_token',
        'authorization',
        'cookie',
    ];

    public static function logThrowable(
        Throwable $error,
        array $context = [],
        ?ServerRequestInterface $request = null
    ): string {
        $errorId = self::makeErrorId();
        $moduleName = (string) ($context['module'] ?? self::moduleFromError($error));
        $functionName = (string) ($context['function'] ?? self::functionFromError($error));
        $params = self::hideSensitive((array) ($context['params'] ?? []));

        $lines = [
            '[' . date('Y-m-d H:i:s') . '] ' . $errorId,
            'Module: ' . $moduleName,
            'Function: ' . $functionName,
            'Error: ' . get_class($error) . ': ' . $error->getMessage(),
            'Location: ' . $error->getFile() . ':' . $error->getLine(),
        ];

        if ($request !== null) {
            $lines[] = 'Request: ' . $request->getMethod() . ' ' . (string) $request->getUri();
        }

        if (!empty($_SESSION['user'])) {
            $user = $_SESSION['user'];
            $userName = $user['display_name'] ?? $user['username'] ?? '';
            if ($userName !== '') {
                $lines[] = 'User: ' . $userName;
            }
        }

        if ($params !== []) {
            $lines[] = 'Params: ' . self::json($params);
        }

        $lines[] = 'Trace:';
        $lines[] = $error->getTraceAsString();
        $lines[] = str_repeat('-', 90);

        self::write(implode(PHP_EOL, $lines) . PHP_EOL);

        return $errorId;
    }

    private static function makeErrorId(): string
    {
        return 'ERR-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    }

    private static function moduleFromError(Throwable $error): string
    {
        $trace = $error->getTrace();
        foreach ($trace as $traceRow) {
            $className = (string) ($traceRow['class'] ?? '');
            if ($className !== '' && !str_starts_with($className, __CLASS__)) {
                return $className;
            }
        }

        return basename($error->getFile());
    }

    private static function functionFromError(Throwable $error): string
    {
        $trace = $error->getTrace();
        foreach ($trace as $traceRow) {
            $functionName = (string) ($traceRow['function'] ?? '');
            $className = (string) ($traceRow['class'] ?? '');
            if ($functionName !== '' && !str_starts_with($className, __CLASS__)) {
                return $functionName;
            }
        }

        return 'unknown';
    }

    private static function write(string $text): void
    {
        $logFile = self::logFile();
        @mkdir(dirname($logFile), 0777, true);
        file_put_contents($logFile, $text, FILE_APPEND | LOCK_EX);
    }

    private static function logFile(): string
    {
        return dirname(__DIR__, 2) . '/tmp/log/errors/' . date('Y-m-d') . '.log';
    }

    private static function hideSensitive(array $data): array
    {
        $cleanData = [];
        foreach ($data as $key => $value) {
            $keyText = strtolower((string) $key);
            if (in_array($keyText, self::HIDDEN_KEYS, true)) {
                $cleanData[$key] = '***';
                continue;
            }

            $cleanData[$key] = is_array($value) ? self::hideSensitive($value) : $value;
        }

        return $cleanData;
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
