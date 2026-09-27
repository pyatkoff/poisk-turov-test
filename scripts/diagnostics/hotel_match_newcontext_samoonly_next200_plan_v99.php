<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
require_once __DIR__.'/hotel_match_retained93_primary_write_v84.php';
const M99_OP='hotel-match-newcontext-samoonly-next200-plan-1971-20260928-v99';
const M99_LEDGER_OP='hotel-match-request-ledger-1971-20260928-v93';
const M99_LEDGER_SHA='8da64bc3ac54d707d335b8808b171f46a02ba0464536681bc40b741136df935c';
const M99_PRIOR_PLAN_OP='hotel-match-newcontext-samoonly200-plan-1971-20260928-v96';
const M99_PRIOR_PLAN_SHA='4d4e0f8ea69c71d07a773f9063565f5ba51620c2290584647956928bfe5736c1';
function m99_need(bool$b,string$m):void{if(!$b)throw new RuntimeException($m);}
function m99_main(array$a):int{
 m99_need(($a[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 m99_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===M99_OP&&preg_match('/^[a-f0-9]{40}$/D',$head),'scope');foreach(['result.json','receipt.json']as$f)m99_need(!file_exists($dir.'/'.$f),'no_replay');
 $ops=dirname($dir);$ledger=w84_read($ops.'/'.M99_LEDGER_OP.'/result.json',M99_LEDGER_SHA,33554432);$prior=w84_read($ops.'/'.M99_PRIOR_PLAN_OP.'/result.json',M99_PRIOR_PLAN_SHA,16777216);
 m99_need(($ledger['state']??'')==='completed_read_only_request_ledger'&&($prior['state']??'')==='completed_read_only_newcontext_plan'&&count($prior['rows']??[])===200,'inputs');
 $used=[];foreach($prior['rows']as$r)$used[(int)$r['tv_hotel_id']]=true;
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$m=hmc4_execute(v2_data_db());m99_need(($m['state']??'')==='completed_read_only_common4_gap_matrix','matrix');$current=[];foreach($m['rows']as$r)$current[(int)$r['tv_hotel_id']]=$r;
 $req=[];foreach($ledger['requests']as$q)foreach($q['hotel_ids']as$id)$req[(int)$id][]=$q;$rank=['Турция'=>1,'Таиланд'=>2,'Вьетнам'=>3,'Китай'=>4,'ОАЭ'=>5,'Египет'=>6,'Мальдивы'=>7,'Катар'=>8];$rows=[];$replay=0;
 foreach($ledger['rows']as$r){$id=(int)$r['tv_hotel_id'];if(isset($used[$id]))continue;$c=$current[$id]??null;if(!$c||($c['gap_bucket']??'')!=='samo_only')continue;if(!in_array(13,array_map('intval',$r['missing_operator_ids']??[]),true)||!empty($r['lanes']['13']['retained_exact']))continue;
  $cids=[];foreach($req[$id]??[]as$q){$cid=(int)($q['context']['countryId']??0);if($cid>0)$cids[$cid]=($cids[$cid]??0)+1;}arsort($cids);$cid=(int)(array_key_first($cids)??0);if($cid<1)continue;
  $dup=false;foreach($req[$id]??[]as$q){$x=$q['context'];if((int)($x['departureId']??0)===1&&(int)($x['countryId']??0)===$cid&&($x['dateFrom']??'')==='2026-10-15'&&($x['dateTo']??'')==='2026-10-15'&&(int)($x['nightsFrom']??0)===7&&(int)($x['nightsTo']??0)===7&&(int)($x['adults']??0)===2&&($x['childs']??[])===[]){$dup=true;break;}}if($dup){$replay++;continue;}
  $missing=[];foreach([13,18,25,43]as$op)if(in_array($op,array_map('intval',$r['missing_operator_ids']??[]),true)&&empty($r['lanes'][(string)$op]['retained_exact']))$missing[]=$op;if(!in_array(13,$missing,true))continue;
  $rows[]=['tv_hotel_id'=>$id,'hotel_name'=>$r['hotel_name'],'country_id'=>$cid,'country'=>$r['country'],'departure_id'=>1,'departure_date'=>'2026-10-15','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>'','missing_operator_ids'=>$missing,'gap_bucket'=>'samo_only','rank'=>$rank[$r['country']]??99];
 }
 usort($rows,fn($x,$y)=>[$x['rank'],$x['tv_hotel_id']]<=>[$y['rank'],$y['tv_hotel_id']]);$eligible=count($rows);$rows=array_slice($rows,0,200);foreach($rows as&$r)unset($r['rank']);unset($r);m99_need(count($rows)===200,'scope200');
 $ids=array_column($rows,'tv_hotel_id');$countries=[];foreach($rows as$r)$countries[$r['country']]=($countries[$r['country']]??0)+1;
 $out=['operation'=>M99_OP,'state'=>'completed_read_only_next200_plan','source_sha'=>$head,'generated_at_utc'=>gmdate('c'),'current_tv_total'=>$m['tv_live30_total'],'current_nontriple'=>$m['live30_non_triple_total'],'prior_scope_excluded'=>count($used),'eligible_after_prior_scope'=>$eligible,'excluded_exact_context_replay'=>$replay,'scope_count'=>200,'scope_id_sha256'=>hash('sha256',json_encode($ids,JSON_UNESCAPED_SLASHES)),'country_counts'=>$countries,'tourvisor_account'=>'TOURVISOR_ANEX_JWT','operator_filter_sent'=>false,'continue_calls'=>0,'dates_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'rows'=>$rows];
 $sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>M99_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo w76_json(array_diff_key($out,['rows'=>true]))."\n";return 0;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(m99_main($argv));
