#!/usr/bin/env python3
import json, re, sys
from pathlib import Path

import anex_search3_three_source_price as transport
import anex_concrete_fuel_binding as concrete

EXPERIMENT='anex_green_gold_1797_flight_bind_20260913_v1'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,'child_ages':[],'meal_family':'ai','currency':'RUB','hotel_external_id':'25084','program':'1797'}

PHP=r'''
const ANEX_1797_EXPERIMENT='anex_green_gold_1797_flight_bind_20260913_v1';
function anex_1797_main():array{
    $started=microtime(true);$pdo=null;$lock=null;$path=null;$reserved=false;$client=null;$secrets=[];
    $out=['schema_version'=>1,'experiment_id'=>ANEX_1797_EXPERIMENT,'status'=>'blocked','automatic_retry'=>false,'supplier_replay_allowed'=>false,
        'anex_requests'=>0,'tourvisor_requests'=>0,'additional_prices_requests'=>0,'andromeda_requests'=>0,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,
        'selected_concrete'=>null,'anex_flights'=>null,'production_price_arithmetic_applied'=>false];
    try{
        $input=json_decode((string)file_get_contents('php://stdin'),true,8,JSON_THROW_ON_ERROR);
        $expected=['experiment_id'=>ANEX_1797_EXPERIMENT,'country'=>'Turkey','date'=>'2026-10-12','nights'=>7,'adults'=>2,'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB','hotel_external_id'=>'25084','program'=>'1797'];
        if(!is_array($input)||$input!==$expected)throw new RuntimeException('ANEX_1797_INVALID_INPUT');
        $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
        if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('ANEX_1797_RUNTIME');
        require_once $home.'/.anytoour-anex/search3-preview.php';require_once $root.'/config.php';
        if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('ANEX_1797_TOKEN');
        $secrets=[ANEX_API_TOKEN];$db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();
        if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('ANEX_1797_DB');
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        if($registry->resolve('anex_online','25084','preview')!==21753)throw new RuntimeException('ANEX_1797_IDENTITY');
        $lookup=$pdo->query("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        if(count($lookup)!==1)throw new RuntimeException('ANEX_1797_LOCAL');$local=$lookup[0];
        $dir=$home.'/.anytoour-anex';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('ANEX_1797_CHECKPOINT_DIR');
        $path=$dir.'/'.ANEX_1797_EXPERIMENT.'.json';$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('ANEX_1797_LOCK');
        if(is_file($path)){ $prior=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR); if(($prior['status']??null)==='completed'&&is_array($prior['result']??null))return array_replace($prior['result'],['reused'=>true]); throw new RuntimeException('ANEX_1797_NOT_REPLAYABLE'); }
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_1797_EXPERIMENT,'status'=>'reserved','reserved_at'=>gmdate('c')]);$reserved=true;
        $client=new AnyTourAnexClient(ANEX_API_TOKEN);
        $departure=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_TOWNFROMS',[]),[$local['departure_name'],'Москва','Moscow']);
        usleep(1050000);$country=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure]),[$local['country_name'],'Турция','Turkey']);
        $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20261012','CHECKIN_END'=>'20261012','ADULT'=>2,'CHILD'=>0];
        usleep(1050000);$currency=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_CURRENCIES',$dated),['RUB','RUR','Рубль','Рубли','Руб']);
        $criteria=['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,'currency_id'=>$currency,'checkin_begin'=>'2026-10-12','checkin_end'=>'2026-10-12','nights_from'=>7,'nights_till'=>7,'adults'=>2,'children'=>0,'child_ages'=>[],'hotel_ids'=>['25084']];
        $search=new AnyTourAnexSearch($client,$registry->previewResolver(),$secrets);usleep(1050000);$page=$search->search($criteria);
        $eligible=[];foreach($page['offers']??[] as $offer){$s=anex_concrete_fuel_offer_summary($offer);if($s!==null)$eligible[]=['raw'=>$offer,'summary'=>$s];}
        if(!$eligible)throw new RuntimeException('ANEX_1797_SEARCH_EMPTY');usort($eligible,static fn($a,$b)=>(float)$a['summary']['price']<=>(float)$b['summary']['price']);
        $group=$eligible[0];$concrete=[];
        if(($group['raw']['kind']??null)==='group_minimum'){usleep(1050000);$expanded=$search->expand($group['raw']['offer_key']);foreach($expanded['offers']??[] as $offer){$s=anex_concrete_fuel_offer_summary($offer);if($s!==null&&($s['kind']??null)==='concrete'&&($s['supplier_tour_program_id']??null)==='1797')$concrete[]=['raw'=>$offer,'summary'=>$s];}}
        else{foreach($eligible as $row)if(($row['summary']['kind']??null)==='concrete'&&($row['summary']['supplier_tour_program_id']??null)==='1797')$concrete[]=$row;}
        if(!$concrete)throw new RuntimeException('ANEX_1797_CONCRETE');usort($concrete,static fn($a,$b)=>(float)$a['summary']['price']<=>(float)$b['summary']['price']);$selected=$concrete[0];
        $out['selected_concrete']=$selected['summary'];usleep(1050000);$flights=$search->flights($selected['raw']['offer_key']);$out['anex_flights']=['routes'=>anex_concrete_fuel_routes($flights),'selected'=>false,'final_price_verified'=>false];
        $out['anex_requests']=$client->requestsMade();$out['status']='completed';$out['supplier_effect']='read_only_search_expand_program1797_flights_completed';$out['reused']=false;
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_1797_EXPERIMENT,'status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out]);$reserved=false;
    }catch(Throwable $e){$code=$e->getMessage();$safe=preg_match('/\\AANEX_1797_[A-Z0-9_]{1,90}\\z/D',$code)?$code:'ANEX_1797_UNCONFIRMED';$out['status']=$reserved?'unknown':'blocked';$out['reason']=$safe;$out['supplier_effect']=$reserved?'unknown':'none';if($reserved&&is_string($path)){try{anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_1797_EXPERIMENT,'status'=>'unknown','reason'=>$safe]);}catch(Throwable $ignored){}}}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if($client instanceof AnyTourAnexClient)$out['anex_requests']=$client->requestsMade();$out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);}
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false)return['schema_version'=>1,'experiment_id'=>ANEX_1797_EXPERIMENT,'status'=>'unknown','reason'=>'ANEX_1797_OUTPUT_REDACTED','automatic_retry'=>false,'supplier_replay_allowed'=>false];return $out;
}
'''

def php_source():
    root=Path(__file__).resolve().parents[2];diag=root/'scripts'/'diagnostics'
    paired=concrete.php_body(diag/'anex_search3_paired_runner.php',False)
    client=concrete.php_body(root/'app'/'integrations'/'anex-client.php')
    normalizer=concrete.php_body(root/'app'/'integrations'/'anex-normalizer.php')
    search=concrete.php_body(root/'app'/'integrations'/'anex-search.php');search='\n'.join(x for x in search.splitlines() if not x.startswith("require_once __DIR__"))+'\n'
    registry=concrete.php_body(root/'app'/'integrations'/'anex-search-mapping-registry.php')
    binding=concrete.php_body(diag/'anex_concrete_fuel_binding.php')
    return "declare(strict_types=1);\ndefine('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY',true);\ndefine('ANYTOUR_ANEX_CONCRETE_FUEL_LIBRARY_ONLY',true);\n"+paired+'\n'+client+'\n'+normalizer+'\n'+search+'\n'+registry+'\n'+binding+'\n'+PHP+'\n$report=anex_1797_main();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n";exit(($report["status"]??null)==="completed"?0:1);'

def canon_flight(value):
    if not isinstance(value,str): return None
    v=re.sub(r'[^A-Za-z0-9]','',value).upper()
    return v if re.fullmatch(r'[A-Z0-9]{2,3}\d{2,5}',v) else None

def summarize(value):
    selected=value.get('selected_concrete') or {}; routes=(value.get('anex_flights') or {}).get('routes') or []
    options=[]
    for route in routes:
        for opt in route.get('options') or []:
            options.append({'date':route.get('date'),'flight':canon_flight(opt.get('name')),'name':opt.get('name'),'carrier':opt.get('carrier'),'departure_airport':opt.get('departure_airport'),'departure_time':opt.get('departure_time'),'arrival_airport':opt.get('arrival_airport'),'arrival_time':opt.get('arrival_time')})
    outbound=any(x.get('flight')=='PC1457' and x.get('departure_airport') in ('VKO','Внуково') and x.get('arrival_airport') in ('BJV','Бодрум') for x in options)
    returned=any(x.get('flight')=='PC1456' and x.get('departure_airport') in ('BJV','Бодрум') and x.get('arrival_airport') in ('VKO','Внуково') for x in options)
    return {'schema_version':1,'experiment_id':EXPERIMENT,'status':value.get('status'),'supplier_replay_allowed':False,'selected_concrete':selected,'flight_options':options,'target_tourvisor':{'tour_id':'13262754554359','base':'133310','price':'162494','fuel':'29184','outbound':'PC1457','return':'PC1456'},'outbound_pc1457_match':outbound,'return_pc1456_match':returned,'exact_default_flight_pair_match':outbound and returned,'production_price_arithmetic_applied':False,'anex_requests':value.get('anex_requests'),'tourvisor_requests':0,'additional_prices_requests':0,'andromeda_requests':0,'booking_calls':0,'mapping_writes':0}

def validate(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT: raise ValueError('result')
    if value.get('supplier_replay_allowed') is not False or value.get('automatic_retry') is not False: raise ValueError('replay')
    for key in ('tourvisor_requests','additional_prices_requests','andromeda_requests','booking_calls','broninit_calls','mapping_writes'):
        if value.get(key)!=0: raise ValueError('effect')
    if value.get('status')=='completed':
        if value.get('supplier_effect')!='read_only_search_expand_program1797_flights_completed': raise ValueError('effect_name')
        if not (1<=int(value.get('anex_requests',0))<=7): raise ValueError('budget')
        selected=value.get('selected_concrete') or {}
        if selected.get('kind')!='concrete' or selected.get('supplier_tour_program_id')!='1797' or selected.get('currency')!='RUB': raise ValueError('selected')
        if not isinstance((value.get('anex_flights') or {}).get('routes'),list): raise ValueError('flights')
    return value

def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix('.tmp');tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)

def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_green_gold_1797_flight_bind.py OUTPUT_DIR')
    out=Path(sys.argv[1])
    try:
        value=validate(transport.ssh_php_no_mux(php_source(),SPEC,maximum_bytes=2000000));save(out/'result.json',value);report=summarize(value);save(out/'report.json',report);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if value.get('status')=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        failure={'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False};save(out/'failure.json',failure);print(json.dumps(failure,sort_keys=True));raise SystemExit(1)
if __name__=='__main__':main()
