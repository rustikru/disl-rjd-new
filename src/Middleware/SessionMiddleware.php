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

    public function __construct(string $sessionName)
    {
        $this->sessionName = $sessionName;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->sessionName);
            session_start();
        }
        return $handler->handle($request);
    }
}
