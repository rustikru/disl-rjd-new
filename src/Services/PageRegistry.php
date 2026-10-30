<?php
declare(strict_types=1);
namespace App\Services;

use App\Reports\ReportConfig;

/** Единый каталог страниц, маршрутов, прав, видимости и меню. */
final class PageRegistry
{
    /** API, которые используются несколькими страницами. */
    private const SHARED_ROUTES = [
        '/api/technical-state/detail' => ['wagon-card'],
    ];

    private const CORE = [
        'dashboard' => ['label' => 'Дашборд', 'routes' => ['/api/dashboard', '/api/kpi'], 'visibility_group' => 'Сайт — основные разделы'],
        'wagon-card' => ['label' => 'Карточка вагона', 'routes' => ['/wagon', '/api/wagon/movement'], 'visibility_group' => 'Сайт — основные разделы'],
        'wagon-detail' => ['label' => 'Детализация по вагону', 'routes' => ['/detail'], 'visibility_group' => 'Сайт — основные разделы'],
        'maps' => ['label' => 'Карта', 'routes' => ['/maps'], 'visibility_group' => 'Сайт — основные разделы', 'navigation' => ['group' => 'analytics', 'order' => 90, 'url' => '/maps', 'target' => '_blank']],
        'import' => ['label' => 'Загрузка справок', 'routes' => ['/import', '/api/import', '/bin/downloads_mail_rjd.php'], 'visibility_group' => 'Сайт — основные разделы', 'navigation' => ['group' => 'import', 'order' => 10, 'url' => '/import', 'label' => 'Загрузка справки РЖД']],
        'directories' => [
            'label' => 'Справочники',
            'routes' => ['/directories', '/admin/directories/norms', '/admin/directories/stations'],
            'visibility_group' => 'Сайт — основные разделы',
            'navigation' => ['group' => 'directories', 'order' => 10, 'url' => '/directories/'],
            'directory_navigation' => [
                ['key' => 'norms', 'label' => 'Нормативы', 'url' => '/directories/norms', 'order' => 10],
                ['key' => 'stations', 'label' => 'Станции и координаты', 'url' => '/directories/stations', 'order' => 20],
            ]
        ],
        'directories_edit' => ['label' => 'Редактирование справочников', 'routes' => ['/directories/norms/save', '/directories/norms/delete', '/directories/stations/save', '/directories/stations/delete', '/admin/directories/norms/save', '/admin/directories/norms/delete', '/admin/directories/stations/save', '/admin/directories/stations/delete'], 'visibility_group' => 'Сайт — основные разделы', 'implies' => ['directories']],
        'profile' => ['label' => 'Мой профиль', 'routes' => ['/profile'], 'visibility_group' => 'Пользователь', 'user_menu' => ['order' => 10, 'url' => '/profile', 'label' => 'Мой профиль']],
        'user-report-mailings' => ['label' => 'Мои рассылки', 'routes' => ['/profile/report-mailings'], 'visibility_group' => 'Пользователь', 'legacy_permission' => 'report-mailings', 'legacy_visibility' => ['report-mailings'], 'user_menu' => ['order' => 20, 'url' => '/profile/report-mailings', 'label' => 'Мои рассылки']],
        'admin' => ['label' => 'Администрирование', 'routes' => ['/admin'], 'visibility_group' => 'Администрирование', 'admin_only' => true, 'derived' => true],
        'users' => ['label' => 'Пользователи', 'routes' => ['/admin/users'], 'visibility_group' => 'Администрирование', 'admin_only' => true, 'admin_navigation' => ['group' => 'Пользователи и доступ', 'order' => 10, 'url' => '/admin/users']],
        'roles' => ['label' => 'Роли', 'routes' => ['/admin/roles'], 'visibility_group' => 'Администрирование', 'admin_only' => true, 'admin_navigation' => ['group' => 'Пользователи и доступ', 'order' => 20, 'url' => '/admin/roles']],
        'organizations' => ['label' => 'Организации', 'routes' => ['/admin/directories/organizations'], 'visibility_group' => 'Администрирование', 'admin_only' => true, 'admin_navigation' => ['group' => 'Справочники', 'order' => 10, 'url' => '/admin/directories/organizations']],
        'settings' => ['label' => 'Настройки приложения', 'routes' => ['/admin/settings'], 'visibility_group' => 'Администрирование', 'super_only' => true, 'admin_navigation' => ['group' => 'Настройки системы', 'order' => 10, 'url' => '/admin/settings']],
        'page-visibility' => ['label' => 'Видимость страниц', 'routes' => ['/admin/page-visibility'], 'visibility_group' => 'Администрирование', 'super_only' => true, 'admin_navigation' => ['group' => 'Настройки системы', 'order' => 20, 'url' => '/admin/page-visibility', 'active_key' => 'visibility']],
        'maintenance' => ['label' => 'Технические работы', 'routes' => ['/admin/maintenance'], 'visibility_group' => 'Администрирование', 'super_only' => true, 'admin_navigation' => ['group' => 'Настройки системы', 'order' => 30, 'url' => '/admin/maintenance']],
        'admin-report-mailings' => ['label' => 'Управление рассылками', 'routes' => ['/admin/report-mailings'], 'visibility_group' => 'Администрирование', 'legacy_visibility' => ['report-mailings'], 'super_only' => true, 'admin_navigation' => ['group' => 'Рассылки', 'order' => 10, 'url' => '/admin/report-mailings']],
        'sessions' => ['label' => 'Активные сессии', 'routes' => ['/admin/sessions'], 'visibility_group' => 'Администрирование', 'super_only' => true, 'admin_navigation' => ['group' => 'Контроль и обслуживание', 'order' => 10, 'url' => '/admin/sessions']],
        'audit' => ['label' => 'Журнал действий', 'routes' => ['/admin/audit'], 'visibility_group' => 'Администрирование', 'super_only' => true, 'admin_navigation' => ['group' => 'Контроль и обслуживание', 'order' => 20, 'url' => '/admin/audit']],
    ];

    private const GROUPS = [
        'movement' => ['label' => 'Движение вагонов', 'order' => 10],
        'analytics' => ['label' => 'Аналитика', 'order' => 20],
        'downtime' => ['label' => 'Простои и оборот', 'order' => 30],
        'registry' => ['label' => 'Реестр', 'order' => 40],
        'directories' => ['label' => '', 'order' => 50],
        'import' => ['label' => 'Импорт', 'order' => 60],
    ];

    public static function all(): array
    {
        $pages = self::CORE;
        foreach (ReportConfig::all() as $code => $report) {
            if (empty($report['navigation']))
                continue;
            $pages[$code] = [
                'label' => (string) ($report['name'] ?? $code),
                'routes' => array_values(array_filter([$report['filters_endpoint'] ?? null, $report['summary_endpoint'] ?? null, $report['detail_endpoint'] ?? null])),
                'visibility_group' => 'Сайт — отчёты',
                'navigation' => $report['navigation'],
            ];
        }
        return $pages;
    }

    public static function labels(): array
    {
        return array_map(static fn(array $p): string => (string) $p['label'], self::all());
    }
    public static function groups(): array
    {
        return self::GROUPS;
    }
    public static function availabilityKeys(string $code): array
    {
        $definition = self::all()[$code] ?? [];
        return array_values(array_unique(array_filter(array_merge(
            [$code, $definition['legacy_permission'] ?? null],
            (array) ($definition['legacy_visibility'] ?? [])
        ))));
    }
    /** Устаревшие коды видимости удаляются из custom-значений после сохранения формы. */
    public static function legacyVisibilityAliases(): array
    {
        $aliases = [];
        foreach (self::all() as $definition) {
            $aliases = array_merge($aliases, (array) ($definition['legacy_visibility'] ?? []));
        }
        return array_values(array_unique(array_filter(array_map('strval', $aliases))));
    }
    public static function availabilityKeysForPath(string $path, array $query = []): array
    {
        $page = self::pageForPath($path, $query);
        if ($page === null)
            return [];
        $keys = self::availabilityKeys($page);
        if (str_starts_with('/' . ltrim($path, '/'), '/admin'))
            $keys[] = 'admin';
        return array_values(array_unique($keys));
    }

    public static function pageForPath(string $path, array $query = []): ?string
    {
        $path = '/' . ltrim($path, '/');
        if ($path === '/')
            return null;
        $matches = [];
        foreach (self::all() as $code => $definition)
            foreach ((array) ($definition['routes'] ?? []) as $prefix) {
                $prefix = rtrim((string) $prefix, '/');
                if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/')))
                    $matches[] = ['code' => $code, 'length' => strlen($prefix)];
            }
        usort($matches, static fn(array $a, array $b): int => $b['length'] <=> $a['length']);
        return $matches[0]['code'] ?? null;
    }

    /** Любое из этих прав разрешает запрос к общему API. */
    public static function accessPages(string $path, array $query = []): array
    {
        $path = '/' . ltrim($path, '/');
        $pages = [];
        $isDetail = false;

        foreach (ReportConfig::all() as $report) {
            $endpoint = rtrim((string) ($report['detail_endpoint'] ?? ''), '/');
            if ($endpoint !== '' && ($path === $endpoint || str_starts_with($path, $endpoint . '/'))) {
                $pages[] = 'wagon-detail';
                $isDetail = true;
                break;
            }
        }

        $page = self::pageForPath($path, $query);
        if (!$isDetail && $page !== null)
            $pages[] = $page;

        foreach (self::SHARED_ROUTES as $prefix => $sharedPages) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/'))
                $pages = array_merge($pages, $sharedPages);
        }
        return array_values(array_unique($pages));
    }

    public static function expandPermissions(array $pages): array
    {
        $expanded = array_values(array_unique(array_map('strval', $pages)));
        $definitions = self::all();
        foreach ($definitions as $code => $definition) {
            $legacyPermission = $definition['legacy_permission'] ?? null;
            if ($legacyPermission !== null && in_array($legacyPermission, $expanded, true))
                $expanded[] = $code;
        }
        foreach ($expanded as $code)
            foreach ((array) ($definitions[$code]['implies'] ?? []) as $implied)
                $expanded[] = $implied;
        return array_values(array_unique($expanded));
    }

    /** Оставляет только те права, которые применимы к системной роли пользователя. */
    public static function forUser(array $pages, array $user): array
    {
        $pages = self::expandPermissions($pages);
        $definitions = self::all();
        $pages = array_values(array_filter($pages, static function (string $code) use ($definitions, $user): bool {
            $definition = $definitions[$code] ?? [];
            if (!empty($definition['derived'])) return false;
            if (!empty($definition['super_only']) && !Authorization::isSuper($user)) return false;
            if (!empty($definition['admin_only']) && !Authorization::isAdmin($user)) return false;
            return true;
        }));
        if (Authorization::isAdmin($user)) {
            foreach ($pages as $code) {
                if (!empty($definitions[$code]['admin_navigation'])) {
                    $pages[] = 'admin';
                    break;
                }
            }
        }
        return array_values(array_unique($pages));
    }
}
