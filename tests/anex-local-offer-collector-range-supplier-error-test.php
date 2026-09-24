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

int_assert(count($attempted) === 3, 'supplier error must not prevent later independent windows');
int_assert($result['status'] === 'incomplete', 'range with supplier error must remain incomplete');
int_assert($result['window_count'] === 3, 'range must retain all planned windows');
int_assert($result['windows_completed'] === 2, 'only successful windows count as completed');
int_assert($result['selection_authority'] === false, 'partial range must never gain selection authority');
int_assert(count($result['windows']) === 3, 'all attempted windows must have receipts');
int_assert(($result['windows'][0]['result']['status'] ?? null) === 'supplier_error', 'failed supplier window must stay explicit');
int_assert(($result['windows'][0]['result']['error_code'] ?? null) === 'ANEX_SUPPLIER_ERROR', 'safe supplier error code must be retained');
int_assert(($result['windows'][1]['result']['status'] ?? null) === 'complete', 'second independent window must complete');
int_assert(($result['windows'][2]['result']['status'] ?? null) === 'complete', 'third independent window must complete');

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
int_assert($singlePropagated, 'single-window supplier diagnostics must keep existing exception behavior');

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
int_assert($incomplete['windows_completed'] === 0, 'incomplete window must not count as completed');

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
int_assert($httpAttempted === 2, 'bounded HTTP supplier error must allow the next multi-window attempt');
int_assert(($httpResult['windows'][0]['result']['error_code'] ?? null) === 'ANEX_HTTP_ERROR', 'HTTP supplier error must stay explicit');
int_assert($httpResult['status'] === 'incomplete', 'HTTP-gapped range must remain incomplete');

echo "OK\n";
