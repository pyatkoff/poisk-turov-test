<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_mass_current_v13.php';
function v13ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$raw='{"x":1}';
$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$anchor=hmc13_anchor_state([$a]);v13ok($anchor['state']==='canonical_anchor_ok','anchor');
$h=['id'=>100,'is_active'=>1,'country_name'=>'Турция'];
$e=['kind'=>'identity','supplier_namespace'=>'bgoperator','external_hotel_id'=>'88','tv_hotel_id'=>100,'safe_to_write_now'=>false];
$r=hmc13_classify_identity($e,$h,[],[],[],$anchor);v13ok($r['writer_ready']===true,'identity_ready');
$src=['bgoperator|88'=>[100=>true,101=>true]];$dst=['bgoperator|100'=>['88'=>true]];
v13ok(hmc13_input_collision($e,$src,$dst)==='input_source_collision','source_collision');
$src=['bgoperator|88'=>[100=>true]];$dst=['bgoperator|100'=>['88'=>true,'99'=>true]];
v13ok(hmc13_input_collision($e,$src,$dst)==='input_target_namespace_collision','target_collision');
$m=['children'=>[
 ['operation'=>'hotel-match-live30-common4-continuation-acquire-1971-20260923-c35-n100-v1','result_sha256'=>str_repeat('a',64)],
 ['operation'=>'hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n800-v1','result_sha256'=>str_repeat('b',64)]
]];
v13ok(count(hmc13_specs($m))===2,'specs');
echo "MATCH_COMMON4_MASS_CURRENT_V13_TEST_OK\n";
