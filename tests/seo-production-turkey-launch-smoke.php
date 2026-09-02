<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/site-page-shell-v1.php';
require_once __DIR__.'/../v2/seo-turkey-launch-dossier-v1.php';

function production_launch_fail(string $message): void
{
    fwrite(STDERR,"SEO_PRODUCTION_TURKEY_LAUNCH_FAIL:$message\n");
    exit(1);
}

$_SERVER['HTTP_HOST']='anytoour.ru';
$_SERVER['DOCUMENT_ROOT']='/tmp/seo-production-launch-no-site-conf';
$expected=v2_seo_turkey_launch_paths();
foreach($expected as $path){
    $_SERVER['REQUEST_URI']=$path;
    $ctx=sp_context($path,'Test','Test');
    if(!str_starts_with((string)($ctx['robots']??''),'index,follow')) production_launch_fail('launch_path_noindex:'.$path);
}

foreach(['/poisk-turov/','/country/egypt/','/country/turkey/hotel/rogue-hotel-999999/'] as $path){
    $_SERVER['REQUEST_URI']=$path;
    $ctx=sp_context($path,'Test','Test');
    if(!str_starts_with((string)($ctx['robots']??''),'noindex,follow')) production_launch_fail('non_launch_path_indexable:'.$path);
}

// Emergency rollback remains available through production site_conf.php.
$tmp=sys_get_temp_dir().'/seo-launch-rollback-'.bin2hex(random_bytes(4));
mkdir($tmp,0700,true);
file_put_contents($tmp.'/site_conf.php',"<?php\n\$params=['SEO_TURKEY_LAUNCH'=>false];\n");
$_SERVER['DOCUMENT_ROOT']=$tmp;
$_SERVER['REQUEST_URI']='/country/turkey/';
$ctx=sp_context('/country/turkey/','Test','Test');
@unlink($tmp.'/site_conf.php'); @rmdir($tmp);
if(!str_starts_with((string)($ctx['robots']??''),'noindex,follow')) production_launch_fail('rollback_override_failed');

$sitemapPath=__DIR__.'/../v2/sitemap.xml';
$xml=file_get_contents($sitemapPath);
if($xml===false) production_launch_fail('sitemap_missing');
preg_match_all('#<loc>([^<]+)</loc>#',$xml,$matches);
$actual=array_values($matches[1]??[]);
$expectedUrls=array_map(static fn(string $path):string=>'https://anytoour.ru'.$path,$expected);
sort($actual,SORT_STRING); sort($expectedUrls,SORT_STRING);
if($actual!==$expectedUrls) production_launch_fail('sitemap_allowlist_drift');
if(str_contains($xml,'/hotel/')||str_contains($xml,'/poisk-turov/')) production_launch_fail('sitemap_protected_route_leak');

$now=1788368460;
$dossier=v2_seo_turkey_launch_dossier($now);
if(($dossier['state']??'')!=='controlled_country_resort_launch_authorized') production_launch_fail('dossier_not_authorized');
if(($dossier['paths']??[])!==$expected||count($dossier['rows']??[])!==6) production_launch_fail('dossier_scope');
if(($dossier['hotel_tours_approved']??true)!==false||($dossier['hotel_tours_indexation_allowed']??true)!==false||($dossier['hotel_tours_sitemap_allowed']??true)!==false) production_launch_fail('hotel_boundary');
foreach($dossier['rows'] as $row){
    if(($row['packet']['state']??'')!=='opportunity_evidence_review_ready') production_launch_fail('evidence_packet');
    if(($row['packet']['demand']['serp_intent']??'')!=='commercial') production_launch_fail('serp_intent');
    if(($row['packet']['uniqueness']['decision']??'')!=='distinct') production_launch_fail('uniqueness');
}
$stale=v2_seo_turkey_launch_dossier($now+32*86400);
if(($stale['state']??'')!=='controlled_country_resort_launch_blocked') production_launch_fail('stale_evidence_not_blocked');

echo "SEO_PRODUCTION_TURKEY_LAUNCH_OK paths=6 sitemap=6 hotelTours=0 rollback=1 evidence=1\n";
