<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
require_once __DIR__.'/hotel_match_retained93_primary_write_v84.php';
const M96_OP='hotel-match-newcontext-samoonly200-plan-1971-20260928-v96';
const M96_LEDGER_OP='hotel-match-request-ledger-1971-20260928-v93';
const M96_LEDGER_SHA='8da64bc3ac54d707d335b8808b171f46a02ba0464536681bc40b741136df935c';
const M96_LIMIT=200;
function m96_need(bool$b,string$m):void{if(!$b)throw new RuntimeException($m);}
function m96_main(array$a):int{
 m96_need(($a[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 m96_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===M96_OP&&preg_match('/^[a-f0-9]{40}$/D',$head),'scope');
 foreach(['result.json','receipt.json']as$f)m96_need(!file_exists($dir.'/'.$f),'no_replay');
 $ledger=w84_read(dirname($dir).'/'.M96_LEDGER_OP.'/result.json',M96_LEDGER_SHA,33554432);
 m96_need(($ledger['state']??'')==='completed_read_only_request_ledger'&&($ledger['provider_http_calls']??-1)===0,'ledger');
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$m=hmc4_execute($db);
 m96_need(($m['state']??'')==='completed_read_only_common4_gap_matrix'&&($m['tv_live30_total']??0)===4399,'matrix');
 $current=[];foreach($m['rows']as$r)$current[(int)$r['tv_hotel_id']]=$r;
 $reqBy=[];foreach($ledger['requests']as$q){foreach($q['hotel_ids']as$id)$reqBy[(int)$id][]=$q;}
 $rows=[];$excludedReplay=0;$excludedNotCurrent=0;$excludedNative=0;
 foreach($ledger['rows']as$r){
  $id=(int)$r['tv_hotel_id'];$c=$current[$id]??null;if(!$c||($c['gap_bucket']??'')!=='samo_only'){$excludedNotCurrent++;continue;}
  if(($r['country']??'')!=='Турция')continue;
  if(!in_array(13,array_map('intval',$r['missing_operator_ids']??[]),true))continue;
  if(!empty($r['lanes']['13']['retained_exact'])){$excludedNative++;continue;}
  $countryIds=[];foreach($reqBy[$id]??[]as$q){$cid=(int)($q['context']['countryId']??0);if($cid>0)$countryIds[$cid]=($countryIds[$cid]??0)+1;}arsort($countryIds);$cid=(int)(array_key_first($countryIds)??0);if($cid!==4)continue;
  $replay=false;foreach($reqBy[$id]??[]as$q){$x=$q['context'];if((int)($x['departureId']??0)===1&&(int)($x['countryId']??0)===4&&($x['dateFrom']??'')==='2026-10-15'&&($x['dateTo']??'')==='2026-10-15'&&(int)($x['nightsFrom']??0)===7&&(int)($x['nightsTo']??0)===7&&(int)($x['adults']??0)===2&&($x['childs']??[])===[]){$replay=true;break;}}
  if($replay){$excludedReplay++;continue;}
  $missing=[];foreach([13,18,25,43]as$op)if(in_array($op,array_map('intval',$r['missing_operator_ids']??[]),true)&&empty($r['lanes'][(string)$op]['retained_exact']))$missing[]=$op;
  if(!in_array(13,$missing,true))continue;
  $rows[]=['tv_hotel_id'=>$id,'hotel_name'=>$r['hotel_name'],'country_id'=>4,'country'=>'Турция','departure_id'=>1,'departure_date'=>'2026-10-15','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','missing_operator_ids'=>$missing,'gap_bucket'=>'samo_only'];
 }
 usort($rows,fn($x,$y)=>$x['tv_hotel_id']<=>$y['tv_hotel_id']);$eligible=count($rows);$rows=array_slice($rows,0,M96_LIMIT);m96_need(count($rows)===M96_LIMIT,'scope_not_200');
 $ids=array_column($rows,'tv_hotel_id');m96_need(count(array_unique($ids))===M96_LIMIT,'dup');
 $out=['operation'=>M96_OP,'state'=>'completed_read_only_newcontext_plan','source_sha'=>$head,'generated_at_utc'=>gmdate('c'),'current_tv_total'=>$m['tv_live30_total'],'current_nontriple'=>$m['live30_non_triple_total'],'eligible_current_samo_only_missing_anex_new_context'=>$eligible,'excluded_exact_context_replay'=>$excludedReplay,'excluded_not_current_samo_only'=>$excludedNotCurrent,'excluded_retained_anex_native'=>$excludedNative,'scope_count'=>count($rows),'scope_id_sha256'=>hash('sha256',json_encode($ids,JSON_UNESCAPED_SLASHES)),'tourvisor_account'=>'TOURVISOR_ANEX_JWT','operator_filter_sent'=>false,'continue_calls'=>0,'dates_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'rows'=>$rows];
 $sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>M96_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo w76_json(array_diff_key($out,['rows'=>true]))."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(m96_main($argv));
