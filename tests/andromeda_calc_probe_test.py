#!/usr/bin/env python3
import hashlib,json,tempfile
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'))
import andromeda_calc_probe as probe

s=probe.PHP
assert "action'=>'calc'" in s and "CURLOPT_POST=>true" in s and "['claim'=>$claimJson]" in s
assert "action'=>'get_flights'" not in s and "action'=>'changeservice'" not in s and "action'=>'bron'" not in s
assert "booking_calls'=>0" in s and "final_price_verified'=>true" in s

claim={'version':'1.01','claimDocument':[{'condition':'ccOffer','transports':[{'transport':[{'direction':'0'},{'direction':'1'}]}]}]}
raw=json.dumps(claim,ensure_ascii=False,separators=(',',':')).encode(); file_sha=hashlib.sha256(raw).hexdigest(); response_sha='a'*64
old_file,old_resp=probe.EXPECTED_PRIVATE_FILE_SHA256,probe.EXPECTED_SELECTED_RESPONSE_SHA256
probe.EXPECTED_PRIVATE_FILE_SHA256=file_sha;probe.EXPECTED_SELECTED_RESPONSE_SHA256=response_sha
try:
  with tempfile.TemporaryDirectory() as t:
    root=Path(t);inp=root/'in';inp.mkdir();out=root/'out'
    (inp/'result.json').write_text(json.dumps({'status':'captured','response_sha256':response_sha,'changeservice_calls':2,'selected_flights':2}))
    (inp/'private-selected-claim.json').write_bytes(raw)
    safe={'status':'captured','phase':'complete','supplier_calls':2,'login_calls':1,'calc_calls':1,'get_flights_calls':0,'changeservice_calls':0,'booking_calls':0,'database_writes':0,'automatic_retry':False,'final_price_verified':True,'price_before_calc':{'amount':'124864','currency':'RUB'},'price_after_calc':{'amount':'150000','currency':'RUB'},'finished_at':'2026-09-11T00:00:00Z'}
    def runner(source,request,maximum_bytes=0):
      assert source==probe.PHP and request=={'claim':claim} and maximum_bytes==4000000
      return {'safe':safe,'private':{'claimDocument':[{'condition':'ccOffer'}]}}
    assert probe.execute(inp,out,runner)==safe
    assert (out/'private-calculated-claim.json').exists()
finally:
  probe.EXPECTED_PRIVATE_FILE_SHA256=old_file;probe.EXPECTED_SELECTED_RESPONSE_SHA256=old_resp
print('Andromeda calc probe: PASS')
