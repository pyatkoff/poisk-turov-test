<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/integrations/three-provider-money-facts.php';
require_once $root . '/app/integrations/three-provider-availability.php';
require_once $root . '/app/integrations/three-provider-flight-details.php';
require_once $root . '/app/integrations/three-provider-operator.php';
require_once $root . '/app/integrations/three-provider-offer-contract.php';
require_once $root . '/app/integrations/three-provider-offer-context.php';
require_once $root . '/app/integrations/three-provider-quote-envelope.php';
require_once $root . '/app/integrations/three-provider-search-handoff.php';
require_once $root . '/app/integrations/anytour-offer-snapshot-producer.php';

$checks = 0;
function producer_check(bool $ok, string $label): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('PRODUCER_CHECK_FAILED:' . $label);
}
function producer_reject(callable $fn, string $needle, string $label): void
{
    try { $fn(); } catch (Throwable $e) {
        producer_check(str_contains($e->getMessage(), $needle), $label . ':wrong:' . $e->getMessage());
        return;
    }
    throw new RuntimeException('PRODUCER_CHECK_FAILED:' . $label . ':no_error');
}
function producer_raw(string $provider, int $local, int $adults, array $additional, string $amount, string $salt): array
{
    return [
        'provider'=>$provider,
        'operator'=>$provider === 'anex' ? null : 'ANEX',
        'local_hotel_id'=>$local,
        'provider_hotel_ref'=>'private-'.$provider.'-hotel-'.$salt,
        'search_ref'=>'private-'.$provider.'-search-'.$salt,
        'offer_ref'=>'private-'.$provider.'-offer-'.$salt,
        'checkin'=>'2026-10-05','nights'=>7,'adults'=>$adults,'children'=>0,'child_ages'=>[],
        'meal'=>['raw'=>'AI','family'=>'ai','qualifiers'=>['plus'=>false,'without_alcohol'=>false]],
        'room'=>['raw'=>'Standard Room','normalized'=>'standard room'],
        'placement'=>$provider === 'andromeda' ? null : ['raw'=>'DBL','normalized'=>'dbl'],
        'availability'=>['hotel'=>null,'flight_outbound_economy'=>null,'flight_return_economy'=>null],
        'search_price'=>['amount'=>$amount,'currency'=>'RUB','source'=>$provider.'_search'],
        'fuel_charge_reported'=>null,
        'additional_prices_reported'=>$additional,
        'observed_at'=>'2026-09-17T06:00:00Z',
    ];
}
function producer_entry(string $provider, int $legacy, ?int $own, int $adults, string $base, string $fuel, string $salt, int $issued): array
{
    $additional=[['kind'=>'fuel_adult','amount'=>$fuel,'currency'=>'RUB','source'=>$provider.'_additional']];
    $offer=AnyTourThreeProviderOfferContract::fromSearch(producer_raw($provider,$legacy,$adults,$additional,$base,$salt));
    $retained=AnyTourThreeProviderOfferContext::retain($offer,41,1,$issued,900);
    $current=['provider'=>$retained['provider'],'operator'=>$retained['operator'],'local_hotel_id'=>$retained['local_hotel_id'],
        'identity'=>$retained['identity'],'generation'=>41,'page'=>1];
    $priced=AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($offer['money'],$adults,0);
    return ['anytour_hotel_id'=>$own,'offer'=>$offer,'retained'=>$retained,'current'=>$current,'priced_money'=>$priced];
}
function producer_params(): array
{
    return ['departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-05',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'',
        'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
        'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
}

$now=new DateTimeImmutable('2026-09-17T06:05:00Z');
$issued=$now->getTimestamp()-60;
$ingestCalls=[];
$ingest=function(string $provider,array $params,array $rows,DateTimeImmutable $at) use (&$ingestCalls): array {
    $ingestCalls[]=compact('provider','params','rows','at');
    return ['provider'=>$provider,'offerCount'=>count($rows),'selectionAuthority'=>false];
};

$anex=producer_entry('anex',3417,501,2,'100000','5000','anex-a',$issued);
$unresolved=producer_entry('anex',3418,null,2,'120000','4000','anex-b',$issued);
$notReady=$anex;$notReady['offer']=AnyTourThreeProviderOfferContract::fromSearch(producer_raw('anex',3419,2,[],'130000','anex-c'));
$notReady['retained']=AnyTourThreeProviderOfferContext::retain($notReady['offer'],41,1,$issued,900);
$notReady['current']=['provider'=>$notReady['retained']['provider'],'operator'=>$notReady['retained']['operator'],
    'local_hotel_id'=>$notReady['retained']['local_hotel_id'],'identity'=>$notReady['retained']['identity'],'generation'=>41,'page'=>1];
$notReady['priced_money']=null;$notReady['anytour_hotel_id']=503;

$result=AnyTourIntOfferSnapshotProducerV1::produce('anex',producer_params(),[
    'complete'=>true,'authoritative_empty'=>false,'offers'=>[$anex,$unresolved,$notReady],
],$now,$ingest);
producer_check($result['published']===true && $result['readyOfferCount']===1,'anex-published');
producer_check($result['unresolvedHotelCount']===1 && $result['notReadyCount']===1,'anex-skips');
producer_check(count($ingestCalls)===1 && $ingestCalls[0]['provider']==='anex','anex-ingest-once');
$row=$ingestCalls[0]['rows'][0];
producer_check($row['anytour_hotel_id']===501 && $row['dto']['finalPrice']==='110000','anex-existing-arithmetic');
producer_check($row['dto']['money']['search_price']['amount']==='100000'
    && $row['dto']['money']['search_price_with_surcharge']['amount']==='110000','anex-money-provenance');
producer_check($row['expires_at']===gmdate('Y-m-d\TH:i:s\Z',$anex['retained']['expires_at']),'retained-expiry');
producer_check(!str_contains(json_encode($row['dto'],JSON_THROW_ON_ERROR),'private-anex'),'browser-safe-dto');

$and=producer_entry('andromeda',4200,777,3,'144790','7185.60','and-a',$issued);
$andResult=AnyTourIntOfferSnapshotProducerV1::produce('andromeda',producer_params(),[
    'complete'=>true,'authoritative_empty'=>false,'offers'=>[$and],
],$now,$ingest);
producer_check($andResult['published']===true && $andResult['readyOfferCount']===1,'andromeda-published');
producer_check($ingestCalls[1]['rows'][0]['dto']['finalPrice']==='166346.80','andromeda-existing-arithmetic');
producer_check(count($ingestCalls)===2,'providers-independent-success');

$before=count($ingestCalls);
producer_reject(static fn()=>AnyTourIntOfferSnapshotProducerV1::produce('anex',producer_params(),[
    'complete'=>false,'authoritative_empty'=>false,'offers'=>[$anex],
],$now,static fn()=>[]),'REFRESH_INCOMPLETE','partial-refused');
producer_check(count($ingestCalls)===$before,'partial-no-ingest');

$onlyUnknown=$notReady;
$notPublished=AnyTourIntOfferSnapshotProducerV1::produce('anex',producer_params(),[
    'complete'=>true,'authoritative_empty'=>false,'offers'=>[$onlyUnknown],
],$now,$ingest);
producer_check($notPublished['published']===false && $notPublished['reason']==='no_final_price_ready_resolved_offers','all-not-ready-preserves-old');
producer_check(count($ingestCalls)===$before,'all-not-ready-no-ingest');

producer_reject(static fn()=>AnyTourIntOfferSnapshotProducerV1::produce('anex',producer_params(),[
    'complete'=>true,'authoritative_empty'=>false,'offers'=>[],
],$now,static fn()=>[]),'EMPTY_NOT_AUTHORITATIVE','empty-needs-authority');
$empty=AnyTourIntOfferSnapshotProducerV1::produce('anex',producer_params(),[
    'complete'=>true,'authoritative_empty'=>true,'offers'=>[],
],$now,$ingest);
producer_check($empty['published']===true && $empty['readyOfferCount']===0,'authoritative-empty-published');
producer_check(count($ingestCalls)===$before+1 && $ingestCalls[array_key_last($ingestCalls)]['rows']===[],'authoritative-empty-ingest');

$tampered=$anex;
$tampered['priced_money']['search_price_with_surcharge']['amount']='109999';
$before=count($ingestCalls);
producer_reject(static fn()=>AnyTourIntOfferSnapshotProducerV1::produce('anex',producer_params(),[
    'complete'=>true,'authoritative_empty'=>false,'offers'=>[$tampered],
],$now,static fn()=>[]),'THREE_PROVIDER_HANDOFF_PRICE','tampered-priced-money');
producer_check(count($ingestCalls)===$before,'tampered-no-ingest');

$duplicate=$anex;$duplicate['anytour_hotel_id']=999;
producer_reject(static fn()=>AnyTourIntOfferSnapshotProducerV1::produce('anex',producer_params(),[
    'complete'=>true,'authoritative_empty'=>false,'offers'=>[$anex,$duplicate],
],$now,static fn()=>[]),'DUPLICATE_IDENTITY','duplicate-fails-before-ingest');

$source=file_get_contents($root.'/app/integrations/anytour-offer-snapshot-producer.php');
producer_check(is_string($source) && !preg_match('/\b(?:curl_|file_get_contents\s*\(\s*[\'\"]https?:|fsockopen|stream_socket_client)\b/i',$source),'producer-no-transport');
producer_check(is_string($source) && !str_contains($source,'withSearchSurchargeEstimate('),'producer-no-price-formula');

echo 'AnyTour INT offer snapshot producer: '.$checks." checks passed; supplier=0 DB=0 mapping=0 booking=0.\n";
