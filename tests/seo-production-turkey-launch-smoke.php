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
$coreMonths=v2_seo_core_month_launch_paths();
$seasonalCompat=v2_seo_seasonal_september_launch_paths();
$expected=v2_seo_controlled_launch_paths();
if(count($turkey)!==6||count($secondWave)!==2||count($coreMonths)!==96||count($expected)!==104) production_launch_fail('launch_scope_count');
if($seasonalCompat!==['/country/turkey/antalya/september/','/country/maldives/september/']) production_launch_fail('seasonal_compat_scope');
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

$tmp=sys_get_temp_dir().'/seo-launch-rollback-'.bin2hex(random_bytes(4));
mkdir($tmp,0700,true);
file_put_contents($tmp.'/site_conf.php',"<?php\n\$params=['SEO_TURKEY_LAUNCH'=>false];\n");
$_SERVER['DOCUMENT_ROOT']=$tmp;
foreach(['/country/turkey/','/country/egypt/','/country/maldives/','/country/turkey/kemer/june/','/country/maldives/january/'] as $path){
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
if(count($actual)!==104) production_launch_fail('sitemap_scope_count');
if(str_contains($xml,'/hotel/')||str_contains($xml,'/poisk-turov/')) production_launch_fail('sitemap_protected_route_leak');

$turkeyNow=1788368460;
$turkeyDossier=v2_seo_turkey_launch_dossier($turkeyNow);
if(($turkeyDossier['state']??'')!=='controlled_country_resort_launch_authorized') production_launch_fail('turkey_dossier_not_authorized');
if(($turkeyDossier['paths']??[])!==$turkey||count($turkeyDossier['rows']??[])!==6) production_launch_fail('turkey_dossier_scope');
if(($turkeyDossier['hotel_tours_approved']??true)!==false||($turkeyDossier['hotel_tours_indexation_allowed']??true)!==false||($turkeyDossier['hotel_tours_sitemap_allowed']??true)!==false) production_launch_fail('turkey_hotel_boundary');

$secondWaveNow=1788385200;
$secondDossier=v2_seo_second_wave_country_launch_dossier($secondWaveNow);
if(($secondDossier['state']??'')!=='second_wave_country_prelaunch_authorized') production_launch_fail('second_wave_dossier_not_authorized');
if(($secondDossier['paths']??[])!==$secondWave||count($secondDossier['rows']??[])!==2) production_launch_fail('second_wave_dossier_scope');
if(($secondDossier['hotel_tours_approved']??true)!==false||($secondDossier['hotel_tours_indexation_allowed']??true)!==false||($secondDossier['hotel_tours_sitemap_allowed']??true)!==false) production_launch_fail('second_wave_hotel_boundary');

$snapshotSource=file_get_contents(__DIR__.'/../v2/seo-offer-snapshot-v1.php');
$countryPageSource=file_get_contents(__DIR__.'/../v2/country-page-v1.php');
$calendarSource=file_get_contents(__DIR__.'/../v2/seo-price-calendar-v1.php');
if($snapshotSource===false||$countryPageSource===false||$calendarSource===false) production_launch_fail('country_offer_source_missing');
foreach(['function v2_seo_country_snapshot_offers',"s.page_type='country'",'s.expires_at>=NOW()',"s.currency='RUB'"] as $needle) if(!str_contains($snapshotSource,$needle)) production_launch_fail('country_snapshot_contract:'.$needle);
if(str_contains($countryPageSource,'tourvisor-client-v1.php')||str_contains($countryPageSource,'v2_data_tv_')||str_contains($countryPageSource,'curl_')) production_launch_fail('country_page_live_provider_call');
if(str_contains($calendarSource,'tourvisor-client-v1.php')||str_contains($calendarSource,'v2_data_tv_')||str_contains($calendarSource,'curl_')) production_launch_fail('calendar_live_provider_call');

foreach($coreMonths as $path){
    if(str_contains($path,'/hotel/')||!preg_match('#/(january|february|march|april|may|june|july|august|september|october|november|december)/$#',$path)) production_launch_fail('core_month_path_boundary');
}

echo "SEO_PRODUCTION_CONTROLLED_LAUNCH_OK paths=104 base=8 coreMonths=96 sitemap=104 hotelTours=0 rollback=1\n";
