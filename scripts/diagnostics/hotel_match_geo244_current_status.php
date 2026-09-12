<?php
declare(strict_types=1);
error_reporting(0);ob_start();
const OP='hotel-match-geo244-current-status-1971-20260912-v1';
function emit(array $out,int $rc=0):void{while(ob_get_level())ob_end_clean();echo 'MATCH_GEO244_STATUS:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit($rc);}
function cols(PDO $db,string $table):array{return array_map('strval',$db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN));}
function pick(array $cols,array $choices):?string{foreach($choices as $x)if(in_array($x,$cols,true))return$x;return null;}
if(in_array('--self-test',$_SERVER['argv']??[],true)){
 $fixture=['anex_prepared_pairs'=>[[1,10],[2,11]],'andromeda_prepared_pairs'=>[[20,12]],'live_rows'=>[[1,10,7]]];
 $seen=[];foreach(array_merge($fixture['anex_prepared_pairs'],$fixture['andromeda_prepared_pairs']) as $r){$k=$r[0].':'.$r[1];if(isset($seen[$k]))emit(['status'=>'failed','safe_message'=>'duplicate_fixture'],2);$seen[$k]=1;}
 if(count($seen)!==3)emit(['status'=>'failed','safe_message'=>'fixture_count'],2);
 echo "MATCH geo244 current-status offline self-test PASS; network=0 database=0\n";exit;
}
$db=null;
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
 $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
 $reportPath=$root.'/reports/hotel-match-geo-context-review-20260912.json';
 if(!is_file($reportPath))throw new RuntimeException('report_missing');
 $report=json_decode(file_get_contents($reportPath),true,512,JSON_THROW_ON_ERROR);
 if(($report['status']??'')!=='prepared_offline_evidence_not_accepted_mappings'||($report['prepared_pairs_are_not_an_apply_manifest']??false)!==true)throw new RuntimeException('report_guard');
 $anex=$report['anex_prepared_pairs']??[];$andr=$report['andromeda_prepared_pairs']??[];$live=$report['live_rows']??[];
 if(count($anex)!==209||count($andr)!==35||count($anex)+count($andr)!==244)throw new RuntimeException('pair_count_guard');
 $liveIds=[];foreach($live as$r)if(isset($r[0]))$liveIds[(string)$r[0]]=true;
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
 $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
 $mapCols=cols($db,'anex_hotel_search_mappings');$decCols=cols($db,'anex_hotel_decisions');$exCols=cols($db,'anex_review_pair_exclusions');$andCols=cols($db,'andromeda_hotel_identities');
 $mapLocal=pick($mapCols,['catalog_hotel_id','local_hotel_id']);$decLocal=pick($decCols,['catalog_hotel_id','local_hotel_id']);$exLocal=pick($exCols,['catalog_hotel_id','local_hotel_id']);
 if(!$mapLocal||!in_array('anex_hotel_id',$mapCols,true)||!in_array('external_hotel_id',$andCols,true)||!in_array('decision_status',$andCols,true)||!in_array('local_hotel_id',$andCols,true))throw new RuntimeException('schema_guard');
 $maps=[];foreach($db->query('SELECT anex_hotel_id,`'.$mapLocal.'` AS local_hotel_id,enabled FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_ASSOC) as$r)$maps[(string)$r['anex_hotel_id']][]=$r;
 $dec=[];foreach($db->query('SELECT * FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_ASSOC) as$r)$dec[(string)$r['anex_hotel_id']][]=$r;
 $exc=[];foreach($db->query('SELECT * FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as$r)$exc[(string)$r['anex_hotel_id']][]=$r;
 $and=[];foreach($db->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")->fetchAll(PDO::FETCH_ASSOC) as$r)$and[(string)$r['external_hotel_id']]=$r;
 $targets=[];foreach(array_merge($anex,$andr)as$r)$targets[(int)$r[1]]=true;$ids=array_keys($targets);$hotels=[];
 foreach(array_chunk($ids,800)as$chunk){$q=$db->prepare('SELECT id,name,country_id,country_name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ('.implode(',',array_fill(0,count($chunk),'?')).')');$q->execute($chunk);foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r)$hotels[(int)$r['id']]=$r;}
 $rows=[];$counts=[];$surviving=[];$liveSurviving=[];
 $add=function(string $provider,$external,int $local,string $reason,array $extra=[])use(&$rows,&$counts,&$surviving,&$liveSurviving,$liveIds){$row=array_merge(['provider'=>$provider,'external_id'=>(string)$external,'proposed_local_id'=>$local,'status'=>$reason],$extra);$rows[]=$row;$counts[$reason]=($counts[$reason]??0)+1;if($reason==='survives_current_status'){$surviving[]=$row;if($provider==='anex'&&isset($liveIds[(string)$external]))$liveSurviving[]=$row;}};
 $seen=[];
 foreach($anex as$p){$aid=(string)$p[0];$lid=(int)$p[1];$key='anex:'.$aid;if(isset($seen[$key])){$add('anex',$aid,$lid,'duplicate_source_in_plan');continue;}$seen[$key]=1;$h=$hotels[$lid]??null;if(!$h||(int)($h['is_active']??0)!==1){$add('anex',$aid,$lid,'target_missing_or_inactive');continue;}
  $current=$maps[$aid]??[];$enabled=array_values(array_filter($current,fn($r)=>(int)($r['enabled']??1)===1));if($enabled){$same=count($enabled)===1&&(int)$enabled[0]['local_hotel_id']===$lid;$add('anex',$aid,$lid,$same?'already_resolved_same':'existing_mapping_protect',['current_local_ids'=>array_values(array_map(fn($r)=>(int)$r['local_hotel_id'],$enabled))]);continue;}
  if(isset($dec[$aid])){$add('anex',$aid,$lid,'manual_decision_protect');continue;}
  $blocked=false;foreach($exc[$aid]??[]as$e){if(!$exLocal||(int)($e[$exLocal]??0)===$lid){$blocked=true;break;}}if($blocked){$add('anex',$aid,$lid,'pair_exclusion_protect');continue;}
  $add('anex',$aid,$lid,'survives_current_status',['target_name'=>$h['name'],'target_country'=>$h['country_name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'live_observed'=>isset($liveIds[$aid])]);}
 foreach($andr as$p){$eid=(string)$p[0];$lid=(int)$p[1];$key='andromeda:'.$eid;if(isset($seen[$key])){$add('andromeda',$eid,$lid,'duplicate_source_in_plan');continue;}$seen[$key]=1;$h=$hotels[$lid]??null;if(!$h||(int)($h['is_active']??0)!==1){$add('andromeda',$eid,$lid,'target_missing_or_inactive');continue;}$r=$and[$eid]??null;if(!$r){$add('andromeda',$eid,$lid,'source_missing_current');continue;}$status=(string)$r['decision_status'];$cur=$r['local_hotel_id'];if($status==='accepted'||$cur!==null){$same=$status==='accepted'&&(int)$cur===$lid;$add('andromeda',$eid,$lid,$same?'already_resolved_same':'existing_identity_protect',['current_status'=>$status,'current_local_id'=>$cur===null?null:(int)$cur]);continue;}if($status!=='pending'){$add('andromeda',$eid,$lid,'identity_status_protect',['current_status'=>$status]);continue;}$add('andromeda',$eid,$lid,'survives_current_status',['target_name'=>$h['name'],'target_country'=>$h['country_name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'current_evidence_sha256'=>$r['evidence_sha256'],'current_catalog_sha256'=>$r['catalog_sha256']]);}
 $db->exec('ROLLBACK');ksort($counts);
 $out=['status'=>'completed','operation_id'=>OP,'generated_at_utc'=>gmdate('c'),'input_report_code_source_sha'=>$report['code_source_sha']??null,'examined'=>count($rows),'input_pairs'=>['anex'=>count($anex),'andromeda'=>count($andr),'total'=>244],'status_counts'=>$counts,'surviving_count'=>count($surviving),'surviving_provider_counts'=>['anex'=>count(array_filter($surviving,fn($r)=>$r['provider']==='anex')),'andromeda'=>count(array_filter($surviving,fn($r)=>$r['provider']==='andromeda'))],'live_input_count'=>count($liveIds),'live_surviving_count'=>count($liveSurviving),'live_surviving'=>$liveSurviving,'surviving'=>$surviving,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'evidence_recomputed'=>false,'snapshot_is_not_write_authority'=>true,'acceptance_requires_new_current_full_evidence_transaction'=>true,'no_replay'=>true];
 emit($out,0);
}catch(Throwable$e){try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable$ignored){}emit(['status'=>'failed','operation_id'=>OP,'safe_message'=>preg_replace('/[^a-zA-Z0-9_-]/','_',substr($e->getMessage(),0,120)),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true],2);}
