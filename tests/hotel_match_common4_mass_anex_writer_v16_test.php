<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_mass_anex_writer_v16.php';
function maw15(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$raw='{"x":1}';
$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'7','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw)];
$row=['kind'=>'anex','supplier_namespace'=>'anex','external_hotel_id'=>'5844','tv_hotel_id'=>100,'operator_id'=>13,
 'source_operation'=>'op','source_result_sha256'=>str_repeat('b',64),'batch'=>1,'search_id_sha256'=>str_repeat('c',64),
 'tour_id_sha256'=>str_repeat('d',64),'operator_link_sha256'=>str_repeat('e',64),'operator_link_host'=>'agent.anextour.ru',
 'query_keys'=>['hotelcode'],'status'=>'current_missing_exact_key','anchor_state'=>'canonical_anchor_ok',
 'unanimous_catalog_sha256'=>str_repeat('a',64),'anchors'=>[$anchor],
 'catalog_hotel'=>['id'=>100,'name'=>'H','country_id'=>4,'country_name'=>'Turkey','region_id'=>1,'region_name'=>'R','subregion_id'=>null,'subregion_name'=>'','category'=>'5','is_active'=>1],
 'writer_ready'=>true,'safe_to_write_now'=>false];
$audit=['operation'=>HMCMAW_AUDIT_OP,'state'=>'completed_read_only_mass_current','writer_ready_counts'=>['identity'=>0,'anex'=>16],
 'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'rows'=>array_fill(0,16,$row)];
for($i=0;$i<16;$i++){ $audit['rows'][$i]['external_hotel_id']=(string)(5844+$i);$audit['rows'][$i]['tv_hotel_id']=100+$i;$audit['rows'][$i]['catalog_hotel']['id']=100+$i;$audit['rows'][$i]['anchors'][0]['local_hotel_id']=100+$i; }
$m=hmcmaw_manifest($audit);maw15(count($m)===16,'manifest_count');maw15($m[0]['anex_hotel_id']==='5844','first');
echo "MATCH_COMMON4_MASS_ANEX_WRITER_V16_TEST_OK\n";
