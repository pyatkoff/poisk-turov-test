<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function meal_check(bool $passed, string $label): void
{
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
}

function meal_reject(callable $callback, string $label): void
{
    try { $callback(); } catch (InvalidArgumentException $error) {
        meal_check($error->getMessage() === 'ANEX_FILTER_UNSUPPORTED', $label . ' error');
        return;
    }
    throw new RuntimeException('FAIL: ' . $label);
}

$date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$params = ['departureId' => 1, 'countryId' => 2, 'dateFrom' => $date, 'dateTo' => $date,
    'nightsFrom' => 7, 'nightsTo' => 7, 'adults' => 2, 'childs' => [],
    'hotelIds' => [], 'regionIds' => [], 'subregionIds' => [], 'operatorIds' => [], 'currency' => 'RUB'];
foreach (['2', '3', '4', '5', '7', '9'] as $meal) {
    $criteria = anytour_anex_search3_core($params + ['meal' => $meal]);
    meal_check($criteria['adults'] === 2 && $criteria['nights_from'] === 7, 'canonical meal ' . $meal . ' is accepted without becoming a supplier ID');
}
meal_reject(static fn() => anytour_anex_search3_core($params + ['meal' => '99']), 'unknown canonical meal fails closed');

$metadata = [1 => ['id' => 1, 'name' => 'Hotel', 'country_id' => 2, 'country_name' => 'Country',
    'region_id' => 10, 'region_name' => 'Region', 'category' => 4, 'rating' => 4.5]];
$baseOffer = ['hotel' => ['local_id' => 1, 'mapping_status' => 'resolved'],
    'price' => ['currency' => 'RUB', 'amount' => '100000'], 'checkin' => $date, 'nights' => 7,
    'adults' => 2, 'children' => 0, 'meal' => 'RO', 'room' => 'Standard', 'kind' => 'concrete'];
$matches = static function (string $filter, string $raw) use ($params, $metadata, $baseOffer): bool {
    $offer = $baseOffer;
    $offer['meal'] = $raw;
    return count(anytour_anex_search3_project([$offer], $metadata, $params + ['meal' => $filter])) === 1;
};

meal_check($matches('2', 'RO') && $matches('2', 'Без питания') && !$matches('2', 'BB'), 'RO family filtering');
meal_check($matches('3', 'BB') && $matches('3', 'Завтрак') && !$matches('3', 'HB'), 'BB family filtering');
meal_check($matches('4', 'HB') && $matches('4', 'HB+') && !$matches('4', 'FB'), 'HB family including plus qualifier');
meal_check($matches('5', 'FB') && $matches('5', 'Full Board Plus') && !$matches('5', 'AI'), 'FB family including plus qualifier');
meal_check($matches('7', 'AI') && $matches('7', 'UAI') && $matches('7', 'ALL') && $matches('7', 'AI-WITHOUT ALCOHOL')
    && !$matches('7', 'HB'), 'AI Search3 family keeps AI and UAI semantics');
meal_check($matches('9', 'UAI') && $matches('9', 'UAI WITHOUT ALCOHOL') && !$matches('9', 'AI'), 'UAI-only filtering');
meal_check(!$matches('3', 'UNKNOWN PLAN'), 'unknown supplier meal label excluded under selected meal');
meal_check($matches('', 'UNKNOWN PLAN'), 'empty meal filter stays broad');

meal_check(anytour_anex_search3_meal_families('7') === ['ai', 'uai'], 'numeric Search3 meal ID maps only to local families');
echo "ANEX canonical meal routing: PASS\n";
