<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';

use App\Controllers\ApiController;
use App\Reports\ReportConfig;
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
foreach(PageRegistry::all() as $code=>$page){
    if(trim((string)($page['label']??''))==='') $errors[]="$code: отсутствует label";
    foreach((array)($page['routes']??[]) as $route) if(!str_starts_with((string)$route,'/')) $errors[]="$code: маршрут должен начинаться с /: $route";
}
try { ConfigSchema::normalize(ConfigSchema::defaults(),false); } catch(Throwable $e) { $errors[]='ConfigSchema: '.$e->getMessage(); }
if($errors){fwrite(STDERR,implode(PHP_EOL,$errors).PHP_EOL);exit(1);} echo 'Single-source registries are consistent: '.count(PageRegistry::all()).' pages, '.count($endpoints).' report endpoints, '.count($sourceKeys).' source columns.'.PHP_EOL;
