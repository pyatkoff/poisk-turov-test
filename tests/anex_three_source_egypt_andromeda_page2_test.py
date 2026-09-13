#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'/'diagnostics'))

import anex_three_source_egypt_andromeda_page2 as page2

checks=0

def ok(value,message):
    global checks
    if not value:
        raise AssertionError(message)
    checks+=1

php=page2.source()
ok(page2.EXPERIMENT in php and page2.egypt.EXPERIMENT not in php,'new page2 experiment bound')
ok("'generation'=>26091307,'page'=>2" in php and "'generation'=>26091306,'page'=>1" not in php,'only Andromeda operation moves to page2')
ok("'three-price-egypt-broad-page2-20260913-v1'" in php,'new durable Andromeda operation id bound')
ok("'Egypt'" in php and "2026-11-03" in php and "'hotelIds'=>[]" in php,'sealed Egypt broad criteria retained')
ok("current_unique_triple_mapping_anchor_only" in php,'identity remains current-context anchor only')

anchor={'local_hotel_id':158,'anex_hotel_id':1275,'andromeda_hotel_id':'103544','hotel_name':'EGYPT ANCHOR',
        'selection_basis':'current_unique_triple_mapping_anchor_only','anex_observation_count':12}
offer={'provider':'andromeda','local_hotel_id':77,'external_hotel_id':'77','date':'2026-11-03','nights':8,
       'adults':2,'children':0,'meal_family':'ai','meal_label':'AI','room':'Standard','room_norm':'standard',
       'placement':'DBL','placement_norm':'dbl','price':'123456','currency':'RUB','fuel_charge':None,
       'fuel_inclusion_verified':False,'final_price_verified':False}
case={'schema_version':1,'experiment_id':page2.EXPERIMENT,'case_id':'andromeda','automatic_retry':False,
      'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'status':'completed','supplier_effect':'read_only_search_completed',
      'subject':anchor,'offers':[offer],'details':{'received_offers':10,'mapped_offers':9,'pages_count':2}}
ok(page2.validate_case(case) is case,'completed page2 case accepted')

bad=dict(case);bad['details']=dict(case['details'],pages_count=1)
try:
    page2.validate_case(bad)
    raise AssertionError('wrong pages_count accepted')
except ValueError as exc:
    ok(str(exc)=='egypt_page2_case_invalid','page2 requires declared second page')

bad_offer=dict(offer,adults=3);bad=dict(case,offers=[bad_offer])
try:
    page2.validate_case(bad)
    raise AssertionError('wrong party accepted')
except ValueError as exc:
    ok(str(exc)=='egypt_page2_offer_invalid','page2 preserves exact party context')

print(f'ANEX Egypt Andromeda page2 continuation: {checks} checks passed; network=0')
