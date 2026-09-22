<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_common4_side_star4_samo_v5.php';

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


$week=hmc_week_dates();
hmc_need($week===['2026-10-05','2026-10-06','2026-10-07','2026-10-08','2026-10-09','2026-10-10','2026-10-11'],'weekly_window');

$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_common4_side_star4_v4.php');
hmc_need(!str_contains($source,"/tours/dates"),'no_tv_dates');
hmc_need(str_contains($source,"'dateFrom'=>\$date,'dateTo'=>\$dateTo"),'tv_week_window');
hmc_need(str_contains($source,"'CHECKIN_BEG'=>\$ymd,'CHECKIN_END'=>\$ymdTo"),'samo_week_window');
hmc_need(str_contains($source,"if(\$requests===0)"),'explicit_continue_exhaustion');
hmc_need(!str_contains($source,'hmc_enrich_tv_anex(\$tvRows'),'no_mass_detail_invocation');
hmc_need(str_contains($source,"'detail_queue_state'=>'pending_current_and_fuel_reconcile'"),'selective_detail_deferred');
hmc_need(!str_contains($source,'v2_data_tv_get('),'no_hidden_tv_retry_client');
hmc_need(str_contains($source,"hmc_private_evidence_record_raw('tourvisor'"),'tv_raw_bytes');
hmc_need(str_contains($source,"hmc_private_evidence_record_raw('samo-price'"),'samo_raw_bytes');
hmc_need(str_contains($source,"'tv_tariff_search_units'=>\$GLOBALS['HMC_TV_TARIFF_UNITS']"),'tv_tariff_units');

$tmp=sys_get_temp_dir().'/hmc2-'.bin2hex(random_bytes(5));
mkdir($tmp,0700,true);
hmc_private_evidence_init($tmp);
$sha=hmc_private_evidence_record('test',['page'=>1],['hotel'=>'raw private evidence','operatorLink'=>'https://example.test/hotel?id=1']);
$files=glob($tmp.'/evidence-private/*.json')?:[];
hmc_need(count($files)===1,'private_evidence_file');
hmc_need(hash_file('sha256',$files[0])===$sha,'private_evidence_hash');
hmc_need(($GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES']??[])===[$sha],'private_hash_projection');
array_map('unlink',$files);rmdir($tmp.'/evidence-private');rmdir($tmp);

$GLOBALS['HMC_PRIVATE_EVIDENCE_HASHES']=[];
$tmp=sys_get_temp_dir().'/hmc2raw-'.bin2hex(random_bytes(5));
mkdir($tmp,0700,true);hmc_private_evidence_init($tmp);
$raw="\x00raw\nbytes\xff";
$rawSha=hmc_private_evidence_record_raw('tourvisor',['path'=>'/tours/search'],429,$raw);
$bins=glob($tmp.'/evidence-private/*.bin')?:[];$metas=glob($tmp.'/evidence-private/*.meta.json')?:[];
hmc_need(count($bins)===1&&count($metas)===1,'raw_evidence_pair');
hmc_need(file_get_contents($bins[0])===$raw&&hash_file('sha256',$bins[0])===$rawSha,'raw_exact_bytes');
$meta=json_decode((string)file_get_contents($metas[0]),true,32,JSON_THROW_ON_ERROR);
hmc_need(($meta['http_status']??null)===429&&($meta['raw_sha256']??null)===$rawSha,'raw_meta');
array_map('unlink',array_merge($bins,$metas));rmdir($tmp.'/evidence-private');rmdir($tmp);


$tmp=sys_get_temp_dir().'/hmc3prev-'.bin2hex(random_bytes(5));mkdir($tmp.'/evidence-private',0700,true);
file_put_contents($tmp.'/result.json',json_encode(['operation'=>HMC_PREVIOUS_OP,'state'=>'terminal_failed_no_replay','reason'=>'tv_call_budget']));
file_put_contents($tmp.'/receipt.json',json_encode(['operation'=>HMC_PREVIOUS_OP,'provider_accessed'=>true,'no_replay'=>true]));
file_put_contents($tmp.'/search-plan.json',json_encode(['route'=>['resort'=>'Side','tv_region'=>['id'=>23,'name'=>'Сиде'],'samo_townto'=>['id'=>20,'name'=>'Сиде']],
    'date_from'=>HMC_DATE_FROM,'date_to'=>HMC_DATE_TO,'nights'=>HMC_NIGHTS,'adults'=>HMC_ADULTS,
    'tv_operator_ids'=>[13,18,25,43],'samo_operator_ids'=>['5','115','315','342']]));
$seq=0;
$put=function(string $path,array $params,array $reply)use($tmp,&$seq){
    ++$seq;$base=$tmp.'/evidence-private/'.sprintf('%04d-tourvisor',$seq);$raw=json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    file_put_contents($base.'.bin',$raw);file_put_contents($base.'.meta.json',json_encode(['source'=>'tourvisor','raw_sha256'=>hash('sha256',$raw),'meta'=>['path'=>$path,'params'=>$params]]));
};
$put('/tours/search',['hotelCategory'=>4],['searchId'=>123]);
$put('/tours/search/123',['limit'=>100],['hotels'=>[['id'=>7,'name'=>'Hotel','tours'=>[['id'=>'t1']]]]]);
for($i=1;$i<=32;$i++)$put('/tours/search/123/continue',[],['requestCount'=>1]);
$cp=hmc3_previous_checkpoint($tmp);
hmc_need($cp['search_id']===123&&$cp['prior_continue_calls']===32&&$cp['last_request_count']===1,'resume_checkpoint');
hmc_need(hmc_tv_signatures($cp['rows'])===['hotels'=>1,'tours'=>1],'resume_union');
foreach(glob($tmp.'/evidence-private/*')?:[] as $f)unlink($f);rmdir($tmp.'/evidence-private');unlink($tmp.'/result.json');unlink($tmp.'/receipt.json');unlink($tmp.'/search-plan.json');rmdir($tmp);

hmc_need(str_contains($source,"foreach([4] as \$star)"),'star4_only');
hmc_need(!str_contains($source,'$td=$star===3?hmc3_tv_resume'),'no_expired_search_resume');
hmc_need(!str_contains($source,'$td=hmc_tv_drain($tvParams,$tvCounter)'),'no_new_tv_search');
hmc_need(str_contains($source,"'acquisition_scope'=>'retained_partial_tv4_plus_fresh_samo4'"),'fresh_pair_scope');

echo "MATCH_TV_SAMO_COMMON4_SIDE_STAR4_SAMO_V5_TEST_OK\n";
