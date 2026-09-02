<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-production-identity-registry-v1.php';

function fail_registry(string $code): never { fwrite(STDERR,"SEO_PRODUCTION_IDENTITY_REGISTRY_FAIL:$code\n"); exit(1); }

$now=strtotime('2026-09-02T17:20:00Z');
$expected=[
    ['path'=>'/country/turkey/','type'=>'country','robots'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/country/turkey/alanya/','type'=>'resort','robots'=>'index,follow','sitemap_member'=>true],
    ['path'=>'/tours/turkey/alanya/example-hotel/','type'=>'hotel_tours','robots'=>'noindex,follow','sitemap_member'=>false],
];
$evidence=[
    'domain'=>'anytoour.ru',
    'observed_at_utc'=>'2026-09-02T17:00:00Z',
    'pages'=>[
        ['path'=>'/country/turkey/','http_status'=>200,'robots'=>'index,follow','canonical'=>'https://anytoour.ru/country/turkey/','sitemap_member'=>true],
        ['path'=>'/country/turkey/alanya/','http_status'=>200,'robots'=>'index,follow','canonical'=>'https://anytoour.ru/country/turkey/alanya/','sitemap_member'=>true],
        ['path'=>'/tours/turkey/alanya/example-hotel/','http_status'=>200,'robots'=>'noindex,follow','canonical'=>'https://anytoour.ru/tours/turkey/alanya/example-hotel/','sitemap_member'=>false],
    ],
];
$ok=v2_seo_production_identity_registry_validate($evidence,$expected,$now);
if(($ok['state']??'')!=='production_identity_registry_valid') fail_registry('valid_state');
if(($ok['integrity_ok']??false)!==true) fail_registry('valid_integrity');
if(($ok['type_counts']['country']??0)!==1||($ok['type_counts']['resort']??0)!==1||($ok['type_counts']['hotel_tours']??0)!==1) fail_registry('type_counts');
if(($ok['hotel_tours_indexation_allowed']??true)!==false) fail_registry('hotel_boundary');

$leak=$evidence;
$leak['pages'][2]['robots']='index,follow';
$leak['pages'][2]['sitemap_member']=true;
$bad=v2_seo_production_identity_registry_validate($leak,$expected,$now);
if(($bad['integrity_ok']??true)!==false) fail_registry('hotel_leak_allowed');
if(!in_array('identity_robots_mismatch:/tours/turkey/alanya/example-hotel/',$bad['errors']??[],true)) fail_registry('hotel_robot_error_missing');
if(!in_array('identity_sitemap_mismatch:/tours/turkey/alanya/example-hotel/',$bad['errors']??[],true)) fail_registry('hotel_sitemap_error_missing');

$stale=$evidence;
$stale['observed_at_utc']='2026-08-31T17:00:00Z';
$bad=v2_seo_production_identity_registry_validate($stale,$expected,$now);
if(!in_array('identity_evidence_stale',$bad['errors']??[],true)) fail_registry('stale_allowed');

$badExpected=$expected;
$badExpected[2]['robots']='index,follow';
$bad=v2_seo_production_identity_registry_validate($evidence,$badExpected,$now);
if(!in_array('hotel_tours_expected_noindex:/tours/turkey/alanya/example-hotel/',$bad['errors']??[],true)) fail_registry('expected_hotel_boundary_missing');

echo "SEO_PRODUCTION_IDENTITY_REGISTRY_OK\n";
