<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-surcharge-cache-autosave.php';

function cache_autosave_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function cache_autosave_offer(string $suffix, string $base, string $program = 'program_1', string $date = '2026-10-30'): array
{
    return [
        'provider' => 'andromeda',
        'operator_ref' => 'operator_1',
        'offer_ref' => 'offer_' . hash('sha256', 'cache-offer-' . $suffix),
        'external_hotel_id' => 'hotel_' . $suffix,
        'room_raw' => 'room_' . $suffix,
        'meal' => ['raw_label' => 'AI_' . $suffix],
        'check_in' => $date,
        'nights' => 7,
        'adults' => 2,
        'children' => 0,
        'price' => ['amount' => $base, 'currency' => 'RUB'],
        'transport_context' => [
            'freight_external' => true,
            'program_ref' => $program,
            'tour_ref' => 'tour_1',
            'spo_ref' => 'spo_' . $suffix,
        ],
    ];
}

function cache_autosave_fact(
    string $base = '185125',
    string $surcharge = '14265',
    string $total = '199390',
    string $aggregation = 'single_distinct_party_markup'
): array {
    return [
        'schema_version' => 1,
        'provider' => 'andromeda',
        'state' => 'estimated',
        'search_price' => ['amount' => $base, 'currency' => 'RUB'],
        'transport_markup_reported' => [
            'amount' => $surcharge,
            'currency' => 'RUB',
            'source' => 'andromeda_get_flights_transport',
            'aggregation' => $aggregation,
        ],
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

$root = sys_get_temp_dir() . '/andromeda-cache-autosave-' . bin2hex(random_bytes(6));
$directory = $root . '/searches';
if (!mkdir($directory, 0700, true)) throw new RuntimeException('mkdir');
try {
    $now = 1800000000;
    $request = ['departureId' => 1, 'countryId' => 4];
    $sourceOffer = cache_autosave_offer('source', '185125');
    $sourceRef = $sourceOffer['offer_ref'];
    $exactEstimated = ['state' => 'estimated', 'fact' => cache_autosave_fact(), 'verified_quote' => null];
    $meta = [
        'source_sha' => str_repeat('a', 40),
        'source_search_ref' => str_repeat('b', 64),
        'source_offer_ref' => $sourceRef,
        'observed_at' => $now - 5,
        'expires_at' => $now + 120,
    ];
    $writes = 0;
    $writer = static function(string $path, array $value) use (&$writes): bool {
        ++$writes;
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return file_put_contents($path, $encoded, LOCK_EX) === strlen($encoded);
    };

    // Exact program-level estimated pricing wins and seeds one strict reusable group specimen.
    $resolved = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        $exactEstimated, $sourceOffer, $request, $directory, $now, $meta, $writer
    );
    cache_autosave_assert($resolved === $exactEstimated, 'exact estimated pricing changed');
    cache_autosave_assert($writes === 1, 'exact estimated pricing did not seed cache');

    // Same transport group but different hotel/room/meal/SPO/base PRICE reuses only
    // the party surcharge and rebases it to this offer's own search price.
    $sibling = cache_autosave_offer('sibling', '190000');
    $fallback = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        null, $sibling, $request, $directory, $now
    );
    cache_autosave_assert(($fallback['state'] ?? null) === 'estimated', 'cache fallback state');
    cache_autosave_assert(array_key_exists('verified_quote',$fallback) && $fallback['verified_quote'] === null, 'cache fallback gained verified quote');
    cache_autosave_assert(($fallback['fact']['search_price'] ?? null) === ['amount' => '190000', 'currency' => 'RUB'], 'cache fallback did not rebase base price');
    cache_autosave_assert(($fallback['fact']['party_surcharge']['amount'] ?? null) === '14265', 'cache fallback surcharge changed');
    cache_autosave_assert(($fallback['fact']['search_price_with_surcharge']['amount'] ?? null) === '204265', 'cache fallback total');
    cache_autosave_assert(($fallback['fact']['final_price_verified'] ?? null) === false, 'cache fallback became final');
    cache_autosave_assert(($fallback['fact']['reuse_basis']['aggregation'] ?? null) === 'single_distinct_party_markup', 'cache fallback lost reuse basis');

    // A choice-dependent exact estimate remains authoritative for that offer, but cannot seed shared cache.
    $choiceSource = cache_autosave_offer('choice-source', '200000', 'program_choice');
    $choiceExact = ['state' => 'estimated', 'fact' => cache_autosave_fact(
        '200000', '12000', '212000', 'minimum_complete_required_roundtrip_markup'
    ), 'verified_quote' => null];
    $choiceMeta = $meta;
    $choiceMeta['source_offer_ref'] = $choiceSource['offer_ref'];
    $beforeChoiceWrites = $writes;
    $choiceResolved = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        $choiceExact, $choiceSource, $request, $directory, $now, $choiceMeta, $writer
    );
    cache_autosave_assert($choiceResolved === $choiceExact, 'choice-dependent exact estimate changed');
    cache_autosave_assert($writes === $beforeChoiceWrites, 'choice-dependent estimate seeded shared cache');
    $choiceSibling = cache_autosave_offer('choice-sibling', '210000', 'program_choice');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $choiceSibling, $request, $directory, $now) === null,
        'choice-dependent estimate leaked to sibling'
    );

    // Verified exact quote always outranks cache and never seeds/rewrites it.
    $verified = ['state' => 'verified', 'fact' => null, 'verified_quote' => [
        'final_price' => ['amount' => '201000', 'currency' => 'RUB'],
        'final_price_verified' => true,
    ]];
    $beforeVerifiedWrites = $writes;
    $verifiedResult = AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
        $verified, $sibling, $request, $directory, $now, $meta, $writer
    );
    cache_autosave_assert($verifiedResult === $verified, 'verified exact pricing changed');
    cache_autosave_assert($writes === $beforeVerifiedWrites, 'verified exact pricing touched cache');

    // Any exact envelope, even one later rejected downstream, blocks cache fallback.
    $invalidExact = ['state' => 'unknown'];
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($invalidExact, $sibling, $request, $directory, $now) === $invalidExact,
        'cache overrode invalid exact pricing'
    );

    // Strict group mismatch must fail closed.
    $differentProgram = cache_autosave_offer('other-program', '190000', 'program_2');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $differentProgram, $request, $directory, $now) === null,
        'program mismatch reused cache'
    );
    $differentDate = cache_autosave_offer('other-date', '190000', 'program_1', '2026-10-31');
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $differentDate, $request, $directory, $now) === null,
        'date mismatch reused cache'
    );
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $sibling, ['departureId' => 2, 'countryId' => 4], $directory, $now) === null,
        'departure mismatch reused cache'
    );

    // Expiry is inherited from the original evidence; resolve never extends it.
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(null, $sibling, $request, $directory, $now + 120) === null,
        'expired cache reused'
    );

    // Invalid seed metadata and cache write failure are best-effort: exact pricing survives.
    $badMeta = $meta; $badMeta['source_sha'] = 'not-a-sha';
    $beforeBadMetaWrites = $writes;
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($exactEstimated, $sourceOffer, $request, $directory, $now, $badMeta, $writer) === $exactEstimated,
        'invalid metadata suppressed exact pricing'
    );
    cache_autosave_assert($writes === $beforeBadMetaWrites, 'invalid metadata wrote cache');

    $newerMeta = $meta;
    $newerMeta['observed_at'] = $now;
    $newerMeta['expires_at'] = $now + 60;
    $failingWriter = static function(string $path, array $value): bool { throw new RuntimeException('expected cache write failure'); };
    cache_autosave_assert(
        AnyTourAndromedaSurchargeCacheAutosaveV1::resolve($exactEstimated, $sourceOffer, $request, $directory, $now, $newerMeta, $failingWriter) === $exactEstimated,
        'cache write failure suppressed exact pricing'
    );

    echo "ANDROMEDA_SURCHARGE_CACHE_AUTOSAVE_OK exact=1 reuse=1 rebase=1 choice_exact_only=1 verified_priority=1 strict=3 expiry=1 fail_closed=2\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}
