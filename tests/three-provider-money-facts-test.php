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

$andromeda=AnyTourThreeProviderMoneyFacts::fromSearch(
    'andromeda',
    ['amount'=>'119114','currency'=>'RUB','source'=>'andromeda_search']
);
money_check($andromeda['fuel_charge_reported']===null);
money_check($andromeda['package_buyer_price']===null);
money_check($andromeda['quote_price']===null);
money_check($andromeda['final_price_verified']===false);

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
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],null,[['kind'=>'BAD-KIND','amount'=>'1','currency'=>'RUB','source'=>'anex_additional']]),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],null,[['kind'=>'fee','amount'=>'1','currency'=>'RUB','source'=>'tourvisor_additional']]),
    fn()=>AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'1','currency'=>'RUB','source'=>'anex_search'],null,[['kind'=>'fee','amount'=>'1','currency'=>'RUB','source'=>'anex_additional_estimated']]),
];
foreach($badCases as $case){try{$case();money_check(false);}catch(InvalidArgumentException $e){money_check(true);}}

$original=['amount'=>'150824','currency'=>'RUB','source'=>'tourvisor_search'];
$copy=$original;AnyTourThreeProviderMoneyFacts::fromSearch('tourvisor',$original);
money_check($original===$copy);

echo 'Three-provider money facts: '.$checks." checks passed; arithmetic/supplier/DB=0.\n";
