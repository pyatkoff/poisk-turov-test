<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_live_samo_anex_writer_v1.php';
$rows=[];for($i=1;$i<=15;$i++)$rows[]=['status'=>'candidate_strict_mutual_unique','anex_hotel_id'=>$i,'tv_hotel_id'=>1000+$i,'source_fingerprint'=>'sourcefp-'.$i,'name_via_tv'=>true,'name_via_samo'=>($i%2===0),'geo_class'=>'place_exact','distance_m'=>null,'place_match'=>true];
$m=['state'=>'completed_read_only_identity_join','source_sha'=>HMSAW_SOURCE_RESULT_SHA,'frontier_samo_present_anex_missing'=>942,'strict_mutual_unique_count'=>15,'rows'=>$rows];
$p=hmsaw_manifest($m);if(count($p)!==15||count(array_unique(array_column($p,'tv_hotel_id')))!==15)throw new RuntimeException('manifest');
$m['rows'][0]['tv_hotel_id']=$m['rows'][1]['tv_hotel_id'];try{hmsaw_manifest($m);throw new RuntimeException('duplicate_not_rejected');}catch(RuntimeException $e){if($e->getMessage()==='duplicate_not_rejected')throw $e;}
echo "MATCH_LIVE_SAMO_ANEX_WRITER_V1_TEST_OK\n";
