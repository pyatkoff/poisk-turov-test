<?php
declare(strict_types=1);
require __DIR__.'/../app/integrations/andromeda-normalizer.php';
require __DIR__.'/../app/integrations/andromeda-client.php';
$c=AnyTourAndromedaClient::priceProbeParams();
$r=['id'=>'opaque-fixture','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,'price'=>107193,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'18.09.2026','nights'=>'8','hotel'=>'Swiss Heaven Sharming Inn Hotel','operator'=>'Anex Tour','meal'=>'AI','mealKey'=>'6','andrMealKey'=>5,'room'=>'Standard Room','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$n=0;
function check($v){global $n;if(!$v)throw new RuntimeException('CHECK_FAILED');$n++;}
function runrow($r,$c,$gen=1){return AnyTourAndromedaNormalizer::page(['PAGE'=>1,'PAGES_COUNT'=>2,'PRICES'=>[$r]],$c,'fixture_search',$gen);}
$a=runrow($r,$c);check(count($a['offers'])===1);$o=$a['offers'][0];
check($a['status']==='partial');check($o['supplier_namespace']==='andromeda_catalog');
check($o['local_hotel_id']===null && $o['price']['fees']==='unknown' && !$o['selection_enabled']);
check($o['meal']['operator_id']==='6' && $o['meal']['andromeda_id']==='5');
check($o['price']['amount']==='107193');
check(strpos(json_encode($a),'opaque-fixture')===false);
check(runrow($r,$c,2)['offers'][0]['offer_ref']!==$o['offer_ref']);
$other=$r;$other['isOperatorHotelKey']=1;
check(runrow($other,$c)['offers'][0]['supplier_namespace']==='operator_5');
$other['operatorKey']=115;check(runrow($other,$c)['offers'][0]['supplier_namespace']==='operator_115');
foreach (['price'=>1.2,'currencyKey'=>840,'checkIn'=>'31.09.2026','adult'=>'3','nights'=>'9','isOperatorHotelKey'=>null] as $k=>$v){
 $bad=$r;$bad[$k]=$v;check(count(runrow($bad,$c)['rejected'])===1);
}
$dup=AnyTourAndromedaNormalizer::page(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$r,$r]],$c,'fixture_search',1);
check(count($dup['offers'])===1 && count($dup['rejected'])===1 && $dup['status']==='partial');
check(AnyTourAndromedaNormalizer::page(['PAGE'=>1,'PAGES_COUNT'=>0,'PRICES'=>[]],$c,'fixture_search',1)['status']==='complete');
echo "Normalizer: $n checks passed\n";
if (isset($argv[1])) {
 $raw=file_get_contents($argv[1]);
 if(hash('sha256',$raw)!=='c4e0722837b12a8c3979972a1145d6d0e43d50539efebbd7e68a416590dad6a9')throw new RuntimeException('EVIDENCE_DIGEST_MISMATCH');
 $saved=json_decode($raw,true,32,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);
 $out=AnyTourAndromedaNormalizer::page($saved['payload'],$saved['params'],'capture_34365549508',1);
 $encoded=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
 file_put_contents($argv[2],$encoded);check(file_get_contents($argv[2])===$encoded);
 echo json_encode(['real_offers'=>count($out['offers']),'rejected'=>$out['rejected'],'status'=>$out['status'],'sha256'=>hash('sha256',$encoded)],JSON_THROW_ON_ERROR)."\n";
}
