#!/usr/bin/env python3
"""Fixed ANEX-only price comparison across direct ANEX, Andromeda and Tourvisor."""
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import sys
import tempfile

import anex_search3_gap_queue as gaps

EXPERIMENT = 'anex_three_source_price_20260911_v1'
CASES = ('anex', 'andromeda', 'tourvisor')
SPEC = {'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-09-20','nights':7,
        'adults':2,'child_ages':[],'meal_family':'ai','currency':'RUB'}


def source():
    here=Path(__file__).resolve().parent
    old=(here/'anex_search3_paired_runner.php').read_text(); new=(here/'anex_search3_three_source_price.php').read_text()
    if not old.startswith('<?php') or not new.startswith('<?php'): raise ValueError('three_source_php_header')
    return "define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"+old[5:]+'\n'+new[5:]


def ssh_php_no_mux(source_text, request, maximum_bytes=4000000):
    """No-mux transport; a non-zero exit may still contain our sanitized PHP JSON."""
    if maximum_bytes not in (65536,4000000): raise ValueError('unsupported_diagnostic_response_limit')
    names=('ANYTOOUR_DEPLOY_SSH_KEY','ANYTOOUR_DEPLOY_HOST','ANYTOOUR_DEPLOY_USER')
    if any(not os.environ.get(name,'').strip() for name in names): raise ValueError('missing_ssh_configuration')
    host,user=(os.environ[name].strip() for name in names[1:])
    if host.startswith('-') or user.startswith('-') or any(c.isspace() for c in host+user): raise ValueError('invalid_ssh_target')
    with tempfile.TemporaryDirectory(prefix='anex-three-price-',dir=os.environ.get('RUNNER_TEMP')) as temp:
        key=Path(temp)/'ssh_key'; key.write_text(os.environ[names[0]].rstrip()+'\n'); key.chmod(0o600)
        command=['ssh','-T','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes','-o','ControlMaster=no','-o','ControlPath=none',
                 '-o','StrictHostKeyChecking=accept-new','-o','UserKnownHostsFile='+str(Path(temp)/'known_hosts'),'-o','ConnectTimeout=15',
                 '-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2','-o','LogLevel=DEBUG1','-l',user,host,
                 'cd "$HOME/www/anytoour.ru" && php -r '+shlex.quote(source_text)]
        env={k:v for k,v in os.environ.items() if k not in names and not k.startswith('ANEX_') and not k.startswith('ANDROMEDA_')}
        result=subprocess.run(command,input=json.dumps(request,ensure_ascii=False),text=True,capture_output=True,timeout=310,env=env)
    if len(result.stdout.encode('utf-8'))>maximum_bytes:
        error=gaps.SSHBatchError(result.returncode,result.stderr,oversized=True); error.attempts=1; raise error
    try: value=json.loads(result.stdout)
    except Exception:
        error=gaps.SSHBatchError(result.returncode,result.stderr); error.attempts=1; raise error from None
    if not isinstance(value,dict): raise ValueError('three_source_remote_json_invalid')
    # The PHP runner is intentionally sanitized and emits JSON even for a guarded refusal.
    return value


def validate_case(value,case_id):
    common=(isinstance(value,dict) and value.get('schema_version')==1 and value.get('experiment_id')==EXPERIMENT
            and value.get('case_id')==case_id and value.get('automatic_retry') is False
            and value.get('booking_calls')==0 and value.get('broninit_calls')==0 and value.get('mapping_writes')==0)
    if not common: raise ValueError('three_source_case_invalid')
    status=value.get('status')
    if status=='blocked':
        reason=value.get('reason')
        if value.get('supplier_effect')!='none' or not isinstance(reason,str) or re.fullmatch(r'(?:THREE_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}',reason) is None:
            raise ValueError('three_source_blocked_invalid')
        return value
    if status=='unknown':
        if value.get('supplier_effect')!='unknown': raise ValueError('three_source_unknown_invalid')
        return value
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' or not isinstance(value.get('subject'),dict) \
            or not isinstance(value.get('offers'),list) or len(value['offers'])>2000: raise ValueError('three_source_case_invalid')
    subject=value['subject']; required={'local_hotel_id','anex_hotel_id','andromeda_hotel_id','hotel_name','selection_basis','anex_observation_count'}
    if set(subject)!=required or subject.get('selection_basis')!='current_unique_triple_mapping': raise ValueError('three_source_subject_invalid')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!=case_id or row.get('local_hotel_id')!=subject['local_hotel_id'] \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=7 or row.get('adults')!=2 or row.get('children')!=0 \
                or row.get('meal_family')!='ai' or row.get('currency')!='RUB' or row.get('fuel_inclusion_verified') is not False \
                or row.get('final_price_verified') is not False or not isinstance(row.get('price'),str) \
                or not isinstance(row.get('room_norm'),str) or not isinstance(row.get('placement_norm'),str):
            raise ValueError('three_source_offer_invalid')
    return value


def aligned_key(row):
    return (row['local_hotel_id'],row['date'],row['nights'],row['adults'],row['children'],row['meal_family'],row['room_norm'])


def compare(results):
    completed={case:v for case,v in results.items() if v.get('status')=='completed'}; index={case:{} for case in CASES}
    for case,value in completed.items():
        for row in value['offers']: index[case].setdefault(aligned_key(row),[]).append(row)
    keys=set(index['anex'])&set(index['andromeda'])&set(index['tourvisor']); triples=[]
    for key in sorted(keys,key=str):
        triples.append({'basis':'same_current_triple_mapped_hotel_date_party_ai_and_exact_room','placement_compared_but_not_identity_key':True,
                        'identical_supplier_package_verified':False,'fuel_inclusion_verified':False,
                        'key':{'local_hotel_id':key[0],'date':key[1],'nights':key[2],'adults':key[3],'children':key[4],'meal_family':key[5],'room_norm':key[6]},
                        'offers':{case:index[case][key] for case in CASES}})
    minima={}
    for case,value in completed.items():
        rows=[r for r in value['offers'] if r.get('price')]; minima[case]=min(rows,key=lambda r:float(r['price'])) if rows else None
    subjects=[v.get('subject') for v in completed.values()]
    return {'same_subject_across_completed_cases':bool(subjects) and all(v==subjects[0] for v in subjects),'completed_cases':sorted(completed),
            'aligned_three_source_tour_count':len(triples),'aligned_three_source_examples':triples[:20],'source_minima_for_same_hotel':minima,
            'interpretation':'comparison evidence only; never accept mapping/package identity or add fuel automatically'}


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True); tmp=path.with_suffix(path.suffix+'.tmp')
    tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n'); tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('three_source_report_readback')


def transport_failure(exc):
    allowed={'response_size_limit','ssh_exit_nonzero','ssh_authentication_failed','ssh_host_key_rejected','ssh_connection_timeout',
             'ssh_connection_refused','ssh_name_resolution_failed','ssh_network_unreachable','ssh_session_rejected','ssh_connection_closed'}
    progress=getattr(exc,'progress',None); keys=('tcp_connected','authenticated','multiplexing_seen','command_sent','remote_exit_seen')
    clean={k:bool(progress.get(k)) for k in keys} if isinstance(progress,dict) else {k:False for k in keys}
    reason=getattr(exc,'reason_code',None); attempts=getattr(exc,'attempts',None)
    return {'status':'transport_unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__=='SSHBatchError' else 'other',
            'reason_code':reason if reason in allowed else 'other','ssh_progress':clean,'ssh_attempts':attempts if type(attempts) is int and 1<=attempts<=2 else None,
            'automatic_retry':False,'supplier_replay_requested':False}


def run(output):
    php=source(); results={}
    for case in CASES:
        value=validate_case(ssh_php_no_mux(php,dict(SPEC,case_id=case)),case); results[case]=value; save(output/f'{case}.json',value)
        if value['status']!='completed': break
    all_done=len(results)==len(CASES) and all(v['status']=='completed' for v in results.values())
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':'completed' if all_done else next(v['status'] for v in results.values() if v['status']!='completed'),
            'spec':SPEC,'case_statuses':{k:v['status'] for k,v in results.items()},'comparison':compare(results),
            'fuel_policy':{'tourvisor':'price and fuelCharge separate; do not add automatically','anex':'search price unverified; AdditionalPricesDaily separate pending evidence',
                           'andromeda':'action=price has no documented separate fuel field; search price unverified'},
            'transport_policy':'ControlMaster=no after supplier-free preflight 34540781410','effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0},
            'unknown_replay_allowed':False}
    save(output/'report.json',report); return report


def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_search3_three_source_price.py OUTPUT_DIR')
    output=Path(sys.argv[1])
    try:
        report=run(output); print(json.dumps(report,ensure_ascii=False,sort_keys=True)); raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other','automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True)); raise SystemExit(1) from None

if __name__=='__main__': main()
