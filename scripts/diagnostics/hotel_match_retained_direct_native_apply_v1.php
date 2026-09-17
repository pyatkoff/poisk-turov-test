<?php
declare(strict_types=1);

/** MATCH-only one-shot writer for four sealed Tourvisor operator-link native identities. */
const OP = 'hotel-match-retained-direct-native-apply-1971-20260917-v1';
const PREFLIGHT_OP = 'hotel-match-retained-direct-native-current-1971-20260917-v1';
const PREFLIGHT_SOURCE_SHA = 'b007ea599a7d8d9d929a96062787579bd098c90e';
const PREFLIGHT_RESULT_SHA256 = '080f48c2cdc2516780dd3340a8cf75298730c958643310c20c63f893fac2d211';
const SCHEMA_AUDIT_OP = 'hotel-match-identity-write-schema-current-1971-20260917-v2';
const SCHEMA_AUDIT_RESULT_SHA256 = '16d18250003402c6d854d78cd5e58bcc5b7518843fc1f3860043bdb7943ab8fd';
const PLAN_SHA256 = '7e7b796b322efb2520c41c90a5b8b137bbed142fb2a6bbeb9326e7ee048c5dbe';
const PLAN_B64 = 'W3sibnMiOiJvcGVyYXRvcl8zMTUiLCJleHQiOiIyNjY0NTQiLCJsb2NhbCI6MTI1LCJ0dm9wIjoyNSwicHJvdmlkZXIiOiJGVU4mU1VOIiwic291cmNlX3J1biI6MzUwNzExMzk4NzgsInNvdXJjZV9yZXN1bHQiOiIwNjNmN2YyYTRhZjBlYTUzOTlmYzVhMjI1NDNkYjk0ZDUwN2Y3OWQ0OGM5ZTRjYWVmYTM3ZTg0OGNmYjViODYzIiwibG9jYWxfbmFtZSI6IlBJQ0tBTEJBVFJPUyBBUVVBIEJMVSBSRVNPUlQgU0hBUk0gRUwgU0hFSUtIIiwiY291bnRyeV9pZCI6MSwibGlua19wYXJhbSI6ImhvdGVscyJ9LHsibnMiOiJvcGVyYXRvcl8zMTUiLCJleHQiOiIxODY4OCIsImxvY2FsIjoyOTMsInR2b3AiOjI1LCJwcm92aWRlciI6IkZVTiZTVU4iLCJzb3VyY2VfcnVuIjozNTA3MTEzOTg3OCwic291cmNlX3Jlc3VsdCI6IjA2M2Y3ZjJhNGFmMGVhNTM5OWZjNWEyMjU0M2RiOTRkNTA3Zjc5ZDQ4YzllNGNhZWZhMzdlODQ4Y2ZiNWI4NjMiLCJsb2NhbF9uYW1lIjoiUElDS0FMQkFUUk9TIExBR1VOQSBWSVNUQSBCRUFDSCIsImNvdW50cnlfaWQiOjEsImxpbmtfcGFyYW0iOiJob3RlbHMifSx7Im5zIjoib3BlcmF0b3JfMzQyIiwiZXh0IjoiMjU5NTgiLCJsb2NhbCI6MTkwLCJ0dm9wIjo0MywicHJvdmlkZXIiOiJJbnRvdXJpc3QiLCJzb3VyY2VfcnVuIjozNTA3MTEzOTg3OCwic291cmNlX3Jlc3VsdCI6IjA2M2Y3ZjJhNGFmMGVhNTM5OWZjNWEyMjU0M2RiOTRkNTA3Zjc5ZDQ4YzllNGNhZWZhMzdlODQ4Y2ZiNWI4NjMiLCJsb2NhbF9uYW1lIjoiRE9NSU5BIENPUkFMIEJBWSBQUkVTVElHRSBQT09MIiwiY291bnRyeV9pZCI6MSwibGlua19wYXJhbSI6ImhvdGVscyJ9LHsibnMiOiJvcGVyYXRvcl8zMTUiLCJleHQiOiIzMDc1MiIsImxvY2FsIjoxMjIxLCJ0dm9wIjoyNSwicHJvdmlkZXIiOiJGVU4mU1VOIiwic291cmNlX3J1biI6MzUwNzEzMzAyMzIsInNvdXJjZV9yZXN1bHQiOiI1MTRlNDdkODg5YWU3MzI3OWIxNmQ4YTYyZDc2ZThjN2ZkNTJhZWYyYTVlMzY0NmQ4ODg3NThjOTk3OTFkNDY4IiwibG9jYWxfbmFtZSI6Ik1FR0FTQVJBWSBSRVNPUlQgU0lERSAoRVguIEFTS0EgU0lERSBIT1RFTCkiLCJjb3VudHJ5X2lkIjo0LCJsaW5rX3BhcmFtIjoiaG90ZWxzIn1d';
const CORE8 = [1,2,4,8,9,10,12,16];

function rq(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function js(array $x): string { return json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function wr(string $path, array $x): string {
    $raw=js($x); $f=@fopen($path,'x+b'); rq(is_resource($f),'exclusive_output');
    try { rq(fwrite($f,$raw)===strlen($raw) && fflush($f),'output_write'); if(function_exists('fsync')) rq(fsync($f),'output_sync'); rewind($f); rq(stream_get_contents($f)===$raw,'output_readback'); }
    finally { fclose($f); }
    return hash('sha256',$raw);
}
function q(PDO $db,string $sql,array $args=[]): array { $s=$db->prepare($sql);$s->execute(array_values($args));$r=$s->fetchAll(PDO::FETCH_ASSOC);rq(count($r)<100000,'query_budget');return $r; }
function plan(): array {
    $raw=base64_decode(PLAN_B64,true);rq(is_string($raw)&&hash_equals(PLAN_SHA256,hash('sha256',$raw)),'plan_digest');
    $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);rq(is_array($p)&&count($p)===4,'plan_population');
    $ids=[];$targets=[];
    foreach($p as $r){
        rq(in_array($r['ns']??'',['operator_315','operator_342'],true),'plan_namespace');
        rq(is_string($r['ext']??null)&&preg_match('/^[1-9][0-9]{0,19}$/D',$r['ext'])===1,'plan_external');
        rq(is_int($r['local']??null)&&$r['local']>0&&is_int($r['country_id']??null)&&in_array($r['country_id'],CORE8,true),'plan_target');
        rq(($r['link_param']??'')==='hotels','plan_link_semantics');
        rq(($r['ns']==='operator_315'&&$r['tvop']===25&&$r['provider']==='FUN&SUN')||($r['ns']==='operator_342'&&$r['tvop']===43&&$r['provider']==='Intourist'),'plan_operator_semantics');
        rq(is_int($r['source_run']??null)&&$r['source_run']>0&&is_string($r['source_result']??null)&&preg_match('/^[a-f0-9]{64}$/D',$r['source_result'])===1,'plan_source');
        rq(is_string($r['local_name']??null)&&$r['local_name']!=='','plan_name');
        $ik=$r['ns'].'/'.$r['ext'];$tk=$r['ns'].'/'.$r['local'];rq(!isset($ids[$ik])&&!isset($targets[$tk]),'plan_duplicate');$ids[$ik]=1;$targets[$tk]=1;
    }
    return $p;
}
function evidence(array $r,string $sourceSha): string {
    return js([
        'schema'=>'tourvisor-operator-link-native/1',
        'operation_id'=>OP,
        'source_sha'=>$sourceSha,
        'preflight_operation'=>PREFLIGHT_OP,
        'preflight_source_sha'=>PREFLIGHT_SOURCE_SHA,
        'preflight_result_sha256'=>PREFLIGHT_RESULT_SHA256,
        'schema_audit_operation'=>SCHEMA_AUDIT_OP,
        'schema_audit_result_sha256'=>SCHEMA_AUDIT_RESULT_SHA256,
        'plan_sha256'=>PLAN_SHA256,
        'catalog_reference_kind'=>'retained_tourvisor_operator_link',
        'country_id'=>$r['country_id'],
        'source'=>[
            'provider'=>$r['provider'],
            'tourvisor_operator_id'=>$r['tvop'],
            'native_hotel_id'=>$r['ext'],
            'operator_link_param'=>$r['link_param'],
            'source_run'=>$r['source_run'],
            'source_result_sha256'=>$r['source_result'],
        ],
        'decision'=>[
            'state'=>'accepted',
            'local_hotel_id'=>$r['local'],
            'reason'=>'retained_single_numeric_operator_link_native_to_current_local',
            'current_guards_verified'=>true,
        ],
    ]);
}
function schema_guard(PDO $db): array {
    $cols=q($db,"SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities' ORDER BY ORDINAL_POSITION");rq(count($cols)>0,'identity_table_missing');
    $required=[];foreach($cols as$c)if(($c['IS_NULLABLE']??'YES')==='NO'&&$c['COLUMN_DEFAULT']===null&&!str_contains(strtolower((string)$c['EXTRA']),'auto_increment'))$required[]=(string)$c['COLUMN_NAME'];sort($required);
    $expected=['catalog_sha256','decision_status','evidence_json','evidence_sha256','external_hotel_id','supplier_namespace'];sort($expected);rq($required===$expected,'required_insert_contract');
    $pk=q($db,"SELECT SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities' AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX");rq(array_column($pk,'COLUMN_NAME')===['supplier_namespace','external_hotel_id'],'identity_pk_contract');
    $eng=q($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels')");rq(count($eng)===2,'table_contract');foreach($eng as$t)rq(strtoupper((string)$t['ENGINE'])==='INNODB','nontransactional_table');
    $extra=q($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (LOWER(TABLE_NAME) LIKE '%hotel%' OR LOWER(TABLE_NAME) LIKE '%identity%') AND LOWER(TABLE_NAME) REGEXP 'manual|exclu|decision|review' AND LOWER(TABLE_NAME) NOT LIKE 'anex_%'");rq($extra===[],'separate_operator_protection_store');
    return ['required_insert_columns'=>$required,'primary_key'=>array_column($pk,'COLUMN_NAME')];
}
function main(): void {
    rq(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===OP,'operation_guard');$sha=(string)getenv('MATCH_SOURCE_SHA');rq(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
    $p=plan();$dir=(string)getenv('HOME').'/.anytoour-match/operations/'.OP;$rp=$dir.'/reservation.json';rq(is_file($rp)&&!is_link($rp)&&realpath($rp)===$rp&&filesize($rp)<16384,'reservation_path');
    $res=json_decode((string)file_get_contents($rp),true,32,JSON_THROW_ON_ERROR);rq(($res['operation_id']??'')===OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_access','reservation_binding');rq(($res['preflight_result_sha256']??'')===PREFLIGHT_RESULT_SHA256&&($res['schema_audit_result_sha256']??'')===SCHEMA_AUDIT_RESULT_SHA256&&($res['plan_sha256']??'')===PLAN_SHA256,'reservation_pins');
    $db=null;$commitAttempted=false;$committed=false;$holds=[];$prepared=[];$readback=[];
    $out=['operation_id'=>OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'preflight_operation'=>PREFLIGHT_OP,'preflight_result_sha256'=>PREFLIGHT_RESULT_SHA256,'schema_audit_operation'=>SCHEMA_AUDIT_OP,'schema_audit_result_sha256'=>SCHEMA_AUDIT_RESULT_SHA256,'plan_sha256'=>PLAN_SHA256,'planned_count'=>4,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'post_commit_readback_verified'=>false];
    ob_start();
    try {
        $root=realpath(getcwd());rq(is_string($root)&&basename($root)==='anytoour.ru','project_guard');$bp=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');rq(realpath($bp)===$bp&&!is_link($bp),'bootstrap_path');require_once $bp;
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$out['schema_contract']=schema_guard($db);
        foreach($p as$r){
            $why=[];$existing=q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=? FOR UPDATE",[$r['ns'],$r['ext']]);if($existing!==[])$why[]='identity_no_longer_absent';
            $cat=q($db,'SELECT id,country_id,name,is_active,latitude,longitude FROM catalog_hotels WHERE id=? FOR UPDATE',[$r['local']]);if(count($cat)!==1){$why[]='local_missing';$c=null;}else{$c=$cat[0];if((int)$c['is_active']!==1)$why[]='local_inactive';if((int)$c['country_id']!==$r['country_id'])$why[]='local_country_drift';if((string)$c['name']!==$r['local_name'])$why[]='local_name_drift';}
            $occ=q($db,"SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace=? AND decision_status='accepted' AND local_hotel_id=? FOR UPDATE",[$r['ns'],$r['local']]);if($occ!==[])$why[]='same_namespace_target_occupied';
            if($why){$holds[]=['ns'=>$r['ns'],'external_id'=>$r['ext'],'local_id'=>$r['local'],'reasons'=>array_values(array_unique($why))];continue;}
            $ev=evidence($r,$sha);$prepared[]=$r+['catalog_sha256'=>PREFLIGHT_RESULT_SHA256,'evidence_sha256'=>hash('sha256',$ev),'evidence_json'=>$ev];
        }
        $public=array_map(static function(array$x):array{unset($x['evidence_json']);return$x;},$prepared);wr($dir.'/precommit.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>'validated_before_commit','planned_count'=>4,'prepared_count'=>count($prepared),'held_count'=>count($holds),'rows'=>$public,'holds'=>$holds,'no_replay'=>true]);
        if(count($prepared)!==4||$holds!==[]){$db->rollBack();$out['state']='completed_no_write';$out['prepared_count']=count($prepared);$out['held_count']=count($holds);$out['holds']=$holds;$out['completed_at_utc']=gmdate('c');$out['post_commit_readback_verified']=true;}
        else {
            $ins=$db->prepare('INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES(?,?,?,\'accepted\',?,?,?)');
            foreach($prepared as$w){$ins->execute([$w['ns'],$w['ext'],$w['local'],$w['catalog_sha256'],$w['evidence_sha256'],$w['evidence_json']]);rq($ins->rowCount()===1,'conditional_insert_count');}
            $commitAttempted=true;$db->commit();$committed=true;
            foreach($prepared as$w){$rr=q($db,"SELECT local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?",[$w['ns'],$w['ext']]);rq(count($rr)===1,'readback_count');$x=$rr[0];$ee=json_decode((string)$x['evidence_json'],true,64,JSON_THROW_ON_ERROR);rq((int)$x['local_hotel_id']===$w['local']&&$x['decision_status']==='accepted'&&hash_equals(PREFLIGHT_RESULT_SHA256,(string)$x['catalog_sha256'])&&hash_equals($w['evidence_sha256'],(string)$x['evidence_sha256'])&&hash_equals($w['evidence_sha256'],hash('sha256',(string)$x['evidence_json']))&&($ee['operation_id']??'')===OP&&($ee['source']['native_hotel_id']??'')===$w['ext'],'post_commit_mismatch');$readback[]=['ns'=>$w['ns'],'external_id'=>$w['ext'],'local_id'=>$w['local'],'decision_status'=>'accepted','evidence_sha256'=>$x['evidence_sha256']];}
            $pending=[];$pt=0;foreach(q($db,"SELECT supplier_namespace,COUNT(*) c FROM andromeda_hotel_identities WHERE supplier_namespace IN ('andromeda_catalog','operator_315','operator_342','operator_5') AND decision_status='pending' AND local_hotel_id IS NULL GROUP BY supplier_namespace ORDER BY supplier_namespace")as$x){$pending[(string)$x['supplier_namespace']]=(int)$x['c'];$pt+=(int)$x['c'];}
            $accepted=[];foreach(q($db,"SELECT supplier_namespace,COUNT(*) c FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_315','operator_342') AND decision_status='accepted' AND local_hotel_id IS NOT NULL GROUP BY supplier_namespace ORDER BY supplier_namespace")as$x)$accepted[(string)$x['supplier_namespace']]=(int)$x['c'];
            $out['state']='completed_committed';$out['prepared_count']=4;$out['held_count']=0;$out['holds']=[];$out['committed_count']=4;$out['database_writes']=4;$out['mapping_writes']=4;$out['post_commit_readback_verified']=count($readback)===4;$out['readback']=$readback;$out['post_pending_by_namespace']=$pending;$out['post_pending_total']=$pt;$out['accepted_by_namespace']=$accepted;$out['completed_at_utc']=gmdate('c');
        }
    } catch(Throwable$e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();if($commitAttempted&&!$committed)$out['state']='unknown_commit_outcome_no_replay';elseif($committed)$out['state']='committed_readback_failed_no_replay';$out['error_code']=preg_match('/^[a-z0-9_]{2,120}$/i',$e->getMessage())?$e->getMessage():'sanitized_failure';$out['prepared_count']=count($prepared);$out['held_count']=count($holds);$out['holds']=$holds;$out['committed_count']=$committed?count($prepared):0;$out['database_writes']=$committed?count($prepared):0;$out['mapping_writes']=$committed?count($prepared):0;}
    while(ob_get_level())ob_end_clean();$rh=wr($dir.'/result.json',$out);wr($dir.'/receipt.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$rh,'readback_verified'=>$out['post_commit_readback_verified']??false,'no_replay'=>true,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0]);echo js(['state'=>$out['state'],'planned_count'=>4,'prepared_count'=>$out['prepared_count']??0,'held_count'=>$out['held_count']??0,'committed_count'=>$out['committed_count']??0,'post_pending_by_namespace'=>$out['post_pending_by_namespace']??null,'post_pending_total'=>$out['post_pending_total']??null,'accepted_by_namespace'=>$out['accepted_by_namespace']??null,'result_sha256'=>$rh]);if(!in_array($out['state'],['completed_committed','completed_no_write'],true)||!($out['post_commit_readback_verified']??false))exit(2);
}
if(in_array('--self-test',$argv??[],true)){ $p=plan();rq(count($p)===4,'self_plan');$ev=evidence($p[0],str_repeat('a',40));rq(str_contains($ev,'tourvisor-operator-link-native/1'),'self_schema');rq(str_contains($ev,'266454')&&str_contains($ev,'FUN&SUN'),'self_evidence');echo "retained_direct_native_apply_v1 self-tests PASS\n";exit; }
main();
