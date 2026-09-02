<?php
require_once __DIR__ . '/../v2/seo-hotel-family-integrity-v1.php';
require_once __DIR__ . '/../v2/seo-hotel-launch-pilot-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-hotel-review-catalog-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-maldives-catalog-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-egypt-hotel-review-catalog-v1.php';

function family_integrity_fail(string $message): void
{
    fwrite(STDERR, "SEO_HOTEL_FAMILY_INTEGRITY_FAIL:$message\n");
    exit(1);
}

$families = [
    ['key'=>'turkey','country_id'=>4,'catalog'=>v2_seo_content_pilot_turkey_hotel_review_catalog()],
    ['key'=>'maldives','country_id'=>8,'catalog'=>v2_seo_content_pilot_maldives_catalog()],
    ['key'=>'egypt','country_id'=>1,'catalog'=>v2_seo_content_pilot_egypt_hotel_review_catalog()],
];
$report = v2_seo_hotel_family_integrity($families);
if (($report['family_count']??0)!==3) family_integrity_fail('family_count');
if (($report['hotel_count']??0)<3) family_integrity_fail('hotel_count');
if (($report['hotel_count']??0)!==($report['unique_paths']??-1)) family_integrity_fail('path_identity_parity');
if (($report['hotel_count']??0)!==($report['unique_country_hotel_identities']??-1)) family_integrity_fail('identity_parity');
if (($report['publication_candidates']??-1)!==0) family_integrity_fail('candidate_leak');
if (($report['state']??'')!=='review_noindex_integrity_only') family_integrity_fail('state');
foreach (($report['families']??[]) as $family) {
    if (($family['hotel_count']??0)<1) family_integrity_fail('empty_family');
    if (($family['publication_candidates']??-1)!==0) family_integrity_fail('family_candidate_leak');
    if (($family['state']??'')!=='review_noindex_integrity_only') family_integrity_fail('family_state');
}

// The controlled 3x3 pilot is review metadata only. Pin the exact boundary so a
// future catalog/publication refactor cannot silently turn a reviewed pilot into
// an indexable/publication candidate before the separate launch approval.
$pilot=v2_seo_hotel_launch_pilot_spec();
if (($pilot['state']??'')!=='proposal_only_requires_launch_approval') family_integrity_fail('pilot_state');
$pilotPaths=[];
foreach(($pilot['countries']??[]) as $bucket){
    foreach(($bucket['paths']??[]) as $path){
        if(isset($pilotPaths[$path])) family_integrity_fail('pilot_duplicate_path');
        $pilotPaths[$path]=true;
    }
}
if(count($pilotPaths)!==9) family_integrity_fail('pilot_count');
$matchedPilotPaths=[];
foreach($families as $family){
    $catalog=$family['catalog'];
    $registry=is_array($catalog['registry']??null)?$catalog['registry']:[];
    $reports=is_array($catalog['reports']??null)?$catalog['reports']:[];
    $candidates=array_fill_keys(array_map('strval',is_array($catalog['publication_candidates']??null)?$catalog['publication_candidates']:[]),true);
    foreach($pilotPaths as $path=>$_){
        if(!isset($registry[$path])) continue;
        if(isset($matchedPilotPaths[$path])) family_integrity_fail('pilot_cross_family_duplicate');
        $matchedPilotPaths[$path]=true;
        $entry=$registry[$path];
        $pageReport=is_array($reports[$path]??null)?$reports[$path]:[];
        if(($entry['type']??'')!=='hotel_tours') family_integrity_fail('pilot_wrong_type');
        if(($pageReport['status']??'')!=='review') family_integrity_fail('pilot_not_review');
        if(($pageReport['publishable']??false)!==true) family_integrity_fail('pilot_structurally_blocked');
        if(isset($candidates[$path])) family_integrity_fail('pilot_candidate_leak');
    }
}
if(count($matchedPilotPaths)!==9) family_integrity_fail('pilot_path_missing_from_catalogs');

try {
    v2_seo_hotel_family_integrity([$families[0], ['key'=>'turkey-copy','country_id'=>4,'catalog'=>$families[0]['catalog']]]);
    family_integrity_fail('cross_family_duplicate_paths_allowed');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'Duplicate hotel-tour path')) family_integrity_fail('duplicate_wrong_error');
}

echo 'SEO_HOTEL_FAMILY_INTEGRITY_OK families=3 hotels='.(int)$report['hotel_count']." candidates=0 globalIdentity=1 pilotReviewOnly=9\n";
