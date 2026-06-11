<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../src/Config.php';

$app = \Slim\Factory\AppFactory::create();

// Подробные ошибки только в development-режиме
$app->addErrorMiddleware(
    $config['app_env'] === 'development',
    true,
    true
);

if ($config['base_path'] !== '') {
    $app->setBasePath($config['base_path']);
}

(require __DIR__ . '/../src/routes.php')($app, $config);

$app->run();
