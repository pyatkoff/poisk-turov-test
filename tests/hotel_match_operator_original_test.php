<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_original_batch.php';
$n=0;
function ok(bool $condition):void{global $n;if(!$condition)throw new RuntimeException('assertion_'.($n+1));$n++;}
function rejects(callable $f):bool{try{$f();return false;}catch(Throwable $e){return true;}}
$row=['hotelKey'=>177152,'operatorKey'=>5,'isOperatorHotelKey'=>0,'hotel'=>'Barcelo Tiran Sharm','original'=>['hotelKey'=>4158,'hotel'=>'Barcelo Tiran Sharm']];
$fact=mob_fact($row,['177152'=>true],'5',1,'request','response');
ok($fact['native_hotel_id']==='4158');ok($fact['andromeda_hotel_id']==='177152');ok($fact['operator_key']==='5');ok(!$fact['is_operator_hotel_key']);
ok(rejects(fn()=>mob_fact($row,['8801'=>true],'5',1,'r','s')));
ok(rejects(fn()=>mob_fact($row,['177152'=>true],'315',1,'r','s')));
foreach([1,'1',null,false,true,'unknown'] as $v){$r=$row;$r['isOperatorHotelKey']=$v;ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);}
foreach([null,0,'0',false,[],str_repeat('9',33),'a|b'] as $v){$r=$row;$r['original']['hotelKey']=$v;ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);}
$r=$row;unset($r['original']);ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);
$r=$row;$r['original']['hotel']='Fortuna 5*';ok(mob_fact($r,['177152'=>true],'5',1,'r','s')===null);
$r=$row;$r['operatorKey']=115;$r['original']['hotelKey']='BG-001.2';$f=mob_fact($r,['177152'=>true],'115',1,'r','s');ok($f['native_hotel_id']==='BG-001.2'&&$f['operator_key']==='115');
$ids=array_map('strval',range(2000000001,2000001200));$chunks=mob_chunks($ids);ok(array_merge(...$chunks)===$ids);ok(count($chunks)>40);foreach($chunks as $chunk)if(count($chunk)>30||strlen(implode(',',$chunk))>300)throw new RuntimeException('chunk_budget');ok(true);
ok(mob_chunks([])===[]);ok(rejects(fn()=>mob_chunks(['0'])));ok(rejects(fn()=>mob_chunks(['177152,4158'])));
$dir=sys_get_temp_dir().'/mob-test-'.bin2hex(random_bytes(5));mkdir($dir);$digest=mob_write($dir.'/result.json',['mapping_writes'=>0]);ok($digest===hash_file('sha256',$dir.'/result.json'));ok(rejects(fn()=>@mob_write($dir.'/result.json',[])));unlink($dir.'/result.json');rmdir($dir);
$other=$row;$other['hotelKey']=999999;$scoped=$row;$scoped['isOperatorHotelKey']=1;
$ins=mob_inspect([$row,$other,$scoped],['177152'=>true],'5',1,'r','s');ok(count($ins['facts'])===1);ok(count($ins['holds'])===2);ok(count($ins['observations'])===3);ok($ins['holds'][0]['reason']==='outside_requested_hotel_or_operator');
echo $n." operator-original checks PASS\n";
