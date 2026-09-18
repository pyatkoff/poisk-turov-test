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

// Andromeda/SAMO transport markup is already a whole-party fact: add exactly once.
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

// Complete two-page cohort publishes once and preserves raw room/placement.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'complete-two-page'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, [normalized_offer('p1')]));
    write_state($dir, $ref, $created, 2, state($ref, 1, 2, 2, $created, [normalized_offer('p2', 'Biblio Globus', 102, '101')]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, party_surcharge());
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['published'] === true, 'complete cohort not published');
    aassert(count($ingests) === 1 && count($ingests[0]['rows']) === 2, 'complete cohort row count');
    aassert($ingests[0]['rows'][0]['dto']['price'] === '199390', 'protected final price mismatch');
    aassert($ingests[0]['rows'][0]['dto']['tour']['room']['raw'] === 'Deluxe Sea View', 'raw room lost');
    aassert($ingests[0]['rows'][0]['dto']['tour']['placement']['raw'] === '2AD+1CH', 'raw placement lost');
    $again = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($again['reason'] === 'already_published' && count($ingests) === 1, 'identical cohort republished');
} finally { cleanup_dir($dir); }

// A saved supplier-verified calc quote is a separate pricing state. Autosave
// forwards it through the INT verified-quote producer path without search-side arithmetic.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'verified-calc'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [
        normalized_offer('verified', 'FUN&SUN', 101, '100', '185125')
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
    aassert($result['published'] === true && $result['readyOfferCount'] === 1, 'verified cohort not published');
    $dto = $ingests[0]['rows'][0]['dto'] ?? null;
    aassert(is_array($dto) && $dto['quote_state'] === 'verified'
        && $dto['final_price_verified'] === true, 'verified dto state lost');
    aassert($dto['finalPriceReady'] === true && $dto['finalPrice'] === '199390'
        && $dto['price'] === '199390' && $dto['currency'] === 'RUB', 'verified final price lost');
    aassert($dto['booking_enabled'] === false && $dto['selection_state'] === 'disabled',
        'verified cached row gained authority');
} finally { cleanup_dir($dir); }

// Real SAMO EOF: advertised page count may later terminate with an empty complete
// page whose pages_count resets to zero. Preceding data pages form the complete cohort.
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
    aassert($result['published'] === true, 'terminal empty cohort not published');
    aassert(count($ingests) === 1 && count($ingests[0]['rows']) === 2, 'terminal empty data pages lost');
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

// Missing saved surcharge stays fail-closed: nonempty cohort cannot expire existing rows.
$dir = temp_searches();
try {
    $ref = hash('sha256', 'missing-surcharge'); $created = time() - 30; $ingests = [];
    write_state($dir, $ref, $created, 1, state($ref, 1, 1, 1, $created, [normalized_offer('nosurcharge')]));
    [$mapping, $canonical, $surcharge, $save, $ingest] = callbacks($ingests, null);
    $result = AnyTourAndromedaOfferAutosaveV1::consume(search_request(), $dir, $ref, 1,
        new DateTimeImmutable('now', new DateTimeZone('UTC')), $mapping, $canonical, $surcharge, $save, $ingest);
    aassert($result['published'] === false && $result['reason'] === 'no_final_price_ready_resolved_offers' && $ingests === [], 'missing surcharge published');
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
    aassert($result['published'] === true && count($ingests) === 1 && $ingests[0]['rows'] === [], 'excluded-only cohort not authoritative empty');
} finally { cleanup_dir($dir); }

// Sanitized MISSING_FIELD rows proven outside Andromeda ownership do not poison an otherwise authoritative cohort.
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
    aassert($result['published'] === true && count($ingests) === 1 && count($ingests[0]['rows']) === 1, 'safe excluded rejection blocked cohort');
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

echo "andromeda-anytour-offer-autosave-test: OK\n";
