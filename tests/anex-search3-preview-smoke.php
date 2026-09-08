<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function search3_check(bool $passed, string $label): void
{
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
}
function search3_reject(callable $callback, string $label): void
{
    try { $callback(); } catch (InvalidArgumentException $expected) { return; }
    throw new RuntimeException('FAIL: ' . $label);
}

$departureRows = [['id' => 1000, 'name' => 'Moscow', 'nameAlt' => 'Москва'], ['id' => 1, 'name' => 'Antalya']];
search3_check(anytour_anex_search3_dictionary_id($departureRows, ['Москва']) === 1000, 'local form ID does not become ANEX ID');
search3_reject(static function () use ($departureRows) {
    anytour_anex_search3_dictionary_id(array_merge($departureRows, [['id' => 2000, 'name' => 'Москва']]), ['Москва']);
}, 'ambiguous dictionary label is rejected');
search3_reject(static function () use ($departureRows) {
    anytour_anex_search3_dictionary_id($departureRows, ['Unknown']);
}, 'unknown dictionary has no numerical fallback');
search3_check(anytour_anex_search3_dictionary_id([['id' => 9, 'alias' => 'RUB'], ['id' => 3, 'alias' => 'EUR']], ['RUB']) === 9, 'currency is translated from its own dictionary');

$date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$params = ['departureId' => 1, 'countryId' => 2, 'dateFrom' => $date, 'dateTo' => $date,
    'nightsFrom' => 7, 'nightsTo' => 10, 'adults' => 2, 'childs' => [], 'meal' => '7'];
search3_check(anytour_anex_search3_core($params)['children'] === 0, 'core criteria preserves party');
search3_reject(static function () use ($params) { anytour_anex_search3_core($params + ['onlyDirect' => 'true']); }, 'unverified flight filter fails closed');
search3_reject(static function () use ($params) { anytour_anex_search3_core(array_replace($params, ['meal' => '99'])); }, 'unknown meal ID fails closed');
search3_reject(static function () use ($params) { anytour_anex_search3_core(array_replace($params, ['childs' => [18]])); }, 'invalid child age rejected before supplier');

$context = anytour_anex_search3_core($params);
$row = ['id' => 'supplier-private-claim', 'hotelKey' => 1, 'hotel' => 'Supplier hotel', 'checkIn' => $date,
    'checkOut' => (new DateTimeImmutable($date))->modify('+7 days')->format('Y-m-d'), 'nights' => 7,
    'adult' => 2, 'child' => 0, 'grouped' => true, 'price' => '12345.50', 'currency' => 'RUB', 'packetType' => 0, 'meal' => 'AI'];
$registry = AnyTourAnexSearchMappingRegistry::fromRows([['anex_hotel_id' => 1, 'catalog_hotel_id' => 999,
    'existing_catalog_hotel_id' => 999, 'enabled' => 1, 'match_class' => 'exact', 'scope' => 'preview',
    'approval_policy' => 'owner_exact_and_strong_20260908']]);
$normalized = anytour_anex_normalize_prices(['prices' => [$row, array_replace($row, ['id' => 'unmapped-claim', 'hotelKey' => 999])]], $context, $registry->previewResolver());
$metadata = [999 => ['id' => 999, 'name' => 'Local catalog hotel', 'country_id' => 2, 'country_name' => 'Country',
    'region_id' => 33, 'region_name' => 'Region', 'category' => 4, 'rating' => '4.5']];
$hotels = anytour_anex_search3_project($normalized['offers'], $metadata, $params);
search3_check(count($hotels) === 1 && $hotels[0]['local_id'] === 999, 'only mapped catalog IDs are rendered, no numeric namespace fallback');
search3_check($hotels[0]['rating'] === 4.5, 'shared sorting receives the catalog rating');
search3_check($hotels[0]['name'] === 'Local catalog hotel', 'hotel name comes from own catalog');
search3_check(count($hotels[0]['tours']) === 1 && $hotels[0]['tours'][0]['price']['amount'] === '12345.50', 'unmapped offer excluded even when external ID equals local ID');
$json = json_encode($hotels);
search3_check(strpos($json, 'supplier-private-claim') === false && strpos($json, 'supplier_offer_id') === false && strpos($json, 'offer_key') === false, 'supplier selection IDs never enter response');
search3_check(anytour_anex_search3_project($normalized['offers'], $metadata, array_replace($params, ['countryId' => 3])) === [], 'mappings cannot cross selected country');
search3_check(anytour_anex_search3_project($normalized['offers'], $metadata, $params + ['hotelCategory' => 5]) === [], 'requested category honored');
search3_check(count(anytour_anex_search3_project($normalized['offers'], $metadata, $params + ['hotelRating' => '5'])) === 1, 'rating enum 5 translates to minimum 4.5');
$lowerRated = $metadata;
$lowerRated[999]['rating'] = '3.25';
search3_check(anytour_anex_search3_project($normalized['offers'], $lowerRated, $params + ['hotelRating' => '3']) === [], 'rating enum 3 translates to minimum 3.5');
search3_check(anytour_anex_search3_project($normalized['offers'], $metadata, $params + ['regionIds' => [77]]) === [], 'requested region honored');
search3_check(anytour_anex_search3_project($normalized['offers'], $metadata, $params + ['priceTo' => 10000]) === [], 'requested price ceiling honored');

$calls = 0;
$fake = new class($calls) {
    private $calls;
    public function __construct(&$calls) { $this->calls =& $calls; }
    public function request(string $action, array $params): array { $this->calls++; return [['id' => 1000, 'name' => 'Moscow']]; }
};
$cache = [];
anytour_anex_search3_dictionary($fake, 'SearchTour_TOWNFROMS', [], $cache);
anytour_anex_search3_dictionary($fake, 'SearchTour_TOWNFROMS', [], $cache);
search3_check($calls === 1, 'valid session dictionary is reused');
foreach ($cache as &$entry) $entry['expires'] = time() - 1;
unset($entry);
anytour_anex_search3_dictionary($fake, 'SearchTour_TOWNFROMS', [], $cache);
search3_check($calls === 2, 'expired dictionary refreshed');
echo "ANEX Search3 preview smoke passed\n";


// A broad shared form interval becomes exactly one ANEX week; the original input stays intact.
$intervals = [];
$client = new AnyTourAnexClient('test-secret', static function ($url) use (&$intervals) {
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $intervals[] = [$query['CHECKIN_BEG'], $query['CHECKIN_END']];
    $days = (new DateTimeImmutable($query['CHECKIN_BEG']))->diff(new DateTimeImmutable($query['CHECKIN_END']))->days;
    return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => $days > 6
        ? ['error' => 101, 'prices' => []] : ['prices' => []]])];
});
$criteria = array_replace($context, ['supplier_namespace' => 'anex_online', 'departure_id' => 2,
    'destination_id' => 4, 'currency_id' => 1, 'checkin_begin' => '2026-09-09', 'checkin_end' => '2026-09-22']);
$result = anytour_anex_search3_prices($client, static function () { return null; }, $criteria);
search3_check($intervals === [['20260909','20260915']], 'single ANEX week request');
search3_check($result['search']['checkin_begin'] === '2026-09-09' && $result['search']['checkin_end'] === '2026-09-15', 'actual ANEX week context');
foreach ([101, 2111] as $code) {
    $calls = 0;
    $client = new AnyTourAnexClient('test-secret', static function () use (&$calls, $code) {
        $calls++;
        return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['error' => $code, 'prices' => []]])];
    });
    try {
        anytour_anex_search3_prices($client, static function () { return null; }, $criteria);
        throw new LogicException('supplier error must not become empty success');
    } catch (RuntimeException $error) {
        search3_check($error->getMessage() === 'ANEX_SUPPLIER_ERROR', 'failed subrange remains failure');
        search3_check($calls === 1, 'supplier errors never multiply requests');
    }
}
echo "ANEX single week smoke passed\n";

search3_check($criteria['checkin_end'] === '2026-09-22', 'shared form interval not mutated');
$oneDay = array_replace($criteria, ['checkin_end' => $criteria['checkin_begin']]);
search3_check(anytour_anex_search3_week($oneDay) === $oneDay, 'single date stays single');
$short = array_replace($criteria, ['checkin_end' => '2026-09-12']);
search3_check(anytour_anex_search3_week($short) === $short, 'short range is not expanded');
$wideParams = array_replace($params, ['dateTo' => (new DateTimeImmutable($date))->modify('+20 days')->format('Y-m-d')]);
search3_check(anytour_anex_search3_core($wideParams)['checkin_end'] === (new DateTimeImmutable($date))->modify('+6 days')->format('Y-m-d'), 'dictionary dates use the same week');
