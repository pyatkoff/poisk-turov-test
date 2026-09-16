<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-batch.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$digest = hash('sha256', "778\0" . "3\0" . "2026-10-18\0" . "7");
$plan = [
    'requested_offers' => 1,
    'unique_contexts' => 1,
    'contexts' => [[
        'context_digest' => $digest,
        'supplier_tour_program_id' => '778',
        'supplier_currency_id' => '3',
        'checkin' => '2026-10-18',
        'nights' => 7,
    ]],
    'offers' => [[
        'offer_ref' => 'anex_online:' . str_repeat('a', 64),
        'local_hotel_id' => 6319,
        'context_digest' => $digest,
    ]],
];

$state = [];
$checkpoints = [];
$readerCalls = 0;
$result = anytour_anex_additional_prices_batch_execute(
    $plan,
    $state,
    static function(array $context) use (&$readerCalls): array {
        ++$readerCalls;
        throw new RuntimeException('ANEX_RATE_LIMIT');
    },
    static function(array $next, string $contextDigest) use (&$checkpoints): void {
        $checkpoints[] = [$contextDigest, $next['additional_prices'][$contextDigest] ?? null];
    }
);
expect($readerCalls === 1, 'budget deferral must reach the local pre-transport guard once');
expect(($result['offers'][0]['status'] ?? null) === 'unknown', 'public status remains fail-closed');
expect(($result['offers'][0]['additional_prices'] ?? 'sentinel') === null, 'deferred APD must expose no money');
expect(!isset($state['additional_prices'][$digest]), 'unsent budget deferral must not become durable unknown');
expect(count($checkpoints) === 2, 'budget deferral must checkpoint reservation and rollback');
expect(($checkpoints[0][1]['status'] ?? null) === 'unknown' && $checkpoints[1][1] === null,
    'rollback checkpoint must remove only the never-sent context');

$evidence = [
    'source' => 'anex_b2b_additional_prices_daily',
    'application' => ['state' => 'applied'],
    'search_plus_additional' => ['amount' => '120000', 'currency' => 'RUB'],
];
$result = anytour_anex_additional_prices_batch_execute(
    $plan,
    $state,
    static function(array $context) use (&$readerCalls, $evidence): array {
        ++$readerCalls;
        return $evidence;
    },
    static function(array $next, string $contextDigest): void {}
);
expect($readerCalls === 2, 'later batch must retry the context after local budget recovery');
expect(($result['offers'][0]['status'] ?? null) === 'complete', 'retried APD context must complete');
expect(($result['offers'][0]['additional_prices'] ?? null) === $evidence, 'completed evidence must be returned unchanged');
expect(($state['additional_prices'][$digest]['status'] ?? null) === 'complete', 'successful retry must become durable complete');

$unknownDigest = hash('sha256', "779\0" . "3\0" . "2026-10-18\0" . "7");
$unknownPlan = $plan;
$unknownPlan['contexts'][0]['context_digest'] = $unknownDigest;
$unknownPlan['contexts'][0]['supplier_tour_program_id'] = '779';
$unknownPlan['offers'][0]['context_digest'] = $unknownDigest;
$unknownState = [];
$unknownCalls = 0;
anytour_anex_additional_prices_batch_execute(
    $unknownPlan,
    $unknownState,
    static function(array $context) use (&$unknownCalls): array {
        ++$unknownCalls;
        throw new RuntimeException('ANEX_B2B_DAILY_UNKNOWN');
    },
    static function(array $next, string $contextDigest): void {}
);
expect(($unknownState['additional_prices'][$unknownDigest]['status'] ?? null) === 'unknown',
    'supplier-observed APD unknown must remain durable');
anytour_anex_additional_prices_batch_execute(
    $unknownPlan,
    $unknownState,
    static function(array $context) use (&$unknownCalls): array {
        ++$unknownCalls;
        return ['unexpected' => true];
    },
    static function(array $next, string $contextDigest): void {}
);
expect($unknownCalls === 1, 'durable supplier unknown must remain no-replay');

echo "ANEX AdditionalPricesDaily budget deferral regression: OK\n";
