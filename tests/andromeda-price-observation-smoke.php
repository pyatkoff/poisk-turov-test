<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/andromeda-price-observation.php';

$checks = 0;
function observation_check(bool $ok, string $name): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('ANDROMEDA_PRICE_OBSERVATION_TEST_' . $name);
}

$estimate = ['amount' => '133486.80', 'currency' => 'RUB', 'source' => 'derived_search_estimate'];
$observation = AnyTourAndromedaPriceObservation::build(
    $estimate,
    ['amount' => '135643', 'currency' => 'RUB']
);
observation_check($observation['state'] === 'comparable', 'state');
observation_check($observation['signed_delta_amount'] === '2156.20', 'signed_delta');
observation_check($observation['absolute_delta_amount'] === '2156.20', 'absolute_delta');
observation_check($observation['relative_delta_bps'] === 162, 'relative_bps');
observation_check($observation['search_price_estimate'] === $estimate, 'estimate_preserved');
observation_check($observation['final_price_verified'] === true, 'final_verified');

$below = AnyTourAndromedaPriceObservation::build(
    ['amount' => '140000', 'currency' => 'RUB', 'source' => 'derived_search_estimate'],
    ['amount' => '135643', 'currency' => 'RUB']
);
observation_check($below['signed_delta_amount'] === '-4357.00', 'negative_delta');
observation_check($below['absolute_delta_amount'] === '4357.00', 'negative_absolute');

$missing = AnyTourAndromedaPriceObservation::build(null, ['amount' => '135643', 'currency' => 'RUB']);
observation_check($missing['state'] === 'estimate_unavailable', 'missing');
observation_check($missing['relative_delta_bps'] === null, 'missing_no_delta');

$mismatch = AnyTourAndromedaPriceObservation::build(
    ['amount' => '1510', 'currency' => 'USD', 'source' => 'derived_search_estimate'],
    ['amount' => '135643', 'currency' => 'RUB']
);
observation_check($mismatch['state'] === 'currency_mismatch', 'currency_mismatch');
observation_check($mismatch['absolute_delta_amount'] === null, 'currency_no_delta');

$summary = AnyTourAndromedaPriceObservation::summarize([$observation, $below, $missing, $mismatch]);
observation_check($summary['counts']['total'] === 4, 'summary_total');
observation_check($summary['counts']['comparable'] === 2, 'summary_comparable');
observation_check($summary['counts']['estimate_unavailable'] === 1, 'summary_missing');
observation_check($summary['counts']['currency_mismatch'] === 1, 'summary_currency');
observation_check($summary['counts']['within_300_bps'] === 1, 'summary_3pct');
observation_check($summary['counts']['within_500_bps'] === 2, 'summary_5pct');
observation_check($summary['p50_relative_delta_bps'] === 162, 'summary_p50');
observation_check($summary['p90_relative_delta_bps'] === 311, 'summary_p90');
observation_check($summary['product_accuracy_target_applied_as_runtime_gate'] === false, 'no_runtime_gate');

foreach ([
    fn() => AnyTourAndromedaPriceObservation::build(
        ['amount'=>'133486.80','currency'=>'RUB','source'=>'guessed'],
        ['amount'=>'135643','currency'=>'RUB']
    ),
    fn() => AnyTourAndromedaPriceObservation::build(
        ['amount'=>'133486.801','currency'=>'RUB','source'=>'derived_search_estimate'],
        ['amount'=>'135643','currency'=>'RUB']
    ),
    fn() => AnyTourAndromedaPriceObservation::build(
        ['amount'=>'133486.80','currency'=>'RUB','source'=>'derived_search_estimate'],
        ['amount'=>'0','currency'=>'RUB']
    ),
] as $bad) {
    try {
        $bad();
        observation_check(false, 'reject');
    } catch (InvalidArgumentException $e) {
        observation_check(true, 'reject');
    }
}

// Search response and later estimate deliberately disagree; only the echoed receipt is authoritative for this corpus.
$now = 1800000000;
$context = ['provider'=>'andromeda','search_ref'=>str_repeat('a',64),'generation'=>7,
    'page'=>1,'offer_ref'=>'offer_'.str_repeat('b',64)];
$basePrice = ['amount'=>'119114','currency'=>'RUB'];
$servedPrice = ['amount'=>'133486.80','currency'=>'RUB'];
$receipt = ['context'=>$context,'local_id'=>900,'base_price'=>$basePrice,'served_price'=>$servedPrice,
    'basis'=>'transport_surcharge_estimate','issued_at'=>$now-30];
$reference = 'listing_'.hash('sha256',json_encode($receipt,JSON_THROW_ON_ERROR));
$receipts = [$reference=>$receipt];
$resolved = ['context'=>$context+['operator_ref'=>'operator_5','local_id'=>900],
    'offer'=>['local_hotel_id'=>900,'price'=>$basePrice]];
$read = static fn($rows,$ref,$selection=null,$created=null,$expires=null,$time=null)
    => AnyTourAndromedaPriceObservation::resolveServed($rows,$ref,$selection??$resolved,$created??($now-60),$expires??($now+60),$time??$now);
$quote = ['state'=>'quote_verified','final_price_verified'=>true,
    'search_price_estimate'=>['amount'=>'140000','currency'=>'RUB','source'=>'derived_search_estimate'],
    'final_price'=>['amount'=>'135643','currency'=>'RUB']];
$found = $read($receipts,$reference);
observation_check($found === $receipt, 'served_lookup');
$served = AnyTourAndromedaPriceObservation::compareServed($found,$quote,$now);
observation_check($served['served_price'] === $servedPrice && $served['basis'] === 'search_api_response','served_provenance');
observation_check($served['signed_delta_amount'] === '2156.20' && $served['relative_delta_bps'] === 162,'served_not_recomputed');
observation_check($served['served_at'] === $now-30 && $served['actualized_at'] === $now,'served_timing');
observation_check(!isset($served['search_price_estimate']), 'separate_corpus');
foreach ([null,[],true,'','listing_'.str_repeat('c',64)] as $ref) observation_check($read($receipts,$ref) === null,'missing_reference');
observation_check($read([],$reference) === null,'other_session');
observation_check($read($receipts,$reference,$resolved,$now-20) === null,'recreated_search');
observation_check($read($receipts,$reference,$resolved,$now-60,$now) === null,'expired_search');
observation_check($read($receipts,$reference,$resolved,$now-60,$now+1000,$now+870) === null,'receipt_ttl');
foreach (['provider'=>'tourvisor','search_ref'=>str_repeat('d',64),'generation'=>8,'page'=>2,
    'offer_ref'=>'offer_'.str_repeat('d',64)] as $key=>$value) {
    $wrong=$resolved;$wrong['context'][$key]=$value;
    observation_check($read($receipts,$reference,$wrong)===null,'wrong_context_'.$key);
}
$wrong=$resolved;$wrong['offer']['local_hotel_id']=901;
observation_check($read($receipts,$reference,$wrong)===null,'reassigned_local');
$wrong=$resolved;$wrong['offer']['price']['amount']='119115';
observation_check($read($receipts,$reference,$wrong)===null,'changed_base');
$wrong=$receipts;$wrong[$reference]['served_price']['amount']='1';
observation_check($read($wrong,$reference)===null,'altered_receipt');
$wrong=$receipt;$wrong['sid']='private-test';$ref='listing_'.hash('sha256',json_encode($wrong));
observation_check($read([$ref=>$wrong],$ref)===null,'surplus_not_accepted');
observation_check(AnyTourAndromedaPriceObservation::compareServed(null,$quote,$now)===null,'missing_not_estimated');
$unverified=$quote;$unverified['final_price_verified']=false;
observation_check(AnyTourAndromedaPriceObservation::compareServed($receipt,$unverified,$now)===null,'unverified_not_compared');
$otherCurrency=$quote;$otherCurrency['final_price']['currency']='USD';
observation_check(AnyTourAndromedaPriceObservation::compareServed($receipt,$otherCurrency,$now)['relative_delta_bps']===null,'served_currency_not_converted');
try { AnyTourAndromedaPriceObservation::summarize([$served]);observation_check(false,'corpora_not_mixed'); }
catch (InvalidArgumentException $e) { observation_check(true,'corpora_not_mixed'); }

// The selected-offer owner retains normalizer metadata; receipts deliberately do not.
$normalized = $resolved;
$normalized['offer']['price'] += ['currency_id'=>'1','kind'=>'offer','fees'=>'unknown','final'=>false];
$originalNormalized = $normalized;
observation_check($read($receipts,$reference,$normalized) === $receipt,'normalized_base_receipt');
observation_check($normalized === $originalNormalized,'normalized_price_preserved');
foreach (['amount'=>'119115','currency'=>'USD','currency_id'=>true,'kind'=>'package',
    'fees'=>'included','final'=>true,'source'=>'guessed','extra'=>true] as $key=>$value) {
    $wrong=$normalized;$wrong['offer']['price'][$key]=$value;
    observation_check($read($receipts,$reference,$wrong)===null,'normalized_reject_'.$key);
}
$wrong=$normalized;unset($wrong['offer']['price']['fees']);
observation_check($read($receipts,$reference,$wrong)===null,'incomplete_normalizer_metadata');
$wrong=$receipts;$wrong[$reference]['base_price']['amount']='119115';
observation_check($read($wrong,$reference,$normalized)===null,'normalized_receipt_hash_preserved');
observation_check($read([],$reference,$normalized)===null,'normalized_other_session');
observation_check($read($receipts,$reference,$normalized,$now-60,$now)===null,'normalized_ttl_preserved');

// The existing assembled-runtime CI supplies the REAL API; no copy of its recorder implementation.
if (isset($argv[1])) {
    $runtime=realpath($argv[1]);observation_check($runtime!==false,'runtime_exists');
    require_once $runtime.'/v2/api-andromeda-search3-preview.php';
    require_once $runtime.'/app/integrations/andromeda-selected-offer.php';
    // Exercise the existing normalizer -> retained store -> selected-offer owner, not a two-field fake.
    $nativeNow=time();$nativeState=[];$nativeStore=new AnyTourAndromedaOfferStore($nativeState,true);
    $nativeStore->begin($context['search_ref'],$context['generation'],$nativeNow);
    $criteria=['TOWNFROMINC'=>'1','STATEINC'=>'3','CHECKIN_BEG'=>'20261215','CHECKIN_END'=>'20261215',
        'ADULT'=>2,'CHILD'=>1,'AGES'=>'8','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>'1','PAGE'=>1];
    $row=['id'=>'private-fixture-only','hotelKey'=>'123','operatorKey'=>'5','isOperatorHotelKey'=>0,
        'price'=>$basePrice['amount'],'currency'=>'RUB','currencyKey'=>'1','checkIn'=>'15.12.2026',
        'nights'=>7,'hotel'=>'Test hotel','operator'=>'ANEX','meal'=>'AI','mealKey'=>'7',
        'room'=>'Standard','htplace'=>'DBL+CHD','adult'=>2,'child'=>1];
    $nativePage=$nativeStore->capture(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]],
        $criteria,$context['search_ref'],$context['generation'],$nativeNow);
    // A local accepted mapping is supplied only to this in-memory fixture, never to a registry.
    $nativeState['snapshot']['offers'][0]['local_hotel_id']=900;
    $nativeContext=$context;$nativeContext['offer_ref']=$nativePage['offers'][0]['offer_ref'];
    $nativeResolved=AnyTourAndromedaSelectedOffer::resolve($nativeStore,$nativeContext,
        static fn(array $offer): bool => $offer['local_hotel_id']===900,$nativeNow);
    observation_check(count($nativeResolved['offer']['price'])===6,'real_normalized_price_shape');
    $temporary=sys_get_temp_dir().'/andromeda-price-session-'.bin2hex(random_bytes(8));
    mkdir($temporary,0700);ini_set('session.save_path',$temporary);ini_set('session.use_cookies','0');session_cache_limiter('');
    session_id('andromeda-price-test-'.bin2hex(random_bytes(8)));
    $projection=['provider'=>'andromeda','hotels'=>[['local_id'=>900,'tours'=>[[
        'offer_context'=>$nativeContext,'price'=>$servedPrice,'base_search_price'=>$nativeResolved['offer']['price'],
        'search_surcharge'=>['state'=>'estimated'],'irrelevant_supplier_field'=>'private-test',
    ]]]]];
    try {
        session_start();$_SESSION=['unrelated'=>'preserved'];session_write_close();
        $output=anytour_andromeda_search3_record_response($projection);
        $ref=$output['hotels'][0]['tours'][0]['listing_price_ref']??null;
        observation_check(is_string($ref),'real_http_recorder');
        session_start();$stored=$_SESSION['andromeda_listing_prices_v1'];
        observation_check($_SESSION['unrelated']==='preserved','session_preserved');session_write_close();
        observation_check(!str_contains(json_encode($stored),'private-test'),'receipt_whitelist');
        $issued=$stored[$ref]['issued_at'];
        $value=AnyTourAndromedaPriceObservation::resolveServed($stored,$ref,$nativeResolved,$issued-60,$issued+600,time());
        observation_check($value!==null && $value['served_price']===$servedPrice,'native_session_roundtrip');
        $changed=$projection;$changed['hotels'][0]['tours'][0]['price']['amount']='140000';
        $later=anytour_andromeda_search3_record_response($changed);
        $laterRef=$later['hotels'][0]['tours'][0]['listing_price_ref'];
        observation_check($laterRef!==$ref,'distinct_response_prices');
        session_start();$laterStored=$_SESSION['andromeda_listing_prices_v1'];session_write_close();
        observation_check($laterStored[$ref]===$stored[$ref],'earlier_receipt_immutable');
        observation_check(AnyTourAndromedaPriceObservation::compareServed($laterStored[$ref],$quote,time())['signed_delta_amount']==='2156.20','earlier_not_overwritten');
        $many=$projection;$many['hotels'][0]['tours']=[];
        for($i=0;$i<2001;++$i){$tour=$projection['hotels'][0]['tours'][0];$tour['offer_context']['offer_ref']='offer_'.hash('sha256',(string)$i);$many['hotels'][0]['tours'][]=$tour;}
        $bounded=anytour_andromeda_search3_record_response($many);
        session_start();$boundedStored=$_SESSION['andromeda_listing_prices_v1'];session_write_close();
        observation_check(count($boundedStored)===2000 && !isset($bounded['hotels'][0]['tours'][2000]['listing_price_ref']),'session_bound');
        observation_check(!isset($projection['hotels'][0]['tours'][0]['listing_price_ref']),'original_price_unchanged');
        session_id('');
        observation_check(anytour_andromeda_search3_record_response($projection)===$projection,'no_session_no_ref');
    } finally {
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        foreach(glob($temporary.'/sess_*') as $file)unlink($file);rmdir($temporary);
    }
}

echo 'Andromeda price observation: ' . $checks . " checks passed; supplier/DB=0.\n";
