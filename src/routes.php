<?php
declare(strict_types=1);

use Slim\App;

return function (App $app, array $config): void {

    $app->add(new \App\Middleware\SessionMiddleware($config['session_name']));

    $db   = null;
    $auth = null;

    $getDb = function () use ($config, &$db) {
        return $db ??= \App\Database\DbFactory::create($config);
    };

    $getAuth = function () use ($config, &$auth, $getDb) {
        return $auth ??= new \App\Auth\AuthService($getDb(), $config);
    };

    // Публичные маршруты
    $app->get('/login', function ($req, $res) use ($getAuth, $config) {
        return (new \App\Controllers\AuthController($getAuth(), $config))->showLogin($req, $res);
    });

    $app->post('/login', function ($req, $res) use ($getAuth, $config) {
        return (new \App\Controllers\AuthController($getAuth(), $config))->handleLogin($req, $res);
    });

    $app->post('/logout', function ($req, $res) {
        session_destroy();
        return $res->withHeader('Location', '/login')->withStatus(302);
    });

    // Защищённые маршруты
    $app->group('', function ($group) use ($config, $getDb) {

        $group->get('/', function ($req, $res) use ($config) {
            return (new \App\Controllers\DashboardController($config))->index($req, $res);
        });

        // Страница импорта XLSX
        $group->get('/import', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ImportController($getDb()))->showForm($req, $res);
        });

        $group->post('/import', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ImportController($getDb()))->handleUpload($req, $res);
        });

        // API эндпоинты
        $group->get('/api/dashboard', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->dashboard($req, $res);
        });

        $group->get('/api/reports', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->reports($req, $res);
        });

        $group->get('/api/dislocation/summary', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->dislocationSummary($req, $res);
        });

        $group->get('/api/dislocation/extended', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->dislocationExtended($req, $res);
        });

        $group->get('/api/approach', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->approach($req, $res);
        });

    })->add(new \App\Middleware\AuthMiddleware());
};
