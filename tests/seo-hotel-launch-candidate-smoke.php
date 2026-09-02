<?php
require_once __DIR__ . '/../v2/seo-hotel-launch-candidate-v1.php';

function launch_candidate_fail(string $message): void
{
    fwrite(STDERR, "SEO_HOTEL_LAUNCH_CANDIDATE_FAIL:$message\n");
    exit(1);
}

$rows = [
    ['path'=>'/country/egypt/hotel/alpha-101/','country_id'=>1,'hotel_id'=>101,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/turkey/hotel/beta-202/','country_id'=>4,'hotel_id'=>202,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/maldives/hotel/gamma-303/','country_id'=>8,'hotel_id'=>303,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/egypt/hotel/stale-404/','country_id'=>1,'hotel_id'=>404,'score'=>90,'ready_for_launch_review'=>false,'errors'=>['fresh_identity_evidence_required']],
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
]);
if (($slice['state']??'')!=='proposal_only_requires_launch_approval') launch_candidate_fail('slice_state');
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
    ]);
    launch_candidate_fail('missing_country_allowed');
} catch (InvalidArgumentException $e) {}

try {
    v2_seo_hotel_country_launch_slice_proposal($rows, [
        ['country_id'=>4,'paths'=>['/country/egypt/hotel/alpha-101/']],
        ['country_id'=>8,'paths'=>['/country/maldives/hotel/gamma-303/']],
        ['country_id'=>1,'paths'=>['/country/turkey/hotel/beta-202/']],
    ]);
    launch_candidate_fail('country_mismatch_allowed');
} catch (InvalidArgumentException $e) {}

echo "SEO_HOTEL_LAUNCH_CANDIDATE_OK explicit=1 readyOnly=1 cap=1 proposalOnly=1 countryBalanced=1\n";
