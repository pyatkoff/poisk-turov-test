<?php
declare(strict_types=1);

/**
 * Provider-neutral money facts for Search3 offers.
 *
 * Search price, fuel, additional charges, package buyer money and quote money remain
 * independent facts with independent evidence. No surcharge is inferred implicitly.
 * The dedicated search-estimate method may add only explicit per-passenger surcharge
 * facts to the preserved base search price; missing/ambiguous surcharge stays unknown.
 * A verified quote is still a later, supplier-authoritative fact.
 */
final class AnyTourThreeProviderMoneyFacts
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const SEARCH_CAPABILITIES = [
        'tourvisor' => ['fuel' => true, 'additional' => false],
        'anex' => ['fuel' => false, 'additional' => true],
        // Transport surcharge facts are allowed only when an upstream Andromeda
        // transport-directory contract has actually supplied them. This class does not
        // discover, guess or synthesize that still-unproven supplier binding.
        'andromeda' => ['fuel' => false, 'additional' => true],
    ];

    public static function fromSearch(
        string $provider,
        array $searchPrice,
        ?array $fuelChargeReported = null,
        array $additionalPricesReported = []
    ): array {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_PROVIDER');
        }
        if (count($additionalPricesReported) > 20
            || ($additionalPricesReported !== []
                && array_keys($additionalPricesReported) !== range(0, count($additionalPricesReported) - 1))) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_ADDITIONAL');
        }
        $capabilities = self::SEARCH_CAPABILITIES[$provider];
        if (($fuelChargeReported !== null && !$capabilities['fuel'])
            || ($additionalPricesReported !== [] && !$capabilities['additional'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_CAPABILITY');
        }

        $search = self::moneyFact($searchPrice, false, $provider, 'search');
        $fuel = $fuelChargeReported === null
            ? null
            : self::moneyFact($fuelChargeReported, true, $provider, 'fuel');

        $additional = [];
        foreach ($additionalPricesReported as $fact) {
            if (!is_array($fact) || !self::exactKeys($fact, ['kind', 'amount', 'currency', 'source'])) {
                throw new InvalidArgumentException('THREE_PROVIDER_MONEY_ADDITIONAL');
            }
            $kind = $fact['kind'];
            if (!is_string($kind) || !preg_match('/\A[a-z][a-z0-9_]{0,39}\z/D', $kind)) {
                throw new InvalidArgumentException('THREE_PROVIDER_MONEY_ADDITIONAL');
            }
            $money = self::moneyFact([
                'amount' => $fact['amount'],
                'currency' => $fact['currency'],
                'source' => $fact['source'],
            ], true, $provider, 'additional');
            $additional[] = ['kind' => $kind] + $money;
        }

        return [
            'schema_version' => 1,
            'provider' => $provider,
            'search_price' => $search,
            'fuel_charge_reported' => $fuel,
            'additional_prices_reported' => $additional,
            // Search evidence cannot fill these fields. They require later package/quote evidence.
            'package_buyer_price' => null,
            'quote_price' => null,
            'search_price_fuel_relation' => 'unknown',
            'final_price_verified' => false,
            'arithmetic_applied' => false,
        ];
    }

    /**
     * Derive the search-card estimate from an explicit adult/child surcharge contract.
     *
     * This never turns the estimate into a verified final price and never mutates the
     * base search or surcharge facts. Direct ANEX supplies these rates from
     * AdditionalPricesDaily. Andromeda may use the same neutral shape only after its
     * transport-directory binding is independently proven upstream.
     */
    public static function withSearchSurchargeEstimate(
        array $searchFacts,
        int $adults,
        int $children
    ): array {
        self::assertCanonicalSearchFacts($searchFacts);
        if (!in_array($searchFacts['provider'], ['anex', 'andromeda'], true)) {
            throw new InvalidArgumentException('THREE_PROVIDER_SURCHARGE_CAPABILITY');
        }
        if ($adults < 1 || $adults > 6 || $children < 0 || $children > 3) {
            throw new InvalidArgumentException('THREE_PROVIDER_SURCHARGE_PARTY');
        }

        $rates = [];
        foreach ($searchFacts['additional_prices_reported'] as $fact) {
            $kind = $fact['kind'];
            if (!in_array($kind, ['fuel_adult', 'fuel_child'], true)) continue;
            if (array_key_exists($kind, $rates)) {
                throw new DomainException('THREE_PROVIDER_SURCHARGE_UNKNOWN');
            }
            if ($fact['currency'] !== $searchFacts['search_price']['currency']) {
                throw new DomainException('THREE_PROVIDER_SURCHARGE_UNKNOWN');
            }
            $rates[$kind] = $fact['amount'];
        }
        if (!array_key_exists('fuel_adult', $rates)
            || ($children > 0 && !array_key_exists('fuel_child', $rates))) {
            throw new DomainException('THREE_PROVIDER_SURCHARGE_UNKNOWN');
        }

        $amounts = [$searchFacts['search_price']['amount'], $rates['fuel_adult']];
        if ($children > 0) $amounts[] = $rates['fuel_child'];
        $scale = 0;
        foreach ($amounts as $amount) $scale = max($scale, self::decimalScale($amount));

        $base = self::decimalUnits($searchFacts['search_price']['amount'], $scale);
        $adult = self::decimalUnits($rates['fuel_adult'], $scale);
        $child = $children > 0 ? self::decimalUnits($rates['fuel_child'], $scale) : 0;
        if ($base === null || $adult === null || $child === null
            || $adult > intdiv(PHP_INT_MAX, $adults)
            || ($children > 0 && $child > intdiv(PHP_INT_MAX, $children))) {
            throw new DomainException('THREE_PROVIDER_SURCHARGE_UNKNOWN');
        }
        $adultTotal = $adult * $adults;
        $childTotal = $child * $children;
        if ($adultTotal > PHP_INT_MAX - $childTotal
            || $base > PHP_INT_MAX - ($adultTotal + $childTotal)) {
            throw new DomainException('THREE_PROVIDER_SURCHARGE_UNKNOWN');
        }

        $next = $searchFacts;
        $next['search_price_with_surcharge'] = [
            'amount' => self::decimalString($base + $adultTotal + $childTotal, $scale),
            'currency' => $searchFacts['search_price']['currency'],
            'source' => 'derived_search_estimate',
        ];
        $next['arithmetic_applied'] = true;
        return $next;
    }

    /**
     * Attach a supplier-verified quote without changing any search money fact.
     *
     * Current evidence proves this flow only for Andromeda. Tourvisor/direct ANEX
     * remain unsupported here until an equivalent selected-package quote contract is
     * independently verified. This method never calculates delta, fuel, conversion or
     * a fallback between search/package/quote amounts.
     */
    public static function withVerifiedQuote(
        array $searchFacts,
        ?array $packageBuyerPrice,
        array $quotePrice
    ): array {
        self::assertCanonicalSearchFacts($searchFacts);
        if ($searchFacts['provider'] !== 'andromeda') {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_QUOTE_CAPABILITY');
        }

        $package = $packageBuyerPrice === null
            ? null
            : self::moneyFact($packageBuyerPrice, false, 'andromeda', 'package');
        $quote = self::moneyFact($quotePrice, false, 'andromeda', 'quote');

        $next = $searchFacts;
        $next['package_buyer_price'] = $package;
        $next['quote_price'] = $quote;
        $next['final_price_verified'] = true;
        // Explicitly preserve the no-arithmetic search/fuel semantics of the final quote path.
        $next['search_price_fuel_relation'] = 'unknown';
        $next['arithmetic_applied'] = false;
        return $next;
    }

    private static function assertCanonicalSearchFacts(array $facts): void
    {
        if (!self::exactKeys($facts, [
            'schema_version', 'provider', 'search_price', 'fuel_charge_reported',
            'additional_prices_reported', 'package_buyer_price', 'quote_price',
            'search_price_fuel_relation', 'final_price_verified', 'arithmetic_applied',
        ])
            || ($facts['schema_version'] ?? null) !== 1
            || ($facts['package_buyer_price'] ?? null) !== null
            || ($facts['quote_price'] ?? null) !== null
            || ($facts['search_price_fuel_relation'] ?? null) !== 'unknown'
            || ($facts['final_price_verified'] ?? null) !== false
            || ($facts['arithmetic_applied'] ?? null) !== false
            || !is_string($facts['provider'] ?? null)
            || !is_array($facts['search_price'] ?? null)
            || (($facts['fuel_charge_reported'] ?? null) !== null
                && !is_array($facts['fuel_charge_reported']))
            || !is_array($facts['additional_prices_reported'] ?? null)) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_SEARCH_STATE');
        }

        try {
            $expected = self::fromSearch(
                $facts['provider'],
                $facts['search_price'],
                $facts['fuel_charge_reported'],
                $facts['additional_prices_reported']
            );
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_SEARCH_STATE', 0, $e);
        }
        if ($facts !== $expected) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_SEARCH_STATE');
        }
    }

    private static function decimalScale(string $amount): int
    {
        $dot = strpos($amount, '.');
        return $dot === false ? 0 : strlen($amount) - $dot - 1;
    }

    private static function decimalUnits(string $amount, int $scale): ?int
    {
        if ($scale < 0 || $scale > 2 || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $amount)) {
            return null;
        }
        $parts = explode('.', $amount, 2);
        $fraction = $parts[1] ?? '';
        if (strlen($fraction) > $scale) return null;
        $factor = 10 ** $scale;
        return ((int) $parts[0] * $factor) + (int) str_pad($fraction, $scale, '0');
    }

    private static function decimalString(int $units, int $scale): string
    {
        if ($units < 0 || $scale < 0 || $scale > 2) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_AMOUNT');
        }
        if ($scale === 0) return (string) $units;
        $factor = 10 ** $scale;
        return intdiv($units, $factor) . '.' . str_pad((string) ($units % $factor), $scale, '0', STR_PAD_LEFT);
    }

    private static function moneyFact(
        array $value,
        bool $allowZero,
        string $provider,
        string $expectedKind
    ): array {
        if (!self::exactKeys($value, ['amount', 'currency', 'source'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_FACT');
        }
        $amount = $value['amount'];
        if (!is_string($amount)
            || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $amount)
            || (!$allowZero && !preg_match('/[1-9]/', $amount))) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_AMOUNT');
        }
        $currency = $value['currency'];
        if (!is_string($currency) || !preg_match('/\A[A-Z][A-Z0-9_]{2,7}\z/D', $currency)) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_CURRENCY');
        }
        $source = $value['source'];
        if (!is_string($source) || $source !== $provider . '_' . $expectedKind) {
            throw new InvalidArgumentException('THREE_PROVIDER_MONEY_SOURCE');
        }
        return ['amount' => $amount, 'currency' => $currency, 'source' => $source];
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }
}
