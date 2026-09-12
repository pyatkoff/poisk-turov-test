#!/usr/bin/env python3
"""One-shot SearchTour concrete-row identifier schema capture for B2B tour binding discovery."""
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport
import anex_concrete_fuel_binding as concrete

EXPERIMENT='anex_searchtour_identifier_schema_20260913_v1'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
      'child_ages':[],'meal_family':'ai','currency':'RUB','hotel_external_id':'25084'}

PHP=r'''
const ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT='anex_searchtour_identifier_schema_20260913_v1';
function anex_searchtour_id_candidate(string $key,$value){
    if(preg_match('/(?:claim|token|oauth|url|href|link|hotel|name|alias)/i',$key))return null;
    if(!preg_match('/(?:tour|program|packet|tariff|freight|key|currency)/i',$key))return null;
    if(is_int($value))return $value;
    if(is_float($value)&&is_finite($value))return $value;
    if(!is_string($value)||$value===''||strlen($value)>80)return null;
    return preg_match('/\\A[\\p{L}\\p{N}_.:+\\- ]{1,80}\\z/uD',$value)?$value:null;
}
function anex_searchtour_id_row_schema(array $row):array{
    $out=[];
    foreach($row as $key=>$value){
        if(!is_string($key)||preg_match('/(?:claim|token|oauth|url|href|link|hotel|name|alias|^id$)/i',$key))continue;
        if(is_array($value)){$out[]=['field'=>$key,'type'=>'array','count'=>count($value)];continue;}
        $type=is_null($value)?'null':(is_bool($value)?'bool':(is_int($value)?'int':(is_float($value)?'float':(is_string($value)?'string':gettype($value)))));
        $entry=['field'=>$key,'type'=>$type];$candidate=anex_searchtour_id_candidate($key,$value);if($candidate!==null)$entry['candidate_value']=$candidate;$out[]=$entry;
    }
    usort($out,static fn($a,$b)=>strcmp((string)$a['field'],(string)$b['field']));return $out;
}
function anex_searchtour_id_main():array{
    $started=microtime(true);$pdo=null;$lock=null;$path=null;$reserved=false;$client=null;$secrets=[];
    $out=['schema_version'=>1,'experiment_id'=>ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT,'status'=>'blocked','automatic_retry'=>false,'supplier_replay_allowed'=>false,
        'anex_requests'=>0,'tourvisor_requests'=>0,'additional_prices_requests'=>0,'andromeda_requests'=>0,'freight_monitor_requests'=>0,'booking_calls'=>0,'broninit_calls'=>0,'mapping_writes'=>0,
        'group_row_schema'=>[],'concrete_row_schemas'=>[],'production_price_arithmetic_applied'=>false];
    try{
        $input=json_decode((string)file_get_contents('php://stdin'),true,8,JSON_THROW_ON_ERROR);
        $expected=['experiment_id'=>ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT,'country'=>'Turkey','date'=>'2026-10-12','nights'=>7,'adults'=>2,'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB','hotel_external_id'=>'25084'];
        if(!is_array($input)||$input!==$expected)throw new RuntimeException('SEARCHTOUR_ID_INVALID_INPUT');
        $home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');$preview=realpath($root.'/_preview/search3-anex-candidate');
        if(!$root||!$preview||$preview!==$root.'/_preview/search3-anex-candidate'||!in_array(realpath((string)getcwd()),[$root,$preview],true))throw new RuntimeException('SEARCHTOUR_ID_RUNTIME');
        require_once $home.'/.anytoour-anex/search3-preview.php';require_once $root.'/config.php';
        if(!defined('ANEX_API_TOKEN')||!is_string(ANEX_API_TOKEN)||trim(ANEX_API_TOKEN)==='')throw new RuntimeException('SEARCHTOUR_ID_TOKEN');
        $secrets=[ANEX_API_TOKEN];$db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();
        if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('SEARCHTOUR_ID_DB');
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);if($registry->resolve('anex_online','25084','preview')!==21753)throw new RuntimeException('SEARCHTOUR_ID_IDENTITY');
        $lookup=$pdo->query("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        if(count($lookup)!==1)throw new RuntimeException('SEARCHTOUR_ID_LOCAL');$local=$lookup[0];
        $dir=$home.'/.anytoour-anex';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('SEARCHTOUR_ID_CHECKPOINT_DIR');
        $path=$dir.'/'.ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT.'.json';$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('SEARCHTOUR_ID_LOCK');
        if(is_file($path)){ $prior=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR); if(($prior['status']??null)==='completed'&&is_array($prior['result']??null))return array_replace($prior['result'],['reused'=>true]); throw new RuntimeException('SEARCHTOUR_ID_NOT_REPLAYABLE'); }
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT,'status'=>'reserved','reserved_at'=>gmdate('c')]);$reserved=true;
        $client=new AnyTourAnexClient(ANEX_API_TOKEN);
        $departure=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_TOWNFROMS',[]),[$local['departure_name'],'Москва','Moscow']);
        usleep(1050000);$country=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_STATES',['TOWNFROMINC'=>$departure]),[$local['country_name'],'Турция','Turkey']);
        $dated=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20261012','CHECKIN_END'=>'20261012','ADULT'=>2,'CHILD'=>0];
        usleep(1050000);$currency=anex_concrete_fuel_dictionary_id(anex_concrete_fuel_dictionary($client,'SearchTour_CURRENCIES',$dated),['RUB','RUR','Рубль','Рубли','Руб']);
        $params=['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CURRENCY'=>$currency,'CHECKIN_BEG'=>'20261012','CHECKIN_END'=>'20261012','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'HOTELS'=>'25084','FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
        usleep(1050000);$payload=$client->request('SearchTour_PRICES',$params);$data=$payload['SearchTour_PRICES']??$payload;$rows=is_array($data['prices']??null)?$data['prices']:[];
        $group=null;foreach(array_slice($rows,0,100) as $row){if(is_array($row)&&(string)($row['hotelKey']??'')==='25084'){$group=$row;break;}}
        if(!is_array($group))throw new RuntimeException('SEARCHTOUR_ID_GROUP');$claim=$group['id']??null;if(!is_string($claim)||$claim==='')throw new RuntimeException('SEARCHTOUR_ID_CLAIM');$out['group_row_schema']=anex_searchtour_id_row_schema($group);
        $expandedParams=$params;unset($expandedParams['PARTITION_PRICE']);$expandedParams['CATCLAIM']=$claim;
        usleep(1050000);$expanded=$client->request('SearchTour_PRICES',$expandedParams);$expandedData=$expanded['SearchTour_PRICES']??$expanded;$expandedRows=is_array($expandedData['prices']??null)?$expandedData['prices']:[];
        foreach(array_slice($expandedRows,0,30) as $row){if(!is_array($row)||(string)($row['hotelKey']??'')!=='25084')continue;$out['concrete_row_schemas'][]=['price'=>is_scalar($row['price']??null)?(string)$row['price']:null,'tourKey'=>is_scalar($row['tourKey']??null)?(string)$row['tourKey']:null,'currencyKey'=>is_scalar($row['currencyKey']??null)?(string)$row['currencyKey']:null,'fields'=>anex_searchtour_id_row_schema($row)];}
        if(!$out['concrete_row_schemas'])throw new RuntimeException('SEARCHTOUR_ID_CONCRETE');$out['anex_requests']=$client->requestsMade();$out['status']='completed';$out['supplier_effect']='read_only_search_expand_identifier_schema_completed';$out['reused']=false;
        anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT,'status'=>'completed','completed_at'=>gmdate('c'),'result'=>$out]);$reserved=false;
    }catch(Throwable $e){$code=$e->getMessage();$safe=preg_match('/\\ASEARCHTOUR_ID_[A-Z0-9_]{1,90}\\z/D',$code)?$code:'SEARCHTOUR_ID_UNCONFIRMED';$out['status']=$reserved?'unknown':'blocked';$out['reason']=$safe;$out['supplier_effect']=$reserved?'unknown':'none';if($reserved&&is_string($path)){try{anex_concrete_fuel_save($path,['schema_version'=>1,'experiment_id'=>ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT,'status'=>'unknown','reason'=>$safe]);}catch(Throwable $ignored){}}}
    finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}if($client instanceof AnyTourAnexClient)$out['anex_requests']=$client->requestsMade();$out['elapsed_ms']=(int)round((microtime(true)-$started)*1000);}
    $json=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);foreach($secrets as $secret)if($secret!==''&&is_string($json)&&strpos($json,$secret)!==false)return['schema_version'=>1,'experiment_id'=>ANEX_SEARCHTOUR_ID_SCHEMA_EXPERIMENT,'status'=>'unknown','reason'=>'SEARCHTOUR_ID_OUTPUT_REDACTED','automatic_retry'=>false,'supplier_replay_allowed'=>false];return $out;
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
    return "declare(strict_types=1);\ndefine('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY',true);\ndefine('ANYTOUR_ANEX_CONCRETE_FUEL_LIBRARY_ONLY',true);\n"+paired+'\n'+client+'\n'+normalizer+'\n'+search+'\n'+registry+'\n'+binding+'\n'+PHP+'\n$report=anex_searchtour_id_main();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n";exit(($report["status"]??null)==="completed"?0:1);'

def validate(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT: raise ValueError('result')
    if value.get('supplier_replay_allowed') is not False or value.get('automatic_retry') is not False: raise ValueError('replay')
    for key in ('tourvisor_requests','additional_prices_requests','andromeda_requests','freight_monitor_requests','booking_calls','broninit_calls','mapping_writes'):
        if value.get(key)!=0: raise ValueError('effect')
    if value.get('status')=='completed':
        if value.get('supplier_effect')!='read_only_search_expand_identifier_schema_completed': raise ValueError('effect_name')
        if not (1<=int(value.get('anex_requests',0))<=6): raise ValueError('budget')
        rows=value.get('concrete_row_schemas')
        if not isinstance(rows,list) or not rows or len(rows)>30: raise ValueError('rows')
        encoded=json.dumps(value,ensure_ascii=False).lower()
        for forbidden in ('catclaim','oauth_token','anex_api_token','https://parser.anextour.ru'):
            if forbidden in encoded: raise ValueError('sensitive')
    return value

def summarize(value):
    candidates=[]
    for row in value.get('concrete_row_schemas') or []:
        for field in row.get('fields') or []:
            if 'candidate_value' in field:candidates.append({'price':row.get('price'),'tourKey':row.get('tourKey'),'field':field.get('field'),'type':field.get('type'),'candidate_value':field.get('candidate_value')})
    return {'schema_version':1,'experiment_id':EXPERIMENT,'status':value.get('status'),'supplier_replay_allowed':False,'production_price_arithmetic_applied':False,
            'anex_requests':value.get('anex_requests'),'concrete_count':len(value.get('concrete_row_schemas') or []),'candidate_identifier_fields':candidates,
            'note':'SearchTour raw-row identifier discovery only; no B2B/TV/Andromeda/FreightMonitor/booking and no raw CATCLAIM.'}

def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix('.tmp');tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)

def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_searchtour_identifier_schema.py OUTPUT_DIR')
    out=Path(sys.argv[1])
    try:
        value=validate(transport.ssh_php_no_mux(php_source(),SPEC,maximum_bytes=4000000));save(out/'result.json',value);report=summarize(value);save(out/'report.json',report);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if value.get('status')=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        failure={'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False};save(out/'failure.json',failure);print(json.dumps(failure,sort_keys=True));raise SystemExit(1)
if __name__=='__main__':main()
