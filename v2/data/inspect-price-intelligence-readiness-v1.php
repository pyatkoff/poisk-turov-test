<?php
/** Read-only production readiness report for exact AnyTour price intelligence. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/price-intelligence-v1.php';

function pir_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return null;
}

function pir_int(?string $value, int $fallback, int $min, int $max): int
{
    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    return $parsed === false ? $fallback : max($min, min($max, (int)$parsed));
}

$days = pir_int(pir_arg($argv, 'days'), 30, 7, 90);
$exampleLimit = pir_int(pir_arg($argv, 'examples'), 10, 0, 50);
$fromDate = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');

try {
    $pdo = v2_data_db();
    $snapshotStmt = $pdo->query("SELECT page_key,page_type,offers_json,observed_at,expires_at
        FROM seo_offer_snapshots
        WHERE expires_at>=NOW() AND offer_count>0 AND currency='RUB'
        ORDER BY observed_at DESC,page_key ASC");
    $snapshotRows = $snapshotStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $currentOffers = [];
    $snapshotOfferRows = 0;
    foreach ($snapshotRows as $snapshot) {
        $offers = json_decode((string)($snapshot['offers_json'] ?? ''), true);
        if (!is_array($offers)) continue;
        foreach ($offers as $offer) {
            if (!is_array($offer)) continue;
            $snapshotOfferRows++;
            $segment = strtolower(trim((string)($offer['segmentFingerprint'] ?? '')));
            $price = (float)($offer['price'] ?? 0);
            if (!preg_match('/^[a-f0-9]{64}$/', $segment) || $price <= 0) continue;
            $observedAt = trim((string)($offer['observedAt'] ?? $snapshot['observed_at'] ?? ''));
            if (!isset($currentOffers[$segment]) || $observedAt > $currentOffers[$segment]['observedAt']) {
                $currentOffers[$segment] = [
                    'segment' => $segment,
                    'price' => $price,
                    'currency' => strtoupper(trim((string)($offer['currency'] ?? 'RUB'))),
                    'hotelId' => (int)($offer['hotelId'] ?? 0),
                    'hotelName' => trim((string)($offer['hotelName'] ?? '')),
                    'departureDate' => trim((string)($offer['departureDate'] ?? '')),
                    'nights' => (int)($offer['nights'] ?? 0),
                    'observedAt' => $observedAt,
                    'pageKey' => (string)($snapshot['page_key'] ?? ''),
                    'pageType' => (string)($snapshot['page_type'] ?? ''),
                ];
            }
        }
    }

    $historyBySegment = [];
    $segments = array_keys($currentOffers);
    foreach (array_chunk($segments, 400) as $chunk) {
        if ($chunk === []) continue;
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = "SELECT segment_fingerprint,price_date,min_price,median_price,max_price,observation_count,independent_search_count
            FROM tour_price_daily_exact
            WHERE segment_fingerprint IN ({$placeholders}) AND price_date>=?
            ORDER BY segment_fingerprint,price_date";
        $stmt = $pdo->prepare($sql);
        $params = array_merge($chunk, [$fromDate]);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $segment = (string)$row['segment_fingerprint'];
            $historyBySegment[$segment][] = $row;
        }
    }

    $stageCounts = [
        'collect_more' => 0,
        'good_price_only' => 0,
        'guarded_delta_ready' => 0,
        'history_ready' => 0,
        'invalid_history' => 0,
    ];
    $historySegments = 0;
    $dropReady = 0;
    $goodPrice = 0;
    $totalIndependentSearches = 0;
    $maxObservedDays = 0;
    $examples = [];

    foreach ($currentOffers as $segment => $offer) {
        $rows = $historyBySegment[$segment] ?? [];
        if ($rows !== []) $historySegments++;
        $summary = v2_price_intelligence_summary($rows, (float)$offer['price']);
        if (!($summary['ok'] ?? false)) {
            $stageCounts['invalid_history']++;
            continue;
        }
        $stage = (string)($summary['state'] ?? 'collect_more');
        if (!array_key_exists($stage, $stageCounts)) $stage = 'collect_more';
        $stageCounts[$stage]++;
        $totalIndependentSearches += (int)($summary['independentSearchCount'] ?? 0);
        $maxObservedDays = max($maxObservedDays, (int)($summary['observedDays'] ?? 0));
        if (($summary['goodPrice'] ?? false) === true) $goodPrice++;
        if (($summary['showHistoricalDrop'] ?? false) === true) {
            $dropReady++;
            $examples[] = [
                'hotelId' => $offer['hotelId'],
                'hotelName' => $offer['hotelName'],
                'departureDate' => $offer['departureDate'],
                'nights' => $offer['nights'],
                'currentPrice' => $summary['currentPrice'] ?? $offer['price'],
                'referencePrice' => $summary['referencePrice'] ?? null,
                'historicalDropPercent' => $summary['historicalDropPercent'] ?? null,
                'historicalLowPrice' => $summary['historicalLowPrice'] ?? null,
                'observedDays' => $summary['observedDays'] ?? 0,
                'independentSearchCount' => $summary['independentSearchCount'] ?? 0,
                'referenceMethod' => $summary['referenceMethod'] ?? null,
                'pageType' => $offer['pageType'],
                'pageKey' => $offer['pageKey'],
            ];
        }
    }

    usort($examples, static function (array $a, array $b): int {
        $drop = ((int)($b['historicalDropPercent'] ?? 0)) <=> ((int)($a['historicalDropPercent'] ?? 0));
        if ($drop !== 0) return $drop;
        return ((int)($b['independentSearchCount'] ?? 0)) <=> ((int)($a['independentSearchCount'] ?? 0));
    });
    if ($exampleLimit >= 0) $examples = array_slice($examples, 0, $exampleLimit);

    $report = [
        'state' => 'price_intelligence_readiness',
        'window_days' => $days,
        'from_date' => $fromDate,
        'reference_method' => 'max_of_daily_min_exact_comparable_segment',
        'historical_drop_gate' => [
            'minimum_independent_searches' => 15,
            'minimum_observed_days' => 7,
            'minimum_drop_percent' => 5,
        ],
        'fresh_snapshot_rows' => count($snapshotRows),
        'snapshot_offer_rows' => $snapshotOfferRows,
        'unique_current_exact_segments' => count($currentOffers),
        'segments_with_exact_history' => $historySegments,
        'segments_without_exact_history' => max(0, count($currentOffers) - $historySegments),
        'stage_counts' => $stageCounts,
        'good_price_ready' => $goodPrice,
        'historical_drop_ready' => $dropReady,
        'max_observed_days' => $maxObservedDays,
        'summed_independent_searches_across_current_segments' => $totalIndependentSearches,
        'examples' => $examples,
        'cached_price_is_final' => false,
    ];

    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'PRICE_INTELLIGENCE_READINESS_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
