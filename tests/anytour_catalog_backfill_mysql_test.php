<?php
/** Real-MySQL coverage for the read-only AnyTour next-missing profile planner. */
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-catalog-backfill-v1.php';

$checks = 0;
function bfVerify(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function bfRefuses(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable) { bfVerify(true, $message); return; }
    bfVerify(false, $message);
}

$dsn = (string)getenv('ANYTOUR_BACKFILL_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;dbname=anytour_backfill_fixture;charset=utf8mb4$/D', $dsn)) {
    throw new RuntimeException('Explicit disposable loopback fixture required');
}
$password = (string)getenv('ANYTOUR_TEST_PASSWORD');
final class BackfillObservedPDO extends PDO {
    public bool $probeReadOnly = false;
    public bool $writeRejected = false;
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        if ($this->probeReadOnly && str_contains($query, 'content_ready_total')) {
            $this->probeReadOnly = false;
            try { $this->exec("INSERT INTO catalog_hotels(id,name) VALUES (999999,'FORBIDDEN_PROBE')"); }
            catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) !== 1792) throw $e;
                $this->writeRejected = true;
            }
        }
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
$pdo = new BackfillObservedPDO($dsn, 'root', $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
bfVerify($pdo->query('SHOW TABLES')->fetchAll() === [], 'fixture starts empty');
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
$sql = preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../v2/data/migrations/20260916-anytour-canonical-catalog.sql'));
foreach (array_values(array_filter(array_map('trim',explode(';',$sql)))) as $statement) $pdo->exec($statement);

$pdo->exec("INSERT INTO catalog_hotels(id,name,is_active) VALUES
 (10,'Ready 10',1),(11,'Already owned 11',1),(12,'Failed 12',1),(13,'Blank description 13',1),
 (14,'No images 14',1),(15,'Invalid images 15',1),(16,'Inactive 16',0),(17,'   ',1),
 (18,'Ready 18',1),(19,'Ready 19',1)");
$good='[\"https://fixture.test/one.jpg\"]';
$details=$pdo->prepare('INSERT INTO catalog_hotel_details(hotel_id,status,description,images_json,fetched_at) VALUES (?,?,?,?,?)');
foreach ([
 [10,'success','Desc 10',$good],[11,'success','Desc 11',$good],[12,'failure','Desc 12',$good],
 [13,'success','   ',$good],[14,'success','Desc 14','[]'],[15,'success','Desc 15','not-json'],
 [16,'success','Desc 16',$good],[17,'success','Desc 17',$good],[18,'success','Desc 18',$good],
 [19,'success','Desc 19',$good],
] as $row) $details->execute([$row[0],$row[1],$row[2],$row[3],'2026-09-17 01:00:00']);

$catalog = new AnyTourCanonicalCatalog($pdo);
$ownedPlan = $catalog->plan([11]);
$ownedSeed = $catalog->seed([11], $ownedPlan['source_sha256']);
bfVerify($ownedSeed['created'] === 1, 'fixture has one already-owned content-ready hotel');
function bfState(PDO $pdo): string {
    $tables=['catalog_hotels','catalog_hotel_details','anytour_hotels','anytour_hotel_sources']; $all=[];
    foreach ($tables as $table) $all[$table]=$pdo->query("SELECT * FROM $table ORDER BY 1")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256', json_encode($all, JSON_THROW_ON_ERROR));
}
$before=bfState($pdo);
$planner = new AnyTourCatalogBackfillV1($pdo);
$pdo->probeReadOnly=true; $report=$planner->planNext(2);
bfVerify($pdo->writeRejected, 'MySQL enforces read-only planner transaction');
bfVerify($report['status']==='backfill_plan_read_only' && $report['writes']===0 && $report['supplier_calls']===0, 'planner is explicitly read-only');
bfVerify($report['content_ready_total']===4 && $report['content_ready_bridged']===1 && $report['content_ready_missing']===3, 'coverage separates owned and missing content-ready hotels');
bfVerify($report['selectedIds']===[10,18] && $report['selected']===2 && $report['remaining_after_selected']===1, 'deterministic next missing cohort');
bfVerify($report['canonical_plan']['source_profiles']===2 && $report['canonical_plan']['existing_bridges']===0
    && $report['canonical_plan']['missingIds']===[] && $report['ready_for_seed_review']===true, 'selected IDs reuse canonical source digest contract');
bfVerify($report['seed_authorized']===false, 'planner output never authorizes seed');
bfVerify(bfState($pdo)===$before && !$pdo->inTransaction(), 'planner leaves source and AnyTour tables unchanged');
$full=$planner->planNext(1000);
bfVerify($full['selectedIds']===[10,18,19] && $full['remaining_after_selected']===0, 'large request remains bounded by factual missing cohort');
foreach ([0,-1,1001,'1.0','x','01'] as $bad) bfRefuses(fn()=>$planner->planNext($bad), 'invalid limit fails closed');
$pdo->beginTransaction(); bfRefuses(fn()=>$planner->planNext(1), 'caller transaction refused');
bfVerify($pdo->inTransaction(), 'caller transaction preserved'); $pdo->rollBack();

// Existing reviewed seed path changes ownership; next planner read must immediately exclude it.
$tenPlan=$catalog->plan([10]); $catalog->seed([10],$tenPlan['source_sha256']);
$afterOwned=$planner->planNext(1000);
bfVerify($afterOwned['content_ready_bridged']===2 && $afterOwned['selectedIds']===[18,19], 'new canonical bridge is reflected without cache or guess');
$stable=bfState($pdo); $planner->planNext(1); bfVerify(bfState($pdo)===$stable, 'repeated planner read is idempotent');

// Execute the private CLI against this actual disposable database.
putenv('ANYTOUR_DATA_DSN='.$dsn); putenv('ANYTOUR_DATA_DB_USER=root'); putenv('ANYTOUR_DATA_DB_PASSWORD='.$password);
$cli=__DIR__.'/../scripts/catalog/anytour_catalog_backfill_plan.php';
function bfCli(array $args): array {
    global $cli;
    $p=proc_open(array_merge([PHP_BINARY,$cli],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('CLI process unavailable');
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err=stream_get_contents($pipes[2]); fclose($pipes[2]); return [proc_close($p),$out,$err];
}
$args=['--limit=1','--expect-dsn-sha256='.hash('sha256',$dsn),
    '--expect-database-sha256='.hash('sha256','anytour_backfill_fixture')];
[$status,$out,$err]=bfCli($args);
bfVerify($status===0 && $err==='', 'real CLI planner succeeds');
$cliReport=json_decode($out,true,512,JSON_THROW_ON_ERROR);
bfVerify($cliReport['selectedIds']===[18] && $cliReport['selected']===1 && $cliReport['seed_authorized']===false, 'CLI returns exact next cohort only');
bfVerify(!str_contains($out,$dsn) && !str_contains($out,$password) && !str_contains($out,'Desc'), 'CLI does not expose credentials or saved presentation text');
foreach ([[],array_merge($args,['--apply=1']),array_merge($args,['--limit=2']),
    ['--limit=1','--expect-dsn-sha256='.str_repeat('0',64),$args[2]],
    [$args[0],$args[1],'--expect-database-sha256='.str_repeat('0',64)]] as $bad) {
    [$status,$out,$err]=bfCli($bad);
    bfVerify($status===1 && $out==='' && str_contains($err,'ANYTOUR_BACKFILL_PLAN_FAILED'), 'CLI bad target/argument fails closed');
    bfVerify(!str_contains($err,$dsn) && !str_contains($err,$password), 'CLI failure stays sanitized');
}
bfVerify(bfState($pdo)===$stable, 'CLI planning performs no database writes');
echo "ANYTOUR_BACKFILL_MYSQL_OK checks=$checks selected=2 remaining=2 readonly_engine=1 cli=1 real_mysql=1 live_database=0\n";
