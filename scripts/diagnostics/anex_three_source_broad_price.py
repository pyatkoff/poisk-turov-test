#!/usr/bin/env python3
"""One bounded direct-ANEX Green Gold program observation."""
import json
from pathlib import Path
import sys

from anex_search3_three_source_price import ssh_php_no_mux, transport_failure

EXPERIMENT='anex_green_gold_program_20260912_v9'
CASES=('anex',)
TARGET_LOCAL_HOTEL_ID=21753
TARGET_ANEX_HOTEL_ID='25084'
SPEC={'experiment_id':EXPERIMENT,'country':'Turkey','date':'2026-10-05','nights':7,'adults':2,'child_ages':[],'meal_family':'ai','currency':'RUB'}


def source():
    here=Path(__file__).resolve().parent
    old=(here/'anex_search3_paired_runner.php').read_text()
    meal=(here.parents[1]/'app/integrations/three-provider-meal-family.php').read_text()
    new=(here/'anex_three_source_broad_price.php').read_text()
    if not old.startswith('<?php') or not meal.startswith('<?php') or not new.startswith('<?php'):
        raise ValueError('broad_php_header')
    marker='declare(strict_types=1);'
    def strict_body(value):
        body=value[5:].lstrip()
        if not body.startswith(marker): raise ValueError('broad_php_strict_header')
        return body[len(marker):].lstrip('\r\n')
    return "declare(strict_types=1);\ndefine('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"+old[5:]+'\n'+strict_body(meal)+'\n'+strict_body(new)


def _provider_id(value):
    return value is None or (isinstance(value,str) and value.isdigit() and not value.startswith('0') and len(value)<=18)


def validate_case(value,case_id):
    if case_id!='anex' or not isinstance(value,dict) or value.get('schema_version')!=1 or value.get('experiment_id')!=EXPERIMENT or value.get('case_id')!='anex' \
            or value.get('automatic_retry') is not False or value.get('booking_calls')!=0 or value.get('broninit_calls')!=0 or value.get('mapping_writes')!=0 \
            or value.get('observation_writes_allowed') is not False:
        raise ValueError('broad_case_invalid')
    status=value.get('status')
    if status=='unknown':
        if value.get('supplier_effect')!='unknown': raise ValueError('broad_unknown_invalid')
        return value
    if status=='blocked':
        if value.get('supplier_effect')!='none': raise ValueError('broad_blocked_invalid')
        return value
    details=value.get('details')
    if status!='completed' or value.get('supplier_effect')!='read_only_search_completed' or not isinstance(value.get('offers'),list) or not isinstance(details,dict):
        raise ValueError('broad_case_invalid')
    coverage=details.get('coverage')
    if not isinstance(coverage,dict) or coverage.get('state')!='bounded' or coverage.get('reason')!='pricepage_1_target_hotel_only' or coverage.get('all_pages_retained') is not False:
        raise ValueError('broad_coverage_invalid')
    for row in value['offers']:
        if not isinstance(row,dict) or row.get('provider')!='anex' or row.get('local_hotel_id')!=TARGET_LOCAL_HOTEL_ID or row.get('external_hotel_id')!=TARGET_ANEX_HOTEL_ID \
                or row.get('date')!=SPEC['date'] or row.get('nights')!=7 or row.get('adults')!=2 or row.get('children')!=0 or row.get('meal_key')!='ai' \
                or row.get('currency')!='RUB' or not isinstance(row.get('price'),str) or row.get('fuel_charge') is not None \
                or row.get('fuel_inclusion_verified') is not False or row.get('final_price_verified') is not False \
                or not _provider_id(row.get('supplier_tour_program_id')) or not _provider_id(row.get('supplier_currency_id')):
            raise ValueError('broad_offer_invalid')
    pairs=details.get('program_pairs')
    if not isinstance(pairs,list): raise ValueError('broad_program_pairs_invalid')
    for pair in pairs:
        if not isinstance(pair,dict) or not _provider_id(pair.get('tour')) or not _provider_id(pair.get('currency')) or pair.get('tour') is None:
            raise ValueError('broad_program_pairs_invalid')
    saved=details.get('saved_cross_source_context')
    if saved!={'date':'2026-10-05','andromeda_search_price':'119952','tourvisor_display_price':'149548','tourvisor_fuel_charge':'29596','requeried':False}:
        raise ValueError('broad_saved_context_invalid')
    return value


def save(path,value):
    path.parent.mkdir(parents=True,exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.tmp');tmp.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n');tmp.replace(path)
    if json.loads(path.read_text())!=value: raise ValueError('broad_report_readback')


def run(output):
    php=source()
    value=validate_case(ssh_php_no_mux(php,dict(SPEC,case_id='anex'),maximum_bytes=4000000),'anex')
    save(output/'anex.json',value)
    status=value['status']
    details=value.get('details',{}) if isinstance(value.get('details'),dict) else {}
    offers=value.get('offers',[]) if isinstance(value.get('offers'),list) else []
    program_pairs=details.get('program_pairs',[]) if isinstance(details.get('program_pairs'),list) else []
    report={'schema_version':1,'experiment_id':EXPERIMENT,'status':status,'spec':SPEC,
        'target_local_hotel_id':TARGET_LOCAL_HOTEL_ID,'target_anex_hotel_id':TARGET_ANEX_HOTEL_ID,
        'offer_count':len(offers),'program_pairs':program_pairs,'offers':offers[:20],
        'saved_cross_source_context':details.get('saved_cross_source_context'),
        'effects':{'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'observation_writes':0},
        'additional_prices_called':False,'andromeda_requeried':False,'tourvisor_requeried':False,'unknown_replay_allowed':False}
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
