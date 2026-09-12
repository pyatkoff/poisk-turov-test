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
combined=mod.source();check(combined.startswith('declare(strict_types=1);\n'));check(mod.EXPERIMENT=='anex_green_gold_program_20260912_v9')
check(mod.CASES==('anex',));check(mod.SPEC['date']=='2026-10-05' and mod.SPEC['nights']==7);check(mod.TARGET_LOCAL_HOTEL_ID==21753 and mod.TARGET_ANEX_HOTEL_ID=='25084')
row={'provider':'anex','local_hotel_id':21753,'external_hotel_id':'25084','hotel_name':'GREEN GOLD','date':'2026-10-05','nights':7,'adults':2,'children':0,
    'meal_family':'ai','meal_key':'ai','meal_qualifiers':[],'meal_equivalence_verified':False,'meal_label':'AI','room':'Standard','room_norm':'standard','placement':'DBL','placement_norm':'dbl',
    'price':'119952','currency':'RUB','fuel_charge':None,'fuel_inclusion_verified':False,'final_price_verified':False,'supplier_tour_program_id':'900','supplier_currency_id':'3'}
value={'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':'anex','status':'completed','offers':[row],
    'details':{'coverage':{'state':'bounded','reason':'pricepage_1_target_hotel_only','page':1,'all_pages_retained':False},'program_pairs':[{'tour':'900','currency':'3'}],
    'saved_cross_source_context':{'date':'2026-10-05','andromeda_search_price':'119952','tourvisor_display_price':'149548','tourvisor_fuel_charge':'29596','requeried':False}},
    'supplier_effect':'read_only_search_completed','automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes_allowed':False,'reused':False}
check(mod.validate_case(value,'anex') is value)
bad=dict(value);bad['observation_writes_allowed']=True
try:mod.validate_case(bad,'anex');check(False)
except ValueError:check(True)
bad=dict(value);bad['offers']=[dict(row,supplier_tour_program_id='bad')]
try:mod.validate_case(bad,'anex');check(False)
except ValueError:check(True)
bad=dict(value);bad['details']=dict(value['details'],saved_cross_source_context={'date':'2026-10-05'})
try:mod.validate_case(bad,'anex');check(False)
except ValueError:check(True)
unknown={'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':'anex','status':'unknown','supplier_effect':'unknown','automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes_allowed':False}
check(mod.validate_case(unknown,'anex') is unknown)
print(f'Green Gold v9 direct-ANEX Python guards: {checks} checks passed; network=0')
