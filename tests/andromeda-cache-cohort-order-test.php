<?php
declare(strict_types=1);

// Reuse the existing real autosave/producer fixtures and run their regressions too.
// All writes are disposable local fixtures; ingestor and supplier transport are not live.
require __DIR__ . '/andromeda-anytour-offer-autosave-test.php';
require_once dirname(__DIR__) . '/app/integrations/andromeda-surcharge-cache-autosave.php';

function order_offer(int $id, string $base, string $program = 'program_1'): array
{
    $offer = normalized_offer('order-' . $id, 'FUN&SUN', $id, (string)($id + 1000), $base);
    $offer['provider'] = 'andromeda';
    $offer['operator_ref'] = 'operator_1';
    $offer['transport_context'] = [
        'freight_external' => true, 'program_ref' => $program,
        'tour_ref' => 'tour_1', 'spo_ref' => 'spo_' . $id,
    ];
    return $offer;
}

function order_verified(array $offer): array
{
    return ['state' => 'verified', 'fact' => null, 'verified_quote' => [
        'schema_version' => 1, 'provider' => 'andromeda',
        'selection_enabled' => true, 'booking_enabled' => false,
        'local_id' => $offer['local_hotel_id'], 'operator' => 'FUN&SUN',
        'search_price' => $offer['price'], 'package_price' => $offer['price'],
        'state' => 'quote_verified', 'quote_state' => 'verified',
        'final_price' => ['amount' => '223000', 'currency' => 'RUB'],
        'final_price_verified' => true, 'flight_selection_required' => false, 'flights' => [],
    ]];
}

function order_case(array $order, bool $validSeed = true, bool $missingPage = false, bool $mappingValid = true): array
{
    $dir = temp_searches();
    try {
        $request = search_request(); $ref = hash('sha256', 'order-' . implode('-', $order));
        $now = time(); $created = $now - 30; $ingests = [];
        $offers = [
            101 => order_offer(101, '190000'),
            102 => order_offer(102, '185125'),
            103 => order_offer(103, '200000'),
            104 => order_offer(104, '210000', 'different_program'),
            105 => order_offer(105, '220000'),
        ];
        $ordered = array_map(static fn(int $id): array => $offers[$id], $order);
        // Put the late specimen on a separate page as well: no page-local shortcut.
        $first = array_slice($ordered, 0, 2); $second = array_slice($ordered, 2);
        write_state($dir, $ref, $created, 1, state($ref, 1, 1, 2, $created, $first));
        if (!$missingPage) write_state($dir, $ref, $created, 2, state($ref, 1, 2, 2, $created, $second));
        [$mapping, $canonical, , $save, $ingest] = callbacks($ingests, null, $mappingValid);
        $reads = []; $cacheWrites = 0;
        $writer = static function(string $path, array $value) use (&$cacheWrites): bool {
            ++$cacheWrites;
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            return file_put_contents($path, $json, LOCK_EX) === strlen($json);
        };
        $pricing = static function(array $state, int $created, array $offer, array $current) use (
            &$reads, $request, $dir, $ref, $now, $validSeed, $writer
        ): ?array {
            $id = $offer['local_hotel_id']; $reads[$id] = ($reads[$id] ?? 0) + 1;
            $exact = $id === 102
                ? ['state' => 'estimated', 'fact' => party_surcharge(), 'verified_quote' => null]
                : ($id === 105 ? order_verified($offer) : null);
            $meta = $id === 102 ? [
                'source_sha' => $validSeed ? str_repeat('a', 40) : 'invalid',
                'source_search_ref' => $ref, 'source_offer_ref' => $offer['offer_ref'],
                'observed_at' => $now - 5, 'expires_at' => $now + 120,
            ] : null;
            return AnyTourAndromedaSurchargeCacheAutosaveV1::resolve(
                $exact, $offer, $request['params'], $dir, $now, $meta, $writer
            );
        };
        $result = AnyTourAndromedaOfferAutosaveV1::consume($request, $dir, $ref, 1,
            new DateTimeImmutable('@' . $now), $mapping, $canonical, $pricing, $save, $ingest);
        if ($missingPage || !$mappingValid) {
            aassert($result['published'] === false && $ingests === [] && $reads === [] && $cacheWrites === 0,
                'invalid cohort or mapping reached pricing/cache/ingest');
            return [];
        }
        aassert(($result['published'] ?? false) === true && count($ingests) === 1, 'order fixture not published');
        $prices = []; $verified = [];
        foreach ($ingests[0]['rows'] as $row) {
            $dto = $row['dto']; $id = $dto['local_hotel_id'];
            $prices[$id] = $dto['price']; $verified[$id] = $dto['final_price_verified'];
            aassert($dto['booking_enabled'] === false && $dto['selection_state'] === 'disabled', 'cache gained authority');
        }
        ksort($prices); ksort($verified);
        $expected = $validSeed
            ? [101 => '204265', 102 => '199390', 103 => '214265', 105 => '223000']
            : [102 => '199390', 105 => '223000'];
        aassert($prices === $expected, 'cohort pricing depends on specimen order: ' . json_encode($prices));
        aassert($verified[105] === true, 'verified exact quote lost');
        foreach ($verified as $id => $isVerified) if ($id !== 105) aassert($isVerified === false, 'estimate became verified');
        aassert(!isset($prices[104]), 'mismatched transport group admitted');
        aassert(($reads[102] ?? 0) === 1 && ($reads[105] ?? 0) === 1, 'non-null exact pricing reread');
        aassert(max($reads) <= 2 && array_sum($reads) <= 2 * count($ordered), 'unbounded pricing scan');
        aassert($cacheWrites === ($validSeed ? 1 : 0), 'cache write count/invalid provenance');
        $again = AnyTourAndromedaOfferAutosaveV1::consume($request, $dir, $ref, 1,
            new DateTimeImmutable('@' . $now), $mapping, $canonical, $pricing, $save, $ingest);
        aassert(($again['reason'] ?? null) === 'already_published' && count($ingests) === 1, 'stable cohort republished');
        return $prices;
    } finally { cleanup_dir($dir); }
}

$early = order_case([102, 101, 103, 104, 105]);
$late = order_case([101, 103, 104, 105, 102]);
$middle = order_case([101, 105, 102, 104, 103]);
aassert($early === $late && $early === $middle, 'cohort permutations differ');
order_case([101, 103, 104, 105, 102], false);
order_case([101, 103, 104, 105, 102], true, true);
order_case([101, 103, 104, 105, 102], true, false, false);
echo "ANDROMEDA_CACHE_COHORT_ORDER_OK permutations=3 cross_page=1 rebase=2 exact_priority=2 invalid_seed=1 incomplete=1 unmapped=1 supplier_calls=0 live_db_writes=0\n";
