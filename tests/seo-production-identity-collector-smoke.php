<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-production-identity-collector-v1.php';
function fail_collector(string $x):never{fwrite(STDERR,"SEO_PRODUCTION_IDENTITY_COLLECTOR_FAIL:$x\n");exit(1);}
$now=1788372000;$expected=v2_seo_production_identity_expected_rows();$sitemap='';$fixtures=[];
foreach($expected as $row){
    if($row['sitemap_member'])$sitemap.='<loc>https://anytoour.ru'.$row['path'].'</loc>';
    $fixtures['https://anytoour.ru'.$row['path']]=['status'=>200,'body'=>'<html><head><meta name="robots" content="'.$row['robots_prefix'].',max-image-preview:large"><link rel="canonical" href="'.$row['canonical'].'"></head></html>'];
}
$fixtures['https://anytoour.ru/sitemap.xml']=['status'=>200,'body'=>$sitemap];
$fetch=static fn(string $url):array=>$fixtures[$url]??['status'=>404,'body'=>''];
$r=v2_seo_collect_production_identity($fetch,$now);
if(($r['state']??'')!=='production_identity_registry_valid'||($r['page_count']??0)!==105)fail_collector('valid');
if(($r['type_counts']['country']??0)!==3||($r['type_counts']['resort']??0)!==5||($r['type_counts']['seasonal']??0)!==96)fail_collector('controlled_scope');
if(($r['type_counts']['hotel_tours']??0)!==1||($r['hotel_tours_indexation_allowed']??true)!==false)fail_collector('hotel_boundary');
$bad=$fixtures;$hotel=(string)v2_seo_ds2_reference_pages()['hotel_tours']['path'];$bad['https://anytoour.ru'.$hotel]['body']=str_replace('noindex,follow','index,follow',$bad['https://anytoour.ru'.$hotel]['body']);
$r=v2_seo_collect_production_identity(static fn(string $url):array=>$bad[$url]??['status'=>404,'body'=>''],$now);
if(($r['state']??'')!=='production_identity_registry_invalid')fail_collector('fail_closed');
echo "SEO_PRODUCTION_IDENTITY_COLLECTOR_OK pages=105 country=3 resort=5 seasonal=96 hotel_tours=noindex\n";
