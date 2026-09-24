<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_business_live30_common4_writer_v5.php';

if(SBW5_AUDIT_OP!=='hotel-match-samo-business-live30-common4-current-1971-20260924-v4')throw new RuntimeException('audit_op');
if(SBW5_NS!==['operator_5'=>5,'operator_115'=>115,'operator_315'=>315,'operator_342'=>342])throw new RuntimeException('namespaces');

$anchor=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'10','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)];
$row=['supplier_namespace'=>'operator_315','operator_id'=>315,'external_hotel_id'=>'900','local_hotel_id'=>100,'source_operation'=>'hotel-match-samo-business-live30-common4-acquire-1971-20260924-v2','source_result_sha256'=>str_repeat('c',64),'source_edge_sha256'=>str_repeat('d',64),'source_edge_sha256s'=>[str_repeat('d',64)],'input_catalog_ids'=>['10'],'catalog_hotel'=>['id'=>100,'name'=>'X','country_id'=>1,'country_name'=>'Turkey','region_id'=>2,'region_name'=>'R','subregion_id'=>3,'subregion_name'=>'S','category'=>'5','is_active'=>1],'unanimous_catalog_sha256'=>str_repeat('a',64),'anchors'=>[$anchor],'status'=>'writer_ready','anchor_state'=>'canonical_anchor_ok','writer_ready'=>true,'safe_to_write_now'=>false];
$audit=['operation'=>SBW5_AUDIT_OP,'state'=>'completed_read_only_samo_business_common4_current','source_operation'=>$row['source_operation'],'source_result_sha256'=>$row['source_result_sha256'],'writer_ready_counts'=>['operator_315'=>1],'writer_ready_total'=>1,'rows'=>[$row],'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
$m=sbw5_manifest($audit,0,1);
if($m['total']!==1||count($m['rows'])!==1||$m['rows'][0]['supplier_namespace']!=='operator_315')throw new RuntimeException('manifest');

$bad=$audit;$bad['rows'][]=$row;$bad['writer_ready_total']=2;$bad['writer_ready_counts']=['operator_315'=>2];
$thrown=false;try{sbw5_manifest($bad,0,1);}catch(Throwable){$thrown=true;}
if(!$thrown)throw new RuntimeException('collision_guard');

echo "MATCH_SAMO_BUSINESS_LIVE30_COMMON4_WRITER_V5_TEST_OK\n";
