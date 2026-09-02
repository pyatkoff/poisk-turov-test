<?php
require_once __DIR__ . '/../v2/seo-launch-manifest-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-catalog-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-maldives-catalog-v1.php';

function manifest_fail(string $message): void { fwrite(STDERR,"SEO_LAUNCH_MANIFEST_FAIL:$message\n"); exit(1); }

$turkey = v2_seo_content_pilot_turkey_catalog();
$turkeyManifest = v2_seo_launch_manifest($turkey, [], 1000);
if (($turkeyManifest['integrity_ok']??false)!==true) manifest_fail('turkey_integrity');
if (($turkeyManifest['type_counts']['country']??0)!==1 || ($turkeyManifest['type_counts']['resort']??0)!==5) manifest_fail('turkey_family_counts');
if (($turkeyManifest['hotel_tours_publication_candidate_count']??-1)!==0 || ($turkeyManifest['hotel_tours_indexation_allowed']??true)!==false) manifest_fail('turkey_hotel_boundary');
if (!preg_match('/^[a-f0-9]{64}$/',(string)($turkeyManifest['manifest_sha256']??''))) manifest_fail('fingerprint');

$maldives = v2_seo_content_pilot_maldives_catalog();
$evidence=[];
foreach (($maldives['registry']??[]) as $path=>$entry) {
    if (($entry['type']??'')!=='hotel_tours') continue;
    $state=$entry['page']['search_state']??[];
    $evidence[]=[
        'country_id'=>(int)($state['country']??0),
        'hotel_id'=>(int)($state['hotel']??0),
        'hotel_slug'=>basename(rtrim((string)$path,'/')),
        'evidence_epoch'=>990,
        'freshness_seconds'=>1000,
    ];
}
$maldivesManifest=v2_seo_launch_manifest($maldives,$evidence,1000);
if (($maldivesManifest['integrity_ok']??false)!==true) manifest_fail('maldives_integrity');
if (($maldivesManifest['type_counts']['hotel_tours']??0)<1) manifest_fail('hotel_family_missing');
if (($maldivesManifest['hotel_tours_publication_candidate_count']??-1)!==0) manifest_fail('hotel_candidate_leak');
if (($maldivesManifest['hotel_tours_publication_allowed']??true)!==false || ($maldivesManifest['hotel_tours_indexation_allowed']??true)!==false) manifest_fail('hotel_publication_boundary');
if (($maldivesManifest['hotel_evidence_valid_until_epoch']??0)!==1990) manifest_fail('evidence_clock');

$unsafe=$maldives;
$hotelPath='';
foreach (($unsafe['registry']??[]) as $path=>$entry) if (($entry['type']??'')==='hotel_tours') { $hotelPath=(string)$path; break; }
if ($hotelPath==='') manifest_fail('no_hotel_fixture');
$unsafe['publication_candidates'][]=$hotelPath;
$blocked=v2_seo_launch_manifest($unsafe,$evidence,1000);
if (($blocked['integrity_ok']??true)!==false || !in_array('hotel_tours_publication_candidate_leak',$blocked['errors']??[],true)) manifest_fail('candidate_leak_not_blocked');

$dupe=$maldives;
$hotelPaths=[];
foreach (($dupe['registry']??[]) as $path=>$entry) if (($entry['type']??'')==='hotel_tours') $hotelPaths[]=$path;
if (count($hotelPaths)>=2) {
    $first=$dupe['registry'][$hotelPaths[0]]['page']['search_state']['hotel'];
    $dupe['registry'][$hotelPaths[1]]['page']['search_state']['hotel']=$first;
    $blocked=v2_seo_launch_manifest($dupe,$evidence,1000);
    if (($blocked['integrity_ok']??true)!==false || !in_array('duplicate_or_invalid_search_identity',$blocked['errors']??[],true)) manifest_fail('duplicate_identity_not_blocked');
}

echo "SEO_LAUNCH_MANIFEST_OK registry=1 readiness=1 hotelBoundary=1 evidenceClock=1 fingerprint=1\n";
