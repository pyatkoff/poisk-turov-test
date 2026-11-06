<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_missing_anex_operator13_writer_v61.php';

function v61t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v61t(V61_OP==='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260926-v61','op');
v61t(V61_EXPECTED===2,'count');
v61t(V61_POLICY==='owner_exact_operator_key_20260912_v2','policy');
v61t(V61_CLASS==='exact_operator_key','class');
v61t(V61_READY_DIGEST==='5d01d83631fe9d10b518ac0be1955fd665426fc0d1858674b9d0720c1f71a7ca','digest');

$a=[
 'operation'=>V61_AUDIT_OP,'state'=>'completed_read_only_v59_current_audit',
 'input_count'=>2,'writer_ready_count'=>2,'writer_ready_target_digest'=>V61_READY_DIGEST,
 'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
 'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'source_sha'=>str_repeat('1',40),
 'source_result_sha256'=>str_repeat('2',64),'rows'=>[]
];
for($i=0;$i<2;$i++){
 $tv=1000+$i;$id=(string)(6000+$i);
 $anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(7000+$i),'local_hotel_id'=>$tv,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)];
 $a['rows'][]=[
  'writer_ready'=>true,'status'=>'current_missing_exact_key','anchor_state'=>'canonical_anchor_ok',
  'anex_hotel_id'=>$id,'tv_hotel_id'=>$tv,'operator_id'=>13,'namespace'=>'anex',
  'source_operation'=>'hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v59',
  'source_result_sha256'=>str_repeat('c',64),'batch'=>$i+1,'search_id_sha256'=>str_repeat('d',64),
  'tour_id_sha256'=>str_repeat('e',64),'operator_link_sha256'=>str_repeat('f',64),
  'catalog_hotel'=>['id'=>$tv,'name'=>'H'.$i,'country_id'=>4,'country_name'=>'Turkey','region_id'=>1,'region_name'=>'R','subregion_id'=>null,'subregion_name'=>'','category'=>'5','is_active'=>1],
  'anchors'=>[$anchor],'unanimous_catalog_sha256'=>str_repeat('a',64)
 ];
}
$m=v61_manifest($a);
v61t(count($m)===2,'manifest_count');
v61t($m[0]['anex_hotel_id']==='6000','first_native');
v61t($m[0]['catalog_hotel_id']===1000,'first_target');
$ev=v61_evidence($m[0],$m[0]['anchors'],str_repeat('1',40),str_repeat('9',64));
v61t($ev['raw_operator_url_exported']===false,'no_url');
v61t($ev['provider_http_calls']===0,'provider_zero');

$dup=$a;$dup['rows'][1]['anex_hotel_id']=$dup['rows'][0]['anex_hotel_id'];
$ok=false;try{v61_manifest($dup);}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'manifest_collision');}
v61t($ok,'collision_guard');

echo "MATCH_V61_TEST_OK\n";
