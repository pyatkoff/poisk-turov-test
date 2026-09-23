<?php
/** Read-only consumer price history for one comparable stay, ignoring tour operator. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=900');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/price-segment-v1.php';
require_once __DIR__ . '/price-consumer-intelligence-v1.php';

function price_consumer_intelligence_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function price_consumer_intelligence_int(string $key, int $min, int $max): int
{
    $raw = $_GET[$key] ?? null;
    if (!is_scalar($raw) || !preg_match('/^(?:0|[1-9][0-9]*)$/D', (string)$raw)) {
        throw new InvalidArgumentException('invalid ' . $key);
    }
    $value = (int)$raw;
    if ($value < $min || $value > $max) throw new InvalidArgumentException('invalid ' . $key);
    return $value;
}

function price_consumer_intelligence_date(string $key): string
{
    $raw = trim((string)($_GET[$key] ?? ''));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $raw) {
        throw new InvalidArgumentException('invalid ' . $key);
    }
    return $raw;
}

try {
    $departureId = price_consumer_intelligence_int('departureId', 1, PHP_INT_MAX);
    $hotelId = price_consumer_intelligence_int('hotelId', 1, PHP_INT_MAX);
    $departureDate = price_consumer_intelligence_date('departureDate');
    $nights = price_consumer_intelligence_int('nights', 1, 28);
    $adults = price_consumer_intelligence_int('adults', 1, 6);
    $childrenCount = price_consumer_intelligence_int('childrenCount', 0, 3);
    $mealId = price_consumer_intelligence_int('mealId', 0, PHP_INT_MAX);
    $roomId = price_consumer_intelligence_int('roomId', 0, PHP_INT_MAX);

    $childAges = trim((string)($_GET['childAges'] ?? ''));
    if ($childrenCount === 0) {
        if ($childAges !== '') throw new InvalidArgumentException('invalid childAges');
    } else {
        $ages = explode(',', $childAges);
        if (count($ages) !== $childrenCount) throw new InvalidArgumentException('invalid childAges');
        foreach ($ages as $age) {
            if (!preg_match('/^(?:0|[1-9]|1[0-7])$/D', $age)) throw new InvalidArgumentException('invalid childAges');
        }
    }

    $roomType = (string)($_GET['roomType'] ?? '');
    if (strlen($roomType) > 255 || !preg_match('//u', $roomType) || preg_match('/[\x00-\x1F\x7F]/', $roomType)) {
        throw new InvalidArgumentException('invalid roomType');
    }
    $roomType = v2_price_segment_room_type($roomType);

    $currency = strtoupper(trim((string)($_GET['currency'] ?? 'RUB')));
    if (!preg_match('/^[A-Z]{3,8}$/D', $currency)) throw new InvalidArgumentException('invalid currency');

    $currentRaw = $_GET['currentPrice'] ?? null;
    if (!is_scalar($currentRaw) || !is_numeric((string)$currentRaw) || (float)$currentRaw <= 0) {
        throw new InvalidArgumentException('invalid currentPrice');
    }
    $currentPrice = (float)$currentRaw;

    $daysRaw = filter_var($_GET['days'] ?? 30, FILTER_VALIDATE_INT);
    $days = $daysRaw === false ? 30 : max(7, min(90, (int)$daysRaw));
    $fromDate = (new DateTimeImmutable('today', new DateTimeZone('UTC')))
        ->modify('-' . ($days - 1) . ' days')->format('Y-m-d');

    $identity = [
        'departure_id' => $departureId,
        'hotel_id' => $hotelId,
        'departure_date' => $departureDate,
        'nights' => $nights,
        'adults' => $adults,
        'children_count' => $childrenCount,
        'child_ages_signature' => $childAges,
        'meal_id' => $mealId,
        'room_id' => $roomId,
        'room_type' => $roomType,
        'currency' => $currency,
    ];
    $consumerSegment = v2_price_consumer_segment_fingerprint($identity);

    $pdo = v2_data_db();
    $stmt = $pdo->prepare("SELECT price_date,operator_id,min_price,observation_count,independent_search_count
        FROM tour_price_daily_exact
        WHERE departure_id=:departure_id
          AND hotel_id=:hotel_id
          AND departure_date=:departure_date
          AND nights=:nights
          AND adults=:adults
          AND children_count=:children_count
          AND child_ages_signature=:child_ages_signature
          AND meal_id=:meal_id
          AND room_id=:room_id
          AND room_type=:room_type
          AND currency=:currency
          AND price_date>=:from_date
        ORDER BY price_date ASC,operator_id ASC");
    $stmt->execute([
        'departure_id' => $departureId,
        'hotel_id' => $hotelId,
        'departure_date' => $departureDate,
        'nights' => $nights,
        'adults' => $adults,
        'children_count' => $childrenCount,
        'child_ages_signature' => $childAges,
        'meal_id' => $mealId,
        'room_id' => $roomId,
        'room_type' => $roomType,
        'currency' => $currency,
        'from_date' => $fromDate,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $summary = v2_price_consumer_intelligence_summary($rows, $currentPrice);
    $summary['consumerSegment'] = $consumerSegment;
    $summary['windowDays'] = $days;
    $summary['source'] = 'anytour-first-party-consumer-price-history';
    $summary['cachedPriceIsFinal'] = false;
    price_consumer_intelligence_out($summary);
} catch (InvalidArgumentException $e) {
    price_consumer_intelligence_out(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('price-consumer-intelligence-read-v1: ' . $e->getMessage());
    price_consumer_intelligence_out([
        'ok' => false,
        'error' => 'Consumer price history is temporarily unavailable',
    ], 503);
}
