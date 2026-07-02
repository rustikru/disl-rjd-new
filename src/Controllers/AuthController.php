<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    private ?AuthService $auth;
    private array        $config;

    public function __construct(?AuthService $auth, array $config)
    {
        $this->auth   = $auth;
        $this->config = $config;
    }

    /** GET /login */
    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!empty($_SESSION['user'])) {
            return $response->withHeader('Location', ($this->config['base_path'] ?? '') . '/')->withStatus(302);
        }

        $appName  = $this->config['app_name'];
        $basePath = $this->config['base_path'] ?? '';
        $error    = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        ob_start();
        require __DIR__ . '/../../templates/login.php';
        $response->getBody()->write(ob_get_clean());

        return $response;
    }

    /** POST /login */
    public function handleLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body     = (array) $request->getParsedBody();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        $base = $this->config['base_path'] ?? '';

        $csrf = $body['csrf_token'] ?? '';
        if ($csrf === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            $_SESSION['login_error'] = 'Ошибка запроса. Попробуйте снова.';
            return $response->withHeader('Location', $base . '/login')->withStatus(302);
        }

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] = 'Введите логин и пароль';
            return $response->withHeader('Location', $base . '/login')->withStatus(302);
        }

        if ($this->auth === null) {
            $_SESSION['login_error'] = 'Сервис авторизации недоступен';
            return $response->withHeader('Location', $base . '/login')->withStatus(302);
        }

        $user = $this->auth->login($username, $password);

        if ($user === null) {
            $_SESSION['login_error'] = 'Неверный логин или пароль';
            return $response->withHeader('Location', $base . '/login')->withStatus(302);
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        return $response->withHeader('Location', $base . '/')->withStatus(302);
    }
}
