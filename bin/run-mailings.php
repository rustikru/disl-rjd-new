<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    require __DIR__ . '/../vendor/autoload.php';
    $config = require __DIR__ . '/../src/Config.php';
    $db = \App\Database\DbFactory::create($config);
    $result = (new \App\Services\MailingScheduler($db, $config))->runDue();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
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
