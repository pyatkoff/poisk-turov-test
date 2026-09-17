<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_three_source_resort_star_v1.php';

function need(bool $ok, string $label): void { if (!$ok) throw new RuntimeException('FAIL:' . $label); }
function baseRow(string $name, int $stars, string $meal, string $room, string $price): array {
    return ['hotel_name'=>$name,'resort'=>'Sharm El Sheikh','stars'=>$stars,'date'=>'2026-10-20','nights'=>7,'adults'=>2,'children'=>0,
        'meal'=>$meal,'room'=>$room,'price'=>$price,'currency'=>'RUB'];
}

$input=['schema_version'=>1,'anex'=>[],'andromeda'=>[],'tourvisor'=>[]];

// Direct ANEX anchor with two comparable contexts.
$input['anex'][] = baseRow('Barcelo Tiran Sharm Hotel',5,'AI','Deluxe','100000') + ['anex_hotel_id'=>'4158'];
$input['anex'][] = baseRow('Barcelo Tiran Sharm Hotel',5,'AI','Sea View','105000') + ['anex_hotel_id'=>'4158'];
$input['andromeda'][] = baseRow('Barcelo Tiran Sharm',5,'AI','Deluxe','100000') + [
    'andromeda_hotel_id'=>'177152','operator_key'=>'5','is_operator_hotel_key'=>0,'original_hotel_id'=>'4158'];
$input['andromeda'][] = baseRow('Barcelo Tiran Sharm',5,'AI','Sea View','105000') + [
    'andromeda_hotel_id'=>'177152','operator_key'=>'5','is_operator_hotel_key'=>0,'original_hotel_id'=>'4158'];
$input['tourvisor'][] = baseRow('Barcelo Tiran Sharm',5,'AI','Deluxe','114000') + [
    'tv_hotel_id'=>'21477','tour_id'=>'7001','operator_name'=>'ANEX','fuel_charge'=>'14000','operator_link_anex_id'=>'4158'];

$r=AnyTourAnexThreeSourceResortStarV1::resolve($input);
need($r['counts']['andromeda_direct_bindings']===1,'andromeda direct');
need($r['counts']['tourvisor_direct_confirmations']===1,'tv direct');
need(($r['direct_tourvisor'][0]['anex_hotel_id']??null)==='4158','tv exact anex id');

// Strong 1-1-1 candidate without operatorLink: exact hotel name + price/fuel relation.
$x=['schema_version'=>1,'anex'=>[],'andromeda'=>[],'tourvisor'=>[]];
$x['anex'][] = baseRow('Royal Example Hotel',4,'AI','Standard','90000') + ['anex_hotel_id'=>'5001'];
$x['anex'][] = baseRow('Royal Example Hotel',4,'AI','Family','95000') + ['anex_hotel_id'=>'5001'];
$x['andromeda'][] = baseRow('Royal Example',4,'AI','Standard','90000') + [
    'andromeda_hotel_id'=>'90001','operator_key'=>5,'is_operator_hotel_key'=>'0','original_hotel_id'=>'5001'];
$x['tourvisor'][] = baseRow('Royal Example',4,'AI','Standard','102000') + [
    'tv_hotel_id'=>'30001','tour_id'=>'8001','operator_name'=>'ANEX Tour','fuel_charge'=>'12000'];
$x['tourvisor'][] = baseRow('Royal Example',4,'AI','Family','107000') + [
    'tv_hotel_id'=>'30001','tour_id'=>'8002','operator_name'=>'ANEX Tour','fuel_charge'=>'12000'];
$r=AnyTourAnexThreeSourceResortStarV1::resolve($x);
need($r['counts']['unique_price_name_candidates']===1,'strong candidate');
need(($r['prepared_candidates'][0]['mapping_write_authorized']??true)===false,'no auto write');
need(($r['prepared_candidates'][0]['anex_hotel_id']??null)==='5001','candidate target');

// Same bucket but two plausible ANEX hotels must never become unique.
$y=$x;
$y['anex'][] = baseRow('Royal Example Annex',4,'AI','Standard','90000') + ['anex_hotel_id'=>'5002'];
$y['anex'][] = baseRow('Royal Example Annex',4,'AI','Family','95000') + ['anex_hotel_id'=>'5002'];
$y['andromeda'][] = baseRow('Royal Example Annex',4,'AI','Standard','90000') + [
    'andromeda_hotel_id'=>'90002','operator_key'=>5,'is_operator_hotel_key'=>0,'original_hotel_id'=>'5002'];
$r=AnyTourAnexThreeSourceResortStarV1::resolve($y);
need($r['counts']['unique_price_name_candidates']===1,'name still disambiguates exact target');

// If names do not disambiguate and two price-identical candidates exist, hold ambiguity.
$z=['schema_version'=>1,'anex'=>[],'andromeda'=>[],'tourvisor'=>[]];
foreach ([['6001','91001','Alpha Resort'],['6002','91002','Beta Resort']] as [$aid,$did,$name]) {
    $z['anex'][]=baseRow($name,3,'AI','Standard','80000')+['anex_hotel_id'=>$aid];
    $z['anex'][]=baseRow($name,3,'AI','Family','85000')+['anex_hotel_id'=>$aid];
    $z['andromeda'][]=baseRow($name,3,'AI','Standard','80000')+['andromeda_hotel_id'=>$did,'operator_key'=>5,'is_operator_hotel_key'=>0,'original_hotel_id'=>$aid];
}
$z['tourvisor'][]=baseRow('Unknown Brand',3,'AI','Standard','90000')+['tv_hotel_id'=>'31001','tour_id'=>'9001','operator_name'=>'ANEX','fuel_charge'=>'10000'];
$z['tourvisor'][]=baseRow('Unknown Brand',3,'AI','Family','95000')+['tv_hotel_id'=>'31001','tour_id'=>'9002','operator_name'=>'ANEX','fuel_charge'=>'10000'];
$r=AnyTourAnexThreeSourceResortStarV1::resolve($z);
need($r['counts']['unique_price_name_candidates']===0,'ambiguous no unique');
need($r['counts']['ambiguous_tv_hotels']===1,'ambiguous retained');

// Conflicting direct Tourvisor native IDs fail closed.
$c=$input;
$c['tourvisor'][] = baseRow('Barcelo Tiran Sharm',5,'AI','Sea View','119000') + [
    'tv_hotel_id'=>'21477','tour_id'=>'7002','operator_name'=>'ANEX','fuel_charge'=>'14000','operator_link_anex_id'=>'9999'];
$r=AnyTourAnexThreeSourceResortStarV1::resolve($c);
need($r['counts']['conflicts']>=1,'tv conflict');

// Fortuna/Roulette are not hotels and disappear before matching.
$f=['schema_version'=>1,
    'anex'=>[baseRow('FORTUNA SHARM 5*',5,'AI','Run of house','70000')+['anex_hotel_id'=>'7777']],
    'andromeda'=>[baseRow('Roulette Sharm',5,'AI','Run of house','70000')+['andromeda_hotel_id'=>'97777','operator_key'=>5,'is_operator_hotel_key'=>0,'original_hotel_id'=>'7777']],
    'tourvisor'=>[baseRow('Фортуна Шарм',5,'AI','Run of house','70000')+['tv_hotel_id'=>'37777','tour_id'=>'9991','operator_name'=>'ANEX']]];
$r=AnyTourAnexThreeSourceResortStarV1::resolve($f);
need($r['counts']['anex_hotels']===0 && $r['counts']['andromeda_hotels']===0 && $r['counts']['tourvisor_hotels']===0,'fortuna excluded');

echo "ANEX_THREE_SOURCE_RESORT_STAR_V1_OK\n";
