<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_current_supplier_bridge.php';
$checks=0;$assert=static function(bool $ok,string $name)use(&$checks){$checks++;if(!$ok)throw new RuntimeException($name);};
$keys=hmsb_keys('The Sunrise Beach Resort & Spa Hotel');
$assert($keys===['beach sunrise the'],'generic_hotel_resort_spa_removed_but_beach_kept');
$assert(hmsb_keys('Garden Hotel')!==hmsb_keys('Beach Hotel'),'meaningful_qualifiers_preserved');
$ex=hmsb_keys('NH COLLECTION MALDIVES HAVODDA RESORT (EX. AMARI HAVODDA MALDIVES)');
$assert(in_array('collection havodda maldives nh',$ex,true)&&in_array('amari havodda maldives',$ex,true),'former_name_alias_extracted');
$index=[];hmsb_index_add($index,4,100,['Foo Resort Hotel'],'a1');hmsb_index_add($index,4,200,['Bar Spa'],'a2');
$assert(hmsb_targets($index,4,['Hotel Foo'])===[100],'supplier_name_bridge_ignores_generic');
$assert(hmsb_targets($index,4,['Beach Foo'])===[],'qualifier_not_dropped');
$assert(hmsb_max_tokens(['Hotel Foo Resort'])===1,'significant_token_count');
$assert(hmsb_numeric_category(['starKey'=>5])===null,'starkey_not_semantic');
$assert(hmsb_numeric_category(['star'=>'4★'])===4,'explicit_star_parsed');
$geo=['coordinate_conflict'=>false,'direct_geo'=>false];
$assert(hmsb_candidate_status(2,$geo)==='strict_supplier_bridge','two_token_exact_bridge');
$assert(hmsb_candidate_status(1,$geo)==='needs_geo_or_independent_evidence','one_token_needs_geo');
$geo=['coordinate_conflict'=>false,'direct_geo'=>true];
$assert(hmsb_candidate_status(1,$geo)==='strict_supplier_bridge','one_token_with_geo');
$geo=['coordinate_conflict'=>true,'direct_geo'=>true];
$assert(hmsb_candidate_status(4,$geo)==='hard_conflict','coordinate_conflict_blocks');
$rows=[
 ['provider'=>'andromeda','external_id'=>'x1','target_local_id'=>50,'status'=>'strict_supplier_bridge'],
 ['provider'=>'andromeda','external_id'=>'x2','target_local_id'=>50,'status'=>'needs_geo_or_independent_evidence'],
 ['provider'=>'anex','external_id'=>'9','target_local_id'=>50,'status'=>'strict_supplier_bridge'],
 ['provider'=>'andromeda','external_id'=>'x3','target_local_id'=>51,'status'=>'hard_conflict'],
];
$out=hmsb_collision_hold($rows);
$assert($out[0]['status']==='duplicate_provider_hold'&&$out[1]['status']==='duplicate_provider_hold','same_provider_collision_held');
$assert($out[2]['status']==='strict_supplier_bridge','different_provider_not_collision');
$assert($out[3]['status']==='hard_conflict','hard_conflict_preserved');
echo "hotel_match_current_supplier_bridge_test: {$checks} checks PASS\n";
