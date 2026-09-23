<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_samo_live30_common4_current_v3.php';
$mk=fn($i,$s)=>['catalog_id'=>(string)$i,'operator_id'=>115,'namespace'=>'operator_115','state'=>$s,'positive_native_candidates'=>[]];
$v1=['operation'=>SLC4C3_V1_OP,'state'=>'completed_read_only_partial','queried_edge_count'=>1084,'batch_error_count'=>21,'edges'=>[]];
for($i=1;$i<=1084;$i++)$v1['edges'][]=$mk($i,'not_returned_in_context');
$v2=['operation'=>SLC4C3_V2_OP,'state'=>'completed_read_only','queried_edge_count'=>630,'v1_failed_batch_count'=>21,'batch_error_count'=>0,'edges'=>[]];
for($i=1;$i<=630;$i++)$v2['edges'][]=$mk($i,'catalog_only');
$c=sc3_combine($v1,$v2);
if($c['v2_override_count']!==630||$c['final_state_counts']!==['catalog_only'=>630,'not_returned_in_context'=>454])throw new RuntimeException('combine');
echo "MATCH_SAMO_LIVE30_COMMON4_CURRENT_V3_TEST_OK\n";
