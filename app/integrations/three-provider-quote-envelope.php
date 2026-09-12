<?php
declare(strict_types=1);

require_once __DIR__ . '/three-provider-money-facts.php';
require_once __DIR__ . '/three-provider-operator.php';
require_once __DIR__ . '/three-provider-offer-context.php';

/**
 * Provider-neutral provenance envelope for a supplier-verified selected quote.
 *
 * This boundary is deliberately source-only. It does not call a supplier, resolve a
 * hotel, calculate a delta, enable selection, or expose private supplier identities.
 * It binds an already verified Andromeda quote to the exact current retained offer
 * before canonical quote money can cross the INT -> SEARCH handoff.
 */
final class AnyTourThreeProviderQuoteEnvelope
{
    private const QUOTE_KEYS = [
        'schema_version', 'provider', 'selection_enabled', 'booking_enabled',
        'local_id', 'operator', 'search_price', 'package_price', 'state',
        'quote_state', 'final_price', 'final_price_verified',
        'flight_selection_required', 'flights',
    ];

    public static function verified(
        array $offer,
        array $retained,
        array $current,
        array $quote,
        int $now
    ): array {
        // Reconstructing the retained state validates the complete canonical search
        // offer as well as its provider/operator/local/digest identity. It also proves
        // that the caller did not pair the offer with a different retained envelope.
        $ttl = self::retainedTtl($retained);
        $expectedRetained = AnyTourThreeProviderOfferContext::retain(
            $offer,
            $retained['generation'] ?? 0,
            $retained['page'] ?? 0,
            $retained['issued_at'] ?? 0,
            $ttl
        );
        if ($expectedRetained !== $retained) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_RETAINED');
        }

        $context = AnyTourThreeProviderOfferContext::validate($retained, $current, $now);
        if (($context['status'] ?? null) !== 'current'
            || ($context['current_context_verified'] ?? null) !== true) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_CONTEXT');
        }
        if (($offer['provider'] ?? null) !== 'andromeda') {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_CAPABILITY');
        }

        self::assertVerifiedQuote($quote);
        if ($quote['provider'] !== $offer['provider']
            || $quote['local_id'] !== $offer['local_hotel_id']
            || $quote['operator'] !== $offer['operator']['raw']) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_BINDING');
        }

        $search = self::publicMoney($quote['search_price'], 'THREE_PROVIDER_QUOTE_SEARCH_PRICE');
        $canonicalSearch = $offer['money']['search_price'] ?? null;
        if (!is_array($canonicalSearch)
            || ($canonicalSearch['amount'] ?? null) !== $search['amount']
            || ($canonicalSearch['currency'] ?? null) !== $search['currency']) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_SEARCH_PRICE');
        }

        $package = $quote['package_price'] === null
            ? null
            : self::publicMoney($quote['package_price'], 'THREE_PROVIDER_QUOTE_PACKAGE_PRICE');
        $final = self::publicMoney($quote['final_price'], 'THREE_PROVIDER_QUOTE_FINAL_PRICE');

        $money = AnyTourThreeProviderMoneyFacts::withVerifiedQuote(
            $offer['money'],
            $package === null ? null : $package + ['source' => 'andromeda_package'],
            $final + ['source' => 'andromeda_quote']
        );

        $evidence = [
            'provider' => 'andromeda',
            'local_hotel_id' => $offer['local_hotel_id'],
            'operator' => $offer['operator']['raw'],
            'search_price' => $search,
            'package_price' => $package,
            'final_price' => $final,
            'state' => 'quote_verified',
            'quote_state' => 'verified',
            'final_price_verified' => true,
            'flight_selection_required' => false,
            'flight_count' => count($quote['flights']),
        ];
        $encoded = json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new RuntimeException('THREE_PROVIDER_QUOTE_EVIDENCE');
        }

        return [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'operator' => $offer['operator'],
            'local_hotel_id' => $offer['local_hotel_id'],
            'identity' => $offer['identity'],
            'context' => [
                'generation' => $retained['generation'],
                'page' => $retained['page'],
                'issued_at' => $retained['issued_at'],
                'expires_at' => $retained['expires_at'],
                'current_context_verified' => true,
            ],
            'quote_evidence_digest' => hash('sha256', $encoded),
            'money' => $money,
            'quote_state' => 'verified',
            'final_price_verified' => true,
            // A verified price is not authority to select/book. SEARCH remains owner.
            'selection_state' => 'disabled',
            'booking_enabled' => false,
        ];
    }

    private static function retainedTtl(array $retained): int
    {
        $issued = $retained['issued_at'] ?? null;
        $expires = $retained['expires_at'] ?? null;
        if (!is_int($issued) || !is_int($expires)) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_RETAINED');
        }
        $ttl = $expires - $issued;
        if ($ttl < 60 || $ttl > 900) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_RETAINED');
        }
        return $ttl;
    }

    private static function assertVerifiedQuote(array $quote): void
    {
        if (!self::exactKeys($quote, self::QUOTE_KEYS)
            || ($quote['schema_version'] ?? null) !== 1
            || ($quote['provider'] ?? null) !== 'andromeda'
            || ($quote['selection_enabled'] ?? null) !== true
            || ($quote['booking_enabled'] ?? null) !== false
            || !is_int($quote['local_id'] ?? null)
            || $quote['local_id'] < 1
            || $quote['local_id'] > 999999999
            || !(is_string($quote['operator'] ?? null) || ($quote['operator'] ?? null) === null)
            || ($quote['state'] ?? null) !== 'quote_verified'
            || ($quote['quote_state'] ?? null) !== 'verified'
            || ($quote['final_price_verified'] ?? null) !== true
            || ($quote['flight_selection_required'] ?? null) !== false
            || !is_array($quote['flights'] ?? null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_STATE');
        }
        // Money is checked separately so failures remain attributable to the exact fact.
        if (!is_array($quote['search_price'] ?? null)
            || (($quote['package_price'] ?? null) !== null && !is_array($quote['package_price']))
            || !is_array($quote['final_price'] ?? null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_STATE');
        }
    }

    private static function publicMoney(array $value, string $error): array
    {
        if (!self::exactKeys($value, ['amount', 'currency'])) {
            throw new InvalidArgumentException($error);
        }
        $amount = $value['amount'];
        $currency = $value['currency'];
        if (!is_string($amount)
            || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $amount)
            || preg_match('/[1-9]/', $amount) !== 1
            || !is_string($currency)
            || !preg_match('/\A[A-Z][A-Z0-9_]{2,7}\z/D', $currency)) {
            throw new InvalidArgumentException($error);
        }
        return ['amount' => $amount, 'currency' => $currency];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
