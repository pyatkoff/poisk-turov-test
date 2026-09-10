#!/usr/bin/env python3
import importlib.util
import json
import os
from pathlib import Path
import sys
from types import SimpleNamespace

path=Path(__file__).resolve().parents[1]/'scripts/diagnostics/anex_search3_three_source_price.py'
sys.path.insert(0,str(path.parent))
spec=importlib.util.spec_from_file_location('three_price',path); mod=importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)
checks=0
def check(value):
    global checks; checks+=1
    if not value: raise AssertionError(f'three_price_py_{checks}')

subject={'local_hotel_id':6319,'anex_hotel_id':8121,'andromeda_hotel_id':'9001','hotel_name':'APERION BEACH','selection_basis':'current_unique_triple_mapping','anex_observation_count':9}
def row(provider,price,room='standard',placement='dbl',fuel=None):
    return {'provider':provider,'local_hotel_id':6319,'external_hotel_id':'1','date':'2026-09-20','nights':7,'adults':2,'children':0,'meal_family':'ai','meal_label':'AI','room':'Standard','room_norm':room,'placement':'DBL','placement_norm':placement,'price':price,'currency':'RUB','fuel_charge':fuel,'fuel_inclusion_verified':False,'final_price_verified':False}
def result(provider,price,fuel=None):
    return {'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':provider,'status':'completed','subject':subject,'offers':[row(provider,price,fuel=fuel)],'details':{},'supplier_effect':'read_only_search_completed','reused':False,'automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0}

results={case:result(case,price,fuel='2500' if case=='tourvisor' else None) for case,price in [('anex','100000'),('andromeda','101000'),('tourvisor','102000')]}
for case,value in results.items(): check(mod.validate_case(value,case) is value)
report=mod.compare(results)
check(report['same_subject_across_completed_cases'] is True); check(report['aligned_three_source_tour_count']==1)
check(report['aligned_three_source_examples'][0]['identical_supplier_package_verified'] is False)
check(report['aligned_three_source_examples'][0]['fuel_inclusion_verified'] is False)
check(report['source_minima_for_same_hotel']['tourvisor']['fuel_charge']=='2500')
changed=result('tourvisor','102000'); changed['offers'][0]['room_norm']='family'
check(mod.compare({'anex':results['anex'],'andromeda':results['andromeda'],'tourvisor':changed})['aligned_three_source_tour_count']==0)
unknown={'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':'anex','status':'unknown','reason':'THREE_PRICE_UNCONFIRMED','supplier_effect':'unknown','automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0}
check(mod.validate_case(unknown,'anex') is unknown)
blocked={'schema_version':1,'experiment_id':mod.EXPERIMENT,'case_id':'anex','status':'blocked','reason':'THREE_PRICE_CASE_NOT_REPLAYABLE','offers':[],'supplier_effect':'none','automatic_retry':False,'booking_calls':0,'broninit_calls':0,'mapping_writes':0}
check(mod.validate_case(blocked,'anex') is blocked)
bad=dict(blocked,reason='PRIVATE ERROR: password');
try: mod.validate_case(bad,'anex'); check(False)
except ValueError: check(True)

class SSHBatchError(Exception):
    reason_code='ssh_connection_closed'; attempts=2
    progress={'tcp_connected':True,'authenticated':False,'multiplexing_seen':False,'command_sent':False,'remote_exit_seen':False}
failure=mod.transport_failure(SSHBatchError('SECRET HOST STDERR'))
check(failure['status']=='transport_unconfirmed' and failure['reason_code']=='ssh_connection_closed'); check('SECRET' not in str(failure))

os.environ['ANYTOOUR_DEPLOY_SSH_KEY']='PRIVATE-KEY-FIXTURE'; os.environ['ANYTOOUR_DEPLOY_HOST']='example.invalid'; os.environ['ANYTOOUR_DEPLOY_USER']='fixture-user'
captured={}
def fake_run(command,**kwargs):
    captured['command']=command; captured['kwargs']=kwargs
    return SimpleNamespace(stdout=json.dumps(blocked),stderr='debug1: Sending command: fixed\ndebug1: Exit status 1\n',returncode=1)
mod.subprocess.run=fake_run
wire=mod.ssh_php_no_mux('echo 1;',{'case':'fixture'}); check(wire==blocked)
joined=' '.join(captured['command']); check('ControlMaster=no' in joined and 'ControlPath=none' in joined and 'ControlMaster=auto' not in joined)
check('PRIVATE-KEY-FIXTURE' not in joined and 'PRIVATE-KEY-FIXTURE' not in str(captured['kwargs']['env']))
check(captured['kwargs']['input']==json.dumps({'case':'fixture'},ensure_ascii=False))

# Non-JSON exit remains a classified SSH error and never leaks stderr.
def fake_bad(command,**kwargs): return SimpleNamespace(stdout='',stderr='Permission denied SECRET',returncode=255)
mod.subprocess.run=fake_bad
try: mod.ssh_php_no_mux('echo 1;',{'case':'fixture'}); check(False)
except Exception as exc:
    check(type(exc).__name__=='SSHBatchError'); sanitized=mod.transport_failure(exc); check('SECRET' not in str(sanitized))

print(f'Three-source ANEX price Python guards: {checks} checks passed; network=0')
