<?php
declare(strict_types=1);
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Services\PageRegistry;

final class SettingsController
{
    public function __construct(private array $config) {}

    public function page(ServerRequestInterface $request, ResponseInterface $response, bool $visibility = false): ResponseInterface
    {
        if (empty($_SESSION['user']['is_super'])) {
            return $response->withStatus(403);
        }
        $file = dirname(__DIR__, 2) . '/rjd_config.php';
        $values = require $file;
        $error = null;
        $success = null;
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            try {
                if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) ($body['csrf_token'] ?? ''))) {
                    throw new \RuntimeException('Обновите страницу и повторите сохранение.');
                }
                $updated = $values;
                if ($visibility) {
                    $enabled = array_filter((array) ($body['enabled'] ?? []), 'is_string');
                    $disabled = array_diff(array_keys(PageRegistry::PAGES), $enabled);
                    $custom = preg_split('/\R/', (string) ($body['custom'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                    $updated['disabled_pages'] = array_values(array_unique(array_filter(array_map('trim', array_merge(array_keys(array_intersect_key(PageRegistry::PAGES, array_flip($disabled))), $custom)))));
                } else {
                    foreach ($values as $key => $value) {
                        if ($key === 'disabled_pages') continue;
                        $input = (string) ($body['settings'][$key] ?? '');
                        if (in_array($key, \App\Services\ProjectPath::SETTINGS, true)) \App\Services\ProjectPath::resolve($input);
                        if ($key === 'db_pass' && $input === '') continue;
                        $updated[$key] = is_bool($value) ? $input === '1' : (is_int($value) ? filter_var($input, FILTER_VALIDATE_INT) : $input);
                        if (is_int($value) && $updated[$key] === false) throw new \RuntimeException('Требуется целое число: ' . $key);
                    }
                    if (!in_array($updated['app_timezone'], \DateTimeZone::listIdentifiers(), true)) throw new \RuntimeException('Некорректная часовая зона.');
                }
                $source = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($updated, true) . ";\n";
                $temp = tempnam(dirname($file), '.config-');
                if ($temp === false) throw new \RuntimeException('Не удалось создать файл настроек.');
                try {
                    if (file_put_contents($temp, $source, LOCK_EX) === false || !chmod($temp, fileperms($file) & 0777) || !rename($temp, $file)) throw new \RuntimeException('Не удалось сохранить настройки.');
                } finally {
                    if (is_file($temp)) unlink($temp);
                }
                if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
                $values = $updated;
                $success = 'Настройки сохранены. Изменения применяются со следующего запроса.';
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? '';
        $user = $_SESSION['user'];
        ob_start();
        include dirname(__DIR__, 2) . '/templates/admin/settings.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }
}
