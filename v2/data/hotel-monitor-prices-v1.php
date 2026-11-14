<?php
/** Read-only adapter for the existing monitor. No supplier client or catalogue-ID inference. */
declare(strict_types=1);

function v2_hmp_id(mixed $value): int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,8}$/D', (string)$value)) {
        throw new InvalidArgumentException('Invalid native ID');
    }
    return (int)$value;
}

function v2_hmp_date(mixed $value): string
{
    if (!is_string($value)) throw new InvalidArgumentException('Expected YYYY-MM-DD');
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if (!$d || $d->format('Y-m-d') !== $value) throw new InvalidArgumentException('Expected real YYYY-MM-DD');
    return $value;
}

function v2_hmp_request(array $query, string $today): array
{
    $allowed = ['tourvisorHotelIds', 'departureId', 'dateFrom', 'dateTo'];
    if (array_diff(array_keys($query), $allowed) || count($query) !== 4) throw new InvalidArgumentException('Four explicit scope fields required');
    $ids = $query['tourvisorHotelIds'];
    if (!is_array($ids) || !array_is_list($ids) || count($ids) < 1 || count($ids) > 100) throw new InvalidArgumentException('Use 1..100 Tourvisor IDs');
    $ids = array_map('v2_hmp_id', $ids);
    if (count(array_unique($ids)) !== count($ids)) throw new InvalidArgumentException('Duplicate Tourvisor ID');
    sort($ids, SORT_NUMERIC);
    $from = v2_hmp_date($query['dateFrom']); $to = v2_hmp_date($query['dateTo']);
    $anchor = new DateTimeImmutable(v2_hmp_date($today), new DateTimeZone('UTC'));
    $last = $anchor->modify('first day of this month')->modify('+17 months')->modify('last day of this month')->format('Y-m-d');
    if ($from <= $today || $to < $from || $to > $last) throw new InvalidArgumentException('Scope must be future dates within 18 calendar months');
    return ['tourvisorHotelIds'=>$ids, 'departureId'=>v2_hmp_id($query['departureId']), 'dateFrom'=>$from, 'dateTo'=>$to,
        'nightsFrom'=>5, 'nightsTo'=>14, 'adults'=>2, 'childrenCount'=>0, 'currency'=>'RUB'];
}

/** Keep every actual room/operator/tour product with known meal terms before taking each hotel's cheapest observations. */
function v2_hmp_offer_sql(int $count): string
{
    if ($count < 1 || $count > 100) throw new InvalidArgumentException('Invalid hotel bound');
    $marks = implode(',', array_fill(0, $count, '?'));
    return "WITH exact_rows AS (
        SELECT o.*, ROW_NUMBER() OVER (
            PARTITION BY o.departure_id,o.hotel_id,o.departure_date,o.nights,o.adults,o.children_count,
                o.child_ages_signature,o.meal_id,o.room_id,o.room_type,o.operator_id,o.currency,o.tour_id
            ORDER BY o.observed_at DESC,o.id DESC
        ) exact_rank
        FROM tour_price_observations o
        WHERE o.hotel_id IN ($marks) AND o.departure_id=?
            AND o.departure_date BETWEEN ? AND ? AND o.nights BETWEEN 5 AND 14
            AND o.adults=2 AND o.children_count=0 AND COALESCE(o.child_ages_signature,'')=''
            AND o.source='scheduled_monitor' AND o.currency='RUB' AND o.price>0
            AND o.meal_id IN (2,3,4,5,7,9)
            AND o.tour_id IS NOT NULL AND o.tour_id<>''
            AND o.observed_at BETWEEN DATE_SUB(NOW(),INTERVAL 24 HOUR) AND NOW()
    ), hotel_rows AS (
        SELECT e.*,ROW_NUMBER() OVER (PARTITION BY e.hotel_id ORDER BY e.price ASC,e.observed_at DESC,e.id DESC) hotel_rank
        FROM exact_rows e WHERE e.exact_rank=1
    ) SELECT h.*,TIMESTAMPDIFF(SECOND,h.observed_at,NOW()) observation_age_seconds
        FROM hotel_rows h WHERE h.hotel_rank<=3 ORDER BY h.hotel_id,h.hotel_rank";
}

/** Completed window coverage is separate from availability, full price and whole-market coverage. */
function v2_hmp_coverage(array $attempts, array $scope, int $hotelId, string $dbNow, bool $truncated): array
{
    $latest = [];
    foreach ($attempts as $a) {
        $key = (string)$a['target_key'];
        if (!isset($latest[$key]) || (int)$a['id'] > (int)$latest[$key]['id']) $latest[$key] = $a;
    }
    $days = []; $counts = ['success'=>0,'empty'=>0,'timeout'=>0,'failure'=>0,'started'=>0];
    $cutoff = (new DateTimeImmutable($dbNow))->modify('-24 hours')->format('Y-m-d H:i:s');
    foreach ($latest as $a) {
        $ids = json_decode((string)($a['hotel_ids_json'] ?? ''), true);
        if (!is_array($ids) || !in_array($hotelId, $ids, true) || (int)$a['departure_id'] !== $scope['departureId']) continue;
        if ((int)$a['nights_from'] !== 5 || (int)$a['nights_to'] !== 14) continue;
        $status = (string)$a['status'];
        if (!isset($counts[$status])) continue;
        $counts[$status]++;
        $finished = (string)($a['finished_at'] ?? '');
        if (!in_array($status, ['success','empty'], true) || $finished < $cutoff || $finished > $dbNow) continue;
        $from = max($scope['dateFrom'], v2_hmp_date($a['date_from']));
        $to = min($scope['dateTo'], v2_hmp_date($a['date_to']));
        for ($d = new DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) $days[$d->format('Y-m-d')] = true;
    }
    $requested = (new DateTimeImmutable($scope['dateFrom']))->diff(new DateTimeImmutable($scope['dateTo']))->days + 1;
    return ['requestedDays'=>$requested, 'completedWindowDays'=>count($days), 'unknownDays'=>$requested-count($days),
        'searchWindowsComplete'=>!$truncated && count($days)===$requested, 'attemptReadTruncated'=>$truncated,
        'attemptStates'=>$counts, 'marketCoverageComplete'=>false,
        'meaning'=>'completed search windows; not all supplier rates or current availability'];
}

function v2_hmp_offer(array $r, string $generatedAt): array
{
    $age = max(0, (int)$r['observation_age_seconds']);
    $offer = ['tourvisorHotelId'=>(int)$r['hotel_id'], 'tourId'=>(string)$r['tour_id'], 'searchId'=>(string)$r['search_id'],
        'observationId'=>(string)$r['id'], 'departureId'=>(int)$r['departure_id'], 'countryId'=>(int)$r['country_id'],
        'departureDate'=>(string)$r['departure_date'], 'nights'=>(int)$r['nights'], 'adults'=>2, 'childrenCount'=>0,
        'mealId'=>$r['meal_id']===null?null:(int)$r['meal_id'], 'roomId'=>$r['room_id']===null?null:(int)$r['room_id'],
        'roomType'=>(string)($r['room_type']??''), 'operatorId'=>$r['operator_id']===null?null:(int)$r['operator_id'],
        'price'=>(float)$r['price'], 'fuelCharge'=>$r['fuel_charge']===null?null:(float)$r['fuel_charge'], 'currency'=>'RUB',
        'observedAtRaw'=>(string)$r['observed_at'], 'source'=>'scheduled_monitor', 'priceKind'=>'listing_observation'];
    // Stable identity excludes export time. Fuel is retained, never added to an unproved total.
    $offer['recordId'] = hash('sha256', json_encode($offer, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $offer['ageSecondsAtExport'] = $age;
    $offer['expiresAt'] = (new DateTimeImmutable($generatedAt))->modify('+'.max(0,min(900,86400-$age)).' seconds')->format('Y-m-d\TH:i:s\Z');
    $offer['currentPriceVerified'] = false;
    $offer['fullPackagePriceVerified'] = false;
    $offer['fuelIncluded'] = null;
    $offer['availabilityVerified'] = false;
    return $offer;
}

function v2_hmp_payload(array $scope, array $rows, array $attempts, string $dbNow, string $generatedAt, bool $truncated): array
{
    $byHotel = [];
    foreach ($rows as $r) $byHotel[(int)$r['hotel_id']][] = v2_hmp_offer($r,$generatedAt);
    $items = [];
    foreach ($scope['tourvisorHotelIds'] as $id) {
        $offers = $byHotel[$id] ?? [];
        $items[] = ['tourvisorHotelId'=>$id, 'state'=>$offers?'observed':'no_fresh_observation', 'offers'=>$offers,
            'coverage'=>v2_hmp_coverage($attempts,$scope,$id,$dbNow,$truncated)];
    }
    return ['ok'=>true, 'format'=>'anytour-hotel-monitor-prices-v1', 'generatedAt'=>$generatedAt, 'scope'=>$scope,
        'identityNamespace'=>'tourvisor', 'source'=>'scheduled_monitor', 'observationWindowHours'=>24,
        'timeBasis'=>'database-relative; stored observation timezone is not asserted',
        'supplierRequests'=>0, 'writes'=>0, 'items'=>$items,
        'missingSemantics'=>'unknown_not_no_tours', 'automaticDispatch'=>false];
}

function v2_hmp_read(PDO $pdo, array $query): array
{
    $dbNow = (string)$pdo->query('SELECT NOW()')->fetchColumn();
    $scope = v2_hmp_request($query, substr($dbNow,0,10));
    $generatedAt = gmdate('Y-m-d\TH:i:s\Z');
    $q = $pdo->prepare(v2_hmp_offer_sql(count($scope['tourvisorHotelIds'])));
    $q->execute([...$scope['tourvisorHotelIds'],$scope['departureId'],$scope['dateFrom'],$scope['dateTo']]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    $q = $pdo->prepare("SELECT id,target_key,departure_id,hotel_ids_json,date_from,date_to,nights_from,nights_to,status,finished_at
        FROM tour_matrix_collection_attempts WHERE target_key LIKE 'monthly:%' AND criterion='hotel_batch'
        AND departure_id=? AND date_to>=? AND date_from<=? AND started_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)
        ORDER BY id DESC LIMIT 10001");
    $q->execute([$scope['departureId'],$scope['dateFrom'],$scope['dateTo']]);
    $attempts = $q->fetchAll(PDO::FETCH_ASSOC); $truncated = count($attempts)>10000;
    $attempts = array_slice($attempts,0,10000);
    return v2_hmp_payload($scope,$rows,$attempts,$dbNow,$generatedAt,$truncated);
}
