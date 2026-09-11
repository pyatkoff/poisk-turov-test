#!/usr/bin/env python3
import importlib.util
import json
from pathlib import Path
import tempfile

ROOT=Path(__file__).resolve().parents[1]
SPEC=importlib.util.spec_from_file_location('probe',ROOT/'scripts/diagnostics/andromeda_fresh_package_probe.py')
probe=importlib.util.module_from_spec(SPEC); SPEC.loader.exec_module(probe)

captured={'safe':{
    'status':'captured','phase':'complete','supplier_calls':3,'database_writes':0,'booking_calls':0,
    'calc_calls':0,'get_flights_calls':0,'price_rows':50,'price_pages':2,'price_id_sha256':'a'*64,
    'catalog_key_sha256':'b'*64,'id_equals_catalog_key':False,'package_sha256':'c'*64,
    'claim_fields':['catalogKey','condition','freightExternal'],'condition':'ccOffer',
    'requires_external_flights':True,'buyer_price':{'amount':'123456','currency':'RUB','status':'package_unverified'},
    'hotels_count':1,'transports_count':0,'services_count':2,'automatic_retry':False,'finished_at':'2026-09-11T12:00:00Z'
},'private':{'price_row':{'id':'PRIVATE_CLAIM'},'package':{'claimDocument':[{'catalogKey':'PRIVATE_KEY'}]}}}

def runner(source,request,maximum_bytes=0):
    assert maximum_bytes==4000000 and request=={}
    assert "action='bron'" not in source and 'bron_ticket' not in source
    assert "->calc(" not in source and "'get_flights'" not in source
    return captured

with tempfile.TemporaryDirectory() as temp:
    out=Path(temp)/'evidence'; safe=probe.execute(out,runner)
    assert safe['status']=='captured' and safe['supplier_calls']==3
    assert json.loads((out/'result.json').read_text())['requires_external_flights'] is True
    private=json.loads((out/'private-package.json').read_text())
    assert private['price_row']['id']=='PRIVATE_CLAIM'
    assert oct((out/'private-package.json').stat().st_mode & 0o777)=='0o600'

bad={'safe':dict(captured['safe'],booking_calls=1),'private':captured['private']}
with tempfile.TemporaryDirectory() as temp:
    try: probe.execute(Path(temp)/'evidence',lambda *a,**k: bad)
    except ValueError: pass
    else: raise AssertionError('booking evidence must fail closed')

unknown={'safe':{'status':'unknown','phase':'price','error':'ANDROMEDA_TRANSPORT_ERROR','supplier_calls':2,
    'database_writes':0,'booking_calls':0,'calc_calls':0,'get_flights_calls':0,'automatic_retry':False,
    'finished_at':'2026-09-11T12:00:00Z'},'private':None}
with tempfile.TemporaryDirectory() as temp:
    safe=probe.execute(Path(temp)/'evidence',lambda *a,**k: unknown)
    assert safe['status']=='unknown' and not (Path(temp)/'evidence'/'private-package.json').exists()
print('Andromeda fresh package probe: PASS')
