<?php
declare(strict_types=1);

$runtime = $argv[1] ?? '';
require $runtime . '/v2/api-andromeda-search3-preview.php';
require $runtime . '/app/integrations/andromeda-package-capture.php';
require __DIR__ . '/../app/integrations/andromeda-package-public.php';

$checks = 0;
function public_check(bool $ok): void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException('package_public_check_' . $checks);
}

$ref = str_repeat('a', 64); $generation = 3; $now = 1002; $mappingAllowed = true;
$criteria = ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>643,'PAGE'=>1];
$fullClaiminc = 'operator5:form42:selected-package-id';
$row = ['id'=>$fullClaiminc,'hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>83080,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'22.09.2026','nights'=>'7',
    'hotel'=>'Public Hotel','operator'=>'Public Operator','meal'=>'AI','mealKey'=>7,
    'room'=>'Sea View','htplace'=>'DBL','adult'=>'2','child'=>'0'];
$resolver = AnyTourAndromedaHotelResolver::fromRows([['supplier_namespace'=>'andromeda_catalog',
    'external_hotel_id'=>'3414','decision_status'=>'accepted','catalog_hotel_id'=>'900',
    'existing_catalog_hotel_id'=>'900']], str_repeat('c',64));
$state = []; $store = new AnyTourAndromedaOfferStore($state, true);
$store->begin($ref, $generation, 1000);
$page = $store->capture(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]], $criteria, $ref, $generation, 1001, $resolver);
$context = ['provider'=>'andromeda','search_ref'=>$ref,'generation'=>$generation,'page'=>1,
    'offer_ref'=>$page['offers'][0]['offer_ref']];
$allows = static function(array $offer) use (&$mappingAllowed): bool {
    return $mappingAllowed && ($offer['local_hotel_id'] ?? null) === 900;
};
$resolved = AnyTourAndromedaSelectedOffer::resolve($store, $context, $allows, $now);
$context = array_intersect_key($resolved['context'], array_flip(['provider','search_ref','generation','page','offer_ref','hotel_scope','operator_ref']));

// SYNTHETIC fixture shaped from the official claim_struct schema. It is not a live supplier response.
// Supplier clarification confirms catalogKey is related to, but shorter than, full PRICES[].id/claiminc.
$raw = [
    'version' => '1.01',
    'claimDocument' => [[
        'catalogKey' => 'selected-package-id',
        'condition' => 'ccOffer',
        'datebeg' => '2026-09-22', 'dateend' => '2026-09-29', 'nights' => '7',
        'peopleCount' => '2', 'adult' => '2', 'child' => '0', 'infant' => '0',
        'freightExternal' => '1',
        'hotels' => [['hotel' => [[
            'uid'=>'private-order-id','key'=>'3414','name'=>'Public Hotel','datebeg'=>'2026-09-22','dateend'=>'2026-09-29',
            'star'=>'5*','town'=>'Antalya','state'=>'Turkey','room'=>'Sea View','htplace'=>'DBL','meal'=>'AI',
            'placesCount'=>'2','roomCount'=>'1','clients'=>[['client'=>[['peopleKey'=>'private-person-id']]]]
        ]]]],
        'transports' => [['transport' => [[
            'uid'=>'private-flight-id','key'=>'55','name'=>'Public Flight','type'=>'ttAvia','class'=>'Economy',
            'onlineClass'=>'economy','datebeg'=>'2026-09-22','dateend'=>'2026-09-22',
            'departure'=>[['state'=>'Russia','town'=>'Moscow','port'=>'SVO','time'=>'10:30']],
            'arrival'=>[['state'=>'Turkey','town'=>'Antalya','port'=>'AYT','time'=>'15:10']],
            'clients'=>[['client'=>[['peopleKey'=>'private-person-id','place'=>'private-seat']]]]
        ]]]],
        'services' => [['service' => [
            ['uid'=>'private-service-id','name'=>'Transfer','type'=>'stTransfer','servicetype'=>'Group transfer',
                'datebeg'=>'2026-09-22','dateend'=>'2026-09-22','required'=>'true','hidden'=>'false'],
            ['uid'=>'private-hidden-id','name'=>'PRIVATE HIDDEN SERVICE','type'=>'stOther','hidden'=>'true']
        ]]],
        'buyerMoneys' => [['buyerClaimMoney' => [['price'=>'90000.00','net'=>'83080.00','currencyKey'=>'643','currency'=>'RUB']]]],
        'moneys' => [['money' => [['net'=>'PRIVATE AGENCY COST','discoCost'=>'PRIVATE DISCOUNT','currency'=>'RUB']]]],
        'buyer' => [['name'=>'PRIVATE BUYER','pnumber'=>'PRIVATE PASSPORT','email'=>'private@example.test']],
        'peoples' => [['people' => [['name'=>'PRIVATE TRAVELLER','pnumber'=>'PRIVATE PASSPORT']]]],
        'provider' => [['name'=>'Public Operator','guid'=>'PRIVATE PROVIDER ID','supportPhone'=>'PRIVATE PHONE','supportNote'=>'PRIVATE NOTE']],
        'note' => [['_'=>'PRIVATE SUPPLIER NOTE']],
    ]],
    'variants' => [['hotels' => [['hotel' => [['name'=>'PRIVATE ALTERNATIVE HOTEL']]]]]],
    'checkFields' => [['people' => [['pnumber'=>[['required'=>'true']]]]]],
];
$record = ['version'=>1,'status'=>'captured','context'=>$resolved['context'],'created_at'=>1001,
    'criteria_sha256'=>$resolved['criteria_sha256'],'supplier_offer_sha256'=>$resolved['supplier_offer_sha256'],
    'private_package'=>$raw,'package_sha256'=>hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR)),
    'identity_verified'=>false,'quote_verified'=>false,'selection_enabled'=>false];
$persist = static fn(array $next, array $expected): array => $next;
$capture = new AnyTourAndromedaPackageCapture($record, $persist, $allows, true, static fn(): int => $GLOBALS['now_for_package_public'] ?? 1002);
$GLOBALS['now_for_package_public'] = $now;

// A raw captured record can expose bounded composition, but cannot establish selection binding by catalogKey.
$direct = AnyTourAndromedaPackagePublic::record($record);
public_check($direct['status'] === 'package_captured_unquoted' && $direct['package_binding_verified'] === false);
public_check($direct['identity_verified'] === false && $direct['quote_verified'] === false && $direct['selection_enabled'] === false);
// current() may bind only after PackageCapture::read() rechecks retained context/mapping and full claiminc digest.
$current = AnyTourAndromedaPackagePublic::current($capture, $store, $context);
public_check($current['status'] === 'package_bound_unquoted' && $current['identity_verified'] === true);
public_check($current['package_binding_verified'] === true && $current['quote_verified'] === false && $current['selection_enabled'] === false);
public_check($current['trip'] === ['date_from'=>'2026-09-22','date_to'=>'2026-09-29','nights'=>7,'people'=>2,'adults'=>2,'children'=>0,'infants'=>0]);
public_check(count($current['hotels']) === 1 && $current['hotels'][0]['name'] === 'Public Hotel');
public_check($current['hotels'][0]['room'] === 'Sea View' && $current['hotels'][0]['placement'] === 'DBL' && $current['hotels'][0]['meal'] === 'AI');
public_check(count($current['transports']) === 1 && $current['transports'][0]['type'] === 'ttAvia');
public_check($current['transports'][0]['departure'][0]['port'] === 'SVO' && $current['transports'][0]['arrival'][0]['port'] === 'AYT');
public_check(count($current['services']) === 1 && $current['services'][0]['name'] === 'Transfer' && $current['services'][0]['required'] === true);
public_check($current['price'] === ['amount'=>'83080.00','currency'=>'RUB','status'=>'package_unverified']);
public_check($current['price_status'] === 'package_unverified' && $current['requires_external_flights'] === true);
public_check($current['alternatives_available'] === true);
$publicJson = json_encode($current, JSON_THROW_ON_ERROR);
foreach (['PRIVATE', 'private@', 'private-', $fullClaiminc, 'selected-package-id', '3414', '643', '90000'] as $secret) {
    public_check(!str_contains($publicJson, $secret));
}
public_check(str_contains($publicJson, '83080.00') && str_contains($publicJson, 'Public Hotel'));

// Agency moneys must never substitute for missing/malformed buyerMoneys.
$withoutBuyer = $record; unset($withoutBuyer['private_package']['claimDocument'][0]['buyerMoneys']);
$withoutBuyer['package_sha256'] = hash('sha256', json_encode($withoutBuyer['private_package'], JSON_THROW_ON_ERROR));
$projected = AnyTourAndromedaPackagePublic::record($withoutBuyer);
public_check($projected['price_status'] === 'unknown' && !isset($projected['price']));
foreach (['PRIVATE AGENCY COST','PRIVATE DISCOUNT'] as $secret) public_check(!str_contains(json_encode($projected), $secret));

foreach ([0.0, '0', '0.00', '-1', '1e5', '1,00', '10.001', 'private-price'] as $badAmount) {
    $changed = $record;
    $changed['private_package']['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][0]['net'] = $badAmount;
    $changed['package_sha256'] = hash('sha256', json_encode($changed['private_package'], JSON_THROW_ON_ERROR));
    $projection = AnyTourAndromedaPackagePublic::record($changed);
    public_check($projection['price_status'] === 'unknown' && !isset($projection['price']));
}
foreach (['1', '1.20', 42, '999999999999.99'] as $amount) {
    $changed = $record;
    $changed['private_package']['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][0]['net'] = $amount;
    $changed['package_sha256'] = hash('sha256', json_encode($changed['private_package'], JSON_THROW_ON_ERROR));
    public_check(AnyTourAndromedaPackagePublic::record($changed)['price']['amount'] === (string) $amount);
}
$changed = $record;
$changed['private_package']['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][] = $changed['private_package']['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][0];
$changed['package_sha256'] = hash('sha256', json_encode($changed['private_package'], JSON_THROW_ON_ERROR));
public_check(AnyTourAndromedaPackagePublic::record($changed)['price_status'] === 'unknown');
$changed = $record; $changed['private_package']['claimDocument'][0]['buyerMoneys'][0]['buyerClaimMoney'][0]['currency']='rub';
$changed['package_sha256']=hash('sha256',json_encode($changed['private_package'],JSON_THROW_ON_ERROR));
public_check(AnyTourAndromedaPackagePublic::record($changed)['price_status']==='unknown');

// catalogKey is a shortened related supplier key, not full claiminc and not binding authority.
$changed = $record; $changed['private_package']['claimDocument'][0]['catalogKey'] = 'another-short-catalog-key';
$changed['package_sha256'] = hash('sha256', json_encode($changed['private_package'], JSON_THROW_ON_ERROR));
$projection = AnyTourAndromedaPackagePublic::record($changed);
public_check($projection['status'] === 'package_captured_unquoted' && !$projection['package_binding_verified'] && isset($projection['trip'],$projection['price']));
$changedCapture = new AnyTourAndromedaPackageCapture($changed, $persist, $allows, true, static fn(): int => 1002);
$projection = AnyTourAndromedaPackagePublic::current($changedCapture, $store, $context);
public_check($projection['status'] === 'package_bound_unquoted' && $projection['package_binding_verified'] && $projection['identity_verified']);
foreach (['', "bad\nkey", str_repeat('x',4097), 123] as $badCatalogKey) {
    $changed = $record;
    $changed['private_package']['claimDocument'][0]['catalogKey'] = $badCatalogKey;
    $changed['package_sha256'] = hash('sha256', json_encode($changed['private_package'], JSON_THROW_ON_ERROR));
    public_check(AnyTourAndromedaPackagePublic::record($changed)['status'] === 'claim_document_invalid');
}

$changed = $record; $changed['private_package']['claimDocument'][0]['condition'] = 'ccBooked';
$changed['package_sha256'] = hash('sha256', json_encode($changed['private_package'], JSON_THROW_ON_ERROR));
$projection = AnyTourAndromedaPackagePublic::record($changed);
public_check($projection['status'] === 'package_not_temporary' && !$projection['package_binding_verified'] && !isset($projection['trip'],$projection['price']));
$bookedCapture = new AnyTourAndromedaPackageCapture($changed, $persist, $allows, true, static fn(): int => 1002);
$projection = AnyTourAndromedaPackagePublic::current($bookedCapture, $store, $context);
public_check($projection['status'] === 'package_not_temporary' && $projection['package_binding_verified'] && !$projection['identity_verified']);

foreach ([[], [$raw['claimDocument'][0], $raw['claimDocument'][0]], ['not'=>'a-list'], [null]] as $documents) {
    $changed = $record; $changed['private_package']['claimDocument'] = $documents;
    $changed['package_sha256'] = hash('sha256', json_encode($changed['private_package'], JSON_THROW_ON_ERROR));
    public_check(AnyTourAndromedaPackagePublic::record($changed)['status'] === 'claim_document_invalid');
}
$changed = $record; $changed['package_sha256'] = str_repeat('0',64);
public_check(AnyTourAndromedaPackagePublic::record($changed)['status'] === 'checkpoint_invalid');
foreach (['reserved','unknown','stale','failed'] as $status) {
    public_check(AnyTourAndromedaPackagePublic::record(array_replace($record,['status'=>$status]))['status'] === 'not_captured');
}
foreach (['identity_verified','quote_verified','selection_enabled'] as $flag) {
    public_check(AnyTourAndromedaPackagePublic::record(array_replace($record,[$flag=>true]))['status'] === 'checkpoint_invalid');
}
$changed = $record; $changed['private_package']['bad'] = NAN;
public_check(AnyTourAndromedaPackagePublic::record($changed)['status'] === 'checkpoint_invalid');
$changed = $record; $changed['private_package']['bad'] = new stdClass();
public_check(AnyTourAndromedaPackagePublic::record($changed)['status'] === 'checkpoint_invalid');

// Current mapping/context authority is mandatory for identity_verified.
$mappingAllowed = false;
$projection = AnyTourAndromedaPackagePublic::current($capture, $store, $context);
public_check($projection['status'] === 'current_context_invalid' && !$projection['identity_verified'] && !isset($projection['trip'],$projection['price']));
$mappingAllowed = true;
$wrong = $context; $wrong['operator_ref'] = 'wrong-operator';
$projection = AnyTourAndromedaPackagePublic::current($capture, $store, $wrong);
public_check($projection['status'] === 'current_context_invalid' && !$projection['identity_verified']);
$GLOBALS['now_for_package_public'] = 9999999;
$projection = AnyTourAndromedaPackagePublic::current($capture, $store, $context);
public_check($projection['status'] === 'current_context_invalid' && !$projection['identity_verified']);
$GLOBALS['now_for_package_public'] = $now;

// Missing hotels is an invalid composition, but current capture provenance can still establish package binding.
$noHotels = $record; unset($noHotels['private_package']['claimDocument'][0]['hotels']);
$noHotels['package_sha256'] = hash('sha256', json_encode($noHotels['private_package'], JSON_THROW_ON_ERROR));
$noHotelCapture = new AnyTourAndromedaPackageCapture($noHotels, $persist, $allows, true, static fn(): int => 1002);
$projection = AnyTourAndromedaPackagePublic::current($noHotelCapture, $store, $context);
public_check($projection['status'] === 'composition_invalid' && $projection['package_binding_verified'] === true
    && $projection['identity_verified'] === true && !isset($projection['price']));

// No source object is mutated by projection.
$before = serialize($record);
AnyTourAndromedaPackagePublic::record($record);
public_check(serialize($record) === $before);

echo 'Package public projection: ' . $checks . " checks passed; shortened catalogKey fixture, supplier/SSH/DB/filesystem writes=0.\n";
