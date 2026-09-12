#!/usr/bin/env python3
"""One-shot SearchTour concrete freight-money schema capture without booking/B2B calls."""
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport
import anex_searchtour_identifier_schema as ids

EXPERIMENT='anex_searchtour_freight_money_20260913_v1'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
      'child_ages':[],'meal_family':'ai','currency':'RUB','hotel_external_id':'25084'}

HELPER=r'''
function anex_searchtour_freight_money_walk($value,string $path='freights',int $depth=0):array{
    if($depth>6)return [];$out=[];
    if(is_array($value)){
        $count=0;foreach($value as $key=>$child){if(++$count>120)break;$name=is_int($key)?('['.$key.']'):(string)$key;
            if(preg_match('/(?:claim|token|oauth|url|href|link|hotel|name|alias|^id$)/i',$name))continue;
            $childPath=$path.'.'.$name;
            if(is_array($child)){$out=array_merge($out,anex_searchtour_freight_money_walk($child,$childPath,$depth+1));continue;}
            if(!preg_match('/(?:surcharge|price|cost|fee|fuel|rate|currency|converted|amount|total|supplement)/i',$childPath))continue;
            $type=is_null($child)?'null':(is_bool($child)?'bool':(is_int($child)?'int':(is_float($child)?'float':(is_string($child)?'string':gettype($child)))));
            $entry=['path'=>$childPath,'type'=>$type];
            if(is_int($child)||is_float($child))$entry['value']=$child;
            elseif(is_string($child)&&$child!==''&&strlen($child)<=80&&preg_match('/\A[\p{L}\p{N}_.:+\- ]{1,80}\z/uD',$child))$entry['value']=$child;
            $out[]=$entry;
        }
    }
    return $out;
}
'''

def php_source():
    source=ids.php_source()
    source=source.replace(ids.EXPERIMENT,EXPERIMENT)
    source=source.replace('function anex_searchtour_id_main():array{',HELPER+'\nfunction anex_searchtour_id_main():array{',1)
    source=source.replace("'group_row_schema'=>[],'concrete_row_schemas'=>[],'production_price_arithmetic_applied'=>false",
                          "'group_row_schema'=>[],'concrete_row_schemas'=>[],'freight_money_schemas'=>[],'production_price_arithmetic_applied'=>false",1)
    needle="$out['concrete_row_schemas'][]=['price'=>is_scalar($row['price']??null)?(string)$row['price']:null,'tourKey'=>is_scalar($row['tourKey']??null)?(string)$row['tourKey']:null,'currencyKey'=>is_scalar($row['currencyKey']??null)?(string)$row['currencyKey']:null,'fields'=>anex_searchtour_id_row_schema($row)];"
    replacement=needle+"$out['freight_money_schemas'][]=['price'=>is_scalar($row['price']??null)?(string)$row['price']:null,'tourKey'=>is_scalar($row['tourKey']??null)?(string)$row['tourKey']:null,'programTypeKey'=>is_scalar($row['programTypeKey']??null)?(string)$row['programTypeKey']:null,'spoKey'=>is_scalar($row['spoKey']??null)?(string)$row['spoKey']:null,'money'=>anex_searchtour_freight_money_walk($row['freights']??[])];"
    if needle not in source: raise RuntimeError('source_shape')
    source=source.replace(needle,replacement,1)
    source=source.replace("'supplier_effect'=>'read_only_search_expand_identifier_schema_completed'","'supplier_effect'=>'read_only_search_expand_freight_money_schema_completed'",1)
    source=source.replace('read_only_search_expand_identifier_schema_completed','read_only_search_expand_freight_money_schema_completed')
    return source

def validate(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT: raise ValueError('result')
    if value.get('supplier_replay_allowed') is not False or value.get('automatic_retry') is not False: raise ValueError('replay')
    for key in ('tourvisor_requests','additional_prices_requests','andromeda_requests','freight_monitor_requests','booking_calls','broninit_calls','mapping_writes'):
        if value.get(key)!=0: raise ValueError('effect')
    if value.get('status')=='completed':
        if value.get('supplier_effect')!='read_only_search_expand_freight_money_schema_completed': raise ValueError('effect_name')
        if not (1<=int(value.get('anex_requests',0))<=6): raise ValueError('budget')
        rows=value.get('freight_money_schemas')
        if not isinstance(rows,list) or not rows or len(rows)>30: raise ValueError('rows')
        encoded=json.dumps(value,ensure_ascii=False).lower()
        for forbidden in ('catclaim','oauth_token','anex_api_token','https://parser.anextour.ru'):
            if forbidden in encoded: raise ValueError('sensitive')
    return value

def summarize(value):
    rows=[]
    for row in value.get('freight_money_schemas') or []:
        rows.append({'price':row.get('price'),'tourKey':row.get('tourKey'),'programTypeKey':row.get('programTypeKey'),'spoKey':row.get('spoKey'),'money':row.get('money') or []})
    return {'schema_version':1,'experiment_id':EXPERIMENT,'status':value.get('status'),'supplier_replay_allowed':False,
            'production_price_arithmetic_applied':False,'anex_requests':value.get('anex_requests'),'concrete_count':len(rows),
            'freight_money':rows,'note':'Nested SearchTour freights money/surcharge discovery only; no B2B/TV/Andromeda/FreightMonitor/booking.'}

def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix('.tmp');tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)

def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_searchtour_freight_money.py OUTPUT_DIR')
    out=Path(sys.argv[1])
    try:
        value=validate(transport.ssh_php_no_mux(php_source(),SPEC,maximum_bytes=4000000));save(out/'result.json',value);report=summarize(value);save(out/'report.json',report);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if value.get('status')=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        failure={'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False};save(out/'failure.json',failure);print(json.dumps(failure,sort_keys=True));raise SystemExit(1)
if __name__=='__main__':main()
