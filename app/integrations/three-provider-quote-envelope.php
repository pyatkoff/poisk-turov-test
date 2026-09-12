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
    private const REPORTED_QUOTE_KEYS = [
        'fuel_surcharges_reported', 'operator_currency_rates_reported',
        'calc_money_facts_reported',
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

        $reported = self::assertVerifiedQuote($quote);
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
        // Keep supplier-reported evidence separate from canonical price fields. These
        // rows are copied only after strict validation; no totals, rates or deltas are
        // derived and their relation to search/final price remains intentionally unknown.
        $money['fuel_surcharges_reported'] = $reported['fuel_surcharges_reported'];
        $money['operator_currency_rates_reported'] = $reported['operator_currency_rates_reported'];
        $money['calc_money_facts_reported'] = $reported['calc_money_facts_reported'];
        $money['transport_markups_reported'] = self::transportMarkupsReported($quote['flights']);

        $evidence = [
            'provider' => 'andromeda',
            'local_hotel_id' => $offer['local_hotel_id'],
            'operator' => $offer['operator']['raw'],
            'search_price' => $search,
            'package_price' => $package,
            'final_price' => $final,
            'reported_money_facts' => [
                'fuel_surcharges_reported' => $money['fuel_surcharges_reported'],
                'operator_currency_rates_reported' => $money['operator_currency_rates_reported'],
                'calc_money_facts_reported' => $money['calc_money_facts_reported'],
                'transport_markups_reported' => $money['transport_markups_reported'],
            ],
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

    private static function assertVerifiedQuote(array $quote): array
    {
        $legacy = self::exactKeys($quote, self::QUOTE_KEYS);
        $current = self::exactKeys($quote, array_merge(self::QUOTE_KEYS, self::REPORTED_QUOTE_KEYS));
        if ((!$legacy && !$current)
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
        if ($legacy) {
            return [
                'fuel_surcharges_reported' => [],
                'operator_currency_rates_reported' => [],
                'calc_money_facts_reported' => [],
            ];
        }
        if (!is_array($quote['fuel_surcharges_reported'])
            || !is_array($quote['operator_currency_rates_reported'])
            || !is_array($quote['calc_money_facts_reported'])) {
            throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_REPORTED_FACTS');
        }
        return [
            'fuel_surcharges_reported' => self::fuelSurchargesReported($quote['fuel_surcharges_reported']),
            'operator_currency_rates_reported' => self::operatorCurrencyRatesReported($quote['operator_currency_rates_reported']),
            'calc_money_facts_reported' => self::calcMoneyFactsReported($quote['calc_money_facts_reported']),
        ];
    }

    private static function fuelSurchargesReported(array $rows): array
    {
        self::listShape($rows, 'THREE_PROVIDER_QUOTE_FUEL_FACT');
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !self::exactKeys($row, ['amount', 'currency', 'route_index', 'source'])
                || !self::amount($row['amount'], false)
                || !self::currency($row['currency'])
                || !in_array($row['route_index'], [null, '0', '1'], true)
                || $row['source'] !== 'andromeda_claim_service') {
                throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_FUEL_FACT');
            }
            $out[] = $row;
        }
        return $out;
    }

    private static function operatorCurrencyRatesReported(array $rows): array
    {
        self::listShape($rows, 'THREE_PROVIDER_QUOTE_RATE_FACT');
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !self::exactKeys($row, [
                    'currency', 'rate', 'is_claim_currency', 'source', 'arithmetic_applied'
                ])
                || !self::currency($row['currency'])
                || !is_string($row['rate'])
                || preg_match('/\A(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,6})?\z/D', $row['rate']) !== 1
                || preg_match('/[1-9]/', $row['rate']) !== 1
                || !(is_bool($row['is_claim_currency']) || $row['is_claim_currency'] === null)
                || $row['source'] !== 'andromeda_claim_money'
                || $row['arithmetic_applied'] !== false) {
                throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_RATE_FACT');
            }
            $out[] = $row;
        }
        return $out;
    }

    private static function calcMoneyFactsReported(array $rows): array
    {
        self::listShape($rows, 'THREE_PROVIDER_QUOTE_CALC_MONEY_FACT');
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !self::exactKeys($row, [
                    'currency', 'gross_amount', 'net_amount', 'commissionable_amount',
                    'commission_amount', 'source', 'arithmetic_applied'
                ])
                || !self::currency($row['currency'])
                || !self::amount($row['gross_amount'], true)
                || !self::nullableAmount($row['net_amount'])
                || !self::nullableAmount($row['commissionable_amount'])
                || !self::nullableAmount($row['commission_amount'])
                || $row['source'] !== 'andromeda_calc_money'
                || $row['arithmetic_applied'] !== false) {
                throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_CALC_MONEY_FACT');
            }
            $out[] = $row;
        }
        return $out;
    }

    private static function transportMarkupsReported(array $flights): array
    {
        self::listShape($flights, 'THREE_PROVIDER_QUOTE_FLIGHT');
        $out = [];
        foreach ($flights as $flight) {
            if (!is_array($flight)) {
                throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_FLIGHT');
            }
            $fact = $flight['transport_markup_reported'] ?? null;
            if ($fact === null) continue;
            if (!is_array($fact) || !self::exactKeys($fact, ['amount', 'currency', 'source', 'aggregation'])
                || !self::amount($fact['amount'], false)
                || !self::currency($fact['currency'])
                || $fact['source'] !== 'andromeda_transport_detail'
                || $fact['aggregation'] !== 'unknown') {
                throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_TRANSPORT_MARKUP_FACT');
            }
            $direction = $flight['direction'] ?? null;
            if (!in_array($direction, [null, '0', '1'], true)) {
                throw new InvalidArgumentException('THREE_PROVIDER_QUOTE_TRANSPORT_MARKUP_FACT');
            }
            $out[] = ['direction' => $direction] + $fact;
        }
        return $out;
    }

    private static function listShape(array $rows, string $error): void
    {
        if ($rows !== [] && array_keys($rows) !== range(0, count($rows) - 1)) {
            throw new InvalidArgumentException($error);
        }
    }

    private static function nullableAmount($value): bool
    {
        return $value === null || self::amount($value, false);
    }

    private static function amount($value, bool $positive): bool
    {
        return is_string($value)
            && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value) === 1
            && (!$positive || preg_match('/[1-9]/', $value) === 1);
    }

    private static function currency($value): bool
    {
        return is_string($value) && preg_match('/\A[A-Z0-9_]{2,8}\z/D', $value) === 1;
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
