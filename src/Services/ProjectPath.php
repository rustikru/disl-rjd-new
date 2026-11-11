<?php
declare(strict_types=1);
namespace App\Services;
final class ProjectPath
{
    public static function resolve(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $path) || in_array('..', explode('/', $path), true)) throw new \InvalidArgumentException('Укажите путь относительно корня проекта, например tmp/mail.');
        return dirname(__DIR__, 2) . '/' . $path;
    }
}
