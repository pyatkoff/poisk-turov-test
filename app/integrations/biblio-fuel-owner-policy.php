<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Owner-authoritative Biblio-Globus fuel policy (22.09.2026).
 *
 * Fuel is zero and already included in the supplier search price. This resolves only
 * the fuel component; it deliberately does NOT make the whole tour final/verified.
 * Children age >=2 follow the adult fuel rate (zero here). Infants remain a separate
 * pricing rule and are never folded into fuel.
 */
final class AnyTourBiblioFuelOwnerPolicyV1
{
    private const POLICY_DATE = '2026-09-22';

    public static function forParty(string $operator, array $party): ?array
    {
        if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) !== 'biblio_globus') return null;
        $normalized = self::party($party);
        $adultEquivalent = $normalized['adults'];
        $infants = 0;
        foreach ($normalized['child_ages'] as $age) {
            if ($age < 2) ++$infants;
            else ++$adultEquivalent;
        }
        return [
            'schema_version' => 1,
            'kind' => 'fuel_owner_policy',
            'operator_family' => 'biblio_globus',
            'source' => 'owner_policy',
            'policy_date' => self::POLICY_DATE,
            'amount' => '0.00',
            'currency' => 'RUB',
            'base_relation' => 'included',
            'adult_equivalent_count' => $adultEquivalent,
            'infant_count' => $infants,
            'infant_pricing_state' => $infants > 0 ? 'separate_unknown' : 'not_applicable',
        ];
    }

    public static function apply(array $dto, array $policy): array
    {
        $hold = static fn(string $reason): array => ['dto'=>$dto,'applied'=>false,'reason'=>$reason];
        try {
            if (($dto['quote_state'] ?? null) !== 'unknown'
                || ($dto['final_price_verified'] ?? null) !== false
                || ($dto['finalPriceReady'] ?? null) !== false
                || ($dto['finalPrice'] ?? null) !== null
                || ($dto['money']['arithmetic_applied'] ?? null) !== false) {
                return $hold('biblio_policy_nonbase_state');
            }
            $operator = $dto['operator']['raw'] ?? null;
            if (!is_string($operator) || AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) !== 'biblio_globus') {
                return $hold('biblio_policy_operator');
            }
            $expected = self::forParty($operator, $dto['tour']['party'] ?? []);
            if ($expected === null || $policy !== $expected) return $hold('biblio_policy_binding');
            $reported = $dto['money']['fuel_charge_reported'] ?? null;
            if ($reported !== null) {
                if (!is_array($reported)
                    || ($reported['amount'] ?? null) !== '0.00'
                    || ($reported['currency'] ?? null) !== 'RUB') {
                    return $hold('biblio_policy_existing_fuel_conflict');
                }
            }
            $out = $dto;
            $out['money']['fuel_charge_reported'] = [
                'amount'=>'0.00','currency'=>'RUB','source'=>'biblio_owner_policy',
            ];
            $out['money']['search_price_fuel_relation'] = 'included';
            $out['money']['operator_fuel_policy'] = $policy;
            // Fuel is resolved, but other mandatory price components may still be unknown.
            // Keep the listing confirmation-required and never manufacture a final total.
            $out['money']['arithmetic_applied'] = false;
            $out['finalPriceReady'] = false;
            $out['finalPrice'] = null;
            $out['final_price_verified'] = false;
            return ['dto'=>$out,'applied'=>true,'reason'=>null];
        } catch (Throwable $ignored) {
            return $hold('biblio_policy_invalid');
        }
    }

    private static function party(mixed $party): array
    {
        if (!is_array($party)
            || !is_int($party['adults'] ?? null) || $party['adults'] < 1 || $party['adults'] > 9
            || !is_int($party['children'] ?? null) || $party['children'] < 0 || $party['children'] > 9
            || !is_array($party['child_ages'] ?? null) || !array_is_list($party['child_ages'])
            || count($party['child_ages']) !== $party['children']) {
            throw new InvalidArgumentException('BIBLIO_FUEL_POLICY_PARTY');
        }
        $ages = $party['child_ages'];
        foreach ($ages as $age) {
            if (!is_int($age) || $age < 0 || $age > 17) throw new InvalidArgumentException('BIBLIO_FUEL_POLICY_PARTY');
        }
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$party['adults'],'children'=>$party['children'],'child_ages'=>$ages];
    }
}
