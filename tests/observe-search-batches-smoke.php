<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/observe-search-batches-v1.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$context=['searchId'=>101,'departureId'=>1,'countryId'=>4,'adults'=>2,'childs'=>[7,3],'currency'=>'RUB'];
$hotels=[];
for($h=1;$h<=100;$h++){
    $tours=[];for($t=1;$t<=501;$t++)$tours[]=['id'=>$h.':'.$t,'price'=>100000+$t,'date'=>'2026-11-10','nights'=>7];
    $hotels[]=['id'=>$h,'country'=>['id'=>4],'tours'=>$tours];
}
$seen=[];$calls=0;
$writer=static function(array $rows,array $ctx)use(&$seen,&$calls,$context):array{
    $calls++;
    check(count($rows)===1 && count($rows[0]['tours'])<=400,'bounded batches');
    foreach($context as $k=>$v)check($ctx[$k]===$v,'context '.$k);
    check($ctx['source']==='user_search','source');
    $written=0;
    foreach($rows[0]['tours'] as $tour){if(!isset($seen[$tour['id']])){$seen[$tour['id']]=true;$written++;}}
    return ['written'=>$written,'seen'=>count($rows[0]['tours']),'ignored'=>0];
};
$result=v2_observe_search_batches($hotels,$context,$writer);
check($result['written']===50100 && $result['seen']===50100 && $result['rows']===100,'no 400 or 50000 search cap');
check($calls===200 && count($seen)===50100,'all chunks');
$again=v2_observe_search_batches($hotels,$context,$writer);
check($again['written']===0 && $again['seen']===50100,'retry retains fingerprints');
foreach(['hotels','items','results'] as $key)check(v2_observe_search_result_rows([$key=>$hotels])===$hotels,'envelope '.$key);
check(v2_observe_search_result_rows([])===[],'valid empty list');
try{v2_observe_search_result_rows(['error'=>'upstream']);throw new LogicException('error accepted as empty');}catch(RuntimeException $e){check(!($e instanceof LogicException),'shape');}
$wrong=[['id'=>1,'country'=>1,'tours'=>[]],['id'=>2,'country'=>['id'=>1],'tours'=>[]]];
check(v2_observe_search_batches($wrong,$context,$writer)['rows']===0,'both country representations filtered');
$small=[['id'=>1,'country'=>4,'tours'=>[['id'=>'a']]]];
try{v2_observe_search_batches($small,$context,static fn()=>['seen'=>0]);throw new LogicException('partial ack accepted');}catch(RuntimeException $e){check(!($e instanceof LogicException),'partial');}
try{v2_observe_search_batches($small,$context,static function(){throw new RuntimeException('DB failure');});throw new LogicException('DB failure swallowed');}catch(RuntimeException $e){check($e->getMessage()==='DB failure','propagated');}
try{v2_observe_search_batches([['id'=>1]],$context,$writer);throw new LogicException('malformed hotel accepted');}catch(RuntimeException $e){check(!($e instanceof LogicException),'missing tours');}
$endpoint=file_get_contents(__DIR__.'/../v2/data/observe-search-v1.php');
check(strpos($endpoint,'fastcgi_finish_request')===false && strpos($endpoint,'http_response_code(202)')===false,'no premature ack');
check(strpos($endpoint,'$pdo->commit();')<strpos($endpoint,"'persisted'=>true"),'ack after commit');
check(str_contains($endpoint,'http_response_code(503)'),'retryable write failure');
echo "OBSERVE_SEARCH_BATCHES_OK hotels=100 tours=50100 cap_removed=1 retry_idempotent=1 truthful_ack=1\n";
