<?php
declare(strict_types=1);

/**
 * Supplier-free observation built only after a real customer listing is later actualized
 * through the existing verified-quote path. This contract does not call suppliers, persist
 * data, recalculate a customer price, or expose private supplier/search/offer references.
 */
final class AnyTourThreeProviderPriceActualizationObservation
{
    public static function fromListingAndVerifiedQuote(array $listing, array $quote): array
    {
        self::assertListing($listing);
        self::assertQuote($quote);
        self::assertSameOffer($listing, $quote);

        $listingMoney = self::rubMoney([
            'amount' => $listing['finalPrice'],
            'currency' => $listing['currency'],
        ], 'THREE_PROVIDER_ACTUALIZATION_LISTING_MONEY');
        $quoteMoney = self::rubMoney($quote['money']['quote_price'], 'THREE_PROVIDER_ACTUALIZATION_QUOTE_MONEY');

        return [
            'schema_version' => 1,
            'provider' => $listing['provider'],
            'operator' => $listing['operator'],
            'local_hotel_id' => $listing['local_hotel_id'],
            'tour' => [
                'checkin' => $listing['tour']['checkin'],
                'nights' => $listing['tour']['nights'],
                'party' => $listing['tour']['party'],
            ],
            'listing_price' => $listingMoney,
            'verified_quote_price' => $quoteMoney,
            'exact_match' => $listingMoney === $quoteMoney,
            'listing_final_price_ready' => true,
            'quote_final_price_verified' => true,
            'context' => [
                'generation' => $listing['context']['generation'],
                'page' => $listing['context']['page'],
            ],
        ];
    }

    private static function assertListing(array $listing): void
    {
        if (($listing['finalPriceReady'] ?? null) !== true
            || ($listing['final_price_verified'] ?? null) !== false
            || ($listing['quote_state'] ?? null) !== 'unknown'
            || ($listing['selection_state'] ?? null) !== 'disabled'
            || ($listing['booking_enabled'] ?? null) !== false
            || !is_string($listing['provider'] ?? null)
            || !is_int($listing['local_hotel_id'] ?? null)
            || !is_array($listing['tour'] ?? null)
            || !is_array($listing['context'] ?? null)
            || !is_string($listing['finalPrice'] ?? null)
            || ($listing['price'] ?? null) !== $listing['finalPrice']
            || ($listing['currency'] ?? null) !== 'RUB') {
            throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_LISTING');
        }
    }

    private static function assertQuote(array $quote): void
    {
        if (($quote['final_price_verified'] ?? null) !== true
            || ($quote['quote_state'] ?? null) !== 'verified'
            || ($quote['selection_state'] ?? null) !== 'disabled'
            || ($quote['booking_enabled'] ?? null) !== false
            || !is_string($quote['provider'] ?? null)
            || !is_int($quote['local_hotel_id'] ?? null)
            || !is_array($quote['tour'] ?? null)
            || !is_array($quote['context'] ?? null)
            || !is_array($quote['money'] ?? null)
            || !is_array($quote['money']['quote_price'] ?? null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_QUOTE');
        }
    }

    private static function assertSameOffer(array $listing, array $quote): void
    {
        foreach (['provider', 'operator', 'local_hotel_id', 'identity'] as $key) {
            if (($listing[$key] ?? null) !== ($quote[$key] ?? null)) {
                throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_IDENTITY');
            }
        }
        foreach (['checkin', 'nights', 'party', 'meal', 'room', 'placement'] as $key) {
            if (($listing['tour'][$key] ?? null) !== ($quote['tour'][$key] ?? null)) {
                throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_TOUR');
            }
        }
        foreach (['generation', 'page'] as $key) {
            if (($listing['context'][$key] ?? null) !== ($quote['context'][$key] ?? null)) {
                throw new InvalidArgumentException('THREE_PROVIDER_ACTUALIZATION_CONTEXT');
            }
        }
    }

    private static function rubMoney(array $money, string $error): array
    {
        if (($money['currency'] ?? null) !== 'RUB') {
            throw new InvalidArgumentException($error);
        }
        $amount = $money['amount'] ?? null;
        if (!is_string($amount)
            || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $amount)
            || !preg_match('/[1-9]/', $amount)) {
            throw new InvalidArgumentException($error);
        }
        return ['amount' => $amount, 'currency' => 'RUB'];
    }
}
