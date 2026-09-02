<?php
require_once __DIR__ . '/../v2/seo-hotel-launch-candidate-v1.php';
require_once __DIR__ . '/../v2/seo-hotel-launch-pilot-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-turkey-hotels-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-maldives-hotels-v1.php';
require_once __DIR__ . '/../v2/seo-content-pilot-egypt-hotels-v1.php';

function launch_candidate_fail(string $message): void
{
    fwrite(STDERR, "SEO_HOTEL_LAUNCH_CANDIDATE_FAIL:$message\n");
    exit(1);
}

$now = 1800000000;
$freshUntil = $now + 600;
$rows = [
    ['path'=>'/country/egypt/hotel/alpha-101/','country_id'=>1,'hotel_id'=>101,'evidence_epoch'=>$now,'evidence_expires_epoch'=>$freshUntil,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/turkey/hotel/beta-202/','country_id'=>4,'hotel_id'=>202,'evidence_epoch'=>$now,'evidence_expires_epoch'=>$freshUntil,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/maldives/hotel/gamma-303/','country_id'=>8,'hotel_id'=>303,'evidence_epoch'=>$now,'evidence_expires_epoch'=>$freshUntil,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/egypt/hotel/stale-404/','country_id'=>1,'hotel_id'=>404,'evidence_epoch'=>$now-1200,'evidence_expires_epoch'=>$now-600,'score'=>90,'ready_for_launch_review'=>false,'errors'=>['fresh_identity_evidence_required']],
];

$proposal = v2_seo_hotel_launch_candidate_proposal($rows, [
    '/country/turkey/hotel/beta-202/',
    '/country/egypt/hotel/alpha-101/',
], 2);
if (count($proposal)!==2) launch_candidate_fail('proposal_count');
if (($proposal[0]['path']??'')!=='/country/turkey/hotel/beta-202/') launch_candidate_fail('explicit_order');
foreach ($proposal as $row) {
    if (($row['state']??'')!=='proposal_only_requires_launch_approval') launch_candidate_fail('proposal_state');
    if (($row['score']??0)!==100) launch_candidate_fail('proposal_score');
}

$slice = v2_seo_hotel_country_launch_slice_proposal($rows, [
    ['country_id'=>4,'paths'=>['/country/turkey/hotel/beta-202/']],
    ['country_id'=>8,'paths'=>['/country/maldives/hotel/gamma-303/']],
    ['country_id'=>1,'paths'=>['/country/egypt/hotel/alpha-101/']],
], [4,8,1], 5, 15, $now);
if (($slice['state']??'')!=='proposal_only_requires_launch_approval') launch_candidate_fail('slice_state');
if (($slice['validated_at_epoch']??0)!==$now) launch_candidate_fail('slice_validation_time');
if (($slice['total']??0)!==3) launch_candidate_fail('slice_total');
foreach ([4,8,1] as $countryId) {
    if (count($slice['countries'][$countryId]??[])!==1) launch_candidate_fail('slice_country_'.$countryId);
}

try {
    v2_seo_hotel_launch_candidate_proposal($rows, ['/country/egypt/hotel/stale-404/']);
    launch_candidate_fail('non_ready_allowed');
} catch (InvalidArgumentException $e) {}

try {
    v2_seo_hotel_launch_candidate_proposal($rows, ['/country/egypt/hotel/alpha-101/','/country/egypt/hotel/alpha-101/']);
    launch_candidate_fail('duplicate_allowed');
} catch (InvalidArgumentException $e) {}

try {
    v2_seo_hotel_country_launch_slice_proposal($rows, [
        ['country_id'=>4,'paths'=>['/country/turkey/hotel/beta-202/']],
        ['country_id'=>8,'paths'=>['/country/maldives/hotel/gamma-303/']],
    ], [4,8,1], 5, 15, $now);
    launch_candidate_fail('missing_country_allowed');
} catch (InvalidArgumentException $e) {}

try {
    v2_seo_hotel_country_launch_slice_proposal($rows, [
        ['country_id'=>4,'paths'=>['/country/egypt/hotel/alpha-101/']],
        ['country_id'=>8,'paths'=>['/country/maldives/hotel/gamma-303/']],
        ['country_id'=>1,'paths'=>['/country/turkey/hotel/beta-202/']],
    ], [4,8,1], 5, 15, $now);
    launch_candidate_fail('country_mismatch_allowed');
} catch (InvalidArgumentException $e) {}

$replayed = $rows;
$replayed[0]['ready_for_launch_review'] = true;
$replayed[0]['score'] = 100;
$replayed[0]['evidence_expires_epoch'] = $now - 1;
try {
    v2_seo_hotel_country_launch_slice_proposal($replayed, [
        ['country_id'=>4,'paths'=>['/country/turkey/hotel/beta-202/']],
        ['country_id'=>8,'paths'=>['/country/maldives/hotel/gamma-303/']],
        ['country_id'=>1,'paths'=>['/country/egypt/hotel/alpha-101/']],
    ], [4,8,1], 5, 15, $now);
    launch_candidate_fail('expired_ready_row_replayed');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'currently fresh')) launch_candidate_fail('expired_wrong_error');
}

$spec = v2_seo_hotel_launch_pilot_spec();
if (($spec['state']??'')!=='proposal_only_requires_launch_approval') launch_candidate_fail('pilot_state');
$manifestPaths = [];
foreach ([v2_seo_turkey_hotel_manifest(), v2_seo_maldives_hotel_manifest(), v2_seo_egypt_hotel_manifest()] as $manifest) {
    foreach ($manifest as $entry) $manifestPaths[(string)($entry['href']??'')] = true;
}
$pilotRows=[];
foreach ($spec['countries'] as $bucket) {
    if (count($bucket['paths']??[])!==3) launch_candidate_fail('pilot_bucket_size');
    foreach ($bucket['paths'] as $path) {
        if (!isset($manifestPaths[$path])) launch_candidate_fail('pilot_path_missing_from_review_manifest');
        if (isset($pilotRows[$path])) launch_candidate_fail('pilot_duplicate_path');
        if (!preg_match('~-([1-9][0-9]*)/$~', $path, $m)) launch_candidate_fail('pilot_path_identity');
        $pilotRows[$path]=[
            'path'=>$path,
            'country_id'=>(int)$bucket['country_id'],
            'hotel_id'=>(int)$m[1],
            'evidence_epoch'=>$now,
            'evidence_expires_epoch'=>$freshUntil,
            'score'=>100,
            'ready_for_launch_review'=>true,
            'errors'=>[],
        ];
    }
}
if (count($pilotRows)!==9) launch_candidate_fail('pilot_total');
$pilotProposal=v2_seo_hotel_launch_pilot_proposal(array_values($pilotRows),$now);
if (($pilotProposal['total']??0)!==9) launch_candidate_fail('pilot_proposal_total');
if (($pilotProposal['max_per_country']??0)!==3 || ($pilotProposal['max_total']??0)!==9) launch_candidate_fail('pilot_caps');

$expiredPilot=array_values($pilotRows);
$expiredPilot[0]['evidence_expires_epoch']=$now-1;
try {
    v2_seo_hotel_launch_pilot_proposal($expiredPilot,$now);
    launch_candidate_fail('pilot_expired_evidence_allowed');
} catch (InvalidArgumentException $e) {}

echo "SEO_HOTEL_LAUNCH_CANDIDATE_OK explicit=1 readyOnly=1 cap=1 proposalOnly=1 countryBalanced=1 evidenceReplayBlocked=1 pilot=9 pilotManifestBound=1\n";
