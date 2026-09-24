<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/biblio-globus-fuel-owner-policy.php';

$checks = 0;
$ok = static function(bool $value, string $label) use (&$checks): void {
    ++$checks;
    if (!$value) throw new RuntimeException('BIBLIO_FUEL_POLICY:' . $label);
};
$same = static function(mixed $expected, mixed $actual, string $label) use (&$checks): void {
    ++$checks;
    if ($expected !== $actual) {
        throw new RuntimeException('BIBLIO_FUEL_POLICY:' . $label
            . ' expected=' . var_export($expected, true)
            . ' actual=' . var_export($actual, true));
    }
};

$base = [
    'operator'=>'Библио-Глобус',
    'search_params'=>[
        'departureId'=>1,
        'countryId'=>4,
        'adults'=>2,
        'childs'=>[7,2],
    ],
    'party'=>['adults'=>2,'children'=>2,'child_ages'=>[7,2]],
    'offer_ref_digest'=>hash('sha256', 'biblio-owner-policy-offer'),
];

$input = AnyTourBiblioGlobusFuelOwnerPolicyV1::inputForTarget($base);
$ok(is_array($input), 'policy missing');
$same('biblio_globus', $input['direction']['operator_family'] ?? null, 'operator family');
$same('departure:1', $input['direction']['market'] ?? null, 'departure market');
$same('country:4', $input['direction']['destination'] ?? null, 'destination');
$same(['adults'=>2,'children'=>2,'child_ages'=>[2,7]], $input['party'] ?? null, 'eligible party normalized');
$same([], $input['observations'] ?? null, 'must not fabricate supplier evidence');
$same(null, $input['exchange'] ?? 'unexpected', 'zero policy must not require FX');
$same([
    'schema_version'=>1,
    'source'=>'owner_policy',
    'policy_date'=>'2026-09-22',
    'operator_family'=>'biblio_globus',
    'amount'=>'0.00',
    'currency'=>'RUB',
    'unit'=>'per_person_one_way',
    'base_relation'=>'included',
], $input['owner_policy'] ?? null, 'owner policy payload');

$infant = $base;
$infant['party'] = ['adults'=>2,'children'=>1,'child_ages'=>[1]];
$same(null, AnyTourBiblioGlobusFuelOwnerPolicyV1::inputForTarget($infant), 'infant must remain separate');

$other = $base;
$other['operator'] = 'FUN&SUN';
$same(null, AnyTourBiblioGlobusFuelOwnerPolicyV1::inputForTarget($other), 'wrong operator accepted');

$badDigest = $base;
$badDigest['offer_ref_digest'] = 'bad';
$same(null, AnyTourBiblioGlobusFuelOwnerPolicyV1::inputForTarget($badDigest), 'bad offer digest accepted');

$badParty = $base;
$badParty['party'] = ['adults'=>2,'children'=>2,'child_ages'=>[7]];
$same(null, AnyTourBiblioGlobusFuelOwnerPolicyV1::inputForTarget($badParty), 'bad party accepted');

$direction = $base;
unset($direction['search_params']);
$direction['direction'] = ['market'=>'departure:1','destination'=>'country:194'];
$resolved = AnyTourBiblioGlobusFuelOwnerPolicyV1::inputForTarget($direction);
$ok(is_array($resolved), 'explicit direction missing');
$same('country:194', $resolved['direction']['destination'] ?? null, 'global BG policy must not be Turkey-only');

echo "INT_BIBLIO_GLOBUS_FUEL_OWNER_POLICY_OK checks={$checks} supplier_calls=0 db_writes=0 final_verified=false\n";
