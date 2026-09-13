<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
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
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    { return array_shift($this->rows) ?? false; }
}
class Search3CatalogPdo extends PDO
{
    public array $responses;
    public array $queries = [];
    public array $statements = [];
    public function __construct(array $responses) { $this->responses = $responses; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    { return $this->prepare($query); }
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

// Common Search3 -> existing gateway -> projected refs -> explicit expansion -> saved DTO.
// All supplier responses/IDs/money below are synthetic; no live supplier or database.
$now = time();
$started = $now;
$clock = static function () use (&$now): int { return $now; };
$local = 999;
$resolver = static function (string $namespace, string $id) use (&$local): ?int {
    search3_check($namespace === 'anex_online' && $id === '1', 'wrong namespace sent to current resolver');
    return $local;
};
$nativeRow = array_replace($row, ['price' => '123.45', 'currency' => 'EUR', 'convertedPrice' => '12345.50 RUB',
    'room' => 'Standard-Room', 'htPlace' => 'DBL / 2 ADL', 'meal' => 'AI WITHOUT ALCOHOL']);
$transportCalls = 0;
$expansionQueries = [];
$factory = static function () use (&$transportCalls, &$expansionQueries, $nativeRow): AnyTourAnexClient {
    return new AnyTourAnexClient('test-secret', static function (string $url) use (&$transportCalls, &$expansionQueries, $nativeRow): array {
        ++$transportCalls;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $rows = [$nativeRow];
        if (isset($query['CATCLAIM'])) {
            $expansionQueries[] = $query;
            $rows = [array_replace($nativeRow, ['id' => 'private-concrete-a', 'grouped' => 0]),
                array_replace($nativeRow, ['id' => 'private-concrete-b', 'grouped' => 0, 'price' => '150.00', 'convertedPrice' => '15000.00 RUB'])];
        }
        return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => $rows]])];
    });
};
$savedCriteria = array_replace($context, ['supplier_namespace' => 'anex_online', 'departure_id' => 2, 'destination_id' => 4, 'currency_id' => 1]);
$gatewaySession = [];
$groups = anytour_anex_search3_prices($factory(), $resolver, $savedCriteria, $gatewaySession, $clock);
$state = ['generation' => 7, 'params' => $params, 'gateway' => $gatewaySession, 'expansions' => []];
$groupState = $state;
$cards = anytour_anex_search3_project($groups['offers'], $metadata, $params, $groups['search_ref']);
$groupTour = $cards[0]['tours'][0];
search3_check(preg_match('/\A[a-f0-9]{32}\z/D', $groupTour['search_ref']) === 1
    && $groupTour['offer_ref'] === $groups['offers'][0]['offer_key'], 'same-session references not projected');
search3_check($groupTour['kind'] === 'group_minimum' && $groupTour['selection_enabled'] === false, 'minimum became selectable');
search3_check($groupTour['price']['currency'] === 'RUB' && $groupTour['price']['amount'] === '12345.50', 'existing RUB display changed');

$readFactoryCalls = 0;
$noClient = static function () use (&$readFactoryCalls): AnyTourAnexClient {
    ++$readFactoryCalls;
    throw new RuntimeException('SAVED_READ_CREATED_CLIENT');
};
$currentMetadata = $metadata;
$metadataReader = static function (array $offers) use (&$currentMetadata): array { return $currentMetadata; };
$reservations = [];
$checkpoint = static function (array &$pending) use (&$reservations): void { $reservations[] = $pending; };
$follow = static function (array $request, array &$current, ?callable $transport = null, ?callable $reserve = null)
    use (&$now, $resolver, $noClient, $metadataReader, $clock): array {
    ++$now;
    return anytour_anex_search3_followup($request, $current, $resolver, $transport ?? $noClient, $metadataReader, $clock, $reserve);
};
$groupRequest = ['action' => 'offer', 'generation' => 7, 'search_ref' => $groups['search_ref'],
    'offer_ref' => $groupTour['offer_ref'], 'local_hotel_id' => 999];
search3_check($follow($groupRequest, $state)['status'] === 'group_minimum', 'group read fabricated concrete facts');
$expandRequest = array_replace($groupRequest, ['action' => 'expand']);
$expanded = $follow($expandRequest, $state, $factory, $checkpoint);
search3_check($expanded['status'] === 'expanded' && count($expanded['hotels'][0]['tours']) === 2, 'concrete offers missing');
search3_check($transportCalls === 2 && count($reservations) === 1
    && $reservations[0]['expansions'][$groupTour['offer_ref']]['status'] === 'unknown', 'expansion not reserved before transport');
search3_check($expansionQueries[0]['CATCLAIM'] === 'supplier-private-claim' && $expansionQueries[0]['HOTELS'] === '1'
    && !isset($expansionQueries[0]['PARTITION_PRICE']), 'expansion lost exact retained supplier group');
$again = $follow($expandRequest, $state);
search3_check($again === $expanded && $transportCalls === 2 && $readFactoryCalls === 0, 'completed expansion was replayed');
$readRequest = array_replace($groupRequest, ['offer_ref' => $expanded['hotels'][0]['tours'][0]['offer_ref']]);
$readA = $follow($readRequest, $state);
$readB = $follow(array_replace($readRequest, ['offer_ref' => $expanded['hotels'][0]['tours'][1]['offer_ref']]), $state);
search3_check($readA['status'] === 'current' && $readA['generation'] === 7
    && $readA['context']['current_context_verified'] === true, 'common saved offer not current');
search3_check($readA['offer']['money']['search_price'] === ['amount' => '123.45', 'currency' => 'EUR', 'source' => 'anex_search']
    && $readB['offer']['money']['search_price']['amount'] === '150.00', 'native A/B money was replaced or mixed');
search3_check($readA['offer']['money']['fuel_charge_reported'] === null && $readA['offer']['money']['quote_price'] === null
    && $readA['offer']['final_price_verified'] === false && $readA['selection_state'] === 'disabled', 'unknown quote/fuel fabricated');
search3_check($readA['offer']['meal']['qualifiers']['without_alcohol'] === true && $readA['offer']['room']['raw'] === 'Standard-Room'
    && $readA['offer']['placement']['raw'] === 'DBL / 2 ADL', 'exact meal/room/placement lost');
search3_check($readA['offer_ref'] !== $readB['offer_ref'] && $readFactoryCalls === 0 && $transportCalls === 2,
    'saved read constructed client or repeated search');
$public = json_encode([$cards, $expanded, $readA, $readB]);
foreach (['supplier-private-claim', 'private-concrete-a', 'private-concrete-b', 'supplier_offer_id', 'offer_key', 'test-secret'] as $private) {
    search3_check(strpos($public, $private) === false, 'private identity leaked: ' . $private);
}
search3_check($state['gateway']['saved_offers']['expires_at'] === $started + 900, 'expansion/read extended original expiry');

foreach ([['generation' => 8, 'expected' => 'mismatch'], ['search_ref' => str_repeat('a', 32), 'expected' => 'mismatch'],
    ['offer_ref' => 'anex_online:' . str_repeat('b', 64), 'expected' => 'not_loaded'], ['local_hotel_id' => 998, 'expected' => 'identity_changed']] as $case) {
    $expected = $case['expected']; unset($case['expected']);
    search3_check($follow(array_replace($readRequest, $case), $state)['status'] === $expected, 'cross-context read accepted');
}
$local = 998;
search3_check($follow($readRequest, $state)['status'] === 'identity_changed', 'remapped identity accepted');
search3_check($follow(array_replace($readRequest, ['local_hotel_id' => 998]), $state)['status'] === 'identity_changed', 'remap retargeted old offer');
$local = null;
search3_check($follow($readRequest, $state)['status'] === 'identity_unresolved', 'revoked identity accepted');
$local = 999;
$currentMetadata[999]['country_id'] = 3;
search3_check($follow($readRequest, $state)['status'] === 'not_available', 'catalog country drift accepted');
$currentMetadata = [];
search3_check($follow($readRequest, $state)['status'] === 'not_available', 'inactive/missing catalog hotel accepted');
$currentMetadata = $metadata;
foreach ([['generation' => '7'], ['offer_ref' => 'private-concrete-a'], ['local_hotel_id' => '999'], ['criteria' => []], ['action' => 'flights']] as $invalid) {
    search3_reject(static function () use ($follow, $readRequest, &$state, $invalid) {
        $follow(array_replace($readRequest, $invalid), $state);
    }, 'invalid followup schema must not reach transport');
}
search3_check($readFactoryCalls === 0 && $transportCalls === 2, 'rejected read dispatched supplier');

$unknownState = $groupState;
$failedCalls = 0;
$failedFactory = static function () use (&$failedCalls): AnyTourAnexClient {
    return new AnyTourAnexClient('test-secret', static function () use (&$failedCalls): array {
        ++$failedCalls;
        throw new RuntimeException('SYNTHETIC_TRANSPORT_FAILURE');
    });
};
$failed = false;
try { $follow($expandRequest, $unknownState, $failedFactory, $checkpoint); } catch (RuntimeException $expected) { $failed = true; }
search3_check($failed && $failedCalls === 1, 'synthetic failure not exercised');
search3_check($follow($expandRequest, $unknownState)['status'] === 'expansion_unknown' && $failedCalls === 1
    && $readFactoryCalls === 0, 'unknown expansion was replayed');
$unreserved = $groupState;
$failed = false;
try { $follow($expandRequest, $unreserved, $factory); } catch (RuntimeException $error) { $failed = $error->getMessage() === 'ANEX_RESERVATION_REQUIRED'; }
search3_check($failed && $transportCalls === 2, 'expansion ran without durable reservation contract');
$unreserved = $groupState;
$failed = false;
try {
    $follow($expandRequest, $unreserved, $factory, static function (): void { throw new RuntimeException('CHECKPOINT_WRITE_FAILED'); });
} catch (RuntimeException $error) { $failed = $error->getMessage() === 'CHECKPOINT_WRITE_FAILED'; }
search3_check($failed && $transportCalls === 2, 'checkpoint failure reached supplier');

$now = $started + 898;
search3_check($follow($readRequest, $state)['status'] === 'current', 'current offer expired too early');
search3_check($follow($readRequest, $state)['status'] === 'expired', 'exact original expiry accepted');
search3_check($follow($expandRequest, $groupState)['status'] === 'expired' && $readFactoryCalls === 0, 'expired expansion called supplier');
$future = $state;
$future['gateway']['saved_offers']['created_at'] = $now + 1;
$future['gateway']['saved_offers']['expires_at'] = $now + 901;
search3_check(!anytour_anex_search3_current($future, $now), 'future-created session accepted');

// Failed new searches must invalidate the same HTTP session before PDO or transport.
$badState = $state;
$emptyPdo = new Search3CatalogPdo([]);
$emptyCache = [];
$diagnostics = null;
search3_reject(static function () use (&$badState, $emptyPdo, &$emptyCache, &$diagnostics) {
    anytour_anex_search3_run(['generation' => 8, 'params' => []], $emptyPdo, null, $emptyCache, $diagnostics, null, $badState);
}, 'replacement validation must fail');
search3_check($badState === [] && $emptyPdo->queries === [], 'failed new search kept old selected context');
$now = $started + 10;
$newSession = [];
$newGroups = anytour_anex_search3_prices($factory(), $resolver, $savedCriteria, $newSession, $clock);
$newState = ['generation' => 7, 'params' => $params, 'gateway' => $newSession, 'expansions' => []];
search3_check($newGroups['search_ref'] !== $groups['search_ref'] && $follow($readRequest, $newState)['status'] === 'mismatch',
    'new search reused an old reference even with the same generation');

// The extracted catalog reader still enforces active local rows with bound IDs.
$catalogPdo = new Search3CatalogPdo([array_values($metadata), [], []]);
$readMetadata = anytour_anex_search3_metadata($catalogPdo, $groups['offers']);
search3_check(isset($readMetadata[999]) && $catalogPdo->statements[0]->parameters === [999]
    && strpos($catalogPdo->queries[0], 'AND is_active=1 LIMIT 300') !== false, 'current catalog read lost its active/bounded scope');

// Exercise real PHP session flush/readback in a separate process before output.
$endpoint = realpath(__DIR__ . '/../v2/api-anex-search3-preview.php');
$sessionCheck = 'require ' . var_export($endpoint, true) . ';' . <<<'PHP'
ini_set('session.use_cookies', '0');
session_cache_limiter('');
$dir = sys_get_temp_dir() . '/anex-search3-session-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) exit(10);
session_save_path($dir);
session_id('anex-test-' . bin2hex(random_bytes(8)));
if (!session_start()) exit(11);
$_SESSION['offer_context'] = ['generation' => 7, 'expansions' => ['fixture' => ['status' => 'unknown']]];
$state =& $_SESSION['offer_context'];
anytour_anex_search3_checkpoint($state);
if (session_status() !== PHP_SESSION_ACTIVE || $_SESSION['offer_context'] !== $state) exit(12);
$state['expansions']['fixture']['status'] = 'complete';
session_write_close();
if (!session_start() || $_SESSION['offer_context']['expansions']['fixture']['status'] !== 'complete') exit(13);
$detached = ['generation' => 99];
try { anytour_anex_search3_checkpoint($detached); exit(14); }
catch (InvalidArgumentException $expected) {
    if ($expected->getMessage() !== 'ANEX_SESSION_CHANGED' || $_SESSION['offer_context']['generation'] !== 7) exit(15);
}
session_destroy();
rmdir($dir);
echo 'SESSION_RESERVATION_READBACK_OK';
PHP;
$sessionOutput = []; $sessionCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -d allow_url_fopen=0 -r ' . escapeshellarg($sessionCheck), $sessionOutput, $sessionCode);
search3_check($sessionCode === 0 && implode('', $sessionOutput) === 'SESSION_RESERVATION_READBACK_OK', 'real session reservation/readback failed');
echo "ANEX_SEARCH3_RETAINED_OK common_refs=1 expand_once=1 saved_supplier_calls=0 native_money=1 stale_rejected=1 fixed_expiry=1 reservation_readback=1\n";


// A selected hotel must reach the supplier before its bounded first page is built.
$filterRow = static function (int $external, int $local): array {
    return ['anex_hotel_id' => $external, 'catalog_hotel_id' => $local,
        'existing_catalog_hotel_id' => $local, 'enabled' => 1, 'match_class' => 'exact',
        'scope' => 'preview', 'approval_policy' => 'owner_exact_and_strong_20260908'];
};
$filterRows = [$filterRow(2, 999), $filterRow(1, 999), $filterRow(3, 998)];
$filterRegistry = AnyTourAnexSearchMappingRegistry::fromRows($filterRows);
search3_check($filterRegistry->previewHotelIds(['999', 999]) === [1, 2], 'all accepted supplier IDs retained, not local IDs or a single alias');
search3_check($filterRegistry->previewHotelIds([998, 999]) === [1, 2, 3], 'multiple selected hotels retain complete supplier coverage');
foreach ([[], [999, 997], ['0999'], [true]] as $selection) {
    search3_check($filterRegistry->previewHotelIds($selection) === [], 'empty, incomplete or invalid selection never narrows to a subset');
}
$guardedRegistry = AnyTourAnexSearchMappingRegistry::fromRows($filterRows,
    [['anex_hotel_id' => 1, 'catalog_hotel_id' => 997, 'existing_catalog_hotel_id' => 997, 'decision_status' => 'accepted'],
     ['anex_hotel_id' => 3, 'catalog_hotel_id' => 998, 'existing_catalog_hotel_id' => 998, 'decision_status' => 'rejected']],
    [['anex_hotel_id' => 2, 'catalog_hotel_id' => 999]]);
search3_check($guardedRegistry->previewHotelIds([999]) === [] && $guardedRegistry->previewHotelIds([998]) === []
    && $guardedRegistry->previewHotelIds([997]) === [1], 'reverse read preserves manual target, rejection and pair exclusion precedence');
$manyRows = array_map(static function (int $id) use ($filterRow): array { return $filterRow($id, 999); }, range(1, 31));
search3_check(AnyTourAnexSearchMappingRegistry::fromRows(array_slice($manyRows, 0, 30))->previewHotelIds([999]) === range(1, 30),
    'exact supplier 30-ID limit is usable');
search3_check(AnyTourAnexSearchMappingRegistry::fromRows($manyRows)->previewHotelIds([999]) === [],
    '31 accepted IDs fall back to broad search, never a truncated supplier subset');

$runHotelSelection = static function (array $selection) use ($filterRows, $params, $row, $metadata): array {
    $db = new Search3CatalogPdo([
        [['departure_name' => 'Moscow', 'country_name' => 'Country']],
        array_slice($filterRows, 0, 2), [], [], array_values($metadata), [], [],
    ]);
    $requests = [];
    $client = new AnyTourAnexClient('test-secret', static function (string $url) use (&$requests, $row): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $requests[] = $query;
        $action = $query['action'];
        $dictionaries = ['SearchTour_TOWNFROMS' => [['id' => 1000, 'name' => 'Moscow']],
            'SearchTour_STATES' => [['id' => 20, 'name' => 'Country']],
            'SearchTour_CURRENCIES' => [['id' => 9, 'alias' => 'RUB']]];
        if ($action === 'SearchTour_PRICES') {
            // The broad first page deliberately does not contain the selected hotel.
            $rows = ($query['HOTELS'] ?? null) === '1,2'
                ? [$row, array_replace($row, ['id' => 'selected-alias-2', 'hotelKey' => 2])]
                : [array_replace($row, ['id' => 'unmapped-first-page', 'hotelKey' => 888])];
            $payload = ['prices' => $rows];
        } else {
            search3_check(isset($dictionaries[$action]), 'unexpected supplier action');
            $payload = $dictionaries[$action];
        }
        return ['status' => 200, 'body' => json_encode([$action => $payload])];
    });
    $cache = []; $diagnostics = []; $observed = []; $state = [];
    $observer = static function (array $offers) use (&$observed): array { $observed = $offers; return ['status' => 'fixture']; };
    $result = anytour_anex_search3_run(['generation' => 9, 'params' => $params + ['hotelIds' => $selection]],
        $db, $client, $cache, $diagnostics, $observer, $state);
    search3_check(count($requests) === 4 && $requests[3]['action'] === 'SearchTour_PRICES',
        'hotel filter must not add supplier requests');
    foreach ($db->queries as $sql) search3_check(strpos($sql, 'SELECT ') === 0, 'hotel filter must remain a read-only registry consumer');
    return [$result, $requests[3], $state, $diagnostics, $observed];
};
[$selected, $selectedQuery, $selectedState] = $runHotelSelection([999]);
search3_check(($selectedQuery['HOTELS'] ?? null) === '1,2' && $selectedQuery['TOWNFROMINC'] === '1000'
    && $selectedQuery['STATEINC'] === '20' && $selectedQuery['CURRENCY'] === '9', 'selected hotels and dictionaries use supplier namespaces');
search3_check(count($selected['hotels']) === 1 && $selected['hotels'][0]['local_id'] === 999
    && count($selected['hotels'][0]['tours']) === 2, 'selected hotel absent from broad first page now found with all accepted aliases');
search3_check($selected['hotels'][0]['tours'][0]['price']['amount'] === '12345.50'
    && $selectedState['gateway']['search']['context']['hotel_ids'] === [1, 2], 'price unchanged and retained context keeps supplier hotel selection');
search3_check(strpos(json_encode($selected), 'supplier-private-claim') === false
    && strpos(json_encode($selected), 'hotel_ids') === false, 'private supplier selector is not projected');
foreach ([[], [999, 998]] as $selection) {
    [$fallback, $fallbackQuery, $fallbackState, $fallbackDiagnostics, $fallbackObserved] = $runHotelSelection($selection);
    search3_check(!isset($fallbackQuery['HOTELS']) && !isset($fallbackState['gateway']['search']['context']['hotel_ids']),
        'broad or incompletely mapped selection retains original broad request');
    search3_check($fallback['hotels'] === [] && $fallbackDiagnostics['unmapped_hotel_ids'] === [888]
        && count($fallbackObserved) === 1 && $fallbackObserved[0]['hotel']['external_id'] === '888',
        'unmapped first-page evidence survives broad fallback without public numeric-ID guessing');
}
echo "ANEX selected hotel upstream smoke passed\n";
