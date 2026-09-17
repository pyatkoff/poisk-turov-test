<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/search3-local-offers-write-v1.php';
function ok(bool $value,string $label):void{if(!$value)throw new RuntimeException('CHECK_FAILED:'.$label);}
function bad(callable $fn,string $needle,string $label):void{try{$fn();}catch(Throwable $e){ok(str_contains($e->getMessage(),$needle),$label.':'.$e->getMessage());return;}throw new RuntimeException('CHECK_FAILED:'.$label.':no_error');}
$params=['departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-10-05','dateTo'=>'2026-10-07','nightsFrom'=>'7','nightsTo'=>'9','adults'=>'2','childs'=>[7],'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>'false','onlyDirect'=>'false'];
$scope=AnyTourSearchScopeV1::fromParams($params)['params'];$now=new DateTimeImmutable('2026-09-17T02:20:00Z');
$offer=['legacyHotelId'=>101,'searchRef'=>str_repeat('a',32),'offerRef'=>'anex_online:'.str_repeat('b',64),'checkin'=>'2026-10-05','nights'=>7,'meal'=>'AI','room'=>'STANDARD ROOM','placement'=>'2AD+1CHD','price'=>'199390','currency'=>'RUB'];
$dto=search3_local_anex_offer_dto($offer,$scope,3,$now);
ok($dto['provider']==='anex','provider');ok($dto['local_hotel_id']===101,'legacy');ok($dto['operator']['canonical_name']==='ANEX'&&$dto['operator']['canonical_verified']===true,'operator');ok($dto['money']['source']==='anex_apd_applied','apd-source');ok($dto['finalPriceReady']===true&&$dto['finalPrice']==='199390'&&$dto['price']==='199390','final-price');ok($dto['selection_state']==='disabled'&&$dto['booking_enabled']===false,'no-selection-authority');ok($dto['tour']['party']===['adults'=>2,'children'=>1,'child_ages'=>[7]],'party-from-scope');ok($dto['tour']['observed_at']==='2026-09-17T02:20:00Z','observed');
$m=new ReflectionMethod(AnyTourOfferStoreV1::class,'validateDto');$validated=$m->invoke(null,$dto);ok($validated['provider']==='anex'&&$validated['legacy_hotel_id']===101&&$validated['price']==='199390','store-contract');
$bad=$offer;$bad['checkin']='2026-10-08';bad(fn()=>search3_local_anex_offer_dto($bad,$scope,3,$now),'ANYTOUR_LOCAL_OFFER_DATE','date-outside-scope');
$bad=$offer;$bad['price']='185125+14265';bad(fn()=>search3_local_anex_offer_dto($bad,$scope,3,$now),'ANYTOUR_LOCAL_OFFER_PRICE','no-price-arithmetic');
$bad=$offer;$bad['offerRef']='guess';bad(fn()=>search3_local_anex_offer_dto($bad,$scope,3,$now),'ANYTOUR_LOCAL_OFFER_REF','no-guessed-offer');
echo "SEARCH3_LOCAL_OFFERS_WRITE_OK dto=1 store_contract=1 fail_closed=3 final=199390\n";
