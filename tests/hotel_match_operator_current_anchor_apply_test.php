<?php
declare(strict_types=1);
putenv('MATCH_OPERATOR_ANCHOR_APPLY_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_current_anchor_apply.php';
function ocaa_t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$r=['safe'=>[]];for($i=0;$i<OCAA_PLAN_COUNT;$i++)$r['safe'][]=['supplier_namespace'=>$i<5?'operator_315':'operator_342','external_hotel_id'=>(string)(100+$i),'target'=>$i+1,'evidence_sha256'=>str_repeat('a',64),'catalog_sha256'=>str_repeat('b',64),'andromeda_id'=>(string)(200+$i),'anchor_evidence_sha256'=>str_repeat('c',64)];$p=ocaa_plan($r);ocaa_t(count($p)===10,'plan count');
$e=['decision_status'=>'accepted','local_hotel_id'=>7,'evidence_sha256'=>'x','evidence_json'=>opb_json(['match_promotion'=>['operation_id'=>OCAA_OP,'review_result_sha256'=>OCAA_REVIEW_SHA256,'target_local_hotel_id'=>7]])];$e['evidence_sha256']=hash('sha256',$e['evidence_json']);ocaa_t(ocaa_readback($e,7),'readback');ocaa_t(!ocaa_readback($e,8),'wrong target blocked');
echo "MATCH_OPERATOR_CURRENT_ANCHOR_APPLY_TEST_OK\n";
