<?php
declare(strict_types=1);

// Fixed one-file MATCH preview publication; no supplier calls or database writes.
const MPP_OP = 'hotel-match-policy-preview-publish-1971-20260912-v1';
const MPP_PRIOR_OP = 'hotel-match-approved-policy-current-1971-20260912-v1';
const MPP_PRIOR_HASH = 'cb7068658b5bef03e47d7beb32789c719bd1f95f004165bfe9c5f5cc98db1284';
const MPP_OLD_HASH = '07e61cd95256a9ab1650bdbc3b7758d9d76c8e8fe7d20fa735c2d792d92324e8';
const MPP_NEW_HASH = '6fd83f389dd6d239230f88e50fc1f1376fb81d8efb71acc97fe352f4612e8c72';
const MPP_SOURCE = '65feecaf9c858b8675ab8b14e25354df59622c29';
const MPP_TARGET = '/_preview/search3-anex-candidate/app/integrations/anex-search-mapping-registry.php';

function mpp_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}
function mpp_write(string $path, string $raw, int $mode = 0600): void {
    $handle = fopen($path, 'x');
    if ($handle === false) throw new RuntimeException('exclusive_write_failed');
    try {
        if (!chmod($path, $mode)) throw new RuntimeException('file_mode_failed');
        $offset = 0;
        while ($offset < strlen($raw)) {
            $written = fwrite($handle, substr($raw, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('file_write_failed');
            $offset += $written;
        }
        if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) throw new RuntimeException('file_sync_failed');
    } finally { fclose($handle); }
    if (file_get_contents($path) !== $raw) throw new RuntimeException('file_readback_failed');
}
function mpp_hash(string $path): string {
    clearstatcache(true, $path);
    $stat = lstat($path);
    if (!is_file($path) || is_link($path) || !$stat || $stat['nlink'] !== 1 || $stat['size'] > 2000000) throw new RuntimeException('file_guard');
    $hash = hash_file('sha256', $path);
    if (!is_string($hash)) throw new RuntimeException('file_hash_failed');
    return $hash;
}
function mpp_select(PDO $db, string $sql): array {
    if (!str_starts_with($sql, 'SELECT ') || str_contains($sql, ';')) throw new RuntimeException('select_only');
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 50000) throw new RuntimeException('row_cap');
    return $rows;
}
function mpp_same_row(array $current, array $expected): bool {
    foreach (['anex_hotel_id','catalog_hotel_id','enabled','scope','approval_policy','match_class','mapping_digest','source_row_digest','existing_catalog_hotel_id','is_active'] as $key) {
        if (!isset($current[$key], $expected[$key]) || (string)$current[$key] !== (string)$expected[$key]) return false;
    }
    return true;
}
function mpp_replace(string $target, string $temporary, string $expectedHash): void {
    if (mpp_hash($target) !== $expectedHash) throw new RuntimeException('target_drift');
    if (!rename($temporary, $target)) throw new RuntimeException('atomic_replace_failed');
}
if (in_array('--self-test', $_SERVER['argv'] ?? [], true)) {
    $root = sys_get_temp_dir() . '/match-publish-fixture-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700)) throw new RuntimeException('fixture_failed');
    $checks = 0;
    set_error_handler(static function() { throw new RuntimeException('synthetic_io_error'); });
    try {
        mpp_write($root.'/target.php', "<?php // original\n", 0644);
        $old = mpp_hash($root.'/target.php');
        mpp_write($root.'/next.php', "<?php // candidate\n", 0644);
        try { mpp_replace($root.'/target.php', $root.'/next.php', str_repeat('0',64)); throw new LogicException('drift accepted'); }
        catch (RuntimeException $e) { if ($e->getMessage() !== 'target_drift') throw $e; $checks++; }
        if (mpp_hash($root.'/target.php') !== $old || !is_file($root.'/next.php')) throw new LogicException('drift changed target'); $checks++;
        mpp_replace($root.'/target.php',$root.'/next.php',$old);
        if (file_get_contents($root.'/target.php') !== "<?php // candidate\n") throw new LogicException('replace failed'); $checks++;
        if ((fileperms($root.'/target.php') & 0777) !== 0644) throw new LogicException('mode changed'); $checks++;
        try { mpp_write($root.'/target.php','overwrite'); throw new LogicException('overwrite accepted'); }
        catch (RuntimeException $e) { $checks++; }
        mpp_write($root.'/rollback.php', "<?php // original\n",0644);
        mpp_replace($root.'/target.php',$root.'/rollback.php',hash('sha256',"<?php // candidate\n"));
        if (mpp_hash($root.'/target.php') !== $old) throw new LogicException('restore failed'); $checks++;
        symlink($root.'/target.php',$root.'/link.php');
        try { mpp_hash($root.'/link.php'); throw new LogicException('symlink accepted'); }
        catch (RuntimeException $e) { $checks++; }
        $row = array_fill_keys(['anex_hotel_id','catalog_hotel_id','enabled','scope','approval_policy','match_class','mapping_digest','source_row_digest','existing_catalog_hotel_id','is_active'],'1');
        if (!mpp_same_row($row,$row)) throw new LogicException('identical rejected'); $checks++;
        foreach (array_keys($row) as $key) { $changed=$row; $changed[$key]='2'; if (mpp_same_row($changed,$row)) throw new LogicException('row drift accepted'); $checks++; }
        echo "MPP publisher self-test PASS $checks\n";
    } finally {
        restore_error_handler();
        foreach (glob($root.'/*') ?: [] as $path) unlink($path);
        rmdir($root);
    }
    exit(0);
}

error_reporting(0); ob_start(); umask(0077);
$db=null; $transaction=false; $dir=null; $target=null; $stage='preflight'; $published=false; $mode=0600; $oldRaw='';
try {
    if (PHP_SAPI !== 'cli' || !defined('MPP_CANDIDATE_BASE64') || !class_exists('AnyTourAnexSearchMappingRegistryCandidate1971',false)
        || class_exists('AnyTourAnexSearchMappingRegistry',false)) throw new RuntimeException('bundled_cli_required');
    $source = (string)getenv('MATCH_SOURCE_SHA');
    if (!preg_match('/^[a-f0-9]{40}$/D',$source)) throw new RuntimeException('source_sha_required');
    $home=realpath((string)getenv('HOME')); $root=realpath(getcwd());
    if (!$home || !$root || $root !== $home.'/www/anytoour.ru' || is_link(getcwd())) throw new RuntimeException('root_guard');
    $base=$home.'/.anytoour-match/operations'; $target=$root.MPP_TARGET;
    if (realpath($base) !== $base || realpath(dirname($target)) !== dirname($target)) throw new RuntimeException('canonical_path_required');
    $priorPath=$base.'/'.MPP_PRIOR_OP.'/result.json';
    if (mpp_hash($priorPath) !== MPP_PRIOR_HASH || mpp_hash($target) !== MPP_OLD_HASH) throw new RuntimeException('preflight_hash_drift');
    $prior=json_decode(file_get_contents($priorPath),true,64,JSON_THROW_ON_ERROR);
    if (($prior['status']??null)!=='read_only_complete' || ($prior['operation_id']??null)!==MPP_PRIOR_OP
        || ($prior['existing_identity_preserved']??null)!==true || ($prior['newly_visible_count']??null)!==73
        || ($prior['candidate_sha256']??null)!==MPP_NEW_HASH || ($prior['deployed_reader_relative_path']??null)!==MPP_TARGET) throw new RuntimeException('prior_proof_invalid');
    $newRaw=base64_decode(MPP_CANDIDATE_BASE64,true);
    if (!is_string($newRaw) || hash('sha256',$newRaw)!==MPP_NEW_HASH) throw new RuntimeException('candidate_hash_drift');
    $next=$base.'/'.MPP_OP;
    if (file_exists($next) || !mkdir($next,0700)) throw new RuntimeException('operation_exists_no_replay');
    $dir=$next;
    mpp_write($dir.'/reservation.json',mpp_json(['operation_id'=>MPP_OP,'source_sha'=>$source,'candidate_source_sha'=>MPP_SOURCE,
        'target'=>MPP_TARGET,'old_sha256'=>MPP_OLD_HASH,'new_sha256'=>MPP_NEW_HASH,'state'=>'reserved_before_db_access','no_replay'=>true]));
    $oldRaw=file_get_contents($target); $mode=fileperms($target)&0777;
    if (!is_string($oldRaw) || hash('sha256',$oldRaw)!==MPP_OLD_HASH || ($mode&0002)!==0) throw new RuntimeException('backup_source_drift');
    mpp_write($dir.'/original.php',$oldRaw);
    $stage='current_guards';
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY'); $transaction=true;
    $rows=mpp_select($db,'SELECT m.anex_hotel_id,m.catalog_hotel_id,m.enabled,m.scope,m.approval_policy,m.match_class,m.mapping_digest,m.source_row_digest,h.id AS existing_catalog_hotel_id,h.is_active FROM anex_hotel_search_mappings m LEFT JOIN catalog_hotels h ON h.id=m.catalog_hotel_id ORDER BY m.anex_hotel_id LIMIT 50001');
    $decisions=mpp_select($db,'SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id LIMIT 50001');
    $exclusions=mpp_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 50001');
    if (count($rows)!==$prior['mapping_rows'] || hash('sha256',mpp_json($decisions))!==$prior['manual_sha256']
        || hash('sha256',mpp_json($exclusions))!==$prior['exclusions_sha256']) throw new RuntimeException('current_registry_drift');
    $index=[]; foreach ($rows as $row) $index[(string)$row['anex_hotel_id']]=$row;
    $candidate=AnyTourAnexSearchMappingRegistryCandidate1971::fromPdo($db);
    if ($candidate->count()!==$prior['candidate_resolved_count']) throw new RuntimeException('current_resolution_count_drift');
    $expected=$prior['comparison']['newly_visible_existing_rows'];
    foreach ($expected as $row) {
        $id=(string)$row['anex_hotel_id'];
        if (!mpp_same_row($index[$id]??[],$row) || $candidate->resolve('anex_online',$id,'preview')!==$row['after']) throw new RuntimeException('current_delta_drift');
    }
    $projection=[];
    foreach (array_merge($rows,$decisions) as $row) {
        $id=(string)$row['anex_hotel_id']; $projection[$id]=$candidate->resolve('anex_online',$id,'preview');
    }
    mpp_write($dir.'/intent.json',mpp_json(['operation_id'=>MPP_OP,'source_sha'=>$source,'target'=>MPP_TARGET,
        'old_sha256'=>MPP_OLD_HASH,'new_sha256'=>MPP_NEW_HASH,'prior_result_sha256'=>MPP_PRIOR_HASH,
        'delta_ids'=>array_column($expected,'anex_hotel_id'),'manual_sha256'=>$prior['manual_sha256'],
        'exclusions_sha256'=>$prior['exclusions_sha256'],'current_guarded'=>true,'database_writes'=>0,'no_replay'=>true]));
    if (class_exists('AnyTourAnexSearchMappingRegistry',false)) throw new RuntimeException('reader_loaded_before_publication');
    $stage='atomic_publication';
    $temp=dirname($target).'/.match-policy-preview-publish-1971-v1-next.php';
    mpp_write($temp,$newRaw,$mode);
    mpp_replace($target,$temp,MPP_OLD_HASH); $published=true;
    if (mpp_hash($target)!==MPP_NEW_HASH) throw new RuntimeException('published_hash_mismatch');
    $stage='installed_reader_readback';
    require_once $target;
    if (realpath((new ReflectionClass('AnyTourAnexSearchMappingRegistry'))->getFileName())!==$target) throw new RuntimeException('installed_reader_path_mismatch');
    $installed=AnyTourAnexSearchMappingRegistry::fromPdo($db);
    if ($installed->count()!==$candidate->count()) throw new RuntimeException('installed_count_mismatch');
    foreach ($projection as $id=>$local) {
        if ($installed->resolve('anex_online',(string)$id,'preview')!==$local) throw new RuntimeException('installed_projection_mismatch');
    }
    $readback=[];
    foreach ($expected as $row) $readback[]=['anex_hotel_id'=>$row['anex_hotel_id'],'local_id'=>$installed->resolve('anex_online',(string)$row['anex_hotel_id'],'preview'),'mapping_digest'=>$row['mapping_digest']];
    $cohort=[]; $nulls=0;
    foreach ($prior['cohort'] as $row) {
        $local=$installed->resolve('anex_online',(string)$row['anex_hotel_id'],'preview');
        if ($local!==$row['after']) throw new RuntimeException('cohort_drift');
        $cohort[]=['anex_hotel_id'=>$row['anex_hotel_id'],'local_id'=>$local]; $nulls+=(int)($local===null);
    }
    $db->exec('ROLLBACK'); $transaction=false; $db=null;
    if (mpp_hash($target)!==MPP_NEW_HASH) throw new RuntimeException('post_readback_target_drift');
    $out=['operation_id'=>MPP_OP,'status'=>'published_and_verified','source_sha'=>$source,'candidate_source_sha'=>MPP_SOURCE,
        'target'=>MPP_TARGET,'previous_sha256'=>MPP_OLD_HASH,'installed_sha256'=>MPP_NEW_HASH,'generated_at_utc'=>gmdate('c'),
        'resolved_count'=>$installed->count(),'restored_existing_count'=>count($readback),'restored_rows'=>$readback,
        'all_projection_readback_count'=>count($projection),'cohort_null_count'=>$nulls,'cohort'=>$cohort,
        'manual_decisions_count'=>count($decisions),'manual_sha256'=>$prior['manual_sha256'],'exclusions_sha256'=>$prior['exclusions_sha256'],
        'existing_identity_preserved'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
        'preview_published'=>true,'production_changed'=>false,'whole_site_deployed'=>false,'browser_verified'=>false,'no_replay'=>true];
    $stage='receipt'; $raw=mpp_json($out);
    mpp_write($dir.'/result.json',$raw);
    mpp_write($dir.'/receipt.json',mpp_json(['operation_id'=>MPP_OP,'state'=>'published_and_verified','result_sha256'=>hash('sha256',$raw),'readback_verified'=>true,'no_replay'=>true]));
    ob_end_clean(); echo 'MPP_JSON:'.$raw; exit(0);
} catch (Throwable $error) {
    if ($db && $transaction) { try {$db->exec('ROLLBACK');} catch (Throwable $ignored) {} }
    $rolledBack=false;
    if ($published && $target && $dir && $oldRaw!=='') {
        try {
            // Never overwrite a different writer's bytes after an ambiguous failure.
            if (mpp_hash($target)!==MPP_NEW_HASH || hash('sha256',$oldRaw)!==MPP_OLD_HASH) throw new RuntimeException('rollback_drift');
            $rollback=dirname($target).'/.match-policy-preview-publish-1971-v1-rollback.php';
            mpp_write($rollback,$oldRaw,$mode); mpp_replace($target,$rollback,MPP_NEW_HASH);
            $rolledBack=mpp_hash($target)===MPP_OLD_HASH;
        } catch (Throwable $ignored) {}
    }
    $out=['operation_id'=>MPP_OP,'status'=>$rolledBack?'rolled_back':($published?'publication_unknown_readback_required':'stopped_before_publication'),
        'stage'=>$stage,'error_class'=>get_class($error),'reason'=>in_array($error->getMessage(),['bundled_cli_required','source_sha_required','root_guard','canonical_path_required','preflight_hash_drift','prior_proof_invalid','candidate_hash_drift','operation_exists_no_replay','backup_source_drift','current_registry_drift','current_resolution_count_drift','current_delta_drift','target_drift','atomic_replace_failed','published_hash_mismatch','installed_reader_path_mismatch','reader_loaded_before_publication','installed_count_mismatch','installed_projection_mismatch','cohort_drift','post_readback_target_drift','exclusive_write_failed','file_mode_failed','file_write_failed','file_sync_failed','file_readback_failed','file_guard','file_hash_failed','select_only','row_cap'],true)?$error->getMessage():'guard_failed',
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'preview_published'=>$published&&!$rolledBack,
        'rollback_verified'=>$rolledBack,'no_replay'=>true];
    if ($dir) {try {mpp_write($dir.'/failure.json',mpp_json($out));} catch (Throwable $ignored) {}}
    ob_end_clean(); echo 'MPP_JSON:'.mpp_json($out); exit(2);
}
