<?php
declare(strict_types=1);

define('ABR_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anchored_provider_bridge_current_v1.php';

function at(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

function identity(string $ns, string $id, ?string $local, string $status, array $evidence = [], string $sha = 'a'): array {
    return [
        'supplier_namespace'=>$ns,
        'external_hotel_id'=>$id,
        'local_hotel_id'=>$local,
        'decision_status'=>$status,
        'evidence_sha256'=>str_repeat($sha, 64),
        'evidence_json'=>json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ];
}

$identities = [
    identity('andromeda_catalog','1','100','accepted',[],'1'),
    identity('andromeda_catalog','2','200','accepted',[],'2'),
    identity('andromeda_catalog','3','300','accepted',[],'3'),
    identity('andromeda_catalog','4','400','accepted',[],'4'),
    identity('andromeda_catalog','5','500','accepted',[],'5'),
    identity('andromeda_catalog','6','600','accepted',[],'6'),

    identity('operator_315','101',null,'pending',['provider_bridges'=>[['andromeda_hotel_id'=>'1','hotel_name'=>'ALPHA HOTEL','country_id'=>4]]],'a'),
    identity('operator_315','102',null,'pending',['provider_bridges'=>[['andromeda_hotel_id'=>'1','hotel_name'=>'ALPHA HOTEL','country_id'=>4]]],'b'),
    identity('operator_315','102','100','accepted',[],'c'),
    identity('operator_342','103',null,'pending',['provider_bridges'=>[['andromeda_hotel_id'=>'2','hotel_name'=>'MISSING HOTEL','country_id'=>4]]],'d'),
    identity('operator_5','104',null,'pending',['provider_bridges'=>[['andromeda_hotel_id'=>'3','hotel_name'=>'INACTIVE HOTEL','country_id'=>4]]],'e'),
    identity('operator_5','105',null,'pending',['provider_bridges'=>[['andromeda_hotel_id'=>'4','hotel_name'=>'PROTECTED HOTEL','country_id'=>4]],'manual_review'=>true],'f'),
    identity('operator_5','106',null,'pending',['provider_bridges'=>[['andromeda_hotel_id'=>'5','hotel_name'=>'DECISION HOTEL','country_id'=>4]]],'0'),
    identity('operator_5','107',null,'pending',['provider_bridges'=>[['andromeda_hotel_id'=>'6','hotel_name'=>'ALREADY ANEX','country_id'=>4]]],'7'),
];

$catalog = [
    ['id'=>100,'country_id'=>4,'country_name'=>'Turkey','region_id'=>10,'region_name'=>'Antalya','subregion_id'=>11,'subregion_name'=>'Side','name'=>'ALPHA HOTEL','category'=>'5*','latitude'=>36.1,'longitude'=>30.1,'is_active'=>1],
    // target 200 deliberately absent
    ['id'=>300,'country_id'=>4,'country_name'=>'Turkey','region_id'=>10,'region_name'=>'Antalya','subregion_id'=>11,'subregion_name'=>'Side','name'=>'INACTIVE HOTEL','category'=>'4*','latitude'=>36.2,'longitude'=>30.2,'is_active'=>0],
    ['id'=>400,'country_id'=>4,'country_name'=>'Turkey','region_id'=>10,'region_name'=>'Antalya','subregion_id'=>11,'subregion_name'=>'Side','name'=>'PROTECTED HOTEL','category'=>'4*','latitude'=>36.3,'longitude'=>30.3,'is_active'=>1],
    ['id'=>500,'country_id'=>4,'country_name'=>'Turkey','region_id'=>10,'region_name'=>'Antalya','subregion_id'=>11,'subregion_name'=>'Side','name'=>'DECISION HOTEL','category'=>'4*','latitude'=>36.4,'longitude'=>30.4,'is_active'=>1],
    ['id'=>600,'country_id'=>4,'country_name'=>'Turkey','region_id'=>10,'region_name'=>'Antalya','subregion_id'=>11,'subregion_name'=>'Side','name'=>'ALREADY ANEX','category'=>'4*','latitude'=>36.5,'longitude'=>30.5,'is_active'=>1],
];
$anexMappings = [
    ['anex_hotel_id'=>'107','catalog_hotel_id'=>'600','enabled'=>1],
];
$decisions = [
    ['anex_hotel_id'=>'106','catalog_hotel_id'=>'500'],
];

$rows = abr_build_dossier($identities, $anexMappings, $catalog, $decisions, []);
$byId = [];
foreach ($rows as $row) $byId[$row['provider_namespace'] . '/' . $row['provider_hotel_id']] = $row;

at(count($rows) === 5, 'accepted identity and enabled ANEX source must be subtracted');
at(isset($byId['operator_315/101']), 'clean anchored residual exists');
at(!isset($byId['operator_315/102']), 'accepted provider source subtracted');
at(!isset($byId['operator_5/107']), 'accepted ANEX source subtracted');
at($byId['operator_315/101']['status'] === 'review_saved_evidence', 'clean row remains review-only');
at($byId['operator_315/101']['name_relation']['exact_primary'] === true, 'exact primary name detected');
at($byId['operator_315/101']['country_relation'] === 'same', 'same country detected');
at($byId['operator_342/103']['status'] === 'hold_target_missing', 'missing target held');
at($byId['operator_5/104']['status'] === 'hold_target_inactive', 'inactive target held');
at($byId['operator_5/105']['status'] === 'hold_protected', 'embedded manual/protected state held');
at($byId['operator_5/106']['status'] === 'hold_protected', 'paired manual decision held');

$former = abr_name_relation('OLD NAME HOTEL', 'NEW NAME (EX. OLD NAME HOTEL)');
at($former['exact_any_form'] === true, 'former-name exact form detected');
at(in_array('OLD NAME HOTEL', $former['matching_forms'], true), 'former-name form preserved');
$generic = abr_name_relation('ALPHA RESORT HOTEL', 'ALPHA RESORT');
at($generic['exact_primary'] === false && $generic['exact_any_form'] === true, 'generic-reduced evidence reported but not primary exact');
at(abr_normalize_name('  Alpha---Hotel  ') === 'ALPHA HOTEL', 'normalization deterministic');

$census = abr_census($rows);
at($census['rows'] === 5, 'census rows');
at(($census['by_status']['hold_protected'] ?? 0) === 2, 'census protected');

echo "anchored provider bridge current v1 smoke PASS rows=" . count($rows) . "\n";
