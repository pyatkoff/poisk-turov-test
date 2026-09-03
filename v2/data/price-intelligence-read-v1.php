<?php
/** Read-only price history/intelligence endpoint for one exact comparable segment. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=900');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/price-intelligence-v1.php';

function price_intelligence_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$segment = strtolower(trim((string)($_GET['segment'] ?? '')));
if (!preg_match('/^[a-f0-9]{64}$/', $segment)) {
    price_intelligence_out(['ok' => false, 'error' => 'invalid segment'], 400);
}

$currentRaw = $_GET['currentPrice'] ?? null;
if (!is_scalar($currentRaw) || !is_numeric((string)$currentRaw) || (float)$currentRaw <= 0) {
    price_intelligence_out(['ok' => false, 'error' => 'invalid currentPrice'], 400);
}
$currentPrice = (float)$currentRaw;

$daysRaw = filter_var($_GET['days'] ?? 30, FILTER_VALIDATE_INT);
$days = $daysRaw === false ? 30 : max(7, min(90, (int)$daysRaw));
$fromDate = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');

try {
    $pdo = v2_data_db();
    $stmt = $pdo->prepare("SELECT price_date,min_price,median_price,max_price,observation_count,independent_search_count
        FROM tour_price_daily_exact
        WHERE segment_fingerprint=:segment AND price_date>=:from_date
        ORDER BY price_date ASC");
    $stmt->execute(['segment' => $segment, 'from_date' => $fromDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $summary = v2_price_intelligence_summary($rows, $currentPrice);
    $summary['segment'] = $segment;
    $summary['windowDays'] = $days;
    $summary['source'] = 'anytour-first-party-price-history';
    $summary['cachedPriceIsFinal'] = false;
    price_intelligence_out($summary);
} catch (Throwable $e) {
    error_log('price-intelligence-read-v1: ' . $e->getMessage());
    price_intelligence_out([
        'ok' => false,
        'error' => 'Price history is temporarily unavailable',
    ], 503);
}
