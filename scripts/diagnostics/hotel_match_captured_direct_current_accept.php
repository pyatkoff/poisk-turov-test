<?php
declare(strict_types=1);

const MATCH_DIRECT_OPERATION = 'hotel-match-captured-direct-current-accept-1971-20260914-v3';
const MATCH_DIRECT_POLICY = 'owner_exact_and_strong_20260908';
const MATCH_DIRECT_SOURCE_RUN = 34884599885;
const MATCH_DIRECT_SOURCE_SHA256 = 'd26d02edbdb8765083fa5462d22361bc893e06eac3ddfa1b9658c35827b53035';

function md_write_once(string $path, array $value): void {
    $raw = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $fh = @fopen($path, 'x');
    if ($fh === false) throw new RuntimeException('write_once_exists:' . basename($path));
    @chmod($path, 0600);
    try {
        if (fwrite($fh, $raw) !== strlen($raw) || !fflush($fh)) throw new RuntimeException('write_failed');
        if (function_exists('fsync') && !fsync($fh)) throw new RuntimeException('sync_failed');
    } finally { fclose($fh); }
}

function md_sig(string $value): array {
    $value = mb_strtolower($value, 'UTF-8');
    $value = strtr($value, ['ё'=>'е','&'=>' and ','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    preg_match_all('/[\p{L}\p{N}]+/u', $value, $m);
    $drop = ['hotel'=>1,'hotels'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'by'=>1];
    $tokens = [];
    foreach ($m[0] ?? [] as $token) if (!isset($drop[$token])) $tokens[$token] = true;
    $tokens = array_keys($tokens); sort($tokens, SORT_STRING); return $tokens;
}

function md_coverage(PDO $db): array {
    $anex = [];
    $q = $db->query("SELECT m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='" . MATCH_DIRECT_POLICY . "' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)");
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $anex[(int)$id] = true;
    $q = $db->query("SELECT d.catalog_hotel_id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)");
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $anex[(int)$id] = true;
    $andr = [];
    $q = $db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL");
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $andr[(int)$id] = true;
    $triple = count(array_intersect_key($anex, $andr));
    return [
        'anex_unique_local'=>count($anex),
        'andromeda_unique_local'=>count($andr),
        'all_three'=>$triple,
        'anex_tv_only'=>count($anex)-$triple,
        'andromeda_tv_only'=>count($andr)-$triple,
    ];
}

function md_table_info(PDO $db, string $table): ?array {
    $q = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]); $engine = $q->fetchColumn();
    if ($engine === false) return null;
    $q = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $q->execute([$table]);
    return ['engine'=>(string)$engine, 'columns'=>$q->fetchAll(PDO::FETCH_COLUMN)];
}

$operation = getenv('MATCH_OPERATION_ID') ?: '';
if ($operation !== MATCH_DIRECT_OPERATION) throw new RuntimeException('operation_contract');
$encoded = getenv('MATCH_EVIDENCE_B64') ?: '';
$decoded = base64_decode($encoded, true);
if ($decoded === false) throw new RuntimeException('evidence_b64');
$evidence = json_decode($decoded, true, 64, JSON_THROW_ON_ERROR);
if (!is_array($evidence) || count($evidence) !== 3) throw new RuntimeException('evidence_count');

$pairs = [];
foreach ($evidence as $row) {
    if (($row['tier'] ?? null) !== 'DIRECT' || ($row['reason'] ?? null) !== 'direct_identity_confirmed') throw new RuntimeException('non_direct_evidence');
    if (($row['operator_name'] ?? null) !== 'Anex' || !($row['operator_link_present'] ?? false)) throw new RuntimeException('operator_evidence');
    $aid = (int)($row['expected_anex_hotel_id'] ?? 0); $target = (int)($row['expected_tourvisor_hotel_id'] ?? 0);
    if ($aid !== (int)($row['operator_identity']['anex_hotel_id'] ?? 0) || $target !== (int)($row['detail_tourvisor_hotel_id'] ?? 0)) throw new RuntimeException('identity_mismatch');
    if (($row['operator_identity']['mode'] ?? null) !== 'legacy_hotellist' || ($row['semantic']['state'] ?? null) !== 'corroborated' || (float)($row['semantic']['ratio'] ?? 0) !== 1.0) throw new RuntimeException('semantic_evidence');
    if (($row['qualifier_conflict'] ?? true) || ($row['numeric_conflict'] ?? true)) throw new RuntimeException('evidence_conflict');
    $pairs[$aid] = ['target'=>$target,'detail'=>(string)$row['detail_hotel_name'],'source'=>(string)$row['source_name'],'search_count'=>(int)$row['live_search_count'],'evidence'=>$row];
}
ksort($pairs, SORT_NUMERIC);
if (array_keys($pairs) !== [1767,1768,4158] || array_map(fn($p)=>(int)$p['target'], $pairs) !== [182,183,37412]) throw new RuntimeException('pair_contract');

$root = realpath(getcwd());
if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('bad_root');
require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
$opDir = getenv('HOME') . '/.anytoour-match/operations/' . $operation;
if (!is_dir($opDir) || !is_file($opDir . '/reservation.json')) throw new RuntimeException('server_reservation_missing');

$db = v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET SESSION innodb_lock_wait_timeout=30'); $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$committed = false; $written = []; $skipped = []; $before = null; $after = null;
$evidenceHash = hash('sha256', json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$mappingDigest = hash('sha256', $operation . '|' . $evidenceHash);

try {
    $db->beginTransaction();
    $need = ['catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','anex_search_hotel_observations'];
    $marks = implode(',', array_fill(0, count($need), '?'));
    $q = $db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)"); $q->execute($need);
    $engines = $q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($need as $table) if (strtoupper((string)($engines[$table] ?? '')) !== 'INNODB') throw new RuntimeException('transactional_table_missing:' . $table);
    $review = md_table_info($db, 'anex_review_state');
    $hasReview = false;
    if ($review !== null) {
        if (strtoupper($review['engine']) !== 'INNODB') throw new RuntimeException('review_state_not_transactional');
        $hasReview = in_array('anex_hotel_id', $review['columns'], true);
    }
    $before = md_coverage($db);

    foreach ($pairs as $aid => $pair) {
        $target = (int)$pair['target']; $reason = null;
        $q = $db->prepare('SELECT id,country_id,name,is_active FROM catalog_hotels WHERE id=? FOR UPDATE'); $q->execute([$target]); $hotel = $q->fetch(PDO::FETCH_ASSOC);
        if (!$hotel || (int)$hotel['country_id'] !== 1 || (int)$hotel['is_active'] !== 1) $reason = 'target_missing_country_or_inactive';
        elseif (md_sig((string)$hotel['name']) !== md_sig($pair['detail'])) $reason = 'current_target_name_drift';

        if ($reason === null) {
            $q = $db->prepare('SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE'); $q->execute([$aid,$target]);
            $sameEnabled=false; $sameDisabled=false; $sourceOther=false; $targetOther=false;
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $mapping) {
                $ma=(int)$mapping['anex_hotel_id']; $mt=(int)$mapping['catalog_hotel_id']; $enabled=(int)$mapping['enabled'];
                if ($ma===$aid && $mt===$target) { if ($enabled===1) $sameEnabled=true; else $sameDisabled=true; }
                elseif ($ma===$aid) $sourceOther=true;
                elseif ($mt===$target) $targetOther=true;
            }
            if ($sameEnabled) $reason='already_same_mapping'; elseif ($sameDisabled) $reason='existing_same_mapping_disabled_protected'; elseif ($sourceOther) $reason='existing_source_mapping_protected'; elseif ($targetOther) $reason='same_provider_target_occupied';
        }
        if ($reason === null) {
            $q=$db->prepare('SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? FOR UPDATE');$q->execute([$aid]);if($q->fetch(PDO::FETCH_ASSOC))$reason='manual_or_conflict_protected';
        }
        if ($reason === null) {
            $q=$db->prepare("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id=? AND decision_status='accepted' AND anex_hotel_id<>? FOR UPDATE");$q->execute([$target,$aid]);if($q->fetch(PDO::FETCH_ASSOC))$reason='manual_target_occupied';
        }
        if ($reason === null && $hasReview) {
            $q=$db->prepare('SELECT anex_hotel_id FROM anex_review_state WHERE anex_hotel_id=? FOR UPDATE');$q->execute([$aid]);if($q->fetch(PDO::FETCH_ASSOC))$reason='review_state_protected';
        }
        if ($reason === null) {
            $q=$db->prepare('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? FOR UPDATE');$q->execute([$aid,$target]);if($q->fetch(PDO::FETCH_ASSOC))$reason='pair_exclusion_protected';
        }
        if ($reason === null) {
            $q=$db->prepare('SELECT country_id,search_count,last_seen_utc FROM anex_search_hotel_observations WHERE anex_hotel_id=? FOR UPDATE');$q->execute([$aid]);$obs=$q->fetch(PDO::FETCH_ASSOC);if(!$obs||(int)$obs['country_id']!==1||(int)$obs['search_count']<1)$reason='live_observation_drift';
        }
        if ($reason !== null) { $skipped[]=['anex_hotel_id'=>$aid,'target_local_hotel_id'=>$target,'reason'=>$reason]; continue; }

        $provenance=['operation_id'=>$operation,'rule'=>'tourvisor_operatorLink_HOTELLIST_direct','source_run_id'=>MATCH_DIRECT_SOURCE_RUN,'source_result_sha256'=>MATCH_DIRECT_SOURCE_SHA256,'anex_hotel_id'=>$aid,'target_local_hotel_id'=>$target,'tour_id'=>$pair['evidence']['tour_id'],'detail_hotel_name'=>$pair['detail'],'source_name'=>$pair['source'],'live_search_count'=>$pair['search_count'],'semantic'=>$pair['evidence']['semantic'],'qualifier_conflict'=>false,'numeric_conflict'=>false];
        $sourceDigest=hash('sha256',json_encode($provenance,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $insert=$db->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $insert->execute([$aid,$target,MATCH_DIRECT_POLICY,$sourceDigest,$mappingDigest]); if($insert->rowCount()!==1) throw new RuntimeException('insert_not_one');
        $written[$aid]=['target'=>$target,'source_digest'=>$sourceDigest,'search_count'=>$pair['search_count']];
    }

    $db->commit(); $committed=true;
    $readback=[]; $q=$db->prepare('SELECT catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');
    foreach($written as $aid=>$write){$q->execute([$aid]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1)throw new RuntimeException('post_commit_row_count');$r=$rows[0];if((int)$r['catalog_hotel_id']!==$write['target']||(string)$r['match_class']!=='strong_candidate'||(string)$r['scope']!=='preview'||(string)$r['approval_policy']!==MATCH_DIRECT_POLICY||(int)$r['enabled']!==1||!hash_equals($write['source_digest'],(string)$r['source_row_digest'])||!hash_equals($mappingDigest,(string)$r['mapping_digest']))throw new RuntimeException('post_commit_readback_failed');$readback[]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>(int)$r['catalog_hotel_id'],'enabled'=>(int)$r['enabled'],'source_row_digest'=>(string)$r['source_row_digest']];}
    $after=md_coverage($db);
    $targets=array_values(array_unique(array_map(fn($p)=>(int)$p['target'],array_values($pairs))));$marks=implode(',',array_fill(0,count($targets),'?'));
    $q=$db->prepare("SELECT external_hotel_id,local_hotel_id,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($marks) ORDER BY local_hotel_id,external_hotel_id");$q->execute($targets);$andromeda=$q->fetchAll(PDO::FETCH_ASSOC);
    $accepted=[];foreach($written as $aid=>$write)$accepted[]=['anex_hotel_id'=>(int)$aid,'target_local_hotel_id'=>$write['target'],'search_count'=>$write['search_count']];
    $result=['status'=>'completed','operation_id'=>$operation,'committed'=>true,'database_writes'=>count($written),'mapping_writes'=>count($written),'supplier_calls'=>0,'tourvisor_calls'=>0,'andromeda_calls'=>0,'accepted'=>$accepted,'skipped'=>$skipped,'post_commit_readback'=>$readback,'coverage_before'=>$before,'coverage_after'=>$after,'accepted_andromeda_on_targets'=>$andromeda,'no_replay'=>true];
    md_write_once($opDir.'/result.json',$result);$raw=file_get_contents($opDir.'/result.json');md_write_once($opDir.'/receipt.json',['operation_id'=>$operation,'state'=>'completed_committed','result_sha256'=>hash('sha256',$raw),'database_writes'=>count($written),'mapping_writes'=>count($written),'post_commit_readback_count'=>count($readback),'readback_verified'=>true,'no_replay'=>true]);
    echo json_encode(['status'=>'completed','writes'=>count($written),'skips'=>count($skipped)],JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) {
    if (!$committed && $db->inTransaction()) {
        $db->rollBack(); $result=['status'=>'failed_before_commit','operation_id'=>$operation,'reason'=>$e->getMessage(),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'andromeda_calls'=>0,'no_replay'=>true];
        md_write_once($opDir.'/result.json',$result);$raw=file_get_contents($opDir.'/result.json');md_write_once($opDir.'/receipt.json',['operation_id'=>$operation,'state'=>'failed_before_commit','result_sha256'=>hash('sha256',$raw),'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true,'no_replay'=>true]);
        fwrite(STDERR,$e->getMessage()."\n"); exit(2);
    }
    @md_write_once($opDir.'/unknown.json',['status'=>'unknown_after_commit','operation_id'=>$operation,'reason'=>$e->getMessage(),'database_writes'=>count($written),'mapping_writes'=>count($written),'committed'=>true,'no_replay'=>true]);
    fwrite(STDERR,$e->getMessage()."\n"); exit(3);
}
