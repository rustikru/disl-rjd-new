<?php
declare(strict_types=1);

// Местное время приложения.
date_default_timezone_set('Asia/Yekaterinburg');

$configFile = __DIR__ . '/../rjd_config.php';

if (!file_exists($configFile)) {
    throw new RuntimeException('Не найден файл конфигурации rjd_config.php');
}

$rawConfig = require $configFile;
if (!is_array($rawConfig)) {
    throw new RuntimeException('Файл rjd_config.php должен возвращать массив');
}
$config = \App\Services\Config\ConfigSchema::normalize($rawConfig);

\App\Services\Config\ConfigSchema::setCurrent($config);
return $config;
