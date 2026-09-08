<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/integrations/anex-search-observations.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';
require_once __DIR__ . '/../app/integrations/anex-search.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function observation_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
}
$context = ['country_id' => 1, 'anex_country_id' => 11, 'checkin_from' => '2026-09-09', 'checkin_to' => '2026-09-15'];
$mapped = ['hotel' => ['external_id' => '5844', 'local_id' => 999, 'mapping_status' => 'resolved', 'name' => 'Hotel A']];
$unmapped = ['hotel' => ['external_id' => '999', 'local_id' => null, 'mapping_status' => 'unmapped', 'name' => 'Hotel B']];
$offers = [$mapped, $mapped, $unmapped];
$rows = AnyTourAnexSearchObservations::rows($offers, $context);
observation_check(count($rows) === 2, 'duplicate tours count once per search');
observation_check($rows[0]['anex_hotel_id'] === 5844 && $rows[0]['last_catalog_hotel_id'] === 999, 'separate supplier and local identities');
observation_check($rows[1]['last_catalog_hotel_id'] === null, 'same numeric local ID never implies mapping');
observation_check(AnyTourAnexSearchObservations::rows([['hotel' => ['external_id' => '1 OR 1=1']]], $context) === [], 'invalid IDs rejected');
observation_check(!isset($rows[0]['adults']) && !isset($rows[0]['session']), 'only bounded hotel context persisted');

$registry = AnyTourAnexSearchMappingRegistry::fromRows([['anex_hotel_id' => 5844, 'catalog_hotel_id' => 999,
    'existing_catalog_hotel_id' => 999, 'enabled' => 1, 'match_class' => 'strong_candidate', 'scope' => 'preview',
    'approval_policy' => 'owner_exact_and_strong_20260908']]);
$snapshot = AnyTourAnexSearchObservations::classify($rows, $registry->previewResolver(), []);
observation_check($snapshot['counts'] === ['observed' => 2, 'mapped' => 1, 'pending' => 1, 'manual_review' => 0], 'separate current mapping and pending queue');
$newRegistry = AnyTourAnexSearchMappingRegistry::fromRows([], [['anex_hotel_id' => 999, 'catalog_hotel_id' => 12,
    'existing_catalog_hotel_id' => 12, 'decision_status' => 'accepted']]);
$snapshot = AnyTourAnexSearchObservations::classify($rows, $newRegistry->previewResolver(), [5844 => true, 999 => true]);
observation_check($snapshot['counts']['mapped'] === 1 && $snapshot['counts']['pending'] === 0 && $snapshot['counts']['manual_review'] === 1,
    'latest acceptance removes pending; explicit blocks never enter automatic queue');

class ObservationStatement extends PDOStatement {
    private $rows;
    public $values;
    public function __construct(array $rows = []) { $this->rows = $rows; }
    public function execute(?array $params = null): bool { $this->values = $params; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed { return $this->rows[0] ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
class ObservationPDO extends PDO {
    public $writes = [];
    public $transaction = false;
    public function __construct() {}
    public function inTransaction(): bool { return $this->transaction; }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (strpos($query, 'INSERT INTO anex_search_hotel_observations ') === 0) {
            $statement = new ObservationStatement();
            $this->writes[] = [$query, $statement];
            return $statement;
        }
        if (strpos($query, 'SELECT d.name AS departure_name') === 0) return new ObservationStatement([['departure_name' => 'Moscow', 'country_name' => 'Egypt']]);
        return new ObservationStatement();
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false { return new ObservationStatement(); }
}
$pdo = new ObservationPDO();
$stored = AnyTourAnexSearchObservations::record($pdo, $offers, $context);
observation_check($stored['unique_hotels'] === 2 && count($pdo->writes) === 1, 'one atomic DB operation per response');
[$sql, $statement] = $pdo->writes[0];
observation_check(count($statement->values) === 16 && $statement->values[0] === 999 && $statement->values[8] === 5844, 'distinct parameterized supplier IDs in consistent lock order');
$update = explode('ON DUPLICATE KEY UPDATE', $sql)[1];
observation_check(strpos($update, 'first_seen_utc=') === false && strpos($update, 'search_count=search_count+1') !== false, 'first seen retained and search frequency incremented');
observation_check(strpos($sql, 'catalog_hotels') === false && strpos($sql, 'anex_hotel_decisions') === false, 'protected tables never written');
AnyTourAnexSearchObservations::record($pdo, [], $context);
observation_check(count($pdo->writes) === 1, 'empty success invents no hotels');
$pdo->transaction = true;
try { AnyTourAnexSearchObservations::record($pdo, $offers, $context); throw new LogicException('transaction accepted'); }
catch (RuntimeException $expected) { observation_check($expected->getMessage() === 'ANEX_OBSERVATION_TRANSACTION', 'independent autocommit write'); }
$pdo->transaction = false;

$date = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$params = ['countryId' => 1, 'departureId' => 1, 'dateFrom' => $date, 'dateTo' => $date, 'nightsFrom' => 7, 'nightsTo' => 7, 'adults' => 2, 'childs' => []];
$priceCalls = 0;
$client = new AnyTourAnexClient('fixture-secret', static function ($url) use (&$priceCalls, $date) {
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $action = $q['action'];
    $data = ['SearchTour_TOWNFROMS' => [['id' => 2, 'name' => 'Moscow']], 'SearchTour_STATES' => [['id' => 11, 'name' => 'Egypt']],
             'SearchTour_CURRENCIES' => [['id' => 1, 'name' => 'RUB']]];
    if ($action === 'SearchTour_PRICES') {
        $priceCalls++;
        $data[$action] = ['prices' => [['id' => 'fixture-claim', 'hotelKey' => 5844, 'hotel' => 'Hotel A', 'checkIn' => $date,
            'checkOut' => (new DateTimeImmutable($date))->modify('+7 days')->format('Y-m-d'), 'nights' => 7, 'adult' => 2, 'child' => 0,
            'grouped' => true, 'price' => '12345', 'currency' => 'RUB', 'packetType' => 0, 'meal' => 'AI']]];
    }
    return ['status' => 200, 'body' => json_encode([$action => $data[$action]])];
});
$cache = []; $diagnostics = []; $calls = 0;
$observer = static function ($received, $receivedContext) use (&$calls): array {
    $calls++;
    observation_check(count($received) === 1 && $received[0]['hotel']['external_id'] === '5844', 'unmapped offers reach collector before projection');
    observation_check($receivedContext['anex_country_id'] === 11 && $receivedContext['country_id'] === 1, 'country namespaces retained');
    throw new RuntimeException('fixture storage unavailable');
};
$result = anytour_anex_search3_run(['generation' => 1, 'params' => $params], $pdo, $client, $cache, $diagnostics, $observer);
observation_check($calls === 1 && $priceCalls === 1 && $result['hotels'] === [], 'no extra supplier requests and unmatched hotels remain excluded from UI');
observation_check($diagnostics['observation']['status'] === 'storage_unavailable', 'storage error does not fail search');
observation_check(!isset($result['observation']), 'internal collection state stays out of product response');
$errorClient = new AnyTourAnexClient('fixture-secret', static function () { return ['status' => 200, 'body' => '{"SearchTour_PRICES":{"error":101}}']; });
try { anytour_anex_search3_run(['generation' => 2, 'params' => $params], $pdo, $errorClient, $cache, $diagnostics, $observer); throw new LogicException('expected supplier failure'); }
catch (RuntimeException $expected) { observation_check($calls === 1, 'failed supplier responses are never observed as successful searches'); }
echo "ANEX observations smoke passed\n";
