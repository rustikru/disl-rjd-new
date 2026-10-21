<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Запускает PHP-сессию перед обработкой запроса.
 */
class SessionMiddleware implements MiddlewareInterface
{
    private const SESSION_LIFETIME = 28800; // 8 часов

    private string $sessionName;
    private bool $secureCookie;
    private string $cookiePath;

    public function __construct(string $sessionName, bool $secureCookie = true, string $basePath = '')
    {
        $this->sessionName = $sessionName;
        $this->secureCookie = $secureCookie;
        $this->cookiePath = $basePath !== '' ? rtrim($basePath, '/') . '/' : '/';
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', (string) self::SESSION_LIFETIME);
            ini_set('session.cookie_lifetime', '0');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $this->cookiePath,
                'secure' => $this->secureCookie,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name($this->sessionName);
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $handler->handle($request);
    }
}
