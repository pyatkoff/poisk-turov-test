<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-batch.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};

$ref1 = 'anex_online:' . str_repeat('a', 64);
$ref2 = 'anex_online:' . str_repeat('b', 64);
$ref3 = 'anex_online:' . str_repeat('c', 64);
$offer = static function (string $ref, int $localId, string $externalId, string $checkin, int $nights): array {
    return [
        'offer_key' => $ref,
        'kind' => 'concrete',
        'hotel' => ['external_id' => $externalId, 'local_id' => $localId],
        'checkin' => $checkin,
        'nights' => $nights,
    ];
};
$state = [
    'gateway' => [
        'saved_offers' => ['offers' => [
            $ref1 => ['offer' => $offer($ref1, 101, '8101', '2026-10-05', 7),
                'supplier_tour_program_id' => '2637', 'supplier_currency_id' => '1'],
            $ref2 => ['offer' => $offer($ref2, 102, '8102', '2026-10-05', 7),
                'supplier_tour_program_id' => '2637', 'supplier_currency_id' => '1'],
            $ref3 => ['offer' => $offer($ref3, 103, '8103', '2026-10-06', 7),
                'supplier_tour_program_id' => '1797', 'supplier_currency_id' => '1'],
        ]],
        'search' => ['offers' => [
            ['offer_key' => $ref1, 'kind' => 'concrete', 'hotel_external_id' => '8101'],
            ['offer_key' => $ref2, 'kind' => 'concrete', 'hotel_external_id' => '8102'],
            ['offer_key' => $ref3, 'kind' => 'concrete', 'hotel_external_id' => '8103'],
        ]],
    ],
    'additional_prices' => [],
];

$plan = anytour_anex_additional_prices_batch_plan([
    ['offer_ref' => $ref1, 'local_hotel_id' => 101],
    ['offer_ref' => $ref2, 'local_hotel_id' => 102],
    ['offer_ref' => $ref3, 'local_hotel_id' => 103],
], $state);
$assert($plan['requested_offers'] === 3, 'three visible offers retained');
$assert($plan['unique_contexts'] === 2, 'shared program/date context deduplicated');
$assert($plan['offers'][0]['context_digest'] === $plan['offers'][1]['context_digest'], 'same APD context shares digest');
$assert($plan['offers'][2]['context_digest'] !== $plan['offers'][0]['context_digest'], 'different program/date gets another digest');
$assert($plan['contexts'][0]['supplier_tour_program_id'] === '2637'
    && $plan['contexts'][0]['supplier_currency_id'] === '1'
    && $plan['contexts'][0]['checkin'] === '2026-10-05'
    && $plan['contexts'][0]['nights'] === 7, 'private supplier context preserved server-side');

$reads = [];
$checkpoints = [];
$reader = static function (array $context) use (&$reads): array {
    $reads[] = $context['context_digest'];
    return ['source' => 'test', 'marker' => $context['checkin']];
};
$checkpoint = static function (array &$current, string $digest) use (&$checkpoints, $assert): void {
    $checkpoints[] = $digest;
    $assert(($current['additional_prices'][$digest]['status'] ?? null) === 'unknown', 'unknown persisted before reader');
};
$result = anytour_anex_additional_prices_batch_execute($plan, $state, $reader, $checkpoint);
$assert(count($reads) === 2 && count($checkpoints) === 2, 'one reader call per unique context');
$assert($result['requested_offers'] === 3 && $result['unique_contexts'] === 2, 'batch result keeps offer/context counts');
$assert($result['offers'][0]['additional_prices']['marker'] === '2026-10-05'
    && $result['offers'][1]['additional_prices']['marker'] === '2026-10-05', 'shared evidence fans out to both offers');
$assert($result['offers'][2]['additional_prices']['marker'] === '2026-10-06', 'second context keeps its evidence');
$assert(count($state['additional_prices']) === 2, 'completed evidence stored once per context');

$cachedReads = 0;
$cached = anytour_anex_additional_prices_batch_execute($plan, $state,
    static function () use (&$cachedReads): array { ++$cachedReads; return ['unexpected' => true]; },
    static function (): void { throw new RuntimeException('CHECKPOINT_MUST_NOT_RUN'); });
$assert($cachedReads === 0, 'completed batch is supplier-free cache read');
$assert($cached['offers'][0]['status'] === 'complete' && $cached['offers'][1]['status'] === 'complete'
    && $cached['offers'][2]['status'] === 'complete', 'cached statuses stay complete');

$unknownState = $state;
$unknownDigest = $plan['offers'][0]['context_digest'];
$unknownState['additional_prices'][$unknownDigest] = ['status' => 'unknown'];
$unknownReads = 0;
$unknown = anytour_anex_additional_prices_batch_execute($plan, $unknownState,
    static function () use (&$unknownReads): array { ++$unknownReads; return ['unexpected' => true]; },
    static function (): void { throw new RuntimeException('UNKNOWN_REPLAY_CHECKPOINT_FORBIDDEN'); });
$assert($unknownReads === 0, 'unknown context is never replayed');
$assert($unknown['offers'][0]['status'] === 'unknown' && $unknown['offers'][1]['status'] === 'unknown'
    && $unknown['offers'][2]['status'] === 'complete', 'unknown only affects offers sharing that context');

$failureState = [
    'gateway' => $state['gateway'],
    'additional_prices' => [],
];
$failurePlan = anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 101]], $failureState);
$failureDigest = $failurePlan['offers'][0]['context_digest'];
$failed = false;
try {
    anytour_anex_additional_prices_batch_execute($failurePlan, $failureState,
        static function () { throw new RuntimeException('SUPPLIER_TIMEOUT'); },
        static function (): void {});
} catch (RuntimeException $e) {
    $failed = $e->getMessage() === 'SUPPLIER_TIMEOUT';
}
$assert($failed && ($failureState['additional_prices'][$failureDigest]['status'] ?? null) === 'unknown',
    'reader failure leaves durable unknown no-replay state');

$tooMany = [];
for ($i = 0; $i < 7; ++$i) $tooMany[] = ['offer_ref' => $ref1, 'local_hotel_id' => 101];
$failed = false;
try { anytour_anex_additional_prices_batch_plan($tooMany, $state); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_ADDITIONAL_BATCH'; }
$assert($failed, 'batch hard-bounded to six offers');

$badState = $state;
$badState['gateway']['saved_offers']['offers'][$ref1]['offer']['kind'] = 'group_minimum';
$failed = false;
try { anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 101]], $badState); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_SESSION'; }
$assert($failed, 'group minimum cannot enter APD batch');

$badState = $state;
$badState['gateway']['saved_offers']['offers'][$ref1]['supplier_tour_program_id'] = null;
$failed = false;
try { anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 101]], $badState); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_ADDITIONAL_CONTEXT_UNAVAILABLE'; }
$assert($failed, 'missing retained private context fails closed');

$failed = false;
try { anytour_anex_additional_prices_batch_plan([['offer_ref' => $ref1, 'local_hotel_id' => 999]], $state); }
catch (InvalidArgumentException $e) { $failed = $e->getMessage() === 'ANEX_INVALID_SESSION'; }
$assert($failed, 'local hotel identity mismatch fails closed');

echo "ANEX additional-prices batch planner/executor: {$checks} checks passed; network=0\n";
