<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_literal_geo_union_accept.php';
function tassert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$prepared=[];
for($i=1;$i<=7;$i++)$prepared[]=['provider'=>'anex','external_id'=>(string)(1000+$i),'country_id'=>1,'target_local_hotel_id'=>2000+$i,'routes'=>['exact_ordered_literal'],'live'=>$i===1,'observation_count'=>$i===1?10:0];
for($i=1;$i<=92;$i++)$prepared[]=['provider'=>'andromeda','external_id'=>(string)(2000000000+$i),'country_id'=>4,'target_local_hotel_id'=>3000+$i,'routes'=>[$i===1?'single_token_exact_place':'exact_ordered_literal'],'live'=>false,'observation_count'=>0];
$review=['status'=>'completed','operation_id'=>HMLGUA_SOURCE_OPERATION,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'stats'=>['prepared'=>99,'prepared_anex'=>7,'prepared_andromeda'=>92],'prepared'=>$prepared];
$m=hmlgua_source_manifest($review);tassert(count($m)===99,'manifest_count');tassert(isset($m['anex:1001']),'anex_key');tassert(isset($m['andromeda:2000000001']),'andromeda_key');tassert($m['andromeda:2000000001']['routes']===['single_token_exact_place'],'route_preserved');
$bad=$review;$bad['prepared'][0]['routes']=['unsafe_route'];$threw=false;try{hmlgua_source_manifest($bad);}catch(RuntimeException $e){$threw=true;}tassert($threw,'unsafe_route_not_blocked');
$j=hmlgua_json(['z'=>1,'a'=>['y'=>2,'x'=>1]]);tassert($j==='{\"a\":{\"x\":1,\"y\":2},\"z\":1}','canonical_json');
echo "hotel-match-literal-geo-union-accept-test: OK\n";
