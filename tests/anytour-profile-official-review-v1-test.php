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

// Schema 2 keeps the reviewed actions byte-for-byte while admitting exactly 25 full
// missing-field profiles and one description-only profile with an existing gallery.
$profiles26 = []; $manifest26 = $manifest;
$manifest26['schema_version'] = 2;
$manifest26['source_policy'] = AnyTourProfileEnrichmentV1::OFFICIAL26_POLICY;
$manifest26['proposed_actions'] = []; $manifest26['targets'] = [];
foreach (range(9001,9026) as $index=>$id) {
    $partial = $id === 9026;
    $page = "https://official.example/hotel/$id";
    $images = ["https://official.example/images/$id-main.jpg","https://official.example/images/$id-room.jpg"];
    $existingImages = array_map(static fn(int $n): string => "https://own.example/existing/$n.jpg", range(1,44));
    $profile = ['name'=>"Hotel $id",'country'=>['name'=>'Страна'],'region'=>['name'=>'Регион'],
        'description'=>null,'primaryImage'=>$partial ? $existingImages[0] : null,'images'=>$partial ? $existingImages : [],
        'detailsAvailable'=>false,'rating'=>4.2,'traits'=>['own-trait'],
        'hotelInformation'=>['meals'=>['OWN MEAL'],'roomTypes'=>'OWN ROOM']];
    $profiles26[$id] = $profile;
    $public = array_merge(AnyTourCanonicalCatalog::publicProfile($profile), ['id'=>$id,'catalog'=>'anytour','revision'=>1]);
    $fields = ['description'];
    $values = ['description'=>"Проверенное новое описание отеля $id."];
    if (!$partial) { $fields = ['description','primaryImage','images']; $values += ['primaryImage'=>$images[0],'images'=>$images]; }
    $changes = [];
    foreach ($fields as $field) $changes[] = ['field'=>$field,'before'=>$profile[$field],'after'=>$values[$field]];
    $manifest26['proposed_actions'][] = ['work_id'=>sprintf('EXP%03d',$index + 1),'anytour_hotel_id'=>$id,
        'canonical_name'=>$profile['name'],'observed_revision'=>1,
        'observed_public_profile_sha256'=>hash('sha256',AnyTourProfileEnrichmentV1::json($public)),
        'field_changes'=>$changes,'unchanged_profile_fields'=>array_values(array_diff(array_keys($public),$fields)),
        'preconditions'=>['fresh_profile_required'=>true,'revision_and_before_match_required'=>true,
            'raw_source_and_manual_override_guards_required'=>true,'atomic_existing_owner_write_required'=>true,
            'zero_automatic_replay_on_unknown'=>true]];
    $manifest26['targets'][] = ['anytourHotelId'=>$id,'expectedIdentity'=>['name'=>$profile['name'],'country'=>'Страна','region'=>'Регион'],
        'sources'=>[['url'=>$page,'checked_at_utc'=>'2026-11-22T10:00:00.123456+00:00',
            'content_sha256'=>hash('sha256',"captured source $id"),'capture_format'=>$id === 9001 ? 'retrieved_web_text_not_origin_html' : 'raw_html']],
        'photos'=>$partial ? [] : array_map(static fn(string $url): array => ['url'=>$url,'source_page_url'=>$page,'sha256'=>hash('sha256',$url)],$images)];
}
$approved26 = official_envelope($manifest26);
$valid26 = $validate->invoke(null,$approved26,true);
official_need(count($valid26['targets']) === 26 && $valid26['strictPublicBefore']
    && $valid26['sourcePolicy'] === AnyTourProfileEnrichmentV1::OFFICIAL26_POLICY, 'explicit-twenty-six-policy');
official_need(array_keys($valid26['targets'][9026]['values']) === ['description'] && $valid26['targets'][9026]['photos'] === [], 'partial-gallery-not-admitted');
official_need($valid26['targets'][9001]['sources'][0]['capture_format'] === 'retrieved_web_text_not_origin_html', 'web-text-capture-not-claimed-as-origin-html');
official_expect(fn()=> $validate->invoke(null,$approved26), 'ENVELOPE', 'eleven-does-not-admit-twenty-six');
official_expect(fn()=> $validate->invoke(null,$approved,true), 'ENVELOPE', 'twenty-six-does-not-admit-eleven');
$mutations26 = [
    'arbitrary-fourth-field'=>static function(array &$m): void { $m['proposed_actions'][0]['field_changes'][] = ['field'=>'rating','before'=>4.2,'after'=>5]; },
    'second-partial-profile'=>static function(array &$m): void { $m['proposed_actions'][0]['field_changes'] = [$m['proposed_actions'][0]['field_changes'][0]]; },
    'primary-only-partial'=>static function(array &$m): void { $m['proposed_actions'][0]['field_changes'] = [$m['proposed_actions'][0]['field_changes'][1]]; },
    'nonempty-before'=>static function(array &$m): void { $m['proposed_actions'][0]['field_changes'][0]['before'] = 'Old content'; },
    'invalid-public-before-sha'=>static function(array &$m): void { $m['proposed_actions'][0]['observed_public_profile_sha256'] = null; },
    'disabled-guard'=>static function(array &$m): void { $m['proposed_actions'][0]['preconditions']['zero_automatic_replay_on_unknown'] = false; },
    'changed-field-labelled-preserved'=>static function(array &$m): void { $m['proposed_actions'][0]['unchanged_profile_fields'][] = 'description'; },
    'partial-with-new-photo'=>static function(array &$m): void { $m['targets'][25]['photos'] = $m['targets'][0]['photos']; },
    'source-capture-unspecified'=>static function(array &$m): void { unset($m['targets'][0]['sources'][0]['capture_format']); },
    'source-capture-fabricated'=>static function(array &$m): void { $m['targets'][0]['sources'][0]['capture_format'] = 'assumed_html'; },
    'missing-captured-source-hash'=>static function(array &$m): void { $m['targets'][0]['sources'][0]['content_sha256'] = null; },
    'identity-disagrees-with-review'=>static function(array &$m): void { $m['targets'][0]['expectedIdentity']['name'] = 'Different hotel'; },
];
foreach ($mutations26 as $label=>$mutate) {
    $changed = $manifest26; $mutate($changed);
    official_expect(fn()=> $validate->invoke(null,official_envelope($changed),true), 'ANYTOUR_PROFILE_OFFICIAL', $label);
}
echo "ANYTOUR_PROFILE_OFFICIAL26_PURE_OK exact_profiles=26 exact_fields=76 gallery_preserved=44\n";
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

$reset26 = static function(array $overrides = []) use ($db,$profiles26): void {
    $db->exec('DELETE FROM anytour_hotel_sources'); $db->exec('DELETE FROM anytour_hotels');
    $insert = $db->prepare('INSERT INTO anytour_hotels(id,profile_json,profile_sha256,revision,is_active,created_at,updated_at)
        VALUES(?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    $source = $db->prepare('INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at)
        VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
    foreach ($profiles26 as $id=>$profile) {
        $profile = array_replace($profile,$overrides[$id] ?? []); $json = AnyTourProfileEnrichmentV1::json($profile);
        $insert->execute([$id,$json,hash('sha256',$json)]);
        $sourceJson = AnyTourProfileEnrichmentV1::json(['canonical_hotel_id'=>$id,'seed'=>'fixture26']);
        $source->execute(['canonical_fixture',(string)$id,$id,'fixture_seed',$sourceJson,hash('sha256',$sourceJson)]);
    }
};
$reset26(); $rowsBefore = $allRows(); $sourcesBefore = $allSources();
$plan26 = $owner->planOfficial26($approved26);
official_need($plan26['writes'] === 0 && count($plan26['selected']) === 26
    && $rowsBefore === $allRows() && $sourcesBefore === $allSources(), 'mixed-plan-read-only');
official_need(array_keys($plan26['selected'][25]['patch']) === ['description']
    && $plan26['selected'][25]['preservedFields'] === ['images','primaryImage'], 'only-reviewed-partial-field-patched');
$result26 = $owner->applyOfficial26('fixture-official26',$approved26,$plan26['planSha256']);
official_need($result26['status'] === 'committed_verified' && $result26['profilesSelected'] === 26
    && $result26['profilesUpdated'] === 26 && $result26['fieldsFilled'] === 76
    && $result26['fieldCounts'] === ['description'=>26,'images'=>25,'primaryImage'=>25]
    && $result26['profileWrites'] === 26 && $result26['provenanceWrites'] === 26
    && $result26['readbackVerified'] && $result26['consumerReadbackVerified'], 'exact-mixed-batch-and-readback');
foreach ($allRows() as $row) {
    $id = (int)$row['id']; $profile = json_decode($row['profile_json'],true,512,JSON_THROW_ON_ERROR);
    $expected = $profiles26[$id];
    foreach ($valid26['targets'][$id]['values'] as $field=>$value) $expected[$field] = $value;
    official_need(AnyTourProfileEnrichmentV1::json($profile) === AnyTourProfileEnrichmentV1::json($expected), 'all-target-and-untargeted-fields-exact');
    official_need((int)$row['revision'] === 2 && $row['profile_sha256'] === hash('sha256',$row['profile_json']), 'mixed-revision-and-integrity');
    if ($id === 9026) official_need($profile['images'] === $profiles26[$id]['images'] && count($profile['images']) === 44
        && $profile['primaryImage'] === $profiles26[$id]['primaryImage'], 'existing-forty-four-gallery-entries-exact');
}
$new26Sources = $allSources();
official_need(array_values(array_filter($new26Sources,static fn(array $r): bool => $r['namespace'] === 'canonical_fixture')) === $sourcesBefore,
    'mixed-existing-source-rows-preserved');
$new26Provenance = array_values(array_filter($new26Sources,static fn(array $r): bool => $r['namespace'] === AnyTourProfileEnrichmentV1::OFFICIAL_NAMESPACE));
$partialProvenance = json_decode($new26Provenance[25]['source_json'],true,512,JSON_THROW_ON_ERROR);
official_need($partialProvenance['fields_filled'] === ['description'] && $partialProvenance['verified_photos'] === []
    && $partialProvenance['content_policy'] === AnyTourProfileEnrichmentV1::OFFICIAL26_POLICY, 'partial-provenance-exact');
official_expect(fn()=> $owner->planOfficial26($approved26), 'PROFILE_STATE', 'mixed-batch-not-replayed');

// Publicly hidden regional placeholders remain nonempty raw fields and cannot be overwritten.
$reset26([9001=>['primaryImage'=>$placeholder,'images'=>[$placeholder]]]);
$rowsBefore = $allRows(); $sourcesBefore = $allSources();
official_expect(fn()=> $owner->planOfficial26($approved26), 'RAW_NOT_MISSING', 'mixed-hidden-raw-value-protected');
official_need($rowsBefore === $allRows() && $sourcesBefore === $allSources(), 'mixed-raw-hold-no-writes');
$reset26([9026=>['images'=>array_reverse($profiles26[9026]['images'])]]);
official_expect(fn()=> $owner->planOfficial26($approved26), 'PUBLIC_BEFORE', 'unchanged-gallery-order-still-bound-to-approval');
$changed = $manifest26; array_pop($changed['proposed_actions'][0]['unchanged_profile_fields']);
$reset26();
official_expect(fn()=> $owner->planOfficial26(official_envelope($changed)), 'PRESERVED_FIELDS', 'preservation-list-is-complete');

$mixedDrifts = [
    'manual-profile-edit'=>[static fn()=> $writeProfile(9026,static function(array &$p): void { $p['description'] = 'Manual edit'; }), 'PUBLIC_BEFORE'],
    'source-row-change'=>[static fn()=> $db->exec("UPDATE anytour_hotel_sources SET acquired_via='manual_review' WHERE anytour_hotel_id=9026"), 'PLAN_DRIFT'],
    'provenance-conflict'=>[static fn()=> $db->exec("UPDATE anytour_hotel_sources SET namespace='profile_enrichment:official_review_v1' WHERE anytour_hotel_id=9026"), 'PRIOR_CONTENT_OPERATION'],
];
foreach ($mixedDrifts as $label=>[$mutate,$error]) {
    $reset26(); $plan26 = $owner->planOfficial26($approved26); $mutate();
    $rowsBefore = $allRows(); $sourcesBefore = $allSources();
    official_expect(fn()=> $owner->applyOfficial26('fixture-mixed-drift',$approved26,$plan26['planSha256']),$error,$label);
    official_need($rowsBefore === $allRows() && $sourcesBefore === $allSources() && !$db->inTransaction(), 'mixed-whole-command-hold:'.$label);
}
$reset26(); $plan26 = $owner->planOfficial26($approved26); $rowsBefore = $allRows(); $sourcesBefore = $allSources();
$db->exec("CREATE TRIGGER official_fixture_reject26 BEFORE INSERT ON anytour_hotel_sources FOR EACH ROW
    BEGIN IF NEW.namespace='profile_enrichment:official_review_v1' AND NEW.anytour_hotel_id=9026 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture mixed late provenance rejection'; END IF; END");
try {
    official_expect(fn()=> $owner->applyOfficial26('fixture-mixed-rollback',$approved26,$plan26['planSha256']),
        'fixture mixed late provenance rejection','last-mixed-profile-write-failure');
    official_need($rowsBefore === $allRows() && $sourcesBefore === $allSources(), 'all-twenty-six-writes-rolled-back');
} finally { $db->exec('DROP TRIGGER official_fixture_reject26'); }
$reset26(); $plan26 = $owner->planOfficial26($approved26); $db->commitFault = 'after';
$result26 = $owner->applyOfficial26('fixture-mixed-commit-ack',$approved26,$plan26['planSha256']);
official_need($result26['consumerReadbackVerified'] && $result26['fieldsFilled'] === 76, 'mixed-lost-ack-readback-no-replay');
echo "ANYTOUR_PROFILE_OFFICIAL26_MYSQL_OK selected=26 filled=76 preserved_gallery=44 atomic_rollback=1 raw_and_public_readback=1 commit_ambiguity_no_replay=1\n";
