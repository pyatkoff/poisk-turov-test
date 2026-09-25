<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_business_live30_common4_wave5_current_v16.php';
if(SBLC4C4_OP!=='hotel-match-samo-business-live30-common4-wave5-current-1971-20260925-v16c')throw new RuntimeException('operation');
if(SBLC4C4_SOURCE_OP!=='hotel-match-samo-business-live30-common4-wave5-recovery-acquire-1971-20260925-v15')throw new RuntimeException('source_operation');
if(SBLC4C4_EXPECTED_EDGES!==1217||SBLC4C4_EXPECTED_CAPTURED!==508)throw new RuntimeException('counts');
$edges=[
 ['catalog_id'=>'10','operator_id'=>342,'supplier_namespace'=>'operator_115','external_hotel_id'=>'900','source_edge_sha256'=>'a','source_edge_index'=>0],
 ['catalog_id'=>'11','operator_id'=>342,'supplier_namespace'=>'operator_115','external_hotel_id'=>'900','source_edge_sha256'=>'b','source_edge_index'=>1],
 ['catalog_id'=>'12','operator_id'=>342,'supplier_namespace'=>'operator_115','external_hotel_id'=>'901','source_edge_sha256'=>'c','source_edge_index'=>2],
];
$g=sb4_group_input($edges,['10'=>100,'11'=>100,'12'=>100]);
if(count($g['exact'])!==2)throw new RuntimeException('dedupe');
if(count($g['target_sources']['operator_115|100']??[])!==2)throw new RuntimeException('target_collision');
$raw='{"ok":true}';$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'10','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
if(sb4_anchor_state([$a])['state']!=='canonical_anchor_ok')throw new RuntimeException('anchor');
echo "MATCH_SAMO_BUSINESS_LIVE30_WAVE5_CURRENT_V16_TEST_OK\n";
