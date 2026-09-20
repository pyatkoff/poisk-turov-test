<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/integrations/three-provider-offer-contract.php';
require_once $root.'/app/integrations/three-provider-offer-context.php';
require_once $root.'/app/integrations/anytour-offer-snapshot-producer.php';

function confirm_check(bool $ok,string $label):void{
    if(!$ok) throw new RuntimeException('ANDROMEDA_CONFIRMATION_CHECK:'.$label);
}

$raw=[
    'provider'=>'andromeda',
    'operator'=>'Библио-Глобус',
    'local_hotel_id'=>4200,
    'provider_hotel_ref'=>'operator_115:synthetic-hotel',
    'search_ref'=>'synthetic-search',
    'offer_ref'=>'synthetic-offer',
    'checkin'=>'2026-09-20',
    'nights'=>7,
    'adults'=>2,
    'children'=>0,
    'child_ages'=>[],
    'meal'=>['raw'=>'AI','family'=>'ai','qualifiers'=>['plus'=>false,'without_alcohol'=>false]],
    'room'=>['raw'=>'Standard Room','normalized'=>'standard room'],
    'placement'=>null,
    'availability'=>['hotel'=>null,'flight_outbound_economy'=>null,'flight_return_economy'=>null],
    'search_price'=>['amount'=>'144790','currency'=>'RUB','source'=>'andromeda_search'],
    'fuel_charge_reported'=>null,
    'additional_prices_reported'=>[],
    'observed_at'=>'2026-09-20T12:00:00Z',
];
$params=[
    'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-09-20','dateTo'=>'2026-09-23',
    'nightsFrom'=>7,'nightsTo'=>10,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'',
    'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
    'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
];
$issued=(new DateTimeImmutable('2026-09-20T12:00:00Z'))->getTimestamp();
$now=new DateTimeImmutable('2026-09-20T12:05:00Z');
$makeEntry=static function(array $offer,int $generation,?array $pricedMoney):array use($issued){
    $retained=AnyTourThreeProviderOfferContext::retain($offer,$generation,1,$issued,900);
    $current=[
        'provider'=>$retained['provider'],'operator'=>$retained['operator'],'local_hotel_id'=>$retained['local_hotel_id'],
        'identity'=>$retained['identity'],'generation'=>$generation,'page'=>1,
    ];
    return ['anytour_hotel_id'=>777,'offer'=>$offer,'retained'=>$retained,'current'=>$current,'priced_money'=>$pricedMoney];
};
$produce=static function(array $entry)use($params,$now):array{
    $ingested=[];
    $result=AnyTourIntOfferSnapshotProducerV1::produce(
        'andromeda',$params,['complete'=>true,'authoritative_empty'=>false,'offers'=>[$entry]],$now,
        static function(string $provider,array $search,array $rows,DateTimeImmutable $at)use(&$ingested):array{
            $ingested=compact('provider','search','rows','at');
            return ['provider'=>$provider,'offerCount'=>count($rows),'selectionAuthority'=>false];
        }
    );
    return [$result,$ingested];
};

$offer=AnyTourThreeProviderOfferContract::fromSearch($raw);
[$result,$ingested]=$produce($makeEntry($offer,17172003,null));
confirm_check($result['published']===true,'published');
confirm_check($result['readyOfferCount']===0,'not-final-ready');
confirm_check($result['confirmationRequiredOfferCount']===1,'confirmation-count');
confirm_check($result['notReadyCount']===0,'not-dropped');
confirm_check(($ingested['provider']??null)==='andromeda'&&count($ingested['rows']??[])===1,'ingest-one');
$dto=$ingested['rows'][0]['dto'];
confirm_check($dto['provider']==='andromeda','provider');
confirm_check($dto['finalPriceReady']===false&&$dto['finalPrice']===null,'no-final-authority');
confirm_check($dto['price']==='144790'&&$dto['currency']==='RUB','supplier-search-price');
confirm_check($dto['quote_state']==='unknown'&&$dto['final_price_verified']===false,'quote-unverified');
confirm_check($dto['selection_state']==='disabled'&&$dto['booking_enabled']===false,'no-selection-booking');
confirm_check($dto['money']['fuel_charge_reported']===null,'fuel-unknown-preserved');
confirm_check($dto['money']['additional_prices_reported']===[],'no-invented-surcharge');

// Historical get_flights transport evidence is weak estimate evidence only. It must
// still validate exactly, but its presence must not hide a mapped offer or leak the
// derived surcharge into canonical customer money.
$estimatedRaw=$raw;
$estimatedRaw['additional_prices_reported']=[[
    'kind'=>'party_transport_surcharge','amount'=>'14265','currency'=>'RUB','source'=>'andromeda_get_flights_transport',
]];
$estimatedOffer=AnyTourThreeProviderOfferContract::fromSearch($estimatedRaw);
$estimatedMoney=AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($estimatedOffer['money'],2,0);
confirm_check(($estimatedMoney['search_price_with_surcharge']['amount']??null)==='159055','historical-estimate-fixture');
[$estimatedResult,$estimatedIngested]=$produce($makeEntry($estimatedOffer,17172004,$estimatedMoney));
confirm_check($estimatedResult['published']===true,'estimate-published-as-confirmation');
confirm_check($estimatedResult['readyOfferCount']===0&&$estimatedResult['confirmationRequiredOfferCount']===1,'estimate-not-promoted');
confirm_check($estimatedResult['notReadyCount']===0,'estimate-not-dropped');
$estimatedDto=$estimatedIngested['rows'][0]['dto'];
confirm_check($estimatedDto['finalPriceReady']===false&&$estimatedDto['finalPrice']===null,'estimate-no-final-authority');
confirm_check($estimatedDto['price']==='144790'&&$estimatedDto['currency']==='RUB','estimate-keeps-search-price');
confirm_check($estimatedDto['money']['fuel_charge_reported']===null,'estimate-fuel-remains-unknown');
confirm_check($estimatedDto['money']['additional_prices_reported']===[],'get-flights-money-not-persisted');
confirm_check(!array_key_exists('search_price_with_surcharge',$estimatedDto['money']),'derived-estimate-not-persisted');

$tampered=$estimatedMoney;
$tampered['search_price_with_surcharge']['amount']='159056';
$rejected=false;
try{$produce($makeEntry($estimatedOffer,17172005,$tampered));}
catch(InvalidArgumentException){$rejected=true;}
confirm_check($rejected,'tampered-estimate-still-fails-closed');

echo "Andromeda confirmation snapshot: PASS; plain=1 historical_estimate=1 tamper=1 supplier=0 DB=0 booking=0.\n";
