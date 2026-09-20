<?php
declare(strict_types=1);
/** Full retained user_search census. Never acquires suppliers or changes identities. */
const UC_OP='hotel-match-userseen-coverage-1971-20260920-v1';
const UC_CAP=50000;
function uc_need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function uc_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function uc_save(string $path,array $v):string{
 $bytes=uc_json($v);$f=fopen($path,'xb');uc_need(is_resource($f),'exclusive_output');
 try{uc_need(fwrite($f,$bytes)===strlen($bytes)&&fflush($f),'output_write');if(function_exists('fsync'))uc_need(fsync($f),'output_sync');}finally{fclose($f);}
 uc_need(file_get_contents($path)===$bytes,'output_readback');return hash('sha256',$bytes);
}
function uc_rows(PDO $db,string $sql,array $args=[]):array{$q=$db->prepare($sql);$q->execute($args);$r=$q->fetchAll(PDO::FETCH_ASSOC);uc_need(count($r)<=UC_CAP,'row_cap');return $r;}
function uc_id(mixed $v):int{uc_need((is_int($v)||is_string($v))&&preg_match('/^[1-9][0-9]{0,9}$/D',(string)$v)===1&&(float)$v<=2147483647,'hotel_id');return(int)$v;}
function uc_empty():array{return ['total'=>0,'both'=>0,'anex_only'=>0,'samo_only'=>0,'neither'=>0];}
function uc_bucket(bool $anex,bool $samo):string{return $anex?($samo?'both':'anex_only'):($samo?'samo_only':'neither');}
function uc_classify(array $seen,array $hotels,array $anex,array $samo,array $operatorSamo=[]):array{
 $all=uc_empty();$active=uc_empty();$countries=[];$rows=[];$ids=[];$missingCatalog=0;$excluded=0;$inactive=0;$operatorOnly=0;
 foreach($seen as $obs){
  $id=uc_id($obs['hotel_id']??null);uc_need(!isset($ids[$id]),'duplicate_hotel');$ids[$id]=true;
  $h=$hotels[$id]??null;$a=array_values($anex[$id]??[]);$s=array_values($samo[$id]??[]);$o=array_values($operatorSamo[$id]??[]);
  sort($a,SORT_NATURAL);sort($s,SORT_NATURAL);sort($o,SORT_NATURAL);$b=uc_bucket($a!==[],$s!==[]);
  $country=trim((string)($h['country_name']??''));$isExcluded=in_array($country,['Россия','Абхазия','Russia','Russian Federation','Abkhazia'],true);
  $isActive=$h!==null&&(int)($h['is_active']??0)===1;$eligible=$isActive&&!$isExcluded;
  $all['total']++;$all[$b]++;if($eligible){$active['total']++;$active[$b]++;}
  $key=$h===null?'missing_catalog':(string)($h['country_id']??'unknown');
  if(!isset($countries[$key]))$countries[$key]=['country_id'=>$h['country_id']??null,'country_name'=>$country]+uc_empty();
  $countries[$key]['total']++;$countries[$key][$b]++;
  $missingCatalog+=(int)($h===null);$excluded+=(int)$isExcluded;$inactive+=(int)($h!==null&&!$isActive);$operatorOnly+=(int)($s===[]&&$o!==[]);
  $rows[]=['tv_hotel_id'=>$id,'name'=>$h['name']??null,'country_id'=>$h['country_id']??null,'country_name'=>$country,'region_name'=>$h['region_name']??null,'subregion_name'=>$h['subregion_name']??null,'is_active'=>$isActive,'excluded_market'=>$isExcluded,'bucket'=>$b,'anex_native_ids'=>$a,'canonical_samo_ids'=>$s,'operator_samo_keys'=>$o,'user_search_observations'=>(int)$obs['observations'],'first_seen_at'=>$obs['first_seen_at'],'last_seen_at'=>$obs['last_seen_at']];
 }
 uc_need($all['total']===$all['both']+$all['anex_only']+$all['samo_only']+$all['neither'],'partition_parity');
 return ['coverage_all_retained'=>$all,'coverage_active_nonexcluded'=>$active,'missing_catalog_hotels'=>$missingCatalog,'excluded_market_hotels'=>$excluded,'inactive_catalog_hotels'=>$inactive,'operator_samo_only_without_canonical'=>$operatorOnly,'countries'=>array_values($countries),'rows'=>$rows];
}
if(($argv[1]??'')==='--self-test'){
 $obs=[];$hot=[];for($i=1;$i<=6;$i++){$obs[]=['hotel_id'=>(string)$i,'observations'=>100,'first_seen_at'=>'2020-01-01','last_seen_at'=>'2026-09-20'];$hot[$i]=['id'=>$i,'country_id'=>4,'country_name'=>'Турция','name'=>'HOTEL '.$i,'is_active'=>1];}
 $hot[5]['country_name']='Россия';$hot[5]['country_id']=1;$hot[6]['is_active']=0;$a=[1=>['10','11'],2=>['20']];$s=[1=>['100','101'],3=>['300']];
 $before=uc_json([$obs,$hot,$a,$s]);$r=uc_classify($obs,$hot,$a,$s,[4=>['operator_5:400']]);$checks=0;
 foreach([$r['coverage_all_retained']===['total'=>6,'both'=>1,'anex_only'=>1,'samo_only'=>1,'neither'=>3],$r['coverage_active_nonexcluded']['total']===4,$r['operator_samo_only_without_canonical']===1,$r['rows'][0]['first_seen_at']==='2020-01-01',$r['rows'][0]['user_search_observations']===100,count($r['rows'][0]['anex_native_ids'])===2,count($r['rows'][0]['canonical_samo_ids'])===2,$r['excluded_market_hotels']===1,$r['inactive_catalog_hotels']===1,uc_json([$obs,$hot,$a,$s])===$before]as$ok){uc_need($ok,'self_count');$checks++;}
 $missing=uc_classify($obs,[],[],[]);uc_need($missing['missing_catalog_hotels']===6&&$missing['coverage_all_retained']['neither']===6,'self_missing');$checks++;
 $empty=uc_classify([],[],[],[]);uc_need($empty['coverage_all_retained']===uc_empty(),'self_empty');$checks++;
 foreach([array_merge($obs,[$obs[0]]),[['hotel_id'=>true,'observations'=>1,'first_seen_at'=>null,'last_seen_at'=>null]]]as$bad){$failed=false;try{uc_classify($bad,$hot,$a,$s);}catch(RuntimeException $e){$failed=true;}uc_need($failed,'self_invalid');$checks++;}
 echo uc_json(['tests_passed'=>$checks,'provider_calls'=>0,'database_writes'=>0]);exit(0);
}
uc_need(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===UC_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.UC_OP;uc_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'directory_guard');
$sha=(string)getenv('MATCH_SOURCE_SHA');uc_need(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);uc_need(($res['operation_id']??'')===UC_OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_read','reservation_guard');
$base=['operation_id'=>UC_OP,'source_sha'=>$sha,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_query_now'=>false,'safe_to_write_now'=>false,'no_replay'=>true];$db=null;
try{
 uc_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);
 require_once __DIR__.'/hotel_match_anex_effective_coverage.php';require_once dirname(__DIR__,2).'/app/integrations/andromeda-hotel-resolver.php';
 $root=realpath(getcwd());uc_need(is_string($root)&&basename($root)==='anytoour.ru','root_guard');require_once(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
 $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
 foreach(['tour_price_observations','catalog_hotels','andromeda_hotel_identities','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions']as$t){$e=uc_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);uc_need(count($e)===1&&strtoupper((string)$e[0]['ENGINE'])==='INNODB','engine_guard');}
 $clock=uc_rows($db,'SELECT NOW() AS database_now,UTC_TIMESTAMP() AS utc_now,@@session.time_zone AS session_time_zone')[0];
 $stats=uc_rows($db,"SELECT COUNT(*) AS observation_rows,COUNT(DISTINCT CASE WHEN hotel_id>0 THEN hotel_id END) AS distinct_positive_hotels,COALESCE(SUM(CASE WHEN hotel_id IS NULL OR hotel_id<=0 THEN 1 ELSE 0 END),0) AS invalid_hotel_rows,MIN(observed_at) AS first_seen_at,MAX(observed_at) AS last_seen_at FROM tour_price_observations WHERE source='user_search'")[0];
 $seen=uc_rows($db,"SELECT hotel_id,COUNT(*) AS observations,MIN(observed_at) AS first_seen_at,MAX(observed_at) AS last_seen_at FROM tour_price_observations WHERE source='user_search' AND hotel_id>0 GROUP BY hotel_id ORDER BY hotel_id LIMIT 50001");
 uc_need(count($seen)===(int)$stats['distinct_positive_hotels'],'all_seen_parity');$sum=0;$ids=[];foreach($seen as$o){$ids[]=uc_id($o['hotel_id']);$sum+=(int)$o['observations'];}uc_need($sum+(int)$stats['invalid_hotel_rows']===(int)$stats['observation_rows'],'observation_parity');
 $hot=[];foreach(array_chunk($ids,1000)as$chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(uc_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$chunk)as$h)$hot[(int)$h['id']]=$h;}
 $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];foreach($coverage['by_local']as$local=>$native)$anex[(int)$local]=array_map('strval',array_keys($native));
 $projection=uc_rows($db,"SELECT i.supplier_namespace,i.external_hotel_id,i.decision_status,i.local_hotel_id AS catalog_hotel_id,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status IN ('accepted','rejected') ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001");
 $resolver=AnyTourAndromedaHotelResolver::fromRows($projection,hash('sha256',uc_json($projection)));$offers=[];foreach($projection as$p)$offers[]=['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>$p['supplier_namespace'],'external_hotel_id'=>$p['external_hotel_id']];
 $mapped=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>$offers]);$samo=[];$operatorSamo=[];
 foreach($mapped['offers']as$p){if($p['local_hotel_id']===null)continue;$id=(int)$p['local_hotel_id'];if($p['supplier_namespace']==='andromeda_catalog')$samo[$id][]=(string)$p['external_hotel_id'];else$operatorSamo[$id][]=$p['supplier_namespace'].':'.$p['external_hotel_id'];}
 $out=uc_classify($seen,$hot,$anex,$samo,$operatorSamo);
 $operators=uc_rows($db,"SELECT operator_id,COUNT(*) AS observations,COUNT(DISTINCT hotel_id) AS unique_hotels FROM tour_price_observations WHERE source='user_search' AND hotel_id>0 GROUP BY operator_id ORDER BY operator_id");
 $db->rollBack();$result=$base+['state'=>'completed_read_only','clock'=>$clock,'scope'=>"all retained tour_price_observations source=user_search; unique positive hotel_id; no operator/date/price/party/market cutoff",'stats'=>$stats,'operators_observed'=>$operators,'samo_definition'=>'effective andromeda_catalog identities; operator-specific namespaces separate','anex_registry_native_count'=>$coverage['native_count']]+$out;
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$result=$base+['state'=>'failed_no_replay','reason'=>preg_match('/^[a-zA-Z0-9_]+$/D',$e->getMessage())?$e->getMessage():'database_or_runtime_error','error_class'=>get_class($e)];}
$hash=uc_save($dir.'/result.json',$result);uc_save($dir.'/receipt.json',$base+['state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash]);
echo uc_json(['state'=>$result['state'],'coverage'=>$result['coverage_all_retained']??null,'result_sha256'=>$hash]);exit($result['state']==='completed_read_only'?0:2);
