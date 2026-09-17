<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_common4_full_drain_v1.php';

function hmc_need(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }

$ops=hmc_common_operators(
    [
        ['id'=>101,'name'=>'ANEX TOUR'],['id'=>102,'name'=>'Библио-Глобус'],
        ['id'=>103,'name'=>'FUN&SUN'],['id'=>104,'name'=>'Интурист'],['id'=>105,'name'=>'Other'],
    ],
    [
        ['id'=>501,'name'=>'ANEX'],['id'=>502,'name'=>'Biblio Globus'],
        ['id'=>503,'name'=>'FUN SUN'],['id'=>504,'name'=>'Intourist'],['id'=>505,'name'=>'Other'],
    ]
);
hmc_need(array_keys($ops['common'])===['anex','biblio','funsun','intourist'],'common4');
hmc_need($ops['missing']===[],'missing');
hmc_need(implode(',',array_map(fn($x)=>(string)$x['samo']['id'],$ops['common']))==='501,502,503,504','samo_csv');

$merged=hmc_tv_merge_rows(
    [
        ['id'=>1,'name'=>'Alpha','tours'=>[['id'=>'t1','operator'=>101,'roomType'=>'STANDARD ROOM']]],
    ],
    [
        ['id'=>1,'name'=>'Alpha','tours'=>[['id'=>'t2','operator'=>102,'roomType'=>'Standard']]],
        ['id'=>2,'name'=>'Beta','tours'=>[['id'=>'t3','operator'=>103,'roomType'=>'Family Sea View Room']]],
    ]
);
$sig=hmc_tv_signatures($merged);
hmc_need($sig['hotels']===2&&$sig['tours']===3,'tv_union');

$common=$ops['common'];
$tvRows=hmc_tv_offer_rows([
    [
        'id'=>100,'name'=>'Sunrise Royal Makadi Resort',
        'tours'=>[
            ['id'=>'tv-a','operator'=>102,'date'=>'2026-10-26','nights'=>7,'roomType'=>'STANDARD ROOM',
                'meal'=>['fullName'=>'All Inclusive'],'price'=>180000,'currency'=>'RUB'],
            ['id'=>'tv-b','operator'=>103,'date'=>'2026-10-26','nights'=>7,'roomType'=>'Standard',
                'meal'=>['name'=>'AI'],'price'=>181000,'currency'=>'RUB'],
            ['id'=>'tv-c','operator'=>101,'date'=>'2026-10-26','nights'=>7,'roomType'=>'DELUXE SEA VIEW ROOM',
                'meal'=>['name'=>'AI'],'price'=>220000,'currency'=>'RUB'],
        ],
    ],
    [
        'id'=>101,'name'=>'Other Hotel',
        'tours'=>[
            ['id'=>'tv-d','operator'=>102,'date'=>'2026-10-26','nights'=>7,'roomType'=>'STANDARD ROOM',
                'meal'=>['name'=>'AI'],'price'=>140000,'currency'=>'RUB'],
        ],
    ],
],'2026-10-26',$common);

$bySamoId=[];
foreach($common as $family=>$pair)$bySamoId[(int)$pair['samo']['id']]=['family'=>$family,'name'=>$pair['samo']['name']];
$samoRaw=[
    [
        'id'=>'sa-a','hotelKey'=>'900','hotel'=>'SUNRISE ROYAL MAKADI','operatorKey'=>502,'operator'=>'Biblio Globus',
        'isOperatorHotelKey'=>0,'price'=>'180500','currency'=>'RUB','currencyKey'=>'643','checkIn'=>'26.10.2026',
        'nights'=>'7','room'=>'Standard Room','meal'=>'All Inclusive','htplace'=>'DBL','adult'=>'2','child'=>'0',
    ],
    [
        'id'=>'sa-b','hotelKey'=>'900','hotel'=>'SUNRISE ROYAL MAKADI','operatorKey'=>503,'operator'=>'FUN SUN',
        'isOperatorHotelKey'=>0,'price'=>'181500','currency'=>'RUB','currencyKey'=>'643','checkIn'=>'26.10.2026',
        'nights'=>'7','room'=>'STANDARD ROOM','meal'=>'AI','htplace'=>'DBL','adult'=>'2','child'=>'0',
    ],
    [
        'id'=>'sa-c','hotelKey'=>'900','hotel'=>'SUNRISE ROYAL MAKADI','operatorKey'=>501,'operator'=>'ANEX',
        'isOperatorHotelKey'=>0,'price'=>'220500','currency'=>'RUB','currencyKey'=>'643','checkIn'=>'26.10.2026',
        'nights'=>'7','room'=>'Deluxe Sea View','meal'=>'AI','htplace'=>'DBL','adult'=>'2','child'=>'0',
        'original'=>['hotelKey'=>'4158','hotel'=>'Sunrise Royal Makadi Resort'],
    ],
    [
        'id'=>'sa-d','hotelKey'=>'901','hotel'=>'Other Hotel','operatorKey'=>502,'operator'=>'Biblio Globus',
        'isOperatorHotelKey'=>0,'price'=>'141000','currency'=>'RUB','currencyKey'=>'643','checkIn'=>'26.10.2026',
        'nights'=>'7','room'=>'Standard','meal'=>'AI','htplace'=>'DBL','adult'=>'2','child'=>'0',
    ],
];
$samoRows=[];
foreach($samoRaw as $raw){$row=hmc_samo_offer_row($raw,'2026-10-26',$bySamoId);hmc_need(is_array($row),'samo_row');$samoRows[]=$row;}

hmc_need($samoRows[2]['native_anex_hotel_id']==='4158','native_anex');
hmc_need($samoRows[2]['room_raw']==='Deluxe Sea View','raw_room');
hmc_need(hmf_room_key('DELUXE SEA VIEW ROOM')==='deluxe sea view','qualifier_preserved');
hmc_need(hmf_room_key('FAMILY SEA VIEW ROOM')==='family sea view','family_preserved');
hmc_need(hmf_room_key('SUITE ROOM')==='suite','suite_preserved');

$resolved=hmf_resolve($tvRows,$samoRows,[]);
hmc_need($resolved['hotel_candidate_count']===2,'two_hotel_pairs');
$main=null;$other=null;
foreach($resolved['hotel_candidates'] as $candidate){
    if((string)$candidate['tv_hotel_id']==='100')$main=$candidate;
    if((string)$candidate['tv_hotel_id']==='101')$other=$candidate;
}
hmc_need(is_array($main)&&is_array($other),'candidate_pairs');
$standard=null;$deluxe=null;
foreach($main['room_candidates'] as $room){
    if($room['room_key']==='standard')$standard=$room;
    if($room['room_key']==='deluxe sea view')$deluxe=$room;
}
hmc_need(is_array($standard),'standard_room');
hmc_need($standard['operator_count']===2,'repeated_operator_confidence');
hmc_need($standard['evidence_class']==='repeated_same_hotel_context','repeated_class');
hmc_need(is_array($deluxe),'deluxe_room');
hmc_need(in_array('DELUXE SEA VIEW ROOM',$deluxe['tv_rooms'],true),'tv_raw_deluxe');
hmc_need(in_array('Deluxe Sea View',$deluxe['samo_rooms'],true),'samo_raw_deluxe');

$otherKeys=array_column($other['room_candidates'],'room_key');
hmc_need($otherKeys===['standard'],'hotel_local_only');

$anex=[
    [
        'hotel_id'=>'4158','hotel_name'=>'Sunrise Royal Makadi Resort','operator_name'=>'ANEX',
        'date'=>'2026-10-26','nights'=>7,'adults'=>2,'children'=>0,
        'room_raw'=>'Deluxe Sea View Room','meal_raw'=>'AI','price'=>220200,
        'native_anex_hotel_id'=>'4158',
    ],
];
$tvWithAnchor=$tvRows;
foreach($tvWithAnchor as &$row)if($row['hotel_id']==='100'&&$row['operator_family']==='anex')$row['native_anex_hotel_id']='4158';
unset($row);
$triplet=hmf_resolve($tvWithAnchor,$samoRows,$anex);
$mainTriplet=null;
foreach($triplet['hotel_candidates'] as $candidate)if((string)$candidate['tv_hotel_id']==='100')$mainTriplet=$candidate;
hmc_need(is_array($mainTriplet),'triplet_pair');
hmc_need($mainTriplet['native_anex_overlap']===['4158'],'native_anchor_overlap');
$tripletDeluxe=null;
foreach($mainTriplet['room_candidates'] as $room)if($room['room_key']==='deluxe sea view')$tripletDeluxe=$room;
hmc_need(is_array($tripletDeluxe),'triplet_deluxe');
hmc_need($tripletDeluxe['anex_rooms']===['Deluxe Sea View Room'],'direct_anex_third_leg');

$detail=hmc_detail_evidence(['operatorLink'=>'https://online.anextour.test/search?HOTELLIST=4158&foo=1']);
hmc_need($detail['native_hotel_refs']===['4158'],'hotelcode_detail');
hmc_need(hmc_url('https://example.test/hotel?sid=secret')===null,'secret_url_rejected');
hmc_need(hmc_date('26.10.2026','x')==='2026-10-26','date_normalization');

echo "MATCH_TV_SAMO_COMMON4_FULL_DRAIN_TEST_OK\n";
