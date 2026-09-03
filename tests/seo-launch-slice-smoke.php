<?php
require_once __DIR__ . '/../v2/seo-config.php';
require_once __DIR__ . '/../v2/seo-launch-slice-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-catalog-v1.php';
require_once __DIR__ . '/../v2/seo-controlled-launch-catalog-v1.php';
function seo_launch_fail(string $message):void{fwrite(STDERR,"SEO_LAUNCH_SLICE_FAIL:$message\n");exit(1);}
$turkeyExpected=['/country/turkey/','/country/turkey/alanya/','/country/turkey/antalya/','/country/turkey/belek/','/country/turkey/kemer/','/country/turkey/side/'];
$secondWaveExpected=['/country/egypt/','/country/maldives/'];
$septemberExpected=['/country/turkey/antalya/september/','/country/maldives/september/'];
$octoberExpected=['/country/turkey/antalya/october/','/country/maldives/october/'];
$seasonalExpected=array_merge($septemberExpected,$octoberExpected);
$expected=array_merge($turkeyExpected,$secondWaveExpected,$seasonalExpected);
if(v2_seo_turkey_launch_paths()!==$turkeyExpected)seo_launch_fail('turkey_compat_paths');
if(v2_seo_second_wave_country_launch_paths()!==$secondWaveExpected)seo_launch_fail('second_wave_paths');
if(v2_seo_seasonal_september_launch_paths()!==$septemberExpected)seo_launch_fail('september_paths');
if(v2_seo_seasonal_october_launch_paths()!==$octoberExpected)seo_launch_fail('october_paths');
$paths=v2_seo_controlled_launch_paths();if($paths!==$expected)seo_launch_fail('unexpected_controlled_paths');
if(count($paths)!==12||count($paths)!==count(array_unique($paths)))seo_launch_fail('path_count_or_duplicate');
foreach($paths as $path)if(str_contains($path,'/hotel/')||$path==='/poisk-turov/')seo_launch_fail('protected_route_leak');
$disabled=v2_seo_controlled_launch_site_params(['OTHER'=>'keep'],false);if(!empty($disabled['SEO_INDEXABLE'])||($disabled['SEO_INDEXABLE_PATHS']??null)!==[])seo_launch_fail('disabled_gate');
$enabled=v2_seo_controlled_launch_site_params([],true);if(empty($enabled['SEO_INDEXABLE'])||($enabled['SEO_INDEXABLE_PATHS']??[])!==$paths)seo_launch_fail('enabled_gate');
$catalog=v2_seo_controlled_launch_catalog();$urls=v2_seo_controlled_launch_sitemap_urls($catalog,true);
$expectedUrls=array_map(static fn(string $path):string=>'https://anytoour.ru'.$path,$paths);sort($expectedUrls,SORT_STRING);
if($urls!==$expectedUrls||count($catalog['publication_candidates']??[])!==12)seo_launch_fail('catalog_or_sitemap_drift');
foreach($seasonalExpected as $path){if(($catalog['registry'][$path]['type']??'')!=='seasonal')seo_launch_fail('seasonal_registry_'.$path);if(($catalog['reports'][$path]['status']??'')!=='approved'||($catalog['reports'][$path]['publishable']??false)!==true)seo_launch_fail('seasonal_publishability_'.$path);}
if(v2_seo_controlled_launch_sitemap_urls($catalog,false)!==[])seo_launch_fail('sitemap_disabled_gate');
$xml=v2_seo_controlled_launch_sitemap_xml($catalog,true);foreach($expectedUrls as $url)if(!str_contains($xml,$url))seo_launch_fail('xml_missing_'.$url);
if(str_contains($xml,'/hotel/')||str_contains($xml,'/poisk-turov/'))seo_launch_fail('xml_protected_route_leak');
$turkeyCatalog=v2_seo_content_pilot_turkey_catalog();$roguePath='/country/turkey/hotel/rogue-hotel-999999/';$rogue=$turkeyCatalog;$sourcePage=$turkeyCatalog['registry']['/country/turkey/']['page']??null;
if(!is_array($sourcePage))seo_launch_fail('rogue_source_page_missing');$rogue['registry'][$roguePath]=['path'=>$roguePath,'type'=>'hotel_tours','page'=>$sourcePage];$rogue['reports'][$roguePath]=['id'=>'hotel_tours.turkey.999999.v1','status'=>'approved','publishable'=>true,'errors'=>[]];$rogue['graph'][$roguePath]=['parent'=>'/country/turkey/','related'=>[]];$rogue['publication_candidates'][]=$roguePath;
try{v2_seo_publication_manifest($rogue);seo_launch_fail('hotel_tours_publication_fence_bypassed');}catch(InvalidArgumentException $e){if(!str_contains($e->getMessage(),'separate launch decision'))seo_launch_fail('hotel_tours_publication_fence_wrong_error');}
echo "SEO_LAUNCH_SLICE_OK paths=12 turkey=6 secondWave=2 seasonal=4 october=2 indexGate=1 sitemapGate=1 hotelTours=0\n";
