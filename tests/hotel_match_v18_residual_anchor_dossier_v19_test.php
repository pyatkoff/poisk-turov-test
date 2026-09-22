<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_v18_residual_anchor_dossier_v19.php';
function v19ok(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$target=['id'=>1,'name'=>'A','country_id'=>4,'country_name'=>'Турция','region_id'=>2,'region_name'=>'Side','subregion_id'=>null,'subregion_name'=>null,'category'=>'5','is_active'=>1];
$m=['tv_hotel_id'=>1,'expected_anchor_count'=>2,'target'=>$target];
$a=['local_hotel_id'=>1,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_hash_valid'=>true];
$x=hm19_classify($m,$target,[$a,$a]);v19ok($x['verdict']==='consistent_multi_anchor','consistent');
$b=$a;$b['catalog_sha256']=str_repeat('b',64);$x=hm19_classify($m,$target,[$a,$b]);v19ok($x['verdict']==='inconsistent_multi_anchor'&&in_array('catalog_hash_not_unanimous',$x['reasons'],true),'hash_conflict');
$m0=$m;$m0['expected_anchor_count']=0;$x=hm19_classify($m0,$target,[]);v19ok($x['verdict']==='zero_anchor','zero');
$changed=$target;$changed['name']='B';$x=hm19_classify($m,$changed,[$a,$a]);v19ok($x['verdict']==='drift','target_drift');
$proj=hm19_source_projection(['nested'=>['source'=>['id'=>7,'name'=>'Hotel','state'=>'TR','secret'=>'no','latitude'=>1.2],'token'=>'no']]);
v19ok(count($proj)===1&&$proj[0]['id']==='7'&&$proj[0]['name']==='Hotel'&&!isset($proj[0]['secret']),'projection_safe');
echo "MATCH_V18_RESIDUAL_ANCHOR_DOSSIER_V19_TEST_OK\n";
