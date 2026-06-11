<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Проверяет, что пользователь авторизован.
 * Если нет — перенаправляет на страницу входа.
 */
class AuthMiddleware implements MiddlewareInterface
{
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($_SESSION['user'])) {
            return (new Response())->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }
        return $handler->handle($request);
    }
}
