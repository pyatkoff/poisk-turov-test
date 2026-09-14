#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'/'diagnostics'))

import anex_three_source_uae_broad as broad

checks=0

def ok(value,message):
    global checks
    if not value:
        raise AssertionError(message)
    checks+=1

php=broad.source()
ok(broad.EXPERIMENT in php and broad.broad3.EXPERIMENT not in php,'fresh UAE broad experiment bound')
ok("'UAE'" in php and "'ОАЭ'" in php and "'Turkey'" not in php and "'Турция'" not in php,'UAE destination replaces Turkey in all provider contexts')
ok("2026-11-20" in php and "20261120" in php and "'nightsFrom'=>8" in php and "'nightsTo'=>8" in php,'new UAE date/stay reaches provider requests')
ok("'adults'=>2" in php and "'ADULT'=>2" in php and "'children'=>0" in php and "'CHILD'=>0" in php,'two-adult party reaches all provider contexts')
ok("'hotelIds'=>[]" in php and "'hotelIds'=>[(int)$subject['local_hotel_id']]" not in php,'Tourvisor/Andromeda stay broad without selected hotel filter')
ok("'hotel_ids'=>[(string)$subject['anex_hotel_id']]" not in php,'direct ANEX stays broad without selected hotel filter')
ok("current_unique_triple_mapping_anchor_only" in php,'current triple identity remains checkpoint anchor only')
ok("'received_offers'=>count($result['offers'])" in php and "'unmapped_received'=>$unmapped" in php,'direct observation coverage remains retained')
ok('broninit(' not in php and '->bron(' not in php and 'bron_ticket' not in php and '->calc(' not in php,'booking and quote operations remain absent')

anchor={'local_hotel_id':209,'anex_hotel_id':5001,'andromeda_hotel_id':'22001','hotel_name':'UAE ANCHOR',
        'selection_basis':'current_unique_triple_mapping_anchor_only','anex_observation_count':5}
def offer(provider,local,price,room='standard room',fuel=None):
    return {'provider':provider,'local_hotel_id':local,'external_hotel_id':str(local),'date':'2026-11-20','nights':8,
            'adults':2,'children':0,'meal_family':'ai','meal_label':'AI','room':'Standard Room','room_norm':room,
            'placement':'DBL','placement_norm':'dbl','price':price,'currency':'RUB','fuel_charge':fuel,
            'fuel_inclusion_verified':False,'final_price_verified':False}
def case(provider,offers,details=None):
    return {'schema_version':1,'experiment_id':broad.EXPERIMENT,'case_id':provider,'automatic_retry':False,
            'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'status':'completed','supplier_effect':'read_only_search_completed',
            'subject':anchor,'offers':offers,'details':details or {}}

anex=case('anex',[offer('anex',201,'90000'),offer('anex',202,'100000')],
          {'received_offers':4,'mapped_received':2,'unmapped_received':2})
andr=case('andromeda',[offer('andromeda',201,'90000'),offer('andromeda',202,'101000')],
          {'received_offers':3,'mapped_offers':2})
tv=case('tourvisor',[offer('tourvisor',201,'108000',fuel='18000'),offer('tourvisor',202,'119000',fuel='18000')])
ok(broad.validate_case(anex,'anex') is anex,'broad UAE direct case accepts multiple mapped hotels')
ok(broad.validate_case(andr,'andromeda') is andr,'broad UAE Andromeda case accepted')
ok(broad.validate_case(tv,'tourvisor') is tv,'broad UAE Tourvisor case accepted')
relation=broad.money_relation({'anex':anex,'andromeda':andr,'tourvisor':tv})
ok(relation['anex_andromeda']=={'pair_contexts':2,'equal_search_price_contexts':1},'base-price equality is measured per exact context')
ok(relation['anex_tourvisor']['matching_delta_contexts']==1 and relation['anex_tourvisor']['observed_fuel_values_rub']==['18000'],
   'Tourvisor delta relation measured without applying it')
ok(relation['andromeda_tourvisor']['matching_delta_contexts']==2 and relation['runtime_arithmetic_authorized'] is False,
   'Andromeda/TV relation remains evidence-only')
report=broad.build_report({'anex':anex,'andromeda':andr,'tourvisor':tv},'completed')
ok(report['observation_summary']['anex_unmapped_received']==2 and report['observation_summary']['andromeda_received_offers']==3,
   'unmapped/received observations retained for external matching handoff')
ok(report['effects']=={'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'additional_prices_calls':0}
   and report['unknown_replay_allowed'] is False,'no-replay and prohibited side effects remain explicit')

bad=case('anex',[dict(offer('anex',201,'90000'),adults=3)])
try:
    broad.validate_case(bad,'anex')
    raise AssertionError('wrong party accepted')
except ValueError as exc:
    ok(str(exc)=='uae_broad_offer_invalid','wrong party rejected')

print(f'ANEX three-source broad UAE comparator: {checks} checks passed; network=0')
