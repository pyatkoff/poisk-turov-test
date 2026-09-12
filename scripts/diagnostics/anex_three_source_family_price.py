#!/usr/bin/env python3
"""One bounded P1 family scenario using the existing three-source search transport."""
from decimal import Decimal, InvalidOperation
import json
from pathlib import Path
import re
import sys

import anex_search3_three_source_price as base

EXPERIMENT='anex_three_source_family_price_20260913_v1'
CASES=base.CASES
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-29','nights':9,
      'adults':2,'child_ages':[7],'meal_family':'ai','currency':'RUB'}


def _replace(text, old, new, minimum=1):
    count=text.count(old)
    if count < minimum:
        raise ValueError('family_source_contract_changed')
    return text.replace(old,new)


def source():
    """Adapt only the three-source section; paired transport/helper code stays byte-equivalent."""
    text=base.source()
    marker="const ANEX_THREE_PRICE_EXPERIMENT = 'anex_three_source_price_20260911_v2';"
    if text.count(marker)!=1: raise ValueError('family_source_marker_changed')
    prefix,suffix=text.split(marker,1)
    suffix=marker+suffix
    suffix=_replace(suffix,"anex_three_source_price_20260911_v2",EXPERIMENT)
    suffix=_replace(suffix,"2026-09-27",SPEC['date'])
    suffix=_replace(suffix,"20260927","20261029")
    suffix=_replace(suffix,"($value['nights'] ?? null) !== 7","($value['nights'] ?? null) !== 9")
    suffix=_replace(suffix,"($value['child_ages'] ?? null) !== []","($value['child_ages'] ?? null) !== [7]")
    suffix=_replace(suffix,"(int)$nights !== 7","(int)$nights !== 9")
    suffix=_replace(suffix,"(int)$children !== 0","(int)$children !== 1")
    suffix=_replace(suffix,"'nights'=>7","'nights'=>9")
    suffix=_replace(suffix,"'children'=>0,'child_ages'=>[]","'children'=>1,'child_ages'=>[7]")
    suffix=_replace(suffix,"'nightsFrom'=>7,'nightsTo'=>7","'nightsFrom'=>9,'nightsTo'=>9")
    suffix=_replace(suffix,"'nights_from'=>7,'nights_till'=>7","'nights_from'=>9,'nights_till'=>9")
    suffix=_replace(suffix,"'childs'=>[]","'childs'=>[7]")
    suffix=_replace(suffix,"'CHILD'=>0","'CHILD'=>1,'AGES'=>'7'")
    suffix=_replace(suffix,"'children'=>0","'children'=>1")
    suffix=_replace(suffix,"'three-price-20260911-v2'","'three-price-family-20260913-v1'")
    suffix=_replace(suffix,"'generation'=>26091102","'generation'=>26091301")
    leaks=("2026-09-27","20260927","'nights'=>7","'nightsFrom'=>7","'nights_from'=>7","'children'=>0","'childs'=>[]","'CHILD'=>0")
    if any(value in suffix for value in leaks): raise ValueError('family_source_old_scenario_leaked')
    required=(EXPERIMENT,SPEC['date'],"'childs'=>[7]","'AGES'=>'7'","'nightsFrom'=>9,'nightsTo'=>9","'nights_from'=>9,'nights_till'=>9","'children'=>1")
    if any(value not in suffix for value in required): raise ValueError('family_source_incomplete')
    return prefix+suffix


def validate_case(value,case_id):
    common=(isinstance(value,dict) and value.get('schema_version')==1 and value.get('experiment_id')==EXPERIMENT
            and value.get('case_id')==case_id and value.get('automatic_retry') is False
            and value.get('booking_calls')==0 and value.get('broninit_calls')==0 and value.get('mapping_writes')==0)
    if not common: raise ValueError('family_case_invalid')
    status=value.get('status')
    if status=='blocked':
        reason=value.get('reason')
        if value.get('supplier_effect')!='none' or not isinstance(reason,str) or re.fullmatch(r'(?:THREE_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}',reason) is None:
            raise ValueError('family_blocked_invalid')
        return value
    if status=='unknown':
        if value.get('supplier_effect')!='unknown': raise ValueError('family_unknown_invalid')
        return value
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' \
            or not isinstance(value.get('subject'),dict) or not isinstance(value.get('offers'),list) or len(value['offers'])>2000:
        raise ValueError('family_case_invalid')
    subject=value['subject']
    required={'local_hotel_id','anex_hotel_id','andromeda_hotel_id','hotel_name','selection_basis','anex_observation_count'}
    if set(subject)!=required or subject.get('selection_basis')!='current_unique_triple_mapping': raise ValueError('family_subject_invalid')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!=case_id or row.get('local_hotel_id')!=subject['local_hotel_id'] \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=9 or row.get('adults')!=2 or row.get('children')!=1 \
                or row.get('meal_family')!='ai' or row.get('currency')!='RUB' or row.get('fuel_inclusion_verified') is not False \
                or row.get('final_price_verified') is not False or not isinstance(row.get('price'),str) \
                or not isinstance(row.get('room_norm'),str) or not isinstance(row.get('placement_norm'),str):
            raise ValueError('family_offer_invalid')
    return value


def _money(value):
    if value is None: return None
    try: amount=Decimal(str(value))
    except (InvalidOperation,ValueError): return None
    return amount if amount.is_finite() and amount>=0 else None


def resolver_coverage(results):
    """Evidence-only mirror of #2270 eligibility; never authorizes arithmetic."""
    anex=results.get('anex',{}).get('offers',[]) if results.get('anex',{}).get('status')=='completed' else []
    tv=results.get('tourvisor',{}).get('offers',[]) if results.get('tourvisor',{}).get('status')=='completed' else []
    grouped={}; exact_pairs=0
    for a in anex:
        for t in tv:
            context=('local_hotel_id','date','nights','adults','children','meal_family','room_norm','placement_norm','currency')
            if any(a.get(k)!=t.get(k) for k in context): continue
            ap=_money(a.get('price')); tp=_money(t.get('price')); fuel=_money(t.get('fuel_charge'))
            if ap is None or tp is None or fuel is None or tp-fuel!=ap: continue
            exact_pairs+=1
            key='|'.join(str(a.get(k,'')) for k in context)+(f'|base={a.get("price")}')
            grouped.setdefault(key,set()).add(str(t.get('fuel_charge')))
    resolved={k:next(iter(v)) for k,v in grouped.items() if len(v)==1}
    ambiguous={k:sorted(v) for k,v in grouped.items() if len(v)>1}
    return {'policy':'exact same context and TV total - TV fuel == direct ANEX base; one distinct fuel only',
            'exact_pair_count':exact_pairs,'candidate_base_count':len(grouped),'resolved_base_count':len(resolved),
            'ambiguous_base_count':len(ambiguous),'resolved_fuels':resolved,'ambiguous_fuels':ambiguous,
            'runtime_arithmetic_authorized':False,'identical_supplier_package_verified':False}


def run(output):
    php=source();results={}
    for case in CASES:
        value=validate_case(base.ssh_php_no_mux(php,dict(SPEC,case_id=case)),case)
        results[case]=value;base.save(output/f'{case}.json',value)
        if value['status']!='completed': break
    all_done=len(results)==len(CASES) and all(v['status']=='completed' for v in results.values())
    report={'schema_version':1,'experiment_id':EXPERIMENT,
            'status':'completed' if all_done else next(v['status'] for v in results.values() if v['status']!='completed'),
            'spec':SPEC,'case_statuses':{k:v['status'] for k,v in results.items()},'comparison':base.compare(results),
            'fuel_resolver_coverage':resolver_coverage(results),
            'p1_scope':'materially different family search matrix; current unique triple-mapped subject only',
            'unmapped_evidence_policy':'none generated by this hotel-scoped scenario; matching remains external #1759',
            'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'additional_prices_calls':0},
            'unknown_replay_allowed':False}
    base.save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_three_source_family_price.py OUTPUT_DIR')
    output=Path(sys.argv[1])
    try:
        report=run(output);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=base.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other','automatic_retry':False,'supplier_replay_requested':False}
        try: base.save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True));raise SystemExit(1) from None

if __name__=='__main__': main()
