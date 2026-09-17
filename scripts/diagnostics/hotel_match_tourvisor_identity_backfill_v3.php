<?php
declare(strict_types=1);

const OP='hotel-match-tourvisor-identity-backfill-1971-20260917-v3';
function req($v,$m){if(!$v)throw new RuntimeException($m);} 
function expectedSql():string{return "SELECT COUNT(*) FROM (SELECT p.hotel_id,p.operator_id FROM tour_price_observations p JOIN catalog_hotels c ON c.id=p.hotel_id WHERE p.operator_id IS NOT NULL AND p.operator_id>0 GROUP BY p.hotel_id,p.operator_id) x";}
function insertSql():string{return <<<'SQL'
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
function w(string $p,array $v):string{$j=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";req(file_put_contents($p,$j,LOCK_EX)===strlen($j),'write_failed');return hash('sha256',$j);}
function selftest():void{req(str_contains(insertSql(),"SHA2(CONCAT('tourvisor|'"),'fingerprint');req(str_contains(insertSql(),'ON DUPLICATE KEY UPDATE'),'upsert');req(str_contains(expectedSql(),'GROUP BY p.hotel_id,p.operator_id'),'expected');req(!preg_match('/https?:\/\//i',insertSql()),'network');echo "hotel_match_tourvisor_identity_backfill_v3 self-test: PASS\n";}
if(in_array('--self-test',$argv??[],true)){selftest();exit(0);} 

$sha=(string)getenv('MATCH_SOURCE_SHA');req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===OP&&preg_match('/^[a-f0-9]{40}$/D',$sha),'operation_guard');
$dir=(string)getenv('HOME').'/.anytoour-match/operations/'.OP;$rv=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);req(($rv['state']??'')==='reserved_before_db_access'&&($rv['source_sha']??'')===$sha,'reservation');
$o=['operation_id'=>OP,'source_sha'=>$sha,'state'=>'failed_no_write','committed'=>false,'rows_before'=>null,'expected_hotel_operator_pairs'=>null,'rows_after'=>null,'covered_pairs_readback'=>null,'duplicate_pair_groups'=>null,'linkless_rows'=>null,'native_enriched_rows'=>null,'database_identity_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'no_replay'=>true];
$db=null;$locked=false;$committed=false;
try{
 $root=realpath(getcwd());req(is_string($root)&&basename($root)==='anytoour.ru','root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $locked=(int)$db->query("SELECT GET_LOCK('anytour_match_tv_identity_backfill_v3',0)")->fetchColumn()===1;req($locked,'named_lock');
 $cols=$db->query("SELECT COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations'")->fetchAll(PDO::FETCH_ASSOC);$cm=[];foreach($cols as$c)$cm[(string)$c['COLUMN_NAME']]=(string)$c['IS_NULLABLE'];foreach(['fingerprint','hotel_id','operator_id','native_id_type','native_id_value','native_id_conflict']as$c)req(isset($cm[$c]),'schema_'.$c);req(($cm['tour_id']??'')==='YES'&&($cm['operator_link']??'')==='YES','nullable_schema');
 req((int)$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations' AND INDEX_NAME='uq_operator_identity_hotel_operator' AND NON_UNIQUE=0")->fetchColumn()>=1,'unique_pair');
 $before=(int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations")->fetchColumn();$o['rows_before']=$before;req($before===0,'rows_before_changed');
 req((int)$db->query("SELECT COUNT(*) FROM (SELECT hotel_id,operator_id FROM tour_operator_identity_observations GROUP BY hotel_id,operator_id HAVING COUNT(*)>1) x")->fetchColumn()===0,'duplicates_before');
 $db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();
 try{$expected=(int)$db->query(expectedSql())->fetchColumn();$o['expected_hotel_operator_pairs']=$expected;req($expected>0,'empty_expected');$affected=$db->exec(insertSql());$db->commit();$committed=true;$o['committed']=true;$o['database_identity_writes']=(int)$affected;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 $after=(int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations")->fetchColumn();$covered=(int)$db->query("SELECT COUNT(*) FROM (SELECT p.hotel_id,p.operator_id FROM tour_price_observations p JOIN catalog_hotels c ON c.id=p.hotel_id JOIN tour_operator_identity_observations i ON i.fingerprint=SHA2(CONCAT('tourvisor|',p.hotel_id,'|',p.operator_id),256) WHERE p.operator_id IS NOT NULL AND p.operator_id>0 GROUP BY p.hotel_id,p.operator_id) x")->fetchColumn();$dups=(int)$db->query("SELECT COUNT(*) FROM (SELECT hotel_id,operator_id FROM tour_operator_identity_observations GROUP BY hotel_id,operator_id HAVING COUNT(*)>1) x")->fetchColumn();$linkless=(int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations WHERE operator_link IS NULL")->fetchColumn();$native=(int)$db->query("SELECT COUNT(*) FROM tour_operator_identity_observations WHERE native_id_value IS NOT NULL")->fetchColumn();$o['rows_after']=$after;$o['covered_pairs_readback']=$covered;$o['duplicate_pair_groups']=$dups;$o['linkless_rows']=$linkless;$o['native_enriched_rows']=$native;req($after>=$o['expected_hotel_operator_pairs'],'rows_after_short');req($covered>=$o['expected_hotel_operator_pairs'],'coverage_short');req($dups===0,'duplicates_after');$o['state']='completed';$o['read_at_utc']=gmdate('c');
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$o['state']=$committed?'committed_needs_reconcile':'failed_no_write';$o['error_code']=preg_match('/^[a-z0-9_]{2,120}$/Di',$e->getMessage())?$e->getMessage():'sanitized_failure';$o['read_at_utc']=gmdate('c');}
finally{if($db instanceof PDO&&$locked){try{$db->query("SELECT RELEASE_LOCK('anytour_match_tv_identity_backfill_v3')");}catch(Throwable $ignore){}}}
$h=w($dir.'/result.json',$o);w($dir.'/receipt.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>$o['state'],'committed'=>$o['committed'],'result_sha256'=>$h,'readback_verified'=>$o['state']==='completed','database_identity_writes'=>$o['database_identity_writes'],'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_andromeda_calls'=>0,'no_replay'=>true]);echo json_encode($o+['result_sha256'=>$h],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit($o['state']==='completed'?0:($o['state']==='committed_needs_reconcile'?3:2));
