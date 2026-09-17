<?php
declare(strict_types=1);

const OP='hotel-match-tourvisor-identity-backfill-sql-contract-1971-20260917-v1';
function req($v,string $m):void{if(!$v)throw new RuntimeException($m);}
function q(PDO $db,string $sql):array{$r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);req(count($r)<=200,'row_budget');return $r;}
function write_json(string $p,array $v):string{$j=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";req(file_put_contents($p,$j,LOCK_EX)===strlen($j),'write_failed');return hash('sha256',$j);}
function original_sql():string{return <<<'SQL'
INSERT INTO tour_operator_identity_observations (
 fingerprint,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,
 hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,
 operator_link,operator_link_host,operator_link_path,operator_link_query,native_id_type,native_id_value,native_id_conflict
)
SELECT SHA2(CONCAT('tourvisor|',p.hotel_id,'|',p.operator_id),256),
 p.first_seen_at,p.last_seen_at,p.observation_count,'historical_backfill',NULL,
 c.country_id,c.region_id,c.subregion_id,c.id,c.name,c.region_name,c.subregion_name,c.latitude,c.longitude,
 p.operator_id,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0
FROM (
 SELECT hotel_id,operator_id,MIN(observed_at) first_seen_at,MAX(observed_at) last_seen_at,
        GREATEST(1,COUNT(DISTINCT search_id)) observation_count
 FROM tour_price_observations
 WHERE operator_id IS NOT NULL AND operator_id>0
 GROUP BY hotel_id,operator_id
) p
JOIN catalog_hotels c ON c.id=p.hotel_id
ON DUPLICATE KEY UPDATE
 first_seen_at=LEAST(first_seen_at,VALUES(first_seen_at)),
 last_seen_at=GREATEST(last_seen_at,VALUES(last_seen_at)),
 observation_count=GREATEST(observation_count,VALUES(observation_count)),
 country_id=VALUES(country_id),region_id=COALESCE(VALUES(region_id),region_id),subregion_id=COALESCE(VALUES(subregion_id),subregion_id),
 hotel_name=COALESCE(VALUES(hotel_name),hotel_name),region_name=COALESCE(VALUES(region_name),region_name),subregion_name=COALESCE(VALUES(subregion_name),subregion_name),
 latitude=COALESCE(VALUES(latitude),latitude),longitude=COALESCE(VALUES(longitude),longitude)
SQL;}
function qualified_sql():string{return str_replace([
 'LEAST(first_seen_at,VALUES(first_seen_at))','GREATEST(last_seen_at,VALUES(last_seen_at))','GREATEST(observation_count,VALUES(observation_count))',
 'COALESCE(VALUES(region_id),region_id)','COALESCE(VALUES(subregion_id),subregion_id)','COALESCE(VALUES(hotel_name),hotel_name)',
 'COALESCE(VALUES(region_name),region_name)','COALESCE(VALUES(subregion_name),subregion_name)','COALESCE(VALUES(latitude),latitude)','COALESCE(VALUES(longitude),longitude)'
],[
 'LEAST(tour_operator_identity_observations.first_seen_at,VALUES(first_seen_at))','GREATEST(tour_operator_identity_observations.last_seen_at,VALUES(last_seen_at))','GREATEST(tour_operator_identity_observations.observation_count,VALUES(observation_count))',
 'COALESCE(VALUES(region_id),tour_operator_identity_observations.region_id)','COALESCE(VALUES(subregion_id),tour_operator_identity_observations.subregion_id)','COALESCE(VALUES(hotel_name),tour_operator_identity_observations.hotel_name)',
 'COALESCE(VALUES(region_name),tour_operator_identity_observations.region_name)','COALESCE(VALUES(subregion_name),tour_operator_identity_observations.subregion_name)','COALESCE(VALUES(latitude),tour_operator_identity_observations.latitude)','COALESCE(VALUES(longitude),tour_operator_identity_observations.longitude)'
],original_sql());}
function prepare_only(PDO $db,string $sql,string $name):array{
 try{
  $db->exec('SET @match_prepare_sql='.$db->quote($sql));
  $db->exec('PREPARE '.$name.' FROM @match_prepare_sql');
  $db->exec('DEALLOCATE PREPARE '.$name);
  return ['ok'=>true,'sqlstate'=>'00000','driver_code'=>0];
 }catch(PDOException $e){$info=$e->errorInfo;return ['ok'=>false,'sqlstate'=>(string)($info[0]??$e->getCode()),'driver_code'=>(int)($info[1]??0)];}
}
function selftest():void{req(str_contains(original_sql(),'LEAST(first_seen_at,VALUES(first_seen_at))'),'original');req(str_contains(qualified_sql(),'tour_operator_identity_observations.first_seen_at'),'qualified');req(!preg_match('/https?:\/\//i',original_sql()),'network');echo "hotel_match_tourvisor_identity_backfill_sql_contract_v1 self-test: PASS\n";}
if(in_array('--self-test',$argv??[],true)){selftest();exit(0);}

$sha=(string)getenv('MATCH_SOURCE_SHA');req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===OP&&preg_match('/^[a-f0-9]{40}$/D',$sha),'operation_guard');
$dir=(string)getenv('HOME').'/.anytoour-match/operations/'.OP;$rv=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);req(($rv['state']??'')==='reserved_before_db_access'&&($rv['source_sha']??'')===$sha,'reservation');
$o=['operation_id'=>OP,'source_sha'=>$sha,'state'=>'failed_no_replay','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'no_replay'=>true];$db=null;
try{
 $root=realpath(getcwd());req(is_string($root)&&basename($root)==='anytoour.ru','root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 $o['server']=['version'=>(string)$db->query('SELECT VERSION()')->fetchColumn(),'sql_mode'=>(string)$db->query('SELECT @@SESSION.sql_mode')->fetchColumn()];
 $o['target_columns']=q($db,"SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations' ORDER BY ORDINAL_POSITION");
 $o['target_indexes']=q($db,"SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations' ORDER BY INDEX_NAME,SEQ_IN_INDEX");
 $o['rows_before']=(int)$db->query('SELECT COUNT(*) FROM tour_operator_identity_observations')->fetchColumn();
 $o['expected_hotel_operator_pairs']=(int)$db->query("SELECT COUNT(*) FROM (SELECT p.hotel_id,p.operator_id FROM tour_price_observations p JOIN catalog_hotels c ON c.id=p.hotel_id WHERE p.operator_id IS NOT NULL AND p.operator_id>0 GROUP BY p.hotel_id,p.operator_id) x")->fetchColumn();
 $o['source_stats']=$db->query("SELECT COUNT(*) pair_rows, SUM(country_id IS NULL) null_country, MAX(CHAR_LENGTH(hotel_name)) max_hotel_name, MAX(CHAR_LENGTH(region_name)) max_region_name, MAX(CHAR_LENGTH(subregion_name)) max_subregion_name, MAX(observation_count) max_observation_count FROM (SELECT c.country_id,c.name hotel_name,c.region_name,c.subregion_name,GREATEST(1,COUNT(DISTINCT p.search_id)) observation_count FROM tour_price_observations p JOIN catalog_hotels c ON c.id=p.hotel_id WHERE p.operator_id IS NOT NULL AND p.operator_id>0 GROUP BY p.hotel_id,p.operator_id,c.country_id,c.name,c.region_name,c.subregion_name) s")->fetch(PDO::FETCH_ASSOC);
 $sample=$db->query("SELECT SHA2(CONCAT('tourvisor|',p.hotel_id,'|',p.operator_id),256) fingerprint,p.hotel_id,p.operator_id,p.first_seen_at,p.last_seen_at,p.observation_count,c.country_id,c.region_id,c.subregion_id,c.name,c.region_name,c.subregion_name,c.latitude,c.longitude FROM (SELECT hotel_id,operator_id,MIN(observed_at) first_seen_at,MAX(observed_at) last_seen_at,GREATEST(1,COUNT(DISTINCT search_id)) observation_count FROM tour_price_observations WHERE operator_id IS NOT NULL AND operator_id>0 GROUP BY hotel_id,operator_id) p JOIN catalog_hotels c ON c.id=p.hotel_id ORDER BY p.hotel_id,p.operator_id LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);$o['source_projection_sample_count']=count($sample);
 $o['prepare_original']=prepare_only($db,original_sql(),'match_stmt_original');
 $o['prepare_qualified']=prepare_only($db,qualified_sql(),'match_stmt_qualified');
 $db->exec('ROLLBACK');$o['state']='completed_read_only';$o['read_at_utc']=gmdate('c');
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$o['error_code']=preg_match('/^[a-z0-9_]{2,120}$/Di',$e->getMessage())?$e->getMessage():'sanitized_failure';$o['read_at_utc']=gmdate('c');}
$h=write_json($dir.'/result.json',$o);write_json($dir.'/receipt.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>$o['state'],'result_sha256'=>$h,'readback_verified'=>$o['state']==='completed_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'no_replay'=>true]);echo json_encode(['state'=>$o['state'],'rows_before'=>$o['rows_before']??null,'expected_hotel_operator_pairs'=>$o['expected_hotel_operator_pairs']??null,'source_stats'=>$o['source_stats']??null,'prepare_original'=>$o['prepare_original']??null,'prepare_qualified'=>$o['prepare_qualified']??null,'server'=>$o['server']??null,'result_sha256'=>$h],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit($o['state']==='completed_read_only'?0:2);
