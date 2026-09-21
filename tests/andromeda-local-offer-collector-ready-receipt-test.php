<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/andromeda-local-offer-collector.php';

function ready_receipt_check(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('READY_RECEIPT_CHECK_FAILED:' . $label);
}

$request = ['generation' => 91, 'params' => ['departureId' => '1', 'countryId' => '4']];
$emptySearch = [
    'provider' => 'andromeda',
    'search_ref' => str_repeat('a', 64),
    'generation' => 91,
    'page' => 1,
    'pages_count' => 0,
    'status' => 'complete',
    'hotels' => [],
    'grouped' => true,
    'first_page_only' => false,
    'external_search_pending' => false,
    'received_offers' => 0,
    'mapped_offers' => 0,
];
$mustNotRun = static function(): array {
    throw new RuntimeException('READY_RECEIPT_UNEXPECTED_CALLBACK');
};

$cases = [
    'published_zero' => [['published' => true, 'reason' => null, 'readyOfferCount' => 0], 0, 'complete'],
    'published_three' => [['published' => true, 'reason' => null, 'readyOfferCount' => 3], 3, 'complete'],
    'idempotent_zero' => [['published' => false, 'reason' => 'already_published', 'readyOfferCount' => 0], 0, 'complete'],
    'failure_missing' => [['published' => false, 'reason' => 'autosave_failed'], null, 'incomplete'],
    'dependency_missing' => [['published' => false, 'reason' => 'runtime_dependency_unavailable'], null, 'incomplete'],
    'negative_invalid' => [['published' => false, 'reason' => 'autosave_failed', 'readyOfferCount' => -1], null, 'incomplete'],
    'string_invalid' => [['published' => false, 'reason' => 'autosave_failed', 'readyOfferCount' => '7'], null, 'incomplete'],
];

foreach ($cases as $label => [$saveReceipt, $expectedReady, $expectedStatus]) {
    $autosaveCalls = 0;
    $result = AnyTourAndromedaLocalOfferCollectorV1::collect(
        $request,
        static fn(array $value): array => $emptySearch,
        static fn(string $ref, int $generation): array => [],
        $mustNotRun,
        $mustNotRun,
        static function(array $value, string $ref, int $generation) use ($saveReceipt, &$autosaveCalls): array {
            ++$autosaveCalls;
            return $saveReceipt;
        },
        0
    );
    ready_receipt_check($autosaveCalls === 1, $label . ':autosave_once');
    ready_receipt_check($result['status'] === $expectedStatus, $label . ':status');
    ready_receipt_check(array_key_exists('ready_offer_count', $result), $label . ':ready_key');
    ready_receipt_check($result['ready_offer_count'] === $expectedReady, $label . ':ready_exact');
    ready_receipt_check($result['autosave'] === $saveReceipt, $label . ':receipt_preserved');
    ready_receipt_check($result['selection_authority'] === false && $result['booking_calls'] === 0,
        $label . ':no_authority');
}

echo "ANDROMEDA_LOCAL_READY_RECEIPT_OK known=3 unknown=4 supplier=0 live_db=0\n";
