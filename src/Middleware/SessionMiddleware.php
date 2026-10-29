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
    private string $sessionName;
    private bool $secureCookie;
    private string $cookiePath;

    public function __construct(string $sessionName, bool $secureCookie, string $basePath, private ?\Closure $getDb, private int $lifetime)
    {
        $this->sessionName = $sessionName;
        $this->secureCookie = $secureCookie;
        $this->cookiePath = $basePath !== '' ? rtrim($basePath, '/') . '/' : '/';
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', (string) $this->lifetime);
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
        $sync = function () use ($request): void {
            if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user'])) return;
            $registry = new \App\Services\SessionRegistry(($this->getDb)(), $this->lifetime);
            if (!$registry->touch(session_id(), $_SESSION['user'], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''), $request->getHeaderLine('User-Agent'))) {
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        };
        $sync();
        $response = $handler->handle($request);
        $sync();
        return $response;
    }
}
