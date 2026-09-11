#!/usr/bin/env python3
import json
import tempfile
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import andromeda_fresh_package_supplier_error_probe as probe

assert "supplierError" in probe.SUPPLIER_ERROR_PHP
assert "['code','id','message','text','description']" in probe.SUPPLIER_ERROR_PHP
assert "(string)($query['claiminc']??'')" in probe.SUPPLIER_ERROR_PHP
assert "(string)($query['sid']??'')" in probe.SUPPLIER_ERROR_PHP
assert "->bron(" not in probe.SUPPLIER_ERROR_PHP
assert "->calc(" not in probe.SUPPLIER_ERROR_PHP
assert "->get_flights(" not in probe.SUPPLIER_ERROR_PHP
assert "'CHECKIN_BEG'=>'20260920'" in probe.SUPPLIER_ERROR_PHP
assert "'CHECKIN_END'=>'20260927'" in probe.SUPPLIER_ERROR_PHP

safe = {
    'status':'unknown','phase':'broninit','error':'ANDROMEDA_SUPPLIER_ERROR','supplier_calls':3,
    'database_writes':0,'booking_calls':0,'calc_calls':0,'get_flights_calls':0,'automatic_retry':False,
    'supplier_error':{'sha256':'a'*64,'kind':'array','keys':['code','message'],'code':'2110','message':'NO OFFER'},
    'finished_at':'2026-09-11T00:00:00+00:00'
}

def runner(source, request, maximum_bytes=0):
    assert source == probe.SUPPLIER_ERROR_PHP
    assert request == {}
    assert maximum_bytes == 4000000
    return {'safe': safe, 'private': None}

with tempfile.TemporaryDirectory() as temp:
    out = Path(temp)/'evidence'
    result = probe.execute(out, runner)
    assert result == safe
    assert json.loads((out/'result.json').read_text()) == safe
    assert not (out/'private-package.json').exists()

print('Andromeda supplier error probe: PASS')
