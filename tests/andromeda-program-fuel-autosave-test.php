<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/integrations/operator-program-fuel-registry.php';
require_once $root.'/app/integrations/andromeda-surcharge-cache-autosave.php';
require_once $root.'/app/integrations/three-provider-offer-contract.php';
require_once $root.'/app/integrations/three-provider-offer-context.php';
require_once $root.'/app/integrations/anytour-offer-snapshot-producer.php';

function pfa_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('PROGRAM_FUEL_AUTOSAVE:'.$label);}
function pfa_write(string $path,array $value):bool{
    $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return file_put_contents($path,$json,LOCK_EX)===strlen($json);
}
function pfa_obs(string $offer,string $evidence,int $at,string $rate):array{
    return [
        'key'=>['operator_family'=>'intourist','program_key'=>'30','tour_key'=>'34'],
        'unit'=>'per_person_one_way','amount'=>'85','currency'=>'EUR','direction_count'=>2,
        'base_relation'=>'excluded',
        'flight_pair'=>['outbound'=>['flight'=>'TK 3003'],'return'=>['flight'=>'TK 3006']],
        'offer_ref_digest'=>hash('sha256',$offer),'evidence_sha256'=>hash('sha256',$evidence),
        'source'=>'andromeda_get_flights','observed_at'=>$at,'expires_at'=>$at+86400,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>$rate,'observed_at'=>$at,'expires_at'=>$at+86400,
            'evidence_sha256'=>hash('sha256','fx-'.$evidence)],
    ];
}
function pfa_raw_offer():array{
    return [
        'provider'=>'andromeda','operator'=>'Intourist','operator_ref'=>'342',
        'offer_ref'=>'offer_'.str_repeat('c',64),
        'external_hotel_id'=>'2000001','supplier_namespace'=>'operator_342','local_hotel_id'=>4200,
        'room_raw'=>'Standard Room','room'=>'Standard Room','placement_raw'=>'DBL','placement'=>'DBL',
        'meal'=>['raw_label'=>'AI','label'=>'AI'],
        'check_in'=>'2026-10-11','nights'=>7,'adults'=>2,'children'=>0,
        'price'=>['amount'=>'98415','currency'=>'RUB','kind'=>'offer','fees'=>'unknown','final'=>false],
        'transport_context'=>[
            'program_ref'=>'30','program_label'=>'Промо Цена',
            'tour_ref'=>'34','tour_label'=>'[TR] Анталья Чартер/MOW-AYT',
            'spo_ref'=>'20531660','spo_label'=>'MOW-26837-AYT PROMO','freight_external'=>false,
        ],
    ];
}

$tmp=sys_get_temp_dir().'/program-fuel-autosave-'.bin2hex(random_bytes(6));
$directory=$tmp.'/searches';
mkdir($directory,0700,true);
try{
    $now=1800001200;
    AnyTourOperatorProgramFuelRegistryV1::append($directory,pfa_obs('offer-a','evidence-a',1800000000,'99.26'),'pfa_write');
    AnyTourOperatorProgramFuelRegistryV1::append($directory,pfa_obs('offer-b','evidence-b',1800000100,'99.49'),'pfa_write');

    $params=['departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-11','dateTo'=>'2026-10-11',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'',
        'hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
        'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
    $raw=pfa_raw_offer();

    $resolved=AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null,$raw,$params,$directory,$now);
    pfa_check(($resolved['state']??null)==='program_fuel','program rule not selected');
    $program=$resolved['program_fuel']??null;
    pfa_check(is_array($program),'program envelope missing');
    pfa_check(($program['program_rule']['amount']??null)==='85.00','85 EUR normalized');
    pfa_check(($program['program_rule']['applied_native_total']??null)==='340.00','2 adults x 2 directions');
    pfa_check(($program['program_rule']['exchange']['rate']??null)==='99.49','latest FX');
    pfa_check(($program['party_surcharge']['amount']??null)==='33826.60','RUB surcharge');
    pfa_check(($program['search_price_with_surcharge']['amount']??null)==='132241.60','program total');

    $exact=['state'=>'verified','fact'=>null,'verified_quote'=>['final_price'=>['amount'=>'130000','currency'=>'RUB']]];
    pfa_check(AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($exact,$raw,$params,$directory,$now)===$exact,
        'exact must outrank program rule');

    $offer=AnyTourThreeProviderOfferContract::fromSearch([
        'provider'=>'andromeda','operator'=>'Intourist','local_hotel_id'=>4200,
        'provider_hotel_ref'=>'operator_342:2000001','search_ref'=>'program-fuel-search',
        'offer_ref'=>$raw['offer_ref'],'checkin'=>'2026-10-11','nights'=>7,'adults'=>2,'children'=>0,'child_ages'=>[],
        'meal'=>['raw'=>'AI','family'=>'ai','qualifiers'=>['plus'=>false,'without_alcohol'=>false]],
        'room'=>['raw'=>'Standard Room','normalized'=>'standard room'],
        'placement'=>['raw'=>'DBL','normalized'=>'dbl'],
        'availability'=>['hotel'=>null,'flight_outbound_economy'=>null,'flight_return_economy'=>null],
        'search_price'=>['amount'=>'98415','currency'=>'RUB','source'=>'andromeda_search'],
        'fuel_charge_reported'=>null,'additional_prices_reported'=>[],
        'observed_at'=>'2027-01-15T08:00:00Z',
    ]);
    $issued=(new DateTimeImmutable('2027-01-15T08:00:00Z'))->getTimestamp();
    $retained=AnyTourThreeProviderOfferContext::retain($offer,71,1,$issued,900);
    $current=['provider'=>$retained['provider'],'operator'=>$retained['operator'],'local_hotel_id'=>$retained['local_hotel_id'],
        'identity'=>$retained['identity'],'generation'=>71,'page'=>1];
    $entry=['anytour_hotel_id'=>777,'offer'=>$offer,'retained'=>$retained,'current'=>$current,
        'priced_money'=>null,'program_fuel'=>$program];

    $ingested=[];
    $producerNow=new DateTimeImmutable('@'.($issued+60));
    $result=AnyTourIntOfferSnapshotProducerV1::produce('andromeda',$params,
        ['complete'=>true,'authoritative_empty'=>false,'offers'=>[$entry]],$producerNow,
        static function(string $provider,array $search,array $rows,DateTimeImmutable $at)use(&$ingested):array{
            $ingested=$rows;return ['provider'=>$provider,'offerCount'=>count($rows),'selectionAuthority'=>false];
        });
    pfa_check($result['published']===true,'producer did not publish');
    pfa_check($result['readyOfferCount']===1&&$result['confirmationRequiredOfferCount']===0,'program offer not ready');
    pfa_check(($result['operatorProgramFuel']['appliedOfferCount']??null)===1,'program receipt');
    $dto=$ingested[0]['dto']??null;
    pfa_check(is_array($dto)&&$dto['finalPriceReady']===true,'dto not ready');
    pfa_check($dto['final_price_verified']===false,'reusable rule became verified quote');
    pfa_check($dto['finalPrice']==='132241.60'&&$dto['price']==='132241.60','dto final estimate');
    pfa_check(($dto['money']['fuel_charge_reported']['amount']??null)==='33826.60','dto fuel');
    pfa_check(($dto['money']['operator_program_fuel_rule']['amount']??null)==='85.00','dto normalized rule');
    pfa_check(($dto['money']['operator_program_fuel_rule']['flight_pair']['outbound']['flight']??null)==='TK 3003','outbound flight');
    pfa_check(($dto['money']['operator_program_fuel_rule']['flight_pair']['return']['flight']??null)==='TK 3006','return flight');
    pfa_check(($dto['selection_state']??null)==='disabled'&&($dto['booking_enabled']??null)===false,'no booking authority');

    echo "ANDROMEDA_PROGRAM_FUEL_AUTOSAVE_OK rate=85EUR pax=2 directions=2 ready=1 verified=0\n";
}finally{
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
    if(is_dir($tmp))rmdir($tmp);
}
