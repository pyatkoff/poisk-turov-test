<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live30_common4_current_v2.php';

function c4ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$raw='{"x":1}';
$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'900','local_hotel_id'=>100,'decision_status'=>'accepted',
    'catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$edge=['supplier_namespace'=>'bgoperator','external_hotel_id'=>'123','tv_hotel_id'=>100,'operator_id'=>18,'source_operation'=>'x',
    'source_result_sha256'=>str_repeat('b',64),'batch'=>1,'search_id_sha256'=>str_repeat('c',64),'tour_id_sha256'=>str_repeat('d',64),
    'operator_link_sha256'=>str_repeat('e',64),'safe_to_write_now'=>false];
$cat=[100=>['id'=>100,'name'=>'HOTEL','country_name'=>'Турция','is_active'=>1]];
$s=['bgoperator|123'=>[100=>true]];$t=['bgoperator|100'=>['123'=>true]];
$r=hmc4c_classify($edge,$cat,[],[],[100=>[$anchor]],[],$s,$t);
c4ok($r['status']==='current_missing_edge'&&$r['writer_ready']===true&&$r['anchor_state']==='canonical_anchor_ok','ready');

$r=hmc4c_classify($edge,$cat,['bgoperator|123'=>[['decision_status'=>'accepted','local_hotel_id'=>100]]],[],[100=>[$anchor]],[],$s,$t);
c4ok($r['status']==='resolved_same'&&$r['writer_ready']===false,'resolved');

$r=hmc4c_classify($edge,$cat,[],[],[100=>[$anchor]],[100=>[['decision_status'=>'rejected']]],$s,$t);
c4ok($r['status']==='manual_target_protected'&&$r['writer_ready']===false,'manual');

$bad=$anchor;$bad['evidence_sha256']=str_repeat('f',64);
$r=hmc4c_classify($edge,$cat,[],[],[100=>[$bad]],[],$s,$t);
c4ok($r['anchor_state']==='canonical_anchor_evidence_invalid'&&$r['writer_ready']===false,'anchor_hash');

$anchor2=$anchor;$anchor2['external_hotel_id']='901';$anchor2['catalog_sha256']=str_repeat('b',64);
$r=hmc4c_classify($edge,$cat,[],[],[100=>[$anchor,$anchor2]],[],$s,$t);
c4ok($r['anchor_state']==='canonical_anchor_catalog_conflict'&&$r['writer_ready']===false,'anchor_conflict');

$r=hmc4c_classify($edge,$cat,[],[],[100=>[$anchor]],[],['bgoperator|123'=>[100=>true,101=>true]],$t);
c4ok($r['status']==='saved_source_collision'&&$r['writer_ready']===false,'source_collision');

echo "MATCH_LIVE30_COMMON4_CURRENT_V2_TEST_OK\n";
