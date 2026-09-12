#!/usr/bin/env python3
"""Bind one concrete ANEX offer to retained AdditionalPricesDaily evidence without replay."""
from decimal import Decimal, InvalidOperation
import hashlib
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport

EXPERIMENT='anex_concrete_fuel_binding_20260912_v2'
RETAINED_SHA256='940a8677c0084a99e0c1f36baaa9e7301d1afef65d89c06a39c64f8799e11163'
BASE_SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
           'child_ages':[],'meal_family':'ai','currency':'RUB'}


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


def retained_additional(path: Path):
    raw=path.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=RETAINED_SHA256:
        raise ValueError('retained_additional_digest_invalid')
    value=json.loads(raw)
    if not isinstance(value,dict) or value.get('schema_version')!=1 \
       or value.get('experiment_id')!='anex_additional_program2637_20260912_v1' \
       or value.get('status')!='completed' or value.get('supplier_replay_allowed') is not False \
       or value.get('additional_prices_requests')!=1 or value.get('direct_anex_requests')!=0 \
       or value.get('tourvisor_requests')!=0 or value.get('booking_calls')!=0 \
       or value.get('broninit_calls')!=0 or value.get('mapping_writes')!=0:
        raise ValueError('retained_additional_contract_invalid')
    context=value.get('additional_request_context')
    expected={'currency':3,'dateBeg':'2026-10-12','nights':7,'page':1,'pageSize':10,'tour':2637}
    if context!=expected:
        raise ValueError('retained_additional_context_invalid')
    payload=value.get('additional_prices')
    if not isinstance(payload,dict) or payload.get('totalCount')!=1 or payload.get('totalPages')!=1:
        raise ValueError('retained_additional_payload_invalid')
    rows=payload.get('data')
    if not isinstance(rows,list) or len(rows)!=1 or not isinstance(rows[0],dict):
        raise ValueError('retained_additional_payload_invalid')
    row=rows[0]
    if row.get('tour')!=2637 or row.get('currency')!=3 or row.get('nights')!=7 \
       or row.get('dateBeg')!='2026-10-12T00:00:00':
        raise ValueError('retained_additional_row_context_invalid')
    for field in ('price_adult','price_chd','cashrate','price_converted_adult','price_converted_chd'):
        if decimal(row.get(field)) is None:
            raise ValueError('retained_additional_money_invalid')
    return {'schema_version':1,'experiment_id':value['experiment_id'],'status':'completed',
            'supplier_replay_allowed':False,'request':context,'payload':payload,
            'result_sha256':RETAINED_SHA256,'artifact_id':10304744621,'run_id':34716809979}


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
        if value.get('supplier_effect')!='read_only_search_expand_flights_tv_with_retained_additional_completed':
            raise ValueError('concrete_fuel_completion_invalid')
        if value.get('additional_source')!='retained_completed_operation':
            raise ValueError('concrete_fuel_retained_source_invalid')
        if not isinstance(value.get('group_minimum'),dict) or not isinstance(value.get('selected_concrete'),dict):
            raise ValueError('concrete_fuel_offer_invalid')
        if value['selected_concrete'].get('kind')!='concrete': raise ValueError('concrete_fuel_not_concrete')
        if not isinstance(value.get('concrete_offers'),list) or not value['concrete_offers']:
            raise ValueError('concrete_fuel_concrete_empty')
        if not isinstance(value.get('anex_flights'),dict) or not isinstance(value.get('tourvisor'),dict):
            raise ValueError('concrete_fuel_evidence_invalid')
        if not isinstance(value.get('additional_prices'),dict) or not isinstance(value['additional_prices'].get('data'),list):
            raise ValueError('concrete_fuel_additional_invalid')
        if not (1 <= int(value.get('anex_requests',0)) <= 12): raise ValueError('concrete_fuel_anex_budget')
        if not (1 <= int(value.get('tourvisor_requests',0)) <= 12): raise ValueError('concrete_fuel_tv_budget')
    return value


def analyze(value):
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':value.get('status'),
            'spec':BASE_SPEC,'supplier_replay_allowed':False,'production_price_arithmetic_applied':False,
            'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'andromeda_requests':0,
            'evidence':None}
    if value.get('status')!='completed':
        report['reason']=value.get('reason'); return report
    group=value['group_minimum']; selected=value['selected_concrete']; tv=(value.get('tourvisor') or {}).get('offers') or []
    rows=(value.get('additional_prices') or {}).get('data') or []
    if len(rows)!=1 or not isinstance(rows[0],dict):
        report['evidence']={'state':'additional_row_absent' if not rows else 'additional_rows_ambiguous','row_count':len(rows)}
        return report
    row=rows[0]; adult=decimal(row.get('price_converted_adult')); supplement=adult*Decimal(2) if adult is not None else None
    concrete_price=decimal(selected.get('price')); group_price=decimal(group.get('price'))
    matches=[]; group_matches=[]
    for candidate in tv:
        if not isinstance(candidate,dict) or candidate.get('room_norm')!=selected.get('room_norm'): continue
        price=decimal(candidate.get('price')); fuel=decimal(candidate.get('fuel_charge'))
        if price is None or fuel is None or supplement is None: continue
        fuel_gap=abs(fuel-supplement); concrete_gap=abs(price-(concrete_price+supplement)) if concrete_price is not None else None
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
        'additional_source':'retained_completed_operation','additional_result_sha256':value.get('additional_result_sha256'),
        'additional_converted_adult':str(adult) if adult is not None else None,
        'candidate_two_adult_supplement':str(supplement) if supplement is not None else None,
        'concrete_total_candidate':str(concrete_price+supplement) if concrete_price is not None and supplement is not None else None,
        'group_total_candidate':str(group_price+supplement) if group_price is not None and supplement is not None else None,
        'concrete_tv_matches':matches,'group_tv_matches':group_matches,
        'unique_concrete_tv_match':len(matches)==1,'group_minimum_supplement_match':len(group_matches)>0,
        'anex_airport_pairs':airports,'anex_flight_selected':False,
        'tourvisor_flight_refresh_requested':False,'supplier_package_identity_verified':False,
        'application_rule_verified_for_search':False,
        'note':'Arithmetic is evidence comparison only. No amount is applied to production search price.'}
    return report


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True)
    data=json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n'
    tmp=path.with_suffix(path.suffix+'.tmp');tmp.write_text(data);tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('concrete_fuel_report_readback')


def run(output, retained_path):
    retained=retained_additional(retained_path)
    spec=dict(BASE_SPEC); spec['retained_additional']=retained
    value=transport.ssh_php_no_mux(source(),spec,maximum_bytes=4000000)
    value=validate(value);save(output/'result.json',value);report=analyze(value);save(output/'report.json',report);return report


def main():
    if len(sys.argv)!=3: raise SystemExit('usage: anex_concrete_fuel_binding.py OUTPUT_DIR RETAINED_RESULT_JSON')
    output=Path(sys.argv[1]); retained_path=Path(sys.argv[2])
    try:
        report=run(output,retained_path);print(json.dumps(report,ensure_ascii=False,sort_keys=True));raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=transport.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True));raise SystemExit(1) from None

if __name__=='__main__': main()
