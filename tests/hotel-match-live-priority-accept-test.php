<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_live_priority_accept.php';

function mlpa_assert(bool $value,string $message): void { if(!$value) throw new RuntimeException($message); }

$base=[
    'rule'=>'unique_ordered_identity_plus_direct_geo',
    'target_local_hotel_id'=>42,
    'country_id'=>4,
    'coordinate_conflict'=>false,
    'distance_m'=>null,
    'source_places'=>['Сиде'],
    'target_region'=>'Сиде',
    'target_subregion'=>'',
    'pair_guard'=>['critical_ok'=>true],
    'strict_identity'=>['identity_tokens'=>['example','beach']],
];
mlpa_assert(mlpa_candidate_safe($base),'safe ordered identity rejected');

$x=$base;$x['coordinate_conflict']=true;
mlpa_assert(!mlpa_candidate_safe($x),'coordinate conflict accepted');
$x=$base;$x['distance_m']=6000;$x['source_places']=[];
mlpa_assert(!mlpa_candidate_safe($x),'>5km accepted');
$x=$base;$x['pair_guard']=['critical_ok'=>false];
mlpa_assert(!mlpa_candidate_safe($x),'critical qualifier mismatch accepted');
$x=$base;$x['strict_identity']=null;
mlpa_assert(!mlpa_candidate_safe($x),'ordered lane without strict identity accepted');
$x=$base;$x['target_local_hotel_id']=0;
mlpa_assert(!mlpa_candidate_safe($x),'zero target accepted');
$x=$base;$x['country_id']=99999;
mlpa_assert(!mlpa_candidate_safe($x),'non-core8 accepted');
$x=$base;$x['rule']='strong_fuzzy_direct_geo_anchor_guard';
mlpa_assert(!mlpa_candidate_safe($x),'evidence-only fuzzy lane accepted');

$exact=$base;
$exact['rule']='unique_exact_name_plus_geo';
$exact['strict_identity']=null;
mlpa_assert(mlpa_candidate_safe($exact),'exact+geo rejected');

echo "hotel-match-live-priority-accept-test: ok\n";
