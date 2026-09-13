<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-search.php';

function freight_identity_check(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('ANEX freight identity failed: ' . $label);
}

function freight_identity_reject(callable $operation, string $label): void
{
    try {
        $operation();
    } catch (InvalidArgumentException $e) {
        freight_identity_check($e->getMessage() === 'ANEX_INVALID_SESSION', $label . ' fixed rejection');
        return;
    }
    throw new RuntimeException('ANEX freight identity expected rejection: ' . $label);
}

$criteria = [
    'supplier_namespace' => 'anex_online',
    'departure_id' => '1', 'destination_id' => '2', 'currency_id' => '3',
    'checkin_begin' => '20261021', 'checkin_end' => '20261021',
    'nights_from' => 7, 'nights_till' => 7,
    'adults' => 2, 'children' => 0, 'child_ages' => [],
    'hotel_ids' => ['1275'],
];

$row = [
    'id' => 'fixture-package-1', 'hotelKey' => 1275, 'hotel' => 'Fixture Resort',
    'checkIn' => '20261021', 'checkOut' => '20261028', 'nights' => 7,
    'adult' => 2, 'child' => 0, 'packetType' => 0,
    'price' => '120000', 'currency' => 'RUB', 'grouped' => 1, 'bron' => 0,
    // Observed SearchTour provider fields: retain only as opaque server evidence.
    'freightBeg' => 166896,
    'freightEnd' => '168151',
];

$calls = 0;
$client = new AnyTourAnexClient('fixture-token', static function (string $url) use (&$calls, $row): array {
    ++$calls;
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    freight_identity_check(($params['action'] ?? null) === 'SearchTour_PRICES', 'unexpected method');
    return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => [$row]]])];
});
$search = new AnyTourAnexSearch($client, static function (string $namespace, string $external): ?int {
    return $namespace === 'anex_online' && $external === '1275' ? 158 : null;
}, ['fixture-token']);
$result = $search->search($criteria);
freight_identity_check($calls === 1 && count($result['offers']) === 1, 'search fixture');
freight_identity_check(!array_key_exists('supplier_freight_refs', $result['offers'][0]), 'refs leaked to normalized offer');
freight_identity_check(strpos(json_encode($result), '166896') === false
    && strpos(json_encode($result), '168151') === false, 'refs leaked to returned search result');

$snapshot = $search->snapshot();
freight_identity_check($snapshot['schema_version'] === 1, 'schema changed');
freight_identity_check($snapshot['offers'][0]['supplier_freight_refs'] === [
    'outbound' => '166896', 'return' => '168151',
], 'opaque refs not retained');
freight_identity_check(!isset($snapshot['offers'][0]['itinerary_details_available']), 'refs promoted to itinerary');
freight_identity_check(!isset($snapshot['offers'][0]['fuel_charge'])
    && !isset($snapshot['offers'][0]['final_price_verified']), 'refs promoted to money evidence');

$noNetworkClient = new AnyTourAnexClient('restore-token', static function (): array {
    throw new RuntimeException('restore must not reach supplier');
});
$restored = new AnyTourAnexSearch($noNetworkClient);
$restored->restore($snapshot);
freight_identity_check($restored->snapshot()['offers'][0]['supplier_freight_refs']
    === ['outbound' => '166896', 'return' => '168151'], 'refs lost on restore');

$legacy = $snapshot;
unset($legacy['offers'][0]['supplier_freight_refs']);
$legacySearch = new AnyTourAnexSearch($noNetworkClient);
$legacySearch->restore($legacy);
freight_identity_check($legacySearch->snapshot()['offers'][0]['supplier_freight_refs'] === [],
    'legacy snapshot compatibility');

foreach ([
    ['outbound' => '01'],
    ['return' => 'https://invalid.example/1'],
    ['outbound' => '166896', 'return' => '168151', 'extra' => '1'],
    ['outbound' => 166896],
] as $badRefs) {
    $bad = $snapshot;
    $bad['offers'][0]['supplier_freight_refs'] = $badRefs;
    freight_identity_reject(static function () use ($noNetworkClient, $bad): void {
        $candidate = new AnyTourAnexSearch($noNetworkClient);
        $candidate->restore($bad);
    }, 'malformed refs');
}

$invalidRow = array_replace($row, ['id' => 'fixture-package-2', 'freightBeg' => '01',
    'freightEnd' => 'https://invalid.example/168151']);
$invalidClient = new AnyTourAnexClient('invalid-token', static function (string $url) use ($invalidRow): array {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    return ['status' => 200, 'body' => json_encode([$params['action'] => ['prices' => [$invalidRow]]])];
});
$invalidSearch = new AnyTourAnexSearch($invalidClient);
$invalidSearch->search($criteria);
freight_identity_check($invalidSearch->snapshot()['offers'][0]['supplier_freight_refs'] === [],
    'malformed provider refs were not omitted');

echo "ANEX_SEARCH_FREIGHT_IDENTITY_SMOKE_OK supplier_calls=synthetic price_arithmetic=0\n";
