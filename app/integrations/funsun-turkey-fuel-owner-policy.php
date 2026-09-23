<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Owner-authoritative FUN&SUN Turkey listing fallback (23.09.2026).
 *
 * This is deliberately an estimate, not a flight/date tariff. It applies only when
 * a more specific exact/transport/program/operator rule is absent. The selected tour
 * must still be actualized separately. Native EUR is retained and converted through
 * the existing fresh INT FX evidence; no RUB constant is stored as source truth.
 */
final class AnyTourFunsunTurkeyFuelOwnerPolicyV1
{
    private const POLICY_DATE = '2026-09-23';
    private const NATIVE_AMOUNT = '70.00';

    public static function forTarget(array $target, array $exchange, int $now): ?array
    {
        try {
            if ($now < 1) return null;
            $operator = $target['operator'] ?? null;
            if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) !== 'fun_and_sun') return null;
            $direction = self::targetDirection($target, $operator);
            if ($direction !== self::turkeyDirection()) return null;
            $party = self::party($target['party'] ?? null);
            if (self::hasInfant($party)) return null;
            $offerDigest = self::digest($target['offer_ref_digest'] ?? null);
            $fx = self::exchange($exchange, $direction, $now);
            $passengers = $party['adults'] + $party['children'];
            if ($passengers < 1) return null;
            $native = self::units(self::NATIVE_AMOUNT);
            if ($native > intdiv(PHP_INT_MAX, $passengers * 2)) return null;
            $nativeTotal = $native * $passengers * 2;

            $policy = [
                'schema_version' => 1,
                'kind' => 'fuel_owner_fallback',
                'operator_family' => 'fun_and_sun',
                'source' => 'owner_policy',
                'policy_date' => self::POLICY_DATE,
                'direction' => $direction,
                'unit' => 'per_person_one_way',
                'amount' => self::NATIVE_AMOUNT,
                'currency' => 'EUR',
                'base_relation' => 'excluded',
                'direction_count' => 2,
                'eligible_passenger_count' => $passengers,
                'applied_native_total' => self::format($nativeTotal),
                'selected_tour_actualization_required' => true,
                'exchange' => $fx,
            ];
            $policy['policy_sha256'] = self::hash($policy);
            return [
                'schema_version' => 1,
                'provider' => 'andromeda',
                'state' => 'estimated',
                'source' => 'funsun_turkey_owner_policy',
                'offer_ref_digest' => $offerDigest,
                'party' => $party,
                'final_price_verified' => false,
                'policy' => $policy,
            ];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    public static function apply(array $dto, array $input, int $now): array
    {
        $hold = static fn(string $reason): array => ['dto'=>$dto, 'applied'=>false, 'reason'=>$reason];
        try {
            if ($now < 1 || ($dto['provider'] ?? null) !== 'andromeda'
                || ($dto['quote_state'] ?? null) !== 'unknown'
                || ($dto['final_price_verified'] ?? null) !== false
                || ($dto['finalPriceReady'] ?? null) !== false
                || ($dto['finalPrice'] ?? null) !== null
                || ($dto['money']['arithmetic_applied'] ?? null) !== false
                || ($dto['money']['additional_prices_reported'] ?? null) !== []) {
                return $hold('funsun_turkey_policy_nonbase_state');
            }
            if (($input['schema_version'] ?? null) !== 1
                || ($input['provider'] ?? null) !== 'andromeda'
                || ($input['state'] ?? null) !== 'estimated'
                || ($input['source'] ?? null) !== 'funsun_turkey_owner_policy'
                || ($input['final_price_verified'] ?? null) !== false) {
                return $hold('funsun_turkey_policy_input');
            }
            $offerDigest = self::digest($input['offer_ref_digest'] ?? null);
            if ($offerDigest !== ($dto['identity']['offer_ref_digest'] ?? null)) {
                return $hold('funsun_turkey_policy_offer_binding');
            }
            $operator = $dto['operator']['raw'] ?? null;
            if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) !== 'fun_and_sun') {
                return $hold('funsun_turkey_policy_operator');
            }
            $party = self::party($input['party'] ?? null);
            if ($party !== self::party($dto['tour']['party'] ?? null) || self::hasInfant($party)) {
                return $hold('funsun_turkey_policy_party');
            }
            $base = $dto['money']['search_price'] ?? null;
            if (!is_array($base) || ($base['currency'] ?? null) !== 'RUB'
                || ($dto['price'] ?? null) !== ($base['amount'] ?? null)) {
                return $hold('funsun_turkey_policy_base');
            }
            $baseUnits = self::units($base['amount'] ?? null);
            if ($baseUnits < 1) return $hold('funsun_turkey_policy_base');
            if (($dto['money']['fuel_charge_reported'] ?? null) !== null) {
                return $hold('funsun_turkey_policy_existing_fuel');
            }

            $policy = $input['policy'] ?? null;
            if (!is_array($policy) || ($policy['schema_version'] ?? null) !== 1
                || ($policy['kind'] ?? null) !== 'fuel_owner_fallback'
                || ($policy['operator_family'] ?? null) !== 'fun_and_sun'
                || ($policy['source'] ?? null) !== 'owner_policy'
                || ($policy['policy_date'] ?? null) !== self::POLICY_DATE
                || ($policy['direction'] ?? null) !== self::turkeyDirection()
                || ($policy['unit'] ?? null) !== 'per_person_one_way'
                || ($policy['amount'] ?? null) !== self::NATIVE_AMOUNT
                || ($policy['currency'] ?? null) !== 'EUR'
                || ($policy['base_relation'] ?? null) !== 'excluded'
                || ($policy['direction_count'] ?? null) !== 2
                || ($policy['eligible_passenger_count'] ?? null) !== ($party['adults'] + $party['children'])
                || ($policy['selected_tour_actualization_required'] ?? null) !== true
                || !is_string($policy['policy_sha256'] ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/D', $policy['policy_sha256']) !== 1) {
                return $hold('funsun_turkey_policy_rule');
            }
            $check = $policy;
            unset($check['policy_sha256']);
            if (!hash_equals(self::hash($check), $policy['policy_sha256'])) {
                return $hold('funsun_turkey_policy_hash');
            }
            $native = self::units(self::NATIVE_AMOUNT);
            $passengers = $party['adults'] + $party['children'];
            if ($native > intdiv(PHP_INT_MAX, $passengers * 2)) {
                return $hold('funsun_turkey_policy_total');
            }
            $nativeTotal = $native * $passengers * 2;
            if (($policy['applied_native_total'] ?? null) !== self::format($nativeTotal)) {
                return $hold('funsun_turkey_policy_total');
            }
            $fx = self::exchange($policy['exchange'] ?? null, self::turkeyDirection(), $now);
            $converted = self::convert($nativeTotal, $fx['rate']);
            if ($baseUnits > 99999999999999 - $converted) {
                return $hold('funsun_turkey_policy_total');
            }
            $total = self::format($baseUnits + $converted);

            $out = $dto;
            $out['money']['fuel_charge_reported'] = [
                'amount'=>self::format($converted),
                'currency'=>'RUB',
                'source'=>'funsun_turkey_owner_fallback',
            ];
            $out['money']['operator_fuel_policy'] = $policy;
            $out['money']['search_price_fuel_relation'] = 'excluded';
            $out['money']['search_price_with_surcharge'] = [
                'amount'=>$total,
                'currency'=>'RUB',
                'source'=>'derived_search_estimate',
            ];
            $out['money']['arithmetic_applied'] = true;
            $out['finalPriceReady'] = true;
            $out['finalPrice'] = $out['price'] = $total;
            $out['final_price_verified'] = false;
            return ['dto'=>$out, 'applied'=>true, 'reason'=>null];
        } catch (Throwable $ignored) {
            return $hold('funsun_turkey_policy_invalid');
        }
    }

    private static function targetDirection(array $target, mixed $operator): array
    {
        if (array_key_exists('direction', $target)) {
            return AnyTourOperatorFuelRuleEvidenceV1::canonicalDirection($operator, $target['direction']);
        }
        if (is_array($target['search_params'] ?? null)) {
            return AnyTourOperatorFuelRuleEvidenceV1::directionFromSearch($operator, $target['search_params']);
        }
        throw new InvalidArgumentException('FUNSUN_TURKEY_POLICY_DIRECTION');
    }

    private static function turkeyDirection(): array
    {
        return [
            'operator_family'=>'fun_and_sun',
            'market'=>'departure:1',
            'destination'=>'country:4',
        ];
    }

    private static function party(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 3
            || !is_int($value['adults'] ?? null) || $value['adults'] < 1 || $value['adults'] > 9
            || !is_int($value['children'] ?? null) || $value['children'] < 0 || $value['children'] > 9
            || !is_array($value['child_ages'] ?? null) || !array_is_list($value['child_ages'])
            || count($value['child_ages']) !== $value['children']) {
            throw new InvalidArgumentException('FUNSUN_TURKEY_POLICY_PARTY');
        }
        $ages = $value['child_ages'];
        foreach ($ages as $age) {
            if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('FUNSUN_TURKEY_POLICY_PARTY');
        }
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$value['adults'], 'children'=>$value['children'], 'child_ages'=>$ages];
    }

    private static function hasInfant(array $party): bool
    {
        foreach ($party['child_ages'] as $age) if ($age < 2) return true;
        return false;
    }

    private static function exchange(mixed $value, array $direction, int $now): array
    {
        if (!is_array($value) || array_is_list($value)
            || ($value['from'] ?? null) !== 'EUR' || ($value['to'] ?? null) !== 'RUB'
            || ($value['source'] ?? null) !== 'andromeda_claim_money'
            || ($value['scope_sha256'] ?? null) !== AnyTourOperatorFuelRuleEvidenceV1::directionDigest($direction)
            || !is_string($value['rate'] ?? null)
            || preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $value['rate']) !== 1
            || preg_match('/[1-9]/', $value['rate']) !== 1
            || !is_int($value['observed_at'] ?? null) || !is_int($value['expires_at'] ?? null)
            || $value['observed_at'] < 1 || $value['observed_at'] > $now || $value['expires_at'] <= $now) {
            throw new InvalidArgumentException('FUNSUN_TURKEY_POLICY_EXCHANGE');
        }
        return [
            'from'=>'EUR',
            'to'=>'RUB',
            'rate'=>$value['rate'],
            'source'=>'andromeda_claim_money',
            'scope_sha256'=>$value['scope_sha256'],
            'observed_at'=>$value['observed_at'],
            'expires_at'=>$value['expires_at'],
            'evidence_sha256'=>self::digest($value['evidence_sha256'] ?? null),
        ];
    }

    private static function digest(mixed $value): string
    {
        if (!is_string($value) || preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('FUNSUN_TURKEY_POLICY_DIGEST');
        }
        return $value;
    }

    private static function units(mixed $value): int
    {
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value) !== 1) {
            throw new InvalidArgumentException('FUNSUN_TURKEY_POLICY_MONEY');
        }
        $parts = explode('.', $value, 2);
        return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
    }

    private static function convert(int $units, string $rate): int
    {
        $parts = explode('.', $rate, 2);
        $scale = strlen($parts[1] ?? '');
        $factor = 10 ** $scale;
        $numerator = (int)$parts[0] * $factor + (int)($parts[1] ?? '0');
        if ($numerator < 1 || $units > intdiv(PHP_INT_MAX, $numerator)) {
            throw new InvalidArgumentException('FUNSUN_TURKEY_POLICY_RATE');
        }
        $product = $units * $numerator;
        $converted = intdiv($product, $factor);
        if ($scale > 0 && $product % $factor >= intdiv($factor, 2)) ++$converted;
        return $converted;
    }

    private static function format(int $units): string
    {
        return intdiv($units, 100) . '.' . str_pad((string)($units % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function hash(mixed $value): string
    {
        $canonical = static function(mixed $v) use (&$canonical): mixed {
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v, SORT_STRING);
            foreach ($v as $key => $item) $v[$key] = $canonical($item);
            return $v;
        };
        return hash('sha256', json_encode($canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
