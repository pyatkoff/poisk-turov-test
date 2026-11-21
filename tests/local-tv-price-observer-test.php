<?php
/** Execute the real observer against inert storage/catalog boundaries. */
declare(strict_types=1);

final class LocalPriceStatement extends PDOStatement
{
    public function __construct(private LocalPricePDO $db, private string $kind) {}
    public function fetchColumn(int $column = 0): mixed { return $this->db->clock; }
    public function execute(?array $params = null): bool
    {
        $this->db->writes[$this->kind][] = $params;
        return true;
    }
    public function rowCount(): int { return 1; }
}

final class LocalPricePDO extends PDO
{
    public mixed $clock = '2031-08-27 08:00:06';
    public array $queries = [];
    public array $writes = [];
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($query !== 'SELECT NOW()') throw new LogicException('Unexpected database query');
        $this->queries[] = $query;
        return new LocalPriceStatement($this, 'clock');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'INSERT IGNORE INTO tour_price_observations')) {
            return new LocalPriceStatement($this, 'prices');
        }
        if (str_starts_with($query, 'UPDATE catalog_hotels')) {
            return new LocalPriceStatement($this, 'photos');
        }
        throw new LogicException('Unexpected prepared statement');
    }
}

function local_price_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$observer = $argv[1] ?? __DIR__ . '/../v2/data/price-observer-v1.php';
local_price_check(is_file($observer), 'Observer source missing');
$fixture = sys_get_temp_dir() . '/local-price-observer-' . bin2hex(random_bytes(8));
local_price_check(mkdir($fixture, 0700), 'Cannot create isolated fixture');
$oldTimezone = date_default_timezone_get();
try {
    local_price_check(copy($observer, $fixture . '/price-observer-v1.php'), 'Cannot stage actual observer');
    file_put_contents($fixture . '/db-v1.php', <<<'PHP'
<?php
function v2_data_db(): PDO { $GLOBALS['dbCalls']++; return $GLOBALS['db']; }
PHP);
    file_put_contents($fixture . '/local-tv-catalog-v1.php', <<<'PHP'
<?php
final class LocalTvCatalogV1
{
    public function __construct(private PDO $db) {}
    public static function enabled(): bool { return $GLOBALS['enabled']; }
    public function discover(array $hotels, string $source, string $seen): void
    {
        $GLOBALS['discovered'][] = [$hotels, $source, $this->db, $seen];
        if ($GLOBALS['localFailure']) throw new RuntimeException('Synthetic LOCAL failure');
    }
}
PHP);
    // The implementation is copied byte-for-byte; only its external boundaries are doubles.
    require $fixture . '/price-observer-v1.php';
    date_default_timezone_set('Pacific/Honolulu');
    $cases = [
        'scheduled', 'user', 'hot', 'no_tour', 'off', 'demo', 'ci',
        'get_ci', 'header_ci', 'other_source', 'local_exception',
        'invalid_db_clock', 'price_caps', 'missing_context',
    ];
    foreach ($cases as $scenario) {
        $_GET = [];
        unset($_SERVER['HTTP_X_ANYTOUR_CI']);
        $db = new LocalPricePDO();
        $dbCalls = 0;
        $enabled = $scenario !== 'off';
        $localFailure = $scenario === 'local_exception';
        $discovered = [];
        $source = ['user' => 'user_search', 'hot' => 'hot_tours', 'other_source' => 'untrusted'][$scenario] ?? 'scheduled_monitor';
        $context = ['searchId' => 11, 'departureId' => 1, 'countryId' => 4, 'adults' => 2, 'childs' => [8], 'source' => $source];
        if ($scenario === 'demo') $context['demo'] = true;
        if ($scenario === 'ci') $context['ci_test'] = true;
        if ($scenario === 'get_ci') $_GET['ci_test'] = 1;
        if ($scenario === 'header_ci') $_SERVER['HTTP_X_ANYTOUR_CI'] = '1';
        if ($scenario === 'missing_context') unset($context['searchId']);
        if ($scenario === 'invalid_db_clock') $db->clock = 'invalid';
        $tour = ['id' => 'synthetic-tour', 'price' => 100000, 'date' => '2031-12-24', 'nights' => 7,
            'meal' => 7, 'room' => 19, 'roomType' => 'Deluxe sea view', 'operator' => 42,
            'fuelCharge' => null, 'currency' => 'RUB'];
        $hotels = [['id' => 1006, 'tours' => $scenario === 'no_tour' ? [] : [$tour]]];
        if ($scenario === 'price_caps') {
            $context['maxHotels'] = 1;
            $context['maxTours'] = 1;
            $hotels[0]['tours'][] = $tour;
            $hotels[] = ['id' => 1007, 'tours' => [$tour]];
        }
        $error = null;
        $result = null;
        try {
            $result = v2_data_observe_search_results($hotels, $context);
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
        $expectDiscovery = !in_array($scenario, ['off', 'demo', 'ci', 'get_ci', 'header_ci', 'other_source', 'missing_context'], true);
        local_price_check(count($discovered) === ($expectDiscovery ? 1 : 0), $scenario . ': wrong discovery count');
        if ($expectDiscovery) {
            local_price_check($discovered[0][0] === $hotels && $discovered[0][1] === $source && $discovered[0][2] === $db,
                $scenario . ': discovery lost original hotels, trusted source or PDO session');
            local_price_check((bool)preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $discovered[0][3]),
                $scenario . ': discovery timestamp lost');
        }
        local_price_check($dbCalls === ($scenario === 'missing_context' ? 0 : 1), $scenario . ': wrong database acquisition');
        local_price_check($db->queries === ($scenario === 'missing_context' ? [] : ['SELECT NOW()']),
            $scenario . ': price clock must use the observer PDO session');
        local_price_check($error === ($scenario === 'invalid_db_clock' ? 'Invalid database observation clock' : null),
            $scenario . ': unexpected observer error');
        $prices = $db->writes['prices'] ?? [];
        $expectPrice = !in_array($scenario, ['no_tour', 'invalid_db_clock', 'missing_context'], true);
        local_price_check(count($prices) === ($expectPrice ? 1 : 0), $scenario . ': wrong price write count');
        if ($expectPrice) {
            $row = $prices[0];
            local_price_check($row['observed_at'] === '2031-08-27 08:00:06', $scenario . ': PHP timezone leaked into price age');
            local_price_check($row['source'] === ($scenario === 'other_source' ? 'user_search' : $source)
                && $row['hotel_id'] === 1006 && $row['price'] === 100000.0 && $row['fuel_charge'] === null
                && $row['adults'] === 2 && $row['child_ages_signature'] === '8' && $row['children_count'] === 1
                && $row['meal_id'] === 7 && $row['room_id'] === 19 && $row['room_type'] === 'Deluxe sea view'
                && $row['operator_id'] === 42 && $row['tour_id'] === 'synthetic-tour',
                $scenario . ': existing price, party or source fields changed');
        }
        if ($scenario === 'invalid_db_clock') {
            local_price_check($db->writes === [], 'Invalid database clock admitted a write');
        } else {
            local_price_check($result['written'] === ($expectPrice ? 1 : 0), $scenario . ': incorrect written result');
        }
    }
    echo 'LOCAL_PRICE_OBSERVER_OK scenarios=' . count($cases) . ' supplier_calls=0 database_calls=0', PHP_EOL;
} finally {
    date_default_timezone_set($oldTimezone);
    foreach (glob($fixture . '/*') as $path) unlink($path);
    rmdir($fixture);
}
