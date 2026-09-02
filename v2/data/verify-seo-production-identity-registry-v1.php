<?php
declare(strict_types=1);
require_once __DIR__.'/../seo-production-identity-registry-v1.php';

$file='';
foreach(array_slice($argv,1) as $arg){
    if(str_starts_with($arg,'--baseline-file=')) $file=substr($arg,16);
}
if($file===''||!is_file($file)){ fwrite(STDERR,"SEO_IDENTITY_REGISTRY_VERIFY_FAIL:baseline_file_missing\n"); exit(2); }
$payload=json_decode((string)file_get_contents($file),true);
if(!is_array($payload)){ fwrite(STDERR,"SEO_IDENTITY_REGISTRY_VERIFY_FAIL:baseline_json_invalid\n"); exit(2); }

$pages=is_array($payload['pages']??null)?$payload['pages']:[];
$protected=is_array($payload['protected_hotel_tour']??null)?$payload['protected_hotel_tour']:[];
if($protected!==[]) $pages[]=$protected;
$evidence=[
    'domain'=>(string)($payload['domain']??''),
    'observed_at_utc'=>(string)($payload['observed_at_utc']??''),
    'pages'=>$pages,
];
$expected=[
    ['path'=>'/country/turkey/','type'=>'country','robots_prefix'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/country/turkey/alanya/','type'=>'resort','robots_prefix'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/country/turkey/antalya/','type'=>'resort','robots_prefix'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/country/turkey/belek/','type'=>'resort','robots_prefix'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/country/turkey/kemer/','type'=>'resort','robots_prefix'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/country/turkey/side/','type'=>'resort','robots_prefix'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/country/turkey/hotel/aegean-park-1601/','type'=>'hotel_tours','robots_prefix'=>'noindex,follow','sitemap_member'=>false],
];
$result=v2_seo_production_identity_registry_validate($evidence,$expected);
if(($result['integrity_ok']??false)!==true){
    fwrite(STDERR,"SEO_IDENTITY_REGISTRY_VERIFY_FAIL:".implode(',',array_map('strval',$result['errors']??[]))."\n");
    exit(1);
}
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
