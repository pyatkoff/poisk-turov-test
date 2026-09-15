<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_coordinate_anchor_accept.php';

function hcaa_test_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

$base=[
    'provider'=>'anex','country_id'=>4,'target_local_hotel_id'=>123,
    'distance_m'=>75.0,'existing_opposite_provider_bridge'=>false,
    'rule'=>'strict_unique_coordinate_name_alias',
    'name'=>[
        'safe'=>true,'critical_ok'=>true,'shared'=>2,'identity_shared'=>2,
        'ordered'=>true,'score'=>0.40,
    ],
];
hcaa_test_assert(hcaa_candidate_safe($base),'unique coordinate+name candidate should pass');

$bad=$base;$bad['distance_m']=101.0;
hcaa_test_assert(!hcaa_candidate_safe($bad),'non-bridge >100m blocked');

$bridge=$base;$bridge['existing_opposite_provider_bridge']=true;$bridge['rule']='strict_coordinate_name_plus_existing_andromeda_bridge';$bridge['distance_m']=249.9;
hcaa_test_assert(hcaa_candidate_safe($bridge),'bridge may extend radius to 250m only with name identity');
$bad=$bridge;$bad['distance_m']=250.1;
hcaa_test_assert(!hcaa_candidate_safe($bad),'bridge >250m blocked');
$bad=$bridge;$bad['name']['shared']=0;$bad['name']['identity_shared']=0;
hcaa_test_assert(!hcaa_candidate_safe($bad),'bridge cannot replace name identity');
$bad=$bridge;$bad['name']['identity_shared']=0;
hcaa_test_assert(!hcaa_candidate_safe($bad),'geography-only shared tokens blocked');
$bad=$bridge;$bad['name']['critical_ok']=false;
hcaa_test_assert(!hcaa_candidate_safe($bad),'critical qualifier mismatch blocked');
$bad=$bridge;$bad['name']['ordered']=false;$bad['name']['score']=0.44;
hcaa_test_assert(!hcaa_candidate_safe($bad),'weak unordered name similarity blocked');

$pair=hcaa_name_pair(['Fortuna Hotel Phu Quoc'],['Phu Quoc'],['MUONG THANH LUXURY PHU QUOC'],['Phu Quoc']);
hcaa_test_assert(!$pair['safe'],'geography-only name overlap must not identify a hotel');
$pair=hcaa_name_pair(['Grand Panorama Hotel'],['Marmaris'],['GRAND PANORAMA FAMILY SUITE HOTEL'],['Marmaris']);
hcaa_test_assert($pair['safe'],'two-token ordered identity should pass');
$pair=hcaa_name_pair(['May Hotel'],['Istanbul'],['OTTOMAN TIME'],['Istanbul']);
hcaa_test_assert(!$pair['safe'],'zero-token identity must remain blocked even with coordinates/bridge elsewhere');

echo "hotel-match-coordinate-anchor-accept-test: ok\n";
