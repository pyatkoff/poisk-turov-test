#!/usr/bin/env python3
import importlib.util
import json
from pathlib import Path
import tempfile

ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location('wide',ROOT/'scripts/diagnostics/andromeda_fresh_package_probe_wide.py')
wide=importlib.util.module_from_spec(SPEC); SPEC.loader.exec_module(wide)

captured={'safe':{
    'status':'captured','phase':'complete','supplier_calls':3,'database_writes':0,'booking_calls':0,
    'calc_calls':0,'get_flights_calls':0,'price_rows':50,'price_pages':3,'price_id_sha256':'a'*64,
    'catalog_key_sha256':'b'*64,'id_equals_catalog_key':False,'package_sha256':'c'*64,
    'claim_fields':['catalogKey','freightExternal'],'condition':'ccOffer','requires_external_flights':False,
    'buyer_price':{'amount':'145000','currency':'RUB','status':'package_unverified'},
    'hotels_count':1,'transports_count':1,'services_count':2,'automatic_retry':False,
    'finished_at':'2026-09-11T12:00:00Z'
},'private':{'price_row':{'id':'PRIVATE'},'package':{'claimDocument':[{}]}}}

def runner(source, request, maximum_bytes=0):
    assert maximum_bytes==4000000 and request=={}
    assert "'CHECKIN_BEG'=>'20260920'" in source
    assert "'CHECKIN_END'=>'20260927'" in source
    assert "'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>8" in source
    assert "'OPERATORS'=>'5'" in source and "'MEAL'=>'5'" not in source
    assert "->calc(" not in source and "'get_flights'" not in source and "->bron(" not in source
    return captured

with tempfile.TemporaryDirectory() as temp:
    safe=wide.execute(Path(temp)/'evidence',runner)
    assert safe['status']=='captured' and safe['supplier_calls']==3
    assert json.loads((Path(temp)/'evidence'/'result.json').read_text())['price_rows']==50
print('Andromeda fresh wide package probe: PASS')
