<?php
declare(strict_types=1);

/**
 * Build a server-only AdditionalPricesDaily batch plan for visible retained direct-ANEX offers.
 *
 * The planner performs no supplier transport. It only validates retained concrete-offer context,
 * bounds the batch to six browser-visible offers, and deduplicates identical
 * program/date/nights/currency contexts so one future supplier read can serve several offers.
 */
function anytour_anex_additional_prices_batch_plan(array $items, array $state): array
{
    if ($items === [] || count($items) > 6 || array_keys($items) !== range(0, count($items) - 1)) {
        throw new InvalidArgumentException('ANEX_INVALID_ADDITIONAL_BATCH');
    }
    $savedOffers = $state['gateway']['saved_offers']['offers'] ?? null;
    $knownRows = $state['gateway']['search']['offers'] ?? null;
    if (!is_array($savedOffers) || !is_array($knownRows)) throw new InvalidArgumentException('ANEX_INVALID_SESSION');
    $known = [];
    foreach ($knownRows as $row) {
        if (!is_array($row) || !is_string($row['offer_key'] ?? null)) continue;
        $known[$row['offer_key']] = $row;
    }

    $contexts = [];
    $offers = [];
    foreach ($items as $item) {
        if (!is_array($item) || count($item) !== 2 || array_diff(['offer_ref', 'local_hotel_id'], array_keys($item))
            || array_diff(array_keys($item), ['offer_ref', 'local_hotel_id'])) {
            throw new InvalidArgumentException('ANEX_INVALID_ADDITIONAL_BATCH');
        }
        $offerRef = $item['offer_ref'] ?? null;
        $localHotelId = $item['local_hotel_id'] ?? null;
        if (!is_string($offerRef) || !preg_match('/\Aanex_online:[a-f0-9]{64}\z/D', $offerRef)
            || !is_int($localHotelId) || $localHotelId < 1 || $localHotelId > 999999999) {
            throw new InvalidArgumentException('ANEX_INVALID_ADDITIONAL_BATCH');
        }
        $savedEntry = $savedOffers[$offerRef] ?? null;
        $offer = is_array($savedEntry) ? ($savedEntry['offer'] ?? null) : null;
        $knownRow = $known[$offerRef] ?? null;
        if (!is_array($savedEntry) || !is_array($offer) || !is_array($knownRow)) {
            throw new InvalidArgumentException('ANEX_INVALID_SESSION');
        }
        if (($offer['offer_key'] ?? null) !== $offerRef || ($offer['kind'] ?? null) !== 'concrete'
            || ($knownRow['kind'] ?? null) !== 'concrete'
            || ($knownRow['hotel_external_id'] ?? null) !== ($offer['hotel']['external_id'] ?? null)
            || ($offer['hotel']['local_id'] ?? null) !== $localHotelId) {
            throw new InvalidArgumentException('ANEX_INVALID_SESSION');
        }
        $tour = $savedEntry['supplier_tour_program_id'] ?? null;
        $currency = $savedEntry['supplier_currency_id'] ?? null;
        $checkin = $offer['checkin'] ?? null;
        $nights = $offer['nights'] ?? null;
        if (!is_string($tour) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $tour)
            || !is_string($currency) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $currency)
            || !is_string($checkin) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $checkin)
            || !is_int($nights) || $nights < 1 || $nights > 60) {
            throw new InvalidArgumentException('ANEX_ADDITIONAL_CONTEXT_UNAVAILABLE');
        }
        $digest = hash('sha256', implode("\0", [$tour, $currency, $checkin, (string) $nights]));
        if (!isset($contexts[$digest])) {
            $contexts[$digest] = [
                'context_digest' => $digest,
                'supplier_tour_program_id' => $tour,
                'supplier_currency_id' => $currency,
                'checkin' => $checkin,
                'nights' => $nights,
            ];
        }
        $offers[] = [
            'offer_ref' => $offerRef,
            'local_hotel_id' => $localHotelId,
            'context_digest' => $digest,
        ];
    }

    return [
        'requested_offers' => count($offers),
        'unique_contexts' => count($contexts),
        'offers' => $offers,
        'contexts' => array_values($contexts),
    ];
}

/**
 * Execute each unique private context at most once.
 *
 * `$reader` receives one private planner context and must return already-validated public-safe
 * evidence. The executor itself never performs transport. New contexts are persisted as unknown
 * before invoking the reader; supplier-observed unknown/reserved attempts are not replayed, while
 * completed evidence is reused without a reader call. A local `ANEX_RATE_LIMIT` raised by the
 * existing runtime client factory happens before supplier transport; that unsent attempt is rolled
 * back to absent state so a later visible-card batch may try it after the session budget recovers.
 * A shared same-day APD supplier unknown remains durable and affects only that context.
 */
function anytour_anex_additional_prices_batch_execute(array $plan, array &$state, callable $reader, callable $checkpoint): array
{
    $contexts = $plan['contexts'] ?? null;
    $offers = $plan['offers'] ?? null;
    if (!is_array($contexts) || !is_array($offers)
        || ($plan['requested_offers'] ?? null) !== count($offers)
        || ($plan['unique_contexts'] ?? null) !== count($contexts)
        || count($offers) < 1 || count($offers) > 6 || count($contexts) < 1 || count($contexts) > 6) {
        throw new InvalidArgumentException('ANEX_INVALID_ADDITIONAL_BATCH_PLAN');
    }
    if (!is_array($state['additional_prices'] ?? null)) $state['additional_prices'] = [];

    $results = [];
    foreach ($contexts as $context) {
        $digest = is_array($context) ? ($context['context_digest'] ?? null) : null;
        if (!is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D', $digest)) {
            throw new InvalidArgumentException('ANEX_INVALID_ADDITIONAL_BATCH_PLAN');
        }
        $attempt = $state['additional_prices'][$digest] ?? null;
        if (is_array($attempt) && ($attempt['status'] ?? null) === 'complete' && is_array($attempt['evidence'] ?? null)) {
            $results[$digest] = ['status' => 'complete', 'cached' => true, 'evidence' => $attempt['evidence']];
            continue;
        }
        if ($attempt !== null) {
            $results[$digest] = ['status' => 'unknown', 'cached' => true, 'evidence' => null];
            continue;
        }
        $state['additional_prices'][$digest] = ['status' => 'unknown'];
        $checkpoint($state, $digest);
        try {
            $evidence = $reader($context);
        } catch (RuntimeException $error) {
            if ($error->getMessage() === 'ANEX_RATE_LIMIT') {
                unset($state['additional_prices'][$digest]);
                $checkpoint($state, $digest);
                $results[$digest] = ['status' => 'unknown', 'cached' => false, 'evidence' => null];
                continue;
            }
            if ($error->getMessage() === 'ANEX_B2B_DAILY_UNKNOWN') {
                $results[$digest] = ['status' => 'unknown', 'cached' => true, 'evidence' => null];
                continue;
            }
            throw $error;
        }
        if (!is_array($evidence)) throw new RuntimeException('ANEX_INVALID_ADDITIONAL_PRICES');
        $state['additional_prices'][$digest] = ['status' => 'complete', 'evidence' => $evidence];
        $results[$digest] = ['status' => 'complete', 'cached' => false, 'evidence' => $evidence];
    }

    $publicOffers = [];
    foreach ($offers as $item) {
        $digest = is_array($item) ? ($item['context_digest'] ?? null) : null;
        if (!is_string($digest) || !isset($results[$digest])
            || !is_string($item['offer_ref'] ?? null) || !is_int($item['local_hotel_id'] ?? null)) {
            throw new InvalidArgumentException('ANEX_INVALID_ADDITIONAL_BATCH_PLAN');
        }
        $publicOffers[] = [
            'offer_ref' => $item['offer_ref'],
            'local_hotel_id' => $item['local_hotel_id'],
            'context_digest' => $digest,
            'status' => $results[$digest]['status'],
            'additional_prices' => $results[$digest]['evidence'],
        ];
    }

    return [
        'requested_offers' => count($publicOffers),
        'unique_contexts' => count($results),
        'offers' => $publicOffers,
    ];
}
