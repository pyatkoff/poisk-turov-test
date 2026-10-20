#!/usr/bin/env python3
import importlib.util,pathlib
p=pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_direct_anex_http400_route_class_audit_v58.py'
spec=importlib.util.spec_from_file_location('v58',p);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
assert m.OP=='hotel-match-direct-anex-http400-route-class-audit-1971-20260926-v58'
assert m.EXPECTED_UNION==172 and m.EXPECTED_UNTOUCHED==89
router={'operation_id':m.ROUTER_OP,'state':'completed_read_only','input_count':777,'routed_future_context_count':659,'batch_count':261,'batch_plan':[]}
for i in range(1,262):
    ctx=f'1|{2 if i<=173 else 4}|2026-10-{(i%28)+1:02d}|7|2|0|'
    router['batch_plan'].append({'context_key':ctx,'batch_index':i,'hotel_ids':[10000+i],'hotel_count':1})
proj=[];pos=0
for key,(op,n) in m.OPS.items():
    for b in range(1,n+1):
        rr=router['batch_plan'][pos];pos+=1
        proj.append({'operation':op,'batch':b,'context_key':rr['context_key'],'hotel_ids':rr['hotel_ids'],'state':'reserved_before_batch_http'})
out=m.audit(router,proj)
assert out['consumed_union_count']==172 and out['untouched_count']==89
assert out['reservation_counts']=={'v41b':40,'v45':50,'v49':50,'v53':31,'v57':1}
assert out['v53_failed_reservation']['batch']==31 and out['v57_failed_reservation']['batch']==1
assert out['provider_http_calls']==out['database_reads']==out['database_writes']==out['mapping_writes']==out['server_mutation']==0
print('MATCH_V58_TEST_OK')
