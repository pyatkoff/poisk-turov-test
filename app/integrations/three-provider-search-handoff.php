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

    private static function project(
        array $offer,
        array $retained,
        array $money,
        string $quoteState,
        bool $finalPriceVerified,
        ?string $quoteEvidenceDigest
    ): array {
        if (!in_array($quoteState, ['unknown', 'verified'], true)
            || ($quoteState === 'verified') !== $finalPriceVerified
            || ($quoteState === 'verified') !== ($quoteEvidenceDigest !== null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_HANDOFF_QUOTE_STATE');
        }

        return [
            'schema_version' => 1,
            'provider' => $offer['provider'],
            'operator' => $offer['operator'],
            'local_hotel_id' => $offer['local_hotel_id'],
            'identity' => $offer['identity'],
            'tour' => [
                'checkin' => $offer['checkin'],
                'nights' => $offer['nights'],
                'party' => $offer['party'],
                'meal' => $offer['meal'],
                'room' => $offer['room'],
                'placement' => $offer['placement'],
                'availability' => $offer['availability'],
                'flight_details' => $offer['flight_details'],
                'observed_at' => $offer['observed_at'],
            ],
            'money' => $money,
            'quote_state' => $quoteState,
            'final_price_verified' => $finalPriceVerified,
            'quote_evidence_digest' => $quoteEvidenceDigest,
            'context' => [
                'generation' => $retained['generation'],
                'page' => $retained['page'],
                'issued_at' => $retained['issued_at'],
                'expires_at' => $retained['expires_at'],
                'current_context_verified' => true,
            ],
            // SEARCH owns the action state; INT never promotes it here.
            'selection_state' => 'disabled',
            'booking_enabled' => false,
        ];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
