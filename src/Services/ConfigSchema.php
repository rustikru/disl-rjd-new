<?php
declare(strict_types=1);
namespace App\Services;

/** Единая схема типов, defaults, группировки и валидации настроек. */
final class ConfigSchema
{
    private static ?array $current = null;
    public static function definitions(): array
    {
        return [
            'app_env'=>self::d('enum','development','Приложение',['development','production']),
            'app_debug'=>self::d('bool',false,'Приложение'), 'app_name'=>self::d('string','Дислокация РЖД','Приложение'),
            'app_timezone'=>self::d('timezone','Asia/Yekaterinburg','Приложение'), 'base_path'=>self::d('string','','Приложение'),
            'dislocation_stale_hours'=>self::d('int',8,'Приложение',null,1,720), 'disabled_pages'=>self::d('array',[],'Видимость'),
            'maintenance_enabled'=>self::d('bool',false,'Технические работы',label:'Режим технических работ'),
            'maintenance_start'=>self::d('datetime','','Технические работы',label:'Начало',help:'Оставьте пустым, чтобы включить сразу.'),
            'maintenance_end'=>self::d('datetime','','Технические работы',label:'Окончание',help:'Оставьте пустым для бессрочного режима.'),
            'maintenance_title'=>self::d('string','Проводятся технические работы','Технические работы',label:'Заголовок'),
            'maintenance_message'=>self::d('string','Сайт временно недоступен. Мы проводим техническое обслуживание и скоро вернёмся.','Технические работы',label:'Текст'),
            'db_host'=>self::d('string','localhost','База данных'), 'db_port'=>self::d('string','1521','База данных'),
            'db_name'=>self::d('string','','База данных'), 'db_user'=>self::d('string','','База данных'), 'db_pass'=>self::d('secret','','База данных'),
            'ad_debug'=>self::d('bool',false,'LDAP'), 'ad_enabled'=>self::d('bool',false,'LDAP'), 'ad_host'=>self::d('string','','LDAP'),
            'ad_domain'=>self::d('string','','LDAP'), 'ad_base_dn'=>self::d('string','','LDAP'),
            'kerberos_enabled'=>self::d('bool',false,'Kerberos'), 'kerberos_mode'=>self::d('enum','local','Kerberos',['local','server']),
            'kerberos_server_key'=>self::d('string','REMOTE_USER','Kerberos'), 'kerberos_local_username'=>self::d('string','administrator','Kerberos'),
            'kerberos_strip_realm'=>self::d('bool',true,'Kerberos'), 'kerberos_auto_create_user'=>self::d('bool',true,'Kerberos'),
            'report_mail_enabled'=>self::d('bool',false,'Почта и отчёты'), 'mail_mode'=>self::d('enum','file','Почта и отчёты',['file','oracle']),
            'report_storage_dir'=>self::d('path','tmp','Почта и отчёты'), 'report_mail_memory_limit'=>self::d('memory','512M','Почта и отчёты'),
            'report_mail_sender'=>self::d('string','noreply@metafrax.ru','Почта и отчёты'),
            'system_mail_subject'=>self::d('string','ИС «МТФ-Дислокация»','Почта и отчёты',label:'Тема системных писем',help:'Добавляется в начало темы системного письма. На рассылки отчётов не влияет.'),
            'password_reset_enabled'=>self::d('bool',true,'Безопасность и сессии'),
            'password_reset_ttl_minutes'=>self::d('int',30,'Безопасность и сессии',null,5,1440),
            'password_reset_storage_dir'=>self::d('path','tmp/password-reset','Безопасность и сессии'),
            'session_name'=>self::d('string','disl_session','Безопасность и сессии'),
            'session_cookie_secure'=>self::d('bool',null,'Безопасность и сессии'),
            'session_lifetime_seconds'=>self::d('int',28800,'Безопасность и сессии',null,300,2592000),
            'auth_log_file'=>self::d('path','tmp/auth_debug.log','Журналирование'),
            'ldap_log_file'=>self::d('path','tmp/ldap_debug.log','Журналирование'),
        ];
    }

    private static function d(string $type, mixed $default, string $section, ?array $choices=null, ?int $min=null, ?int $max=null, ?string $label=null, ?string $help=null): array
    { return compact('type','default','section','choices','min','max','label','help'); }

    public static function defaults(): array
    { return array_map(static fn(array $d): mixed => $d['default'], self::definitions()); }

    public static function setCurrent(array $config): void { self::$current=$config; }
    public static function current(): array { return self::$current ?? []; }

    public static function normalize(array $raw, bool $resolvePaths=true): array
    {
        $result=[];
        foreach(self::definitions() as $key=>$definition){
            $value=array_key_exists($key,$raw)?$raw[$key]:$definition['default'];
            if($key==='session_cookie_secure' && $value===null) $value=(string)($raw['app_env']??'development')!=='development';
            $result[$key]=self::normalizeValue($key,$value,$definition,$resolvePaths);
        }
        self::validateRelations($result);
        return $result;
    }

    public static function normalizeForStorage(array $input, array $current): array
    {
        $candidate=array_replace(self::defaults(),$current);
        foreach(self::definitions() as $key=>$definition){
            if($key==='disabled_pages') continue;
            if(!array_key_exists($key,$input)) continue;
            if($definition['type']==='secret' && (string)($input[$key]??'')==='') continue;
            $candidate[$key]=$input[$key];
        }
        return self::normalize($candidate,false);
    }

    private static function normalizeValue(string $key,mixed $value,array $d,bool $resolvePaths): mixed
    {
        $type=$d['type'];
        if($type==='bool') $value=is_bool($value)?$value:in_array(strtolower((string)$value),['1','true','yes','on'],true);
        elseif($type==='int') { if(filter_var($value,FILTER_VALIDATE_INT)===false) throw new \InvalidArgumentException("Требуется целое число: $key"); $value=(int)$value; }
        elseif($type==='array') $value=array_values(array_unique(array_filter(array_map(static fn($v)=>strtolower(trim((string)$v)),(array)$value))));
        else $value=trim((string)$value);
        if($d['choices']!==null && !in_array($value,$d['choices'],true)) throw new \InvalidArgumentException("Недопустимое значение настройки $key");
        if($d['min']!==null && $value<$d['min'] || $d['max']!==null && $value>$d['max']) throw new \InvalidArgumentException("Настройка $key вне допустимого диапазона");
        if($type==='timezone' && !in_array($value,\DateTimeZone::listIdentifiers(),true)) throw new \InvalidArgumentException('Некорректная часовая зона.');
        if($type==='datetime' && $value!=='') {
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value);
            $errors=\DateTimeImmutable::getLastErrors();
            if(!$date || ($errors!==false && ($errors['warning_count']>0 || $errors['error_count']>0)) || $date->format('Y-m-d\TH:i')!==$value) throw new \InvalidArgumentException("Некорректные дата и время: $key");
        }
        if($type==='memory' && !preg_match('/^\d+[KMG]?$/i',$value)) throw new \InvalidArgumentException("Некорректный лимит памяти: $key");
        if($type==='path') { ProjectPath::resolve($value); if($resolvePaths) $value=ProjectPath::resolve($value); }
        if($key==='base_path') $value=rtrim($value,'/');
        return $value;
    }

    private static function validateRelations(array $config): void
    {
        if($config['app_env']==='production' && !$config['session_cookie_secure']) throw new \InvalidArgumentException('В production session_cookie_secure должен быть включён.');
        if($config['ad_enabled'] && ($config['ad_host']==='' || $config['ad_domain']==='' || $config['ad_base_dn']==='')) throw new \InvalidArgumentException('Для LDAP заполните ad_host, ad_domain и ad_base_dn.');
        if($config['maintenance_start']!=='' && $config['maintenance_end']!=='' && $config['maintenance_end']<=$config['maintenance_start']) throw new \InvalidArgumentException('Окончание технических работ должно быть позже начала.');
    }
}
