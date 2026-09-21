<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/api-andromeda-stored-quote-preview.php';
$n=1700000000; $checks=0;
$ok=function($v,$m)use(&$checks){if(!$v)throw new LogicException($m);++$checks;};
$base=['source'=>'andromeda-stored-offer-v1','provider'=>'andromeda','handle'=>'stored_'.str_repeat('a',64),
'anytourHotelId'=>4234,'scopeDigest'=>str_repeat('b',64),'expiresAt'=>gmdate('c',$n+600),
'quote'=>['state'=>'confirmation_required','finalPrice'=>null,'expiresAt'=>null]];
$r=anytour_stored_quote_gate($base,null,$n);
$ok($r['state']==='actualization_required'&&$r['supplierCallAllowed']===true&&!$r['finalPriceReady'],'fresh exact context may proceed once');
foreach(['reserved','unknown'] as $s){$r=anytour_stored_quote_gate($base,['status'=>$s],$n);$ok($r['state']==='attempt_sealed'&&!$r['supplierCallAllowed'],'sealed '.$s);}
$verified=['status'=>'completed','result'=>['state'=>'quote_verified','quote_state'=>'verified','final_price_verified'=>true,
'flight_selection_required'=>false,'final_price'=>['amount'=>'199390.00','currency'=>'RUB']]];
$r=anytour_stored_quote_gate($base,$verified,$n);
$ok($r['finalPriceReady']&&$r['finalPrice']['amount']==='199390.00'&&!$r['supplierCallAllowed'],'completed verified quote reused locally');
$flight=['status'=>'completed','result'=>['state'=>'flight_selection_required','quote_state'=>'unverified','final_price_verified'=>false,
'flight_selection_required'=>true,'final_price'=>null]];
$r=anytour_stored_quote_gate($base,$flight,$n);$ok(!$r['finalPriceReady']&&$r['state']==='confirmation_required','flight choice is not final price');
$expired=$base;$expired['expiresAt']=gmdate('c',$n-1);
$r=anytour_stored_quote_gate($expired,null,$n);$ok($r['state']==='refresh_required'&&$r['sameCriteria']&&!$r['supplierCallAllowed'],'expired native context requests same-criteria refresh without hidden call');
$priced=$base;$priced['quote']=['state'=>'verified','finalPrice'=>['amount'=>'159999.90','currency'=>'RUB'],'expiresAt'=>gmdate('c',$n+120)];
$r=anytour_stored_quote_gate($priced,null,$n);$ok($r['finalPriceReady']&&$r['finalPrice']['amount']==='159999.90'&&!$r['supplierCallAllowed'],'existing verified saved price wins without supplier call');
try{anytour_stored_quote_gate(array_replace($base,['handle'=>'bad']),null,$n);throw new LogicException('bad accepted');}catch(InvalidArgumentException $e){++$checks;}
echo "Stored quote gate: $checks checks passed; supplier calls=0; no price arithmetic.\n";
