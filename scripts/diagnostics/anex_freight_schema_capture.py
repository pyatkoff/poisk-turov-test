#!/usr/bin/env python3
"""One-shot FreightMonitor schema capture for a current Green Gold concrete offer."""
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport
import anex_concrete_fuel_binding as concrete

EXPERIMENT='anex_freight_schema_20260913_v1'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
      'child_ages':[],'meal_family':'ai','currency':'RUB','hotel_external_id':'25084','program':'2637'}

PHP = r'''
const ANEX_FREIGHT_SCHEMA_EXPERIMENT='anex_freight_schema_20260913_v1';
function anex_freight_schema_safe_scalar($key,$value,$claim,$token){
    if(!preg_match('/(?:freight|key|id|price|cost|currency|tax|fee|rate|amount|tour|packet|class|tariff|supp|surcharge)/i',(string)$key))return null;
    if(preg_match('/(?:claim|token|oauth|url|href|link)/i',(string)$key))return null;
    if(is_int($value)||is_float($value))return is_finite((float)$value)?$value:null;
    if(!is_string($value)||$value===''||strlen($value)>80||strpos($value,$claim)!==false||strpos($value,$token)!==false)return null;
    return preg_match('/\A[\p{L}\p{N}_.:+\- ]{1,80}\z/uD',$value)?$value:null;
}
function anex_freight_schema_walk($value,string $path,string $claim,string $token,array &$out,int $depth=0):void{
    if($depth>12||count($out)>=1200)return;
    if(is_array($value)){
        $list=$value===[]||array_keys($value)===range(0,count($value)-1);
        $out[]=['path'=>$path,'type'=>$list?'list':'object','count'=>count($value)];
        foreach(array_slice($value,0,50,true) as $key=>$child){
            $label=$list?'[]':(string)$key;
            if(!$list&&preg_match('/(?:claim|token|oauth|url|href|link)/i',$label))continue;
            anex_freight_schema_walk($child,$path.'.'.$label,$claim,$token,$out,$depth+1);
        }
        return;
    }
    $key=substr($path,strrpos($path,'.')+1);$candidate=anex_freight_schema_safe_scalar($key,$value,$claim,$token);
    $entry=['path'=>$path,'type'=>is_null($value)?'null':(is_bool($value)?'bool':(is_int($value)?'int':(is_float($value)?'float':(is_string($value)?'string':gettype($value)))))];
    if($candidate!==null)$entry['candidate_value']=$candidate;
    elseif(is_string($value))$entry['length']=strlen($value);
    $out[]=$entry;
}
function anex_freight_schema_main():array{
    $started=microtime(true);$pdo=null;$lock=null;$path=null;$reserved=false;$client=null;$secrets=[];
    $out=['schema_version'=>1,'experiment_id'=>ANEX_FREIGHT_SCHEMA_EXPERIMENT,'status'=>'blocked','automatic_retry'=>false,
        'supplier_replay_allowed'=>false,'anex_requests'=>0,'additional_prices_requests'=>0,'tourvisor_requests'=>0,'andromeda_requests'=>0,
        'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,'selected_concrete'=>null,'freight_schema'=>[]];
    try{
        $input=json_decode((string)file_get_contents('php://stdin'),true,8,JSON_THROW_ON_ERROR);
        $expected=['experiment_id'=>ANEX_FREIGHT_SCHEMA_EXPERIMENT,'country'=>'Turkey','date'=>'2026-10-12','nights'=>7,'adults'=>2,
            'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB','hotel_external_id'=>'25084','program'=>'2637'];
        if(!is_array($input)||$input!==$expected)throw new RuntimeException('FREIGHT_SCHEMA_INVALID_INPUT');
        $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
        if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('FREIGHT_SCHEMA_RUNTIME');
        require_once $home.'/.anytoour-anex/search3-preview.php';require_once $root.'/config.php';
        if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('FREIGHT_SCHEMA_TOKEN');
        $secrets=[ANEX_API_TOKEN];$db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();
        if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('FREIGHT_SCHEMA_DB');
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        if($registry->resolve('anex_online','25084','preview')!==21753)throw new RuntimeException('FREIGHT_SCHEMA_IDENTITY');
        $lookup=$pdo->query("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        if(count($lookup)!==1)throw new RuntimeException('FREIGHT_SCHEMA_LOCAL');$local=$lookup[0];
        $dir=$home.'/.anytoour-anex';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('FREIGHT_SCHEMA_CHECKPOINT_DIR');
        $path=$dir.'/'.ANEX_FREIGHT_SCHEMA_EXPERIMENT.'.json';$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('FREIGHT_SCHEMA_LOCK');
        if(is_file($path)){ $prior=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR); if(($prior['status']??null)==='completed'&&is_array($prior['result']??null))return array_replace($prior['result'],['reused'=>true]); throw new RuntimeException('FREIGHT_SCHEMA_NOT_REPLAYABLE'); }
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_FREIGHT_SCHEMA_EXPERIMENT,'status'=>'reserved','reserved_at'=>gmdate('c')]);$reserved=true;
        $client=new AnyTourAnexClient(ANEX_API_TOKEN);
        $departure=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_TOWNFROMS',[]),[$local['departure_name'],'Москва','Moscow']);
        usleep(1050000);$country=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure]),[$local['country_name'],'Турция','Turkey']);
        $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20261012','CHECKIN_END'=>'20261012','ADULT'=>2,'CHILD'=>0];
        usleep(1050000);$currency=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_CURRENCIES',$dated),['RUB','RUR','Рубль','Рубли','Руб']);
        $criteria=['supplier_namespace'=>'anex_online','departure_id'=>$departure,'destination_id'=>$country,'currency_id'=>$currency,
            'checkin_begin'=>'2026-10-12','checkin_end'=>'2026-10-12','nights_from'=>7,'nights_till'=>7,'adults'=>2,'children'=>0,'child_ages'=>[],'hotel_ids'=>['25084']];
        $search=new AnyTourAnexSearch($client,$registry->previewResolver(),$secrets);usleep(1050000);$page=$search->search($criteria);
        $groups=[];foreach($page['offers']??[] as $offer){$s=anex_concrete_fuel_offer_summary($offer);if($s!==null)$groups[]=['raw'=>$offer,'summary'=>$s];}
        if(!$groups)throw new RuntimeException('FREIGHT_SCHEMA_SEARCH_EMPTY');usort($groups,static fn($a,$b)=>(float)$a['summary']['price']<=>(float)$b['summary']['price']);
        $first=$groups[0];if(($first['raw']['kind']??null)!=='group_minimum')throw new RuntimeException('FREIGHT_SCHEMA_GROUP');
        usleep(1050000);$expanded=$search->expand($first['raw']['offer_key']);$concrete=[];
        foreach($expanded['offers']??[] as $offer){$s=anex_concrete_fuel_offer_summary($offer);if($s!==null&&($s['kind']??null)==='concrete'&&($s['supplier_tour_program_id']??null)==='2637')$concrete[]=['raw'=>$offer,'summary'=>$s];}
        if(!$concrete)throw new RuntimeException('FREIGHT_SCHEMA_CONCRETE');usort($concrete,static fn($a,$b)=>(float)$a['summary']['price']<=>(float)$b['summary']['price']);$selected=$concrete[0];
        $claim=$selected['raw']['supplier_offer_id']??null;if(!is_string($claim)||$claim==='')throw new RuntimeException('FREIGHT_SCHEMA_CLAIM');$out['selected_concrete']=$selected['summary'];
        usleep(1050000);$payload=$client->request('FreightMonitor_FREIGHTSBYPACKET',['CATCLAIM'=>$claim]);$schema=[];anex_freight_schema_walk($payload,'FreightMonitor_FREIGHTSBYPACKET',$claim,ANEX_API_TOKEN,$schema);
        $out['freight_schema']=$schema;$out['anex_requests']=$client->requestsMade();$out['status']='completed';$out['supplier_effect']='read_only_search_expand_freight_schema_completed';$out['reused']=false;
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_FREIGHT_SCHEMA_EXPERIMENT,'status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out]);$reserved=false;
    }catch(Throwable $e){$code=$e->getMessage();$safe=preg_match('/\AFREIGHT_SCHEMA_[A-Z0-9_]{1,90}\z/D',$code)?$code:'FREIGHT_SCHEMA_UNCONFIRMED';$out['status']=$reserved?'unknown':'blocked';$out['reason']=$safe;$out['supplier_effect']=$reserved?'unknown':'none';if($reserved&&is_string($path)){try{anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_FREIGHT_SCHEMA_EXPERIMENT,'status'=>'unknown','reason'=>$safe]);}catch(Throwable $ignored){}}}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if($client instanceof AnyTourAnexClient)$out['anex_requests']=$client->requestsMade();$out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);}
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false)return['schema_version'=>1,'experiment_id'=>ANEX_FREIGHT_SCHEMA_EXPERIMENT,'status'=>'unknown','reason'=>'FREIGHT_SCHEMA_OUTPUT_REDACTED','automatic_retry'=>false,'supplier_replay_allowed'=>false];return $out;
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
    return "declare(strict_types=1);\ndefine('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY',true);\ndefine('ANYTOUR_ANEX_CONCRETE_FUEL_LIBRARY_ONLY',true);\n"+paired+'\n'+client+'\n'+normalizer+'\n'+search+'\n'+registry+'\n'+binding+'\n'+PHP+'\n$report=anex_freight_schema_main();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n";exit(($report["status"]??null)==="completed"?0:1);'


def validate(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT: raise ValueError('freight_schema_result')
    if value.get('supplier_replay_allowed') is not False or value.get('automatic_retry') is not False: raise ValueError('freight_schema_replay')
    for key in ('additional_prices_requests','tourvisor_requests','andromeda_requests','booking_calls','broninit_calls','mapping_writes'):
        if value.get(key)!=0: raise ValueError('freight_schema_effect')
    if value.get('status')=='completed':
        if value.get('supplier_effect')!='read_only_search_expand_freight_schema_completed': raise ValueError('freight_schema_effect_name')
        if not (1<=int(value.get('anex_requests',0))<=7): raise ValueError('freight_schema_budget')
        selected=value.get('selected_concrete') or {}
        if selected.get('supplier_tour_program_id')!='2637' or selected.get('kind')!='concrete': raise ValueError('freight_schema_program')
        schema=value.get('freight_schema')
        if not isinstance(schema,list) or not schema or len(schema)>1200: raise ValueError('freight_schema_empty')
        encoded=json.dumps(schema,ensure_ascii=False)
        for forbidden in ('CATCLAIM','oauth_token','ANEX_API_TOKEN','https://parser.anextour.ru'):
            if forbidden in encoded: raise ValueError('freight_schema_sensitive')
    return value


def summarize(value):
    candidates=[x for x in value.get('freight_schema',[]) if isinstance(x,dict) and 'candidate_value' in x]
    paths=sorted({x.get('path') for x in value.get('freight_schema',[]) if isinstance(x,dict) and isinstance(x.get('path'),str)})
    return {'schema_version':1,'experiment_id':EXPERIMENT,'status':value.get('status'),'supplier_replay_allowed':False,
            'production_price_arithmetic_applied':False,'selected_concrete':value.get('selected_concrete'),
            'anex_requests':value.get('anex_requests'),'path_count':len(paths),'paths':paths,'candidate_fields':candidates,
            'fuel_rate_binding_verified':False,'note':'Schema-only FreightMonitor evidence; no B2B/TV/Andromeda/booking and no raw CATCLAIM.'}


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix('.tmp');tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)


def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_freight_schema_capture.py OUTPUT_DIR')
    out=Path(sys.argv[1])
    try:
        value=validate(transport.ssh_php_no_mux(php_source(),SPEC,maximum_bytes=4000000));save(out/'result.json',value);report=summarize(value);save(out/'report.json',report);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if value.get('status')=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        failure={'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False}
        try:save(out/'failure.json',failure)
        except Exception:pass
        print(json.dumps(failure,sort_keys=True));raise SystemExit(1)
if __name__=='__main__':main()
