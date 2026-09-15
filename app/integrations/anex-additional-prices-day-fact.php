<?php
declare(strict_types=1);

/**
 * Project context-verified direct-ANEX AdditionalPricesDaily observations onto
 * a day-level money fact. This deliberately does not call the supplier and
 * does not assign fuel/package/final-price semantics.
 *
 * Day identity: ANEX tour/program + departure date + provider currency.
 * `nights` remains observation provenance. Different nights may collapse only
 * when every retained APD money field is identical; disagreement fails closed.
 */
function anytour_anex_apd_day_fact(array $observations): array
{
    if ($observations === [] || array_keys($observations) !== range(0, count($observations) - 1)) {
        return ['state' => 'unknown', 'reason' => 'no_observations'];
    }

    $identity = null;
    $money = null;
    $nights = [];
    foreach ($observations as $observation) {
        if (!is_array($observation) || ($observation['context_verified'] ?? null) !== true) {
            return ['state' => 'unknown', 'reason' => 'unverified_context'];
        }
        $tour = anytour_anex_apd_day_positive_int($observation['tour'] ?? null, 999999999);
        $currency = anytour_anex_apd_day_positive_int($observation['currency'] ?? null, 999999999);
        $night = anytour_anex_apd_day_positive_int($observation['nights'] ?? null, 60);
        $date = $observation['date_beg'] ?? null;
        if ($tour === null || $currency === null || $night === null || !is_string($date)
            || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $date, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return ['state' => 'unknown', 'reason' => 'invalid_context'];
        }
        $candidateIdentity = ['tour' => $tour, 'date_beg' => $date, 'currency' => $currency];
        if ($identity === null) $identity = $candidateIdentity;
        elseif ($identity !== $candidateIdentity) return ['state' => 'conflict', 'reason' => 'day_identity_mismatch'];

        $candidateMoney = [];
        foreach (['price_adult', 'price_child', 'cashrate', 'price_converted_adult', 'price_converted_child'] as $field) {
            $candidateMoney[$field] = anytour_anex_apd_day_decimal($observation[$field] ?? null);
        }
        if ($candidateMoney['price_adult'] === null || $candidateMoney['price_child'] === null) {
            return ['state' => 'unknown', 'reason' => 'money_unknown'];
        }
        if ($money === null) $money = $candidateMoney;
        elseif ($money !== $candidateMoney) return [
            'state' => 'conflict',
            'reason' => 'money_differs_by_nights',
            'identity' => $identity,
            'observed_nights' => anytour_anex_apd_day_sorted_nights(array_merge($nights, [$night])),
        ];
        $nights[] = $night;
    }

    return [
        'state' => 'observed',
        'identity' => $identity,
        'money' => $money,
        'observed_nights' => anytour_anex_apd_day_sorted_nights($nights),
        'source' => 'AdditionalPricesDaily',
        'fuel_equivalence_verified' => false,
        'final_price_verified' => false,
        'arithmetic_applied' => false,
    ];
}

function anytour_anex_apd_day_positive_int($value, int $max): ?int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/\A[1-9][0-9]{0,8}\z/D', (string) $value)) return null;
    $value = (int) $value;
    return $value <= $max ? $value : null;
}

function anytour_anex_apd_day_decimal($value): ?string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string) $value;
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?\z/D', $value)) return null;
    return $value;
}

function anytour_anex_apd_day_sorted_nights(array $nights): array
{
    $nights = array_values(array_unique(array_map('intval', $nights)));
    sort($nights, SORT_NUMERIC);
    return $nights;
}
