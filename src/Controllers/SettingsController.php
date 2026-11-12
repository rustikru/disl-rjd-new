<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Logging\ErrorLogger;
use App\Database\DbInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Services\Navigation\PageRegistry;
use App\Services\Navigation\PageVisibility;
use App\Services\Logging\AuditLog;

final class SettingsController
{
    public function __construct(private array $config, private DbInterface $db) {}

    public function page(ServerRequestInterface $request, ResponseInterface $response, bool $visibility = false): ResponseInterface
    {
        if (!\App\Services\Auth\Authorization::isSuper()) {
            return $response->withStatus(403);
        }
        [$file, $definitions, $values] = $this->load();
        if ($visibility) {
            $values['disabled_pages'] = (new PageVisibility($this->db))->disabledPages();
        }
        $error = null;
        $success = null;
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            try {
                $this->checkCsrf($body);
                $updated = $values;
                if ($visibility) {
                    $enabled = array_filter((array) ($body['enabled'] ?? []), 'is_string');
                    $disabled = array_diff(array_keys(PageRegistry::labels()), $enabled);
                    $custom = preg_split('/\R/', (string) ($body['custom'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                    $custom = array_diff($custom, PageRegistry::legacyVisibilityAliases());
                    $updated['disabled_pages'] = array_values(array_unique(array_filter(array_map('trim', array_merge(array_keys(array_intersect_key(PageRegistry::labels(), array_flip($disabled))), $custom)))));
                    (new PageVisibility($this->db))->save(
                        $updated['disabled_pages'],
                        isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null
                    );
                    (new AuditLog($this->db))->write(
                        'PAGE_VISIBILITY_UPDATE',
                        'PAGE_VISIBILITY',
                        null,
                        'Изменена глобальная видимость страниц; отключено: ' . count($updated['disabled_pages'])
                    );
                } else {
                    $updated = \App\Services\Config\ConfigSchema::normalizeForStorage((array)($body['settings'] ?? []), $values);
                    $this->save($file, $updated);
                }
                $values = $updated;
                $success = 'Настройки сохранены. ';
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (\Throwable $exception) {
                $errorId = ErrorLogger::logThrowable($exception, [
                    'module' => self::class,
                    'function' => 'page',
                    'params' => ['visibility' => $visibility],
                ], $request);
                $error = 'Не удалось сохранить настройки. Код ошибки: ' . $errorId;
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

    public function maintenancePage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!\App\Services\Auth\Authorization::isSuper()) {
            return $response->withStatus(403);
        }
        [$file, $definitions, $values] = $this->load();
        $error = null;
        $success = null;
        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            try {
                $this->checkCsrf($body);
                $keys = [
                    'maintenance_enabled', 'maintenance_start', 'maintenance_end',
                    'maintenance_title', 'maintenance_message',
                ];
                $settings = array_intersect_key((array) ($body['settings'] ?? []), array_flip($keys));
                $updated = \App\Services\Config\ConfigSchema::normalizeForStorage($settings, $values);
                if ($updated['maintenance_title'] === '' || $updated['maintenance_message'] === '') {
                    throw new \InvalidArgumentException('Укажите заголовок и текст сообщения.');
                }
                $this->save($file, $updated);
                $values = $updated;
                $success = 'Настройки технических работ сохранены.';
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            } catch (\Throwable $exception) {
                $errorId = ErrorLogger::logThrowable($exception, [
                    'module' => self::class,
                    'function' => 'maintenancePage',
                ], $request);
                $error = 'Не удалось сохранить настройки технических работ. Код ошибки: ' . $errorId;
            }
        }
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? '';
        $user = $_SESSION['user'];
        $maintenanceStatus = \App\Services\Config\Maintenance::status($values);
        ob_start();
        include dirname(__DIR__, 2) . '/templates/admin/maintenance.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }

    private function load(): array
    {
        $file = dirname(__DIR__, 2) . '/rjd_config.php';
        $storedValues = require $file;
        if (!is_array($storedValues)) throw new \RuntimeException('Файл настроек должен возвращать массив.');
        $definitions = \App\Services\Config\ConfigSchema::definitions();
        $values = \App\Services\Config\ConfigSchema::normalize($storedValues, false);
        return [$file, $definitions, $values];
    }

    private function checkCsrf(array $body): void
    {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) ($body['csrf_token'] ?? ''))) {
            throw new \RuntimeException('Обновите страницу и повторите сохранение.');
        }
    }

    private function save(string $file, array $values): void
    {
        $source = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($values, true) . ";\n";
        $temp = tempnam(dirname($file), '.config-');
        if ($temp === false) throw new \RuntimeException('Не удалось создать файл настроек.');
        try {
            if (file_put_contents($temp, $source, LOCK_EX) === false || !chmod($temp, fileperms($file) & 0777) || !rename($temp, $file)) throw new \RuntimeException('Не удалось сохранить настройки.');
        } finally {
            if (is_file($temp)) unlink($temp);
        }
        if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
    }
}
