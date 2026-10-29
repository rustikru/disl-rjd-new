<?php
declare(strict_types=1);
namespace App\Services;

use App\Reports\ReportConfig;

/** Единый каталог страниц, маршрутов, прав, видимости и меню. */
final class PageRegistry
{
    private const CORE = [
        'dashboard' => ['label'=>'Дашборд','routes'=>['/api/dashboard','/api/kpi'],'visibility_group'=>'Основные разделы'],
        'maps' => ['label'=>'Карта','routes'=>['/maps'],'visibility_group'=>'Основные разделы','navigation'=>['group'=>'analytics','order'=>90,'url'=>'/maps','target'=>'_blank']],
        'import' => ['label'=>'Загрузка справок','routes'=>['/import','/api/import'],'visibility_group'=>'Основные разделы','navigation'=>['group'=>'import','order'=>10,'url'=>'/import','label'=>'Загрузка справки РЖД']],
        'directories' => ['label'=>'Справочники','routes'=>['/directories'],'visibility_group'=>'Основные разделы','navigation'=>['group'=>'directories','order'=>10,'url'=>'/directories/'],'directory_navigation'=>[
            ['key'=>'norms','label'=>'Нормативы','url'=>'/directories/norms','order'=>10],
            ['key'=>'stations','label'=>'Станции и координаты','url'=>'/directories/stations','order'=>20],
        ]],
        'directories_edit' => ['label'=>'Ведение справочников','routes'=>['/directories/norms/save','/directories/norms/delete','/directories/stations/save','/directories/stations/delete'],'visibility_group'=>'Основные разделы','implies'=>['directories']],
        'profile' => ['label'=>'Профиль','routes'=>['/profile'],'visibility_group'=>'Основные разделы','user_menu'=>['order'=>10,'url'=>'/profile','label'=>'Мой профиль']],
        'admin' => ['label'=>'Администрирование','routes'=>['/admin'],'visibility_group'=>'Администрирование'],
        'users' => ['label'=>'Пользователи','routes'=>['/admin/users'],'visibility_group'=>'Администрирование','admin_navigation'=>['group'=>'Пользователи и доступ','order'=>10,'url'=>'/admin/users']],
        'roles' => ['label'=>'Роли','routes'=>['/admin/roles'],'visibility_group'=>'Администрирование','admin_navigation'=>['group'=>'Пользователи и доступ','order'=>20,'url'=>'/admin/roles']],
        'organizations' => ['label'=>'Организации','routes'=>['/admin/directories/organizations'],'visibility_group'=>'Администрирование','admin_navigation'=>['group'=>'Справочники','order'=>10,'url'=>'/admin/directories/organizations']],
        'settings' => ['label'=>'Настройки приложения','routes'=>['/admin/settings'],'visibility_group'=>'Администрирование','super_only'=>true,'admin_navigation'=>['group'=>'Настройки системы','order'=>10,'url'=>'/admin/settings']],
        'page-visibility' => ['label'=>'Видимость страниц','routes'=>['/admin/page-visibility'],'visibility_group'=>'Администрирование','super_only'=>true,'admin_navigation'=>['group'=>'Настройки системы','order'=>20,'url'=>'/admin/page-visibility','active_key'=>'visibility']],
        'report-mailings' => ['label'=>'Рассылки','routes'=>['/profile/report-mailings','/admin/report-mailings'],'visibility_group'=>'Администрирование','user_menu'=>['order'=>20,'url'=>'/profile/report-mailings','label'=>'Рассылка отчётов'],'admin_navigation'=>['group'=>'Рассылки','order'=>10,'url'=>'/admin/report-mailings','super_only'=>true]],
        'sessions' => ['label'=>'Активные сессии','routes'=>['/admin/sessions'],'visibility_group'=>'Администрирование','super_only'=>true,'admin_navigation'=>['group'=>'Контроль и обслуживание','order'=>10,'url'=>'/admin/sessions']],
        'audit' => ['label'=>'Журнал действий','routes'=>['/admin/audit'],'visibility_group'=>'Администрирование','super_only'=>true,'admin_navigation'=>['group'=>'Контроль и обслуживание','order'=>20,'url'=>'/admin/audit']],
    ];

    private const GROUPS = [
        'movement'=>['label'=>'Движение вагонов','order'=>10], 'analytics'=>['label'=>'Аналитика','order'=>20],
        'downtime'=>['label'=>'Простои и оборот','order'=>30], 'registry'=>['label'=>'Реестр','order'=>40],
        'directories'=>['label'=>'','order'=>50], 'import'=>['label'=>'Импорт','order'=>60],
    ];

    public static function all(): array
    {
        $pages = self::CORE;
        foreach (ReportConfig::all() as $code => $report) {
            if (empty($report['navigation'])) continue;
            $pages[$code] = [
                'label'=>(string)($report['name'] ?? $code),
                'routes'=>array_values(array_filter([$report['filters_endpoint']??null,$report['summary_endpoint']??null,$report['detail_endpoint']??null])),
                'visibility_group'=>'Отчёты', 'navigation'=>$report['navigation'], 'legacy_permission'=>'dashboard',
            ];
        }
        return $pages;
    }

    public static function labels(): array { return array_map(static fn(array $p): string => (string)$p['label'], self::all()); }
    /** Страницы, доступные роли ADMIN. Разделы super_only доступны только SUPER. */
    public static function adminPermissions(): array
    {
        return array_keys(array_filter(
            self::all(),
            static fn(array $definition): bool => empty($definition['super_only'])
        ));
    }
    public static function groups(): array { return self::GROUPS; }
    public static function availabilityKeys(string $code): array
    {
        $definition=self::all()[$code]??[];
        return array_values(array_unique(array_filter([$code,$definition['legacy_permission']??null])));
    }
    public static function availabilityKeysForPath(string $path,array $query=[]): array
    {
        $page=self::pageForPath($path,$query); if($page===null) return [];
        $keys=self::availabilityKeys($page);
        if(str_starts_with('/'.ltrim($path,'/'),'/admin')) $keys[]='admin';
        return array_values(array_unique($keys));
    }

    public static function pageForPath(string $path, array $query = []): ?string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/detail') { $ctx=(string)($query['ctx']??''); return isset(self::all()[$ctx]) ? $ctx : null; }
        if ($path === '/' || $path === '/wagon') return null;
        $matches=[];
        foreach (self::all() as $code=>$definition) foreach ((array)($definition['routes']??[]) as $prefix) {
            $prefix=rtrim((string)$prefix, '/');
            if ($prefix!=='' && ($path===$prefix || str_starts_with($path,$prefix.'/'))) $matches[]=['code'=>$code,'length'=>strlen($prefix)];
        }
        usort($matches, static fn(array $a,array $b): int => $b['length'] <=> $a['length']);
        return $matches[0]['code'] ?? null;
    }

    public static function expandPermissions(array $pages): array
    {
        $expanded=array_values(array_unique(array_map('strval',$pages))); $definitions=self::all();
        foreach ($expanded as $code) foreach ((array)($definitions[$code]['implies']??[]) as $implied) $expanded[]=$implied;
        if (in_array('dashboard',$expanded,true)) foreach ($definitions as $code=>$definition)
            if (($definition['legacy_permission']??null)==='dashboard') $expanded[]=$code;
        return array_values(array_unique($expanded));
    }
}
