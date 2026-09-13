#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'/'diagnostics'))

import anex_three_source_family_broad as broad

checks=0

def ok(value,message):
    global checks
    if not value:
        raise AssertionError(message)
    checks+=1

php=broad.source()
ok(broad.EXPERIMENT in php and broad.broad3.EXPERIMENT not in php,'fresh family broad experiment bound')
ok("2026-10-29" in php and "'nightsFrom'=>9" in php and "'adults'=>2" in php,'family date/stay/adults retained')
ok("'children'=>1" in php and "'childs'=>[7]" in php and "'CHILD'=>1,'AGES'=>'7'" in php,'child7 reaches all provider request contexts')
ok("'hotelIds'=>[]" in php and "'hotelIds'=>[(int)$subject['local_hotel_id']]" not in php,'Tourvisor/Andromeda selected-hotel filters remain removed')
ok("'hotel_ids'=>[(string)$subject['anex_hotel_id']]" not in php,'direct ANEX selected-hotel filter remains removed')
ok("current_unique_triple_mapping_anchor_only" in php,'triple subject remains checkpoint anchor only')
ok("'received_offers'=>count($result['offers'])" in php and "'unmapped_received'=>$unmapped" in php,'direct observation coverage preserved')

anchor={'local_hotel_id':1239,'anex_hotel_id':8652,'andromeda_hotel_id':'76957','hotel_name':'HEDEF RESORT HOTEL',
        'selection_basis':'current_unique_triple_mapping_anchor_only','anex_observation_count':67}
def offer(provider,local,price,room='standard room'):
    return {'provider':provider,'local_hotel_id':local,'external_hotel_id':str(local),'date':'2026-10-29','nights':9,
            'adults':2,'children':1,'meal_family':'ai','meal_label':'AI','room':'Standard Room','room_norm':room,
            'placement':'DBL+CHD','placement_norm':'dbl+chd','price':price,'currency':'RUB','fuel_charge':None,
            'fuel_inclusion_verified':False,'final_price_verified':False}
def case(provider,offers,details=None):
    return {'schema_version':1,'experiment_id':broad.EXPERIMENT,'case_id':provider,'automatic_retry':False,
            'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'status':'completed','supplier_effect':'read_only_search_completed',
            'subject':anchor,'offers':offers,'details':details or {}}

anex=case('anex',[offer('anex',101,'100000'),offer('anex',102,'110000')],
          {'received_offers':3,'mapped_received':2,'unmapped_received':1})
andr=case('andromeda',[offer('andromeda',101,'100000')],{'received_offers':2,'mapped_offers':1})
tv_offer=offer('tourvisor',101,'120000');tv_offer['fuel_charge']='20000'
tv=case('tourvisor',[tv_offer])
ok(broad.validate_case(anex,'anex') is anex,'broad family direct case accepts multiple local hotels')
ok(broad.validate_case(andr,'andromeda') is andr,'broad family Andromeda case accepted')
ok(broad.validate_case(tv,'tourvisor') is tv,'broad family Tourvisor case accepted')
comparison=broad.comparison({'anex':anex,'andromeda':andr,'tourvisor':tv})
ok(comparison['triple_tuple_count']==1 and comparison['provider_counts']['anex']['unique_tuples']==2,'comparison measures broad family overlap')
report=broad.build_report({'anex':anex,'andromeda':andr,'tourvisor':tv},'completed')
ok(report['observation_summary']['anex_unmapped_received']==1 and report['observation_summary']['andromeda_received_offers']==2,
   'unmapped/received family evidence retained for external matching handoff')
ok(report['unknown_replay_allowed'] is False and report['effects']['mapping_writes']==0 and report['effects']['additional_prices_calls']==0,
   'no-replay and INT scope boundaries explicit')

bad=case('anex',[dict(offer('anex',101,'100000'),children=0)])
try:
    broad.validate_case(bad,'anex')
    raise AssertionError('wrong child party accepted')
except ValueError as exc:
    ok(str(exc)=='family_broad_offer_invalid','wrong child party rejected')

print(f'ANEX three-source broad family comparator: {checks} checks passed; network=0')
