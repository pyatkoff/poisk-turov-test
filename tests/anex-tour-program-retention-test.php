<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-client.php';
require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';

function tour_program_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('ANEX tour program retention failed: ' . $message);
}

$criteria = [
    'supplier_namespace' => 'anex_online',
    'departure_id' => '1',
    'destination_id' => '2',
    'currency_id' => '9',
    'checkin_begin' => '20260920',
    'checkin_end' => '20260920',
    'nights_from' => 7,
    'nights_till' => 7,
    'adults' => 2,
    'children' => 0,
    'hotel_ids' => ['469'],
];
$row = [
    'id' => 'program-group-fixture',
    'tourKey' => 778,
    'currencyKey' => 3,
    'hotelKey' => 469,
    'hotel' => 'Fixture Hotel',
    'checkIn' => '20260920',
    'checkOut' => '20260927',
    'nights' => 7,
    'adult' => 2,
    'child' => 0,
    'packetType' => 0,
    'price' => '1000.00',
    'currency' => 'EUR',
    'convertedPrice' => '104 230.00 RUB',
    'grouped' => 1,
    'bron' => 0,
];

$normalized = anytour_anex_normalize_prices(['prices' => [$row]], $criteria)['offers'][0];
tour_program_check($normalized['supplier_tour_program_id'] === '778', 'numeric tourKey not retained');
tour_program_check($normalized['supplier_currency_id'] === '3', 'numeric currencyKey not retained');
tour_program_check($normalized['supplier_currency_id'] !== $criteria['currency_id'], 'request currency gained native authority');
$unknownProgram = anytour_anex_normalize_prices(
    ['prices' => [array_replace($row, ['tourKey' => '778&other=1'])]],
    $criteria
)['offers'][0];
tour_program_check($unknownProgram['supplier_tour_program_id'] === null, 'invalid tourKey gained authority');
$unknownCurrency = anytour_anex_normalize_prices(
    ['prices' => [array_replace($row, ['currencyKey' => '3&other=1'])]],
    $criteria
)['offers'][0];
tour_program_check($unknownCurrency['supplier_currency_id'] === null, 'invalid currencyKey gained authority');
tour_program_check($unknownCurrency['price']['currency'] === 'EUR', 'invalid currencyKey changed native ISO fact');

$calls = 0;
$factory = static function () use (&$calls, $row): AnyTourAnexClient {
    return new AnyTourAnexClient('fixture-secret', static function (string $url) use (&$calls, $row): array {
        ++$calls;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $payload = isset($params['CATCLAIM'])
            ? ['prices' => [array_replace($row, ['id' => 'program-concrete-fixture', 'grouped' => 0])]]
            : ['prices' => [$row]];
        return ['status' => 200, 'body' => json_encode([$params['action'] => $payload])];
    });
};
$resolver = static function (string $provider, string $externalId): ?int {
    return $provider === 'anex_online' && $externalId === '469' ? 245 : null;
};
$clock = 1789851600;
$gateway = new AnyTourAnexPreviewGateway($factory, $resolver, ['fixture-secret'],
    static function () use (&$clock): int { return $clock; });
$session = [];
$groups = $gateway->handle(['action' => 'search', 'criteria' => $criteria], $session);
$group = $groups['offers'][0];

tour_program_check(!array_key_exists('supplier_tour_program_id', $group), 'program id leaked in public search result');
tour_program_check(!array_key_exists('supplier_currency_id', $group), 'currency id leaked in public search result');
$groupEntry = $session['saved_offers']['offers'][$group['offer_key']] ?? null;
tour_program_check(is_array($groupEntry)
    && ($groupEntry['supplier_tour_program_id'] ?? null) === '778', 'program id missing from private saved group');
tour_program_check(is_array($groupEntry)
    && ($groupEntry['supplier_currency_id'] ?? null) === '3', 'currency id missing from private saved group');
tour_program_check(!array_key_exists('supplier_tour_program_id', $groupEntry['offer']), 'program id copied into saved public offer');
tour_program_check(!array_key_exists('supplier_currency_id', $groupEntry['offer']), 'currency id copied into saved public offer');

++$clock;
$expanded = $gateway->handle(['action' => 'expand', 'offer_key' => $group['offer_key']], $session);
$concrete = $expanded['offers'][0];
tour_program_check(!array_key_exists('supplier_tour_program_id', $concrete), 'program id leaked in public concrete result');
tour_program_check(!array_key_exists('supplier_currency_id', $concrete), 'currency id leaked in public concrete result');
$concreteEntry = $session['saved_offers']['offers'][$concrete['offer_key']] ?? null;
tour_program_check(is_array($concreteEntry)
    && ($concreteEntry['supplier_tour_program_id'] ?? null) === '778', 'program id missing from private saved concrete');
tour_program_check(is_array($concreteEntry)
    && ($concreteEntry['supplier_currency_id'] ?? null) === '3', 'currency id missing from private saved concrete');
tour_program_check(!array_key_exists('supplier_tour_program_id', $concreteEntry['offer']), 'program id entered canonical saved offer');
tour_program_check(!array_key_exists('supplier_currency_id', $concreteEntry['offer']), 'currency id entered canonical saved offer');
tour_program_check($calls === 2, 'unexpected supplier calls');

echo "ANEX_TOUR_PROGRAM_RETENTION_OK private_program=1 private_currency=1 request_currency_fallback=0 public_leak=0 supplier_calls=2\n";
