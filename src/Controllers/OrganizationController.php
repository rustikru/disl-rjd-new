<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\OrganizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OrganizationController
{
    private OrganizationService $organizations;
    private string $basePath;

    public function __construct(OrganizationService $organizations, string $basePath = '')
    {
        $this->organizations = $organizations;
        $this->basePath = $basePath;
    }

    public function select(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $csrf = (string) ($body['csrf_token'] ?? '');
        if ($csrf === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
            return $response->withStatus(403);
        }

        $organizationId = (int) ($body['organization_id'] ?? 0);
        if ($organizationId <= 0 || !$this->organizations->set($organizationId)) {
            return $response->withStatus(403);
        }

        $returnUrl = (string) ($body['return_url'] ?? '/');
        if (!$this->isLocalUrl($returnUrl)) {
            $returnUrl = $this->basePath . '/';
        }

        return $response->withHeader('Location', $returnUrl)->withStatus(302);
    }

    private function isLocalUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return false;
        }
        return str_starts_with($parts['path'] ?? '', '/');
    }
}
