<?php
require_once __DIR__ . '/../v2/seo-hotel-launch-candidate-v1.php';

function launch_candidate_fail(string $message): void
{
    fwrite(STDERR, "SEO_HOTEL_LAUNCH_CANDIDATE_FAIL:$message\n");
    exit(1);
}

$rows = [
    ['path'=>'/country/a/hotel/alpha-101/','country_id'=>1,'hotel_id'=>101,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/b/hotel/beta-202/','country_id'=>2,'hotel_id'=>202,'score'=>100,'ready_for_launch_review'=>true,'errors'=>[]],
    ['path'=>'/country/c/hotel/gamma-303/','country_id'=>3,'hotel_id'=>303,'score'=>90,'ready_for_launch_review'=>false,'errors'=>['fresh_identity_evidence_required']],
];

$proposal = v2_seo_hotel_launch_candidate_proposal($rows, [
    '/country/b/hotel/beta-202/',
    '/country/a/hotel/alpha-101/',
], 2);
if (count($proposal)!==2) launch_candidate_fail('proposal_count');
if (($proposal[0]['path']??'')!=='/country/b/hotel/beta-202/') launch_candidate_fail('explicit_order');
foreach ($proposal as $row) {
    if (($row['state']??'')!=='proposal_only_requires_launch_approval') launch_candidate_fail('proposal_state');
    if (($row['score']??0)!==100) launch_candidate_fail('proposal_score');
}

try {
    v2_seo_hotel_launch_candidate_proposal($rows, ['/country/c/hotel/gamma-303/']);
    launch_candidate_fail('non_ready_allowed');
} catch (InvalidArgumentException $e) {}

try {
    v2_seo_hotel_launch_candidate_proposal($rows, ['/country/a/hotel/alpha-101/','/country/a/hotel/alpha-101/']);
    launch_candidate_fail('duplicate_allowed');
} catch (InvalidArgumentException $e) {}

try {
    v2_seo_hotel_launch_candidate_proposal($rows, ['/country/a/hotel/alpha-101/','/country/b/hotel/beta-202/'], 1);
    launch_candidate_fail('cap_bypass');
} catch (InvalidArgumentException $e) {}

echo "SEO_HOTEL_LAUNCH_CANDIDATE_OK explicit=1 readyOnly=1 cap=1 proposalOnly=1\n";
