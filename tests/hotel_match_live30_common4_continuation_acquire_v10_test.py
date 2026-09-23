#!/usr/bin/env python3
import importlib.util
from pathlib import Path

P=Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'/'hotel_match_live30_common4_continuation_acquire_v10.py'
spec=importlib.util.spec_from_file_location('c10',P);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

def plan(n=45):
    rows=[{'tv_hotel_id':1000+i,'missing_operator_ids':[13,18,25,43] if i%2 else [18,25,43],
           'departure_id':1,'country_id':4,'departure_date':'2026-10-01','nights':7,'adults':2,
           'children_count':0,'child_ages_signature':''} for i in range(1,n+1)]
    return {'state':'live30_common4_continuation_ready','acquisition_target_count':n,'rows':rows,
            'acquisition_target_id_sha256':m.plan_id_digest([x['tv_hotel_id'] for x in rows]),
            'provider_http_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False}

p=plan();frontier,ids,digest=m.validate_continuation_plan(p,'a'*64)
assert frontier==45 and len(ids)==45 and digest==p['acquisition_target_id_sha256']
scope,meta=m.scope_continuation_plan(p,0,30)
assert len(scope)==30 and meta['frontier_count']==45 and meta['scope_offset']==0
scope2,meta2=m.scope_continuation_plan(p,30,15)
assert len(scope2)==15 and meta2['scope_count']==15
try:m.scope_continuation_plan(p,30,16);raise AssertionError('overflow accepted')
except RuntimeError as e:assert str(e)=='scope_guard'
bad=plan();bad['rows'][0]['missing_operator_ids']=[999]
try:m.validate_continuation_plan(bad);raise AssertionError('bad operator accepted')
except RuntimeError as e:assert str(e)=='continuation_plan_operator'
bad=plan();bad['provider_http_calls']=1
try:m.validate_continuation_plan(bad);raise AssertionError('write authority accepted')
except RuntimeError as e:assert str(e)=='continuation_plan_authority'
assert m.native_projection(13,'https://agent.anextour.ru/search/tour?HOTELLIST=5200')['positive_native_candidates']==[5200]
assert m.native_projection(18,'https://www.bgoperator.ru/x?F4=12345')['positive_native_candidates']==[12345]
print('MATCH_LIVE30_COMMON4_CONTINUATION_ACQUIRE_V10_TEST_OK')

assert m.ACCOUNT_CONST=='TOURVISOR_ANEX_JWT'
assert m.ACCOUNT_LEDGER=='tourvisor-anex'
src=P.read_text()
assert 'defined("TOURVISOR_ANEX_JWT")' in src
assert 'tourvisor_anex_account_guard' in src
assert "action in ('search_start','search_continue','flights_actualization')" in src
assert "self.day=q/f'{ACCOUNT_LEDGER}-{day}.json'" in src
assert "defined(\"TOURVISOR_JWT\")?TOURVISOR_JWT" in src  # only as distinct-account guard

# Compatible retained contexts should fill 30-hotel searches even when exact dates/nights differ.
mixed=[]
for i in range(1,61):
    mixed.append({'tv_hotel_id':2000+i,'missing_operator_ids':[13,18,25,43],
                  'departure_id':1,'country_id':4,'departure_date':f'2026-10-{1+((i-1)%20):02d}',
                  'nights':7+((i-1)%4),'adults':2,'children_count':0,'child_ages_signature':''})
groups=m.chunks(mixed)
assert [len(g['hotel_ids']) for g in groups]==[30,30]
assert all((__import__('datetime').date.fromisoformat(g['date_to'])-__import__('datetime').date.fromisoformat(g['date_from'])).days<=20 for g in groups)
