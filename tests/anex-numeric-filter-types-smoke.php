<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function anex_numeric_filter_reject(array $params, string $field, $value): void
{
    try {
        anytour_anex_search3_core(array_replace($params, [$field => $value]));
    } catch (InvalidArgumentException $error) {
        if ($error->getMessage() === 'ANEX_INVALID_SEARCH') return;
        throw new RuntimeException('unexpected rejection for ' . $field . ': ' . $error->getMessage());
    }
    throw new RuntimeException('invalid type accepted for ' . $field);
}

$date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$params = [
    'departureId' => 1,
    'countryId' => 2,
    'dateFrom' => $date,
    'dateTo' => $date,
    'nightsFrom' => 7,
    'nightsTo' => 7,
    'adults' => 2,
    'childs' => [],
    'meal' => '7',
    'currency' => 'RUB',
];

foreach (['hotelCategory', 'priceFrom', 'priceTo', 'departureId', 'countryId'] as $field) {
    anex_numeric_filter_reject($params, $field, true);
    anex_numeric_filter_reject($params, $field, false);
}
foreach (['hotelIds', 'regionIds', 'subregionIds'] as $field) {
    anex_numeric_filter_reject($params, $field, [true]);
    anex_numeric_filter_reject($params, $field, [false]);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([true, false] as $value) {
    try {
        anytour_anex_search3_operator_scope($pdo, [$value]);
    } catch (InvalidArgumentException $error) {
        if ($error->getMessage() === 'ANEX_INVALID_SEARCH') continue;
        throw new RuntimeException('unexpected operator ID rejection: ' . $error->getMessage());
    }
    throw new RuntimeException('boolean accepted for operatorIds');
}

$valid = array_replace($params, [
    'departureId' => '1',
    'countryId' => 2,
    'hotelCategory' => '4.5',
    'priceFrom' => 100000,
    'priceTo' => 250000.50,
    'hotelIds' => [3],
    'regionIds' => ['4'],
    'subregionIds' => [5],
]);
$criteria = anytour_anex_search3_core($valid);
if (($criteria['adults'] ?? null) !== 2 || ($criteria['nights_from'] ?? null) !== 7 || ($criteria['nights_till'] ?? null) !== 7) {
    throw new RuntimeException('valid numeric filter values changed supplier search context');
}

echo "ANEX strict numeric filter types: PASS\n";
