<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_missing_anex_operator13_writer_v47.php';

function v47t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
v47t(V47_OP==='hotel-match-live-samo-missing-anex-operator13-writer-1971-20260925-v47','op');
v47t(V47_EXPECTED===17,'count');
v47t(V47_POLICY==='owner_exact_operator_key_20260912_v2','policy');
v47t(V47_CLASS==='exact_operator_key','class');
v47t(V47_READY_DIGEST==='3093f8ad2c65b09eb8a8bd5ec6d698dae1c764affe036cfd19bcc43097d1a656','digest');

$a=[
 'operation'=>V47_AUDIT_OP,'state'=>'completed_read_only_v45_current_audit',
 'input_count'=>16,'writer_ready_count'=>16,'writer_ready_target_digest'=>V47_READY_DIGEST,
 'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
 'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'source_sha'=>str_repeat('1',40),
 'source_result_sha256'=>str_repeat('2',64),'rows'=>[]
];
for($i=0;$i<17;$i++){
 $tv=1000+$i;$id=(string)(6000+$i);
 $anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(7000+$i),'local_hotel_id'=>$tv,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)];
 $a['rows'][]=[
  'writer_ready'=>true,'status'=>'current_missing_exact_key','anchor_state'=>'canonical_anchor_ok',
  'anex_hotel_id'=>$id,'tv_hotel_id'=>$tv,'operator_id'=>13,'namespace'=>'anex',
  'source_operation'=>'hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v41b',
  'source_result_sha256'=>str_repeat('c',64),'batch'=>$i+1,'search_id_sha256'=>str_repeat('d',64),
  'tour_id_sha256'=>str_repeat('e',64),'operator_link_sha256'=>str_repeat('f',64),
  'catalog_hotel'=>['id'=>$tv,'name'=>'H'.$i,'country_id'=>4,'country_name'=>'Turkey','region_id'=>1,'region_name'=>'R','subregion_id'=>null,'subregion_name'=>'','category'=>'5','is_active'=>1],
  'anchors'=>[$anchor],'unanimous_catalog_sha256'=>str_repeat('a',64)
 ];
}
$m=v47_manifest($a);
v47t(count($m)===17,'manifest_count');
v47t($m[0]['anex_hotel_id']==='6000','first_native');
v47t($m[0]['catalog_hotel_id']===1000,'first_target');
$ev=v47_evidence($m[0],$m[0]['anchors'],str_repeat('1',40),str_repeat('9',64));
v47t($ev['raw_operator_url_exported']===false,'no_url');
v47t($ev['provider_http_calls']===0,'provider_zero');

$dup=$a;$dup['rows'][1]['anex_hotel_id']=$dup['rows'][0]['anex_hotel_id'];
$ok=false;try{v47_manifest($dup);}catch(RuntimeException $e){$ok=str_contains($e->getMessage(),'manifest_collision');}
v47t($ok,'collision_guard');

echo "MATCH_V47_TEST_OK\n";
