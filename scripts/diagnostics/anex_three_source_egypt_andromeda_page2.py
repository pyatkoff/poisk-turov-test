#!/usr/bin/env python3
"""Bounded Andromeda page-2 continuation for the completed broad Egypt criteria."""
import json
from pathlib import Path
import re
import sys

import anex_three_source_egypt_broad as egypt

EXPERIMENT='anex_three_source_egypt_andromeda_page2_20260913_v1'
SPEC={'experiment_id':EXPERIMENT,'country':'Egypt','date':'2026-11-03','nights':8,
      'adults':2,'child_ages':[],'meal_family':'ai','currency':'RUB'}


def _replace(text,old,new,minimum=1):
    if text.count(old) < minimum:
        raise ValueError('egypt_page2_source_contract_changed')
    return text.replace(old,new)


def source():
    """Reuse exact broad Egypt transport, changing only experiment and Andromeda page operation."""
    text=egypt.source()
    text=_replace(text,egypt.EXPERIMENT,EXPERIMENT)
    text=_replace(text,"'three-price-egypt-broad-20260913-v1'","'three-price-egypt-broad-page2-20260913-v1'")
    text=_replace(text,"'generation'=>26091306,'page'=>1","'generation'=>26091307,'page'=>2")
    if egypt.EXPERIMENT in text or "'three-price-egypt-broad-20260913-v1'" in text or "'generation'=>26091306,'page'=>1" in text:
        raise ValueError('egypt_page2_old_operation_leaked')
    required=(EXPERIMENT,"'generation'=>26091307,'page'=>2","'three-price-egypt-broad-page2-20260913-v1'",
              "'Egypt'","'Египет'",SPEC['date'],"'hotelIds'=>[]","current_unique_triple_mapping_anchor_only")
    if any(value not in text for value in required):
        raise ValueError('egypt_page2_source_incomplete')
    return text


def validate_case(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT \
            or value.get('case_id')!='andromeda' or value.get('automatic_retry') is not False \
            or value.get('booking_calls')!=0 or value.get('broninit_calls')!=0 or value.get('mapping_writes')!=0:
        raise ValueError('egypt_page2_case_invalid')
    status=value.get('status')
    if status=='blocked':
        reason=value.get('reason')
        if value.get('supplier_effect')!='none' or not isinstance(reason,str) or re.fullmatch(r'(?:THREE_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}',reason) is None:
            raise ValueError('egypt_page2_blocked_invalid')
        return value
    if status=='unknown':
        if value.get('supplier_effect')!='unknown':
            raise ValueError('egypt_page2_unknown_invalid')
        return value
    details=value.get('details')
    subject=value.get('subject')
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' or not isinstance(details,dict) \
            or details.get('pages_count')!=2 or not isinstance(subject,dict) \
            or subject.get('selection_basis')!='current_unique_triple_mapping_anchor_only' \
            or not isinstance(value.get('offers'),list) or len(value['offers'])>1000:
        raise ValueError('egypt_page2_case_invalid')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!='andromeda' or not isinstance(row.get('local_hotel_id'),int) or row['local_hotel_id']<1 \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=8 or row.get('adults')!=2 or row.get('children')!=0 \
                or row.get('meal_family')!='ai' or row.get('currency')!='RUB' or row.get('fuel_charge') is not None \
                or row.get('fuel_inclusion_verified') is not False or row.get('final_price_verified') is not False:
            raise ValueError('egypt_page2_offer_invalid')
    return value


def save(path,value):
    path=Path(path);path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix(path.suffix+'.tmp')
    tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)
    if json.loads(path.read_text())!=value:
        raise ValueError('egypt_page2_report_readback')


def run(output):
    output=Path(output)
    try:
        value=validate_case(egypt.broad3.base.ssh_php_no_mux(source(),dict(SPEC,case_id='andromeda')))
    except Exception as exc:
        if type(exc).__name__!='SSHBatchError':
            raise
        failure=egypt.broad3.base.transport_failure(exc);save(output/'failure.json',failure);return failure
    save(output/'andromeda.json',value)
    details=value.get('details',{}) if isinstance(value.get('details'),dict) else {}
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':value['status'],'spec':SPEC,'page':2,
            'offer_count':len(value.get('offers',[])),'received_offers':details.get('received_offers'),
            'mapped_offers':details.get('mapped_offers'),'pages_count':details.get('pages_count'),
            'local_hotel_ids':sorted({row['local_hotel_id'] for row in value.get('offers',[]) if isinstance(row.get('local_hotel_id'),int)}),
            'comparison_input':'combine only with sealed Egypt broad artifact 10325467572; do not replay its provider cases',
            'observation_policy':'Andromeda runtime raw page observation only; matching remains external #1759',
            'effects':{'direct_anex_calls':0,'tourvisor_calls':0,'additional_prices_calls':0,'booking_calls':0,'broninit_calls':0,'mapping_writes':0},
            'supplier_replay_requested':False,'unknown_replay_allowed':False}
    save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=2:
        raise SystemExit('usage: anex_three_source_egypt_andromeda_page2.py OUTPUT_DIR')
    output=Path(sys.argv[1])
    try:
        report=run(output);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if report.get('status')=='completed' else 1)
    except SystemExit:
        raise
    except Exception as exc:
        report=egypt.broad3.base.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True));raise SystemExit(1) from None


if __name__=='__main__':
    main()
