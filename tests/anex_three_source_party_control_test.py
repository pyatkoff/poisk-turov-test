#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'/'diagnostics'))

import anex_three_source_party_control as control

checks=0

def ok(value,message):
    global checks
    if not value:
        raise AssertionError(message)
    checks+=1

php=control.source()
ok(control.EXPERIMENT in php,'new experiment bound')
ok("2026-10-20" in php and "'nightsFrom'=>8" in php and "'nightsTo'=>8" in php,'same date and stay retained')
ok("($value['adults'] ?? null) !== 2" in php and "(int)$adults !== 2" in php,'two-adult validation bound')
ok("'ADULT'=>2" in php and "'adults'=>2" in php and "$tour['adults']??2" in php,'two-adult supplier contexts bound')
ok("'hotelIds'=>[(int)$subject['local_hotel_id']]" in php and "'hotel_ids'=>[(string)$subject['anex_hotel_id']]" in php,'same selected-subject filters retained')
ok(control.party.EXPERIMENT not in php and "'adults'=>3" not in php and "'ADULT'=>3" not in php,'sealed three-adult scenario not leaked')

subject={'local_hotel_id':1239,'anex_hotel_id':8652,'andromeda_hotel_id':'76957','hotel_name':'HEDEF RESORT HOTEL',
         'selection_basis':'current_unique_triple_mapping','anex_observation_count':10}
case={'schema_version':1,'experiment_id':control.EXPERIMENT,'case_id':'anex','automatic_retry':False,
      'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'status':'completed','supplier_effect':'read_only_search_completed',
      'subject':subject,'offers':[{'provider':'anex','local_hotel_id':1239,'external_hotel_id':'8652','date':'2026-10-20',
      'nights':8,'adults':2,'children':0,'meal_family':'ai','meal_label':'AI','room':'Standard','room_norm':'standard',
      'placement':'DBL','placement_norm':'dbl','price':'150000','currency':'RUB','fuel_charge':None,
      'fuel_inclusion_verified':False,'final_price_verified':False}]}
ok(control.validate_case(case,'anex') is case,'completed two-adult case accepted')
bad=dict(case);bad['offers']=[dict(case['offers'][0],adults=3)]
try:
    control.validate_case(bad,'anex')
    raise AssertionError('three-adult replay-shaped offer accepted')
except ValueError as exc:
    ok(str(exc)=='party_control_offer_invalid','three-adult offer rejected by control validator')

blocked=dict(case,status='blocked',supplier_effect='none',reason='THREE_PRICE_SUBJECT_CHANGED',offers=[])
ok(control.validate_case(blocked,'anex') is blocked,'blocked no-effect case retained')
unknown=dict(case,status='unknown',supplier_effect='unknown',offers=[])
ok(control.validate_case(unknown,'anex') is unknown,'unknown case retained without replay authorization')

report=control.build_report({'anex':case},'unconfirmed')
ok(report['spec']['adults']==2 and report['effects']['booking_calls']==0 and report['effects']['additional_prices_calls']==0,
   'report keeps party and forbidden effects explicit')
ok(report['unknown_replay_allowed'] is False and report['supplier_replay_requested'] is False,'report keeps no-replay explicit')

print(f'ANEX three-source two-adult control: {checks} checks passed; network=0')
