<?php
declare(strict_types=1);
putenv('MATCH_OPERATOR_ANCHOR_V4_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_current_anchor_review_v4.php';
function t4(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$bridge=['action'=>'price','is_operator_hotel_key'=>false,'operator_key'=>'315','native_hotel_id'=>'123','country_id'=>4,'andromeda_hotel_id'=>'500','hotel_name'=>'Grand Emin','original_name'=>'Grand Emin','request_sha256'=>str_repeat('a',64),'response_sha256'=>str_repeat('b',64)];
$e=['schema'=>'operator-original-price-bridge/1','source'=>['operator_key'=>'315','id'=>'123'],'decision'=>['state'=>'pending','local_hotel_id'=>null],'country_id'=>4,'provider_bridges'=>[$bridge]];
$eRaw=json_encode($e,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$pending=[['supplier_namespace'=>'operator_315','external_hotel_id'=>'123','decision_status'=>'pending','local_hotel_id'=>null,'evidence_sha256'=>hash('sha256',$eRaw),'catalog_sha256'=>str_repeat('d',64),'evidence_json'=>$eRaw]];
$anchorEvidence=['source'=>['name'=>'Grand Emin']];
$aRaw=json_encode($anchorEvidence,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$anchors=['500'=>['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'500','decision_status'=>'accepted','local_hotel_id'=>10,'evidence_sha256'=>hash('sha256',$aRaw),'evidence_json'=>$aRaw]];
$hotels=[10=>['id'=>10,'country_id'=>4,'name'=>'Grand Emin','latitude'=>null,'longitude'=>null,'is_active'=>1]];
$r=ocar4_evaluate($pending,$anchors,$hotels);t4(count($r['safe'])===1&&$r['safe'][0]['target']===10,'safe current accepted anchor');t4(($r['safe_by_operator']['operator_315']??0)===1,'operator count');
$r2=ocar4_evaluate($pending,[],$hotels);t4(count($r2['safe'])===0&&($r2['hold_reasons']['andromeda_anchor_not_accepted']??0)===1,'missing anchor held');
$bad=$anchors;$bad['500']['local_hotel_id']=11;$r3=ocar4_evaluate($pending,$bad,$hotels);t4(count($r3['safe'])===0&&($r3['hold_reasons']['local_target_missing']??0)===1,'missing local target held');
echo "MATCH_OPERATOR_ANCHOR_V4_TEST_OK\n";
