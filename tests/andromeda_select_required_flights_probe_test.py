#!/usr/bin/env python3
import json,tempfile,hashlib
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'))
import andromeda_select_required_flights_probe as probe

src=probe.PHP
assert "action'=>'changeservice'" in src
assert "'NEW_UID'=>$item['uid']" in src
assert 'OLD_UID' in src and 'deliberately omitted' in src
assert "action'=>'calc'" not in src and "action'=>'bron'" not in src and "action'=>'get_flights'" not in src
assert "changeservice_calls'=>2" in src and "booking_calls'=>0" in src

claim={
 'checkFields':[],
 'claimDocument':[{'transports':[None]}],
 'variants':[{'transports':[{'transport':[
   {'uid':'out1','groupId':'20001','direction':'0','type':'ttAvia'},
   {'uid':'back1','groupId':'20002','direction':'1','type':'ttAvia'}]}]}],
 'groups':[{'group':[{'id':'20001','required':'true','oneItem':'true'},{'id':'20002','required':'true','oneItem':'true'}]}],
 'version':'1.01'
}
# Unit-test the file plumbing by temporarily overriding the expected digest.
raw=json.dumps(claim,ensure_ascii=False,separators=(',',':')).encode(); digest=hashlib.sha256(raw).hexdigest()
old=probe.EXPECTED_FLIGHTS_RESPONSE_SHA256; probe.EXPECTED_FLIGHTS_RESPONSE_SHA256=digest
try:
  with tempfile.TemporaryDirectory() as t:
    root=Path(t); inp=root/'in';inp.mkdir();out=root/'out'
    (inp/'result.json').write_text(json.dumps({'status':'captured','response_sha256':digest,'get_flights_calls':1}))
    (inp/'private-flights.json').write_text(json.dumps(claim,ensure_ascii=False,separators=(',',':')))
    safe={'status':'captured','phase':'complete','supplier_calls':3,'login_calls':1,'changeservice_calls':2,'calc_calls':0,'booking_calls':0,'database_writes':0,'automatic_retry':False,'selected_flights':2,'finished_at':'2026-09-11T00:00:00Z'}
    def runner(source,request,maximum_bytes=0):
      assert source==probe.PHP and request=={'claim':claim} and maximum_bytes==4000000
      return {'safe':safe,'private':{'claimDocument':[{'transports':[{'transport':[{'direction':'0'},{'direction':'1'}]}]}]}}
    assert probe.execute(inp,out,runner)==safe
    assert (out/'private-selected-claim.json').exists()
finally:
  probe.EXPECTED_FLIGHTS_RESPONSE_SHA256=old
print('Andromeda select required flights probe: PASS')
