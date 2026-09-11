<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_literal_geo_union_accept.php';

function hmlgua_t(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}

$row=['provider'=>'anex','external_id'=>'8121','country_id'=>4,'target_local_hotel_id'=>6319,'routes'=>['exact_ordered_literal','single_token_coord_le_1km'],'live'=>true,'observation_count'=>17,'evidence'=>['distance_m'=>0.02,'place_match'=>true]];
$e=hmlgua_anex_evidence(HMLGUA_OPERATION,$row);
hmlgua_t($e['operation_id']===HMLGUA_OPERATION,'anex operation');
hmlgua_t($e['provider']==='anex'&&$e['external_id']===8121&&$e['target_local_hotel_id']===6319,'anex ids');
hmlgua_t($e['server_current_same_transaction']===true&&count($e['routes'])===2,'anex provenance');

$prior=['source'=>['name'=>'Fixture']];
$a=hmlgua_andromeda_evidence(HMLGUA_OPERATION,$prior,['provider'=>'andromeda','external_id'=>'2000001','country_id'=>4,'target_local_hotel_id'=>6319,'routes'=>['exact_ordered_literal'],'live'=>false,'observation_count'=>0,'evidence'=>['place_match'=>true]]);
hmlgua_t($a['prior_evidence']===$prior,'prior evidence preserved');
hmlgua_t($a['promotion']['provider']==='andromeda'&&$a['promotion']['external_id']==='2000001','andromeda identity');
hmlgua_t($a['promotion']['target_local_hotel_id']===6319&&$a['promotion']['server_current_same_transaction']===true,'andromeda promotion');

$c=hmlgur_consensus([10=>['x'=>1]],[10=>['x'=>2]]);hmlgua_t($c['state']==='unique'&&$c['target_local_hotel_id']===10,'same-target consensus');
$c=hmlgur_consensus([10=>['x'=>1]],[11=>['x'=>2]]);hmlgua_t($c['state']==='conflict'&&$c['candidate_target_ids']===[10,11],'cross-lane conflict');
$c=hmlgur_consensus([],[]);hmlgua_t($c['state']==='none','empty consensus');

$source=file_get_contents(__DIR__ . '/../scripts/diagnostics/hotel_match_literal_geo_union_accept.php');
hmlgua_t(strpos($source,'FOR UPDATE')!==false,'locking guard absent');
hmlgua_t(strpos($source,'pair_exclusion_protected')!==false,'pair exclusion guard absent');
hmlgua_t(strpos($source,"'readback_verified'=>true")!==false,'readback guard absent');
hmlgua_t(strpos($source,"'tourvisor_calls'=>0")!==false&&strpos($source,"'supplier_calls'=>0")!==false,'supplier zero contract absent');
hmlgua_t(strpos($source,"require_once __DIR__ . '/hotel_match_literal_geo_union_review.php';")!==false,'union review dependency absent');
hmlgua_t(strpos($source,"UPDATE andromeda_hotel_identities")!==false&&strpos($source,"INSERT INTO anex_hotel_search_mappings")!==false,'writer targets absent');

echo "hotel-match-literal-geo-union-accept-test: OK\n";
