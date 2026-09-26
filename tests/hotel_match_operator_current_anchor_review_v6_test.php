<?php
declare(strict_types=1);
putenv('MATCH_OPERATOR_ANCHOR_V6_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_current_anchor_review_v6.php';
function t6(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
t6(OCAR6_OP==='hotel-match-operator-current-anchor-review-1971-20260915-v6','operation id');
$bridge=['action'=>'price','is_operator_hotel_key'=>false,'operator_key'=>'342','native_hotel_id'=>'321','country_id'=>4,'andromeda_hotel_id'=>'700','hotel_name'=>'Innvista','original_name'=>'Innvista','request_sha256'=>str_repeat('a',64),'response_sha256'=>str_repeat('b',64)];
$e=['schema'=>'operator-original-price-bridge/1','source'=>['operator_key'=>'342','id'=>'321'],'decision'=>['state'=>'pending','local_hotel_id'=>null],'country_id'=>4,'provider_bridges'=>[$bridge]];$raw=json_encode($e,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$a=['source'=>['name'=>'Innvista']];$araw=json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$pending=[['supplier_namespace'=>'operator_342','external_hotel_id'=>'321','decision_status'=>'pending','local_hotel_id'=>null,'evidence_sha256'=>hash('sha256',$raw),'catalog_sha256'=>str_repeat('c',64),'evidence_json'=>$raw]];
$anchors=['700'=>['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'700','decision_status'=>'accepted','local_hotel_id'=>55,'evidence_sha256'=>hash('sha256',$araw),'evidence_json'=>$araw]];
$hotels=[55=>['id'=>55,'country_id'=>4,'name'=>'Innvista','latitude'=>null,'longitude'=>null,'is_active'=>1]];
$r=ocar4_evaluate($pending,$anchors,$hotels);t6(count($r['safe'])===1&&($r['safe_by_operator']['operator_342']??0)===1,'helper policy unchanged');
$src=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_operator_current_anchor_review_v6.php');
foreach(["supplier_namespace IN ('operator_315','operator_342')",'START TRANSACTION READ ONLY',"'operator_5_writes'=>0","'mapping_writes'=>0",'ocar4_evaluate('] as $needle)t6(str_contains($src,$needle),'source guard '.$needle);
t6(!str_contains($src,"supplier_namespace IN ('operator_5'"),'operator_5 excluded');
echo "MATCH_OPERATOR_ANCHOR_V6_TEST_OK\n";
