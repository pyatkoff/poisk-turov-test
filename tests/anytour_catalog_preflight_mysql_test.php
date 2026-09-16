<?php
/** Real SQL/CLI checks. Only an empty disposable loopback fixture is accepted. */
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-canonical-catalog-v1.php';
$checks = 0;
function verify(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function refuses(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable) { verify(true, $message); return; }
    verify(false, $message);
}
$dsn = (string)getenv('ANYTOUR_PREFLIGHT_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;dbname=anytour_preflight_fixture;charset=utf8mb4$/D', $dsn)) {
    throw new RuntimeException('Explicit disposable loopback fixture required');
}
$password = (string)getenv('ANYTOUR_TEST_PASSWORD');
final class ObservedPDO extends PDO {
    public bool $probeReadOnly = false;
    public bool $writeRejected = false;
    public int $profileReads = 0;
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_contains($query, 'FROM catalog_hotels h')) {
            $this->profileReads++;
            if ($this->probeReadOnly) {
                $this->probeReadOnly = false;
                // Fixture-only probe: the real SQL engine must reject this write.
                try { $this->exec("INSERT INTO catalog_hotels(id,name) VALUES (900000,'FORBIDDEN_PROBE')"); }
                catch (PDOException $e) {
                    if ((int)($e->errorInfo[1] ?? 0) !== 1792) throw $e;
                    $this->writeRejected = true;
                }
            }
        }
        return parent::prepare($query, $options);
    }
}
$pdo = new ObservedPDO($dsn, 'root', $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
verify($pdo->query('SHOW TABLES')->fetchAll() === [], 'fixture starts empty');
$pdo->exec("CREATE TABLE catalog_hotels (
    id BIGINT PRIMARY KEY,name VARCHAR(255) NOT NULL,is_active TINYINT DEFAULT 1,
    country_id INT DEFAULT 4,country_name VARCHAR(255) DEFAULT 'Турция',
    region_id INT DEFAULT 8,region_name VARCHAR(255) DEFAULT 'Регион',
    subregion_id INT NULL,subregion_name VARCHAR(255) NULL,category INT DEFAULT 5,
    rating FLOAT NULL,hotel_type INT DEFAULT 2,latitude FLOAT NULL,longitude FLOAT NULL,
    primary_image_url VARCHAR(2048) NULL) ENGINE=InnoDB CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE catalog_hotel_details (
    hotel_id BIGINT PRIMARY KEY,status VARCHAR(32),description TEXT,address TEXT,place TEXT,
    build_info TEXT,repair_info TEXT,square_info TEXT,images_json TEXT,infrastructure_json TEXT,
    meals_json TEXT,services_json TEXT,room_types TEXT,fetched_at DATETIME) ENGINE=InnoDB CHARSET=utf8mb4");
$pdo->beginTransaction();
$insert = $pdo->prepare('INSERT INTO catalog_hotels(id,name) VALUES (?,?)');
foreach (range(10001,11000) as $id) $insert->execute([$id,'Сохранённый отель '.$id]);
$pdo->exec("INSERT INTO catalog_hotels(id,name,is_active) VALUES (1,'Inactive',0),(2,'  ',1)");
$pdo->exec("INSERT INTO catalog_hotel_details(hotel_id,status,description,images_json,fetched_at)
    VALUES (10001,'success','Описание','[\"https://fixture.test/one.jpg\"]','2026-09-16 01:00:00'),
           (10002,'failure','Must not leak','[\"https://fixture.test/two.jpg\"]','2026-09-16 01:00:00')");
$pdo->commit();
function legacyHash(PDO $pdo): string {
    return hash('sha256',AnyTourCanonicalCatalog::json([
        $pdo->query('SELECT * FROM catalog_hotels ORDER BY id')->fetchAll(),
        $pdo->query('SELECT * FROM catalog_hotel_details ORDER BY hotel_id')->fetchAll()]));
}
$catalog = new AnyTourCanonicalCatalog($pdo); $ids = range(10001,11000); $before = legacyHash($pdo);
$pdo->probeReadOnly = true; $report = $catalog->preflight($ids);
verify($pdo->writeRejected, 'SQL engine enforced read-only source transaction');
verify($pdo->profileReads === 10, '1000 profiles use ten bounded actual SELECTs');
verify($report['source_profiles'] === 1000 && $report['missingIds'] === [], 'real source profiles counted');
verify($report['profiles_with_description'] === 1 && $report['profiles_with_images'] === 1
    && $report['profiles_with_saved_details'] === 1, 'failed details excluded from coverage');
verify($report['target_schema_state'] === 'absent' && $report['target_tables_present'] === [], 'no-schema preflight succeeds');
verify($report['writes'] === 0 && !$report['migration_authorized'] && !$report['seed_authorized'], 'read-only not permission');
verify($report['database_name_sha256'] === hash('sha256','anytour_preflight_fixture'), 'actual selected DB bound');
verify(count($pdo->query('SHOW TABLES')->fetchAll()) === 2 && legacyHash($pdo) === $before, 'no target tables or data writes');
verify(!$pdo->inTransaction(), 'read-only transaction closed');
refuses(fn()=>$catalog->plan($ids), 'old plan still requires installed schema');
verify($catalog->preflight(array_reverse($ids))['source_sha256'] === $report['source_sha256'], 'stable sorted source digest');
$special = $catalog->preflight([1,2,9999]);
verify($special['unnamedIds'] === [2] && $special['missingIds'] === [1,9999], 'inactive absent unnamed remain distinct');
$pdo->beginTransaction();
refuses(fn()=>$catalog->preflight([10001]), 'caller transaction refused');
verify($pdo->inTransaction(), 'caller transaction not committed or rolled back'); $pdo->rollBack();
$pdo->exec("UPDATE catalog_hotels SET name='Changed source' WHERE id=10001");
verify($catalog->preflight($ids)['source_sha256'] !== $report['source_sha256'], 'source drift detected');
$insert = $pdo->prepare('UPDATE catalog_hotels SET name=? WHERE id=10001'); $insert->execute(['Сохранённый отель 10001']);
verify(legacyHash($pdo) === $before, 'test setup restored source exactly');
$pdo->exec('RENAME TABLE catalog_hotels TO fixture_source_hold');
refuses(fn()=>$catalog->preflight([10001]), 'missing source refused');
$pdo->exec('CREATE VIEW catalog_hotels AS SELECT * FROM fixture_source_hold');
refuses(fn()=>$catalog->preflight([10001]), 'view cannot masquerade as source table');
$pdo->exec('DROP VIEW catalog_hotels'); $pdo->exec('RENAME TABLE fixture_source_hold TO catalog_hotels');
$pdo->exec('ALTER TABLE catalog_hotels ENGINE=MyISAM');
refuses(fn()=>$catalog->preflight([10001]), 'nontransactional source refused');
$pdo->exec('ALTER TABLE catalog_hotels ENGINE=InnoDB');
verify(!$pdo->inTransaction(), 'all source failure transactions closed');

// Execute private CLI against the actual disposable database, not a mocked PDO.
putenv('ANYTOUR_DATA_DSN='.$dsn); putenv('ANYTOUR_DATA_DB_USER=root'); putenv('ANYTOUR_DATA_DB_PASSWORD='.$password);
$cli = __DIR__.'/../scripts/catalog/anytour_catalog_preflight.php';
function cliResult(array $args): array {
    global $cli;
    $p = proc_open(array_merge([PHP_BINARY,$cli],$args), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('CLI process unavailable');
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err=stream_get_contents($pipes[2]); fclose($pipes[2]); return [proc_close($p),$out,$err];
}
$args = ['--ids=10001,10002','--expect-dsn-sha256='.hash('sha256',$dsn),
    '--expect-database-sha256='.hash('sha256','anytour_preflight_fixture')];
[$status,$out,$err] = cliResult($args);
verify($status === 0 && $err === '', 'real CLI preflight succeeds');
$cliReport = json_decode($out,true,512,JSON_THROW_ON_ERROR);
verify($cliReport['source_profiles'] === 2 && $cliReport['target_schema_state'] === 'absent', 'CLI report from actual SQL');
verify(!str_contains($out,$dsn) && !str_contains($out,$password) && !str_contains($out,'Описание'), 'no secrets or source text in report');
foreach ([[],array_merge($args,['--apply']),array_merge($args,['--ids=10001']),
    ['--ids=10001','--expect-dsn-sha256='.str_repeat('0',64),$args[2]],
    [$args[0],$args[1],'--expect-database-sha256='.str_repeat('0',64)]] as $bad) {
    [$status,$out,$err] = cliResult($bad);
    verify($status === 1 && $out === '' && str_contains($err,'ANYTOUR_PREFLIGHT_FAILED'), 'CLI bad target/argument fails closed');
    verify(!str_contains($err,$dsn) && !str_contains($err,$password), 'CLI errors sanitized');
}
verify(legacyHash($pdo) === $before && count($pdo->query('SHOW TABLES')->fetchAll()) === 2, 'CLI never installs or seeds');

// Explicit test-only schema install proves preflight/plan/seed share one source contract.
$sql = preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql'));
$statements = array_values(array_filter(array_map('trim',explode(';',$sql))));
$pdo->exec(array_shift($statements));
verify($catalog->preflight($ids)['target_schema_state'] === 'partial_requires_review', 'partial schema is not adopted');
foreach ($statements as $statement) $pdo->exec($statement);
$present = $catalog->preflight($ids);
verify($present['target_schema_state'] === 'present_requires_review', 'all present still requires ownership review');
$plan = $catalog->plan($ids);
verify($plan['source_sha256'] === $report['source_sha256'] && $present['source_sha256'] === $plan['source_sha256'], 'identical pre-schema/post-schema seed digest');
$seed = $catalog->seed($ids,$report['source_sha256']);
verify($seed['created'] === 1000 && $seed['verified_bridges'] === 1000, 'reviewed preflight hash works with unchanged seed');
verify(legacyHash($pdo) === $before, 'old catalogue remains unchanged');
$after = $catalog->preflight($ids);
verify($after['source_sha256'] === $report['source_sha256'] && $after['writes'] === 0, 'inspection after seed remains read-only');
verify($catalog->seed($ids,$report['source_sha256'])['created'] === 0, 'no duplicate canonical hotels');
echo "ANYTOUR_PREFLIGHT_MYSQL_OK checks=$checks profiles=1000 readonly_engine=1 cli=1 hash_parity=1 real_mysql=1 live_database=0\n";
