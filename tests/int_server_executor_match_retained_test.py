#!/usr/bin/env python3
"""Offline contract, contention, projection and native MySQL acceptance."""
import ast
from concurrent.futures import ThreadPoolExecutor
import copy
import datetime as dt
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
SOURCE=Path(os.environ.get('MATCH_RETAINED_SOURCE_ROOT',ROOT))


def load(name,path):
    spec=importlib.util.spec_from_file_location(name,path)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    return module


m=load('retained_control',ROOT/'scripts/deploy/int_server_executor_match_retained.py')
q=load('retained_quota',SOURCE/'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py')
p=load('retained_projection',SOURCE/'scripts/diagnostics/hotel_match_retained_projection_v1.py')
sys.path.insert(0,str(SOURCE/'scripts/diagnostics'))
a=load('retained_acquisition',SOURCE/'scripts/diagnostics/hotel_match_retained_acquire_v1.py')
SHA='a'*40
CONTROL='b'*40
OP='int-andromeda-match-retained-20261020-v1'
QUOTA_OP='int-andromeda-match-quota5000-20261020-v1'
IDENTITY_COLUMNS=['supplier_namespace','external_hotel_id','local_hotel_id','decision_status']


def core():
    return load('retained_core_test',ROOT/'scripts/deploy/int_server_executor.py')


class ContractTest(unittest.TestCase):
    def test_write_completion_requires_nonzero_per_row_commit_readback(self):
        cmd={'mode':'match-retained-anex-write','source_sha':SHA,'operation_id':'int-andromeda-match-retained-anex-write-20261020-v1',
             'cohort':'anex7-verified','target_operation_id':'int-andromeda-match-retained-acquire-20261020-v1'}
        data={**cmd,'control_source_sha':CONTROL,'state':'committed_verified','no_replay':True,'safe_to_write_now':False,
              'new_accepted_mappings':2,'rows':[{},{}],'database_writes':2,'mapping_writes':2,'supplier_calls':0,
              'readback_verified':True,'resolver_readback_verified':True}
        result={**cmd,'status':'complete','production_unchanged':True,'supplier_calls':0,'database_writes':2,'mapping_writes':2,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()}}
        self.assertTrue(m.completed_result(cmd,result,CONTROL))
        for key,value in (('new_accepted_mappings',0),('readback_verified',False),('resolver_readback_verified',False),('mapping_writes',None)):
            changed=copy.deepcopy(result);changed['match_retained']['data'][key]=value
            changed['match_retained']['evidence_sha256']=hashlib.sha256(json.dumps(changed['match_retained']['data'],sort_keys=True,separators=(',',':')).encode()).hexdigest()
            self.assertFalse(m.completed_result(cmd,changed,CONTROL))

    def test_acquisition_completion_accounts_for_real_supplier_calls(self):
        cmd={'mode':'match-retained-acquire','source_sha':SHA,'operation_id':'int-andromeda-match-retained-acquire-20261020-v1',
             'cohort':'anex105-current','target_operation_id':'int-andromeda-match-retained-current-20261020-v1'}
        data={**cmd,'control_source_sha':CONTROL,'state':'acquired_retained_native_read_only','no_replay':True,
              'safe_to_write_now':False,'supplier_calls':3,'database_writes':0,'mapping_writes':0}
        result={**cmd,'status':'complete','production_unchanged':True,'supplier_calls':3,'database_writes':0,'mapping_writes':0,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()}}
        self.assertTrue(m.completed_result(cmd,result,CONTROL))
        for patch in ({'supplier_calls':0},{'supplier_calls':None},{'mapping_writes':1}):
            changed=copy.deepcopy(result);changed.update(patch);self.assertFalse(m.completed_result(cmd,changed,CONTROL))

    def test_current_uses_complete_project_cohort_and_preserves_parent(self):
        with tempfile.TemporaryDirectory() as td:
            private=Path(td)/'private';name='int-andromeda-match-retained-project-20261020-v2'
            target=private/name;target.mkdir(parents=True);child=private/'new-current';child.mkdir()
            project=Path(td)/'project';project.mkdir();(project/'config.php').write_text('private config fixture')
            projection={'state':'projected_read_only','cohort':'common4-retained','cohort_tv_hotel_ids':[100,200],
                        'cohort_hotels':2,'supplier_calls':0,'database_writes':0,'mapping_writes':0}
            parent={'state':'reprojected_retained_read_only','operation_id':name,'source_sha':SHA,
                    'projection':projection,'supplier_calls':0,'database_writes':0,'mapping_writes':0}
            prior={'status':'complete','mode':'match-retained-project','production_unchanged':True,
                   'operation_id':name,'source_sha':SHA,'supplier_calls':0,'database_writes':0,'mapping_writes':0,
                   'match_retained':{'data':parent,'evidence_sha256':hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()}}
            (target/'result.json').write_text(json.dumps(prior));(target/'reservation.json').write_text(json.dumps({'operation_id':name,'source_sha':SHA}))
            before={f.name:f.read_bytes() for f in target.iterdir()}
            def fail(reason):raise ValueError(reason)
            def run_current(*args,**kwargs):
                scope=json.loads((child/'current-scope.json').read_text())
                self.assertEqual([100,200],scope['tv_hotel_ids'])
                value={'state':'current_retained_read_only','parent_receipt_sha256':scope['parent_receipt_sha256'],
                       'cohort_hotels':2,'rows':[{'tv_hotel_id':100},{'tv_hotel_id':200}],
                       'safe_to_write_now':False,'supplier_calls':0,'database_writes':0,'mapping_writes':0}
                return subprocess.CompletedProcess(args,0,json.dumps(value),'')
            space={'private':private,'project':project,'op':child,'operation':'int-andromeda-match-retained-current-20261020-v1',
                   'source':SHA,'payload':{'target_operation_id':name,'cohort':'common4-retained','match_retained_control_sha':CONTROL},
                   'result':{},'re':__import__('re'),'json':json,'hashlib':hashlib,'os':os,'subprocess':subprocess,'fail':fail,
                   'safe_json':lambda path,bound:json.loads(path.read_text()),
                   'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound}
            exec(m.REMOTE_HANDLER,space)
            with mock.patch.object(subprocess,'run',side_effect=run_current):
                result=space['current_retained_target'](SOURCE)
            self.assertEqual('current_retained_read_only',result['data']['state'])
            self.assertEqual(before,{f.name:f.read_bytes() for f in target.iterdir()})
            prior['status']='unknown_no_replay';(target/'result.json').write_text(json.dumps(prior))
            with self.assertRaisesRegex(ValueError,'parent_not_complete'):space['current_retained_target'](SOURCE)

    def test_saved_projection_requires_complete_parent_and_preserves_its_files(self):
        with tempfile.TemporaryDirectory() as td:
            private=Path(td)/'private';target=private/OP;target.mkdir(parents=True)
            project=Path(td)/'project';project.mkdir();child=private/'new-projection';child.mkdir()
            scope={'kind':'scope','cohort':'common4-retained','captured_at_utc':'2026-10-20T00:00:00Z',
                'database_snapshot':'repeatable-read-read-only','schema_columns':{'andromeda_hotel_identities':IDENTITY_COLUMNS}}
            raw=(json.dumps(scope)+'\n').encode();(target/'retained-raw.ndjson').write_bytes(raw)
            capture={'state':'captured_read_only','raw_sha256':hashlib.sha256(raw).hexdigest(),
                     'raw_bytes':len(raw),'rows':{k:0 for k in ('observation','legacy_source','identity','target')}}
            prior={'operation_id':OP,'mode':'match-retained-intake','status':'complete','production_unchanged':True,
                   'supplier_calls':0,'database_writes':0,'mapping_writes':0,
                   'match_retained':{'data':{'operation_id':OP,'source_sha':SHA,'capture':capture}}}
            prior['match_retained']['evidence_sha256']=hashlib.sha256(json.dumps(prior['match_retained']['data'],sort_keys=True,separators=(',',':')).encode()).hexdigest()
            (target/'reservation.json').write_text(json.dumps({'operation_id':OP,'source_sha':SHA}))
            (target/'result.json').write_text(json.dumps(prior))
            before={p.name:p.read_bytes() for p in target.iterdir()}
            def fail(reason):raise ValueError(reason)
            space={'private':private,'project':project,'op':child,'operation':'int-andromeda-match-retained-project-20261020-v1',
                'source':SHA,'payload':{'target_operation_id':OP,'cohort':'common4-retained','match_retained_control_sha':CONTROL},
                're':__import__('re'),'json':json,'hashlib':hashlib,'os':os,'subprocess':subprocess,'fail':fail,
                'safe_json':lambda path,bound:json.loads(path.read_text()),
                'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound}
            exec(m.REMOTE_HANDLER,space);result=space['project_retained_target'](SOURCE)
            self.assertEqual('reprojected_retained_read_only',result['data']['state'])
            self.assertEqual(before,{p.name:p.read_bytes() for p in target.iterdir()})
            prior['status']='unknown_no_replay';(target/'result.json').write_text(json.dumps(prior))
            with self.assertRaisesRegex(ValueError,'parent_not_complete'):space['project_retained_target'](SOURCE)

    def test_readback_preserves_failed_receipt_and_never_loads_unreviewed_helper(self):
        with tempfile.TemporaryDirectory() as td:
            private=Path(td)/'private';target=private/OP;target.mkdir(parents=True)
            project=Path(td)/'project';(project/'data').mkdir(parents=True)
            (project/'data/db-v1.php').write_text('unreviewed helper')
            reservation={'operation_id':OP,'source_sha':SHA}
            prior={**reservation,'status':'unknown_no_replay','supplier_calls':0,'database_writes':0,'reason':'retained_child_failed_no_replay'}
            (target/'reservation.json').write_text(json.dumps(reservation));(target/'result.json').write_text(json.dumps(prior))
            (target/'retained-raw.ndjson').write_text('{"kind":"observation","operator_link":"private raw"}\n{bad-partial-json')
            before={p.name:p.read_bytes() for p in target.iterdir()}
            def fail(reason):raise ValueError(reason)
            space={'private':private,'project':project,'payload':{'target_operation_id':OP},'re':__import__('re'),
                   'json':json,'hashlib':hashlib,'fail':fail,
                   'safe_json':lambda path,bound:json.loads(path.read_text()),
                   'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound}
            exec(m.REMOTE_HANDLER,space)
            result=space['retained_readback']()
            self.assertEqual('reconciled_read_only',result['state'])
            self.assertEqual({'observation':1,'invalid_json':1},result['raw_row_kinds'])
            self.assertNotIn('database_schema',result)
            self.assertNotIn('private raw',json.dumps(result))
            self.assertEqual(before,{p.name:p.read_bytes() for p in target.iterdir()})

    def test_closed_parser_keeps_old_modes_and_rejects_scope_expansion(self):
        c=core();original=c.parse_command(c.PREFIX+SHA+' local-readback int-andromeda-read-local-test-v1 1 4 2026-10-21 2026-10-22 7 2 AI 0')
        m.register_parser(c)
        self.assertEqual(original,c.parse_command(c.PREFIX+SHA+' local-readback int-andromeda-read-local-test-v1 1 4 2026-10-21 2026-10-22 7 2 AI 0'))
        for mode,cohort in m.MODES.items():
            op={'match-retained-intake':OP,'match-retained-readback':'int-andromeda-match-retained-readback-20261020-v1','match-retained-project':'int-andromeda-match-retained-project-20261020-v1','match-tourvisor-quota-policy':QUOTA_OP,'match-retained-current':'int-andromeda-match-retained-current-20261020-v1','match-retained-acquire':'int-andromeda-match-retained-acquire-20261020-v1','match-retained-anex-write':'int-andromeda-match-retained-anex-write-20261020-v1','match-retained-anex-write-v2':'int-andromeda-match-retained-anex-write-20261020-v2'}[mode]
            command=c.PREFIX+SHA+' '+mode+' '+op+' '+cohort+(' '+('int-andromeda-match-retained-acquire-20261020-v1' if mode in ('match-retained-anex-write','match-retained-anex-write-v2') else 'int-andromeda-match-retained-current-20261020-v1' if mode=='match-retained-acquire' else 'int-andromeda-match-retained-project-20261020-v2' if mode=='match-retained-current' else OP) if mode in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2') else '')
            parsed=c.parse_command(command)
            self.assertEqual(cohort,parsed['cohort'])
            for bad in (command+' extra',command.replace(cohort,'arbitrary-sql'),command.replace(op,op+'-replay')):
                with self.assertRaises(ValueError):c.parse_command(bad)
            with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}):
                with self.assertRaises(ValueError):c.parse_command(command)

    def test_exact_source_bundle_and_tamper_rejection(self):
        bundle,hashes=m.bundle_source(SOURCE)
        self.assertEqual(m.SOURCE_HASHES,hashes)
        with tarfile.open(fileobj=io.BytesIO(bundle)) as archive:
            self.assertEqual(set(m.SOURCE_FILES)|{'manifest.json'},set(archive.getnames()))
        with tempfile.TemporaryDirectory() as td:
            for rel in m.SOURCE_FILES:
                target=Path(td)/rel;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes((SOURCE/rel).read_bytes())
            target.write_bytes(target.read_bytes()+b'\n')
            with self.assertRaisesRegex(ValueError,'reviewed_source_changed'):m.bundle_source(td)

    def test_stock_remote_registration_cannot_dispatch_supplier_collector(self):
        for mode,cohort in m.MODES.items():
            c=core();m.register_parser(c)
            op={'match-retained-intake':OP,'match-retained-readback':'int-andromeda-match-retained-readback-20261020-v1','match-retained-project':'int-andromeda-match-retained-project-20261020-v1','match-tourvisor-quota-policy':QUOTA_OP,'match-retained-current':'int-andromeda-match-retained-current-20261020-v1','match-retained-acquire':'int-andromeda-match-retained-acquire-20261020-v1','match-retained-anex-write':'int-andromeda-match-retained-anex-write-20261020-v1','match-retained-anex-write-v2':'int-andromeda-match-retained-anex-write-20261020-v2'}[mode]
            cmd=c.parse_command(c.PREFIX+SHA+' '+mode+' '+op+' '+cohort+(' '+('int-andromeda-match-retained-acquire-20261020-v1' if mode in ('match-retained-anex-write','match-retained-anex-write-v2') else 'int-andromeda-match-retained-current-20261020-v1' if mode=='match-retained-acquire' else 'int-andromeda-match-retained-project-20261020-v2' if mode=='match-retained-current' else OP) if mode in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2') else ''))
            with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,cmd)
            ast.parse(c.REMOTE)
            self.assertEqual(2,c.REMOTE.count("if mode not in ('match-retained-intake','match-tourvisor-quota-policy','match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2','reconcile',"))
            self.assertIn("globals().get('retained_reserved') is True",c.REMOTE)
            self.assertNotIn("if 'op' in globals() and op.exists():",c.REMOTE)
            self.assertEqual(CONTROL,cmd['match_retained_control_sha'])
            self.assertIn("files!="+repr(m.SOURCE_HASHES),c.REMOTE)

    def test_completion_rejects_wrong_identity_writes_or_digest(self):
        cmd={'mode':'match-retained-intake','source_sha':SHA,'operation_id':OP,'cohort':'common4-retained'}
        data={**cmd,'control_source_sha':CONTROL,'state':'captured_and_projected_read_only',
              'supplier_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
        result={**cmd,'status':'complete','production_unchanged':True,
                'supplier_calls':0,'database_writes':0,'mapping_writes':0,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()}}
        self.assertTrue(m.completed_result(cmd,result,CONTROL))
        for change in ({'supplier_calls':1},{'database_writes':True},{'source_sha':'c'*40},{'production_unchanged':False}):
            other=copy.deepcopy(result);other.update(change);self.assertFalse(m.completed_result(cmd,other,CONTROL))
        other=copy.deepcopy(result);other['match_retained']['data']['safe_to_write_now']=True
        self.assertFalse(m.completed_result(cmd,other,CONTROL))


class QuotaTest(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
        self.now=dt.datetime(2026,10,20,12,tzinfo=dt.timezone.utc)
        self.quota=q.Quota(Path(self.tmp.name),lambda:self.now)
        self.quota.root.mkdir(parents=True)
        self.original={'provider':'tourvisor-anex','provider_day':self.quota.day,'owner_daily_limit':3000,
                       'physical_http_attempts':4980,'tariff_search_units':120,
                       'operations':{'old':{'status':'unknown_no_replay','physical_http_attempts':3}},
                       'baseline_status':'not_measured','custom_protected':{'retained':True}}
        self.quota.path.write_bytes(q.encoded(self.original))

    def test_policy_preserves_inode_all_usage_operations_and_unknown(self):
        inode=self.quota.path.stat().st_ino;receipt=self.quota.apply_policy();after=json.loads(self.quota.path.read_text())
        for k,v in self.original.items():
            if k!='owner_daily_limit':self.assertEqual(v,after[k])
        self.assertEqual(inode,self.quota.path.stat().st_ino)
        self.assertEqual(20,receipt['local_remaining_upper_bound'])
        self.assertEqual('not_measured',receipt['global_usage'])
        self.assertEqual(5000,after['owner_daily_limit'])
        self.assertEqual(5000,after['physical_http_daily_limit'])

    def test_concurrent_reservations_never_cross_daily_physical_cap(self):
        self.quota.apply_policy()
        def reserve(i):
            try:self.quota.reserve(OP,i,'search_results');return True
            except ValueError as e:
                self.assertEqual('quota_exhausted',str(e));return False
        with ThreadPoolExecutor(max_workers=6) as pool:outcomes=list(pool.map(reserve,range(1,41)))
        self.assertEqual(20,sum(outcomes))
        after=json.loads(self.quota.path.read_text())
        self.assertEqual(5000,after['physical_http_attempts']);self.assertEqual(120,after['tariff_search_units'])
        self.assertEqual(20,len(after['physical_http_reservations']))

    def test_reservation_is_charged_before_unknown_outcome_and_never_replayed(self):
        self.quota.apply_policy();self.quota.reserve(OP,1,'search_start')
        state=json.loads(self.quota.path.read_text())
        self.assertEqual(121,state['tariff_search_units'])
        self.assertEqual('reserved_unknown_no_replay',state['physical_http_reservations'][OP+':1']['state'])
        self.quota.complete(OP,1,'failed_unknown_no_replay')
        with self.assertRaisesRegex(ValueError,'request_no_replay'):self.quota.reserve(OP,1,'search_start')
        self.assertEqual(4981,json.loads(self.quota.path.read_text())['physical_http_attempts'])

    def test_empty_or_wrong_day_state_is_not_reset(self):
        for data in (b'',q.encoded({**self.original,'provider_day':'2026-10-19'})):
            self.quota.path.write_bytes(data)
            with self.assertRaises((ValueError,KeyError)):self.quota.apply_policy()
            self.assertEqual(data,self.quota.path.read_bytes())

    def test_provider_day_rollover_and_symlink_are_rejected(self):
        self.now+=dt.timedelta(days=1)
        with self.assertRaisesRegex(ValueError,'day_changed'):self.quota.apply_policy()
        self.now-=dt.timedelta(days=1);self.quota.path.unlink();other=Path(self.tmp.name)/'protected';other.write_text('protected');self.quota.path.symlink_to(other)
        with self.assertRaises(OSError):self.quota.apply_policy()
        self.assertEqual('protected',other.read_text())


class ProjectionTest(unittest.TestCase):
    def test_link_audit_separates_missing_empty_and_unrecognized_values(self):
        missing=self.observation();del missing['row']['operator_link']
        rows=[missing,*[self.observation(link=x) for x in
              (None,'',{},'not-an-absolute-url','https://unknown.example/a?hotel=123')]]
        result=self.fixture(rows)
        audit=result['retained_link_audit']['observations_by_operator']['25']
        self.assertEqual(6,audit['observations'])
        self.assertEqual({'field_missing':1,'null':1,'empty':1,'non_string':1,
            'relative_or_missing_host':1,'absolute_http_url':1},audit['operator_link']['states'])
        self.assertEqual({'field_missing':6},audit['native_field_states'])
        self.assertEqual([],result['candidate_edges'])

    def test_link_audit_never_publishes_url_text_or_query_values(self):
        secret='private-sentinel-never-publish'
        url='https://unknown.example/'+secret+'?token='+secret
        source_raw=json.dumps({'nested':[{'operatorLink':url},{'operator_link':None}]})
        source={'namespace':'unbound','external_key':'1','source_json':source_raw,
                'source_sha256':hashlib.sha256(source_raw.encode()).hexdigest()}
        legacy={'kind':'legacy_source','old_local_id':1,'tv_hotel_id':1546,
                'snapshot_sha256':'a'*64,'source':source}
        result=self.fixture([self.observation(link=url),legacy])
        audit=result['retained_link_audit']
        self.assertEqual({'absolute_http_url':1,'null':1},audit['legacy_operator_link_fields']['states'])
        self.assertEqual(1,audit['legacy_sources_scanned'])
        self.assertNotIn(secret,json.dumps(result));self.assertNotIn('unknown.example',json.dumps(audit))
        self.assertFalse(audit['acceptance_rules_changed'])

    def test_absent_operator_name_and_stored_native_field_are_preserved(self):
        row=self.observation(link=None);row['row'].update(operator_name=None,native_id_type='HOTELLIST',native_id_value='126268')
        result=self.fixture([row]);self.assertEqual(1,result['observed_operators'][0]['with_stored_native_field'])
        self.assertEqual('retained_code_hint_not_account_access_proof',result['observed_operators'][0]['family_binding'])
        self.assertEqual('observer_native:25:HOTELLIST',result['candidate_edges'][0]['namespace'])
        self.assertEqual('needs_independent_namespace_bridge',result['candidate_edges'][0]['state'])
        row['row']['operator_id']=999
        result=self.fixture([row]);self.assertEqual(1,result['observed_operators'][0]['observations'])
        self.assertEqual('unknown',result['observed_operators'][0]['family_binding'])
        self.assertEqual([],result['candidate_edges'])

    def fixture(self,extras,registry=True):
        scope={'kind':'scope','cohort':'common4-retained','captured_at_utc':'2026-10-20T00:00:00Z',
               'database_snapshot':'repeatable-read-read-only','schema_columns':{'andromeda_hotel_identities':IDENTITY_COLUMNS if registry else []}}
        with tempfile.TemporaryDirectory() as td:
            path=Path(td)/'raw.ndjson';path.write_text(''.join(json.dumps(r,ensure_ascii=False)+'\n' for r in [scope,*extras]))
            return p.project(path)

    def observation(self,link='https://fun-sun.ru/hotel/126268',tv=1546):
        return {'kind':'observation','row':{'id':tv,'hotel_id':tv,'operator_id':25,'operator_name':'FUN&SUN',
                'operator_link':link,'first_seen_at':'2026-10-01','last_seen_at':'2026-10-19'}}

    def test_url_namespace_is_not_accepted_samo_namespace(self):
        result=self.fixture([self.observation()]);edge=result['candidate_edges'][0]
        self.assertEqual('operator_url:fun-sun.ru:hotel-path',edge['namespace'])
        self.assertEqual('needs_independent_namespace_bridge',edge['state'])
        self.assertIs(False,edge['safe_to_write_now']);self.assertEqual(0,result['new_accepted_mappings'])

    def test_conflicting_targets_and_secret_bearing_urls_are_held(self):
        result=self.fixture([self.observation(tv=1546),self.observation(tv=1584)])
        self.assertEqual({'hold_multiple_targets':2},result['candidate_states'])
        for url in ('https://fun-sun.ru/hotel/123?token=secret','https://evil.ru/hotel/123',
                    'https://u:p@fun-sun.ru/hotel/123','https://fun-sun.ru/hotel/123#secret'):
            self.assertEqual([],p.native_link(url))

    def test_missing_registry_is_unknown_and_cannot_be_reported_as_zero_coverage(self):
        result=self.fixture([self.observation()],registry=False)
        self.assertEqual({'needs_current_registry_read':1},result['candidate_states'])
        self.assertIsNone(result['registry_coverage'][0]['accepted_rows'])

    def test_raw_source_integrity_and_exact_namespace_ownership(self):
        raw='{"hotel":12}';source={'namespace':'operator_315','external_key':'12','source_json':raw,'source_sha256':hashlib.sha256(raw.encode()).hexdigest()}
        row={'kind':'legacy_source','old_local_id':736,'tv_hotel_id':1546,'snapshot_sha256':'a'*64,'source':source}
        accepted={'kind':'identity','row':{'supplier_namespace':'operator_315','external_hotel_id':'12','local_hotel_id':1546,'decision_status':'accepted'}}
        result=self.fixture([row,accepted]);self.assertEqual({'already_accepted':1},result['candidate_states'])
        row['source']['source_json']='tampered'
        with self.assertRaisesRegex(ValueError,'source_integrity'):self.fixture([row])

    def test_materialized_bridge_requires_digest_and_current_acceptance(self):
        value={'schema_version':1,'provider':'andromeda','supplier_namespace':'operator_315',
               'external_hotel_id':'12','accepted_local_hotel_id':1546}
        raw=json.dumps(value);source={'namespace':'provider_ref_digest:andromeda',
            'external_key':hashlib.sha256(b'operator_315:12').hexdigest(),'acquired_via':'match_accepted_bridge',
            'source_json':raw,'source_sha256':hashlib.sha256(raw.encode()).hexdigest()}
        row={'kind':'legacy_source','old_local_id':736,'tv_hotel_id':1546,'snapshot_sha256':'a'*64,'source':source}
        self.assertEqual({'hold_stale_accepted_bridge':1},self.fixture([row])['candidate_states'])
        accepted={'kind':'identity','row':{'supplier_namespace':'operator_315','external_hotel_id':'12','local_hotel_id':1546,'decision_status':'accepted'}}
        self.assertEqual({'already_accepted':1},self.fixture([row,accepted])['candidate_states'])
        source['external_key']='0'*64
        self.assertEqual([],self.fixture([row,accepted])['candidate_edges'])


class ContinuationTest(unittest.TestCase):
    def fixture(self):
        scope=json.loads((SOURCE/a.SCOPE_FILE).read_text())
        rows=[]
        for hid in (101,102):
            ctx={'country_id':2,'departure_id':1,'adults':2,'children_count':0,
                 'child_ages_signature':'','departure_date':'2099-01-03','nights':9,'hotel_id':hid,
                 'source':'user_search','observed_at':'2026-10-20 00:01:02'}
            rows.append({'tv_hotel_id':hid,'target':{'country_id':2},'future_context':ctx,
                         'acquisition_ready':True,'direct_anex_native_ids':[],
                         'anchor_state':'canonical_anchor_ok'})
        current={'rows':rows}
        scope.update(selected_tv_hotel_ids=[101,102],selected_count=2,
            country_id=2,
            parent_evidence_sha256=hashlib.sha256(a.canonical(current)).hexdigest(),
            current_row_sha256={str(r['tv_hotel_id']):hashlib.sha256(a.canonical(r)).hexdigest() for r in rows},
            future_contexts={str(r['tv_hotel_id']):r['future_context'] for r in rows})
        scope['scope_id']=hashlib.sha256(a.canonical({k:scope[k] for k in
            ('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts')})).hexdigest()
        scope['cohort']='anex-next-'+scope['scope_id'][:16]
        return scope,current

    def test_all_selected_rows_bound_to_parent_context_and_never_selected(self):
        scope,current=self.fixture()
        self.assertEqual([101,102],[r['tv_hotel_id'] for r in a.select_scope_rows(scope,current,'2026-10-20')])
        for field,value in (('account','TOURVISOR_JWT'),('operator_id',18),('selected_count',True),
                            ('children_count',False),('scope_id','0'*64),
                            ('prior_selected_hotel_ids',[101]),('selected_tv_hotel_ids',[101,101])):
            bad=copy.deepcopy(scope);bad[field]=value
            with self.assertRaises(ValueError):a.select_scope_rows(bad,current,'2026-10-20')
        for field,value in (('acquisition_ready',False),('direct_anex_native_ids',[5844]),
                            ('anchor_state','source_ambiguous')):
            bad=copy.deepcopy(current);bad['rows'][0][field]=value
            with self.assertRaises(ValueError):a.select_scope_rows(scope,bad,'2026-10-20')
        with self.assertRaises(ValueError):a.select_scope_rows(scope,current,'2099-01-04')

    def test_shared_selection_reservation_blocks_rename_day_and_concurrent_replay(self):
        scope,_=self.fixture()
        with tempfile.TemporaryDirectory() as td:
            home=Path(td);(home/'.anytoour-match').mkdir()
            ledger=home/'.anytoour-match/retained-selection-claims.json'
            with self.assertRaises(FileNotFoundError):a.reserve_selection(home,scope,'missing-ledger')
            ledger.write_bytes(a.canonical({'schema_version':1,'claims':{
                'TOURVISOR_ANEX_JWT:13:'+str(hid):{'state':'prior_consumed'}
                for hid in scope['prior_selected_hotel_ids']}}))
            def reserve(op):
                try:a.reserve_selection(home,scope,op);return True
                except ValueError:return False
            with ThreadPoolExecutor(max_workers=2) as pool:
                got=list(pool.map(reserve,['new-operation-one','new-operation-renamed']))
            self.assertEqual(1,sum(got))
            ledger=home/'.anytoour-match/retained-selection-claims.json';before=ledger.read_bytes()
            with self.assertRaisesRegex(ValueError,'consumed_no_replay'):
                a.reserve_selection(home,scope,'different-operation-next-day')
            self.assertEqual(before,ledger.read_bytes())
            state=json.loads(before)
            self.assertEqual('prior_consumed',state['claims']['TOURVISOR_ANEX_JWT:13:'+str(scope['prior_selected_hotel_ids'][0])]['state'])

    def test_negative_history_preserves_unknown_and_blocks_old_target_reselection(self):
        with tempfile.TemporaryDirectory() as td:
            private=Path(td);history=[]
            for number,state,unknown,ids in [(1,'completed',0,[11]),(2,'partial_no_replay',1,[12,13])]:
                name='int-andromeda-match-retained-acquire-20261020-v'+str(number)
                target=private/name;target.mkdir()
                data={'operation_id':name,'source_sha':SHA,'acquisition_state':state,
                      'unknown_http_calls':unknown,'selected_hotels':len(ids),'database_writes':0,'mapping_writes':0}
                digest=hashlib.sha256(a.canonical(data)).hexdigest()
                wrapper={'status':'complete','mode':'match-retained-acquire','production_unchanged':True,
                         'operation_id':name,'source_sha':SHA,'database_writes':0,'mapping_writes':0,
                         'match_retained':{'data':data,'evidence_sha256':digest}}
                for file,value in [('result.json',wrapper),('reservation.json',{'operation_id':name,'source_sha':SHA}),
                                   ('acquisition-plan.json',{'hotel_ids':ids})]:
                    (target/file).write_bytes(a.canonical(value))
                history.append({'operation_id':name,'selected_tv_hotel_ids':ids,'evidence_sha256':digest,
                                'acquisition_state':state,'unknown_http_calls':unknown})
            def fail(reason):raise ValueError(reason)
            space={'private':private,'safe_json':lambda path,bound:json.loads(path.read_text()),
                   'hashlib':hashlib,'json':json,'re':__import__('re'),'fail':fail}
            exec(m.REMOTE_HANDLER,space)
            scope={'exclusion_history':history,'prior_selected_hotel_ids':[11,12,13],'selected_tv_hotel_ids':[14]}
            before={p:p.read_bytes() for p in private.rglob('*.json')}
            self.assertEqual(2,len(space['retained_prior_selection'](scope)))
            self.assertEqual(before,{p:p.read_bytes() for p in private.rglob('*.json')})
            for change in [('unknown',0),('state','completed'),('roster',[12])]:
                bad=copy.deepcopy(scope)
                if change[0]=='unknown':bad['exclusion_history'][1]['unknown_http_calls']=change[1]
                elif change[0]=='state':bad['exclusion_history'][1]['acquisition_state']=change[1]
                else:bad['selected_tv_hotel_ids']=change[1]
                with self.assertRaises(ValueError):space['retained_prior_selection'](bad)

    def test_exact_context_grouping_keeps_saved_dates_and_nights_separate(self):
        scope,current=self.fixture();current['rows'][1]['future_context'].update(departure_date='2099-01-04',nights=12)
        class C:
            calls=reserved=tariff=unknown=0;counts=__import__('collections').Counter();before={}
            seen=[]
            def call(self,action,path,params):
                self.calls+=1;self.reserved+=1;self.counts[action]+=1;self.tariff+=int(action=='search_start')
                self.seen.append((action,params))
                value={'searchId':777} if action=='search_start' else {'progress':100} if action=='search_status' else {'hotels':[]}
                return 200,value,{}
        with tempfile.TemporaryDirectory() as td:
            client=C();a.acquire(client,Path(td),current['rows'],sleep=lambda _:None,scope=scope)
        starts=[params for action,params in client.seen if action=='search_start']
        self.assertEqual(2,len(starts))
        self.assertEqual([('2099-01-03',9,[101]),('2099-01-04',12,[102])],
                         [(x['dateFrom'],x['nightsFrom'],x['hotelIds']) for x in starts])
        self.assertTrue(all(x['dateFrom']==x['dateTo'] and x['nightsFrom']==x['nightsTo'] for x in starts))

    def test_continuation_country_and_real_context_reach_existing_api_without_continue(self):
        scope,current=self.fixture()
        class C:
            calls=reserved=tariff=unknown=0
            counts=__import__('collections').Counter();before={'physical_http_attempts':38}
            seen=[]
            def call(self,action,path,params):
                self.calls+=1;self.reserved+=1;self.counts[action]+=1;self.tariff+=int(action=='search_start');self.seen.append((action,path,params))
                if action=='search_start':value={'searchId':777}
                elif action=='search_status':value={'progress':100}
                elif action=='search_results':value={'hotels':[{'id':101,'tours':[{'id':888,'operator':13}]},{'id':102,'tours':[{'id':889,'operator':13}]}]}
                elif path.endswith('888'):value={'hotel':101,'operator':13,'operatorLink':'https://anextour.ru/?HOTELLIST=5844'}
                else:value={'hotel':102,'operator':13,'operatorLink':None}
                return 200,value,{'response_number':self.calls,'raw_sha256':'a'*64}
        with tempfile.TemporaryDirectory() as td:
            client=C();result=a.acquire(client,Path(td),current['rows'],sleep=lambda _:None,scope=scope)
        self.assertEqual({'verified_native_pair':1,'native_link_unresolved':1},result['edge_states'])
        start=client.seen[0][2]
        self.assertEqual((2,1,2,[],[13]),tuple(start[k] for k in ('countryId','departureId','adults','childs','operatorIds')))
        self.assertEqual(('2099-01-03','2099-01-03',9,9),tuple(start[k] for k in ('dateFrom','dateTo','nightsFrom','nightsTo')))
        self.assertEqual([101,102],start['hotelIds']);self.assertLessEqual(len(start['hotelIds']),30)
        self.assertFalse(any('continue' in path for _,path,_ in client.seen))

    def test_existing_modes_admit_only_reviewed_continuation_not_renamed_frozen_scope(self):
        c=core();m.register_parser(c)
        for mode,op,parent in [('match-retained-acquire',m.NEXT_SCOPE['operation_id'],m.NEXT_SCOPE['parent_operation_id']),
                               ('match-retained-anex-write-v2',m.NEXT_SCOPE['write_operation_id'],m.NEXT_SCOPE['operation_id'])]:
            c=core();m.register_parser(c)
            command=c.PREFIX+' '.join((SHA,mode,op,m.NEXT_SCOPE['cohort'],parent))
            parsed=c.parse_command(command);self.assertEqual(m.NEXT_SCOPE['cohort'],parsed['cohort'])
            with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,dict(parsed))
            ast.parse(c.REMOTE)
            for bad in (command.replace(op,op+'-replay'),command.replace(parent,parent+'0'),
                        command.replace(m.NEXT_SCOPE['cohort'],m.MODES[mode])):
                with self.assertRaises(ValueError):c.parse_command(bad)

    def test_public_completion_requires_scope_digest_and_full_new_write_readback(self):
        cmd={'mode':'match-retained-anex-write-v2','source_sha':SHA,'operation_id':m.NEXT_SCOPE['write_operation_id'],
             'cohort':m.NEXT_SCOPE['cohort'],'target_operation_id':m.NEXT_SCOPE['operation_id']}
        data={**cmd,'control_source_sha':CONTROL,'state':'committed_verified','supplier_calls':0,
              'database_writes':8,'mapping_writes':8,'new_accepted_mappings':8,'rows':[{}]*8,
              'readback_verified':True,'resolver_readback_verified':True,'no_replay':True,'safe_to_write_now':False,
              'continuation_scope_sha256':m.NEXT_SCOPE['sha256'],'scope_id':m.NEXT_SCOPE['scope_id']}
        result={**cmd,'status':'complete','production_unchanged':True,'supplier_calls':0,'database_writes':8,'mapping_writes':8,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(a.canonical(data)).hexdigest()}}
        self.assertTrue(m.completed_result(cmd,result,CONTROL))
        for key,value in [('scope_id','f'*64),('continuation_scope_sha256','f'*64),('resolver_readback_verified',False)]:
            bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
            bad['match_retained']['evidence_sha256']=hashlib.sha256(a.canonical(bad['match_retained']['data'])).hexdigest()
            self.assertFalse(m.completed_result(cmd,bad,CONTROL))


class AcquisitionTest(unittest.TestCase):
    def test_exact_anex_namespace_identity_and_sensitive_link_holds(self):
        detail={'hotel':{'id':1546},'operator':{'id':13},'operatorLink':'https://agent.anextour.ru/search?HOTELLIST=5844'}
        value=a.native_pair(detail,1546)
        self.assertEqual('verified_native_pair',value['state']);self.assertEqual('anex_online',value['namespace'])
        self.assertEqual('5844',value['anex_hotel_id'])
        self.assertEqual('detail_identity_mismatch',a.native_pair(detail,1584)['state'])
        for link in ('https://evil.example/?HOTELLIST=5844','https://anextour.ru/?HOTELLIST=5844&token=secret',
                     'https://anextour.ru/?HOTELLIST=5844&hotelCode=999','https://anextour.ru:444/?HOTELLIST=5844'):
            self.assertNotEqual('verified_native_pair',a.native_pair({**detail,'operatorLink':link},1546)['state'])

    def test_raw_body_precedes_parsing_and_request_contains_no_token(self):
        with tempfile.TemporaryDirectory() as td:
            directory=Path(td);events=[]
            class Q:
                def reserve(self,*args):events.append(('reserve',args))
                def complete(self,*args):events.append(('complete',args))
            class R:
                code=200
                def __enter__(self):return self
                def __exit__(self,*args):pass
                def read(self,*args):return b'raw response before parsing'
            client=a.Client.__new__(a.Client);client.q=Q();client.directory=directory;client.operation='test'
            client.reserved=client.calls=client.tariff=client.unknown=0;client.counts=__import__('collections').Counter()
            client.deadline=__import__('time').monotonic()+60;client.token='private-fixture-token'
            client.open=mock.Mock();client.open.open.return_value=R()
            def parse(raw):
                self.assertEqual(raw,(directory/'response-0001.bin').read_bytes());raise ValueError('bad-json')
            with mock.patch.object(a.json,'loads',side_effect=parse):
                with self.assertRaisesRegex(ValueError,'response_json_invalid'):client.call('search_results','/tours/search/123',{'limit':10000})
            self.assertEqual(1,client.calls);self.assertEqual('reserve',events[0][0]);self.assertEqual('response_received',events[-1][1][-1])
            self.assertNotIn(client.token,(directory/'request-0001.json').read_text())

    def test_only_selected_operator_hotel_is_detailed_once(self):
        class C:
            calls=reserved=tariff=unknown=0
            counts=__import__('collections').Counter();before={'physical_http_attempts':0}
            def call(self,action,path,params):
                self.calls+=1;self.reserved+=1;self.counts[action]+=1;self.tariff+=int(action=='search_start')
                if action=='search_start':value={'searchId':777};self.start=params
                elif action=='search_status':value={'progress':100}
                elif action=='search_results':value={'hotels':[{'id':1546,'tours':[{'id':888,'operator':13}]},{'id':1546,'tours':[{'id':888,'operator':13}]},{'id':999,'tours':[{'id':889,'operator':13}]}]}
                else:value={'hotel':1546,'operator':13,'operatorLink':'https://anextour.ru/?HOTELLIST=5844'}
                return 200,value,{'response_number':self.calls,'raw_sha256':'a'*64}
        selected=[{'tv_hotel_id':1546,'future_context':{'departure_date':'2026-11-02','nights':7}}]
        with tempfile.TemporaryDirectory() as td:
            client=C();result=a.acquire(client,Path(td),selected,sleep=lambda _:None)
        self.assertEqual([13],client.start['operatorIds']);self.assertEqual([1546],client.start['hotelIds'])
        self.assertEqual({'verified_native_pair':1},result['edge_states']);self.assertEqual(4,result['supplier_calls'])
        self.assertEqual('verified_live_operator13_anextour_link',result['operator_account_binding']['state'])


@unittest.skipUnless(os.environ.get('MATCH_RETAINED_NATIVE_CI')=='1','native MySQL contract runs in stock CI')
class NativeMySQLTest(unittest.TestCase):
    def test_guarded_writer_commit_holds_rollback_and_unknown_ack(self):
        code=r'''define('MATCH_RETAINED_ANEX_WRITE_LIBRARY_ONLY',true);require $argv[1];
        class FixtureWritePDO extends PDO{
          public bool $failInsert=false;public bool $loseAck=false;
          public function prepare(string $query,array $options=[]):PDOStatement|false{if($this->failInsert&&str_starts_with($query,'INSERT INTO anex_hotel_search_mappings'))throw new PDOException('fictional insert failure');return parent::prepare($query,$options);}
          public function commit():bool{$v=parent::commit();if($this->loseAck)throw new PDOException('fictional lost commit acknowledgement');return $v;}
        }
        $db=new FixtureWritePDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $name='match_write_test_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
        try{
          $db->exec('CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name TEXT,country_id INT,country_name TEXT,region_id INT,region_name TEXT,subregion_id INT,subregion_name TEXT,category TEXT,latitude DECIMAL(10,7),longitude DECIMAL(10,7),is_active INT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class TEXT,scope TEXT,approval_policy TEXT,source_row_digest CHAR(64),mapping_digest CHAR(64),enabled INT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT PRIMARY KEY,decision_status TEXT,catalog_hotel_id INT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anex_search_hotel_observations(anex_hotel_id INT PRIMARY KEY) ENGINE=InnoDB');
          $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(80),external_hotel_id VARCHAR(80),local_hotel_id INT,decision_status VARCHAR(40),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json TEXT,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB');
          $st=$db->prepare("INSERT INTO catalog_hotels VALUES(?,'Fictional hotel',4,'Turkey',1,'Region',1,'Subregion','5',36,32,?)");
          foreach([100,200,300,400,500,600,700,800,900]as$id)$st->execute([$id,$id===700?0:1]);
          $st=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,?,'accepted',?,?,?)");
          foreach([100,200,300,400,500,600,700,800]as$id)$st->execute([(string)$id,$id,str_repeat('a',64),hash('sha256','{}'),'{}']);
          $pairs=[];
          foreach([111=>100,112=>200,113=>300,114=>400,115=>500,116=>600,117=>700,118=>800]as$native=>$tv){
            $target=hmc4aw_query($db,'SELECT * FROM catalog_hotels WHERE id=?',[$tv])[0];
            $anchor=hmc4a_anchor_state(hmc4aw_query($db,'SELECT * FROM andromeda_hotel_identities WHERE local_hotel_id=?',[$tv]));
            $pairs[]=['edge'=>['state'=>'verified_native_pair','anex_hotel_id'=>(string)$native,'tv_hotel_id'=>$tv],
              'snapshot'=>['tv_hotel_id'=>$tv,'target'=>$target,'anchors'=>$anchor['anchors'],'canonical_catalog_sha256'=>$anchor['catalog_sha256']]];
          }
          $last=array_pop($pairs);
          $db->exec("INSERT INTO anex_hotel_decisions VALUES(113,'rejected',NULL)");$db->exec('INSERT INTO anex_review_pair_exclusions VALUES(114,400)');
          $st=$db->prepare("INSERT INTO anex_hotel_search_mappings VALUES(115,900,'exact_operator_key','preview','owner_exact_operator_key_20260912_v2',?,?,1)");$st->execute([str_repeat('b',64),str_repeat('c',64)]);
          $db->exec("UPDATE andromeda_hotel_identities SET evidence_json='changed' WHERE local_hotel_id=600");
          $input=['operation_id'=>'int-andromeda-match-retained-anex-write-20261020-v1','acquisition_evidence_sha256'=>str_repeat('d',64),'pairs'=>$pairs];
          $input=json_decode(hmc4aw_json($input),true,64,JSON_THROW_ON_ERROR);
          $snap=$input['pairs'][0]['snapshot'];$legacyOrderRejected=false;
          try{hmc4aw_validate_anchors(['catalog_hotel_id'=>100,'unanimous_catalog_sha256'=>$snap['canonical_catalog_sha256'],'anchors'=>$snap['anchors']],hmc4aw_query($db,'SELECT * FROM andromeda_hotel_identities WHERE local_hotel_id=100'));}
          catch(Throwable $e){$legacyOrderRejected=$e->getMessage()==='anchor_projection_drift';}
          if(!$legacyOrderRejected)throw new RuntimeException('canonical_json_order_regression_not_reproduced');
          $db->failInsert=true;$failed=MatchRetainedAnexWriteV1::run($db,$input,str_repeat('e',40));$db->failInsert=false;
          if($failed['state']!=='rolled_back_no_write'||(int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings')->fetchColumn()!==1)throw new RuntimeException('rollback_contract');
          $success=MatchRetainedAnexWriteV1::run($db,$input,str_repeat('e',40));
          if($success['state']!=='committed_verified'||$success['new_accepted_mappings']!==2||count($success['held'])!==5)throw new RuntimeException('commit_or_holds_contract');
          if((int)$db->query('SELECT COUNT(*) FROM anex_hotel_decisions')->fetchColumn()!==1||(int)$db->query('SELECT COUNT(*) FROM anex_review_pair_exclusions')->fetchColumn()!==1)throw new RuntimeException('protected_rows_changed');
          $db->loseAck=true;$unknown=MatchRetainedAnexWriteV1::run($db,['operation_id'=>'int-andromeda-match-retained-anex-write-20261020-v2','acquisition_evidence_sha256'=>str_repeat('d',64),'pairs'=>[$last]],str_repeat('e',40));
          if($unknown['state']!=='commit_unknown_no_replay'||$unknown['mapping_writes']!==null||(int)$db->query('SELECT COUNT(*) FROM anex_hotel_search_mappings WHERE anex_hotel_id=118')->fetchColumn()!==1)throw new RuntimeException('unknown_ack_contract');
          echo json_encode(['committed'=>$success['new_accepted_mappings'],'held'=>count($success['held']),'readback'=>$success['resolver_readback_verified'],'rollback'=>$failed['state'],'unknown'=>$unknown['state']],JSON_THROW_ON_ERROR);
        }finally{if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}'''
        run=subprocess.run(['php','-r',code,str(SOURCE/'scripts/diagnostics/hotel_match_retained_anex_write_v1.php')],capture_output=True,text=True,timeout=60)
        self.assertEqual(0,run.returncode,run.stderr);data=json.loads(run.stdout)
        self.assertEqual(2,data['committed']);self.assertEqual(5,data['held']);self.assertTrue(data['readback'])
        self.assertEqual('rolled_back_no_write',data['rollback']);self.assertEqual('commit_unknown_no_replay',data['unknown'])

    def test_current_resolver_manual_exclusion_context_and_scope(self):
        code=r'''define('MATCH_RETAINED_CURRENT_LIBRARY_ONLY',true);require $argv[1];
        $db=new PDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $name='match_current_test_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
        try{
          $db->exec('CREATE TABLE catalog_hotels(id INT PRIMARY KEY,name TEXT,country_id INT,country_name TEXT,region_id INT,region_name TEXT,subregion_id INT,subregion_name TEXT,category TEXT,latitude DECIMAL(10,7),longitude DECIMAL(10,7),is_active INT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INT,catalog_hotel_id INT,match_class TEXT,approval_policy TEXT,enabled INT,scope TEXT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anex_hotel_decisions(anex_hotel_id INT,decision_status TEXT,catalog_hotel_id INT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INT,catalog_hotel_id INT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(80),external_hotel_id VARCHAR(80),local_hotel_id INT,decision_status VARCHAR(40),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json TEXT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE tour_price_observations(hotel_id INT,departure_id INT,country_id INT,departure_date DATE,nights INT,adults INT,children_count INT,child_ages_signature TEXT,observed_at DATETIME,source TEXT) ENGINE=InnoDB');
          $st=$db->prepare("INSERT INTO catalog_hotels VALUES(?,'Fixture',4,?,1,'Region',1,'Subregion','5',36,32,1)");
          foreach([100,200,300,400,500,900] as $id)$st->execute([$id,$id===500?'Russia':'Turkey']);
          $db->exec("INSERT INTO anex_hotel_search_mappings VALUES(1,100,'exact_operator_key','owner_exact_operator_key_20260912_v2',1,'preview'),(2,200,'exact_operator_key','owner_exact_operator_key_20260912_v2',1,'preview'),(3,400,'exact_operator_key','owner_exact_operator_key_20260912_v2',1,'preview'),(4,500,'exact_operator_key','owner_exact_operator_key_20260912_v2',1,'preview')");
          $db->exec("INSERT INTO anex_hotel_decisions VALUES(2,'rejected',NULL)");$db->exec('INSERT INTO anex_review_pair_exclusions VALUES(3,400)');
          $st=$db->prepare("INSERT INTO andromeda_hotel_identities VALUES('andromeda_catalog',?,?,'accepted',?,?,?)");
          foreach([200,300,400] as $id)$st->execute([(string)$id,$id,str_repeat('a',64),hash('sha256','{}'),'{}']);
          $db->exec("INSERT INTO tour_price_observations VALUES(200,1,4,'2026-11-02',7,2,0,'','2026-10-19','user_search'),(300,1,4,'2026-10-01',7,2,0,'','2026-10-19','user_search'),(400,1,4,'2026-11-03',7,2,0,'','2026-10-19','background'),(400,1,4,'2026-11-10',7,2,0,'','2026-10-18','user_search'),(900,1,4,'2026-11-02',7,2,0,'','2026-10-19','user_search')");
          $result=MatchRetainedCurrentV1::run($db,['tv_hotel_ids'=>[100,200,300,400,500,999],'parent_receipt_sha256'=>str_repeat('b',64)],'2026-10-20');
          echo json_encode($result,JSON_THROW_ON_ERROR);
        }finally{if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}'''
        run=subprocess.run(['php','-r',code,str(SOURCE/'scripts/diagnostics/hotel_match_retained_current_v1.php')],capture_output=True,text=True,timeout=60)
        self.assertEqual(0,run.returncode,run.stderr);data=json.loads(run.stdout)
        self.assertEqual(2,data['direct_anex_global_effective_native_ids'])
        self.assertEqual(2,data['direct_anex_effective_on_cohort']);self.assertEqual(1,data['direct_anex_effective_on_eligible_cohort']);self.assertEqual(2,data['acquisition_ready_count'])
        rows={r['tv_hotel_id']:r for r in data['rows']}
        self.assertNotIn(900,rows);self.assertEqual('future_context_missing',rows[300]['state'])
        self.assertEqual('target_ineligible',rows[500]['state']);self.assertEqual('target_missing',rows[999]['state'])
        self.assertEqual('2026-11-10',rows[400]['future_context']['departure_date'])
        self.assertEqual([],rows[200]['direct_anex_native_ids']);self.assertEqual([],rows[400]['direct_anex_native_ids'])
        self.assertFalse(data['safe_to_write_now'])

    def test_actual_read_only_snapshot_and_dml_rejection(self):
        code=r'''define('MATCH_RETAINED_LIBRARY_ONLY',true);require $argv[1];
        $db=new PDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $name='match_retained_test_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
        try{
          $db->exec('CREATE TABLE tour_operator_identity_observations(id INT,hotel_id INT,operator_id INT,operator_name TEXT,operator_link TEXT,country_id INT,first_seen_at DATETIME,last_seen_at DATETIME) ENGINE=InnoDB');
          $db->exec('CREATE TABLE local_tv_legacy_links(old_local_id INT,tv_id INT,snapshot_json LONGTEXT,snapshot_sha256 CHAR(64)) ENGINE=InnoDB');
          $db->exec('CREATE TABLE andromeda_hotel_identities(supplier_namespace VARCHAR(80),external_hotel_id VARCHAR(80),local_hotel_id INT,decision_status VARCHAR(40)) ENGINE=InnoDB');
          $db->exec('CREATE TABLE catalog_hotels(id INT,name TEXT,country_id INT,country_name TEXT,region_name TEXT,subregion_name TEXT,latitude DECIMAL(10,7),longitude DECIMAL(10,7),is_active INT) ENGINE=InnoDB');
          $db->exec("INSERT INTO tour_operator_identity_observations VALUES(1,1546,25,'FUN&SUN','https://fun-sun.ru/hotel/126268',4,'2026-10-01','2026-10-19')");
          $source=['namespace'=>'operator_315','external_key'=>'12','source_json'=>'{}','source_sha256'=>hash('sha256','{}')];
          $snapshot=json_encode(['sources'=>[$source]],JSON_THROW_ON_ERROR);$s=$db->prepare('INSERT INTO local_tv_legacy_links VALUES(?,?,?,?)');$s->execute([736,1546,$snapshot,hash('sha256',$snapshot)]);
          $db->exec("INSERT INTO andromeda_hotel_identities VALUES('operator_315','12',1546,'accepted')");
          $db->exec("INSERT INTO catalog_hotels VALUES(1546,'A11',4,'Turkey','Alanya','',36.5,32.0,1)");
          $db->exec("INSERT INTO catalog_hotels VALUES(999999,'Unrelated catalog hotel',4,'Turkey','Alanya','',36.5,32.0,1)");
          $rows=[];$result=MatchRetainedIntakeV1::capture($db,static function($row)use(&$rows){$rows[]=$row;});
          define('ANYTOUR_DATA_DSN',getenv('MATCH_RETAINED_TEST_DSN').';dbname='.$name);
          define('ANYTOUR_DATA_DB_USER','root');define('ANYTOUR_DATA_DB_PASSWORD',getenv('MATCH_RETAINED_TEST_PASSWORD'));
          require $argv[2].'/v2/data/db-v1.php';
          $isolated=MatchRetainedIntakeV1::capture(v2_data_db(),static function($row){});
          if($isolated['rows']!==$result['rows'])throw new RuntimeException('isolated_source_helper_snapshot_mismatch');
          $denied=false;try{MatchRetainedIntakeV1::capture($db,static function($row)use($db){$db->exec('DELETE FROM catalog_hotels');});}catch(PDOException $e){$denied=true;}
          if(!$denied||(int)$db->query('SELECT COUNT(*) FROM catalog_hotels')->fetchColumn()!==2)throw new RuntimeException('read_only_not_enforced');
          echo json_encode(['result'=>$result,'rows'=>$rows,'dml_denied'=>$denied,'isolated_source_helper_verified'=>true],JSON_THROW_ON_ERROR);
        }finally{if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}'''
        run=subprocess.run(['php','-r',code,str(SOURCE/'scripts/diagnostics/hotel_match_retained_intake_v1.php'),str(SOURCE)],capture_output=True,text=True,timeout=60)
        self.assertEqual(0,run.returncode,run.stderr);data=json.loads(run.stdout)
        self.assertTrue(data['dml_denied']);self.assertTrue(data['isolated_source_helper_verified'])
        self.assertEqual({'observation':1,'legacy_source':1,'identity':1,'target':1},data['result']['rows'])
        with tempfile.TemporaryDirectory() as td:
            path=Path(td)/'raw';path.write_text(''.join(json.dumps(r)+'\n' for r in data['rows']))
            result=p.project(path)
            self.assertEqual(1,result['candidate_states']['already_accepted'])
            self.assertEqual(1,result['candidate_states']['needs_independent_namespace_bridge'])


if __name__=='__main__':unittest.main()
