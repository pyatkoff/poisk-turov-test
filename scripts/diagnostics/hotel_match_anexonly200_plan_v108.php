<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
require_once __DIR__.'/hotel_match_retained93_primary_write_v84.php';
const M108_OP='hotel-match-anexonly200-plan-1971-20260928-v108';
const M108_LEDGER_OP='hotel-match-request-ledger-1971-20260928-v93';
const M108_LEDGER_SHA='8da64bc3ac54d707d335b8808b171f46a02ba0464536681bc40b741136df935c';
const M108_LIMIT=200;
function m108_need(bool$b,string$m):void{if(!$b)throw new RuntimeException($m);}
function m108_main(array$a):int{
 m108_need(($a[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 m108_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===M108_OP&&preg_match('/^[a-f0-9]{40}$/D',$head),'scope');foreach(['result.json','receipt.json']as$f)m108_need(!file_exists($dir.'/'.$f),'no_replay');
 $ledger=w84_read(dirname($dir).'/'.M108_LEDGER_OP.'/result.json',M108_LEDGER_SHA,33554432);m108_need(($ledger['state']??'')==='completed_read_only_request_ledger','ledger');
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$m=hmc4_execute(v2_data_db());m108_need(($m['state']??'')==='completed_read_only_common4_gap_matrix'&&($m['tv_live30_total']??0)===4399,'matrix');
 $cur=[];foreach($m['rows']as$r)$cur[(int)$r['tv_hotel_id']]=$r;$reqBy=[];foreach($ledger['requests']as$q)foreach($q['hotel_ids']as$id)$reqBy[(int)$id][]=$q;
 $pool=[];$replay=0;$noctx=0;$complete=0;
 foreach($ledger['rows']as$r){$id=(int)$r['tv_hotel_id'];if(($cur[$id]['gap_bucket']??'')!=='anex_only')continue;$missing=[];
  foreach([18,25,43]as$op)if(in_array($op,array_map('intval',$r['missing_operator_ids']??[]),true)&&empty($r['lanes'][(string)$op]['retained_exact']))$missing[]=$op;
  if(!$missing){$complete++;continue;}$contexts=$reqBy[$id]??[];if(!$contexts){$noctx++;continue;}
  $freq=[];foreach($contexts as$q){$x=$q['context']??[];$dep=(int)($x['departureId']??0);$cid=(int)($x['countryId']??0);if($dep>0&&$cid>0)$freq[$dep.'|'.$cid]=($freq[$dep.'|'.$cid]??0)+1;}arsort($freq);$k=array_key_first($freq);if(!$k){$noctx++;continue;}[$dep,$cid]=array_map('intval',explode('|',$k));
  $seen=false;foreach($contexts as$q){$x=$q['context']??[];if((int)($x['departureId']??0)===$dep&&(int)($x['countryId']??0)===$cid&&($x['dateFrom']??'')==='2026-10-22'&&($x['dateTo']??'')==='2026-10-22'&&(int)($x['nightsFrom']??0)===7&&(int)($x['nightsTo']??0)===7&&(int)($x['adults']??0)===2&&($x['childs']??[])===[]){$seen=true;break;}}
  if($seen){$replay++;continue;}$pool[$r['country']][]=['tv_hotel_id'=>$id,'hotel_name'=>$r['hotel_name'],'country_id'=>$cid,'country'=>$r['country'],'departure_id'=>$dep,'departure_date'=>'2026-10-22','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','missing_operator_ids'=>$missing,'gap_bucket'=>'anex_only'];
 }
 foreach($pool as&$rows)usort($rows,fn($x,$y)=>$x['tv_hotel_id']<=>$y['tv_hotel_id']);unset($rows);ksort($pool,SORT_NATURAL|SORT_FLAG_CASE);
 $rows=[];while(count($rows)<M108_LIMIT){$added=0;foreach(array_keys($pool)as$c){if(!$pool[$c])continue;$rows[]=array_shift($pool[$c]);$added++;if(count($rows)>=M108_LIMIT)break;}if(!$added)break;}
 m108_need(count($rows)>0&&count($rows)<=M108_LIMIT,'scope_empty');$ids=array_column($rows,'tv_hotel_id');m108_need(count($ids)===count(array_unique($ids)),'dup');
 $countries=[];foreach($rows as$r)$countries[$r['country']]=($countries[$r['country']]??0)+1;
 $out=['operation'=>M108_OP,'state'=>'completed_read_only_anexonly200_plan','source_sha'=>$head,'generated_at_utc'=>gmdate('c'),'current_tv_total'=>$m['tv_live30_total'],'current_nontriple'=>$m['live30_non_triple_total'],'current_gap_counts'=>$m['gap_bucket_counts'],'eligible_count'=>array_sum(array_map('count',$pool))+count($rows),'excluded_exact_context_replay'=>$replay,'excluded_no_context'=>$noctx,'excluded_retained_complete'=>$complete,'scope_count'=>count($rows),'scope_id_sha256'=>hash('sha256',json_encode($ids,JSON_UNESCAPED_SLASHES)),'country_counts'=>$countries,'tourvisor_account'=>'TOURVISOR_ANEX_JWT','operator_filter_sent'=>false,'continue_calls'=>0,'dates_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'rows'=>$rows];
 $sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>M108_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo w76_json(array_diff_key($out,['rows'=>true]))."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(m108_main($argv));
