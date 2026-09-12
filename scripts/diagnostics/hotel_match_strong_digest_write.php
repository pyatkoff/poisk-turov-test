<?php
declare(strict_types=1);
/** MATCH #1971 — transactional writer for the strict 11-row CURRENT-safe cohort. */
const HMSDW_OPERATION='hotel-match-strong-digest-write-1971-20260912-v1';
const HMSDW_POLICY='owner_exact_and_strong_20260908';
const HMSDW_CLASS='strong_candidate';
const HMSDW_SCOPE='preview';
const HMSDW_AUDIT_SHA='025c635bb0d425be79b7e89ce73bf828296efaf010510183ec5f5e04dfa03e82';

function hmsdw_manifest(string $path): array {
    $m=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if(($m['schema']??'')!=='hotel-match-strong-digest-write/1'||($m['expected_count']??0)!==11||count($m['pairs']??[])!==11) throw new RuntimeException('manifest_guard');
    if(($m['source_audit_result_sha256']??'')!==HMSDW_AUDIT_SHA||($m['approval_policy']??'')!==HMSDW_POLICY||($m['match_class']??'')!==HMSDW_CLASS||($m['scope']??'')!==HMSDW_SCOPE) throw new RuntimeException('manifest_guard');
    $seen=[];foreach($m['pairs'] as $p){if(($p['provider']??'')!=='anex')throw new RuntimeException('provider_guard');$id=(int)($p['external_hotel_id']??0);$target=(int)($p['catalog_hotel_id']??0);if($id<=0||$target<=0||isset($seen[$id]))throw new RuntimeException('manifest_pair_guard');$seen[$id]=true;foreach(['source_digest','target_digest'] as $k)if(!preg_match('/\A[a-f0-9]{64}\z/',(string)($p[$k]??'')))throw new RuntimeException('manifest_digest_guard');}
    return $m;
}
function hmsdw_pair_key(array $p): string {return (string)$p['provider'].':'.(string)$p['external_hotel_id'].'>'.(int)$p['catalog_hotel_id'];}
function hmsdw_safe_index(array $rows): array {$out=[];foreach($rows as $r){$k=hmsdw_pair_key(['provider'=>$r['provider'],'external_hotel_id'=>$r['external_hotel_id'],'catalog_hotel_id'=>$r['proposed_local_id']]);$out[$k]=['source_digest'=>(string)$r['source_digest'],'target_digest'=>(string)$r['target_digest']];}ksort($out);return $out;}
function hmsdw_manifest_index(array $m): array {$out=[];foreach($m['pairs'] as $r){$k=hmsdw_pair_key($r);$out[$k]=['source_digest'=>(string)$r['source_digest'],'target_digest'=>(string)$r['target_digest']];}ksort($out);return $out;}
function hmsdw_require_innodb(PDO $pdo,array $tables): void {$q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');foreach($tables as $t){$q->execute([$t]);$e=$q->fetchColumn();if($e===false)throw new RuntimeException('required_table_missing');if(strcasecmp((string)$e,'InnoDB')!==0)throw new RuntimeException('required_transactional_table_missing');}}
function hmsdw_mapping_digest(array $manifest): string {return hash('sha256',json_encode(['operation'=>HMSDW_OPERATION,'audit_sha'=>HMSDW_AUDIT_SHA,'manifest'=>hmsdw_manifest_index($manifest)],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
function hmsdw_evidence_digest(array $pair): string {return hash('sha256',json_encode(['operation_id'=>HMSDW_OPERATION,'lane'=>'MATCH','rule'=>'current_strong_unique_digest_guard','source_audit_result_sha256'=>HMSDW_AUDIT_SHA,'external_hotel_id'=>(int)$pair['external_hotel_id'],'catalog_hotel_id'=>(int)$pair['catalog_hotel_id'],'source_digest'=>(string)$pair['source_digest'],'target_digest'=>(string)$pair['target_digest'],'matched_keys'=>$pair['matched_keys']??[],'country_authority'=>$pair['country_authority']??[],'server_current_revalidated'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}

function hmsdw_run(PDO $pdo,array $manifest,array $auditManifest): array {
    hmsdw_require_innodb($pdo,['catalog_hotels','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','anex_hotels','anex_search_hotel_observations']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('SET SESSION innodb_lock_wait_timeout=20');$pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    $committed=false;$writes=0;$accepted=[];$mappingDigest=hmsdw_mapping_digest($manifest);
    try{
        $pdo->beginTransaction();
        $audit=hmpw_run($pdo,$auditManifest);
        if(($audit['status']??'')!=='read_only_complete'||($audit['safe_count']??0)!==11)throw new RuntimeException('current_safe_set_changed');
        if(hmsdw_safe_index($audit['safe']??[])!==hmsdw_manifest_index($manifest))throw new RuntimeException('current_digest_set_changed');
        $ids=array_map(static fn($p)=>(int)$p['external_hotel_id'],$manifest['pairs']);
        $targets=array_values(array_unique(array_map(static fn($p)=>(int)$p['catalog_hotel_id'],$manifest['pairs'])));
        foreach([['catalog_hotels','id',$targets],['anex_hotels','anex_hotel_id',$ids],['anex_search_hotel_observations','anex_hotel_id',$ids],['anex_hotel_decisions','anex_hotel_id',$ids],['anex_hotel_search_mappings','anex_hotel_id',$ids],['anex_review_pair_exclusions','anex_hotel_id',$ids]] as [$table,$col,$vals]){if(!$vals)continue;$q=$pdo->prepare('SELECT `'.$col.'` FROM `'.$table.'` WHERE `'.$col.'` IN ('.implode(',',array_fill(0,count($vals),'?')).') FOR UPDATE');$q->execute($vals);$q->fetchAll(PDO::FETCH_COLUMN);}
        $auditLocked=hmpw_run($pdo,$auditManifest);
        if(($auditLocked['safe_count']??0)!==11||hmsdw_safe_index($auditLocked['safe']??[])!==hmsdw_manifest_index($manifest))throw new RuntimeException('locked_digest_set_changed');
        $insert=$pdo->prepare("INSERT INTO anex_hotel_search_mappings (anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,?,?,?,?,?,1)");
        foreach($manifest['pairs'] as $p){$sourceDigest=hmsdw_evidence_digest($p);$insert->execute([(int)$p['external_hotel_id'],(int)$p['catalog_hotel_id'],HMSDW_CLASS,HMSDW_SCOPE,HMSDW_POLICY,$sourceDigest,$mappingDigest]);if($insert->rowCount()!==1)throw new RuntimeException('insert_not_one');$writes++;$accepted[]=['external_hotel_id'=>(int)$p['external_hotel_id'],'catalog_hotel_id'=>(int)$p['catalog_hotel_id'],'source_row_digest'=>$sourceDigest];}
        $pdo->commit();$committed=true;
        $read=$pdo->prepare('SELECT catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');
        foreach($accepted as $a){$read->execute([$a['external_hotel_id']]);$r=$read->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['catalog_hotel_id']!==$a['catalog_hotel_id']||$r['match_class']!==HMSDW_CLASS||$r['scope']!==HMSDW_SCOPE||$r['approval_policy']!==HMSDW_POLICY||(int)$r['enabled']!==1||!hash_equals($a['source_row_digest'],(string)$r['source_row_digest'])||!hash_equals($mappingDigest,(string)$r['mapping_digest']))throw new RuntimeException('post_commit_readback_failed');}
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$resolve=$registry->previewResolver();foreach($accepted as $a)if($resolve('anex_online',(string)$a['external_hotel_id'])!==$a['catalog_hotel_id'])throw new RuntimeException('resolver_readback_failed');
        return ['status'=>'completed','operation_id'=>HMSDW_OPERATION,'database_writes'=>$writes,'mapping_writes'=>$writes,'committed'=>true,'readback_verified'=>true,'accepted'=>$accepted,'supplier_calls'=>0,'tourvisor_calls'=>0,'source_audit_result_sha256'=>HMSDW_AUDIT_SHA,'mapping_digest'=>$mappingDigest];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['status'=>'failed','operation_id'=>HMSDW_OPERATION,'database_writes'=>$committed?$writes:0,'mapping_writes'=>$committed?$writes:0,'committed'=>$committed,'readback_verified'=>false,'reason'=>in_array($e->getMessage(),['manifest_guard','provider_guard','manifest_pair_guard','manifest_digest_guard','required_table_missing','required_transactional_table_missing','current_safe_set_changed','current_digest_set_changed','locked_digest_set_changed','insert_not_one','post_commit_readback_failed','resolver_readback_failed'],true)?$e->getMessage():'runtime_failure','supplier_calls'=>0,'tourvisor_calls'=>0];}
}

if(in_array('--self-test',$_SERVER['argv']??[],true)){if(hmsdw_pair_key(['provider'=>'anex','external_hotel_id'=>'32616','catalog_hotel_id'=>17527])!=='anex:32616>17527')exit(2);echo "MATCH strong digest writer self-test PASS; network=0 database=0\n";exit(0);}
if(!defined('HMSDW_LIBRARY_ONLY')){error_reporting(0);$pdo=null;try{if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');define('HMPW_LIBRARY_ONLY',true);require_once $root.'/scripts/diagnostics/hotel_match_strong_prewrite_audit.php';require_once $root.'/app/integrations/anex-search-mapping-registry.php';require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$manifest=hmsdw_manifest(getenv('HMSDW_MANIFEST_PATH')?:$root.'/reports/hotel-match-strong-digest-write-1971.json');$auditManifest=hmpw_manifest(getenv('HMSDW_AUDIT_MANIFEST_PATH')?:$root.'/reports/hotel-match-current-strong-prewrite-1971.json');$pdo=v2_data_db();$r=hmsdw_run($pdo,$manifest,$auditManifest);echo 'HMSDW_JSON:'.json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;if(($r['status']??'')!=='completed')exit(2);}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();echo 'HMSDW_JSON:'.json_encode(['status'=>'failed','operation_id'=>HMSDW_OPERATION,'reason'=>'bootstrap_failure','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0],JSON_UNESCAPED_SLASHES).PHP_EOL;exit(2);}}
