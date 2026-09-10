#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import sys
import types

helper = types.ModuleType('anex_search3_owner_decisions')
helper.ssh_php = lambda *args, **kwargs: (_ for _ in ()).throw(AssertionError('network disabled'))
sys.modules['anex_search3_owner_decisions'] = helper
path = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/anex_search3_three_source_price.py'
spec = importlib.util.spec_from_file_location('three_price', path)
mod = importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)

checks = 0
def check(value):
    global checks
    checks += 1
    if not value:
        raise AssertionError(f'three_price_py_{checks}')

subject={'local_hotel_id':6319,'anex_hotel_id':8121,'andromeda_hotel_id':'9001','hotel_name':'APERION BEACH',
         'selection_basis':'current_unique_triple_mapping','anex_observation_count':9}
def row(provider, price, room='standard', placement='dbl', fuel=None):
    return {'provider':provider,'local_hotel_id':6319,'external_hotel_id':'1','date':'2026-09-20','nights':7,'adults':2,'children':0,
            'meal_family':'ai','meal_label':'AI','room':'Standard','room_norm':room,'placement':'DBL','placement_norm':placement,
            'price':price,'currency':'RUB','fuel_charge':fuel,'fuel_inclusion_verified':False,'final_price_verified':False}
def result(provider, price, fuel=None):
    return {'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':provider,'status':'completed','subject':subject,
            'offers':[row(provider,price,fuel=fuel)],'details':{},'supplier_effect':'read_only_search_completed','reused':False,
            'automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0}

results={case:result(case,price,fuel='2500' if case=='tourvisor' else None) for case,price in [('anex','100000'),('andromeda','101000'),('tourvisor','102000')]}
for case,value in results.items(): check(mod.validate_case(value,case) is value)
report=mod.compare(results)
check(report['same_subject_across_completed_cases'] is True)
check(report['aligned_three_source_tour_count']==1)
check(report['aligned_three_source_examples'][0]['identical_supplier_package_verified'] is False)
check(report['aligned_three_source_examples'][0]['placement_compared_but_not_identity_key'] is True)
check(report['aligned_three_source_examples'][0]['fuel_inclusion_verified'] is False)
check(report['source_minima_for_same_hotel']['tourvisor']['fuel_charge']=='2500')

changed_placement=result('andromeda','101000'); changed_placement['offers'][0]['placement_norm']=''
report2=mod.compare({'anex':results['anex'],'andromeda':changed_placement,'tourvisor':results['tourvisor']})
check(report2['aligned_three_source_tour_count']==1)
changed_room=result('tourvisor','102000'); changed_room['offers'][0]['room_norm']='family'
report3=mod.compare({'anex':results['anex'],'andromeda':results['andromeda'],'tourvisor':changed_room})
check(report3['aligned_three_source_tour_count']==0)
unknown={'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':'anex','status':'unknown','reason':'THREE_PRICE_UNCONFIRMED',
         'supplier_effect':'unknown','automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0}
check(mod.validate_case(unknown,'anex') is unknown)

bad=result('anex','100000'); bad['booking_calls']=1
try: mod.validate_case(bad,'anex'); check(False)
except ValueError: check(True)

class SSHBatchError(Exception):
    reason_code='ssh_connection_closed'
    attempts=2
    progress={'tcp_connected':True,'authenticated':False,'multiplexing_seen':False,'command_sent':False,'remote_exit_seen':False}

failure=mod.transport_failure(SSHBatchError('SECRET HOST STDERR'))
check(failure['status']=='transport_unconfirmed' and failure['reason_code']=='ssh_connection_closed')
check(failure['ssh_attempts']==2 and failure['ssh_progress']['tcp_connected'] is True)
check(failure['ssh_progress']['command_sent'] is False and failure['automatic_retry'] is False)
check('SECRET' not in str(failure) and failure['supplier_replay_requested'] is False)

class WeirdSSHBatchError(Exception):
    reason_code='private-secret-code'
    attempts=99
    progress={'authenticated':'yes','command_sent':1,'extra':'secret'}

failure2=mod.transport_failure(WeirdSSHBatchError())
check(failure2['reason_code']=='other' and failure2['ssh_attempts'] is None)
check(set(failure2['ssh_progress'])=={'tcp_connected','authenticated','multiplexing_seen','command_sent','remote_exit_seen'})

print(f'Three-source ANEX price Python guards: {checks} checks passed; network=0')
