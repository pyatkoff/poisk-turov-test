#!/usr/bin/env python3
"""One new inventory-backed ANEX B2B additional-price parity scenario."""
from decimal import Decimal, InvalidOperation
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport

EXPERIMENT='anex_additional_parity_20260912_v1'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-12','nights':7,'adults':2,
      'child_ages':[],'meal_family':'ai','currency':'RUB'}


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


def source() -> str:
    here=Path(__file__).resolve().parent
    paired=php_body(here/'anex_search3_paired_runner.php',require_strict=False)
    client=php_body(here.parent.parent/'app/integrations/anex-additional-prices-client.php')
    parity=php_body(here/'anex_additional_parity_v3.php')
    return "declare(strict_types=1);\ndefine('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\ndefine('ANYTOUR_ANEX_ADDITIONAL_PARITY_LIBRARY_ONLY', true);\n"+paired+'\n'+client+'\n'+parity+'\n$report=anex_additional_parity_main(); echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n"; exit(($report["status"]??null)==="completed"?0:1);'


def validate(value):
    if not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT:
        raise ValueError('additional_parity_result_invalid')
    if value.get('automatic_retry') is not False or value.get('supplier_replay_allowed') is not False:
        raise ValueError('additional_parity_replay_invalid')
    if value.get('booking_calls')!=0 or value.get('broninit_calls')!=0 or value.get('mapping_writes')!=0:
        raise ValueError('additional_parity_effect_invalid')
    status=value.get('status')
    if status not in ('completed','unknown','blocked'):
        raise ValueError('additional_parity_status_invalid')
    if status=='completed':
        if value.get('supplier_effect')!='read_only_search_and_additional_completed':
            raise ValueError('additional_parity_completion_invalid')
        pair=value.get('selected_pair'); additional=value.get('additional_prices')
        if not isinstance(pair,dict) or not isinstance(pair.get('anex'),dict) or not isinstance(pair.get('tourvisor'),dict):
            raise ValueError('additional_parity_pair_invalid')
        if not isinstance(additional,dict) or not isinstance(additional.get('data'),list):
            raise ValueError('additional_parity_additional_invalid')
        if value.get('additional_prices_requests')!=1:
            raise ValueError('additional_parity_request_count_invalid')
    return value


def decimal(value):
    try:
        if isinstance(value,(str,int)) and str(value) and not str(value).startswith('-'):
            return Decimal(str(value))
    except InvalidOperation:
        pass
    return None


def analyze(value):
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':value.get('status'),
            'spec':SPEC,'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0},
            'supplier_replay_allowed':False,'price_arithmetic_applied':False,
            'universal_formula_verified':False,'evidence':None}
    if value.get('status')!='completed':
        report['reason']=value.get('reason'); return report
    pair=value['selected_pair']; anex=pair['anex']; tv=pair['tourvisor']; rows=value['additional_prices'].get('data') or []
    row=rows[0] if rows and isinstance(rows[0],dict) else None
    if row is None:
        report['evidence']={'state':'additional_row_absent','basis':pair.get('basis')}; return report
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
        'note':'single inventory-backed scenario; candidate arithmetic is evidence comparison only and is not applied to search price',
    }
    return report


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.tmp'); tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n'); tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('additional_parity_report_readback')


def run(output):
    value=validate(transport.ssh_php_no_mux(source(),SPEC,maximum_bytes=4000000))
    save(output/'result.json',value); report=analyze(value); save(output/'report.json',report); return report


def main():
    if len(sys.argv)!=2: raise SystemExit('usage: anex_additional_parity_v3.py OUTPUT_DIR')
    output=Path(sys.argv[1])
    try:
        report=run(output); print(json.dumps(report,ensure_ascii=False,sort_keys=True)); raise SystemExit(0 if report['status']=='completed' else 1)
    except SystemExit: raise
    except Exception as exc:
        report=transport.transport_failure(exc) if type(exc).__name__=='SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False}
        try: save(output/'failure.json',report)
        except Exception: pass
        print(json.dumps(report,sort_keys=True)); raise SystemExit(1) from None

if __name__=='__main__': main()
