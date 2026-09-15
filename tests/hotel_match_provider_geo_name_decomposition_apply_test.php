<?php
declare(strict_types=1);
putenv('MATCH_PROVIDER_GEO_NAME_DECOMP_APPLY_TEST_LIBRARY=1');
require_once __DIR__.'/../scripts/diagnostics/hotel_match_provider_geo_name_decomposition_apply.php';

function t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$c=[];for($i=1;$i<=MPGNDA_PLAN_COUNT;$i++)$c[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(1000+$i),'evidence_sha256'=>str_repeat(dechex($i%16),64),'route'=>'auto_accept_candidate','reason'=>$i%2?'provider_geo_name_decomposition_unique_exact':'provider_geo_name_decomposition_strong_fuzzy','target'=>2000+$i,'derived'=>['x'=>['from'=>'x town','removed_geo_label'=>'town','source_field'=>'townLName','side'=>'suffix']]];
$p=mpgnda_plan(['candidates'=>$c]);t(count($p)===MPGNDA_PLAN_COUNT,'plan_count');t(isset($p['1001'])&&$p['1001']['target']===2001,'plan_target');
t(mpgnda_same_derived(['b'=>['side'=>'suffix','from'=>'x'],'a'=>['from'=>'y']],['a'=>['from'=>'y'],'b'=>['from'=>'x','side'=>'suffix']]),'derived_order');
$bad=$c;$bad[0]['reason']='provider_geo_consensus_strong_fuzzy';$thrown=false;try{mpgnda_plan(['candidates'=>$bad]);}catch(RuntimeException $e){$thrown=true;}t($thrown,'reject_wrong_reason');
$dup=$c;$dup[17]['external_hotel_id']=$dup[0]['external_hotel_id'];$dup[17]['target']=99999;$thrown=false;try{mpgnda_plan(['candidates'=>$dup]);}catch(RuntimeException $e){$thrown=true;}t($thrown,'reject_conflict');
$src=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_provider_geo_name_decomposition_apply.php');
foreach(['FOR UPDATE','beginTransaction()','mpgnd_select(','mpga_name_guard(','evidence_sha_changed','candidate_changed','promotion_field_already_present','post_commit_readback','readback_verified','MPGNDA_REVIEW_SHA256','coordinate_conflict_gt5km_blocked','qualifiers_preserved'] as $needle)t(str_contains($src,$needle),'source_guard_'.$needle);
t(!preg_match('/(?:curl_|fsockopen|stream_socket_client|broninit|booking\(|lead\()/i',$src),'no_external_transport');
echo "hotel_match_provider_geo_name_decomposition_apply_test: OK\n";
