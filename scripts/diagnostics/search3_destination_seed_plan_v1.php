<?php
declare(strict_types=1);
const OP='search3-destination-seed-plan-20260921-v1';
function out(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function save(string $p,array $x):void{$h=fopen($p,'x');if(!$h)throw new RuntimeException('OUTPUT_EXISTS');$b=out($x);if(fwrite($h,$b)!==strlen($b)||!fflush($h))throw new RuntimeException('OUTPUT_WRITE');fclose($h);}
function q(PDO $db,string $sql,array $a=[]):array{if(!str_starts_with($sql,'SELECT ')||str_contains($sql,';'))throw new RuntimeException('SELECT_ONLY');$s=$db->prepare($sql);$s->execute($a);return$s->fetchAll(PDO::FETCH_ASSOC);}
function plan(PDO $db):array{
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$db->inTransaction())throw new RuntimeException('DEDICATED_MYSQL_REQUIRED');
 $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
 try{
  $required=['catalog_countries','catalog_regions','catalog_subregions'];$slots=implode(',',array_fill(0,3,'?'));
  $present=array_column(q($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$slots.') ORDER BY TABLE_NAME',$required),'TABLE_NAME');
  if(count($present)!==3)throw new RuntimeException('SOURCE_CATALOG_INCOMPLETE');
  $countries=q($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id LIMIT 1001');
  $regions=q($db,'SELECT id,country_id,name FROM catalog_regions WHERE is_active=1 ORDER BY id LIMIT 5001');
  $subs=q($db,'SELECT s.id,s.region_id,s.name,r.country_id FROM catalog_subregions s JOIN catalog_regions r ON r.id=s.region_id WHERE s.is_active=1 AND r.is_active=1 ORDER BY s.id LIMIT 10001');
  if(count($countries)===1001||count($regions)===5001||count($subs)===10001)throw new RuntimeException('SOURCE_CATALOG_LIMIT');
  $countryIds=[];$regionIds=[];$errors=[];$rows=[];
  foreach($countries as $r){$id=(int)$r['id'];if($id<1||isset($countryIds[$id]))$errors[]='country-id:'.$id;$countryIds[$id]=true;$rows[]=['key'=>'country:'.$id,'kind'=>'country','parentKey'=>null,'nameRu'=>(string)$r['name'],'slug'=>'tourvisor-country-'.$id,'tourvisorId'=>(string)$id];}
  foreach($regions as $r){$id=(int)$r['id'];$parent=(int)$r['country_id'];if($id<1||isset($regionIds[$id]))$errors[]='region-id:'.$id;if(!isset($countryIds[$parent]))$errors[]='region-orphan:'.$id;$regionIds[$id]=true;$rows[]=['key'=>'region:'.$id,'kind'=>'region','parentKey'=>'country:'.$parent,'nameRu'=>(string)$r['name'],'slug'=>'tourvisor-region-'.$id,'tourvisorId'=>(string)$id];}
  $subIds=[];foreach($subs as $r){$id=(int)$r['id'];$parent=(int)$r['region_id'];if($id<1||isset($subIds[$id]))$errors[]='subregion-id:'.$id;if(!isset($regionIds[$parent]))$errors[]='subregion-orphan:'.$id;$subIds[$id]=true;$rows[]=['key'=>'subregion:'.$id,'kind'=>'subregion','parentKey'=>'region:'.$parent,'nameRu'=>(string)$r['name'],'slug'=>'tourvisor-subregion-'.$id,'tourvisorId'=>(string)$id];}
  $turkey=array_values(array_filter($rows,fn($x)=>$x['key']==='country:4'||($x['kind']==='region'&&in_array((int)substr($x['parentKey'],8),[4],true))||($x['kind']==='subregion'&&in_array((int)($regions[array_search((int)substr($x['parentKey'],7),array_map(fn($r)=>(int)$r['id'],$regions),true)]['country_id']??0),[4],true))));
  $names=['Белек','Сиде','Кемер'];$visible=[];foreach($rows as $x)if(in_array($x['nameRu'],$names,true))$visible[]=$x;
  $existing=(int)q($db,"SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anytour_destinations_v1','anytour_destination_sources_v1')")[0]['n'];
  $db->rollBack();
  return['source'=>'current-tourvisor-synchronized-catalog','sourceCounts'=>['countries'=>count($countries),'regions'=>count($regions),'subregions'=>count($subs)],'plannedRows'=>count($rows),'plannedBridges'=>count($rows),'errors'=>$errors,'safeToApply'=>$errors===[]&&$existing===0,'destinationTablesPresent'=>$existing,'turkeyRows'=>count($turkey),'ownerVisibleResorts'=>$visible,'planSha256'=>hash('sha256',out($rows)),'planPreview'=>array_slice($rows,0,20)];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function main(array $argv):int{if($argv===['--self-test']){echo "DESTINATION_SEED_PLAN_SELF_TEST_OK\n";return 0;}if($argv!==['--execute'])throw new RuntimeException('EXPLICIT_EXECUTION_REQUIRED');$dir=realpath((string)getenv('SEARCH3_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));$sha=(string)getenv('SEARCH3_SOURCE_SHA');if(!$dir||!$root||basename($dir)!==OP||str_starts_with($dir,$root.'/'))throw new RuntimeException('OP_SCOPE');$base=['operation'=>OP,'source_sha'=>$sha,'state'=>'started','database_writes'=>0,'mapping_writes'=>0,'provider_calls'=>0,'site_file_writes'=>0,'at_utc'=>gmdate('c')];save($dir.'/started.json',$base);try{require_once $root.'/data/db-v1.php';$r=$base+plan(v2_data_db())+['state'=>'completed_read_only'];save($dir.'/result.json',$r);save($dir.'/receipt.json',$base+['state'=>'completed_read_only','result_sha256'=>hash_file('sha256',$dir.'/result.json')]);echo out(['state'=>'completed_read_only']);return 0;}catch(Throwable $e){save($dir.'/receipt.json',$base+['state'=>'failed_read_only','error_code'=>$e->getMessage()]);return 1;}}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(main(array_slice($argv,1)));
