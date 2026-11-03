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

\App\Services\ConfigSchema::setCurrent($config);
return $config;
