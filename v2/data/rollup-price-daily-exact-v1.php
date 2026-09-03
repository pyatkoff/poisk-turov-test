<?php
/** Build daily price aggregates for exact comparable tour segments. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/price-segment-v1.php';

function exact_rollup_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return null;
}

function exact_rollup_date(?string $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $raw) {
        throw new InvalidArgumentException('--date must use a real YYYY-MM-DD calendar date');
    }
    return $raw;
}

function exact_rollup_median(array $prices): float
{
    $count = count($prices);
    if ($count === 0) throw new InvalidArgumentException('median requires at least one price');
    sort($prices, SORT_NUMERIC);
    $middle = intdiv($count, 2);
    return $count % 2 === 1
        ? (float)$prices[$middle]
        : ((float)$prices[$middle - 1] + (float)$prices[$middle]) / 2;
}

$date = exact_rollup_date(exact_rollup_arg($argv, 'date'));
$daysRaw = filter_var(exact_rollup_arg($argv, 'days') ?? '31', FILTER_VALIDATE_INT);
$days = $daysRaw === false ? 31 : max(1, min(365, (int)$daysRaw));

if ($date !== null) {
    $from = $date;
    $to = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
} else {
    $today = new DateTimeImmutable('today');
    $from = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $to = $today->modify('+1 day')->format('Y-m-d');
}

$pdo = v2_data_db();
$select = $pdo->prepare("SELECT
        DATE(observed_at) AS price_date,search_id,
        departure_id,country_id,region_id,subregion_id,hotel_id,departure_date,nights,adults,
        children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,currency,price
    FROM tour_price_observations
    WHERE observed_at >= :from_value AND observed_at < :to_value
      AND price > 0
    ORDER BY
        price_date,departure_id,hotel_id,departure_date,nights,adults,children_count,
        child_ages_signature,COALESCE(meal_id,0),COALESCE(room_id,0),COALESCE(room_type,''),
        COALESCE(operator_id,0),currency,price,id");
$select->execute(['from_value' => $from . ' 00:00:00', 'to_value' => $to . ' 00:00:00']);

$upsert = $pdo->prepare("INSERT INTO tour_price_daily_exact (
        segment_fingerprint,price_date,departure_id,country_id,region_id,subregion_id,hotel_id,departure_date,
        nights,adults,children_count,child_ages_signature,meal_id,room_id,room_type,operator_id,currency,
        min_price,median_price,max_price,observation_count,independent_search_count,calculated_at
    ) VALUES (
        :segment_fingerprint,:price_date,:departure_id,:country_id,:region_id,:subregion_id,:hotel_id,:departure_date,
        :nights,:adults,:children_count,:child_ages_signature,:meal_id,:room_id,:room_type,:operator_id,:currency,
        :min_price,:median_price,:max_price,:observation_count,:independent_search_count,:calculated_at
    ) ON DUPLICATE KEY UPDATE
        country_id=VALUES(country_id),region_id=VALUES(region_id),subregion_id=VALUES(subregion_id),
        departure_id=VALUES(departure_id),hotel_id=VALUES(hotel_id),departure_date=VALUES(departure_date),
        nights=VALUES(nights),adults=VALUES(adults),children_count=VALUES(children_count),
        child_ages_signature=VALUES(child_ages_signature),meal_id=VALUES(meal_id),room_id=VALUES(room_id),
        room_type=VALUES(room_type),operator_id=VALUES(operator_id),currency=VALUES(currency),
        min_price=VALUES(min_price),median_price=VALUES(median_price),max_price=VALUES(max_price),
        observation_count=VALUES(observation_count),independent_search_count=VALUES(independent_search_count),
        calculated_at=VALUES(calculated_at)");

$currentKey = null;
$current = null;
$prices = [];
$searchIds = [];
$groups = 0;
$observations = 0;
$calculatedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

$flush = static function () use (&$current, &$prices, &$searchIds, &$groups, $upsert, $calculatedAt): void {
    if ($current === null || $prices === []) return;
    $fingerprint = v2_price_segment_fingerprint($current);
    $upsert->execute([
        'segment_fingerprint' => $fingerprint,
        'price_date' => (string)$current['price_date'],
        'departure_id' => (int)$current['departure_id'],
        'country_id' => (int)$current['country_id'],
        'region_id' => $current['region_id'] !== null ? (int)$current['region_id'] : null,
        'subregion_id' => $current['subregion_id'] !== null ? (int)$current['subregion_id'] : null,
        'hotel_id' => (int)$current['hotel_id'],
        'departure_date' => (string)$current['departure_date'],
        'nights' => (int)$current['nights'],
        'adults' => (int)$current['adults'],
        'children_count' => (int)$current['children_count'],
        'child_ages_signature' => (string)$current['child_ages_signature'],
        'meal_id' => (int)($current['meal_id'] ?? 0),
        'room_id' => (int)($current['room_id'] ?? 0),
        'room_type' => (string)($current['room_type'] ?? ''),
        'operator_id' => (int)($current['operator_id'] ?? 0),
        'currency' => strtoupper((string)$current['currency']),
        'min_price' => min($prices),
        'median_price' => exact_rollup_median($prices),
        'max_price' => max($prices),
        'observation_count' => count($prices),
        'independent_search_count' => count($searchIds),
        'calculated_at' => $calculatedAt,
    ]);
    $groups++;
};

try {
    $pdo->beginTransaction();
    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
        $observations++;
        $segmentFingerprint = v2_price_segment_fingerprint($row);
        $key = (string)$row['price_date'] . '|' . $segmentFingerprint;
        if ($currentKey !== null && $key !== $currentKey) {
            $flush();
            $prices = [];
            $searchIds = [];
        }
        if ($key !== $currentKey) {
            $currentKey = $key;
            $current = $row;
        }
        $prices[] = (float)$row['price'];
        $searchId = (int)($row['search_id'] ?? 0);
        if ($searchId > 0) $searchIds[$searchId] = true;
    }
    $flush();

    $delete = $pdo->prepare("DELETE FROM tour_price_daily_exact
        WHERE price_date >= :from_date AND price_date < :to_date AND calculated_at < :calculated_at");
    $delete->execute(['from_date' => $from, 'to_date' => $to, 'calculated_at' => $calculatedAt]);

    $pdo->commit();
    fwrite(STDOUT, "PRICE_DAILY_EXACT_ROLLUP_OK from={$from} to={$to} observations={$observations} groups={$groups}\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'PRICE_DAILY_EXACT_ROLLUP_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
