#!/usr/bin/env python3
"""One bounded two-adult control for the completed three-adult selected-hotel P1 scenario."""
import json
from pathlib import Path
import re
import sys

import anex_search3_three_source_price as base
import anex_three_source_family_price as party

EXPERIMENT='anex_three_source_party_control_20260913_v1'
CASES=party.CASES
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-20','nights':8,
      'adults':2,'child_ages':[],'meal_family':'ai','currency':'RUB'}


def _replace(text, old, new, minimum=1):
    count=text.count(old)
    if count < minimum:
        raise ValueError('party_control_source_contract_changed')
    return text.replace(old,new)


def source():
    """Keep the reviewed selected-hotel runtime and change only experiment/adult-count identity."""
    text=party.source()
    text=_replace(text,party.EXPERIMENT,EXPERIMENT)
    text=_replace(text,"($value['adults'] ?? null) !== 3","($value['adults'] ?? null) !== 2")
    text=_replace(text,"(int)$adults !== 3","(int)$adults !== 2")
    text=_replace(text,"'adults'=>3","'adults'=>2")
    text=_replace(text,"'ADULT'=>3","'ADULT'=>2")
    text=_replace(text,"$tour['adults']??3","$tour['adults']??2")
    text=_replace(text,"'three-price-party-20260913-v2'","'three-price-party-control-20260913-v1'")
    text=_replace(text,"'generation'=>26091302","'generation'=>26091303")
    forbidden=(party.EXPERIMENT,"($value['adults'] ?? null) !== 3","(int)$adults !== 3",
               "'adults'=>3","'ADULT'=>3","$tour['adults']??3","'three-price-party-20260913-v2'",
               "'generation'=>26091302")
    if any(value in text for value in forbidden):
        raise ValueError('party_control_old_scenario_leaked')
    required=(EXPERIMENT,SPEC['date'],"($value['adults'] ?? null) !== 2","(int)$adults !== 2",
              "'adults'=>2","'ADULT'=>2","$tour['adults']??2","'nightsFrom'=>8","'nightsTo'=>8",
              "'hotelIds'=>[(int)$subject['local_hotel_id']]", "'hotel_ids'=>[(string)$subject['anex_hotel_id']]")
    if any(value not in text for value in required):
        raise ValueError('party_control_source_incomplete')
    return text


def validate_case(value,case_id):
    common=(isinstance(value,dict) and value.get('schema_version')==1 and value.get('experiment_id')==EXPERIMENT
            and value.get('case_id')==case_id and value.get('automatic_retry') is False
            and value.get('booking_calls')==0 and value.get('broninit_calls')==0 and value.get('mapping_writes')==0)
    if not common:
        raise ValueError('party_control_case_invalid')
    status=value.get('status')
    if status=='blocked':
        reason=value.get('reason')
        if value.get('supplier_effect')!='none' or not isinstance(reason,str) or re.fullmatch(r'(?:THREE_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}',reason) is None:
            raise ValueError('party_control_blocked_invalid')
        return value
    if status=='unknown':
        if value.get('supplier_effect')!='unknown':
            raise ValueError('party_control_unknown_invalid')
        return value
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' \
            or not isinstance(value.get('subject'),dict) or not isinstance(value.get('offers'),list) or len(value['offers'])>2000:
        raise ValueError('party_control_case_invalid')
    subject=value['subject']
    required={'local_hotel_id','anex_hotel_id','andromeda_hotel_id','hotel_name','selection_basis','anex_observation_count'}
    if set(subject)!=required or subject.get('selection_basis')!='current_unique_triple_mapping':
        raise ValueError('party_control_subject_invalid')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!=case_id or row.get('local_hotel_id')!=subject['local_hotel_id'] \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=8 or row.get('adults')!=2 or row.get('children')!=0 \
                or row.get('meal_family')!='ai' or row.get('currency')!='RUB' or row.get('fuel_inclusion_verified') is not False \
                or row.get('final_price_verified') is not False or not isinstance(row.get('price'),str) \
                or not isinstance(row.get('room_norm'),str) or not isinstance(row.get('placement_norm'),str):
            raise ValueError('party_control_offer_invalid')
    return value


def save(path,value):
    path=Path(path);path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.tmp')
    tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    tmp.replace(path)
    if json.loads(path.read_text())!=value:
        raise ValueError('party_control_report_readback')


def build_report(results,status,transport=None):
    subjects=[value.get('subject') for value in results.values() if isinstance(value.get('subject'),dict)]
    same_subject=len(subjects)>0 and all(item==subjects[0] for item in subjects)
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':status,'spec':SPEC,
            'case_statuses':{k:v['status'] for k,v in results.items()},
            'missing_cases':[case for case in CASES if case not in results],
            'comparison':base.compare(results),'same_subject_across_completed_cases':same_subject,
            'p1_scope':'two-adult control for the sealed three-adult selected-hotel scenario; same date/stay/meal and current subject-selection contract',
            'interpretation_guard':'difference versus the sealed 3-adult run may isolate party sensitivity; it does not prove universal provider coverage',
            'unmapped_evidence_policy':'hotel-scoped scenario does not create identities; matching remains external #1759',
            'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'additional_prices_calls':0},
            'supplier_replay_requested':False,'unknown_replay_allowed':False}
    if transport is not None:
        report['transport_failure']=transport
    return report


def run(output):
    output=Path(output);php=source();results={}
    for case in CASES:
        try:
            value=validate_case(base.ssh_php_no_mux(php,dict(SPEC,case_id=case)),case)
        except Exception as exc:
            if type(exc).__name__!='SSHBatchError':
                raise
            failure=base.transport_failure(exc)
            save(output/'failure.json',failure)
            report=build_report(results,failure['status'],failure)
            save(output/'report.json',report)
            return report
        results[case]=value;save(output/f'{case}.json',value)
        if value['status']!='completed':
            break
    all_done=len(results)==len(CASES) and all(v['status']=='completed' for v in results.values())
    status='completed' if all_done else next(v['status'] for v in results.values() if v['status']!='completed')
    report=build_report(results,status);save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=2:
        raise SystemExit('usage: anex_three_source_party_control.py OUTPUT_DIR')
    output=Path(sys.argv[1])
    try:
        report=run(output);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit:
        raise
    except Exception as exc:
        report=base.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True));raise SystemExit(1) from None


if __name__=='__main__':
    main()
