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
ok(full.EXPERIMENT in php and full.egypt.EXPERIMENT not in php,'fresh full-page experiment bound')
ok("2026-12-14" in php and "20261214" in php and "2026-11-03" not in php,'new date replaces sealed Egypt date')
ok("'nightsFrom'=>9" in php and "'nightsTo'=>9" in php and "'nights_from'=>9" in php and "'nights_till'=>9" in php,'nine-night stay reaches all providers')
ok("'adults'=>2" in php and "'ADULT'=>2" in php and "($value['adults'] ?? null) !== 2" in php,'two-adult party reaches all provider and validation paths')
ok("'children'=>1" in php and "'childs'=>[7]" in php and "'CHILD'=>1,'AGES'=>'7'" in php
   and "($value['child_ages'] ?? null) !== [7]" in php,'child age seven reaches all provider and validation paths')
ok("'three-price-egypt-family-full-pages-20260914-v3'" in php and "'generation'=>26091402" in php,'new operation/session generation bound')
ok("for($pageNo=1;$pageNo<=$pagesCount&&$pageNo<=5;++$pageNo)" in php and "$request['page']=$pageNo" in php,'Andromeda establishes page1 then walks pages sequentially')
ok("'pages_loaded'=>$pagesLoaded" in php and "$receivedTotal+=(int)($result['received_offers']??0)" in php,'Andromeda page coverage is accumulated')
ok("'hotelIds'=>[]" in php and "current_unique_triple_mapping_anchor_only" in php,'broad scope remains no-hotel-filter with identity anchor only')
ok("'page_context_missing'=>'PAGE_CONTEXT_MISSING'" in php
   and "'previous_page_missing'=>'PREVIOUS_PAGE_MISSING'" in php
   and "'page_outside_latest_response'=>'PAGE_OUTSIDE_LATEST_RESPONSE'" in php
   and "'page_session_expired'=>'PAGE_SESSION_EXPIRED'" in php
   and "'supplier_unavailable'=>'SUPPLIER_UNAVAILABLE'" in php,
   'known Andromeda pagination/runtime failures have fixed safe categories')
ok("$pageError instanceof OverflowException&&$pageMessage==='monthly_quota_exhausted'" in php
   and "$pageCategory='MONTHLY_QUOTA'" in php
   and "$pageError instanceof DomainException" in php
   and "$pageCategory='PROVIDER_CONTEXT'" in php
   and "$pageError instanceof InvalidArgumentException" in php
   and "$pageCategory='INVALID_REQUEST_CONTEXT'" in php
   and "$pageError instanceof JsonException" in php
   and "$pageCategory='STATE_JSON_INVALID'" in php
   and "$pageCategory='RUNTIME_EXCEPTION'" in php,
   'remaining page failures collapse to bounded typed categories')
ok("throw new RuntimeException('THREE_PRICE_ANDROMEDA_PAGE_'.$pageNo.'_'.$pageCategory)" in php
   and "'THREE_PRICE_ANDROMEDA_PAGE_'.$pageNo.'_'.$pageMessage" not in php,
   'page number and category survive outer sanitizer without exposing raw exception text')

anchor={'local_hotel_id':158,'anex_hotel_id':1275,'andromeda_hotel_id':'103544','hotel_name':'EGYPT ANCHOR',
        'selection_basis':'current_unique_triple_mapping_anchor_only','anex_observation_count':12}
def offer(provider,local,price,fuel=None):
    return {'provider':provider,'local_hotel_id':local,'external_hotel_id':str(local),'date':'2026-12-14','nights':9,
            'adults':2,'children':1,'meal_family':'ai','meal_label':'AI','room':'Standard Room','room_norm':'standard room',
            'placement':'2+1','placement_norm':'2 1','price':price,'currency':'RUB','fuel_charge':fuel,
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
tv=case('tourvisor',[offer('tourvisor',101,'118843','18843')])
report=full.build_report({'anex':anex,'andromeda':andromeda,'tourvisor':tv},'completed')
ok(report['observation_summary']['andromeda_pages_loaded']==2 and report['money_relation']['runtime_arithmetic_authorized'] is False,
   'report retains pagination evidence without authorizing arithmetic')
ok(report['effects']=={'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'additional_prices_calls':0}
   and report['unknown_replay_allowed'] is False,'no-replay and prohibited effects remain explicit')

print(f'ANEX three-source Egypt family full-pages comparator: {checks} checks passed; network=0')
