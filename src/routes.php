<?php
declare(strict_types=1);

use Slim\App;

return function (App $app, array $config): void {


    $db = null;
    $auth = null;
    $organizations = null;

    $getDb = function () use ($config, &$db) {
        return $db ??= \App\Database\DbFactory::create($config);
    };
    $app->add(new \App\Middleware\SessionMiddleware(
        $config['session_name'],
        (bool) $config['session_cookie_secure'],
        $config['base_path'] ?? '',
        $getDb,
        (int) $config['session_lifetime_seconds']
    ));

    $getAuth = function () use ($config, &$auth, $getDb) {
        return $auth ??= new \App\Auth\AuthService($getDb(), $config);
    };

    $getOrganizations = function () use (&$organizations, $getDb) {
        return $organizations ??= new \App\Services\Organizations($getDb());
    };

    // Публичные маршруты
    $app->get('/login', function ($req, $res) use ($config) {
        return (new \App\Controllers\AuthController(null, $config))->showLogin($req, $res);
    });
    // Публичные маршруты
    $app->get('/tmp/admin-mockup.html', function ($req, $res) {
        $file = __DIR__ . '/../tmp/admin-mockup.html';
        if (file_exists($file)) {
            $res->getBody()->write(file_get_contents($file));
            return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
        }
        return $res->withStatus(404)->write('File not found');
    });

    $app->post('/login', function ($req, $res) use ($getAuth, $config) {
        $auth = $getAuth();
        return (new \App\Controllers\AuthController($auth, $config))->handleLogin($req, $res);
    });

    $app->get('/forgot-password', function ($req, $res) use ($getDb, $config) {
        return (new \App\Controllers\UserController($getDb(), $config))->forgotPassword($req, $res);
    });
    $app->post('/forgot-password', function ($req, $res) use ($getDb, $config) {
        return (new \App\Controllers\UserController($getDb(), $config))->sendPasswordReset($req, $res);
    });
    $app->get('/reset-password', function ($req, $res) use ($getDb, $config) {
        return (new \App\Controllers\UserController($getDb(), $config))->resetPasswordForm($req, $res);
    });
    $app->post('/reset-password', function ($req, $res) use ($getDb, $config) {
        return (new \App\Controllers\UserController($getDb(), $config))->resetPassword($req, $res);
    });

    $app->get('/auth/kerberos', function ($req, $res) use ($getAuth, $config) {
        $auth = $getAuth();
        return (new \App\Controllers\AuthController($auth, $config))->handleKerberos($req, $res);
    });

    $app->post('/logout', function ($req, $res) use ($config, $getDb) {
        $body = (array) $req->getParsedBody();
        $csrf = $body['csrf_token'] ?? '';
        if ($csrf === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            return $res->withHeader('Location', ($config['base_path'] ?? '') . '/')->withStatus(302);
        }
        $currentUser = (array) ($_SESSION['user'] ?? []);
        (new \App\Services\AuditLog($getDb()))->write(
            'LOGOUT',
            'USER',
            isset($currentUser['id']) ? (int) $currentUser['id'] : null,
            'Выход из системы',
            isset($currentUser['id']) ? (int) $currentUser['id'] : null,
            (string) ($currentUser['username'] ?? '')
        );
        (new \App\Services\SessionRegistry($getDb(), (int)$config['session_lifetime_seconds']))->revoke(hash('sha256', session_id()));
        session_destroy();
        return $res->withHeader('Location', ($config['base_path'] ?? '') . '/login')->withStatus(302);
    });

    $app->get('/logout', function ($req, $res) use ($config) {
        return $res->withHeader('Location', ($config['base_path'] ?? '') . '/login')->withStatus(302);
    });

    // Отдельная ручная страница: берёт последние XLSX из уже обработанных писем.
    // Маршрут не включён в навигацию, но требует обычной авторизации приложения.
    $app->map(['GET', 'POST'], '/bin/downloads_mail_rjd.php', function ($req, $res) {
        ob_start();
        require __DIR__ . '/../bin/downloads_mail_rjd.php';
        $html = (string) ob_get_clean();
        $res->getBody()->write($html);
        return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
    })->add(new \App\Middleware\AuthMiddleware($config['base_path'] ?? ''));

    // маршруты
    $app->group('', function ($group) use ($config, $getDb, $getOrganizations) {

        // ==========================================
        // WEB VIEW
        // ==========================================

        // Главная страница дашборда
        $group->get('/', function ($req, $res) use ($config, $getDb) {
            return (new \App\Controllers\DashboardController($config, $getDb()))->index($req, $res);
        });

        // Карточка вагона
        $group->get('/wagon', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\DashboardController($config, $getDb()))->wagon($req, $res);
        });

        // Профиль текущего пользователя
        $group->get('/profile', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\UserController($getDb(), $config))->profile($req, $res);
        });

        // Личные настройки рассылки отчётов
        $group->get('/profile/report-mailings', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->settingsPage($req, $res);
        });
        $group->post('/profile/report-mailings/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->save($req, $res);
        });
        $group->post('/profile/report-mailings/active', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->setActive($req, $res);
        });
        $group->post('/profile/report-mailings/delete', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->delete($req, $res);
        });
        $group->post('/profile/report-mailings/run', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->run($req, $res);
        });

        // Страница импорта XLSX
        $group->get('/import', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\ImportController($getDb(), $config))->showForm($req, $res);
        });

        $group->post('/import', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\ImportController($getDb(), $config))->handleUpload($req, $res);
        });

        // карта
        $group->get('/maps', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MapsController($getDb(), $config))->showMaps($req, $res);
        });

        // Администрирование (пользователи и роли)
        $group->map(['GET', 'POST'], '/admin/sessions', function ($req, $res) use ($config, $getDb) {
            return (new \App\Controllers\SessionsController($config, $getDb()))->page($req, $res);
        });
        $group->map(['GET', 'POST'], '/admin/settings', function ($req, $res) use ($config) {
            return (new \App\Controllers\SettingsController($config))->page($req, $res);
        });
        $group->map(['GET', 'POST'], '/admin/page-visibility', function ($req, $res) use ($config) {
            return (new \App\Controllers\SettingsController($config))->page($req, $res, true);
        });
        $group->get('/admin', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->index($req, $res);
        });
        $group->get('/admin/users', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->usersPage($req, $res);
        });
        $group->get('/admin/users/new', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->userCreatePage($req, $res);
        });
        $group->get('/admin/users/{id}/edit', function ($req, $res, $args) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->userEditPage($req, $res, $args);
        });
        $group->get('/admin/roles', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->rolesPage($req, $res);
        });
        $group->get('/admin/roles/new', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->roleCreatePage($req, $res);
        });
        $group->get('/admin/roles/{id}/view', function ($req, $res, $args) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->roleViewPage($req, $res, $args);
        });
        $group->get('/admin/roles/{id}/edit', function ($req, $res, $args) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->roleEditPage($req, $res, $args);
        });
        $group->get('/admin/report-mailings', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->adminPage($req, $res);
        });
        $group->get('/admin/audit', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->auditPage($req, $res);
        });
        $group->post('/admin/report-mailings/run', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->adminRun($req, $res);
        });
        $group->post('/admin/report-mailings/active', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\MailingController($getDb(), $config))->adminSetActive($req, $res);
        });
        // --- Справочники ---
        $group->get('/directories', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->directoriesPage($req, $res);
        });
        $group->get('/directories/', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->directoriesPage($req, $res);
        });
        $group->get('/directories/norms', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->normsPage($req, $res);
        });
        $group->get('/directories/norms/stations', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->normStations($req, $res);
        });
        $group->get('/directories/norms/values', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->normValues($req, $res);
        });
        // Удаление норматива
        $group->post('/directories/norms/delete', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->deleteNorm($req, $res);
        });
        $group->post('/directories/norms/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveNorm($req, $res);
        });
        $group->get('/directories/stations', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->stationsPage($req, $res);
        });
        $group->get('/directories/stations/new', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->stationCreatePage($req, $res);
        });
        $group->get('/directories/stations/{code:[0-9]+}/edit', function ($req, $res, $args) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->stationEditPage($req, $res, $args);
        });
        $group->post('/directories/stations/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveStation($req, $res);
        });
        $group->post('/directories/stations/delete', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->deleteStation($req, $res);
        });

        // --- Нормативы ---
        $group->get('/admin/directories/norms', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->normsPage($req, $res);
        });
        $group->get('/admin/directories/norms/stations', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->normStations($req, $res);
        });
        $group->get('/admin/directories/norms/values', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->normValues($req, $res);
        });
        // Удаление норматива
        $group->post('/admin/directories/norms/delete', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->deleteNorm($req, $res);
        });
        $group->post('/admin/directories/norms/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveNorm($req, $res);
        });
        $group->get('/admin/directories/stations', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->stationsPage($req, $res);
        });
        $group->get('/admin/directories/organizations', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->organizationsPage($req, $res);
        });
        $group->get('/admin/directories/organizations/new', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->organizationCreatePage($req, $res);
        });
        $group->get('/admin/directories/organizations/{id:[0-9]+}/edit', function ($req, $res, $args) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->organizationEditPage($req, $res, $args);
        });
        $group->post('/admin/users', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->createUser($req, $res);
        });
        $group->post('/admin/users/roles', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveUserRoles($req, $res);
        });
        $group->post('/admin/users/organizations', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveUserOrganizations($req, $res);
        });
        $group->post('/admin/users/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveUser($req, $res);
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
        $group->post('/admin/directories/stations/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveStation($req, $res);
        });
        $group->post('/admin/directories/stations/delete', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->deleteStation($req, $res);
        });
        $group->post('/admin/directories/organizations/save', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->saveOrganization($req, $res);
        });
        $group->post('/admin/directories/organizations/active', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\AdminController($getDb(), $config))->toggleOrganization($req, $res);
        });
        $group->post('/organization/select', function ($req, $res) use ($getDb, $config) {
            return (new \App\Controllers\UserController($getDb(), $config))->selectOrganization($req, $res);
        });

        // Детальная страница (статический шаблон)
        $group->get('/detail', function ($req, $res) use ($config) {
            $appName = $config['app_name'] ?? 'Дислокация РЖД';
            $basePath = $config['base_path'] ?? '';
            $user = $_SESSION['user'] ?? ['display_name' => '', 'username' => '', 'auth_source' => ''];
            $reportDefinitions = \App\Reports\ReportConfig::forContext('page');
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

            // Движение вагона по текущему рейсу
            $api->get('/wagon/movement', function ($req, $res) use ($config, $getDb) {
                return (new \App\Controllers\ApiController($getDb()))->wagonMovement($req, $res);
            });

            // --- Главный Дашборд ---
            $api->get('/dashboard', function ($req, $res) use ($getDb) {
                return (new \App\Controllers\ApiController($getDb()))->dashboard($req, $res);
            });
            // --- Загрузка файлов через API ---
            $api->post('/import/file', function ($req, $res) use ($getDb, $config) {
                return (new \App\Controllers\ImportController($getDb(), $config))->handleUploadJson($req, $res);
            });

            // Отчётные GET-маршруты регистрируются из единого ReportConfig.
            foreach (\App\Reports\ReportConfig::all() as $reportCode=>$definition) {
                foreach ((array)($definition['api_handlers']??[]) as $kind=>$method) {
                    $endpoint=$definition[$kind.'_endpoint']??null;
                    if(!$endpoint) continue;
                    $path=substr((string)$endpoint,4); // убрать /api
                    $api->get($path,function($req,$res) use($getDb,$method,$reportCode,$kind){
                        try {
                            \App\Reports\ReportConfig::validateFilters($reportCode,$req->getQueryParams(),$kind!=='filters');
                        } catch (\InvalidArgumentException $error) {
                            $res->getBody()->write(json_encode(['status'=>'error','message'=>$error->getMessage()],JSON_UNESCAPED_UNICODE));
                            return $res->withStatus(422)->withHeader('Content-Type','application/json; charset=utf-8');
                        }
                        return (new \App\Controllers\ApiController($getDb()))->{$method}($req,$res);
                    });
                }
            }
            // --- Карточки KPI ---
            $api->group('/kpi', function ($sub) use ($getDb) {
                $sub->get('/summary', function ($req, $res) use ($getDb) {
                    return (new \App\Controllers\ApiController($getDb()))->kpiSummary($req, $res);
                });
            });
            // --- Экспорт виртуальной таблицы в Excel ---
            $api->post('/export/vt-table', function ($req, $res) use ($getDb) {
                return (new \App\Controllers\ApiController($getDb()))->exportVirtualTable($req, $res);
            });

            // --- Экспорт сложных матриц (шахматок дашборда) в Excel ---
            $api->post('/export/matrix', function ($req, $res) use ($getDb) {
                return (new \App\Controllers\ApiController($getDb()))->exportMatrixTable($req, $res);
            });

        });

    })
        ->add(new \App\Middleware\PageAccessMiddleware($getDb, $config['base_path'] ?? '', $config))
        ->add(new \App\Middleware\OrganizationMiddleware($getOrganizations))
        ->add(new \App\Middleware\AuthMiddleware($config['base_path'] ?? ''));

};
