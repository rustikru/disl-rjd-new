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
        $body = (array) $req->getParsedBody();
        $csrf = $body['csrf_token'] ?? '';
        if ($csrf === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            return $res->withHeader('Location', ($config['base_path'] ?? '') . '/')->withStatus(302);
        }
        session_destroy();
        return $res->withHeader('Location', ($config['base_path'] ?? '') . '/login')->withStatus(302);
    });

    $app->get('/logout', function ($req, $res) use ($config) {
        return $res->withHeader('Location', ($config['base_path'] ?? '') . '/login')->withStatus(302);
    });

    // маршруты
    $app->group('', function ($group) use ($config, $getDb) {

        // ==========================================
        // WEB VIEW
        // ==========================================

        // Главная страница дашборда
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

        // Интерактивная карта
        $group->get('/maps', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MapsController($getDb(), $config))->showMaps($req, $res);
        });

        // Администрирование (пользователи и роли)
        $group->get('/admin', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->index($req, $res);
        });
        $group->get('/admin/users', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->usersPage($req, $res);
        });
        $group->get('/admin/roles', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->rolesPage($req, $res);
        });
        $group->post('/admin/users', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->createUser($req, $res);
        });
        $group->post('/admin/users/roles', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveUserRoles($req, $res);
        });
        $group->post('/admin/users/active', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->toggleActive($req, $res);
        });
        $group->post('/admin/users/password', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->resetPassword($req, $res);
        });
        $group->post('/admin/roles', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->createRole($req, $res);
        });
        $group->post('/admin/roles/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveRolePages($req, $res);
        });
        $group->post('/admin/roles/delete', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->deleteRole($req, $res);
        });

        // Детальная страница (статический шаблон)
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


        // ==========================================
        // API (`/api`)
        // ==========================================
        $group->group('/api', function ($api) use ($getDb, $config) {

            // --- Главный Дашборд ---
            $api->get('/dashboard', function ($req, $res) use ($getDb) {
                return (new \App\Controllers\ApiController($getDb()))->dashboard($req, $res);
            });
            // --- Загрузка файлов через API ---
            $api->post('/import/file', function ($req, $res) use ($getDb, $config) {
                return (new \App\Controllers\ImportController($getDb(), $config))->handleUploadJson($req, $res);
            });

            // --- Дислокация РЖД ---
            $api->group('/dislocation', function ($sub) use ($getDb) {
                $sub->get('/filters', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->dislFilters($req, $res);
                });
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->dislSummary($req, $res);
                });
                $sub->get('/detail', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->dislDetail($req, $res);
                });
            });

            // --- Подход (сводная) ---
            $api->group('/approach', function ($sub) use ($getDb) {
                $sub->get('/filters', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->approachFilters($req, $res);
                });
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->approachSummary($req, $res);
                });
                $sub->get('/detail', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->approachDetail($req, $res);
                });

            });

            // --- Отправление ---
            $api->group('/departure', function ($sub) use ($getDb) {
                $sub->get('/filters', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->departureFilters($req, $res);
                });
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->departureSummary($req, $res);
                });
                $sub->get('/detail', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->departureDetail($req, $res);
                });

            });

            // --- Погрузка ---
            $api->group('/loading', function ($sub) use ($getDb) {
                $sub->get('/filters', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->loadingFilters($req, $res);
                });
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->loadingSummary($req, $res);
                });
                $sub->get('/detail', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->loadingDetail($req, $res);
                });
            });

            // --- Простои ---
            $api->group('/downtime', function ($sub) use ($getDb) {
                $sub->get('/filters', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->downtimeFilters($req, $res);
                });
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->downtimeSummary($req, $res);
                });
                $sub->get('/detail', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->downtimeDetail($req, $res);
                });
            });

            // --- Сырьё ---
            $api->group('/raw-material', function ($sub) use ($getDb) {
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->rawSummary($req, $res);
                });
                $sub->get('/detail', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->rawDetail($req, $res);
                });
            });

            // --- Анализ за период ---
            $api->group('/analysis', function ($sub) use ($getDb) {
                $sub->get('/filters', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->analysisFilters($req, $res);
                });
                $sub->get('/period/detail', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->analysisPeriod($req, $res);
                });
            });
            // --- Карточки KPI ---
            $api->group('/kpi', function ($sub) use ($getDb) {
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->kpiSummary($req, $res);
                });
            });

        }); // Конец группы /api

    })
        ->add(new \App\Middleware\PageAccessMiddleware($getDb, $config['base_path'] ?? ''))
        ->add(new \App\Middleware\AuthMiddleware($config['base_path'] ?? ''));

};
