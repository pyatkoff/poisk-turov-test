<?php
declare(strict_types=1);

require_once __DIR__ . '/operator-fuel-rule-evidence.php';

/**
 * Owner-authoritative Biblio-Globus listing fuel policy.
 *
 * Owner clarification 2026-09-22: Biblio-Globus has no fuel surcharge for the
 * current INT listing model. This is policy authority, not fabricated supplier
 * evidence and never individual quote verification. Infant pricing stays separate.
 */
final class AnyTourBiblioGlobusFuelOwnerPolicyV1
{
    /** @return array<string,mixed>|null */
    public static function inputForTarget(array $target): ?array
    {
        try {
            $operator = $target['operator'] ?? null;
            if (AnyTourOperatorFuelRuleEvidenceV1::operatorFamily($operator) !== 'biblio_globus') {
                return null;
            }
            if (!is_string($operator)) return null;

            if (array_key_exists('direction', $target)) {
                $direction = AnyTourOperatorFuelRuleEvidenceV1::canonicalDirection(
                    $operator,
                    $target['direction']
                );
            } elseif (is_array($target['search_params'] ?? null)) {
                $direction = AnyTourOperatorFuelRuleEvidenceV1::directionFromSearch(
                    $operator,
                    $target['search_params']
                );
            } else {
                return null;
            }
            if (($direction['operator_family'] ?? null) !== 'biblio_globus') return null;

            $party = self::party($target['party'] ?? null);
            foreach ($party['child_ages'] as $age) {
                if ($age < 2) return null;
            }

            $offerDigest = $target['offer_ref_digest'] ?? null;
            if (!is_string($offerDigest)
                || preg_match('/\A[a-f0-9]{64}\z/D', $offerDigest) !== 1) {
                return null;
            }

            return [
                'offer_ref_digest'=>$offerDigest,
                'direction'=>$direction,
                'party'=>$party,
                'observations'=>[],
                'exchange'=>null,
                'owner_policy'=>[
                    'schema_version'=>1,
                    'source'=>'owner_policy',
                    'policy_date'=>'2026-09-22',
                    'operator_family'=>'biblio_globus',
                    'amount'=>'0.00',
                    'currency'=>'RUB',
                    'unit'=>'per_person_one_way',
                    'base_relation'=>'included',
                ],
            ];
        } catch (Throwable $ignored) {
            return null;
        }
    }

    /** @return array{adults:int,children:int,child_ages:list<int>} */
    private static function party(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 3
            || !is_int($value['adults'] ?? null) || $value['adults'] < 1 || $value['adults'] > 9
            || !is_int($value['children'] ?? null) || $value['children'] < 0 || $value['children'] > 9
            || !is_array($value['child_ages'] ?? null) || !array_is_list($value['child_ages'])
            || count($value['child_ages']) !== $value['children']) {
            throw new InvalidArgumentException('BIBLIO_FUEL_PARTY');
        }
        $ages = $value['child_ages'];
        foreach ($ages as $age) {
            if (!is_int($age) || $age < 0 || $age > 17) {
                throw new InvalidArgumentException('BIBLIO_FUEL_PARTY');
            }
        }
        sort($ages, SORT_NUMERIC);
        return ['adults'=>$value['adults'],'children'=>$value['children'],'child_ages'=>$ages];
    }
}
