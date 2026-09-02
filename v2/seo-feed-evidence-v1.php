<?php
require_once __DIR__ . '/seo-seasonal-evidence-v1.php';

/**
 * Extract exact fresh offer evidence for future feed work.
 *
 * Output is evidence only: it cannot publish a feed, create landing pages or
 * claim discounts/availability. Prices are copied only from fresh first-party
 * observations and inherit the enclosing snapshot expiry.
 */
function v2_seo_feed_evidence_from_snapshot(array $snapshot, ?int $nowEpoch = null, int $maxOfferAgeSeconds = 172800): array
{
    $nowEpoch ??= time();
    $snapshotRow = v2_seo_seasonal_evidence_from_snapshot($snapshot);
    $assessment = v2_seo_seasonal_evidence_assess($snapshotRow, $nowEpoch);
    $blocked = [];
    $items = [];

    if (($assessment['usable'] ?? false) !== true) {
        return [
            'state'=>'blocked',
            'source_page_key'=>(string)($assessment['page_key'] ?? ''),
            'feed_publish_allowed'=>false,
            'items'=>[],
            'blocked'=>[['errors'=>$assessment['errors'] ?? ['snapshot_blocked']]],
        ];
    }

    $offers = is_array($snapshot['offers'] ?? null) ? $snapshot['offers'] : [];
    foreach ($offers as $index => $offer) {
        if (!is_array($offer)) {
            $blocked[] = ['index'=>$index,'errors'=>['invalid_offer']];
            continue;
        }
        $errors = [];
        $hotelId = (int)($offer['hotelId'] ?? 0);
        $hotelName = trim((string)($offer['hotelName'] ?? ''));
        $tourId = trim((string)($offer['tourId'] ?? ''));
        $price = (float)($offer['price'] ?? 0);
        $currency = trim((string)($offer['currency'] ?? ''));
        $nights = (int)($offer['nights'] ?? 0);
        $departureDate = trim((string)($offer['departureDate'] ?? ''));
        $departureEpoch = $departureDate !== '' ? strtotime($departureDate . ' 00:00:00 UTC') : false;
        $observedAt = v2_seo_seasonal_evidence_epoch($offer['observedAt'] ?? null);
        $regionId = isset($offer['regionId']) ? (int)$offer['regionId'] : null;

        if ($hotelId <= 0) $errors[] = 'invalid_hotel_identity';
        if ($hotelName === '') $errors[] = 'missing_hotel_name';
        if ($tourId === '') $errors[] = 'missing_tour_identity';
        if (!is_finite($price) || $price <= 0) $errors[] = 'invalid_price';
        if ($currency !== 'RUB') $errors[] = 'unsupported_currency';
        if ($nights <= 0 || $nights > 60) $errors[] = 'invalid_nights';
        if ($departureEpoch === false || $departureEpoch < strtotime(gmdate('Y-m-d', $nowEpoch) . ' 00:00:00 UTC')) $errors[] = 'invalid_departure_date';
        if ($observedAt === null) $errors[] = 'invalid_observed_at';
        elseif ($observedAt > $nowEpoch + 300) $errors[] = 'future_offer_observation';
        elseif ($observedAt < $nowEpoch - max(60, $maxOfferAgeSeconds)) $errors[] = 'stale_offer_observation';

        if (($assessment['scope'] ?? '') === 'resort_month') {
            $expectedRegion = (int)($assessment['region_id'] ?? 0);
            if ($expectedRegion <= 0 || $regionId !== $expectedRegion) $errors[] = 'region_identity_mismatch';
        }

        $errors = array_values(array_unique($errors));
        if ($errors !== []) {
            $blocked[] = ['index'=>$index,'hotel_id'=>$hotelId,'errors'=>$errors];
            continue;
        }

        $items[] = [
            'state'=>'fresh_feed_evidence',
            'source_page_key'=>(string)$assessment['page_key'],
            'country_id'=>(int)$assessment['country_id'],
            'region_id'=>$assessment['region_id'],
            'departure_id'=>(int)$assessment['departure_id'],
            'year'=>(int)$assessment['year'],
            'month'=>(int)$assessment['month'],
            'tour_id'=>$tourId,
            'hotel_id'=>$hotelId,
            'hotel_name'=>$hotelName,
            'departure_date'=>$departureDate,
            'nights'=>$nights,
            'price'=>$price,
            'currency'=>$currency,
            'observed_at_epoch'=>$observedAt,
            'expires_at_epoch'=>(int)$assessment['expires_at_epoch'],
            'feed_publish_allowed'=>false,
        ];
    }

    usort($items, static fn(array $a, array $b): int => [$a['price'],$a['hotel_id'],$a['tour_id']] <=> [$b['price'],$b['hotel_id'],$b['tour_id']]);
    return [
        'state'=>'review_only_feed_evidence',
        'source_page_key'=>(string)$assessment['page_key'],
        'feed_publish_allowed'=>false,
        'item_count'=>count($items),
        'blocked_count'=>count($blocked),
        'items'=>$items,
        'blocked'=>$blocked,
        'forbidden_claims'=>['discount','deal_quality','availability_guarantee','hotel_rating','hotel_attribute'],
    ];
}
