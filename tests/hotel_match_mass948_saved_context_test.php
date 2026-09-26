<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_mass948_saved_context_v1.php';
$n=0;
function check(bool $v,string $msg):void{global$n;$n++;if(!$v)throw new RuntimeException($msg);}
function rejects(callable $f,string $msg):void{$bad=false;try{$f();}catch(Throwable){$bad=true;}check($bad,$msg);}
$m=['census_result_sha256'=>M948_CENSUS_SHA,'rows'=>[]];for($i=0;$i<948;$i++)$m['rows'][]=['tv_hotel_id'=>1000000+$i,'needs_samo'=>$i<220,'needs_anex'=>$i>=220];
check(count(m948_manifest($m))===948,'all948');
foreach(['census_result_sha256','rows']as$key){$bad=$m;unset($bad[$key]);rejects(fn()=>m948_manifest($bad),'missing_'.$key);}
$bad=$m;$bad['rows'][1]=$bad['rows'][0];rejects(fn()=>m948_manifest($bad),'duplicate');
foreach([11742,11748,11771,11773,27691,45455,60000,64351,64355,64722,71376,113617,117800,119844]as$id){$bad=$m;$bad['rows'][0]['tv_hotel_id']=$id;rejects(fn()=>m948_manifest($bad),'completed_'.$id);}
foreach([0,-1,'1000000',null]as$id){$bad=$m;$bad['rows'][0]['tv_hotel_id']=$id;rejects(fn()=>m948_manifest($bad),'invalid_id');}
foreach([[true,true],[false,false],[1,false],[null,true]]as[$s,$a]){$bad=$m;$bad['rows'][0]['needs_samo']=$s;$bad['rows'][0]['needs_anex']=$a;rejects(fn()=>m948_manifest($bad),'not_one_missing');}
$bad=$m;$bad['rows'][0]['needs_samo']=false;$bad['rows'][0]['needs_anex']=true;rejects(fn()=>m948_manifest($bad),'wrong220728');
$good=['source'=>['id'=>7,'name'=>'Hotel','operatorKey'=>5,'original'=>['hotelKey'=>'77'],'hotel_url'=>'https://agent.anextour.ru/?hotellist=77','password'=>'secret'],'username'=>'private','customer_email'=>'x@example.test'];$s=m948_safe($good);check(isset($s['source']['original']['hotelKey']),'original_key_retained');check(!isset($s['source']['password'],$s['username'],$s['customer_email']),'private_fields_removed');
foreach(['?token=x','?session=x','?api_key=x','?password=x']as$q){$s=m948_safe(['url'=>'https://example.test/'.$q]);check(!isset($s['url']),'credential_url_removed');}
check(m948_safe(['url'=>'https://user:pass@example.test/a'])===[],'userinfo_removed');
$rows=[];m948_hotels(['all'=>['payload'=>['HOTEL'=>[['id'=>7,'name'=>'Hotel'],['id'=>7,'name'=>'Hotel']]]]],$rows);check(count($rows)===1,'catalog_dedup');
$ids=[];hmsab_collect_ids(['operatorKey'=>5,'original'=>['hotelKey'=>'77']],$ids);check(array_keys($ids)===[77],'anex_anchor');$ids=[];hmsab_collect_ids(['operatorKey'=>315,'hotelKey'=>'77'],$ids);check($ids===[],'wrong_operator_no_bridge');
$p=hmsma_direct_id(['operator_name'=>'ANEX','operator_link'=>'https://agent.anextour.ru/?hotellist=77']);check($p['status']==='direct_id'&&$p['anex_hotel_id']==='77','direct_native');
$p=hmsma_direct_id(['operator_name'=>'ANEX','operator_link'=>'https://agent.anextour.ru/?hotellist=77,78']);check($p['status']==='ambiguous_direct_id','ambiguous_native');
$tmp=sys_get_temp_dir().'/m948-'.bin2hex(random_bytes(6)).'.json';try{$h=m948_save($tmp,['ok'=>true]);check(hash_file('sha256',$tmp)===$h,'durable_hash');rejects(fn()=>m948_save($tmp,['again'=>true]),'no_replay_file');}finally{if(is_file($tmp))unlink($tmp);}
echo "MASS948_CONTEXT_CHECKS_OK $n\n";
