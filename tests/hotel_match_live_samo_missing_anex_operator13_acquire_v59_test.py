#!/usr/bin/env python3
import importlib.util,pathlib
p=pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_live_samo_missing_anex_operator13_acquire_v59.py'
spec=importlib.util.spec_from_file_location('v59',p)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
assert m.OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v59'
assert m.V53_OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v53'
assert m.V57_OP=='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v57'
assert m.V57_RESULT_SHA=='3034f56a4c0c73d6792778bc77c516745e879600a18adb380e0f0ffc8101c80e'
assert m.V57_RECEIPT_SHA=='8bceb1f759a989d5c2c2d932f4deee2ecdaba52abcfbe79de89104746ec0248f'
assert m.V57_CONSUMED_BATCHES==1
assert m.ACCOUNT=='TOURVISOR_ANEX_JWT' and m.ACCOUNT_LEDGER=='tourvisor-anex'
assert m.DAILY_LIMIT==3000 and m.CALL_CAP==1000 and m.MAX_BATCHES==88
assert m.EXPECTED_DAY=='2026-09-26'
assert callable(m.load_consumed_v57)
x=m.link_projection({'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=5844'})
assert x['link_state']=='captured_single_native' and x['positive_native_candidates']==[5844]
assert m.batch_signature('ctx',[3,1,2])=='ctx|1,2,3'
print('MATCH_V59_TEST_OK')
