<?php
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-profile-enrichment-v1.php';

function official_need(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException('OFFICIAL_CHECK_FAILED:' . $label);
}
function official_expect(callable $fn, string $needle, string $label): void
{
    try { $fn(); }
    catch (Throwable $e) {
        official_need(str_contains($e->getMessage(), $needle), $label . ':wrong:' . $e->getMessage());
        return;
    }
    throw new RuntimeException('OFFICIAL_CHECK_FAILED:' . $label . ':no_error');
}
function official_envelope(array $manifest): array
{
    $manifest['proposed_actions_sha256'] = hash('sha256', AnyTourProfileEnrichmentV1::json($manifest['proposed_actions']));
    $json = AnyTourProfileEnrichmentV1::json($manifest);
    return ['manifestJson'=>$json,'manifestSha256'=>hash('sha256', $json),'actionsSha256'=>$manifest['proposed_actions_sha256'],
        'anytourHotelIds'=>array_column($manifest['targets'], 'anytourHotelId'),'approvalRef'=>$manifest['approval_ref']];
}

// Synthetic, self-contained inputs. No approved production content, real supplier or HTTP request.
$manifest = ['schema_version'=>1,'source_policy'=>'official_review_missing_only_v1',
    'original_package_sha256'=>hash('sha256', 'synthetic original package'),
    'approval_ref'=>'https://github.com/example/fixture/issues/1#issuecomment-2','proposed_actions'=>[],
    'proposed_actions_sha256'=>'','targets'=>[]];
foreach (range(9001, 9011) as $index=>$id) {
    $page = "https://official.example/hotel/$id";
    $image = "https://official.example/images/$id.jpg";
    $manifest['targets'][] = ['anytourHotelId'=>$id,'expectedIdentity'=>['name'=>"Hotel $id",'country'=>'Страна','region'=>'Регион'],
        'sources'=>[['url'=>$page,'checked_at_utc'=>'2026-11-21T12:00:00Z','content_sha256'=>null]],
        'photos'=>[['url'=>$image,'source_page_url'=>$page,'sha256'=>hash('sha256', "synthetic image bytes $id")]]];
    $manifest['proposed_actions'][] = ['anytourHotelId'=>$id,'workId'=>sprintf('EXP%03d', $index + 1),'observedRevision'=>1,
        'mutation'=>'fill_only_if_stored_field_still_missing',
        'fields'=>[
            ['field'=>'description','before_public_projection'=>null,'proposed_value'=>"Проверенное описание отеля $id.",'decision'=>'proposed_not_applied'],
            ['field'=>'primaryImage','before_public_projection'=>null,'proposed_value'=>$image,'decision'=>'proposed_not_applied'],
            ['field'=>'images','before_public_projection'=>[],'proposed_value'=>[$image],'decision'=>'proposed_not_applied'],
        ],'guards_required_before_apply'=>['owner approval','fresh raw state','missing only','atomic provenance','canonical readback'],'runtime_admission'=>null];
}
$approved = official_envelope($manifest);
$validate = new ReflectionMethod(AnyTourProfileEnrichmentV1::class, 'officialApproved');
$valid = $validate->invoke(null, $approved);
official_need(array_keys($valid['targets']) === range(9001,9011), 'exact-eleven-own-ids-without-alias');
official_need($valid['targets'][9001]['sources'][0]['content_sha256'] === null, 'no-invented-html-hash');
$precise = $manifest; $precise['targets'][0]['sources'][0]['checked_at_utc'] = '2026-11-21T13:48:36.761327+00:00';
$preciseResult = $validate->invoke(null, official_envelope($precise));
official_need($preciseResult['targets'][9001]['sources'][0]['checked_at_utc'] === '2026-11-21T13:48:36.761327+00:00', 'exact-utc-microseconds-preserved');
$bad = $approved; $bad['manifestJson'] .= "\n";
official_expect(fn()=> $validate->invoke(null, $bad), 'ENVELOPE', 'actual-manifest-bytes-pinned');
$bad = $approved; $bad['anytourHotelIds'][10] = 9001;
official_expect(fn()=> $validate->invoke(null, $bad), 'IDS', 'duplicate-own-id');
$bad = $approved; $bad['actionsSha256'] = str_repeat('f', 64);
official_expect(fn()=> $validate->invoke(null, $bad), 'MANIFEST', 'independent-actions-pin');
$mutations = [
    'fourth-field'=>static function(array &$m): void { $m['proposed_actions'][0]['fields'][] = ['field'=>'detailsAvailable','before_public_projection'=>false,'proposed_value'=>true,'decision'=>'proposed_not_applied']; },
    'foreign-id'=>static function(array &$m): void { $m['proposed_actions'][0]['anytourHotelId'] = 9999; },
    'revision-two'=>static function(array &$m): void { $m['proposed_actions'][0]['observedRevision'] = 2; },
    'forbidden-field'=>static function(array &$m): void { $m['proposed_actions'][0]['fields'][0]['field'] = 'rating'; },
    'photo-without-bytes-hash'=>static function(array &$m): void { $m['targets'][0]['photos'][0]['sha256'] = null; },
    'unlinked-photo-source'=>static function(array &$m): void { $m['targets'][0]['photos'][0]['source_page_url'] = 'https://official.example/other'; },
    'gallery-without-evidence'=>static function(array &$m): void { $m['targets'][0]['photos'] = []; },
    'non-https-image'=>static function(array &$m): void { $m['proposed_actions'][0]['fields'][1]['proposed_value'] = 'http://official.example/p.jpg'; },
    'invalid-source-time'=>static function(array &$m): void { $m['targets'][0]['sources'][0]['checked_at_utc'] = '2026-02-31T12:00:00Z'; },
    'private-dossier-excluded'=>static function(array &$m): void { $m['private_facts'] = ['not runtime input']; },
];
foreach ($mutations as $label=>$mutate) {
    $changed = $manifest; $mutate($changed);
    official_expect(fn()=> $validate->invoke(null, official_envelope($changed)), 'ANYTOUR_PROFILE_OFFICIAL_', $label);
}
// Optional cross-repository projection check; the expected raw-file pin is supplied independently.
$actualPath = getenv('ANYTOUR_PROFILE_OFFICIAL_TEST_MANIFEST');
if (is_string($actualPath) && $actualPath !== '') {
    $actualJson = file_get_contents($actualPath);
    $actual = json_decode($actualJson, true, 64, JSON_THROW_ON_ERROR);
    $validate->invoke(null, ['manifestJson'=>$actualJson,'manifestSha256'=>(string)getenv('ANYTOUR_PROFILE_OFFICIAL_TEST_MANIFEST_SHA256'),
        'actionsSha256'=>'45e11557da29ecc652589e5cc89a6dcfa457b57e1a8936fe87d57d2ceb12edb4',
        'anytourHotelIds'=>[5371,6014,6202,5160,5794,4516,7218,12008,5598,10771,8689],
        'approvalRef'=>'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7063885208']);
    echo "ANYTOUR_PROFILE_OFFICIAL_ACTUAL_MANIFEST_OK\n";
}
echo "ANYTOUR_PROFILE_OFFICIAL_PURE_OK cases=16\n";
if (in_array('--self-test', $argv, true)) exit(0);

// Reuse the existing disposable fixture and its native legacy regression, then remove all legacy tables.
require __DIR__ . '/anytour-profile-enrichment-v1-test.php';
foreach (['tour_price_observations','catalog_hotel_details','catalog_hotels'] as $table) $db->exec("DROP TABLE `$table`");

class OfficialCommitFixturePDO extends PDO
{
    public string $commitFault = '';
    public function commit(): bool
    {
        $fault = $this->commitFault; $this->commitFault = '';
        if ($fault === 'before') throw new RuntimeException('fixture lost commit transport');
        $result = parent::commit();
        if ($fault === 'after') throw new RuntimeException('fixture lost commit acknowledgement');
        if ($fault === 'tamper') $this->exec('UPDATE anytour_hotels SET revision=revision+1 WHERE id=9011');
        return $result;
    }
}
$db = new OfficialCommitFixturePDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$reset = static function(array $overrides = []) use ($db): array {
    $db->exec('DELETE FROM anytour_hotel_sources'); $db->exec('DELETE FROM anytour_hotels');
    $insert = $db->prepare('INSERT INTO anytour_hotels(id,profile_json,profile_sha256,revision,is_active,created_at,updated_at)
        VALUES(?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $source = $db->prepare('INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $before = [];
    foreach (range(9001,9011) as $id) {
        $profile = ['name'=>"Hotel $id",'country'=>['name'=>'Страна'],'region'=>['name'=>'Регион'],
            'description'=>null,'primaryImage'=>null,'images'=>[],'detailsAvailable'=>false,'category'=>5,'rating'=>4.2,
            'coordinates'=>['latitude'=>27.9,'longitude'=>34.3], 'traits'=>['existing-own-trait'],
            'hotelInformation'=>['meals'=>['OWN MEAL'],'roomTypes'=>'OWN ROOM','infrastructure'=>['OWN INFRA'],'services'=>['OWN SERVICE']]];
        $profile = array_replace($profile, $overrides[$id] ?? []);
        $json = AnyTourProfileEnrichmentV1::json($profile);
        $insert->execute([$id,$json,hash('sha256', $json)]); $before[$id] = $profile;
        // Existing accepted source rows must survive; they are evidence, never an alias prerequisite.
        $sourceJson = AnyTourProfileEnrichmentV1::json(['canonical_hotel_id'=>$id,'seed'=>'fixture']);
        $source->execute(['canonical_fixture',(string)$id,$id,'fixture_seed',$sourceJson,hash('sha256', $sourceJson)]);
    }
    return $before;
};
$allRows = static fn(): array => $db->query('SELECT * FROM anytour_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$allSources = static fn(): array => $db->query('SELECT * FROM anytour_hotel_sources ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$writeProfile = static function(int $id, callable $change) use ($db): void {
    $query = $db->prepare('SELECT profile_json FROM anytour_hotels WHERE id=?'); $query->execute([$id]);
    $profile = json_decode($query->fetchColumn(), true, 512, JSON_THROW_ON_ERROR); $change($profile);
    $json = AnyTourProfileEnrichmentV1::json($profile);
    $db->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=? WHERE id=?')->execute([$json,hash('sha256',$json),$id]);
};
$owner = new AnyTourProfileEnrichmentV1($db);
$before = $reset(); $sourceBefore = $allSources(); $rowsBefore = $allRows();
$plan = $owner->planOfficial11($approved);
official_need($plan['status'] === 'prepared_read_only' && $plan['writes'] === 0 && count($plan['selected']) === 11, 'read-only-eleven-snapshots');
official_need($allRows() === $rowsBefore && $allSources() === $sourceBefore, 'plan-no-mutations');
$result = $owner->applyOfficial11('fixture-official11', $approved, $plan['planSha256']);
official_need($result['status'] === 'committed_verified' && $result['profilesSelected'] === 11 && $result['profilesUpdated'] === 11
    && $result['fieldsFilled'] === 33 && $result['profileWrites'] === 11 && $result['provenanceWrites'] === 11, 'atomic-eleven-thirty-three');
official_need($result['fieldCounts'] === ['description'=>11,'images'=>11,'primaryImage'=>11]
    && $result['legacyWrites'] === 0 && $result['mappingWrites'] === 0 && $result['supplierCalls'] === 0, 'exact-three-field-owner');
official_need($result['readbackVerified'] && $result['consumerReadbackVerified'] && count($result['readback']) === 11, 'independent-raw-and-catalogue-readback');
$public = (new AnyTourCanonicalCatalog($db))->read(range(9001,9011));
official_need($result['consumerReadbackSha256'] === hash('sha256', AnyTourProfileEnrichmentV1::json($public)), 'actual-consumer-digest');
foreach ($allRows() as $row) {
    $id = (int)$row['id']; $profile = json_decode($row['profile_json'], true, 512, JSON_THROW_ON_ERROR);
    official_need((int)$row['revision'] === 2 && $row['profile_sha256'] === hash('sha256', $row['profile_json']), 'revision-and-byte-integrity');
    official_need($profile['description'] === "Проверенное описание отеля $id."
        && $profile['primaryImage'] === "https://official.example/images/$id.jpg"
        && $profile['images'] === ["https://official.example/images/$id.jpg"], 'exact-approved-values');
    foreach (['description','primaryImage','images'] as $field) unset($profile[$field], $before[$id][$field]);
    official_need(AnyTourProfileEnrichmentV1::json($profile) === AnyTourProfileEnrichmentV1::json($before[$id])
        && $profile['detailsAvailable'] === false, 'all-other-content-identity-and-availability-preserved');
}
$newSources = $allSources();
official_need(array_values(array_filter($newSources, static fn(array $r): bool => $r['namespace'] === 'canonical_fixture')) === $sourceBefore, 'accepted-sources-byte-identical');
$provenance = array_values(array_filter($newSources, static fn(array $r): bool => $r['namespace'] === AnyTourProfileEnrichmentV1::OFFICIAL_NAMESPACE));
official_need(count($provenance) === 11, 'distinct-official-provenance');
$p = json_decode($provenance[0]['source_json'], true, 512, JSON_THROW_ON_ERROR);
official_need($p['source_kind'] === 'owner_reviewed_official_web' && $p['manifest_sha256'] === $approved['manifestSha256']
    && $p['proposed_actions_sha256'] === $approved['actionsSha256'] && $p['official_sources'][0]['content_sha256'] === null
    && !isset($p['accepted_local_hotel_id']) && !isset($p['source_raw_sha256']), 'no-fictional-tv-source-or-alias');
official_expect(fn()=> $owner->planOfficial11($approved), 'PROFILE_STATE', 'already-updated-revision-is-held');

$placeholder = 'https://static.tourvisor.ru/hotel_pics/reg-400/22.jpg';
$reset([
    9001=>['description'=>'OWN DESCRIPTION','primaryImage'=>'https://own.example/one.jpg','images'=>['https://own.example/one.jpg']],
    9002=>['primaryImage'=>$placeholder,'images'=>[$placeholder]],
    9003=>['description'=>false],
]);
$plan = $owner->planOfficial11($approved);
official_need(count($plan['selected']) === 11 && $plan['selected'][0]['patch'] === [], 'preserved-profile-still-in-snapshot');
$result = $owner->applyOfficial11('fixture-preserve', $approved, $plan['planSha256']);
official_need($result['profilesUpdated'] === 10 && $result['fieldsFilled'] === 27 && $result['fieldCounts'] === ['description'=>9,'images'=>9,'primaryImage'=>9], 'truthful-bounded-counts');
$byId = [];
foreach ($allRows() as $row) $byId[(int)$row['id']] = json_decode($row['profile_json'],true,512,JSON_THROW_ON_ERROR);
official_need($byId[9001]['description'] === 'OWN DESCRIPTION' && $byId[9002]['images'] === [$placeholder]
    && $byId[9002]['primaryImage'] === $placeholder && $byId[9003]['description'] === false, 'every-nonempty-raw-value-protected');
official_need($result['readback'][1]['imagesCount'] === 0 && !$result['readback'][1]['primaryImagePresent']
    && $result['readback'][1]['detailsAvailable'] === false, 'public-filter-and-unmodified-availability-reported-honestly');

$full = [];
foreach (range(9001,9011) as $id) $full[$id] = ['description'=>'Own','primaryImage'=>'https://own.example/a.jpg','images'=>['https://own.example/a.jpg']];
$reset($full); $rowsBefore = $allRows(); $sourceBefore = $allSources();
$plan = $owner->planOfficial11($approved); $result = $owner->applyOfficial11('fixture-preserve-all', $approved, $plan['planSha256']);
official_need($result['profilesSelected'] === 11 && $result['profilesUpdated'] === 0 && $result['fieldsFilled'] === 0
    && $result['provenanceWrites'] === 0 && $result['consumerReadbackVerified'] && $allRows() === $rowsBefore && $allSources() === $sourceBefore, 'all-preserved-verified-zero-write');

$drifts = [
    'revision'=>[static fn()=> $db->exec('UPDATE anytour_hotels SET revision=2 WHERE id=9011'), 'PROFILE_STATE'],
    'inactive'=>[static fn()=> $db->exec('UPDATE anytour_hotels SET is_active=0 WHERE id=9011'), 'PROFILE_STATE'],
    'identity'=>[static fn()=> $writeProfile(9011, static function(array &$p): void { $p['name'] = 'Different hotel'; }), 'IDENTITY'],
    'raw-integrity'=>[static fn()=> $db->exec("UPDATE anytour_hotels SET profile_sha256=REPEAT('f',64) WHERE id=9011"), 'PROFILE_INTEGRITY'],
    'new-own-content'=>[static fn()=> $writeProfile(9011, static function(array &$p): void { $p['description'] = 'A new own edit'; }), 'PLAN_DRIFT'],
    'source-snapshot'=>[static fn()=> $db->exec("UPDATE anytour_hotel_sources SET acquired_via='changed' WHERE anytour_hotel_id=9011"), 'PLAN_DRIFT'],
    'source-integrity'=>[static fn()=> $db->exec("UPDATE anytour_hotel_sources SET source_sha256=REPEAT('f',64) WHERE anytour_hotel_id=9011"), 'SOURCE_INTEGRITY'],
    'prior-content-operation'=>[static fn()=> $db->exec("UPDATE anytour_hotel_sources SET namespace='profile_sync:retained_tv_v1' WHERE anytour_hotel_id=9011"), 'PRIOR_CONTENT_OPERATION'],
];
foreach ($drifts as $label=>[$mutate,$error]) {
    $reset(); $plan = $owner->planOfficial11($approved); $mutate();
    $rowsBefore = $allRows(); $sourceBefore = $allSources();
    official_expect(fn()=> $owner->applyOfficial11('fixture-drift', $approved, $plan['planSha256']), $error, $label);
    official_need($allRows() === $rowsBefore && $allSources() === $sourceBefore && !$db->inTransaction(), 'whole-command-hold:' . $label);
}

$reset(); $plan = $owner->planOfficial11($approved); $rowsBefore = $allRows(); $sourceBefore = $allSources();
$db->exec("CREATE TRIGGER official_fixture_reject BEFORE INSERT ON anytour_hotel_sources FOR EACH ROW
    BEGIN IF NEW.namespace='profile_enrichment:official_review_v1' AND NEW.anytour_hotel_id=9011 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture late provenance rejection'; END IF; END");
try {
    official_expect(fn()=> $owner->applyOfficial11('fixture-rollback', $approved, $plan['planSha256']), 'fixture late provenance rejection', 'late-native-write-failure');
    official_need($allRows() === $rowsBefore && $allSources() === $sourceBefore, 'all-eleven-writes-rolled-back');
} finally { $db->exec('DROP TRIGGER official_fixture_reject'); }

foreach (['after','before','tamper'] as $fault) {
    $reset(); $plan = $owner->planOfficial11($approved); $db->commitFault = $fault;
    if ($fault === 'after') {
        $result = $owner->applyOfficial11('fixture-commit-ack', $approved, $plan['planSha256']);
        official_need($result['readbackVerified'] && $result['profilesUpdated'] === 11, 'lost-ack-resolved-by-readback-not-replay');
    } else {
        official_expect(fn()=> $owner->applyOfficial11('fixture-commit-fault', $approved, $plan['planSha256']),
            $fault === 'before' ? 'COMMIT_UNKNOWN' : 'POSTCOMMIT_READBACK', 'commit-or-readback-uncertainty:' . $fault);
    }
    $revisions = array_map(static fn(array $r): int => (int)$r['revision'], $allRows());
    official_need($revisions === ($fault === 'before' ? array_fill(0,11,1) : ($fault === 'tamper' ? [...array_fill(0,10,2),3] : array_fill(0,11,2))), 'never-automatically-replayed:' . $fault);
}
echo "ANYTOUR_PROFILE_OFFICIAL_MYSQL_OK selected=11 filled=33 aliases=0 legacy_tables=0 atomic_rollback=1 raw_and_public_readback=1 commit_ambiguity_no_replay=1\n";
