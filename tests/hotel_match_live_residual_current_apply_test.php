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
 ['kind'=>'andromeda_catalog','external_hotel_id'=>'23','reason'=>'unique_exact_name_plus_own_geography','target'=>203],
]];
$p=mca_plan($review);at(count($p)===4,'eligible count');at(isset($p['operator_native|operator_342|10']),'operator eligible');at(!isset($p['operator_native|operator_5|11']),'operator5 excluded');at(isset($p['andromeda_catalog||20'])&&isset($p['andromeda_catalog||21'])&&isset($p['andromeda_catalog||23']),'catalog exact/fuzzy/geo eligible');
$x=mca_evidence(['source'=>['name'=>'Demo']],['operation_id'=>'x','target_local_hotel_id'=>123]);at(($x['prior_evidence']['source']['name']??'')==='Demo'&&($x['promotion']['target_local_hotel_id']??0)===123,'promotion preserves prior');
$row=['decision_status'=>'accepted','local_hotel_id'=>123,'evidence_json'=>mcr_json(['promotion'=>['operation_id'=>MCA_OP,'target_local_hotel_id'=>123]])];at(mca_readback_ok($row,123),'readback valid');at(!mca_readback_ok($row,124),'readback target guard');
$hotels=[10=>['id'=>10,'country_id'=>4,'name'=>'Grand Emin Hotel','region_name'=>null,'subregion_name'=>null,'latitude'=>null,'longitude'=>null]];$forms=[10=>['Grand Emin Hotel']];$exact=[4=>['grand emin'=>[10]]];$token=[4=>['grand'=>[10=>true],'emin'=>[10=>true]]];$geo=[];
$cur=['decision_status'=>'pending','local_hotel_id'=>null,'evidence_json'=>mcr_json(['source'=>['name'=>'Grand Emin Resort','stateKey'=>5]])];$plan=['target'=>10,'reason'=>'unique_exact_name_or_alias'];$v=mca_catalog_check($plan,$cur,['5'=>4],$hotels,$forms,$exact,$token,$geo);at(($v['ok']??false)===true,'catalog exact current revalidation');$cur['decision_status']='conflict';$v=mca_catalog_check($plan,$cur,['5'=>4],$hotels,$forms,$exact,$token,$geo);at(($v['ok']??true)===false&&($v['reason']??'')==='current_state_protected','protected current state');
$hotels=[20=>['id'=>20,'country_id'=>4,'name'=>'SUREYYA HOTEL LALELI','region_name'=>'Стамбул','subregion_name'=>'Лалели','latitude'=>null,'longitude'=>null]];$forms=[20=>['SUREYYA HOTEL LALELI']];$geo=mcr_geo_exact_index($hotels,$forms);$cur=['decision_status'=>'pending','local_hotel_id'=>null,'evidence_json'=>mcr_json(['source'=>['name'=>'Sureyya Hotel','stateKey'=>5]])];$plan=['target'=>20,'reason'=>'unique_exact_name_plus_own_geography'];$v=mca_catalog_check($plan,$cur,['5'=>4],$hotels,$forms,[],[4=>['sureyya'=>[20=>true]]],$geo);at(($v['ok']??false)===true,'catalog geography exact current revalidation');
echo "hotel_match_live_residual_current_apply_test: OK\n";
