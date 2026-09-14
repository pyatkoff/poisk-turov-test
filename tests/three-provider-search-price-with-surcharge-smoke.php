<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/three-provider-money-facts.php';

function check_money(bool $ok,string $label): void { if(!$ok) throw new RuntimeException($label); }

$anex=AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate(
    AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'100000','currency'=>'RUB','source'=>'anex_search'],null,[
        ['kind'=>'fuel_adult','amount'=>'5000','currency'=>'RUB','source'=>'anex_additional'],
        ['kind'=>'fuel_child','amount'=>'3000','currency'=>'RUB','source'=>'anex_additional'],
    ]),
    2,
    1
);
check_money($anex['search_price_with_surcharge']['amount']==='113000','anex total');
check_money($anex['search_price']['amount']==='100000','base preserved');
check_money($anex['final_price_verified']===false,'not final');
check_money($anex['arithmetic_applied']===true,'arithmetic flag');

$andromeda=AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate(
    AnyTourThreeProviderMoneyFacts::fromSearch('andromeda',['amount'=>'124864','currency'=>'RUB','source'=>'andromeda_search'],null,[
        ['kind'=>'fuel_adult','amount'=>'5389.50','currency'=>'RUB','source'=>'andromeda_additional'],
    ]),
    2,
    0
);
check_money($andromeda['search_price_with_surcharge']['amount']==='135643.00','andromeda total');

$unknown=AnyTourThreeProviderMoneyFacts::fromSearch('anex',['amount'=>'100000','currency'=>'RUB','source'=>'anex_search']);
$thrown=false;
try { AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($unknown,2,0); } catch (DomainException $e) { $thrown=$e->getMessage()==='THREE_PROVIDER_SURCHARGE_UNKNOWN'; }
check_money($thrown,'unknown is not zero');

echo "ok\n";
