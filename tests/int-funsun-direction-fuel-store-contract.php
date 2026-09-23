<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/operator-fuel-rule-store.php';

function fail_test(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fail_test($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function assert_true(bool $value, string $message): void
{
    if (!$value) fail_test($message);
}

$root = sys_get_temp_dir() . '/anytour-int-funsun-fuel-' . bin2hex(random_bytes(8));
$searches = $root . '/searches';
if (!mkdir($searches, 0700, true) && !is_dir($searches)) fail_test('temp searches directory');

$write = static function (string $path, array $value): bool {
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return file_put_contents($path, $encoded, LOCK_EX) !== false;
};

$party = ['adults' => 2, 'children' => 0, 'child_ages' => []];
$direction = ['market' => 'departure:1', 'destination' => 'country:4'];
$now = 1790142000;
$expires = $now + 86400;

$observation = static function (string $sample, int $observedAt, string $flightOut, string $flightBack) use ($party, $direction, $expires): array {
    return [
        'provider' => 'andromeda',
        'operator_raw' => 'Fun&Sun',
        'direction' => $direction,
        'scope' => [
            'market' => 'andromeda:departure:1',
            'outbound' => ['origin' => 'MOW', 'destination' => 'AYT', 'carrier' => 'U6', 'flight' => $flightOut],
            'return' => ['origin' => 'AYT', 'destination' => 'MOW', 'carrier' => 'ZF', 'flight' => $flightBack],
            'party' => $party,
        ],
        'unit' => 'per_person_one_way',
        'amount' => '140',
        'currency' => 'EUR',
        'base_relation' => 'excluded',
        'base_includes_other_required_charges' => true,
        'offer_ref_digest' => hash('sha256', 'funsun-114-78-offer-' . $sample),
        'evidence_sha256' => hash('sha256', 'funsun-114-78-evidence-' . $sample),
        'source_response_sha256' => hash('sha256', 'funsun-114-78-response-' . $sample),
        'source' => 'andromeda_claim_service',
        'observed_at' => $observedAt,
        'expires_at' => $expires,
        'valid_from' => $sample === 'spo-a' ? '2026-10-11' : '2026-10-18',
        'valid_to' => $sample === 'spo-a' ? '2026-10-11' : '2026-10-18',
        'exchange' => [
            'from' => 'EUR',
            'to' => 'RUB',
            'rate' => '102.7',
            'source' => 'andromeda_claim_money',
            'observed_at' => $observedAt,
            'expires_at' => $expires,
            'evidence_sha256' => hash('sha256', 'funsun-114-78-fx-' . $sample),
        ],
    ];
};

try {
    $first = $observation('spo-a', $now - 120, '3555', '3004');
    $second = $observation('spo-b', $now - 60, '3556', '3005');

    $write1 = AnyTourOperatorFuelRuleStoreV1::append($searches, $first, $write);
    assert_same('created', $write1['status'] ?? null, 'first independent observation creates direction evidence');
    assert_same(1, $write1['observationCount'] ?? null, 'first observation count');

    $target = [
        'operator' => 'FUN&SUN',
        'direction' => $direction,
        'party' => $party,
        'offer_ref_digest' => hash('sha256', 'fresh-target-offer'),
    ];

    assert_same(null, AnyTourOperatorFuelRuleStoreV1::inputForTarget($searches, $target, $now), 'one observation must not confirm reusable rule');

    $write2 = AnyTourOperatorFuelRuleStoreV1::append($searches, $second, $write);
    assert_same('appended', $write2['status'] ?? null, 'second independent observation appends');
    assert_same(2, $write2['observationCount'] ?? null, 'two distinct observations retained');

    $input = AnyTourOperatorFuelRuleStoreV1::inputForTarget($searches, $target, $now);
    assert_true(is_array($input), 'two distinct-SPO-equivalent observations confirm direction rule');
    assert_same(
        ['operator_family' => 'fun_and_sun', 'market' => 'departure:1', 'destination' => 'country:4'],
        $input['direction'] ?? null,
        'canonical reusable FUN&SUN Moscow-to-Turkey direction'
    );
    assert_same($party, $input['party'] ?? null, 'target party retained');
    assert_same(2, count($input['observations'] ?? []), 'two evidence observations returned');
    foreach ($input['observations'] as $row) {
        assert_same('140', $row['amount'] ?? null, 'native fuel rate');
        assert_same('EUR', $row['currency'] ?? null, 'native fuel currency');
        assert_same('per_person_one_way', $row['unit'] ?? null, 'fuel unit');
        assert_same('excluded', $row['base_relation'] ?? null, 'fuel excluded from supplier base');
        assert_same(true, $row['base_includes_other_required_charges'] ?? null, 'other required charges fact');
    }
    assert_same('EUR', $input['exchange']['from'] ?? null, 'FX source currency');
    assert_same('RUB', $input['exchange']['to'] ?? null, 'FX target currency');
    assert_same('102.7', $input['exchange']['rate'] ?? null, 'fresh retained FX rate');

    $pricing = AnyTourOperatorFuelRuleStoreV1::pricingEnvelopeForTarget($searches, $target, $now);
    assert_same('operator_fuel', $pricing['state'] ?? null, 'pricing envelope state');
    $pricedInput = $pricing['operator_fuel'] ?? null;
    assert_true(is_array($pricedInput), 'pricing envelope carries direction input');
    assert_same([
        'schema_version'=>1,
        'source'=>'owner_policy',
        'policy_date'=>'2026-09-23',
        'operator_family'=>'fun_and_sun',
        'destination'=>'country:4',
        'amount'=>'70.00',
        'currency'=>'EUR',
        'unit'=>'per_person_one_way',
        'base_relation'=>'excluded',
    ], $pricedInput['owner_policy'] ?? null, 'pricing envelope carries owner 70 EUR Turkey fallback');
    unset($pricedInput['owner_policy']);
    assert_same($input, $pricedInput, 'owner policy does not rewrite retained 140 EUR supplier evidence');

    $files = glob($searches . '/operator-fuel-rule-v2-*.json') ?: [];
    assert_same(1, count($files), 'both samples collapse into one reusable direction store');

    fwrite(STDOUT, "PASS FUN&SUN direction fuel store contract: 2 independent observations -> 140 EUR per-person one-way reusable direction rule\n");
} finally {
    foreach (glob($searches . '/*') ?: [] as $path) @unlink($path);
    @rmdir($searches);
    @rmdir($root);
}
