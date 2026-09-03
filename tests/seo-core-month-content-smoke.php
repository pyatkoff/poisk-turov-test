<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-month-content-v1.php';

$records=v2_seo_core_month_content_records();
if(count($records)!==96)exit(1);
$byPath=[];
foreach($records as $record){
    $path=(string)($record['path']??'');
    if($path===''||isset($byPath[$path]))exit(2);
    $byPath[$path]=$record;
    if(($record['status']??'')!=='review'||($record['type']??'')!=='seasonal')exit(3);
    foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag)if(($record[$flag]??true)!==false)exit(4);
    $data=$record['data']??[];
    foreach(['title','description','h1','intro','breadcrumbs','sections','related','search_state','seasonal_identity'] as $key)if(!array_key_exists($key,$data))exit(5);
    if(count($data['sections'])<3)exit(6);
    if(str_contains($data['intro'],'₽')||preg_match('/\b\d+[ ]?(?:руб|₽)\b/u',$data['intro']))exit(7);
}
$checks=[
    '/country/turkey/january/'=>'Туры в Турцию в январе',
    '/country/egypt/december/'=>'Туры в Египет в декабре',
    '/country/maldives/september/'=>'Туры на Мальдивы в сентябре',
    '/country/turkey/kemer/june/'=>'Туры в Кемер в июне',
    '/country/turkey/antalya/october/'=>'Туры в Анталью в октябре',
];
foreach($checks as $path=>$h1){if(($byPath[$path]['data']['h1']??'')!==$h1)exit(8);}
if(($byPath['/country/turkey/kemer/june/']['data']['search_state']??[])!==['country'=>4,'region'=>22])exit(9);
if(($byPath['/country/maldives/september/']['data']['search_state']??[])!==['country'=>8])exit(10);
if(($byPath['/country/turkey/kemer/june/']['data']['seasonal_identity']['page_key']??'')!=='resort_month:1:4:22:2026-06')exit(11);
if(($byPath['/country/maldives/september/']['data']['seasonal_identity']['page_key']??'')!=='month:1:8:2026-09')exit(12);
echo "SEO_CORE_MONTH_CONTENT_OK records=96 useful_sections=3 publication=0\n";
