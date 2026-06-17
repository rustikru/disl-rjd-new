<?php
declare(strict_types=1);

use Slim\App;

return function (App $app, array $config): void {

    $app->add(new \App\Middleware\SessionMiddleware($config['session_name']));

    $db = null;
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

    $app->post('/logout', function ($req, $res) use ($config) {
        session_destroy();
        return $res->withHeader('Location', ($config['base_path'] ?? '') . '/login')->withStatus(302);
    });

    $app->get('/logout', function ($req, $res) use ($config) {
        return $res->withHeader('Location', ($config['base_path'] ?? '') . '/login')->withStatus(302);
    });

    // маршруты
    $app->group('', function ($group) use ($config, $getDb) {

        $group->get('/', function ($req, $res) use ($config) {
            return (new \App\Controllers\DashboardController($config))->index($req, $res);
        });

        // Страница импорта XLSX
        $group->get('/import', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\ImportController($getDb(), $config))->showForm($req, $res);
        });

        $group->post('/import', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\ImportController($getDb(), $config))->handleUpload($req, $res);
        });

        $group->post('/api/import/file', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\ImportController($getDb(), $config))->handleUploadJson($req, $res);
        });

        // API эндпоинты
        $group->get('/api/dashboard', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->dashboard($req, $res);
        });

        $group->get('/api/dislocation/filters', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->dislFilters($req, $res);
        });

        $group->get('/api/dislocation/summary', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->dislSummary($req, $res);
        });

        $group->get('/api/dislocation/detail', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->dislDetail($req, $res);
        });
        // Подход сводная 
        $group->get('/api/approach/summary', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->approachSummary($req, $res);
        });

        $group->get('/api/approach/detail', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->approachDetail($req, $res);
        });

        $group->get('/api/approach/filters', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->approachFilters($req, $res);
        });

        // Отправление
        $group->get('/api/departure/filters', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->departureFilters($req, $res);
        });
        $group->get('/api/departure/summary', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->departureSummary($req, $res);
        });
        $group->get('/api/departure/detail', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->departureDetail($req, $res);
        });

        // Погрузка
        $group->get('/api/loading/filters', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->loadingFilters($req, $res);
        });
        $group->get('/api/loading/summary', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->loadingSummary($req, $res);
        });
        $group->get('/api/loading/detail', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->loadingDetail($req, $res);
        });

        // Простои
        $group->get('/api/downtime/filters', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->downtimeFilters($req, $res);
        });
        $group->get('/api/downtime/summary', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->downtimeSummary($req, $res);
        });
        $group->get('/api/downtime/detail', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->downtimeDetail($req, $res);
        });

        // Сырьё
        $group->get('/api/raw-material/summary', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->rawSummary($req, $res);
        });

        $group->get('/api/raw-material/detail', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->rawDetail($req, $res);
        });

        //Анализ за период
        $group->get('/api/analysis/period/detail', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->analysisPeriod($req, $res);
        });

        $group->get('/api/map/stations', function ($req, $res) use ($getDb) {
            return (new \App\Controllers\ApiController($getDb()))->mapStations($req, $res);
        });

        // Страница импорта XLSX
        $group->get('/maps', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MapsController($getDb(), $config))->showMaps($req, $res);
        });



        $group->get('/detail', function ($req, $res) use ($config) {
            $appName = $config['app_name'] ?? 'Метафракс';
            $basePath = $config['base_path'] ?? '';
            $user = $_SESSION['user'] ?? ['display_name' => '', 'username' => '', 'auth_source' => ''];
            ob_start();
            require __DIR__ . '/../templates/detail.php';
            $html = ob_get_clean();
            $res->getBody()->write($html);
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

    })->add(new \App\Middleware\AuthMiddleware($config['base_path'] ?? ''));
};
