<?php
declare(strict_types=1);
use App\Services\PageRegistry;
$groups=[];
foreach(PageRegistry::groups() as $code=>$definition) $groups[$code]=['group'=>$definition['label'],'order'=>$definition['order'],'items'=>[]];
foreach(PageRegistry::all() as $code=>$page){
    $nav=$page['navigation']??null; if(!$nav || !isset($groups[$nav['group']??''])) continue;
    $item=['id'=>$code,'label'=>$nav['label']??$page['label'],'page'=>$code,'order'=>(int)($nav['order']??0)];
    foreach(['url','target'] as $key) if(isset($nav[$key])) $item[$key]=$nav[$key];
    $groups[$nav['group']]['items'][]=$item;
}
foreach($groups as &$group){usort($group['items'],static fn($a,$b)=>$a['order']<=>$b['order']);foreach($group['items'] as &$item)unset($item['order']);unset($item);}unset($group);
$navigation=array_values(array_filter($groups,static fn($group)=>$group['items']!==[]));usort($navigation,static fn($a,$b)=>$a['order']<=>$b['order']);foreach($navigation as &$group)unset($group['order']);unset($group);
return $navigation;
