<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_mass_current_v14.php';
function v14ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$raw='{"x":1}';
$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$anchor=hmc14_anchor_state([$a]);v14ok($anchor['state']==='canonical_anchor_ok','anchor');
$e=['kind'=>'identity','supplier_namespace'=>'bgoperator','external_hotel_id'=>'88','tv_hotel_id'=>100,'safe_to_write_now'=>false];
$src=['bgoperator|88'=>[100=>true,101=>true]];$dst=['bgoperator|100'=>['88'=>true]];
v14ok(hmc14_input_collision($e,$src,$dst)==='input_source_collision','source_collision');
$m=['children'=>[
 ['operation'=>'hotel-match-live30-common4-continuation-acquire-1971-20260923-c35-n100-v1','result_sha256'=>str_repeat('a',64)],
 ['operation'=>'hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n899-v1','result_sha256'=>str_repeat('b',64)],
 ['operation'=>'hotel-match-live30-common4-continuation-resume-1971-20260924-r2-n138-v1','result_sha256'=>str_repeat('c',64)]
]];
v14ok(count(hmc14_specs($m))===3,'specs');
echo "MATCH_COMMON4_MASS_CURRENT_V14_TEST_OK\n";
