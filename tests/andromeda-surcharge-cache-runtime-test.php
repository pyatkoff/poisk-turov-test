<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-surcharge-cache-runtime.php';

function cache_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function cache_runtime_offer(string $suffix, string $base, string $program = 'program_1'): array
{
    return [
        'provider' => 'andromeda',
        'operator_ref' => 'operator_1',
        'offer_ref' => 'offer_' . hash('sha256', 'runtime-offer-' . $suffix),
        'external_hotel_id' => 'hotel_' . $suffix,
        'room_raw' => 'room_' . $suffix,
        'meal' => ['raw_label' => 'AI_' . $suffix],
        'check_in' => '2026-10-30',
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

function cache_runtime_fact(string $base = '185125', string $surcharge = '14265', string $total = '199390'): array
{
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

function cache_runtime_write_sidecar(
    string $directory,
    int $created,
    array $context,
    array $fact,
    int $observedAt,
    int $expiresAt,
    array $overrides = []
): string {
    $runtime = dirname(__DIR__) . '/app/integrations/andromeda-saved-package-runtime.php';
    $record = [
        'version' => 1,
        'status' => 'complete',
        'source' => str_repeat('a', 40),
        'implementation_sha256' => hash_file('sha256', $runtime),
        'context' => $context,
        'snapshot_created_at' => $created,
        'observed_at' => $observedAt,
        'expires_at' => $expiresAt,
        'fact' => $fact,
    ];
    $record = array_replace($record, $overrides);
    $path = $directory . '/' . $context['search_ref'] . '-' . $created . '-' . $context['page']
        . '-' . $context['offer_ref'] . '-surcharge-v1.json';
    file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
    return $path;
}

$root = sys_get_temp_dir() . '/andromeda-cache-runtime-' . bin2hex(random_bytes(6));
$directory = $root . '/searches';
if (!mkdir($directory, 0700, true)) throw new RuntimeException('mkdir');
try {
    $now = 1800000000;
    $created = $now - 30;
    $request = ['departureId' => 1, 'countryId' => 4];
    $source = cache_runtime_offer('source', '185125');
    $context = [
        'provider' => 'andromeda',
        'search_ref' => hash('sha256', 'runtime-search'),
        'generation' => 17171850,
        'page' => 1,
        'offer_ref' => $source['offer_ref'],
    ];
    $fact = cache_runtime_fact();
    $exact = ['state' => 'estimated', 'fact' => $fact, 'verified_quote' => null];
    cache_runtime_write_sidecar($directory, $created, $context, $fact, $now - 5, $now + 120);

    $writes = 0;
    $writer = static function(string $path, array $value) use (&$writes): bool {
        ++$writes;
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return file_put_contents($path, $encoded, LOCK_EX) === strlen($encoded);
    };

    // Existing exact estimate remains authoritative and seeds exactly one group record.
    $resolved = anytour_andromeda_pricing_with_surcharge_cache(
        $exact, $directory, $created, $context, $source, $request, $now, $writer
    );
    cache_runtime_assert($resolved === $exact, 'exact estimate changed');
    cache_runtime_assert($writes === 1, 'valid exact sidecar did not seed cache');

    // Missing exact pricing may reuse the strict group surcharge against its own PRICE.
    $sibling = cache_runtime_offer('sibling', '190000');
    $siblingContext = $context;
    $siblingContext['offer_ref'] = $sibling['offer_ref'];
    $fallback = anytour_andromeda_pricing_with_surcharge_cache(
        null, $directory, $created, $siblingContext, $sibling, $request, $now, $writer
    );
    cache_runtime_assert(($fallback['state'] ?? null) === 'estimated', 'fallback state');
    cache_runtime_assert(array_key_exists('verified_quote', $fallback) && $fallback['verified_quote'] === null, 'fallback verified quote');
    cache_runtime_assert(($fallback['fact']['search_price']['amount'] ?? null) === '190000', 'fallback base price');
    cache_runtime_assert(($fallback['fact']['party_surcharge']['amount'] ?? null) === '14265', 'fallback surcharge');
    cache_runtime_assert(($fallback['fact']['search_price_with_surcharge']['amount'] ?? null) === '204265', 'fallback total');
    cache_runtime_assert(($fallback['fact']['final_price_verified'] ?? null) === false, 'fallback became final');

    // Verified exact pricing wins and never seeds group evidence.
    $verified = [
        'state' => 'verified',
        'fact' => null,
        'verified_quote' => [
            'final_price' => ['amount' => '201000', 'currency' => 'RUB'],
            'final_price_verified' => true,
        ],
    ];
    $beforeVerified = $writes;
    cache_runtime_assert(
        anytour_andromeda_pricing_with_surcharge_cache(
            $verified, $directory, $created, $context, $source, $request, $now, $writer
        ) === $verified,
        'verified exact changed'
    );
    cache_runtime_assert($writes === $beforeVerified, 'verified exact wrote cache');

    // Invalid/non-authoritative exact envelope still blocks cache fallback; downstream owns rejection.
    $unknown = ['state' => 'unknown'];
    cache_runtime_assert(
        anytour_andromeda_pricing_with_surcharge_cache(
            $unknown, $directory, $created, $context, $source, $request, $now, $writer
        ) === $unknown,
        'cache repaired non-null exact envelope'
    );

    // Sidecar provenance/timing mismatch cannot seed but must not suppress exact pricing.
    $isolatedDirectory = $root . '/isolated/searches';
    if (!mkdir($isolatedDirectory, 0700, true)) throw new RuntimeException('isolated mkdir');
    $badSource = cache_runtime_offer('bad-source', '185125', 'program_bad_source');
    $badContext = $context;
    $badContext['offer_ref'] = $badSource['offer_ref'];
    cache_runtime_write_sidecar(
        $isolatedDirectory, $created, $badContext, $fact, $now - 5, $now + 120,
        ['source' => 'not-a-sha']
    );
    $beforeBad = $writes;
    cache_runtime_assert(
        anytour_andromeda_pricing_with_surcharge_cache(
            $exact, $isolatedDirectory, $created, $badContext, $badSource, $request, $now, $writer
        ) === $exact,
        'bad provenance suppressed exact'
    );
    cache_runtime_assert($writes === $beforeBad, 'bad provenance seeded cache');
    cache_runtime_assert(
        anytour_andromeda_pricing_with_surcharge_cache(
            null, $isolatedDirectory, $created, $badContext, $badSource, $request, $now, $writer
        ) === null,
        'bad provenance created cache fallback'
    );

    $staleDirectory = $root . '/stale/searches';
    if (!mkdir($staleDirectory, 0700, true)) throw new RuntimeException('stale mkdir');
    $staleSource = cache_runtime_offer('stale', '185125', 'program_stale');
    $staleContext = $context;
    $staleContext['offer_ref'] = $staleSource['offer_ref'];
    cache_runtime_write_sidecar($staleDirectory, $created, $staleContext, $fact, $now - 200, $now - 1);
    $beforeStale = $writes;
    cache_runtime_assert(
        anytour_andromeda_pricing_with_surcharge_cache(
            $exact, $staleDirectory, $created, $staleContext, $staleSource, $request, $now, $writer
        ) === $exact,
        'stale sidecar suppressed exact'
    );
    cache_runtime_assert($writes === $beforeStale, 'stale sidecar seeded cache');

    $mismatchDirectory = $root . '/mismatch/searches';
    if (!mkdir($mismatchDirectory, 0700, true)) throw new RuntimeException('mismatch mkdir');
    $mismatchSource = cache_runtime_offer('mismatch', '185125', 'program_mismatch');
    $mismatchContext = $context;
    $mismatchContext['offer_ref'] = $mismatchSource['offer_ref'];
    $otherFact = cache_runtime_fact('185125', '10000', '195125');
    cache_runtime_write_sidecar($mismatchDirectory, $created, $mismatchContext, $otherFact, $now - 5, $now + 120);
    $beforeMismatch = $writes;
    cache_runtime_assert(
        anytour_andromeda_pricing_with_surcharge_cache(
            $exact, $mismatchDirectory, $created, $mismatchContext, $mismatchSource, $request, $now, $writer
        ) === $exact,
        'fact mismatch suppressed exact'
    );
    cache_runtime_assert($writes === $beforeMismatch, 'fact mismatch seeded cache');

    // Strict group mismatch still fails closed after a valid seed.
    $otherProgram = cache_runtime_offer('other-program', '190000', 'program_2');
    $otherContext = $context;
    $otherContext['offer_ref'] = $otherProgram['offer_ref'];
    cache_runtime_assert(
        anytour_andromeda_pricing_with_surcharge_cache(
            null, $directory, $created, $otherContext, $otherProgram, $request, $now, $writer
        ) === null,
        'strict group mismatch reused cache'
    );

    echo "ANDROMEDA_SURCHARGE_CACHE_RUNTIME_OK exact=1 seed=1 fallback=1 rebase=1 verified_priority=1 provenance=1 stale=1 mismatch=2\n";
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
