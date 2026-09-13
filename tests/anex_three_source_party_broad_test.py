#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'/'diagnostics'))

import anex_three_source_party_broad as broad

checks=0

def ok(value,message):
    global checks
    if not value:
        raise AssertionError(message)
    checks+=1

php=broad.source()
ok(broad.EXPERIMENT in php and broad.party.EXPERIMENT not in php,'fresh broad experiment bound')
ok("2026-10-20" in php and "'nightsFrom'=>8" in php and "'adults'=>3" in php,'same selected-gap scenario dimensions retained')
ok("'hotelIds'=>[]" in php and "'hotelIds'=>[(int)$subject['local_hotel_id']]" not in php,'Tourvisor/Andromeda selected-hotel filters removed')
ok("'hotel_ids'=>[(string)$subject['anex_hotel_id']]" not in php,'direct ANEX selected-hotel filter removed')
ok("current_unique_triple_mapping_anchor_only" in php,'triple subject downgraded to checkpoint anchor semantics')
ok("'received_offers'=>count($result['offers'])" in php and "'unmapped_received'=>$unmapped" in php,'direct observation coverage exposed')
ok("anex_three_price_offer('tourvisor',$localId,$localId" in php,'Tourvisor rows use returned local hotel IDs')
ok("anex_three_price_offer('anex',$localId,(string)($offer['hotel']['external_id']??'')" in php,'direct ANEX rows preserve returned external hotel IDs')
ok("anex_three_price_offer('andromeda',$localId,(string)$localId" in php,'Andromeda projected rows use current local IDs without inventing cross-provider identity')

anchor={'local_hotel_id':1239,'anex_hotel_id':8652,'andromeda_hotel_id':'76957','hotel_name':'HEDEF RESORT HOTEL',
        'selection_basis':'current_unique_triple_mapping_anchor_only','anex_observation_count':67}
def offer(provider,local,price,room='standard room'):
    return {'provider':provider,'local_hotel_id':local,'external_hotel_id':str(local),'date':'2026-10-20','nights':8,
            'adults':3,'children':0,'meal_family':'ai','meal_label':'AI','room':'Standard Room','room_norm':room,
            'placement':'DBL','placement_norm':'dbl','price':price,'currency':'RUB','fuel_charge':None,
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
ok(broad.validate_case(anex,'anex') is anex,'broad direct case accepts multiple local hotels')
ok(broad.validate_case(andr,'andromeda') is andr,'broad Andromeda case accepted')
ok(broad.validate_case(tv,'tourvisor') is tv,'broad Tourvisor case accepted')
comparison=broad.comparison({'anex':anex,'andromeda':andr,'tourvisor':tv})
ok(comparison['triple_tuple_count']==1 and comparison['provider_counts']['anex']['unique_tuples']==2,'comparison measures broad overlap without mapping writes')
report=broad.build_report({'anex':anex,'andromeda':andr,'tourvisor':tv},'completed')
ok(report['observation_summary']['anex_unmapped_received']==1 and report['observation_summary']['andromeda_received_offers']==2,
   'unmapped/received evidence retained for external matching handoff')
ok(report['unknown_replay_allowed'] is False and report['effects']['mapping_writes']==0 and report['effects']['additional_prices_calls']==0,
   'no-replay and scope boundaries explicit')

bad=case('anex',[dict(offer('anex',101,'100000'),adults=2)])
try:
    broad.validate_case(bad,'anex')
    raise AssertionError('wrong party accepted')
except ValueError as exc:
    ok(str(exc)=='party_broad_offer_invalid','wrong party rejected')

print(f'ANEX three-source broad three-adult comparator: {checks} checks passed; network=0')
