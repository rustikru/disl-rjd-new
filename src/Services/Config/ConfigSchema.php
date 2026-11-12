<?php
declare(strict_types=1);
namespace App\Services\Config;

/** Единая схема типов, defaults, группировки и валидации настроек. */
final class ConfigSchema
{
    private static ?array $current = null;
    public static function definitions(): array
    {
        return [
            'app_env'=>self::d('enum','development','Приложение',['development','production'],label:'Среда приложения',help:'Определяет режим работы приложения и связанные настройки безопасности.'),
            'app_debug'=>self::d('bool',false,'Приложение',label:'Отладка',help:'Показывает расширенную диагностику; включайте только при разработке.'), 'app_name'=>self::d('string','Дислокация РЖД','Приложение',label:'Название приложения',help:'Отображается в заголовке и интерфейсе приложения.'),
            'base_path'=>self::d('string','','Приложение',label:'Базовый путь',help:'Путь размещения приложения, если оно открывается не из корня домена.'),
            'dislocation_stale_hours'=>self::d('int',8,'Приложение',null,1,720,label:'Срок актуальности дислокации',help:'Через это число часов справка считается неактуальной.'),
            'user_kpi_management_enabled'=>self::d('bool',false,'Приложение',label:'Управление пользовательскими KPI',help:'Разрешает создание, редактирование, скрытие и восстановление KPI-карточек.'),
            'maintenance_enabled'=>self::d('bool',false,'Технические работы',label:'Режим технических работ',help:'Временно закрывает приложение для всех пользователей, кроме SUPER.'),
            'maintenance_start'=>self::d('datetime','','Технические работы',label:'Начало',help:'Оставьте пустым, чтобы включить сразу.'),
            'maintenance_end'=>self::d('datetime','','Технические работы',label:'Окончание',help:'Оставьте пустым для бессрочного режима.'),
            'maintenance_title'=>self::d('string','Проводятся технические работы','Технические работы',label:'Заголовок',help:'Заголовок страницы, которую видят пользователи во время работ.'),
            'maintenance_message'=>self::d('string','Сайт временно недоступен. Мы проводим техническое обслуживание и скоро вернёмся.','Технические работы',label:'Текст',help:'Сообщение для пользователей во время технических работ.'),
            'db_host'=>self::d('string','localhost','База данных',label:'Хост',help:'Адрес сервера Oracle.'), 'db_port'=>self::d('string','1521','База данных',label:'Порт',help:'Порт подключения к Oracle.'),
            'db_name'=>self::d('string','','База данных',label:'Имя базы',help:'Имя сервиса или базы Oracle.'), 'db_user'=>self::d('string','','База данных',label:'Пользователь',help:'Имя пользователя Oracle.'), 'db_pass'=>self::d('secret','','База данных',label:'Пароль',help:'Пароль пользователя Oracle.'),
            'ad_debug'=>self::d('bool',false,'LDAP',label:'Отладка LDAP',help:'Записывает расширенную информацию о LDAP-подключении.'), 'ad_enabled'=>self::d('bool',false,'LDAP',label:'LDAP включён',help:'Разрешает вход пользователей через LDAP.'), 'ad_host'=>self::d('string','','LDAP',label:'Хост LDAP',help:'Адрес сервера каталога LDAP.'),
            'ad_domain'=>self::d('string','','LDAP',label:'Домен LDAP',help:'Домен пользователей каталога.'), 'ad_base_dn'=>self::d('string','','LDAP',label:'Базовый DN',help:'Корневой путь поиска пользователей в каталоге.'),
            'kerberos_enabled'=>self::d('bool',false,'Kerberos',label:'Kerberos включён',help:'Разрешает аутентификацию через Kerberos.'), 'kerberos_mode'=>self::d('enum','local','Kerberos',['local','server'],label:'Режим',help:'Выбирает способ получения имени пользователя Kerberos.'),
            'kerberos_server_key'=>self::d('string','REMOTE_USER','Kerberos',label:'Ключ имени пользователя',help:'Имя HTTP-параметра с учётной записью Kerberos.'), 'kerberos_local_username'=>self::d('string','administrator','Kerberos',label:'Локальный пользователь',help:'Имя пользователя для локальной проверки Kerberos.'),
            'kerberos_strip_realm'=>self::d('bool',true,'Kerberos',label:'Убирать домен',help:'Удаляет часть после символа @ из имени пользователя.'), 'kerberos_auto_create_user'=>self::d('bool',true,'Kerberos',label:'Создавать пользователей',help:'Автоматически создаёт локальную учётную запись при первом входе.'),
            'report_mail_enabled'=>self::d('bool',false,'Почта и отчёты',label:'Рассылки включены',help:'Разрешает формирование и отправку отчётных рассылок.'), 'mail_mode'=>self::d('enum','file','Почта и отчёты',['file','oracle'],label:'Способ отправки',help:'Выбирает файловый или Oracle-механизм отправки писем.'),
            'report_storage_dir'=>self::d('path','tmp','Почта и отчёты',label:'Каталог отчётов',help:'Каталог для временного хранения сформированных отчётов.'), 'report_mail_memory_limit'=>self::d('memory','512M','Почта и отчёты',label:'Лимит памяти отчёта',help:'Максимальный объём памяти PHP при формировании XLSX.'),
            'report_mail_sender'=>self::d('string','noreply@metafrax.ru','Почта и отчёты',label:'Адрес отправителя',help:'Адрес, от которого отправляются отчётные письма.'),
            'mailing_scheduler_token'=>self::d('secret','','Почта и отчёты',label:'Токен планировщика',help:'Используется внешним планировщиком для запуска рассылок через API. Оставьте пустым, чтобы сохранить текущий токен.'),
            'system_mail_subject'=>self::d('string','ИС «МТФ-Дислокация»','Почта и отчёты',label:'Тема системных писем',help:'Добавляется в начало темы системного письма. На рассылки отчётов не влияет.'),
            'password_reset_enabled'=>self::d('bool',true,'Безопасность и сессии',label:'Восстановление пароля',help:'Разрешает пользователям запрашивать ссылку для смены пароля.'),
            'password_reset_ttl_minutes'=>self::d('int',30,'Безопасность и сессии',null,5,1440,label:'Срок действия ссылки',help:'Время действия ссылки восстановления пароля в минутах.'),
            'password_reset_storage_dir'=>self::d('path','tmp/password-reset','Безопасность и сессии',label:'Каталог ссылок',help:'Каталог для временных данных восстановления пароля.'),
            'session_name'=>self::d('string','disl_session','Безопасность и сессии',label:'Имя сессии',help:'Имя cookie сессии приложения.'),
            'session_cookie_secure'=>self::d('bool',null,'Безопасность и сессии',label:'Защищённая cookie',help:'Передаёт cookie только по HTTPS.'),
            'session_lifetime_seconds'=>self::d('int',28800,'Безопасность и сессии',null,300,2592000,label:'Время сессии',help:'Период бездействия до завершения сессии в секундах.'),
            'auth_log_file'=>self::d('path','tmp/auth_debug.log','Журналирование',label:'Журнал авторизации',help:'Файл для диагностических записей входа и выхода.'),
            'ldap_log_file'=>self::d('path','tmp/ldap_debug.log','Журналирование',label:'Журнал LDAP',help:'Файл для диагностических записей LDAP.'),
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
