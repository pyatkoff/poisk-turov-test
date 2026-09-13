<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-additional-prices-client.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    ++$checks;
};
$remove = static function (string $path) use (&$remove): void {
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) $remove($path . DIRECTORY_SEPARATOR . $name);
        @rmdir($path);
        return;
    }
    @unlink($path);
};
$cacheDir = sys_get_temp_dir() . '/anytour-apd-cache-test-' . bin2hex(random_bytes(6));
$now = 1789320000;
$clock = static function () use (&$now): int { return $now; };
$criteria = ['page' => 1, 'pageSize' => 10, 'tour' => 987654321, 'dateBeg' => '2026-10-05', 'nights' => 7, 'currency' => 345];
$body = static function (array $criteria): string {
    return json_encode(['data' => [[
        'price_adult' => '120', 'price_chd' => '60', 'cashrate' => '104.23',
        'price_converted_adult' => '12507.6', 'price_converted_chd' => '6253.8',
        'tour' => $criteria['tour'], 'currency' => $criteria['currency'],
        'dateBeg' => $criteria['dateBeg'], 'nights' => $criteria['nights'],
        'supplier_note' => 'must-not-persist',
    ]], 'totalCount' => 1, 'totalPages' => 1], JSON_THROW_ON_ERROR);
};

try {
    $firstCalls = 0;
    $first = new AnyTourAnexAdditionalPricesClient('cache-test-token',
        static function (string $url) use (&$firstCalls, $body, $criteria): array {
            ++$firstCalls;
            return ['status' => 200, 'body' => $body($criteria)];
        }, $cacheDir, $clock);
    $firstPayload = $first->additionalPricesDaily($criteria);
    $assert($firstCalls === 1 && $first->requestsMade() === 1, 'first same-day context performs one HTTP transport');
    $assert(($first->lastRequestDiagnostics()['cache_status'] ?? null) === 'miss', 'first same-day context is a cache miss');
    $assert($firstPayload['data'][0]['price_converted_adult'] === '12507.6', 'first response preserves APD money fact');

    $secondCalls = 0;
    $second = new AnyTourAnexAdditionalPricesClient('different-token',
        static function () use (&$secondCalls): array { ++$secondCalls; throw new RuntimeException('CACHE_MISS_UNEXPECTED'); },
        $cacheDir, $clock);
    $secondPayload = $second->additionalPricesDaily($criteria);
    $assert($secondCalls === 0 && $second->requestsMade() === 0, 'second PHP client reuses same-day cache without HTTP');
    $assert(($second->lastRequestDiagnostics()['cache_status'] ?? null) === 'hit', 'shared completed entry reports cache hit');
    $assert($secondPayload['data'][0]['price_converted_adult'] === '12507.6'
        && $secondPayload['data'][0]['tour'] === 987654321
        && $secondPayload['data'][0]['currency'] === 345, 'cached money is rebound to exact requested context');

    $jsonFiles = glob($cacheDir . '/*/*.json') ?: [];
    $assert(count($jsonFiles) === 1, 'one opaque file stores one completed same-day context');
    $stored = file_get_contents($jsonFiles[0]);
    $assert(is_string($stored) && strpos($stored, '987654321') === false && strpos($stored, '"currency":345') === false
        && strpos($stored, 'cache-test-token') === false && strpos($stored, 'must-not-persist') === false,
        'shared cache stores no token, supplier program/currency id, or unrelated supplier fields');
    $assert((fileperms($jsonFiles[0]) & 0777) === 0600, 'cache evidence file is private');
    $dayDirs = glob($cacheDir . '/*', GLOB_ONLYDIR) ?: [];
    $assert(count($dayDirs) === 1 && (fileperms($dayDirs[0]) & 0777) === 0700, 'calendar-day cache directory is private');

    $failedCriteria = $criteria;
    $failedCriteria['tour'] = 123456789;
    $failedCalls = 0;
    $failedClient = new AnyTourAnexAdditionalPricesClient('cache-test-token',
        static function () use (&$failedCalls): array { ++$failedCalls; throw new RuntimeException('SUPPLIER_TIMEOUT'); },
        $cacheDir, $clock);
    $failed = false;
    try { $failedClient->additionalPricesDaily($failedCriteria); }
    catch (RuntimeException $e) { $failed = $e->getMessage() === 'ANEX_B2B_TRANSPORT_ERROR'; }
    $assert($failed && $failedCalls === 1 && $failedClient->requestsMade() === 1, 'failed first transport leaves same-day reservation');

    $replayCalls = 0;
    $replayClient = new AnyTourAnexAdditionalPricesClient('cache-test-token',
        static function () use (&$replayCalls): array { ++$replayCalls; return ['status' => 500, 'body' => '{}']; },
        $cacheDir, $clock);
    $replayBlocked = false;
    try { $replayClient->additionalPricesDaily($failedCriteria); }
    catch (RuntimeException $e) { $replayBlocked = $e->getMessage() === 'ANEX_B2B_DAILY_UNKNOWN'; }
    $assert($replayBlocked && $replayCalls === 0 && $replayClient->requestsMade() === 0,
        'same-day unknown context is not replayed by another PHP client');

    $now += 86400;
    $nextDayCalls = 0;
    $nextDay = new AnyTourAnexAdditionalPricesClient('cache-test-token',
        static function (string $url) use (&$nextDayCalls, $body, $failedCriteria): array {
            ++$nextDayCalls;
            return ['status' => 200, 'body' => $body($failedCriteria)];
        }, $cacheDir, $clock);
    $nextPayload = $nextDay->additionalPricesDaily($failedCriteria);
    $assert($nextDayCalls === 1 && $nextDay->requestsMade() === 1
        && ($nextDay->lastRequestDiagnostics()['cache_status'] ?? null) === 'miss', 'new Moscow calendar day may refresh prior unknown context');
    $assert($nextPayload['data'][0]['tour'] === 123456789, 'next-day refresh remains bound to requested context');

    $limitBlocked = false;
    try { $second->additionalPricesDaily($criteria); }
    catch (RuntimeException $e) { $limitBlocked = $e->getMessage() === 'ANEX_B2B_REQUEST_LIMIT'; }
    $assert($limitBlocked, 'cache hit still consumes the client instance logical-read allowance');

    $uncachedCalls = 0;
    $uncached = new AnyTourAnexAdditionalPricesClient('cache-test-token',
        static function (string $url) use (&$uncachedCalls, $body, $criteria): array {
            ++$uncachedCalls;
            return ['status' => 200, 'body' => $body($criteria)];
        });
    $uncached->additionalPricesDaily($criteria);
    $assert($uncachedCalls === 1 && $uncached->requestsMade() === 1,
        'custom transport remains cache-off unless an explicit cache directory is supplied');
} finally {
    $remove($cacheDir);
}

echo "ANEX AdditionalPricesDaily shared daily cache: {$checks} checks passed; external network=0\n";
