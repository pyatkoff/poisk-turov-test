<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../app/integrations/anex-preview-gateway.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function hotel_filter_check(bool $passed, string $label): void
{
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
}

final class HotelFilterStatement extends PDOStatement
{
    private array $rows;
    public array $params = [];
    public function __construct(array $rows) { $this->rows = $rows; }
    public function execute(?array $params = null): bool { $this->params = $params ?? []; return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}

final class HotelFilterPdo extends PDO
{
    private array $rows;
    public ?HotelFilterStatement $last = null;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->last = new HotelFilterStatement($this->rows);
        return $this->last;
    }
}

$approved = static function (string $external, int $local): array {
    return ['anex_hotel_id' => $external, 'catalog_hotel_id' => $local,
        'existing_catalog_hotel_id' => $local, 'enabled' => 1, 'match_class' => 'exact',
        'scope' => 'preview', 'approval_policy' => 'owner_exact_and_strong_20260908'];
};

$mappingRows = [$approved('22', 888), $approved('11', 999), $approved('12', 999)];
$registry = AnyTourAnexSearchMappingRegistry::fromRows($mappingRows);
$pdo = new HotelFilterPdo([
    ['anex_hotel_id' => '12', 'catalog_hotel_id' => '999'],
    ['anex_hotel_id' => '22', 'catalog_hotel_id' => '888'],
    ['anex_hotel_id' => '11', 'catalog_hotel_id' => '999'],
    // Raw rows not accepted by the registry must not leak into supplier criteria.
    ['anex_hotel_id' => '9999', 'catalog_hotel_id' => '999'],
]);

$ids = anytour_anex_search3_supplier_hotel_ids($pdo, $registry, [999, 888]);
hotel_filter_check($ids === ['11', '12', '22'], 'all current accepted ANEX identities forwarded deterministically');
hotel_filter_check($pdo->last !== null && $pdo->last->params === ['999', '888', '999', '888'],
    'reverse lookup is bounded to selected local hotel IDs');
hotel_filter_check(anytour_anex_search3_supplier_hotel_ids($pdo, $registry, [999, 777]) === null,
    'partial local-to-ANEX coverage keeps the supplier search broad');
hotel_filter_check(anytour_anex_search3_supplier_hotel_ids($pdo, $registry, []) === null,
    'no hotel selection keeps the supplier search broad');

$excluded = AnyTourAnexSearchMappingRegistry::fromRows($mappingRows, [], [
    ['anex_hotel_id' => '22', 'catalog_hotel_id' => 888],
]);
hotel_filter_check(anytour_anex_search3_supplier_hotel_ids($pdo, $excluded, [888]) === null,
    'current pair exclusion prevents upstream narrowing');

$manyMappings = [];
$manyRows = [];
for ($i = 1; $i <= 31; $i++) {
    $external = (string) (1000 + $i);
    $manyMappings[] = $approved($external, 999);
    $manyRows[] = ['anex_hotel_id' => $external, 'catalog_hotel_id' => '999'];
}
$manyRegistry = AnyTourAnexSearchMappingRegistry::fromRows($manyMappings);
hotel_filter_check(anytour_anex_search3_supplier_hotel_ids(new HotelFilterPdo($manyRows), $manyRegistry, [999]) === null,
    'more than 30 supplier hotel IDs falls back to broad search instead of truncating');

$seenHotels = null;
$client = new AnyTourAnexClient('test-secret', static function (string $url) use (&$seenHotels): array {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    if (($query['action'] ?? '') === 'SearchTour_PRICES') $seenHotels = $query['HOTELS'] ?? null;
    return ['status' => 200, 'body' => json_encode(['SearchTour_PRICES' => ['prices' => []]])];
});
$date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$criteria = ['supplier_namespace' => 'anex_online', 'departure_id' => 1, 'destination_id' => 2,
    'currency_id' => 3, 'checkin_begin' => $date, 'checkin_end' => $date,
    'nights_from' => 7, 'nights_till' => 7, 'adults' => 2, 'children' => 0,
    'child_ages' => [], 'hotel_ids' => $ids];
(new AnyTourAnexSearch($client, $registry->previewResolver()))->search($criteria);
hotel_filter_check($seenHotels === '11,12,22', 'translated accepted identities reach SearchTour_PRICES HOTELS');

echo "ANEX selected hotel upstream filter: PASS\n";
