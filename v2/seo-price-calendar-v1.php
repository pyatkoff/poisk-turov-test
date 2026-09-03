<?php
/** Server-rendered low-price calendar for SEO pages, backed only by first-party observations. */
declare(strict_types=1);

require_once __DIR__ . '/data/db-v1.php';
require_once __DIR__ . '/data/price-calendar-core-v1.php';
require_once __DIR__ . '/seo-page-primitives-v1.php';
require_once __DIR__ . '/seo-offer-snapshot-v1.php';

function v2_seo_price_calendar_plan(
    array $offers,
    int $days = 14,
    ?DateTimeImmutable $today = null,
    ?string $scopeDateFrom = null,
    ?string $scopeDateTo = null
): ?array {
    $days = max(7, min(21, $days));
    $businessTz = new DateTimeZone('Europe/Moscow');
    $today ??= new DateTimeImmutable('today', $businessTz);
    $today = v2_price_calendar_date($today->setTimezone($businessTz)->format('Y-m-d'));

    $scopeFrom = null;
    $scopeTo = null;
    try {
        if ($scopeDateFrom !== null && $scopeDateFrom !== '') $scopeFrom = v2_price_calendar_date($scopeDateFrom);
        if ($scopeDateTo !== null && $scopeDateTo !== '') $scopeTo = v2_price_calendar_date($scopeDateTo);
    } catch (Throwable) {
        return null;
    }
    if ($scopeFrom !== null && $scopeTo !== null && $scopeTo < $scopeFrom) return null;

    $effectiveFloor = $today;
    if ($scopeFrom !== null && $scopeFrom > $effectiveFloor) $effectiveFloor = $scopeFrom;
    if ($scopeTo !== null && $scopeTo < $effectiveFloor) return null;

    $groups = [];
    foreach ($offers as $offer) {
        if (!is_array($offer)) continue;
        $departureId = (int)($offer['departureId'] ?? 0);
        $departureName = trim((string)($offer['departureName'] ?? ''));
        $dateRaw = trim((string)($offer['departureDate'] ?? ''));
        $nights = (int)($offer['nights'] ?? 0);
        if ($departureId <= 0 || $dateRaw === '' || $nights <= 0) continue;
        try {
            $date = v2_price_calendar_date($dateRaw);
        } catch (Throwable) {
            continue;
        }
        if ($date < $effectiveFloor || ($scopeTo !== null && $date > $scopeTo)) continue;
        if (!isset($groups[$departureId])) {
            $groups[$departureId] = [
                'departureId' => $departureId,
                'departureName' => $departureName,
                'count' => 0,
                'earliest' => null,
                'nights' => [],
            ];
        }
        $groups[$departureId]['count']++;
        if ($groups[$departureId]['departureName'] === '' && $departureName !== '') {
            $groups[$departureId]['departureName'] = $departureName;
        }
        if ($groups[$departureId]['earliest'] === null || $dateRaw < $groups[$departureId]['earliest']) {
            $groups[$departureId]['earliest'] = $dateRaw;
        }
        $groups[$departureId]['nights'][$nights] = ($groups[$departureId]['nights'][$nights] ?? 0) + 1;
    }
    if ($groups === []) return null;

    uasort($groups, static function (array $a, array $b): int {
        $count = ((int)$b['count']) <=> ((int)$a['count']);
        if ($count !== 0) return $count;
        $date = strcmp((string)$a['earliest'], (string)$b['earliest']);
        if ($date !== 0) return $date;
        return ((int)$a['departureId']) <=> ((int)$b['departureId']);
    });
    $chosen = reset($groups);
    if (!is_array($chosen) || empty($chosen['earliest'])) return null;

    $nightCounts = (array)$chosen['nights'];
    uksort($nightCounts, static function ($a, $b) use ($nightCounts): int {
        $count = ((int)$nightCounts[$b]) <=> ((int)$nightCounts[$a]);
        return $count !== 0 ? $count : ((int)$a <=> (int)$b;
    });
    $nights = (int)array_key_first($nightCounts);
    if ($nights <= 0) return null;

    $dateFrom = v2_price_calendar_date((string)$chosen['earliest']);
    if ($dateFrom < $effectiveFloor) $dateFrom = $effectiveFloor;
    $dateTo = $dateFrom->modify('+' . ($days - 1) . ' days');
    if ($scopeTo !== null && $dateTo > $scopeTo) $dateTo = $scopeTo;
    if ($dateTo < $dateFrom) return null;

    return [
        'departureId' => (int)$chosen['departureId'],
        'departureName' => (string)$chosen['departureName'],
        'nights' => $nights,
        'dateFrom' => $dateFrom->format('Y-m-d'),
        'dateTo' => $dateTo->format('Y-m-d'),
        'days' => (int)$dateFrom->diff($dateTo)->format('%a') + 1,
    ];
}

function v2_seo_price_calendar(
    array $offers,
    int $countryId,
    int $regionId = 0,
    int $days = 14,
    ?string $scopeDateFrom = null,
    ?string $scopeDateTo = null
): array {
    if ($countryId <= 0) return [];
    $plan = v2_seo_price_calendar_plan($offers, $days, null, $scopeDateFrom, $scopeDateTo);
    if ($plan === null) return [];

    try {
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
               AND o.nights=:nights
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
            'departure_id' => (int)$plan['departureId'],
            'country_id' => $countryId,
            'date_from' => (string)$plan['dateFrom'],
            'date_to' => (string)$plan['dateTo'],
            'nights' => (int)$plan['nights'],
        ];
        if ($regionId > 0) $params['region_id'] = $regionId;
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $calendar = v2_price_calendar_build($rows, (string)$plan['dateFrom'], (string)$plan['dateTo']);
    } catch (Throwable) {
        return [];
    }

    $observedDays = (int)($calendar['observedDays'] ?? 0);
    $span = max(1, (int)($calendar['days'] ?? 0));
    if ($observedDays < 3 || ($observedDays / $span) < 0.20) return [];

    return $calendar + [
        'departureId' => (int)$plan['departureId'],
        'departureName' => (string)$plan['departureName'],
        'nights' => (int)$plan['nights'],
        'countryId' => $countryId,
        'regionId' => $regionId > 0 ? $regionId : null,
        'currency' => 'RUB',
        'observationWindowHours' => 72,
        'cachedPriceIsFinal' => false,
    ];
}

function v2_seo_price_calendar_short_date(string $date): string
{
    try {
        $d = v2_price_calendar_date($date);
    } catch (Throwable) {
        return $date;
    }
    $months = [1=>'янв',2=>'фев',3=>'мар',4=>'апр',5=>'мая',6=>'июн',7=>'июл',8=>'авг',9=>'сен',10=>'окт',11=>'ноя',12=>'дек'];
    return (int)$d->format('j') . ' ' . ($months[(int)$d->format('n')] ?? $d->format('m'));
}

function v2_seo_render_price_calendar(array $calendar, array $searchState, string $title = 'Цены на туры по датам вылета'): string
{
    if ($calendar === [] || (int)($calendar['observedDays'] ?? 0) < 3) return '';
    $departureId = (int)($calendar['departureId'] ?? 0);
    $departureName = trim((string)($calendar['departureName'] ?? ''));
    $nights = (int)($calendar['nights'] ?? 0);
    if ($departureId <= 0 || $nights <= 0) return '';

    $subtitleParts = [];
    if ($departureName !== '') $subtitleParts[] = 'вылет из ' . $departureName;
    $subtitleParts[] = $nights . ' ночей';
    $subtitleParts[] = '2 взрослых';
    $subtitle = implode(' · ', $subtitleParts);

    $items = [];
    foreach ((array)($calendar['series'] ?? []) as $point) {
        if (!is_array($point)) continue;
        $date = trim((string)($point['date'] ?? ''));
        if ($date === '') continue;
        $label = v2_seo_price_calendar_short_date($date);
        $observed = ($point['observed'] ?? false) === true && (float)($point['minPrice'] ?? 0) > 0;
        if (!$observed) {
            $items[] = '<div class="sp-price-day sp-price-day--unknown" role="listitem"><time datetime="'.sp_e($date).'">'.sp_e($label).'</time><strong>—</strong><small>нет свежих данных</small></div>';
            continue;
        }
        $state = $searchState;
        $state['from'] = $departureId;
        $state['dateFrom'] = $date;
        $state['dateTo'] = $date;
        $state['daysFrom'] = $nights;
        $state['daysTill'] = $nights;
        $state['count_people'] = $state['count_people'] ?? 2;
        $href = v2_seo_search_handoff_url('/poisk-turov/', $state);
        $price = v2_seo_offer_price_label((float)$point['minPrice'], 'RUB');
        $hotelCount = (int)($point['hotelCount'] ?? 0);
        $best = ($point['best'] ?? false) === true;
        $items[] = '<a class="sp-price-day'.($best?' sp-price-day--best':'').'" role="listitem" href="'.sp_e($href).'">'
            .'<time datetime="'.sp_e($date).'">'.sp_e($label).'</time>'
            .'<strong>от '.sp_e($price).'</strong>'
            .'<small>'.($hotelCount > 0 ? sp_e($hotelCount . ' отелей') : 'свежая цена').'</small>'
            .($best?'<span class="sp-price-day__badge">лучшая цена</span>':'')
            .'</a>';
    }
    if ($items === []) return '';

    return '<section class="sp-card sp-price-calendar" data-seo-price-calendar>'
        .'<div class="sp-price-calendar__head"><div><h2>'.sp_e($title).'</h2><p>'.sp_e($subtitle).'</p></div>'
        .'<span class="sp-price-calendar__freshness">данные ≤ 72 ч</span></div>'
        .'<div class="sp-price-calendar__grid" role="list">'.implode('', $items).'</div>'
        .'<p class="sp-price-calendar__note">Показываем минимальные цены, которые AnyTour реально находил за последние 72 часа. Прочерк означает, что по дате нет свежего наблюдения, а не что туров нет. Цена и доступность перепроверяются в поиске.</p>'
        .'</section>';
}
