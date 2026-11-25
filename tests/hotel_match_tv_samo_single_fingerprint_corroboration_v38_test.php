<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_tv_samo_single_fingerprint_corroboration_v38.php';

function v38t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}

v38t(V38_OP==='hotel-match-tv-samo-single-fingerprint-corroboration-1971-20260925-v38','op');
v38t(V38_SOURCE_OP==='hotel-match-tv-samo-common4-fingerprint-join-1971-20260925-v37','source_op');
v38t(V38_SOURCE_SHA==='b7ef6082b8d27ad822ddaf69dd86e9249523f859cd61ee1b547c106118ffc55a','source_sha');
v38t(V38_EXPECTED===['single_direct'=>9,'support_only'=>1],'counts');

v38t(v38_name_key('The Example Hotel & Spa')==='example','name_key');
v38t(v38_name_key('EXAMPLE RESORT')==='example','generic_strip');
v38t(v38_place_key('Side City')==='side','place_key');
v38t(v38_dist([36.7,31.9],[36.7,31.9])===0.0,'distance_zero');

$facts=[
 100=>['id'=>100,'name'=>'Example Hotel','normalized_name'=>'example hotel','country_id'=>5,'country_name'=>'Турция','region_name'=>'Side','subregion_name'=>'','is_active'=>1,'latitude'=>36.7,'longitude'=>31.9],
 101=>['id'=>101,'name'=>'Other Hotel','normalized_name'=>'other hotel','country_id'=>5,'country_name'=>'Турция','region_name'=>'Alanya','subregion_name'=>'','is_active'=>1,'latitude'=>36.5,'longitude'=>32.0],
];
$nameIndex=['example'=>[100=>true],'other'=>[101=>true]];
$snapshot=['wanted'=>[
 '900'=>[['stateinc'=>5,'names'=>['Example Hotel'],'places'=>['Side'],'points'=>[[36.7,31.9]],'file_sha256'=>str_repeat('a',64)]],
 '901'=>[['stateinc'=>5,'names'=>['Example Hotel'],'places'=>['Alanya'],'points'=>[[36.5,32.0]],'file_sha256'=>str_repeat('b',64)]],
]];
$c=['source_status'=>'single_direct','andromeda_catalog_id'=>'900','candidate_local_hotel_id'=>100,'frontier_bucket'=>'tv_only_missing_both','direct_operator_count'=>1,'support_operator_count'=>0,'source_row_sha256'=>str_repeat('c',64),'safe_to_write_now'=>false];
$r=v38_classify_candidate($c,$snapshot,[],[],[],[],[],$facts,$nameIndex,[],[5=>5]);
v38t($r['status']==='corroborated_single_direct','corroborated');
v38t($r['country_match']===true,'country');
v38t($r['name_evidence_keys']!==[],'name_evidence');
v38t($r['place_evidence_keys']!==[],'place_evidence');

$c2=$c;$c2['source_status']='support_only';
$r=v38_classify_candidate($c2,$snapshot,[],[],[],[],[],$facts,$nameIndex,[],[5=>5]);
v38t($r['status']==='support_only_review','support_stays_review');

$c3=$c;$c3['andromeda_catalog_id']='901';
$r=v38_classify_candidate($c3,$snapshot,[],[],[],[],[],$facts,$nameIndex,[],[5=>5]);
v38t($r['status']==='review_geo_not_supported','geo_fail');

$r=v38_classify_candidate($c,$snapshot,[],['900'=>[101=>true]],[],[],[],$facts,$nameIndex,[],[5=>5]);
v38t($r['status']==='hold_source_occupied','source_occupied');

echo "MATCH_TV_SAMO_SINGLE_CORROBORATION_V38_TEST_OK\n";
