<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Applies a confirmed reusable operator+direction fuel rate to one provider offer.
 * Exact flights/dates/hotel/nights are provenance only and never bind the rule.
 */
final class AnyTourThreeProviderFuelEvidenceV1
{
    public static function apply(array $dto, array $input, int $now): array
    {
        $hold = static fn(string $reason): array => ['dto'=>$dto, 'applied'=>false, 'reason'=>$reason];
        try {
            if ($now < 1 || ($dto['quote_state'] ?? null) !== 'unknown'
                || ($dto['final_price_verified'] ?? null) !== false
                || ($dto['money']['arithmetic_applied'] ?? null) !== false
                || ($dto['money']['additional_prices_reported'] ?? null) !== []) {
                return $hold('fuel_overlap_or_nonbase_state');
            }
            $base = $dto['money']['search_price'] ?? null;
            if (!is_array($base) || ($base['currency'] ?? null) !== 'RUB') return $hold('fuel_base_currency');
            $baseUnits = self::units($base['amount'] ?? null);
            if ($baseUnits < 1 || ($dto['price'] ?? null) !== $base['amount']) return $hold('fuel_base_mismatch');
            if (($input['offer_ref_digest'] ?? null) !== ($dto['identity']['offer_ref_digest'] ?? null)
                || !self::digest($input['offer_ref_digest'] ?? null)) return $hold('fuel_offer_binding');

            $operator = $dto['operator']['raw'] ?? null;
            $family = AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator);
            if ($family === null) return $hold('fuel_operator_unknown');
            $direction = self::direction($input['direction'] ?? null);
            if ($direction['operator_family'] !== $family) return $hold('fuel_direction_binding');
            $party = self::party($input['party'] ?? null);
            if ($party !== self::party($dto['tour']['party'] ?? null)) return $hold('fuel_party_binding');
            if (self::hasInfant($party)) return $hold('fuel_infant_separate');

            $observations = $input['observations'] ?? null;
            if (!is_array($observations) || !array_is_list($observations) || count($observations) > 256) {
                return $hold('fuel_observations_shape');
            }
            $amounts = []; $offers = []; $digests = []; $matched = 0;
            $freshUntil = PHP_INT_MAX; $evidenceFrom = null; $evidenceTo = null;
            foreach ($observations as $sample) {
                if (!is_array($sample)) continue;
                try {
                    $sampleDirection = self::direction($sample['direction'] ?? null);
                    $sampleParty = self::party($sample['party'] ?? null);
                } catch (InvalidArgumentException $ignored) { continue; }
                if ($sampleDirection !== $direction || $sampleParty !== $party) continue;
                if (!in_array($sample['provider'] ?? null, ['tourvisor','andromeda'], true)
                    || ($sample['kind'] ?? null) !== 'fuel'
                    || ($sample['unit'] ?? null) !== 'party_roundtrip'
                    || ($sample['base_includes_other_required_charges'] ?? null) !== true
                    || !in_array($sample['base_relation'] ?? null, ['included','excluded'], true)
                    || !self::digest($sample['offer_ref_digest'] ?? null)
                    || !self::digest($sample['evidence_sha256'] ?? null)
                    || !is_int($sample['observed_at'] ?? null) || !is_int($sample['expires_at'] ?? null)
                    || $sample['observed_at'] < 1 || $sample['expires_at'] <= $sample['observed_at']) {
                    return $hold('fuel_evidence_invalid');
                }
                if ($sample['observed_at'] > $now || $sample['expires_at'] <= $now) continue;
                self::date($sample['evidence_valid_from'] ?? null);
                self::date($sample['evidence_valid_to'] ?? null);
                if ($sample['evidence_valid_from'] > $sample['evidence_valid_to']) return $hold('fuel_evidence_invalid');
                $native = self::units($sample['amount'] ?? null);
                $currency = self::currency($sample['currency'] ?? null);
                $factKey = $currency . '|' . $native . '|' . $sample['base_relation'] . '|party_roundtrip';
                $amounts[$factKey] = [$native, $currency, $sample['base_relation']];
                if (count($amounts) > 1) return $hold('fuel_rule_conflict');
                $offers[$sample['offer_ref_digest']] = true;
                $digests[$sample['evidence_sha256']] = true;
                ++$matched;
                $freshUntil = min($freshUntil, $sample['expires_at']);
                $evidenceFrom = $evidenceFrom === null ? $sample['evidence_valid_from'] : min($evidenceFrom, $sample['evidence_valid_from']);
                $evidenceTo = $evidenceTo === null ? $sample['evidence_valid_to'] : max($evidenceTo, $sample['evidence_valid_to']);
            }
            if (count($offers) < 2 || count($digests) < 2 || $amounts === []) {
                return $hold('fuel_independent_evidence_missing');
            }
            [$native, $currency, $relation] = array_values($amounts)[0];
            $fx = $input['exchange'] ?? null;
            $converted = $native;
            $fxEvidence = null;
            if ($currency !== 'RUB') {
                if (!is_array($fx) || ($fx['from'] ?? null) !== $currency || ($fx['to'] ?? null) !== 'RUB'
                    || ($fx['scope_sha256'] ?? null) !== self::hash($direction)
                    || !self::digest($fx['evidence_sha256'] ?? null)
                    || !is_int($fx['observed_at'] ?? null) || !is_int($fx['expires_at'] ?? null)
                    || $fx['observed_at'] < 1 || $fx['observed_at'] > $now || $fx['expires_at'] <= $now) {
                    return $hold('fuel_exchange_unavailable');
                }
                $converted = self::convert($native, $fx['rate'] ?? null);
                $fxEvidence = array_intersect_key($fx, array_flip([
                    'from','to','rate','observed_at','expires_at','evidence_sha256'
                ]));
            } elseif ($fx !== null) {
                return $hold('fuel_unexpected_exchange');
            }

            $alreadyReported = $dto['money']['fuel_charge_reported'] ?? null;
            if ($alreadyReported !== null && (!is_array($alreadyReported)
                || $relation !== 'included' || ($alreadyReported['currency'] ?? null) !== 'RUB'
                || self::units($alreadyReported['amount'] ?? null) !== $converted)) {
                return $hold('fuel_existing_fact_conflict');
            }
            $increment = $relation === 'excluded' ? $converted : 0;
            if ($baseUnits > 99999999999999 - $increment) return $hold('fuel_total_overflow');
            $total = self::format($baseUnits + $increment);
            $evidenceKeys = array_keys($digests); sort($evidenceKeys, SORT_STRING);
            $rule = [
                'schema_version'=>2,
                'kind'=>'fuel',
                'unit'=>'party_roundtrip',
                'direction'=>$direction,
                'applicable_party'=>$party,
                'amount'=>self::format($native),
                'currency'=>$currency,
                'base_relation'=>$relation,
                'evidence_period_from'=>$evidenceFrom,
                'evidence_period_to'=>$evidenceTo,
                'expires_at'=>$freshUntil,
                'independent_offer_count'=>count($offers),
                'evidence_count'=>count($digests),
                'evidence_sha256'=>self::hash($evidenceKeys),
            ];
            $out = $dto;
            $out['money']['fuel_charge_reported'] = [
                'amount'=>self::format($converted), 'currency'=>'RUB', 'source'=>'operator_fuel_direction_rule'
            ];
            $out['money']['operator_fuel_rule'] = $rule + [
                'rule_sha256'=>self::hash($rule),
                'price_evidence_expires_at'=>$fxEvidence === null ? $freshUntil : min($freshUntil, $fxEvidence['expires_at']),
                'exchange'=>$fxEvidence,
            ];
            $out['money']['search_price_fuel_relation'] = $relation;
            $out['money']['search_price_with_surcharge'] = [
                'amount'=>$total,'currency'=>'RUB','source'=>'derived_search_estimate'
            ];
            $out['money']['arithmetic_applied'] = $relation === 'excluded';
            $out['finalPriceReady'] = true;
            $out['finalPrice'] = $out['price'] = $total;
            // A reusable listing rule is not an individually actualized supplier quote.
            $out['final_price_verified'] = false;
            return ['dto'=>$out,'applied'=>true,'reason'=>null,'matched_observations'=>$matched];
        } catch (InvalidArgumentException | JsonException $ignored) {
            return $hold('fuel_evidence_invalid');
        }
    }

    public static function hash(mixed $value): string
    {
        $canonical = static function(mixed $v) use (&$canonical): mixed {
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v, SORT_STRING);
            foreach ($v as $k => $item) $v[$k] = $canonical($item);
            return $v;
        };
        return hash('sha256', json_encode($canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function direction(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) throw new InvalidArgumentException('fuel_direction');
        $keys = array_keys($value); sort($keys);
        if ($keys !== ['destination','market','operator_family']) throw new InvalidArgumentException('fuel_direction');
        if (!in_array($value['operator_family'], ['biblio_globus','fun_and_sun','intourist'], true)) {
            throw new InvalidArgumentException('fuel_direction');
        }
        return [
            'operator_family'=>$value['operator_family'],
            'market'=>self::label($value['market']),
            'destination'=>self::label($value['destination']),
        ];
    }

    private static function party(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 3 || !is_int($value['adults'] ?? null)
            || $value['adults'] < 1 || $value['adults'] > 9 || !is_int($value['children'] ?? null)
            || $value['children'] < 0 || $value['children'] > 9
            || !is_array($value['child_ages'] ?? null) || !array_is_list($value['child_ages'])
            || count($value['child_ages']) !== $value['children']) throw new InvalidArgumentException('fuel_party');
        $ages = $value['child_ages'];
        foreach ($ages as $age) if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('fuel_age');
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$value['adults'],'children'=>$value['children'],'child_ages'=>$ages];
    }

    private static function hasInfant(array $party): bool
    {
        foreach ($party['child_ages'] as $age) if ($age < 2) return true;
        return false;
    }

    private static function units(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value)) {
            throw new InvalidArgumentException('fuel_money');
        }
        $parts = explode('.', $value, 2);
        return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
    }

    private static function convert(int $units, mixed $rate): int
    {
        if (!is_string($rate) || !preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $rate)) {
            throw new InvalidArgumentException('fuel_rate');
        }
        $parts = explode('.', $rate, 2); $scale = strlen($parts[1] ?? ''); $factor = 10 ** $scale;
        $numerator = (int)$parts[0] * $factor + (int)($parts[1] ?? '0');
        if ($numerator < 1 || $units > intdiv(PHP_INT_MAX, $numerator)) throw new InvalidArgumentException('fuel_rate');
        $product = $units * $numerator;
        $converted = intdiv($product, $factor);
        if ($scale > 0 && $product % $factor >= intdiv($factor, 2)) ++$converted;
        if ($converted > 99999999999999) throw new InvalidArgumentException('fuel_rate');
        return $converted;
    }

    private static function format(int $units): string
    {
        return intdiv($units, 100) . '.' . str_pad((string)($units % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function currency(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[A-Z]{3}\z/D', $value)) throw new InvalidArgumentException('fuel_currency');
        return $value;
    }

    private static function label(mixed $value): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 80
            || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F*]/', $value)) throw new InvalidArgumentException('fuel_label');
        return $value;
    }

    private static function digest(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function date(mixed $value): void
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC')) : false;
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('fuel_date');
    }
}
