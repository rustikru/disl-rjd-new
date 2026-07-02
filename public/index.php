<?php
declare(strict_types=1);

use App\Database\QueryLogger;
use App\Middleware\ErrorHandler;
use Slim\Exception\HttpNotFoundException;

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../src/Config.php';

// Включаем лог SQL-запросов в режиме отладки
if (($_ENV['APP_DEBUG'] ?? '') === 'true') {
    QueryLogger::enable(__DIR__ . '/../tmp/log/sql_debug.log');
}

$app = \Slim\Factory\AppFactory::create();

$isDev = $config['app_env'] === 'development';

$errorMiddleware = $app->addErrorMiddleware($isDev, true, $isDev);

// Централизованный обработчик: JSON для /api/*, HTML для остальных
$errorMiddleware->setDefaultErrorHandler(
    new ErrorHandler($app->getResponseFactory(), $config)
);

// 404 — рендерим шаблон с шапкой приложения
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    function ($request, $exception) use ($app, $config) {
        $response    = $app->getResponseFactory()->createResponse(404);
        $basePath    = $config['base_path'] ?? '';
        $appName     = $config['app_name']  ?? '';
        $user        = $_SESSION['user']    ?? [];
        $headerSub   = '';
        $headerRight = '';

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

$app->addBodyParsingMiddleware();

(require __DIR__ . '/../src/routes.php')($app, $config);

$app->run();
