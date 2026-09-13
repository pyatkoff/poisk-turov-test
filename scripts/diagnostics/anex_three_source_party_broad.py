#!/usr/bin/env python3
"""One bounded broad three-adult provider comparator using the reviewed selected-hotel transport."""
import json
from pathlib import Path
import re
import sys

import anex_search3_three_source_price as base
import anex_three_source_family_price as party

EXPERIMENT='anex_three_source_party_broad_20260913_v1'
CASES=party.CASES
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-20','nights':8,
      'adults':3,'child_ages':[],'meal_family':'ai','currency':'RUB'}


def _replace(text,old,new,minimum=1):
    count=text.count(old)
    if count < minimum:
        raise ValueError('party_broad_source_contract_changed')
    return text.replace(old,new)


def source():
    """Broaden only hotel scope while preserving provider transport, party/date/stay and no-replay runtime."""
    text=party.source()
    text=_replace(text,party.EXPERIMENT,EXPERIMENT)
    text=_replace(text,"'three-price-party-20260913-v2'","'three-price-party-broad-20260913-v1'")
    text=_replace(text,"'generation'=>26091302","'generation'=>26091304")
    text=_replace(text,"'selection_basis'=>'current_unique_triple_mapping'","'selection_basis'=>'current_unique_triple_mapping_anchor_only'")

    text=_replace(text,
        ",'onlyCharter'=>false,'onlyDirect'=>false,\n        'hotelIds'=>[(int)$subject['local_hotel_id']]];",
        ",'onlyCharter'=>false,'onlyDirect'=>false];")
    text=_replace(text,
        "foreach($groups as $hotel){if(!is_array($hotel)||(int)($hotel['id']??0)!==(int)$subject['local_hotel_id'])continue;",
        "foreach($groups as $hotel){if(!is_array($hotel))continue;$localId=(int)($hotel['id']??0);if($localId<1)continue;")
    text=_replace(text,
        "anex_three_price_offer('tourvisor',(int)$subject['local_hotel_id'],(int)$subject['local_hotel_id'],",
        "anex_three_price_offer('tourvisor',$localId,$localId,")

    text=_replace(text,
        ",'adults'=>3,'children'=>0,'child_ages'=>[],\n        'hotel_ids'=>[(string)$subject['anex_hotel_id']]];",
        ",'adults'=>3,'children'=>0,'child_ages'=>[]];")
    text=_replace(text,
        "        if(($offer['hotel']['local_id']??null)!==(int)$subject['local_hotel_id'])continue;\n",
        "        $localId=$offer['hotel']['local_id']??null;if(!is_int($localId)||$localId<1)continue;\n")
    text=_replace(text,
        "anex_three_price_offer('anex',(int)$subject['local_hotel_id'],(string)$subject['anex_hotel_id'],",
        "anex_three_price_offer('anex',$localId,(string)($offer['hotel']['external_id']??''),")
    text=_replace(text,
        "\n    return ['offers'=>$offers,'observation'=>$obs,'requests'=>$client->requestsMade(),",
        "\n    $unmapped=0;foreach($result['offers'] as $rawOffer){if(!is_int($rawOffer['hotel']['local_id']??null))++$unmapped;}\n"
        "    return ['offers'=>$offers,'observation'=>$obs,'requests'=>$client->requestsMade(),'received_offers'=>count($result['offers']),'mapped_received'=>count($result['offers'])-$unmapped,'unmapped_received'=>$unmapped,")

    text=_replace(text,
        "'currency'=>'RUB','meal'=>7,'onlyCharter'=>false,'onlyDirect'=>false,'hotelIds'=>[(int)$subject['local_hotel_id']],\n",
        "'currency'=>'RUB','meal'=>7,'onlyCharter'=>false,'onlyDirect'=>false,'hotelIds'=>[],\n")
    text=_replace(text,
        "foreach($result['hotels']??[] as $hotel){if((int)($hotel['local_id']??0)!==(int)$subject['local_hotel_id'])continue;\n",
        "foreach($result['hotels']??[] as $hotel){$localId=(int)($hotel['local_id']??0);if($localId<1)continue;\n")
    text=_replace(text,
        "anex_three_price_offer('andromeda',(int)$subject['local_hotel_id'],(string)$subject['andromeda_hotel_id'],",
        "anex_three_price_offer('andromeda',$localId,(string)$localId,")

    forbidden=(party.EXPERIMENT,"'three-price-party-20260913-v2'","'generation'=>26091302",
               "'selection_basis'=>'current_unique_triple_mapping'",
               "'hotelIds'=>[(int)$subject['local_hotel_id']]","'hotel_ids'=>[(string)$subject['anex_hotel_id']]",
               "(int)($hotel['id']??0)!==(int)$subject['local_hotel_id']",
               "($offer['hotel']['local_id']??null)!==(int)$subject['local_hotel_id'])",
               "(int)$subject['local_hotel_id'],(int)$subject['local_hotel_id']",
               "(int)$subject['local_hotel_id'],(string)$subject['anex_hotel_id']",
               "(int)$subject['local_hotel_id'],(string)$subject['andromeda_hotel_id']")
    if any(value in text for value in forbidden):
        raise ValueError('party_broad_selected_filter_leaked')
    required=(EXPERIMENT,SPEC['date'],"'nightsFrom'=>8","'nightsTo'=>8","'adults'=>3","'ADULT'=>3",
              "'hotelIds'=>[]","'received_offers'=>count($result['offers'])","'unmapped_received'=>$unmapped",
              "current_unique_triple_mapping_anchor_only","three-price-party-broad-20260913-v1")
    if any(value not in text for value in required):
        raise ValueError('party_broad_source_incomplete')
    return text


def validate_case(value,case_id):
    common=(isinstance(value,dict) and value.get('schema_version')==1 and value.get('experiment_id')==EXPERIMENT
            and value.get('case_id')==case_id and value.get('automatic_retry') is False
            and value.get('booking_calls')==0 and value.get('broninit_calls')==0 and value.get('mapping_writes')==0)
    if not common:
        raise ValueError('party_broad_case_invalid')
    status=value.get('status')
    if status=='blocked':
        reason=value.get('reason')
        if value.get('supplier_effect')!='none' or not isinstance(reason,str) or re.fullmatch(r'(?:THREE_PRICE|ANEX|ANDROMEDA)_[A-Z0-9_]{1,80}',reason) is None:
            raise ValueError('party_broad_blocked_invalid')
        return value
    if status=='unknown':
        if value.get('supplier_effect')!='unknown':
            raise ValueError('party_broad_unknown_invalid')
        return value
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' \
            or not isinstance(value.get('subject'),dict) or not isinstance(value.get('offers'),list) or len(value['offers'])>2000:
        raise ValueError('party_broad_case_invalid')
    subject=value['subject']
    required={'local_hotel_id','anex_hotel_id','andromeda_hotel_id','hotel_name','selection_basis','anex_observation_count'}
    if set(subject)!=required or subject.get('selection_basis')!='current_unique_triple_mapping_anchor_only':
        raise ValueError('party_broad_anchor_invalid')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!=case_id or not isinstance(row.get('local_hotel_id'),int) or row['local_hotel_id']<1 \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=8 or row.get('adults')!=3 or row.get('children')!=0 \
                or row.get('meal_family')!='ai' or row.get('currency')!='RUB' or row.get('fuel_inclusion_verified') is not False \
                or row.get('final_price_verified') is not False or not isinstance(row.get('price'),str) \
                or not isinstance(row.get('room_norm'),str) or not isinstance(row.get('placement_norm'),str):
            raise ValueError('party_broad_offer_invalid')
    return value


def key(row):
    return (row['local_hotel_id'],row['date'],row['nights'],row['adults'],row['children'],row['meal_family'],row['room_norm'])


def comparison(results):
    index={case:{} for case in CASES}
    for case,value in results.items():
        if value.get('status')!='completed':
            continue
        for row in value['offers']:
            index[case].setdefault(key(row),[]).append(row)
    sets={case:set(index[case]) for case in CASES}
    triple=sets['anex']&sets['andromeda']&sets['tourvisor']
    pair_sets={'anex_andromeda':sets['anex']&sets['andromeda'],
               'anex_tourvisor':sets['anex']&sets['tourvisor'],
               'andromeda_tourvisor':sets['andromeda']&sets['tourvisor']}
    examples=[]
    for item in sorted(triple,key=str)[:30]:
        examples.append({'basis':'same_current_local_hotel_date_party_ai_and_exact_room',
                         'identical_supplier_package_verified':False,'fuel_inclusion_verified':False,
                         'key':{'local_hotel_id':item[0],'date':item[1],'nights':item[2],'adults':item[3],
                                'children':item[4],'meal_family':item[5],'room_norm':item[6]},
                         'offers':{case:index[case][item] for case in CASES}})
    counts={case:{'offers':sum(len(rows) for rows in index[case].values()),'unique_tuples':len(sets[case]),
                  'fuel_reported_offers':sum(1 for rows in index[case].values() for row in rows if row.get('fuel_charge') is not None)}
            for case in CASES}
    return {'provider_counts':counts,'triple_tuple_count':len(triple),
            'pair_tuple_counts':{name:len(items) for name,items in pair_sets.items()},'triple_examples':examples,
            'interpretation':'broad display-level coverage comparison only; price equality never proves package identity and fuel is never added automatically'}


def save(path,value):
    path=Path(path);path.parent.mkdir(parents=True,exist_ok=True);tmp=path.with_suffix(path.suffix+'.tmp')
    tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)
    if json.loads(path.read_text())!=value:
        raise ValueError('party_broad_report_readback')


def build_report(results,status,transport=None):
    direct=results.get('anex',{}).get('details',{}) if results.get('anex',{}).get('status')=='completed' else {}
    andromeda=results.get('andromeda',{}).get('details',{}) if results.get('andromeda',{}).get('status')=='completed' else {}
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':status,'spec':SPEC,
            'case_statuses':{k:v['status'] for k,v in results.items()},'missing_cases':[case for case in CASES if case not in results],
            'comparison':comparison(results),
            'observation_summary':{'anex_received_offers':direct.get('received_offers'),'anex_mapped_received':direct.get('mapped_received'),
                                   'anex_unmapped_received':direct.get('unmapped_received'),'andromeda_received_offers':andromeda.get('received_offers'),
                                   'andromeda_mapped_offers':andromeda.get('mapped_offers')},
            'p1_scope':'broad/no-hotel-filter three-adult provider coverage comparator for the selected-gap date/stay/meal',
            'anchor_policy':'current unique triple identity is checkpoint/current-context anchor only and is not sent as a hotel filter',
            'unmapped_evidence_policy':'observation evidence only; matching remains external #1759',
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
            failure=base.transport_failure(exc);save(output/'failure.json',failure)
            report=build_report(results,failure['status'],failure);save(output/'report.json',report);return report
        results[case]=value;save(output/f'{case}.json',value)
        if value['status']!='completed':
            break
    all_done=len(results)==len(CASES) and all(v['status']=='completed' for v in results.values())
    status='completed' if all_done else next(v['status'] for v in results.values() if v['status']!='completed')
    report=build_report(results,status);save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=2:
        raise SystemExit('usage: anex_three_source_party_broad.py OUTPUT_DIR')
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
