<?php
declare(strict_types=1);

/**
 * Provider-neutral money facts for Search3 offers.
 *
 * This object deliberately does no arithmetic. Search price, fuel, additional charges,
 * package buyer money and quote money are independent facts with independent evidence.
 * The search-layer constructor never invents package/quote values and never marks a
 * search price final.
 */
final class AnyTourThreeProviderMoneyFacts
{
    private const PROVIDERS = ['tourvisor', 'anex', 'andromeda'];
    private const SEARCH_CAPABILITIES = [
        'tourvisor' => ['fuel' => true, 'additional' => false],
        'anex' => ['fuel' => false, 'additional' => true],
        'andromeda' => ['fuel' => false, 'additional' => false],
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
