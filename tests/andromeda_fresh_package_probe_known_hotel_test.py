#!/usr/bin/env python3
import json
import tempfile
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import andromeda_fresh_package_probe_known_hotel as probe

src=probe.KNOWN_HOTEL_PHP
assert "'TOWNFROMINC'=>1" in src
assert "'STATEINC'=>3" in src
assert "'CHECKIN_BEG'=>'20260918'" in src and "'CHECKIN_END'=>'20260918'" in src
assert "'NIGHTS_FROM'=>8,'NIGHTS_TILL'=>8" in src
assert "'MEAL'=>'5'" in src and "'OPERATORS'=>'5'" in src and "'HOTELS'=>'416247'" in src
assert "$supplierCalls=3; $package=$client->package($offer)" in src
assert "->get_flights(" not in src and "->calc(" not in src and "->bron(" not in src

safe={'status':'captured','phase':'complete','supplier_calls':3,'database_writes':0,'booking_calls':0,
      'calc_calls':0,'get_flights_calls':0,'price_rows':8,'price_pages':1,
      'price_id_sha256':'a'*64,'catalog_key_sha256':'b'*64,'id_equals_catalog_key':False,
      'package_sha256':'c'*64,'claim_fields':['catalogKey','condition','freightExternal'],
      'condition':'ccOffer','requires_external_flights':True,'buyer_price':None,
      'hotels_count':1,'transports_count':1,'services_count':0,'automatic_retry':False,
      'finished_at':'2026-09-11T00:00:00+00:00'}
private={'price_row':{'id':'PRIVATE_CLAIM'},'package':{'claimDocument':[{'catalogKey':'PRIVATE'}]}}

def runner(source, request, maximum_bytes=0):
    assert source == probe.KNOWN_HOTEL_PHP and request == {} and maximum_bytes == 4000000
    return {'safe':safe,'private':private}

with tempfile.TemporaryDirectory() as temp:
    out=Path(temp)/'evidence'
    got=probe.execute(out,runner)
    assert got==safe
    assert json.loads((out/'result.json').read_text())==safe
    assert json.loads((out/'private-package.json').read_text())==private
print('Andromeda known-hotel package probe: PASS')
