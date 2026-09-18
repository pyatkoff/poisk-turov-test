<?php
/**
 * One-shot LOCAL operation: read CURRENT saved offers and compile an offline hotel-local
 * room/meal review packet. The only write is the requested operation artifact under an
 * ephemeral output directory; the site DB and site tree stay read-only.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/anytour_hotel_stay_offer_candidates_v2.php';
require_once __DIR__ . '/anytour_hotel_stay_review_packet_v2.php';

function stay_frontier_fail(string $code): never
{
    fwrite(STDERR, "ANYTOUR_HOTEL_STAY_REVIEW_FRONTIER_FAILED code={$code}\n");
    exit(1);
}

function stay_frontier_json(array $value): string
{
    ksort($value, SORT_STRING);
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

try {
    $releaseSha = '';
    $limit = AnyTourHotelStayOfferCandidatesV2::LIMIT;
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--release-sha=([a-f0-9]{40})$/D', $arg, $m) && $releaseSha === '') {
            $releaseSha = $m[1];
        } elseif (preg_match('/^--limit=([0-9]+)$/D', $arg, $m)) {
            $limit = (int)$m[1];
        } else {
            stay_frontier_fail('USAGE');
        }
    }
    if ($releaseSha === '' || $limit < 1 || $limit > AnyTourHotelStayOfferCandidatesV2::LIMIT) {
        stay_frontier_fail('USAGE');
    }

    $runtimeSha = trim((string)getenv('ANYTOUR_OPERATION_RELEASE_SHA'));
    if ($runtimeSha === '' || !hash_equals($releaseSha, $runtimeSha)) {
        stay_frontier_fail('RELEASE_SHA_MISMATCH');
    }

    $siteRoot = rtrim((string)getenv('ANYTOUR_SITE_ROOT'), "/\\");
    $outputDir = rtrim((string)getenv('ANYTOUR_OPERATION_OUTPUT_DIR'), "/\\");
    if ($siteRoot === '' || $outputDir === '' || !is_dir($outputDir)) {
        stay_frontier_fail('OPERATION_ENV');
    }
    $resolvedOutput = realpath($outputDir);
    if ($resolvedOutput === false || !is_dir($resolvedOutput) || is_link($outputDir)) {
        stay_frontier_fail('OUTPUT_DIR');
    }

    $dbHelper = $siteRoot . '/data/db-v1.php';
    if (!is_file($dbHelper)) stay_frontier_fail('DB_HELPER_MISSING');
    require_once $dbHelper;
    if (!function_exists('v2_data_db')) stay_frontier_fail('DB_HELPER_API');

    $db = v2_data_db();
    if (!$db instanceof PDO) stay_frontier_fail('DB_HANDLE');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $inventory = (new AnyTourHotelStayOfferCandidatesV2($db))->collect($limit);
    $packet = AnyTourHotelStayReviewPacketV2::build($inventory, 100);

    $actionableRoom = 0;
    $actionableMeal = 0;
    $hotels = [];
    $providers = [];
    foreach ($packet['batches'] as $batch) {
        foreach ($batch['items'] as $item) {
            if (($item['kind'] ?? null) === 'room') ++$actionableRoom;
            elseif (($item['kind'] ?? null) === 'meal') ++$actionableMeal;
            $hotelId = (int)($item['anytourHotelId'] ?? 0);
            if ($hotelId > 0) $hotels[$hotelId] = true;
            $provider = (string)($item['provider'] ?? '');
            if ($provider !== '') $providers[$provider] = ($providers[$provider] ?? 0) + 1;
        }
    }
    ksort($providers, SORT_STRING);

    $packetPath = $resolvedOutput . '/review-packet.json';
    if (file_exists($packetPath) || is_link($packetPath)) stay_frontier_fail('OUTPUT_EXISTS');
    $packetJson = json_encode(
        $packet,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    umask(0077);
    if (file_put_contents($packetPath, $packetJson, LOCK_EX) !== strlen($packetJson)) {
        stay_frontier_fail('OUTPUT_WRITE');
    }

    echo stay_frontier_json([
        'schema_version' => 1,
        'operation' => 'anytour-hotel-stay-review-frontier-live-v2',
        'mode' => 'read_only_current_offers_to_offline_review_packet',
        'release_sha' => $releaseSha,
        'inventory_generated_at' => (string)$inventory['generatedAt'],
        'current_identity_validated_offers' => (int)$inventory['currentIdentityValidatedOffers'],
        'total_exact_cohorts' => (int)$inventory['totalExactCohorts'],
        'returned_cohorts' => (int)$inventory['returnedCohorts'],
        'review_needed_cohorts' => (int)$inventory['reviewNeededCohorts'],
        'held_cohorts' => (int)$inventory['heldCohorts'],
        'existing_negative_cohorts' => (int)$inventory['existingNegativeCohorts'],
        'actionable_items' => (int)$packet['actionableItemCount'],
        'actionable_rooms' => $actionableRoom,
        'actionable_meals' => $actionableMeal,
        'actionable_hotels' => count($hotels),
        'actionable_by_provider' => $providers,
        'blocked_items' => (int)$packet['blockedItemCount'],
        'packet_sha256' => (string)$packet['packetSha256'],
        'packet_file_sha256' => hash('sha256', $packetJson),
        'inventory_truncated' => (int)$inventory['totalExactCohorts'] > (int)$inventory['returnedCohorts'],
        'database_writes' => 0,
        'mapping_writes' => 0,
        'supplier_calls' => 0,
        'automatic_decisions' => 0,
        'site_file_writes' => 0,
    ]) . PHP_EOL;
} catch (Throwable $error) {
    $message = strtoupper((string)$error->getMessage());
    if ($error instanceof RuntimeException && preg_match('/^[A-Z][A-Z0-9_]{0,95}$/D', $message)) {
        stay_frontier_fail($message);
    }
    stay_frontier_fail('UNEXPECTED_' . preg_replace('/[^A-Z0-9_]/', '_', strtoupper(get_class($error))));
}
