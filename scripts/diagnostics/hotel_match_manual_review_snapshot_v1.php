<?php
declare(strict_types=1);
// MATCH-only consistent read. No provider access and no database mutation.
const OP = 'hotel-match-manual-review-snapshot-1971-20260921-v1';
const PROJECTIONS = [
 'catalog_hotels' => ['id','name','country_id','country_name','region_id','region_name','subregion_id','subregion_name','category','is_active','latitude','longitude','lat','lon','lng','hotel_url','website','url'],
 'andromeda_hotel_identities' => ['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json','created_at'],
 'anex_hotel_decisions' => ['anex_hotel_id','decision_status','catalog_hotel_id','decision_note','decided_at','updated_at'],
];
const CAPS = ['catalog_hotels'=>100000,'andromeda_hotel_identities'=>50000,'anex_hotel_decisions'=>50000];
function need(bool $v, string $reason): void { if (!$v) throw new RuntimeException($reason); }
function savej(string $path, array $data): string {
 $body=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
 $f=fopen($path,'xb'); need(is_resource($f),'exclusive_file');
 need(fwrite($f,$body)===strlen($body)&&fflush($f),'file_write');
 if(function_exists('fsync')) need(fsync($f),'file_sync'); fclose($f);
 $h=hash('sha256',$body); need(hash_file('sha256',$path)===$h,'file_readback'); return $h;
}
function ro(PDO $db,string $sql): array {
 need(preg_match('/^(?:SELECT |SHOW COLUMNS FROM )/D',$sql)===1,'read_only_sql');
 $s=$db->query($sql);need($s!==false,'read_query');return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function columns(string $table,array $available): array {
 need(isset(PROJECTIONS[$table]),'table_allowlist');
 return array_values(array_intersect(PROJECTIONS[$table],$available));
}
function snapshot(PDO $db,string $dir,string $sha): array {
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 try {
  $names=array_column(ro($db,"SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('catalog_hotels','andromeda_hotel_identities','anex_hotel_decisions')"),'table_name');
  need(in_array('catalog_hotels',$names,true)&&in_array('andromeda_hotel_identities',$names,true),'required_tables');
  $manifest=[];
  foreach(PROJECTIONS as $table=>$wanted) {
   if(!in_array($table,$names,true)){$manifest[$table]=['state'=>'absent_optional','rows'=>0];continue;}
   $schema=ro($db,'SHOW COLUMNS FROM `'.$table.'`'); $selected=columns($table,array_column($schema,'Field'));
   need(count($selected)>1,'empty_projection');
   $total=(int)ro($db,'SELECT COUNT(*) AS total FROM `'.$table.'`')[0]['total'];
   $fields=implode(',',array_map(fn($c)=>'`'.$c.'`',$selected));
   $order=$table==='catalog_hotels'?'`id`':($table==='andromeda_hotel_identities'?'`supplier_namespace`,`external_hotel_id`':'`anex_hotel_id`');
   $rows=ro($db,'SELECT '.$fields.' FROM `'.$table.'` ORDER BY '.$order.' LIMIT '.CAPS[$table]);
   $doc=['operation'=>OP,'source_sha'=>$sha,'state'=>'read_only_table_snapshot','table'=>$table,'columns'=>$selected,'missing_columns'=>array_values(array_diff($wanted,$selected)),'total_rows'=>$total,'captured_rows'=>count($rows),'truncated'=>$total>count($rows),'rows'=>$rows];
   $h=savej($dir.'/'.$table.'.json',$doc);
   $manifest[$table]=['state'=>'captured','total_rows'=>$total,'rows'=>count($rows),'truncated'=>$total>count($rows),'sha256'=>$h,'columns'=>$selected];
  }
  $db->rollBack();
  return ['operation'=>OP,'source_sha'=>$sha,'state'=>'completed_read_only','snapshot_at_utc'=>gmdate('c'),'tables'=>$manifest,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
 } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}
if(defined('MATCH_UNIT_TEST'))return;
need(PHP_SAPI==='cli','cli_only');
$mode=$argv[1]??'';
if($mode==='--self-test'){
 need(columns('catalog_hotels',['name','id','password'])===['id','name'],'allowlisted_columns');
 need(!in_array('decided_by',PROJECTIONS['anex_hotel_decisions'],true),'omit_actor');
 try{columns('customers',['id']);throw new LogicException('expected_rejection');}catch(RuntimeException $e){need($e->getMessage()==='table_allowlist','bad_table');}
 echo "MATCH_MANUAL_REVIEW_SELFTEST_OK\n";exit;
}
need($mode==='--execute','disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$sha=(string)getenv('MATCH_SOURCE_SHA');
need(is_string($root)&&basename($root)==='anytoour.ru'&&is_string($dir)&&basename($dir)===OP&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'scope');
$r=json_decode((string)file_get_contents($dir.'/payload/reservation.json'),true,512,JSON_THROW_ON_ERROR);
need($r['operation']===OP&&$r['state']==='reserved_before_db'&&$r['source_sha']===$sha&&$r['script_sha256']===hash_file('sha256',__FILE__),'reservation');
savej($dir.'/started.json',['operation'=>OP,'state'=>'started_read_only_no_replay','source_sha'=>$sha]);
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
try {
 $out=snapshot(v2_data_db(),$dir,$sha);$h=savej($dir.'/result.json',$out);
 savej($dir.'/receipt.json',['operation'=>OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$h,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
 echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){savej($dir.'/failure.json',['operation'=>OP,'state'=>'failed_read_only_no_replay','error_class'=>get_class($e),'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,"MATCH_SNAPSHOT_FAILED_NO_REPLAY\n");exit(1);}
