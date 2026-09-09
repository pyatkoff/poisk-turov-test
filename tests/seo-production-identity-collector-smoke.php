<?php
declare(strict_types=1);

$root=__DIR__.'/../v2';
$generated=$root.'/data/generated';
if(!is_dir($generated)&&!mkdir($generated,0777,true)&&!is_dir($generated))exit(1);
$manifestFile=$generated.'/seo-core-resort-review-routes-v1.json';
$registryFile=$generated.'/seo-core-resort-review-registry-v1.php';
$manifestBackup=is_file($manifestFile)?file_get_contents($manifestFile):null;
$registryBackup=is_file($registryFile)?file_get_contents($registryFile):null;
$months=['january','february','march','april','may','june','july','august','september','october','november','december'];
$routes=[];
foreach([['egypt','aleksandriya'],['maldives','male']] as [$country,$resort]){
    $base="/country/$country/$resort/";$routes[]=$base;
    foreach($months as $month)$routes[]=$base.$month.'/';
}
file_put_contents($manifestFile,json_encode([
    'state'=>'core_resort_review_routes_materialized','generated_resorts'=>2,'generated_routes'=>$routes,
    'publication_allowed'=>true,'indexation_allowed'=>true,'sitemap_allowed'=>true,'route_launch_allowed'=>true,
],JSON_UNESCAPED_SLASHES));
file_put_contents($registryFile,"<?php return [\n'/country/egypt/aleksandriya/'=>['data'=>['name'=>'Александрия']],\n'/country/maldives/male/'=>['data'=>['name'=>'Мале']],\n];\n");

try{
    require_once $root.'/seo-production-identity-collector-v1.php';
    function fail_collector(string $x):never{fwrite(STDERR,"SEO_PRODUCTION_IDENTITY_COLLECTOR_FAIL:$x\n");exit(1);}
    $now=1788372000;$expected=v2_seo_production_identity_expected_rows();$sitemap='';$fixtures=[];
    foreach($expected as $row){
        if($row['sitemap_member'])$sitemap.='<loc>https://anytoour.ru'.$row['path'].'</loc>';
        $fixtures['https://anytoour.ru'.$row['path']]=['status'=>200,'body'=>'<html><head><meta name="robots" content="'.$row['robots_prefix'].',max-image-preview:large"><link rel="canonical" href="'.$row['canonical'].'"></head></html>'];
    }
    $fixtures['https://anytoour.ru/sitemap.xml']=['status'=>200,'body'=>$sitemap];
    $fetch=static fn(string $url):array=>$fixtures[$url]??['status'=>404,'body'=>''];
    $r=v2_seo_collect_production_identity($fetch,$now);
    if(($r['state']??'')!=='production_identity_registry_valid'||($r['page_count']??0)!==131)fail_collector('valid');
    if(($r['type_counts']['country']??0)!==3||($r['type_counts']['resort']??0)!==7||($r['type_counts']['seasonal']??0)!==120)fail_collector('controlled_scope');
    if(($r['type_counts']['hotel_tours']??0)!==1||($r['hotel_tours_indexation_allowed']??true)!==false)fail_collector('hotel_boundary');
    $types=[];foreach($expected as $row)$types[$row['path']]=$row['type'];
    if(($types['/country/egypt/aleksandriya/']??'')!=='resort')fail_collector('dynamic_resort_type');
    if(($types['/country/egypt/aleksandriya/april/']??'')!=='seasonal')fail_collector('dynamic_month_type');
    $bad=$fixtures;$hotel=(string)v2_seo_ds2_reference_pages()['hotel_tours']['path'];$bad['https://anytoour.ru'.$hotel]['body']=str_replace('noindex,follow','index,follow',$bad['https://anytoour.ru'.$hotel]['body']);
    $r=v2_seo_collect_production_identity(static fn(string $url):array=>$bad[$url]??['status'=>404,'body'=>''],$now);
    if(($r['state']??'')!=='production_identity_registry_invalid')fail_collector('fail_closed');
    echo "SEO_PRODUCTION_IDENTITY_COLLECTOR_OK pages=131 country=3 resort=7 seasonal=120 hotel_tours=noindex dynamic_types=1\n";
} finally {
    if($manifestBackup===null)@unlink($manifestFile);else file_put_contents($manifestFile,$manifestBackup);
    if($registryBackup===null)@unlink($registryFile);else file_put_contents($registryFile,$registryBackup);
}
