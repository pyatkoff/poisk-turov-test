<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/operator-program-fuel-fx-evidence.php';

$checks = 0;
$assert = static function(bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException('ASSERT ' . $label);
};

$root = sys_get_temp_dir() . '/anytour-int-fx-' . bin2hex(random_bytes(6));
$searches = $root . '/searches';
if (!mkdir($searches, 0700, true) && !is_dir($searches)) throw new RuntimeException('mkdir');

$cleanup = static function(string $path) use (&$cleanup): void {
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $cleanup($path . '/' . $name);
        }
        rmdir($path);
        return;
    }
    unlink($path);
};

$hex = static fn(string $seed): string => hash('sha256', $seed);
$put = static function(
    string $directory,
    string $program,
    string $tour,
    string $rate,
    int $fxObserved,
    int $fxExpires,
    string $seed,
    string $source = 'andromeda_get_flights'
) use ($hex): void {
    $key = ['operator_family'=>'fun_and_sun','program_key'=>$program,'tour_key'=>$tour];
    $row = AnyTourOperatorProgramFuelRegistryV1::observation([
        'key'=>$key,
        'unit'=>'per_person_one_way',
        'amount'=>'999.00', // deliberately irrelevant to this FX-only helper
        'currency'=>'EUR',
        'direction_count'=>2,
        'base_relation'=>'excluded',
        'flight_pair'=>null,
        'offer_ref_digest'=>$hex('offer-' . $seed),
        'evidence_sha256'=>$hex('fuel-' . $seed),
        'source'=>$source,
        'observed_at'=>$fxObserved - 10,
        'expires_at'=>$fxExpires + 10,
        'exchange'=>[
            'from'=>'EUR','to'=>'RUB','rate'=>$rate,
            'observed_at'=>$fxObserved,'expires_at'=>$fxExpires,
            'evidence_sha256'=>$hex('fx-' . $seed),
        ],
    ]);
    $digest = AnyTourOperatorFuelRuleEvidenceV1::hash($key);
    $value = ['version'=>1,'key_sha256'=>$digest,'key'=>$key,'observations'=>[$row]];
    $path = $directory . '/operator-program-fuel-v1-' . $digest . '.json';
    file_put_contents($path, json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
};

try {
    $now = 1790184000;
    $direction = ['market'=>'departure:1','destination'=>'country:4'];
    $canonical = AnyTourOperatorFuelRuleEvidenceV1::canonicalDirection('FUN&SUN', $direction);

    $put($searches, '114', '78', '102.70000000', $now - 30, $now + 3600, 'newest');
    $put($searches, '120', '80', '101.00000000', $now - 90, $now + 7200, 'older');
    $fx = AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection(
        $searches, 'FUN&SUN', $direction, $now
    );
    $assert(is_array($fx), 'fresh fx selected');
    $assert(($fx['rate'] ?? null) === '102.70000000', 'newest rate wins');
    $assert(($fx['scope_sha256'] ?? null) === AnyTourOperatorFuelRuleEvidenceV1::directionDigest($canonical), 'direction scoped');
    $assert(($fx['evidence_sha256'] ?? null) === $hex('fx-newest'), 'source evidence retained');
    $assert(!array_key_exists('amount', $fx) && !array_key_exists('base_relation', $fx), 'fuel facts not exposed');

    // Same-time same-rate evidence is not ambiguous. Prefer the longer-lived receipt deterministically.
    $put($searches, '121', '81', '102.70000000', $now - 30, $now + 5400, 'same-rate');
    $fx = AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection($searches, 'FUN&SUN', $direction, $now);
    $assert(($fx['expires_at'] ?? null) === $now + 5400, 'same-rate longest expiry selected');
    $assert(($fx['evidence_sha256'] ?? null) === $hex('fx-same-rate'), 'deterministic same-rate evidence');

    // Operator official reference is fuel authority only; it cannot manufacture FX here.
    $put($searches, '122', '82', '120.00000000', $now - 5, $now + 9000, 'official', 'operator_official_reference');
    $fx = AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection($searches, 'FUN&SUN', $direction, $now);
    $assert(($fx['rate'] ?? null) === '102.70000000', 'official reference fx ignored');

    // Newest same-time rate disagreement is fail-closed rather than order-dependent.
    $put($searches, '123', '83', '103.10000000', $now - 30, $now + 5400, 'conflict');
    $assert(AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection($searches, 'FUN&SUN', $direction, $now) === null, 'newest rate conflict blocked');

    $staleRoot = $root . '/stale/searches';
    if (!mkdir($staleRoot, 0700, true) && !is_dir($staleRoot)) throw new RuntimeException('mkdir stale');
    $put($staleRoot, '114', '78', '102.70000000', $now - 7200, $now - 1, 'stale');
    $assert(AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection($staleRoot, 'FUN&SUN', $direction, $now) === null, 'stale fx blocked');
    $assert(AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection($staleRoot, 'Интурист', $direction, $now) === null, 'operator family binding');

    $badRoot = $root . '/bad/searches';
    if (!mkdir($badRoot, 0700, true) && !is_dir($badRoot)) throw new RuntimeException('mkdir bad');
    file_put_contents($badRoot . '/operator-program-fuel-v1-' . str_repeat('a', 64) . '.json', '{"version":1}');
    $assert(AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection($badRoot, 'FUN&SUN', $direction, $now) === null, 'corrupt canonical store fails closed');

    echo 'INT_PROGRAM_FUEL_FX_REFERENCE_OK checks=' . $checks . " supplier=0 db=0 writes=fixture_only fuel_inference=0\n";
} finally {
    $cleanup($root);
}
