<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_live30_common4_gap_matrix_v1.php';
const M89_OP='hotel-match-next-native-gap-plan-1971-20260928-v89b';
const M89_LIMIT=200;
function m89_need(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
function m89_num(mixed $v):?int{if(is_int($v)&&$v>0)return$v;if(is_string($v)&&preg_match('/^[1-9][0-9]{0,20}$/D',$v))return(int)$v;return null;}
function m89_read(string $p,int $cap=33554432):?array{if(!is_file($p)||is_link($p)||filesize($p)<=0||filesize($p)>$cap)return null;try{$x=json_decode((string)file_get_contents($p),true,128,JSON_THROW_ON_ERROR);return is_array($x)?$x:null;}catch(Throwable){return null;}}
function m89_collect_ids(mixed $x,array &$out,int $depth=0):void{
 if($depth>5||!is_array($x))return;
 foreach(['scope_hotel_ids','hotel_ids','hotelIds'] as$k)if(isset($x[$k])&&is_array($x[$k]))foreach($x[$k]as$v)if(($n=m89_num($v))!==null)$out[$n]=true;
 foreach(['tv_hotel_id','hotel_id']as$k)if(isset($x[$k])&&($n=m89_num($x[$k]))!==null)$out[$n]=true;
 foreach($x as$v)if(is_array($v))m89_collect_ids($v,$out,$depth+1);
}
function m89_archive(string $ops):array{
 $attempted=[];$exact=[];$files=0;$bytes=0;$bad=0;$opdirs=0;
 foreach(new DirectoryIterator($ops)as$op){if($op->isDot()||!$op->isDir()||$op->isLink()||!str_starts_with($op->getFilename(),'hotel-match-'))continue;$opdirs++;
  $dir=$op->getPathname();
  foreach(new DirectoryIterator($dir)as$f){if($f->isDot()||!$f->isFile()||$f->isLink())continue;$name=$f->getFilename();if(!preg_match('/^(?:tv-request-|tv-edge-|tv-plan|result\.json)/',$name))continue;
   $sz=$f->getSize();if($sz<=0||$sz>33554432)continue;$files++;$bytes+=$sz;m89_need($files<=100000&&$bytes<=2147483648,'archive_scan_budget');$j=m89_read($f->getPathname());if(!$j){$bad++;continue;}
   if(str_starts_with($name,'tv-request-')){if(($j['action']??'')==='search_start')m89_collect_ids($j['params']??[],$attempted);continue;}
   if($name==='tv-plan.json'){m89_collect_ids($j,$attempted);continue;}
   if(str_starts_with($name,'tv-edge-')){
    $id=m89_num($j['tv_hotel_id']??null);$opid=m89_num($j['operator_id']??null);$ids=$j['positive_native_candidates']??[];
    if($id&&in_array($opid,[13,18,25,43],true)&&($j['link_state']??'')==='captured_single_native'&&is_array($ids)&&count($ids)===1&&m89_num($ids[0]))$exact[$id][$opid][(string)m89_num($ids[0])]=true;continue;
   }
   if($name==='result.json'){
    $calls=max((int)($j['provider_calls']??0),(int)($j['provider_http_calls']??0),(int)($j['supplier_calls']??0));
    if($calls>0)m89_collect_ids(['scope_hotel_ids'=>$j['scope_hotel_ids']??[]],$attempted);
    foreach($j['edges']??[]as$e)if(is_array($e)){
      $id=m89_num($e['tv_hotel_id']??null);$opid=m89_num($e['operator_id']??null);$ids=$e['positive_native_candidates']??[];
      if($id&&in_array($opid,[13,18,25,43],true)&&($e['link_state']??'')==='captured_single_native'&&is_array($ids)&&count($ids)===1&&m89_num($ids[0]))$exact[$id][$opid][(string)m89_num($ids[0])]=true;
    }
   }
  }
 }
 ksort($attempted,SORT_NUMERIC);ksort($exact,SORT_NUMERIC);return['attempted'=>$attempted,'exact'=>$exact,'operation_dirs'=>$opdirs,'files_examined'=>$files,'bytes_examined'=>$bytes,'invalid_json'=>$bad];
}
function m89_ctx(PDO$db,array$ids):array{$out=[];foreach(array_chunk($ids,250)as$c){$ph=implode(',',array_fill(0,count($c),'?'));$q=$db->prepare("SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,observed_at,source FROM tour_price_observations WHERE hotel_id IN ($ph) ORDER BY hotel_id,(departure_date>=CURDATE()) DESC,(source='user_search') DESC,observed_at DESC");$q->execute($c);foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[]as$r){$id=(int)$r['hotel_id'];if(!isset($out[$id]))$out[$id]=$r;}}return$out;}
function m89_plan(PDO$db,string$ops):array{
 $m=hmc4_execute($db);m89_need(($m['state']??'')==='completed_read_only_common4_gap_matrix','matrix');$a=m89_archive($ops);
 $facts=[];foreach($db->query("SELECT id,country_id,country_name,name FROM catalog_hotels WHERE is_active=1 AND country_name NOT IN ('Россия','Абхазия','Russia','Abkhazia','Russian Federation') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[]as$r)$facts[(int)$r['id']]=$r;
 $map=['anex'=>13,'biblio'=>18,'funsun'=>25,'intourist'=>43];$candidates=[];$excludedAttempt=0;$excludedRetained=0;
 foreach($m['rows']as$r){$id=(int)$r['tv_hotel_id'];if(!isset($facts[$id]))continue;$missing=[];foreach($map as$lane=>$opid)if(empty($r['lanes'][$lane]['exact_evidence'])&&empty($a['exact'][$id][$opid]))$missing[]=$opid;
  if(!$missing){$excludedRetained++;continue;}if(isset($a['attempted'][$id])){$excludedAttempt++;continue;}
  $candidates[]=['tv_hotel_id'=>$id,'hotel_name'=>$facts[$id]['name'],'country_id'=>(int)$facts[$id]['country_id'],'country'=>$facts[$id]['country_name'],'gap_bucket'=>$r['gap_bucket'],'missing_operator_ids'=>$missing];
 }
 $rank=['Турция'=>1,'Таиланд'=>2,'Вьетнам'=>3,'Китай'=>4,'ОАЭ'=>5,'Египет'=>6,'Мальдивы'=>7,'Катар'=>8];usort($candidates,function($x,$y)use($rank){$a=$rank[$x['country']]??99;$b=$rank[$y['country']]??99;return[$a,$x['tv_hotel_id']]<=>[$b,$y['tv_hotel_id']];});
 $selected=array_slice($candidates,0,M89_LIMIT);m89_need(count($selected)>0&&count($selected)<=M89_LIMIT,'scope_empty_or_over_200');$ids=array_column($selected,'tv_hotel_id');$ctx=m89_ctx($db,$ids);$deps=[];foreach($db->query("SELECT country_id,departure_id FROM catalog_departure_countries WHERE is_active=1 ORDER BY country_id,(departure_id=1) DESC,departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[]as$r){$c=(int)$r['country_id'];if(!isset($deps[$c]))$deps[$c]=(int)$r['departure_id'];}
 foreach($selected as&$r){$id=$r['tv_hotel_id'];$c=$ctx[$id]??null;$cid=$r['country_id'];if(!$c)$c=['departure_id'=>$deps[$cid]??1,'departure_date'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d'),'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];if((string)$c['departure_date']<gmdate('Y-m-d'))$c['departure_date']=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');$r+=['departure_id'=>(int)$c['departure_id'],'departure_date'=>(string)$c['departure_date'],'nights'=>max(1,min(28,(int)$c['nights'])),'adults'=>max(1,min(6,(int)$c['adults'])),'children_count'=>max(0,min(3,(int)$c['children_count'])),'child_ages_signature'=>(string)$c['child_ages_signature']];}unset($r);
 return['operation'=>M89_OP,'state'=>'completed_read_only_next_native_gap_plan','generated_at_utc'=>gmdate('c'),'tv_live30_total'=>$m['tv_live30_total'],'current_nontriple_total'=>$m['live30_non_triple_total'],'archive_operation_dirs'=>$a['operation_dirs'],'archive_files_examined'=>$a['files_examined'],'archive_invalid_json'=>$a['invalid_json'],'historically_attempted_hotel_count'=>count($a['attempted']),'retained_exact_hotel_count'=>count($a['exact']),'excluded_already_attempted_current'=>$excludedAttempt,'excluded_retained_exact_complete'=>$excludedRetained,'eligible_unattempted_current_count'=>count($candidates),'scope_count'=>count($selected),'rows'=>$selected,'tourvisor_account'=>'TOURVISOR_ANEX_JWT','operator_filter_sent'=>false,'continue_calls'=>0,'dates_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}
function m89_main(array$argv):int{m89_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');m89_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===M89_OP&&preg_match('/^[a-f0-9]{40}$/D',$head),'scope');foreach(['result.json','receipt.json']as$f)m89_need(!file_exists($dir.'/'.$f),'no_replay');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$out=m89_plan(v2_data_db(),dirname($dir));$out['source_sha']=$head;$sha=w76_save($dir.'/result.json',$out);w76_save($dir.'/receipt.json',['operation'=>M89_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo json_encode(array_diff_key($out,['rows'=>true]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";return 0;}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(m89_main($argv));
