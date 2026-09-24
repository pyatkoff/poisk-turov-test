<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-local-offer-collector.php';

function int_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$request = [
    'action' => 'search',
    'generation' => 100,
    'params' => [],
];

$threeDays = AnyTourAnexLocalOfferCollectorV1::dateWindows('2026-10-03', '2026-10-05');
int_assert(count($threeDays) === 3, 'three-day user range must become three exact-day supplier windows');
int_assert($threeDays[0] === ['from' => '2026-10-03', 'to' => '2026-10-03'], 'first supplier window must be exact day');
int_assert($threeDays[2] === ['from' => '2026-10-05', 'to' => '2026-10-05'], 'last supplier window must be exact day');

$attempted = [];
$result = AnyTourAnexLocalOfferCollectorV1::collectRange(
    $request,
    '2026-09-29',
    '2026-10-19',
    static function (array $windowRequest, int $index, array $window) use (&$attempted): array {
        $attempted[] = [$index, $windowRequest['params']['dateFrom'], $windowRequest['params']['dateTo']];
        if ($index === 0) throw new RuntimeException('ANEX_SUPPLIER_ERROR');
        return ['status' => 'complete', 'selection_authority' => false, 'date_range' => $window];
    }
);

int_assert(count($attempted) === 21, 'supplier error must not prevent later independent exact days');
int_assert($attempted[0] === [0, '2026-09-29', '2026-09-29'], 'range must begin with an exact-day supplier request');
int_assert($attempted[20] === [20, '2026-10-19', '2026-10-19'], 'range must reach the final exact day');
int_assert($result['status'] === 'incomplete', 'range with supplier error must remain incomplete');
int_assert($result['window_count'] === 21, 'range must retain all planned exact days');
int_assert($result['windows_completed'] === 20, 'only successful exact days count as completed');
int_assert($result['selection_authority'] === false, 'partial range must never gain selection authority');
int_assert(count($result['windows']) === 21, 'all attempted exact days must have receipts');
int_assert(($result['windows'][0]['result']['status'] ?? null) === 'supplier_error', 'failed supplier day must stay explicit');
int_assert(($result['windows'][0]['result']['error_code'] ?? null) === 'ANEX_SUPPLIER_ERROR', 'safe supplier error code must be retained');
int_assert(($result['windows'][1]['result']['status'] ?? null) === 'complete', 'next independent day must complete');
int_assert(($result['windows'][20]['result']['status'] ?? null) === 'complete', 'last independent day must complete');

$singlePropagated = false;
try {
    AnyTourAnexLocalOfferCollectorV1::collectRange(
        $request,
        '2026-09-29',
        '2026-09-29',
        static function (): array { throw new RuntimeException('ANEX_SUPPLIER_ERROR'); }
    );
} catch (RuntimeException $error) {
    $singlePropagated = $error->getMessage() === 'ANEX_SUPPLIER_ERROR';
}
int_assert($singlePropagated, 'single-day supplier diagnostics must keep existing exception behavior');

$nonSupplierPropagated = false;
try {
    AnyTourAnexLocalOfferCollectorV1::collectRange(
        $request,
        '2026-09-29',
        '2026-10-19',
        static function (): array { throw new RuntimeException('ANEX_INVALID_RESPONSE'); }
    );
} catch (RuntimeException $error) {
    $nonSupplierPropagated = $error->getMessage() === 'ANEX_INVALID_RESPONSE';
}
int_assert($nonSupplierPropagated, 'non-supplier failures must never be isolated as date gaps');

$attemptedIncomplete = 0;
$incomplete = AnyTourAnexLocalOfferCollectorV1::collectRange(
    $request,
    '2026-09-29',
    '2026-10-19',
    static function () use (&$attemptedIncomplete): array {
        ++$attemptedIncomplete;
        return ['status' => 'incomplete'];
    }
);
int_assert($attemptedIncomplete === 1, 'persistence/invariant incomplete result must still fail-stop');
int_assert($incomplete['status'] === 'incomplete', 'fail-stop result must remain incomplete');
int_assert($incomplete['windows_completed'] === 0, 'incomplete day must not count as completed');

$httpAttempted = 0;
$httpResult = AnyTourAnexLocalOfferCollectorV1::collectRange(
    $request,
    '2026-09-29',
    '2026-10-12',
    static function (array $windowRequest, int $index) use (&$httpAttempted): array {
        ++$httpAttempted;
        if ($index === 0) throw new RuntimeException('ANEX_HTTP_ERROR');
        return ['status' => 'complete'];
    }
);
int_assert($httpAttempted === 14, 'bounded HTTP supplier error must allow every later exact-day attempt');
int_assert(($httpResult['windows'][0]['result']['error_code'] ?? null) === 'ANEX_HTTP_ERROR', 'HTTP supplier error must stay explicit');
int_assert($httpResult['windows_completed'] === 13, 'HTTP-gapped range must count only successful days');
int_assert($httpResult['status'] === 'incomplete', 'HTTP-gapped range must remain incomplete');

echo "OK\n";
