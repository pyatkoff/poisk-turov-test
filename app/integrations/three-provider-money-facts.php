<?php
declare(strict_types=1);

/**
 * Provider-neutral money facts for Search3 offers.
 *
 * Raw search price, fuel/additional charges, package buyer money and quote money stay
 * independent evidence facts. A search-only surcharge estimate may additionally sum
 * a supplier-reported per-passenger flight-program surcharge for the current party;
 * that estimate is never marked as the final supplier price.
 */
final class AnyTourThreeProviderMoneyFacts
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const SEARCH_CAPABILITIES = [
        'tourvisor' => ['fuel' => true, 'additional' => false],
        'anex' => ['fuel' => false, 'additional' => true],
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
            'package_buyer_price' => null,
            'quote_price' => null,
            'search_price_fuel_relation' => 'unknown',
            'final_price_verified' => false,
            'arithmetic_applied' => false,
        ];
    }

    /**
     * Add supplier-reported flight-program/date surcharge to the search package price.
     * Adult and child rates are intentionally separate. Missing required rate is unknown,
     * never zero. Raw source facts remain untouched and the result is not a final quote.
     */
    public static function withSearchSurchargeEstimate(array $searchFacts, int $adults, int $children): array
    {
        self::assertCanonicalSearchFacts($searchFacts);
        if (!in_array($searchFacts['provider'], ['anex', 'andromeda'], true)
            || $adults < 1 || $adults > 6 || $children < 0 || $children > 3) {
            throw new InvalidArgumentException('THREE_PROVIDER_SURCHARGE_CONTEXT');
        }

        $rates = [];
        foreach ($searchFacts['additional_prices_reported'] as $fact) {
            $kind = $fact['kind'];
            if (!in_array($kind, ['fuel_adult', 'fuel_child'], true)) continue;
            if (isset($rates[$kind])) throw new InvalidArgumentException('THREE_PROVIDER_SURCHARGE_AMBIGUOUS');
            if ($fact['currency'] !== $searchFacts['search_price']['currency']) {
                throw new DomainException('THREE_PROVIDER_SURCHARGE_UNKNOWN');
            }
            $rates[$kind] = $fact;
        }
        if (!isset($rates['fuel_adult']) || ($children > 0 && !isset($rates['fuel_child']))) {
            throw new DomainException('THREE_PROVIDER_SURCHARGE_UNKNOWN');
        }

        $base = self::moneyCents($searchFacts['search_price']['amount']);
        $adult = self::moneyCents($rates['fuel_adult']['amount']);
        $child = $children === 0 ? 0 : self::moneyCents($rates['fuel_child']['amount']);
        $surcharge = ($adult * $adults) + ($child * $children);
        if ($surcharge < 0 || $base > PHP_INT_MAX - $surcharge) {
            throw new InvalidArgumentException('THREE_PROVIDER_SURCHARGE_AMOUNT');
        }
        $keepDecimals = self::hasDecimals($searchFacts['search_price']['amount'])
            || self::hasDecimals($rates['fuel_adult']['amount'])
            || ($children > 0 && self::hasDecimals($rates['fuel_child']['amount']));

        $next = $searchFacts;
        $next['search_price_with_surcharge'] = [
            'amount' => self::moneyFromCents($base + $surcharge, $keepDecimals),
            'currency' => $searchFacts['search_price']['currency'],
            'source' => $searchFacts['provider'] . '_search_estimate',
        ];
        $next['search_surcharge_total'] = [
            'amount' => self::moneyFromCents($surcharge, $keepDecimals),
            'currency' => $searchFacts['search_price']['currency'],
            'source' => $searchFacts['provider'] . '_additional',
        ];
        $next['search_surcharge_party'] = ['adults' => $adults, 'children' => $children];
        $next['search_price_fuel_relation'] = 'base_plus_per_passenger_program_surcharge';
        $next['arithmetic_applied'] = true;
        return $next;
    }

    /** Attach a supplier-verified quote without changing any raw search money fact. */
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

    private static function moneyCents(string $amount): int
    {
        if (!preg_match('/\A(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?\z/D', $amount, $m)) {
            throw new InvalidArgumentException('THREE_PROVIDER_SURCHARGE_AMOUNT');
        }
        $fraction = str_pad($m[2] ?? '', 2, '0');
        return ((int) $m[1] * 100) + (int) $fraction;
    }

    private static function moneyFromCents(int $cents, bool $keepDecimals): string
    {
        if ($cents < 0) throw new InvalidArgumentException('THREE_PROVIDER_SURCHARGE_AMOUNT');
        $whole = intdiv($cents, 100);
        $fraction = $cents % 100;
        if (!$keepDecimals && $fraction === 0) return (string) $whole;
        return $whole . '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    private static function hasDecimals(string $amount): bool
    {
        return strpos($amount, '.') !== false;
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
