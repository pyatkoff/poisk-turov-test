<?php
declare(strict_types=1);
// READ ONLY continuation of the truncated catalog export; never reread its prefix.
const OP = 'hotel-match-catalog-tail-1971-20260921-v1';
const CURSOR_ID = 126145;
const CAP = 50000;
const COLS = ['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active','latitude','longitude'];
const PRIOR_RESULT = '8eb7327107a2759349ab27f293f9bfa5e734b62364e0d1cc1ddaf64e5ba688d3';
function need(bool $v,string $m):void { if (!$v) throw new RuntimeException($m); }
function readj(string $p):array { $r=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR); need(is_array($r),'json'); return $r; }
function savej(string $p,array $v):string {
 $b=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
 $h=fopen($p,'xb'); need(is_resource($h),'exclusive_output');
 need(fwrite($h,$b)===strlen($b)&&fflush($h),'write');
 if(function_exists('fsync')) need(fsync($h),'fsync'); fclose($h);
 need(hash_file('sha256',$p)===hash('sha256',$b),'disk_readback');return hash('sha256',$b);
}
function rows(PDO $db,string $sql,array $params=[]):array { $s=$db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function validateRows(array $rs,int $total):void {
 need(count($rs)===min($total,CAP),'count');$last=CURSOR_ID;
 foreach($rs as $r){need(array_keys($r)===COLS,'projection');need((int)$r['id']>$last,'cursor_order');$last=(int)$r['id'];}
}
function collect(PDO $db):array {
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 try {
  $count=rows($db,'SELECT COUNT(*) AS n FROM catalog_hotels WHERE id>?',[CURSOR_ID]);
  need(count($count)===1&&isset($count[0]['n']),'count_row');$total=(int)$count[0]['n'];need($total>=0,'count_negative');
  $rs=rows($db,'SELECT '.implode(',',COLS).' FROM catalog_hotels WHERE id>? ORDER BY id LIMIT '.CAP,[CURSOR_ID]);
  validateRows($rs,$total);$db->rollBack();
  return ['cursor_exclusive'=>CURSOR_ID,'limit'=>CAP,'tail_total_rows'=>$total,'captured_rows'=>count($rs),'truncated'=>$total>CAP,'exhausted'=>$total<=CAP,'columns'=>COLS,'rows'=>$rs];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function selfTest():void {
 $r=array_fill_keys(COLS,null);$r['id']=CURSOR_ID+1;validateRows([$r],1);
 foreach([[$r,$r], [array_replace($r,['id'=>CURSOR_ID])], [array_diff_key($r,['name'=>true])]] as $bad){$failed=false;try{validateRows($bad,count($bad));}catch(RuntimeException){$failed=true;}need($failed,'invalid_rows_allowed');}
 validateRows([],0);echo "MATCH_CATALOG_TAIL_SELFTEST_OK\n";
}
if(defined('MATCH_UNIT_TEST'))return;
need(PHP_SAPI==='cli','cli_only');$mode=$argv[1]??'';
if($mode==='--self-test'){selfTest();exit;}need($mode==='--execute','disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$sha=(string)getenv('MATCH_SOURCE_SHA');
need(is_string($root)&&basename($root)==='anytoour.ru'&&is_string($dir)&&basename($dir)===OP&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'scope');
$res=readj(__DIR__.'/reservation.json');need($res['operation']===OP&&$res['source_sha']===$sha&&$res['script_sha256']===hash_file('sha256',__FILE__)&&$res['state']==='reserved_before_db'&&$res['cursor_exclusive']===CURSOR_ID,'reservation');
savej($dir.'/started.json',['operation'=>OP,'source_sha'=>$sha,'state'=>'started_no_replay']);
$base=['operation'=>OP,'source_sha'=>$sha,'prior_snapshot_result_sha256'=>PRIOR_RESULT,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
try {
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
 $at=gmdate('c');$out=$base+['state'=>'completed_read_only','snapshot_at_utc'=>$at]+collect(v2_data_db());
 $digest=savej($dir.'/result.json',$out);
 savej($dir.'/receipt.json',$base+['state'=>'completed_read_only','result_sha256'=>$digest,'captured_rows'=>$out['captured_rows'],'tail_total_rows'=>$out['tail_total_rows'],'truncated'=>$out['truncated']]);
 echo json_encode(['state'=>$out['state'],'rows'=>$out['captured_rows'],'tail_total'=>$out['tail_total_rows'],'truncated'=>$out['truncated'],'result_sha256'=>$digest],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){savej($dir.'/failure.json',$base+['state'=>'failed_read_only_no_replay','error_class'=>get_class($e)]);fwrite(STDERR,"MATCH_CATALOG_TAIL_FAILED_NO_REPLAY\n");exit(1);}
