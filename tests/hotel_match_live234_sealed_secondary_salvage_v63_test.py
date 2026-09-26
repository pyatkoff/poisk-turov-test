#!/usr/bin/env python3
import importlib.util,pathlib,tempfile,json,hashlib
p=pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_live234_sealed_secondary_salvage_v63.py'
spec=importlib.util.spec_from_file_location('v63',p);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
assert m.OP=='hotel-match-live234-sealed-secondary-salvage-1971-20260926-v63'
assert [x[1:] for x in m.CHILDREN]==[(0,78),(78,78),(156,78)]
assert m.OPS=={18:'bgoperator',25:'operator_315',43:'operator_342'}

with tempfile.TemporaryDirectory() as td:
    root=pathlib.Path(td)
    cur=set(range(1,235))
    child,off,lim=m.CHILDREN[0]
    d=root/child;d.mkdir()
    result={'frontier_count':234,'scope_offset':off,'scope_count':lim,'operator_ids':[18,25,43],
            'state':'completed_read_only','provider_calls':9,'searched_hotels':78,
            'database_writes':0,'mapping_writes':0,'edges':[
                {'tv_hotel_id':1,'operator_id':25,'namespace':'operator_315','state':'detail_identity_verified',
                 'link_state':'captured_single_native','positive_native_candidates':[111],
                 'search_id_sha256':'a'*64,'tour_id_sha256':'b'*64,'operator_link_sha256':'c'*64},
                {'tv_hotel_id':999,'operator_id':25,'namespace':'operator_315','state':'detail_identity_verified',
                 'link_state':'captured_single_native','positive_native_candidates':[222]},
            ]}
    raw=(json.dumps(result,sort_keys=True,separators=(',',':'))+'\n').encode();(d/'result.json').write_bytes(raw)
    rsha=hashlib.sha256(raw).hexdigest()
    receipt={'result_sha256':rsha,'state':'completed_read_only','no_replay':True}
    (d/'receipt.json').write_text(json.dumps(receipt))
    out=m.salvage(root,cur)
    assert out['current_membership_count']==234
    assert out['detail_verified_single_native_count']==1
    assert out['single_native_namespace_counts']=={'operator_315':1}
    assert out['single_native_edges'][0]['tv_hotel_id']==1
    assert out['provider_http_calls']==out['database_reads']==out['database_writes']==out['mapping_writes']==out['server_mutation']==0
print('MATCH_LIVE234_SEALED_SECONDARY_SALVAGE_V63_TEST_OK')
