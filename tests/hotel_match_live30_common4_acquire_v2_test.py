#!/usr/bin/env python3
import hashlib,importlib.util,json
from pathlib import Path

P=Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_live30_common4_acquire_v2.py'
spec=importlib.util.spec_from_file_location('c4a2',P)
m=importlib.util.module_from_spec(spec);assert spec.loader is not None;spec.loader.exec_module(m)

def plan():
    rows=[{'tv_hotel_id':i,'missing_operator_ids':[13,18],
           'departure_id':1,'country_id':4,'departure_date':'2026-10-01',
           'nights':7,'adults':2,'children_count':0,'child_ages_signature':''} for i in range(1,1350)]
    digest=hashlib.sha256(json.dumps(list(range(1,1350)),separators=(',',':')).encode()).hexdigest()
    return {'state':'live30_common4_continuation_ready','acquisition_target_count':1349,
            'never_attempted_current_count':1349,'attempted_hotel_count':450,
            'acquisition_target_id_sha256':digest,'safe_to_write_now':False,
            'provider_http_calls':0,'database_writes':0,'mapping_writes':0,'rows':rows},digest

p,d=plan();m.PLAN_TARGET_SHA=d;m.PLAN_RESULT_SHA='a'*64
frontier,scope=m.continuation_scope(p,'a'*64,0,30)
assert frontier==1349 and len(scope)==30 and scope[0]['tv_hotel_id']==1 and scope[-1]['tv_hotel_id']==30
_,tail=m.continuation_scope(p,'a'*64,1330,19)
assert [x['tv_hotel_id'] for x in tail]==list(range(1331,1350))

bad=dict(p);bad['rows']=list(p['rows']);bad['rows'][0]=dict(bad['rows'][0]);bad['rows'][0]['missing_operator_ids']=[]
try:m.continuation_scope(bad,'a'*64,0,30)
except RuntimeError as e:assert str(e)=='plan_missing_lanes'
else:raise AssertionError('empty missing lanes accepted')

try:m.continuation_scope(p,'b'*64,0,30)
except RuntimeError as e:assert str(e)=='plan_hash_guard'
else:raise AssertionError('wrong plan hash accepted')

bad2=dict(p);bad2['rows']=list(p['rows']);bad2['rows'][1]=dict(bad2['rows'][1]);bad2['rows'][1]['tv_hotel_id']=1
try:m.continuation_scope(bad2,'a'*64,0,30)
except RuntimeError as e:assert str(e)=='plan_row_id'
else:raise AssertionError('duplicate target accepted')

assert m.native_projection(13,'https://agent.anextour.ru/search/tour?HOTELLIST=5200')['positive_native_candidates']==[5200]
assert [len(x['hotel_ids']) for x in m.chunks([{'departure_id':1,'country_id':4,'departure_date':'2026-10-01','nights':7,'adults':2,'child_ages_signature':'','tv_hotel_id':i,'missing_operator_ids':[13,18,25,43]} for i in range(1,66)])]==[30,30,5]
print('MATCH_COMMON4_CONTINUATION_ACQUIRE_V2_TEST_OK')
