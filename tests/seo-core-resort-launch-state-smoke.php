<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-core-resort-launch-state-v1.php';
$dir=__DIR__.'/../v2/data/generated';$manifestFile=$dir.'/seo-core-resort-review-routes-v1.json';$registryFile=$dir.'/seo-core-resort-review-registry-v1.php';
if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))exit(1);
$oldManifest=is_file($manifestFile)?file_get_contents($manifestFile):null;$oldRegistry=is_file($registryFile)?file_get_contents($registryFile):null;
$months=['january','february','march','april','may','june','july','august','september','october','november','december'];$base='/country/egypt/sharm-el-sheikh/';$routes=[$base];foreach($months as $month)$routes[]=$base.$month.'/';sort($routes,SORT_STRING);
try{
    file_put_contents($registryFile,"<?php return ".var_export([$base=>['path'=>$base,'type'=>'resort','status'=>'approved','data'=>['name'=>'Шарм-эль-Шейх']]],true).";\n");
    file_put_contents($manifestFile,json_encode(['state'=>'core_resort_review_routes_materialized','generated_resorts'=>1,'generated_routes'=>$routes,'generated_route_count'=>13,'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'route_launch_allowed'=>false],JSON_UNESCAPED_SLASHES));
    if(v2_seo_core_resort_launch_paths()!==[])exit(2);
    file_put_contents($manifestFile,json_encode(['state'=>'core_resort_review_routes_materialized','generated_resorts'=>1,'generated_routes'=>$routes,'generated_route_count'=>13,'publication_allowed'=>true,'indexation_allowed'=>true,'sitemap_allowed'=>true,'route_launch_allowed'=>true,'hotel_tours_indexation_allowed'=>false],JSON_UNESCAPED_SLASHES));
    if(v2_seo_core_resort_launch_paths()!==$routes)exit(3);
    $links=v2_seo_core_resort_country_links('/country/egypt/');if(($links[$base]??'')!=='Шарм-эль-Шейх')exit(4);
    if(v2_seo_core_resort_country_links('/country/turkey/')!==[])exit(5);
    $bad=$routes;$bad[0]='/country/egypt/hotel/';file_put_contents($manifestFile,json_encode(['state'=>'core_resort_review_routes_materialized','generated_resorts'=>1,'generated_routes'=>$bad,'generated_route_count'=>13,'publication_allowed'=>true,'indexation_allowed'=>true,'sitemap_allowed'=>true,'route_launch_allowed'=>true],JSON_UNESCAPED_SLASHES));
    if(v2_seo_core_resort_launch_paths()!==[])exit(6);
} finally {
    if($oldManifest===null)@unlink($manifestFile);else file_put_contents($manifestFile,$oldManifest);
    if($oldRegistry===null)@unlink($registryFile);else file_put_contents($registryFile,$oldRegistry);
}
echo "SEO_CORE_RESORT_LAUNCH_STATE_OK routes=13 manifest_gate=1 reserved_slug_guard=1\n";
