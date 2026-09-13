#!/usr/bin/env python3
"""One fresh broad Egypt three-source scenario with complete advertised Andromeda pagination."""
import json
from pathlib import Path
import re
import sys

import anex_three_source_egypt_broad as egypt

EXPERIMENT='anex_three_source_egypt_full_pages_20260913_v1'
CASES=egypt.CASES
SPEC={'experiment_id':EXPERIMENT,'country':'Egypt','date':'2026-11-12','nights':9,
      'adults':2,'child_ages':[],'meal_family':'ai','currency':'RUB'}


def _replace(text,old,new,minimum=1):
    count=text.count(old)
    if count < minimum:
        raise ValueError('egypt_full_pages_source_contract_changed')
    return text.replace(old,new)


def source():
    """Use one new scenario and let its Andromeda case establish page1 then walk advertised pages."""
    text=egypt.source()
    text=_replace(text,egypt.EXPERIMENT,EXPERIMENT)
    text=_replace(text,'2026-11-03',SPEC['date'])
    text=_replace(text,'20261103','20261112')
    text=_replace(text,"($value['nights'] ?? null) !== 8","($value['nights'] ?? null) !== 9")
    text=_replace(text,"(int)$nights !== 8","(int)$nights !== 9")
    text=_replace(text,"'nights'=>8","'nights'=>9")
    text=_replace(text,"'nightsFrom'=>8","'nightsFrom'=>9")
    text=_replace(text,"'nightsTo'=>8","'nightsTo'=>9")
    text=_replace(text,"'nights_from'=>8","'nights_from'=>9")
    text=_replace(text,"'nights_till'=>8","'nights_till'=>9")
    text=_replace(text,"'three-price-egypt-broad-20260913-v1'","'three-price-egypt-full-pages-20260913-v1'")
    text=_replace(text,"'generation'=>26091306","'generation'=>26091308")

    single="$result=anytour_andromeda_search3_run($request,$pdo,$saved,$config,$session);$offers=[];"
    paged=("$offers=[];$receivedTotal=0;$mappedTotal=0;$pagesCount=1;$pagesLoaded=0;"
           "for($pageNo=1;$pageNo<=$pagesCount&&$pageNo<=5;++$pageNo){$request['page']=$pageNo;"
           "$result=anytour_andromeda_search3_run($request,$pdo,$saved,$config,$session);++$pagesLoaded;"
           "$receivedTotal+=(int)($result['received_offers']??0);$mappedTotal+=(int)($result['mapped_offers']??0);"
           "$advertised=(int)($result['pages_count']??1);if($advertised<1||$advertised>5)throw new RuntimeException('THREE_PRICE_ANDROMEDA_PAGE_COUNT');"
           "$pagesCount=max($pagesCount,$advertised);")
    text=_replace(text,single,paged)
    return_old=("    return ['offers'=>$offers,'received_offers'=>$result['received_offers']??null,'mapped_offers'=>$result['mapped_offers']??null,'pages_count'=>$result['pages_count']??null,\n"
                "        'source_price_semantics'=>'andromeda_search_price_unverified_until_package_or_calc','fuel_field_semantics'=>'documented action=price has no separate fuel field'];")
    return_new=("    }\n    return ['offers'=>$offers,'received_offers'=>$receivedTotal,'mapped_offers'=>$mappedTotal,'pages_count'=>$pagesCount,'pages_loaded'=>$pagesLoaded,\n"
                "        'source_price_semantics'=>'andromeda_search_price_unverified_until_package_or_calc','fuel_field_semantics'=>'documented action=price has no separate fuel field'];")
    text=_replace(text,return_old,return_new)

    leaks=(egypt.EXPERIMENT,'2026-11-03','20261103',"($value['nights'] ?? null) !== 8","(int)$nights !== 8",
           "'nights'=>8","'nightsFrom'=>8","'nightsTo'=>8","'nights_from'=>8","'nights_till'=>8",
           "'three-price-egypt-broad-20260913-v1'","'generation'=>26091306",single)
    if any(value in text for value in leaks):
        raise ValueError('egypt_full_pages_old_scenario_leaked')
    required=(EXPERIMENT,"'Egypt'","'Египет'",SPEC['date'],'20261112',"'nightsFrom'=>9","'nightsTo'=>9",
              "'nights_from'=>9","'nights_till'=>9","'adults'=>2","'ADULT'=>2","'hotelIds'=>[]",
              "'three-price-egypt-full-pages-20260913-v1'","'generation'=>26091308",
              "for($pageNo=1;$pageNo<=$pagesCount&&$pageNo<=5;++$pageNo)","'pages_loaded'=>$pagesLoaded")
    if any(value not in text for value in required):
        raise ValueError('egypt_full_pages_source_incomplete')
    return text


def validate_case(value,case_id):
    common=(isinstance(value,dict) and value.get('schema_version')==1 and value.get('experiment_id')==EXPERIMENT
            and value.get('case_id')==case_id and value.get('automatic_retry') is False
            and value.get('booking_calls')==0 and value.get('broninit_calls')==0 and value.get('mapping_writes')==0)
    if not common:
        raise ValueError('egypt_full_pages_case_invalid')
    status=value.get('status')
    if status=='blocked':
        reason=value.get('reason')
        if value.get('supplier_effect')!='none' or not isinstance(reason,str) or re.fullmatch(r'(?:THREE_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}',reason) is None:
            raise ValueError('egypt_full_pages_blocked_invalid')
        return value
    if status=='unknown':
        if value.get('supplier_effect')!='unknown':
            raise ValueError('egypt_full_pages_unknown_invalid')
        return value
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' \
            or not isinstance(value.get('subject'),dict) or not isinstance(value.get('offers'),list) or len(value['offers'])>4000:
        raise ValueError('egypt_full_pages_case_invalid')
    subject=value['subject']
    required={'local_hotel_id','anex_hotel_id','andromeda_hotel_id','hotel_name','selection_basis','anex_observation_count'}
    if set(subject)!=required or subject.get('selection_basis')!='current_unique_triple_mapping_anchor_only':
        raise ValueError('egypt_full_pages_anchor_invalid')
    if case_id=='andromeda':
        details=value.get('details')
        if not isinstance(details,dict) or not isinstance(details.get('pages_count'),int) or not isinstance(details.get('pages_loaded'),int) \
                or details['pages_count']<1 or details['pages_count']>5 or details['pages_loaded']!=details['pages_count']:
            raise ValueError('egypt_full_pages_pagination_incomplete')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!=case_id or not isinstance(row.get('local_hotel_id'),int) or row['local_hotel_id']<1 \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=9 or row.get('adults')!=2 or row.get('children')!=0 \
                or row.get('meal_family')!='ai' or row.get('currency')!='RUB' or row.get('fuel_inclusion_verified') is not False \
                or row.get('final_price_verified') is not False or not isinstance(row.get('price'),str) \
                or not isinstance(row.get('room_norm'),str) or not isinstance(row.get('placement_norm'),str):
            raise ValueError('egypt_full_pages_offer_invalid')
    return value


def save(path,value):
    path=Path(path);path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix(path.suffix+'.tmp')
    tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)
    if json.loads(path.read_text())!=value:
        raise ValueError('egypt_full_pages_report_readback')


def build_report(results,status,transport=None):
    direct=results.get('anex',{}).get('details',{}) if results.get('anex',{}).get('status')=='completed' else {}
    andromeda=results.get('andromeda',{}).get('details',{}) if results.get('andromeda',{}).get('status')=='completed' else {}
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':status,'spec':SPEC,
            'case_statuses':{k:v['status'] for k,v in results.items()},'missing_cases':[case for case in CASES if case not in results],
            'comparison':egypt.broad3.comparison(results),'money_relation':egypt.money_relation(results),
            'observation_summary':{'anex_received_offers':direct.get('received_offers'),'anex_mapped_received':direct.get('mapped_received'),
                                   'anex_unmapped_received':direct.get('unmapped_received'),'andromeda_received_offers':andromeda.get('received_offers'),
                                   'andromeda_mapped_offers':andromeda.get('mapped_offers'),'andromeda_pages_count':andromeda.get('pages_count'),
                                   'andromeda_pages_loaded':andromeda.get('pages_loaded')},
            'p1_scope':'broad Egypt cross-provider coverage with complete advertised Andromeda pagination',
            'anchor_policy':'current unique triple identity is checkpoint/current-context anchor only and is not sent as a hotel filter',
            'unmapped_evidence_policy':'observation evidence only; matching remains external #1759',
            'money_policy':'search price, Tourvisor fuelCharge, AdditionalPricesDaily, package and quote/final remain separate facts',
            'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'additional_prices_calls':0},
            'supplier_replay_requested':False,'unknown_replay_allowed':False}
    if transport is not None:
        report['transport_failure']=transport
    return report


def run(output):
    output=Path(output);php=source();results={}
    for case in CASES:
        try:
            value=validate_case(egypt.broad3.base.ssh_php_no_mux(php,dict(SPEC,case_id=case)),case)
        except Exception as exc:
            if type(exc).__name__!='SSHBatchError':
                raise
            failure=egypt.broad3.base.transport_failure(exc);save(output/'failure.json',failure)
            report=build_report(results,failure['status'],failure);save(output/'report.json',report);return report
        results[case]=value;save(output/f'{case}.json',value)
        if value['status']!='completed':
            break
    all_done=len(results)==len(CASES) and all(v['status']=='completed' for v in results.values())
    status='completed' if all_done else next(v['status'] for v in results.values() if v['status']!='completed')
    report=build_report(results,status);save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=2:
        raise SystemExit('usage: anex_three_source_egypt_full_pages.py OUTPUT_DIR')
    output=Path(sys.argv[1])
    try:
        report=run(output);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if report['status']=='completed' else 1)
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
