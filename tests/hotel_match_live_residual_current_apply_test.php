<?php
declare(strict_types=1);
putenv('MATCH_APPLY_TEST_LIBRARY=1');
require __DIR__ . '/../scripts/diagnostics/hotel_match_live_residual_current_apply.php';
function at(bool $ok,string $msg):void{if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}}
$review=['auto_accept_candidates'=>[
 ['kind'=>'operator_native','supplier_namespace'=>'operator_342','external_hotel_id'=>'10','reason'=>'operator_native_accepted_andromeda_bridge','target'=>100],
 ['kind'=>'operator_native','supplier_namespace'=>'operator_5','external_hotel_id'=>'11','reason'=>'operator_native_current_authority','target'=>101],
 ['kind'=>'andromeda_catalog','external_hotel_id'=>'20','reason'=>'unique_exact_name_or_alias','target'=>200],
 ['kind'=>'andromeda_catalog','external_hotel_id'=>'21','reason'=>'strong_fuzzy_winner','target'=>201],
 ['kind'=>'andromeda_catalog','external_hotel_id'=>'22','reason'=>'needs_extra_evidence','target'=>202],
]];
$p=mca_eligible_plan($review);at(count($p)===3,'eligible count');at(isset($p['operator_native|operator_342|10']),'operator eligible');at(!isset($p['operator_native|operator_5|11']),'operator5 excluded');at(isset($p['andromeda_catalog||20'])&&isset($p['andromeda_catalog||21']),'catalog exact/fuzzy eligible');
$prior=['source'=>['name'=>'Demo']];$x=mca_promotion_json($prior,['operation_id'=>'x','target_local_hotel_id'=>123]);at(($x['prior_evidence']['source']['name']??'')==='Demo'&&($x['promotion']['target_local_hotel_id']??0)===123,'promotion preserves prior');
$row=['decision_status'=>'accepted','local_hotel_id'=>123,'evidence_json'=>mcr_json(['promotion'=>['operation_id'=>'op','target_local_hotel_id'=>123]])];at(mca_result_readback_ok($row,'op',123),'readback valid');at(!mca_result_readback_ok($row,'op',124),'readback target guard');
$hotels=[10=>['id'=>10,'country_id'=>4,'name'=>'Grand Emin Hotel','latitude'=>null,'longitude'=>null]];$forms=[10=>['Grand Emin Hotel']];$exact=[4=>['grand emin'=>[10]]];$token=[4=>['grand'=>[10=>true],'emin'=>[10=>true]]];$cur=['decision_status'=>'pending','local_hotel_id'=>null,'evidence_json'=>mcr_json(['source'=>['name'=>'Grand Emin Resort','stateKey'=>5]])];$plan=['target'=>10,'reason'=>'unique_exact_name_or_alias'];$v=mca_validate_catalog($plan,$cur,['5'=>4],$hotels,$forms,$exact,$token);at(($v['ok']??false)===true,'catalog exact current revalidation');
$cur['decision_status']='conflict';$v=mca_validate_catalog($plan,$cur,['5'=>4],$hotels,$forms,$exact,$token);at(($v['ok']??true)===false&&($v['reason']??'')==='current_state_protected','protected current state');
echo "hotel_match_live_residual_current_apply_test: OK\n";
