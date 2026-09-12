#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
DIAG=ROOT/'scripts'/'diagnostics'
sys.path.insert(0,str(DIAG))
spec=importlib.util.spec_from_file_location('additional_parity',DIAG/'anex_additional_parity_v3.py')
mod=importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)


def completed(fuel='25015.2', adult='12507.6'):
    return {
        'schema_version':1,'experiment_id':mod.EXPERIMENT,'status':'completed','automatic_retry':False,
        'supplier_replay_allowed':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,
        'supplier_effect':'read_only_search_and_additional_completed','additional_prices_requests':1,
        'selected_pair':{
            'basis':'same_current_local_hotel_date_party_ai_and_exact_room',
            'anex':{'local_hotel_id':1039,'room_norm':'standard room','price':'100000'},
            'tourvisor':{'price':str(100000+float(fuel)),'fuel_charge':fuel},
        },
        'additional_prices':{'data':[{'price_adult':'120','price_chd':'120','cashrate':'104.23',
            'price_converted_adult':adult,'price_converted_chd':adult,'tour':778,'currency':3,
            'dateBeg':'2026-10-12','nights':7}]},
    }


value=mod.validate(completed())
report=mod.analyze(value); evidence=report['evidence']
assert report['price_arithmetic_applied'] is False and report['universal_formula_verified'] is False
assert evidence['delta_equals_tourvisor_fuel'] is True
assert evidence['two_adult_rate_sum_equals_tourvisor_fuel'] is True
assert evidence['single_adult_rate_equals_tourvisor_fuel'] is False
assert evidence['application_rule_verified_for_search'] is False

mismatch=mod.analyze(completed(fuel='30000'))['evidence']
assert mismatch['delta_equals_tourvisor_fuel'] is True
assert mismatch['two_adult_rate_sum_equals_tourvisor_fuel'] is False

blocked=mod.analyze({'status':'blocked','reason':'ADDITIONAL_PARITY_NO_ALIGNED_PAIR'})
assert blocked['status']=='blocked' and blocked['reason']=='ADDITIONAL_PARITY_NO_ALIGNED_PAIR'

php=(DIAG/'anex_additional_parity_v3.php').read_text()
for forbidden in ('->bron(', 'bron_ticket', 'broninit(', '->calc(', 'INSERT INTO anex_hotel_search_mappings', 'UPDATE anex_hotel_search_mappings'):
    assert forbidden not in php
assert "ANEX_ADDITIONAL_PARITY_DATE = '2026-10-12'" in php
assert "AnyTourAnexAdditionalPricesClient" in php
assert "supplier_replay_allowed'=>false" in php
assert 'ANEX_ADDITIONAL_PARITY_TARGETS' in php
print('ANEX additional parity v3 offline evidence checks: PASS')
