<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/andromeda-quote-attempt-state.php';

$checks = 0;
$context = hash('sha256', 'context-a');
$operation = hash('sha256', 'andromeda-selected-quote-v1');
$reserved = AnyTourAndromedaQuoteAttemptState::reserve($context, $operation);
if (($reserved['status'] ?? null) !== 'reserved' || !array_key_exists('result', $reserved) || $reserved['result'] !== null) throw new RuntimeException('reserve');
++$checks;

try {
    AnyTourAndromedaQuoteAttemptState::replay($reserved, $context, $operation);
    throw new RuntimeException('reserved replay accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_REPLAY_REFUSED') throw $e;
}
++$checks;

$unknown = AnyTourAndromedaQuoteAttemptState::unknown($reserved);
try {
    AnyTourAndromedaQuoteAttemptState::replay($unknown, $context, $operation);
    throw new RuntimeException('unknown replay accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_REPLAY_REFUSED') throw $e;
}
++$checks;

$result = [
    'schema_version' => 1,
    'provider' => 'andromeda',
    'selection_enabled' => true,
    'booking_enabled' => false,
    'local_id' => 6319,
    'state' => 'quote_verified',
    'quote_state' => 'verified',
    'search_price' => ['amount' => '119114', 'currency' => 'RUB'],
    'package_price' => ['amount' => '124864', 'currency' => 'RUB'],
    'final_price' => ['amount' => '135643', 'currency' => 'RUB'],
    'final_price_verified' => true,
    'flight_selection_required' => false,
    'flights' => [],
];
$completed = AnyTourAndromedaQuoteAttemptState::completed($reserved, $result);
$replayed = AnyTourAndromedaQuoteAttemptState::replay($completed, $context, $operation);
if ($replayed !== $result) throw new RuntimeException('completed replay');
++$checks;

foreach ([hash('sha256', 'context-b'), $context] as $i => $candidate) {
    $candidateOperation = $i === 0 ? $operation : hash('sha256', 'operation-b');
    try {
        AnyTourAndromedaQuoteAttemptState::replay($completed, $candidate, $candidateOperation);
        throw new RuntimeException('provenance mismatch accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ANDROMEDA_QUOTE_REPLAY_REFUSED') throw $e;
    }
    ++$checks;
}

$choice = $result;
$choice['state'] = 'flight_selection_required';
$choice['quote_state'] = 'unverified';
$choice['final_price'] = null;
$choice['final_price_verified'] = false;
$choice['flight_selection_required'] = true;
$choiceState = AnyTourAndromedaQuoteAttemptState::completed($reserved, $choice);
if (AnyTourAndromedaQuoteAttemptState::replay($choiceState, $context, $operation) !== $choice) throw new RuntimeException('choice replay');
++$checks;

$private = $result;
$private['supplier_offer_id'] = 'opaque';
try {
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $private);
    throw new RuntimeException('private state accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_PRIVATE_STATE') throw $e;
}
++$checks;

$booking = $result;
$booking['booking_enabled'] = true;
try {
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $booking);
    throw new RuntimeException('booking accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_RESULT_INVALID') throw $e;
}
++$checks;

$unverified = $result;
$unverified['final_price_verified'] = false;
try {
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $unverified);
    throw new RuntimeException('unverified final accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_RESULT_INVALID') throw $e;
}
++$checks;

$badMoney = [];
foreach ([
    ['final_price', ['currency' => 'RUB']],
    ['final_price', ['amount' => '0', 'currency' => 'RUB']],
    ['final_price', ['amount' => '135643.001', 'currency' => 'RUB']],
    ['final_price', ['amount' => '135643', 'currency' => 'rub']],
    ['final_price', ['amount' => '135643', 'currency' => 'RUB', 'source' => 'invented']],
    ['search_price', ['amount' => '0', 'currency' => 'RUB']],
    ['search_price', ['amount' => '119114', 'currency' => 'RUB', 'total' => '150824']],
    ['package_price', ['amount' => '124864', 'currency' => 'RUB', 'agency_cost' => '1']],
] as [$field, $value]) {
    $bad = $result;
    $bad[$field] = $value;
    $badMoney[] = $bad;
}
foreach ($badMoney as $bad) {
    try {
        AnyTourAndromedaQuoteAttemptState::completed($reserved, $bad);
        throw new RuntimeException('malformed money accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ANDROMEDA_QUOTE_MONEY_INVALID') throw $e;
    }
    ++$checks;
}

$noPackagePrice = $result;
$noPackagePrice['package_price'] = null;
if (AnyTourAndromedaQuoteAttemptState::replay(
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $noPackagePrice),
    $context,
    $operation
) !== $noPackagePrice) throw new RuntimeException('nullable package price rejected');
++$checks;

foreach ([
    ['schema_version', 2],
    ['local_id', null],
    ['local_id', 0],
    ['flight_selection_required', true],
    ['flights', ['not-a-list' => []]],
] as [$field, $value]) {
    $bad = $result;
    $bad[$field] = $value;
    try {
        AnyTourAndromedaQuoteAttemptState::completed($reserved, $bad);
        throw new RuntimeException('state mismatch accepted');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'ANDROMEDA_QUOTE_RESULT_INVALID') throw $e;
    }
    ++$checks;
}

$choiceMismatch = $choice;
$choiceMismatch['flight_selection_required'] = false;
try {
    AnyTourAndromedaQuoteAttemptState::completed($reserved, $choiceMismatch);
    throw new RuntimeException('choice boolean mismatch accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_RESULT_INVALID') throw $e;
}
++$checks;

$corruptCompleted = $completed;
$corruptCompleted['result']['final_price'] = ['amount' => 'not-money', 'currency' => 'RUB'];
try {
    AnyTourAndromedaQuoteAttemptState::replay($corruptCompleted, $context, $operation);
    throw new RuntimeException('corrupt completed replay accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'ANDROMEDA_QUOTE_MONEY_INVALID') throw $e;
}
++$checks;

// V2 is issued only by the fresh initial gateway. It keeps the supplier head
// separate from cached public receipts and bounds the entire mutable context.
$expires=2000000000;
$choice['expires_at']=$expires;
$choice['search_price_estimate']=['amount'=>'130614','currency'=>'RUB','source'=>'derived_search_estimate'];
$raw=['claimDocument'=>[['condition'=>'ccOffer','transports'=>[]]]];
$v2=AnyTourAndromedaFlightRepricingState::create($context,hash('sha256','inventory'),'SID_fixture',$raw,$choice,$expires);
$expectRefusal=static function(callable $call,string $message)use(&$checks):void{
    try{$call();throw new RuntimeException('EXPECTED_REPRICE_REFUSAL');}
    catch(RuntimeException|DomainException $e){if($e->getMessage()!==$message)throw $e;}
    ++$checks;
};
$pair=static function(string $out,string $back,int $ref):array{
    return ['selection'=>['provider'=>'andromeda','outbound_ref'=>'flight_'.str_pad(dechex($ref),32,'0',STR_PAD_LEFT),
        'return_ref'=>'flight_'.str_pad(dechex($ref+1),32,'0',STR_PAD_LEFT)],
        'selected'=>[['uid'=>$out,'direction'=>'0','type'=>'ttAvia'],['uid'=>$back,'direction'=>'1','type'=>'ttAvia']]];
};
$a=$pair('out_a','back_a',1);$b=$pair('out_b','back_b',3);$c=$pair('out_c','back_c',5);$d=$pair('out_d','back_d',7);
$receipt=static function(array $pair,string $amount)use($result,$choice,$expires):array{
    $receipt=$result;$receipt['expires_at']=$expires;$receipt['verified_at']=1900000000;$receipt['search_price_estimate']=$choice['search_price_estimate'];
    $receipt['final_price']=['amount'=>$amount,'currency'=>'RUB'];
    $receipt['flights']=[['direction'=>'0','flight_ref'=>$pair['selection']['outbound_ref']],
        ['direction'=>'1','flight_ref'=>$pair['selection']['return_ref']]];
    return $receipt;
};
$head=static function(array $pair,string $amount,string $generation):array{
    return ['generation'=>$generation,'claimDocument'=>[['condition'=>'ccOffer',
        'transports'=>[['transport'=>$pair['selected']]],
        'buyerMoneys'=>[['buyerClaimMoney'=>[['net'=>$amount,'currency'=>'RUB']]]]]]];
};
if(AnyTourAndromedaFlightRepricingState::metadata($v2)!==['enabled'=>true,'max_pairs'=>3,'used_pairs'=>0,'remaining_pairs'=>3])throw new RuntimeException('V2_INITIAL_CAP');
++ $checks;
$completePair=static function(array $state,array $pair,string $token,string $amount,string $generation)use($context,$receipt,$head,&$checks):array{
    $begun=AnyTourAndromedaFlightRepricingState::begin($state,$context,$pair['selection'],$pair['selected'],$token,1900000000);
    if($begun['replay']!==null)throw new RuntimeException('FRESH_PAIR_REPLAY');
    $state=$begun['state'];
    $first=AnyTourAndromedaFlightRepricingState::spend($state,$token,1900000000000);
    $second=AnyTourAndromedaFlightRepricingState::spend($first['state'],$token,1900000000000);
    $third=AnyTourAndromedaFlightRepricingState::spend($second['state'],$token,1900000000000);
    if($second['wait_ms']!==$first['wait_ms']+1050||$third['wait_ms']!==$second['wait_ms']+1050)throw new RuntimeException('CROSS_REQUEST_PACING_LOST');
    $state=AnyTourAndromedaFlightRepricingState::completed($third['state'],$token,$head($pair,$amount,$generation),$receipt($pair,$amount),1900000000);
    ++$checks;return $state;
};
$v2=$completePair($v2,$a,str_repeat('a',32),'135643','head_a');
$v2=$completePair($v2,$b,str_repeat('b',32),'136643','head_b');
$beforeReplay=$v2;
$reordered=['return_ref'=>$a['selection']['return_ref'],'outbound_ref'=>$a['selection']['outbound_ref'],'provider'=>'andromeda'];
$replay=AnyTourAndromedaFlightRepricingState::begin($v2,$context,$reordered,$a['selected'],str_repeat('d',32),1900000000);
if($replay['state']!==$beforeReplay||$replay['replay']!==$receipt($a,'135643')
    ||$replay['state']['claim']['generation']!=='head_b'||$replay['state']['action_count']!==6)throw new RuntimeException('CACHE_REWOUND_SUPPLIER_HEAD_OR_SPENT');
++ $checks;
$v2=$completePair($replay['state'],$c,str_repeat('c',32),'137643','head_c');
if(AnyTourAndromedaFlightRepricingState::metadata($v2)!==['enabled'=>true,'max_pairs'=>3,'used_pairs'=>3,'remaining_pairs'=>0]
    ||$v2['action_count']!==9||$v2['initial_pricing']['package_price']!==$choice['package_price'])throw new RuntimeException('V2_CAP_OR_INITIAL_PRICE_CHANGED');
++ $checks;
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::begin($v2,$context,$d['selection'],$d['selected'],str_repeat('d',32),1900000000),'ANDROMEDA_FLIGHT_REPRICE_BUDGET');
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::begin($v2,hash('sha256','other'),$a['selection'],$a['selected'],str_repeat('d',32),1900000000),'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::begin($v2,$context,$a['selection'],$a['selected'],str_repeat('d',32),$expires),'offer_expired');
$aKey=AnyTourAndromedaFlightRepricingState::pairKey($context,$a['selection']);
$corruptCache=$v2;$corruptCache['pairs'][$aKey]['result']['package_price']=['amount'=>'136643','currency'=>'RUB'];
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::begin($corruptCache,$context,$a['selection'],$a['selected'],str_repeat('d',32),1900000000),'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
$corruptCache=$v2;$corruptCache['pairs'][$aKey]['result']['final_price']['currency']='USD';
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::begin($corruptCache,$context,$a['selection'],$a['selected'],str_repeat('d',32),1900000000),'ANDROMEDA_FLIGHT_REPRICE_CURRENCY');
if($v2['action_count']!==9||$v2['claim']['generation']!=='head_c'||$v2['pairs'][$aKey]['result']!==$receipt($a,'135643'))throw new RuntimeException('CORRUPT_CACHE_READ_MUTATED_OR_SPENT');
$sealed=AnyTourAndromedaFlightRepricingState::unknown($v2,str_repeat('c',32));
if(AnyTourAndromedaFlightRepricingState::metadata($sealed)!==['enabled'=>false,'max_pairs'=>3,'used_pairs'=>3,'remaining_pairs'=>0])throw new RuntimeException('UNKNOWN_CAP_STILL_ENABLED');
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::begin($sealed,$context,$a['selection'],$a['selected'],str_repeat('d',32),1900000000),'ANDROMEDA_QUOTE_REPLAY_REFUSED');

$fresh=AnyTourAndromedaFlightRepricingState::create($context,hash('sha256','inventory'),'SID_fixture',$raw,$choice,$expires);
$pending=AnyTourAndromedaFlightRepricingState::begin($fresh,$context,$a['selection'],$a['selected'],str_repeat('e',32),1900000000)['state'];
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::begin($pending,$context,$b['selection'],$b['selected'],str_repeat('f',32),1900000000),'ANDROMEDA_QUOTE_REPLAY_REFUSED');
if($pending['status']!=='reserved'||$pending['action_count']!==0)throw new RuntimeException('REENTRY_MUTATED_OR_SEALED_OWNER');
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::spend($pending,str_repeat('f',32),1900000000000),'ANDROMEDA_QUOTE_REPLAY_REFUSED');
$spent=AnyTourAndromedaFlightRepricingState::spend($pending,str_repeat('e',32),1900000000000)['state'];
$badHead=$head($b,'135643','wrong_pair');
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::completed($spent,str_repeat('e',32),$badHead,$receipt($a,'135643'),1900000000),'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
$badReceipt=$receipt($a,'135643');$badReceipt['package_price']=['amount'=>'136643','currency'=>'RUB'];
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::completed($spent,str_repeat('e',32),$head($a,'135643','head'),$badReceipt,1900000000),'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
$badReceipt=$receipt($a,'135643');$badReceipt['final_price']['currency']='USD';
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::completed($spent,str_repeat('e',32),$head($a,'135643','head'),$badReceipt,1900000000),'ANDROMEDA_FLIGHT_REPRICE_CURRENCY');
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::completed($spent,str_repeat('e',32),$head($a,'135642','head'),$receipt($a,'135643'),1900000000),'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::completed($spent,str_repeat('e',32),$head($a,'135643','head'),$receipt($a,'135643'),$expires),'offer_expired');
$oversize=$raw;$oversize['padding']=str_repeat('x',2097152);
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::create($context,hash('sha256','inventory'),'SID_fixture',$oversize,$choice,$expires),'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
$nearExpiry=$pending;$nearExpiry['last_started_ms']=$expires*1000-100;
$expectRefusal(static fn()=>AnyTourAndromedaFlightRepricingState::spend($nearExpiry,str_repeat('e',32),$expires*1000-100),'offer_expired');
print("Andromeda quote attempt state: {$checks} checks passed\n");
