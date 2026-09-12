#!/usr/bin/env python3
"""One bounded concrete-offer ANEX fuel binding experiment without replaying B2B evidence."""
from decimal import Decimal, InvalidOperation
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport

EXPERIMENT='anex_concrete_fuel_binding_20260913_v2'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
      'child_ages':[],'meal_family':'ai','currency':'RUB'}
PRESERVED_PROGRAM='2637'
PRESERVED_NATIVE_CURRENCY='3'
PRESERVED_RUN=34716809979
PRESERVED_ARTIFACT=10304744621
PRESERVED_EXPERIMENT='anex_additional_program2637_20260912_v1'


def php_body(path: Path, strict=True) -> str:
    text=path.read_text()
    if not text.startswith('<?php\n'):
        raise ValueError('php_header_invalid')
    body=text[6:]
    marker='declare(strict_types=1);\n'
    if strict:
        if not body.startswith(marker): raise ValueError('php_strict_header_invalid')
        body=body[len(marker):]
    elif body.startswith(marker):
        body=body[len(marker):]
    return body


def source() -> str:
    root=Path(__file__).resolve().parents[2]
    diag=root/'scripts'/'diagnostics'
    paired=php_body(diag/'anex_search3_paired_runner.php',False)
    client=php_body(root/'app'/'integrations'/'anex-client.php')
    normalizer=php_body(root/'app'/'integrations'/'anex-normalizer.php')
    search=php_body(root/'app'/'integrations'/'anex-search.php')
    search='\n'.join(line for line in search.splitlines() if not line.startswith("require_once __DIR__"))+'\n'
    registry=php_body(root/'app'/'integrations'/'anex-search-mapping-registry.php')
    binding=php_body(diag/'anex_concrete_fuel_binding.php')
    return ("declare(strict_types=1);\n"
            "define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"
            "define('ANYTOUR_ANEX_CONCRETE_FUEL_LIBRARY_ONLY', true);\n"
            +paired+'\n'+client+'\n'+normalizer+'\n'+search+'\n'+registry+'\n'+binding
            +'\n$report=anex_concrete_fuel_main(); echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n"; exit(($report["status"]??null)==="completed"?0:1);')


def decimal(value):
    try:
        if isinstance(value,(str,int,float)) and not isinstance(value,bool):
            result=Decimal(str(value))
            if result.is_finite() and result >= 0: return result
    except InvalidOperation:
        pass
    return None


def read_preserved(path: Path):
    value=json.loads(path.read_text())
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('status')!='completed':
        raise ValueError('preserved_program2637_invalid')
    if value.get('experiment_id')!=PRESERVED_EXPERIMENT or value.get('supplier_replay_allowed') is not False:
        raise ValueError('preserved_program2637_identity_invalid')
    spec=value.get('spec') or {}
    if spec.get('country')!='Turkey' or spec.get('date')!='2026-10-12' or spec.get('nights')!=7 \
       or spec.get('adults')!=2 or spec.get('child_ages')!=[]:
        raise ValueError('preserved_program2637_scope_invalid')
    evidence=value.get('evidence') or {}
    supplement=decimal(evidence.get('candidate_two_adult_rate_sum'))
    adult=decimal(evidence.get('additional_price_converted_adult'))
    retained=value.get('retained_search_source') or {}
    if supplement is None or adult is None or supplement!=adult*Decimal(2):
        raise ValueError('preserved_program2637_money_invalid')
    if retained.get('source_operation_still_no_replay') is not True:
        raise ValueError('preserved_program2637_replay_invalid')
    if (value.get('new_requests') or {}).get('additional_prices_requests')!=1:
        raise ValueError('preserved_program2637_request_invalid')
    return {'supplement':supplement,'adult':adult,'report':value}


def validate(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT:
        raise ValueError('concrete_fuel_result_invalid')
    if value.get('automatic_retry') is not False or value.get('supplier_replay_allowed') is not False:
        raise ValueError('concrete_fuel_replay_invalid')
    for field in ('booking_calls','broninit_calls','mapping_writes','andromeda_requests','additional_prices_requests'):
        if value.get(field)!=0: raise ValueError('concrete_fuel_effect_invalid')
    if value.get('status') not in ('completed','unknown','blocked'):
        raise ValueError('concrete_fuel_status_invalid')
    if value.get('status')=='completed':
        if value.get('supplier_effect')!='read_only_search_expand_flights_tv_completed':
            raise ValueError('concrete_fuel_completion_invalid')
        if not isinstance(value.get('group_minimum'),dict) or not isinstance(value.get('selected_concrete'),dict):
            raise ValueError('concrete_fuel_offer_invalid')
        if value['selected_concrete'].get('kind')!='concrete': raise ValueError('concrete_fuel_not_concrete')
        if value['selected_concrete'].get('supplier_tour_program_id')!=PRESERVED_PROGRAM:
            raise ValueError('concrete_fuel_program_mismatch')
        if value['selected_concrete'].get('supplier_currency_id')!=PRESERVED_NATIVE_CURRENCY:
            raise ValueError('concrete_fuel_currency_mismatch')
        if not isinstance(value.get('concrete_offers'),list) or not value['concrete_offers']:
            raise ValueError('concrete_fuel_concrete_empty')
        if not isinstance(value.get('anex_flights'),dict) or not isinstance(value.get('tourvisor'),dict):
            raise ValueError('concrete_fuel_evidence_invalid')
        if not (1 <= int(value.get('anex_requests',0)) <= 12): raise ValueError('concrete_fuel_anex_budget')
        if not (1 <= int(value.get('tourvisor_requests',0)) <= 12): raise ValueError('concrete_fuel_tv_budget')
    return value


def analyze(value,preserved):
    supplement=preserved['supplement'];adult=preserved['adult']
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':value.get('status'),
            'spec':SPEC,'supplier_replay_allowed':False,'production_price_arithmetic_applied':False,
            'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'andromeda_requests':0,
            'preserved_additional_prices':{
                'experiment_id':PRESERVED_EXPERIMENT,'program':PRESERVED_PROGRAM,'native_currency_id':PRESERVED_NATIVE_CURRENCY,
                'converted_adult':str(adult),'candidate_two_adult_supplement':str(supplement),
                'source_run':PRESERVED_RUN,'source_artifact':PRESERVED_ARTIFACT,'supplier_replay_performed':False},
            'evidence':None}
    if value.get('status')!='completed':
        report['reason']=value.get('reason'); return report
    group=value['group_minimum']; selected=value['selected_concrete']; tv=(value.get('tourvisor') or {}).get('offers') or []
    concrete_price=decimal(selected.get('price')); group_price=decimal(group.get('price'))
    matches=[]; group_matches=[]
    for candidate in tv:
        if not isinstance(candidate,dict) or candidate.get('room_norm')!=selected.get('room_norm'): continue
        selected_place=selected.get('placement_norm') or ''
        candidate_place=candidate.get('placement_norm') or ''
        if selected_place and candidate_place and candidate_place!=selected_place: continue
        price=decimal(candidate.get('price')); fuel=decimal(candidate.get('fuel_charge'))
        if price is None or fuel is None: continue
        fuel_gap=abs(fuel-supplement)
        concrete_gap=abs(price-(concrete_price+supplement)) if concrete_price is not None else None
        group_gap=abs(price-(group_price+supplement)) if group_price is not None else None
        observed={'tourvisor_price':str(price),'tourvisor_fuel':str(fuel),'fuel_gap':str(fuel_gap),
                  'concrete_total_gap':str(concrete_gap) if concrete_gap is not None else None,
                  'group_total_gap':str(group_gap) if group_gap is not None else None,
                  'room_norm':candidate.get('room_norm'),'placement_norm':candidate.get('placement_norm')}
        if fuel_gap <= Decimal('1') and concrete_gap is not None and concrete_gap <= Decimal('1'): matches.append(observed)
        if fuel_gap <= Decimal('1') and group_gap is not None and group_gap <= Decimal('1'): group_matches.append(observed)
    airports=[]
    for route in (value.get('anex_flights') or {}).get('routes') or []:
        for option in route.get('options') or []:
            pair=(option.get('departure_airport'),option.get('arrival_airport'))
            if all(isinstance(x,str) and x for x in pair) and list(pair) not in airports: airports.append(list(pair))
    report['requests']={'anex':value.get('anex_requests'),'tourvisor':value.get('tourvisor_requests'),
                        'additional_prices':0,'andromeda':0}
    report['evidence']={
        'state':'observed','group_kind':group.get('kind'),'group_minimum_price':group.get('price'),
        'selected_concrete_price':selected.get('price'),'selected_program':selected.get('supplier_tour_program_id'),
        'selected_native_currency_id':selected.get('supplier_currency_id'),'selected_room_norm':selected.get('room_norm'),
        'selected_placement_norm':selected.get('placement_norm'),
        'preserved_additional_converted_adult':str(adult),'candidate_two_adult_supplement':str(supplement),
        'concrete_total_candidate':str(concrete_price+supplement) if concrete_price is not None else None,
        'group_total_candidate':str(group_price+supplement) if group_price is not None else None,
        'concrete_tv_matches':matches,'group_tv_matches':group_matches,
        'unique_concrete_tv_match':len(matches)==1,'group_minimum_supplement_match':len(group_matches)>0,
        'anex_airport_pairs':airports,'anex_flight_selected':False,
        'tourvisor_flight_refresh_requested':False,'supplier_package_identity_verified':False,
        'application_rule_verified_for_search':False,
        'note':'Program2637 supplement is read from sealed completed evidence; no B2B replay and no amount is applied to production search price.'}
    return report


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True)
    data=json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n'
    tmp=path.with_suffix(path.suffix+'.tmp');tmp.write_text(data);tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('concrete_fuel_report_readback')


def run(output,preserved_path):
    preserved=read_preserved(preserved_path)
    value=transport.ssh_php_no_mux(source(),SPEC,maximum_bytes=4000000)
    value=validate(value);save(output/'result.json',value);report=analyze(value,preserved);save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=3: raise SystemExit('usage: anex_concrete_fuel_binding.py OUTPUT_DIR PRESERVED_PROGRAM2637_REPORT')
    output=Path(sys.argv[1]);preserved_path=Path(sys.argv[2])
    try:
        report=run(output,preserved_path);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=transport.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True));raise SystemExit(1) from None

if __name__=='__main__': main()
