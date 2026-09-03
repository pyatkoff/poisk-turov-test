<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/site-page-shell-v1.php';
require_once __DIR__.'/../v2/seo-turkey-launch-dossier-v1.php';
require_once __DIR__.'/../v2/seo-second-wave-country-launch-dossier-v1.php';

function production_launch_fail(string $message): void
{
    fwrite(STDERR,"SEO_PRODUCTION_CONTROLLED_LAUNCH_FAIL:$message\n");
    exit(1);
}

$_SERVER['HTTP_HOST']='anytoour.ru';
$_SERVER['DOCUMENT_ROOT']='/tmp/seo-production-launch-no-site-conf';
$turkey=v2_seo_turkey_launch_paths();
$secondWave=v2_seo_second_wave_country_launch_paths();
$seasonal=v2_seo_seasonal_september_launch_paths();
$expected=v2_seo_controlled_launch_paths();
if(count($turkey)!==6||count($secondWave)!==2||count($seasonal)!==2||count($expected)!==10) production_launch_fail('launch_scope_count');
if($seasonal!==['/country/turkey/antalya/september/','/country/maldives/september/']) production_launch_fail('seasonal_scope');
foreach($expected as $path){
    $_SERVER['REQUEST_URI']=$path;
    $ctx=sp_context($path,'Test','Test');
    if(!str_starts_with((string)($ctx['robots']??''),'index,follow')) production_launch_fail('launch_path_noindex:'.$path);
}

foreach(['/poisk-turov/','/country/oae/','/country/turkey/hotel/rogue-hotel-999999/','/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/'] as $path){
    $_SERVER['REQUEST_URI']=$path;
    $ctx=sp_context($path,'Test','Test');
    if(!str_starts_with((string)($ctx['robots']??''),'noindex,follow')) production_launch_fail('non_launch_path_indexable:'.$path);
}

// Emergency rollback must close the entire controlled cohort, including seasonal URLs.
$tmp=sys_get_temp_dir().'/seo-launch-rollback-'.bin2hex(random_bytes(4));
mkdir($tmp,0700,true);
file_put_contents($tmp.'/site_conf.php',"<?php\n\$params=['SEO_TURKEY_LAUNCH'=>false];\n");
$_SERVER['DOCUMENT_ROOT']=$tmp;
foreach(['/country/turkey/','/country/egypt/','/country/maldives/','/country/turkey/antalya/september/','/country/maldives/september/'] as $path){
    $_SERVER['REQUEST_URI']=$path;
    $ctx=sp_context($path,'Test','Test');
    if(!str_starts_with((string)($ctx['robots']??''),'noindex,follow')) production_launch_fail('rollback_override_failed:'.$path);
}
@unlink($tmp.'/site_conf.php'); @rmdir($tmp);

$sitemapPath=__DIR__.'/../v2/sitemap.xml';
$xml=file_get_contents($sitemapPath);
if($xml===false) production_launch_fail('sitemap_missing');
preg_match_all('#<loc>([^<]+)</loc>#',$xml,$matches);
$actual=array_values($matches[1]??[]);
$expectedUrls=array_map(static fn(string $path):string=>'https://anytoour.ru'.$path,$expected);
sort($actual,SORT_STRING); sort($expectedUrls,SORT_STRING);
if($actual!==$expectedUrls) production_launch_fail('sitemap_allowlist_drift');
if(count($actual)!==10) production_launch_fail('sitemap_scope_count');
if(str_contains($xml,'/hotel/')||str_contains($xml,'/poisk-turov/')) production_launch_fail('sitemap_protected_route_leak');

// The original Turkey dossier remains independently evidence-bound.
$turkeyNow=1788368460;
$turkeyDossier=v2_seo_turkey_launch_dossier($turkeyNow);
if(($turkeyDossier['state']??'')!=='controlled_country_resort_launch_authorized') production_launch_fail('turkey_dossier_not_authorized');
if(($turkeyDossier['paths']??[])!==$turkey||count($turkeyDossier['rows']??[])!==6) production_launch_fail('turkey_dossier_scope');
if(($turkeyDossier['hotel_tours_approved']??true)!==false||($turkeyDossier['hotel_tours_indexation_allowed']??true)!==false||($turkeyDossier['hotel_tours_sitemap_allowed']??true)!==false) production_launch_fail('turkey_hotel_boundary');
foreach($turkeyDossier['rows'] as $row){
    if(($row['packet']['state']??'')!=='opportunity_evidence_review_ready') production_launch_fail('turkey_evidence_packet');
    if(($row['packet']['demand']['serp_intent']??'')!=='commercial') production_launch_fail('turkey_serp_intent');
    if(($row['packet']['uniqueness']['decision']??'')!=='distinct') production_launch_fail('turkey_uniqueness');
}
$turkeyStale=v2_seo_turkey_launch_dossier($turkeyNow+32*86400);
if(($turkeyStale['state']??'')!=='controlled_country_resort_launch_blocked') production_launch_fail('turkey_stale_evidence_not_blocked');

// Egypt/Maldives keep their own prelaunch dossier; no numeric demand is fabricated.
$secondWaveNow=1788385200;
$secondDossier=v2_seo_second_wave_country_launch_dossier($secondWaveNow);
if(($secondDossier['state']??'')!=='second_wave_country_prelaunch_authorized') production_launch_fail('second_wave_dossier_not_authorized');
if(($secondDossier['paths']??[])!==$secondWave||count($secondDossier['rows']??[])!==2) production_launch_fail('second_wave_dossier_scope');
if(($secondDossier['hotel_tours_approved']??true)!==false||($secondDossier['hotel_tours_indexation_allowed']??true)!==false||($secondDossier['hotel_tours_sitemap_allowed']??true)!==false) production_launch_fail('second_wave_hotel_boundary');
foreach($secondDossier['rows'] as $row){
    if(($row['decision']??'')!=='GO') production_launch_fail('second_wave_decision');
    if(!array_key_exists('numeric_demand_score',$row)||$row['numeric_demand_score']!==null) production_launch_fail('second_wave_numeric_demand_invented');
}

// Seasonal authorization itself is evidence-bound by the dedicated live dossier; this
// smoke only confirms that no path outside the exact approved pair leaked into launch.
foreach($seasonal as $path) if(str_contains($path,'/hotel/')||!str_ends_with($path,'/september/')) production_launch_fail('seasonal_path_boundary');

echo "SEO_PRODUCTION_CONTROLLED_LAUNCH_OK paths=10 turkey=6 secondWave=2 seasonal=2 sitemap=10 hotelTours=0 rollback=1 evidence=1\n";
