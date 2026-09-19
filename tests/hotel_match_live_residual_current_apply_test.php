<?php
declare(strict_types=1);
putenv('MATCH_APPLY_TEST_LIBRARY=1');
require __DIR__ . '/../scripts/diagnostics/hotel_match_live_residual_current_apply.php';
function at(bool $ok,string $msg):void{if(!$ok){fwrite(STDERR,"FAIL $msg\n");exit(1);}}
$review=['auto_accept_candidates'=>[
 ['kind'=>'operator_native','supplier_namespace'=>'operator_315','external_hotel_id'=>'10','reason'=>'operator_native_accepted_andromeda_bridge','target'=>100],
 ['kind'=>'operator_native','supplier_namespace'=>'operator_342','external_hotel_id'=>'11','reason'=>'operator_native_accepted_andromeda_bridge','target'=>101],
 ['kind'=>'operator_native','supplier_namespace'=>'operator_5','external_hotel_id'=>'12','reason'=>'operator_native_accepted_andromeda_bridge','target'=>102],
 ['kind'=>'operator_native','supplier_namespace'=>'operator_5','external_hotel_id'=>'13','reason'=>'operator_native_current_authority','target'=>103],
 ['kind'=>'andromeda_catalog','supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'20','reason'=>'unique_exact_name_plus_own_geography','target'=>200],
]];
$p=mca_plan($review);at(count($p)===2,'only non-ANEX operator bridge eligible');at(isset($p['operator_315|10'])&&isset($p['operator_342|11']),'315/342 eligible');at(!isset($p['operator_5|12'])&&!isset($p['operator_5|13']),'operator5 excluded');at(!isset($p['andromeda_catalog|20']),'catalog excluded in receiving apply');
$x=mca_evidence(['source'=>['name'=>'Demo']],['operation_id'=>'x','target_local_hotel_id'=>123]);at(($x['prior_evidence']['source']['name']??'')==='Demo'&&($x['promotion']['target_local_hotel_id']??0)===123,'promotion preserves prior');
$row=['decision_status'=>'accepted','local_hotel_id'=>123,'evidence_json'=>mcr_json(['promotion'=>['operation_id'=>MCA_OP,'target_local_hotel_id'=>123]])];at(mca_readback_ok($row,123),'readback valid');at(!mca_readback_ok($row,124),'readback target guard');
$g=mcr_direct_target_guard(['Sureyya Hotel'],[],['name'=>'SUREYYA HOTEL LALELI','latitude'=>null,'longitude'=>null]);at(($g['ok']??false)===true,'direct name guard accepts shared substantive identity');
$g=mcr_direct_target_guard(['Greenport Beach'],[],['name'=>'Greenport Garden','latitude'=>null,'longitude'=>null]);at(($g['ok']??true)===false&&($g['reason']??'')==='direct_target_qualifier_conflict','meaningful qualifier protected');
echo "hotel_match_live_residual_current_apply_test: OK\n";
