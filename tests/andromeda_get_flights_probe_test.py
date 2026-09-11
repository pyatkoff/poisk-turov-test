#!/usr/bin/env python3
import json
import tempfile
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import andromeda_get_flights_probe as probe

assert "action'=>'get_flights'" in probe.PHP
assert "CURLOPT_POST=>true" in probe.PHP
assert "['claim'=>$claimJson]" in probe.PHP
assert probe.EXPECTED_ARTIFACT_RUN == 34605764015
assert len(probe.EXPECTED_PACKAGE_SHA256) == 64
assert "->package(" not in probe.PHP
assert "action'=>'calc'" not in probe.PHP
assert "action'=>'bron'" not in probe.PHP
assert "get_flights_calls'=>1" in probe.PHP
assert "booking_calls'=>0" in probe.PHP and "calc_calls'=>0" in probe.PHP

claim={'version':'1.01','claimDocument':[{'freightExternal':1}],'variants':[],'groups':[],'checkFields':[]}
receipt={
    'status':'captured','phase':'complete','supplier_calls':3,
    'package_sha256':probe.EXPECTED_PACKAGE_SHA256,'requires_external_flights':True,
}
private={'price_row':{'id':'private-sentinel'},'package':claim}

with tempfile.TemporaryDirectory() as temp:
    root=Path(temp)
    inp=root/'input'; inp.mkdir()
    (inp/'result.json').write_text(json.dumps(receipt))
    (inp/'private-package.json').write_text(json.dumps(private))
    out=root/'output'
    safe={
        'status':'captured','phase':'complete','supplier_calls':2,'login_calls':1,'get_flights_calls':1,
        'calc_calls':0,'booking_calls':0,'database_writes':0,'automatic_retry':False,
        'input_claim_sha256':probe.EXPECTED_PACKAGE_SHA256,'response_sha256':'b'*64,
        'top_fields':['version','claimDocument'],'claim_document_count':1,'variants_count':0,'groups_count':0,
        'requires_external_flights':False,'finished_at':'2026-09-11T00:00:00+00:00'
    }
    def runner(source, request, maximum_bytes=0):
        assert source == probe.PHP
        assert request == {'claim':claim}
        assert maximum_bytes == 4000000
        return {'safe':safe,'private':{'version':'1.01','claimDocument':[{'freightExternal':0}]}}
    result=probe.execute(inp,out,runner)
    assert result==safe
    assert json.loads((out/'result.json').read_text())==safe
    assert (out/'private-flights.json').exists()

print('Andromeda get_flights probe: PASS')
