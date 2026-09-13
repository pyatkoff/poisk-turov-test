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
