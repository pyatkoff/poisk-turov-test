<?php
declare(strict_types=1);

require_once __DIR__ . '/three-provider-money-facts.php';
require_once __DIR__ . '/three-provider-availability.php';
require_once __DIR__ . '/three-provider-flight-details.php';
require_once __DIR__ . '/three-provider-meal-family.php';
require_once __DIR__ . '/three-provider-room-placement.php';
require_once __DIR__ . '/three-provider-offer-context.php';
require_once __DIR__ . '/three-provider-quote-envelope.php';

/**
 * Stable browser-safe INT -> SEARCH boundary.
 *
 * It composes already validated provider-neutral primitives; it does not resolve hotel
 * identity, call a supplier, calculate price deltas, enable selection or book anything.
 */
final class AnyTourThreeProviderSearchHandoff
{
    private const OFFER_KEYS = [
        'schema_version', 'provider', 'operator', 'local_hotel_id', 'identity',
        'checkin', 'nights', 'party', 'meal', 'room', 'placement', 'availability',
        'flight_details', 'money', 'observed_at', 'quote_state',
        'final_price_verified', 'selection_state',
    ];

    public static function fromSearchOffer(
        array $offer,
        array $retained,
        array $current,
        int $now
    ): array {
        self::assertCurrentOffer($offer, $retained, $current, $now);
        self::assertCanonicalSearchSurface($offer);
        return self::project(
            $offer,
            $retained,
            $offer['money'],
            'unknown',
            false,
            null
        );
    }

    /**
     * Customer-result DTO for the existing SEARCH fail-closed consumer.
     *
     * `finalPriceReady` is listing/display readiness, not supplier quote verification.
     * Tourvisor already reports the displayed search price and its fuel fact separately;
     * that price is usable only when the fuel fact is present. Direct ANEX and Andromeda
     * are usable when the existing canonical surcharge estimator has produced the exact
     * party-specific search_price_with_surcharge from explicit fuel facts. A later
     * Andromeda calc may still reprice selected regular/external transport; that later
     * quote remains a separate fact and does not erase a fuel-complete listing price.
     * Missing/unknown surcharge never falls back to the base search price.
     */
    public static function fromCustomerSearchOffer(
        array $offer,
        array $retained,
        array $current,
        int $now,
        ?array $pricedMoney = null
    ): array {
        self::assertCurrentOffer($offer, $retained, $current, $now);
        self::assertCanonicalSearchSurface($offer);
        $readiness = self::customerPriceReadiness($offer, $pricedMoney);
        $out = self::project(
            $offer,
            $retained,
            $pricedMoney ?? $offer['money'],
            'unknown',
            false,
            null
        );
        $out['finalPriceReady'] = $readiness['ready'];
        $out['finalPrice'] = $readiness['amount'];
        $out['price'] = $readiness['amount'];
        $out['currency'] = 'RUB';
        return $out;
    }

    public static function fromVerifiedQuote(
        array $offer,
        array $retained,
        array $current,
        array $quote,
        int $now
    ): array {
        self::assertCanonicalSearchSurface($offer);
        $envelope = AnyTourThreeProviderQuoteEnvelope::verified(
            $offer,
            $retained,
            $current,
            $quote,
            $now
        );
        if (($envelope['provider'] ?? null) !== ($offer['provider'] ?? null)
            || ($envelope['operator'] ?? null) !== ($offer['operator'] ?? null)
            || ($envelope['local_hotel_id'] ?? null) !== ($offer['local_hotel_id'] ?? null)
            || ($envelope['identity'] ?? null) !== ($offer['identity'] ?? null)
            || !is_string($envelope['quote_evidence_digest'] ?? null)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $envelope['quote_evidence_digest'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_QUOTE');
        }

        return self::project(
            $offer,
            $retained,
            $envelope['money'],
            'verified',
            true,
            $envelope['quote_evidence_digest']
        );
    }

    private static function assertCurrentOffer(
        array $offer,
        array $retained,
        array $current,
        int $now
    ): void {
        $issued = $retained['issued_at'] ?? null;
        $expires = $retained['expires_at'] ?? null;
        if (!is_int($issued) || !is_int($expires)) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_RETAINED');
        }
        $ttl = $expires - $issued;
        if ($ttl < 60 || $ttl > 900) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_RETAINED');
        }

        $expected = AnyTourThreeProviderOfferContext::retain(
            $offer,
            $retained['generation'] ?? 0,
            $retained['page'] ?? 0,
            $issued,
            $ttl
        );
        if ($expected !== $retained) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_RETAINED');
        }

        $status = AnyTourThreeProviderOfferContext::validate($retained, $current, $now);
        if (($status['status'] ?? null) !== 'current'
            || ($status['current_context_verified'] ?? null) !== true) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_CONTEXT');
        }
    }

    private static function assertCanonicalSearchSurface(array $offer): void
    {
        if (!self::exactKeys($offer, self::OFFER_KEYS)
            || ($offer['schema_version'] ?? null) !== 1
            || !is_string($offer['provider'] ?? null)
            || ($offer['quote_state'] ?? null) !== 'unknown'
            || ($offer['final_price_verified'] ?? null) !== false
            || ($offer['selection_state'] ?? null) !== 'disabled') {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_OFFER');
        }
        $provider = $offer['provider'];

        $checkin = $offer['checkin'] ?? null;
        $date = is_string($checkin) ? DateTimeImmutable::createFromFormat('!Y-m-d', $checkin) : false;
        if (!is_string($checkin) || !$date || $date->format('Y-m-d') !== $checkin
            || !is_int($offer['nights'] ?? null) || $offer['nights'] < 1 || $offer['nights'] > 30) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_TOUR');
        }

        $party = $offer['party'] ?? null;
        if (!is_array($party) || !self::exactKeys($party, ['adults', 'children', 'child_ages'])
            || !is_int($party['adults']) || $party['adults'] < 1 || $party['adults'] > 9
            || !is_int($party['children']) || $party['children'] < 0 || $party['children'] > 9
            || !is_array($party['child_ages'])
            || ($party['child_ages'] !== [] && array_keys($party['child_ages']) !== range(0, count($party['child_ages']) - 1))
            || count($party['child_ages']) !== $party['children']) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_PARTY');
        }
        foreach ($party['child_ages'] as $age) {
            if (!is_int($age) || $age < 0 || $age > 17) {
                throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_PARTY');
            }
        }

        $meal = $offer['meal'] ?? null;
        if (!is_array($meal) || !self::exactKeys($meal, ['raw', 'family', 'qualifiers', 'family_verified'])
            || !is_string($meal['raw']) || !is_array($meal['qualifiers'])
            || !self::exactKeys($meal['qualifiers'], ['plus', 'without_alcohol'])
            || !is_bool($meal['qualifiers']['plus']) || !is_bool($meal['qualifiers']['without_alcohol'])
            || !is_bool($meal['family_verified'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_MEAL');
        }
        $normalizedMeal = AnyTourThreeProviderMealFamily::normalize($meal['raw']);
        if ($meal['qualifiers'] !== $normalizedMeal['qualifiers']
            || ($meal['family'] !== null && $meal['family'] !== $normalizedMeal['family'])
            || $meal['family_verified'] !== ($meal['family'] !== null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_MEAL');
        }

        $room = $offer['room'] ?? null;
        $placement = $offer['placement'] ?? null;
        if (!is_array($room) || !is_string($room['raw'] ?? null)
            || ($placement !== null && (!is_array($placement) || !is_string($placement['raw'] ?? null)))) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_ROOM');
        }
        $labels = AnyTourThreeProviderRoomPlacement::normalize(
            $provider,
            $room['raw'],
            $placement === null ? null : $placement['raw']
        );
        if ($room !== $labels['room'] || $placement !== $labels['placement']) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_ROOM');
        }

        $availability = $offer['availability'] ?? null;
        if (!is_array($availability)) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_AVAILABILITY');
        }
        $rawAvailability = [
            'hotel' => is_array($availability['hotel'] ?? null) ? ($availability['hotel']['raw'] ?? null) : null,
            'flight_outbound_economy' => is_array($availability['flight_outbound_economy'] ?? null)
                ? ($availability['flight_outbound_economy']['raw'] ?? null) : null,
            'flight_return_economy' => is_array($availability['flight_return_economy'] ?? null)
                ? ($availability['flight_return_economy']['raw'] ?? null) : null,
        ];
        if ($availability !== AnyTourThreeProviderAvailability::fromSearch($provider, $rawAvailability)) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_AVAILABILITY');
        }
        if (($offer['flight_details'] ?? null) !== AnyTourThreeProviderFlightDetails::forSearch($provider)) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_FLIGHT');
        }

        $money = $offer['money'] ?? null;
        if (!is_array($money)
            || !is_array($money['search_price'] ?? null)
            || !array_key_exists('fuel_charge_reported', $money)
            || !is_array($money['additional_prices_reported'] ?? null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_MONEY');
        }
        try {
            $expectedMoney = AnyTourThreeProviderMoneyFacts::fromSearch(
                $provider,
                $money['search_price'],
                $money['fuel_charge_reported'],
                $money['additional_prices_reported']
            );
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_MONEY', 0, $error);
        }
        if ($money !== $expectedMoney) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_MONEY');
        }

        $observedAt = $offer['observed_at'] ?? null;
        $observed = is_string($observedAt)
            ? DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $observedAt, new DateTimeZone('UTC'))
            : false;
        if (!is_string($observedAt) || !$observed
            || $observed->format('Y-m-d\\TH:i:s\\Z') !== $observedAt) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_TIMESTAMP');
        }
    }

    private static function customerPriceReadiness(array $offer, ?array $pricedMoney): array
    {
        $provider = $offer['provider'];
        $searchMoney = $offer['money'];
        if ($provider === 'tourvisor') {
            if ($pricedMoney !== null && $pricedMoney !== $searchMoney) {
                throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_PRICE');
            }
            if (($searchMoney['fuel_charge_reported'] ?? null) === null) {
                return ['ready' => false, 'amount' => null];
            }
            $amount = self::readyRubAmount($searchMoney['search_price'] ?? null);
            return $amount === null
                ? ['ready' => false, 'amount' => null]
                : ['ready' => true, 'amount' => $amount];
        }

        if (!in_array($provider, ['anex', 'andromeda'], true) || $pricedMoney === null) {
            return ['ready' => false, 'amount' => null];
        }
        try {
            $expected = AnyTourThreeProviderMoneyFacts::withSearchSurchargeEstimate(
                $searchMoney,
                $offer['party']['adults'],
                $offer['party']['children']
            );
        } catch (DomainException $error) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_PRICE', 0, $error);
        }
        if ($pricedMoney !== $expected) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_PRICE');
        }
        $amount = self::readyRubAmount($pricedMoney['search_price_with_surcharge'] ?? null);
        return $amount === null
            ? ['ready' => false, 'amount' => null]
            : ['ready' => true, 'amount' => $amount];
    }

    private static function readyRubAmount($money): ?string
    {
        if (!is_array($money) || ($money['currency'] ?? null) !== 'RUB') return null;
        $amount = $money['amount'] ?? null;
        if (!is_string($amount)
            || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $amount)
            || !preg_match('/[1-9]/', $amount)) {
            return null;
        }
        return $amount;
    }

    private static function project(
        array $offer,
        array $retained,
        array $money,
        string $quoteState,
        bool $finalPriceVerified,
        ?string $quoteEvidenceDigest
    ): array {
        if (!in_array($quoteState, ['unknown', 'verified'], true)