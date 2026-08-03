<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\OrganizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class OrganizationMiddleware implements MiddlewareInterface
{
    /** @var callable():OrganizationService */
    private $organizations;

    public function __construct(callable $organizations)
    {
        $this->organizations = $organizations;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ($this->organizations)()->sync();
        return $handler->handle($request);
    }
}
