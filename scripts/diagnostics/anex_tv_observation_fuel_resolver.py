#!/usr/bin/env python3
"""Read-only proof that saved Tourvisor observations can resolve fuel for exact direct-ANEX base prices."""
from decimal import Decimal, InvalidOperation
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport

EXPERIMENT='anex_tv_observation_fuel_resolver_20260913_v1'
SPEC={'experiment_id':EXPERIMENT,'local_hotel_id':21753,'date':'2026-10-12','nights':7,'adults':2,'children':0,
      'currency':'RUB','direct_anex_bases':['119448','122366','133310','136541']}

PHP=r'''
$input=json_decode((string)file_get_contents('php://stdin'),true,16,JSON_THROW_ON_ERROR);
$expected=['experiment_id'=>'anex_tv_observation_fuel_resolver_20260913_v1','local_hotel_id'=>21753,'date'=>'2026-10-12','nights'=>7,'adults'=>2,'children'=>0,'currency'=>'RUB','direct_anex_bases'=>['119448','122366','133310','136541']];
if(!is_array($input)||$input!==$expected)throw new RuntimeException('TV_FUEL_INVALID_INPUT');
$home=(string)getenv('HOME');$root=realpath($home.'/www/anytoour.ru');if(!$root||realpath((string)getcwd())!==$root)throw new RuntimeException('TV_FUEL_RUNTIME');
$db=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $db;$pdo=v2_data_db();
if(!$pdo instanceof PDO||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('TV_FUEL_DB');
$sql="SELECT observed_at,search_id,hotel_id,tour_id,departure_date,nights,adults,children_count,meal_id,room_id,room_type,operator_id,price,fuel_charge,currency,ROUND(price-fuel_charge,2) base_price FROM tour_price_observations WHERE hotel_id=:hotel AND departure_date=:date AND nights=:nights AND adults=:adults AND children_count=:children AND fuel_charge IS NOT NULL AND fuel_charge>0 AND currency=:currency ORDER BY observed_at DESC LIMIT 500";
$stmt=$pdo->prepare($sql);$stmt->execute(['hotel'=>21753,'date'=>'2026-10-12','nights'=>7,'adults'=>2,'children'=>0,'currency'=>'RUB']);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$out=['schema_version'=>1,'experiment_id'=>'anex_tv_observation_fuel_resolver_20260913_v1','status'=>'completed','db_writes'=>0,'supplier_requests'=>0,'rows'=>[]];
foreach($rows as $row){$out['rows'][]=['observed_at'=>(string)$row['observed_at'],'search_id'=>(int)$row['search_id'],'tour_id'=>$row['tour_id']===null?null:(string)$row['tour_id'],'meal_id'=>$row['meal_id']===null?null:(int)$row['meal_id'],'room_id'=>$row['room_id']===null?null:(int)$row['room_id'],'room_type'=>$row['room_type']===null?null:(string)$row['room_type'],'operator_id'=>$row['operator_id']===null?null:(int)$row['operator_id'],'price'=>(string)$row['price'],'fuel_charge'=>(string)$row['fuel_charge'],'base_price'=>(string)$row['base_price'],'currency'=>(string)$row['currency']];}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
'''

def php_source():
    return "declare(strict_types=1);\n"+PHP

def dec(v):
    try:return Decimal(str(v))
    except (InvalidOperation,ValueError,TypeError):raise ValueError('decimal')

def analyze(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT or value.get('status')!='completed':raise ValueError('result')
    if value.get('db_writes')!=0 or value.get('supplier_requests')!=0:raise ValueError('effects')
    rows=value.get('rows');
    if not isinstance(rows,list) or len(rows)>500:raise ValueError('rows')
    bases={Decimal(x) for x in SPEC['direct_anex_bases']};matches={str(x):[] for x in SPEC['direct_anex_bases']}
    for row in rows:
        if not isinstance(row,dict):raise ValueError('row')
        price=dec(row.get('price'));fuel=dec(row.get('fuel_charge'));base=dec(row.get('base_price'))
        if fuel<=0 or abs((price-fuel)-base)>Decimal('0.01'):raise ValueError('arithmetic')
        for direct in bases:
            if abs(base-direct)<=Decimal('0.01'):
                matches[str(direct)].append({'fuel_charge':str(fuel.normalize()),'tv_price':str(price.normalize()),'search_id':row.get('search_id'),'tour_id':row.get('tour_id'),'operator_id':row.get('operator_id'),'room_id':row.get('room_id'),'room_type':row.get('room_type'),'meal_id':row.get('meal_id'),'observed_at':row.get('observed_at')})
    resolved={}
    for base,items in matches.items():
        fuels=sorted({x['fuel_charge'] for x in items},key=Decimal)
        resolved[base]={'matched_rows':len(items),'distinct_fuels':fuels,'resolved':len(fuels)==1,'fuel_charge':fuels[0] if len(fuels)==1 else None,'examples':items[:5]}
    return {'schema_version':1,'experiment_id':EXPERIMENT,'status':'completed','supplier_requests':0,'db_writes':0,'observation_rows':len(rows),'matches':resolved,
            'rule':'same local hotel/date/nights/party/currency and exact arithmetic equality: TV price - TV fuel == direct ANEX base; accept only one distinct fuel',
            'runtime_enabled':False}

def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True);path.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n')

def main():
    if len(sys.argv)!=2:raise SystemExit('usage: anex_tv_observation_fuel_resolver.py OUTPUT_DIR')
    out=Path(sys.argv[1])
    try:
        raw=transport.ssh_php_no_mux(php_source(),SPEC,maximum_bytes=4000000);save(out/'raw.json',raw);report=analyze(raw);save(out/'report.json',report);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0)
    except SystemExit:raise
    except Exception as exc:
        failure={'status':'unconfirmed','error_kind':type(exc).__name__,'supplier_requests':0,'db_writes':0};save(out/'failure.json',failure);print(json.dumps(failure,sort_keys=True));raise SystemExit(1)
if __name__=='__main__':main()
