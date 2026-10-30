<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';

use App\Controllers\ApiController;
use App\Reports\ReportConfig;
use App\Reports\WagonConfig;
use App\Services\ConfigSchema;
use App\Services\PageRegistry;

$errors=[]; $endpoints=[]; $template=(string)file_get_contents(dirname(__DIR__).'/templates/app.php');
$legacyReferences=[];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src')) as $file){
    if(!$file->isFile() || $file->getExtension()!=='php') continue;
    $source=(string)file_get_contents($file->getPathname());
    if(str_contains($source,'AdminController::PAGES') || str_contains($source,'self::PAGES')) $legacyReferences[]=$file->getPathname();
}
if($legacyReferences) $errors[]='Остались ссылки на удалённый каталог PAGES: '.implode(', ',$legacyReferences);
foreach(ReportConfig::all() as $code=>$report){
    foreach((array)($report['api_handlers']??[]) as $kind=>$method){
        $endpoint=$report[$kind.'_endpoint']??null;
        if(!$endpoint) $errors[]="$code: для $kind handler задан без endpoint";
        elseif(isset($endpoints[$endpoint])) $errors[]="$code: endpoint $endpoint уже использован {$endpoints[$endpoint]}";
        else $endpoints[$endpoint]=$code;
        if(!method_exists(ApiController::class,$method)) $errors[]="$code: метод ApiController::$method отсутствует";
    }
    if(!empty($report['navigation']) && !str_contains($template,'id="panel-'.$code.'"')) $errors[]="$code: в app.php отсутствует panel-$code";
    foreach((array)($report['page']??[]) as $key=>$value){
        if(str_ends_with((string)$key,'_id') && is_string($value)
            && !str_contains($template,'id="'.$value.'"')
            && !str_contains($template,"reportElementId('$code','$key'")) $errors[]="$code: в app.php отсутствует id=$value";
    }
}
$sourceKeys=array_column(ReportConfig::sourceColumns(),'key');
if(count($sourceKeys)!==count(array_unique($sourceKeys))) $errors[]='source-columns.php содержит повторяющиеся key';
$wagonConfig=WagonConfig::all(); $wagonFields=(array)($wagonConfig['fields']??[]);
if(count($wagonFields)!==count(array_unique($wagonFields))) $errors[]='WagonConfig содержит повторяющиеся поля';
$wagonKnown=array_merge($sourceKeys,['report_dt','type_reference']);
foreach($wagonFields as $field) if(!in_array($field,$wagonKnown,true)) $errors[]="WagonConfig содержит неизвестное поле: $field";
$wagonTabs=array_column((array)($wagonConfig['tabs']??[]),'key');
if(count($wagonTabs)!==count(array_unique($wagonTabs))) $errors[]='WagonConfig содержит повторяющиеся вкладки';
foreach((array)($wagonConfig['tabs']??[]) as $tab) if(empty($tab['history']) && !isset($wagonConfig['sections'][$tab['section']??''])) $errors[]='WagonConfig: для вкладки '.($tab['key']??'').' не найден раздел';
foreach(PageRegistry::all() as $code=>$page){
    if(trim((string)($page['label']??''))==='') $errors[]="$code: отсутствует label";
    foreach((array)($page['routes']??[]) as $route){
        if(!str_starts_with((string)$route,'/')) $errors[]="$code: маршрут должен начинаться с /: $route";
        elseif(PageRegistry::pageForPath((string)$route)!==$code) $errors[]="$code: маршрут сопоставлен с другим правом: $route";
    }
    foreach(['navigation','user_menu','admin_navigation'] as $menuKey){
        $url=$page[$menuKey]['url']??null;
        if($url!==null && PageRegistry::pageForPath((string)$url)!==$code) $errors[]="$code: $menuKey ведёт на страницу ".(PageRegistry::pageForPath((string)$url)??'без права').": $url";
    }
    foreach((array)($page['directory_navigation']??[]) as $item){
        $url=(string)($item['url']??'');
        if(PageRegistry::pageForPath($url)!==$code) $errors[]="$code: directory_navigation ведёт на страницу ".(PageRegistry::pageForPath($url)??'без права').": $url";
    }
}
try { ConfigSchema::normalize(ConfigSchema::defaults(),false); } catch(Throwable $e) { $errors[]='ConfigSchema: '.$e->getMessage(); }
foreach(PageRegistry::accessPages('/api/technical-state/detail') as $code) if(!isset(PageRegistry::all()[$code])) $errors[]="Общий API ссылается на неизвестное право: $code";
if(PageRegistry::pageForPath('/detail',['ctx'=>'dislocation'])!=='wagon-detail') $errors[]='/detail должен использовать право wagon-detail';
if(PageRegistry::accessPages('/api/dislocation/detail')!==['wagon-detail']) $errors[]='Detail API должен использовать право wagon-detail';
if(PageRegistry::accessPages('/api/technical-state/detail')!==['wagon-detail','wagon-card']) $errors[]='Технический detail API должен быть доступен из детализации и карточки вагона';
if(PageRegistry::accessPages('/api/dislocation/summary')!==['dislocation']) $errors[]='Summary API должен сохранять право своего отчёта';
if($errors){fwrite(STDERR,implode(PHP_EOL,$errors).PHP_EOL);exit(1);} echo 'Single-source registries are consistent: '.count(PageRegistry::all()).' pages, '.count($endpoints).' report endpoints, '.count($sourceKeys).' source columns, '.count($wagonFields).' wagon fields.'.PHP_EOL;
