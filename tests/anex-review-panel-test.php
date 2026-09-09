<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/admin/anex-review/service.php';
require_once __DIR__ . '/../app/admin/anex-review/access.php';
require_once __DIR__ . '/../app/admin/anex-review/view.php';
require_once __DIR__ . '/../app/integrations/anex-search-mapping-registry.php';

$checks = 0;
function check(bool $ok, string $message): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
function fails(callable $fn, string $reason): void {
    try { $fn(); } catch (Throwable $e) { check($e->getMessage() === $reason, $reason . ': ' . $e->getMessage()); return; }
    throw new RuntimeException('expected ' . $reason);
}

// CI database is explicitly named and local. Never accept the application DSN.
$dsn = getenv('ANEX_REVIEW_TEST_DSN') ?: '';
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') throw new RuntimeException('test_database_required');
$db = new PDO($dsn, 'root', getenv('ANEX_REVIEW_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$fixtures = [
    'catalog_hotels' => 'id INT PRIMARY KEY,name VARCHAR(300),country_id INT,country_name VARCHAR(100),region_name VARCHAR(100),subregion_name VARCHAR(100),category INT,latitude DECIMAL(10,7),longitude DECIMAL(10,7)',
    'anex_search_hotel_observations' => 'anex_hotel_id INT PRIMARY KEY,hotel_name VARCHAR(300),country_id INT,search_count INT,last_seen_utc DATETIME',
    'anex_hotels' => 'anex_hotel_id INT PRIMARY KEY,xml_name VARCHAR(300),api_name VARCHAR(300),api_address VARCHAR(1024),checked_at DATETIME',
    'anex_hotel_auto_matches' => 'anex_hotel_id INT PRIMARY KEY,candidate_count INT,automated_status VARCHAR(32),automated_reason VARCHAR(64)',
    'anex_hotel_candidates' => 'anex_hotel_id INT,candidate_rank INT,catalog_hotel_id INT,name_similarity DECIMAL(8,6),score DECIMAL(8,6),distance_m DECIMAL(12,2),candidate_json TEXT,PRIMARY KEY(anex_hotel_id,candidate_rank)',
    'anex_hotel_decisions' => "anex_hotel_id INT PRIMARY KEY,decision_status VARCHAR(32),catalog_hotel_id INT,decided_by VARCHAR(255),decision_note TEXT,decided_at DATETIME",
    'anex_hotel_search_mappings' => 'anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,enabled INT,scope VARCHAR(32),approval_policy VARCHAR(64),match_class VARCHAR(32)'
];
foreach ($fixtures as $table => $columns) $db->exec('CREATE TABLE ' . $table . ' (' . $columns . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$schema = file_get_contents(__DIR__ . '/../app/admin/anex-review/schema.sql');
foreach (explode(';', $schema) as $sql) if (trim($sql) !== '') $db->exec($sql);
$db->exec("INSERT INTO catalog_hotels VALUES (101,'Hotel One',1,'Turkey','Region','Resort',5,36.0000000,30.0000000),(102,'Hotel Two',1,'Turkey','Region','Resort',5,36.0001000,30.0001000),(103,'Different Country',2,'Egypt','Region','Resort',5,27,33)");
$observe = $db->prepare("INSERT INTO anex_search_hotel_observations VALUES (?,?,1,?, '2026-09-09 05:00:00')");
for ($id=1;$id<=30;$id++) $observe->execute([$id,$id === 1 ? '<script>alert(1)</script> Hotel' : 'Hotel ' . $id,100-$id]);
$db->exec("INSERT INTO anex_hotels VALUES (1,'Hotel One','Hotel One','Long address','2026-09-08 12:00:00')");
$db->exec("INSERT INTO anex_hotel_auto_matches VALUES (1,608,'review','competing_candidates')");
$insert = $db->prepare('INSERT INTO anex_hotel_candidates VALUES (?,?,?,1,0.9,10,?)');
foreach ([1,2,3,4,5,6,7,8,9,10,11,12] as $id) {
    $insert->execute([$id,1,101,'{"name":"Hotel One","address":"<img src=x onerror=alert(1)>"}']);
    $insert->execute([$id,2,102,'{"name":"Hotel Two"}']);
}
$insert->execute([1,3,103,'{}']);
$db->exec("INSERT INTO anex_hotel_decisions VALUES (20,'accepted',101,'owner:historic','preserve me','2026-09-08 12:00:00')");
$db->exec("INSERT INTO anex_hotel_decisions VALUES (21,'rejected',NULL,'owner:historic','hotel-wide block','2026-09-08 12:00:00')");
$db->exec("INSERT INTO anex_hotel_search_mappings VALUES (22,102,1,'preview','owner_exact_and_strong_20260908','strong_candidate')");
$service = new AnexReviewService($db);
$canonical = $db->query('SELECT * FROM catalog_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$policy = $db->query('SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
$historic = $db->query('SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
$candidateHash = hash('sha256', AnexReviewService::json($db->query('SELECT * FROM anex_hotel_candidates ORDER BY anex_hotel_id,candidate_rank')->fetchAll(PDO::FETCH_ASSOC)));

$queue = $service->queue(['status'=>'all']);
check(count($queue['items']) === 25 && $queue['total'] === 30 && $queue['pages'] === 2, 'pagination');
check(count($service->queue(['status'=>'all','page'=>2])['items']) === 5, 'next page');
check($queue['items'][0]['anex_hotel_id'] === 1, 'frequency order');
check($service->queue(['status'=>'mapped'])['total'] === 2, 'manual and policy projection');
check($service->queue(['q'=>'%'])['total'] === 0, 'LIKE literal wildcard');
check($service->queue(['q'=>"' OR 1=1 --"])['total'] === 0, 'bound search');
check($service->queue(['q'=>'1','status'=>'all'])['total'] > 0, 'search by ID/name');
check($service->queue(['country'=>2])['total'] === 0, 'country filter');
fails(fn()=>$service->queue(['status'=>'invented']), 'invalid_status');
fails(fn()=>$service->detail('1 OR 1'), 'invalid_hotel_id');
$first = $service->detail(1);
check($first['evidence']['candidate_count'] === 608 && count($first['candidates']) === 3, 'incomplete staging not disguised');
check($service->detail(30)['source'] === null && $service->detail(30)['candidates'] === [], 'missing evidence remains visible');
check((int)$db->query('SELECT COUNT(*) FROM anex_review_state')->fetchColumn() === 0, 'GET does not create state');
check((int)$db->query('SELECT COUNT(*) FROM anex_review_audit')->fetchColumn() === 0, 'GET does not audit a decision');

$session = ['actor'=>'owner:test','authenticated'=>true,'expires_at'=>time()+300,'capabilities'=>['anex:review']];
check(AnexReviewAccess::principal($session) === 'owner:test', 'verified actor');
fails(fn()=>AnexReviewAccess::principal([]), 'review_forbidden');
fails(fn()=>AnexReviewAccess::principal(array_merge($session,['expires_at'=>time()-1])), 'review_forbidden');
fails(fn()=>AnexReviewAccess::principal(array_merge($session,['capabilities'=>[]])), 'review_forbidden');
$csrf = str_repeat('a',64);
AnexReviewAccess::post(['REQUEST_METHOD'=>'POST'], ['csrf'=>$csrf], $csrf);
fails(fn()=>AnexReviewAccess::post(['REQUEST_METHOD'=>'GET'], ['csrf'=>$csrf],$csrf),'method_not_allowed');
fails(fn()=>AnexReviewAccess::post(['REQUEST_METHOD'=>'POST'], ['csrf'=>'bad'],$csrf),'review_csrf');
fails(fn()=>AnexReviewAccess::post(['REQUEST_METHOD'=>'POST','HTTP_SEC_FETCH_SITE'=>'cross-site'], ['csrf'=>$csrf],$csrf),'review_csrf');

function request(AnexReviewService $service, int $id, string $action, ?int $target): array {
    return ['id'=>$id,'action'=>$action,'target'=>$target,'version'=>$service->detail($id)['version'],'request_id'=>bin2hex(random_bytes(16))];
}
$later = request($service,1,'later',null);
$laterResult = $service->decide($later,'owner:test');
check($laterResult['mapped_id'] === null && $service->queue(['status'=>'later'])['total'] === 1, 'later persisted without mapping');
check((int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions WHERE anex_hotel_id=1')->fetchColumn() === 0, 'later no manual block');
check($service->decide($later,'owner:test')['replayed'] === true, 'idempotent replay before stale test');
fails(fn()=>$service->decide($later,'owner:other'), 'request_reused');
$stale=$later; $stale['request_id']=bin2hex(random_bytes(16));
fails(fn()=>$service->decide($stale,'owner:test'), 'stale_evidence');
check((int)$db->query('SELECT COUNT(*) FROM anex_review_audit')->fetchColumn() === 1, 'one durable audit for double click');

$reject = request($service,1,'reject_pair',101);
$service->decide($reject,'owner:test');
check($service->queue(['status'=>'pair_rejected'])['total'] === 1, 'pair exclusion persisted');
check((int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions WHERE anex_hotel_id=1')->fetchColumn() === 0, 'pair rejection not hotel-wide');
fails(fn()=>$service->decide(request($service,1,'accept',101),'owner:test'), 'pair_already_excluded');
fails(fn()=>$service->decide(request($service,1,'accept',103),'owner:test'), 'target_country_mismatch');
fails(fn()=>$service->decide(request($service,1,'accept',999),'owner:test'), 'not_a_saved_candidate');
$accept = request($service,1,'accept',102);
$result = $service->decide($accept,'owner:test');
check($result['mapped_id'] === 102, 'alternative accepted after pair rejection');
$registry = AnyTourAnexSearchMappingRegistry::fromPdo($db);
check($registry->resolve('anex_xml',1,'preview') === 102, 'existing preview registry reads new owner decision');
check($registry->resolve('anex_xml',1,'production') === null, 'production remains disabled');
check($service->decide($accept,'owner:test')['replayed'] === true, 'accept replay');
fails(fn()=>$service->decide(request($service,1,'accept',101),'owner:test'), 'existing_decision_preserved');
fails(fn()=>$service->decide(request($service,1,'reject_pair',102),'owner:test'), 'active_mapping_preserved');
check($service->queue(['status'=>'later'])['total'] === 0, 'approval clears deferral');

// Two owner tabs and an imported evidence refresh cannot silently overwrite each other.
$tabA = request($service,2,'accept',101);
$tabB = request($service,2,'accept',102);
$service->decide($tabA,'owner:test');
fails(fn()=>$service->decide($tabB,'owner:test'), 'stale_evidence');
$oldEvidence = request($service,3,'accept',101);
$db->exec("UPDATE anex_hotel_candidates SET candidate_json='{}' WHERE anex_hotel_id=3 AND candidate_rank=1");
fails(fn()=>$service->decide($oldEvidence,'owner:test'), 'stale_evidence');
$db->prepare('UPDATE anex_hotel_candidates SET candidate_json=? WHERE anex_hotel_id=3 AND candidate_rank=1')->execute(['{"name":"Hotel One","address":"<img src=x onerror=alert(1)>"}']);
$volatile = request($service,3,'later',null);
$db->exec('UPDATE anex_search_hotel_observations SET search_count=search_count+1 WHERE anex_hotel_id=3');
check($service->decide($volatile,'owner:test')['action'] === 'later', 'frequency change does not stale evidence');

// Fault after the decision insert must roll back BOTH decision and state.
$db->exec("CREATE TRIGGER review_audit_failure BEFORE INSERT ON anex_review_audit FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test_audit_failure'");
$beforeRollback=$service->detail(4)['version'];
try { $service->decide(request($service,4,'accept',101),'owner:test'); throw new RuntimeException('missing failure'); }
catch (PDOException $e) { check(strpos($e->getMessage(),'test_audit_failure') !== false,'injected failure'); }
$db->exec('DROP TRIGGER review_audit_failure');
check($service->detail(4)['version'] === $beforeRollback, 'transaction rollback retains version');
check((int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions WHERE anex_hotel_id=4')->fetchColumn() === 0, 'failed audit rolls back acceptance');

check($canonical === $db->query('SELECT * FROM catalog_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'catalog rows unchanged');
check($policy === $db->query('SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC), 'policy rows unchanged');
check($historic === $db->query('SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN (20,21) ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC), 'previous manual accept/block unchanged');
check($candidateHash === hash('sha256',AnexReviewService::json($db->query('SELECT * FROM anex_hotel_candidates ORDER BY anex_hotel_id,candidate_rank')->fetchAll(PDO::FETCH_ASSOC))), 'candidate evidence unchanged');

$html = anex_review_render($service->queue(['status'=>'all']),$service->detail(1),[], $csrf,false,'test-nonce');
check(strpos($html,'<script>alert(1)</script>') === false && strpos($html,'&lt;script&gt;') !== false,'escaped hotel name');
check(strpos($html,'<img src=x') === false && strpos($html,'&lt;img') !== false,'escaped candidate JSON');
check(strpos($html,'type="submit" disabled') !== false,'read-only actions disabled');
check(strpos($html,'608') !== false && strpos($html,'не доказывает отсутствие конкурентов') !== false,'incomplete evidence warning');
check(strpos($html,'<script') === false && strpos($html,'https://') === false,'no scripts or remote resources');
// CI-only synthetic fixture, not user hotel data. Retained for later visual review.
if (getenv('ANEX_REVIEW_TEST_HTML')) file_put_contents(getenv('ANEX_REVIEW_TEST_HTML'), $html);
echo 'ANEX_REVIEW_PANEL_OK checks=' . $checks . ' supplier_calls=0 test_db=anex_review_test live_db=untouched' . PHP_EOL;
