<?php
declare(strict_types=1);
require __DIR__.'/../app/integrations/andromeda-client.php';
$n=0;
function ok($v){global $n;if(!$v)throw new RuntimeException('TEST_FAILED');$n++;}
function rejects($fn,$code){try{$fn();}catch(RuntimeException $e){ok($e->getMessage()===$code);return;}throw new RuntimeException('EXPECTED_REJECTION');}
$params=AnyTourAndromedaClient::priceProbeParams();
ok($params['MEAL']==='5' && $params['CURRENCYINC']===643 && $params['STATEINC']===3 && $params['PAGE']===1);
$calls=[];
$c=new AnyTourAndromedaClient(function($url)use(&$calls,$params){
 parse_str(parse_url($url,PHP_URL_QUERY),$q);$calls[]=$q['action'];
 if($q['action']==='login')return ['status'=>200,'body'=>'{"sid":"FixtureSessionABC123456"}'];
 foreach($params as $k=>$v)ok($q[$k]===(string)$v);
 return ['status'=>200,'body'=>'{"PAGE":1,"PAGES_COUNT":4,"PRICES":[{"id":"999999999999999999999999999","price":"123456.78"}]}'];
},true);
rejects(fn()=>$c->priceProbe(),'ANDROMEDA_LOGIN_REQUIRED');
$c->login('fixture-user','fixture-password');
$r=$c->priceProbe();ok($r['PRICES'][0]['id']==='999999999999999999999999999');ok($r['PAGES_COUNT']===4);
rejects(fn()=>$c->priceProbe(),'ANDROMEDA_PRICE_REPLAY_REFUSED');ok($calls===['login','price']);
foreach(['{"PAGE":2,"PAGES_COUNT":4,"PRICES":[]}','{"PAGE":1,"PAGES_COUNT":0,"PRICES":[{}]}'] as $bad){
 $c=new AnyTourAndromedaClient(fn($url)=>['status'=>200,'body'=>strpos($url,'action=login')!==false?'{"sid":"FixtureSessionABC123456"}':$bad],true);
 $c->login('fixture-user','fixture-password');rejects(fn()=>$c->priceProbe(),'ANDROMEDA_INVALID_PRICE_RESPONSE');
 rejects(fn()=>$c->priceProbe(),'ANDROMEDA_PRICE_REPLAY_REFUSED');
}
$c=new AnyTourAndromedaClient(fn($url)=>['status'=>200,'body'=>strpos($url,'action=login')!==false?'{"sid":"FixtureSessionABC123456"}':'{"PAGE":1,"PAGES_COUNT":1,"PRICES":[{"echo":"FixtureSessionABC123456"}]}'],true);
$c->login('fixture-user','fixture-password');rejects(fn()=>$c->priceProbe(),'ANDROMEDA_SECRET_ECHO');
echo "Andromeda price: $n checks passed\n";
