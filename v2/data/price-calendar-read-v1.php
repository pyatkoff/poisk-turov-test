<?php
/** Read-only departure-date low-price calendar from first-party observations. */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=900');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/db-v1.php';
require_once __DIR__ . '/price-calendar-core-v1.php';

function price_calendar_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function price_calendar_int(string $name, int $min, int $max, ?int $fallback = null): int
{
    $raw = $_GET[$name] ?? null;
    if (($raw === null || $raw === '') && $fallback !== null) return $fallback;
    $value = filter_var($raw, FILTER_VALIDATE_INT);
    if ($value === false || (int)$value < $min || (int)$value > $max) {
        throw new InvalidArgumentException("invalid {$name}");
    }
    return (int)$value;
}

try {
    $departureId = price_calendar_int('departureId', 1, 1000000);
    $countryId = price_calendar_int('countryId', 1, 1000000);
    $regionId = price_calendar_int('regionId', 0, 1000000, 0);
    $nightsFrom = price_calendar_int('nightsFrom', 1, 30, 7);
    $nightsTo = price_calendar_int('nightsTo', 1, 30, 10);
    if ($nightsTo < $nightsFrom) throw new InvalidArgumentException('nightsTo must not precede nightsFrom');

    // v1 deliberately uses the canonical SEO/search comparison party: two adults,
    // no children. Child ages materially affect package price and need their own
    // explicit calendar contract rather than being mixed here.
    $dateFrom = trim((string)($_GET['dateFrom'] ?? ''));
    $dateTo = trim((string)($_GET['dateTo'] ?? ''));
    $from = v2_price_calendar_date($dateFrom);
    $to = v2_price_calendar_date($dateTo);
    if ($to < $from || ((int)$from->diff($to)->format('%a') + 1) > 31) {
        throw new InvalidArgumentException('calendar date range must be 1..31 days');
    }
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    if ($from < $today) throw new InvalidArgumentException('dateFrom must not be in the past');

    $pdo = v2_data_db();
    $regionSql = $regionId > 0 ? ' AND o.region_id=:region_id' : '';
    $sql = "WITH ranked AS (
        SELECT o.*,
               ROW_NUMBER() OVER (
                 PARTITION BY o.departure_id,o.hotel_id,o.departure_date,o.nights,
                              o.adults,o.children_count,o.child_ages_signature,
                              COALESCE(o.meal_id,0),COALESCE(o.room_id,0),COALESCE(o.room_type,''),
                              COALESCE(o.operator_id,0),o.currency
                 ORDER BY o.observed_at DESC,o.id DESC
               ) AS rn
          FROM tour_price_observations o
         WHERE o.observed_at>=DATE_SUB(NOW(), INTERVAL 72 HOUR)
           AND o.departure_id=:departure_id
           AND o.country_id=:country_id
           {$regionSql}
           AND o.departure_date BETWEEN :date_from AND :date_to
           AND o.nights BETWEEN :nights_from AND :nights_to
           AND o.adults=2 AND o.children_count=0
           AND o.price>0 AND o.currency='RUB'
    )
    SELECT departure_date,
           MIN(price) AS min_price,
           COUNT(DISTINCT hotel_id) AS hotel_count,
           COUNT(DISTINCT search_id) AS independent_search_count,
           MAX(observed_at) AS latest_observed_at
      FROM ranked
     WHERE rn=1
     GROUP BY departure_date
     HAVING MIN(price)>0 AND COUNT(DISTINCT hotel_id)>0 AND COUNT(DISTINCT search_id)>0
     ORDER BY departure_date";
    $stmt = $pdo->prepare($sql);
    $params = [
        'departure_id' => $departureId,
        'country_id' => $countryId,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'nights_from' => $nightsFrom,
        'nights_to' => $nightsTo,
    ];
    if ($regionId > 0) $params['region_id'] = $regionId;
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $calendar = v2_price_calendar_build($rows, $dateFrom, $dateTo);
    $calendar += [
        'ok' => true,
        'departureId' => $departureId,
        'countryId' => $countryId,
        'regionId' => $regionId > 0 ? $regionId : null,
        'nightsFrom' => $nightsFrom,
        'nightsTo' => $nightsTo,
        'adults' => 2,
        'childrenCount' => 0,
        'currency' => 'RUB',
        'observationWindowHours' => 72,
        'source' => 'latest-known-exact-segments-from-anytour-first-party-observations',
        'cachedPriceIsFinal' => false,
    ];
    price_calendar_out($calendar);
} catch (InvalidArgumentException $e) {
    price_calendar_out(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('price-calendar-read-v1: ' . $e->getMessage());
    price_calendar_out(['ok' => false, 'error' => 'Price calendar is temporarily unavailable'], 503);
}
