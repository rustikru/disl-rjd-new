<?php
declare(strict_types=1);

use Slim\Exception\HttpNotFoundException;

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../src/Config.php';

$app = \Slim\Factory\AppFactory::create();

// 1. Сначала СОЗДАЕМ Middleware ошибок (вы этот шаг случайно пропустили)
$errorMiddleware = $app->addErrorMiddleware(
    $config['app_env'] === 'development',
    true,
    true
);

// 2. Теперь НАСТРАИВАЕМ перехват 404 ошибки
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    function ($request, $exception) use ($app) {
        $response = $app->getResponseFactory()->createResponse(404);

        // Включаем буферизацию вывода и подключаем ваш шаблон
        ob_start();
        require __DIR__ . '/../templates/404.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }
);

if ($config['base_path'] !== '') {
    $app->setBasePath($config['base_path']);
}

(require __DIR__ . '/../src/routes.php')($app, $config);

$app->run();