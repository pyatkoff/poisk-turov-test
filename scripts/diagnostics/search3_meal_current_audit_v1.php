<?php
/** Bounded current meal-catalogue inspection; existing DB, SELECT only. */
declare(strict_types=1);
const SM_AUDIT_OP='search3-meal-current-audit-3353-20260921-v1';
const SM_SOURCE='1514a9a240d99307c0d479193eac149bd29743d6';
const SM_TABLES=[
 'anytour_meal_plans'=>['id','code','name_ru','family_code','is_active'],
 'anytour_search_meal_provider_mappings_v1'=>['provider','scope_key','external_id','meal_plan_id','state','evidence_sha256'],
 'anytour_search_meal_memberships_v1'=>['anytour_hotel_id','meal_concept_id','meal_concept_revision','meal_plan_id','state','evidence_sha256'],
 'anytour_hotel_meal_concepts_v2'=>['id','anytour_hotel_id','local_key','name_ru','revision','is_active'],
 'anytour_hotel_stay_mappings_v2'=>['id','namespace','external_hotel_key','operator_key','kind','key_kind','external_key','anytour_hotel_id','meal_concept_id','state','evidence_sha256'],
 'anytour_stay_mappings'=>['id','namespace','external_hotel_key','operator_key','kind','key_kind','external_key','anytour_hotel_id','meal_id','state','evidence_sha256'],
 'catalog_meals'=>['id','code','name','name_ru','full_name'],
];
function sm_json(array $x): string {return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function sm_save(string $path,array $value): void {
 $h=fopen($path,'x');if(!$h)throw new RuntimeException('EXCLUSIVE_OUTPUT_EXISTS');
 try{$b=sm_json($value);if(fwrite($h,$b)!==strlen($b)||!fflush($h))throw new RuntimeException('OUTPUT_WRITE_FAILED');if(function_exists('fsync')&&!fsync($h))throw new RuntimeException('OUTPUT_SYNC_FAILED');}finally{fclose($h);}
}
function sm_read(PDO $db,string $sql,array $args=[]): array {
 if(!str_starts_with($sql,'SELECT ')||str_contains($sql,';'))throw new RuntimeException('SELECT_ONLY');
 $s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);
}
function sm_projection(string $table,array $columns): array {
 if(!isset(SM_TABLES[$table]))throw new RuntimeException('TABLE_NOT_ALLOWED');
 return array_values(array_intersect(SM_TABLES[$table],$columns));
}
function sm_snapshot(PDO $db): array {
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$db->inTransaction())throw new RuntimeException('DEDICATED_MYSQL_REQUIRED');
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
 try{
  $slots=implode(',',array_fill(0,count(SM_TABLES),'?'));
  $meta=sm_read($db,'SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$slots.') ORDER BY TABLE_NAME,ORDINAL_POSITION',array_keys(SM_TABLES));
  $schema=[];foreach($meta as $m)$schema[$m['TABLE_NAME']][]=$m;
  $tables=[];
  foreach(SM_TABLES as $table=>$wanted){
   if(!isset($schema[$table])){$tables[$table]=['state'=>'absent'];continue;}
   $cols=array_column($schema[$table],'COLUMN_NAME');$selected=sm_projection($table,$cols);
   $total=(int)sm_read($db,'SELECT COUNT(*) AS n FROM `'.$table.'`')[0]['n'];
   $item=['state'=>'present','total_rows'=>$total,'columns'=>$schema[$table],'missing_projected_columns'=>array_values(array_diff($wanted,$selected))];
   $where='';$args=[];$limit=1001;
   if(in_array($table,['anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2','anytour_stay_mappings','anytour_search_meal_memberships_v1'],true)){
    if(!in_array('anytour_hotel_id',$cols,true)){$item['rows_state']='scope_column_missing';$tables[$table]=$item;continue;}
    $where=' WHERE anytour_hotel_id=?';$args=[4234];$item['row_scope']=['anytour_hotel_id'=>4234];
    if(in_array('kind',$cols,true)){$where.=' AND kind=?';$args[]='meal';}
   }else{$item['row_scope']='all';$limit=$table==='anytour_search_meal_provider_mappings_v1'?10001:1001;}
   if($selected){
    $order=implode(',',array_map(fn($c)=>'`'.$c.'`',array_slice($selected,0,3)));
    $rows=sm_read($db,'SELECT '.implode(',',array_map(fn($c)=>'`'.$c.'`',$selected)).' FROM `'.$table.'`'.$where.' ORDER BY '.$order.' LIMIT '.$limit,$args);
    $item['rows_truncated']=count($rows)===$limit;if($item['rows_truncated'])array_pop($rows);
    $item['rows']=$rows;$item['captured_rows']=count($rows);$item['rows_sha256']=hash('sha256',sm_json($rows));
   }
   $group=array_values(array_intersect(['provider','scope_key','namespace','kind','key_kind','state','is_active'],$cols));
   if($group){$g=implode(',',array_map(fn($c)=>'`'.$c.'`',$group));$item['counts']=sm_read($db,'SELECT '.$g.',COUNT(*) AS n FROM `'.$table.'` GROUP BY '.$g.' ORDER BY '.$g.' LIMIT 1001');$item['counts_truncated']=count($item['counts'])===1001;}
   $tables[$table]=$item;
  }
  // Metadata only: find any earlier meal-ID relation without reading arbitrary tables.
  $discovered=sm_read($db,"SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'anytour\\_%' OR TABLE_NAME LIKE 'catalog\\_%' OR TABLE_NAME LIKE 'anex\\_%' OR TABLE_NAME LIKE 'andromeda\\_%') AND (COLUMN_NAME IN ('local_id_meal','tourvisor_id_meal','samo_id_meal','anex_id_meal') OR TABLE_NAME LIKE '%meal%') ORDER BY TABLE_NAME,ORDINAL_POSITION LIMIT 501");
  $db->rollBack();return ['tables'=>$tables,'meal_schema_discovery'=>$discovered,'discovery_truncated'=>count($discovered)===501];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function sm_self_test(): void {
 if(sm_projection('anytour_meal_plans',['id','password','name_ru'])!==['id','name_ru'])throw new RuntimeException('PROJECTION_FAILURE');
 try{sm_projection('leads',['id']);throw new LogicException('TABLE_ALLOWED');}catch(RuntimeException $e){if($e->getMessage()!=='TABLE_NOT_ALLOWED')throw $e;}
 $db=new class extends PDO{public function __construct(){}};
 foreach(['INSERT INTO anytour_meal_plans VALUES(1)','SELECT 1; SELECT 2'] as $q){try{sm_read($db,$q);throw new LogicException('QUERY_ALLOWED');}catch(RuntimeException $e){if($e->getMessage()!=='SELECT_ONLY')throw $e;}}
 echo "MEAL_CURRENT_AUDIT_SELF_TEST_OK\n";
}
function sm_main(array $argv): int {
 if(PHP_SAPI!=='cli')return 1;
 if($argv===['--self-test']){sm_self_test();return 0;}
 if($argv!==['--execute'])throw new RuntimeException('EXPLICIT_EXECUTION_REQUIRED');
 $dir=realpath((string)getenv('SEARCH3_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));$source=(string)getenv('SEARCH3_SOURCE_SHA');
 if(!$dir||!$root||basename($dir)!==SM_AUDIT_OP||str_starts_with($dir,$root.'/')||!preg_match('/^[a-f0-9]{40}$/D',$source))throw new RuntimeException('OPERATION_SCOPE');
 $reservation=json_decode((string)file_get_contents($dir.'/payload/reservation.json'),true,16,JSON_THROW_ON_ERROR);
 if(($reservation['operation']??null)!==SM_AUDIT_OP||($reservation['source_sha']??null)!==$source||($reservation['script_sha256']??null)!==hash_file('sha256',__FILE__))throw new RuntimeException('RESERVATION_MISMATCH');
 $base=['operation'=>SM_AUDIT_OP,'source_sha'=>$source,'candidate_source_sha'=>SM_SOURCE,'at_utc'=>gmdate('c'),'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'site_file_writes'=>0,'no_replay'=>true];
 sm_save($dir.'/started.json',$base+['state'=>'started_before_db']);
 try{
  $files=[];foreach(['data/db-v1.php','data/anytour-search-meal-catalog-v1.php','_preview/search3-local-candidate/data/anytour-search-meal-catalog-v1.php','_preview/search3-local-candidate/data/search3-local-results-read-v1.php','_preview/search3-local-candidate/prototype-search/data.js','_preview/search3-local-candidate/prototype-search/app.js'] as $f){$p=$root.'/'.$f;$files[$f]=is_file($p)?['bytes'=>filesize($p),'sha256'=>hash_file('sha256',$p)]:null;}
  ob_start();try{require_once $root.'/data/db-v1.php';$db=v2_data_db();}finally{ob_end_clean();}
  $snapshot=sm_snapshot($db);$result=$base+$snapshot+['state'=>'completed_read_only','runtime_files'=>$files];
  sm_save($dir.'/result.json',$result);sm_save($dir.'/receipt.json',$base+['state'=>'completed_read_only','result_sha256'=>hash_file('sha256',$dir.'/result.json')]);
  echo sm_json(['operation'=>SM_AUDIT_OP,'state'=>'completed_read_only']);return 0;
 }catch(Throwable $e){$code=preg_match('/^[A-Z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'READ_ONLY_INSPECTION_FAILED';sm_save($dir.'/receipt.json',$base+['state'=>'failed_read_only','error_code'=>$code,'error_class'=>get_class($e)]);fwrite(STDERR,$code."\n");return 1;}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(sm_main(array_slice($argv,1)));
