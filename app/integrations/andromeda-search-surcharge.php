<?php
declare(strict_types=1);

/**
 * Derive a Search3 display-price estimate from the already supplier-returned
 * Andromeda get_flights claim.
 *
 * Owner rule (2026-09-14): the transport markup is the flight-program surcharge
 * for the current tourist party. It is added once to the package search price;
 * identical markup repeated on outbound/return ttAvia rows is evidence of the same
 * party surcharge, not two charges. If get_flights exposes materially different
 * markup facts, the exact surcharge remains unknown until a concrete flight is chosen.
 * Final price is still established only by selected-tour/flight actualization.
 */
final class AnyTourAndromedaSearchSurcharge
{
    public static function estimate(array $claim, array $searchPrice): array
    {
        $base = self::money($searchPrice['amount'] ?? null, 2);
        $targetCurrency = $searchPrice['currency'] ?? null;
        if ($base === null || !is_string($targetCurrency)
            || preg_match('/^[A-Z0-9_]{2,8}$/D', $targetCurrency) !== 1) {
            throw new InvalidArgumentException('ANDROMEDA_SEARCH_SURCHARGE_PRICE');
        }
        $doc = self::document($claim);
        $markupFacts = self::markupFacts($claim);
        $rates = self::rates($doc);

        $result = [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'search_price' => ['amount' => $base, 'currency' => $targetCurrency],
            'transport_markup_reported' => null,
            'operator_currency_rates_reported' => array_values($rates),
            'party_surcharge' => null,
            'search_price_with_surcharge' => null,
            'surcharge_scope' => 'party',
            'final_price_verified' => false,
            'arithmetic_applied' => false,
            'state' => 'unknown',
        ];

        // One distinct value may be repeated on both directions. Different values
        // mean the flight choice affects money, so search must stay fail-closed.
        if (count($markupFacts) !== 1) return $result;
        $markup = array_values($markupFacts)[0];
        $result['transport_markup_reported'] = $markup + [
            'source' => 'andromeda_get_flights_transport',
            'aggregation' => 'single_distinct_party_markup',
        ];

        $converted = self::convert($markup['amount'], $markup['currency'], $targetCurrency, $rates);
        if ($converted === null) return $result;
        $total = self::addMoney($base, $converted);
        if ($total === null) return $result;

        $result['party_surcharge'] = [
            'amount' => $converted,
            'currency' => $targetCurrency,
            'source' => $markup['currency'] === $targetCurrency
                ? 'andromeda_get_flights_transport'
                : 'andromeda_get_flights_transport_converted',
        ];
        $result['search_price_with_surcharge'] = [
            'amount' => $total,
            'currency' => $targetCurrency,
            'source' => 'derived_search_estimate',
        ];
        $result['arithmetic_applied'] = true;
        $result['state'] = 'estimated';
        return $result;
    }

    /**
     * Private server-side strategy for a choice-dependent get_flights claim.
     *
     * This chooses the lowest safely comparable supplier-reported transport
     * markup independently for the required outbound and return directions.
     * The chosen markup is NOT a customer price. The caller must still apply
     * both selections through changeservice and accept only supplier calc.
     *
     * @return array{selected:array<int,array>,candidate_counts:array<string,int>,target_currency:string}|null
     */
    public static function cheapestRequiredFlightSelection(array $claim, array $searchPrice): ?array
    {
        $base = self::money($searchPrice['amount'] ?? null, 2);
        $targetCurrency = $searchPrice['currency'] ?? null;
        if ($base === null || !is_string($targetCurrency)
            || preg_match('/^[A-Z0-9_]{2,8}$/D', $targetCurrency) !== 1) {
            throw new InvalidArgumentException('ANDROMEDA_SEARCH_SURCHARGE_PRICE');
        }
        $doc = self::document($claim);
        $rates = self::rates($doc);
        $required = [];
        foreach (($claim['groups'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['group'] ?? null)) continue;
            foreach ($block['group'] as $group) {
                if (!is_array($group)
                    || (string)($group['required'] ?? '') !== 'true'
                    || (string)($group['oneItem'] ?? '') !== 'true') continue;
                $id = (string)($group['id'] ?? '');
                if ($id !== '' && strlen($id) <= 128) $required[$id] = true;
            }
        }
        if ($required === []) return null;

        $options = ['0' => [], '1' => []];
        foreach (($claim['variants'] ?? []) as $variant) {
            if (!is_array($variant)) continue;
            foreach (($variant['transports'] ?? []) as $block) {
                if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
                foreach ($block['transport'] as $item) {
                    if (!is_array($item) || ($item['type'] ?? null) !== 'ttAvia') continue;
                    $direction = (string)($item['direction'] ?? '');
                    $groupId = (string)($item['groupId'] ?? '');
                    $uid = $item['uid'] ?? null;
                    if (!isset($options[$direction]) || !isset($required[$groupId])
                        || !is_string($uid) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $uid) !== 1) {
                        continue;
                    }
                    $markup = self::transportMarkupFact($item);
                    if ($markup === null) continue;
                    $converted = self::convert(
                        $markup['amount'], $markup['currency'], $targetCurrency, $rates
                    );
                    if ($converted === null) continue;
                    $units = self::units($converted, 2);
                    if ($units === null) continue;
                    $options[$direction][] = [
                        'item' => $item,
                        'converted_units' => $units,
                    ];
                }
            }
        }

        $selected = [];
        $counts = [];
        foreach (['0', '1'] as $direction) {
            $counts[$direction] = count($options[$direction]);
            if ($options[$direction] === []) return null;
            $best = $options[$direction][0];
            foreach (array_slice($options[$direction], 1) as $candidate) {
                if ($candidate['converted_units'] < $best['converted_units']) $best = $candidate;
            }
            $selected[(int)$direction] = $best['item'];
        }
        return [
            'selected' => $selected,
            'candidate_counts' => $counts,
            'target_currency' => $targetCurrency,
        ];
    }

    public static function diagnostic(array $claim): array
    {
        $doc = self::document($claim);
        $ttAvia = 0;
        $details = 0;
        $markupKeys = 0;
        $currencies = [];
        foreach (($claim['variants'] ?? []) as $variant) {
            if (!is_array($variant)) continue;
            foreach (($variant['transports'] ?? []) as $block) {
                if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
                foreach ($block['transport'] as $transport) {
                    if (!is_array($transport) || ($transport['type'] ?? null) !== 'ttAvia') continue;
                    ++$ttAvia;
                    foreach (($transport['details'] ?? []) as $detailBlock) {
                        if (!is_array($detailBlock) || !is_array($detailBlock['detail'] ?? null)) continue;
                        foreach ($detailBlock['detail'] as $detail) {
                            if (!is_array($detail)) continue;
                            ++$details;
                            if (array_key_exists('markup', $detail)) ++$markupKeys;
                            $currency = $detail['currency'] ?? null;
                            if (is_string($currency) && preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) === 1) {
                                $currencies[$currency] = true;
                            }
                        }
                    }
                }
            }
        }
        $facts = self::markupFacts($claim);
        $rates = self::rates($doc);
        $factCurrencies = [];
        foreach ($facts as $fact) $factCurrencies[$fact['currency']] = true;
        $rateCurrencies = [];
        foreach ($rates as $currency => $unused) $rateCurrencies[$currency] = true;
        $sort = static function(array $set): array {
            $keys = array_keys($set);
            sort($keys, SORT_STRING);
            return $keys;
        };
        return [
            'schema_version' => 1,
            'ttavia_option_count' => $ttAvia,
            'transport_detail_count' => $details,
            'markup_key_count' => $markupKeys,
            'valid_markup_fact_count' => count($facts),
            'distinct_markup_count' => count($facts),
            'detail_currencies' => $sort($currencies),
            'markup_currencies' => $sort($factCurrencies),
            'operator_rate_currencies' => $sort($rateCurrencies),
        ];
    }

    private static function document(array $claim): array
    {
        if (!is_array($claim['claimDocument'] ?? null)
            || array_keys($claim['claimDocument']) !== [0]
            || !is_array($claim['claimDocument'][0])) {
            throw new InvalidArgumentException('ANDROMEDA_SEARCH_SURCHARGE_CLAIM');
        }
        return $claim['claimDocument'][0];
    }

    private static function transportMarkupFact(array $transport): ?array
    {
        $facts = [];
        foreach (($transport['details'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['detail'] ?? null)) continue;
            foreach ($block['detail'] as $detail) {
                if (!is_array($detail)) continue;
                $amount = self::money($detail['markup'] ?? null, 2, true);
                $currency = $detail['currency'] ?? null;
                if ($amount === null || !is_string($currency)
                    || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) continue;
                $units = self::units($amount, 2);
                if ($units === null) continue;
                $facts[$currency . "\0" . $units] ??= [
                    'amount' => $amount,
                    'currency' => $currency,
                ];
            }
        }
        return count($facts) === 1 ? array_values($facts)[0] : null;
    }

    private static function markupFacts(array $claim): array
    {
        $facts = [];
        foreach (($claim['variants'] ?? []) as $variant) {
            if (!is_array($variant)) continue;
            foreach (($variant['transports'] ?? []) as $block) {
                if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
                foreach ($block['transport'] as $transport) {
                    if (!is_array($transport) || ($transport['type'] ?? null) !== 'ttAvia') continue;
                    foreach (($transport['details'] ?? []) as $details) {
                        if (!is_array($details) || !is_array($details['detail'] ?? null)) continue;
                        foreach ($details['detail'] as $detail) {
                            if (!is_array($detail)) continue;
                            $amount = self::money($detail['markup'] ?? null, 2, true);
                            $currency = $detail['currency'] ?? null;
                            if ($amount === null || !is_string($currency)
                                || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) continue;
                            // Compare decimal value, not supplier formatting; keep the first raw fact.
                            $units = self::units($amount, 2);
                            if ($units === null) continue;
                            $facts[$currency . "\0" . $units] ??= [
                                'amount' => $amount,
                                'currency' => $currency,
                            ];
                        }
                    }
                }
            }
        }
        return $facts;
    }

    private static function rates(array $doc): array
    {
        $rates = [];
        foreach (($doc['moneys'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['money'] ?? null)) continue;
            foreach ($block['money'] as $money) {
                if (!is_array($money)) continue;
                $currency = $money['currency'] ?? null;
                $rate = self::money($money['rate'] ?? null, 6);
                if ($rate === null || !is_string($currency)
                    || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1
                    || $rate === '0') continue;
                $isClaim = (string)($money['isClaimCurrency'] ?? '');
                $row = [
                    'currency' => $currency,
                    'rate' => $rate,
                    'is_claim_currency' => $isClaim === 'true' ? true : ($isClaim === 'false' ? false : null),
                    'source' => 'andromeda_claim_money',
                ];
                if (isset($rates[$currency])
                    && (self::units($rates[$currency]['rate'], 6) !== self::units($rate, 6)
                        || $rates[$currency]['is_claim_currency'] !== $row['is_claim_currency'])) {
                    // Ambiguous rate means conversion cannot be used safely.
                    $rates[$currency] = null;
                } elseif (!array_key_exists($currency, $rates)) {
                    $rates[$currency] = $row;
                }
            }
        }
        return array_filter($rates, static fn($row) => is_array($row));
    }

    private static function convert(string $amount, string $from, string $to, array $rates): ?string
    {
        if ($from === $to) return self::fixed($amount, 2);
        if (!isset($rates[$from], $rates[$to])) return null;
        $fromRate = self::units($rates[$from]['rate'], 6);
        $toRate = self::units($rates[$to]['rate'], 6);
        $money = self::units($amount, 2);
        if ($fromRate === null || $toRate === null || $money === null || $fromRate === 0) return null;
        if ($money > intdiv(PHP_INT_MAX, $toRate)) return null;
        // Rates are supplier-reported relative rates. Preserve cents with half-up rounding.
        $numerator = $money * $toRate;
        $cents = intdiv($numerator + intdiv($fromRate, 2), $fromRate);
        return self::fromUnits($cents, 2);
    }

    private static function addMoney(string $left, string $right): ?string
    {
        $a = self::units($left, 2); $b = self::units($right, 2);
        if ($a === null || $b === null || $a > PHP_INT_MAX - $b) return null;
        return self::fromUnits($a + $b, 2);
    }

    private static function money(mixed $value, int $maxScale, bool $allowZero = false): ?string
    {
        if (is_int($value)) $value = (string)$value;
        if (is_float($value) && is_finite($value)) $value = rtrim(rtrim(sprintf('%.'.$maxScale.'F', $value), '0'), '.');
        if (!is_string($value)
            || !preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,'.$maxScale.'})?$/D', $value)
            || (!$allowZero && preg_match('/[1-9]/', $value) !== 1)) return null;
        return $value;
    }

    private static function fixed(string $value, int $scale): ?string
    {
        $units = self::units($value, $scale);
        return $units === null ? null : self::fromUnits($units, $scale);
    }

    private static function units(string $value, int $scale): ?int
    {
        if (!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.([0-9]{1,6}))?$/D', $value, $m)) return null;
        $fraction = $m[1] ?? '';
        if (strlen($fraction) > $scale) {
            $extra = substr($fraction, $scale);
            if (preg_match('/[1-9]/', $extra)) return null;
            $fraction = substr($fraction, 0, $scale);
        }
        $factor = 10 ** $scale;
        $whole = (int)strtok($value, '.');
        if ($whole > intdiv(PHP_INT_MAX, $factor)) return null;
        return $whole * $factor + (int)str_pad($fraction, $scale, '0');
    }

    private static function fromUnits(int $units, int $scale): string
    {
        $factor = 10 ** $scale;
        return intdiv($units, $factor) . ($scale === 0 ? '' : '.' . str_pad((string)($units % $factor), $scale, '0', STR_PAD_LEFT));
    }
}
