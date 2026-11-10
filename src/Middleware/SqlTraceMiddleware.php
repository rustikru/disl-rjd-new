<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Database\QueryLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SqlTraceMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!QueryLogger::isEnabled()) {
            return $handler->handle($request);
        }

        $started = microtime(true);
        QueryLogger::startRequest($request->getMethod(), $request->getUri()->getPath());

        try {
            $response = $handler->handle($request);
            QueryLogger::finishRequest($response->getStatusCode(), (microtime(true) - $started) * 1000);
            return $response;
        } catch (\Throwable $error) {
            QueryLogger::finishRequest('ERROR', (microtime(true) - $started) * 1000, $error);
            throw $error;
        }
    }
}
