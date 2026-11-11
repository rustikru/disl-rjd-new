<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$lockFile = __DIR__ . '/../tmp/mailing-worker.lock';
$lockHandle = @fopen($lockFile, 'x');
if ($lockHandle === false) {
    fwrite(STDOUT, "Worker уже запущен или остался lock-файл: {$lockFile}" . PHP_EOL);
    exit(0);
}
fwrite($lockHandle, (string) getmypid());
fclose($lockHandle);
register_shutdown_function(static function () use ($lockFile): void {
    @unlink($lockFile);
});

try {
    require __DIR__ . '/../vendor/autoload.php';
    $config = require __DIR__ . '/../src/Config.php';
    $db = \App\Database\DbFactory::create($config);
    $result = (new \App\Services\MailingScheduler($db, $config))->runDue();
    echo '[' . date('Y-m-d H:i:s') . '] ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($result['errors'] > 0 ? 1 : 0);
} catch (Throwable $error) {
    if (class_exists(\App\Logging\ErrorLogger::class)) {
        $errorId = \App\Logging\ErrorLogger::logThrowable($error, [
            'module' => 'bin/run-mailings.php',
            'function' => 'main',
        ]);
        fwrite(STDERR, 'Не удалось выполнить планировщик рассылок. Код ошибки: ' . $errorId . PHP_EOL);
    } else {
        error_log($error->getMessage());
        fwrite(STDERR, 'Не удалось загрузить приложение.' . PHP_EOL);
    }
    exit(1);
}
