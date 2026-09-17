<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-money-facts.php';

$checks=0;
function money_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('money_check_'.$checks); }

$tv=AnyTourThreeProviderMoneyFacts::fromSearch(
    'tourvisor',
    ['amount'=>'150824','currency'=>'RUB','source'=>'tourvisor_search'],
    ['amount'=>'31710','currency'=>'RUB','source'=>'tourvisor_fuel']
);
money_check($tv['provider']==='tourvisor');
money_check($tv['search_price']['amount']==='150824');
money_check($tv['fuel_charge_reported']['amount']==='31710');
money_check($tv['package_buyer_price']===null&&$tv['quote_price']===null);
money_check($tv['search_price_fuel_relation']==='unknown');
money_check($tv['final_price_verified']===false&&$tv['arithmetic_applied']===false);
money_check(!array_key_exists('display_price',$tv)&&!array_key_exists('total_price',$tv));
try {
    AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($tv,2,0);
    money_check(false);
} catch (InvalidArgumentException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_SURCHARGE_CAPABILITY');
}

$anex=AnyTourThreeProviderMoneyFacts::fromSearch(
    'anex',
    ['amount'=>'119114','currency'=>'RUB','source'=>'anex_search'],
    null,
    [
        ['kind'=>'fuel_surcharge','amount'=>'31710','currency'=>'RUB','source'=>'anex_additional'],
        ['kind'=>'other_fee','amount'=>'0','currency'=>'RUB','source'=>'anex_additional'],
    ]
);
money_check($anex['search_price']['amount']==='119114');
money_check($anex['fuel_charge_reported']===null);
money_check(count($anex['additional_prices_reported'])===2);
money_check($anex['additional_prices_reported'][0]['kind']==='fuel_surcharge');
money_check($anex['additional_prices_reported'][0]['amount']==='31710');
money_check($anex['additional_prices_reported'][1]['amount']==='0');
money_check($anex['arithmetic_applied']===false);

$anexParty=AnyTourThreeProviderMoneyFacts::fromSearch(
    'anex',
    ['amount'=>'100000','currency'=>'RUB','source'=>'anex_search'],
    null,
    [
        ['kind'=>'fuel_adult','amount'=>'5000','currency'=>'RUB','source'=>'anex_additional'],
        ['kind'=>'fuel_child','amount'=>'3000','currency'=>'RUB','source'=>'anex_additional'],
    ]
);
$anexEstimate=AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($anexParty,2,1);
money_check($anexEstimate['search_price_with_surcharge']['amount']==='113000');
money_check($anexEstimate['search_price_with_surcharge']['currency']==='RUB');
money_check($anexEstimate['search_price_with_surcharge']['source']==='derived_search_estimate');
money_check($anexEstimate['search_price']===$anexParty['search_price']);
money_check($anexEstimate['additional_prices_reported']===$anexParty['additional_prices_reported']);
money_check($anexEstimate['final_price_verified']===false);
money_check($anexEstimate['arithmetic_applied']===true);
money_check($anexParty['arithmetic_applied']===false&&!array_key_exists('search_price_with_surcharge',$anexParty));

$andromeda=AnyTourThreeProviderMoneyFacts::fromSearch(
    'andromeda',
    ['amount'=>'119114','currency'=>'RUB','source'=>'andromeda_search']
);
money_check($andromeda['fuel_charge_reported']===null);
money_check($andromeda['package_buyer_price']===null);
money_check($andromeda['quote_price']===null);
money_check($andromeda['final_price_verified']===false);

// Andromeda get_flights markup already covers the selected tourist party. Supplier
// provenance is accepted at the boundary, then canonicalized to provider-neutral
// `andromeda_additional`; the amount is added once regardless of party size.
$andromedaSurcharge=AnyTourThreeProviderMoneyFacts::fromSearch(
    'andromeda',
    ['amount'=>'124864','currency'=>'RUB','source'=>'andromeda_search'],
    null,
    [['kind'=>'party_transport_surcharge','amount'=>'10779','currency'=>'RUB','source'=>'andromeda_get_flights_transport']]
);
money_check($andromedaSurcharge['additional_prices_reported'][0]['source']==='andromeda_additional');
$andromedaEstimate=AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($andromedaSurcharge,2,0);
money_check($andromedaEstimate['search_price_with_surcharge']['amount']==='135643');
money_check($andromedaEstimate['search_price']['amount']==='124864');
money_check($andromedaEstimate['final_price_verified']===false&&$andromedaEstimate['arithmetic_applied']===true);
$andromedaDifferentParty=AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($andromedaSurcharge,5,3);
money_check($andromedaDifferentParty['search_price_with_surcharge']['amount']==='135643');

try {
    AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($andromeda,2,0);
    money_check(false);
} catch (DomainException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_SURCHARGE_UNKNOWN');
}
$missingChild=AnyTourThreeProviderMoneyFacts::fromSearch(
    'anex',
    ['amount'=>'100000','currency'=>'RUB','source'=>'anex_search'],
    null,
    [['kind'=>'fuel_adult','amount'=>'5000','currency'=>'RUB','source'=>'anex_additional']]
);
try {
    AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($missingChild,2,1);
    money_check(false);
} catch (DomainException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_SURCHARGE_UNKNOWN');
}
$duplicateAdult=AnyTourThreeProviderMoneyFacts::fromSearch(
    'anex',
    ['amount'=>'100000','currency'=>'RUB','source'=>'anex_search'],
    null,
    [
        ['kind'=>'fuel_adult','amount'=>'5000','currency'=>'RUB','source'=>'anex_additional'],
        ['kind'=>'fuel_adult','amount'=>'6000','currency'=>'RUB','source'=>'anex_additional'],
    ]
);
try {
    AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($duplicateAdult,2,0);
    money_check(false);
} catch (DomainException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_SURCHARGE_UNKNOWN');
}
$wrongCurrency=AnyTourThreeProviderMoneyFacts::fromSearch(
    'andromeda',
    ['amount'=>'100000','currency'=>'RUB','source'=>'andromeda_search'],
    null,
    [['kind'=>'party_transport_surcharge','amount'=>'50','currency'=>'USD','source'=>'andromeda_additional']]
);
try {
    AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($wrongCurrency,2,0);
    money_check(false);
} catch (DomainException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_SURCHARGE_UNKNOWN');
}

$quoted=AnyTourThreeProviderMoneyFacts::withVerifiedQuote(
    $andromeda,
    ['amount'=>'124864','currency'=>'RUB','source'=>'andromeda_package'],
    ['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote']
);
money_check($quoted['search_price']===$andromeda['search_price']);
money_check($quoted['package_buyer_price']['amount']==='124864');
money_check($quoted['quote_price']['amount']==='135643');
money_check($quoted['final_price_verified']===true);
money_check($quoted['search_price_fuel_relation']==='unknown');
money_check($quoted['arithmetic_applied']===false);
money_check($andromeda['package_buyer_price']===null&&$andromeda['quote_price']===null);
money_check(!array_key_exists('delta',$quoted)&&!array_key_exists('total_price',$quoted));

$quotedNoPackage=AnyTourThreeProviderMoneyFacts::withVerifiedQuote(
    $andromeda,
    null,
    ['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote']
);
money_check($quotedNoPackage['package_buyer_price']===null);
money_check($quotedNoPackage['quote_price']['amount']==='135643');
money_check($quotedNoPackage['final_price_verified']===true);

foreach ([$tv,$anex] as $unsupported) {
    try {
        AnyTourThreeProviderMoneyFacts::withVerifiedQuote(
            $unsupported,
            null,
            ['amount'=>'1','currency'=>'RUB','source'=>$unsupported['provider'].'_quote']
        );
        money_check(false);
    } catch (InvalidArgumentException $e) {
        money_check($e->getMessage()==='THREE_PROVIDER_MONEY_QUOTE_CAPABILITY');
    }
}

$badQuoteCases=[
    fn()=>AnyTourThreeProviderMoneyFacts::withVerifiedQuote($andromeda,['amount'=>'124864','currency'=>'RUB','source'=>'andromeda_quote'],['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote']),
    fn()=>AnyTourThreeProviderMoneyFacts::withVerifiedQuote($andromeda,['amount'=>'0','currency'=>'RUB','source'=>'andromeda_package'],['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote']),
    fn()=>AnyTourThreeProviderMoneyFacts::withVerifiedQuote($andromeda,null,['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_package']),
    fn()=>AnyTourThreeProviderMoneyFacts::withVerifiedQuote($andromeda,null,['amount'=>'0','currency'=>'RUB','source'=>'andromeda_quote']),
    fn()=>AnyTourThreeProviderMoneyFacts::withVerifiedQuote($andromeda,null,['amount'=>'135643.001','currency'=>'RUB','source'=>'andromeda_quote']),
    fn()=>AnyTourThreeProviderMoneyFacts::withVerifiedQuote($andromeda,null,['amount'=>'135643','currency'=>'rub','source'=>'andromeda_quote']),
    fn()=>AnyTourThreeProviderMoneyFacts::withVerifiedQuote($andromeda,null,['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote','delta'=>'10779']),
];
foreach($badQuoteCases as $case){try{$case();money_check(false);}catch(InvalidArgumentException $e){money_check(true);}}

$tamperedSearch=$andromeda;
$tamperedSearch['final_price_verified']=true;
try {
    AnyTourThreeProviderMoneyFacts::withVerifiedQuote($tamperedSearch,null,['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote']);
    money_check(false);
} catch (InvalidArgumentException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_MONEY_SEARCH_STATE');
}
$tamperedSearch=$andromeda;
$tamperedSearch['search_price']['source']='tourvisor_search';
try {
    AnyTourThreeProviderMoneyFacts::withVerifiedQuote($tamperedSearch,null,['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote']);
    money_check(false);
} catch (InvalidArgumentException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_MONEY_SEARCH_STATE');
}
$tamperedSearch=$andromeda;
$tamperedSearch['unexpected']='x';
try {
    AnyTourThreeProviderMoneyFacts::withVerifiedQuote($tamperedSearch,null,['amount'=>'135643','currency'=>'RUB','source'=>'andromeda_quote']);
    money_check(false);
} catch (InvalidArgumentException $e) {
    money_check($e->getMessage()==='THREE_PROVIDER_MONEY_SEARCH_STATE');
}

$zeroFuel=AnyTourThreeProviderMoneyFacts::fromSearch(
    'tourvisor',
    ['amount'=>'100000','currency'=>'RUB','source'=>'tourvisor_search'],
    ['amount'=>'0','currency'=>'RUB','source'=>'tourvisor_fuel']
);
money_check($zeroFuel['fuel_charge_reported']['amount']==='0');

$badCases=[
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('other',['amount'=>'1','currency'=>'RUB','source'=>'other_search']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'0','currency'=>'RUB','source'=>'anex_search']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1.234','currency'=>'RUB','source'=>'anex_search']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>1,'currency'=>'RUB','source'=>'anex_search']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'rub','source'=>'anex_search']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'tourvisor_search']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_unverified_search']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search_replayed']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search','supplier_offer_id'=>'PRIVATE']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],['amount'=>'1','currency'=>'RUB','source'=>'anex_additional']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],['amount'=>'1','currency'=>'RUB','source'=>'anex_fuel']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('andromeda',['amount'=>'1','currency'=>'RUB','source'=>'andromeda_search'],['amount'=>'1','currency'=>'RUB','source'=>'andromeda_fuel']),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],null,[['kind'=>'BAD-KIND','amount'=>'1','currency'=>'RUB','source'=>'anex_additional']]),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],null,[['kind'=>'fee','amount'=>'1','currency'=>'RUB','source'=>'tourvisor_additional']]),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],null,[['kind'=>'fee','amount'=>'1','currency'=>'RUB','source'=>'anex_additional_estimated']]),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('tourvisor',['amount'=>'1','currency'=>'RUB','source'=>'tourvisor_search'],null,[['kind'=>'fee','amount'=>'1','currency'=>'RUB','source'=>'tourvisor_additional']]),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('andromeda',['amount'=>'1','currency'=>'RUB','source'=>'andromeda_search'],null,[['kind'=>'fuel_adult','amount'=>'1','currency'=>'RUB','source'=>'andromeda_additional']]),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('andromeda',['amount'=>'1','currency'=>'RUB','source'=>'andromeda_search'],null,[['kind'=>'party_transport_surcharge','amount'=>'1','currency'=>'RUB','source'=>'andromeda_unverified']]),
];
foreach($badCases as $case){try{$case();money_check(false);}catch(InvalidArgumentException $e){money_check(true);}}

$original=['amount'=>'150824','currency'=>'RUB','source'=>'tourvisor_search'];
$copy=$original;AnyTourThreeProviderMoneyFacts::fromSearch('tourvisor',$original);
money_check($original===$copy);

echo 'Three-provider money facts: '.$checks." checks passed; supplier/DB=0.\n";
