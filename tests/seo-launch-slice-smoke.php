<?php
require_once __DIR__ . '/../v2/seo-config.php';
require_once __DIR__ . '/../v2/seo-launch-slice-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-catalog-v1.php';
require_once __DIR__ . '/../v2/seo-controlled-launch-catalog-v1.php';
require_once __DIR__ . '/../v2/data/seo-controlled-route-identities-v1.php';

function seo_launch_fail(string $message): void
{
    fwrite(STDERR, "SEO_LAUNCH_SLICE_FAIL:$message\n");
    exit(1);
}

$turkeyExpected=[
    '/country/turkey/','/country/turkey/alanya/','/country/turkey/antalya/',
    '/country/turkey/belek/','/country/turkey/kemer/','/country/turkey/side/',
];
$secondWaveExpected=['/country/egypt/','/country/maldives/'];
$seasonalExpected=['/country/turkey/antalya/september/','/country/maldives/september/'];
$hubExpected=['/','/country/'];
$coreMonths=v2_seo_core_month_launch_paths();
if(count($coreMonths)!==96)seo_launch_fail('core_month_count');
foreach(['/country/turkey/january/','/country/egypt/december/','/country/maldives/september/','/country/turkey/kemer/june/'] as $path){if(!in_array($path,$coreMonths,true))seo_launch_fail('core_month_missing_'.$path);}
$expected=array_values(array_unique(array_merge($turkeyExpected,$secondWaveExpected,$coreMonths)));
$indexableExpected=array_values(array_unique(array_merge($hubExpected,$expected)));

if(v2_seo_turkey_launch_paths()!==$turkeyExpected)seo_launch_fail('turkey_compat_paths');
if(v2_seo_second_wave_country_launch_paths()!==$secondWaveExpected)seo_launch_fail('second_wave_paths');
if(v2_seo_seasonal_september_launch_paths()!==$seasonalExpected)seo_launch_fail('seasonal_compat_paths');
if(v2_seo_core_hub_launch_paths()!==$hubExpected)seo_launch_fail('core_hub_paths');
$paths=v2_seo_controlled_launch_paths();
if($paths!==$expected||count($paths)!==104)seo_launch_fail('unexpected_controlled_paths');
$indexablePaths=v2_seo_controlled_indexable_paths();
if($indexablePaths!==$indexableExpected||count($indexablePaths)!==106)seo_launch_fail('unexpected_indexable_paths');
if(v2_seo_static_controlled_launch_paths()!==$indexableExpected)seo_launch_fail('unexpected_static_public_paths');
if(in_array('/poisk-turov/',$paths,true))seo_launch_fail('search_route_must_not_be_indexable');
if(count($paths)!==count(array_unique($paths)))seo_launch_fail('duplicate_path');
foreach($paths as $path)if(str_contains($path,'/hotel/'))seo_launch_fail('hotel_tours_launch_leak');

$routeBindings=v2_seo_controlled_route_identities();
$routePaths=array_values($routeBindings);
sort($routePaths,SORT_STRING);
$launchPaths=$paths;
sort($launchPaths,SORT_STRING);
if($routePaths!==$launchPaths)seo_launch_fail('controlled_route_registry_drift');
if(count($routeBindings)!==104)seo_launch_fail('controlled_route_identity_count');

$disabled=v2_seo_controlled_launch_site_params(['OTHER'=>'keep'],false);
if(!empty($disabled['SEO_INDEXABLE'])||($disabled['SEO_INDEXABLE_PATHS']??null)!==[])seo_launch_fail('disabled_gate');
$enabled=v2_seo_controlled_launch_site_params([],true);
if(empty($enabled['SEO_INDEXABLE'])||($enabled['SEO_INDEXABLE_PATHS']??[])!==$indexablePaths)seo_launch_fail('enabled_gate');

$turkeyCatalog=v2_seo_content_pilot_turkey_catalog();
$turkeyUrls=v2_seo_turkey_launch_sitemap_urls($turkeyCatalog,true);
$turkeyExpectedUrls=array_map(static fn(string $path):string=>'https://anytoour.ru'.$path,$turkeyExpected);
sort($turkeyExpectedUrls,SORT_STRING);
if($turkeyUrls!==$turkeyExpectedUrls)seo_launch_fail('turkey_sitemap_drift');

$catalog=v2_seo_controlled_launch_catalog();
$urls=v2_seo_controlled_launch_sitemap_urls($catalog,true);
$expectedUrls=array_map(static fn(string $path):string=>'https://anytoour.ru'.$path,$indexablePaths);
sort($expectedUrls,SORT_STRING);
if($urls!==$expectedUrls)seo_launch_fail('controlled_sitemap_drift');
if(count($urls)!==106)seo_launch_fail('controlled_sitemap_count');
if(count($catalog['publication_candidates']??[])!==104)seo_launch_fail('publication_candidate_count');
foreach($coreMonths as $path){
    if(($catalog['registry'][$path]['type']??'')!=='seasonal')seo_launch_fail('seasonal_registry_'.$path);
    if(($catalog['reports'][$path]['status']??'')!=='approved'||($catalog['reports'][$path]['publishable']??false)!==true)seo_launch_fail('seasonal_publishability_'.$path);
}
if(v2_seo_controlled_launch_sitemap_urls($catalog,false)!==[])seo_launch_fail('sitemap_disabled_gate');
$xml=v2_seo_controlled_launch_sitemap_xml($catalog,true);
foreach($expectedUrls as $url)if(!str_contains($xml,$url))seo_launch_fail('xml_missing_'.$url);
if(str_contains($xml,'/poisk-turov/')||str_contains($xml,'/hotel/'))seo_launch_fail('xml_protected_route_leak');

$roguePath='/country/turkey/hotel/rogue-hotel-999999/';
$rogue=$turkeyCatalog;$sourcePage=$turkeyCatalog['registry']['/country/turkey/']['page']??null;
if(!is_array($sourcePage))seo_launch_fail('rogue_source_page_missing');
$rogue['registry'][$roguePath]=['path'=>$roguePath,'type'=>'hotel_tours','page'=>$sourcePage];
$rogue['reports'][$roguePath]=['id'=>'hotel_tours.turkey.999999.v1','status'=>'approved','publishable'=>true,'errors'=>[]];
$rogue['graph'][$roguePath]=['parent'=>'/country/turkey/','related'=>[]];$rogue['publication_candidates'][]=$roguePath;
try{v2_seo_publication_manifest($rogue);seo_launch_fail('hotel_tours_publication_fence_bypassed');}
catch(InvalidArgumentException $e){if(!str_contains($e->getMessage(),'separate launch decision'))seo_launch_fail('hotel_tours_publication_fence_wrong_error');}

echo "SEO_LAUNCH_SLICE_OK catalogPaths=104 hubs=2 indexable=106 coreMonths=96 routeAuthority=104 sitemap=106 hotelTours=0\n";
