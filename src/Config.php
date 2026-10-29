<?php
declare(strict_types=1);

$configFile = __DIR__ . '/../rjd_config.php';

if (!file_exists($configFile)) {
    throw new RuntimeException('Не найден файл конфигурации rjd_config.php');
}

$rawConfig = require $configFile;
if (!is_array($rawConfig)) {
    throw new RuntimeException('Файл rjd_config.php должен возвращать массив');
}
$config = \App\Services\ConfigSchema::normalize($rawConfig);
$appTimezone = $config['app_timezone'];
try {
    date_default_timezone_set($appTimezone);
} catch (\Throwable $error) {
    throw new RuntimeException('Некорректная часовая зона app_timezone: ' . $appTimezone, 0, $error);
}

\App\Services\ConfigSchema::setCurrent($config);
return $config;
