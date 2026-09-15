<?php
declare(strict_types=1);
putenv('MATCH_AME_TEST_LIBRARY=1');
require __DIR__.'/../scripts/diagnostics/hotel_match_andromeda_operator_evidence_mass_v1.php';
function ame_t(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$ids=[];for($i=1;$i<=67;$i++)$ids[]=(string)$i;$chunks=ame_chunks($ids);ame_t(count($chunks)===3,'chunk_count');ame_t(count($chunks[0])===30&&count($chunks[1])===30&&count($chunks[2])===7,'chunk_sizes');
$wanted=['177152'=>true];$row=['hotelKey'=>177152,'hotel'=>'Barcelo Tiran Sharm','operatorKey'=>5,'operator'=>'Anex Tour','isOperatorHotelKey'=>0,'town'=>'Sharm El Sheikh','original'=>['hotelKey'=>4158,'hotel'=>'BARCELO TIRAN SHARM']];$f=ame_native_fact($row,$wanted,'5',1,str_repeat('a',64),str_repeat('b',64));ame_t(is_array($f),'fact');ame_t($f['native_hotel_id']==='4158'&&$f['andromeda_hotel_id']==='177152'&&$f['country_id']===1,'identity');
$bad=$row;$bad['isOperatorHotelKey']=1;ame_t(ame_native_fact($bad,$wanted,'5',1,'x','y')===null,'operator_scoped_hold');
$bad=$row;$bad['original']['hotelKey']='';ame_t(ame_native_fact($bad,$wanted,'5',1,'x','y')===null,'empty_native_hold');
$bad=$row;$bad['hotel']='Roulette 5*';ame_t(ame_native_fact($bad,$wanted,'5',1,'x','y')===null,'roulette_hold');
$bad=$row;$bad['hotelKey']=999;ame_t(ame_native_fact($bad,$wanted,'5',1,'x','y')===null,'outside_target_hold');
echo "hotel_match_andromeda_operator_evidence_mass_v1_test PASS\n";
