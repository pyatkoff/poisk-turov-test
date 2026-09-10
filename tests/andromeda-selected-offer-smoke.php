<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/andromeda-selected-offer.php';

$checks = 0;
$ok = static function (bool $value) use (&$checks): void {
    ++$checks;
    if (!$value) throw new RuntimeException('CHECK_' . $checks);
};
$fails = static function (callable $call, string $expected) use ($ok): void {
    try { $call(); }
    catch (RuntimeException $error) { $ok($error->getMessage() === $expected); return; }
    throw new RuntimeException('EXPECTED_' . $expected);
};

$criteria = ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260918','CHECKIN_END'=>'20260918',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>8,'NIGHTS_TILL'=>8,'CURRENCYINC'=>643,'PAGE'=>2,'HOTELS'=>'3414'];
$rawId = 'private-supplier-offer';
$row = ['id'=>$rawId,'hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>123456,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'18.09.2026','nights'=>'8',
    'hotel'=>'Fixture hotel','operator'=>'Fixture operator','meal'=>'AI','mealKey'=>6,
    'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$resolver = AnyTourAndromedaHotelResolver::fromRows([['supplier_namespace'=>'andromeda_catalog',
    'external_hotel_id'=>'3414','decision_status'=>'accepted','catalog_hotel_id'=>'900',
    'existing_catalog_hotel_id'=>'900']], str_repeat('b', 64));
$state = [];
$store = new AnyTourAndromedaOfferStore($state, true);
$store->begin('saved_search', 1, 1000);
$page = $store->capture(['PAGE'=>2,'PAGES_COUNT'=>3,'PRICES'=>[$row]], $criteria, 'saved_search', 1, 1001, $resolver);
$context = ['provider'=>'andromeda','search_ref'=>'saved_search','generation'=>1,'page'=>2,
    'offer_ref'=>$page['offers'][0]['offer_ref'],'hotel_scope'=>'3414','operator_ref'=>'5'];
$allowed = static fn(array $offer): bool => $offer['local_hotel_id'] === 900;

$public = AnyTourAndromedaSelectedOffer::publicSelection($store, $context, $allowed, 1002);
$ok($public['local_id'] === 900 && $public['operator_ref'] === '5' && $public['page'] === 2);
$ok($public['tour']['room'] === 'Standard' && $public['tour']['placement'] === 'DBL');
$ok($public['tour']['price']['final'] === false && $public['quote_status'] === 'unverified');
$json = json_encode($public, JSON_THROW_ON_ERROR);
$ok(strpos($json, $rawId) === false && strpos($json, 'supplier_offer') === false);
$fails(fn() => AnyTourAndromedaSelectedOffer::publicSelection($store, $context, static fn() => false, 1002),
    'ANDROMEDA_SELECTION_MAPPING_UNAVAILABLE');
$bad = $context; $bad['operator_ref'] = '7';
$fails(fn() => AnyTourAndromedaSelectedOffer::publicSelection($store, $bad, $allowed, 1002),
    'ANDROMEDA_SELECTION_CONTEXT_MISMATCH');
$fails(fn() => AnyTourAndromedaSelectedOffer::publicSelection($store, $context, $allowed, 1900), 'EXPIRED_SEARCH');

echo "Andromeda selected offer: $checks checks passed\n";
