#!/usr/bin/env python3
import copy
import importlib.util
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
DIAG=ROOT/'scripts'/'diagnostics'
sys.path.insert(0,str(DIAG))
spec=importlib.util.spec_from_file_location('concrete_fuel',DIAG/'anex_concrete_fuel_binding.py')
mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)

assembled=mod.source()
assert assembled.startswith("declare(strict_types=1);\n")
assert assembled.count('<?php')==0
assert 'final class AnyTourAnexSearch' in assembled
assert 'final class AnyTourAnexAdditionalPricesClient' not in assembled
assert 'final class AnyTourAnexSearchMappingRegistry' in assembled
assert 'function anex_concrete_fuel_main' in assembled
assert "require_once __DIR__ . '/anex-client.php'" not in assembled
assert '$report=anex_concrete_fuel_main();' in assembled

value={
    'schema_version':1,'experiment_id':mod.EXPERIMENT,'status':'completed','automatic_retry':False,
    'supplier_replay_allowed':False,'supplier_effect':'read_only_search_expand_flights_tv_completed',
    'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'andromeda_requests':0,
    'anex_requests':6,'tourvisor_requests':5,'additional_prices_requests':0,
    'group_minimum':{'kind':'group_minimum','price':'119448','room_norm':'standard room','placement_norm':'dbl',
        'supplier_tour_program_id':'2637','supplier_currency_id':'3'},
    'selected_concrete':{'kind':'concrete','price':'133310','room_norm':'standard room','placement_norm':'dbl',
        'supplier_tour_program_id':'2637','supplier_currency_id':'3'},
    'concrete_offers':[{'kind':'concrete','price':'133310','room_norm':'standard room','placement_norm':'dbl',
        'supplier_tour_program_id':'2637','supplier_currency_id':'3'}],
    'anex_flights':{'routes':[{'options':[{'departure_airport':'SVO','arrival_airport':'ADB'}]}],'selected':False},
    'tourvisor':{'offers':[
        {'price':'140294','fuel_charge':'20846','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'162494','fuel_charge':'29184','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'165725','fuel_charge':'29184','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'162494','fuel_charge':'29184','room_norm':'standard room','placement_norm':'trpl'},
    ],'search_complete':True},
}
assert mod.validate(value) is value
report=mod.analyze(value);e=report['evidence']
assert report['production_price_arithmetic_applied'] is False
assert report['preserved_additional_prices']['supplier_replay_performed'] is False
assert report['preserved_additional_prices']['source_run']==34716809979
assert e['candidate_two_adult_supplement']=='29184.4'
assert e['concrete_total_candidate']=='162494.4'
assert e['group_total_candidate']=='148632.4'
assert e['unique_concrete_tv_match'] is True
assert e['concrete_tv_matches'][0]['tourvisor_price']=='162494'
assert e['concrete_tv_matches'][0]['fuel_gap']=='0.4'
assert e['group_minimum_supplement_match'] is False
assert e['anex_airport_pairs']==[['SVO','ADB']]
assert e['supplier_package_identity_verified'] is False and e['application_rule_verified_for_search'] is False
assert e['tourvisor_flight_refresh_requested'] is False
assert report['requests']['additional_prices']==0

ambiguous=copy.deepcopy(value)
ambiguous['tourvisor']['offers'].append({'price':'162494.2','fuel_charge':'29184.2','room_norm':'standard room','placement_norm':'dbl'})
assert mod.analyze(ambiguous)['evidence']['unique_concrete_tv_match'] is False
for field,bad in [('booking_calls',1),('mapping_writes',1),('andromeda_requests',1),('additional_prices_requests',1)]:
    broken=copy.deepcopy(value);broken[field]=bad
    try:mod.validate(broken)
    except ValueError:pass
    else:raise AssertionError('invalid effect accepted: '+field)
wrong_program=copy.deepcopy(value);wrong_program['selected_concrete']['supplier_tour_program_id']='778'
try:mod.validate(wrong_program)
except ValueError:pass
else:raise AssertionError('wrong program accepted')
for bad in (None,True,False,float('nan'),float('inf'),-1,'NaN','-1'):
    assert mod.decimal(bad) is None
assert mod.decimal(0.0)==0

php=(DIAG/'anex_concrete_fuel_binding.php').read_text()
for forbidden in ('->bron(', 'bron_ticket', 'broninit(', '->calc(', 'AnyTourAnexAdditionalPricesClient', 'ANEX_B2B_TOKEN',
                  'INSERT INTO anex_hotel_search_mappings', 'UPDATE anex_hotel_search_mappings'):
    assert forbidden not in php
assert "ANEX_CONCRETE_FUEL_DATE = '2026-10-12'" in php
assert "ANEX_CONCRETE_FUEL_PROGRAM = '2637'" in php
assert '->expand(' in php and '->flights(' in php
assert "'tour_flights_requested'=>false" in php
print('ANEX concrete fuel binding offline evidence checks: PASS')
