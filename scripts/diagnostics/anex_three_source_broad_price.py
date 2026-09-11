#!/usr/bin/env python3
"""One bounded broad ANEX-only search across direct ANEX, Andromeda and Tourvisor."""
import json
from pathlib import Path
import sys

from anex_search3_three_source_price import ssh_php_no_mux, transport_failure

EXPERIMENT='anex_three_source_broad_price_20260911_v3'
CASES=('anex','andromeda','tourvisor')
SPEC={'experiment_id':EXPERIMENT,'country':'Egypt','date':'2026-10-19','nights':10,'adults':3,'child_ages':[],'meal_family':'ai','currency':'RUB'}


def source():
    here=Path(__file__).resolve().parent
    old=(here/'anex_search3_paired_runner.php').read_text()
    meal=(here.parents[1]/'app/integrations/three-provider-meal.php').read_text()
    new=(here/'anex_three_source_broad_price.php').read_text()
    if not old.startswith('<?php') or not meal.startswith('<?php') or not new.startswith('<?php'):
        raise ValueError('broad_php_header')
    strict='\ndeclare(strict_types=1);\n'
    meal_body=meal[5:];new_body=new[5:]
    if not meal_body.startswith(strict) or not new_body.startswith(strict):
        raise ValueError('broad_php_strict_header')
    return "declare(strict_types=1);\ndefine('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"+old[5:]+'\n'+meal_body[len(strict):]+'\n'+new_body[len(strict):]


def validate_case(value,case_id):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT \
            or value.get('case_id')!=case_id or value.get('automatic_retry') is not False \
            or value.get('booking_calls')!=0 or value.get('broninit_calls')!=0 or value.get('mapping_writes')!=0 \
            or value.get('observation_writes_allowed') is not True:
        raise ValueError('broad_case_invalid')
    status=value.get('status')
    if status=='unknown':
        if value.get('supplier_effect')!='unknown': raise ValueError('broad_unknown_invalid')
        return value
    if status=='blocked':
        if value.get('supplier_effect')!='none': raise ValueError('broad_blocked_invalid')
        return value
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' or not isinstance(value.get('offers'),list) or len(value['offers'])>1500:
        raise ValueError('broad_case_invalid')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!=case_id or not isinstance(row.get('local_hotel_id'),int) or row['local_hotel_id']<1 \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=10 or row.get('adults')!=3 or row.get('children')!=0 \
                or row.get('meal_family')!='ai' or row.get('meal_key')!='ai' or row.get('meal_qualifiers')!=[] \
                or row.get('meal_equivalence_verified') is not False or row.get('currency')!='RUB' or not isinstance(row.get('price'),str) \
                or not isinstance(row.get('room_norm'),str) or not isinstance(row.get('placement_norm'),str) \
                or row.get('fuel_inclusion_verified') is not False or row.get('final_price_verified') is not False:
            raise ValueError('broad_offer_invalid')
    return value


def key(row):
    return (row['local_hotel_id'],row['date'],row['nights'],row['adults'],row['children'],row['meal_key'],row['room_norm'])


def index(results):
    out={case:{} for case in CASES}
    for case,value in results.items():
        if value.get('status')!='completed': continue
        for row in value['offers']: out[case].setdefault(key(row),[]).append(row)
    return out


def comparison(results):
    idx=index(results); sets={case:set(idx[case]) for case in CASES}
    triple=sets['anex']&sets['andromeda']&sets['tourvisor']
    pairs={
        'anex_andromeda':sets['anex']&sets['andromeda'],
        'anex_tourvisor':sets['anex']&sets['tourvisor'],
        'andromeda_tourvisor':sets['andromeda']&sets['tourvisor'],
    }
    examples=[]
    for item in sorted(triple,key=str)[:30]:
        examples.append({'basis':'same_current_local_hotel_date_party_canonical_meal_key_and_room','identical_supplier_package_verified':False,
            'fuel_inclusion_verified':False,'key':{'local_hotel_id':item[0],'date':item[1],'nights':item[2],'adults':item[3],'children':item[4],'meal_key':item[5],'room_norm':item[6]},
            'offers':{case:idx[case][item] for case in CASES}})
    pair_examples={}
    for name,items in pairs.items():
        a,b=name.split('_',1) if name!='andromeda_tourvisor' else ('andromeda','tourvisor')
        if name=='anex_andromeda': a,b='anex','andromeda'
        elif name=='anex_tourvisor': a,b='anex','tourvisor'
        selected=[]
        for item in sorted(items-triple,key=str)[:20]:
            selected.append({'key':{'local_hotel_id':item[0],'date':item[1],'nights':item[2],'adults':item[3],'children':item[4],'meal_key':item[5],'room_norm':item[6]},
                'offers':{a:idx[a][item],b:idx[b][item]},'identical_supplier_package_verified':False})
        pair_examples[name]=selected
    counts={case:{'offers':len(results.get(case,{}).get('offers',[])),'unique_tuples':len(sets[case]),
                  'fuel_reported_offers':sum(1 for row in results.get(case,{}).get('offers',[]) if row.get('fuel_charge') is not None)} for case in CASES}
    return {'provider_counts':counts,'triple_tuple_count':len(triple),'pair_tuple_counts':{name:len(items) for name,items in pairs.items()},
            'triple_examples':examples,'pair_only_examples':pair_examples,
            'interpretation':'display-level comparison candidates only; price equality never proves package identity and fuel is never added automatically'}


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix(path.suffix+'.tmp')
    tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('broad_report_readback')


def run(output):
    php=source();results={}
    for case in CASES:
        value=validate_case(ssh_php_no_mux(php,dict(SPEC,case_id=case),maximum_bytes=4000000),case)
        results[case]=value;save(output/f'{case}.json',value)
        if value['status']!='completed': break
    all_done=len(results)==3 and all(value['status']=='completed' for value in results.values())
    unresolved={
        'anex_unmapped_received':results.get('anex',{}).get('details',{}).get('unmapped_received'),
        'andromeda_received_offers':results.get('andromeda',{}).get('details',{}).get('received_offers'),
        'andromeda_mapped_offers':results.get('andromeda',{}).get('details',{}).get('mapped_offers'),
    }
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':'completed' if all_done else next((v['status'] for v in results.values() if v['status']!='completed'),'unconfirmed'),
            'spec':SPEC,'case_statuses':{k:v['status'] for k,v in results.items()},'comparison':comparison(results),'observation_summary':unresolved,
            'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes_allowed':True},
            'unknown_replay_allowed':False,'p3_matching_external':True}
    save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_three_source_broad_price.py OUTPUT_DIR')
    output=Path(sys.argv[1])
    try:
        report=run(output);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other','automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True));raise SystemExit(1) from None

if __name__=='__main__':main()
