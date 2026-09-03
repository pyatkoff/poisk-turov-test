<?php
declare(strict_types=1);
require_once __DIR__.'/seo-production-identity-registry-v1.php';
require_once __DIR__.'/seo-launch-slice-v1.php';
require_once __DIR__.'/seo-ds2-reference-pages-v1.php';

function v2_seo_production_identity_expected_rows(): array
{
    $rows=[];
    $seasonal=array_fill_keys(v2_seo_core_month_launch_paths(),true);
    $turkeyResorts=array_fill_keys(array_values(array_diff(v2_seo_turkey_launch_paths(),['/country/turkey/'])),true);
    foreach(v2_seo_controlled_launch_paths() as $path){
        $type=isset($seasonal[$path])?'seasonal':(isset($turkeyResorts[$path])?'resort':'country');
        $rows[]=[
            'path'=>$path,
            'type'=>$type,
            'http_status'=>200,
            'robots_prefix'=>'index,follow',
            'canonical'=>'https://anytoour.ru'.$path,
            'sitemap_member'=>true,
        ];
    }
    $hotel=(string)v2_seo_ds2_reference_pages()['hotel_tours']['path'];
    $rows[]=[
        'path'=>$hotel,'type'=>'hotel_tours','http_status'=>200,'robots_prefix'=>'noindex,follow',
        'canonical'=>'https://anytoour.ru'.$hotel,'sitemap_member'=>false,
    ];
    return $rows;
}

/** Collect normalized production evidence using an injected fetcher. */
function v2_seo_collect_production_identity(callable $fetch, ?int $nowEpoch=null): array
{
    $nowEpoch??=time();$expected=v2_seo_production_identity_expected_rows();$sitemap=(array)$fetch('https://anytoour.ru/sitemap.xml');$sitemapBody=(string)($sitemap['body']??'');$pages=[];
    foreach($expected as $row){
        $url='https://anytoour.ru'.$row['path'];$res=(array)$fetch($url);$body=(string)($res['body']??'');$robots='';$canonical='';
        if(preg_match('~<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)~i',$body,$m)||preg_match('~<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']robots["\']~i',$body,$m))$robots=trim($m[1]);
        if(preg_match('~<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)~i',$body,$m)||preg_match('~<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']~i',$body,$m))$canonical=trim($m[1]);
        $pages[]=['path'=>$row['path'],'http_status'=>(int)($res['status']??0),'robots'=>$robots,'canonical'=>$canonical,'sitemap_member'=>str_contains($sitemapBody,'https://anytoour.ru'.$row['path'])];
    }
    $evidence=['domain'=>'anytoour.ru','observed_at_utc'=>gmdate('c',$nowEpoch),'pages'=>$pages];
    $validated=v2_seo_production_identity_registry_validate($evidence,$expected,$nowEpoch);
    $validated['transport_sitemap_status']=(int)($sitemap['status']??0);$validated['source']='live_http_collector';$validated['publication_allowed']=false;$validated['hotel_tours_publication_allowed']=false;$validated['hotel_tours_indexation_allowed']=false;
    return $validated;
}
