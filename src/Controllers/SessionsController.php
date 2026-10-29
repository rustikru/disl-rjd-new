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
        if (empty($_SESSION['user']['is_super'])) return $response->withStatus(403);
        $registry = new SessionRegistry($this->db);
        $basePath = $this->config['base_path'] ?? '';
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) ($body['csrf_token'] ?? ''))) return $response->withStatus(403);
            $ids = $body['users'] ?? [];
            if (!is_array($ids) || array_filter($ids, static fn($id) => !is_string($id))) return $response->withStatus(400);
            $registry->revokeUsers($ids);
            return $response->withHeader('Location', $basePath . '/admin/sessions')->withStatus(303);
        }
        $rows = $registry->active();
        $current = hash('sha256', session_id());
        $users = [];
        foreach ($rows as $key => $row) {
            $id = $row['user_id'];
            if (!isset($users[$id])) $users[$id] = $row + ['sessions' => 0, 'ips' => [], 'current' => false, 'entries' => []];
            $users[$id]['entries'][] = $row;
            $users[$id]['sessions']++;
            $users[$id]['ips'][$row['ip']] = true;
            $users[$id]['current'] = $users[$id]['current'] || $key === $current;
        }
        $appName = $this->config['app_name'] ?? '';
        $user = $_SESSION['user'];
        ob_start();
        include dirname(__DIR__, 2) . '/templates/admin/sessions.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }
}
