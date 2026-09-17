<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_fingerprint_room_evidence_v1.php';

function need(bool $v,string $m): void { if(!$v) throw new RuntimeException($m); }

$ctx = ['date'=>'2026-10-26','nights'=>7,'adults'=>2,'children'=>0];

$tv = [
    $ctx + ['hotel_id'=>'100','hotel_name'=>'Sunrise Royal Makadi Resort','operator_name'=>'Библио-Глобус','room_raw'=>'STANDARD ROOM','meal_raw'=>'AI','price'=>180000],
    $ctx + ['hotel_id'=>'100','hotel_name'=>'Sunrise Royal Makadi Resort','operator_name'=>'FUN&SUN','room_raw'=>'Standard','meal_raw'=>'All Inclusive','price'=>181000],
    $ctx + ['hotel_id'=>'100','hotel_name'=>'Sunrise Royal Makadi Resort','operator_name'=>'Интурист','room_raw'=>'DELUXE SEA VIEW ROOM','meal_raw'=>'AI','price'=>220000],
    $ctx + ['hotel_id'=>'100','hotel_name'=>'Sunrise Royal Makadi Resort','operator_name'=>'ANEX TOUR','room_raw'=>'Deluxe Sea View','meal_raw'=>'AI','price'=>219500,'native_anex_hotel_id'=>'4158','hotel_url'=>'https://tourvisor.test/h/100'],
    $ctx + ['hotel_id'=>'101','hotel_name'=>'Other Hotel','operator_name'=>'ANEX TOUR','room_raw'=>'STANDARD ROOM','meal_raw'=>'AI','price'=>150000],
];

$samo = [
    $ctx + ['hotel_id'=>'900','hotel_name'=>'SUNRISE ROYAL MAKADI','operator_name'=>'Biblio Globus','room_raw'=>'Standard Room','meal_raw'=>'AI','price'=>180500],
    $ctx + ['hotel_id'=>'900','hotel_name'=>'SUNRISE ROYAL MAKADI','operator_name'=>'FUN SUN','room_raw'=>'STANDARD ROOM','meal_raw'=>'AI','price'=>181300],
    $ctx + ['hotel_id'=>'900','hotel_name'=>'SUNRISE ROYAL MAKADI','operator_name'=>'Intourist','room_raw'=>'Deluxe Sea View','meal_raw'=>'All Inclusive','price'=>220400],
    $ctx + ['hotel_id'=>'900','hotel_name'=>'SUNRISE ROYAL MAKADI','operator_name'=>'Анекс','room_raw'=>'DELUXE SEA VIEW ROOM','meal_raw'=>'AI','price'=>219800,'native_anex_hotel_id'=>'4158','hotel_url'=>'https://samo.test/h/900'],
    $ctx + ['hotel_id'=>'901','hotel_name'=>'Other Hotel','operator_name'=>'ANEX TOUR','room_raw'=>'Family Room','meal_raw'=>'AI','price'=>150500],
];

$anex = [
    $ctx + ['hotel_id'=>'4158','hotel_name'=>'Sunrise Royal Makadi Resort','operator_name'=>'ANEX','room_raw'=>'Deluxe Sea View Room','meal_raw'=>'AI','price'=>219700,'native_anex_hotel_id'=>'4158','hotel_url'=>'https://anex.test/hotel?hotelCode=4158'],
];

$r = hmf_resolve($tv,$samo,$anex);
need($r['tourvisor_hotels']===2,'tv_hotels');
need($r['samo_hotels']===2,'samo_hotels');
need($r['direct_anex_hotels']===1,'anex_hotels');
need($r['hotel_candidate_count']===2,'hotel_candidates');

$main = null;
foreach($r['hotel_candidates'] as $c) if($c['tv_hotel_id']==='100' && $c['samo_hotel_id']==='900') $main=$c;
need(is_array($main),'main_pair');
need($main['operator_overlap']['operators']===['anex','biblio','funsun','intourist'],'all_four_operator_overlap');
need($main['native_anex_overlap']===['4158'],'native_anex_anchor');
need($main['hotel_evidence_class']==='direct_anex_anchor_plus_operator_fingerprint','hotel_evidence_class');
need($main['safe_to_write_now']===false,'hotel_no_auto_write');

$rooms=[];
foreach($main['room_candidates'] as $c)$rooms[$c['room_key']]=$c;
need(isset($rooms['standard']),'standard_room');
need(isset($rooms['deluxe sea view']),'deluxe_room');
need($rooms['standard']['operator_count']===2,'standard_multi_operator');
need($rooms['deluxe sea view']['operator_count']===2,'deluxe_multi_operator');
need($rooms['deluxe sea view']['anex_rooms']===['Deluxe Sea View Room'],'anex_room_third_leg');
need($rooms['standard']['safe_to_write_now']===false,'room_no_auto_write');

$other = null;
foreach($r['hotel_candidates'] as $c) if($c['tv_hotel_id']==='101' && $c['samo_hotel_id']==='901') $other=$c;
need(is_array($other),'other_pair');
need(count($other['room_candidates'])===0,'rooms_do_not_cross_or_fuzzy_merge');

need(hmf_url('https://example.test/hotel?session=secret')===null,'secret_url_rejected');
need(hmf_room_key('Family Sea View Room')==='family sea view','room_qualifiers_preserved');

echo "MATCH_OPERATOR_FINGERPRINT_ROOM_EVIDENCE_TEST_OK\n";
