<?php
require_once __DIR__ . '/../v2/seo-hotel-review-pilot-package-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-hotel-review-catalog-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-maldives-catalog-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-egypt-hotel-review-catalog-v1.php';

function pilot_package_fail(string $message): void { fwrite(STDERR,"SEO_HOTEL_REVIEW_PILOT_PACKAGE_FAIL:$message\n"); exit(1); }

$families=[
    ['key'=>'turkey','catalog'=>v2_seo_content_pilot_turkey_hotel_review_catalog()],
    ['key'=>'maldives','catalog'=>v2_seo_content_pilot_maldives_catalog()],
    ['key'=>'egypt','catalog'=>v2_seo_content_pilot_egypt_hotel_review_catalog()],
];
$union=v2_seo_review_catalog_union($families);
$spec=v2_seo_hotel_launch_pilot_spec();
$pilotCatalog=v2_seo_hotel_review_pilot_catalog($union,$spec);
$now=1800000000;
$evidence=[];
foreach(($pilotCatalog['registry']??[]) as $path=>$entry){
    if(($entry['type']??'')!=='hotel_tours') continue;
    $state=$entry['page']['search_state']??[];
    $hotelId=(int)($state['hotel']??0);
    if($hotelId<=0) pilot_package_fail('hotel_identity');
    $slug=preg_replace('/-'.$hotelId.'\/$/','',basename(rtrim((string)$path,'/')).'-'.$hotelId.'/');
    $slug=basename(rtrim((string)$path,'/'));
    $evidence[]=[
        'country_id'=>(int)($state['country']??0),
        'hotel_id'=>$hotelId,
        'hotel_slug'=>$slug,
        'evidence_epoch'=>$now,
        'freshness_seconds'=>600,
    ];
}
if(count($evidence)!==9) pilot_package_fail('evidence_count');
$package=v2_seo_hotel_review_pilot_package($families,$evidence,$now);
if(($package['state']??'')!=='review_only_manifest_bound_pilot_package') pilot_package_fail('state');
if(($package['hotel_count']??0)!==9||($package['country_count']??0)!==3) pilot_package_fail('counts');
if(($package['registry_count']??0)!==12) pilot_package_fail('registry_scope');
$manifest=$package['manifest']??[];
if(($manifest['integrity_ok']??false)!==true||($manifest['family_quality_floor']??0)!==100||($manifest['hotel_evidence_fresh']??false)!==true) pilot_package_fail('manifest');
$slice=$package['slice']??[];
if(($slice['manifest_bound']??false)!==true||($slice['total']??0)!==9||($slice['evidence_fresh']??false)!==true) pilot_package_fail('slice');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag){
    if(($package[$flag]??true)!==false||($slice[$flag]??true)!==false) pilot_package_fail('boundary_'.$flag);
}
if(($package['publication_candidates']??null)!==[]||($package['explicit_user_indexation_approval_required']??false)!==true) pilot_package_fail('approval_boundary');

$stale=$evidence; $stale[0]['evidence_epoch']=$now-601; $stale[0]['freshness_seconds']=600;
$blocked=false;
try{v2_seo_hotel_review_pilot_package($families,$stale,$now);}catch(InvalidArgumentException $e){$blocked=true;}
if(!$blocked) pilot_package_fail('stale_evidence_allowed');

echo "SEO_HOTEL_REVIEW_PILOT_PACKAGE_OK countries=3 hotels=9 registry=12 manifestBound=1 publication=0 indexation=0 staleBlocked=1\n";
