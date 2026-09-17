<?php
declare(strict_types=1);

/** MATCH-only CURRENT Tourvisor invocation census v2. READ ONLY; never calls Tourvisor. */
const HMTV2_OP = 'hotel-match-tourvisor-daily-attempt-current-1971-20260917-v2';

function hmtv2_require(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function hmtv2_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function hmtv2_write(string $p, array $v): string {
    $raw=hmtv2_json($v); $f=@fopen($p,'x+b'); hmtv2_require(is_resource($f),'exclusive_output');
    try { hmtv2_require(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write'); if(function_exists('fsync')) hmtv2_require(fsync($f),'output_sync'); rewind($f); hmtv2_require(stream_get_contents($f)===$raw,'output_readback'); } finally { fclose($f); }
    return hash('sha256',$raw);
}
function hmtv2_read(string $p,int $max): array {
    hmtv2_require(!is_link($p)&&realpath($p)===$p&&is_file($p),'input_path'); $f=fopen($p,'rb'); hmtv2_require(is_resource($f),'input_open');
    try { $a=fstat($f); hmtv2_require(is_array($a)&&$a['nlink']===1&&$a['size']<=$max,'input_size'); $raw=stream_get_contents($f,$max+1); $b=fstat($f); hmtv2_require(is_string($raw)&&strlen($raw)===$a['size']&&$a['ino']===$b['ino']&&$a['mtime']===$b['mtime']&&$a['size']===$b['size'],'input_changed'); } finally { fclose($f); }
    $v=json_decode($raw,true,32,JSON_THROW_ON_ERROR); hmtv2_require(is_array($v),'input_shape'); return $v;
}
function hmtv2_query(PDO $db,string $sql,array $args=[]): array { $q=$db->prepare($sql); $q->execute(array_values($args)); $r=$q->fetchAll(PDO::FETCH_ASSOC); hmtv2_require(count($r)<=10000,'query_row_budget'); return $r; }
function hmtv2_total(array $rows): int { $n=0; foreach($rows as $r){$c=(int)($r['attempts']??0); hmtv2_require($c>=0,'negative_count'); $n+=$c;} return $n; }

function hmtv2_main(): void {
    hmtv2_require(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===HMTV2_OP,'operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA'); hmtv2_require(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
    $dir=(string)getenv('HOME').'/.anytoour-match/operations/'.HMTV2_OP; $res=hmtv2_read($dir.'/reservation.json',32768);
    hmtv2_require(($res['operation_id']??'')===HMTV2_OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_access','reservation_guard');
    $out=['operation_id'=>HMTV2_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'daily_limit'=>300,'safe_to_spend_tourvisor_now'=>false];
    $db=null; $phase='configuration'; ob_start();
    try {
        $root=realpath(getcwd()); hmtv2_require(is_string($root)&&basename($root)==='anytoour.ru','project_guard');
        $bp=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'); hmtv2_require(realpath($bp)===$bp&&!is_link($bp),'bootstrap_path'); require_once $bp;
        $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $phase='current_read';
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION READ ONLY');
        $clock=hmtv2_query($db,"SELECT UTC_TIMESTAMP() AS utc_now,NOW() AS db_now,@@session.time_zone AS session_time_zone,@@global.time_zone AS global_time_zone,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW()) AS db_utc_offset_seconds"); hmtv2_require(count($clock)===1,'clock_contract');
        hmtv2_require(substr((string)$clock[0]['utc_now'],0,10)==='2026-09-17','utc_date_guard');
        $inventory=hmtv2_query($db,"SELECT TABLE_NAME,TABLE_TYPE,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (LOWER(TABLE_NAME) LIKE '%invocation%' OR LOWER(TABLE_NAME) LIKE '%audit%') ORDER BY TABLE_NAME");
        $exact=array_values(array_filter($inventory,fn($r)=>(string)$r['TABLE_NAME']==='api_invocation_audit'));
        $columns=hmtv2_query($db,"SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_invocation_audit' ORDER BY ORDINAL_POSITION");
        $cm=[]; foreach($columns as $c)$cm[(string)$c['COLUMN_NAME']]=strtolower((string)$c['DATA_TYPE']);
        $out['table_inventory']=$inventory; $out['audit_columns']=$columns; $out['clock']=$clock[0];
        if(count($exact)!==1 || !isset($cm['provider'],$cm['method'],$cm['created_at'])) {
            $db->exec('ROLLBACK'); $out['state']='completed_schema_only'; $out['error_code']='audit_schema_not_countable';
        } else {
            hmtv2_require(in_array($cm['created_at'],['timestamp','datetime'],true),'created_at_type');
            $starts=['conservative'=>'2026-09-16 21:00:00','plus02'=>'2026-09-16 22:00:00','utc'=>'2026-09-17 00:00:00'];
            $windows=[];
            foreach($starts as $label=>$start){
                $rows=hmtv2_query($db,"SELECT provider,method,COUNT(*) AS attempts,MIN(created_at) AS first_at,MAX(created_at) AS last_at FROM api_invocation_audit WHERE LOWER(provider) LIKE '%tourvisor%' AND created_at>=? GROUP BY provider,method ORDER BY provider,method",[$start]);
                $windows[$label]=['start_db_literal'=>$start,'total'=>hmtv2_total($rows),'by_provider_method'=>$rows];
            }
            $hourly=hmtv2_query($db,"SELECT DATE_FORMAT(created_at,'%Y-%m-%d %H:00:00') AS hour_db,provider,method,COUNT(*) AS attempts FROM api_invocation_audit WHERE LOWER(provider) LIKE '%tourvisor%' AND created_at>='2026-09-16 18:00:00' GROUP BY hour_db,provider,method ORDER BY hour_db,provider,method");
            $used=$windows['conservative']['total']; $remaining=max(0,300-$used);
            $out+=['audit_table'=>$exact[0],'created_at_data_type'=>$cm['created_at'],'windows'=>$windows,'lookback_hourly'=>$hourly,'conservative_used'=>$used,'conservative_remaining'=>$remaining,'safe_to_spend_tourvisor_now'=>$used<300,'consistency_note'=>strtoupper((string)($exact[0]['ENGINE']??''))==='INNODB'?'transactional_snapshot_table':'read_only_count_non_innodb_or_view_conservative_window','read_at_utc'=>gmdate('c'),'transaction'=>'REPEATABLE READ / READ ONLY'];
            $db->exec('ROLLBACK'); $out['state']='completed_read_only';
        }
    } catch(Throwable $e){ if($db instanceof PDO&&$db->inTransaction())$db->rollBack(); $out['error_phase']=$phase; $m=$e->getMessage(); $out['error_code']=preg_match('/^[a-z_]{3,100}$/D',$m)?$m:'sanitized_failure'; }
    while(ob_get_level())ob_end_clean();
    $digest=hmtv2_write($dir.'/result.json',$out); hmtv2_write($dir.'/receipt.json',['operation_id'=>HMTV2_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$digest,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0]);
    echo hmtv2_json(['state'=>$out['state'],'result_sha256'=>$digest,'conservative_used'=>$out['conservative_used']??null,'conservative_remaining'=>$out['conservative_remaining']??null]);
    if(!in_array($out['state'],['completed_read_only','completed_schema_only'],true))exit(2);
}

if(in_array('--self-test',$argv??[],true)){
    hmtv2_require(hmtv2_total([['attempts'=>'4'],['attempts'=>7]])===11,'total_test');
    $p=tempnam(sys_get_temp_dir(),'hmtv2-'); hmtv2_require(is_string($p),'tmp'); unlink($p); $h=hmtv2_write($p,['ok'=>true]); hmtv2_require($h===hash_file('sha256',$p),'write_test'); unlink($p); echo "2 Tourvisor audit v2 self-tests PASS\n"; exit;
}
hmtv2_main();
