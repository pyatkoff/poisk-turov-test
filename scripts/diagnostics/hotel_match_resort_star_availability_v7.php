<?php
declare(strict_types=1);
ini_set('display_errors','0');ini_set('log_errors','0');
const OP='hotel-match-resort-star-availability-1971-20260911-v7';
require_once getcwd().'/config.php';
$tvClient=is_file(getcwd().'/data/tourvisor-client-v1.php')?getcwd().'/data/tourvisor-client-v1.php':getcwd().'/v2/data/tourvisor-client-v1.php';require_once $tvClient;
if(!defined('TOURVISOR_ANEX_JWT'))throw new RuntimeException('credential_missing');
$token=trim((string)constant('TOURVISOR_ANEX_JWT'));if(stripos($token,'Bearer ')===0)$token=trim(substr($token,7));if($token==='')throw new RuntimeException('credential_empty');putenv('TOURVISOR_JWT='.$token);
$regions=[
 ['id'=>20,'name'=>'Анталья'],['id'=>19,'name'=>'Аланья'],['id'=>22,'name'=>'Кемер'],['id'=>21,'name'=>'Белек'],['id'=>23,'name'=>'Сиде'],['id'=>24,'name'=>'Бодрум'],['id'=>26,'name'=>'Мармарис'],
];
$dates=['2026-09-25','2026-10-02','2026-10-16','2026-11-06','2026-11-20'];
$rows=[];$searches=0;
foreach($regions as $r){
 foreach([3,4,5] as $star){
  $entry=['region'=>$r,'star'=>$star,'attempts'=>[],'active_date'=>null,'hotel_count'=>0];
  foreach($dates as $date){
   $searches++;
   try{
    $start=v2_data_tv_get('/tours/search',['departureId'=>1,'countryId'=>4,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,'operatorIds'=>[13],'regionIds'=>[$r['id']],'hotelCategory'=>$star]);
    $sid=(int)($start['searchId']??$start['id']??0);if($sid<=0)throw new RuntimeException('search_id_missing');
    $done=false;for($i=0;$i<24;$i++){if($i)usleep(700000);$s=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);$status=strtolower((string)($s['status']??''));if((int)($s['progress']??0)>=100||in_array($status,['complete','completed','done','ready'],true)){$done=true;break;}}
    if(!$done)throw new RuntimeException('not_complete');
    $p=v2_data_tv_get('/tours/search/'.$sid,['limit'=>10000]);$hotels=[];$stack=[$p];
    while($stack){$x=array_pop($stack);if(!is_array($x))continue;if(isset($x['id'],$x['name'])&&filter_var($x['id'],FILTER_VALIDATE_INT)!==false)$hotels[(string)(int)$x['id']]=(string)$x['name'];foreach($x as $v)if(is_array($v))$stack[]=$v;}
    $count=count($hotels);$entry['attempts'][]=['date'=>$date,'hotel_count'=>$count];
    if($count>0){$entry['active_date']=$date;$entry['hotel_count']=$count;break;}
   }catch(Throwable $e){$entry['attempts'][]=['date'=>$date,'hotel_count'=>0,'error'=>substr($e->getMessage(),0,80)];}
   usleep(250000);
  }
  if(!$entry['active_date'])$entry['reason']='no_program_on_probed_dates';$rows[]=$entry;
 }
}
echo 'MATCH_RSA_JSON:'.json_encode(['status'=>'completed','operation_id'=>OP,'country'=>'Turkey','searches_started'=>$searches,'rows'=>$rows,'supplier_calls'=>$searches,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
