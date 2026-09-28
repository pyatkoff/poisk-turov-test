<?php
declare(strict_types=1);
/** Supplier-free receipt inventory. Never treats a plan as a request or authorizes HTTP. */
const L93_OP='hotel-match-request-ledger-1971-20260928-v93';
const L93_OPS=[13,18,25,43];
function l93_need(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
function l93_json(mixed $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function l93_ids(mixed $a):array{l93_need(is_array($a)&&array_is_list($a),'id_list');$out=[];foreach($a as$v){l93_need((is_int($v)||is_string($v))&&preg_match('/^[1-9][0-9]{0,8}$/D',(string)$v)===1,'positive_id');$out[(int)$v]=true;}$r=array_keys($out);sort($r,SORT_NUMERIC);return$r;}
function l93_request(array $j):?array{
 if(($j['action']??null)!=='search_start')return null;
 l93_need(($j['path']??null)==='/tours/search'&&is_array($j['params']??null),'search_shape');$p=$j['params'];
 $ids=l93_ids($p['hotelIds']??null);l93_need(count($ids)>0,'empty_hotels');
 $operators=array_key_exists('operatorIds',$p)?l93_ids($p['operatorIds']):L93_OPS;
 l93_need($operators!==[],'empty_operator_scope');$operators=array_values(array_intersect(L93_OPS,$operators));
 $keys=['departureId','countryId','dateFrom','dateTo','nightsFrom','nightsTo','adults','childs','currency','onlyCharter','hotelIds','operatorIds'];
 l93_need(!array_diff(array_keys($p),$keys),'unknown_request_filter');
 foreach(['departureId','countryId','nightsFrom','nightsTo','adults']as$k){l93_need(isset($p[$k]),'missing_context');$p[$k]=l93_ids([$p[$k]])[0];}
 foreach(['dateFrom','dateTo']as$k)l93_need(is_string($p[$k]??null)&&preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$p[$k])===1,'date_shape');
 l93_need(is_array($p['childs']??null)&&array_is_list($p['childs']),'children_shape');foreach($p['childs']as$v)l93_need(is_int($v)&&$v>=0&&$v<=17,'child_age');sort($p['childs'],SORT_NUMERIC);
 l93_need(($p['currency']??null)==='RUB'&&is_bool($p['onlyCharter']??null),'price_context_shape');
 unset($p['hotelIds'],$p['operatorIds']);ksort($p,SORT_STRING);
 return['hotel_ids'=>$ids,'operator_ids'=>$operators,'context'=>$p,'context_sha256'=>hash('sha256',l93_json($p))];
}
function l93_plan_ids(array $j):array{
 $out=[];foreach(['scope_hotel_ids','hotel_ids','hotelIds']as$k)if(isset($j[$k])&&is_array($j[$k]))foreach($j[$k]as$v)if((is_int($v)||is_string($v))&&preg_match('/^[1-9][0-9]{0,8}$/D',(string)$v))$out[(int)$v]=true;
 foreach($j['rows']??[]as$r)if(is_array($r)&&isset($r['tv_hotel_id'])&&is_numeric($r['tv_hotel_id']))$out[(int)$r['tv_hotel_id']]=true;
 return array_keys($out);
}
function l93_read(string $p,array &$stats,int $cap=33554432):array{
 l93_need(is_file($p)&&!is_link($p),'file');$sz=filesize($p);l93_need($sz>0&&$sz<=$cap,'file_size');$stats['files']++;$stats['bytes']+=$sz;l93_need($stats['files']<=100000&&$stats['bytes']<=2147483648,'scan_budget');
 $b=file_get_contents($p);l93_need(is_string($b)&&strlen($b)===$sz,'file_changed');$j=json_decode($b,true,128,JSON_THROW_ON_ERROR);l93_need(is_array($j),'object');return[$j,hash('sha256',$b)];
}
function l93_inventory(string $ops,array $front):array{
 $stats=['files'=>0,'bytes'=>0];$ledger=[];$planned=[];$actual=[];$exact=[];$issues=[];$accounting=[];$summaries=[];
 $dirs=[];foreach(new DirectoryIterator($ops)as$d)if(!$d->isDot()&&$d->isDir()&&!$d->isLink()&&str_starts_with($d->getFilename(),'hotel-match-')&&$d->getFilename()!==L93_OP)$dirs[$d->getFilename()]=$d->getPathname();ksort($dirs,SORT_STRING);
 foreach($dirs as$op=>$dir){
  $summary=['operation'=>$op,'request_receipts'=>0,'actions'=>[],'search_start_receipts'=>0,'matched_frontier_hotels'=>[],'plans'=>[],'result'=>null,'errors'=>[]];$byReceipt=[];
  $files=[];foreach(new DirectoryIterator($dir)as$f)if(!$f->isDot()&&$f->isFile()&&!$f->isLink()&&(preg_match('/^tv-request-[0-9]+\.json$/D',$f->getFilename())||preg_match('/^tv-edge-[0-9]+-[0-9]+\.json$/D',$f->getFilename())||in_array($f->getFilename(),['tv-plan.json','plan.json','plan-pre.json','result.json','quota-reservation.json'],true)))$files[$f->getFilename()]=$f->getPathname();ksort($files,SORT_STRING);
  foreach($files as$name=>$path){
   // Very large unrelated historical censuses are not request ledgers.
   if($name==='result.json'&&filesize($path)>33554432){$summary['result']=['skipped_large_non_request_file'=>true];continue;}
   try{[$j,$sha]=l93_read($path,$stats);}catch(Throwable$e){$issue=['operation'=>$op,'file'=>$name,'reason'=>get_class($e).':'.(preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'invalid_json')];$issues[]=$issue;$summary['errors'][]=$issue;continue;}
   $ref=['operation'=>$op,'file'=>$name,'sha256'=>$sha];
   if(str_starts_with($name,'tv-request-')){
    $summary['request_receipts']++;$action=is_string($j['action']??null)?$j['action']:'UNKNOWN';$summary['actions'][$action]=($summary['actions'][$action]??0)+1;
    if($action==='UNKNOWN'){$issues[]=$ref+['reason'=>'request_action_missing'];continue;}
    try{$rq=l93_request($j);}catch(Throwable$e){$issues[]=$ref+['reason'=>$e->getMessage()];continue;}
    if($rq===null)continue;$summary['search_start_receipts']++;
    $relevant=array_values(array_intersect($rq['hotel_ids'],array_keys($front)));if(!$relevant)continue;
    $ledger[]=$rq+['proof'=>$ref];
    foreach($relevant as$id){$summary['matched_frontier_hotels'][$id]=true;$byReceipt[$id]=true;foreach($rq['operator_ids']as$to)$actual[$id][$to][$rq['context_sha256']]=true;}
   }elseif(in_array($name,['tv-plan.json','plan.json','plan-pre.json'],true)){
    $pids=l93_plan_ids($j);$summary['plans'][]=$ref+['hotel_count'=>count($pids)];foreach($pids as$id)if(isset($front[$id]))$planned[$id]=true;
   }elseif($name==='quota-reservation.json'){
    $s=$ref;foreach(['provider_day','accounted_before_local_ledger','cap','limit']as$k)if(isset($j[$k])&&is_scalar($j[$k]))$s[$k]=$j[$k];$accounting[]=$s;
   }
   $edges=[];
   if(str_starts_with($name,'tv-edge-'))$edges=[$j];
   if($name==='result.json'){
    $s=$ref;foreach(['operation','state','provider_calls','provider_http_calls','supplier_calls','database_writes','mapping_writes','provider_day','accounted_requests_after','tourvisor_account','generated_at_utc']as$k)if(isset($j[$k])&&is_scalar($j[$k]))$s[$k]=$j[$k];
    $s['scope_hotel_ids']=l93_plan_ids(['scope_hotel_ids'=>$j['scope_hotel_ids']??[]]);$s['scope_current_ids']=array_values(array_intersect($s['scope_hotel_ids'],array_keys($front)));$summary['result']=$s;$edges=$j['edges']??[];
   }
   foreach($edges as$i=>$e){if(!is_array($e))continue;$id=(int)($e['tv_hotel_id']??0);$to=(int)($e['operator_id']??0);if(!isset($front[$id])||!in_array($to,L93_OPS,true))continue;$ns=$e['positive_native_candidates']??[];
    if(($e['state']??'')==='detail_identity_verified'&&($e['link_state']??'')==='captured_single_native'&&is_array($ns)&&count($ns)===1&&(is_int($ns[0])||is_string($ns[0]))&&preg_match('/^[1-9][0-9]{0,20}$/D',(string)$ns[0]))$exact[$id][$to][(string)$ns[0]]=$ref+['json_pointer'=>str_starts_with($name,'tv-edge-')?'':'/edges/'.$i];
   }
  }
  $summary['matched_frontier_hotels']=array_keys($summary['matched_frontier_hotels']);sort($summary['matched_frontier_hotels'],SORT_NUMERIC);
  $s=$summary['result']??[];$charge=max((int)($s['provider_calls']??0),(int)($s['provider_http_calls']??0),(int)($s['supplier_calls']??0));
  $summary['charged_scope_without_search_receipt']=$charge>0?array_values(array_diff($s['scope_current_ids']??[],array_keys($byReceipt))):[];
  if($summary['request_receipts']||$summary['plans']||$summary['charged_scope_without_search_receipt']||$summary['errors'])$summaries[]=$summary;
 }
 $unknown=[];foreach($summaries as$s)foreach($s['charged_scope_without_search_receipt']as$id)$unknown[$id][]=$s['operation'];
 return['scan'=>$stats+['operation_dirs'=>count($dirs)],'requests'=>$ledger,'planned'=>$planned,'actual'=>$actual,'exact'=>$exact,'unknown'=>$unknown,'issues'=>$issues,'operation_summaries'=>$summaries,'quota_reservations'=>$accounting];
}
function l93_selftest():void{
 $n=0;$t=function($b)use(&$n){$n++;l93_need($b,'test_'.$n);};$p=['departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-10-11','dateTo'=>'2026-10-11','nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'hotelIds'=>[3,1,3]];$r=['action'=>'search_start','path'=>'/tours/search','params'=>$p];$x=l93_request($r);$t($x['hotel_ids']===[1,3]);$t($x['operator_ids']===L93_OPS);
 $s=$r;$s['params']['hotelIds']=[9];$t(l93_request($s)['context_sha256']===$x['context_sha256']);$s['params']['dateFrom']='2026-10-12';$t(l93_request($s)['context_sha256']!==$x['context_sha256']);
 $s=$r;$s['params']['operatorIds']=[25];$t(l93_request($s)['operator_ids']===[25]);$t(l93_request(['scope_hotel_ids'=>[1,3]])===null);$t(l93_request(['action'=>'search_results','params'=>['hotelIds'=>[1,3]]])===null);
 foreach(['operatorIds'=>[],'hotelIds'=>[],'token'=>'fixture-secret','onlyCharter'=>'false','childs'=>[20]]as$k=>$v){$s=$r;$s['params'][$k]=$v;try{l93_request($s);$t(false);}catch(RuntimeException$e){$t(!str_starts_with($e->getMessage(),'test_'));}}
 $d=sys_get_temp_dir().'/match-ledger-fixture-'.bin2hex(random_bytes(6));mkdir($d);mkdir($d.'/hotel-match-fixture');$q=$d.'/hotel-match-fixture/';file_put_contents($q.'tv-plan.json',l93_json(['scope_hotel_ids'=>[1,2,3]]));file_put_contents($q.'tv-request-0001.json',l93_json($r));file_put_contents($q.'result.json',l93_json(['provider_http_calls'=>1,'scope_hotel_ids'=>[1,2,3]]));$z=l93_inventory($d,[1=>true,2=>true,3=>true]);$t(isset($z['planned'][2])&&!isset($z['actual'][2]));$t(isset($z['unknown'][2]));$t(isset($z['actual'][1][13],$z['actual'][3][43]));$t(count($z['requests'])===1);$t($z['issues']===[]);
 foreach(glob($q.'*')as$f)unlink($f);rmdir($q);rmdir($d);echo 'L93_TEST_PASS '.$n."\n";
}
function l93_main(array $a):int{
 if(($a[1]??'')==='--self-test'){l93_selftest();return 0;}
 l93_need(($a[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
 l93_need(is_dir($root)&&!is_link($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&!is_link($dir)&&basename($dir)===L93_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'scope');
 require_once __DIR__.'/hotel_match_retained93_primary_write_v84.php';require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
 $rv=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);l93_need(($rv['operation']??null)===L93_OP&&($rv['source_sha']??null)===$head&&($rv['provider_http_calls']??-1)===0&&($rv['database_writes']??-1)===0,'reservation');
 foreach(['execution-started.json','result.json','receipt.json']as$f)l93_need(!file_exists($dir.'/'.$f),'no_replay');w76_save($dir.'/execution-started.json',['operation'=>L93_OP,'source_sha'=>$head]);
 try{
  require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$m=hmc4_execute($db);l93_need(($m['state']??null)==='completed_read_only_common4_gap_matrix','matrix');$front=[];foreach($m['rows']as$r)$front[(int)$r['tv_hotel_id']]=$r;
  $inv=l93_inventory(dirname($dir),$front);$rows=[];$counts=['planned_current'=>0,'actual_request_current'=>0,'planned_without_actual_current'=>0,'unknown_charged_current'=>0,'missing_native_lanes'=>0,'missing_lanes_without_any_recorded_request'=>0];
  foreach($front as$id=>$r){$missing=[];$lanes=[];foreach([13=>'anex',18=>'biblio',25=>'funsun',43=>'intourist']as$to=>$name){$exact=$inv['exact'][$id][$to]??[];$known=($r['lanes'][$name]['exact_evidence']??false)||count($exact)===1;$attempts=count($inv['actual'][$id][$to]??[]);if(!$known){$missing[]=$to;$counts['missing_native_lanes']++;if(!$attempts)$counts['missing_lanes_without_any_recorded_request']++;}$lanes[$to]=['retained_exact'=>array_keys($exact),'known_exact'=>$known,'request_contexts'=>$attempts];}
   $planned=isset($inv['planned'][$id]);$attempted=isset($inv['actual'][$id]);$unknown=$inv['unknown'][$id]??[];$counts['planned_current']+=(int)$planned;$counts['actual_request_current']+=(int)$attempted;$counts['planned_without_actual_current']+=(int)($planned&&!$attempted);$counts['unknown_charged_current']+=(int)!!$unknown;
   $rows[]=['tv_hotel_id'=>$id,'hotel_name'=>$r['name']??$r['hotel_name']??null,'country'=>$r['country']??$r['country_name']??null,'gap_bucket'=>$r['gap_bucket'],'lanes'=>$lanes,'missing_operator_ids'=>$missing,'planned'=>$planned,'actual_request'=>$attempted,'unknown_charged_operations'=>$unknown,'query_authorized'=>false];
  }
  $cohort=w84_live_sources($root);$db->exec('START TRANSACTION READ ONLY');try{$c=w76_census($db);$ident=w76_q($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001');$sc=w84_samo_census($ident,$cohort);$db->rollBack();}catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
  l93_need(count($front)===$c['tv_total']-$c['full_triple'],'frontier_drift');
  $quota=[];$qd=dirname(dirname($dir)).'/provider-quotas';$day=(new DateTimeImmutable('now',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');$qp=$qd.'/tourvisor-test-'.$day.'.json';if(is_file($qp)){[$q,$qs]=l93_read($qp,$inv['scan'],1048576);foreach(['provider','provider_day','owner_daily_limit','accounted_requests','known_prior_attempt_floor','match_new_attempts','baseline_status']as$k)if(isset($q[$k])&&is_scalar($q[$k]))$quota[$k]=$q[$k];$quota['sha256']=$qs;}else$quota=['provider_day'=>$day,'ledger_missing'=>true];
  $out=['state'=>'completed_read_only_request_ledger','coverage_current'=>$c,'samo_live30_current'=>$sc,'counts'=>$counts,'scan'=>$inv['scan'],'issues'=>$inv['issues'],'rows'=>$rows,'requests'=>$inv['requests'],'operation_summaries'=>$inv['operation_summaries'],'quota_reservations'=>$inv['quota_reservations'],'quota_current'=>$quota,'safe_to_query_now'=>false];
 }catch(Throwable$e){$out=['state'=>'failed_read_only_request_ledger','reason'=>preg_match('/^[a-z_]+$/D',$e->getMessage())?$e->getMessage():'inventory_failed','error_class'=>get_class($e)];}
 $out+=['operation'=>L93_OP,'source_sha'=>$head,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'new_operator_ids'=>0,'no_replay'=>true,'generated_at_utc'=>gmdate('c')];$sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>L93_OP,'source_sha'=>$head,'state'=>$out['state'],'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
 echo l93_json(array_intersect_key($out,array_flip(['state','reason','counts','scan','coverage_current','quota_current'])))."\n";return$out['state']==='completed_read_only_request_ledger'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(l93_main($argv));
