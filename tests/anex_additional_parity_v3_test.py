#!/usr/bin/env python3
import copy
import importlib.util
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
        'selected_pair':{
            'basis':'same_current_local_hotel_date_party_ai_and_exact_room',
            'anex':{'local_hotel_id':1039,'room_norm':'standard room','price':'100000'},
            'tourvisor':{'price':str(100000+float(fuel)),'fuel_charge':fuel},
        },
        'additional_prices':{'data':[{'price_adult':'120','price_chd':'120','cashrate':'104.23',
            'price_converted_adult':adult,'price_converted_chd':adult,'tour':778,'currency':3,
            'dateBeg':'2026-10-12','nights':7}]},
    }


assembled=mod.source()
assert assembled.startswith("declare(strict_types=1);\n")
assert 'function anex_paired_text' in assembled
assert 'final class AnyTourAnexAdditionalPricesClient' in assembled
assert 'function anex_additional_parity_main' in assembled
assert assembled.count('<?php')==0
assert '$report=anex_additional_parity_main(true);' in mod.source(True)

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

# Actual B2B converted prices are JSON numbers, not always decimal strings.
floating=mod.analyze(completed(fuel='27100',adult=13549.9))['evidence']
assert floating['candidate_two_adult_rate_sum']=='27099.8'
assert floating['two_adult_rate_sum_equals_tourvisor_fuel'] is False  # no hidden rounding rule
for bad in (None,True,False,float('nan'),float('inf'),-1,'NaN','Infinity','-1'):
    assert mod.decimal(bad) is None
assert mod.decimal(0.0)==0
ambiguous=completed(); ambiguous['additional_prices']['data']*=2
assert mod.analyze(ambiguous)['evidence']['state']=='additional_rows_ambiguous'

common={'local_hotel_id':21753,'date':'2026-10-12','nights':7,'adults':2,'children':0,
        'meal_family':'ai','currency':'RUB','room_norm':'standard room','placement_norm':'dbl'}
anex=dict(common,provider='anex',external_hotel_id='25084',supplier_tour_program_id='2637',
          supplier_currency_id='3',price='119448',fuel_charge=None)
tv=dict(common,provider='tourvisor',external_hotel_id='21753',price='140294',fuel_charge='20846')
retained={'experiment_id':mod.EXPERIMENT,'status':'unknown','supplier_replay_allowed':False,
          'search_evidence':{'anex':{'offers':[anex]},'tourvisor':{'offers':[
              dict(tv,price='162494',fuel_charge='29184'),tv,dict(tv,price='143212')]}}}
pair=mod.program2637_pair(retained)
assert pair['tourvisor']['price']=='140294' and pair['tourvisor']['fuel_charge']=='20846'
assert pair['anex']['fuel_charge'] is None and pair['identical_supplier_package_verified'] is False
assert retained['status']=='unknown'  # consuming evidence does not reopen the operation

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
for field,bad_value in [('direct_anex_requests',1),('tourvisor_requests',1),('additional_prices_requests',2)]:
    bad=dict(followup);bad[field]=bad_value
    try: mod.validate(bad,True)
    except ValueError: pass
    else: raise AssertionError('unexpected supplier request accepted: '+field)
bad=copy.deepcopy(followup);bad['additional_request_context']['tour']=778
try: mod.validate(bad,True)
except ValueError: pass
else: raise AssertionError('wrong program accepted')

php=(DIAG/'anex_additional_parity_v3.php').read_text()
for forbidden in ('->bron(', 'bron_ticket', 'broninit(', '->calc(', 'INSERT INTO anex_hotel_search_mappings', 'UPDATE anex_hotel_search_mappings'):
    assert forbidden not in php
assert "ANEX_ADDITIONAL_PARITY_DATE = '2026-10-12'" in php
assert "AnyTourAnexAdditionalPricesClient" in php
assert "supplier_replay_allowed'=>false" in php
assert 'ANEX_ADDITIONAL_PARITY_TARGETS' in php
print('ANEX additional parity v3 offline evidence checks: PASS')
