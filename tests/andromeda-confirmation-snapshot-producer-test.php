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
    'checkin'=>'2026-09-19',
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
    'observed_at'=>'2026-09-19T08:00:00Z',
];
$offer=AnyTourThreeProviderOfferContract::fromSearch($raw);
$issued=(new DateTimeImmutable('2026-09-19T08:00:00Z'))->getTimestamp();
$retained=AnyTourThreeProviderOfferContext::retain($offer,17171903,1,$issued,900);
$current=[
    'provider'=>$retained['provider'],
    'operator'=>$retained['operator'],
    'local_hotel_id'=>$retained['local_hotel_id'],
    'identity'=>$retained['identity'],
    'generation'=>17171903,
    'page'=>1,
];
$entry=[
    'anytour_hotel_id'=>777,
    'offer'=>$offer,
    'retained'=>$retained,
    'current'=>$current,
    'priced_money'=>null,
];
$params=[
    'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-09-19','dateTo'=>'2026-09-22',
    'nightsFrom'=>7,'nightsTo'=>10,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'',
    'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
    'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
];
$ingested=[];
$result=AnyTourIntOfferSnapshotProducerV1::produce(
    'andromeda',
    $params,
    ['complete'=>true,'authoritative_empty'=>false,'offers'=>[$entry]],
    new DateTimeImmutable('2026-09-19T08:05:00Z'),
    static function(string $provider,array $search,array $rows,DateTimeImmutable $now)use(&$ingested):array{
        $ingested=compact('provider','search','rows','now');
        return ['provider'=>$provider,'offerCount'=>count($rows),'selectionAuthority'=>false];
    }
);

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

echo "Andromeda confirmation snapshot: PASS; supplier=0 DB=0 booking=0.\n";
