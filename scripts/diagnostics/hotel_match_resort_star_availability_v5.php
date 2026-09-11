<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','0');
const OP='hotel-match-resort-star-adaptive-1971-20260911-v5';
require_once getcwd().'/config.php';
$dbHelper=is_file(getcwd().'/data/db-v1.php')?getcwd().'/data/db-v1.php':getcwd().'/v2/data/db-v1.php';
require_once $dbHelper;
$tvClient=is_file(getcwd().'/data/tourvisor-client-v1.php')?getcwd().'/data/tourvisor-client-v1.php':getcwd().'/v2/data/tourvisor-client-v1.php';
require_once $tvClient;
if(!defined('TOURVISOR_ANEX_JWT')) throw new RuntimeException('credential_missing');
$token=trim((string)constant('TOURVISOR_ANEX_JWT'));
if(stripos($token,'Bearer ')===0)$token=trim(substr($token,7));
if($token==='')throw new RuntimeException('credential_empty');
putenv('TOURVISOR_JWT='.$token);
$pdo=v2_data_db();
$targets=[
 ['label'=>'Анталья','aliases'=>['Анталья','Antalya']],
 ['label'=>'Аланья','aliases'=>['Аланья','Alanya']],
 ['label'=>'Кемер','aliases'=>['Кемер','Kemer']],
 ['label'=>'Белек','aliases'=>['Белек','Belek']],
 ['label'=>'Сиде','aliases'=>['Сиде','Side']],
 ['label'=>'Бодрум','aliases'=>['Бодрум','Bodrum']],
 ['label'=>'Мармарис','aliases'=>['Мармарис','Marmaris']],
];
$q=$pdo->prepare('SELECT id,name FROM catalog_regions WHERE country_id=4 AND is_active=1 ORDER BY name');
$q->execute();
$all=$q->fetchAll(PDO::FETCH_ASSOC);
$fold=static fn(string $v): string => mb_strtolower(trim($v),'UTF-8');
$regions=[];
foreach($targets as $t){
  $exact=[];$partial=[];
  foreach($all as $r){
    $rn=$fold((string)$r['name']);
    foreach($t['aliases'] as $a){
      $an=$fold((string)$a);
      if($rn===$an){$exact[(string)$r['id']]=$r;continue 2;}
      if($an!==''&&mb_stripos($rn,$an,0,'UTF-8')!==false)$partial[(string)$r['id']]=$r;
    }
  }
  $hits=$exact!==[]?$exact:$partial;
  $values=array_values($hits);
  $regions[]=(count($values)===1)
    ? ['wanted'=>$t['label'],'id'=>(int)$values[0]['id'],'name'=>(string)$values[0]['name'],'match_kind'=>$exact!==[]?'exact':'unique_partial']
    : ['wanted'=>$t['label'],'id'=>null,'name'=>null,'catalog_hits'=>count($values),'candidates'=>array_slice(array_map(static fn($r)=>['id'=>(int)$r['id'],'name'=>(string)$r['name']],$values),0,8)];
}
$dates=['2026-09-25','2026-10-02','2026-10-16','2026-11-06','2026-11-20'];
$rows=[];$searches=0;
foreach($regions as $r){
  foreach([3,4,5] as $star){
    $entry=['resort'=>$r,'star'=>$star,'attempts'=>[],'active_date'=>null,'hotel_count'=>0];
    if(!$r['id']){$entry['reason']='region_not_unique';$rows[]=$entry;continue;}
    foreach($dates as $date){
      $searches++;
      try{
        $start=v2_data_tv_get('/tours/search',[
          'departureId'=>1,'countryId'=>4,'dateFrom'=>$date,'dateTo'=>$date,
          'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'currency'=>'RUB',
          'onlyCharter'=>false,'onlyDirect'=>false,'operatorIds'=>[13],
          'regionIds'=>[$r['id']],'hotelCategory'=>$star,
        ]);
        $sid=(int)($start['searchId']??$start['id']??0);
        if($sid<=0)throw new RuntimeException('search_id_missing');
        $done=false;
        for($i=0;$i<24;$i++){
          if($i)usleep(700000);
          $s=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);
          $status=strtolower((string)($s['status']??''));
          if((int)($s['progress']??0)>=100||in_array($status,['complete','completed','done','ready'],true)){$done=true;break;}
        }
        if(!$done)throw new RuntimeException('not_complete');
        $p=v2_data_tv_get('/tours/search/'.$sid,['limit'=>10000]);
        $hotels=[];$stack=[$p];
        while($stack){
          $x=array_pop($stack);if(!is_array($x))continue;
          if(isset($x['id'],$x['name'])&&filter_var($x['id'],FILTER_VALIDATE_INT)!==false)$hotels[(string)(int)$x['id']]=(string)$x['name'];
          foreach($x as $v)if(is_array($v))$stack[]=$v;
        }
        $count=count($hotels);
        $entry['attempts'][]=['date'=>$date,'hotel_count'=>$count];
        if($count>0){$entry['active_date']=$date;$entry['hotel_count']=$count;break;}
      }catch(Throwable $e){
        $entry['attempts'][]=['date'=>$date,'hotel_count'=>0,'error'=>substr($e->getMessage(),0,80)];
      }
      usleep(250000);
    }
    if(!$entry['active_date'])$entry['reason']='no_program_on_probed_dates';
    $rows[]=$entry;
  }
}
echo 'MATCH_RS_JSON:'.json_encode([
  'status'=>'completed','operation_id'=>OP,'country'=>'Turkey','catalog_regions_total'=>count($all),'searches_started'=>$searches,
  'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
