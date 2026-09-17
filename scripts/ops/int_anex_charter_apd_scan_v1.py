#!/usr/bin/env python3
from __future__ import annotations
import json, sys
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'scripts'/'diagnostics'))
import anex_search3_three_source_price as transport

EXPERIMENT='int_anex_charter_apd_scan_20260917_v1'
SPEC={'experiment_id':EXPERIMENT,'departure_local_id':1,'country_local_id':4,'date':'2026-10-12','nights':7,'adults':2,'children':0,'currency':'RUB'}

PHP=r'''
function cs_norm($v): string {
  $v=is_string($v)?trim($v):'';
  $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
  return trim((string)preg_replace('/[^\\p{L}\\p{N}]+/u',' ',str_replace(['ё','Ё'],'е',$v)));
}
function cs_dict_id(array $rows,array $names): int {
  $wanted=array_map('cs_norm',$names);$found=[];
  foreach($rows as $row){
    if(!is_array($row)||!preg_match('/\\A[1-9][0-9]{0,7}\\z/D',(string)($row['id']??'')))continue;
    foreach(['name','nameAlt','alias','currencyISO'] as $f)
      if(is_string($row[$f]??null)&&in_array(cs_norm($row[$f]),$wanted,true))$found[(int)$row['id']]=true;
  }
  if(count($found)!==1)throw new RuntimeException('CHARTER_SCAN_DICTIONARY');
  return (int)array_key_first($found);
}
function cs_req($client,string $action,array $params,int &$count){
  if($count>0)usleep(1050000);++$count;return $client->request($action,$params);
}
function cs_prices($payload): array {
  if(!is_array($payload))throw new RuntimeException('CHARTER_SCAN_PRICES');
  $d=array_key_exists('SearchTour_PRICES',$payload)?$payload['SearchTour_PRICES']:$payload;
  if(!is_array($d)||!is_array($d['prices']??null))throw new RuntimeException('CHARTER_SCAN_PRICES');
  return $d;
}
function cs_text(array $row): string {
  return cs_norm(implode(' ',[
    (string)($row['programType']??''),(string)($row['programTypeAlt']??''),
    (string)($row['tour']??''),(string)($row['tourAlt']??''),
    (string)($row['spo']??''),(string)($row['flightData']??'')
  ]));
}
function cs_gdsish(array $row): bool {
  $t=cs_text($row);return strpos($t,'gds')!==false||strpos($t,'regular')!==false||strpos($t,'регуляр')!==false;
}
function cs_decimal($v): ?string {
  if(is_int($v)||(is_float($v)&&is_finite($v)))$v=(string)$v;
  return is_string($v)&&preg_match('/\\A(?:0|[1-9][0-9]{0,11})(?:\\.[0-9]{1,4})?\\z/D',$v)?$v:null;
}
function cs_safe_row(array $row): array {
  $keys=['id','hotelKey','hotel','star','town','checkIn','checkOut','nights','adult','child','meal','mealKey','room','roomKey','htPlace','htPlaceKey',
    'price','currency','currencyKey','convertedPrice','convertedPriceNumber','hotelAvailability','freights','bron','tour','tourKey','programType','programTypeKey',
    'spo','spoKey','transportCodeKey','freightExternal','flightData','partnerIncoming','grouped'];
  $out=[];foreach($keys as $k)if(array_key_exists($k,$row))$out[$k]=$row[$k];return $out;
}
function cs_flight_summary($payload): array {
  $types=[];$names=[];$carriers=[];$detailed=false;$routes=[];
  foreach(is_array($payload['routes']??null)?array_slice($payload['routes'],0,6):[] as $route){
    if(!is_array($route))continue;
    $r=['date'=>$route['info']['date']??null,'from'=>$route['info']['sourceTown']??null,'to'=>$route['info']['targetTown']??null,'options'=>[]];
    foreach(is_array($route['freights']??null)?array_slice($route['freights'],0,30):[] as $f){
      if(!is_array($f))continue;
      $type=$f['transportType']??null;$name=$f['name']??null;$carrier=$f['transportCompany']??null;
      if(is_string($type)&&$type!=='')$types[$type]=true;if(is_string($name)&&$name!=='')$names[$name]=true;if(is_string($carrier)&&$carrier!=='')$carriers[$carrier]=true;
      $dep=is_array($f['departure']??null)?$f['departure']:[];$arr=is_array($f['arrival']??null)?$f['arrival']:[];
      $ok=is_string($name)&&trim($name)!==''&&is_string($carrier)&&trim($carrier)!==''&&is_string($dep['portAlias']??null)&&trim($dep['portAlias'])!==''
        &&is_string($dep['time']??null)&&trim($dep['time'])!==''&&is_string($arr['portAlias']??null)&&trim($arr['portAlias'])!==''
        &&is_string($arr['time']??null)&&trim($arr['time'])!=='';
      $detailed=$detailed||$ok;
      $r['options'][]=['name'=>$name,'carrier'=>$carrier,'transportType'=>$type,'departure'=>$dep,'arrival'=>$arr,'places'=>$f['places']??[]];
    }
    $routes[]=$r;
  }
  $regular=false;foreach(array_keys($types) as $x){$n=cs_norm($x);if(strpos($n,'regular')!==false||strpos($n,'регуляр')!==false)$regular=true;}
  return ['transport_types'=>array_keys($types),'flight_names'=>array_keys($names),'carriers'=>array_keys($carriers),'itinerary_detailed'=>$detailed,'regular_type_seen'=>$regular,'routes'=>$routes];
}
function cs_main(): array {
  $started=microtime(true);$reqs=0;$apdReqs=0;$out=['schema_version'=>1,'experiment_id'=>'int_anex_charter_apd_scan_20260917_v1','status'=>'blocked',
    'automatic_retry'=>false,'supplier_replay_allowed'=>false,'database_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0];
  try{
    $raw=file_get_contents('php://stdin',false,null,0,4097);$input=is_string($raw)?json_decode($raw,true,16,JSON_THROW_ON_ERROR):null;
    $expected=['experiment_id'=>'int_anex_charter_apd_scan_20260917_v1','departure_local_id'=>1,'country_local_id'=>4,'date'=>'2026-10-12','nights'=>7,'adults'=>2,'children'=>0,'currency'=>'RUB'];
    if(!is_array($input)||$input!==$expected)throw new RuntimeException('CHARTER_SCAN_INPUT');
    $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');
    if(!$root||realpath((string)getcwd())!==$root)throw new RuntimeException('CHARTER_SCAN_RUNTIME');
    $ledger=$home.'/.anytour-ops/int_anex_charter_apd_scan_20260917_v1';if(file_exists($ledger))throw new RuntimeException('CHARTER_SCAN_NO_REPLAY');
    if(!is_dir($home.'/.anytoour-ops')&&!mkdir($home.'/.anytoour-ops',0700,true))throw new RuntimeException('CHARTER_SCAN_LEDGER');
    if(!mkdir($ledger,0700))throw new RuntimeException('CHARTER_SCAN_LEDGER');file_put_contents($ledger.'/state',"reserved\n",LOCK_EX);

    require_once $home.'/.anytoour-anex/search3-preview.php';require_once $root.'/config.php';
    $base=$root.'/_preview/search3-anex-candidate/app/integrations';if(!is_dir($base))$base=$root.'/app/integrations';
    foreach(['anex-client.php','anex-normalizer.php','anex-search-mapping-registry.php','anex-additional-prices-client.php'] as $f)require_once $base.'/'.$f;
    if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('CHARTER_SCAN_TOKEN');
    $b2b=defined('ANEX_B2B_TOKEN')&&is_string(ANEX_B2B_TOKEN)?trim(ANEX_B2B_TOKEN):'';if($b2b==='')throw new RuntimeException('CHARTER_SCAN_B2B_TOKEN');
    $db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();
    $q=$pdo->prepare('SELECT d.name departure_name,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');
    $q->execute([1,4]);$local=$q->fetch(PDO::FETCH_ASSOC);if(!$local)throw new RuntimeException('CHARTER_SCAN_LOCAL');
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$resolver=$registry->previewResolver();$client=new AnyTourAnexClient(trim(ANEX_API_TOKEN));

    $departure=cs_dict_id(cs_req($client,'SearchTour_TOWNFROMS',[],$reqs),[$local['departure_name'],'Москва','Moscow']);
    $country=cs_dict_id(cs_req($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure],$reqs),[$local['country_name'],'Турция','Turkey']);
    $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20261012','CHECKIN_END'=>'20261012','ADULT'=>2,'CHILD'=>0];
    $currency=cs_dict_id(cs_req($client,'SearchTour_CURRENCIES',$dated,$reqs),['RUB','RUR','Рубль','Рубли','Руб']);
    $params=$dated+['CURRENCY'=>$currency,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
    $initial=cs_prices(cs_req($client,'SearchTour_PRICES',$params,$reqs));
    $candidates=[];$gds=0;$non=0;
    foreach($initial['prices'] as $row){
      if(!is_array($row)||anytour_anex_normalizer_flag($row['grouped']??null)!==true)continue;
      $hotel=anytour_anex_normalizer_id($row['hotelKey']??null);if($hotel===null)continue;$lid=$resolver('anex_online',$hotel);if(!is_int($lid)||$lid<1)continue;
      if(cs_gdsish($row)){$gds++;continue;}$non++;
      $p=cs_decimal($row['convertedPriceNumber']??$row['price']??null);$candidates[]=['row'=>$row,'hotel'=>$hotel,'local'=>$lid,'price'=>$p===null?PHP_FLOAT_MAX:(float)$p];
    }
    usort($candidates,static fn($a,$b)=>$a['price']<=>$b['price']);
    $probes=[];$charters=[];
    foreach(array_slice($candidates,0,8) as $cand){
      $gid=(string)($cand['row']['id']??'');if($gid==='')continue;
      $ep=$params;unset($ep['PARTITION_PRICE']);$ep['CATCLAIM']=$gid;$ep['HOTELS']=$cand['hotel'];
      $expanded=cs_prices(cs_req($client,'SearchTour_PRICES',$ep,$reqs));$concretes=[];
      foreach($expanded['prices'] as $row){
        if(!is_array($row)||anytour_anex_normalizer_flag($row['grouped']??null)!==false||(string)($row['hotelKey']??'')!==$cand['hotel'])continue;
        if(cs_gdsish($row))continue;$concretes[]=$row;
      }
      if(!$concretes)continue;$row=$concretes[0];$cat=(string)($row['id']??'');if($cat==='')continue;
      $fp=cs_req($client,'FreightMonitor_FREIGHTSBYPACKET',['CATCLAIM'=>$cat],$reqs);$fs=cs_flight_summary($fp);
      $isCharter=!$fs['regular_type_seen']&&$fs['itinerary_detailed']===true;
      $probe=['group'=>cs_safe_row($cand['row']),'concrete'=>cs_safe_row($row),'concrete_count'=>count($concretes),'flight'=>$fs,'charter_candidate'=>$isCharter,'apd'=>null];
      if($isCharter){
        $tour=$row['tourKey']??null;$cur=$row['currencyKey']??null;
        if((is_int($tour)||ctype_digit((string)$tour))&&(is_int($cur)||ctype_digit((string)$cur))){
          $apd=new AnyTourAnexAdditionalPricesClient($b2b,null,$ledger.'/apd-'.count($charters));
          $payload=$apd->additionalPricesDaily(['page'=>1,'pageSize'=>10,'tour'=>(int)$tour,'dateBeg'=>'2026-10-12','nights'=>7,'currency'=>(int)$cur]);$apdReqs+=$apd->requestsMade();
          $probe['apd']=['criteria'=>['tour'=>(int)$tour,'currency'=>(int)$cur,'dateBeg'=>'2026-10-12','nights'=>7],
            'total_count'=>$payload['totalCount']??null,'rows'=>$payload['data']??null,'diagnostics'=>$apd->lastRequestDiagnostics()];
        }
        $charters[]=$probe;
      }
      $probes[]=$probe;if(count($charters)>=3)break;
    }
    $out=['schema_version'=>1,'experiment_id'=>'int_anex_charter_apd_scan_20260917_v1','status'=>'completed','observed_at'=>gmdate('c'),
      'scope'=>['departure'=>$local['departure_name'],'country'=>$local['country_name'],'date'=>'2026-10-12','nights'=>7,'adults'=>2,'children'=>0,'currency'=>'RUB'],
      'initial_rows'=>count($initial['prices']),'mapped_gdsish_groups'=>$gds,'mapped_non_gds_groups'=>$non,'non_gds_candidates_total'=>count($candidates),
      'probed_groups'=>count($probes),'charter_candidates_found'=>count($charters),'probes'=>$probes,'charters'=>$charters,'anex_search_flight_requests'=>$reqs,'apd_requests'=>$apdReqs,
      'automatic_retry'=>false,'supplier_replay_allowed'=>false,'database_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0];
    file_put_contents($ledger.'/state',"completed\n",LOCK_EX);
  }catch(Throwable $e){
    $m=$e->getMessage();$out['reason']=is_string($m)&&preg_match('/\\A(?:CHARTER_SCAN|ANEX_B2B)_[A-Z0-9_]{1,80}\\z/D',$m)?$m:'CHARTER_SCAN_UNCONFIRMED';
  }
  $out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);return $out;
}
$report=cs_main();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";exit(($report['status']??null)==='completed'?0:1);
'''

def main():
    if len(sys.argv)!=2: raise SystemExit('usage: int_anex_charter_apd_scan_v1.py OUTPUT')
    out=Path(sys.argv[1])
    try:value=transport.ssh_php_no_mux("declare(strict_types=1);\n"+PHP,SPEC,maximum_bytes=4000000)
    except Exception as exc:value={'schema_version':1,'experiment_id':EXPERIMENT,'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False}
    out.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps({'status':value.get('status'),'initial_rows':value.get('initial_rows'),'mapped_non_gds_groups':value.get('mapped_non_gds_groups'),
      'probed_groups':value.get('probed_groups'),'charter_candidates_found':value.get('charter_candidates_found'),'anex_requests':value.get('anex_search_flight_requests'),'apd_requests':value.get('apd_requests')},ensure_ascii=False,sort_keys=True))
    return 0 if value.get('status')=='completed' else 1
if __name__=='__main__':raise SystemExit(main())
