<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_business_live30_common4_current_v4.php';

if(SBLC4C4_OP!=='hotel-match-samo-business-live30-common4-current-1971-20260924-v4')throw new RuntimeException('operation');
if(SBLC4C4_SOURCE_OP!=='hotel-match-samo-business-live30-common4-acquire-1971-20260924-v2')throw new RuntimeException('source_operation');
if(SBLC4C4_EXPECTED_EDGES!==6480)throw new RuntimeException('edge_count');
if(SBLC4C4_NS!==[5=>'operator_5',115=>'operator_115',315=>'operator_315',342=>'operator_342'])throw new RuntimeException('namespaces');

$edges=[
 ['catalog_id'=>'10','operator_id'=>115,'supplier_namespace'=>'operator_115','external_hotel_id'=>'900','source_edge_sha256'=>'a','source_edge_index'=>0],
 ['catalog_id'=>'11','operator_id'=>115,'supplier_namespace'=>'operator_115','external_hotel_id'=>'900','source_edge_sha256'=>'b','source_edge_index'=>1],
 ['catalog_id'=>'12','operator_id'=>115,'supplier_namespace'=>'operator_115','external_hotel_id'=>'901','source_edge_sha256'=>'c','source_edge_index'=>2],
 ['catalog_id'=>'13','operator_id'=>315,'supplier_namespace'=>'operator_315','external_hotel_id'=>'700','source_edge_sha256'=>'d','source_edge_index'=>3],
];
$g=sb4_group_input($edges,['10'=>100,'11'=>100,'12'=>100,'13'=>200]);
if(count($g['exact'])!==3)throw new RuntimeException('exact_identity_dedupe');
if(count($g['exact']['operator_115|900|100']??[])!==2)throw new RuntimeException('same_identity_sources');
if(count($g['source_targets']['operator_115|900']??[])!==1)throw new RuntimeException('source_target_unique');
if(count($g['target_sources']['operator_115|100']??[])!==2)throw new RuntimeException('target_namespace_collision');

$raw='{"ok":true}';
$a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'10','local_hotel_id'=>100,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$x=sb4_anchor_state([$a]);
if(($x['state']??'')!=='canonical_anchor_ok'||($x['catalog_sha256']??'')!==str_repeat('a',64))throw new RuntimeException('anchor_ok');
$a['evidence_sha256']=str_repeat('b',64);
if(sb4_anchor_state([$a])['state']!=='canonical_anchor_evidence_invalid')throw new RuntimeException('anchor_hash_guard');

echo "MATCH_SAMO_BUSINESS_LIVE30_COMMON4_CURRENT_V4_TEST_OK\n";
