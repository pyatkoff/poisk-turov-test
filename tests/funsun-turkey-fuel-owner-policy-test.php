<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/integrations/funsun-turkey-fuel-owner-policy.php';

function fs_ok(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function fs_same(mixed $expected,mixed $actual,string $message):void{if($expected!==$actual)throw new RuntimeException($message.' expected='.var_export($expected,true).' actual='.var_export($actual,true));}

$now = 1790181000;
$party = ['adults'=>2,'children'=>0,'child_ages'=>[]];
$direction = ['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'];
$fx = [
    'from'=>'EUR','to'=>'RUB','rate'=>'102.7','source'=>'andromeda_claim_money',
    'scope_sha256'=>AnyTourOperatorFuelRuleEvidenceV1::directionDigest($direction),
    'observed_at'=>$now-60,'expires_at'=>$now+3600,
    'evidence_sha256'=>hash('sha256','fresh-eur-rub-fx'),
];
$offerDigest = hash('sha256','funsun-owner-fallback-target');
$target = [
    'operator'=>'FUN&SUN',
    'search_params'=>['departureId'=>1,'countryId'=>4],
    'party'=>$party,
    'offer_ref_digest'=>$offerDigest,
];
$input = AnyTourFunsunTurkeyFuelOwnerPolicyV1::forTarget($target,$fx,$now);
fs_ok(is_array($input),'owner fallback input missing');
fs_same('70.00',$input['policy']['amount']??null,'native rate');
fs_same('EUR',$input['policy']['currency']??null,'native currency');
fs_same('per_person_one_way',$input['policy']['unit']??null,'native unit');
fs_same(2,$input['policy']['direction_count']??null,'direction count');
fs_same(2,$input['policy']['eligible_passenger_count']??null,'eligible passenger count');
fs_same('280.00',$input['policy']['applied_native_total']??null,'two adults native total');
fs_same(true,$input['policy']['selected_tour_actualization_required']??null,'selected tour actualization marker');
fs_same(false,$input['final_price_verified']??null,'fallback cannot verify final price');

$dto = [
    'provider'=>'andromeda','quote_state'=>'unknown','final_price_verified'=>false,
    'finalPriceReady'=>false,'finalPrice'=>null,'price'=>'100000.00','currency'=>'RUB',
    'identity'=>['offer_ref_digest'=>$offerDigest],
    'operator'=>['raw'=>'FUN&SUN'],
    'tour'=>['party'=>$party],
    'money'=>[
        'search_price'=>['amount'=>'100000.00','currency'=>'RUB'],
        'fuel_charge_reported'=>null,'additional_prices_reported'=>[],
        'arithmetic_applied'=>false,'search_price_fuel_relation'=>'unknown',
    ],
];
$applied = AnyTourFunsunTurkeyFuelOwnerPolicyV1::apply($dto,$input,$now);
fs_same(true,$applied['applied']??null,'fallback was not applied');
$out = $applied['dto'];
fs_same(['amount'=>'28756.00','currency'=>'RUB','source'=>'funsun_turkey_owner_fallback'],$out['money']['fuel_charge_reported']??null,'2A RUB fuel');
fs_same(['amount'=>'128756.00','currency'=>'RUB','source'=>'derived_search_estimate'],$out['money']['search_price_with_surcharge']??null,'derived listing total');
fs_same('100000.00',$out['money']['search_price']['amount']??null,'supplier base mutated');
fs_same(true,$out['money']['arithmetic_applied']??null,'fallback arithmetic flag');
fs_same(true,$out['finalPriceReady']??null,'listing estimate not ready');
fs_same('128756.00',$out['finalPrice']??null,'listing estimate total');
fs_same(false,$out['final_price_verified']??null,'fallback promoted final_verified');

$childParty = ['adults'=>2,'children'=>1,'child_ages'=>[5]];
$childInput = AnyTourFunsunTurkeyFuelOwnerPolicyV1::forTarget(array_replace($target,['party'=>$childParty]),$fx,$now);
fs_ok(is_array($childInput),'child >=2 fallback missing');
fs_same(3,$childInput['policy']['eligible_passenger_count']??null,'child >=2 not eligible');
fs_same('420.00',$childInput['policy']['applied_native_total']??null,'child >=2 native total');

$infantParty = ['adults'=>2,'children'=>1,'child_ages'=>[1]];
fs_same(null,AnyTourFunsunTurkeyFuelOwnerPolicyV1::forTarget(array_replace($target,['party'=>$infantParty]),$fx,$now),'infant entered generic fallback');
fs_same(null,AnyTourFunsunTurkeyFuelOwnerPolicyV1::forTarget(array_replace($target,['operator'=>'Интурист']),$fx,$now),'fallback leaked to Intourist');
fs_same(null,AnyTourFunsunTurkeyFuelOwnerPolicyV1::forTarget(array_replace($target,['search_params'=>['departureId'=>1,'countryId'=>8]]),$fx,$now),'fallback leaked outside Turkey');
fs_same(null,AnyTourFunsunTurkeyFuelOwnerPolicyV1::forTarget($target,array_replace($fx,['expires_at'=>$now]),$now),'expired FX accepted');

$already = $dto;
$already['money']['fuel_charge_reported']=['amount'=>'1.00','currency'=>'RUB','source'=>'exact'];
$blocked = AnyTourFunsunTurkeyFuelOwnerPolicyV1::apply($already,$input,$now);
fs_same(false,$blocked['applied']??null,'fallback overwrote existing fuel');
fs_same('funsun_turkey_policy_existing_fuel',$blocked['reason']??null,'existing fuel precedence reason');

$tampered = $input;
$tampered['policy']['amount']='80.00';
$blocked = AnyTourFunsunTurkeyFuelOwnerPolicyV1::apply($dto,$tampered,$now);
fs_same(false,$blocked['applied']??null,'tampered owner policy applied');

$stale = $input;
$stale['policy']['exchange']['expires_at']=$now;
$blocked = AnyTourFunsunTurkeyFuelOwnerPolicyV1::apply($dto,$stale,$now);
fs_same(false,$blocked['applied']??null,'stale FX policy applied');

fwrite(STDOUT,"PASS FUN&SUN Turkey owner fallback: 70 EUR per eligible passenger one-way, fresh FX, estimate-only, infant separate\n");
