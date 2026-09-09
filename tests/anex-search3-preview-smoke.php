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
search3_check($hotels[0]['catalog']['hotel_id'] === 999 && $hotels[0]['catalog']['source'] === 'tourvisor'
    && $hotels[0]['catalog']['image_url'] === null && $hotels[0]['catalog']['sea_distance'] === null,
    'mapped card identity is explicit even when optional content is missing');
$richMetadata = $metadata;
$richMetadata[999] += ['primary_image_url' => '//static.tourvisor.ru/hotel_pics/main400/999.jpg',
    'description' => '<p>Beach &amp; pool</p><script>privateScript()</script><p>Family hotel</p>',
    'address' => '<b>Address</b>', 'subregion_name' => 'Resort'];
$richHotel = anytour_anex_search3_project($normalized['offers'], $richMetadata, $params)[0];
search3_check($richHotel['catalog']['image_url'] === 'https://static.tourvisor.ru/hotel_pics/main400/999.jpg'
    && $richHotel['catalog']['description'] === 'Beach & pool Family hotel'
    && $richHotel['catalog']['address'] === 'Address' && $richHotel['catalog']['subregion'] === 'Resort',
    'stored catalog media and plain description follow the resolved local identity');
search3_check($richHotel['tours'] === $hotels[0]['tours'], 'catalog enrichment never changes supplier offers or prices');
$richMetadata[999]['primary_image_url'] = 'javascript:alert(1)';
$richMetadata[999]['description'] = str_repeat('x', 5000);
$safeHotel = anytour_anex_search3_project($normalized['offers'], $richMetadata, $params)[0];
search3_check($safeHotel['catalog']['image_url'] === null && strlen($safeHotel['catalog']['description']) === 2000,
    'unsafe image rejected and long description bounded without dropping offer');
$wrongMetadata = $richMetadata;
$wrongMetadata[999]['id'] = 998;
search3_check(anytour_anex_search3_project($normalized['offers'], $wrongMetadata, $params) === [],
    'metadata from another catalog identity cannot decorate a resolved hotel');

// Real PDO method contracts, but no DB or supplier is needed to exercise optional read failures.
class Search3CatalogStatement extends PDOStatement
{
    private array $rows;
    public array $parameters = [];
    public function __construct(array $rows) { $this->rows = $rows; }
    public function execute(?array $params = null): bool { $this->parameters = $params ?? []; return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
class Search3CatalogPdo extends PDO
{
    public array $responses;
    public array $queries = [];
    public array $statements = [];
    public function __construct(array $responses) { $this->responses = $responses; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        $response = array_shift($this->responses);
        if ($response === null) throw new PDOException('optional storage unavailable');
        $statement = new Search3CatalogStatement($response);
        $this->statements[] = $statement;
        return $statement;
    }
}
$pdo = new Search3CatalogPdo([null,
    [['hotel_id' => 999, 'primary_image_url' => '//static.tourvisor.ru/saved.jpg', 'description' => 'Saved description', 'address' => null],
     ['hotel_id' => 123, 'primary_image_url' => 'https://example.test/other.jpg', 'description' => 'Other hotel', 'address' => null]]]);
$hydrated = anytour_anex_search3_catalog_hydrate($pdo, $metadata);
search3_check($hydrated[999]['primary_image_url'] === 'https://static.tourvisor.ru/saved.jpg'
    && $hydrated[999]['description'] === 'Saved description' && !isset($hydrated[123]),
    'missing media column uses saved details; unrelated identity is ignored');
search3_check(count($pdo->queries) === 2 && $pdo->statements[0]->parameters === [999], 'content hydration uses a bounded batch of local IDs');
$pdo = new Search3CatalogPdo([[['hotel_id' => 999, 'primary_image_url' => '//static.tourvisor.ru/observed.jpg']], null]);
$hydrated = anytour_anex_search3_catalog_hydrate($pdo, $metadata);
search3_check($hydrated[999]['primary_image_url'] === 'https://static.tourvisor.ru/observed.jpg',
    'saved catalog picture survives when details storage is absent');
$pdo = new Search3CatalogPdo([null, null]);
search3_check(anytour_anex_search3_project($normalized['offers'], anytour_anex_search3_catalog_hydrate($pdo, $metadata), $params) === $hotels,
    'all optional stores may fail without hiding available tours');
search3_check(anytour_anex_search3_catalog_hydrate($pdo, []) === [] && count($pdo->queries) === 2,
    'empty mapping set does not query content storage');
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

// The full-search AI filter must retain the same known meals as the local result filter.
foreach (['AI', 'ALL', 'ALL INCLUSIVE', 'UAI', 'ULTRA ALL INCLUSIVE', 'AI-WITHOUT ALCOHOL',
    ' ai without alcohol ', 'всё включено', 'ультра всё включено', 'всё включено без алкоголя'] as $meal) {
    $offer = $normalized['offers'][0];
    $offer['meal'] = $meal;
    $matched = anytour_anex_search3_project([$offer], $metadata, $params + ['meal' => '7']);
    search3_check(count($matched) === 1 && $matched[0]['tours'][0]['meal'] === $meal,
        'known All Inclusive meal retained without rewriting supplier text: ' . $meal);
}
foreach (['HB', 'FB', 'BB', 'RO', '', '7', 'unknown', 'NOT ALL INCLUSIVE'] as $meal) {
    $offer = $normalized['offers'][0];
    $offer['meal'] = $meal;
    search3_check(anytour_anex_search3_project([$offer], $metadata, $params + ['meal' => '7']) === [],
        'unconfirmed All Inclusive meal stays excluded: ' . $meal);
}
$cheapHb = $normalized['offers'][0];
$cheapHb['meal'] = 'HB';
$expensiveAi = $cheapHb;
$expensiveAi['meal'] = 'AI-WITHOUT ALCOHOL';
$expensiveAi['price']['amount'] = '20000';
search3_check(anytour_anex_search3_project([$cheapHb, $expensiveAi], $metadata,
    $params + ['meal' => '7', 'priceTo' => 15000]) === [], 'meal and budget apply to the same offer');

$received = [];
foreach (range(1, 5) as $index) {
    $offer = $cheapHb;
    $offer['price']['amount'] = (string) (10000 + $index * 1000);
    $received[] = $offer;
}
$received[] = $expensiveAi;
$fullHotel = anytour_anex_search3_project($received, $metadata, array_replace($params, ['meal' => '']));
search3_check(count($fullHotel) === 1 && count($fullHotel[0]['tours']) === 6,
    'all received tours survive projection for later local filtering');
search3_check($fullHotel[0]['tours'][0]['price']['amount'] === '11000'
    && $fullHotel[0]['tours'][5]['meal'] === 'AI-WITHOUT ALCOHOL', 'minimum and sixth matching offer preserved');
$boundedHotel = anytour_anex_search3_project(array_fill(0, 301, $cheapHb), $metadata, array_replace($params, ['meal' => '']));
search3_check(count($boundedHotel[0]['tours']) === 300, 'existing total received-offer bound preserved');

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
