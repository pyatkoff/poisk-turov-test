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
foreach([['egypt','aleksandriya'],['egypt','sharm-el-sheikh'],['maldives','male']] as [$country,$resort]){
    $base="/country/$country/$resort/";$routes[]=$base;
    foreach($months as $month)$routes[]=$base.$month.'/';
}
$manifest=[
    'state'=>'core_resort_review_routes_materialized','generated_resorts'=>3,'generated_routes'=>$routes,
    'publication_allowed'=>true,'indexation_allowed'=>true,'sitemap_allowed'=>true,'route_launch_allowed'=>true,
];
$registry=[
    '/country/egypt/aleksandriya/'=>['data'=>['name'=>'Александрия']],
    '/country/egypt/sharm-el-sheikh/'=>['data'=>['name'=>'Шарм-эль-Шейх']],
    '/country/maldives/male/'=>['data'=>['name'=>'Мале']],
];
file_put_contents($manifestFile,json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
file_put_contents($registryFile,"<?php return ".var_export($registry,true).";\n");

try{
    require $root.'/seo-core-resort-launch-state-v1.php';
    $egypt=v2_seo_core_resort_country_links('/country/egypt/');
    $maldives=v2_seo_core_resort_country_links('/country/maldives/');
    if($egypt!==[
        '/country/egypt/aleksandriya/'=>'Александрия',
        '/country/egypt/sharm-el-sheikh/'=>'Шарм-эль-Шейх',
    ])exit(2);
    if($maldives!==['/country/maldives/male/'=>'Мале'])exit(3);
    if(v2_seo_core_resort_country_links('/country/turkey/')!==[])exit(4);
    if(v2_seo_core_resort_sibling_links('/country/egypt/aleksandriya/')!==['/country/egypt/sharm-el-sheikh/'=>'Шарм-эль-Шейх'])exit(5);
    if(v2_seo_core_resort_sibling_links('/country/maldives/male/')!==[])exit(6);
    if(v2_seo_core_resort_sibling_links('/country/turkey/kemer/')!==[])exit(7);

    $countrySource=(string)file_get_contents($root.'/country-page-v1.php');
    foreach(["require_once __DIR__ . '/seo-core-resort-launch-state-v1.php'",'v2_seo_core_resort_country_links($countryPath)','data-core-resort-links'] as $needle){
        if(!str_contains($countrySource,$needle))exit(8);
    }
    $resortSource=(string)file_get_contents($root.'/seo-resort-page-v1.php');
    foreach(['v2_seo_core_resort_sibling_links($path,8)','data-sibling-resort-links'] as $needle){
        if(!str_contains($resortSource,$needle))exit(9);
    }
    echo "SEO_CORE_RESORT_COUNTRY_LINKS_OK egypt=2 maldives=1 siblings=1 fail_closed=1\n";
} finally {
    if($manifestBackup===null)@unlink($manifestFile);else file_put_contents($manifestFile,$manifestBackup);
    if($registryBackup===null)@unlink($registryFile);else file_put_contents($registryFile,$registryBackup);
}
