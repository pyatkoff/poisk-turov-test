<?php
/** No live DB, supplier, booking or lead requests. SQLite data is an explicit fixture. */
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/hotel-presentation-read-v1.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function same(mixed $actual, mixed $expected, string $message): void
{
    check($actual === $expected, $message . ': ' . json_encode($actual, JSON_UNESCAPED_UNICODE));
}
function invalid(callable $call, string $message): void
{
    try { $call(); } catch (InvalidArgumentException) { check(true, $message); return; }
    throw new RuntimeException($message);
}

same(hotel_presentation_read_ids(['10', 11, '10']), [10, 11], 'Deduplicate in first-seen order');
same(hotel_presentation_read_ids(range(1, 100)), range(1, 100), 'Boundary batch accepted');
foreach ([[], '10,11', null, [0], [-1], [true], [10.0], [[10]], ['1e2'], ['1.2'], ['anex:10'],
    ['1 OR 1=1'], [1, 'bad'], ['key' => 10], range(1, 101), array_fill(0, 101, 10)] as $bad) {
    invalid(static fn() => hotel_presentation_read_ids($bad), 'Invalid batch rejected as a whole');
}
same(hotel_details_read_json('broken'), [], 'Malformed JSON is unknown');
same(hotel_details_read_json('null'), [], 'JSON null is unknown');
same(hotel_details_read_json('{"free":"Wi-Fi"}'), ['free' => 'Wi-Fi'], 'Local service structure retained');

$row = array_fill_keys(['region_id','region_name','subregion_id','subregion_name','category','rating',
    'hotel_type','latitude','longitude'], null) + [
    'id' => 10, 'name' => 'Наш отель', 'country_id' => 4, 'country_name' => 'Турция',
    'status' => 'success', 'description' => 'Описание из локальной базы',
    'primary_image_url' => 'https://images.example.test/main.jpg',
    'images_json' => json_encode(['https://images.example.test/main.jpg',
        'https://images.example.test/second.jpg', 'javascript:alert(1)',
        'https://user:password@images.example.test/private.jpg']),
    'services_json' => '{"free":"Wi-Fi"}', 'meals_json' => '{"list":"BB, AI"}',
    'room_types' => 'Standard sea view; Family 2 bedrooms', 'fetched_at' => '2026-09-16 10:00:00',
    'provider_token' => 'must-not-be-exposed',
];
$before = $row;
$item = hotel_presentation_read_item($row);
same($item['name'], 'Наш отель', 'Name belongs to local profile');
same($item['description'], 'Описание из локальной базы', 'Local description retained');
same($item['images'], ['https://images.example.test/main.jpg', 'https://images.example.test/second.jpg'],
    'Safe primary first, gallery deduplicated, unsafe URLs absent');
same($item['roomTypes'], $row['room_types'], 'Room text is not guessed into a room mapping');
same($item['meals'], ['list' => 'BB, AI'], 'Hotel meal availability is not an offer meal');
same($item['services'], ['free' => 'Wi-Fi'], 'Sparse known services retained');
same($item['type'], null, 'Unknown type is not false or a family guess');
same($item['detailsAvailable'], true, 'Successful details are explicit');
same($row, $before, 'DTO mapping leaves input unchanged');
check(!isset($item['provider_token']) && !isset($item['price']) && !isset($item['tours']), 'No provider/offer/private fields');
$unsafe = hotel_presentation_read_item(array_replace($row, ['primary_image_url' => 'javascript:alert(1)']));
same($unsafe['primaryImage'], null, 'Unsafe stored primary is not returned');
$large = array_replace($row, ['images_json' => json_encode(array_map(
    static fn(int $n): string => "https://images.example.test/$n.jpg", range(1, 120)))]);
same(count(hotel_presentation_read_item($large)['images']), 100, 'Gallery remains bounded');

if (in_array('--unit-only', $argv, true)) {
    echo "SEARCH3_LOCAL_CATALOG_UNIT_OK checks=$checks sqlite=NOT_RUN http=NOT_RUN\n";
    exit(0);
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('pdo_sqlite required for the real SQL/HTTP regression; use --unit-only only for local partial checks');
}

class CatalogReadTestPDO extends PDO
{
    public array $reads = [];
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->reads[] = $query;
        return parent::prepare($query, $options);
    }
}
$root = sys_get_temp_dir() . '/search3-local-catalog-' . bin2hex(random_bytes(8));
mkdir($root . '/public/data', 0700, true);
$server = null;
try {
    $pdo = new CatalogReadTestPDO('sqlite:' . $root . '/catalog.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE catalog_hotels (
        id INTEGER PRIMARY KEY, name TEXT, country_id INTEGER, country_name TEXT,
        region_id INTEGER, region_name TEXT, subregion_id INTEGER, subregion_name TEXT,
        category INTEGER, rating REAL, hotel_type INTEGER, latitude REAL, longitude REAL,
        primary_image_url TEXT, is_active INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE catalog_hotel_details (
        hotel_id INTEGER PRIMARY KEY, status TEXT, description TEXT, address TEXT, place TEXT,
        build_info TEXT, repair_info TEXT, square_info TEXT, images_json TEXT, infrastructure_json TEXT,
        meals_json TEXT, services_json TEXT, room_types TEXT, fetched_at TEXT
    )');
    $insert = $pdo->prepare('INSERT INTO catalog_hotels(id,name,country_id,country_name,is_active,primary_image_url) VALUES(?,?,?,?,?,?)');
    foreach ([10, 11, 20, 30, ...range(1000, 1099)] as $id) {
        $insert->execute([$id, 'Локальный отель ' . $id, 4, 'Турция', $id === 20 ? 0 : 1,
            "https://images.example.test/$id.jpg"]);
    }
    $pdo->prepare('INSERT INTO catalog_hotel_details(hotel_id,status,description,services_json,meals_json,room_types) VALUES(?,?,?,?,?,?)')
        ->execute([10, 'success', 'Локальное описание', '{"family":true}', '{"list":"AI"}', 'Standard sea view']);
    $pdo->prepare('INSERT INTO catalog_hotel_details(hotel_id,status,description) VALUES(?,?,?)')
        ->execute([30, 'failed', 'FAILED DETAILS MUST NOT LEAK']);
    $writesBefore = (int)$pdo->query('SELECT total_changes()')->fetchColumn();
    $pdo->reads = [];
    $batch = hotel_presentation_read_many($pdo, [11, 10, 11, 20, 99, 30]);
    same(count($pdo->reads), 1, 'One SELECT for the whole batch');
    check(str_starts_with($pdo->reads[0], 'SELECT '), 'Reader is SELECT-only');
    check(!str_contains($pdo->reads[0], '11'), 'IDs are bound, not interpolated');
    same($batch['requestedIds'], [11, 10, 20, 99, 30], 'Stable unique request list');
    same(array_column($batch['items'], 'id'), [11, 10, 30], 'Request order independent from SQL order');
    same($batch['missingIds'], [20, 99], 'Inactive and missing profiles explicit');
    same($batch['items'][0]['description'], null, 'No successful details means no description');
    same($batch['items'][0]['detailsAvailable'], false, 'Unknown details are not invented');
    same($batch['items'][2]['description'], null, 'Failed stored details excluded by JOIN');
    same($batch['items'][2]['detailsAvailable'], false, 'Failed details do not become successful');
    same($batch['items'][1]['services'], ['family' => true], 'One sparse local positive survives');
    foreach ($batch['items'] as $profile) {
        same(hotel_presentation_read_many($pdo, [$profile['id']])['items'][0], $profile, 'Single/batch DTO parity');
    }
    $pdo->reads = [];
    same(count(hotel_presentation_read_many($pdo, range(1000, 1099))['items']), 100, '100 real SQL rows');
    same(count($pdo->reads), 1, '100 profiles still one SELECT');
    invalid(static fn() => hotel_presentation_read_many($pdo, [10, '10); DROP TABLE catalog_hotels; --']), 'Injection rejected before SQL');
    same(count($pdo->reads), 1, 'Invalid batch executes no SQL');
    same((int)$pdo->query('SELECT total_changes()')->fetchColumn(), $writesBefore, 'Reads caused no writes');

    // Run the actual endpoint against a disposable local database, not a mocked response.
    foreach (['hotel-details-read-v1.php', 'hotel-presentation-read-v1.php', 'hotel-details-v1.php', 'db-v1.php'] as $file) {
        copy(__DIR__ . '/../v2/data/' . $file, $root . '/public/data/' . $file);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) throw new RuntimeException('Could not reserve local test port');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $env = array_merge(getenv(), ['ANYTOUR_DATA_DSN' => 'sqlite:' . $root . '/catalog.sqlite',
        'ANYTOUR_DATA_DB_USER' => 'fixture', 'ANYTOUR_DATA_DB_PASSWORD' => '']);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $root . '/public'], [
        0 => ['file', '/dev/null', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.log', 'a']
    ], $pipes, $root, $env);
    if (!is_resource($server)) throw new RuntimeException('Could not start local endpoint fixture');
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(20000);
    }
    check($ready, 'Local fixture server ready');
    $request = static function (string $query) use ($address): array {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $body = file_get_contents('http://' . $address . '/data/hotel-details-read-v1.php?' . $query, false, $context);
        if ($body === false) throw new RuntimeException('Local fixture HTTP failed');
        $headers = $http_response_header;
        preg_match('/\s(\d{3})\s/', $headers[0], $match);
        return [(int)$match[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR), implode("\n", $headers)];
    };
    [$status, $single, $headers] = $request('hotelId=10');
    same($status, 200, 'Legacy single endpoint HTTP 200');
    same(array_keys($single), ['ok', 'item', 'source'], 'Legacy single envelope unchanged');
    same($single['source'], 'anytour-local-hotel', 'Local provenance retained');
    check(str_contains($headers, 'max-age=300'), 'Successful response retains public cache policy');
    [$status, $multiple] = $request('hotelIds[]=11&hotelIds[]=10&hotelIds[]=20&hotelIds[]=99');
    same($status, 200, 'Batch endpoint HTTP 200');
    same($multiple['items'][1], $single['item'], 'Actual single/batch HTTP profile parity');
    same($multiple['missingIds'], [20, 99], 'HTTP missing IDs explicit');
    same($request('hotelIds[]=99')[1]['items'], [], 'All missing is explicit empty local set');
    same($request('hotelId=99')[0], 404, 'Legacy absent single remains 404');
    foreach (['', 'hotelId=bad', 'hotelId=10&hotelIds[]=11', 'hotelIds=10,11',
        'hotelIds[]=10&hotelIds[]=bad', 'hotelIds[x]=10', 'hotelIds[][]=10',
        implode('&', array_fill(0, 101, 'hotelIds[]=10'))] as $query) {
        [$status, $body, $headers] = $request($query);
        same($status, 400, 'Invalid HTTP input rejected');
        same($body['ok'], false, 'No partial success for invalid IDs');
        check(str_contains($headers, 'no-store'), 'Errors are not cached as valid profiles');
    }
    $pdo->exec('DROP TABLE catalog_hotels');
    [$status, $body, $headers] = $request('hotelIds[]=10');
    same($status, 503, 'DB failure is 503, never empty successful catalogue');
    same($body, ['ok' => false, 'error' => 'Hotel details temporarily unavailable'], 'No SQL/private details leak');
    check(str_contains($headers, 'no-store'), 'DB failure is not cached');
    echo "SEARCH3_LOCAL_CATALOG_OK checks=$checks sql=REAL_SQLITE http=REAL_LOCAL batch100_queries=1 supplier_calls=0 live_db_writes=0\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo = null;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($root);
}
