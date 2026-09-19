<?php
declare(strict_types=1);

/**
 * CURRENT read-only preflight for exactly the 31 COMMON4 edges whose saved tour detail
 * returned 404 in terminal operation hotel-match-common4-saved-tour-detail180-1971-20260919-v1.
 * No provider access, no DB writes, no mapping acceptance.
 */
const C4S31_OP='hotel-match-common4-stale31-search-1971-20260919-v1';
const C4S31_INPUT_SHA='29c124239b56ff154f98a5277edd5b3799012bf688831c5de2bc88099b80455b';
const C4S31_ROUTES=[
  13=>['operator'=>'anex','ns'=>'operator_5'],
  18=>['operator'=>'biblio_globus','ns'=>'operator_115'],
  25=>['operator'=>'funsun','ns'=>'operator_315'],
  43=>['operator'=>'intourist','ns'=>'operator_342'],
];

function c4s31_req(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function c4s31_canonical(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function c4s31_read(string $p):array{
  c4s31_req(!is_link($p)&&realpath($p)===$p&&is_file($p),'input_path');
  $raw=file_get_contents($p);c4s31_req(is_string($raw)&&strlen($raw)<=1048576,'input_read');
  $x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);c4s31_req(is_array($x),'input_shape');return $x;
}
function c4s31_q(PDO $db,string $sql,array $args=[]):array{
  $q=$db->prepare($sql);$q->execute(array_values($args));return $q->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function c4s31_main():void{
  c4s31_req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===C4S31_OP,'operation_guard');
  $sha=(string)getenv('MATCH_SOURCE_SHA');c4s31_req(preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'source_sha');
  $dir=(string)getenv('MATCH_OPERATION_DIR');c4s31_req($dir!==''&&is_dir($dir),'operation_dir');
  $inputPath=$dir.'/payload/stale31.json';$raw=file_get_contents($inputPath);
  c4s31_req(is_string($raw)&&hash('sha256',$raw)===C4S31_INPUT_SHA,'input_sha');
  $input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);c4s31_req(is_array($input)&&count($input)===31,'input_count');
  $seen=[];foreach($input as$e){
    c4s31_req(is_array($e),'edge_shape');$hid=(int)($e['tv_hotel_id']??0);$op=(int)($e['operator_id']??0);
    c4s31_req($hid>0&&isset(C4S31_ROUTES[$op]),'edge_identity');
    $k=$op.':'.$hid;c4s31_req(!isset($seen[$k]),'duplicate_edge');$seen[$k]=true;
    c4s31_req((string)($e['operator']??'')===C4S31_ROUTES[$op]['operator'],'operator_name');
    c4s31_req((string)($e['target_supplier_namespace']??'')===C4S31_ROUTES[$op]['ns'],'operator_namespace');
    c4s31_req(preg_match('/^[1-9][0-9]{5,21}$/D',(string)($e['stale_tour_id']??''))===1,'stale_tour_id');
  }

  $root=realpath(getcwd());c4s31_req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
  require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
  $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
  $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
  try{
    foreach(['tour_price_observations','andromeda_hotel_identities','catalog_hotels']as$t){
      $r=c4s31_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);
      c4s31_req(count($r)===1&&strtoupper((string)$r[0]['ENGINE'])==='INNODB','table_contract_'.$t);
    }
    $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
    $edges=[];$skipped=[];
    foreach($input as$e){
      $hid=(int)$e['tv_hotel_id'];$op=(int)$e['operator_id'];$route=C4S31_ROUTES[$op];
      $hotel=c4s31_q($db,'SELECT id,name,is_active FROM catalog_hotels WHERE id=?',[$hid]);
      if(count($hotel)!==1||(int)$hotel[0]['is_active']!==1){$skipped[]=['tv_hotel_id'=>$hid,'operator_id'=>$op,'reason'=>'inactive_or_missing'];continue;}
      $accepted=c4s31_q($db,"SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace=? AND local_hotel_id=? AND decision_status='accepted' LIMIT 2",[$route['ns'],$hid]);
      if($accepted){$skipped[]=['tv_hotel_id'=>$hid,'operator_id'=>$op,'reason'=>'accepted_same_provider','accepted_external_hotel_ids'=>array_values(array_unique(array_map(fn($r)=>(string)$r['external_hotel_id'],$accepted)))];continue;}
      $obs=c4s31_q($db,"SELECT country_id,region_id,subregion_id,departure_id,departure_date,nights,adults,children_count,child_ages_signature,search_id,tour_id,observed_at FROM tour_price_observations WHERE source='user_search' AND hotel_id=? AND operator_id=? AND departure_date>=? ORDER BY observed_at DESC LIMIT 1",[$hid,$op,$today]);
      if(count($obs)!==1){$skipped[]=['tv_hotel_id'=>$hid,'operator_id'=>$op,'reason'=>'no_current_future_context'];continue;}
      $r=$obs[0];$ages=(string)$r['child_ages_signature'];$childs=$ages===''?[]:array_values(array_map('intval',explode(',',$ages)));
      c4s31_req((int)$r['children_count']===count($childs),'child_context_shape');
      foreach($childs as$a)c4s31_req($a>=0&&$a<=17,'child_age');
      $currentTour=$r['tour_id']===null?'':(string)$r['tour_id'];
      $edges[]=[
        'tv_hotel_id'=>$hid,'hotel_name'=>(string)$hotel[0]['name'],'operator_id'=>$op,'operator'=>$route['operator'],'target_supplier_namespace'=>$route['ns'],
        'stale_tour_id'=>(string)$e['stale_tour_id'],
        'detail_first_tour_id'=>($currentTour!==''&&$currentTour!==(string)$e['stale_tour_id'])?$currentTour:null,
        'context'=>['departure_id'=>(int)$r['departure_id'],'country_id'=>(int)$r['country_id'],'region_id'=>$r['region_id']===null?null:(int)$r['region_id'],'subregion_id'=>$r['subregion_id']===null?null:(int)$r['subregion_id'],'departure_date'=>(string)$r['departure_date'],'nights'=>(int)$r['nights'],'adults'=>(int)$r['adults'],'childs'=>$childs,'search_id'=>(int)$r['search_id'],'observed_at'=>(string)$r['observed_at']]
      ];
    }
    $db->commit();
    usort($edges,fn($a,$b)=>($a['operator_id']<=>$b['operator_id'])?:strcmp($a['context']['departure_date'],$b['context']['departure_date'])?:($a['tv_hotel_id']<=>$b['tv_hotel_id']));
    $groups=[];foreach($edges as$e){
      $c=$e['context'];$k=implode('|',[$e['operator_id'],$c['departure_id'],$c['country_id'],$c['departure_date'],$c['nights'],$c['adults'],implode(',',$c['childs'])]);
      if(!isset($groups[$k]))$groups[$k]=['operator_id'=>$e['operator_id'],'operator'=>$e['operator'],'target_supplier_namespace'=>$e['target_supplier_namespace'],'departure_id'=>$c['departure_id'],'country_id'=>$c['country_id'],'departure_date'=>$c['departure_date'],'nights'=>$c['nights'],'adults'=>$c['adults'],'childs'=>$c['childs'],'hotel_ids'=>[]];
      $groups[$k]['hotel_ids'][]=$e['tv_hotel_id'];
    }
    foreach($groups as&$g){$g['hotel_ids']=array_values(array_unique($g['hotel_ids']));sort($g['hotel_ids'],SORT_NUMERIC);c4s31_req(count($g['hotel_ids'])<=30,'batch_over_30');}unset($g);
    $out=['operation'=>C4S31_OP,'state'=>'current_read_only_complete','source_sha'=>$sha,'read_at_utc'=>gmdate('c'),'today_moscow'=>$today,'input_count'=>31,'eligible_edges'=>$edges,'skipped'=>$skipped,'context_groups'=>array_values($groups),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0];
    echo c4s31_canonical($out);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(($argv[1]??'')==='--self-test'){
  c4s31_req(count(C4S31_ROUTES)===4,'routes');
  c4s31_req(C4S31_ROUTES[18]['ns']==='operator_115'&&C4S31_ROUTES[25]['ns']==='operator_315','bindings');
  c4s31_req(preg_match('/^[0-9a-f]{64}$/',C4S31_INPUT_SHA)===1,'input_hash');
  echo "hotel-match-common4-stale31-current-v1: PASS\n";exit(0);
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)c4s31_main();
