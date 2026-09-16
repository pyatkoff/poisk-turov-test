<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/catalog/anytour_stay_import.php';
$n = 0;
function ok(bool $condition, string $message): void { global $n; $n++; if (!$condition) throw new RuntimeException($message); }
function fails(callable $fn, string $message, string $class = Throwable::class): void {
    try { $fn(); } catch (Throwable $e) { ok($e instanceof $class, $message . ': unexpected ' . get_class($e)); return; }
    ok(false, $message . ': no failure');
}
function entry(string $namespace, string $hotelKey, int $hotelId, string $roomKey, string $externalKey): array {
    return ['scope' => ['namespace' => $namespace, 'hotelKey' => $hotelKey, 'operatorKey' => '005'],
        'reference' => ['kind' => 'room', 'keyKind' => 'code', 'externalKey' => $externalKey],
        'hotelId' => $hotelId, 'sourceSha256' => hash('sha256', '{}'),
        'target' => ['localKey' => $roomKey, 'nameRu' => 'Стандарт · море', 'categoryCode' => 'standard', 'facts' => ['view' => 'Море', 'areaM2' => 36.0]],
        'evidence' => ['ref' => 'fixture-reviewed-room-dossier', 'sha256' => hash('sha256', 'fixture-only-reviewed-evidence'), 'reviewedBy' => 'fixture-reviewer']];
}
function batch(array $rows, string $operation = 'fixture-batch'): array { return ['version' => 1, 'operation' => $operation, 'rows' => $rows]; }
function measure(PDO $p): array {
    $names = ['anytour_hotels', 'anytour_hotel_sources', 'anytour_hotel_rooms', 'anytour_stay_mappings']; $all = [];
    foreach ($names as $t) $all[$t] = $p->query("SELECT * FROM $t ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    return $all;
}
$r = entry('tourvisor', '00015', 41, 'sea', 'STD SV');
$input = batch([$r]); $unchanged = serialize($input);
$m = AnyTourStayImport::manifest($input);
ok(serialize($input) === $unchanged, 'input not mutated');
ok($m['rows'][0]['scope']['hotelKey'] === '00015' && $m['rows'][0]['scope']['operatorKey'] === '005', 'leading zeros retained');
$second = entry('anex', '001', 41, 'sea', 'S/SV');
$ordered = AnyTourStayImport::manifest(batch([$r, $second]));
ok($ordered === AnyTourStayImport::manifest(batch([$second, $r])), 'stable source ordering');
foreach (['version', 'operation', 'rows'] as $key) { $bad = $input; unset($bad[$key]); fails(fn() => AnyTourStayImport::manifest($bad), 'required top field'); }
$bad = $input; $bad['price'] = 199390; fails(fn() => AnyTourStayImport::manifest($bad), 'no offer payload');
foreach ([[], array_fill(0, 1001, $r), ['named' => $r], [false]] as $rows) fails(fn() => AnyTourStayImport::manifest(batch($rows)), 'invalid batch');
fails(fn() => AnyTourStayImport::manifest(batch([$r, $r])), 'duplicate exact identity');
foreach (['../operation', '', 'UPPER', "op\n", str_repeat('x', 129)] as $op) fails(fn() => AnyTourStayImport::manifest(batch([$r], $op)), 'invalid operation');
foreach (['41', 0, -1, true] as $id) { $bad = $r; $bad['hotelId'] = $id; fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'independent ID typing'); }
foreach (['sourceSha256', 'target', 'evidence', 'scope', 'reference'] as $key) { $bad = $r; $bad[$key] = null; fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'required row content'); }
$bad = $r; $bad['evidence']['sha256'] = 'guess'; fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'evidence digest');
$bad = $r; $bad['evidence']['reviewedBy'] = ''; fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'reviewer required');
$bad = $r; $bad['target']['facts']['nights'] = 7; fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'tour nights never room facts');
$bad = $second; $bad['target']['facts']['view'] = 'Территория'; fails(fn() => AnyTourStayImport::manifest(batch([$r, $bad])), 'room distinctions cannot collide in batch');
$bad = $r; $bad['scope']['hotelKey'] = 15; fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'source keys not numeric canonical IDs');
$bad = $r; $bad['scope']['wildcard'] = true; fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'no global scope fallback');
$bad = $r; $bad['target']['nameRu'] = str_repeat('a', 256); fails(fn() => AnyTourStayImport::manifest(batch([$bad])), 'bounded name');
$pure = $n;
if (in_array('--unit-only', $argv, true)) {
    if (getenv('CI')) throw new RuntimeException('CI must execute real MySQL tests');
    echo "ANYTOUR_STAY_IMPORT_PURE_OK checks=$pure sql=NOT_RUN\n"; exit;
}
$dsn = (string)getenv('ANYTOUR_STAY_IMPORT_TEST_DSN');
if (!extension_loaded('pdo_mysql') || $dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=anytour_stay_import_fixture;charset=utf8mb4') throw new RuntimeException('Explicit local disposable MySQL fixture required');
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$pdo = new PDO($dsn, 'root', (string)getenv('ANYTOUR_STAY_IMPORT_TEST_PASSWORD'), $options);
ok((int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() === 0, 'fresh disposable fixture');
$root = dirname(__DIR__);
$pdo->exec(file_get_contents($root . '/v2/data/migrations/20260916-anytour-canonical-catalog.sql'));
$import = new AnyTourStayImport($pdo);
fails(fn() => $import->plan($input), 'missing stay installation fails instead of auto-DDL');
ok((int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() === 3, 'no automatic schema creation');
$pdo->exec(file_get_contents($root . '/v2/data/migrations/20260916-anytour-stay-catalog.sql'));
$hotel = $pdo->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,created_at,updated_at) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
$hotel->execute(['{}', hash('sha256', '{}')]); $a = (int)$pdo->lastInsertId();
$hotel->execute(['{}', hash('sha256', '{}')]); $b = (int)$pdo->lastInsertId();
$source = $pdo->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES(?,?,?,'fixture','{}',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
foreach (['tourvisor', 'anex', 'andromeda'] as $ns) $source->execute([$ns, '00015', $a, hash('sha256', '{}')]);
$catalog = new AnyTourStayCatalog($pdo); $events = [];
$checkpoint = static function(array $r) use (&$events): void { $events[] = $r; };
$rows = [];
for ($i = 0; $i < 1000; $i++) $rows[] = entry('tourvisor', '00015', $a, 'room-' . $i, 'ROOM-' . $i);
$large = batch($rows, 'fixture-thousand');
$before = measure($pdo); $plan = $import->plan($large);
ok($before === measure($pdo), 'plan is actually read-only');
ok($plan['rows'] === 1000 && $plan['createRooms'] === 1000 && $plan['createMappings'] === 1000 && $plan['writes'] === 0, 'full 1000-row plan');
$receipt = $import->apply($large, $plan['planSha256'], $checkpoint);
ok($receipt['status'] === 'committed_verified' && $receipt['createdRooms'] === 1000 && $receipt['createdMappings'] === 1000, '1000 rooms and mappings committed and reread');
ok(array_column($events, 'status') === ['before_commit', 'committed_unverified', 'committed_verified'], 'durable boundary callback ordering');
ok($events[0]['expectedReadbackSha256'] === $receipt['expectedReadbackSha256'], 'postcommit matches precommit fingerprint');
$after = measure($pdo);
ok($after['anytour_hotels'] === $before['anytour_hotels'] && $after['anytour_hotel_sources'] === $before['anytour_hotel_sources'], 'hotel/source rows never changed');
fails(fn() => $import->apply($large, $plan['planSha256'], $checkpoint), 'old plan must not replay');
ok($after === measure($pdo), 'stale replay changes nothing');
$again = $import->plan($large);
ok($again['createRooms'] === 0 && $again['createMappings'] === 0 && $again['unchangedMappings'] === 1000, 'fresh repeat plan is a no-op');
$noop = $import->apply($large, $again['planSha256'], $checkpoint);
ok($noop['createdRooms'] === 0 && $noop['createdMappings'] === 0 && $after === measure($pdo), 'fresh no-op apply preserves all rows');
// Three providers share one explicitly reviewed local room, never by source numeric ID equality.
$converge = batch([entry('anex', '00015', $a, 'shared-sea', '001'), entry('andromeda', '00015', $a, 'shared-sea', '999'), entry('tourvisor', '00015', $a, 'shared-sea', 'SV')], 'fixture-converge');
$p = $import->plan($converge); $res = $import->apply($converge, $p['planSha256'], $checkpoint);
ok($res['createdRooms'] === 1 && $res['createdMappings'] === 3, 'batch converges sources on one reviewed local room');
$roomIds = [];
foreach ($converge['rows'] as $v) $roomIds[] = $catalog->resolve($v['scope'], [$v['reference']])['items'][0]['canonical']['id'];
ok(count(array_unique($roomIds)) === 1, 'existing resolver reads same own room for all providers');
$meal = entry('tourvisor', '00015', $a, 'unused', 'HB+');
$meal['reference']['kind'] = 'meal'; $meal['target'] = ['code' => 'half-board-plus'];
$meals = batch([$meal], 'fixture-meal'); $p = $import->plan($meals); $res = $import->apply($meals, $p['planSha256'], $checkpoint);
ok($res['createdRooms'] === 0 && $catalog->resolve($meal['scope'], [$meal['reference']])['items'][0]['canonical']['code'] === 'half-board-plus', 'explicit plus plan retained');
$state = measure($pdo);
$bad = $converge; $bad['rows'][0]['target']['facts']['view'] = 'Территория'; $bad['rows'] = [$bad['rows'][0]];
fails(fn() => $import->plan($bad), 'existing editorial content not overwritten');
$bad = $meals; $bad['rows'][0]['target']['code'] = 'half-board'; fails(fn() => $import->plan($bad), 'existing plus decision not downgraded');
$bad = $meals; $bad['rows'][0]['evidence']['reviewedBy'] = 'other-reviewer'; fails(fn() => $import->plan($bad), 'review provenance not overwritten');
$pending = entry('tourvisor', '00015', $a, 'not-created', 'REJECTED');
$pdo->beginTransaction(); $catalog->recordDecision($pending['scope'], $pending['reference'], $a, 'rejected', null, $pending['evidence']); $pdo->commit();
fails(fn() => $import->plan(batch([$pending])), 'negative decision blocks promotion');
$state = measure($pdo);
$drift = batch([entry('tourvisor', '00015', $a, 'drift-room', 'DRIFT')], 'fixture-drift');
$p = $import->plan($drift);
$pdo->exec('UPDATE anytour_hotels SET revision=revision+1 WHERE id=' . $a);
fails(fn() => $import->apply($drift, $p['planSha256'], $checkpoint), 'canonical revision change invalidates reviewed plan');
$pdo->exec('UPDATE anytour_hotels SET revision=revision-1 WHERE id=' . $a);
$pdo->exec("UPDATE anytour_hotel_sources SET anytour_hotel_id=$b WHERE namespace='tourvisor'");
fails(fn() => $import->plan($drift), 'source hotel drift invalidates mapping');
$pdo->exec("UPDATE anytour_hotel_sources SET anytour_hotel_id=$a WHERE namespace='tourvisor'");
$pdo->exec("UPDATE anytour_hotel_sources SET source_json='{\"changed\":true}' WHERE namespace='tourvisor'");
fails(fn() => $import->plan($drift), 'corrupt source bytes cannot use unchanged digest');
$pdo->exec("UPDATE anytour_hotel_sources SET source_json='{}' WHERE namespace='tourvisor'");
$pdo->exec('UPDATE anytour_hotels SET is_active=0 WHERE id=' . $a);
fails(fn() => $import->plan($drift), 'inactive hotel cannot receive mappings');
$pdo->exec('UPDATE anytour_hotels SET is_active=1 WHERE id=' . $a);
$bad = $drift; $bad['rows'][0]['scope']['hotelKey'] = '15'; fails(fn() => $import->plan($bad), 'no leading-zero source fallback');
$bad = $drift; $bad['rows'][0]['target']['categoryCode'] = 'invented'; fails(fn() => $import->plan($bad), 'unknown room category held');
ok($state === measure($pdo), 'all rejected operations preserve catalogue data');
// Failure after actual INSERTs but before COMMIT rolls back the complete batch.
$p = $import->plan($drift); $reached = false;
fails(function() use ($import, $drift, $p, &$reached): void {
    $import->apply($drift, $p['planSha256'], static function(array $event) use (&$reached): void { $reached = $event['status'] === 'before_commit'; throw new RuntimeException('fixture journal failed'); });
}, 'failed precommit checkpoint rolls back');
ok($reached && $state === measure($pdo), 'actual room and mapping INSERTs rolled back together');
// Error after COMMIT must never be described as rollback or eligible for replay.
$p = $import->plan($drift);
fails(fn() => $import->apply($drift, $p['planSha256'], static function(array $event): void {
    if ($event['status'] === 'committed_unverified') throw new RuntimeException('fixture receipt failure after commit');
}), 'postcommit failure is explicit', AnyTourStayReadbackFailed::class);
ok($import->plan($drift)['createMappings'] === 0, 'postcommit rows remain committed despite receipt failure');
fails(fn() => $import->apply($drift, $p['planSha256'], $checkpoint), 'postcommit failed operation cannot replay old plan');
// A real COMMIT followed by a simulated lost acknowledgement exercises unknown outcome handling.
final class LostAckPDO extends PDO {
    public bool $loseAck = false;
    public function commit(): bool { $result = parent::commit(); if ($this->loseAck) throw new RuntimeException('fixture lost COMMIT ack'); return $result; }
}
$lost = new LostAckPDO($dsn, 'root', (string)getenv('ANYTOUR_STAY_IMPORT_TEST_PASSWORD'), $options);
$uncertainImport = new AnyTourStayImport($lost);
$uncertain = batch([entry('tourvisor', '00015', $a, 'uncertain-room', 'UNCERTAIN')], 'fixture-uncertain');
$p = $uncertainImport->plan($uncertain); $lost->loseAck = true;
fails(fn() => $uncertainImport->apply($uncertain, $p['planSha256'], $checkpoint), 'lost COMMIT ack classified unknown', AnyTourStayCommitUncertain::class);
ok($import->plan($uncertain)['createMappings'] === 0, 'independent connection discovers commit after acknowledgement loss');
$pdo->beginTransaction(); fails(fn() => $import->plan($drift), 'nested caller transaction rejected'); $pdo->rollBack();
$pdo->exec('UPDATE anytour_catalog_control SET schema_version=2 WHERE singleton_id=1');
fails(fn() => $import->plan($drift), 'unsupported schema version rejected');
$pdo->exec('UPDATE anytour_catalog_control SET schema_version=1 WHERE singleton_id=1');
echo "ANYTOUR_STAY_IMPORT_OK checks=$n pure=$pure sql=REAL_MYSQL batch1000=1 rollback=1 drift=1 postcommit=1 uncertain_commit=1 live_db_writes=0 supplier_calls=0\n";
