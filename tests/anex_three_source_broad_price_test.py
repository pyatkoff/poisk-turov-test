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
check(mod.EXPERIMENT=='anex_three_source_green_gold_20260912_v8');check(mod.SPEC['date']=='2026-09-28');check(mod.SPEC['nights']==7 and mod.SPEC['adults']==2)
check(mod.TARGET_LOCAL_HOTEL_ID==21753 and mod.TARGET_ANEX_HOTEL_ID=='25084')

def row(provider,room,price,fuel=None,program=None,currency_id=None,local=None,external=None):
    if local is None: local=mod.TARGET_LOCAL_HOTEL_ID
    if external is None: external=mod.TARGET_ANEX_HOTEL_ID if provider=='anex' else str(local)
    value={'provider':provider,'local_hotel_id':local,'external_hotel_id':str(external),'hotel_name':'GREEN GOLD','date':mod.SPEC['date'],'nights':mod.SPEC['nights'],'adults':2,'children':0,
        'meal_family':'ai','meal_key':'ai','meal_qualifiers':[],'meal_equivalence_verified':False,'meal_label':'AI','room':room,'room_norm':room.lower(),'placement':'DBL','placement_norm':'dbl',
        'price':price,'currency':'RUB','fuel_charge':fuel,'fuel_inclusion_verified':False,'final_price_verified':False}
    if provider=='anex': value.update(supplier_tour_program_id=program,supplier_currency_id=currency_id)
    return value
def coverage(provider):
    if provider=='anex': return {'state':'bounded','reason':'pricepage_1_target_hotel_only','page':1,'all_pages_retained':False}
    if provider=='andromeda': return {'state':'complete','reason':'all_advertised_target_hotel_pages_retained','page':1,'pages_count':1,'all_pages_retained':True}
    return {'state':'bounded','reason':'status_without_continue_no_growth','status_complete':True,'all_pages_retained':False}
def case(provider,rows):
    return {'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':provider,'status':'completed','offers':rows,'details':{'coverage':coverage(provider)},'supplier_effect':'read_only_search_completed',
        'automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes_allowed':True,'reused':False}

results={'anex':case('anex',[row('anex','standard','100000',program='900',currency_id='3')]),
 'andromeda':case('andromeda',[row('andromeda','standard','100000')]),
 'tourvisor':case('tourvisor',[row('tourvisor','standard','102500','2500')])}
for name,value in results.items():check(mod.validate_case(value,name) is value)
report=mod.comparison(results);check(report['target_local_hotel_id']==21753 and report['target_anex_hotel_id']=='25084')
check(report['triple_tuple_count']==1);check(report['pair_tuple_counts']['anex_tourvisor']==1);check(report['pair_tuple_counts']['anex_andromeda']==1);check(report['pair_tuple_counts']['andromeda_tourvisor']==1)
check(report['provider_counts']['tourvisor']['fuel_reported_offers']==1);check(report['provider_counts']['anex']['program_id_observed_offers']==1)
check(report['triple_examples'][0]['identical_supplier_package_verified'] is False);check(report['triple_examples'][0]['key']['meal_key']=='ai')
check(report['provider_counts']['anex']['coverage_state']=='bounded' and report['provider_counts']['andromeda']['coverage_state']=='complete' and report['provider_counts']['tourvisor']['coverage_state']=='bounded')
cohorts=report['anex_program_fuel_cohorts'];check(len(cohorts)==1);check(cohorts[0]['supplier_tour_program_id']=='900' and cohorts[0]['supplier_currency_id']=='3' and cohorts[0]['tourvisor_fuel_charges']==['2500'])
check(cohorts[0]['fuel_equivalence_verified'] is False and cohorts[0]['arithmetic_applied'] is False)
changed=case('tourvisor',[row('tourvisor','different','102500','2500')]);check(mod.comparison({'anex':results['anex'],'andromeda':results['andromeda'],'tourvisor':changed})['triple_tuple_count']==0)
qualified=row('tourvisor','standard','102500','2500');qualified['meal_key']='ai:without_alcohol';qualified['meal_qualifiers']=['without_alcohol']
try:mod.validate_case(case('tourvisor',[qualified]),'tourvisor');check(False)
except ValueError:check(True)
bad_target=case('tourvisor',[row('tourvisor','standard','102500','2500',local=1239,external='1239')])
try:mod.validate_case(bad_target,'tourvisor');check(False)
except ValueError:check(True)
bad_external=case('anex',[row('anex','standard','100000',program='900',currency_id='3',external='8652')])
try:mod.validate_case(bad_external,'anex');check(False)
except ValueError:check(True)
bad_program=case('anex',[row('anex','standard','100000',program='bad',currency_id='3')])
try:mod.validate_case(bad_program,'anex');check(False)
except ValueError:check(True)
bad_coverage=case('tourvisor',[]);bad_coverage['details']['coverage']={'state':'complete','reason':'status_only','status_complete':True,'all_pages_retained':True}
try:mod.validate_case(bad_coverage,'tourvisor');check(False)
except ValueError:check(True)
unknown={'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':'anex','status':'unknown','supplier_effect':'unknown','automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes_allowed':True}
check(mod.validate_case(unknown,'anex') is unknown)
bad=case('anex',[row('anex','standard','100000',program='900',currency_id='3')]);bad['mapping_writes']=1
try:mod.validate_case(bad,'anex');check(False)
except ValueError:check(True)
print(f'Green Gold v8 three-source Python guards: {checks} checks passed; network=0')
