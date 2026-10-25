<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_missing_anex_operator13_current_v60.php';

function v60t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v60t(V60_OP==='hotel-match-live-samo-missing-anex-operator13-current-1971-20260926-v60','op');
v60t(V60_SOURCE_OP==='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v59','source');
v60t(V60_EXPECTED_SINGLE===2,'count');

$raw='{"ok":true}';
$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'900','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$edge=['anex_hotel_id'=>'5844','tv_hotel_id'=>100,'operator_id'=>13,'namespace'=>'anex','source_operation'=>V60_SOURCE_OP,'source_result_sha256'=>str_repeat('b',64),'search_id_sha256'=>str_repeat('c',64),'tour_id_sha256'=>str_repeat('d',64),'operator_link_sha256'=>str_repeat('e',64),'safe_to_write_now'=>false];
$hotel=['id'=>100,'name'=>'Hotel','country_name'=>'Турция','is_active'=>1];
$src=['5844'=>[100=>true]];$dst=[100=>['5844'=>true]];

$r=v60_classify($edge,$hotel,['by_native'=>[],'by_local'=>[]],[],[],[],[100=>[$anchor]],$src,$dst,[],[]);
v60t($r['status']==='current_missing_exact_key'&&$r['writer_ready']===true,'ready');

$r=v60_classify($edge,$hotel,['by_native'=>[5844=>100],'by_local'=>[100=>[5844=>true]]],[],[],[],[100=>[$anchor]],$src,$dst,[],[]);
v60t($r['status']==='already_resolved_same'&&!$r['writer_ready'],'same');

$r=v60_classify($edge,$hotel,['by_native'=>[5844=>101],'by_local'=>[101=>[5844=>true]]],[],[],[],[100=>[$anchor]],$src,$dst,[],[]);
v60t($r['status']==='hold_source_occupied_effective','source_occupied');

$r=v60_classify($edge,$hotel,['by_native'=>[999=>100],'by_local'=>[100=>[999=>true]]],[],[],[],[100=>[$anchor]],$src,$dst,[],[]);
v60t($r['status']==='hold_target_effective_anex_other','target_occupied');

$r=v60_classify($edge,$hotel,['by_native'=>[],'by_local'=>[]],[],['5844'=>['decision_status'=>'rejected','catalog_hotel_id'=>100]],[],[100=>[$anchor]],$src,$dst,[],[]);
v60t($r['status']==='hold_manual_source_protected','manual');

$r=v60_classify($edge,$hotel,['by_native'=>[],'by_local'=>[]],['5844'=>['enabled'=>0]],[],[],[100=>[$anchor]],$src,$dst,[],[]);
v60t($r['status']==='hold_mapping_row_present_non_effective','mapping');

$r=v60_classify($edge,$hotel,['by_native'=>[],'by_local'=>[]],[],[],['5844'=>[100=>true]],[100=>[$anchor]],$src,$dst,[],[]);
v60t($r['status']==='hold_pair_excluded','pair');

$r=v60_classify($edge,$hotel,['by_native'=>[],'by_local'=>[]],[],[],[],[100=>[$anchor]],['5844'=>[100=>true,101=>true]],$dst,[],[]);
v60t($r['status']==='hold_input_source_collision','input_source');

$r=v60_classify($edge,$hotel,['by_native'=>[],'by_local'=>[]],[],[],[],[100=>[$anchor]],$src,[100=>['5844'=>true,'999'=>true]],[],[]);
v60t($r['status']==='hold_input_target_collision','input_target');

$bad=$anchor;$bad['evidence_sha256']=str_repeat('f',64);
$r=v60_classify($edge,$hotel,['by_native'=>[],'by_local'=>[]],[],[],[],[100=>[$bad]],$src,$dst,[],[]);
v60t($r['status']==='hold_canonical_anchor_evidence_invalid','anchor');

echo "MATCH_V60_TEST_OK\n";
