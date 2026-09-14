<?php
declare(strict_types=1);

const MD_OP='hotel-match-captured-direct-current-accept-1971-20260914-v4';
const MD_POLICY='owner_exact_and_strong_20260908';
const MD_SOURCE_RUN=34884599885;
const MD_SOURCE_SHA='d26d02edbdb8765083fa5462d22361bc893e06eac3ddfa1b9658c35827b53035';

function md_write(string $path,array $value): void {
    $raw=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x'); if(!$f) throw new RuntimeException('write_once_exists:'.basename($path)); @chmod($path,0600);
    try { if(fwrite($f,$raw)!==strlen($raw)||!fflush($f)) throw new RuntimeException('write_failed'); if(function_exists('fsync')&&!fsync($f)) throw new RuntimeException('sync_failed'); } finally { fclose($f); }
}
function md_receipt(string $dir,string $state,int $writes,bool $verified=true): void {
    $raw=file_get_contents($dir.'/result.json'); md_write($dir.'/receipt.json',['operation_id'=>MD_OP,'state'=>$state,'result_sha256'=>hash('sha256',$raw),'database_writes'=>$writes,'mapping_writes'=>$writes,'post_commit_readback_count'=>$writes,'readback_verified'=>$verified,'no_replay'=>true]);
}
function md_sig(string $s): array {
    $s=mb_strtolower($s,'UTF-8');$s=strtr($s,['ё'=>'е','&'=>' and ','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);preg_match_all('/[\p{L}\p{N}]+/u',$s,$m);
    $drop=['hotel'=>1,'hotels'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'by'=>1];$out=[];foreach($m[0]??[] as $x)if(!isset($drop[$x]))$out[$x]=1;$out=array_keys($out);sort($out,SORT_STRING);return $out;
}
function md_coverage(PDO $db): array {
    $a=[];$q=$db->query("SELECT m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".MD_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)");foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)$a[(int)$id]=1;
    $q=$db->query("SELECT d.catalog_hotel_id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)");foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)$a[(int)$id]=1;
    $n=[];$q=$db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL");foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)$n[(int)$id]=1;$tr=count(array_intersect_key($a,$n));
    return ['anex_unique_local'=>count($a),'andromeda_unique_local'=>count($n),'all_three'=>$tr,'anex_tv_only'=>count($a)-$tr,'andromeda_tv_only'=>count($n)-$tr];
}

$opDir=getenv('HOME').'/.anytoour-match/operations/'.MD_OP;
if(!is_dir($opDir)||!is_file($opDir.'/reservation.json')) throw new RuntimeException('server_reservation_missing');
$pairs=[];
try {
    if((getenv('MATCH_OPERATION_ID')?:'')!==MD_OP) throw new RuntimeException('operation_contract');
    $raw=base64_decode(getenv('MATCH_EVIDENCE_B64')?:'',true); if($raw===false) throw new RuntimeException('evidence_b64');
    $e=json_decode($raw,true,64,JSON_THROW_ON_ERROR); if(!is_array($e)||count($e)!==3) throw new RuntimeException('evidence_count');
    foreach($e as $r){
        if(($r['tier']??'')!=='DIRECT'||($r['reason']??'')!=='direct_identity_confirmed'||($r['operator_name']??'')!=='Anex'||!($r['operator_link_present']??false))throw new RuntimeException('direct_contract');
        $aid=(int)($r['expected_anex_hotel_id']??0);$target=(int)($r['expected_tourvisor_hotel_id']??0);
        if($aid!==(int)($r['operator_identity']['anex_hotel_id']??0)||$target!==(int)($r['detail_tourvisor_hotel_id']??0)||($r['operator_identity']['mode']??'')!=='legacy_hotellist')throw new RuntimeException('provider_identity_contract');
        if(($r['semantic']['state']??'')!=='corroborated'||(float)($r['semantic']['ratio']??0)!==1.0||($r['qualifier_conflict']??true)||($r['numeric_conflict']??true))throw new RuntimeException('semantic_contract');
        $pairs[$aid]=['target'=>$target,'detail'=>(string)$r['detail_hotel_name'],'source'=>(string)$r['source_name'],'search_count'=>(int)$r['live_search_count'],'row'=>$r];
    }
    ksort($pairs,SORT_NUMERIC);$targets=[];foreach($pairs as $aid=>$p)$targets[$aid]=(int)$p['target'];
    if($targets!==[1767=>182,1768=>183,4158=>37412])throw new RuntimeException('pair_contract');
} catch(Throwable $e) {
    md_write($opDir.'/result.json',['status'=>'failed_before_db','operation_id'=>MD_OP,'reason'=>$e->getMessage(),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'andromeda_calls'=>0,'no_replay'=>true]);md_receipt($opDir,'failed_before_db',0);fwrite(STDERR,$e->getMessage()."\n");exit(2);
}

$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('bad_root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$committed=false;$written=[];$skipped=[];$mappingDigest=hash('sha256',MD_OP.'|'.hash('sha256',json_encode($pairs,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
try {
    $db->beginTransaction();
    $need=['catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','anex_search_hotel_observations'];$marks=implode(',',array_fill(0,count($need),'?'));$q=$db->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");$q->execute($need);$eng=$q->fetchAll(PDO::FETCH_KEY_PAIR);foreach($need as $t)if(strtoupper((string)($eng[$t]??''))!=='INNODB')throw new RuntimeException('transactional_table_missing:'.$t);
    $q=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_review_state'");$re=$q->fetchColumn();$hasReview=false;if($re!==false){if(strtoupper((string)$re)!=='INNODB')throw new RuntimeException('review_state_not_transactional');$q=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_review_state' AND COLUMN_NAME='anex_hotel_id'");$hasReview=(int)$q->fetchColumn()===1;}
    $before=md_coverage($db);
    foreach($pairs as $aid=>$p){$target=$p['target'];$reason=null;
        $q=$db->prepare('SELECT id,country_id,name,is_active FROM catalog_hotels WHERE id=? FOR UPDATE');$q->execute([$target]);$h=$q->fetch(PDO::FETCH_ASSOC);if(!$h||(int)$h['country_id']!==1||(int)$h['is_active']!==1)$reason='target_missing_country_or_inactive';elseif(md_sig((string)$h['name'])!==md_sig($p['detail']))$reason='current_target_name_drift';
        if($reason===null){$q=$db->prepare('SELECT anex_hotel_id,catalog_hotel_id,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? OR catalog_hotel_id=? ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE');$q->execute([$aid,$target]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $m){$ma=(int)$m['anex_hotel_id'];$mt=(int)$m['catalog_hotel_id'];$en=(int)$m['enabled'];if($ma===$aid&&$mt===$target){$reason=$en===1?'already_same_mapping':'existing_same_mapping_disabled_protected';break;}if($ma===$aid){$reason='existing_source_mapping_protected';break;}if($mt===$target){$reason='same_provider_target_occupied';break;}}}
        if($reason===null){$q=$db->prepare('SELECT anex_hotel_id FROM anex_hotel_decisions WHERE anex_hotel_id=? FOR UPDATE');$q->execute([$aid]);if($q->fetchColumn()!==false)$reason='manual_or_conflict_protected';}
        if($reason===null){$q=$db->prepare("SELECT anex_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id=? AND decision_status='accepted' AND anex_hotel_id<>? FOR UPDATE");$q->execute([$target,$aid]);if($q->fetchColumn()!==false)$reason='manual_target_occupied';}
        if($reason===null&&$hasReview){$q=$db->prepare('SELECT anex_hotel_id FROM anex_review_state WHERE anex_hotel_id=? FOR UPDATE');$q->execute([$aid]);if($q->fetchColumn()!==false)$reason='review_state_protected';}
        if($reason===null){$q=$db->prepare('SELECT anex_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=? FOR UPDATE');$q->execute([$aid,$target]);if($q->fetchColumn()!==false)$reason='pair_exclusion_protected';}
        if($reason===null){$q=$db->prepare('SELECT country_id,search_count FROM anex_search_hotel_observations WHERE anex_hotel_id=? FOR UPDATE');$q->execute([$aid]);$o=$q->fetch(PDO::FETCH_ASSOC);if(!$o||(int)$o['country_id']!==1||(int)$o['search_count']<1)$reason='live_observation_drift';}
        if($reason!==null){$skipped[]=['anex_hotel_id'=>$aid,'target_local_hotel_id'=>$target,'reason'=>$reason];continue;}
        $prov=['operation_id'=>MD_OP,'rule'=>'tourvisor_operatorLink_HOTELLIST_direct','source_run_id'=>MD_SOURCE_RUN,'source_result_sha256'=>MD_SOURCE_SHA,'anex_hotel_id'=>$aid,'target_local_hotel_id'=>$target,'tour_id'=>$p['row']['tour_id'],'detail_hotel_name'=>$p['detail'],'source_name'=>$p['source'],'live_search_count'=>$p['search_count'],'semantic'=>$p['row']['semantic'],'qualifier_conflict'=>false,'numeric_conflict'=>false];$digest=hash('sha256',json_encode($prov,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $ins=$db->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");$ins->execute([$aid,$target,MD_POLICY,$digest,$mappingDigest]);if($ins->rowCount()!==1)throw new RuntimeException('insert_not_one');$written[$aid]=['target'=>$target,'digest'=>$digest,'search_count'=>$p['search_count']];
    }
    $db->commit();$committed=true;
    $readback=[];$q=$db->prepare('SELECT catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');foreach($written as $aid=>$w){$q->execute([$aid]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1)throw new RuntimeException('post_commit_row_count');$r=$rows[0];if((int)$r['catalog_hotel_id']!==$w['target']||(string)$r['match_class']!=='strong_candidate'||(string)$r['scope']!=='preview'||(string)$r['approval_policy']!==MD_POLICY||(int)$r['enabled']!==1||!hash_equals($w['digest'],(string)$r['source_row_digest'])||!hash_equals($mappingDigest,(string)$r['mapping_digest']))throw new RuntimeException('post_commit_readback_failed');$readback[]=['anex_hotel_id'=>$aid,'catalog_hotel_id'=>(int)$r['catalog_hotel_id'],'enabled'=>(int)$r['enabled'],'source_row_digest'=>(string)$r['source_row_digest']];}
    $after=md_coverage($db);$targetIds=array_values(array_unique(array_map(fn($p)=>(int)$p['target'],array_values($pairs))));$marks=implode(',',array_fill(0,count($targetIds),'?'));$q=$db->prepare("SELECT external_hotel_id,local_hotel_id,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($marks) ORDER BY local_hotel_id,external_hotel_id");$q->execute($targetIds);$and=$q->fetchAll(PDO::FETCH_ASSOC);
    $accepted=[];foreach($written as $aid=>$w)$accepted[]=['anex_hotel_id'=>(int)$aid,'target_local_hotel_id'=>$w['target'],'search_count'=>$w['search_count']];md_write($opDir.'/result.json',['status'=>'completed','operation_id'=>MD_OP,'committed'=>true,'database_writes'=>count($written),'mapping_writes'=>count($written),'supplier_calls'=>0,'tourvisor_calls'=>0,'andromeda_calls'=>0,'accepted'=>$accepted,'skipped'=>$skipped,'post_commit_readback'=>$readback,'coverage_before'=>$before,'coverage_after'=>$after,'accepted_andromeda_on_targets'=>$and,'no_replay'=>true]);md_receipt($opDir,'completed_committed',count($written));echo json_encode(['status'=>'completed','writes'=>count($written),'skips'=>count($skipped)],JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e) {
    if(!$committed&&$db->inTransaction()){$db->rollBack();md_write($opDir.'/result.json',['status'=>'failed_before_commit','operation_id'=>MD_OP,'reason'=>$e->getMessage(),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'andromeda_calls'=>0,'no_replay'=>true]);md_receipt($opDir,'failed_before_commit',0);fwrite(STDERR,$e->getMessage()."\n");exit(2);}
    @md_write($opDir.'/unknown.json',['status'=>'unknown_after_commit','operation_id'=>MD_OP,'reason'=>$e->getMessage(),'database_writes'=>count($written),'mapping_writes'=>count($written),'committed'=>true,'no_replay'=>true]);fwrite(STDERR,$e->getMessage()."\n");exit(3);
}
