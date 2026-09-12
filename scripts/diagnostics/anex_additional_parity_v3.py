#!/usr/bin/env python3
"""Inventory-backed ANEX B2B parity and one program-only retained-evidence follow-up."""
from decimal import Decimal, InvalidOperation
import hashlib
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport

EXPERIMENT='anex_additional_parity_20260912_v1'
PROGRAM2637_EXPERIMENT='anex_additional_program2637_20260912_v1'
RETAINED_ARTIFACT_ID=10304537645
RETAINED_RESULT_SHA256='7b9a8237739b9d1334f1cebfa91fa89b6c4321f029714d76dabca710b2cae589'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
      'child_ages':[],'meal_family':'ai','currency':'RUB'}
PROGRAM2637_CONTEXT={'page':1,'pageSize':10,'tour':2637,'dateBeg':'2026-10-12','nights':7,'currency':3}


def php_body(path: Path, require_strict=True) -> str:
    text=path.read_text()
    if not text.startswith('<?php\n'):
        raise ValueError('php_header_invalid')
    body=text[len('<?php\n'):]
    strict='declare(strict_types=1);\n'
    if require_strict:
        if not body.startswith(strict):
            raise ValueError('php_strict_header_invalid')
        body=body[len(strict):]
    elif body.startswith(strict):
        body=body[len(strict):]
    return body


def source(program_followup=False) -> str:
    here=Path(__file__).resolve().parent
    paired=php_body(here/'anex_search3_paired_runner.php',require_strict=False)
    client=php_body(here.parent.parent/'app/integrations/anex-additional-prices-client.php')
    parity=php_body(here/'anex_additional_parity_v3.php')
    argument='true' if program_followup else 'false'
    return "declare(strict_types=1);\ndefine('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\ndefine('ANYTOUR_ANEX_ADDITIONAL_PARITY_LIBRARY_ONLY', true);\n"+paired+'\n'+client+'\n'+parity+'\n$report=anex_additional_parity_main('+argument+'); echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n"; exit(($report["status"]??null)==="completed"?0:1);'


def validate(value, program_followup=False):
    experiment=PROGRAM2637_EXPERIMENT if program_followup else EXPERIMENT
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=experiment:
        raise ValueError('additional_parity_result_invalid')
    if value.get('automatic_retry') is not False or value.get('supplier_replay_allowed') is not False:
        raise ValueError('additional_parity_replay_invalid')
    if value.get('booking_calls')!=0 or value.get('broninit_calls')!=0 or value.get('mapping_writes')!=0:
        raise ValueError('additional_parity_effect_invalid')
    if program_followup and (value.get('direct_anex_requests')!=0 or value.get('tourvisor_requests')!=0):
        raise ValueError('additional_program_search_replay')
    status=value.get('status')
    if status not in ('completed','unknown','blocked'):
        raise ValueError('additional_parity_status_invalid')
    if status=='completed':
        effect='read_only_additional_from_retained_search_completed' if program_followup else 'read_only_search_and_additional_completed'
        if value.get('supplier_effect')!=effect:
            raise ValueError('additional_parity_completion_invalid')
        pair=value.get('selected_pair'); additional=value.get('additional_prices')
        if not isinstance(pair,dict) or not isinstance(pair.get('anex'),dict) or not isinstance(pair.get('tourvisor'),dict):
            raise ValueError('additional_parity_pair_invalid')
        if not isinstance(additional,dict) or not isinstance(additional.get('data'),list):
            raise ValueError('additional_parity_additional_invalid')
        if value.get('additional_prices_requests')!=1:
            raise ValueError('additional_parity_request_count_invalid')
        if program_followup and value.get('additional_request_context')!=PROGRAM2637_CONTEXT:
            raise ValueError('additional_program_context_invalid')
    return value


def decimal(value):
    try:
        if isinstance(value,(str,int,float)) and not isinstance(value,bool):
            amount=Decimal(str(value))
            if amount.is_finite() and amount>=0:
                return amount
    except InvalidOperation:
        pass
    return None


def program2637_pair(value):
    """Reuse an already accepted historical identity; never infer or write mappings."""
    if not isinstance(value,dict) or value.get('experiment_id')!=EXPERIMENT or value.get('supplier_replay_allowed') is not False:
        raise ValueError('additional_program_retained_source_invalid')
    evidence=value.get('search_evidence') or {}
    expected={'local_hotel_id':21753,'date':'2026-10-12','nights':7,'adults':2,'children':0,'meal_family':'ai','currency':'RUB'}
    def matches(row):
        return isinstance(row,dict) and all(row.get(k)==v for k,v in expected.items()) and bool(row.get('room_norm')) and decimal(row.get('price')) is not None
    direct=[row for row in (evidence.get('anex') or {}).get('offers',[]) if matches(row)
            and row.get('provider')=='anex' and row.get('external_hotel_id')=='25084'
            and row.get('supplier_tour_program_id')=='2637' and row.get('supplier_currency_id')=='3']
    if len(direct)!=1:
        raise ValueError('additional_program_retained_anex_ambiguous')
    anex=direct[0]
    candidates=[row for row in (evidence.get('tourvisor') or {}).get('offers',[]) if matches(row)
                and row.get('provider')=='tourvisor' and row.get('external_hotel_id')=='21753'
                and row.get('room_norm')==anex['room_norm'] and decimal(row.get('fuel_charge')) is not None]
    if not candidates:
        raise ValueError('additional_program_retained_tourvisor_absent')
    # Choose the captured minimum, not a fuel/price equality that would bias the experiment.
    tv=min(candidates,key=lambda row: decimal(row['price']))
    return {'basis':'retained_same_local_hotel_date_party_ai_exact_room_minimum',
            'identical_supplier_package_verified':False,'placement_compared_but_not_identity_key':True,
            'anex':anex,'tourvisor':tv}


def read_program2637_evidence(path):
    if path.stat().st_size>4000000:
        raise ValueError('additional_program_retained_size')
    raw=path.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=RETAINED_RESULT_SHA256:
        raise ValueError('additional_program_retained_digest')
    return program2637_pair(json.loads(raw))


def analyze(value):
    report={'schema_version':1,'experiment_id':value.get('experiment_id',EXPERIMENT),'status':value.get('status'),
            'spec':dict(SPEC,experiment_id=value.get('experiment_id',EXPERIMENT)),
            'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0},
            'supplier_replay_allowed':False,'price_arithmetic_applied':False,
            'universal_formula_verified':False,'evidence':None}
    if 'retained_search_source' in value:
        report['retained_search_source']=value['retained_search_source']
        report['new_requests']={k:value.get(k) for k in ('direct_anex_requests','tourvisor_requests','additional_prices_requests')}
    if value.get('status')!='completed':
        report['reason']=value.get('reason'); return report
    pair=value['selected_pair']; anex=pair['anex']; tv=pair['tourvisor']; rows=value['additional_prices'].get('data') or []
    if len(rows)!=1 or not isinstance(rows[0],dict):
        report['evidence']={'state':'additional_row_absent' if not rows else 'additional_rows_ambiguous',
                            'row_count':len(rows),'basis':pair.get('basis')}; return report
    row=rows[0]
    direct=decimal(anex.get('price')); display=decimal(tv.get('price')); fuel=decimal(tv.get('fuel_charge'))
    adult=decimal(row.get('price_converted_adult')); child=decimal(row.get('price_converted_chd'))
    delta=display-direct if direct is not None and display is not None else None
    party2=adult*Decimal(2) if adult is not None else None
    report['evidence']={
        'state':'observed',
        'basis':pair.get('basis'),
        'identical_supplier_package_verified':False,
        'local_hotel_id':anex.get('local_hotel_id'),
        'room_norm':anex.get('room_norm'),
        'direct_anex_search_price':str(direct) if direct is not None else None,
        'tourvisor_display_price':str(display) if display is not None else None,
        'tourvisor_fuel_charge_reported':str(fuel) if fuel is not None else None,
        'tourvisor_minus_direct':str(delta) if delta is not None else None,
        'additional_price_converted_adult':str(adult) if adult is not None else None,
        'additional_price_converted_child':str(child) if child is not None else None,
        'candidate_two_adult_rate_sum':str(party2) if party2 is not None else None,
        'delta_equals_tourvisor_fuel':delta is not None and fuel is not None and delta==fuel,
        'two_adult_rate_sum_equals_tourvisor_fuel':party2 is not None and fuel is not None and party2==fuel,
        'single_adult_rate_equals_tourvisor_fuel':adult is not None and fuel is not None and adult==fuel,
        'additional_native_amount_adult':str(row.get('price_adult')) if row.get('price_adult') is not None else None,
        'additional_native_amount_child':str(row.get('price_chd')) if row.get('price_chd') is not None else None,
        'cashrate':str(row.get('cashrate')) if row.get('cashrate') is not None else None,
        'application_rule_verified_for_search':False,
        'note':'candidate arithmetic is evidence comparison only, never applied to search price; retained search prices are not revalidated',
    }
    return report


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.tmp'); tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n'); tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('additional_parity_report_readback')


def run(output, retained_source=None):
    program_followup=retained_source is not None
    pair=read_program2637_evidence(retained_source) if program_followup else None
    spec=dict(SPEC,experiment_id=PROGRAM2637_EXPERIMENT) if program_followup else SPEC
    value=transport.ssh_php_no_mux(source(program_followup),spec,maximum_bytes=4000000)
    if program_followup and isinstance(value,dict):
        value['selected_pair']=pair
        value['retained_search_source']={'experiment_id':EXPERIMENT,'run_id':34715151815,
            'artifact_id':RETAINED_ARTIFACT_ID,'result_sha256':RETAINED_RESULT_SHA256,
            'source_operation_still_no_replay':True,'search_prices_revalidated':False}
    value=validate(value,program_followup)
    save(output/'result.json',value); report=analyze(value); save(output/'report.json',report); return report


def main():
    if len(sys.argv) not in (2,4) or (len(sys.argv)==4 and sys.argv[2]!='--program2637'):
        raise SystemExit('usage: anex_additional_parity_v3.py OUTPUT_DIR [--program2637 RETAINED_RESULT_JSON]')
    output=Path(sys.argv[1]); retained=Path(sys.argv[3]) if len(sys.argv)==4 else None
    try:
        report=run(output,retained); print(json.dumps(report,ensure_ascii=False,sort_keys=True)); raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=transport.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True)); raise SystemExit(1) from None

if __name__=='__main__': main()
