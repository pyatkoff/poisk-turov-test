#!/usr/bin/env python3
import importlib.util,json,os,pathlib,tempfile,time
P=pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_live30_common4_remainder_v1.py'
spec=importlib.util.spec_from_file_location('r',P);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

def dump(p,v):
    p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v))

with tempfile.TemporaryDirectory() as td:
    root=pathlib.Path(td)
    rows=[{'tv_hotel_id':i,'missing_operator_ids':[13,18,25,43],'departure_id':1,'country_id':4,
           'departure_date':'2026-10-01','nights':7,'adults':2,'child_ages_signature':''} for i in range(1,1350)]
    plan={'state':'live30_common4_continuation_ready','acquisition_target_count':1349,
          'acquisition_target_id_sha256':m.id_digest(range(1,1350)),'rows':rows,
          'provider_http_calls':0,'database_writes':0,'mapping_writes':0}
    d=root/m.PLAN_OP;d.mkdir();raw=m.enc(plan)+b'\n';(d/'result.json').write_bytes(raw)
    dump(d/'receipt.json',{'result_sha256':__import__('hashlib').sha256(raw).hexdigest()})
    op=root/'hotel-match-live30-common4-continuation-acquire-1971-20260923-c0-n30-v1';op.mkdir()
    dump(op/'tv-plan.json',{'groups':[{'hotel_ids':[1,2,3]},{'hotel_ids':[4,5]}]})
    dump(op/'tv-request-0001.json',{'action':'search_start'});dump(op/'tv-request-0002.json',{'action':'search_start'})
    dump(op/'result.json',{});dump(op/'receipt.json',{})
    attempted,children,maxr=m.collect_attempted(root,plan,now=time.time()+300)
    assert set(attempted)=={1,2,3,4,5} and maxr==0
    remaining,selected=m.select_remaining(plan,attempted,3)
    assert [x['tv_hotel_id'] for x in selected]==[6,7,8] and len(remaining)==1344
    op2=root/'hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n10-v1';op2.mkdir()
    dump(op2/'tv-plan.json',{'groups':[{'hotel_ids':[6,7]}]});dump(op2/'tv-request-0001.json',{'action':'search_start'})
    old=time.time()-300;os.utime(op2/'tv-request-0001.json',(old,old))
    attempted,children,maxr=m.collect_attempted(root,plan,now=time.time())
    assert set(attempted)=={1,2,3,4,5,6,7} and maxr==1
    os.utime(op2/'tv-request-0001.json',None)
    try:m.collect_attempted(root,plan,now=time.time());raise AssertionError('recent child accepted')
    except RuntimeError as e:assert str(e)=='recent_nonterminal_child'
print('MATCH_LIVE30_COMMON4_REMAINDER_V1_TEST_OK')
