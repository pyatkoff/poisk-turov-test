#!/usr/bin/env python3
from __future__ import annotations
import json, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts' / 'diagnostics'))
import anex_search3_three_source_price as transport

EXPERIMENT='int_anex_egypt_charter_apd_20260917_v3'
DATES=['2026-10-12','2026-10-13','2026-10-14']
SPEC={'experiment_id':EXPERIMENT,'departure_local_id':1,'country_local_id':1,'dates':DATES,'nights':7,'adults':2,'children':0,'currency':'RUB','target_distinct_programs':5}

PHP = r'''
function ds_norm($v): string {
  $v=is_string($v)?trim($v):'';
  $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
  return trim((string)preg_replace('/[^\\p{L}\\p{N}]+/u',' ',str_replace(['ё','Ё'],'е',$v)));
}
function ds_dict_id(array $rows,array $names): int {
  $wanted=array_map('ds_norm',$names);$found=[];
  foreach($rows as $row){
    if(!is_array($row)||!preg_match('/\\A[1-9][0-9]{0,7}\\z/D',(string)($row['id']??'')))continue;
    foreach(['name','nameAlt','alias','currencyISO'] as $f){
      if(is_string($row[$f]??null)&&in_array(ds_norm($row[$f]),$wanted,true))$found[(int)$row['id']]=true;
    }
  }
  if(count($found)!==1)throw new RuntimeException('DISTINCT_SCAN_DICTIONARY');
  return (int)array_key_first($found);
}
function ds_req($client,string $action,array $params,int &$count){
  if($count>0)usleep(1050000);
  ++$count;
  return $client->request($action,$params);
}
function ds_prices($payload): array {
  if(!is_array($payload))throw new RuntimeException('DISTINCT_SCAN_PRICES');
  $d=array_key_exists('SearchTour_PRICES',$payload)?$payload['SearchTour_PRICES']:$payload;
  if(!is_array($d)||!is_array($d['prices']??null))throw new RuntimeException('DISTINCT_SCAN_PRICES');
  return $d;
}
function ds_gdsish(array $row): bool {
  $text=ds_norm(implode(' ',[
    (string)($row['programType']??''),(string)($row['programTypeAlt']??''),
    (string)($row['tour']??''),(string)($row['tourAlt']??''),
    (string)($row['spo']??''),(string)($row['flightData']??'')
  ]));
  return strpos($text,'gds')!==false||strpos($text,'regular')!==false||strpos($text,'регуляр')!==false
    || strtoupper((string)($row['freightExternal']??''))==='Y';
}
function ds_safe_row(array $row): array {
  $keys=['id','hotelKey','hotel','star','town','checkIn','checkOut','nights','adult','child','meal','mealKey','room','roomKey','htPlace','htPlaceKey',
    'price','currency','currencyKey','convertedPrice','convertedPriceNumber','hotelAvailability','freights','bron','tour','tourKey','programType','programTypeKey',
    'spo','spoKey','transportCodeKey','freightExternal','flightData','partnerIncoming','grouped'];
  $out=[];
  foreach($keys as $k)if(array_key_exists($k,$row))$out[$k]=$row[$k];
  return $out;
}
function ds_flights($payload): array {
  $types=[];$names=[];$carriers=[];$routes=[];$detailed=false;$regular=false;
  foreach(is_array($payload['routes']??null)?array_slice($payload['routes'],0,6):[] as $route){
    if(!is_array($route))continue;
    $r=['date'=>$route['info']['date']??null,'from'=>$route['info']['sourceTown']??null,'to'=>$route['info']['targetTown']??null,'options'=>[]];
    foreach(is_array($route['freights']??null)?array_slice($route['freights'],0,40):[] as $f){
      if(!is_array($f))continue;
      $type=$f['transportType']??null;$name=$f['name']??null;$carrier=$f['transportCompany']??null;
      if(is_string($type)&&trim($type)!==''){$types[$type]=true;$n=ds_norm($type);if(strpos($n,'regular')!==false||strpos($n,'регуляр')!==false)$regular=true;}
      if(is_string($name)&&trim($name)!=='')$names[$name]=true;
      if(is_string($carrier)&&trim($carrier)!=='')$carriers[$carrier]=true;
      $dep=is_array($f['departure']??null)?$f['departure']:[];
      $arr=is_array($f['arrival']??null)?$f['arrival']:[];
      $ok=is_string($name)&&trim($name)!==''&&is_string($carrier)&&trim($carrier)!==''
        &&is_string($dep['portAlias']??null)&&trim($dep['portAlias'])!==''
        &&is_string($dep['time']??null)&&trim($dep['time'])!==''
        &&is_string($arr['portAlias']??null)&&trim($arr['portAlias'])!==''
        &&is_string($arr['time']??null)&&trim($arr['time'])!=='';
      $detailed=$detailed||$ok;
      $r['options'][]=['name'=>$name,'carrier'=>$carrier,'transportType'=>$type,'departure'=>$dep,'arrival'=>$arr,'places'=>$f['places']??[]];
    }
    $routes[]=$r;
  }
  return ['transport_types'=>array_keys($types),'flight_names'=>array_keys($names),'carriers'=>array_keys($carriers),
    'itinerary_detailed'=>$detailed,'regular_type_seen'=>$regular,'routes'=>$routes];
}
function ds_main(): array {
  $started=microtime(true);$reqs=0;$apdReqs=0;
  $out=['schema_version'=>1,'experiment_id'=>'int_anex_egypt_charter_apd_20260917_v3','status'=>'blocked',
    'automatic_retry'=>false,'supplier_replay_allowed'=>false,'database_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0];
  try{
    $raw=file_get_contents('php://stdin',false,null,0,8193);
    $input=is_string($raw)?json_decode($raw,true,32,JSON_THROW_ON_ERROR):null;
    $expected=['experiment_id'=>'int_anex_egypt_charter_apd_20260917_v3','departure_local_id'=>1,'country_local_id'=>1,
      'dates'=>['2026-10-12','2026-10-13','2026-10-14'],'nights'=>7,'adults'=>2,'children'=>0,'currency'=>'RUB','target_distinct_programs'=>5];
    if(!is_array($input)||$input!==$expected)throw new RuntimeException('DISTINCT_SCAN_INPUT');
    $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');
    if(!$root||realpath((string)getcwd())!==$root)throw new RuntimeException('DISTINCT_SCAN_RUNTIME');
    $ledger=$home.'/.anytour-ops/int_anex_egypt_charter_apd_20260917_v3';
    if(file_exists($ledger))throw new RuntimeException('DISTINCT_SCAN_NO_REPLAY');
    if(!is_dir($home.'/.anytoour-ops')&&!mkdir($home.'/.anytoour-ops',0700,true))throw new RuntimeException('DISTINCT_SCAN_LEDGER');
    if(!mkdir($ledger,0700))throw new RuntimeException('DISTINCT_SCAN_LEDGER');
    file_put_contents($ledger.'/state',"reserved\n",LOCK_EX);

    require_once $home.'/.anytoour-anex/search3-preview.php';
    require_once $root.'/config.php';
    $base=$root.'/_preview/search3-anex-candidate/app/integrations';
    if(!is_dir($base))$base=$root.'/app/integrations';
    foreach(['anex-client.php','anex-normalizer.php','anex-search-mapping-registry.php','anex-additional-prices-client.php'] as $f)require_once $base.'/'.$f;
    if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('DISTINCT_SCAN_TOKEN');
    $b2b=defined('ANEX_B2B_TOKEN')&&is_string(ANEX_B2B_TOKEN)?trim(ANEX_B2B_TOKEN):'';
    if($b2b==='')throw new RuntimeException('DISTINCT_SCAN_B2B_TOKEN');

    $db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
    require_once $db;
    $pdo=v2_data_db();
    $q=$pdo->prepare('SELECT d.name departure_name,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');
    $q->execute([1,1]);$local=$q->fetch(PDO::FETCH_ASSOC);
    if(!$local)throw new RuntimeException('DISTINCT_SCAN_LOCAL');
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$resolver=$registry->previewResolver();
    $client=new AnyTourAnexClient(trim(ANEX_API_TOKEN));

    $departure=ds_dict_id(ds_req($client,'SearchTour_TOWNFROMS',[],$reqs),[$local['departure_name'],'Москва','Moscow']);
    $country=ds_dict_id(ds_req($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure],$reqs),[$local['country_name'],'Египет','Egypt']);

    $programs=[];$dateStats=[];$currency=null;
    foreach(['2026-10-12','2026-10-13','2026-10-14'] as $date){
      $compact=str_replace('-','',$date);
      $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>$compact,'CHECKIN_END'=>$compact,'ADULT'=>2,'CHILD'=>0];
      if($currency===null)$currency=ds_dict_id(ds_req($client,'SearchTour_CURRENCIES',$dated,$reqs),['RUB','RUR','Рубль','Рубли','Руб']);
      $params=$dated+['CURRENCY'=>$currency,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
      $initial=ds_prices(ds_req($client,'SearchTour_PRICES',$params,$reqs));
      $mapped=0;$nonGds=0;$uniqueThis=[];
      foreach($initial['prices'] as $row){
        if(!is_array($row)||anytour_anex_normalizer_flag($row['grouped']??null)!==true)continue;
        $hotel=anytour_anex_normalizer_id($row['hotelKey']??null);$tour=anytour_anex_normalizer_id($row['tourKey']??null);
        if($hotel===null||$tour===null)continue;
        $lid=$resolver('anex_online',$hotel);if(!is_int($lid)||$lid<1)continue;$mapped++;
        if(ds_gdsish($row))continue;$nonGds++;$uniqueThis[$tour]=true;
        if(isset($programs[$tour]))continue;
        $programs[$tour]=['date'=>$date,'params'=>$params,'group'=>$row,'hotel'=>$hotel,'local'=>$lid];
      }
      $dateStats[]=['date'=>$date,'initial_rows'=>count($initial['prices']),'mapped_groups'=>$mapped,'mapped_non_gds_groups'=>$nonGds,'distinct_non_gds_tourkeys'=>count($uniqueThis)];
      if(count($programs)>=8)break;
    }

    $results=[];$success=0;
    foreach($programs as $tourKey=>$cand){
      if(count($results)>=8||$success>=5)break;
      $ep=$cand['params'];unset($ep['PARTITION_PRICE']);$ep['CATCLAIM']=(string)($cand['group']['id']??'');$ep['HOTELS']=$cand['hotel'];
      $expanded=ds_prices(ds_req($client,'SearchTour_PRICES',$ep,$reqs));
      $selected=null;$concreteCount=0;
      foreach($expanded['prices'] as $row){
        if(!is_array($row)||anytour_anex_normalizer_flag($row['grouped']??null)!==false||(string)($row['hotelKey']??'')!==$cand['hotel'])continue;
        if((string)($row['tourKey']??'')!==(string)$tourKey||ds_gdsish($row))continue;
        $concreteCount++;if($selected===null)$selected=$row;
      }
      if($selected===null)continue;
      $cat=(string)($selected['id']??'');if($cat==='')continue;
      $flightPayload=ds_req($client,'FreightMonitor_FREIGHTSBYPACKET',['CATCLAIM'=>$cat],$reqs);
      $flights=ds_flights($flightPayload);
      $charterish=strtoupper((string)($selected['freightExternal']??''))==='N' && $flights['itinerary_detailed']===true && $flights['regular_type_seen']===false;

      $apd=null;$cur=anytour_anex_normalizer_id($selected['currencyKey']??null);
      if($cur!==null){
        $apdClient=new AnyTourAnexAdditionalPricesClient($b2b,null,$ledger.'/apd-'.$tourKey);
        $payload=$apdClient->additionalPricesDaily(['page'=>1,'pageSize'=>10,'tour'=>(int)$tourKey,'dateBeg'=>$cand['date'],'nights'=>7,'currency'=>(int)$cur]);
        $apdReqs+=$apdClient->requestsMade();
        $apd=['criteria'=>['tour'=>(int)$tourKey,'dateBeg'=>$cand['date'],'nights'=>7,'currency'=>(int)$cur],
          'total_count'=>$payload['totalCount']??null,'rows'=>$payload['data']??null,'diagnostics'=>$apdClient->lastRequestDiagnostics()];
      }
      $entry=['tourKey'=>(string)$tourKey,'date'=>$cand['date'],'local_hotel_id'=>$cand['local'],'hotel_external_id'=>$cand['hotel'],
        'group'=>ds_safe_row($cand['group']),'concrete'=>ds_safe_row($selected),'concrete_count'=>$concreteCount,
        'flights'=>$flights,'charterish'=>$charterish,'apd'=>$apd];
      $results[]=$entry;
      if($charterish)$success++;
    }

    $out=['schema_version'=>1,'experiment_id'=>'int_anex_egypt_charter_apd_20260917_v3','status'=>'completed','observed_at'=>gmdate('c'),
      'scope'=>['departure'=>$local['departure_name'],'country'=>$local['country_name'],'dates'=>['2026-10-12','2026-10-13','2026-10-14'],'nights'=>7,'adults'=>2,'children'=>0,'currency'=>'RUB'],
      'date_stats'=>$dateStats,'candidate_distinct_tourkeys'=>array_keys($programs),'checked_distinct_programs'=>count($results),
      'charterish_programs'=>array_values(array_map(static fn($x)=>(string)$x['tourKey'],array_filter($results,static fn($x)=>$x['charterish']===true))),
      'results'=>$results,'anex_requests'=>$reqs,'apd_requests'=>$apdReqs,
      'automatic_retry'=>false,'supplier_replay_allowed'=>false,'database_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0];
    file_put_contents($ledger.'/state',"completed\n",LOCK_EX);
  }catch(Throwable $e){
    $m=$e->getMessage();
    $out['reason']=is_string($m)&&preg_match('/\\A(?:DISTINCT_SCAN|ANEX_B2B)_[A-Z0-9_]{1,80}\\z/D',$m)?$m:'DISTINCT_SCAN_UNCONFIRMED';
  }
  $out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);
  return $out;
}
$report=ds_main();
echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit(($report['status']??null)==='completed'?0:1);
'''

def main():
    if len(sys.argv)!=2:
        raise SystemExit('usage: int_anex_egypt_charter_apd_v3.py OUTPUT')
    out=Path(sys.argv[1])
    try:
        value=transport.ssh_php_no_mux("declare(strict_types=1);\n"+PHP,SPEC,maximum_bytes=4000000)
    except Exception as exc:
        value={'schema_version':1,'experiment_id':EXPERIMENT,'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False}
    out.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps({
      'status':value.get('status'),
      'candidate_distinct_tourkeys':value.get('candidate_distinct_tourkeys'),
      'checked_distinct_programs':value.get('checked_distinct_programs'),
      'charterish_programs':value.get('charterish_programs'),
      'anex_requests':value.get('anex_requests'),
      'apd_requests':value.get('apd_requests')
    },ensure_ascii=False,sort_keys=True))
    return 0 if value.get('status')=='completed' else 1

if __name__=='__main__':
    raise SystemExit(main())
