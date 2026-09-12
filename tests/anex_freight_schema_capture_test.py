#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
DIAG=ROOT/'scripts'/'diagnostics'
sys.path.insert(0,str(DIAG))
spec=importlib.util.spec_from_file_location('freight_schema',DIAG/'anex_freight_schema_capture.py')
mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)

src=mod.php_source()
assert src.startswith('declare(strict_types=1);\n')
assert src.count('<?php')==0
assert "FreightMonitor_FREIGHTSBYPACKET" in src
assert "$client->request('FreightMonitor_FREIGHTSBYPACKET',['CATCLAIM'=>$claim])" in src
assert "supplier_tour_program_id']??null)==='2637'" in src
assert "hotel_external_id':'25084'" not in src  # Python spec never leaks into generated PHP text as JSON.
for forbidden in ('AnyTourAnexAdditionalPricesClient','ANEX_B2B_TOKEN','anex_concrete_fuel_tv($local','->bron(','broninit(','->calc(',
                  'INSERT INTO anex_hotel_search_mappings','UPDATE anex_hotel_search_mappings'):
    # Existing concrete library contains the TV helper definition, but the schema main must not call it.
    if forbidden=='anex_concrete_fuel_tv($local':
        assert src.count(forbidden)==1
    else:
        assert forbidden not in src
assert "'tourvisor_requests'=>0" in src and "'additional_prices_requests'=>0" in src and "'andromeda_requests'=>0" in src
assert "preg_match('/(?:claim|token|oauth|url|href|link)/i" in src

value={'schema_version':1,'experiment_id':mod.EXPERIMENT,'status':'completed','automatic_retry':False,
       'supplier_replay_allowed':False,'anex_requests':6,'additional_prices_requests':0,'tourvisor_requests':0,'andromeda_requests':0,
       'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'supplier_effect':'read_only_search_expand_freight_schema_completed',
       'selected_concrete':{'kind':'concrete','supplier_tour_program_id':'2637','price':'119448'},
       'freight_schema':[
           {'path':'FreightMonitor_FREIGHTSBYPACKET','type':'object','count':1},
           {'path':'FreightMonitor_FREIGHTSBYPACKET.routes','type':'list','count':2},
           {'path':'FreightMonitor_FREIGHTSBYPACKET.routes.[].freights.[].freightKey','type':'int','candidate_value':318},
           {'path':'FreightMonitor_FREIGHTSBYPACKET.routes.[].freights.[].price','type':'string','candidate_value':'100'},
       ]}
assert mod.validate(value) is value
report=mod.summarize(value)
assert report['fuel_rate_binding_verified'] is False and report['production_price_arithmetic_applied'] is False
assert report['path_count']==4 and len(report['candidate_fields'])==2
for key,bad in [('tourvisor_requests',1),('additional_prices_requests',1),('andromeda_requests',1),('booking_calls',1),('mapping_writes',1)]:
    broken=dict(value);broken[key]=bad
    try:mod.validate(broken)
    except ValueError:pass
    else:raise AssertionError('invalid effect accepted: '+key)
secret=dict(value);secret['freight_schema']=[{'path':'x','type':'string','candidate_value':'CATCLAIM'}]
try:mod.validate(secret)
except ValueError:pass
else:raise AssertionError('sensitive marker accepted')
wrong=dict(value);wrong['selected_concrete']={'kind':'concrete','supplier_tour_program_id':'1797'}
try:mod.validate(wrong)
except ValueError:pass
else:raise AssertionError('wrong program accepted')
print('ANEX FreightMonitor schema capture offline checks: PASS')
