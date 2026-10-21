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
    def _transport_fixture(self, td):
        base=Path(td);private=base/'private';private.mkdir();project=base/'project';project.mkdir()
        (project/'config.php').write_text('private-configuration-sentinel')
        account_root=base/'.anytoour-match';(account_root/'provider-quotas').mkdir(parents=True)
        ledger_paths=[account_root/'retained-selection-claims.json',account_root/'retained-context-claims.json',
                      account_root/'provider-quotas/tourvisor-anex-2026-10-20.json']
        for path in ledger_paths:path.write_text('{"preserved_private_ledger":true}')
        profile=copy.deepcopy(m.TRANSPORT_DIAGNOSTIC);stamp=1792400000000000000
        failed=private/profile['target_operation_id'];failed.mkdir()
        failed_result={'operation_id':profile['target_operation_id'],'source_sha':profile['failed_source_sha'],
                       'mode':'match-retained-readback','status':'unknown_no_replay','reason':"name 'Path' is not defined",
                       'supplier_calls':0,'database_writes':0,'mapping_writes':0,'lead_calls':0,'booking_calls':0}
        failed_raw=a.canonical(failed_result);(failed/'result.json').write_bytes(failed_raw)
        profile['failed_receipt_sha256']=hashlib.sha256(failed_raw).hexdigest()
        (failed/'reservation.json').write_text(json.dumps({k:failed_result[k] for k in ('operation_id','source_sha','mode')}))
        for pin in profile['parents']:
            name='int-andromeda-match-retained-acquire-20261020-v'+str(pin['version']);target=private/name;target.mkdir()
            data={'operation_id':name,'source_sha':SHA,'supplier_calls':pin['calls'],'physical_http_reservations':pin['calls'],
                  'unknown_http_calls':pin['unknown'],'database_writes':0,'mapping_writes':0,'stop_reason':'TimeoutError' if pin['unknown'] else None,
                  'acquisition_state':'partial_no_replay' if pin['unknown'] else 'completed','actions':{'search_start':pin['calls']}}
            pin['evidence']=hashlib.sha256(a.canonical(data)).hexdigest()
            wrapper={'operation_id':name,'source_sha':SHA,'status':'complete','mode':'match-retained-acquire','production_unchanged':True,
                     'supplier_calls':pin['calls'],'database_writes':0,'mapping_writes':0,
                     'match_retained':{'data':data,'evidence_sha256':pin['evidence']}}
            (target/'result.json').write_text(json.dumps(wrapper));os.utime(target/'result.json',ns=(stamp+30_000_000_000,)*2)
            (target/'reservation.json').write_text(json.dumps({'operation_id':name,'source_sha':SHA}))
            for n in range(1,pin['calls']+1):
                request=target/('request-%04d.json'%n)
                request.write_text(json.dumps({'action':'search_start','path':'/tours/search','params':{'secret':'private-query-sentinel'}}))
                os.utime(request,ns=(stamp,)*2)
                if n<=pin['calls']-pin['unknown']:
                    body=target/('response-%04d.bin'%n);body.write_bytes(b'private-raw-body-sentinel')
                    meta=target/('response-%04d.json'%n)
                    meta.write_text(json.dumps({'http_status':200,'raw_sha256':hashlib.sha256(body.read_bytes()).hexdigest()}))
                    os.utime(meta,ns=(stamp+5_000_000_000,)*2)
        def fail(reason):raise ValueError(reason)
        space={'private':private,'project':project,'payload':{'cohort':m.NEXT_SCOPE['cohort'],
               'target_operation_id':profile['target_operation_id']},'operation':profile['operation_id'],'TRANSPORT_DIAGNOSTIC':profile,
               'NEXT_SCOPE':m.NEXT_SCOPE,'pathlib':__import__('pathlib'),'json':json,'hashlib':hashlib,'os':os,'re':__import__('re'),
               'subprocess':subprocess,'fail':fail,'safe_file':lambda p,b:p.is_file() and not p.is_symlink() and p.stat().st_size<=b}
        exec(m.REMOTE_HANDLER,space)
        space['retained_next_scope']=lambda stage:{'scope_id':m.NEXT_SCOPE['scope_id']}
        space['retained_prior_selection']=lambda scope:None
        return space,ledger_paths

    def test_passive_transport_readback_binds_history_preserves_unknown_and_never_reads_raw_or_secrets(self):
        with tempfile.TemporaryDirectory() as td:
            space,ledgers=self._transport_fixture(td);before={p:p.read_bytes() for p in ledgers}
            self.assertNotIn('Path',space)  # Stock REMOTE imports pathlib, not a Path alias.
            failed=space['private']/space['TRANSPORT_DIAGNOSTIC']['target_operation_id']/'result.json'
            failed_before=failed.read_bytes()
            metadata={'configured':True,'matches_default_token':False,'jwt_claims_parseable':True,
                      'expiry_epoch_seconds':1792500000,'expired_at_capture':False,'capture_epoch_seconds':1792400000}
            original=Path.read_bytes
            def guarded_read(path):
                if path.suffix=='.bin':raise AssertionError('raw response body must not be read')
                return original(path)
            def metadata_only(args,**kwargs):
                self.assertEqual('php',args[0]);self.assertIn('allow_url_fopen=0',args)
                self.assertNotIn('TOURVISOR_ANEX_JWT',kwargs['env']);self.assertNotIn('TOURVISOR_JWT',kwargs['env'])
                self.assertIn('disable_functions=',next(arg for arg in args if arg.startswith('disable_functions=')))
                return subprocess.CompletedProcess(args,0,json.dumps(metadata),'')
            with mock.patch.dict(os.environ,{'HOME':td,'TOURVISOR_ANEX_JWT':'private-ambient-sentinel'}), \
                 mock.patch.object(Path,'read_bytes',guarded_read),mock.patch.object(subprocess,'run',side_effect=metadata_only) as child:
                out=space['retained_transport_diagnostic'](SOURCE)
            self.assertEqual(1,child.call_count);self.assertEqual((47,3),(out['known_lineage_http_attempts'],out['known_lineage_unknown']))
            missing=[row for row in out['requests'] if row['http_status'] is None]
            self.assertEqual(3,len(missing));self.assertTrue(all(row['request_to_terminal_file_ms']==30000 for row in missing))
            self.assertEqual(before,{p:p.read_bytes() for p in ledgers});self.assertFalse(out['cause_established'])
            self.assertEqual(failed_before,failed.read_bytes());self.assertFalse(out['failed_operation_replayed'])
            serialized=json.dumps(out)
            for token in ('private-raw-body-sentinel','private-query-sentinel','private-configuration-sentinel','private-ambient-sentinel','https://'):
                self.assertNotIn(token,serialized)
            first=space['private']/'int-andromeda-match-retained-acquire-20261020-v1/result.json'
            broken=json.loads(first.read_text());broken['match_retained']['data']['unknown_http_calls']=1;first.write_text(json.dumps(broken))
            with mock.patch.dict(os.environ,{'HOME':td}),mock.patch.object(subprocess,'run',side_effect=AssertionError('no child after drift')):
                with self.assertRaisesRegex(ValueError,'transport_parent_binding'):space['retained_transport_diagnostic'](SOURCE)

    def test_passive_recovery_requires_the_exact_failed_receipt_before_any_metadata_child(self):
        with tempfile.TemporaryDirectory() as td:
            space,_=self._transport_fixture(td)
            failed=space['private']/space['TRANSPORT_DIAGNOSTIC']['target_operation_id']/'result.json'
            failed.write_text('{}')
            with mock.patch.object(subprocess,'run',side_effect=AssertionError('no child after changed failure')):
                with self.assertRaisesRegex(ValueError,'transport_failed_binding'):space['retained_transport_diagnostic'](SOURCE)

    @unittest.skipUnless(__import__('shutil').which('php'),'native PHP required')
    def test_passive_config_metadata_uses_real_php_and_cannot_expose_jwt_claims(self):
        with tempfile.TemporaryDirectory() as td:
            space,ledgers=self._transport_fixture(td)
            encoded=__import__('base64').urlsafe_b64encode(json.dumps({'exp':1,'sub':'private-subject-sentinel'}).encode()).decode().rstrip('=')
            token='e30.'+encoded+'.private-signature-sentinel'
            (space['project']/'config.php').write_text("<?php define('TOURVISOR_ANEX_JWT', '"+token+"');define('TOURVISOR_JWT','different-private-token');")
            with mock.patch.dict(os.environ,{'HOME':td}):out=space['retained_transport_diagnostic'](SOURCE)
            self.assertTrue(out['current_account_metadata']['configured']);self.assertTrue(out['current_account_metadata']['expired_at_capture'])
            self.assertFalse(out['current_account_metadata']['matches_default_token']);self.assertFalse(out['metadata_signature_verified'])
            for value in (token,encoded,'private-subject-sentinel','private-signature-sentinel'):self.assertNotIn(value,json.dumps(out))

    def test_transport_readback_has_one_exact_admission_and_no_write_authority(self):
        c=core();m.register_parser(c);p=m.TRANSPORT_DIAGNOSTIC
        command=c.PREFIX+SHA+' match-retained-readback '+p['operation_id']+' '+m.NEXT_SCOPE['cohort']+' '+p['target_operation_id']
        cmd=c.parse_command(command);self.assertEqual(p['target_operation_id'],cmd['target_operation_id'])
        for text in (command.replace(p['target_operation_id'],m.NEXT_SCOPE['saved_acquisition_operation_id']),
                     command.replace(m.NEXT_SCOPE['cohort'],'common4-retained'),command.replace(p['operation_id'],'int-andromeda-match-retained-readback-20261020-v5'),
                     command.replace(p['operation_id'],'int-andromeda-match-retained-readback-20261020-v7')):
            with self.assertRaises(ValueError):c.parse_command(text)
        self.assertEqual((47,3),(sum(v['calls'] for v in p['parents']),sum(v['unknown'] for v in p['parents'])))

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
        scope.pop('scope_kind',None);scope.pop('wire_context',None)
        scope['supplier_http_maximum']=400;scope['context_grouping']='exact_date_nights'
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
            name='int-andromeda-match-retained-acquire-20261020-v4';target=private/name;target.mkdir()
            wrapper=json.loads((private/history[1]['operation_id']/'result.json').read_text())
            wrapper['operation_id']=name;wrapper['match_retained']['data'].update(operation_id=name,selected_hotels=1)
            digest=hashlib.sha256(a.canonical(wrapper['match_retained']['data'])).hexdigest();wrapper['match_retained']['evidence_sha256']=digest
            for file,value in [('result.json',wrapper),('reservation.json',{'operation_id':name,'source_sha':SHA}),('acquisition-plan.json',{'hotel_ids':[12]})]:
                (target/file).write_bytes(a.canonical(value))
            variation=dict(scope,scope_kind='owner_date_variation',context_origin='owner_20261020_1257_timeout60_new_window',
                           selected_tv_hotel_ids=[12],context_history=[dict(history[1],operation_id=name,selected_tv_hotel_ids=[12],evidence_sha256=digest)])
            self.assertEqual(3,len(space['retained_prior_selection'](variation)))
            fresh=dict(variation,scope_kind='never_selected_bounded',context_origin='saved_user_search_exact_date_nights',selected_tv_hotel_ids=[14])
            self.assertEqual(3,len(space['retained_prior_selection'](fresh)))
            fresh['context_origin']='unreviewed-origin'
            with self.assertRaisesRegex(ValueError,'history_shape'):space['retained_prior_selection'](fresh)
            variation['context_origin']='owner_20261020_1031_saved_peer_context'
            with self.assertRaisesRegex(ValueError,'history_shape'):space['retained_prior_selection'](variation)
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
                               ('match-retained-readback',m.NEXT_SCOPE['readback_operation_id'],m.NEXT_SCOPE['saved_acquisition_operation_id']),
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

    def test_new_owner60_policy_does_not_retarget_or_reopen_the_old_scope(self):
        self.assertEqual('458857b2e3641c143699247ea7c92c235cd9a0765b3e06fbce4aa81adfe41c31',m.NEXT_SCOPE['sha256'])
        self.assertEqual('266f0791573661882104a39ec1b4206940cec16eefef29de4d2e0a11f52ede07',m.NEXT_60_SCOPE['sha256'])
        for p in (m.NEXT_60_SCOPE,m.NEXT_WIDE_SCOPE):
            c=core();m.register_parser(c)
            command=c.PREFIX+' '.join((SHA,'match-retained-acquire',p['operation_id'],p['cohort'],p['parent_operation_id']))
            parsed=c.parse_command(command)
            with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,dict(parsed))
            self.assertIn('NEXT_SCOPE='+repr(p),c.REMOTE)
            self.assertIn("'continuation_scope_path':NEXT_SCOPE['path']",c.REMOTE)
            for bad in (command.replace(p['operation_id'],m.NEXT_SCOPE['operation_id']),
                        command.replace(p['cohort'],m.NEXT_SCOPE['cohort']),command.replace('match-retained-acquire','match-retained-readback')):
                with self.assertRaises(ValueError):c.parse_command(bad)


class AcquisitionTest(unittest.TestCase):
    def test_never9_scope_extends_consumed195_without_retargeting_old_manifests(self):
        p=m.NEXT_NEVER9_SCOPE;scope=json.loads((SOURCE/p['path']).read_text())
        self.assertEqual(p['sha256'],hashlib.sha256((SOURCE/p['path']).read_bytes()).hexdigest())
        self.assertEqual((9,195,7,3),(len(scope['selected_tv_hotel_ids']),len(scope['prior_selected_hotel_ids']),
            len(scope['exclusion_history'])+len(scope['context_history']),sum(x['unknown_http_calls'] for x in scope['exclusion_history']+scope['context_history'])))
        self.assertFalse(set(scope['selected_tv_hotel_ids'])&set(scope['prior_selected_hotel_ids']))
        self.assertEqual({('2026-10-21',5,8,1,'user_search')},{(x['departure_date'],x['nights'],x['country_id'],x['departure_id'],x['source']) for x in scope['future_contexts'].values()})
        for old in (m.NEXT_SCOPE,m.NEXT_60_SCOPE,m.NEXT_WIDE_SCOPE,m.NEXT_NEVER13_SCOPE):
            self.assertEqual(old['sha256'],hashlib.sha256((SOURCE/old['path']).read_bytes()).hexdigest())
        c=core();m.register_parser(c);body=c.PREFIX+' '.join((SHA,'match-retained-acquire',p['operation_id'],p['cohort'],p['parent_operation_id']))
        parsed=c.parse_command(body)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,dict(parsed))
        ast.parse(c.REMOTE);self.assertIn('NEXT_SCOPE='+repr(p),c.REMOTE)
        with self.assertRaises(ValueError):c.parse_command(body.replace(p['operation_id'],m.NEXT_NEVER13_SCOPE['operation_id']))
        data={**parsed,'control_source_sha':CONTROL,'state':'acquired_retained_native_read_only','supplier_calls':3,
              'physical_http_reservations':3,'tariff_search_units':1,'database_writes':0,'mapping_writes':0,
              'no_replay':True,'safe_to_write_now':False,'continuation_scope_sha256':p['sha256'],'scope_id':p['scope_id'],
              'context_origin':p['context_origin'],'wire_context':p['wire_context'],'selection_reservation_sha256':'c'*64}
        result={**parsed,'status':'complete','production_unchanged':True,'supplier_calls':3,'database_writes':0,'mapping_writes':0,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(a.canonical(data)).hexdigest()}}
        self.assertTrue(m.completed_result(parsed,result,CONTROL))
        for key,value in [('supplier_calls',31),('tariff_search_units',2),('physical_http_reservations',2),('selection_reservation_sha256',None)]:
            bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
            if key=='supplier_calls':bad['supplier_calls']=value
            bad['match_retained']['evidence_sha256']=hashlib.sha256(a.canonical(bad['match_retained']['data'])).hexdigest()
            self.assertFalse(m.completed_result(parsed,bad,CONTROL))

    def bounded_fresh_scope(self):
        scope,current=ContinuationTest().fixture()
        scope.update(scope_kind='never_selected_bounded',context_origin='saved_user_search_exact_date_nights',
                     wire_context={'date_from':'2099-01-03','date_to':'2099-01-03','nights_from':9,'nights_to':9},
                     supplier_http_maximum=30,search_start_maximum=1,http_timeout_seconds=60)
        scope['scope_id']=hashlib.sha256(a.canonical({k:scope[k] for k in ('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts','scope_kind','wire_context','context_origin')})).hexdigest()
        scope['cohort']='anex-next-'+scope['scope_id'][:16]
        return scope,current

    def test_never_selected_bounded_keeps_real_exact_context_and_rejects_budget_or_reselection(self):
        scope,current=self.bounded_fresh_scope();before=copy.deepcopy(current)
        self.assertEqual(2,len(a.select_scope_rows(scope,current,'2026-10-20')));self.assertEqual(before,current)
        for key,value in [('supplier_http_maximum',400),('search_start_maximum',2),('http_timeout_seconds',30),
                          ('context_origin','owner_20261020_1257_timeout60_new_window'),('prior_selected_hotel_ids',[101])]:
            with self.assertRaises(ValueError):a.select_scope_rows(dict(scope,**{key:value}),current,'2026-10-20')
        changed=copy.deepcopy(scope);changed['wire_context']['nights_to']=10
        changed['scope_id']=hashlib.sha256(a.canonical({k:changed[k] for k in ('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts','scope_kind','wire_context','context_origin')})).hexdigest()
        changed['cohort']='anex-next-'+changed['scope_id'][:16]
        with self.assertRaisesRegex(ValueError,'exact_saved_context'):a.select_scope_rows(changed,current,'2026-10-20')
        a.check_never_selected_requests(scope,[{'params':{'hotelIds':[999]}}])
        with self.assertRaisesRegex(ValueError,'effective_request_consumed'):
            a.check_never_selected_requests(scope,[{'params':{'hotelIds':[101]},'unknown_http_calls':1}])

    def test_never_selected_budget_is_applied_to_real_client_and_first_unknown_stops(self):
        scope,current=self.bounded_fresh_scope();client=a.Client.__new__(a.Client)
        client.http_maximum=400;client.search_start_maximum=None
        a.configure_scope_budget(client,scope)
        self.assertEqual((30,1),(client.http_maximum,client.search_start_maximum))
        client.deadline=10**20;client.reserved=30;client.tariff=0
        with self.assertRaisesRegex(ValueError,'finite_budget_stop'):client.call('tour_detail','/tours/1',{})
        client.reserved=0;client.tariff=1
        with self.assertRaisesRegex(ValueError,'finite_budget_stop'):client.call('search_start','/tours/search',{})
        legacy=a.Client.__new__(a.Client);legacy.http_maximum=400;legacy.search_start_maximum=None
        a.configure_scope_budget(legacy,{'scope_kind':'historical'})
        self.assertEqual((400,None),(legacy.http_maximum,legacy.search_start_maximum))
        class C:
            calls=reserved=tariff=unknown=0;counts=__import__('collections').Counter();before={}
            def call(self,action,path,params):
                self.calls+=1;self.reserved+=1;self.tariff+=1;self.unknown+=1;self.counts[action]+=1
                raise TimeoutError()
        with tempfile.TemporaryDirectory() as td:
            out=a.acquire(C(),Path(td),current['rows'],sleep=lambda _:self.fail('no polling after UNKNOWN'),scope=scope)
        self.assertEqual(('partial_no_replay',1,1),tuple(out[k] for k in ('acquisition_state','supplier_calls','unknown_http_calls')))

    def test_new13_exact_profile_preserves_old_manifests_and_requires_full_bounded_completion(self):
        p=m.NEXT_NEVER13_SCOPE;scope=json.loads((SOURCE/p['path']).read_text())
        self.assertEqual(13,p['selected_count']);self.assertFalse(set(scope['selected_tv_hotel_ids']).intersection(scope['prior_selected_hotel_ids']))
        self.assertEqual(6,len(scope['exclusion_history'])+len(scope['context_history']))
        self.assertEqual((2,22,2,0),tuple(scope[k] for k in ('country_id','departure_id','adults','children_count')))
        self.assertEqual(1,len({(c['departure_date'],c['nights']) for c in scope['future_contexts'].values()}))
        c=core();m.register_parser(c)
        command=c.PREFIX+' '.join((SHA,'match-retained-acquire',p['operation_id'],p['cohort'],p['parent_operation_id']))
        parsed=c.parse_command(command)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,dict(parsed))
        self.assertIn('NEXT_SCOPE='+repr(p),c.REMOTE);ast.parse(c.REMOTE)
        self.assertIn("continuation.get('scope_kind')=='never_selected_bounded'",c.REMOTE)
        for old in (m.NEXT_SCOPE,m.NEXT_60_SCOPE,m.NEXT_WIDE_SCOPE):
            self.assertNotEqual(p['operation_id'],old['operation_id'])
            self.assertEqual(old['sha256'],hashlib.sha256((SOURCE/old['path']).read_bytes()).hexdigest())
        for bad in (command.replace(p['operation_id'],p['operation_id']+'-renamed'),command.replace(p['cohort'],m.NEXT_WIDE_SCOPE['cohort'])):
            with self.assertRaises(ValueError):c.parse_command(bad)
        data={**parsed,'control_source_sha':CONTROL,'state':'acquired_retained_native_read_only','supplier_calls':3,
              'physical_http_reservations':3,'tariff_search_units':1,'database_writes':0,'mapping_writes':0,
              'no_replay':True,'safe_to_write_now':False,'continuation_scope_sha256':p['sha256'],'scope_id':p['scope_id'],
              'context_origin':p['context_origin'],'wire_context':p['wire_context'],'selection_reservation_sha256':'c'*64}
        result={**parsed,'status':'complete','production_unchanged':True,'supplier_calls':3,'database_writes':0,'mapping_writes':0,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(a.canonical(data)).hexdigest()}}
        self.assertTrue(m.completed_result(parsed,result,CONTROL))
        for key,value in [('supplier_calls',31),('physical_http_reservations',2),('tariff_search_units',2),('selection_reservation_sha256',None)]:
            bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
            if key=='supplier_calls':bad['supplier_calls']=value
            bad['match_retained']['evidence_sha256']=hashlib.sha256(a.canonical(bad['match_retained']['data'])).hexdigest()
            self.assertFalse(m.completed_result(parsed,bad,CONTROL))

    def test_scope_loader_requires_both_controller_digest_and_exact_carrier_manifest_without_path_escape(self):
        with tempfile.TemporaryDirectory() as td:
            root=Path(td);relative=m.NEXT_WIDE_SCOPE['path'];path=root/relative;path.parent.mkdir(parents=True)
            scope={'scope_id':'fixture'};raw=a.canonical(scope);path.write_bytes(raw);digest=hashlib.sha256(raw).hexdigest()
            manifest={'schema_version':1,'files':{relative:digest}};(root/'manifest.json').write_bytes(a.canonical(manifest))
            data={'continuation_scope':scope,'continuation_scope_path':relative,'continuation_scope_sha256':digest}
            self.assertEqual(scope,a.load_scope_file(root,data))
            for rel in ('../scope.json','/tmp/scope.json','scripts/diagnostics/fixtures/../../secret.json'):
                with self.assertRaisesRegex(ValueError,'scope_path'):a.load_scope_file(root,dict(data,continuation_scope_path=rel))
            for changed in (dict(data,continuation_scope_sha256='0'*64),dict(data,continuation_scope={'scope_id':'changed'})):
                with self.assertRaisesRegex(ValueError,'scope_binding'):a.load_scope_file(root,changed)
            manifest['files'].clear();(root/'manifest.json').write_bytes(a.canonical(manifest))
            with self.assertRaisesRegex(ValueError,'scope_binding'):a.load_scope_file(root,data)

    def date_scope(self):
        scope,current=ContinuationTest().fixture()
        peer={'tv_hotel_id':103,'target':{'country_id':2},'future_context':dict(current['rows'][0]['future_context'],hotel_id=103,departure_date='2099-02-01',nights=6)}
        current['rows'].append(peer)
        scope.update(scope_kind='owner_date_variation',context_origin='owner_20261020_1031_saved_peer_context',
                     wire_context={'date_from':'2099-02-01','date_to':'2099-02-01','nights_from':6,'nights_to':6},
                     prior_selected_hotel_ids=[101,102],context_peer_tv_hotel_id=103,
                     context_peer_row_sha256=hashlib.sha256(a.canonical(peer)).hexdigest(),
                     supplier_http_maximum=30,search_start_maximum=1,
                     parent_evidence_sha256=hashlib.sha256(a.canonical(current)).hexdigest())
        scope['scope_id']=hashlib.sha256(a.canonical({k:scope[k] for k in ('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts','scope_kind','wire_context','context_origin')})).hexdigest()
        scope['cohort']='anex-next-'+scope['scope_id'][:16]
        return scope,current

    def test_owner_date_context_preserves_original_observation_and_excludes_old_effective_ranges(self):
        scope,current=self.date_scope();before=copy.deepcopy(current)
        self.assertEqual(2,len(a.select_scope_rows(scope,current,'2026-10-20')));self.assertEqual(before,current)
        old={'params':{'countryId':2,'departureId':1,'adults':2,'childs':[],'operatorIds':[13],
                       'hotelIds':[101],'dateFrom':'2099-01-01','dateTo':'2099-01-31'}}
        a.check_new_context(scope,[old])
        for k,v in [('date_from','2099-01-31'),('date_to','2099-01-31')]:
            bad=copy.deepcopy(scope);bad['wire_context'].update(date_from='2099-01-31',date_to='2099-01-31',nights_from=28,nights_to=28)
            with self.assertRaisesRegex(ValueError,'effective_date_range_consumed'):a.check_new_context(bad,[old])
        with tempfile.TemporaryDirectory() as td:
            home=Path(td);root=home/'.anytoour-match';root.mkdir();ledger=root/'retained-selection-claims.json'
            original=a.canonical({'schema_version':1,'claims':{'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'UNKNOWN_consumed'} for h in [101,102]}})
            ledger.write_bytes(original);a.reserve_contexts(home,scope,'new-date-context', [old]);self.assertEqual(original,ledger.read_bytes())
            for changed in (scope,dict(scope,selected_tv_hotel_ids=[101])):
                with self.assertRaisesRegex(ValueError,'context_consumed'):a.reserve_contexts(home,changed,'renamed-next-day',[old])
            self.assertEqual(original,ledger.read_bytes())

    def test_date_context_is_one_group_and_timeout_stops_without_new_country_or_retry(self):
        scope,current=self.date_scope()
        class C:
            calls=reserved=tariff=unknown=0;counts=__import__('collections').Counter();before={}
            params=None
            def call(self,action,path,params):
                self.calls+=1;self.reserved+=1;self.tariff+=1;self.unknown+=1;self.counts[action]+=1;self.params=params
                raise TimeoutError()
        with tempfile.TemporaryDirectory() as td:
            client=C();result=a.acquire(client,Path(td),current['rows'][:2],sleep=lambda _:self.fail('no status after timeout'),scope=scope)
        self.assertEqual(('partial_no_replay','TimeoutError',1,1),tuple(result[k] for k in ('acquisition_state','stop_reason','supplier_calls','unknown_http_calls')))
        self.assertEqual(('2099-02-01','2099-02-01',6,6,[101,102]),tuple(client.params[k] for k in ('dateFrom','dateTo','nightsFrom','nightsTo','hotelIds')))
        client=a.Client.__new__(a.Client);client.reserved=30;client.http_maximum=30;client.deadline=10**20
        with self.assertRaisesRegex(ValueError,'finite_budget_stop'):client.call('tour_detail','/tours/1',{})
        client.reserved=0;client.tariff=1;client.search_start_maximum=1
        with self.assertRaisesRegex(ValueError,'finite_budget_stop'):client.call('search_start','/tours/search',{})

    def test_fresh_owner_window_is_not_claimed_as_a_saved_peer_date_and_still_blocks_unknown_ranges(self):
        scope,current=self.date_scope();before=copy.deepcopy(current)
        scope.update(context_origin='owner_20261020_1257_timeout60_new_window',owner_authorized_at='2026-10-20T12:57:13+02:00',http_timeout_seconds=60,
                     wire_context={'date_from':'2099-02-08','date_to':'2099-02-08','nights_from':6,'nights_to':6})
        scope['scope_id']=hashlib.sha256(a.canonical({k:scope[k] for k in ('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts','scope_kind','wire_context','context_origin')})).hexdigest()
        scope['cohort']='anex-next-'+scope['scope_id'][:16]
        self.assertEqual(2,len(a.select_scope_rows(scope,current,'2026-10-20')));self.assertEqual(before,current)
        previous={'params':{'countryId':2,'departureId':1,'adults':2,'childs':[],'operatorIds':[13],'hotelIds':[101],
                            'dateFrom':'2099-02-01','dateTo':'2099-02-01'}}
        a.check_new_context(scope,[previous])
        previous['params']['dateTo']='2099-02-08'
        with self.assertRaisesRegex(ValueError,'effective_date_range_consumed'):a.check_new_context(scope,[previous])
        scope['owner_authorized_at']='2026-10-20T10:31:00+02:00'
        with self.assertRaisesRegex(ValueError,'owner_authority'):a.check_new_context(scope,[])

    def test_saved_detail_inspection_never_exposes_url_path_or_query_values(self):
        detail={'hotel':8038,'operator':13,'operatorLink':'https://anextour.ru/hotels/opaque-hotel-private?HOTELLIST=123&token=secret-value#opaque-fragment'}
        result=a.inspect_saved_detail(detail,8038);serialized=json.dumps(result)
        for token in ['opaque-hotel-private','secret-value','opaque-fragment','https://','123']:
            self.assertNotIn(token,serialized)
        self.assertEqual(['hotels','opaque:20'],result['path_shape']);self.assertEqual(['HOTELLIST','redacted'],result['query_key_shapes'])

    def test_new_saved_detail_readback_is_exact_receipt_bound_read_only_and_preserves_inputs(self):
        operation='int-andromeda-match-retained-readback-20261020-v7';profile=copy.deepcopy(m.SAVED_DETAIL_READBACKS[operation])
        with tempfile.TemporaryDirectory() as td:
            home=Path(td);private=home/'private';target=private/profile['target_operation_id'];target.mkdir(parents=True)
            ledger=home/'.anytoour-match';(ledger/'provider-quotas').mkdir(parents=True)
            paths={'hotel_selection':ledger/'retained-selection-claims.json','context_selection':ledger/'retained-context-claims.json',
                   'account_day':ledger/'provider-quotas/tourvisor-anex-2026-10-20.json'}
            for path in paths.values():path.write_bytes(b'{}')
            profile.update(hotel_ledger_sha256=hashlib.sha256(b'{}').hexdigest(),context_ledger_sha256=hashlib.sha256(b'{}').hexdigest())
            scope=dict(m.NEXT_NEVER13_SCOPE);detail={'hotel':616,'operator':13,'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=private-hotel-name'}
            raw=a.canonical(detail);digest=hashlib.sha256(raw).hexdigest();profile['raw_sha256']=digest
            data={'operation_id':profile['target_operation_id'],'source_sha':profile['source_sha'],'acquisition_state':'completed',
                  'unknown_http_calls':0,'selected_hotels':13,'continuation_scope_sha256':scope['sha256'],'scope_id':scope['scope_id'],
                  'database_writes':0,'mapping_writes':0,'edges':[{'tv_hotel_id':616,'detail_raw':{'raw_sha256':digest,'response_number':4},
                  'tour_id_sha256':hashlib.sha256(b'1234').hexdigest()}]}
            profile['evidence_sha256']=hashlib.sha256(a.canonical(data)).hexdigest()
            wrapper={'operation_id':profile['target_operation_id'],'source_sha':profile['source_sha'],'status':'complete',
                     'mode':'match-retained-acquire','production_unchanged':True,'database_writes':0,'mapping_writes':0,
                     'match_retained':{'data':data,'evidence_sha256':profile['evidence_sha256']}}
            receipt=a.canonical(wrapper);profile['receipt_sha256']=hashlib.sha256(receipt).hexdigest()
            (target/'result.json').write_bytes(receipt)
            reservation=a.canonical({'operation_id':profile['target_operation_id'],'source_sha':profile['source_sha']})
            (target/'reservation.json').write_bytes(reservation);(target/'response-0004.bin').write_bytes(raw)
            (target/'response-0004.json').write_bytes(a.canonical({'raw_sha256':digest,'http_status':200,'complete_body':True}))
            (target/'request-0004.json').write_bytes(a.canonical({'action':'tour_detail','path':'/tours/1234'}))
            def fail(reason):raise ValueError(reason)
            space={'private':private,'operation':operation,'payload':{'cohort':profile['cohort'],'target_operation_id':profile['target_operation_id']},
                   'SAVED_DETAIL_READBACKS':{operation:profile},'NEXT_SCOPE':scope,'safe_file':lambda p,b:p.is_file() and not p.is_symlink() and p.stat().st_size<=b,
                   'safe_json':lambda p,b:json.loads(p.read_bytes()),'hashlib':hashlib,'json':json,'re':__import__('re'),'fail':fail}
            exec(m.REMOTE_HANDLER,space);space['retained_next_scope']=lambda stage:scope;space['retained_prior_selection']=lambda value:None
            before={p:p.read_bytes() for p in target.iterdir()};before.update({p:p.read_bytes() for p in paths.values()})
            with mock.patch('pathlib.Path.home',return_value=home),mock.patch('subprocess.run',side_effect=AssertionError('no child/HTTP')):
                out=space['retained_saved_detail'](SOURCE)
                self.assertEqual((616,0,True),(out['tv_hotel_id'],out['supplier_calls'],out['protected_inputs_unchanged']))
                self.assertNotIn('private-hotel-name',json.dumps(out));self.assertEqual('non_identifier_text',out['inspection']['selector_analysis'][0]['format'])
                self.assertFalse(out['inspection']['selector_analysis'][0]['native_namespace_authority'])
                for path,value in [(target/'result.json',receipt+b' '),(target/'reservation.json',reservation.replace(profile['source_sha'].encode(),b'f'*40)),
                                   (target/'response-0004.bin',raw+b' '),(paths['hotel_selection'],b'{"changed":true}')]:
                    saved=path.read_bytes();path.write_bytes(value)
                    with self.assertRaises(ValueError):space['retained_saved_detail'](SOURCE)
                    path.write_bytes(saved)
            self.assertEqual(before,{p:p.read_bytes() for p in before})

    def test_new_saved_readback_parser_completion_and_old_profiles_stay_closed(self):
        operation='int-andromeda-match-retained-readback-20261020-v7';p=m.SAVED_DETAIL_READBACKS[operation];scope=m.NEXT_NEVER13_SCOPE
        c=core();m.register_parser(c);body=c.PREFIX+' '.join((SHA,'match-retained-readback',operation,p['cohort'],p['target_operation_id']))
        parsed=c.parse_command(body)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,dict(parsed))
        ast.parse(c.REMOTE);self.assertIn('SAVED_DETAIL_READBACKS='+repr(m.SAVED_DETAIL_READBACKS),c.REMOTE)
        for bad in [body.replace('readback-20261020-v7','readback-20261020-v8'),body.replace(p['target_operation_id'],m.NEXT_WIDE_SCOPE['operation_id']),
                    body.replace(p['cohort'],m.NEXT_WIDE_SCOPE['cohort'])]:
            with self.assertRaises((ValueError,KeyError)):c.parse_command(bad)
        data={**parsed,'control_source_sha':CONTROL,'state':'saved_detail_inspected_read_only','supplier_calls':0,'database_writes':0,'mapping_writes':0,
              'no_replay':True,'safe_to_write_now':False,'new_accepted_mappings':0,'continuation_scope_sha256':scope['sha256'],'scope_id':scope['scope_id'],
              'tv_hotel_id':616,'raw_sha256':p['raw_sha256'],'source_acquisition_receipt_sha256':p['receipt_sha256'],'protected_inputs_unchanged':True,
              'protected_ledger_sha256':{'hotel_selection':p['hotel_ledger_sha256'],'context_selection':p['context_ledger_sha256'],'account_day':'c'*64}}
        result={**parsed,'status':'complete','production_unchanged':True,'supplier_calls':0,'database_writes':0,'mapping_writes':0,
                'match_retained':{'data':data,'evidence_sha256':hashlib.sha256(a.canonical(data)).hexdigest()}}
        self.assertTrue(m.completed_result(parsed,result,CONTROL))
        for key,value in [('tv_hotel_id',8038),('raw_sha256','f'*64),('source_acquisition_receipt_sha256','f'*64),('protected_inputs_unchanged',False),('new_accepted_mappings',1)]:
            bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
            bad['match_retained']['evidence_sha256']=hashlib.sha256(a.canonical(bad['match_retained']['data'])).hexdigest()
            self.assertFalse(m.completed_result(parsed,bad,CONTROL))

    def test_saved_selector_structure_preserves_arrays_and_never_promotes_namespace(self):
        for text,kind,tokens in [('[100000001]','json_positive_integer_array',[100000001]),
                                 ('[5844,5844]','json_positive_integer_array',[5844,5844]),
                                 ('[5844,7777]','json_positive_integer_array',[5844,7777]),
                                 ('some hotel name','non_identifier_text',[])]:
            link='https://agent.anextour.ru/search/tour?HOTELLIST='+__import__('urllib.parse',fromlist=['quote']).quote(text)
            out=a.inspect_saved_detail({'hotel':8038,'operator':13,'operatorLink':link},8038)
            item=out['selector_analysis'][0]
            self.assertEqual((kind,tokens,False),(item['format'],item['positive_tokens'],item['native_namespace_authority']))
            self.assertEqual('native_link_unresolved',out['proof']['state'])
        c=core();m.register_parser(c)
        command=c.PREFIX+SHA+' match-retained-readback int-andromeda-match-retained-readback-20261020-v4 '+m.NEXT_SCOPE['cohort']+' '+m.NEXT_SCOPE['saved_acquisition_operation_id']
        self.assertEqual('int-andromeda-match-retained-readback-20261020-v4',c.parse_command(command)['operation_id'])
        with self.assertRaises(ValueError):c.parse_command(command.replace('readback-20261020-v4','readback-20261020-v5'))

    def test_saved_readback_new_cohort_returns_before_any_child_or_supplier_collector(self):
        with tempfile.TemporaryDirectory() as td:
            project=Path(td);(project/'config.php').write_text('fixture');(project/'data').mkdir();(project/'data/db-v1.php').write_text('fixture')
            def fail(reason):raise ValueError(reason)
            space={'payload':{'cohort':m.NEXT_SCOPE['cohort'],'match_retained_control_sha':CONTROL},'mode':'match-retained-readback',
                   'RETAINED_MODES':m.MODES,'NEXT_SCOPE':m.NEXT_SCOPE,'TRANSPORT_DIAGNOSTIC':m.TRANSPORT_DIAGNOSTIC,
                   'PROOF_SOURCE_INSPECTION':m.PROOF_SOURCE_INSPECTION,'project':project,'safe_file':lambda p,b:p.is_file(),
                   'hashlib':hashlib,'json':json,'re':__import__('re'),'fail':fail,'operation':m.NEXT_SCOPE['readback_operation_id'],'source':SHA}
            exec(m.REMOTE_HANDLER,space)
            space['retained_saved_detail']=lambda stage:{'state':'saved_detail_inspected_read_only'}
            with mock.patch('subprocess.run',side_effect=AssertionError('no child/HTTP')):
                out=space['run_match_retained'](SOURCE)
            self.assertEqual('saved_detail_inspected_read_only',out['data']['state']);self.assertEqual(0,out['data']['supplier_calls'])

    def test_effective_contexts_come_from_actual_requests_including_unknown_not_plan(self):
        with tempfile.TemporaryDirectory() as td:
            private=Path(td);name='int-andromeda-match-retained-acquire-20261020-v2';target=private/name;target.mkdir()
            data={'physical_http_reservations':3,'tariff_search_units':2,'unknown_http_calls':1,'actions':{'search_start':2}}
            (target/'result.json').write_text(json.dumps({'match_retained':{'data':data}}))
            base={'countryId':2,'departureId':1,'adults':2,'childs':[],'operatorIds':[13],'hotelIds':[101],
                  'dateFrom':'2026-12-14','dateTo':'2027-01-03','nightsFrom':6,'nightsTo':9}
            requests=[{'action':'search_start','path':'/tours/search','params':base},
                      {'action':'search_status','path':'/tours/search/1/status','params':{}},
                      {'action':'search_start','path':'/tours/search','params':dict(base,dateFrom='2026-10-20',dateTo='2026-11-08')}]
            for n,value in enumerate(requests,1):(target/('request-%04d.json'%n)).write_bytes(a.canonical(value))
            def fail(reason):raise ValueError(reason)
            space={'private':private,'safe_json':lambda path,bound:json.loads(path.read_text()),'hashlib':hashlib,'json':json,'re':__import__('re'),'fail':fail}
            exec(m.REMOTE_HANDLER,space);space['retained_prior_selection']=lambda scope:None
            out=space['retained_effective_requests']({'exclusion_history':[{'operation_id':name,'selected_tv_hotel_ids':[101]}]})
            self.assertEqual([1,3],[x['request_number'] for x in out]);self.assertEqual('2026-11-08',out[1]['params']['dateTo'])
            self.assertTrue(all(len(x['request_sha256'])==64 for x in out))
            (target/'request-0003.json').unlink()
            with self.assertRaises(FileNotFoundError):space['retained_effective_requests']({'exclusion_history':[{'operation_id':name,'selected_tv_hotel_ids':[101]}]})

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
            self.assertEqual(60,client.open.open.call_args.kwargs['timeout'])
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


class ProofSourceProjectionTest(unittest.TestCase):
    """Pure saved-source projections: useful signals never become native authority."""
    def setUp(self):
        def fail(reason): raise ValueError(reason)
        self.space={'json':json,'hashlib':hashlib,'re':__import__('re'),'fail':fail}
        exec(m.REMOTE_HANDLER,self.space)
        self.project=self.space['retained_reference_projection']
        self.structure=self.space['retained_result_structure']
        self.remaining={101:{'tv_hotel_id':101,'target':{'country_id':2}},
                        102:{'tv_hotel_id':102,'target':{'country_id':1}}}

    def capture(self,rows,optional=('tour_id','search_id','source')):
        columns=['id','hotel_id','operator_id','operator_name','operator_link','country_id','first_seen_at','last_seen_at']+list(optional)
        records=[{'kind':'scope','cohort':'common4-retained','catalog_id_namespace':'tourvisor_hotel',
                  'target_scope':'observed_and_legacy_ids','schema_columns':{'tour_operator_identity_observations':columns}}]
        records += [{'kind':'observation','row':row} for row in rows]
        raw=b''.join((json.dumps(x,ensure_ascii=False,separators=(',',':'))+'\n').encode() for x in records)
        return raw,{'scope':1,'observation':len(rows)}

    def row(self,n,**values):
        return dict(id=n,hotel_id=101,operator_id=13,operator_name='ANEX',operator_link=None,country_id=2,
                    first_seen_at='2026-10-19 12:38:21',last_seen_at='2026-10-20 00:43:30',**values)

    def test_optional_schema_presence_differs_from_null_empty_and_missing_fields(self):
        raw,kinds=self.capture([self.row(1)],optional=())
        summary,candidates=self.project(raw,self.remaining,p.operator_family,kinds)
        self.assertEqual({'tour_id':False,'search_id':False,'source':False},summary['schema_fields_present'])
        self.assertEqual({'field_missing':1},summary['field_states']['tour_id']);self.assertEqual([],candidates)
        raw,kinds=self.capture([self.row(1,tour_id=None,search_id=None,source=None),self.row(2,tour_id=' ',search_id='',source='\n')])
        summary,candidates=self.project(raw,self.remaining,p.operator_family,kinds)
        self.assertEqual({'tour_id':True,'search_id':True,'source':True},summary['schema_fields_present'])
        self.assertEqual({'null':1,'empty':1},summary['field_states']['tour_id'])
        self.assertFalse(summary['native_authority']);self.assertFalse(summary['account_binding_authority']);self.assertEqual([],candidates)

    def test_reference_signals_classify_opaque_boolean_large_and_outside_roster_without_public_value_leak(self):
        opaque='OPAQUE_PRIVATE_TOUR_REFERENCE_ABC987'
        search='PRIVATE_SEARCH_REFERENCE_XYZ654'
        private_source='https://private.invalid/path?token=DO_NOT_PUBLISH'
        rows=[self.row(1),self.row(2,tour_id=None,search_id=None,source=None),
              self.row(3,tour_id=' ',search_id='',source='\n'),
              self.row(4,tour_id=123456,search_id='789',source='user_search'),
              self.row(5,tour_id=opaque,search_id=search,source=private_source),
              self.row(6,tour_id=True,search_id=False,source=True),
              self.row(7,tour_id='x'*4097,search_id='y'*4097,source='z'*4097),
              self.row(8,tour_id={'id':999},search_id=[999],source=1.5)]
        outside=self.row(9,tour_id='OUTSIDE_PRIVATE_REFERENCE',search_id='OUTSIDE_SEARCH',source='OUTSIDE_SOURCE');outside['hotel_id']=999
        raw,kinds=self.capture(rows+[outside]);summary,candidates=self.project(raw,self.remaining,p.operator_family,kinds)
        self.assertEqual({'field_missing':1,'null':1,'empty':1,'positive_decimal':1,'opaque_scalar':1,'unsupported_type':2,'too_long':1},summary['field_states']['tour_id'])
        self.assertEqual(8,summary['counts']['remaining_observation_rows']);self.assertEqual(2,summary['counts']['reference_candidate_rows'])
        self.assertEqual(1,summary['remaining_targets_with_observations']);self.assertEqual([101],summary['candidate_tv_hotel_ids'])
        self.assertEqual(0,summary['counts']['country_disagreements'])
        self.assertEqual([4,5],[x['private_fields']['id'] for x in candidates])
        self.assertEqual(opaque,candidates[1]['private_fields']['tour_id']);self.assertEqual(private_source,candidates[1]['private_fields']['source'])
        self.assertEqual(hashlib.sha256(json.dumps(opaque,separators=(',',':')).encode()).hexdigest(),candidates[1]['reference_hashes']['tour_id'])
        lines=raw.splitlines(keepends=True)
        self.assertEqual(hashlib.sha256(lines[candidates[1]['line_number']-1]).hexdigest(),candidates[1]['line_sha256'])
        public=json.dumps(summary,ensure_ascii=False)
        for private in (opaque,search,private_source,'DO_NOT_PUBLISH','OUTSIDE_PRIVATE_REFERENCE','OUTSIDE_SEARCH','OUTSIDE_SOURCE'):
            self.assertNotIn(private,public)
        self.assertFalse(summary['native_authority']);self.assertFalse(summary['account_binding_authority'])

    def test_named_operator_signals_and_search_only_reference_preserve_country_conflict_without_numeric_binding_guess(self):
        rows=[self.row(1,tour_id='OPAQUE_ANEX_SIGNAL'),self.row(2,search_id='SEARCH_ONLY_SIGNAL'),
              self.row(3,tour_id='FUN_SIGNAL'),self.row(4,tour_id='HINT_ONLY_SIGNAL')]
        rows[2].update(operator_name='FUN&SUN',operator_id=25,country_id=1)
        rows[3].update(operator_name=None,operator_id=13)
        raw,kinds=self.capture(rows);summary,candidates=self.project(raw,self.remaining,p.operator_family,kinds)
        self.assertEqual(['ANEX','ANEX','FUN&SUN','unresolved'],[x['operator_family'] for x in candidates])
        self.assertEqual('field_missing',candidates[1]['field_states']['tour_id'])
        self.assertEqual([False,False,True,False],[x['country_disagreement'] for x in candidates])
        self.assertEqual(1,summary['counts']['country_disagreements'])
        self.assertFalse(summary['native_authority']);self.assertFalse(summary['account_binding_authority'])

    def test_capture_header_counts_and_unknown_record_type_cannot_silently_be_skipped(self):
        raw,kinds=self.capture([self.row(1,tour_id='PRIVATE')])
        with self.assertRaisesRegex(ValueError,'capture_counts'):
            self.project(raw,self.remaining,p.operator_family,dict(kinds,observation=2))
        records=[json.loads(x) for x in raw.splitlines()]
        records[0]['catalog_id_namespace']='samo_hotel'
        bad=b''.join(a.canonical(x)+b'\n' for x in records)
        with self.assertRaisesRegex(ValueError,'capture_scope'):self.project(bad,self.remaining,p.operator_family,kinds)
        bad=raw+a.canonical({'kind':'unexpected','row':{'secret':'PRIVATE'}})+b'\n'
        with self.assertRaisesRegex(ValueError,'capture_kind'):self.project(bad,self.remaining,p.operator_family,kinds)

    def test_saved_results_empty_and_unrecognized_envelopes_are_distinct_and_neither_has_native_authority(self):
        empty={'data':{'hotels':[],'total':0,'hasMore':False,'next':'https://private.invalid/signed?token=PRIVATE_VALUE'}}
        out=self.structure(empty,a.shape,{101})
        self.assertEqual('recognized_empty',out['shape_state']);self.assertEqual({},out['counts'])
        self.assertEqual({'total':0,'hasMore':False},out['unwrapped_pagination_numeric']);self.assertFalse(out['native_authority'])
        self.assertNotIn('PRIVATE_VALUE',json.dumps(out))
        out=self.structure({'data':{'unknown_envelope':[],'message':'PRIVATE_API_MESSAGE'}},a.shape,{101})
        self.assertEqual('unrecognized_envelope',out['shape_state']);self.assertFalse(out['native_authority'])
        self.assertNotIn('PRIVATE_API_MESSAGE',json.dumps(out))

    def test_exact_inspection_parser_and_stock_compressed_command_keep_old_modes_closed(self):
        import base64,zlib
        c=core();m.register_parser(c);profile=m.PROOF_SOURCE_INSPECTION
        fields=[SHA,'match-retained-readback',profile['operation_id'],profile['cohort'],profile['target_operation_id']]
        command=c.parse_command(c.PREFIX+' '.join(fields))
        for index,value in ((1,'match-retained-project'),(2,'int-andromeda-match-retained-readback-20261020-v6'),
                            (3,m.NEXT_SCOPE['cohort']),(4,'int-andromeda-match-retained-20261020-v4')):
            changed=fields.copy();changed[index]=value
            # v6 has its own immutable target/cohort; neither profile may be retargeted.
            with self.subTest(field=index),self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(changed))
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaisesRegex(ValueError,'first_attempt'):
            c.parse_command(c.PREFIX+' '.join(fields))
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,command)
        encoded=base64.b64encode(zlib.compress(c.REMOTE.encode(),9)).decode()
        remote_command="python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'"
        self.assertLessEqual(len(remote_command.encode()),65536)
        ast.parse(c.REMOTE)
        self.assertEqual(m.SOURCE_HASHES,dict(m.bundle_source(SOURCE)[1]))

    def test_saved_results_count_all_rows_before_selected_filter_and_observe_aliases_without_promoting_them(self):
        opaque='PRIVATE_OPAQUE_TOUR_ID'
        rows=[{'id':101,'name':'PRIVATE_HOTEL_NAME','tours':[
                    {'operator':13,'id':321},{'operatorId':13,'id':opaque},
                    {'operator':{'id':13},'tour_id':765},{'operator_id':18,'tourId':999},'PRIVATE_NONOBJECT_TOUR']},
              {'id':909,'tours':[{'operator':13,'id':987}]},
              {'hotelId':102,'tours':[{'operator':13,'id':654}]},
              {'id':102,'tours':{'operator':13,'id':555}},'PRIVATE_NONOBJECT_HOTEL',{'id':110,'tours':None}]
        value={'data':{'results':rows,'total':6,'page':1,'hasNext':True,'next':'https://private.invalid/signed'},
               'token':'PRIVATE_TOKEN','signedUrl':'https://private.invalid/token'}
        out=self.structure(value,a.shape,{101,102});counts=out['counts']
        self.assertEqual('recognized_nonempty',out['shape_state']);self.assertEqual([101,102],out['selected_hotel_ids'])
        self.assertEqual({'raw_hotel_rows':6,'nonobject_hotel_rows':1,'selected_hotel_rows':2,'hotel_alias_presence_rows':1,
                          'nonlist_tours_rows':2,'raw_tour_rows':7,'nonobject_tour_rows':1,'alternate_tour_id_presence':1,
                          'missing_or_nonnumeric_tour_ids':2,'selected_operator13_tours':3,
                          'selected_operator13_numeric_tour_ids':1,'selected_operator13_unusable_tour_ids':2},counts)
        self.assertEqual({'13':5,'18':1},out['operator_id_counts'])
        self.assertEqual({'total':6,'page':1,'hasNext':True},out['unwrapped_pagination_numeric'])
        self.assertNotIn('token',out['root_keys']);self.assertNotIn('signedUrl',out['root_keys'])
        public=json.dumps(out)
        for private in (opaque,'PRIVATE_HOTEL_NAME','PRIVATE_TOKEN','PRIVATE_NONOBJECT_TOUR','PRIVATE_NONOBJECT_HOTEL','https://private.invalid'):
            self.assertNotIn(private,public)
        self.assertFalse(out['native_authority'])


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


class CatalogueTest(unittest.TestCase):
    """Exercise trusted catalogue through the same parser, carrier and receiver."""
    def setUp(self):
        self.saved_snapshot = m._CATALOGUE_SNAPSHOT
        m._CATALOGUE_SNAPSHOT = None
        self.raw = (ROOT/m.CATALOGUE_PATH).read_bytes()
        self.catalogue = json.loads(self.raw)
        self.members = self.catalogue['scopes']
        self.policies = m.validate_catalogue(self.raw)

    def tearDown(self):
        m._CATALOGUE_SNAPSHOT = self.saved_snapshot

    def parse(self, scope, mode='match-retained-acquire'):
        c = core(); m.register_parser(c)
        operation = scope['operation_id'] if mode == 'match-retained-acquire' else scope['write_operation_id']
        target = scope['parent_operation_id'] if mode == 'match-retained-acquire' else scope['operation_id']
        body = c.PREFIX+' '.join((SHA,mode,operation,scope['cohort'],target))
        return c, c.parse_command(body), body

    def remote_space(self, policy, command, private=None):
        def fail(reason): raise ValueError(reason)
        space = {'hashlib':hashlib,'json':json,'re':__import__('re'),'fail':fail,
                 'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=bound,
                 'safe_json':lambda path,bound:json.loads(path.read_bytes()),
                 'NEXT_SCOPE':policy,'payload':command,'pathlib':__import__('pathlib'),'os':os,
                 'operation':command['operation_id'],'private':private}
        exec(m.REMOTE_HANDLER,space)
        return space

    def result(self, scope):
        _, command, _ = self.parse(scope)
        policy = self.policies[scope['cohort']]
        data = dict(command,control_source_sha=CONTROL,state='acquired_retained_native_read_only',
                    acquisition_state='completed',unknown_http_calls=0,supplier_calls=3,
                    physical_http_reservations=3,tariff_search_units=1,database_writes=0,mapping_writes=0,
                    no_replay=True,safe_to_write_now=False,continuation_scope_sha256=policy['sha256'],
                    continuation_catalogue_sha256=policy['catalogue_sha256'],scope_id=scope['scope_id'],
                    context_origin=scope['context_origin'],wire_context=copy.deepcopy(scope['wire_context']),
                    selection_reservation_sha256='c'*64,catalogue_sibling_history_sha256='d'*64)
        result = dict(command,status='complete',production_unchanged=True,supplier_calls=3,
                      database_writes=0,mapping_writes=0,match_retained={'data':data})
        self.seal(result)
        return command,result

    def seal(self,result):
        result['match_retained']['evidence_sha256'] = hashlib.sha256(a.canonical(result['match_retained']['data'])).hexdigest()

    def test_catalogue_baseline_bounds_party_and_distinct_operation_rejections(self):
        self.assertEqual(2,len(self.policies))
        self.assertEqual([2,8],[len(x['selected_tv_hotel_ids']) for x in self.members])
        mutations = [('supplier_http_maximum',31),('search_start_maximum',2),('http_timeout_seconds',30),
                     ('operator_id',18),('account','TOURVISOR_JWT'),('adults',7),('children_count',1),
                     ('children_count',False),('safe_to_write_now',True),('selected_count',True),
                     ('readback_operation_id','int-andromeda-match-retained-readback-20261020-v8'),
                     ('operation_id',m.NEXT_NEVER9_SCOPE['operation_id'])]
        for field,value in mutations:
            with self.subTest(field=field):
                bad=copy.deepcopy(self.catalogue);bad['scopes'][0][field]=value
                with self.assertRaises(ValueError):m.validate_catalogue(a.canonical(bad))
        for change in ('unknown','prior','parent','overlap','operation'):
            bad=copy.deepcopy(self.catalogue)
            if change=='unknown':bad['scopes'][0]['exclusion_history'][1]['unknown_http_calls']=0
            elif change=='prior':bad['scopes'][0]['prior_selected_hotel_ids'].pop()
            elif change=='parent':bad['scopes'][0]['parent_evidence_sha256']='0'*64
            elif change=='overlap':bad['scopes'][1]['selected_tv_hotel_ids'][0]=bad['scopes'][0]['selected_tv_hotel_ids'][0]
            else:bad['scopes'][1]['operation_id']=bad['scopes'][0]['operation_id']
            with self.subTest(change=change),self.assertRaises(ValueError):m.validate_catalogue(a.canonical(bad))
        with self.assertRaisesRegex(ValueError,'duplicate_key'):
            m.validate_catalogue(b'{"schema_version":1,"schema_version":1,"kind":"retained_never_selected_catalogue","scopes":[]}')

    def test_both_catalogue_members_round_trip_through_parser_bundle_remote_and_helper(self):
        for scope in self.members:
            with self.subTest(cohort=scope['cohort']),tempfile.TemporaryDirectory() as td:
                c,command,body=self.parse(scope);policy=self.policies[scope['cohort']]
                with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,dict(command))
                module=ast.parse(c.REMOTE)
                injected={node.targets[0].id:ast.literal_eval(node.value) for node in module.body
                          if isinstance(node,ast.Assign) and len(node.targets)==1 and isinstance(node.targets[0],ast.Name)
                          and node.targets[0].id=='NEXT_SCOPE'}
                self.assertEqual(policy,injected['NEXT_SCOPE'])
                archive,hashes=c.bundle_source(SOURCE);stage=Path(td)
                with tarfile.open(fileobj=io.BytesIO(archive),mode='r:gz') as tar:tar.extractall(stage,filter='data')
                manifest=json.loads((stage/'manifest.json').read_bytes())
                self.assertEqual(hashes,manifest['files']);self.assertEqual(policy['catalogue_sha256'],hashes[m.CATALOGUE_PATH])
                self.assertEqual(set(m.SOURCE_FILES)|{m.CATALOGUE_PATH},set(hashes))
                loaded=self.remote_space(policy,command)['retained_next_scope'](stage)
                received={'continuation_scope':loaded,'continuation_scope_path':m.CATALOGUE_PATH,
                          'continuation_scope_sha256':policy['sha256'],'continuation_catalogue_sha256':policy['catalogue_sha256']}
                self.assertEqual(scope,a.load_scope_file(stage,received))
                for changed in (body.replace(scope['operation_id'],scope['operation_id']+'-renamed'),
                                body.replace(scope['parent_operation_id'],'int-andromeda-match-retained-current-20261020-v1'),
                                body.replace(scope['cohort'],self.members[1 if scope is self.members[0] else 0]['cohort'])):
                    with self.assertRaises(ValueError):c.parse_command(changed)

    def test_catalogue_receiver_rejects_wrong_path_digest_manifest_and_member_even_with_new_outer_hash(self):
        scope=self.members[0];policy=self.policies[scope['cohort']]
        with tempfile.TemporaryDirectory() as td:
            stage=Path(td);archive,_=m.bundle_source(SOURCE,catalogue=True)
            with tarfile.open(fileobj=io.BytesIO(archive),mode='r:gz') as tar:tar.extractall(stage,filter='data')
            data={'continuation_scope':copy.deepcopy(scope),'continuation_scope_path':m.CATALOGUE_PATH,
                  'continuation_scope_sha256':policy['sha256'],'continuation_catalogue_sha256':policy['catalogue_sha256']}
            self.assertEqual(scope,a.load_scope_file(stage,data))
            for field,value in [('continuation_scope_path','../catalogue.json'),('continuation_scope_sha256','0'*64),
                                ('continuation_catalogue_sha256','0'*64),('continuation_scope',self.members[1])]:
                with self.subTest(field=field),self.assertRaises(ValueError):a.load_scope_file(stage,dict(data,**{field:value}))
            path=stage/m.CATALOGUE_PATH;manifest_path=stage/'manifest.json';original_manifest=manifest_path.read_bytes()
            bad=copy.deepcopy(self.catalogue);bad['scopes'][0]['context_reason']='tampered member'
            changed=a.canonical(bad);path.write_bytes(changed);digest=hashlib.sha256(changed).hexdigest()
            manifest=json.loads(original_manifest);manifest['files'][m.CATALOGUE_PATH]=digest;manifest_path.write_bytes(a.canonical(manifest))
            with self.assertRaisesRegex(ValueError,'member_binding'):
                a.load_scope_file(stage,dict(data,continuation_catalogue_sha256=digest))
            path.write_bytes(self.raw);manifest_path.write_bytes(original_manifest)
            manifest=json.loads(original_manifest);manifest['files'].pop(m.CATALOGUE_PATH);manifest_path.write_bytes(a.canonical(manifest))
            with self.assertRaisesRegex(ValueError,'catalogue_binding'):a.load_scope_file(stage,data)
            manifest_path.write_bytes(original_manifest);saved=stage/'catalogue-real.json';path.rename(saved);path.symlink_to(saved)
            with self.assertRaisesRegex(ValueError,'catalogue_path'):a.load_scope_file(stage,data)

    def test_remote_next_scope_rejects_changed_catalogue_and_wrong_selected_member_hash(self):
        scope=self.members[0];policy=copy.deepcopy(self.policies[scope['cohort']]);_,command,_=self.parse(scope)
        with tempfile.TemporaryDirectory() as td:
            stage=Path(td);path=stage/m.CATALOGUE_PATH;path.parent.mkdir(parents=True);path.write_bytes(self.raw)
            space=self.remote_space(policy,command)
            self.assertEqual(scope,space['retained_next_scope'](stage))
            path.write_bytes(self.raw+b'\n')
            with self.assertRaisesRegex(ValueError,'stage_binding'):space['retained_next_scope'](stage)
            path.write_bytes(self.raw);space['NEXT_SCOPE']=dict(policy,sha256='0'*64)
            with self.assertRaisesRegex(ValueError,'stage_member'):space['retained_next_scope'](stage)
            space['NEXT_SCOPE']=policy;space['payload']=dict(command,match_retained_catalogue_sha256='0'*64)
            with self.assertRaisesRegex(ValueError,'stage_shape'):space['retained_next_scope'](stage)

    def test_catalogue_parse_snapshot_cannot_drift_before_activation_or_weaken_by_returned_mutation(self):
        with tempfile.TemporaryDirectory() as td:
            root=Path(td);modulepath=root/'scripts/deploy/control.py';modulepath.parent.mkdir(parents=True)
            path=root/m.CATALOGUE_PATH;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(self.raw)
            with mock.patch.object(m,'__file__',str(modulepath)),mock.patch.object(m,'_CATALOGUE_SNAPSHOT',None):
                raw,policies=m.catalogue_snapshot();policies[self.members[0]['cohort']]['wire_context']['nights_from']=28
                self.assertNotEqual(28,m.catalogue_snapshot()[1][self.members[0]['cohort']]['wire_context']['nights_from'])
                c,command,_=self.parse(self.members[0]);remote_before=c.REMOTE
                path.write_bytes(raw+b'\n')
                with self.assertRaisesRegex(ValueError,'changed_after_parse'),mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):
                    m.activate(c,dict(command))
                self.assertEqual(remote_before,c.REMOTE)

    def test_completion_binds_each_member_catalogue_and_real_bounded_counters(self):
        for scope in self.members:
            command,result=self.result(scope)
            self.assertTrue(m.completed_result(command,result,CONTROL))
            for field,value in [('continuation_catalogue_sha256','0'*64),('continuation_scope_sha256','0'*64),
                                ('scope_id','0'*64),('supplier_calls',31),('physical_http_reservations',2),
                                ('tariff_search_units',2),('selection_reservation_sha256',None),
                                ('catalogue_sibling_history_sha256',None),
                                ('wire_context',dict(scope['wire_context'],nights_to=28))]:
                with self.subTest(cohort=scope['cohort'],field=field):
                    bad=copy.deepcopy(result);bad['match_retained']['data'][field]=value
                    if field=='supplier_calls':bad['supplier_calls']=value
                    self.seal(bad);self.assertFalse(m.completed_result(command,bad,CONTROL))
            bad_command=dict(command,match_retained_catalogue_sha256='0'*64)
            self.assertFalse(m.completed_result(bad_command,result,CONTROL))

    def test_durable_hotel_claims_allow_disjoint_siblings_but_preserve_all204_and_block_rename(self):
        prior=self.members[0]['prior_selected_hotel_ids']
        with tempfile.TemporaryDirectory() as td:
            home=Path(td);root=home/'.anytoour-match';root.mkdir();ledger=root/'retained-selection-claims.json'
            old={'schema_version':1,'claims':{'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'prior_consumed','sentinel':h} for h in prior}}
            ledger.write_bytes(a.canonical(old));context=root/'retained-context-claims.json';context.write_bytes(b'{"protected":"old-unknown-context"}')
            protected_context=context.read_bytes()
            for scope in self.members:
                before=ledger.read_bytes();a.reserve_selection(home,scope,scope['operation_id'])
                after=ledger.read_bytes();self.assertNotEqual(before,after)
                snapshot=json.loads(after)
                for key,value in old['claims'].items():self.assertEqual(value,snapshot['claims'][key])
                self.assertEqual(protected_context,context.read_bytes())
                with self.assertRaisesRegex(ValueError,'consumed_no_replay'):
                    a.reserve_selection(home,scope,scope['operation_id']+'-renamed-next-day')
                self.assertEqual(after,ledger.read_bytes())
            self.assertEqual(204+10,len(json.loads(ledger.read_bytes())['claims']))

    def test_sibling_reserved_without_receipt_or_partial_unknown_stops_before_history_or_supplier(self):
        scope=self.members[1];policy=self.policies[scope['cohort']];_,command,_=self.parse(scope);sibling=self.members[0]
        with tempfile.TemporaryDirectory() as td:
            base=Path(td);private=base/'private';private.mkdir();home=base/'home';(home/'.anytoour-match').mkdir(parents=True)
            ledger=home/'.anytoour-match/retained-selection-claims.json'
            ledger.write_bytes(a.canonical({'schema_version':1,'claims':{'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'prior'} for h in scope['prior_selected_hotel_ids']}}))
            stage=base/'stage';path=stage/m.CATALOGUE_PATH;path.parent.mkdir(parents=True);path.write_bytes(self.raw)
            target=private/sibling['operation_id'];target.mkdir()
            (target/'reservation.json').write_bytes(a.canonical({'operation_id':sibling['operation_id'],'source_sha':SHA}))
            space=self.remote_space(policy,command,private)
            space['retained_effective_requests']=mock.Mock(side_effect=AssertionError('must stop before historical reads'))
            before={p:p.read_bytes() for p in target.iterdir()}
            with mock.patch.dict(os.environ,{'HOME':str(home)}),self.assertRaisesRegex(ValueError,'sibling_unknown_no_replay'):
                space['retained_catalogue_history'](scope,stage)
            self.assertEqual(before,{p:p.read_bytes() for p in target.iterdir()})
            old=target/'source'/m.CATALOGUE_PATH;old.parent.mkdir(parents=True);old.write_bytes(self.raw)
            (target/'source/manifest.json').write_bytes(a.canonical({'schema_version':1,'files':{m.CATALOGUE_PATH:policy['catalogue_sha256']}}))
            data={'acquisition_state':'partial_no_replay','unknown_http_calls':1}
            (target/'result.json').write_bytes(a.canonical({'status':'complete','mode':'match-retained-acquire','match_retained':{'data':data}}))
            with mock.patch.dict(os.environ,{'HOME':str(home)}),self.assertRaisesRegex(ValueError,'sibling_unknown_no_replay'):
                space['retained_catalogue_history'](scope,stage)
            space['retained_effective_requests'].assert_not_called()
            ledger_state=json.loads(ledger.read_bytes());ledger_state['claims']['TOURVISOR_ANEX_JWT:13:999999999']={'state':'reserved'}
            ledger.write_bytes(a.canonical(ledger_state))
            with mock.patch.dict(os.environ,{'HOME':str(home)}),self.assertRaisesRegex(ValueError,'unreconciled_claim_no_replay'):
                space['retained_catalogue_history'](scope,stage)

    def test_completed_sibling_retained_old_catalogue_append_real_history_wire_and_snapshot_binding(self):
        # Canonical sanitized data are retained public receipts, matched to all eight pinned evidence hashes.
        # Request metadata below is a controlled UNIT fixture, not reconstructed historical supplier requests.
        baseline_data = self._catalogue_baseline_receipts_for_test()
        baseline_by_op={x['operation_id']:x for x in baseline_data}
        scope=self.members[1];sibling=self.members[0];policy=self.policies[scope['cohort']];_,command,_=self.parse(scope)
        history=scope['exclusion_history']+scope['context_history']
        self.assertEqual(3,sum(x['unknown_http_calls'] for x in baseline_data))
        self.assertEqual(set(x['operation_id'] for x in history),set(baseline_by_op))
        for entry in history:
            self.assertEqual(entry['evidence_sha256'],hashlib.sha256(a.canonical(baseline_by_op[entry['operation_id']])).hexdigest())
        with tempfile.TemporaryDirectory() as td:
            base=Path(td);private=base/'private';private.mkdir();home=base/'home';(home/'.anytoour-match').mkdir(parents=True)
            claims={'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'prior'} for h in scope['prior_selected_hotel_ids']}
            claims.update({'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'reserved_before_http_no_replay','operation_id':sibling['operation_id']} for h in sibling['selected_tv_hotel_ids']})
            (home/'.anytoour-match/retained-selection-claims.json').write_bytes(a.canonical({'schema_version':1,'claims':claims}))
            stage=base/'stage';current_path=stage/m.CATALOGUE_PATH;current_path.parent.mkdir(parents=True);current_path.write_bytes(self.raw)
            def fixture(name,data,ids,wire=None,country=1,departure=1,adults=2):
                target=private/name;target.mkdir()
                source=data['source_sha'];digest=hashlib.sha256(a.canonical(data)).hexdigest()
                wrapper={'status':'complete','mode':'match-retained-acquire','production_unchanged':True,
                         'operation_id':name,'source_sha':source,'database_writes':0,'mapping_writes':0,
                         'match_retained':{'data':data,'evidence_sha256':digest}}
                for filename,value in [('result.json',wrapper),('reservation.json',{'operation_id':name,'source_sha':source}),
                                       ('acquisition-plan.json',{'hotel_ids':ids})]:
                    (target/filename).write_bytes(a.canonical(value))
                wire=wire or data.get('wire_context') or {'date_from':'2099-01-01','date_to':'2099-01-01','nights_from':7,'nights_to':7}
                for number in range(1,data['physical_http_reservations']+1):
                    params={'hotelIds':ids[:30],'operatorIds':[13],'countryId':country,'departureId':departure,'adults':adults,'childs':[],
                            'dateFrom':wire['date_from'],'dateTo':wire['date_to'],'nightsFrom':wire['nights_from'],'nightsTo':wire['nights_to']}
                    value={'action':'search_start' if number<=data['tariff_search_units'] else 'search_status','path':'/tours/search','params':params}
                    (target/('request-%04d.json'%number)).write_bytes(a.canonical(value))
                return target
            for entry in history:
                fixture(entry['operation_id'],baseline_by_op[entry['operation_id']],entry['selected_tv_hotel_ids'])
            old_raw=json.dumps({'schema_version':1,'kind':'retained_never_selected_catalogue','scopes':[sibling]},indent=1).encode()+b'\n'
            old_digest=hashlib.sha256(old_raw).hexdigest()
            self.assertNotEqual(policy['catalogue_sha256'],old_digest)
            member_digest=hashlib.sha256(a.canonical(sibling)).hexdigest()
            sibling_data={'operation_id':sibling['operation_id'],'source_sha':SHA,'acquisition_state':'completed','unknown_http_calls':0,
                          'scope_id':sibling['scope_id'],'continuation_scope_sha256':member_digest,'continuation_catalogue_sha256':old_digest,
                          'cohort':sibling['cohort'],'target_operation_id':scope['parent_operation_id'],'parent_evidence_sha256':scope['parent_evidence_sha256'],
                          'wire_context':sibling['wire_context'],'context_origin':sibling['context_origin'],'selected_hotels':sibling['selected_count'],
                          'selected_id_sha256':hashlib.sha256(json.dumps(sibling['selected_tv_hotel_ids'],separators=(',',':')).encode()).hexdigest(),
                          'supplier_calls':3,'physical_http_reservations':3,'tariff_search_units':1,'actions':{'search_start':1,'search_status':1,'search_results':1},
                          'batches':[{'batch':1,'search_complete':True,'sent':sibling['selected_count'],'returned_anex_hotels':0}],'edges':[],'edge_states':{},'safe_to_write_now':False,'database_writes':0,'mapping_writes':0}
            target=fixture(sibling['operation_id'],sibling_data,sibling['selected_tv_hotel_ids'],sibling['wire_context'],sibling['country_id'],sibling['departure_id'],sibling['adults'])
            old_path=target/'source'/m.CATALOGUE_PATH;old_path.parent.mkdir(parents=True);old_path.write_bytes(old_raw)
            (target/'source/manifest.json').write_bytes(a.canonical({'schema_version':1,'files':{m.CATALOGUE_PATH:old_digest}}))
            space=self.remote_space(policy,command,private)
            protected={p:p.read_bytes() for p in base.rglob('*.json')}
            with mock.patch.dict(os.environ,{'HOME':str(home)}):
                before,requests=space['retained_catalogue_history'](scope,stage)
                after,rechecked=space['retained_catalogue_history'](scope,stage)
            self.assertEqual(before,after);self.assertEqual(requests,rechecked)
            self.assertEqual(19,len(requests));self.assertEqual(old_digest,before[sibling['operation_id']]['catalogue'])
            self.assertEqual(protected,{p:p.read_bytes() for p in base.rglob('*.json')})
            reservation=target/'reservation.json';reservation.write_bytes(reservation.read_bytes()+b'\n')
            with mock.patch.dict(os.environ,{'HOME':str(home)}):changed,_=space['retained_catalogue_history'](scope,stage)
            self.assertNotEqual(before,changed)  # The caller's post-child snapshot comparison must stop on this drift.
            request_path=target/'request-0001.json';request=json.loads(request_path.read_bytes());request['params']['dateFrom']='2099-02-02'
            request_path.write_bytes(a.canonical(request))
            with mock.patch.dict(os.environ,{'HOME':str(home)}),self.assertRaisesRegex(ValueError,'catalogue_sibling_wire'):
                space['retained_catalogue_history'](scope,stage)

    def _catalogue_baseline_receipts_for_test(self):
        # Existing immutable sanitized baseline data moved here unchanged, not duplicated.
        # Controlled request metadata below are UNIT fixtures, not recovered supplier wire.
        return json.loads(r'''[{"acquisition_state":"completed","actions":{"search_results":10,"search_start":10,"search_status":10,"tour_detail":8},"batches":[{"batch":1,"returned_anex_hotels":0,"search_complete":true,"sent":30},{"batch":2,"returned_anex_hotels":2,"search_complete":true,"sent":29},{"batch":3,"returned_anex_hotels":2,"search_complete":true,"sent":23},{"batch":4,"returned_anex_hotels":0,"search_complete":true,"sent":9},{"batch":5,"returned_anex_hotels":0,"search_complete":true,"sent":5},{"batch":6,"returned_anex_hotels":1,"search_complete":true,"sent":3},{"batch":7,"returned_anex_hotels":1,"search_complete":true,"sent":2},{"batch":8,"returned_anex_hotels":1,"search_complete":true,"sent":2},{"batch":9,"returned_anex_hotels":1,"search_complete":true,"sent":1},{"batch":10,"returned_anex_hotels":0,"search_complete":true,"sent":1}],"cohort":"anex105-current","control_source_sha":"2b5dedca28d912ebcbc9ad1acf322f49ebabbf57","database_writes":0,"edge_states":{"native_link_unresolved":1,"verified_native_pair":7},"edges":[{"anex_hotel_id":"9384","batch":2,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":35297,"detail_operator_id":13,"detail_raw":{"raw_sha256":"16a379a69a3d21dc420388068ff3267d704b9e8fdc3cc17ba5846b23fbdbf157","response_number":7},"namespace":"anex_online","operator_id":13,"operator_link_field":"str","operator_link_host":"agent.anextour.ru","operator_link_sha256":"066109b41e7a18b8f5b0b00e21de3184181842feecabd723f2dfda3452725681","search_id_sha256":"ac6b18f91dad56ecd53df63f4b8d9543c57cfe11ae7aafa917ce23d88ea747cf","state":"verified_native_pair","tour_id_sha256":"64adfca50f4b64fd96c8b97ddb95305d47ea54c765b4bbf3d4ed2edc7d53df0a","tv_hotel_id":35297},{"anex_hotel_id":"12901","batch":2,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":1474,"detail_operator_id":13,"detail_raw":{"raw_sha256":"ac10f71e19cd380168fb8c879ada7e51c99d85c74a7dd401808139e65859c326","response_number":8},"namespace":"anex_online","operator_id":13,"operator_link_field":"str","operator_link_host":"agent.anextour.ru","operator_link_sha256":"8edcebaef9232ccee049632b59716f4412e302e29b5dfe29006394077a84a52a","search_id_sha256":"ac6b18f91dad56ecd53df63f4b8d9543c57cfe11ae7aafa917ce23d88ea747cf","state":"verified_native_pair","tour_id_sha256":"751759b64916cc8f74bcdeae4a8009847c0037e49e5614b3bc5cf52aa28e7025","tv_hotel_id":1474},{"batch":3,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":17603,"detail_operator_id":13,"detail_raw":{"raw_sha256":"da92ea7e50911b596dac625007091d9264f525e41e0f711b1d3577c99f160645","response_number":12},"link_state":"missing_native","operator_link_field":"str","search_id_sha256":"4956f15c23b8164fac9a78667f7f9e68d53ad327100bc6d9b73247e9c1a35c90","state":"native_link_unresolved","tour_id_sha256":"b4222d773463c2045312792fe35e867521aa202246bbd8e465d6e728ba450d62","tv_hotel_id":17603},{"anex_hotel_id":"8475","batch":3,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":1014,"detail_operator_id":13,"detail_raw":{"raw_sha256":"e4d97de7f061f33e286cada311d7207ee06fd70c84b10aab3065a73c442b94be","response_number":13},"namespace":"anex_online","operator_id":13,"operator_link_field":"str","operator_link_host":"agent.anextour.ru","operator_link_sha256":"756f83bc93bc5203fcee26c313f27346ad0c458d7390feb6c841e4ae85d1762e","search_id_sha256":"4956f15c23b8164fac9a78667f7f9e68d53ad327100bc6d9b73247e9c1a35c90","state":"verified_native_pair","tour_id_sha256":"955f4e7a5343c5deb58de8ccdb010f17ad2e05901c17631ad59d6a627b5f90a2","tv_hotel_id":1014},{"anex_hotel_id":"8476","batch":6,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":1095,"detail_operator_id":13,"detail_raw":{"raw_sha256":"d0e16b529c87365130e2af6cfdc66392c321f68084c0ea43dcbf773ba4e9d4cb","response_number":23},"namespace":"anex_online","operator_id":13,"operator_link_field":"str","operator_link_host":"agent.anextour.ru","operator_link_sha256":"9619d94cb74cb263bc4fbc00029e65f83794b594d3a1c3e3e2524c5da2aae374","search_id_sha256":"58850171742bd5dfd5a3f5090957c2633cea8356bdf52add148c04587ab94c30","state":"verified_native_pair","tour_id_sha256":"362bc7af6531df8198b8259631ac0940735afda98eb820b928795e16dd3f7777","tv_hotel_id":1095},{"anex_hotel_id":"14818","batch":7,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":1015,"detail_operator_id":13,"detail_raw":{"raw_sha256":"0702edaf81662711ee1c820d9d93ff082b6f6199ec9a8592e180c0a7e5d6d51b","response_number":27},"namespace":"anex_online","operator_id":13,"operator_link_field":"str","operator_link_host":"agent.anextour.ru","operator_link_sha256":"7de6a79459ee28f2f7e676b8613ac7cfba2b4df3dc0d2e480379050ce9dde577","search_id_sha256":"6e627a14f0d0ebbe502426219e8bdd1621084eb634a5f41b57805a5ae4101d64","state":"verified_native_pair","tour_id_sha256":"0116aba77ab3089b6198c2f42074c2a7f0e306fda6ca81a99b635dc955e43c14","tv_hotel_id":1015},{"anex_hotel_id":"26591","batch":8,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":3418,"detail_operator_id":13,"detail_raw":{"raw_sha256":"86af6ed1b845cbc6dc657724915278e9a8fc25fa90660896435247ae5d44d3bd","response_number":31},"namespace":"anex_online","operator_id":13,"operator_link_field":"str","operator_link_host":"agent.anextour.ru","operator_link_sha256":"cbb83ec95ff92a44d7e3d152d32149b9774881de27b721ff88e71d01ed4e20de","search_id_sha256":"8ecaee202ecdea3da4047d5e3dd1c375003d467e1bc86c7d3c2d411f2439d2b1","state":"verified_native_pair","tour_id_sha256":"9aebbd2598d3a7298987bda42f5597102605aa04380150df96c01272fe095693","tv_hotel_id":3418},{"anex_hotel_id":"19606","batch":9,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":57027,"detail_operator_id":13,"detail_raw":{"raw_sha256":"51db1443a805c0f3b92e40a8203be3fdf0aa5991ec2fce4217a750858ee695e4","response_number":35},"namespace":"anex_online","operator_id":13,"operator_link_field":"str","operator_link_host":"agent.anextour.ru","operator_link_sha256":"ece310fa94dcaed04827a64de8c3c0297df95ad7e30ef93e2ba472bc6c937939","search_id_sha256":"233b1a74ebb75a085903f508291f9a685cca2eb6fece0d1eb7d958c6c4f7278d","state":"verified_native_pair","tour_id_sha256":"ca86516d001852ab7ebc8785245d28cf842869c824d3bcf3aff08080cdeb1630","tv_hotel_id":57027}],"global_usage":"not_measured","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v1","operator_account_binding":{"account":"TOURVISOR_ANEX_JWT","detail_raw":{"raw_sha256":"16a379a69a3d21dc420388068ff3267d704b9e8fdc3cc17ba5846b23fbdbf157","response_number":7},"operator_id":13,"state":"verified_live_operator13_anextour_link","tv_hotel_id":35297},"parent_evidence_sha256":"c0d7c1f53cceea267f08a5b15d29d9f2bb314401b7de380e0fe40b59ad513046","parent_receipt_sha256":"65bc5fea35777627e13cd9143c2537288fc0af72ea0642323b3c457c6afe10fb","physical_http_reservations":38,"quota_before":{"physical_http_attempts":0,"provider_day":"2026-10-20","tariff_search_units":0},"safe_to_write_now":false,"selected_hotels":105,"selected_id_sha256":"4f8fa702fad1445d2a6bb5978bbdc1e60f30abd75b9328251f381a86f2b351d3","source_sha":"7e388eeacb1715ba7abe86b67b26576c4e110ee9","state":"acquired_retained_native_read_only","stop_reason":null,"supplier_calls":38,"target_operation_id":"int-andromeda-match-retained-current-20261020-v1","tariff_search_units":10,"unknown_http_calls":0},{"acquisition_state":"partial_no_replay","actions":{"search_results":2,"search_start":2,"search_status":2,"tour_detail":1},"batches":[{"batch":1,"returned_anex_hotels":1,"search_complete":true,"sent":29}],"cohort":"anex-next-e424c34b2b9d970f","continuation_scope_sha256":"c0b1651a689325017203ee9529a26c87a4154061675cf10fb72786864a84997e","control_source_sha":"abad0e7c70c60f7d693bebb236c9800dacb6a557","database_writes":0,"edge_states":{"native_link_unresolved":1},"edges":[{"batch":1,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":8038,"detail_operator_id":13,"detail_raw":{"raw_sha256":"b6bc670f30163350d5940b2f29bc79d35f4b74c0090f4d255daaef2b84889328","response_number":4},"link_state":"missing_native","operator_link_field":"str","search_id_sha256":"03f577be1ab04da0aacfc7010b184b59752415f945aea23b6047d921fcd1226f","state":"native_link_unresolved","tour_id_sha256":"4ed7c5013dc0b587b74001aae7bacb85aaa4fb1a36fd3e07c0f5774358f76bd5","tv_hotel_id":8038}],"global_usage":"not_measured","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v2","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":7,"quota_before":{"physical_http_attempts":38,"provider_day":"2026-10-20","tariff_search_units":10},"safe_to_write_now":false,"scope_id":"e424c34b2b9d970ff21026d97b1a4cf4bacc1e39f3023d0b01f8088cd9359525","selected_hotels":55,"selected_id_sha256":"04b7255c36d52f189dc2dd0e0b622806af8d7e9c898bbed9bce193d2fcfe22d4","source_sha":"d871c971f993534363be256977aaec2a52418eff","state":"acquired_retained_native_read_only","stop_reason":"TimeoutError","supplier_calls":7,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":2,"unknown_http_calls":1},{"acquisition_state":"partial_no_replay","actions":{"search_start":1},"batches":[],"cohort":"anex-next-d0017d8fffb4f69d","continuation_scope_sha256":"9e6d4c654ae791327b5288fc8d87593a8fc79dc93c84f253b2fe90ba5fef32ba","control_source_sha":"acd92b8ea6ec10e8f8f577587f377751699e0541","database_writes":0,"edge_states":{},"edges":[],"global_usage":"not_measured","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v3","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":1,"quota_before":{"physical_http_attempts":45,"provider_day":"2026-10-20","tariff_search_units":12},"safe_to_write_now":false,"scope_id":"d0017d8fffb4f69d4d0049c74d1c20fdc4bf35b678fcd7410637b5e7157e729c","selected_hotels":22,"selected_id_sha256":"dbde662384f375f401db02cb7a24db4f881ea177e165ed420a5f75542daeb148","source_sha":"d4c578bb5fe4d6f05441a928d720b0e805079561","state":"acquired_retained_native_read_only","stop_reason":"TimeoutError","supplier_calls":1,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":1,"unknown_http_calls":1},{"acquisition_state":"partial_no_replay","actions":{"search_start":1},"batches":[],"cohort":"anex-next-ee68d11668a22e84","context_origin":"owner_20261020_1031_saved_peer_context","context_reservation_sha256":"b4bae2f3d2c6c8f23661c130dda750a714d55ec7371051602f9d5dace26e16ea","continuation_scope_sha256":"458857b2e3641c143699247ea7c92c235cd9a0765b3e06fbce4aa81adfe41c31","control_source_sha":"9809907b2d2b30d51017de082505439ce6d12d3f","database_writes":0,"edge_states":{},"edges":[],"global_usage":"not_measured","hotel_ledger_sha256":"298b16959c85511bd2d5ccb261f44041b643cec266e509e1a1f628e427faacf5","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v4","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":1,"quota_before":{"physical_http_attempts":46,"provider_day":"2026-10-20","tariff_search_units":13},"safe_to_write_now":false,"scope_id":"ee68d11668a22e84934fa4acd94a779238491f8a0db822ee2f91dc2212456f04","selected_hotels":25,"selected_id_sha256":"8cbcf79c8a4b63a0b91cd14fe7ed197202d9f9651e6e7ac4f7e924ed7b17aa62","source_sha":"78f029da7522bc97051904770e7c077bbe1b985e","state":"acquired_retained_native_read_only","stop_reason":"TimeoutError","supplier_calls":1,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":1,"unknown_http_calls":1,"wire_context":{"date_from":"2026-11-15","date_to":"2026-11-15","nights_from":6,"nights_to":6}},{"acquisition_state":"completed","actions":{"search_results":1,"search_start":1,"search_status":1},"batches":[{"batch":1,"returned_anex_hotels":0,"search_complete":true,"sent":25}],"cohort":"anex-next-358bc9fca19be2f8","context_origin":"owner_20261020_1257_timeout60_new_window","context_reservation_sha256":"9c9c6549a9329ac5dcb1f8f02c328b12e35a5c25520800f6a8242232e7df69e1","continuation_scope_sha256":"266f0791573661882104a39ec1b4206940cec16eefef29de4d2e0a11f52ede07","control_source_sha":"09c08eb19a662b49413618eb15370eeb13fe2973","database_writes":0,"edge_states":{},"edges":[],"global_usage":"not_measured","hotel_ledger_sha256":"298b16959c85511bd2d5ccb261f44041b643cec266e509e1a1f628e427faacf5","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v5","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":3,"quota_before":{"physical_http_attempts":47,"provider_day":"2026-10-20","tariff_search_units":14},"safe_to_write_now":false,"scope_id":"358bc9fca19be2f882660a21d2c6b99ac1048bd4bb83a2d21327e6d1197cecaa","selected_hotels":25,"selected_id_sha256":"8cbcf79c8a4b63a0b91cd14fe7ed197202d9f9651e6e7ac4f7e924ed7b17aa62","source_sha":"fbe50829c646ea06b86637199231a91d68a5506d","state":"acquired_retained_native_read_only","stop_reason":null,"supplier_calls":3,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":1,"unknown_http_calls":0,"wire_context":{"date_from":"2027-02-07","date_to":"2027-02-07","nights_from":6,"nights_to":6}},{"acquisition_state":"completed","actions":{"search_results":1,"search_start":1,"search_status":1},"batches":[{"batch":1,"returned_anex_hotels":0,"search_complete":true,"sent":25}],"cohort":"anex-next-a286c36432d9174f","context_origin":"owner_20261020_1257_timeout60_new_window","context_reservation_sha256":"054f5977db0ba9810787ab8031b15a6c8005c6e52c3a1152c11cec0ceab2614a","continuation_scope_sha256":"c83d38a5726d71e955743e133dd815e9898a9a3067ba6048c5e1c31076ee6379","control_source_sha":"c480440232f8b08c662f79c3c037597aae062d4b","database_writes":0,"edge_states":{},"edges":[],"global_usage":"not_measured","hotel_ledger_sha256":"298b16959c85511bd2d5ccb261f44041b643cec266e509e1a1f628e427faacf5","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v6","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":3,"quota_before":{"physical_http_attempts":50,"provider_day":"2026-10-20","tariff_search_units":15},"safe_to_write_now":false,"scope_id":"a286c36432d9174f1989561ca4bfc4b05f98ecf1ea61ef2b48b68f4b771f73c3","selected_hotels":25,"selected_id_sha256":"8cbcf79c8a4b63a0b91cd14fe7ed197202d9f9651e6e7ac4f7e924ed7b17aa62","source_sha":"37df62279182b57494bc2447bb87f92597be91aa","state":"acquired_retained_native_read_only","stop_reason":null,"supplier_calls":3,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":1,"unknown_http_calls":0,"wire_context":{"date_from":"2027-02-14","date_to":"2027-02-20","nights_from":7,"nights_to":14}},{"acquisition_state":"completed","actions":{"search_results":1,"search_start":1,"search_status":1,"tour_detail":1},"batches":[{"batch":1,"returned_anex_hotels":1,"search_complete":true,"sent":13}],"cohort":"anex-next-e6f47f44a76c11fc","context_origin":"saved_user_search_exact_date_nights","continuation_scope_sha256":"6886821dd545f24e1a3f35db2c3f9b87ede3597df9e73fd6aaae190c0f99dce5","control_source_sha":"b8f293f2db08351204329bb194a04bacd4c8a787","database_writes":0,"edge_states":{"native_link_unresolved":1},"edges":[{"batch":1,"detail_fields":["adults","childs","currency","date","departure","flightNights","flightPlace","fuelCharge","hotel","hotelDescription","hotelPlace","id","isCharter","isPromo","meal","name","nights","operator","operatorLink","picture","placement","price","roomId","roomType"],"detail_hotel_id":616,"detail_operator_id":13,"detail_raw":{"raw_sha256":"ba75482de581f48d90aa2a14c5a61616438b1600a0dd9b30d5674dbea4c01752","response_number":4},"link_state":"missing_native","operator_link_field":"str","search_id_sha256":"fef43d3aa9ba63e7b4b11931fb793e9054e6579fab26652b54cd21b49f042e7c","state":"native_link_unresolved","tour_id_sha256":"9df5bb16d43332393f719cc97f4ddfe8b3bad73c8eba784be6693d9aa2434ea9","tv_hotel_id":616}],"global_usage":"not_measured","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v7","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":4,"quota_before":{"physical_http_attempts":53,"provider_day":"2026-10-20","tariff_search_units":16},"safe_to_write_now":false,"scope_id":"e6f47f44a76c11fc6725efc36143717964c2178a3007178e1df22170a38906c2","selected_hotels":13,"selected_id_sha256":"e2a8a350beafa3a543fbd1e41fc11fc5576d1465d6b1754647146c2561c0610d","selection_reservation_sha256":"60d411fe1d1079d5a718cabc27ca31cc973c55f6ec368eb2bd879803013c3ffa","source_sha":"45085a38b10edaecc6f1dcfef5d2e118dd753fda","state":"acquired_retained_native_read_only","stop_reason":null,"supplier_calls":4,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":1,"unknown_http_calls":0,"wire_context":{"date_from":"2026-11-11","date_to":"2026-11-11","nights_from":9,"nights_to":9}},{"acquisition_state":"completed","actions":{"search_results":1,"search_start":1,"search_status":1},"batches":[{"batch":1,"returned_anex_hotels":0,"search_complete":true,"sent":9}],"cohort":"anex-next-fcb0ccc19be1929c","context_origin":"saved_user_search_exact_date_nights","continuation_scope_sha256":"175e28a9cd21dc15ec9aed34d6acb0444370374116c3fbe38062715b519958b3","control_source_sha":"82e33153b0c9897afbd875bc21a3d4c3807e393b","database_writes":0,"edge_states":{},"edges":[],"global_usage":"not_measured","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v8","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":3,"quota_before":{"physical_http_attempts":57,"provider_day":"2026-10-20","tariff_search_units":17},"safe_to_write_now":false,"scope_id":"fcb0ccc19be1929cbed8608244f1b47d853525f73c215c5c4588f85b8d95529f","selected_hotels":9,"selected_id_sha256":"e5894225eb18e3e026c3cbd83bc77b200ca63680a738ca0e981387a7376e21ba","selection_reservation_sha256":"e027bb376d9f4cb29af50e7d35c040d751d53b3957bc8cfa96e85305f2780e35","source_sha":"df8183e5047f33f2495fe23a42bfd8fe7caa6567","state":"acquired_retained_native_read_only","stop_reason":null,"supplier_calls":3,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":1,"unknown_http_calls":0,"wire_context":{"date_from":"2026-10-21","date_to":"2026-10-21","nights_from":5,"nights_to":5}}]''')

    def _actual_catalogue_v9_receipt_for_test(self):
        # Exact sanitized consumed v9 receipt: no top-level search_complete field.
        return json.loads(r'''{"booking_calls":0,"database_writes":0,"lead_calls":0,"mapping_writes":0,"match_retained":{"data":{"acquisition_state":"completed","actions":{"search_results":1,"search_start":1,"search_status":1},"batches":[{"batch":1,"returned_anex_hotels":0,"search_complete":true,"sent":2}],"catalogue_sibling_history_sha256":"954e4aa4978af7a68bcbbad557947d0f5811f57f25e0751175dd7b0613fd73c8","cohort":"anex-next-d6d2deace82a110d","context_origin":"saved_user_search_exact_date_nights","continuation_catalogue_sha256":"bf61e5bcc50cfa6264819fca384915c3e4a45f756e304a96a22bc65ad2120b73","continuation_scope_sha256":"c8057c865a291b56c294a8397aede86d69814ae20c1cd7e53334eb522c03f10e","control_source_sha":"5dea4d172be30f90f2da29c72a4d7bd81ae1a95e","database_writes":0,"edge_states":{},"edges":[],"global_usage":"not_measured","mapping_writes":0,"new_accepted_mappings":0,"no_replay":true,"operation_id":"int-andromeda-match-retained-acquire-20261020-v9","operator_account_binding":null,"parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","parent_receipt_sha256":"627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9","physical_http_reservations":3,"quota_before":{"physical_http_attempts":60,"provider_day":"2026-10-20","tariff_search_units":18},"safe_to_write_now":false,"scope_id":"d6d2deace82a110dd0b72ec64b24aff82c74a89a080eed539b076c29ee479a39","selected_hotels":2,"selected_id_sha256":"c3e5d7231d6a86fa6ff2af7fc9cb08c805ab14de7fede50479f858d473ab6c15","selection_reservation_sha256":"d1a0e674c7110661071095cca50c6de5b95996c0a2bcb81d936f70dd6880caea","source_sha":"ba6ecf2ae842bea1424a585b5a2eb20907ffba9a","state":"acquired_retained_native_read_only","stop_reason":null,"supplier_calls":3,"target_operation_id":"int-andromeda-match-retained-current-20261020-v2","tariff_search_units":1,"unknown_http_calls":0,"wire_context":{"date_from":"2026-10-30","date_to":"2026-10-30","nights_from":6,"nights_to":6}},"evidence_sha256":"4725428bd44d20704c3f274b09a43d8de21837053e3d959751510bbdb5482324"},"mode":"match-retained-acquire","operation_id":"int-andromeda-match-retained-acquire-20261020-v9","production_after":{"index.php":"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f","v2/api-v2.php":null,"v2/index.php":null,"v2/lead-adapter-v2.php":null},"production_before":{"index.php":"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f","v2/api-v2.php":null,"v2/index.php":null,"v2/lead-adapter-v2.php":null},"production_unchanged":true,"schema_version":1,"source_sha":"ba6ecf2ae842bea1424a585b5a2eb20907ffba9a","status":"complete","supplier_calls":3}''')

    def test_actual_v9_sibling_uses_batch_completion_and_rejects_inconsistent_counts(self):
        actual=self._actual_catalogue_v9_receipt_for_test()
        self.assertEqual('1cf755d5aa043d92ee6780bf0d7b0117b330ab41beb6fff23ab5c3d0dd2c544a',
                         hashlib.sha256(a.canonical(actual)).hexdigest())
        actual_data=actual['match_retained']['data']
        self.assertEqual('4725428bd44d20704c3f274b09a43d8de21837053e3d959751510bbdb5482324',
                         hashlib.sha256(a.canonical(actual_data)).hexdigest())
        self.assertNotIn('search_complete',actual_data)
        self.assertIs(actual_data['batches'][0]['search_complete'],True)
        scope=self.members[1];sibling=self.members[0];policy=self.policies[scope['cohort']]
        _,command,_=self.parse(scope)
        self.assertEqual(sibling['operation_id'],actual_data['operation_id'])
        baseline_by_op={x['operation_id']:x for x in self._catalogue_baseline_receipts_for_test()}
        history=scope['exclusion_history']+scope['context_history']
        for entry in history:
            self.assertEqual(entry['evidence_sha256'],hashlib.sha256(a.canonical(baseline_by_op[entry['operation_id']])).hexdigest())
        with tempfile.TemporaryDirectory() as td:
            base=Path(td);private=base/'private';private.mkdir();home=base/'home'
            account=home/'.anytoour-match';account.mkdir(parents=True)
            claims={'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'prior_consumed'} for h in scope['prior_selected_hotel_ids']}
            claims.update({'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'reserved_before_http_no_replay','operation_id':sibling['operation_id']}
                           for h in sibling['selected_tv_hotel_ids']})
            (account/'retained-selection-claims.json').write_bytes(a.canonical({'schema_version':1,'claims':claims}))
            stage=base/'stage';catalogue_path=stage/m.CATALOGUE_PATH
            catalogue_path.parent.mkdir(parents=True);catalogue_path.write_bytes(self.raw)
            def fixture(name,data,ids,wire=None,country=1,departure=1,adults=2,wrapper=None):
                target=private/name;target.mkdir()
                source=data['source_sha'];digest=hashlib.sha256(a.canonical(data)).hexdigest()
                wrapper=copy.deepcopy(wrapper) if wrapper is not None else {
                    'status':'complete','mode':'match-retained-acquire','production_unchanged':True,
                    'operation_id':name,'source_sha':source,'database_writes':0,'mapping_writes':0,
                    'match_retained':{'data':copy.deepcopy(data),'evidence_sha256':digest}}
                for filename,value in [('result.json',wrapper),('reservation.json',{'operation_id':name,'source_sha':source}),
                                       ('acquisition-plan.json',{'hotel_ids':ids})]:
                    (target/filename).write_bytes(a.canonical(value))
                wire=wire or data.get('wire_context') or {'date_from':'2099-01-01','date_to':'2099-01-01','nights_from':7,'nights_to':7}
                for number in range(1,data['physical_http_reservations']+1):
                    params={'hotelIds':ids[:30],'operatorIds':[13],'countryId':country,'departureId':departure,
                            'adults':adults,'childs':[],'dateFrom':wire['date_from'],'dateTo':wire['date_to'],
                            'nightsFrom':wire['nights_from'],'nightsTo':wire['nights_to']}
                    # Only the selected sibling context is asserted against actual member policy.
                    value={'action':'search_start' if number<=data['tariff_search_units'] else 'search_status',
                           'path':'/tours/search','params':params}
                    (target/('request-%04d.json'%number)).write_bytes(a.canonical(value))
                return target
            for entry in history:
                fixture(entry['operation_id'],baseline_by_op[entry['operation_id']],entry['selected_tv_hotel_ids'])
            target=fixture(sibling['operation_id'],actual_data,sibling['selected_tv_hotel_ids'],
                           sibling['wire_context'],sibling['country_id'],sibling['departure_id'],sibling['adults'],actual)
            old_path=target/'source'/m.CATALOGUE_PATH;old_path.parent.mkdir(parents=True);old_path.write_bytes(self.raw)
            (target/'source/manifest.json').write_bytes(a.canonical({'schema_version':1,'files':{
                m.CATALOGUE_PATH:policy['catalogue_sha256']}}))
            space=self.remote_space(policy,command,private)
            protected={p:p.read_bytes() for p in base.rglob('*.json')}
            with mock.patch.dict(os.environ,{'HOME':str(home)}):
                snapshots,requests=space['retained_catalogue_history'](scope,stage)
            self.assertEqual(19,len(requests))
            self.assertEqual('1cf755d5aa043d92ee6780bf0d7b0117b330ab41beb6fff23ab5c3d0dd2c544a',
                             snapshots[sibling['operation_id']]['result'])
            self.assertEqual(protected,{p:p.read_bytes() for p in base.rglob('*.json')})
            mutations={
                'batches_missing':lambda d:d.pop('batches'),
                'batches_empty':lambda d:d.__setitem__('batches',[]),
                'batches_not_list':lambda d:d.__setitem__('batches',{}),
                'batch_not_object':lambda d:d.__setitem__('batches',[None]),
                'two_batches':lambda d:d['batches'].append(copy.deepcopy(d['batches'][0])),
                'batch_not_complete':lambda d:d['batches'][0].__setitem__('search_complete',False),
                'numeric_complete':lambda d:d['batches'][0].__setitem__('search_complete',1),
                'batch_number':lambda d:d['batches'][0].__setitem__('batch',2),
                'sent_wrong':lambda d:d['batches'][0].__setitem__('sent',3),
                'sent_bool':lambda d:d['batches'][0].__setitem__('sent',True),
                'returned_negative':lambda d:d['batches'][0].__setitem__('returned_anex_hotels',-1),
                'returned_exceeds_selected':lambda d:d['batches'][0].__setitem__('returned_anex_hotels',3),
                'returned_without_detail':lambda d:d['batches'][0].__setitem__('returned_anex_hotels',1),
                'status_zero':lambda d:d['actions'].__setitem__('search_status',0),
                'status_bool':lambda d:d['actions'].__setitem__('search_status',True),
                'status_over_bound':lambda d:d['actions'].__setitem__('search_status',4),
                'second_start':lambda d:d['actions'].__setitem__('search_start',2),
                'results_missing':lambda d:d['actions'].pop('search_results'),
                'unknown_action':lambda d:d['actions'].__setitem__('search_continue',1),
                'physical_count':lambda d:d.__setitem__('physical_http_reservations',2),
                'supplier_count':lambda d:d.__setitem__('supplier_calls',4),
                'tariff_count':lambda d:d.__setitem__('tariff_search_units',2),
                'unknown_preserved':lambda d:d.__setitem__('unknown_http_calls',1),
                'partial_preserved':lambda d:d.__setitem__('acquisition_state','partial_no_replay'),
                'edges_without_return':lambda d:d['edges'].append({'tv_hotel_id':345,'state':'native_link_unresolved'}),
                'edge_states_without_edge':lambda d:d.__setitem__('edge_states',{'native_link_unresolved':1}),
            }
            receipt=target/'result.json';immutable_actual=a.canonical(actual)
            for name,mutate in mutations.items():
                with self.subTest(reason=name):
                    changed=copy.deepcopy(actual);mutate(changed['match_retained']['data'])
                    self.seal(changed);receipt.write_bytes(a.canonical(changed))
                    before={p:p.read_bytes() for p in base.rglob('*.json')}
                    with mock.patch.dict(os.environ,{'HOME':str(home)}),self.assertRaisesRegex(ValueError,'catalogue_sibling'):
                        space['retained_catalogue_history'](scope,stage)
                    self.assertEqual(before,{p:p.read_bytes() for p in base.rglob('*.json')})
            receipt.write_bytes(immutable_actual)
            self.assertEqual(immutable_actual,receipt.read_bytes())
            self.assertNotIn('search_complete',actual_data)


    def test_readonly_proof_source_inspector_end_to_end_with_producer_shaped_rows(self):
        # Synthetic unit capture/requests; only v9's sanitized receipt SHAPE is reused.
        # No historical supplier wire or new identity authority is asserted by this fixture.
        import socket
        import subprocess
        import urllib.request
        from contextlib import ExitStack
        actual=self._actual_catalogue_v9_receipt_for_test()
        catalogue=copy.deepcopy(self.catalogue)
        selected=set(catalogue['scopes'][0]['prior_selected_hotel_ids'])|{345,131024}
        self.assertEqual(206,len(selected))
        accepted={35297,1474,1014,1095,1015,3418,57027}
        self.assertTrue(accepted<=selected)
        selected_ready=sorted(selected-accepted);remaining_ids=list(range(900000,900407))
        self.assertEqual(199,len(selected_ready));self.assertFalse(selected.intersection(remaining_ids))
        rows=[{'tv_hotel_id':hid,'state':'missing_direct_anex_with_future_context_and_anchor',
               'acquisition_ready':True,'anchor_state':'canonical_anchor_ok','direct_anex_native_ids':[],
               'target':{'id':hid,'country_id':1},'future_context':{'hotel_id':hid,'country_id':1}}
              for hid in selected_ready+remaining_ids]
        self.assertTrue(all('country_id' not in row for row in rows))
        private_tour='fixture-private-tour-ref';private_search='fixture-private-search-ref'
        private_source='fixture-private-source-ref'
        schema=['hotel_id','operator_id','operator_name','country_id','tour_id','search_id','source']
        records=[{'kind':'scope','cohort':'common4-retained','catalog_id_namespace':'tourvisor_hotel',
                  'schema_columns':{'tour_operator_identity_observations':schema}}]
        records.append({'kind':'observation','row':{'hotel_id':remaining_ids[0],'operator_id':13,
            'operator_name':'ANEX','country_id':1,'tour_id':private_tour,'search_id':private_search,'source':private_source}})
        records.extend({'kind':'observation','row':{'hotel_id':42}} for _ in range(13610))
        for kind,count in [('legacy_source',14687),('identity',15298),('target',5310)]:
            records.extend({'kind':kind,'row':{}} for _ in range(count))
        raw=b''.join(a.canonical(record)+b'\n' for record in records)
        self.assertLess(len(raw),4*1024*1024)
        with tempfile.TemporaryDirectory() as td:
            base=Path(td);private=base/'private';private.mkdir()
            home=base/'home';account=home/'.anytoour-match';(account/'provider-quotas').mkdir(parents=True)
            stage=base/'stage';(stage/'scripts/diagnostics').mkdir(parents=True)
            outdir=base/'inspection';outdir.mkdir()
            profile=copy.deepcopy(m.PROOF_SOURCE_INSPECTION)
            profile['capture_raw_sha256']=hashlib.sha256(raw).hexdigest();profile['capture_raw_bytes']=len(raw)
            profile['selected_id_sha256']=hashlib.sha256(a.canonical(sorted(selected))).hexdigest()
            profile['remaining_id_sha256']=hashlib.sha256(a.canonical(remaining_ids)).hexdigest()
            def save_receipt(name,mode,data,wrapper=None):
                target=private/name;target.mkdir()
                wrapper=copy.deepcopy(wrapper) if wrapper is not None else {
                    'schema_version':1,'status':'complete','mode':mode,'operation_id':name,'source_sha':SHA,
                    'production_unchanged':True,'supplier_calls':0,'database_writes':0,'mapping_writes':0,
                    'lead_calls':0,'booking_calls':0,'match_retained':{'data':copy.deepcopy(data)}}
                wrapper['mode']=mode;wrapper['operation_id']=name
                wrapper['match_retained']['data']=copy.deepcopy(data)
                wrapper['match_retained']['evidence_sha256']=hashlib.sha256(a.canonical(data)).hexdigest()
                (target/'result.json').write_bytes(a.canonical(wrapper))
                (target/'reservation.json').write_bytes(a.canonical({
                    'operation_id':name,'source_sha':wrapper['source_sha'],'mode':mode}))
                return target,wrapper,hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
            common={'source_sha':SHA,'supplier_calls':0,'database_writes':0,'mapping_writes':0,
                    'lead_calls':0,'booking_calls':0,'safe_to_write_now':False,'no_replay':True,
                    'cohort':'common4-retained'}
            capture_data=dict(common,operation_id=profile['target_operation_id'],
                state='captured_and_projected_read_only',raw_sha256=profile['capture_raw_sha256'],
                raw_bytes=len(raw),rows={'observation':13611,'legacy_source':14687,'identity':15298,'target':5310})
            capture_data['capture']={'raw_sha256':profile['capture_raw_sha256'],'raw_bytes':len(raw)}
            capture,_,profile['capture_receipt_sha256']=save_receipt(
                profile['target_operation_id'],'match-retained-intake',capture_data)
            raw_path=capture/'retained-raw.ndjson';raw_path.write_bytes(raw)
            current=dict(common,operation_id=profile['current_operation_id'],state='current_retained_read_only',rows=rows)
            profile['current_evidence_sha256']=hashlib.sha256(a.canonical(current)).hexdigest()
            _,_,profile['current_receipt_sha256']=save_receipt(
                profile['current_operation_id'],'match-retained-current',current)
            identity_keys=('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts',
                           'scope_kind','wire_context','context_origin')
            for member in catalogue['scopes']:
                member['parent_evidence_sha256']=profile['current_evidence_sha256']
                member['parent_receipt_sha256']=profile['current_receipt_sha256']
                identity=hashlib.sha256(a.canonical({k:member[k] for k in identity_keys})).hexdigest()
                member['scope_id']=identity;member['cohort']='anex-next-'+identity[:16]
            catalogue_raw=a.canonical(catalogue);profile['catalogue_sha256']=hashlib.sha256(catalogue_raw).hexdigest()
            member=next(x for x in catalogue['scopes'] if x['operation_id']==profile['acquisition_operation_id'])
            data=copy.deepcopy(actual['match_retained']['data'])
            self.assertNotIn('search_complete',data)
            data.update(parent_evidence_sha256=profile['current_evidence_sha256'],
                        parent_receipt_sha256=profile['current_receipt_sha256'],
                        continuation_catalogue_sha256=profile['catalogue_sha256'],
                        continuation_scope_sha256=hashlib.sha256(a.canonical(member)).hexdigest(),
                        scope_id=member['scope_id'],cohort=member['cohort'])
            profile['acquisition_evidence_sha256']=hashlib.sha256(a.canonical(data)).hexdigest()
            acquired,_,profile['acquisition_receipt_sha256']=save_receipt(
                profile['acquisition_operation_id'],'match-retained-acquire',data,actual)
            old_catalogue=acquired/'source'/m.CATALOGUE_PATH
            old_catalogue.parent.mkdir(parents=True);old_catalogue.write_bytes(catalogue_raw)
            start={'action':'search_start','path':'/tours/search','params':{
                'departureId':10,'countryId':1,'dateFrom':'2026-10-30','dateTo':'2026-10-30',
                'nightsFrom':6,'nightsTo':6,'adults':2,'childs':[],'currency':'RUB',
                'onlyCharter':False,'operatorIds':[13],'hotelIds':[345,131024]}}
            status={'action':'search_status','path':'/tours/search/777/status','params':{'operatorStatus':False}}
            request={'action':'search_results','path':'/tours/search/777','params':{'limit':10000}}
            for number,value in [(1,start),(2,status),(3,request)]:
                (acquired/('request-%04d.json'%number)).write_bytes(a.canonical(value))
            result_raw=a.canonical({'data':{'hotels':[
                {'id':9999999,'tours':[{'id':888,'operatorId':13}]},
                {'id':345,'tours':[{'tour_id':'alternative-id-shape','operator':18}]}],
                'totalHotels':2,'limit':10000,'hasMore':False}})
            result_path=acquired/'response-0003.bin';result_path.write_bytes(result_raw)
            meta={'http_status':200,'complete_body':True,'raw_sha256':hashlib.sha256(result_raw).hexdigest()}
            meta_path=acquired/'response-0003.json';meta_path.write_bytes(a.canonical(meta))
            for name,contents in [('retained-selection-claims.json',{'schema_version':1,'claims':{
                    'TOURVISOR_ANEX_JWT:13:'+str(h):{'state':'reserved_before_http_no_replay'} for h in sorted(selected)}}),
                    ('retained-context-claims.json',{'schema_version':1,'claims':{'old-unknown':{'state':'UNKNOWN'}}}),
                    ('provider-quotas/tourvisor-anex-2026-10-20.json',{'schema_version':1,'physical_http_attempts':63,'unknown':3})]:
                (account/name).write_bytes(a.canonical(contents))
            profile['hotel_ledger_sha256']=hashlib.sha256((account/'retained-selection-claims.json').read_bytes()).hexdigest()
            profile['context_ledger_sha256']=hashlib.sha256((account/'retained-context-claims.json').read_bytes()).hexdigest()
            command={'source_sha':SHA,'mode':'match-retained-readback','operation_id':profile['operation_id'],
                     'cohort':profile['cohort'],'target_operation_id':profile['target_operation_id']}
            space=self.remote_space(m.NEXT_SCOPE,command,private)
            space.update(PROOF_SOURCE_INSPECTION=profile,op=outdir)
            protected={path:path.read_bytes() for root in (private,account) for path in root.rglob('*') if path.is_file()}
            private_output=outdir/'proof-source-candidates.json'
            with ExitStack() as stack:
                stack.enter_context(mock.patch.dict(os.environ,{'HOME':str(home)}))
                for owner,name in [(subprocess,'run'),(urllib.request,'urlopen'),
                                   (urllib.request.OpenerDirector,'open'),(socket,'create_connection')]:
                    stack.enter_context(mock.patch.object(owner,name,side_effect=AssertionError('no subprocess/network allowed')))
                result=space['retained_proof_sources'](stage)
                self.assertEqual('retained_proof_sources_inspected_read_only',result['state'])
                self.assertEqual((606,199,407),(result['same_parent_ready'],result['same_parent_selected_ready'],result['same_parent_never_selected']))
                self.assertEqual(16,result['protected_inputs_count']);self.assertIs(result['protected_inputs_unchanged'],True)
                self.assertIs(result['native_authority'],False);self.assertEqual(0,result['new_accepted_mappings'])
                self.assertEqual('not_measured',result['global_usage'])
                refs=result['reference_projection'];self.assertEqual(1,refs['counts']['reference_candidate_rows'])
                self.assertEqual(0,refs['counts'].get('country_disagreements',0))
                self.assertEqual([remaining_ids[0]],refs['candidate_tv_hotel_ids'])
                self.assertIs(refs['account_binding_authority'],False)
                self.assertEqual(2,result['result_structure']['counts']['raw_hotel_rows'])
                self.assertEqual(1,result['result_structure']['counts']['selected_hotel_rows'])
                self.assertEqual([345],result['result_structure']['selected_hotel_ids'])
                self.assertEqual({'13':1,'18':1},result['result_structure']['operator_id_counts'])
                public=json.dumps(result,sort_keys=True)
                for marker in (private_tour,private_search,private_source,'alternative-id-shape'):
                    self.assertNotIn(marker,public)
                self.assertEqual(0o600,private_output.stat().st_mode&0o777)
                projected=json.loads(private_output.read_bytes())
                self.assertEqual(private_tour,projected['references'][0]['private_fields']['tour_id'])
                self.assertEqual(hashlib.sha256(private_output.read_bytes()).hexdigest(),result['private_reference_projection_sha256'])
                self.assertEqual(protected,{path:path.read_bytes() for path in protected})
                private_output.unlink()
                bad_profile=copy.deepcopy(profile);bad_profile['current_evidence_sha256']='0'*64
                space['PROOF_SOURCE_INSPECTION']=bad_profile
                with self.assertRaisesRegex(ValueError,'proof_current_binding'):
                    space['retained_proof_sources'](stage)
                space['PROOF_SOURCE_INSPECTION']=profile
                bad_meta=dict(meta,raw_sha256='0'*64);meta_path.write_bytes(a.canonical(bad_meta))
                with self.assertRaisesRegex(ValueError,'proof_response_digest'):
                    space['retained_proof_sources'](stage)
                meta_path.write_bytes(a.canonical(meta))
                moved=acquired/'response-real.bin';result_path.rename(moved);result_path.symlink_to(moved)
                with self.assertRaisesRegex(ValueError,'proof_source_path'):
                    space['retained_proof_sources'](stage)
                result_path.unlink();moved.rename(result_path)
                original_read=Path.read_bytes;changed=[False]
                def drift(path):
                    value=original_read(path)
                    if path==raw_path and not changed[0]:
                        changed[0]=True;path.write_bytes(value+b' ')
                    return value
                with mock.patch.object(Path,'read_bytes',drift),self.assertRaisesRegex(ValueError,'proof_source_drift'):
                    space['retained_proof_sources'](stage)
                raw_path.write_bytes(raw)
                self.assertTrue(changed[0]);self.assertFalse(private_output.exists())
                self.assertEqual(protected,{path:path.read_bytes() for path in protected})

class BindingFailureReconciliationTest(unittest.TestCase):
    expected_protected = 25

    def policy(self): return m.BINDING_RECONCILIATION

    def handler(self): return m.BINDING_RECONCILIATION_HANDLER

    def command(self):
        p=self.policy()
        return dict(source_sha=SHA,mode='match-retained-readback',operation_id=p['operation_id'],
                    cohort=p['cohort'],target_operation_id=p['parent_operation_id'])

    def test_fixed_readonly_parser_and_dispatch(self):
        c=core();m.register_parser(c);cmd=self.command();body=c.PREFIX+' '.join(cmd.values())
        self.assertEqual(cmd,c.parse_command(body))
        for key in ('mode','operation_id','target_operation_id'):
            bad=dict(cmd);bad[key]+='x'
            with self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(bad.values()))
        with self.assertRaises(ValueError):c.parse_command(body+' x')
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(body)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':SHA}):m.activate(c,cmd)
        self.assertIn("if mode=='match-retained-readback':return retained_binding_reconciliation(stage)",c.REMOTE)
        self.assertNotIn('return retained_operator_dictionary(stage)',c.REMOTE)
        ast.parse(c.REMOTE)

    def fixture(self,root):
        home=root/'home';private=root/'private';stage=root/'stage';stage.mkdir()
        profile=copy.deepcopy(self.policy());target=private/profile['parent_operation_id']
        target.mkdir(parents=True);(target/'source').mkdir()
        prior=dict(status='unknown_no_replay',operation_id=profile['parent_operation_id'],
                   source_sha=profile['failed_source_sha'],mode='match-retained-acquire',supplier_calls=None,
                   database_writes=0,mapping_writes=0,lead_calls=0,booking_calls=0,reason='synthetic missing day file')
        profile['failed_canonical_sha256']=hashlib.sha256(m.canonical(prior)).hexdigest()
        (target/'result.json').write_bytes(m.canonical(prior))
        (target/'reservation.json').write_text(json.dumps({k:prior[k] for k in ('operation_id','source_sha','mode')}))
        manifest={'schema_version':1,'files':m.SOURCE_HASHES}
        for dest in (stage,target/'source'):
            (dest/'manifest.json').write_text(json.dumps(manifest))
        for relative in m.SOURCE_FILES:
            dest=target/'source'/relative;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes((SOURCE/relative).read_bytes())
        ledger=home/'.anytoour-match';(ledger/'provider-quotas').mkdir(parents=True)
        for name in ('retained-selection-claims.json','retained-context-claims.json','provider-quotas/tourvisor-anex-2026-10-20.json'):
            (ledger/name).write_text('{"historical":"preserve"}')
        day=ledger/'provider-quotas/tourvisor-anex-2026-10-21.json'
        day.write_text(json.dumps(dict(provider='tourvisor-anex',provider_day='2026-10-21',quota_policy='physical-http-v1',
            physical_http_daily_limit=5000,physical_http_attempts=4,tariff_search_units=1,operations={},
            physical_http_reservations={'another-operation:1':{'state':'reserved_unknown_no_replay'}})))
        space=dict(pathlib=__import__('pathlib'),os=os,re=__import__('re'),json=json,hashlib=hashlib,
                   NEXT_SCOPE=profile,private=private,mode='match-retained-readback',operation=profile['operation_id'],source=SHA,
                   payload={**self.command(),'match_retained_control_sha':SHA},
                   safe_file=lambda p,b: p.is_file() and not p.is_symlink() and p.stat().st_size<=b,
                   fail=lambda reason: (_ for _ in ()).throw(ValueError(reason)))
        exec(self.handler(),space)
        return home,stage,target,day,space

    def test_actual_readonly_source_and_request_absence_proof_preserves_every_input(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);home,stage,target,day,space=self.fixture(root)
            before={str(p):p.read_bytes() for p in root.rglob('*') if p.is_file()}
            with mock.patch.object(Path,'home',return_value=home):out=space['retained_binding_reconciliation'](stage)
            self.assertEqual(before,{str(p):p.read_bytes() for p in root.rglob('*') if p.is_file()})
            self.assertEqual(self.expected_protected,out['data']['protected_inputs_count'])
            self.assertEqual(0,out['data']['proved_failed_operation_supplier_calls'])
            self.assertEqual('unknown_no_replay',out['data']['prior_status'])
            self.assertIsNone(out['data']['prior_supplier_calls'])
            result=dict(status='complete',production_unchanged=True,mode='match-retained-readback',source_sha=SHA,
                        operation_id=space['operation'],supplier_calls=0,database_writes=0,mapping_writes=0,
                        booking_calls=0,lead_calls=0,match_retained=out)
            with mock.patch.dict(self.policy(),space['NEXT_SCOPE']):
                self.assertTrue(m.completed_result(self.command(),result,SHA))
                for key,value in [('proved_failed_operation_supplier_calls',False),('prior_supplier_calls',0),
                                  ('staged_source_files_verified',True),('safe_to_write_now',True),
                                  ('prior_receipt_preserved',False),('failed_canonical_sha256','0'*64),
                                  ('lead_calls',True),('booking_calls',1),('global_usage','measured')]:
                    bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
                    bad['match_retained']['evidence_sha256']=hashlib.sha256(m.canonical(bad['match_retained']['data'])).hexdigest()
                    with self.subTest(key=key):self.assertFalse(m.completed_result(self.command(),bad,SHA))

    def test_refuses_any_request_debit_source_or_receipt_ambiguity(self):
        for variant in ('request','reservation','source','receipt','symlink','hardlink','duplicate'):
            with self.subTest(variant=variant),tempfile.TemporaryDirectory() as tmp:
                root=Path(tmp);home,stage,target,day,space=self.fixture(root)
                if variant=='request':(target/'request-0001.json').write_text('{}')
                elif variant=='reservation':
                    value=json.loads(day.read_text());value['physical_http_reservations'][space['NEXT_SCOPE']['parent_operation_id']+':1']={};day.write_text(json.dumps(value))
                elif variant=='source':(target/'source'/m.SOURCE_FILES[0]).write_text('changed')
                elif variant=='receipt':(target/'result.json').write_text('{}')
                elif variant=='duplicate':day.write_text('{"provider":1,"provider":2}')
                else:
                    path=target/'reservation.json';other=root/'other';path.rename(other)
                    if variant=='symlink':path.symlink_to(other)
                    else:os.link(other,path)
                with mock.patch.object(Path,'home',return_value=home),self.assertRaises(ValueError):
                    space['retained_binding_reconciliation'](stage)


class BindingFailureReconciliationV2Test(BindingFailureReconciliationTest):
    expected_protected = 29

    def policy(self): return m.BINDING_RECONCILIATION_V2

    def handler(self): return m.binding_reconciliation_v2_handler()

    def fixture(self,root):
        home,stage,target,day,space=super().fixture(root);profile=space['NEXT_SCOPE']
        # Execute the actual stock carrier metadata writer; the v1 fixture omitted it.
        lines=core().REMOTE.splitlines()
        index=next(i for i,line in enumerate(lines) if "(op/'installed-source.json').write_text" in line)
        writer='\n'.join(line[4:] for line in lines[index:index+2])
        exec(writer,dict(op=target,source=profile['failed_source_sha'],files=m.SOURCE_HASHES,json=json,os=os))
        previous=space['private']/profile['prior_reconciliation_id'];previous.mkdir();(previous/'source').mkdir()
        result=dict(status='unknown_no_replay',reason='binding_reconciliation_request_present',
                    source_sha=profile['prior_reconciliation_source'],operation_id=profile['prior_reconciliation_id'],
                    mode='match-retained-readback',supplier_calls=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0)
        profile['prior_reconciliation_digest']=hashlib.sha256(m.canonical(result)).hexdigest()
        (previous/'result.json').write_bytes(m.canonical(result))
        (previous/'reservation.json').write_text(json.dumps({k:result[k] for k in ('source_sha','operation_id','mode')}))
        exec(writer,dict(op=previous,source=profile['prior_reconciliation_source'],files=m.SOURCE_HASHES,json=json,os=os))
        return home,stage,target,day,space

    def test_actual_carrier_metadata_is_required_and_both_failed_receipts_remain_bound(self):
        for variant in ('installed_source','installed_files','previous_result','previous_metadata','previous_request','completion'):
            with self.subTest(variant=variant),tempfile.TemporaryDirectory() as tmp:
                home,stage,target,day,space=self.fixture(Path(tmp));previous=space['private']/space['NEXT_SCOPE']['prior_reconciliation_id']
                if variant=='installed_source':
                    value=json.loads((target/'installed-source.json').read_text());value['source_sha']='0'*40
                    (target/'installed-source.json').write_text(json.dumps(value))
                elif variant=='installed_files':
                    value=json.loads((target/'installed-source.json').read_text());value['files']={}
                    (target/'installed-source.json').write_text(json.dumps(value))
                elif variant=='previous_result':(previous/'result.json').write_text('{}')
                elif variant=='previous_metadata':(previous/'installed-source.json').write_text('{}')
                elif variant=='previous_request':(previous/'request-0001.json').write_text('{}')
                with mock.patch.object(Path,'home',return_value=home):
                    if variant!='completion':
                        with self.assertRaises(ValueError):space['retained_binding_reconciliation'](stage)
                    else:
                        out=space['retained_binding_reconciliation'](stage)
                        result=dict(status='complete',production_unchanged=True,mode='match-retained-readback',source_sha=SHA,
                            operation_id=space['operation'],supplier_calls=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,match_retained=out)
                        with mock.patch.dict(self.policy(),space['NEXT_SCOPE']):
                            for key,value in [('installed_metadata_verified',False),('prior_reconciliation_preserved',False),
                                ('prior_reconciliation_canonical_sha256','0'*64),('prior_reconciliation_operation_id','wrong')]:
                                bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
                                bad['match_retained']['evidence_sha256']=hashlib.sha256(m.canonical(bad['match_retained']['data'])).hexdigest()
                                self.assertFalse(m.completed_result(self.command(),bad,SHA))


class OperatorDictionaryTest(unittest.TestCase):
    profile_name = 'OPERATOR_BINDING'
    parent_mode = 'match-retained-current'
    parent_state = 'current_retained_read_only'
    expected_url = '/operators?departureId=1&countryId=1'

    def profile(self):
        return getattr(m, self.profile_name)

    def parser(self):
        c=core();m.register_parser(c);return c

    def command(self):
        return dict(source_sha=SHA, mode='match-retained-acquire',
            operation_id=self.profile()['operation_id'], cohort=self.profile()['cohort'],
            target_operation_id=self.profile()['parent_operation_id'])

    def space(self):
        space={};exec(m.OPERATOR_BINDING_HANDLER,space);return space

    def test_fixed_dictionary_command_rejects_other_intents_and_attempts(self):
        c=self.parser();cmd=self.command()
        text=c.PREFIX+' '.join(cmd.values())
        self.assertEqual(cmd,c.parse_command(text))
        for key in ('mode','operation_id','target_operation_id'):
            bad=dict(cmd);bad[key]={'mode':'match-retained-readback','operation_id':cmd['operation_id']+'0',
                                  'target_operation_id':'int-andromeda-match-retained-current-20261020-v1'}[key]
            with self.subTest(key=key),self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(bad.values()))
        for extra in (' x',''):
            if extra:
                with self.assertRaises(ValueError):c.parse_command(text+extra)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(text)
        with self.assertRaises(ValueError):c.parse_command(text.rsplit(' ',1)[0])

    def test_dictionary_requires_typed_unique_unambiguous_official_names(self):
        parse=self.space()['operator_dictionary_projection']
        rows=[{'id':709,'name':'ANEX Tour'}, {'id':801,'name':'Библио-Глобус','russianName':'Библио-Глобус'},
              {'id':55,'name':'FUN&SUN'}, {'id':19,'name':'Интурист'}, {'id':80,'name':'Unrelated operator'}]
        self.assertEqual([{'family':'anex','operator_id':709},{'family':'biblio','operator_id':801},
                          {'family':'funsun','operator_id':55},{'family':'intourist','operator_id':19}],parse(rows))
        self.assertEqual([],parse([]))
        bad=[{},[{'id':True,'name':'ANEX'}],[{'id':'13','name':'ANEX'}],
             [{'id':13,'name':'ANEX','russianName':'Библио-Глобус'}],
             [{'id':13,'name':'ANEX'},{'id':14,'name':'ANEX Tour'}],rows+[rows[0]],
             [{'id':13,'name':'ANEX','operatorLink':'not-a-dictionary-field'}],
             [{'id':13,'name':None}],[{'id':13,'name':'ANEX\n'}],[{'id':13}],
             [{'id':0,'name':'ANEX'}],[{'id':13,'name':'x'*256}]]
        for value in bad:
            with self.subTest(value=value),self.assertRaises(ValueError):parse(value)
        self.assertEqual([],parse([{'id':25,'name':'Библио-Глобус UNKNOWN VARIANT'}]))

    def _dictionary_case(self, supplied_body=None, timeout=False, bad_parent=False, missing_day=False):
        with tempfile.TemporaryDirectory() as td:
            home=Path(td);project=home/'project';project.mkdir();(project/'config.php').write_text('private-config')
            private=home/'private';private.mkdir();op=private/self.profile()['operation_id'];op.mkdir()
            parent_dir=private/self.profile()['parent_operation_id'];parent_dir.mkdir()
            parent_data=dict(state=self.parent_state,operation_id=parent_dir.name,source_sha=SHA,
                             supplier_calls=0,database_writes=0,mapping_writes=0)
            digest=hashlib.sha256(m.canonical(parent_data)).hexdigest()
            parent=dict(status='complete',mode=self.parent_mode,production_unchanged=True,
                        operation_id=parent_dir.name,source_sha=SHA,supplier_calls=0,database_writes=0,mapping_writes=0,
                        match_retained=dict(data=parent_data,evidence_sha256=digest))
            (parent_dir/'result.json').write_bytes(m.canonical(parent))
            (parent_dir/'reservation.json').write_bytes(m.canonical(dict(operation_id=parent_dir.name,source_sha=SHA)))
            quota=q.Quota(home);quota.root.mkdir(parents=True);quota.path.write_bytes(q.encoded(dict(provider='tourvisor-anex',
                provider_day=quota.day,physical_http_attempts=7,tariff_search_units=2,operations={'old':'unknown_no_replay'})))
            quota.apply_policy()
            for name in ('retained-selection-claims.json','retained-context-claims.json'):
                (home/'.anytoour-match'/name).write_text('{"retained":true}')
            protected={path:path.read_bytes() for path in [project/'config.php',parent_dir/'result.json',
                parent_dir/'reservation.json',home/'.anytoour-match/retained-selection-claims.json',
                home/'.anytoour-match/retained-context-claims.json']}
            profile=dict(self.profile(),parent_evidence_sha256=digest)
            if bad_parent:
                parent['mode']='match-retained-current'
                (parent_dir/'result.json').write_bytes(m.canonical(parent))
                protected[parent_dir/'result.json']=(parent_dir/'result.json').read_bytes()
            if missing_day:quota.path.unlink()
            space=self.space();space.update(NEXT_SCOPE=profile,private=private,project=project,op=op,
                mode='match-retained-acquire',operation=op.name,source=SHA,
                payload=dict(self.command(),match_retained_control_sha=CONTROL),
                pathlib=__import__('pathlib'),hashlib=hashlib,json=json,time=__import__('time'),os=os,
                fail=lambda reason:(_ for _ in ()).throw(ValueError(reason)),
                safe_file=lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound)
            body=supplied_body if supplied_body is not None else json.dumps([{'id':801,'name':'Библио-Глобус'}]).encode()
            calls=[]
            class Response:
                code=200
                def __enter__(self):return self
                def __exit__(self,*args):return False
                def read(self,n):
                    if timeout:raise TimeoutError('private-supplier-sentinel')
                    return body
            class Opener:
                def open(self,request,timeout):
                    ledger=json.loads(quota.path.read_bytes())
                    row=ledger['physical_http_reservations'][op.name+':1']
                    self_case.assertEqual('reserved_unknown_no_replay',row['state'])
                    self_case.assertEqual(8,ledger['physical_http_attempts'])
                    self_case.assertEqual('dictionary',row['action'])
                    calls.append((request.full_url,timeout));return Response()
            self_case=self;real_json_loads=json.loads
            def check_parse(value,*args,**kwargs):
                if value==body:self.assertEqual(body,(op/'response-0001.bin').read_bytes())
                return real_json_loads(value,*args,**kwargs)
            with mock.patch.object(Path,'home',return_value=home), \
                 mock.patch.object(subprocess,'run',return_value=subprocess.CompletedProcess([],0,b'private-token',b'')), \
                 mock.patch('urllib.request.build_opener',return_value=Opener()),mock.patch.object(json,'loads',check_parse):
                if bad_parent or missing_day:
                    with self.assertRaises(ValueError if bad_parent else FileNotFoundError):
                        space['retained_operator_dictionary'](SOURCE)
                    self.assertEqual([],calls)
                    self.assertFalse((op/'response-0001.bin').exists())
                    return
                out=space['retained_operator_dictionary'](SOURCE)
            self.assertEqual([(a.BASE+self.expected_url,60)],calls)
            self.assertEqual(protected,{path:path.read_bytes() for path in protected})
            if timeout:self.assertFalse((op/'response-0001.bin').exists())
            else:
                self.assertEqual(body,(op/'response-0001.bin').read_bytes())
                self.assertEqual(0o600,(op/'response-0001.bin').stat().st_mode&0o777)
            after=json.loads(quota.path.read_bytes());self.assertEqual(8,after['physical_http_attempts'])
            self.assertEqual(2,after['tariff_search_units']);self.assertEqual({'old':'unknown_no_replay'},after['operations'])
            self.assertEqual('failed_unknown_no_replay' if timeout else 'response_received',
                             after['physical_http_reservations'][op.name+':1']['state'])
            result=dict(self.command(),status='complete',production_unchanged=True,supplier_calls=1,
                        database_writes=0,mapping_writes=0,match_retained=out)
            with mock.patch.dict(self.profile(),profile):
                if supplied_body is not None or timeout:
                    self.assertEqual('operator_dictionary_failed_no_replay',out['data']['state'])
                    self.assertEqual(1 if timeout else 0,out['data']['unknown_http_calls'])
                    self.assertNotIn('private-supplier-sentinel',json.dumps(out))
                    self.assertFalse(m.completed_result(self.command(),result,CONTROL))
                    return
                self.assertTrue(m.completed_result(self.command(),result,CONTROL))
                for key,value in [('supplier_calls',True),('unknown_http_calls',1),('native_hotel_authority',True),
                                  ('physical_http_reservations',0),('selected_hotels',1),('new_accepted_mappings',1),
                                  ('dictionary_scope',{'departure_id':True,'country_id':1}),('actions',{'dictionary':True}),
                                  ('raw_response',{'response_number':1,'raw_sha256':'a'*64,'url':'private-url-sentinel'}),
                                  ('safe_to_write_now',True),('bindings',[{'family':'biblio','operator_id':True}])]:
                    bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
                    bad['match_retained']['evidence_sha256']=hashlib.sha256(m.canonical(bad['match_retained']['data'])).hexdigest()
                    with self.subTest(key=key):self.assertFalse(m.completed_result(self.command(),bad,CONTROL))

    def test_real_client_reserves_once_retains_raw_before_parse_and_protects_inputs(self):
        self._dictionary_case()

    def test_invalid_or_duplicate_raw_and_unknown_are_sealed_without_second_request(self):
        for body in (b'not-json',b'[{"id":13,"id":801,"name":"ANEX"}]',b'{"items":[]}'):
            with self.subTest(body=body):self._dictionary_case(supplied_body=body)
        self._dictionary_case(timeout=True)

    def test_new_remote_uses_existing_acquisition_slot_manifest_and_fixed_dictionary_dispatch(self):
        c=self.parser();cmd=self.command()
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,cmd)
        ast.parse(c.REMOTE)
        self.assertIn("if mode=='match-retained-acquire':return retained_operator_dictionary(stage)",c.REMOTE)
        self.assertIn('NEXT_SCOPE='+repr(self.profile()),c.REMOTE)
        self.assertEqual(m.SOURCE_HASHES,c.bundle_source(SOURCE)[1])
        self.assertEqual(CONTROL,cmd['match_retained_control_sha'])

class FullOperatorDictionaryTest(OperatorDictionaryTest):
    profile_name = 'OPERATOR_BINDING_FULL'
    parent_mode = 'match-retained-readback'
    parent_state = 'binding_failure_reconciled_before_supplier_call'
    expected_url = '/operators'

    def space(self):
        space={};exec(m.full_operator_binding_handler(),space);return space

    def test_current_snapshot_or_missing_day_cannot_trigger_http(self):
        self._dictionary_case(bad_parent=True)
        self._dictionary_case(missing_day=True)

    def test_new_scope_cannot_use_consumed_filtered_command_or_parent(self):
        c=self.parser();cmd=self.command()
        for key,value in [('target_operation_id',m.OPERATOR_BINDING['parent_operation_id']),
                          ('operation_id',m.OPERATOR_BINDING['operation_id']),
                          ('cohort',m.OPERATOR_BINDING['cohort'])]:
            bad=dict(cmd);bad[key]=value
            with self.subTest(key=key),self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(bad.values()))
        self.assertNotIn("client.call('dictionary', '/operators', {'departureId'",m.full_operator_binding_handler())
        self.assertIn("dictionary_scope={'kind': 'all_active_operators'}",m.full_operator_binding_handler())


if __name__=='__main__':unittest.main()


