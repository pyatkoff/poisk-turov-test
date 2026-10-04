#!/usr/bin/env python3
import importlib.util
import json
import pathlib
import tempfile
import hashlib

ROOT = pathlib.Path(__file__).resolve().parents[1]
PATH = ROOT / 'scripts/diagnostics/hotel_match_anex2_selectors_readonly_v1.py'
SPEC = importlib.util.spec_from_file_location('anex2', PATH)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)
MANIFEST = ROOT/'scripts/diagnostics/fixtures/hotel_match_anex2_selectors_readonly_v1.json'
data = M.manifest(MANIFEST)
groups = M.groups(data)
assert M.HTTP_CAP == 12 and data['request']['operator_id'] == 13
assert [(g['country_id'],[r['target_tv_hotel_id'] for r in g['rows']]) for g in groups] == [(4,[109380]),(1,[159])]
assert all(r['source_namespace']=='operator_5' for r in data['rows'])
for pkey,skey in [('candidate_roster_path','candidate_roster_sha256'),('negative_ledger_path','negative_ledger_sha256')]:
    assert hashlib.sha256((ROOT/data['inputs'][pkey]).read_bytes()).hexdigest()==data['inputs'][skey]

class FakeProvider:
    def __init__(self):
        self.root=ROOT; self.calls=[]; self.target=None
    def check(self,action,rows):
        return M.current_preflight(self.root,rows)
    def call(self,action,path,params,rows):
        self.calls.append((action,path,params,rows))
        self.target=rows[0]['target_tv_hotel_id']
        if action=='search_start': return 200,{'searchId':77}
        if action=='search_status': return 200,{'status':'ready'}
        if action=='search_results': return 200,{'hotels':[{'id':self.target,'tours':[{'id':99,'operator':{'id':13}}]}]}
        if action=='tour_detail':
            return 200,{'hotel':{'id':self.target},'operator':{'id':13},'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST='+('43661' if self.target==109380 else '804,44562')}
        raise AssertionError(action)

old_preflight,old_sleep=M.current_preflight,M.time.sleep
M.current_preflight=lambda root,rows:[{'source_catalog_id':r['source_catalog_id'],'target_tv_hotel_id':r['target_tv_hotel_id'],'state':'eligible','holds':[],'safe_to_write_now':False} for r in rows]
M.time.sleep=lambda seconds:None
try:
    with tempfile.TemporaryDirectory() as tmp:
        p=FakeProvider()
        results=[M.run_group(p,pathlib.Path(tmp),i,g,data['request']) for i,g in enumerate(groups,1)]
        starts=[c[2] for c in p.calls if c[0]=='search_start']
        assert [(s['countryId'],s['hotelIds'],s['operatorIds']) for s in starts]==[(4,[109380],[13]),(1,[159],[13])]
        assert all(s['dateFrom']==s['dateTo']=='2026-11-15' and s['nightsFrom']==s['nightsTo']==7 and s['departureId']==1 and s['adults']==2 and s['childs']==[] for s in starts)
        a,b=[g['edges'][0] for g in results]
        assert a['matches_source_native'] is True and b['matches_source_native'] is False
        assert b['prior_identity_tokens']==['804','44562'] and b['raw_identity_tokens']==['804','44562']
        assert a['safe_to_write_now'] is b['safe_to_write_now'] is False
        assert len(p.calls)==8 and not any('continue' in c[1] or 'dates' in c[1] for c in p.calls)
    # One row's CURRENT HOLD does not suppress the independent country's work.
    eligible=M.current_preflight
    M.current_preflight=lambda root,rows:[dict(r,state='hold',holds=['target_occupied']) if r['target_tv_hotel_id']==109380 else r for r in eligible(root,rows)]
    with tempfile.TemporaryDirectory() as tmp:
        p=FakeProvider()
        results=[M.run_group(p,pathlib.Path(tmp),i,g,data['request']) for i,g in enumerate(groups,1)]
        assert results[0]['state']=='preflight_hold' and results[1]['returned_targets']==1
        assert len(p.calls)==4 and all(c[3][0]['target_tv_hotel_id']==159 for c in p.calls)
finally:
    M.current_preflight,M.time.sleep=old_preflight,old_sleep

for tokens in ['804,44562','-804,44562','44562,44562','44562,signed-value']:
    edge=M.native_projection('https://agent.anextour.ru/search/tour?HOTELLIST='+tokens)
    assert edge['raw_identity_values']==[tokens] and edge['raw_identity_tokens']==tokens.split(',')
    assert edge['link_state']=='captured_ambiguous_native'
assert M.native_projection('https://anextour.ru.evil.test/search?HOTELLIST=44562')['link_state']=='unexpected_anex_host'
assert M.native_projection('https://x@anextour.ru/search?HOTELLIST=44562')['link_state']=='invalid_origin'
assert not M.safe_payload({'operatorLink':'https://agent.anextour.ru/?HOTELLIST=44562&session=secret'},'jwt')

with tempfile.TemporaryDirectory() as tmp:
    p=M.Provider.__new__(M.Provider);p.day_value='2026-10-04';p.quota=pathlib.Path(tmp)
    p.day=p.quota/'tourvisor-anex-2026-10-04.json';p.lock=p.quota/'tourvisor-anex-2026-10-04.lock';p.used=0;p.tariff_used=0
    for i in range(12):p.reserve('search_status')
    ledger=json.loads(p.day.read_text())
    assert ledger['physical_http_attempts']==12 and ledger['operations'][M.OP]['physical_http_attempts']==12
    assert p.day.stat().st_mode & 0o777==0o600
    try:p.reserve('search_status');raise AssertionError('operation cap accepted')
    except RuntimeError as e:assert str(e)=='quota_exhausted'
with tempfile.TemporaryDirectory() as tmp:
    p=M.Provider.__new__(M.Provider);p.day_value='2026-10-04';p.quota=pathlib.Path(tmp)
    p.day=p.quota/'tourvisor-anex-2026-10-04.json';p.lock=p.quota/'tourvisor-anex-2026-10-04.lock';p.used=0;p.tariff_used=0
    p.day.write_text(json.dumps({'provider':M.ACCOUNT_LEDGER,'provider_day':p.day_value,'owner_daily_limit':3000,'tariff_search_units':0,'physical_http_attempts':3000,'operations':{'other-common4-operation':{'physical_http_attempts':3000}}}))
    try:p.reserve('search_status');raise AssertionError('shared daily cap accepted')
    except RuntimeError as e:assert str(e)=='quota_exhausted'
source=PATH.read_text()
assert source.index('current = self.check(action, rows)')<source.index('self.reserve(action)')
assert 'os.replace(tmp, self.day)' in source and 'read_json(self.day) != state' in source and 'fsync_dir(self.quota)' in source
assert 'path = source_root / data["inputs"][path_key]' in source
assert not any(x in source for x in ['INSERT INTO','DELETE FROM','UPDATE catalog_hotels'])
print('MATCH_ANEX2_SELECTORS_READONLY_V1_TEST_OK')
