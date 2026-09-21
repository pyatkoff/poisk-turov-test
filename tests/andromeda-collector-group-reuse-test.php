<?php
declare(strict_types=1);

// Real collector + existing private evidence store. All search, package and final
// intake boundaries are local closures; no supplier/production DB is contacted.
require_once __DIR__ . '/../app/integrations/andromeda-local-offer-collector.php';
require_once __DIR__ . '/../app/integrations/andromeda-surcharge-evidence-store.php';

$checks = 0;
function reuse_check(bool $ok, string $message): void {
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException($message);
}
function reuse_offer(int $id): array {
    return [
        'provider' => 'andromeda', 'offer_ref' => 'offer_' . hash('sha256', 'reuse-' . $id),
        'local_hotel_id' => 1000 + $id, 'operator' => 'FUN&SUN', 'operator_ref' => '315',
        'check_in' => '2026-10-07', 'nights' => 7, 'adults' => 2, 'children' => 0,
        'hotel' => 'Synthetic hotel ' . $id, 'room' => 'Room ' . $id,
        'meal' => $id % 2 === 0 ? 'AI' : 'UAI',
        'price' => ['amount' => (string)(100000 + $id), 'currency' => 'RUB'],
        'transport_context' => ['freight_external' => true, 'program_ref' => '5',
            'tour_ref' => '3005', 'spo_ref' => 'different-spo-' . $id],
    ];
}
function reuse_rows(array $offers): array {
    return array_map(static fn(array $offer): array => ['page' => 1, 'offer' => $offer], $offers);
}
function reuse_fact(array $offer): array {
    $base = $offer['price'];
    return ['schema_version' => 1, 'provider' => 'andromeda', 'state' => 'estimated',
        'search_price' => $base,
        'party_surcharge' => ['amount' => '15000.50', 'currency' => 'RUB',
            'source' => 'andromeda_get_flights_transport'],
        'search_price_with_surcharge' => ['amount' => ((int)$base['amount'] + 15000) . '.50',
            'currency' => 'RUB', 'source' => 'derived_search_estimate'],
        'surcharge_scope' => 'party', 'arithmetic_applied' => true, 'final_price_verified' => false];
}
function reuse_seed(string $directory, array $offer, array $request, int $now): void {
    $evidence = AnyTourAndromedaSurchargeEvidenceV1::capture($offer, $request, reuse_fact($offer), $now, $now + 300);
    reuse_check(is_array($evidence), 'Actual evidence owner must admit fixture');
    AnyTourAndromedaSurchargeEvidenceStoreV1::save($directory, $evidence,
        ['source_sha' => str_repeat('a', 40), 'source_search_ref' => str_repeat('b', 64),
            'source_offer_ref' => $offer['offer_ref']],
        static function(string $path, array $value): bool {
            return file_put_contents($path, json_encode($value, JSON_THROW_ON_ERROR)) !== false;
        });
}
function reuse_collect(array $request, array $offers, callable $capture, callable $autosave,
    int $budget = 100, ?callable $cached = null, string $mode = 'all', ?callable $allowed = null): array {
    return AnyTourAndromedaLocalOfferCollectorV1::collect($request,
        static fn(array $r): array => ['provider' => 'andromeda', 'search_ref' => str_repeat('b', 64),
            'pages_count' => 1, 'status' => 'complete'],
        static fn(string $ref, int $generation): array => reuse_rows($offers),
        $allowed ?? static fn(array $selection, array $offer): bool => true,
        $capture, $autosave, $budget, $mode, 0, null, $cached);
}

$request = ['generation' => 31, 'params' => ['departureId' => '1', 'countryId' => '4', 'childs' => []]];
$offers = array_map('reuse_offer', range(1, 100));
$before = $offers;
$captures = 0; $saves = 0;
$save = static function(array $r, string $ref, int $generation) use (&$saves, $request): array {
    reuse_check($r === $request && $ref === str_repeat('b', 64) && $generation === 31, 'Unchanged autosave scope');
    ++$saves;
    // Receipt fixture is explicitly non-final; grouping never grants price authority.
    return ['published' => true, 'readyOfferCount' => 0, 'confirmationRequiredOfferCount' => 100];
};
$unavailable = static function(array $selection) use (&$captures): array {
    ++$captures;
    return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
};
$result = reuse_collect($request, $offers, $unavailable, $save);
reuse_check($captures === 1, 'SAME_GROUP_RECAPTURE: expected 1 capture, got ' . $captures);
reuse_check($result['capture_queue_offers'] === 1 && $result['surcharge_capture_attempts'] === 1,
    'Queue and actual capture counts');
reuse_check($result['surcharge_group_duplicate_skips'] === 99 && $result['reusable_surcharge_groups'] === 1,
    'Duplicate/group metrics count compatible external groups only');
reuse_check($offers === $before && $saves === 1 && $result['eligible_offers'] === 100,
    'All offers retained for the unchanged full-cohort autosave');
reuse_check($result['ready_offer_count'] === 0 && $result['surcharge_ready'] === 0,
    'Skipped siblings are never marked final-price-ready');

$root = sys_get_temp_dir() . '/andromeda-reuse-' . bin2hex(random_bytes(8));
$directory = $root . '/searches';
mkdir($directory, 0700, true);
$now = 1800000000;
try {
    reuse_seed($directory, $offers[0], $request, $now);
    $cacheChecks = 0;
    $hasCached = static function(array $selection, array $offer, array $r) use (&$cacheChecks, $directory, $now): bool {
        ++$cacheChecks;
        reuse_check($selection['offer_ref'] === $offer['offer_ref'], 'Cache checked for same selected representative');
        return AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($directory, $offer, $r, $now) !== null;
    };
    $captures = 0; $saves = 0; $applied = 0;
    $saveAll = static function(array $r, string $ref, int $generation) use (
        &$saves, &$applied, $directory, $offers, $now, $request
    ): array {
        reuse_check($r === $request, 'Cached autosave receives original search');
        ++$saves;
        foreach ($offers as $offer) {
            $fact = AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($directory, $offer, $r, $now);
            reuse_check(is_array($fact), 'Actual store applies one retained surcharge to every compatible offer');
            reuse_check($fact['search_price'] === $offer['price'], 'Own base preserved');
            reuse_check($fact['search_price_with_surcharge']['amount'] === ((int)$offer['price']['amount'] + 15000) . '.50',
                'Add party surcharge exactly once, never copy first offer total');
            reuse_check($fact['final_price_verified'] === false && !isset($fact['fuel_surcharge']),
                'Transport estimate is not fuel or individual final verification');
            ++$applied;
        }
        return ['published' => true, 'readyOfferCount' => 0, 'confirmationRequiredOfferCount' => count($offers)];
    };
    $cachedResult = reuse_collect($request, $offers, $unavailable, $saveAll, 100, $hasCached);
    reuse_check($captures === 0 && $cacheChecks === 1 && $applied === 100 && $saves === 1,
        'One valid group cache check avoids all capture calls and still processes 100 offers');
    reuse_check($cachedResult['surcharge_cache_checks'] === 1 && $cachedResult['surcharge_cache_hits'] === 1
        && $cachedResult['surcharge_capture_attempts'] === 0 && $cachedResult['surcharge_ready'] === 0,
        'Cache hits are groups, not invented verified offers');

    // Different operator/program/tour/date/route/currency/party stay distinct.
    $otherProgram = reuse_offer(101); $otherProgram['transport_context']['program_ref'] = '6';
    $variants = [$otherProgram];
    foreach (['operator_ref' => '342', 'check_in' => '2026-10-08', 'adults' => 3] as $key => $value) {
        $variant = reuse_offer(110 + count($variants)); $variant[$key] = $value; $variants[] = $variant;
    }
    $tour = reuse_offer(120); $tour['transport_context']['tour_ref'] = '3006'; $variants[] = $tour;
    $currency = reuse_offer(121); $currency['price']['currency'] = 'USD'; $variants[] = $currency;
    // Old flight-specific evidence is NOT relabelled as fixed program evidence.
    foreach ([10, 14] as $nights) {
        $variant = reuse_offer(130 + $nights); $variant['nights'] = $nights; $variants[] = $variant;
    }
    foreach ($variants as $variant) {
        reuse_check(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($directory, $variant, $request, $now) === null,
            'Do not broaden existing flight evidence scope');
    }
    foreach (['departureId' => '2', 'countryId' => '5'] as $field => $value) {
        $different = $request; $different['params'][$field] = $value;
        reuse_check(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($directory, $offers[0], $different, $now) === null,
            'Route-scoped cache');
    }
    $family = reuse_offer(150); $family['children'] = 2;
    $familyRequest = $request; $familyRequest['params']['childs'] = [3, 7];
    reuse_seed($directory, $family, $familyRequest, $now);
    $otherAges = $familyRequest; $otherAges['params']['childs'] = [3, 8];
    reuse_check(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($directory, $family, $otherAges, $now) === null,
        'Different child ages never reuse party surcharge');
    $reversed = $familyRequest; $reversed['params']['childs'] = [7, 3];
    reuse_check(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($directory, $family, $reversed, $now) !== null,
        'Same children order is nonsemantic');

    $captures = 0; $cacheChecks = 0;
    $mixed = reuse_collect($request, [$offers[0], $otherProgram], $unavailable, $save, 1, $hasCached);
    reuse_check($captures === 1 && $cacheChecks === 2 && $mixed['surcharge_cache_hits'] === 1,
        'Cached group does not consume capture budget of another group');

    // Expiry/malformed files cannot authorize reuse; no stale quote is promoted.
    $expired = static fn(array $selection, array $offer, array $r): bool =>
        AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($directory, $offer, $r, $now + 300) !== null;
    $captures = 0;
    $expiredResult = reuse_collect($request, $offers, $unavailable, $save, 100, $expired);
    reuse_check($captures === 1 && $expiredResult['surcharge_cache_hits'] === 0,
        'Expired group evidence is not reused and still causes at most one representative attempt');
    foreach (glob($directory . '/*.json') as $path) file_put_contents($path, '{}');
    $captures = 0;
    $invalidResult = reuse_collect($request, $offers, $unavailable, $save, 100, $hasCached);
    reuse_check($captures === 1 && $invalidResult['surcharge_cache_hits'] === 0, 'Corrupt cache cannot authorize reuse');

    // Unknown/failed first representative never falls back to another sibling.
    $failedCalls = [];
    $failure = static function(array $selection) use (&$failedCalls, $offers): array {
        $failedCalls[] = $selection['offer_ref'];
        if ($selection['offer_ref'] === $offers[0]['offer_ref']) throw new RuntimeException('ANDROMEDA_PACKAGE_OUTCOME_UNKNOWN');
        return ['status' => 'captured', 'surcharge' => ['status' => 'unavailable', 'fact' => null]];
    };
    $failed = reuse_collect($request, array_merge($offers, [$otherProgram]), $failure, $save);
    reuse_check($failedCalls === [$offers[0]['offer_ref'], $otherProgram['offer_ref']]
        && $failed['surcharge_capture_attempts'] === 2, 'Unknown sealed group only continues to disjoint group');
    $failedCalls = [];
    $badReceipt = static function(array $selection) use (&$failedCalls): array {
        $failedCalls[] = $selection['offer_ref']; return ['status' => 'failed'];
    };
    reuse_collect($request, $offers, $badReceipt, $save);
    reuse_check(count($failedCalls) === 1, 'Failed result is not permission to recapture siblings');

    $captures = 0; $cacheChecks = 0;
    $zero = reuse_collect($request, $offers, $unavailable, $save, 0, $hasCached);
    reuse_check($captures === 0 && $cacheChecks === 0 && $zero['surcharge_cache_checks'] === 0,
        'Zero capture budget performs no cache preflight or capture');
    $rejected = reuse_collect($request, $offers, $unavailable, $save, 100, $hasCached, 'all', static fn(): bool => false);
    reuse_check($captures === 0 && $rejected['eligible_offers'] === 0, 'Unmapped offers never enter capture/cache');

    // Nonexternal/unknown legacy grouping is scheduling only, not reusable evidence.
    foreach ([false, null] as $freight) {
        $legacy = [$offers[0], $offers[1]];
        foreach ($legacy as &$offer) $offer['transport_context']['freight_external'] = $freight;
        unset($offer);
        $captures = 0; $cacheChecks = 0;
        $legacyResult = reuse_collect($request, $legacy, $unavailable, $save, 2, $hasCached);
        reuse_check($captures === 2 && $cacheChecks === 0 && $legacyResult['reusable_surcharge_groups'] === 0,
            'Do not treat unproven legacy groups as common program surcharges');
    }
    $malformed = [$offers[0], $offers[1]];
    foreach ($malformed as &$offer) unset($offer['transport_context']['program_ref']);
    unset($offer);
    $captures = 0; $cacheChecks = 0;
    $unkeyed = reuse_collect($request, $malformed, $unavailable, $save, 100, $hasCached);
    reuse_check($captures === 2 && $cacheChecks === 0 && $unkeyed['surcharge_group_duplicate_skips'] === 0,
        'Unkeyable candidates keep independent no-evidence buckets');
    $captures = 0;
    try {
        reuse_collect($request, [$offers[0]], $unavailable, $save, 1, static fn() => 'yes');
        throw new LogicException('Nonboolean cache result accepted');
    } catch (RuntimeException $error) {
        reuse_check($error->getMessage() === 'ANDROMEDA_LOCAL_COLLECTOR_CACHE_RESULT' && $captures === 0,
            'Invalid cache callback fails before supplier capture');
    }
    $ticks = [100.0, 341.0]; $captures = 0;
    $budgeted = AnyTourAndromedaLocalOfferCollectorV1::collect($request,
        static fn(): array => ['provider' => 'andromeda', 'search_ref' => str_repeat('b', 64), 'pages_count' => 1, 'status' => 'complete'],
        static fn(): array => reuse_rows([$offers[0], $otherProgram]),
        static fn(): bool => true, $unavailable, $save, 1, 'all', 240,
        static function() use (&$ticks): float { return array_shift($ticks); },
        static fn(): bool => true);
    reuse_check($captures === 0 && $budgeted['surcharge_cache_hits'] === 1
        && $budgeted['capture_time_budget_exhausted'] === true, 'Cache preflight time also respects capture deadline');

    // Execute the actual CLI unchanged except for its injected boundaries. The
    // collector double checks the new seam against the REAL existing store.
    foreach (glob($directory . '/*.json') as $path) unlink($path);
    reuse_seed($directory, $offers[0], $request, $now);
    $cli = $root . '/cli';
    $put = static function(string $path, string $bytes): void {
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
        if (file_put_contents($path, $bytes) === false) throw new RuntimeException('CLI fixture write');
    };
    $put($cli . '/scripts/ops/andromeda_local_offer_collect.php',
        (string)file_get_contents(__DIR__ . '/../scripts/ops/andromeda_local_offer_collect.php'));
    // CLI clock is real. Seed only this disposable fixture at the real current time.
    foreach (glob($directory . '/*.json') as $path) unlink($path);
    reuse_seed($directory, $offers[0], $request, time());
    $offerBytes = var_export($offers[0], true);
    $put($cli . '/app/integrations/andromeda-local-offer-collector.php', '<?php
final class AnyTourAndromedaLocalOfferCollectorV1 {
    public static function collect($request,$search,$load,$allow,$capture,$autosave,$max,$mode,$seconds,$clock,$cached): array {
        $offer = ' . $offerBytes . ';
        if (!is_callable($cached) || $clock !== null || $max !== 1 || $mode !== "all") throw new RuntimeException("CLI cache seam absent");
        $hit = $cached([], $offer, $request);
        $offer["transport_context"]["program_ref"] = "other";
        $miss = $cached([], $offer, $request);
        if ($hit !== true || $miss !== false) throw new RuntimeException("CLI actual cache reader binding failed");
        return ["status"=>"complete","fixture_only"=>true,"cache_hit"=>$hit,"cache_miss"=>!$miss];
    }
}');
    $put($cli . '/v2/api-andromeda-search3-preview.php', '<?php
function anytour_andromeda_search3_catalog($config,$request):array {return [];}
');
    $put($cli . '/app/integrations/andromeda-saved-package-runtime.php', '<?php // No package operation in CLI fixture.');
    $put($cli . '/app/integrations/andromeda-anytour-offer-autosave-cache-runtime.php', '<?php require_once '
        . var_export(realpath(__DIR__ . '/../app/integrations/andromeda-surcharge-evidence-store.php'), true) . ';');
    $site = $root . '/anytoour.ru';
    $put($site . '/config.php', '<?php // No production config.');
    $put($site . '/data/db-v1.php', '<?php
final class ReusePdoFixture extends PDO {
    public function __construct() {}
    public function setAttribute(int $attribute,mixed $value):bool {return true;}
}
function v2_data_db():PDO {return new ReusePdoFixture();}
');
    $put($site . '/_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php', '<?php // No database writes.');
    $put($root . '/config.php', '<?php return ' . var_export(['enabled'=>true,'catalog_path'=>$root.'/catalog.json'], true) . ';');
    $command = [PHP_BINARY, '-d', 'allow_url_fopen=0', $cli . '/scripts/ops/andromeda_local_offer_collect.php',
        '--site-root=' . $site, '--private-config=' . $root . '/config.php', '--source-sha=' . str_repeat('a', 40),
        '--date-from=2026-10-07', '--nights=7', '--generation=31', '--departure=1', '--country=4',
        '--max-captures=1', '--capture-mode=all'];
    $process = proc_open($command, [0=>['file','/dev/null','r'],1=>['file',$root.'/stdout','w'],2=>['file',$root.'/stderr','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('CLI fixture process');
    $exit = proc_close($process);
    reuse_check($exit === 0, 'Actual CLI cache preflight: ' . file_get_contents($root . '/stderr'));
    $output = json_decode((string)file_get_contents($root . '/stdout'), true, 32, JSON_THROW_ON_ERROR);
    reuse_check($output === ['status'=>'complete','fixture_only'=>true,'cache_hit'=>true,'cache_miss'=>true],
        'Actual CLI invokes existing cache reader with correct program and own price');
    reuse_check($offers === $before, 'Input offers remain immutable after all paths');
    echo 'ANDROMEDA_COLLECTOR_GROUP_REUSE_OK checks=' . $checks
        . ' compatible_offers=100 uncached_capture_callbacks=1 cached_capture_callbacks=0 cache_hits=1'
        . ' existing_evidence_applications=100 supplier_http=0 live_db_writes=0'
        . ' cross_night_fixed_evidence=not_proven no_final_price_promotion=1' . PHP_EOL;
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname());
    }
    rmdir($root);
}
