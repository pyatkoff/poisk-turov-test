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

        $aggregation = 'single_distinct_party_markup';
        $markup = null;
        $converted = null;
        if (count($markupFacts) === 1) {
            $markup = array_values($markupFacts)[0];
            $converted = self::convert($markup['amount'], $markup['currency'], $targetCurrency, $rates);
        } else {
            // Owner-approved search fallback (2026-09-18): when get_flights returns
            // several choice-dependent markup values, use the numerical minimum only
            // if the FULL claim proves one complete required one-item roundtrip and
            // every eligible option in both required flight groups has one valid,
            // convertible markup. A party surcharge must be selectable on both
            // directions; never manufacture a value from only one leg.
            $minimum = self::minimumCompleteRequiredRoundtripMarkup($claim, $targetCurrency, $rates);
            if ($minimum !== null) {
                $markup = $minimum['reported'];
                $converted = $minimum['converted'];
                $aggregation = 'minimum_complete_required_roundtrip_markup';
            }
        }
        if ($markup === null || $converted === null) return $result;

        $result['transport_markup_reported'] = $markup + [
            'source' => 'andromeda_get_flights_transport',
            'aggregation' => $aggregation,
        ];

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
     * Return one direct supplier-reported exchange rate without using floats.
     *
     * Andromeda claim money rows are relative rates. The existing exact pricing
     * converter uses toRate/fromRate; expose that same interpretation so retained
     * direction evidence can carry a typed native->RUB rate into the existing
     * operator-fuel handoff. Missing/ambiguous currencies remain unknown.
     */
    public static function directExchangeRate(array $claim, string $from, string $to): ?string
    {
        if (preg_match('/^[A-Z]{3}$/D', $from) !== 1 || preg_match('/^[A-Z]{3}$/D', $to) !== 1) {
            throw new InvalidArgumentException('ANDROMEDA_SEARCH_SURCHARGE_CURRENCY');
        }
        if ($from === $to) return '1';
        $rates = self::rates(self::document($claim));
        if (!isset($rates[$from], $rates[$to])) return null;
        $fromRate = self::units($rates[$from]['rate'], 6);
        $toRate = self::units($rates[$to]['rate'], 6);
        if ($fromRate === null || $toRate === null || $fromRate < 1 || $toRate < 1) return null;
        return self::divideRate($toRate, $fromRate, 8);
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

    /**
     * Search-only fallback for choice-dependent money. The full shape must have
     * exactly one required one-item flight group per direction in one variant.
     * Every option in those two groups must expose one unambiguous markup that can
     * be converted to the target currency. Because the markup is party-scoped,
     * only values available in BOTH directions are valid roundtrip candidates.
     *
     * @return array{reported:array{amount:string,currency:string},converted:string}|null
     */
    private static function minimumCompleteRequiredRoundtripMarkup(
        array $claim,
        string $targetCurrency,
        array $rates
    ): ?array {
        $required = [];
        foreach (($claim['groups'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['group'] ?? null)) continue;
            foreach ($block['group'] as $group) {
                if (!is_array($group) || (string)($group['required'] ?? '') !== 'true') continue;
                $id = (string)($group['id'] ?? '');
                if ($id === '' || strlen($id) > 128) return null;
                $required[$id] = (string)($group['oneItem'] ?? '') === 'true';
            }
        }
        if ($required === []) return null;

        $variants = [];
        foreach (($claim['variants'] ?? []) as $variant) {
            if (is_array($variant)) $variants[] = $variant;
        }
        // Cross-variant option compatibility is not inferable. Stay unknown unless
        // one complete variant owns the entire required roundtrip choice set.
        if (count($variants) !== 1) return null;

        $groupDirection = [];
        $groupValues = [];
        $reportedByUnits = [];
        foreach (($variants[0]['transports'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
            foreach ($block['transport'] as $item) {
                if (!is_array($item) || ($item['type'] ?? null) !== 'ttAvia') continue;
                $groupId = (string)($item['groupId'] ?? '');
                if (!array_key_exists($groupId, $required)) continue;
                if ($required[$groupId] !== true) return null;
                $direction = (string)($item['direction'] ?? '');
                if ($direction !== '0' && $direction !== '1') return null;
                if (isset($groupDirection[$groupId]) && $groupDirection[$groupId] !== $direction) return null;
                $groupDirection[$groupId] = $direction;

                $markup = self::transportMarkupFact($item);
                if ($markup === null) return null;
                $converted = self::convert($markup['amount'], $markup['currency'], $targetCurrency, $rates);
                if ($converted === null) return null;
                $units = self::units($converted, 2);
                if ($units === null) return null;
                $groupValues[$groupId][$units] = true;
                $reportedByUnits[$units] ??= $markup;
            }
        }

        $byDirection = ['0' => [], '1' => []];
        foreach ($groupDirection as $groupId => $direction) {
            if (($groupValues[$groupId] ?? []) === []) return null;
            $byDirection[$direction][] = $groupId;
        }
        // A bounded roundtrip fallback only. More than one required flight group
        // on a direction can carry hidden dependency semantics, so fail closed.
        if (count($byDirection['0']) !== 1 || count($byDirection['1']) !== 1) return null;

        $out = $groupValues[$byDirection['0'][0]];
        $back = $groupValues[$byDirection['1'][0]];
        $common = array_intersect_key($out, $back);
        if ($common === []) return null;
        $units = array_keys($common);
        sort($units, SORT_NUMERIC);
        $minimumUnits = (int)$units[0];
        $reported = $reportedByUnits[$minimumUnits] ?? null;
        if (!is_array($reported)) return null;
        return [
            'reported' => $reported,
            'converted' => self::fromUnits($minimumUnits, 2),
        ];
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

    private static function divideRate(int $numerator, int $denominator, int $scale): ?string
    {
        if ($numerator < 1 || $denominator < 1 || $scale < 1 || $scale > 8) return null;
        $whole = intdiv($numerator, $denominator);
        if ($whole > 999999) return null;
        $remainder = $numerator % $denominator;
        $digits = [];
        for ($i = 0; $i <= $scale; ++$i) {
            if ($remainder > intdiv(PHP_INT_MAX, 10)) return null;
            $remainder *= 10;
            $digits[] = intdiv($remainder, $denominator);
            $remainder %= $denominator;
        }
        $round = array_pop($digits);
        if ($round >= 5) {
            for ($i = count($digits) - 1; $i >= 0; --$i) {
                if ($digits[$i] < 9) {
                    ++$digits[$i];
                    $round = 0;
                    break;
                }
                $digits[$i] = 0;
            }
            if ($round !== 0) {
                ++$whole;
                if ($whole > 999999) return null;
            }
        }
        $fraction = rtrim(implode('', array_map('strval', $digits)), '0');
        return (string)$whole . ($fraction === '' ? '' : '.' . $fraction);
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
