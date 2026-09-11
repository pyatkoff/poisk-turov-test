#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import sys

path=Path(__file__).resolve().parents[1]/'scripts/diagnostics/anex_three_source_broad_price.py'
sys.path.insert(0,str(path.parent));spec=importlib.util.spec_from_file_location('broad_price',path);mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
checks=0
def check(value):
    global checks;checks+=1
    if not value:raise AssertionError(f'broad_price_py_{checks}')

combined=mod.source();check(combined.startswith('declare(strict_types=1);\n'));check("define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);" in combined)
check(mod.EXPERIMENT=='anex_three_source_broad_price_20260911_v2');check(mod.SPEC['date']=='2026-10-05')

def row(provider,local,room,price,fuel=None):
    return {'provider':provider,'local_hotel_id':local,'external_hotel_id':str(local),'hotel_name':'Hotel','date':mod.SPEC['date'],'nights':7,'adults':2,'children':0,
        'meal_family':'ai','meal_label':'AI','room':room,'room_norm':room.lower(),'placement':'DBL','placement_norm':'dbl','price':price,'currency':'RUB','fuel_charge':fuel,
        'fuel_inclusion_verified':False,'final_price_verified':False}
def case(provider,rows):
    return {'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':provider,'status':'completed','offers':rows,'details':{},'supplier_effect':'read_only_search_completed',
        'automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes_allowed':True,'reused':False}

results={
 'anex':case('anex',[row('anex',10,'standard','100000'),row('anex',20,'family','120000')]),
 'andromeda':case('andromeda',[row('andromeda',10,'standard','101000'),row('andromeda',30,'suite','140000')]),
 'tourvisor':case('tourvisor',[row('tourvisor',10,'standard','102000','2500'),row('tourvisor',20,'family','121000')]),
}
for name,value in results.items():check(mod.validate_case(value,name) is value)
report=mod.comparison(results);check(report['triple_tuple_count']==1);check(report['pair_tuple_counts']['anex_tourvisor']==2);check(report['pair_tuple_counts']['anex_andromeda']==1);check(report['pair_tuple_counts']['andromeda_tourvisor']==1)
check(report['provider_counts']['tourvisor']['fuel_reported_offers']==1);check(report['triple_examples'][0]['identical_supplier_package_verified'] is False)
check(len(report['pair_only_examples']['anex_tourvisor'])==1 and report['pair_only_examples']['anex_tourvisor'][0]['key']['local_hotel_id']==20)
changed=case('tourvisor',[row('tourvisor',10,'different','102000','2500')]);check(mod.comparison({'anex':results['anex'],'andromeda':results['andromeda'],'tourvisor':changed})['triple_tuple_count']==0)
unknown={'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':'anex','status':'unknown','supplier_effect':'unknown','automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes_allowed':True}
check(mod.validate_case(unknown,'anex') is unknown)
bad=case('anex',[row('anex',10,'standard','100000')]);bad['mapping_writes']=1
try:mod.validate_case(bad,'anex');check(False)
except ValueError:check(True)
print(f'Broad three-source Python guards: {checks} checks passed; network=0')
