<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-saved-package-runtime.php';
require_once __DIR__.'/../app/integrations/andromeda-transport.php';
require_once __DIR__.'/../v2/api-andromeda-stored-quote-preview.php';
$n=1700000000; $checks=0;
$ok=function($v,$m)use(&$checks){if(!$v)throw new LogicException($m);++$checks;};
$base=['source'=>'andromeda-stored-offer-v1','provider'=>'andromeda','handle'=>'stored_'.str_repeat('a',64),
'anytourHotelId'=>4234,'scopeDigest'=>str_repeat('b',64),'expiresAt'=>gmdate('c',$n+600),
'identity'=>['search_ref_digest'=>hash('sha256',str_repeat('c',64)),'offer_ref_digest'=>hash('sha256','offer_'.str_repeat('d',64)),'provider_hotel_ref_digest'=>str_repeat('e',64)],
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

$root=sys_get_temp_dir().'/stored-quote-'.bin2hex(random_bytes(6));
$directory=$root.'/searches';mkdir($directory,0700,true);
try {
    $context=['provider'=>'andromeda','search_ref'=>str_repeat('c',64),'generation'=>3,'page'=>2,
        'offer_ref'=>'offer_'.str_repeat('d',64),'hotel_scope'=>'4234','operator_ref'=>'315','local_id'=>4234];
    $viewer=['private_context'=>$context,'expires_at'=>$n+600];
    $config=['enabled'=>true,'catalog_path'=>$root.'/catalog.json'];
    $captureCalls=0;
    $capture=static function($dir,$ctx,$source,$mapping,$transport,$enabled,$clock,$withSurcharge,$flightRequest,$cfg,$cap)
        use (&$captureCalls,$directory,$context,$config):array {
        ++$captureCalls;
        if($dir!==$directory||$ctx!==$context||!preg_match('/^[a-f0-9]{40}$/D',$source)
            ||!$enabled||!$withSurcharge||$flightRequest!==null||$cfg!==$config
            ||!$cap instanceof AnyTourAndromedaPrivateRuntimeCapability
            ||!$transport instanceof AnyTourAndromedaTransport||$mapping([])!==true) throw new LogicException('capture contract');
        return ['status'=>'captured','source'=>$source,'reused'=>false,'context'=>$context,
            'package_sha256'=>str_repeat('f',64),'identity_verified'=>false,'quote_verified'=>false,'selection_enabled'=>false,
            'surcharge'=>['status'=>'complete','reused'=>false,'fact'=>null,'final_price_verified'=>true]];
    };
    $r=anytour_stored_quote_actualize($base,$viewer,$config,$directory,static fn()=>true,$capture,$n);
    $ok($captureCalls===1&&$r['state']==='actualized_verified'&&!$r['finalPriceReady']&&$r['finalPrice']===null,
        'actualization invokes exact private owner once but never invents price before CURRENT reread');
    $sealed=anytour_stored_quote_actualize($base,$viewer,$config,$directory,static fn()=>true,$capture,$n,['status'=>'reserved']);
    $ok($captureCalls===1&&$sealed['state']==='attempt_sealed','sealed attempt cannot enter capture owner');
    $bad=$viewer;$bad['private_context']['offer_ref']='offer_'.str_repeat('9',64);
    try{anytour_stored_quote_actualize($base,$bad,$config,$directory,static fn()=>true,$capture,$n);throw new LogicException('bad private context accepted');}
    catch(DomainException $e){++$checks;}
    $ok($captureCalls===1,'tampered private context refused before capture');
} finally { rmdir($directory);rmdir($root); }
