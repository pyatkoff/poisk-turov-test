#!/usr/bin/env python3
import importlib.util, pathlib

p=pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_live_samo_missing_anex_operator13_acquire_v49.py'
spec=importlib.util.spec_from_file_location('v41',p)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

assert m.OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v49'
assert m.V45_OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v45'
assert m.V41B_OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v41b'
assert m.V41B_RESULT_SHA=='a2b3538998c2742096b8655a80af2c5f3f7ea4a8cf3a7c7d81b2d9ec4d5c533e'
assert m.V41B_RECEIPT_SHA=='d27cf7686650c1c93fca01cd120cdc51e60c311fc314224bfc1a4b959a28a5f4'
assert m.V41B_CONSUMED_BATCHES==40
assert m.ACCOUNT=='TOURVISOR_ANEX_JWT'
assert m.ACCOUNT_LEDGER=='tourvisor-anex'
assert m.DAILY_LIMIT==3000 and m.CALL_CAP==500 and m.MAX_BATCHES==50
x=m.link_projection({'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=5844'})
assert x['link_state']=='captured_single_native' and x['positive_native_candidates']==[5844]
x=m.link_projection({'operatorLink':'https://evil.example/x?HOTELLIST=5844'})
assert x['link_state']=='invalid_origin'
assert m.batch_signature('ctx',[3,1,2])=='ctx|1,2,3'
print('MATCH_V49_TEST_OK')
