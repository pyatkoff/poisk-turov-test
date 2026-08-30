<?php
/** Synchronize Tourvisor hot tours into the fast AnyTour snapshot table. */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/tourvisor-client-v1.php';

function hot_arg(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) return substr($arg, strlen($name) + 3);
    }
    return null;
}

function hot_required_int(?string $value, string $name): int
{
    $v = filter_var($value, FILTER_VALIDATE_INT);
    if ($v === false || (int)$v <= 0) throw new InvalidArgumentException('--' . $name . ' must be a positive integer');
    return (int)$v;
}

function hot_int_list(?string $value): array
{
    if ($value === null || trim($value) === '') return [];
    $out = [];
    foreach (explode(',', $value) as $raw) {
        $v = filter_var(trim($raw), FILTER_VALIDATE_INT);
        if ($v !== false && (int)$v > 0) $out[] = (int)$v;
    }
    return array_values(array_unique($out));
}

function hot_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    foreach (['!Y-m-d', '!d.m.Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}

$departureId = hot_required_int(hot_arg($argv, 'departure'), 'departure');
$countryIds = hot_int_list(hot_arg($argv, 'countries'));
$regionIds = hot_int_list(hot_arg($argv, 'regions'));
$operatorIds = hot_int_list(hot_arg($argv, 'operators'));
$currency = strtoupper(trim((string)(hot_arg($argv, 'currency') ?? 'RUB')));
if (!preg_match('/^[A-Z]{2,4}$/', $currency)) throw new InvalidArgumentException('--currency is invalid');
$limitRaw = filter_var(hot_arg($argv, 'limit') ?? '200', FILTER_VALIDATE_INT);
$limit = max(1, min(200, $limitRaw === false ? 200 : (int)$limitRaw));
$onlyCharterRaw = strtolower(trim((string)(hot_arg($argv, 'only-charter') ?? '0')));
$onlyCharter = in_array($onlyCharterRaw, ['1','true','yes'], true);
$dateFrom = hot_date(hot_arg($argv, 'date-from'));
$dateTo = hot_date(hot_arg($argv, 'date-to'));
if (($dateFrom === null) !== ($dateTo === null)) throw new InvalidArgumentException('date-from and date-to must be provided together');
if ($dateFrom !== null && $dateTo < $dateFrom) throw new InvalidArgumentException('date-to must not be before date-from');
if ($dateFrom !== null) {
    $span = (new DateTimeImmutable($dateFrom))->diff(new DateTimeImmutable($dateTo))->days;
    if ($span === false || $span > 21) throw new InvalidArgumentException('hot tour date range must not exceed 21 days');
}

$params = [
    'departureId' => $departureId,
    'countryIds' => $countryIds,
    'dateFrom' => $dateFrom,
    'dateTo' => $dateTo,
    'regionIds' => $regionIds,
    'operatorIds' => $operatorIds,
    'currency' => $currency,
    'onlyCharter' => $onlyCharter,
    'limit' => $limit,
];

$meal = filter_var(hot_arg($argv, 'meal'), FILTER_VALIDATE_INT);
if ($meal !== false && (int)$meal > 0) $params['meal'] = (int)$meal;
$category = filter_var(hot_arg($argv, 'category'), FILTER_VALIDATE_INT);
if ($category !== false && (int)$category > 0 && (int)$category <= 5) $params['hotelCategory'] = (int)$category;

$pdo = v2_data_db();
$fetchedAt = new DateTimeImmutable('now');
$expiresAt = $fetchedAt->modify('+60 minutes');

try {
    $rows = v2_data_tv_get('/tours/hots', $params);
    $pdo->beginTransaction();

    // Do not delete an entire departure snapshot before a filtered refresh. Upsert
    // returned rows and let stale rows age out, so a failed/partial request cannot
    // blank `/hot/` or erase unrelated countries/filters.
    $deleteExpired = $pdo->prepare('DELETE FROM hot_tours_current WHERE expires_at < :now_value');
    $deleteExpired->execute(['now_value' => $fetchedAt->format('Y-m-d H:i:s')]);

    $insert = $pdo->prepare("INSERT INTO hot_tours_current (
        snapshot_key,tour_id,departure_id,departure_name,country_id,country_name,region_id,region_name,
        subregion_id,subregion_name,hotel_id,hotel_name,hotel_category,hotel_rating,picture_url,
        departure_date,nights,meal_id,meal_name,operator_id,operator_name,price,old_price,currency,fetched_at,expires_at
    ) VALUES (
        :snapshot_key,:tour_id,:departure_id,:departure_name,:country_id,:country_name,:region_id,:region_name,
        :subregion_id,:subregion_name,:hotel_id,:hotel_name,:hotel_category,:hotel_rating,:picture_url,
        :departure_date,:nights,:meal_id,:meal_name,:operator_id,:operator_name,:price,:old_price,:currency,:fetched_at,:expires_at
    ) ON DUPLICATE KEY UPDATE
        departure_name=VALUES(departure_name),country_name=VALUES(country_name),region_id=VALUES(region_id),region_name=VALUES(region_name),
        subregion_id=VALUES(subregion_id),subregion_name=VALUES(subregion_name),hotel_name=VALUES(hotel_name),hotel_category=VALUES(hotel_category),
        hotel_rating=VALUES(hotel_rating),picture_url=VALUES(picture_url),meal_id=VALUES(meal_id),meal_name=VALUES(meal_name),
        operator_id=VALUES(operator_id),operator_name=VALUES(operator_name),price=VALUES(price),old_price=VALUES(old_price),
        currency=VALUES(currency),fetched_at=VALUES(fetched_at),expires_at=VALUES(expires_at)");

    $written = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $departure = is_array($row['departure'] ?? null) ? $row['departure'] : [];
        $country = is_array($row['country'] ?? null) ? $row['country'] : [];
        $hotel = is_array($row['hotel'] ?? null) ? $row['hotel'] : [];
        $hotelCountry = is_array($hotel['country'] ?? null) ? $hotel['country'] : [];
        $region = is_array($hotel['region'] ?? null) ? $hotel['region'] : [];
        $subregion = is_array($hotel['subRegion'] ?? null) ? $hotel['subRegion'] : [];
        $mealData = is_array($row['meal'] ?? null) ? $row['meal'] : [];
        $operator = is_array($row['operator'] ?? null) ? $row['operator'] : [];
        $tourId = trim((string)($row['tourId'] ?? ''));
        $hotelId = (int)($hotel['id'] ?? 0);
        $countryId = (int)($country['id'] ?? ($hotelCountry['id'] ?? 0));
        $date = hot_date((string)($row['date'] ?? ''));
        $price = (float)($row['price'] ?? 0);
        if ($tourId === '' || $hotelId <= 0 || $countryId <= 0 || $date === null || $price <= 0) continue;

        $snapshotKey = hash('sha256', implode('|', [$departureId,$countryId,$hotelId,$tourId,$date,(int)($row['nights'] ?? 0),$currency]));
        $insert->execute([
            'snapshot_key'=>$snapshotKey,
            'tour_id'=>$tourId,
            'departure_id'=>(int)($departure['id'] ?? $departureId),
            'departure_name'=>(string)($departure['name'] ?? ''),
            'country_id'=>$countryId,
            'country_name'=>(string)($country['name'] ?? ($hotelCountry['name'] ?? '')),
            'region_id'=>isset($region['id']) ? (int)$region['id'] : null,
            'region_name'=>$region['name'] ?? null,
            'subregion_id'=>isset($subregion['id']) ? (int)$subregion['id'] : null,
            'subregion_name'=>$subregion['name'] ?? null,
            'hotel_id'=>$hotelId,
            'hotel_name'=>(string)($hotel['name'] ?? ''),
            'hotel_category'=>isset($hotel['category']) ? (int)$hotel['category'] : null,
            'hotel_rating'=>isset($hotel['rating']) ? (float)$hotel['rating'] : null,
            'picture_url'=>$hotel['picturelink'] ?? null,
            'departure_date'=>$date,
            'nights'=>(int)($row['nights'] ?? 0),
            'meal_id'=>isset($mealData['id']) ? (int)$mealData['id'] : null,
            'meal_name'=>(string)($mealData['russianName'] ?? $mealData['name'] ?? ''),
            'operator_id'=>isset($operator['id']) ? (int)$operator['id'] : null,
            'operator_name'=>(string)($operator['russianName'] ?? $operator['name'] ?? ''),
            'price'=>$price,
            'old_price'=>isset($row['priceOld']) && (float)$row['priceOld'] > 0 ? (float)$row['priceOld'] : null,
            'currency'=>(string)($row['currency'] ?? $currency),
            'fetched_at'=>$fetchedAt->format('Y-m-d H:i:s'),
            'expires_at'=>$expiresAt->format('Y-m-d H:i:s'),
        ]);
        $written++;
    }

    $pdo->commit();
    fwrite(STDOUT, "HOT_TOURS_OK departure={$departureId} received=" . count($rows) . " written={$written}\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "HOT_TOURS_FAILED {$e->getMessage()}\n");
    exit(1);
}
