<?php
declare(strict_types=1);
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Services\SessionRegistry;

final class SessionsController
{
    public function __construct(private array $config, private \App\Database\DbInterface $db) {}

    public function page(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!\App\Services\Authorization::isSuper()) return $response->withStatus(403);
        $sessionLifetime = (int)$this->config['session_lifetime_seconds'];
        $registry = new SessionRegistry($this->db, $sessionLifetime);
        $basePath = $this->config['base_path'] ?? '';
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) ($body['csrf_token'] ?? ''))) return $response->withStatus(403);
            $keys = $body['sessions'] ?? [];
            if (!is_array($keys) || array_filter($keys, static fn($key) => !is_string($key))) return $response->withStatus(400);
            $registry->revokeKeys($keys);
            return $response->withHeader('Location', $basePath . '/admin/sessions')->withStatus(303);
        }
        $rows = $registry->active();
        $current = hash('sha256', session_id());
        $users = [];
        foreach ($rows as $key => $row) {
            $group = (string) $row['user_id'] . '|' . (string) $row['ip'];
            if (!isset($users[$group])) $users[$group] = $row + ['sessions' => 0, 'current' => false, 'entries' => []];
            $users[$group]['entries'][] = $row + ['session_key' => $key];
            $users[$group]['sessions']++;
            $users[$group]['current'] = $users[$group]['current'] || $key === $current;
        }
        $appName = $this->config['app_name'] ?? '';
        $user = $_SESSION['user'];
        ob_start();
        include dirname(__DIR__, 2) . '/templates/admin/sessions.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }
}
