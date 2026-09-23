<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_continuation_current_v11.php';
function v11ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$raw='{"x":1}';$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];$anchor=hmc11_anchor_state([$a]);
$h=['id'=>100,'is_active'=>1,'country_name'=>'Турция'];
$e=['kind'=>'identity','supplier_namespace'=>'bgoperator','external_hotel_id'=>'88','tv_hotel_id'=>100,'safe_to_write_now'=>false];
$r=hmc11_classify_identity($e,$h,[],[],[],$anchor);v11ok($r['status']==='current_missing_edge'&&$r['writer_ready'],'identity');
$r=hmc11_classify_identity($e,$h,['bgoperator|88'=>[['decision_status'=>'accepted','local_hotel_id'=>100]]],[],[],$anchor);v11ok($r['status']==='resolved_same'&&!$r['writer_ready'],'resolved');
$e=['kind'=>'anex','supplier_namespace'=>'anex','external_hotel_id'=>'5844','tv_hotel_id'=>100,'safe_to_write_now'=>false];
$r=hmc11_classify_anex($e,$h,null,null,false,$anchor,null);v11ok($r['status']==='current_missing_exact_key'&&$r['writer_ready'],'anex');
$r=hmc11_classify_anex($e,$h,null,['decision_status'=>'rejected','catalog_hotel_id'=>100],false,$anchor,null);v11ok($r['status']==='manual_source_protected'&&!$r['writer_ready'],'manual');
$bad=$a;$bad['evidence_sha256']=str_repeat('f',64);v11ok(hmc11_anchor_state([$bad])['state']==='canonical_anchor_evidence_invalid','anchorhash');
echo "MATCH_COMMON4_CONTINUATION_CURRENT_V11_TEST_OK\n";
