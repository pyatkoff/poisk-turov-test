<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';
require_once __DIR__ . '/../app/integrations/anex-additional-prices-batch.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path . DIRECTORY_SEPARATOR . $name;
        is_dir($child) && !is_link($child) ? $removeTree($child) : @unlink($child);
    }
    @rmdir($path);
};

$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'anytour-apd-batch-unknown-' . bin2hex(random_bytes(6));
$clock = static function (): int { return 1789326000; };
$criteria = ['page' => 1, 'pageSize' => 10, 'tour' => 2637, 'dateBeg' => '2026-10-05', 'nights' => 7, 'currency' => 1];

try {
    // First process: reserve this APD context, then suffer a real transport failure.
    // The same-day cache must remain durable unknown and never be replayed by a later process.
    $primeTransport = 0;
    $prime = new AnyTourAnexAdditionalPricesClient('test-token',
        static function () use (&$primeTransport): array {
            ++$primeTransport;
            throw new RuntimeException('synthetic timeout');
        }, $cacheDir, $clock);
    $primeFailed = false;
    try {
        $prime->additionalPricesDaily($criteria);
    } catch (RuntimeException $error) {
        $primeFailed = $error->getMessage() === 'ANEX_B2B_TRANSPORT_ERROR';
    }
    $assert($primeFailed && $primeTransport === 1 && $prime->requestsMade() === 1,
        'first process performs one failed transport after durable daily reservation');

    $ref1 = 'anex_online:' . str_repeat('a', 64);
    $ref2 = 'anex_online:' . str_repeat('b', 64);
    $offer = static function (string $ref, int $localId, string $externalId, string $checkin): array {
        return [
            'offer_key' => $ref,
            'kind' => 'concrete',
            'hotel' => ['external_id' => $externalId, 'local_id' => $localId],
            'checkin' => $checkin,
            'nights' => 7,
        ];
    };
    $state = [
        'gateway' => [
            'saved_offers' => ['offers' => [
                $ref1 => ['offer' => $offer($ref1, 101, '8101', '2026-10-05'),
                    'supplier_tour_program_id' => '2637', 'supplier_currency_id' => '1'],
                $ref2 => ['offer' => $offer($ref2, 102, '8102', '2026-10-06'),
                    'supplier_tour_program_id' => '1797', 'supplier_currency_id' => '1'],
            ]],
            'search' => ['offers' => [
                ['offer_key' => $ref1, 'kind' => 'concrete', 'hotel_external_id' => '8101'],
                ['offer_key' => $ref2, 'kind' => 'concrete', 'hotel_external_id' => '8102'],
            ]],
        ],
        'additional_prices' => [],
    ];
    $plan = anytour_anex_additional_prices_batch_plan([
        ['offer_ref' => $ref1, 'local_hotel_id' => 101],
        ['offer_ref' => $ref2, 'local_hotel_id' => 102],
    ], $state);

    $readerCalls = 0;
    $supplierTransports = 0;
    $reader = static function (array $context) use (&$readerCalls, &$supplierTransports, $cacheDir, $clock): array {
        ++$readerCalls;
        $client = new AnyTourAnexAdditionalPricesClient('test-token',
            static function (string $url) use (&$supplierTransports): array {
                ++$supplierTransports;
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $tour = (int) ($query['tour'] ?? 0);
                if ($tour !== 1797) throw new RuntimeException('DAILY_UNKNOWN_WAS_REPLAYED');
                return ['status' => 200, 'body' => json_encode([
                    'data' => [[
                        'price_adult' => '2000', 'price_chd' => '1000', 'cashrate' => '1',
                        'price_converted_adult' => '2000', 'price_converted_chd' => '1000',
                        'tour' => 1797, 'currency' => 1, 'dateBeg' => '2026-10-06', 'nights' => 7,
                    ]],
                    'totalCount' => 1,
                    'totalPages' => 1,
                ], JSON_THROW_ON_ERROR)];
            }, $cacheDir, $clock);
        try {
            $payload = $client->additionalPricesDaily([
                'page' => 1,
                'pageSize' => 10,
                'tour' => (int) $context['supplier_tour_program_id'],
                'dateBeg' => $context['checkin'],
                'nights' => $context['nights'],
                'currency' => (int) $context['supplier_currency_id'],
            ]);
        } finally {
            // requestsMade is zero for the shared same-day unknown and one for the fresh second context.
            $supplierTransports += 0;
        }
        return ['source' => 'test', 'total_count' => $payload['totalCount']];
    };
    $checkpoints = 0;
    $checkpoint = static function (array &$current, string $digest) use (&$checkpoints, $assert): void {
        ++$checkpoints;
        $assert(($current['additional_prices'][$digest]['status'] ?? null) === 'unknown',
            'session unknown is persisted before each new context reader');
    };

    $result = anytour_anex_additional_prices_batch_execute($plan, $state, $reader, $checkpoint);
    $assert($readerCalls === 2 && $checkpoints === 2,
        'both new session contexts are inspected once without replay loops');
    $assert($supplierTransports === 1,
        'same-day unknown performs zero supplier transports while independent fresh context still completes');
    $assert($result['offers'][0]['status'] === 'unknown' && $result['offers'][0]['additional_prices'] === null,
        'shared daily unknown becomes per-offer unknown instead of aborting the batch');
    $assert($result['offers'][1]['status'] === 'complete'
        && ($result['offers'][1]['additional_prices']['total_count'] ?? null) === 1,
        'later independent context completes in the same visible-card batch');

    $firstDigest = $plan['offers'][0]['context_digest'];
    $secondDigest = $plan['offers'][1]['context_digest'];
    $assert(($state['additional_prices'][$firstDigest]['status'] ?? null) === 'unknown'
        && ($state['additional_prices'][$secondDigest]['status'] ?? null) === 'complete',
        'session state preserves unknown/no-replay separately from completed evidence');

    $repeatReaderCalls = 0;
    $repeat = anytour_anex_additional_prices_batch_execute($plan, $state,
        static function () use (&$repeatReaderCalls): array {
            ++$repeatReaderCalls;
            throw new RuntimeException('REPLAY_FORBIDDEN');
        },
        static function (): void { throw new RuntimeException('CHECKPOINT_REPLAY_FORBIDDEN'); });
    $assert($repeatReaderCalls === 0 && $repeat['offers'][0]['status'] === 'unknown'
        && $repeat['offers'][1]['status'] === 'complete',
        'same session reuses both sealed unknown and completed contexts without reader calls');
} finally {
    $removeTree($cacheDir);
}

echo "ANEX APD shared-daily unknown batch regression: {$checks} checks passed; supplier_transport_after_prime=1\n";
