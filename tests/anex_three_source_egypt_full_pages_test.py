#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'/'diagnostics'))

import anex_three_source_egypt_full_pages as full

checks=0

def ok(value,message):
    global checks
    if not value:
        raise AssertionError(message)
    checks+=1

php=full.source()
ok(full.EXPERIMENT in php and full.egypt.EXPERIMENT not in php,'fresh half-board experiment bound')
ok("2026-12-07" in php and "20261207" in php and "2026-11-03" not in php,'known Tourvisor-positive date replaces sealed Egypt base date')
ok("'nightsFrom'=>10" in php and "'nightsTo'=>10" in php and "'nights_from'=>10" in php and "'nights_till'=>10" in php,'ten-night stay reaches all providers')
ok("'adults'=>2" in php and "'ADULT'=>2" in php and "($value['adults'] ?? null) !== 2" in php,'two-adult party reaches all provider and validation paths')
ok("'children'=>0" in php and "'childs'=>[]" in php and "'CHILD'=>0" in php,'adult-only control remains explicit')
ok("($value['meal_family'] ?? null) !== 'hb'" in php and "$mealFamily !== 'hb'" in php and "'meal_family'=>'hb'" in php,
   'half-board family is enforced at input, normalized offer and output')
ok("['hb','half board','полупансион']" in php and "'meal'=>4" in php and "'meal'=>7" not in php,
   'known HB labels are classified locally and Andromeda receives its provider-specific HB code')
ok("'three-price-egypt-half-board-20260914-v6'" in php and "'generation'=>26091405" in php,'new operation/session generation bound')
ok("for($pageNo=1;$pageNo<=$pagesCount&&$pageNo<=5;++$pageNo)" in php and "$request['page']=$pageNo" in php,'Andromeda establishes page1 then walks pages sequentially')
ok("'pages_loaded'=>$pagesLoaded" in php and "$receivedTotal+=(int)($result['received_offers']??0)" in php,'Andromeda page coverage is accumulated')
ok("'hotelIds'=>[]" in php and "current_unique_triple_mapping_anchor_only" in php,'broad scope remains no-hotel-filter with identity anchor only')
ok("'page_context_missing'=>'PAGE_CONTEXT_MISSING'" in php
   and "'previous_page_missing'=>'PREVIOUS_PAGE_MISSING'" in php
   and "'page_outside_latest_response'=>'PAGE_OUTSIDE_LATEST_RESPONSE'" in php
   and "'page_session_expired'=>'PAGE_SESSION_EXPIRED'" in php
   and "'supplier_unavailable'=>'SUPPLIER_UNAVAILABLE'" in php,
   'known Andromeda pagination/runtime failures retain fixed safe categories')
ok("throw new RuntimeException('THREE_PRICE_ANDROMEDA_PAGE_'.$pageNo.'_'.$pageCategory)" in php
   and "'THREE_PRICE_ANDROMEDA_PAGE_'.$pageNo.'_'.$pageMessage" not in php,
   'page number and category survive outer sanitizer without exposing raw exception text')

anchor={'local_hotel_id':248,'anex_hotel_id':10449,'andromeda_hotel_id':'1','hotel_name':'EGYPT ANCHOR',
        'selection_basis':'current_unique_triple_mapping_anchor_only','anex_observation_count':12}
def offer(provider,local,price,fuel=None):
    return {'provider':provider,'local_hotel_id':local,'external_hotel_id':str(local),'date':'2026-12-07','nights':10,
            'adults':2,'children':0,'meal_family':'hb','meal_label':'HB','room':'Standard Room','room_norm':'standard room',
            'placement':'DBL','placement_norm':'dbl','price':price,'currency':'RUB','fuel_charge':fuel,
            'fuel_inclusion_verified':False,'final_price_verified':False}
def case(provider,offers,details=None):
    return {'schema_version':1,'experiment_id':full.EXPERIMENT,'case_id':provider,'automatic_retry':False,
            'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'status':'completed','supplier_effect':'read_only_search_completed',
            'subject':anchor,'offers':offers,'details':details or {}}

andromeda=case('andromeda',[offer('andromeda',101,'100000')],
               {'received_offers':75,'mapped_offers':70,'pages_count':2,'pages_loaded':2})
ok(full.validate_case(andromeda,'andromeda') is andromeda,'complete advertised Andromeda pagination accepted')

bad=case('andromeda',[offer('andromeda',101,'100000')],
         {'received_offers':50,'mapped_offers':47,'pages_count':2,'pages_loaded':1})
try:
    full.validate_case(bad,'andromeda')
    raise AssertionError('partial pagination accepted')
except ValueError as exc:
    ok(str(exc)=='egypt_full_pages_pagination_incomplete','partial advertised pagination rejected')

anex=case('anex',[offer('anex',101,'100000')],{'received_offers':1,'mapped_received':1,'unmapped_received':0})
tv=case('tourvisor',[offer('tourvisor',101,'121535','21535')])
report=full.build_report({'anex':anex,'andromeda':andromeda,'tourvisor':tv},'completed')
ok(report['spec']['meal_family']=='hb' and report['observation_summary']['andromeda_pages_loaded']==2,
   'report retains HB scenario and complete pagination')
ok(report['money_relation']['runtime_arithmetic_authorized'] is False
   and report['money_relation']['additional_prices_equated_to_fuel'] is False,
   'display-money evidence never authorizes arithmetic or APD equivalence')
ok(report['comparison']['triple_examples'][0]['basis']=='same_current_local_hotel_date_party_meal_and_exact_room',
   'comparison basis is meal-generic rather than falsely AI-specific')
ok(report['effects']=={'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'additional_prices_calls':0}
   and report['unknown_replay_allowed'] is False,'no-replay and prohibited effects remain explicit')

print(f'ANEX three-source Egypt half-board comparator: {checks} checks passed; network=0')
