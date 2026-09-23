<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const OP='hotel-match-saved159-current-links-1971-20260920-v3';
const FRONTIER_SHA='2b09cbda05446ca5210901fcd8f5d9498bdbab2f30e0cab4c6b0192f68c6ed66';
const ANEX_OP=13;
function req(bool $x,string $m):void{if(!$x)throw new RuntimeException($m);}
function js(array $x):string{return json_encode($x,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function once(string $p,array $x):string{$b=js($x);$f=@fopen($p,'xb');req(is_resource($f),'immutable_output');try{req(fwrite($f,$b)===strlen($b),'write');req(fflush($f),'flush');if(function_exists('fsync'))req(fsync($f),'sync');}finally{fclose($f);}req(file_get_contents($p)===$b,'readback');return hash('sha256',$b);}
function pi($v):?int{$x=filter_var($v,FILTER_VALIDATE_INT);return $x!==false&&(int)$x>0?(int)$x:null;}
function st($v):string{return trim((string)$v);}
function rows(PDO $db,string $q,array $a=[]):array{$s=$db->prepare($q);$s->execute($a);$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];req(count($r)<=50000,'row_cap');return $r;}
function safeLink($v):?string{$s=st($v);if($s===''||strlen($s)>2048||stripos($s,'https://')!==0)return null;$p=parse_url($s);if(!is_array($p)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;parse_str((string)($p['query']??''),$q);foreach(array_keys($q) as $k)if(preg_match('/(?:token|secret|pass|auth|api.?key|signature|sig)/i',(string)$k))return null;return $s;}

req(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===OP,'operation_guard');
$expected=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.OP;
$er=realpath($expected);$pr=realpath((string)getenv('MATCH_OPERATION_DIR'));req(is_string($er)&&is_string($pr)&&hash_equals($er,$pr),'operation_directory');$dir=$er;
$sha=(string)getenv('MATCH_SOURCE_SHA');req(preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);req(($res['operation_id']??'')===OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_read','reservation_guard');
$base=['operation_id'=>OP,'source_sha'=>$sha,'supplier_calls'=>0,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];$db=null;
try{
  once($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);
  req(hash_file('sha256',$dir.'/canonical-frontier.json')===FRONTIER_SHA,'frontier_digest');
  $f=json_decode((string)file_get_contents($dir.'/canonical-frontier.json'),true,128,JSON_THROW_ON_ERROR);$targets=[];
  foreach(($f['remaining_retained_context_frontier']??[]) as $r){if(($r['route']??'')!=='saved_tour_detail_ready')continue;$l=pi($r['local_hotel_id']??null);$c=$r['latest_future_anex_context']??null;req($l!==null&&is_array($c),'target_shape');$search=pi($c['search_id']??null);$tour=st($c['tour_id']??'');req($search!==null&&$tour!=='','context_shape');req(!isset($targets[$l]),'duplicate_local');$targets[$l]=['local_hotel_id'=>$l,'name'=>st($r['name']??''),'country'=>st($r['country']??''),'region'=>st($r['region']??''),'search_id'=>$search,'tour_id'=>$tour,'departure_date'=>st($c['departure_date']??''),'observed_at'=>st($c['observed_at']??'')];}
  req(count($targets)===159,'input_count');
  $root=realpath((string)getenv('ANYTOUR_ROOT'));req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
  $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
  foreach(['tour_price_observations','tour_operator_identity_observations','andromeda_hotel_identities','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions'] as $t){$x=rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);req(count($x)===1&&strtoupper((string)$x[0]['ENGINE'])==='INNODB','table_'.$t);}
  $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$effective=$coverage['by_local'];$samo=[];foreach(rows($db,"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){$l=pi($r['local_hotel_id']??null);if($l)$samo[$l][(string)$r['external_hotel_id']]=true;}
  $ps=$db->prepare('SELECT hotel_id,search_id,tour_id,observed_at,source FROM tour_price_observations WHERE search_id=? AND tour_id=? AND operator_id=? ORDER BY observed_at DESC,id DESC LIMIT 20');
  $os=$db->prepare('SELECT hotel_id,search_id,tour_id,source,last_seen_at,observation_count,operator_name,operator_link,operator_link_host,operator_link_path,operator_link_query FROM tour_operator_identity_observations WHERE hotel_id=? AND operator_id=? ORDER BY (operator_link IS NOT NULL) DESC,last_seen_at DESC,id DESC LIMIT 100');
  $out=[];$counts=[];$saved=0;$resolved=[];
  foreach($targets as $l=>$t){$class='';$tv=null;$obs=[];$chosen=[];$pm=[];
    if(isset($effective[$l]))$class='already_effective_now';elseif(!isset($samo[$l]))$class='lost_samo_hold';else{
      $ps->execute([$t['search_id'],$t['tour_id'],ANEX_OP]);$pm=$ps->fetchAll(PDO::FETCH_ASSOC)?:[];$ids=[];foreach($pm as $p){$x=pi($p['hotel_id']??null);if($x)$ids[$x]=1;}
      if(count($ids)!==1)$class=count($ids)?'saved_tour_context_ambiguous':'saved_tour_context_missing';else{$tv=(int)array_key_first($ids);$resolved[$tv]=1;$os->execute([$tv,ANEX_OP]);$obs=$os->fetchAll(PDO::FETCH_ASSOC)?:[];$exact=[];$same=[];$alt=[];$seen=false;foreach($obs as $o){$ot=st($o['tour_id']??'');$sr=pi($o['search_id']??null);$ln=safeLink($o['operator_link']??null);if($ot===$t['tour_id'])$seen=true;if($ln!==null){$e=['search_id'=>$sr,'tour_id'=>$ot,'tv_hotel_id'=>$tv,'source'=>st($o['source']??''),'last_seen_at'=>st($o['last_seen_at']??''),'operator_name'=>st($o['operator_name']??''),'operator_link'=>$ln,'operator_link_host'=>st($o['operator_link_host']??''),'operator_link_path'=>st($o['operator_link_path']??''),'operator_link_query'=>st($o['operator_link_query']??'')];if($ot===$t['tour_id'])$exact[]=$e;elseif($sr===$t['search_id'])$same[]=$e;else $alt[]=$e;}}
        if($exact){$class='exact_tour_saved_link';$chosen=$exact;}elseif($same){$class='same_search_saved_link';$chosen=$same;}elseif($alt){$class='alternate_tour_saved_link';$chosen=$alt;}elseif($seen)$class='exact_tour_linkless';elseif($obs)$class='anex_identity_rows_linkless';else $class='detail_still_needed_after_ledger';
      }}
    $counts[$class]=($counts[$class]??0)+1;if(str_contains($class,'saved_link'))$saved++;$out[]=$t+['tv_hotel_id'=>$tv,'classification'=>$class,'saved_link_evidence'=>$chosen,'current_price_match_count'=>count($pm),'current_anex_identity_row_count'=>count($obs),'safe_to_query_now'=>false,'safe_to_write_now'=>false];}
  ksort($counts);$utc=rows($db,'SELECT UTC_TIMESTAMP AS t')[0]['t'];$db->rollBack();req(array_sum($counts)===159,'partition');
  $result=$base+['state'=>'completed_read_only','read_at_utc'=>$utc,'frontier_sha256'=>FRONTIER_SHA,'input_saved_tour_count'=>159,'current_effective_anex_count'=>$coverage['local_count'],'classification_counts'=>$counts,'saved_link_hotel_count'=>$saved,'resolved_tv_hotel_id_count'=>count($resolved),'rows'=>$out];
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$m=$e->getMessage();$result=$base+['state'=>'failed_no_replay','reason'=>preg_match('/^[a-z0-9_]+$/D',$m)?$m:'database_or_runtime_error','error_class'=>get_class($e)];}
$h=once($dir.'/result.json',$result);once($dir.'/receipt.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'supplier_calls'=>0,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo js(['state'=>$result['state'],'classification_counts'=>$result['classification_counts']??null,'saved_link_hotel_count'=>$result['saved_link_hotel_count']??null,'resolved_tv_hotel_id_count'=>$result['resolved_tv_hotel_id_count']??null,'result_sha256'=>$h]);if($result['state']!=='completed_read_only')exit(2);
