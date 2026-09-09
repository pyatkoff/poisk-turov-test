<?php
require_once __DIR__.'/../app/integrations/andromeda-client.php';
$calls=[];
$client=new AnyTourAndromedaClient(static function($url)use(&$calls){
    parse_str(parse_url($url,PHP_URL_QUERY),$query);$calls[]=$query;
    return ['status'=>200,'body'=>json_encode($query['action']==='login'?['sid'=>'test_session_long_enough']:['PAGE'=>1,'PAGES_COUNT'=>0,'PRICES'=>[]])];
},true);
$client->login('test','test');
$params=['TOWNFROMINC'=>7,'STATEINC'=>3,'CHECKIN_BEG'=>'20270102','CHECKIN_END'=>'20270104','NIGHTS_FROM'=>6,'NIGHTS_TILL'=>9,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>1];
$client->price($params);
if(count($calls)!==2 || $calls[1]['TOWNFROMINC']!=='7' || isset($calls[1]['OPERATORS']) || $calls[1]['CHECKIN_END']!=='20270104')throw new RuntimeException('dynamic criteria not preserved');
try{$client->price($params);throw new LogicException('replayed');}catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_PRICE_REPLAY_REFUSED')throw $e;}
$bad=$params;$bad['CHECKIN_BEG']='20270230';
try{$client->price($bad);throw new LogicException('invalid date accepted');}catch(InvalidArgumentException $e){}
if(count($calls)!==2)throw new RuntimeException('validation spent supplier calls');
echo "Andromeda live criteria passed\n";
