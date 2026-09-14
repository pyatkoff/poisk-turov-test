<?php
declare(strict_types=1);
define('OPB_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_pending_anex_bridge.php';
$n=0;function t(bool $v):void{global $n;if(!$v)throw new RuntimeException('assertion_'.($n+1));$n++;}
$e=['schema'=>'operator-original-price-bridge/1','source'=>['id'=>'4158','operator_key'=>'5','name'=>'Barcelo Tiran Sharm'],'provider_bridges'=>[['operator_key'=>'5','native_hotel_id'=>'4158','andromeda_hotel_id'=>'177152','country_id'=>1,'hotel_name'=>'Barcelo Tiran Sharm Hotel Sharm El Sheikh','original_name'=>'Barcelo Tiran Sharm Hotel Sharm El Sheikh','is_operator_hotel_key'=>false,'action'=>'price']]];
$b=opb_bridge($e,'4158');t($b['ok']);t($b['andromeda_ids']===['177152']);t($b['country_id']===1);
$x=$e;$x['source']['operator_key']='315';t(!opb_bridge($x,'4158')['ok']);
$x=$e;$x['provider_bridges'][0]['operator_key']='342';t(!opb_bridge($x,'4158')['ok']);
$x=$e;$x['provider_bridges'][0]['native_hotel_id']='999';t(!opb_bridge($x,'4158')['ok']);
$x=$e;$x['provider_bridges'][0]['is_operator_hotel_key']=true;t(!opb_bridge($x,'4158')['ok']);
$x=$e;$x['provider_bridges'][0]['action']='all';t(!opb_bridge($x,'4158')['ok']);
$x=$e;$x['provider_bridges'][0]['country_id']=8;t(!opb_bridge($x,'4158')['ok']);
$x=$e;$x['provider_bridges'][]=$x['provider_bridges'][0];$x['provider_bridges'][1]['country_id']=4;t(!opb_bridge($x,'4158')['ok']);
t(opb_name_guard(['BARCELÓ TIRAN SHARM HOTEL','Barcelo Tiran Sharm'],'BARCELO TIRAN SHARM'));
t(!opb_name_guard(['Barcelo Tiran Sharm Beach'],'BARCELO TIRAN SHARM'));
t(!opb_name_guard(['Domina Aquamarine Pool'],'DOMINA AQUAMARINE BEACH'));
t(!opb_name_guard(['Hotel Resort Spa'],'HOTEL RESORT SPA'));
$h=['latitude'=>28.069,'longitude'=>34.442];t(opb_distance(['latitude'=>28.069,'longitude'=>34.442],$h)<0.001);t(opb_distance([],[])===null);t(opb_distance(['latitude'=>41.0,'longitude'=>29.0],$h)>5);
$d=sys_get_temp_dir().'/opb-'.bin2hex(random_bytes(4));mkdir($d);$sha=opb_write($d.'/a.json',['a'=>1]);t($sha===hash_file('sha256',$d.'/a.json'));try{opb_write($d.'/a.json',['a'=>2]);t(false);}catch(Throwable $ignore){t(true);}unlink($d.'/a.json');rmdir($d);
echo $n." operator-pending-anex bridge checks PASS\n";