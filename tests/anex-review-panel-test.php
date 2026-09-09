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
    'anex_hotel_search_mappings' => "anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,enabled INT,scope VARCHAR(32),approval_policy VARCHAR(64),match_class VARCHAR(32),source_row_digest CHAR(64) DEFAULT '',mapping_digest CHAR(64) DEFAULT ''",
    'anex_hotel_content' => 'anex_hotel_id INT PRIMARY KEY,status VARCHAR(24),source_sha CHAR(40),content_sha256 CHAR(64),payload_json MEDIUMTEXT,reason VARCHAR(64),fetched_at_utc DATETIME',
    'catalog_hotel_details' => 'hotel_id INT PRIMARY KEY,status VARCHAR(24),source_hash CHAR(64),address VARCHAR(1000),site VARCHAR(1000),latitude DECIMAL(10,7),longitude DECIMAL(10,7),fetched_at DATETIME,primary_image_url VARCHAR(2048),description MEDIUMTEXT,images_json MEDIUMTEXT'
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
$db->exec("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy,match_class) VALUES (22,102,1,'preview','owner_exact_and_strong_20260908','strong_candidate')");
$content = ['id'=>1,'name'=>'Hotel One','address'=>'ANEX address','description'=>'<script>unsafe description</script>',
    'latitude'=>36.0,'longitude'=>30.0,'photos'=>[['url'=>'https://images.example.com/anex.jpg','note'=>'Территория ANEX']],
    'location'=>'Рядом с пляжем', 'attributes'=>[['name'=>'Год открытия','value'=>'2005']],
    'rooms'=>[['name'=>'Standard','description'=>'Номер с балконом']]];
$contentHash = hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
$payload = AnexReviewService::json(['status'=>'ok','hotel_id'=>1,'content'=>$content,'source_sha256'=>$contentHash]);
$db->prepare("INSERT INTO anex_hotel_content VALUES (1,'ready',?,?,?,NULL,'2026-09-09 06:00:00')")->execute([str_repeat('a',40),$contentHash,$payload]);
$db->prepare("INSERT INTO catalog_hotel_details VALUES (101,'success',?,'TV address','https://example.com',36,30,'2026-09-09 06:00:00',?,'Saved TV description',?)")
    ->execute([str_repeat('b',64),'https://images.example.com/main.jpg','["https://images.example.com/second.jpg","javascript:alert(1)"]']);
$service = new AnexReviewService($db);
check(AnexReviewService::publicCard(16193)['url'] === 'https://anextour.ru/hotels/turkey/dragut-point-north', 'exact official ANEX page identity');
check(AnexReviewService::publicCard(16194) === null, 'official page is never inferred for another ID');
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
check($first['content']['status'] === 'ready' && $first['content']['content']['address'] === 'ANEX address', 'content ID/digest verified with restored coordinate types');
check($first['candidates'][0]['details']['description'] === 'Saved TV description', 'persistent TV content reused');
$contentVersion=$first['version'];
$db->exec("UPDATE anex_hotel_content SET content_sha256=REPEAT('c',64) WHERE anex_hotel_id=1");
check($service->detail(1)['content']['status'] === 'integrity_unavailable', 'corrupt content hidden');
check($service->detail(1)['version'] !== $contentVersion, 'content integrity change stales evidence');
$db->prepare('UPDATE anex_hotel_content SET content_sha256=? WHERE anex_hotel_id=1')->execute([$contentHash]);
check($service->detail(1)['version'] === $contentVersion, 'unchanged recovered content preserves version');
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

require __DIR__ . '/anex-review-pair-import-test.php';

$html = anex_review_render($service->queue(['status'=>'all']),$service->detail(1),[], $csrf,false,'test-nonce');
check(strpos($html,'<script>alert(1)</script>') === false && strpos($html,'&lt;script&gt;') !== false,'escaped hotel name');
check(strpos($html,'<img src=x') === false && strpos($html,'&lt;img') !== false,'escaped candidate JSON');
check(strpos($html,'type="submit" disabled') !== false,'read-only actions disabled');
check(strpos($html,'608') !== false && strpos($html,'не доказывает отсутствие конкурентов') !== false,'incomplete evidence warning');
check(strpos($html,'<script') === false && strpos($html,'loading="lazy"') !== false,'gallery uses lazy images without scripts');
check(strpos($html,'unsafe description&lt;/script&gt;') !== false,'content description escaped');
check(strpos($html,'javascript:alert') === false && strpos($html,'src="https://images.example.com/anex.jpg"') !== false,'safe own ANEX photo displayed');
check(strpos($html,'referrerpolicy="no-referrer"') !== false && strpos($html,'alt="ANEX · Территория ANEX"') !== false,'gallery privacy and source attribution');
check(strpos($html,'Номер с балконом') !== false && strpos($html,'2005') !== false,'saved ANEX rooms and characteristics displayed');
check(strpos($html,'rel="noopener noreferrer"') !== false,'external photo link privacy');
check(strpos(anex_review_gallery(['http://example.com/a','https://user:pass@example.com/a','https://localhost/a','data:image/png,x'], 'ANEX'),'<a ') === false,'unsafe photo URLs rejected');
check(strpos(anex_review_gallery([], 'ANEX'), 'карточке ANEX фотографий нет') !== false,'missing ANEX gallery is not filled with Tourvisor photos');
check($db->query('SELECT payload_json FROM anex_hotel_content WHERE anex_hotel_id=1')->fetchColumn() === $payload, 'content payload never rewritten');
$sectionsJson = json_encode([
    ['title'=>' РАСПОЛОЖЕНИЕ ', 'text'=>"Первая строка\r\nВторая строка"],
    ['title'=>'<img src=x onerror=alert(1)>', 'text'=>'<script>unsafe section</script>'],
    ['title'=>'', 'text'=>'Текст без заголовка'],
    ['title'=>'Пустой раздел', 'text'=>' ']
], JSON_UNESCAPED_UNICODE);
$sectionsHtml = anex_review_description($sectionsJson, true);
check(strpos($sectionsHtml, '<h4>РАСПОЛОЖЕНИЕ</h4>') !== false && strpos($sectionsHtml, "Первая строка<br />\nВторая строка") !== false, 'decoded sections and line breaks');
check(strpos($sectionsHtml, '<script') === false && strpos($sectionsHtml, '<img') === false && strpos($sectionsHtml, '&lt;script&gt;unsafe section') !== false && strpos($sectionsHtml, '&lt;img src=x') !== false, 'section title and text escaped');
check(strpos($sectionsHtml, '<h4>Описание</h4>') !== false && strpos($sectionsHtml, 'Пустой раздел') === false, 'empty title fallback and empty section omitted');
check(strpos($sectionsHtml, '&quot;title&quot;') === false && substr_count($sectionsHtml, 'description-section') === 3, 'JSON wrapper not displayed');
foreach (['[{"title":"broken"', '[{"title":[],"text":"bad"}]', '[{"title":"bad","text":{}}]', '{"title":"object","text":"bad"}', json_encode(array_fill(0,33,['title'=>'A','text'=>'B'])), '[' . str_repeat(' ',16000) . ']'] as $malformed) {
    check(strpos(anex_review_description($malformed,true), 'Не удалось прочитать') !== false, 'malformed or oversized structured description explicit');
}
check(strpos(anex_review_description('[]',true),'Нет сохранённого описания') !== false && strpos(anex_review_description(null,true),'Нет сохранённого описания') !== false, 'missing description explicit');
check(anex_review_description('Plain <b>text</b>',true) === '<p>Plain &lt;b&gt;text&lt;/b&gt;</p>', 'plain ANEX text remains supported');
check(strpos(anex_review_description('[TV] Plain text',false),'[TV] Plain text') !== false, 'Tourvisor prose not parsed as ANEX sections');
$fortunaRow = ['status'=>'ready','content'=>['id'=>17097,'description'=>$sectionsJson]];
$fortunaHtml = anex_review_saved_content($fortunaRow,true);
check(strpos($fortunaHtml,'<summary>Условия предложения FORTUNA</summary>') !== false && strpos($fortunaHtml,'не подтверждает совпадение с конкретным отелем') !== false, 'verified Fortuna offer distinguished from hotel description');
$fortunaRow['content']['id'] = 999;
$fortunaRow['content']['name'] = 'Hotel Fortuna';
check(strpos(anex_review_saved_content($fortunaRow,true),'Условия предложения FORTUNA') === false, 'Fortuna name alone does not classify a hotel');
// CI-only synthetic fixture, not user hotel data. Retained for later visual review.
if (getenv('ANEX_REVIEW_TEST_HTML')) file_put_contents(getenv('ANEX_REVIEW_TEST_HTML'), $html);
// Separate synthetic dossier for HTTP/gallery checks; never added to application data.
$browserContent = $content;
$browserContent['id'] = 5;
$browserContent['name'] = 'Hotel 5';
$browserContent['description'] = json_encode([
    ['title'=>'РАСПОЛОЖЕНИЕ', 'text'=>"Сохранённое описание ANEX для проверки сравнения карточек.\r\nВторая строка расположения."],
    ['title'=>'НОМЕРА', 'text'=>str_repeat('Просторный номер с балконом и видом на территорию. ', 16)],
    ['title'=>'ПИТАНИЕ', 'text'=>'Завтрак и ужин в основном ресторане.']
], JSON_UNESCAPED_UNICODE);
$browserHash = hash('sha256', json_encode($browserContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
$browserPayload = AnexReviewService::json(['status'=>'ok','hotel_id'=>5,'content'=>$browserContent,'source_sha256'=>$browserHash]);
$db->prepare("INSERT INTO anex_hotel_content VALUES (5,'ready',?,?,?,NULL,'2026-09-09 06:00:00')")->execute([str_repeat('a',40),$browserHash,$browserPayload]);
echo 'ANEX_REVIEW_PANEL_OK checks=' . $checks . ' supplier_calls=0 test_db=anex_review_test live_db=untouched' . PHP_EOL;
