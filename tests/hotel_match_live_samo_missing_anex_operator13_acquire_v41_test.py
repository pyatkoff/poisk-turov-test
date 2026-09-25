#!/usr/bin/env python3
import importlib.util, pathlib

p=pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_live_samo_missing_anex_operator13_acquire_v41.py'
spec=importlib.util.spec_from_file_location('v41',p)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

assert m.OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v41b'
assert m.ACCOUNT=='TOURVISOR_ANEX_JWT'
assert m.ACCOUNT_LEDGER=='tourvisor-anex'
assert m.DAILY_LIMIT==3000 and m.CALL_CAP==500 and m.MAX_BATCHES==50
x=m.link_projection({'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=5844'})
assert x['link_state']=='captured_single_native' and x['positive_native_candidates']==[5844]
x=m.link_projection({'operatorLink':'https://evil.example/x?HOTELLIST=5844'})
assert x['link_state']=='invalid_origin'
print('MATCH_V41_TEST_OK')
