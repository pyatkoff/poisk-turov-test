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
assert mod.RETAINED_SHA256=='940a8677c0084a99e0c1f36baaa9e7301d1afef65d89c06a39c64f8799e11163'

value={
    'schema_version':1,'experiment_id':mod.EXPERIMENT,'status':'completed','automatic_retry':False,
    'supplier_replay_allowed':False,'supplier_effect':'read_only_search_expand_flights_tv_with_retained_additional_completed',
    'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'andromeda_requests':0,
    'anex_requests':6,'tourvisor_requests':5,'additional_prices_requests':0,
    'additional_source':'retained_completed_operation','additional_result_sha256':mod.RETAINED_SHA256,
    'group_minimum':{'kind':'group_minimum','price':'119448','room_norm':'standard room','supplier_tour_program_id':'2637','supplier_currency_id':'3'},
    'selected_concrete':{'kind':'concrete','price':'133310','room_norm':'standard room','placement_norm':'dbl','supplier_tour_program_id':'2637','supplier_currency_id':'3'},
    'concrete_offers':[{'kind':'concrete','price':'133310','room_norm':'standard room','supplier_tour_program_id':'2637','supplier_currency_id':'3'}],
    'anex_flights':{'routes':[{'options':[{'departure_airport':'SVO','arrival_airport':'ADB'}]}],'selected':False},
    'additional_prices':{'data':[{'tour':2637,'currency':3,'dateBeg':'2026-10-12T00:00:00','nights':7,'price_adult':140,
        'price_chd':140,'cashrate':104.23,'price_converted_adult':14592.2,'price_converted_chd':14592.2}],
        'totalCount':1,'totalPages':1},
    'tourvisor':{'offers':[
        {'price':'140294','fuel_charge':'20846','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'162494','fuel_charge':'29184','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'165725','fuel_charge':'29184','room_norm':'standard room','placement_norm':'dbl'},
    ],'search_complete':True},
}
assert mod.validate(value) is value
report=mod.analyze(value);e=report['evidence']
assert report['production_price_arithmetic_applied'] is False
assert report['requests']['additional_prices']==0
assert e['additional_source']=='retained_completed_operation'
assert e['additional_result_sha256']==mod.RETAINED_SHA256
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

ambiguous=copy.deepcopy(value)
ambiguous['tourvisor']['offers'].append({'price':'162494.2','fuel_charge':'29184.2','room_norm':'standard room','placement_norm':'dbl'})
assert mod.analyze(ambiguous)['evidence']['unique_concrete_tv_match'] is False
for field,bad in [('booking_calls',1),('mapping_writes',1),('andromeda_requests',1),('additional_prices_requests',1)]:
    broken=copy.deepcopy(value);broken[field]=bad
    try:mod.validate(broken)
    except ValueError:pass
    else:raise AssertionError('invalid effect accepted: '+field)
for bad in (None,True,False,float('nan'),float('inf'),-1,'NaN','-1'):
    assert mod.decimal(bad) is None
assert mod.decimal(0.0)==0

php=(DIAG/'anex_concrete_fuel_binding.php').read_text()
for forbidden in ('->bron(', 'bron_ticket', 'broninit(', '->calc(', 'additionalPricesDaily', 'AnyTourAnexAdditionalPricesClient', 'ANEX_B2B_TOKEN',
                  'INSERT INTO anex_hotel_search_mappings', 'UPDATE anex_hotel_search_mappings'):
    assert forbidden not in php
assert "ANEX_CONCRETE_FUEL_EXPERIMENT = 'anex_concrete_fuel_binding_20260912_v2'" in php
assert "ANEX_CONCRETE_FUEL_DATE = '2026-10-12'" in php
assert '->expand(' in php and '->flights(' in php
assert "'tour_flights_requested'=>false" in php
assert "'additional_prices_requests'=>0" in php
print('ANEX concrete fuel binding v2 offline evidence checks: PASS')
