#!/usr/bin/env python3
import copy
import importlib.util
import json
from pathlib import Path
import sys
import tempfile

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
        'selected_pair':{'basis':'same_current_local_hotel_date_party_ai_and_exact_room',
            'anex':{'local_hotel_id':1039,'room_norm':'standard room','price':'100000'},
            'tourvisor':{'price':str(100000+float(fuel)),'fuel_charge':fuel}},
        'additional_prices':{'data':[{'price_adult':'120','price_chd':'120','cashrate':'104.23',
            'price_converted_adult':adult,'price_converted_chd':adult,'tour':778,'currency':3,
            'dateBeg':'2026-10-12','nights':7}]}}

assembled=mod.source()
assert assembled.startswith("declare(strict_types=1);\n")
assert 'function anex_paired_text' in assembled and 'final class AnyTourAnexAdditionalPricesClient' in assembled
assert 'function anex_additional_parity_main' in assembled and assembled.count('<?php')==0
assert '$report=anex_additional_parity_main(true);' in mod.source(True)
program1797_source=mod.source(False,True)
assert "tour'=>1797" in program1797_source and mod.PROGRAM1797_EXPERIMENT in program1797_source
assert 'anex_additional_parity_save' in program1797_source and 'ADDITIONAL_PARITY_NOT_REPLAYABLE' in program1797_source

value=mod.validate(completed())
report=mod.analyze(value); evidence=report['evidence']
assert report['price_arithmetic_applied'] is False and report['universal_formula_verified'] is False
assert evidence['delta_equals_tourvisor_fuel'] is True and evidence['two_adult_rate_sum_equals_tourvisor_fuel'] is True
assert evidence['single_adult_rate_equals_tourvisor_fuel'] is False and evidence['application_rule_verified_for_search'] is False
mismatch=mod.analyze(completed(fuel='30000'))['evidence']
assert mismatch['delta_equals_tourvisor_fuel'] is True and mismatch['two_adult_rate_sum_equals_tourvisor_fuel'] is False
floating=mod.analyze(completed(fuel='27100',adult=13549.9))['evidence']
assert floating['candidate_two_adult_rate_sum']=='27099.8' and floating['two_adult_rate_sum_equals_tourvisor_fuel'] is False
for bad in (None,True,False,float('nan'),float('inf'),-1,'NaN','Infinity','-1'): assert mod.decimal(bad) is None
assert mod.decimal(0.0)==0

common={'local_hotel_id':21753,'date':'2026-10-12','nights':7,'adults':2,'children':0,
        'meal_family':'ai','currency':'RUB','room_norm':'standard room','placement_norm':'dbl'}
anex=dict(common,provider='anex',external_hotel_id='25084',supplier_tour_program_id='2637',supplier_currency_id='3',price='119448',fuel_charge=None)
tv=dict(common,provider='tourvisor',external_hotel_id='21753',price='140294',fuel_charge='20846')
retained={'experiment_id':mod.EXPERIMENT,'status':'unknown','supplier_replay_allowed':False,
          'search_evidence':{'anex':{'offers':[anex]},'tourvisor':{'offers':[dict(tv,price='162494',fuel_charge='29184'),tv,dict(tv,price='143212')]}}}
pair=mod.program2637_pair(retained)
assert pair['tourvisor']['price']=='140294' and pair['tourvisor']['fuel_charge']=='20846'
assert pair['anex']['fuel_charge'] is None and pair['identical_supplier_package_verified'] is False
assert retained['status']=='unknown'
for field,bad_value in [('date','2026-10-13'),('children',1),('currency','USD'),('supplier_tour_program_id','778')]:
    bad=copy.deepcopy(retained); bad['search_evidence']['anex']['offers'][0][field]=bad_value
    try: mod.program2637_pair(bad)
    except ValueError: pass
    else: raise AssertionError('retained context mismatch accepted: '+field)
with tempfile.TemporaryDirectory() as directory:
    path=Path(directory)/'result.json';path.write_text('{}')
    try: mod.read_program2637_evidence(path)
    except ValueError as error: assert str(error)=='additional_program_retained_digest'
    else: raise AssertionError('unverified retained evidence accepted')

followup=completed(fuel='20846',adult=10423.0)
followup.update(experiment_id=mod.PROGRAM2637_EXPERIMENT,selected_pair=pair,direct_anex_requests=0,
                tourvisor_requests=0,additional_request_context=dict(mod.PROGRAM2637_CONTEXT),
                supplier_effect='read_only_additional_from_retained_search_completed')
assert mod.validate(followup,True) is followup
assert mod.analyze(followup)['evidence']['two_adult_rate_sum_equals_tourvisor_fuel'] is True

# Program1797 reuses the completed concrete/TV artifact and must not replay either search.
concrete1797={
    'schema_version':1,'experiment_id':'anex_concrete_fuel_binding_20260913_v2','status':'completed',
    'supplier_replay_allowed':False,'additional_prices_requests':0,
    'concrete_offers':[
        {'kind':'concrete','price':'133310','room_norm':'standard room','placement_norm':'dbl','supplier_tour_program_id':'1797','supplier_currency_id':'3'},
        {'kind':'concrete','price':'136541','room_norm':'standard room','placement_norm':'dbl','supplier_tour_program_id':'1797','supplier_currency_id':'3'},
        {'kind':'concrete','price':'119448','room_norm':'standard room','placement_norm':'dbl','supplier_tour_program_id':'2637','supplier_currency_id':'3'}],
    'tourvisor':{'offers':[
        {'price':'140294','fuel_charge':'20846','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'143212','fuel_charge':'20846','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'162494','fuel_charge':'29184','room_norm':'standard room','placement_norm':'dbl'},
        {'price':'165725','fuel_charge':'29184','room_norm':'standard room','placement_norm':'dbl'}]}}
raw=json.dumps(concrete1797,separators=(',',':')).encode()
with tempfile.TemporaryDirectory() as directory:
    path=Path(directory)/'result.json';path.write_bytes(raw)
    old=mod.CONCRETE1797_RESULT_SHA256;mod.CONCRETE1797_RESULT_SHA256=__import__('hashlib').sha256(raw).hexdigest()
    retained1797=mod.read_program1797_evidence(path);mod.CONCRETE1797_RESULT_SHA256=old
assert [str(x) for x in retained1797['direct_prices']]==['133310','136541']
assert [(str(x['direct_price']),str(x['tourvisor_price']),str(x['tourvisor_fuel'])) for x in retained1797['exact_pairs']]==[
    ('133310','162494','29184'),('136541','165725','29184')]
program1797_value={'schema_version':1,'experiment_id':mod.PROGRAM1797_EXPERIMENT,'status':'completed','automatic_retry':False,
    'supplier_replay_allowed':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'direct_anex_requests':0,'tourvisor_requests':0,
    'additional_prices_requests':1,'supplier_effect':'read_only_additional_from_retained_concrete_completed',
    'additional_request_context':dict(mod.PROGRAM1797_CONTEXT),'additional_prices':{'data':[{
        'price_adult':'140','price_chd':'140','cashrate':'104.23','price_converted_adult':'14592.2','price_converted_chd':'14592.2',
        'tour':1797,'currency':3,'dateBeg':'2026-10-12','nights':7}]}}
assert mod.validate(program1797_value,program1797=True) is program1797_value
r1797=mod.analyze_program1797(program1797_value,retained1797)
assert r1797['new_requests']=={'direct_anex_requests':0,'tourvisor_requests':0,'additional_prices_requests':1}
assert r1797['evidence']['candidate_two_adult_rate_sum']=='29184.4'
assert r1797['evidence']['all_retained_pairs_match_candidate_within_one_ruble'] is True
assert all(x['candidate_matches_within_one_ruble'] for x in r1797['evidence']['retained_exact_concrete_tv_pairs'])
assert r1797['evidence']['additional_prices_equals_fuel_verified'] is False
for field,bad_value in [('direct_anex_requests',1),('tourvisor_requests',1),('additional_prices_requests',2)]:
    bad=dict(program1797_value);bad[field]=bad_value
    try: mod.validate(bad,program1797=True)
    except ValueError: pass
    else: raise AssertionError('unexpected program1797 request accepted: '+field)
bad=copy.deepcopy(program1797_value);bad['additional_request_context']['tour']=2637
try: mod.validate(bad,program1797=True)
except ValueError: pass
else: raise AssertionError('wrong program1797 context accepted')

php=(DIAG/'anex_additional_parity_v3.php').read_text()
for forbidden in ('->bron(', 'bron_ticket', 'broninit(', '->calc(', 'INSERT INTO anex_hotel_search_mappings', 'UPDATE anex_hotel_search_mappings'):
    assert forbidden not in php
assert "ANEX_ADDITIONAL_PARITY_DATE = '2026-10-12'" in php and "AnyTourAnexAdditionalPricesClient" in php
assert "supplier_replay_allowed'=>false" in php and 'ANEX_ADDITIONAL_PARITY_TARGETS' in php
print('ANEX additional parity v3 offline evidence checks: PASS')
