<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-anytour-offer-autosave.php';
require_once dirname(__DIR__) . '/app/integrations/andromeda-normalizer.php';

function aassert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function temp_searches(): string {
    $root = sys_get_temp_dir() . '/anytour-andromeda-autosave-' . bin2hex(random_bytes(6));
    $dir = $root . '/searches';
    if (!mkdir($dir, 0700, true)) throw new RuntimeException('temp mkdir');
    return $dir;
}

function cleanup_dir(string $directory): void {
    $root = dirname($directory);
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function search_request(int $generation = 1, array $children = [7]): array {
    return [
        'generation' => $generation,
        'page' => 1,
        'params' => [
            'departureId' => 1,
            'countryId' => 1,
            'dateFrom' => '2026-10-10',
            'dateTo' => '2026-10-10',
            'nightsFrom' => 7,
            'nightsTo' => 7,
            'adults' => 2,
            'childs' => $children,
            'currency' => 'RUB',
        ],
    ];
}

function normalized_offer(string $suffix, string $operator = 'FUN&SUN', int $local = 101, string $external = '100', string $amount = '185125'): array {
    return [
        'offer_ref' => 'offer_' . hash('sha256', 'offer-' . $suffix),
        'operator' => $operator,
        'supplier_namespace' => 'andromeda_catalog',
        'external_hotel_id' => $external,
        'local_hotel_id' => $local,
        'check_in' => '2026-10-10',
        'nights' => 7,
        'adults' => 2,
        'children' => 1,
        'meal' => ['raw_label' => 'AI', 'label' => 'AI'],
        'room_raw' => 'Deluxe Sea View',
        'placement_raw' => '2AD+1CH',
        'price' => ['amount' => $amount, 'currency' => 'RUB'],
    ];
}

function state(string $ref, int $generation, int $page, int $pages, int $created, array $offers, array $rejected = []): array {
    return [
        'status' => $page === $pages ? 'complete' : 'partial',
        'search_ref' => $ref,
        'generation' => $generation,
        'store' => [
            'version' => 1,
            'search_ref' => $ref,
            'generation' => $generation,
            'created_at' => $created,
            'expires_at' => $created + 900,
            'snapshot' => [
                'provider' => 'andromeda',
                'search_ref' => $ref,
                'generation' => $generation,
                'page' => $page,
                'pages_count' => $pages,
                'offers' => $offers,
                'rejected' => $rejected,
                'selection_enabled' => false,
            ],
        ],
    ];
}

function write_state(string $directory, string $ref, int $created, int $page, array $value): void {
    $path = $page === 1
        ? $directory . '/' . $ref . '-1.json'
        : $directory . '/' . $ref . '-' . $created . '-' . $page . '.json';
    file_put_contents($path, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    chmod($path, 0600);
}

function party_surcharge(string $base = '185125', string $surcharge = '14265', string $total = '199390'): array {
    return [
        'schema_version' => 1,
        'provider' => 'andromeda',
        'state' => 'estimated',
        'search_price' => ['amount' => $base, 'currency' => 'RUB'],
        'party_surcharge' => [
            'amount' => $surcharge,
            'currency' => 'RUB',
            'source' => 'andromeda_get_flights_transport',
        ],
        'search_price_with_surcharge' => [
            'amount' => $total,
            'currency' => 'RUB',
            'source' => 'derived_search_estimate',
        ],
        'surcharge_scope' => 'party',
        'arithmetic_applied' => true,
        'final_price_verified' => false,
    ];
}

function callbacks(array &$ingests, ?array $surcharge = null, bool $mapping = true): array {
    $mappingReader = static function(array $offers) use ($mapping): array {
        if (!$mapping) return [];
        $out = [];
        foreach ($offers as $offer) {
            $out[json_encode([$offer['supplier_namespace'], (string)$offer['external_hotel_id']], JSON_THROW_ON_ERROR)] = $offer['local_hotel_id'];
        }
        return $out;
    };
    $canonicalResolver = static function(array $legacy): array {
        $out = [];
        foreach ($legacy as $id) $out[$id] = $id + 900;
        return $out;
    };
    $surchargeReader = static fn(array $state, int $created, array $offer, array $current): ?array => $surcharge;
    $save = static function(string $path, array $value): bool {
        file_put_contents($path, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        chmod($path, 0600);
        return true;
    };
    $ingest = static function(string $provider, array $search, array $rows, DateTimeImmutable $at) use (&$ingests): array {
        $ingests[] = compact('provider', 'search', 'rows', 'at');
        return ['status' => 'completed', 'provider' => $provider, 'row_count' => count($rows)];
    };
    return [$mappingReader, $canonicalResolver, $surchargeReader, $save, $ingest];
}

function assert_confirmation_dto(array $dto, string $amount = '185125'): void {
    aassert($dto['finalPriceReady'] === false && $dto['finalPrice'] === null
        && $dto['price'] === $amount && $dto['currency'] === 'RUB', 'confirmation used a full or derived price');
    aassert($dto['money']['fuel_charge_reported'] === null
        && !array_key_exists('search_price_with_surcharge', $dto['money'])
        && $dto['money']['arithmetic_applied'] === false
        && $dto['quote_state'] === 'unknown' && $dto['final_price_verified'] === false
        && $dto['selection_state'] === 'disabled' && $dto['booking_enabled'] === false,
        'confirmation gained fuel/final/selection authority');
}

// Missing-field provenance is sanitized and records only ownership, never raw supplier text.
$criteria = [
    'TOWNFROMINC' => '1', 'STATEINC' => '4', 'CHECKIN_BEG' => '20261010', 'CHECKIN_END' => '20261010',
    'ADULT' => '2', 'CHILD' => '1', 'NIGHTS_FROM' => '7', 'NIGHTS_TILL' => '7', 'CURRENCYINC' => '1',
];
$missingBase = [
    'id' => 'x', 'hotelKey' => '100', 'operatorKey' => '1', 'isOperatorHotelKey' => '0',
    'price' => '100000', 'currency' => 'RUB', 'currencyKey' => '1', 'checkIn' => '10.10.2026', 'nights' => '7',
    'hotel' => 'Hotel', 'operator' => 'ANEX Secret Label', 'meal' => 'AI', 'room' => 'STD', 'htplace' => '2AD+1CH',
    'adult' => '2', 'child' => '1',
];
$ownedMissing = $missingBase; $ownedMissing['operator'] = 'Biblio Globus';
$unknownMissing = $missingBase; unset($unknownMissing['operator']); $unknownMissing['mealKey'] = '1';
$normalizedMissing = AnyTourAndromedaNormalizer::page([
    'PAGE' => 1, 'PAGES_COUNT' => 1, 'PRICES' => [$missingBase, $ownedMissing, $unknownMissing],
], $criteria, 'rejection_contract', 1);
aassert(($normalizedMissing['rejected'][0] ?? null) === [
    'index' => 0, 'reason' => 'MISSING_FIELD', 'missing_field' => 'mealKey', 'ownership_class' => 'excluded_direct_or_tv',
], 'excluded missing-field provenance mismatch');
aassert(($normalizedMissing['rejected'][1]['ownership_class'] ?? null) === 'andromeda_owned', 'owned missing-field provenance mismatch');
aassert(($normalizedMissing['rejected'][2] ?? null) === [
    'index' => 2, 'reason' => 'MISSING_FIELD', 'missing_field' => 'operator', 'ownership_class' => 'unknown',
], 'unknown missing-field provenance mismatch');
aassert(!str_contains(json_encode($normalizedMissing['rejected'], JSON_THROW_ON_ERROR), 'Secret Label'), 'raw rejected operator leaked');

$invalidRoom=$missingBase;
$invalidRoom['operator']='Biblio Globus';$invalidRoom['mealKey']='1';$invalidRoom['room']='-';
$normalizedInvalidRoom=AnyTourAndromedaNormalizer::page([
    'PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$invalidRoom],
],$criteria,'invalid_room_contract',1);
aassert(($normalizedInvalidRoom['rejected'][0]??null)===[
    'index'=>0,'reason'=>'THREE_PROVIDER_ROOM_LABEL','ownership_class'=>'andromeda_owned',
], 'owned invalid-room provenance mismatch');

// Synthetic flight estimate arithmetic remains available; this is not fuel proof.
$andromeda = AnyTourThreeProviderMoneyFacts::fromSearch('andromeda',
    ['amount' => '185125', 'currency' => 'RUB', 'source' => 'andromeda_search'], null, [[
        'kind' => 'party_transport_surcharge', 'amount' => '14265', 'currency' => 'RUB',
        'source' => 'andromeda_get_flights_transport',
    ]]);
$andromedaPriced = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($andromeda, 2, 1);
aassert($andromedaPriced['search_price_with_surcharge']['amount'] === '199390', 'Andromeda party surcharge multiplied');

// Direct ANEX remains per-passenger.
$anex = AnyTourThreeProviderMoneyFacts::fromSearch('anex',
    ['amount' => '100000', 'currency' => 'RUB', 'source' => 'anex_search'], null, [
        ['kind' => 'fuel_adult', 'amount' => '1000', 'currency' => 'RUB', 'source' => 'anex_additional'],
        ['kind' => 'fuel_child', 'amount' => '500', 'currency' => 'RUB', 'source' => 'anex_additional'],
    ]);
$anexPriced = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate($anex, 2, 1);
aassert($anexPriced['search_price_with_surcharge']['amount'] === '102500', 'ANEX per-passenger arithmetic regressed');

// Flight-only evidence must not remove valid mapped PRICE rows from the store.
// Retain search prices as confirmation-required, never the derived flight total.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'complete-two-page'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, [normalized_offer('p1')]));
    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 2, $created, [normalized_offer('p2', 'Biblio Globus', 102, '101')]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    echo 'ANDROMEDA_ESTIMATE_RETENTION_MEASURE ' . json_encode([
        'published' => $result['published'], 'ready' => $result['readyOfferCount'],
        'confirmation' => $result['confirmationRequiredOfferCount'] ?? null,
        'stored_rows' => count($ingests[0]['rows'] ?? []),
    ], JSON_THROW_ON_ERROR) . "\n";
    aassert($result['published'] === true && $result['readyOfferCount'] === 0
        && $result['confirmationRequiredOfferCount'] === 2, 'flight-only search rows were dropped, marked final or hidden from receipt');
    aassert($result['receivedOfferCount'] === 2 && $result['ownedOfferCount'] === 2
        && count($ingests) === 1 && count($ingests[0]['rows']) === 2, 'flight-only cohort retention');
    foreach ($ingests[0]['rows'] as $row) assert_confirmation_dto($row['dto']);
    $checkpoint = json_decode(file_get_contents($dir . '/' . $ref . '-' . $created . '-anytour-offer-autosave-v1.json'), true, 64, JSON_THROW_ON_ERROR);
    aassert($checkpoint['ready_offer_count'] === 0, 'confirmation checkpoint claimed final prices');
    $again = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($again['reason'] === 'already_published' && $again['readyOfferCount'] === 0 && count($ingests) === 1,
        'idempotency changed readiness or repeated intake');
} finally { cleanup_dir($dir); }

// Saved independently verified totals still publish a complete two-page cohort,
// preserve raw room/placement and the existing checkpoint idempotency.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'verified-calc'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, [
        normalized_offer('verified', 'FUN&SUN', 101, '100', '185125')
    ]));
    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 2, $created, [
        normalized_offer('verified-page2', 'FUN&SUN', 101, '100', '185125')
    ]));
    $verifiedPricing = [
        'state'=>'verified',
        'fact'=>null,
        'verified_quote'=>[
            'schema_version'=>1,
            'provider'=>'andromeda',
            'selection_enabled'=>true,
            'booking_enabled'=>false,
            'local_id'=>101,
            'operator'=>'FUN&SUN',
            'search_price'=>['amount'=>'185125','currency'=>'RUB'],
            'package_price'=>['amount'=>'185125','currency'=>'RUB'],
            'state'=>'quote_verified',
            'quote_state'=>'verified',
            'final_price'=>['amount'=>'199390','currency'=>'RUB'],
            'final_price_verified'=>true,
            'flight_selection_required'=>false,
            'flights'=>[],
        ],
    ];
    [$mapping, $canonical, $pricing, $save, $ingest] = callbacks($ingests, $verifiedPricing);
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $pricing, $save, $ingest);
    aassert($result['published'] === true && $result['readyOfferCount'] === 2
        && $result['confirmationRequiredOfferCount'] === 0 && count($ingests[0]['rows']) === 2,
        'verified cohort not published or confirmation count wrong');
    $dto = $ingests[0]['rows'][0]['dto'] ?? null;
    aassert(is_array($dto) && $dto['quote_state'] === 'verified'
        && $dto['final_price_verified'] === true, 'verified dto state lost');
    aassert($dto['finalPriceReady'] === true && $dto['finalPrice'] === '199390'
        && $dto['price'] === '199390' && $dto['currency'] === 'RUB', 'verified final price lost');
    aassert($dto['booking_enabled'] === false && $dto['selection_state'] === 'disabled',
        'verified cached row gained authority');
    aassert($dto['tour']['room']['raw'] === 'Deluxe Sea View', 'raw room lost');
    aassert($dto['tour']['placement']['raw'] === '2AD+1CH', 'raw placement lost');
    $again = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $pricing, $save, $ingest);
    aassert($again['reason'] === 'already_published' && $again['readyOfferCount'] === 2 && count($ingests) === 1, 'identical verified cohort republished');
} finally { cleanup_dir($dir); }

// EOF completion retains preceding PRICE rows without promoting flight-only totals.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'terminal-empty-eof'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 5, $created, [normalized_offer('eof1')]));
    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 5, $created, [normalized_offer('eof2', 'Интурист', 102, '101')]));
    $terminal = state($ref, 1, 3, 0, $created, []);
    $terminal['status'] = 'complete';
    write_state($dir, $ref, $created, 3, $terminal);
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['published'] === true && $result['readyOfferCount'] === 0
        && $result['confirmationRequiredOfferCount'] === 2, 'terminal flight-only prices were promoted, hidden from receipt or rows lost');
    aassert($result['receivedOfferCount'] === 2 && $result['ownedOfferCount'] === 2
        && count($ingests[0]['rows']) === 2, 'terminal data pages lost');
    foreach ($ingests[0]['rows'] as $row) assert_confirmation_dto($row['dto']);
} finally { cleanup_dir($dir); }

// A zero pages_count page with offers is not EOF and must fail closed.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'invalid-zero-nonempty'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 3, $created, [normalized_offer('badzero1')]));
    $bad = state($ref, 1, 2, 0, $created, [normalized_offer('badzero2', 'Интурист', 102, '101')]);
    $bad['status'] = 'complete';
    write_state($dir, $ref, $created, 2, $bad);
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['published'] === false && $result['reason'] === 'cohort_invalid' && $ingests === [], 'nonempty zero page accepted');
} finally { cleanup_dir($dir); }

// Missing advertised page blocks publication.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'missing-page'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, [normalized_offer('missing')]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['reason'] === 'cohort_incomplete' && $ingests === [], 'missing page published');
} finally { cleanup_dir($dir); }

// A later page can increase pages_count; the newly advertised page is mandatory.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'growing-pages'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, [normalized_offer('grow1')]));
    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 3, $created, [normalized_offer('grow2', 'Intourist', 102, '101')]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['reason'] === 'cohort_incomplete' && $ingests === [], 'growing pages published early');
} finally { cleanup_dir($dir); }

// Missing saved surcharge persists only the supplier search price as confirmation-required.
// It must not manufacture a final/fuel-inclusive total or selection/booking authority.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'missing-surcharge'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('nosurcharge')]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, null);
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['published'] === true && $result['readyOfferCount'] === 0
        && $result['confirmationRequiredOfferCount'] === 1 && count($ingests) === 1,
        'missing surcharge confirmation snapshot not published or receipt lost confirmation count');
    $dto = $ingests[0]['rows'][0]['dto'] ?? null;
    aassert(is_array($dto) && $dto['finalPriceReady'] === false && $dto['finalPrice'] === null
        && $dto['price'] === '185125' && $dto['currency'] === 'RUB', 'confirmation search price invalid');
    aassert($dto['money']['fuel_charge_reported'] === null && $dto['final_price_verified'] === false
        && $dto['selection_state'] === 'disabled' && $dto['booking_enabled'] === false,
        'confirmation snapshot gained final/fuel/selection authority');
} finally { cleanup_dir($dir); }

// Current mapping mismatch blocks a false authoritative empty snapshot.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'mapping-mismatch'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('mapping')]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge(), false);
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['reason'] === 'no_current_mapped_offers' && $ingests === [], 'mapping mismatch expired snapshot');
} finally { cleanup_dir($dir); }

// Operators owned elsewhere form an authoritative empty Andromeda-owned cohort.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'excluded-only'); $created = time() - 30; $ingests = [];
    $offers = [
        normalized_offer('anex', 'ANEX'), normalized_offer('pegas', 'PEGAS Touristik', 102, '101'),
        normalized_offer('coral', 'Coral Travel', 103, '102'), normalized_offer('sunmar', 'Sunmar', 104, '103'),
    ];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, $offers));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['published'] === true && $result['confirmationRequiredOfferCount'] === 0
        && count($ingests) === 1 && $ingests[0]['rows'] === [], 'excluded-only cohort not authoritative empty');
} finally { cleanup_dir($dir); }

// Safe excluded rejections do not poison valid confirmation-required rows.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'safe-excluded-rejection'); $created = time() - 30; $ingests = [];
    $safeRejected = [[
        'index' => 1, 'reason' => 'MISSING_FIELD', 'missing_field' => 'mealKey', 'ownership_class' => 'excluded_direct_or_tv',
    ]];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('safe-rejection')], $safeRejected));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['published'] === true && $result['readyOfferCount'] === 0
        && $result['confirmationRequiredOfferCount'] === 1
        && $result['ownedOfferCount'] === 1 && count($ingests[0]['rows']) === 1, 'safe rejection changed completeness or retention');
    assert_confirmation_dto($ingests[0]['rows'][0]['dto']);
} finally { cleanup_dir($dir); }

// One sanitized Andromeda-owned invalid room is quarantined without poisoning valid siblings.
$dir = temp_searches();
try {
    $ref=hash('sha256','owned-invalid-room-quarantine');$created=time()-30;$ingests=[];
    $rejected=[['index'=>1,'reason'=>'THREE_PROVIDER_ROOM_LABEL','ownership_class'=>'andromeda_owned']];
    write_state($dir,$ref,$created,1,state($ref,1,1,1,$created,[normalized_offer('valid-sibling')],$rejected));
    [$mapping,$canonical,$surcharge,$save,$ingest]=callbacks($ingests,party_surcharge());
    $result=AnyTourAndromedaOfferAutosaveV1::consume(search_request(),$dir,$ref,1,
        new DateTimeImmutable('now',new DateTimeZone('UTC')),$mapping,$canonical,$surcharge,$save,$ingest);
    aassert($result['published']===true && $result['confirmationRequiredOfferCount']===1
        && count($ingests)===1 && count($ingests[0]['rows'])===1,'owned invalid room poisoned valid cohort');
} finally { cleanup_dir($dir); }

// Owned, unknown, legacy or payload-bearing rejected rows stay fail-closed.
foreach ([
    'owned' => [['index' => 1, 'reason' => 'MISSING_FIELD', 'missing_field' => 'mealKey', 'ownership_class' => 'andromeda_owned']],
    'unknown' => [['index' => 1, 'reason' => 'MISSING_FIELD', 'missing_field' => 'operator', 'ownership_class' => 'unknown']],
    'legacy' => [['index' => 1, 'reason' => 'MISSING_FIELD']],
    'payload' => [['index' => 1, 'reason' => 'MISSING_FIELD', 'missing_field' => 'mealKey', 'ownership_class' => 'excluded_direct_or_tv', 'operator' => 'ANEX']],
] as $case => $rejected) {
    $dir = temp_searches();
    try {
        $ref = hash('sha256', 'unsafe-rejection-' . $case); $created = time() - 30; $ingests = [];
        write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('unsafe-' . $case)], $rejected));
        [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
        $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
            new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
        aassert($result['published'] === false && $result['reason'] === 'cohort_rejected_rows' && $ingests === [], 'unsafe rejection accepted: ' . $case);
    } finally { cleanup_dir($dir); }
}

// Duplicate supplier offer identity across pages is never authoritative.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'duplicate'); $created = time() - 30; $ingests = [];
    $same = normalized_offer('same');
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, [$same]));
    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 2, $created, [$same]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['reason'] === 'duplicate_offer_identity' && $ingests === [], 'duplicate offer published');
} finally { cleanup_dir($dir); }

// Both retained envelope shapes and explicit zero keep only the supplier base.
foreach ([party_surcharge(), party_surcharge('185125', '0', '185125'),
    ['state' => 'estimated', 'fact' => party_surcharge(), 'verified_quote' => null]] as $case => $fact) {
    $dir = temp_searches();
    try {
        $ref = hash('sha256', 'estimated-shape-' . $case); $created = time() - 30; $ingests = [];
        write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('shape-' . $case)]));
        [$mapping, $canonical, $pricing, $save, $ingest] = callbacks($ingests, $fact);
        $before = $fact;
        $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
            new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $pricing, $save, $ingest);
        aassert($result['published'] === true && $result['readyOfferCount'] === 0
            && $result['confirmationRequiredOfferCount'] === 1 && count($ingests[0]['rows']) === 1,
            'estimated shape not retained or receipt lost confirmation count');
        assert_confirmation_dto($ingests[0]['rows'][0]['dto']);
        aassert($fact === $before, 'retained flight evidence mutated');
    } finally { cleanup_dir($dir); }
}

// A later verified quote must upgrade a mixed PRICE cohort, not be suppressed by
// its earlier confirmation checkpoint. Missing evidence never loses sibling rows.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'estimate-upgrade'); $created = time() - 30; $ingests = [];
    $plain = normalized_offer('upgrade-plain'); $estimated = normalized_offer('upgrade-estimated');
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [$plain, $estimated]));
    [$mapping, $canonical, $unused, $save, $ingest] = callbacks($ingests);
    $mode = 'estimated';
    $pricing = static function(array $state, int $created, array $offer, array $current) use (&$mode, $estimated, $verifiedPricing): ?array {
        if ($offer['offer_ref'] !== $estimated['offer_ref']) return null;
        return $mode === 'estimated' ? ['state' => 'estimated', 'fact' => party_surcharge(), 'verified_quote' => null] : $verifiedPricing;
    };
    $at = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $first = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1, $at, $mapping, $canonical, $pricing, $save, $ingest);
    aassert($first['published'] === true && $first['readyOfferCount'] === 0
        && $first['confirmationRequiredOfferCount'] === 2 && count($ingests[0]['rows']) === 2,
        'mixed estimate lost a row or fresh receipt lost confirmation count');
    foreach ($ingests[0]['rows'] as $row) assert_confirmation_dto($row['dto']);
    $mode = 'verified';
    $upgraded = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1, $at, $mapping, $canonical, $pricing, $save, $ingest);
    aassert($upgraded['published'] === true && $upgraded['readyOfferCount'] === 1
        && $upgraded['confirmationRequiredOfferCount'] === 1
        && count($ingests) === 2 && count($ingests[1]['rows']) === 2, 'verified upgrade suppressed, sibling lost or receipt count wrong');
    assert_confirmation_dto($ingests[1]['rows'][0]['dto']);
    aassert($ingests[1]['rows'][1]['dto']['finalPriceReady'] === true
        && $ingests[1]['rows'][1]['dto']['finalPrice'] === '199390', 'verified final not preserved');
    $again = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1, $at, $mapping, $canonical, $pricing, $save, $ingest);
    aassert($again['reason'] === 'already_published' && $again['readyOfferCount'] === 1 && count($ingests) === 2,
        'mixed finalizer inflated readiness or repeated intake');
    echo "ANDROMEDA_ESTIMATE_UPGRADE_OK stored=2 initial_ready=0 initial_confirmation=2 upgraded_ready=1 upgraded_confirmation=1 repeated_intakes=0\n";
} finally { cleanup_dir($dir); }

// Malformed or contradictory retained evidence still refuses BEFORE persistence.
$invalid = [];
$bad = party_surcharge(); $bad['surcharge_scope'] = 'person'; $invalid['scope'] = $bad;
$bad = party_surcharge(); $bad['search_price']['amount'] = '1'; $invalid['base'] = $bad;
$bad = party_surcharge(); $bad['party_surcharge']['source'] = 'unknown'; $invalid['source'] = $bad;
$bad = party_surcharge(); $bad['party_surcharge']['currency'] = 'EUR'; $invalid['currency'] = $bad;
$bad = party_surcharge(); $bad['search_price_with_surcharge']['amount'] = '199391'; $invalid['sum'] = $bad;
$invalid['shape'] = ['state' => 'estimated', 'fact' => 'bad'];
foreach ($invalid as $case => $fact) {
    $dir = temp_searches();
    try {
        $ref = hash('sha256', 'invalid-estimate-' . $case); $created = time() - 30; $ingests = [];
        write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('invalid-' . $case)]));
        [$mapping, $canonical, $pricing, $save, $ingest] = callbacks($ingests, $fact);
        $refused = false;
        try {
            $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
                new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $pricing, $save, $ingest);
            $refused = $result['published'] === false && $result['reason'] === 'offer_contract_incomplete';
        } catch (DomainException $error) {
            $refused = $case === 'sum' && $error->getMessage() === 'ANDROMEDA_ANYTOUR_PROTECTED_PRICE_MISMATCH';
        }
        aassert($refused && $ingests === [], 'invalid estimate fell back: ' . $case);
        aassert(!file_exists($dir . '/' . $ref . '-' . $created . '-anytour-offer-autosave-v1.json'), 'invalid estimate wrote checkpoint');
    } finally { cleanup_dir($dir); }
}
echo "ANDROMEDA_ESTIMATE_RETENTION_OK shapes=3 invalid=6 full_price_guard_unchanged=1 receipt_counts=1 supplier=0 live_db=0\n";
echo "andromeda-anytour-offer-autosave-test: OK\n";