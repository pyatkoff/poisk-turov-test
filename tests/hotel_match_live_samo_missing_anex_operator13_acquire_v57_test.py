#!/usr/bin/env python3
import importlib.util, pathlib
p=pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_live_samo_missing_anex_operator13_acquire_v57.py'
spec=importlib.util.spec_from_file_location('v57',p)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
assert m.OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v57'
assert m.V49_OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v49'
assert m.V49_RESULT_SHA=='ee1f6765456940c6b665a3111cdb8b7f2e3dba2ec70e0d7981fe601e759c203a'
assert m.V49_RECEIPT_SHA=='2a7dee34887519bd75eaededbe4df3114228ac6efddd168b550faf5e015aafbe'
assert m.V53_OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v53'
assert m.V53_RESULT_SHA=='ef37e3380401bacdfe6ff16edeb445a103ec248594ed5cacafc0071b0fca5ec4'
assert m.V53_RECEIPT_SHA=='ae64c83c79ff0c4e4002500fc668cc666ba1e5e1b9cc3af665ad575935846701'
assert m.V53_CONSUMED_BATCHES==31
assert m.ACCOUNT=='TOURVISOR_ANEX_JWT' and m.ACCOUNT_LEDGER=='tourvisor-anex'
assert m.DAILY_LIMIT==3000 and m.CALL_CAP==1000 and m.MAX_BATCHES==90
assert m.EXPECTED_DAY=='2026-09-26'
x=m.link_projection({'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=5844'})
assert x['link_state']=='captured_single_native' and x['positive_native_candidates']==[5844]
x=m.link_projection({'operatorLink':'https://evil.example/x?HOTELLIST=5844'})
assert x['link_state']=='invalid_origin'
assert m.batch_signature('ctx',[3,1,2])=='ctx|1,2,3'
assert callable(m.load_consumed_v49) and callable(m.load_consumed_v53)
assert callable(m.run_batch) and callable(m.execute) and m.Provider is not None
print('MATCH_V57_TEST_OK')
