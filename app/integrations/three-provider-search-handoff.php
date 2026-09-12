<?php
declare(strict_types=1);

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
    public static function fromSearchOffer(
        array $offer,
        array $retained,
        array $current,
        int $now
    ): array {
        self::assertCurrentOffer($offer, $retained, $current, $now);
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
}
