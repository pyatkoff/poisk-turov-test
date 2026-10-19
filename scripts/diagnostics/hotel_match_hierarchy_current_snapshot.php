<?php
declare(strict_types=1);
/** MATCH-only single READ ONLY snapshot. No supplier client or mutation SQL. */
const HMHC_OP = 'hotel-match-hierarchy-current-snapshot-1971-20260916-v1';
const HMHC_INPUT = '__CHECKED_FRONTIER_MANIFEST_BASE64__';
function hmhc_require(bool $ok, string $reason): void { if (!$ok) throw new RuntimeException($reason); }
function hmhc_json(array $x): string { return json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function hmhc_write(string $p, array $x): string {
    $raw=hmhc_json($x);$f=@fopen($p,'x+b');hmhc_require(is_resource($f),'exclusive_output');
    try {
        hmhc_require(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');
        if(function_exists('fsync'))hmhc_require(fsync($f),'output_sync');
        rewind($f);hmhc_require(stream_get_contents($f)===$raw,'output_readback');
    } finally {fclose($f);}return hash('sha256',$raw);
}
function hmhc_read(string $p,int $max): array {
    hmhc_require(!is_link($p)&&realpath($p)===$p&&is_file($p),'input_path');
    $f=fopen($p,'rb');hmhc_require(is_resource($f),'input_open');
    try{$a=fstat($f);hmhc_require($a['nlink']===1&&$a['size']<=$max,'input_size');
        $raw=stream_get_contents($f,$max+1);$b=fstat($f);
        hmhc_require(is_string($raw)&&strlen($raw)===$a['size']&&$a['ino']===$b['ino']&&$a['mtime']===$b['mtime']&&$a['size']===$b['size'],'input_changed');
    }finally{fclose($f);}
    $x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);hmhc_require(is_array($x),'input_shape');return $x;
}
function hmhc_protected($v,string $key='',int $depth=0): bool {
    if($depth>20)return true;
    if(is_array($v)){foreach($v as $k=>$x)if(hmhc_protected($x,(string)$k,$depth+1))return true;return false;}
    if(!preg_match('/manual|exclude|exclusion|conflict|reject|review/i',$key))return false;
    if(is_bool($v))return $v;if(is_numeric($v))return (float)$v!=0;
    return is_string($v)&&!in_array(strtolower(trim($v)),['','false','none','no','null'],true);
}
function hmhc_states(array $e): array {
    $s=is_array($e['source']??null)?$e['source']:$e;$out=[];
    foreach([$s['stateKey']??null,$s['state_key']??null,$e['stateKey']??null,$e['state_key']??null] as $v)
        if((is_int($v)||is_string($v))&&preg_match('/^[1-9][0-9]{0,9}$/D',(string)$v))$out[(string)$v]=true;
    $ids=array_map('strval',array_keys($out));sort($ids,SORT_STRING);return $ids;
}
function hmhc_names(array $e): array {
    $s=is_array($e['source']??null)?$e['source']:$e;$names=[];
    foreach(['name','lName','hotel_name','hotelName','original_name'] as $key){$v=$s[$key]??null;
        if(is_string($v)&&trim($v)!==''&&strlen($v)<=1024&&!preg_match('/[\x00-\x1f\x7f]/',$v))$names[trim($v)]=true;
    }return array_keys($names);
}
function hmhc_manifest(array $x): array {
    hmhc_require(($x['input_identity_sha256']??'')==='712aa93f1476ff6b0549d1f1ed606e4c999ef3076c8c6fce3dbd06614682da97','input_digest');
    hmhc_require(is_array($x['frontier']??null)&&count($x['frontier'])===1321&&count($x['countries']??[])===8,'input_population');
    $seen=[];foreach($x['frontier'] as $r){$id=$r['external_hotel_id']??null;
        hmhc_require(is_string($id)&&preg_match('/^[1-9][0-9]{0,19}$/D',$id)===1&&!isset($seen[$id]),'manifest_id');
        hmhc_require(preg_match('/^[a-f0-9]{64}$/D',(string)($r['evidence_sha256']??''))===1,'manifest_source_hash');
        hmhc_require(is_int($r['state_key'])&&$r['state_key']>0&&is_int($r['country_id'])&&isset($x['countries'][(string)$r['country_id']]),'manifest_country');$seen[$id]=$r;
    }return $seen;
}
function hmhc_query(PDO $db,string $sql,array $params=[]): array {
    $q=$db->prepare($sql);$q->execute(array_values($params));$r=$q->fetchAll(PDO::FETCH_ASSOC);
    hmhc_require(count($r)<=150000,'query_row_budget');return $r;
}
function hmhc_main(): void {
    hmhc_require(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===HMHC_OP,'operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA');hmhc_require(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
    $manifest=json_decode((string)base64_decode(HMHC_INPUT,true),true,64,JSON_THROW_ON_ERROR);$selected=hmhc_manifest($manifest);
    $dir=(string)getenv('HOME').'/.anytoour-match/operations/'.HMHC_OP;
    $reservation=hmhc_read($dir.'/reservation.json',16384);
    hmhc_require(($reservation['operation_id']??'')===HMHC_OP&&($reservation['source_sha']??'')===$sha&&($reservation['state']??'')==='reserved_before_db_access','reservation_guard');
    hmhc_require(($reservation['manifest_sha256']??'')===hash('sha256',(string)base64_decode(HMHC_INPUT,true)),'manifest_reservation');
    $db=null;$phase='configuration';$out=['operation_id'=>HMHC_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'safe_to_write_now'=>false];
    ob_start();
    try {
        $root=realpath(getcwd());hmhc_require(is_string($root)&&basename($root)==='anytoour.ru','project_guard');
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        hmhc_require(realpath($bootstrap)===$bootstrap&&!is_link($bootstrap),'bootstrap_path');require_once $bootstrap;
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$phase='current_read';
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $countries=[];foreach(hmhc_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c)$countries[(string)$c['id']]=$c['name'];
        foreach($manifest['countries'] as $cid=>$name)hmhc_require(($countries[(string)$cid]??null)===$name,'country_context_changed');
        $cids=array_map('intval',array_keys($manifest['countries']));$sqlIds=implode(',',array_fill(0,count($cids),'?'));
        $hotels=[];foreach(hmhc_query($db,"SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($sqlIds) ORDER BY id",$cids) as $h){
            foreach(['id','country_id','region_id','subregion_id'] as $k)$h[$k]=$h[$k]===null?null:(int)$h[$k];$hotels[(string)$h['id']]=$h;
        }
        $aliases=[];foreach(hmhc_query($db,"SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($sqlIds) ORDER BY a.hotel_id,a.id",$cids) as $r)$aliases[(string)$r['hotel_id']][]=$r['alias'];
        $current=[];$occupancy=[];$status=[];
        foreach(hmhc_query($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id") as $r){
            $id=(string)$r['external_hotel_id'];$st=(string)$r['decision_status'];$status[$st]=($status[$st]??0)+1;
            if($r['local_hotel_id']!==null)$occupancy[(string)$r['local_hotel_id']][]=$id;
            if(!isset($selected[$id]))continue;
            $e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);hmhc_require(is_array($e),'current_evidence_shape');$p=$selected[$id];$states=hmhc_states($e);$holds=[];
            if($st!=='pending'||$r['local_hotel_id']!==null)$holds[]='not_current_pending_null';
            if(!is_string($r['evidence_sha256'])||!hash_equals($p['evidence_sha256'],$r['evidence_sha256']))$holds[]='source_digest_changed';
            if($states!==[(string)$p['state_key']])$holds[]='current_state_context_changed';
            if(hmhc_protected($e))$holds[]='current_protected_evidence';
            $current[$id]=['external_hotel_id'=>$id,'decision_status'=>$st,'local_hotel_id'=>$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'],
                'evidence_sha256'=>$r['evidence_sha256'],'evidence_bytes_sha256'=>hash('sha256',(string)$r['evidence_json']),
                'state_keys'=>$states,'names'=>hmhc_names($e),'holds'=>$holds];
        }
        $missing=array_values(array_diff(array_map('strval',array_keys($selected)),array_map('strval',array_keys($current))));
        $out+=['input_identity_sha256'=>$manifest['input_identity_sha256'],'frontier_count'=>count($selected),'current_frontier'=>$current,
            'missing_current_ids'=>$missing,'current_status_counts'=>$status,'current_local_hotels'=>$hotels,'current_aliases'=>$aliases,
            'current_occupancy'=>$occupancy,'current_countries'=>array_intersect_key($countries,$manifest['countries']),
            'read_at_utc'=>gmdate('c'),'transaction'=>'REPEATABLE READ / READ ONLY'];
        $db->exec('ROLLBACK');$out['state']='completed_read_only';
    }catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['error_phase']=$phase;
        $out['error_code']=preg_match('/^[a-z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';}
    while(ob_get_level())ob_end_clean();$digest=hmhc_write($dir.'/result.json',$out);
    hmhc_write($dir.'/receipt.json',['operation_id'=>HMHC_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$digest,
        'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0]);
    echo hmhc_json(['state'=>$out['state'],'result_sha256'=>$digest,'frontier_count'=>$out['frontier_count']??null]);
    if($out['state']!=='completed_read_only')exit(2);
}
if(in_array('--self-test',$argv??[],true)){
    $n=0;foreach([[['manual'=>true],true],[['source'=>['review'=>'hold']],true],[['conflict'=>false],false],[['excluded'=>0],false],[['name'=>'manual hotel'],false],[['source'=>['pair_exclusion'=>1]],true]] as [$x,$expect]){hmhc_require(hmhc_protected($x)===$expect,'protected_test');$n++;}
    hmhc_require(hmhc_states(['source'=>['stateKey'=>5],'state_key'=>'5'])===['5'],'state_dedupe');$n++;
    hmhc_require(hmhc_states(['source'=>['stateKey'=>5],'state_key'=>3])===['3','5'],'state_conflict');$n++;
    hmhc_require(hmhc_names(['source'=>['name'=>'Yaman Life','token'=>'private']])===['Yaman Life'],'names_allowlist');$n++;
    $p=tempnam(sys_get_temp_dir(),'hmhc-');unlink($p);$h=hmhc_write($p,['ok'=>true]);hmhc_require($h===hash_file('sha256',$p),'durable_readback');$n++;
    $rejected=false;try{hmhc_write($p,['ok'=>false]);}catch(Throwable $e){$rejected=true;}hmhc_require($rejected,'exclusive_replay');unlink($p);$n++;
    echo "$n CURRENT snapshot self-tests PASS\n";exit;
}
hmhc_main();
