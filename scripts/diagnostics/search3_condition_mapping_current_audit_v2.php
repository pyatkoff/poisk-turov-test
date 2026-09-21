<?php
declare(strict_types=1);
const OP='search3-condition-mapping-current-audit-3353-20260921-v2';
const CANDIDATE='1514a9a240d99307c0d479193eac149bd29743d6';
function j(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function save(string $p,array $x):void{$h=fopen($p,'x');if(!$h)throw new RuntimeException('OUTPUT_EXISTS');$b=j($x);if(fwrite($h,$b)!==strlen($b)||!fflush($h))throw new RuntimeException('OUTPUT_WRITE');fclose($h);}
function rows(PDO $db,string $sql,array $args=[]):array{if(!str_starts_with($sql,'SELECT ')||str_contains($sql,';'))throw new RuntimeException('SELECT_ONLY');$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);}
function snapshot(PDO $db):array{
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$db->inTransaction())throw new RuntimeException('DEDICATED_MYSQL_REQUIRED');
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
 try{
  $exists=(int)rows($db,"SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anytour_stay_mappings'")[0]['n']===1;
  $mealRows=$exists?rows($db,"SELECT id,namespace,HEX(external_hotel_key) external_hotel_key_hex,HEX(operator_key) operator_key_hex,key_kind,CONVERT(external_key USING utf8mb4) external_key,anytour_hotel_id,meal_id,state,evidence_ref,evidence_sha256,reviewed_by FROM anytour_stay_mappings WHERE kind='meal' ORDER BY id LIMIT 1001"):[];
  $meta=rows($db,"SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((LOWER(TABLE_NAME) REGEXP 'meal|resort|region|subregion|destination') OR LOWER(COLUMN_NAME) REGEXP 'local.*(meal|resort|region)|tourvisor.*(meal|resort|region)|samo.*(meal|resort|region)|anex.*(meal|resort|region)|resort|subregion') ORDER BY TABLE_NAME,ORDINAL_POSITION LIMIT 2001");
  $tables=[];foreach($meta as $m)$tables[$m['TABLE_NAME']][]=$m;
  $files=[];$root=realpath((string)getenv('ANYTOUR_ROOT'));
  foreach(['/_preview/search3-local-candidate/prototype-search/app.js','/_preview/search3-local-candidate/prototype-search/data.js','/_preview/search3-local-candidate/data/search3-local-results-read-v1.php'] as $f){$p=$root.$f;$files[$f]=is_file($p)?['bytes'=>filesize($p),'sha256'=>hash_file('sha256',$p)]:null;}
  $db->rollBack();
  return ['stay_mapping_table_present'=>$exists,'meal_mapping_rows'=>$mealRows,'meal_mapping_rows_truncated'=>count($mealRows)===1001,'mapping_counts'=>array_count_values(array_map(fn($r)=>$r['namespace'].'|'.$r['key_kind'].'|'.$r['state'],$mealRows)),'dictionary_schema'=>$tables,'schema_discovery_truncated'=>count($meta)===2001,'runtime_files'=>$files];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function selftest():void{echo "CONDITION_MAPPING_AUDIT_SELF_TEST_OK\n";}
function main(array $argv):int{
 if($argv===['--self-test']){selftest();return 0;}if($argv!==['--execute'])throw new RuntimeException('EXPLICIT_EXECUTION_REQUIRED');
 $dir=realpath((string)getenv('SEARCH3_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));$sha=(string)getenv('SEARCH3_SOURCE_SHA');
 if(!$dir||!$root||basename($dir)!==OP||str_starts_with($dir,$root.'/')||!preg_match('/^[a-f0-9]{40}$/D',$sha))throw new RuntimeException('OP_SCOPE');
 $reservation=json_decode((string)file_get_contents($dir.'/payload/reservation.json'),true,16,JSON_THROW_ON_ERROR);
 if(($reservation['operation']??null)!==OP||($reservation['source_sha']??null)!==$sha||($reservation['script_sha256']??null)!==hash_file('sha256',__FILE__))throw new RuntimeException('RESERVATION');
 $base=['operation'=>OP,'source_sha'=>$sha,'candidate_source_sha'=>CANDIDATE,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'site_file_writes'=>0,'no_replay'=>true,'at_utc'=>gmdate('c')];
 save($dir.'/started.json',$base+['state'=>'started_before_db']);
 try{ob_start();try{require_once $root.'/data/db-v1.php';$db=v2_data_db();}finally{ob_end_clean();}$result=$base+snapshot($db)+['state'=>'completed_read_only'];save($dir.'/result.json',$result);save($dir.'/receipt.json',$base+['state'=>'completed_read_only','result_sha256'=>hash_file('sha256',$dir.'/result.json')]);echo j(['operation'=>OP,'state'=>'completed_read_only']);return 0;}
 catch(Throwable $e){save($dir.'/receipt.json',$base+['state'=>'failed_read_only','error_code'=>preg_match('/^[A-Z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'READ_FAILED']);return 1;}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(main(array_slice($argv,1)));
