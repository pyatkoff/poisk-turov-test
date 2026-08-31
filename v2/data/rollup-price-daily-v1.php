<?php
/** Build compact daily price aggregates from first-party tour observations. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';

function rollup_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return null;
}

function rollup_date(?string $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        throw new InvalidArgumentException('--date must use YYYY-MM-DD');
    }
    return $date->format('Y-m-d');
}

function rollup_median(array $prices): float
{
    $count = count($prices);
    if ($count === 0) throw new InvalidArgumentException('median requires at least one price');
    // The source query orders price inside every segment, but sorting here keeps
    // this helper deterministic and safe for direct/test usage too.
    sort($prices, SORT_NUMERIC);
    $middle = intdiv($count, 2);
    return $count % 2 === 1
        ? (float)$prices[$middle]
        : ((float)$prices[$middle - 1] + (float)$prices[$middle]) / 2;
}

$date = rollup_date(rollup_arg($argv, 'date'));
$daysRaw = filter_var(rollup_arg($argv, 'days') ?? '7', FILTER_VALIDATE_INT);
$days = $daysRaw === false ? 7 : max(1, min(31, (int)$daysRaw));

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
        DATE(observed_at) AS price_date,
        departure_id,country_id,region_id,hotel_id,departure_year,departure_month,nights,adults,
        children_count,child_ages_signature,meal_id,currency,price
    FROM tour_price_observations
    WHERE observed_at >= :from_value AND observed_at < :to_value
      AND price > 0
    ORDER BY
        price_date,departure_id,country_id,COALESCE(region_id,0),hotel_id,departure_year,departure_month,
        nights,adults,children_count,child_ages_signature,COALESCE(meal_id,0),currency,price");
$select->execute(['from_value' => $from . ' 00:00:00', 'to_value' => $to . ' 00:00:00']);

$upsert = $pdo->prepare("INSERT INTO tour_price_daily (
        price_date,departure_id,country_id,region_id,hotel_id,departure_year,departure_month,nights,adults,
        children_count,child_ages_signature,meal_id,currency,min_price,median_price,max_price,observation_count,calculated_at
    ) VALUES (
        :price_date,:departure_id,:country_id,:region_id,:hotel_id,:departure_year,:departure_month,:nights,:adults,
        :children_count,:child_ages_signature,:meal_id,:currency,:min_price,:median_price,:max_price,:observation_count,:calculated_at
    ) ON DUPLICATE KEY UPDATE
        country_id=VALUES(country_id),region_id=VALUES(region_id),min_price=VALUES(min_price),median_price=VALUES(median_price),
        max_price=VALUES(max_price),observation_count=VALUES(observation_count),calculated_at=VALUES(calculated_at)");

$groupFields = [
    'price_date','departure_id','country_id','region_id','hotel_id','departure_year','departure_month','nights','adults',
    'children_count','child_ages_signature','meal_id','currency',
];
$currentKey = null;
$current = null;
$prices = [];
$groups = 0;
$observations = 0;
$calculatedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

$flush = static function () use (&$current, &$prices, &$groups, $upsert, $calculatedAt): void {
    if ($current === null || $prices === []) return;
    $upsert->execute([
        'price_date' => $current['price_date'],
        'departure_id' => (int)$current['departure_id'],
        'country_id' => (int)$current['country_id'],
        'region_id' => $current['region_id'] !== null ? (int)$current['region_id'] : null,
        'hotel_id' => (int)$current['hotel_id'],
        'departure_year' => (int)$current['departure_year'],
        'departure_month' => (int)$current['departure_month'],
        'nights' => (int)$current['nights'],
        'adults' => (int)$current['adults'],
        'children_count' => (int)$current['children_count'],
        'child_ages_signature' => (string)$current['child_ages_signature'],
        'meal_id' => $current['meal_id'] !== null ? (int)$current['meal_id'] : null,
        'currency' => (string)$current['currency'],
        'min_price' => min($prices),
        'median_price' => rollup_median($prices),
        'max_price' => max($prices),
        'observation_count' => count($prices),
        'calculated_at' => $calculatedAt,
    ]);
    $groups++;
};

try {
    $pdo->beginTransaction();
    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
        $observations++;
        $parts = [];
        foreach ($groupFields as $field) $parts[] = $row[$field] === null ? '' : (string)$row[$field];
        $key = implode('|', $parts);
        if ($currentKey !== null && $key !== $currentKey) {
            $flush();
            $prices = [];
        }
        if ($key !== $currentKey) {
            $currentKey = $key;
            $current = $row;
        }
        $prices[] = (float)$row['price'];
    }
    $flush();

    // If observations were removed/rejected later, do not leave stale aggregates
    // inside the recalculated window. Only rows not recalculated in this run are stale.
    $delete = $pdo->prepare("DELETE FROM tour_price_daily
        WHERE price_date >= :from_date AND price_date < :to_date AND calculated_at < :calculated_at");
    $delete->execute(['from_date' => $from, 'to_date' => $to, 'calculated_at' => $calculatedAt]);

    $pdo->commit();
    fwrite(STDOUT, "PRICE_DAILY_ROLLUP_OK from={$from} to={$to} observations={$observations} groups={$groups}\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'PRICE_DAILY_ROLLUP_FAILED ' . mb_substr($e->getMessage(), 0, 1000) . "\n");
    exit(1);
}
