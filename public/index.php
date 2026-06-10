<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../src/Config.php';

$app = \Slim\Factory\AppFactory::create();

// Подробные ошибки только в development-режиме
$app->addErrorMiddleware(
    displayErrorDetails: $config['app_env'] === 'development',
    logErrors: true,
    logErrorDetails: true
);

(require __DIR__ . '/../src/routes.php')($app, $config);

$app->run();
