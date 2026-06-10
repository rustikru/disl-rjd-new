<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    private AuthService $auth;
    private array       $config;

    public function __construct(AuthService $auth, array $config)
    {
        $this->auth   = $auth;
        $this->config = $config;
    }

    /** GET /login */
    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!empty($_SESSION['user'])) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $appName = $this->config['app_name'];
        $error   = $_SESSION['login_error'] ?? null;
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

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] = 'Введите логин и пароль';
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->auth->login($username, $password);

        if ($user === null) {
            $_SESSION['login_error'] = 'Неверный логин или пароль';
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $_SESSION['user'] = $user;
        return $response->withHeader('Location', '/')->withStatus(302);
    }
}
