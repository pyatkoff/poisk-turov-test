<?php
declare(strict_types=1);
require $argv[1].'/v2/api-andromeda-search3-preview.php';
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

$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE catalog_hotels(id INTEGER,is_active INTEGER,country_id INTEGER)');
$pdo->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace TEXT,external_hotel_id TEXT,local_hotel_id INTEGER,decision_status TEXT)');
$pdo->exec("INSERT INTO catalog_hotels VALUES(900,1,4),(901,1,4)");
$pdo->exec("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog','3414',900,'accepted')");
$saved=['status'=>'complete','store'=>$state];
$read=static fn()=>anytour_andromeda_search3_detail_selection($saved,$context,1002,$pdo,4);
$detail=$read();
$selected=$detail['selected_offer'];
if($detail['local_id']!==900||$detail['price']!==$selected['tour']['price']
    ||$selected['operator_ref']!=='5'||$selected['page']!==2
    ||$selected['quote_status']!=='unverified'||$selected['package_status']!=='not_loaded'
    ||$detail['selection_enabled']!==false||$detail['booking_enabled']!==false
    ||str_contains(json_encode($detail),$rawId))throw new RuntimeException('selection projection');
foreach([
    "UPDATE andromeda_hotel_identities SET decision_status='rejected'",
    "UPDATE andromeda_hotel_identities SET decision_status='accepted'; UPDATE catalog_hotels SET is_active=0 WHERE id=900",
    "UPDATE catalog_hotels SET is_active=1,country_id=1 WHERE id=900",
    "UPDATE catalog_hotels SET country_id=4 WHERE id=900; UPDATE andromeda_hotel_identities SET local_hotel_id=901"
] as $sql){
    $pdo->exec($sql);
    try{$read();throw new LogicException('stale mapping was accepted');}
    catch(RuntimeException $e){if($e->getMessage()!=='ANDROMEDA_SELECTION_MAPPING_UNAVAILABLE')throw $e;}
}
echo "Detail selection: display consistency and four current-mapping changes passed; supplier calls 0\n";
