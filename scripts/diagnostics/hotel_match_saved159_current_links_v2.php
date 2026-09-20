<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const SL_OP='hotel-match-saved159-current-links-1971-20260920-v2';
const SL_FRONTIER_SHA='2b09cbda05446ca5210901fcd8f5d9498bdbab2f30e0cab4c6b0192f68c6ed66';
const SL_ANEX_OPERATOR=13;

function sl_need(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function sl_json(array $v):string{return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function sl_save(string $p,array $v):string{$b=sl_json($v);$f=fopen($p,'xb');sl_need(is_resource($f),'immutable_output');try{sl_need(fwrite($f,$b)===strlen($b)&&fflush($f),'output_write');if(function_exists('fsync'))sl_need(fsync($f),'output_sync');}finally{fclose($f);}sl_need(file_get_contents($p)===$b,'output_readback');return hash('sha256',$b);}
function sl_rows(PDO $db,string $sql,array $params=[]):array{$q=$db->prepare($sql);$q->execute($params);$r=$q->fetchAll(PDO::FETCH_ASSOC);sl_need(count($r)<=50000,'row_limit');return$r;}
function sl_int($v):?int{$x=filter_var($v,FILTER_VALIDATE_INT);return $x!==false&&(int)$x>0?(int)$x:null;}
function sl_str($v):string{return trim((string)$v);}
function sl_safe_link($v):?string{$s=sl_str($v);if($s===''||strlen($s)>2048||!str_starts_with(strtolower($s),'https://'))return null;$p=parse_url($s);if(!is_array($p)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;$q=(string)($p['query']??'');if($q!==''){parse_str($q,$params);foreach(array_keys($params) as $k){if(preg_match('/(?:^|_)(?:token|jwt|secret|password|passwd|auth|authorization|access[_-]?token|api[_-]?key|signature|sig)(?:$|_)/i',(string)$k))return null;}}return $s;}
function sl_obs_public(array $r):array{return [
    'search_id'=>sl_int($r['search_id']??null),'tour_id'=>sl_str($r['tour_id']??''),'tv_hotel_id'=>sl_int($r['hotel_id']??null),
    'source'=>sl_str($r['source']??''),'last_seen_at'=>sl_str($r['last_seen_at']??''),'observation_count'=>(int)($r['observation_count']??0),
    'operator_name'=>sl_str($r['operator_name']??''),'operator_link'=>sl_safe_link($r['operator_link']??null),
    'operator_link_host'=>sl_str($r['operator_link_host']??''),'operator_link_path'=>sl_str($r['operator_link_path']??''),
    'operator_link_query'=>sl_str($r['operator_link_query']??'')
];}

sl_need(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===SL_OP,'operation_guard');
$dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.SL_OP;
sl_need(realpath((string)getenv('MATCH_OPERATION_DIR'))===$dir,'operation_directory');
$source=(string)getenv('MATCH_SOURCE_SHA');sl_need(preg_match('/^[0-9a-f]{40}$/D',$source)===1,'source_sha');
$res=json_decode((string)file_get_contents($dir.'/reservation.json'),true,64,JSON_THROW_ON_ERROR);
sl_need(($res['operation_id']??'')===SL_OP&&($res['source_sha']??'')===$source&&($res['state']??'')==='reserved_before_db_read','reservation_guard');
$base=['operation_id'=>SL_OP,'source_sha'=>$source,'supplier_calls'=>0,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];$db=null;
try{
    sl_save($dir.'/execution-reservation.json',$base+['state'=>'started_before_db_read']);
    sl_need(hash_file('sha256',$dir.'/canonical-frontier.json')===SL_FRONTIER_SHA,'frontier_digest');
    $frontier=json_decode((string)file_get_contents($dir.'/canonical-frontier.json'),true,64,JSON_THROW_ON_ERROR);
    $targets=[];
    foreach(($frontier['remaining_retained_context_frontier']??[]) as $r){if(($r['route']??'')!=='saved_tour_detail_ready')continue;$id=sl_int($r['local_hotel_id']??null);$ctx=$r['latest_future_anex_context']??null;sl_need($id!==null&&is_array($ctx),'target_shape');$tour=sl_str($ctx['tour_id']??'');$search=sl_int($ctx['search_id']??null);sl_need($tour!==''&&$search!==null,'target_context');sl_need(!isset($targets[$id]),'duplicate_local');$targets[$id]=['local_hotel_id'=>$id,'name'=>sl_str($r['name']??''),'country'=>sl_str($r['country']??''),'region'=>sl_str($r['region']??''),'category'=>sl_int($r['category']??null),'samo_external_ids'=>array_values(array_map('strval',$r['samo_external_ids']??[])),'search_id'=>$search,'tour_id'=>$tour,'observed_at'=>sl_str($ctx['observed_at']??''),'departure_date'=>sl_str($ctx['departure_date']??'')];}
    sl_need(count($targets)===159,'saved159_count');
    $root=realpath(getcwd());sl_need(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
    require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach(['tour_price_observations','tour_operator_identity_observations','andromeda_hotel_identities','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'] as $table){$x=sl_rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);sl_need(count($x)===1&&strtoupper((string)$x[0]['ENGINE'])==='INNODB','table_engine_'.$table);}
    $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$effective=$coverage['by_local'];
    $samo=[];foreach(sl_rows($db,"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){$id=sl_int($r['local_hotel_id']??null);if($id!==null)$samo[$id][(string)$r['external_hotel_id']]=true;}
    $priceStmt=$db->prepare('SELECT hotel_id,search_id,tour_id,operator_id,observed_at,source FROM tour_price_observations WHERE search_id=? AND tour_id=? AND operator_id=? ORDER BY observed_at DESC,id DESC LIMIT 20');
    $obsStmt=$db->prepare('SELECT hotel_id,search_id,tour_id,source,last_seen_at,observation_count,operator_name,operator_link,operator_link_host,operator_link_path,operator_link_query FROM tour_operator_identity_observations WHERE hotel_id=? AND operator_id=? ORDER BY (operator_link IS NOT NULL) DESC,last_seen_at DESC,id DESC LIMIT 100');
    $out=[];$counts=[];$linkHotels=[];$tvIds=[];
    foreach($targets as $local=>$t){
        $route='';$tv=null;$priceRows=[];$obsRows=[];$chosen=[];$reason='';
        if(isset($effective[$local])){$route='already_effective_now';$reason='canonical_anex_now_effective';}
        elseif(!isset($samo[$local])){$route='lost_samo_hold';$reason='canonical_samo_no_longer_accepted';}
        else{
            $priceStmt->execute([$t['search_id'],$t['tour_id'],SL_ANEX_OPERATOR]);$priceRows=$priceStmt->fetchAll(PDO::FETCH_ASSOC);
            $ids=[];foreach($priceRows as $p){$id=sl_int($p['hotel_id']??null);if($id!==null)$ids[$id]=true;}
            if(count($ids)!==1){$route=count($ids)===0?'saved_tour_context_missing':'saved_tour_context_ambiguous';$reason='current_price_observation_tv_id_count_'.count($ids);}
            else{
                $tv=(int)array_key_first($ids);$tvIds[$tv]=true;$obsStmt->execute([$tv,SL_ANEX_OPERATOR]);$obsRows=$obsStmt->fetchAll(PDO::FETCH_ASSOC);
                $exactLink=[];$sameSearchLink=[];$otherLink=[];$exactSeen=false;
                foreach($obsRows as $o){$ot=sl_str($o['tour_id']??'');$os=sl_int($o['search_id']??null);$link=sl_safe_link($o['operator_link']??null);if($ot===$t['tour_id'])$exactSeen=true;if($link!==null){if($ot===$t['tour_id'])$exactLink[]=$o;elseif($os===$t['search_id'])$sameSearchLink[]=$o;else $otherLink[]=$o;}}
                if($exactLink){$route='exact_tour_saved_link';$chosen=array_map('sl_obs_public',$exactLink);}
                elseif($sameSearchLink){$route='same_search_saved_link';$chosen=array_map('sl_obs_public',$sameSearchLink);}
                elseif($otherLink){$route='alternate_tour_saved_link';$chosen=array_map('sl_obs_public',$otherLink);}
                elseif($exactSeen){$route='exact_tour_linkless';}
                elseif($obsRows){$route='anex_identity_rows_linkless';}
                else{$route='detail_still_needed_after_ledger';}
                $reason=$route;
            }
        }
        $counts[$route]=($counts[$route]??0)+1;if(str_contains($route,'saved_link'))$linkHotels[$local]=true;
        $out[]=$t+['tv_hotel_id'=>$tv,'classification'=>$route,'reason'=>$reason,'saved_link_evidence'=>$chosen,'current_price_match_count'=>count($priceRows),'current_anex_identity_row_count'=>count($obsRows),'safe_to_query_now'=>false,'safe_to_write_now'=>false];
    }
    ksort($counts);$clock=sl_rows($db,'SELECT UTC_TIMESTAMP AS db_utc_timestamp')[0]['db_utc_timestamp'];$db->rollBack();
    sl_need(array_sum($counts)===159,'partition_count');
    $result=$base+['state'=>'completed_read_only','read_at_utc'=>$clock,'frontier_sha256'=>SL_FRONTIER_SHA,'input_saved_tour_count'=>159,'current_effective_anex_count'=>$coverage['local_count'],'classification_counts'=>$counts,'saved_link_hotel_count'=>count($linkHotels),'resolved_tv_hotel_id_count'=>count($tvIds),'rows'=>$out];
}catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();$m=$e->getMessage();$result=$base+['state'=>'failed_no_replay','reason'=>preg_match('/^[a-z0-9_]+$/D',$m)?$m:'database_or_runtime_error','error_class'=>get_class($e)];}
$hash=sl_save($dir.'/result.json',$result);sl_save($dir.'/receipt.json',['operation_id'=>SL_OP,'source_sha'=>$source,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'supplier_calls'=>0,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
echo sl_json(['state'=>$result['state'],'classification_counts'=>$result['classification_counts']??null,'saved_link_hotel_count'=>$result['saved_link_hotel_count']??null,'resolved_tv_hotel_id_count'=>$result['resolved_tv_hotel_id_count']??null,'result_sha256'=>$hash]);if($result['state']!=='completed_read_only')exit(2);
