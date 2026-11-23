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
        for mode,cohort in m.LEGACY_MODES.items():
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
        for mode,cohort in m.LEGACY_MODES.items():
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


class BiblioIdentityTest(unittest.TestCase):
    profile_name = 'BG_IDENTITY_SCOPE'
    operator_id = 18
    accepted_namespace = 'bgoperator'
    native_namespace = 'operator_url:bgoperator.ru:f4'
    native_url = 'https://www.bgoperator.ru/price.shtml?f4=10212345'
    candidate_file = 'bg-native-candidate.json'
    reason_prefix = 'bg_'

    def profile(self): return getattr(m,self.profile_name)
    def handler(self): return m.BG_IDENTITY_HANDLER
    def binding(self,parent,profile,kind):
        return parent(profile['parent_operation_id'],'match-retained-acquire',dict(state='operator_dictionary_captured',
            account='TOURVISOR_ANEX_JWT',bindings=[{'family':'biblio','operator_id':18}],unknown_http_calls=0))

    def command(self):
        p=self.profile()
        return dict(source_sha=SHA,mode='match-retained-acquire',operation_id=p['operation_id'],
                    cohort=p['cohort'],target_operation_id=p['parent_operation_id'])

    def test_closed_scope_and_original_bundle(self):
        c=core();m.register_parser(c);cmd=self.command();text=c.PREFIX+' '.join(cmd.values())
        self.assertEqual(cmd,c.parse_command(text))
        for key in ('mode','operation_id','target_operation_id'):
            bad=dict(cmd);bad[key]+='x'
            with self.subTest(key=key),self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(bad.values()))
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(text)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,cmd)
        ast.parse(c.REMOTE)
        self.assertIn('return retained_'+self.reason_prefix+'identity(stage)',c.REMOTE)
        self.assertIn('NEXT_SCOPE='+repr(self.profile()),c.REMOTE)
        self.assertEqual(m.SOURCE_HASHES,c.bundle_source(SOURCE)[1])

    def run_case(self,kind='candidate',bad_parent=False):
        fixed_now=dt.datetime(2026,10,22,tzinfo=dt.timezone.utc)
        with tempfile.TemporaryDirectory() as td, mock.patch('datetime.datetime',wraps=dt.datetime) as clock:
            clock.now.return_value=fixed_now
            home=Path(td);project=home/'project';project.mkdir();(project/'config.php').write_text('private-config')
            private=home/'private';private.mkdir();profile=copy.deepcopy(self.profile())
            op=private/profile['operation_id'];op.mkdir()
            def parent(name,mode,data):
                data=dict(data,operation_id=name,source_sha=SHA)
                value=dict(status='complete',mode=mode,production_unchanged=True,source_sha=SHA,operation_id=name,
                    match_retained=dict(data=data,evidence_sha256=hashlib.sha256(m.canonical(data)).hexdigest()))
                directory=private/name;directory.mkdir();(directory/'result.json').write_bytes(m.canonical(value))
                (directory/'reservation.json').write_bytes(m.canonical(dict(operation_id=name,source_sha=SHA)))
                return value
            binding=self.binding(parent,profile,kind)
            profile['parent_evidence_sha256']=binding['match_retained']['evidence_sha256']
            if self.operator_id==25:
                profile['parent_receipt_sha256']=hashlib.sha256(m.canonical(binding)).hexdigest()
                profile['alias_source_sha']=SHA if kind!='alias_source' else '0'*40
            context={'adults':1,'child_ages_signature':'13','children_count':1,'country_id':1,
                'departure_date':'2026-10-24','departure_id':11,'hotel_id':321,'nights':6,
                'observed_at':'2026-10-08 00:57:29','source':'user_search'}
            row=dict(tv_hotel_id=321,anchor_state='canonical_anchor_ok',target={'country_id':1,'is_active':1},
                     accepted_provider_ids={'operator_5':['17678']},future_context=context)
            if kind=='already':row['accepted_provider_ids'][self.accepted_namespace]=['old']
            if kind=='wrong_party':row['future_context']['adults']=2
            profile['target_row_sha256']=hashlib.sha256(m.canonical(row)).hexdigest()
            current=parent(profile['current_operation_id'],'match-retained-current',dict(state='current_retained_read_only',rows=[row]))
            profile['current_evidence_sha256']=current['match_retained']['evidence_sha256']
            profile['current_receipt_sha256']=hashlib.sha256(m.canonical(current)).hexdigest()
            if bad_parent:profile['parent_evidence_sha256']='0'*64
            quota=q.Quota(home);quota.root.mkdir(parents=True);quota.path.write_bytes(q.encoded(dict(provider='tourvisor-anex',
                provider_day=quota.day,physical_http_attempts=7,tariff_search_units=2,operations={'old':'unknown_no_replay'})))
            quota.apply_policy()
            hotel=home/'.anytoour-match/retained-selection-claims.json';hotel.write_text('{"old":"preserve"}')
            ledger=home/'.anytoour-match/retained-context-claims.json'
            oldclaim={'context':{'account':'TOURVISOR_ANEX_JWT','namespace':'anex_online','operator_id':13,'tv_hotel_id':321}}
            if kind=='prior_context':oldclaim['context'].update(operator_id=self.operator_id,namespace='tourvisor_operator:'+str(self.operator_id))
            if kind=='other_operator':oldclaim['context'].update(operator_id=18,namespace='tourvisor_operator:18')
            ledger.write_bytes(m.canonical({'schema_version':1,'claims':{'old':oldclaim}}))
            protected={p:p.read_bytes() for p in private.rglob('*') if p.is_file()}
            protected[hotel]=hotel.read_bytes();protected[project/'config.php']=(project/'config.php').read_bytes()
            space={};exec(self.handler(),space)
            space.update(NEXT_SCOPE=profile,private=private,project=project,op=op,operation=op.name,
                mode='match-retained-acquire',source=SHA,payload=dict(self.command(),match_retained_control_sha=CONTROL),
                pathlib=__import__('pathlib'),hashlib=hashlib,json=json,time=__import__('time'),os=os,
                fail=lambda reason:(_ for _ in ()).throw(ValueError(reason)),
                safe_file=lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound)
            values=[{'searchId':999},{'progress':100},[] if kind=='empty' else [{'id':321,'tours':[{'id':9991,'operatorId':self.operator_id}]}],
                    {'hotelId':321,'operatorId':13 if kind=='mismatch' else self.operator_id,
                     'operatorLink':self.native_url}]
            if kind=='pending':values=[{'searchId':999}]+[{'progress':0}]*3
            calls=[];self_case=self;real_loads=json.loads
            class Response:
                code=200
                def __init__(self,body,index):self.body=body;self.index=index
                def __enter__(self):return self
                def __exit__(self,*args):return False
                def read(self,n):
                    if kind=='timeout' and self.index==2:raise TimeoutError('private-url-secret-sentinel')
                    return self.body
            class Opener:
                def open(self,request,timeout):
                    index=len(calls)+1;state=real_loads(quota.path.read_bytes())
                    debit=state['physical_http_reservations'][op.name+':'+str(index)]
                    self_case.assertEqual('reserved_unknown_no_replay',debit['state'])
                    self_case.assertEqual(7+index,state['physical_http_attempts'])
                    calls.append((request.full_url,timeout));return Response(json.dumps(values[index-1]).encode(),index)
            def parse_check(body,*args,**kwargs):
                if body in [json.dumps(v).encode() for v in values]:
                    self.assertTrue(any(p.read_bytes()==body for p in op.glob('response-*.bin')))
                return real_loads(body,*args,**kwargs)
            with mock.patch.object(Path,'home',return_value=home),mock.patch.object(subprocess,'run',return_value=subprocess.CompletedProcess([],0,b'token',b'')), \
                 mock.patch('urllib.request.build_opener',return_value=Opener()),mock.patch('time.sleep'),mock.patch.object(json,'loads',parse_check):
                run=space['retained_'+self.reason_prefix+'identity']
                if bad_parent or kind in ('already','wrong_party','alias_label','alias_foreign','alias_bool','alias_source','prior_context'):
                    before=ledger.read_bytes()
                    with self.assertRaises(ValueError):run(SOURCE)
                    self.assertEqual([],calls);self.assertEqual(before,ledger.read_bytes());return
                out=run(SOURCE)
                count=3 if kind=='empty' else 2 if kind=='timeout' else 4
                self.assertEqual(count,len(calls))
                if kind=='pending':
                    self.assertEqual(self.reason_prefix+'search_not_complete',out['data']['stop_reason'])
                    self.assertEqual({'search_start':1,'search_status':3},out['data']['actions'])
                query=__import__('urllib.parse',fromlist=['parse_qs','urlsplit'])
                params=query.parse_qs(query.urlsplit(calls[0][0]).query)
                for key,value in {'childs':['13'],'adults':['1'],'operatorIds':[str(self.operator_id)],'hotelIds':['321'],
                                  'dateFrom':['2026-10-24'],'dateTo':['2026-10-24'],'departureId':['11'],'countryId':['1']}.items():
                    self.assertEqual(value,params[key])
                self.assertTrue(all(0<t<=60 for _,t in calls))
                self.assertEqual(protected,{p:p.read_bytes() for p in protected})
                claims=real_loads(ledger.read_bytes());self.assertEqual(oldclaim,claims['claims']['old']);self.assertEqual(2,len(claims['claims']))
                self.assertEqual('tourvisor_operator:'+str(self.operator_id),next(v['context']['namespace'] for k,v in claims['claims'].items() if k!='old'))
                self.assertNotIn('private-url-secret-sentinel',json.dumps(out))
                self.assertNotIn('f4=',json.dumps(out))
                self.assertFalse(out['data']['native_hotel_authority']);self.assertFalse(out['data']['safe_to_write_now'])
                result=dict(self.command(),status='complete',production_unchanged=True,supplier_calls=count,
                            database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,match_retained=out)
                with mock.patch.dict(self.profile(),profile):
                    good=kind in ('candidate','empty','other_operator')
                    self.assertEqual(good,m.completed_result(self.command(),result,CONTROL))
                    if kind in ('candidate','other_operator'):
                        self.assertEqual(1,out['data']['native_candidate_count'])
                        candidate=real_loads((op/self.candidate_file).read_bytes())
                        self.assertEqual(self.native_namespace,candidate['namespace'])
                        self.assertTrue(candidate['independent_namespace_bridge_required'])
                    if good:
                        for key,value in [('supplier_calls',True),('physical_http_reservations',0),('unknown_http_calls',1),
                            ('operator_id',13),('tv_hotel_id',129),('native_hotel_authority',True),('hotel_sales_access_proven',True),
                            ('parent_evidence_sha256','0'*64),('context_claim_namespace','anex_online'),('target_row_sha256','0'*64),
                            ('booking_calls',1),('lead_calls',1),
                            ('actions',{'search_start':True,'search_status':1,'search_results':1}),('native_candidate_count',True),
                            ('alias_literal_sha256','0'*64) if self.operator_id==25 else ('operator_id',25)]:
                            bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
                            bad['match_retained']['evidence_sha256']=hashlib.sha256(m.canonical(bad['match_retained']['data'])).hexdigest()
                            with self.subTest(key=key):self.assertFalse(m.completed_result(self.command(),bad,CONTROL))
                with self.assertRaisesRegex(ValueError,self.reason_prefix+'context_consumed_no_replay'):run(SOURCE)
                self.assertEqual(count,len(calls))

    def test_real_client_exact_family_wire_raw_first_and_candidate_namespace(self):self.run_case()
    def test_zero_results_completes_without_detail_or_write(self):self.run_case('empty')
    def test_unknown_stops_without_retry_and_wrong_detail_never_completes(self):
        self.run_case('timeout');self.run_case('mismatch');self.run_case('pending')
    def test_bad_parent_existing_bg_and_changed_party_stop_before_network(self):
        self.run_case(bad_parent=True);self.run_case('already');self.run_case('wrong_party')


class FunsunRuIdentityTest(BiblioIdentityTest):
    profile_name='FUNSUN_RU_IDENTITY_SCOPE'
    operator_id=25
    accepted_namespace='operator_315'
    native_namespace='operator_url:fun-sun.ru:hotel-path'
    native_url='https://fun-sun.ru/hotel/12345'
    candidate_file='funsun-ru-native-candidate.json'
    reason_prefix='funsun_ru_'

    def handler(self): return m.funsun_ru_identity_handler()
    def binding(self,parent,profile,kind):
        labels=[dict(field=k,value=None,sha256=profile['alias_literal_sha256']) for k in ('name','fullName','russianName')]
        if kind=='alias_label':labels[0]['sha256']='0'*64
        row=dict(operator_id=93 if kind=='alias_foreign' else True if kind=='alias_bool' else 25,candidate_only=True,labels=labels)
        return parent(profile['parent_operation_id'],'match-retained-readback',dict(state='retained_operator_alias_audit_completed',
            parent_evidence_sha256=m.OPERATOR_ALIAS_AUDIT['parent_evidence_sha256'],raw_sha256=m.OPERATOR_ALIAS_AUDIT['raw_sha256'],
            protected_inputs_unchanged=True,unknown_http_calls=0,alias_candidates=[row]))

    def test_exact_ru_digest_and_other_regions_do_not_authorize_http(self):
        self.assertEqual(self.profile()['alias_literal_sha256'],hashlib.sha256(b'Fun&Sun (RU)').hexdigest())
        for kind in ('alias_label','alias_foreign','alias_bool','alias_source','prior_context'):
            with self.subTest(kind=kind):self.run_case(kind)

    def test_sealed_bg_context_is_preserved_and_disjoint(self):self.run_case('other_operator')


class OperatorAliasAuditTest(unittest.TestCase):
    def command(self):
        p=m.OPERATOR_ALIAS_AUDIT
        return dict(source_sha=SHA,mode='match-retained-readback',operation_id=p['operation_id'],
                    cohort=p['cohort'],target_operation_id=p['parent_operation_id'])

    def fixture(self,root,rows=None):
        profile=copy.deepcopy(m.OPERATOR_ALIAS_AUDIT);private=root/'private';private.mkdir()
        target=private/profile['parent_operation_id'];target.mkdir();op=private/profile['operation_id'];op.mkdir()
        stage=root/'stage';stage.mkdir();manifest={'schema_version':1,'files':m.SOURCE_HASHES}
        (stage/'manifest.json').write_bytes(m.canonical(manifest))
        (target/'installed-source.json').write_bytes(m.canonical({'source_sha':profile['captured_source_sha'],'files':manifest['files']}))
        rows=rows if rows is not None else [{'id':13,'name':'ANEX Tour'},{'id':18,'name':'Библио-Глобус'},
            {'id':43,'name':'Интурист'},{'id':77,'name':'TUI / FUN and SUN','fullName':'FUN&SUN (TUI)'}]
        body=m.canonical(rows);(target/'response-0001.bin').write_bytes(body);profile['raw_sha256']=hashlib.sha256(body).hexdigest()
        space={};exec(m.operator_alias_audit_handler(),space)
        data=dict(state='operator_dictionary_captured',operation_id=target.name,source_sha=profile['captured_source_sha'],
            dictionary_scope={'kind':'all_active_operators'},bindings=space['operator_dictionary_projection'](rows),
            raw_response={'response_number':1,'raw_sha256':profile['raw_sha256']})
        profile['parent_evidence_sha256']=hashlib.sha256(m.canonical(data)).hexdigest()
        parent=dict(status='complete',mode='match-retained-acquire',production_unchanged=True,source_sha=profile['captured_source_sha'],
            operation_id=target.name,match_retained=dict(data=data,evidence_sha256=profile['parent_evidence_sha256']))
        (target/'result.json').write_bytes(m.canonical(parent))
        (target/'reservation.json').write_bytes(m.canonical(dict(operation_id=target.name,source_sha=profile['captured_source_sha'])))
        home=root/'home';ledgers=home/'.anytoour-match';(ledgers/'provider-quotas').mkdir(parents=True)
        for name in ['retained-selection-claims.json','retained-context-claims.json','provider-quotas/tourvisor-anex-2026-10-22.json']:
            (ledgers/name).write_text('{"preserved":true}')
        space.update(NEXT_SCOPE=profile,private=private,op=op,mode='match-retained-readback',operation=op.name,source=SHA,
            payload=dict(self.command(),match_retained_control_sha=CONTROL),pathlib=__import__('pathlib'),hashlib=hashlib,
            json=json,os=os,fail=lambda reason:(_ for _ in ()).throw(ValueError(reason)),
            safe_file=lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound)
        return profile,space,home,stage,target,op

    def test_fixed_mode_dispatches_before_config_db_and_has_no_client(self):
        c=core();m.register_parser(c);cmd=self.command();text=c.PREFIX+' '.join(cmd.values())
        self.assertEqual(cmd,c.parse_command(text))
        for key in ('mode','operation_id','target_operation_id'):
            bad=dict(cmd);bad[key]+='x'
            with self.subTest(key=key),self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(bad.values()))
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(text)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,cmd)
        ast.parse(c.REMOTE)
        self.assertLess(c.REMOTE.index('return retained_operator_alias_audit(stage)'),c.REMOTE.index("config=project/'config.php';dbhelper=project/'data/db-v1.php'"))
        self.assertNotIn('Client(',m.operator_alias_audit_handler())
        self.assertEqual(m.SOURCE_HASHES,c.bundle_source(SOURCE)[1])

    def test_typed_literal_candidates_preserve_all_inputs_without_http_token_or_db(self):
        for rows in (None,[{'id':18,'name':'Библио-Глобус'}]):
            with self.subTest(rows=rows),tempfile.TemporaryDirectory() as td:
                p,s,home,stage,target,op=self.fixture(Path(td),rows)
                before={f:f.read_bytes() for f in Path(td).rglob('*') if f.is_file()}
                with mock.patch.object(Path,'home',return_value=home),mock.patch.object(subprocess,'run',side_effect=AssertionError('no token/PHP/DB')), \
                     mock.patch('urllib.request.build_opener',side_effect=AssertionError('no HTTP')):
                    out=s['retained_operator_alias_audit'](stage)
                self.assertEqual(before,{f:f.read_bytes() for f in before})
                self.assertEqual(8,out['data']['protected_inputs_count'])
                self.assertFalse(out['data']['operator_family_binding_authority']);self.assertFalse(out['data']['native_hotel_authority'])
                if rows is None:
                    self.assertEqual(77,out['data']['alias_candidates'][0]['operator_id'])
                    self.assertEqual('FUN&SUN (TUI)',out['data']['alias_candidates'][0]['labels'][1]['value'])
                else:self.assertEqual([],out['data']['alias_candidates'])
                result=dict(self.command(),status='complete',production_unchanged=True,supplier_calls=0,
                    database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,match_retained=out)
                with mock.patch.dict(m.OPERATOR_ALIAS_AUDIT,p):
                    self.assertTrue(m.completed_result(self.command(),result,CONTROL))
                    for key,value in [('supplier_calls',True),('candidate_count',True),('protected_inputs_count',7),
                        ('operator_family_binding_authority',True),('native_hotel_authority',True),('safe_to_write_now',True),
                        ('raw_sha256','0'*64),('captured_source_sha','0'*40),('prior_bindings_preserved',False),('lead_calls',1)]:
                        bad=copy.deepcopy(result);bad['match_retained']['data'][key]=value
                        bad['match_retained']['evidence_sha256']=hashlib.sha256(m.canonical(bad['match_retained']['data'])).hexdigest()
                        with self.subTest(key=key):self.assertFalse(m.completed_result(self.command(),bad,CONTROL))

    def test_parent_raw_metadata_and_duplicate_or_typed_bad_rows_fail_closed(self):
        for variant in ('parent','raw','metadata','symlink','duplicate','bool_id'):
            with self.subTest(variant=variant),tempfile.TemporaryDirectory() as td:
                p,s,home,stage,target,op=self.fixture(Path(td))
                if variant=='parent':p['parent_evidence_sha256']='0'*64
                elif variant=='raw':(target/'response-0001.bin').write_text('[]')
                elif variant=='metadata':(target/'installed-source.json').write_text('{}')
                elif variant=='symlink':
                    raw=target/'response-0001.bin';raw.rename(target/'other.bin');raw.symlink_to(target/'other.bin')
                else:
                    body=b'[{"id":77,"id":78,"name":"TUI"}]' if variant=='duplicate' else b'[{"id":true,"name":"TUI"}]'
                    (target/'response-0001.bin').write_bytes(body);p['raw_sha256']=hashlib.sha256(body).hexdigest()
                    parent=json.loads((target/'result.json').read_bytes());parent['match_retained']['data']['raw_response']['raw_sha256']=p['raw_sha256']
                    p['parent_evidence_sha256']=hashlib.sha256(m.canonical(parent['match_retained']['data'])).hexdigest()
                    parent['match_retained']['evidence_sha256']=p['parent_evidence_sha256'];(target/'result.json').write_bytes(m.canonical(parent))
                with mock.patch.object(Path,'home',return_value=home),self.assertRaises(ValueError):s['retained_operator_alias_audit'](stage)
                self.assertFalse((op/'retained-evidence.json').exists())

    def test_unsafe_literal_is_hashed_and_candidate_roster_is_bounded(self):
        for label in ('TUI https://private.example/?token=sentinel','TUI eyJhbGci.sentinel.signature','TUI token sentinel'):
            with self.subTest(label=label),tempfile.TemporaryDirectory() as td:
                p,s,home,stage,target,op=self.fixture(Path(td),[{'id':77,'name':label}])
                with mock.patch.object(Path,'home',return_value=home):out=s['retained_operator_alias_audit'](stage)
                self.assertIsNone(out['data']['alias_candidates'][0]['labels'][0]['value'])
                self.assertNotIn('sentinel',json.dumps(out));self.assertNotIn('https://',json.dumps(out))
        with tempfile.TemporaryDirectory() as td:
            p,s,home,stage,target,op=self.fixture(Path(td),[{'id':i+1,'name':'TUI variant '+str(i)} for i in range(9)])
            with mock.patch.object(Path,'home',return_value=home),self.assertRaisesRegex(ValueError,'alias_candidate_bound'):s['retained_operator_alias_audit'](stage)



class OwnerTop500CurrentTest(unittest.TestCase):
    def fields(self):
        p=m.OWNER_TOP500_CURRENT
        return [SHA,'match-retained-current',p['operation_id'],p['cohort'],p['parent_operation_id']]

    def fixture(self, root, missing_parent_target=False):
        profile=copy.deepcopy(m.OWNER_TOP500_CURRENT)
        private=root/'private';target=private/profile['parent_operation_id'];target.mkdir(parents=True)
        op=private/profile['operation_id'];op.mkdir()
        project=root/'project';project.mkdir();(project/'config.php').write_text('PRIVATE_CONFIG_SENTINEL')
        ids=sorted([p[1] for p in profile['pairs']]+[999999])
        if missing_parent_target:ids.remove(profile['pairs'][0][1])
        projection={'state':'projected_read_only','cohort':'common4-retained','cohort_tv_hotel_ids':ids,
                    'cohort_hotels':len(ids),'supplier_calls':0,'database_writes':0,'mapping_writes':0}
        parent={'state':'reprojected_retained_read_only','operation_id':profile['parent_operation_id'],
                'source_sha':SHA,'projection':projection,'supplier_calls':0,'database_writes':0,'mapping_writes':0}
        prior={'status':'complete','mode':'match-retained-project','production_unchanged':True,
               'operation_id':profile['parent_operation_id'],'source_sha':SHA,
               'supplier_calls':0,'database_writes':0,'mapping_writes':0,
               'match_retained':{'data':parent,'evidence_sha256':hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()}}
        (target/'result.json').write_text(json.dumps(prior))
        (target/'reservation.json').write_text(json.dumps({'operation_id':profile['parent_operation_id'],'source_sha':SHA}))
        profile['parent_receipt_sha256']=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
        command=dict(zip(('source_sha','mode','operation_id','cohort','target_operation_id'),self.fields()))
        def fail(reason):raise ValueError(reason)
        space={'OWNER_TOP500_CURRENT':profile,'RETAINED_MODES':m.MODES,'NEXT_SCOPE':m.NEXT_SCOPE,
               'private':private,'project':project,'op':op,'operation':profile['operation_id'],
               'mode':command['mode'],'source':SHA,'payload':dict(command,match_retained_control_sha=CONTROL),
               'result':{},'re':__import__('re'),'json':json,'hashlib':hashlib,'os':os,
               'subprocess':subprocess,'fail':fail,'safe_json':lambda path,bound:json.loads(path.read_text()),
               'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and path.stat().st_size<=bound}
        exec(m.REMOTE_HANDLER,space)
        rows=[]
        for index,(anex,tv,rank) in enumerate(profile['pairs']):
            target_row={'id':str(tv),'is_active':'1','name':'PRIVATE_TARGET_SENTINEL'}
            native=[anex];state='already_effectively_linked'
            if index==1:native=[999999]
            if index==5:target_row['is_active']='0';state='target_ineligible'
            if index==6:target_row=None;native=[];state='target_missing'
            rows.append({'tv_hotel_id':tv,'state':state,'target':target_row,'direct_anex_native_ids':native,
                         'future_context':{'departure_date':'PRIVATE_TRIP_SENTINEL'},
                         'anchors':[{'evidence_json':'PRIVATE_ANCHOR_SENTINEL'}],
                         'accepted_provider_ids':{'other_provider':['PRIVATE_NATIVE_SENTINEL']}})
        data={'state':'current_retained_read_only','captured_at_utc':'2026-11-06T10:00:00+00:00',
              'database_snapshot':'repeatable-read-read-only','parent_receipt_sha256':profile['parent_receipt_sha256'],
              'cohort_hotels':7,'rows':sorted(rows,key=lambda row:row['tv_hotel_id']),
              'direct_anex_global_effective_native_ids':123456,'safe_to_write_now':False,
              'supplier_calls':0,'database_writes':0,'mapping_writes':0}
        def child(*args,**kwargs):
            scope=json.loads((op/'current-scope.json').read_text())
            self.assertEqual(sorted(p[1] for p in profile['pairs']),scope['tv_hotel_ids'])
            self.assertEqual(profile['parent_receipt_sha256'],scope['parent_receipt_sha256'])
            self.assertIn('allow_url_fopen=0',args[0])
            self.assertIn('disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen',args[0])
            self.assertEqual(300,kwargs['timeout'])
            return subprocess.CompletedProcess(args,0,json.dumps(data),'')
        return profile,command,space,child,target,op,project,data

    def result(self, command, evidence):
        return dict(command,status='complete',production_unchanged=True,supplier_calls=0,
                    database_writes=0,mapping_writes=0,match_retained=evidence)

    def rehash(self, result):
        data=result['match_retained']['data']
        result['match_retained']['evidence_sha256']=hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()

    def test_only_exact_current_operation_parent_and_cohort_are_admitted(self):
        import base64,zlib
        c=core();m.register_parser(c);fields=self.fields()
        command=c.parse_command(c.PREFIX+' '.join(fields))
        changes=((0,'z'*40),(1,'match-retained-acquire'),(1,'match-retained-anex-write-v2'),
                 (1,'match-retained-readback'),(2,'int-andromeda-match-retained-current-20261106-v2'),
                 (3,'common4-retained'),(3,'owner-top500-retained8-20261106'),
                 (4,'int-andromeda-match-retained-project-20261020-v1'))
        for index,value in changes:
            bad=fields.copy();bad[index]=value
            with self.subTest(index=index,value=value),self.assertRaises(ValueError):
                c.parse_command(c.PREFIX+' '.join(bad))
        for bad in (fields[:-1],fields+['999999']):
            with self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(bad))
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaisesRegex(ValueError,'first_attempt'):
            c.parse_command(c.PREFIX+' '.join(fields))
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,command)
        ast.parse(c.REMOTE)
        encoded=base64.b64encode(zlib.compress(c.REMOTE.encode(),9)).decode()
        remote_command="python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'"
        self.assertLessEqual(len(remote_command.encode()),65536)

    def test_current_read_is_exact_private_and_preserves_parent_and_config(self):
        with tempfile.TemporaryDirectory() as td:
            p,command,space,child,target,op,project,raw=self.fixture(Path(td))
            before={path:path.read_bytes() for path in [*target.iterdir(),project/'config.php']}
            with mock.patch.object(subprocess,'run',side_effect=child) as run:
                evidence=space['run_match_retained'](SOURCE)
                self.assertEqual(1,run.call_count)
                with self.assertRaises(FileExistsError):space['run_match_retained'](SOURCE)
                self.assertEqual(1,run.call_count)
            data=evidence['data']
            self.assertEqual(4,data['matched_current_pairs'])
            self.assertEqual(['matched_current','expected_pair_unresolved','matched_current','matched_current',
                              'matched_current','target_ineligible','target_missing'],[r['state'] for r in data['rows']])
            self.assertEqual(before,{path:path.read_bytes() for path in before})
            for marker in ('PRIVATE_TARGET_SENTINEL','PRIVATE_TRIP_SENTINEL','PRIVATE_ANCHOR_SENTINEL','PRIVATE_NATIVE_SENTINEL'):
                self.assertIn(marker,(op/'current-raw.json').read_text())
                self.assertNotIn(marker,json.dumps(evidence))
                self.assertNotIn(marker,(op/'retained-evidence.json').read_text())
            self.assertNotIn('direct_anex_global_effective_native_ids',data)
            self.assertEqual(0o600,(op/'current-raw.json').stat().st_mode & 0o777)
            for key in ('publication_admission','website_online_bridge_verified','canonical_publication_binding_verified'):
                self.assertIs(data[key],False)
            with mock.patch.object(m,'OWNER_TOP500_CURRENT',p):
                self.assertTrue(m.completed_result(command,self.result(command,evidence),CONTROL))

    def test_parent_drift_unknown_missing_members_and_retarget_fail_before_database_read(self):
        for case,reason in (('drift','parent_pin'),('unknown','parent_not_complete'),
                            ('missing','parent_membership'),('mode','current_scope'),('cohort','current_scope')):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as td:
                p,command,space,child,target,op,project,raw=self.fixture(Path(td),missing_parent_target=case=='missing')
                if case=='drift':
                    with (target/'result.json').open('a') as handle:handle.write('\n')
                elif case=='unknown':
                    prior=json.loads((target/'result.json').read_text());prior['status']='unknown_no_replay'
                    (target/'result.json').write_text(json.dumps(prior))
                elif case=='mode':space['mode']='match-retained-acquire'
                elif case=='cohort':space['payload']['cohort']='common4-retained'
                with mock.patch.object(subprocess,'run') as run,self.assertRaisesRegex(ValueError,reason):
                    space['current_retained_target'](SOURCE)
                run.assert_not_called()
                self.assertFalse((op/'current-scope.json').exists())
                self.assertFalse((op/'current-raw.json').exists())

    def test_malformed_or_nonzero_child_result_is_never_a_current_confirmation(self):
        for case in ('extra_target','wrong_target','native_bool','write','wrong_snapshot'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as td:
                p,command,space,child,target,op,project,raw=self.fixture(Path(td))
                if case=='extra_target':raw['rows'].append({'tv_hotel_id':999999})
                elif case=='wrong_target':raw['rows'][0]['target']['id']='999999'
                elif case=='native_bool':raw['rows'][0]['direct_anex_native_ids']=[True]
                elif case=='write':raw['mapping_writes']=1
                elif case=='wrong_snapshot':raw['database_snapshot']='read-write'
                with mock.patch.object(subprocess,'run',side_effect=child),self.assertRaises(ValueError):
                    space['run_match_retained'](SOURCE)
                self.assertFalse((op/'retained-evidence.json').exists())

    def test_completion_rejects_changed_pair_scope_admission_and_private_fields_even_with_valid_hash(self):
        with tempfile.TemporaryDirectory() as td:
            p,command,space,child,target,op,project,raw=self.fixture(Path(td))
            with mock.patch.object(subprocess,'run',side_effect=child):
                result=self.result(command,space['run_match_retained'](SOURCE))
            patches=(
                lambda d:d['rows'].pop(),
                lambda d:d['rows'].reverse(),
                lambda d:d['rows'][0].update(anex_hotel_id=999999),
                lambda d:d['rows'][0].update(tv_hotel_id=999999),
                lambda d:d['rows'][0].update(owner_rank=True),
                lambda d:d['rows'][0].update(target_eligible=1),
                lambda d:d['rows'][1].update(state='matched_current'),
                lambda d:d['rows'][0].update(future_context={'private':'sentinel'}),
                lambda d:d.update(anchors=['sentinel']),
                lambda d:d.update(publication_admission=True),
                lambda d:d.update(website_online_bridge_verified=True),
                lambda d:d.update(canonical_publication_binding_verified=True),
                lambda d:d.update(parent_receipt_sha256='f'*64),
                lambda d:d.update(matched_current_pairs=True),
                lambda d:d.update(new_database_reads=2),
                lambda d:d.update(mapping_writes=1),
                lambda d:d.update(captured_at_utc='2026-99-06T10:00:00Z'),
            )
            with mock.patch.object(m,'OWNER_TOP500_CURRENT',p):
                for index,patch in enumerate(patches):
                    with self.subTest(index=index):
                        changed=copy.deepcopy(result);patch(changed['match_retained']['data']);self.rehash(changed)
                        self.assertFalse(m.completed_result(command,changed,CONTROL))
                changed=copy.deepcopy(command);changed['cohort']='common4-retained'
                self.assertFalse(m.completed_result(changed,result,CONTROL))



class Local11IdentityTest(unittest.TestCase):
    """New evidence for exact own→TV pairs; historical profiles remain frozen."""
    def fields(self):
        p=m.LOCAL11_IDENTITY
        return [SHA,'match-retained-current',p['operation_id'],p['cohort'],p['parent_operation_id']]

    def command(self):
        c=core();m.register_parser(c)
        return c,c.parse_command(c.PREFIX+' '.join(self.fields()))

    def identity(self,pair,empty=False):
        return dict(zip(('name','country','region','country_id','region_id'),
                        [None]*5 if empty else pair[2:]))

    def snapshot(self):
        owns=[];sources=[];targets=[]
        for index,pair in enumerate(m.LOCAL11_IDENTITY['pairs']):
            own,tv=pair[:2];identity=self.identity(pair)
            owns.append({'own_id':own,'revision':2,'active':True,'identity':identity,
                         'profile_sha256':'1'*64,'profile_integrity':True})
            sources.append({'own_id':own,'namespace':'legacy_catalog','external_key':str(tv),
                'acquired_via':'saved_catalog','source_sha256':'2'*64,'source_integrity':True,
                'source_identity_id':tv,'identity':identity})
            if tv in (2526,7458):continue
            targets.append({'tv_id':tv,'state':'pending','revision':1,'discovery_identity':identity,
                'content_identity':self.identity(pair,True),'manual_identity':self.identity(pair,True),
                'manual_fields':[],'discovery_sha256':'3'*64,'content_sha256':'4'*64,'manual_sha256':'4'*64,
                'source_absent_sha256':'5'*64,'source_present':False,'source_sha256':None,
                'source_integrity':None,'source_identity_id':None})
        return {'schema_version':1,'state':'local11_identity_snapshot','captured_at_utc':'2026-11-22T10:00:00+00:00',
                'database_snapshot':'repeatable-read-read-only','own_rows':owns,'source_rows':sources,'link_rows':[],
                'target_rows':targets,'supplier_calls':0,'provider_http_calls':0,'database_writes':0,'mapping_writes':0}

    def link(self,own=5371,tv=12120):
        return {'own_id':own,'tv_id':tv,'snapshot_sha256':'6'*64,'snapshot_integrity':True,
            'snapshot_own_id':own,'snapshot_revision':1,'snapshot_profile_sha256':'7'*64,
            'snapshot_profile_integrity':True,'migration_issue_fields':['originKnown','preservedFields'],
            'migration_issues_sha256':'8'*64}

    def parent(self):
        p=m.LOCAL11_IDENTITY
        protected={'config.php':'9'*64,'data/db-v1.php':m.LOCAL11_HASHES['v2/data/db-v1.php']}
        terminal={'schema_version':1,'operation_id':p['parent_operation_id'],'source_sha':p['parent_source_sha'],
            'control_sha':p['parent_control_sha'],'manifest_sha256':p['source_contract']['expected_own_manifest_sha256'],
            'actions_sha256':'45e11557da29ecc652589e5cc89a6dcfa457b57e1a8936fe87d57d2ceb12edb4',
            'original_package_sha256':'01090549e278cbd739efa4936472c256724f6c555bd0d44864d6d21d2ce15123',
            'approval_ref':'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7063885208',
            'state':'committed_verified','requested_profiles':11,'requested_fields':33,
            'plan_sha256':p['plan_sha256'],'profiles_updated':11,'fields_updated':33,
            'field_counts':{'description':11,'primaryImage':11,'images':11},'preserved_fields':0,
            'profile_writes':11,'provenance_writes':11,'database_writes':22,'supplier_calls':0,
            'provider_http_calls':0,'mapping_writes':0,'legacy_writes':0,'schema_writes':0,
            'readback_verified':True,'consumer_readback_verified':True,
            'consumer_sha256':'956f73539da2d6a25cd9c39770eb1e733b5a1c143fe2270856a833696aec7943',
            'owner_receipt_sha256':p['owner_receipt_sha256'],'protected_before':protected,
            'protected_after':copy.deepcopy(protected),'error_code':None}
        before={k:'a'*64 for k in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        return {'schema_version':1,'operation_id':p['parent_operation_id'],'source_sha':p['parent_source_sha'],
            'mode':'local-official11-apply-v1','status':'complete','supplier_calls':0,'database_writes':22,
            'booking_calls':0,'lead_calls':0,'production_before':before,'production_after':copy.deepcopy(before),
            'production_unchanged':True,'profile_writes':11,'provenance_writes':11,'mapping_writes':0,
            'legacy_writes':0,'schema_writes':0,'local_official11':terminal}

    def fixture(self,root):
        p=m.LOCAL11_IDENTITY;c,command=self.command()
        private=root/'private';target=private/p['parent_operation_id'];target.mkdir(parents=True)
        op=private/p['operation_id'];op.mkdir();project=root/'project';project.mkdir()
        (project/'config.php').write_text('PRIVATE_CONFIG_SENTINEL')
        (target/'result.json').write_bytes(m.l11_json(self.parent()))
        (target/'reservation.json').write_text(json.dumps({'operation_id':p['parent_operation_id'],
            'source_sha':p['parent_source_sha'],'mode':'local-official11-apply-v1','reserved_at':1795290000}))
        raw=self.snapshot()
        def fail(reason):raise ValueError(reason)
        space={'LOCAL11_IDENTITY':p,'LOCAL11_HASHES':m.LOCAL11_HASHES,'LOCAL11_RUNNER':m.LOCAL11_RUNNER,
            'private':private,'op':op,'project':project,'source':SHA,'operation':p['operation_id'],
            'payload':dict(command,match_retained_control_sha=CONTROL),'mode':command['mode'],
            'json':json,'hashlib':hashlib,'os':os,'re':__import__('re'),'subprocess':subprocess,'fail':fail,
            'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=bound}
        exec(m.LOCAL11_VALIDATOR+m.LOCAL11_HANDLER,space)
        def child(*args,**kwargs):
            self.assertEqual({'pairs':[v[:2] for v in p['pairs']]},json.loads((op/'local11-scope.json').read_text()))
            self.assertEqual(str(SOURCE/m.LOCAL11_RUNNER),args[0][-1])
            self.assertIn('allow_url_fopen=0',args[0])
            self.assertIn('disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen',args[0])
            self.assertEqual(120,kwargs['timeout'])
            self.assertLessEqual(set(kwargs['env']),{'HOME','PATH','LANG','LC_ALL','MATCH_RETAINED_DIR','MATCH_RETAINED_SOURCE_ROOT'})
            return subprocess.CompletedProcess(args,0,json.dumps(raw),'')
        return command,space,child,target,op,project,raw

    def result(self,command,evidence):
        before={k:'a'*64 for k in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        return {'schema_version':1,**{k:command[k] for k in ('operation_id','source_sha','mode')},
            'status':'complete','production_unchanged':True,'supplier_calls':0,'database_writes':0,
            'mapping_writes':0,'booking_calls':0,'lead_calls':0,'production_before':before,
            'production_after':copy.deepcopy(before),'match_retained':evidence}

    def test_exact_command_narrow_bundle_and_legacy_generated_bytes(self):
        import base64,zlib
        c,command=self.command()
        for index,value in ((0,'z'*40),(1,'match-retained-acquire'),(2,'int-andromeda-match-retained-current-20261122-v2'),
                            (3,'owner-top500-retained7-20261106'),(4,m.OWNER_TOP500_CURRENT['parent_operation_id'])):
            fields=self.fields();fields[index]=value
            with self.subTest(index=index),self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(fields))
        for fields in (self.fields()[:-1],self.fields()+['999999']):
            with self.assertRaises(ValueError):c.parse_command(c.PREFIX+' '.join(fields))
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):
            c.parse_command(c.PREFIX+' '.join(self.fields()))
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,command)
        ast.parse(c.REMOTE);self.assertIn('def current_local11_identity(stage):',c.REMOTE)
        self.assertNotIn('def current_retained_target(stage):',c.REMOTE)
        encoded=base64.b64encode(zlib.compress(c.REMOTE.encode(),9)).decode()
        self.assertLessEqual(len(("python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'").encode()),65536)
        bundle,hashes=c.bundle_source(SOURCE)
        self.assertEqual(m.LOCAL11_HASHES,hashes)
        with tarfile.open(fileobj=io.BytesIO(bundle),mode='r:gz') as archive:
            self.assertEqual({*m.LOCAL11_HASHES,'manifest.json'},{v.name for v in archive.getmembers()})
            self.assertTrue(all(v.mode==0o600 for v in archive.getmembers()))
        with tempfile.TemporaryDirectory() as td:
            db=Path(td)/'v2/data/db-v1.php';db.parent.mkdir(parents=True);db.write_text('source drift')
            with self.assertRaisesRegex(ValueError,'source_drift'):m.bundle_local11_source(Path(td))
        old=core();m.register_parser(old);p=m.OWNER_TOP500_CURRENT
        command=old.parse_command(old.PREFIX+' '.join([SHA,'match-retained-current',p['operation_id'],p['cohort'],p['parent_operation_id']]))
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(old,command)
        self.assertEqual('6cfe1fc88ce03feaefded7692db023cebad894c62f0e65b4dfb1d87c59154eba',hashlib.sha256(old.REMOTE.encode()).hexdigest())
        self.assertEqual('c6b262c97a5a47957c2336f6d1109ebec3e081b3b5ee06e78525dc0ab9ef8048',hashlib.sha256(m.REMOTE_HANDLER.encode()).hexdigest())
        self.assertEqual(17,len(m.SOURCE_FILES))
        _,legacy_hashes=m.bundle_source(SOURCE);self.assertEqual(m.SOURCE_HASHES,legacy_hashes)

    def test_pending_visibility_historical_snapshot_and_absent_targets_are_distinct(self):
        raw=self.snapshot();raw['link_rows']=[self.link()]
        data=m.l11_project(raw,m.LOCAL11_IDENTITY);first=data['rows'][0]
        self.assertEqual('visible',first['reverse_visibility'])
        self.assertEqual('expected_target',first['bridge_status'])
        self.assertEqual('historical_profile',first['snapshot_profile_relation'])
        self.assertNotIn('bridge_snapshot_integrity_failed',first['reasons'])
        self.assertEqual('pending',first['target']['state'])
        self.assertEqual([4516,8689],[r['own_id'] for r in data['rows'] if r['target'] is None])
        self.assertEqual(10,sum(r['bridge_status']=='absent' for r in data['rows']))
        raw['target_rows'][0]['state']='excluded';raw['target_rows'][0]['manual_fields']=['description']
        first=m.l11_project(raw,m.LOCAL11_IDENTITY)['rows'][0]
        self.assertEqual('target_excluded',first['reverse_visibility'])
        self.assertIn('target_manual_fields_present',first['reasons'])

    def test_cross_namespace_collision_integrity_and_wrong_bridge_material(self):
        raw=self.snapshot();first=raw['source_rows'][0]
        first['namespace']='operator_315';first['external_key']='12120'
        row=m.l11_project(raw,m.LOCAL11_IDENTITY)['rows'][0]
        self.assertIn('legacy_source_absent',row['reasons']);self.assertEqual([],row['expected_legacy_owners'])
        foreign=copy.deepcopy(first);foreign.update(own_id=999999,namespace='legacy_catalog',source_integrity=False)
        raw['source_rows'].append(foreign);raw['link_rows']=[self.link(999999,12120),self.link(5371,64957)]
        row=m.l11_project(raw,m.LOCAL11_IDENTITY)['rows'][0]
        self.assertEqual([999999],row['expected_legacy_owners'])
        self.assertIn('legacy_target_owned_by_other_own',row['reasons'])
        self.assertIn('target_linked_to_other_own',row['reasons'])
        self.assertEqual('different_target',row['bridge_status']);self.assertEqual(64957,row['linked_target']['tv_id'])
        raw['link_rows'][1]['snapshot_integrity']=False
        self.assertIn('bridge_snapshot_integrity_failed',m.l11_project(raw,m.LOCAL11_IDENTITY)['rows'][0]['reasons'])
        for mutate in (lambda x:x['own_rows'][0].update(own_id=12120),
                       lambda x:x['own_rows'][0].update(profile_sha256='bad'),
                       lambda x:x['source_rows'].append(copy.deepcopy(x['source_rows'][0])),
                       lambda x:x['target_rows'][0].update(tv_id=True),
                       lambda x:x.update(database_snapshot='read-write'),
                       lambda x:x.update(database_writes=True)):
            bad=self.snapshot();mutate(bad)
            with self.assertRaises(ValueError):m.l11_project(bad,m.LOCAL11_IDENTITY)

    def test_parent_exact_semantic_pins_reject_partial_unknown_and_bool_counts(self):
        m.l11_parent(self.parent(),m.LOCAL11_IDENTITY)
        for mutate in (lambda p:p.update(status='unknown_no_replay'),
                       lambda p:p.update(source_sha='f'*40),
                       lambda p:p['local_official11'].update(control_sha='f'*40),
                       lambda p:p['local_official11'].update(plan_sha256='f'*64),
                       lambda p:p['local_official11'].update(owner_receipt_sha256='f'*64),
                       lambda p:p['local_official11'].update(consumer_sha256='f'*64),
                       lambda p:p['local_official11'].update(readback_verified=False),
                       lambda p:p['local_official11'].update(profiles_updated=10),
                       lambda p:p['local_official11']['field_counts'].update(description=True),
                       lambda p:p['local_official11'].pop('protected_after')):
            prior=self.parent();mutate(prior)
            with self.assertRaises(ValueError):m.l11_parent(prior,m.LOCAL11_IDENTITY)
        with self.assertRaises(ValueError):json.loads('{"id":1,"id":2}',object_pairs_hook=m.l11_unique)

    def test_private_single_run_and_strict_receiver_rehashed_forgery(self):
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,project,raw=self.fixture(Path(td))
            protected={p:p.read_bytes() for p in [*target.iterdir(),project/'config.php']}
            with mock.patch.object(subprocess,'run',side_effect=child) as call:
                evidence=space['current_local11_identity'](SOURCE)
                with self.assertRaises(FileExistsError):space['current_local11_identity'](SOURCE)
                self.assertEqual(1,call.call_count)
            self.assertEqual(protected,{p:p.read_bytes() for p in protected})
            for name in ('local11-scope.json','local11-raw.json','retained-evidence.json'):
                self.assertEqual(0o600,(op/name).stat().st_mode & 0o777)
            self.assertNotIn('PRIVATE_CONFIG_SENTINEL',json.dumps(evidence))
            result=self.result(command,evidence);self.assertTrue(m.completed_result(command,result,CONTROL))
            for mutate in (lambda d:d['rows'].reverse(),
                           lambda d:d['rows'][0].update(own_id=12120),
                           lambda d:d['rows'][0].update(reverse_visibility='visible'),
                           lambda d:d['rows'][0].update(reasons=[]),
                           lambda d:d['rows'][0]['target'].update(state='accepted'),
                           lambda d:d['rows'][0]['target'].update(source_sha256='bad'),
                           lambda d:d['rows'][0].update(profile_json='PRIVATE'),
                           lambda d:d.update(publication_admission=True),
                           lambda d:d.update(safe_to_write_now=True),
                           lambda d:d.update(accepted_native_bindings=1),
                           lambda d:d.update(database_writes=True),
                           lambda d:d.update(source_contract={}),
                           lambda d:d.update(new_database_reads=2)):
                bad=copy.deepcopy(result);mutate(bad['match_retained']['data'])
                bad['match_retained']['evidence_sha256']=hashlib.sha256(m.l11_json(bad['match_retained']['data'])).hexdigest()
                self.assertFalse(m.completed_result(command,bad,CONTROL))
            bad=copy.deepcopy(result);bad['production_after']['index.php']='f'*64
            self.assertFalse(m.completed_result(command,bad,CONTROL))
            bad=copy.deepcopy(result);bad['match_retained']['evidence_sha256']='f'*64
            self.assertFalse(m.completed_result(command,bad,CONTROL))

    def test_parent_or_child_drift_fails_closed_and_no_dml_or_http_in_helper(self):
        for cause in ('parent','reservation','child_scope','child_counter','protected'):
            with self.subTest(cause=cause),tempfile.TemporaryDirectory() as td:
                command,space,child,target,op,project,raw=self.fixture(Path(td))
                if cause=='parent':
                    prior=self.parent();prior['local_official11']['state']='held_before_write'
                    (target/'result.json').write_text(json.dumps(prior))
                if cause=='reservation':(target/'reservation.json').write_text('{}')
                if cause=='child_scope':raw['target_rows'][0]['tv_id']=999999
                if cause=='child_counter':raw['provider_http_calls']=1
                def run(*args,**kwargs):
                    value=child(*args,**kwargs)
                    if cause=='protected':(project/'config.php').write_text('changed')
                    return value
                with mock.patch.object(subprocess,'run',side_effect=run) as call,self.assertRaises(ValueError):
                    space['current_local11_identity'](SOURCE)
                if cause in ('parent','reservation'):call.assert_not_called()
                self.assertFalse((op/'retained-evidence.json').exists())
        php=(ROOT/m.LOCAL11_RUNNER).read_text()
        self.assertNotRegex(php,r'(?i)\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE|migrateLinks|curl_exec|file_put_contents)\b')
        self.assertIn("SET TRANSACTION READ ONLY",php)
        self.assertIn("namespace='legacy_catalog' AND external_key IN",php)
        self.assertIn('WHERE old_local_id IN ($marks) OR tv_id IN ($marks)',php)


@unittest.skipUnless(os.environ.get('MATCH_RETAINED_NATIVE_CI')=='1','native MySQL/PHP CI required')
class Local11IdentityNativeTest(unittest.TestCase):
    def test_actual_bounded_snapshot_excluded_manual_namespace_hash_and_read_only(self):
        lint=subprocess.run(['php','-l',str(ROOT/m.LOCAL11_RUNNER)],capture_output=True,text=True,timeout=20)
        self.assertEqual(0,lint.returncode,lint.stderr)
        code=r'''define('MATCH_LOCAL11_IDENTITY_LIBRARY_ONLY',true);require $argv[1];
        class Local11ProbePDO extends PDO {
          public bool $probe=true;public bool $denied=false;
          public function prepare(string $query,array $options=[]): PDOStatement|false {
            if($this->probe && str_contains($query,'FROM anytour_hotels')){
              $this->probe=false;
              try{$this->exec('DELETE FROM anytour_hotels');}catch(PDOException $error){$this->denied=true;}
              if(!$this->denied)throw new RuntimeException('read_only_dml_not_denied');
            }
            return parent::prepare($query,$options);
          }
        }
        $db=new Local11ProbePDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $name='match_local11_test_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
        try{
          $db->exec('CREATE TABLE anytour_hotels(id BIGINT PRIMARY KEY,profile_json LONGTEXT,profile_sha256 CHAR(64),revision BIGINT,is_active INT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE anytour_hotel_sources(anytour_hotel_id BIGINT,namespace VARCHAR(64),external_key VARBINARY(128),acquired_via VARCHAR(64),source_json LONGTEXT,source_sha256 CHAR(64),first_seen_at DATETIME,last_seen_at DATETIME,UNIQUE KEY(namespace,external_key)) ENGINE=InnoDB');
          $db->exec('CREATE TABLE local_tv_legacy_links(old_local_id BIGINT PRIMARY KEY,tv_id BIGINT,snapshot_json LONGTEXT,snapshot_sha256 CHAR(64),migration_issues_json LONGTEXT) ENGINE=InnoDB');
          $db->exec('CREATE TABLE local_tv_hotels(id BIGINT PRIMARY KEY,state VARCHAR(24),revision BIGINT,discovery_json LONGTEXT,content_json LONGTEXT,manual_json LONGTEXT,source_json LONGTEXT NULL,source_sha256 CHAR(64) NULL,source_absent_json LONGTEXT) ENGINE=InnoDB');
          $scope=['pairs'=>MatchLocal11IdentityV1::PAIRS];
          $proof=json_decode($argv[2],true,16,JSON_THROW_ON_ERROR);
          $insertOwn=$db->prepare('INSERT INTO anytour_hotels VALUES(?,?,?,?,?)');
          $insertSource=$db->prepare("INSERT INTO anytour_hotel_sources VALUES(?,?,?,?,?,?,'2026-11-21','2026-11-21')");
          $insertTarget=$db->prepare('INSERT INTO local_tv_hotels VALUES(?,?,?,?,?,?,?,?,?)');
          foreach($scope['pairs'] as $index=>[$own,$tv]){
            $identity=$proof[$index];
            $profile=json_encode(['name'=>$identity[2],'country'=>['name'=>$identity[3]],'region'=>['name'=>$identity[4]]]);
            $insertOwn->execute([$own,$profile,hash('sha256',$profile),2,1]);
            $source=json_encode(['id'=>$tv,'name'=>$identity[2]]);
            $insertSource->execute([$own,'legacy_catalog',(string)$tv,'saved_catalog',$source,
                $index===2?str_repeat('f',64):hash('sha256',$source)]);
            if(in_array($tv,[2526,7458],true))continue;
            $discovery=json_encode(['name'=>$identity[2],'country'=>['id'=>$identity[5],'name'=>$identity[3]],'region'=>['id'=>$identity[6],'name'=>$identity[4]]]);
            $insertTarget->execute([$tv,$index===1?'excluded':'pending',1,$discovery,'{}',
                $index===0?'{"description":"PRIVATE_MANUAL_SENTINEL","country.name":"Manual country"}':'{}',null,null,'[]']);
          }
          $insertOwn->execute([999999,'{}',hash('sha256','{}'),1,1]);
          $insertSource->execute([5371,'operator_315','5371','native_fixture','{"id":5371}',hash('sha256','{"id":5371}')]);
          $old=['id'=>5371,'profile_json'=>'{}','profile_sha256'=>hash('sha256','{}'),'revision'=>1,'is_active'=>1];
          $snapshot=json_encode(['profile'=>$old,'sources'=>[]]);
          $link=$db->prepare('INSERT INTO local_tv_legacy_links VALUES(?,?,?,?,?)');
          $link->execute([5371,12120,$snapshot,hash('sha256',$snapshot),'{"preservedFields":[],"originKnown":true}']);
          $link->execute([999999,64957,$snapshot,hash('sha256',$snapshot),'{}']);
          $before=[];foreach(['anytour_hotels','anytour_hotel_sources','local_tv_legacy_links','local_tv_hotels'] as $table)
            $before[$table]=hash('sha256',json_encode($db->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC)));
          $result=MatchLocal11IdentityV1::run($db,$scope);
          if(!$db->denied || $db->inTransaction())throw new RuntimeException('snapshot_not_read_only_or_not_closed');
          $after=[];foreach(array_keys($before) as $table)
            $after[$table]=hash('sha256',json_encode($db->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC)));
          if($before!==$after)throw new RuntimeException('fixture_database_changed');
          $bad=$scope;$bad['pairs'][0]=[12120,5371];$scopeDenied=false;
          try{MatchLocal11IdentityV1::run($db,$bad);}catch(RuntimeException $e){$scopeDenied=$e->getMessage()==='local11_scope';}
          if(!$scopeDenied)throw new RuntimeException('cross_namespace_scope_admitted');
          $db->exec('ALTER TABLE anytour_hotel_sources ENGINE=MyISAM');$engineDenied=false;
          try{MatchLocal11IdentityV1::run($db,$scope);}catch(RuntimeException $e){$engineDenied=$e->getMessage()==='local11_snapshot_engine';}
          if(!$engineDenied || $db->inTransaction())throw new RuntimeException('nontransactional_schema_admitted');
          echo json_encode(['snapshot'=>$result,'dml_denied'=>$db->denied,'unchanged'=>$before===$after,'engine_denied'=>$engineDenied],JSON_THROW_ON_ERROR);
        }finally{if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}'''
        run=subprocess.run(['php','-r',code,str(ROOT/m.LOCAL11_RUNNER),json.dumps(m.LOCAL11_IDENTITY['pairs'])],capture_output=True,text=True,timeout=60)
        self.assertEqual(0,run.returncode,run.stderr);data=json.loads(run.stdout);raw=data['snapshot']
        self.assertTrue(data['dml_denied']);self.assertTrue(data['unchanged']);self.assertTrue(data['engine_denied'])
        self.assertEqual(11,len(raw['own_rows']));self.assertEqual(9,len(raw['target_rows']))
        self.assertEqual(12,len(raw['source_rows']));self.assertEqual(2,len(raw['link_rows']))
        self.assertNotIn('PRIVATE_MANUAL_SENTINEL',run.stdout)
        rows=m.l11_project(raw,m.LOCAL11_IDENTITY)['rows']
        first=rows[0];second=rows[1]
        self.assertEqual('visible',first['reverse_visibility'])
        self.assertEqual('historical_profile',first['snapshot_profile_relation'])
        self.assertIn('target_manual_fields_present',first['reasons'])
        self.assertIn('target_manual_identity_present',first['reasons'])
        self.assertEqual('Manual country',first['target']['manual_identity']['country'])
        self.assertNotIn('own_identity_drift',first['reasons'])
        self.assertEqual('excluded',second['target']['state'])
        self.assertIn('target_linked_to_other_own',second['reasons'])
        self.assertIn('own_source_integrity_failed',rows[2]['reasons'])


class Local9ApplyControlTest(unittest.TestCase):
    """Admission, actual consumed-parent binding and honest write receipts."""
    # The sanitized actual read receipt is embedded solely as an immutable test
    # input. Runtime consumes the private parent's original result and reservation.
    PARENT_ZLIB_BASE64 = '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'

    def parent(self):
        import base64,zlib
        return json.loads(zlib.decompress(base64.b64decode(self.PARENT_ZLIB_BASE64)))

    def command(self):
        p=m.LOCAL9_APPLY;c=core();m.register_parser(c)
        body=c.PREFIX+' '.join([p['source_sha'],p['mode'],p['operation_id'],p['cohort'],p['parent_operation_id']])
        return c,c.parse_command(body),body

    def terminal(self,state='committed_verified'):
        p=m.LOCAL9_APPLY;committed=state in ('committed_verified','post_commit_readback_unknown')
        verified=state=='committed_verified';unknown=state in ('commit_unknown','rollback_unknown')
        hashes={'canonical_rows_sha256':'1'*64,'target_preserved_rows_sha256':'2'*64,'excluded_rows_sha256':'3'*64}
        counts=dict(zip(('database_writes','mapping_writes','native_updates','manual_field_writes'),
            [None]*4 if unknown else [18,9,9,27] if committed else [0]*4))
        return {'schema_version':'anytour_local9_bridge_apply_v1','operation_id':p['operation_id'],
            'state':state,'completed_at_utc':'2026-11-23T00:00:00+00:00','proposal_sha256':p['proposal_sha256'],
            'approval_ref':p['approval_ref'],'no_replay':True,'supplier_calls':0,'provider_http_calls':0,**counts,
            'canonical_profile_writes':0,'source_writes':0,'new_target_inserts':0,'accepted_native_bindings':0,
            'commit_acknowledged':committed,'readback_verified':verified,'before':hashes,
            'after':copy.deepcopy(hashes) if verified else None,
            'checks':{'fresh_parent_match':True,'canonical_unchanged':True if verified else None,
                'target_preserved':True if verified else None,'excluded_unchanged':True if verified else None,
                'direct_tv_sql':verified,'own_to_tv_sql':verified,'snapshots_verified':verified},
            'pairs':[{'own_id':own,'tv_id':tv,'revision_from':1,'revision_to':2,
                'manual_fields':['description','primaryImage','images'],'manual_sha256':manual,
                'snapshot_sha256':hashlib.sha256(str(own).encode()).hexdigest(),
                'direct_tv_sql':verified,'own_to_tv_sql':verified} for own,tv,manual in p['pairs']] if verified else [],
            'error':None if verified else {'code':'local9_fixture_failure','message_sha256':'e'*64}}

    def fixture(self,root,terminal=None):
        p=m.LOCAL9_APPLY;c,command,body=self.command()
        private=root/'private';target=private/p['parent_operation_id'];target.mkdir(parents=True)
        op=private/p['operation_id'];op.mkdir();stage=op/'source';stage.mkdir()
        project=root/'project';(project/'data').mkdir(parents=True)
        (project/'config.php').write_text('PRIVATE_CONFIGURATION_SENTINEL')
        (project/'data/db-v1.php').write_bytes((SOURCE/'v2/data/db-v1.php').read_bytes())
        (target/'result.json').write_bytes(m.l11_json(self.parent()))
        (target/'reservation.json').write_text(json.dumps({'operation_id':p['parent_operation_id'],
            'source_sha':p['parent_source_sha'],'mode':'match-retained-current','reserved_at':1795344304}))
        def fail(reason):raise ValueError(reason)
        space={'LOCAL9_APPLY':p,'LOCAL9_HASHES':m.LOCAL9_HASHES,'LOCAL9_RUNNER':m.LOCAL9_RUNNER,
            'LOCAL11_IDENTITY':m.LOCAL11_IDENTITY,'LOCAL11_HASHES':m.LOCAL11_HASHES,
            'completed_local11_identity':m.completed_local11_identity,'private':private,'op':op,'project':project,
            'source':p['source_sha'],'operation':p['operation_id'],'payload':dict(command,match_retained_control_sha=CONTROL),
            'mode':p['mode'],'json':json,'hashlib':hashlib,'os':os,'re':__import__('re'),'subprocess':subprocess,'fail':fail,
            'result':{'supplier_calls':0,'database_writes':0,'mapping_writes':0},
            'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=bound}
        exec(m.LOCAL11_VALIDATOR+m.LOCAL9_VALIDATOR+m.LOCAL9_HANDLER,space)
        terminal=terminal or self.terminal()
        def child(args,**kwargs):
            self.assertEqual(str(stage/m.LOCAL9_RUNNER),args[-1]);self.assertEqual(project,kwargs['cwd'])
            self.assertIn('allow_url_fopen=0',args)
            self.assertIn('disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen',args)
            self.assertLessEqual(set(kwargs['env']),{'HOME','PATH','LANG','LC_ALL','MATCH_RETAINED_DIR','MATCH_RETAINED_SOURCE_ROOT'})
            self.assertEqual(120,kwargs['timeout']);self.assertIs(False,kwargs['text'])
            reservation=json.loads((op/'local9-child-reservation.json').read_text())
            self.assertEqual(p['approval_ref'],reservation['approval_ref']);self.assertTrue(reservation['no_replay'])
            return subprocess.CompletedProcess(args,0 if terminal['state']=='committed_verified' else 1,m.l11_json(terminal),b'')
        return command,space,child,target,op,stage

    def result(self,command,evidence):
        before={k:'a'*64 for k in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        terminal=evidence['data']['terminal']
        return {'schema_version':1,**{k:command[k] for k in ('operation_id','source_sha','mode')},
            'status':'complete','production_unchanged':True,'supplier_calls':0,
            'database_writes':terminal['database_writes'],'mapping_writes':terminal['mapping_writes'],
            'booking_calls':0,'lead_calls':0,'production_before':before,'production_after':copy.deepcopy(before),
            'match_retained':evidence}

    def rehash(self,result):
        result['match_retained']['evidence_sha256']=hashlib.sha256(m.l11_json(result['match_retained']['data'])).hexdigest()

    def test_dedicated_exact_write_command_and_frozen_current_read_command(self):
        import base64,zlib
        c,command,body=self.command();p=m.LOCAL9_APPLY
        for bad in (body+' extra',body.replace(p['source_sha'],SHA),body.replace(p['mode'],'match-retained-current'),
            body.replace(p['operation_id'],p['operation_id']+'-replay'),body.replace(p['cohort'],'common4-retained'),
            body.replace(p['parent_operation_id'],m.LOCAL11_IDENTITY['parent_operation_id'])):
            with self.subTest(command=bad),self.assertRaises(ValueError):c.parse_command(bad)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(body)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,command)
        ast.parse(c.REMOTE)
        self.assertIn('def apply_local9_bridge(stage):',c.REMOTE);self.assertNotIn('def run_match_retained(stage):',c.REMOTE)
        self.assertIn("result['database_writes']=result['mapping_writes']=None",c.REMOTE)
        self.assertEqual(2,c.REMOTE.count("if mode not in ('local9-bridge-apply-v1','reconcile',"))
        self.assertIn("globals().get('local9_reserved') is True",c.REMOTE)
        self.assertIn('len(members)!=4',c.REMOTE)
        encoded=base64.b64encode(zlib.compress(c.REMOTE.encode(),9)).decode()
        self.assertLessEqual(len(("python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'").encode()),65536)
        old,read_command=Local11IdentityTest().command()
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(old,read_command)
        self.assertEqual('b9bdbe96297307e3a9919c6c075beff720509f4099e26b89bf2a241e6dcaa1ab',hashlib.sha256(old.REMOTE.encode()).hexdigest())

    def test_bundle_uses_main_writer_and_immutable_proposal_not_feature_copies(self):
        with tempfile.TemporaryDirectory() as td:
            source=Path(td)/'source';control=Path(td)/'control'
            for rel in m.LOCAL9_HASHES:
                path=source/rel;path.parent.mkdir(parents=True,exist_ok=True)
                path.write_bytes((SOURCE/rel).read_bytes() if rel=='v2/data/db-v1.php' else b'FEATURE CANNOT SELECT THE WRITER OR PROPOSAL')
                if rel!='v2/data/db-v1.php':
                    path=control/rel;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes((ROOT/rel).read_bytes())
            bundle,hashes=m.bundle_local9_source(source)
            with tarfile.open(fileobj=io.BytesIO(bundle),mode='r:gz') as archive:
                self.assertEqual({*m.LOCAL9_HASHES,'manifest.json'},set(archive.getnames()))
                self.assertEqual((ROOT/m.LOCAL9_PROPOSAL).read_bytes(),archive.extractfile(m.LOCAL9_PROPOSAL).read())
                self.assertTrue(all(item.mode==0o600 for item in archive.getmembers()))
            self.assertEqual(m.LOCAL9_HASHES,hashes)
            (control/m.LOCAL9_PROPOSAL).write_bytes((control/m.LOCAL9_PROPOSAL).read_bytes()+b'\n')
            with mock.patch.object(m,'__file__',str(control/'scripts/deploy/controller.py')):
                with self.assertRaisesRegex(ValueError,'local9_source_drift'):m.bundle_local9_source(source)

    def test_actual_parent_receipt_pin_rejects_even_rehashed_substitution(self):
        prior=self.parent();m.l9_parent(prior)
        self.assertEqual(m.LOCAL9_APPLY['parent_evidence_sha256'],prior['match_retained']['evidence_sha256'])
        for mutate in (lambda d:d.update(control_source_sha=CONTROL),lambda d:d.update(current_raw_sha256='9'*64),
            lambda d:d['rows'][0]['own'].update(profile_sha256='e'*64)):
            changed=copy.deepcopy(prior);mutate(changed['match_retained']['data']);self.rehash(changed)
            with self.assertRaises(ValueError):m.l9_parent(changed)

    @unittest.skipUnless(__import__('shutil').which('php'),'native PHP required')
    def test_cli_rejects_reencoded_or_repinned_proposal_before_loading_private_configuration(self):
        proposal=json.loads((ROOT/m.LOCAL9_PROPOSAL).read_bytes())
        proposals=[json.dumps(proposal,ensure_ascii=False).encode()]
        changed=copy.deepcopy(proposal);changed['pairs'][0]['before']['own']['profile_sha256']='1'*64
        proposals.append(json.dumps(changed,ensure_ascii=False).encode())
        for raw in proposals:
            with self.subTest(raw_sha256=hashlib.sha256(raw).hexdigest()),tempfile.TemporaryDirectory() as td:
                home=Path(td);project=home/'www/anytoour.ru';project.mkdir(parents=True)
                op=home/'.anytoour-int-executor'/m.LOCAL9_APPLY['operation_id'];stage=op/'source'
                for rel in m.LOCAL9_HASHES:
                    path=stage/rel;path.parent.mkdir(parents=True,exist_ok=True)
                    path.write_bytes((SOURCE/rel).read_bytes() if rel=='v2/data/db-v1.php' else (ROOT/rel).read_bytes())
                (stage/m.LOCAL9_PROPOSAL).write_bytes(raw)
                marker=home/'configuration_loaded'
                (project/'config.php').write_text('<?php file_put_contents('+json.dumps(str(marker))+',"unexpected");')
                env={k:os.environ[k] for k in ('PATH','LANG','LC_ALL') if k in os.environ}
                env.update(HOME=str(home),MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage))
                call=subprocess.run(['php',str(stage/m.LOCAL9_RUNNER)],cwd=project,env=env,capture_output=True,text=True,timeout=20)
                self.assertEqual(1,call.returncode,call.stderr);self.assertEqual('',call.stderr)
                terminal=json.loads(call.stdout);m.l9_terminal(terminal)
                self.assertEqual('rejected_before_write',terminal['state'])
                self.assertEqual('local9_proposal_hash',terminal['error']['code'])
                self.assertEqual(0,terminal['database_writes']);self.assertFalse(marker.exists())

    def test_success_preserves_actual_parent_and_is_not_replayable(self):
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage=self.fixture(Path(td))
            before={path.name:path.read_bytes() for path in target.iterdir()}
            with mock.patch.object(subprocess,'run',side_effect=child) as call:
                evidence=space['apply_local9_bridge'](stage)
                with self.assertRaises(FileExistsError):space['apply_local9_bridge'](stage)
            self.assertEqual(1,call.call_count);self.assertEqual(before,{path.name:path.read_bytes() for path in target.iterdir()})
            self.assertEqual((18,9),(space['result']['database_writes'],space['result']['mapping_writes']))
            result=self.result(command,evidence);self.assertTrue(m.completed_result(command,result,CONTROL))
            self.assertNotIn('PRIVATE_CONFIGURATION_SENTINEL',json.dumps(result))
            self.assertEqual(evidence['data']['raw_sha256'],hashlib.sha256((op/'local9-raw.json').read_bytes()).hexdigest())
            for mutate in (lambda d:d['terminal'].update(database_writes=True),
                lambda d:d['terminal'].update(provider_http_calls=1),lambda d:d['terminal'].update(accepted_native_bindings=9),
                lambda d:d['terminal']['pairs'][0].update(manual_sha256='f'*64),
                lambda d:d['terminal']['pairs'][0].update(own_id=4516),
                lambda d:d['terminal']['after'].update(excluded_rows_sha256='f'*64),
                lambda d:d.update(approval_ref='https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7063885208')):
                changed=copy.deepcopy(result);mutate(changed['match_retained']['data']);self.rehash(changed)
                self.assertFalse(m.completed_result(command,changed,CONTROL))

    def test_failed_child_receipts_and_unknown_commit_counts_are_preserved(self):
        for state in ('rejected_before_write','rolled_back','commit_unknown','rollback_unknown','post_commit_readback_unknown'):
            with self.subTest(state=state),tempfile.TemporaryDirectory() as td:
                terminal=self.terminal(state);command,space,child,target,op,stage=self.fixture(Path(td),terminal)
                with mock.patch.object(subprocess,'run',side_effect=child):evidence=space['apply_local9_bridge'](stage)
                self.assertEqual(terminal,evidence['data']['terminal'])
                self.assertEqual(terminal['database_writes'],space['result']['database_writes'])
                self.assertEqual(terminal['mapping_writes'],space['result']['mapping_writes'])
                self.assertEqual(1,evidence['data']['child_exit_code'])
                self.assertFalse(m.completed_result(command,self.result(command,evidence),CONTROL))
                self.assertEqual(terminal,json.loads((op/'local9-raw.json').read_bytes()))
                if state in ('commit_unknown','rollback_unknown'):
                    bad=copy.deepcopy(terminal);bad.update(database_writes=0,mapping_writes=0,native_updates=0,manual_field_writes=0)
                    with self.assertRaisesRegex(ValueError,'local9_write_counts'):m.l9_terminal(bad)

    def test_timeout_and_postwrite_guard_failure_cannot_be_reported_as_zero(self):
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage=self.fixture(Path(td))
            timeout=subprocess.TimeoutExpired('php',120,output=b'{"partial":')
            with mock.patch.object(subprocess,'run',side_effect=timeout),self.assertRaisesRegex(ValueError,'timeout_no_replay'):
                space['apply_local9_bridge'](stage)
            self.assertIsNone(space['result']['database_writes']);self.assertIsNone(space['result']['mapping_writes'])
            self.assertEqual(b'{"partial":',(op/'local9-raw.json').read_bytes())
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage=self.fixture(Path(td))
            def drift(*args,**kwargs):
                response=child(*args,**kwargs);(target/'reservation.json').write_text('{}');return response
            with mock.patch.object(subprocess,'run',side_effect=drift),self.assertRaisesRegex(ValueError,'local9_protected_inputs'):
                space['apply_local9_bridge'](stage)
            self.assertEqual(18,space['result']['database_writes']);self.assertEqual(9,space['result']['mapping_writes'])
            self.assertEqual('committed_verified',space['result']['match_retained']['data']['terminal']['state'])
            self.assertFalse(space['result']['match_retained']['data']['protected_inputs_unchanged'])


@unittest.skipUnless(os.environ.get('MATCH_RETAINED_NATIVE_CI')=='1','native MySQL/PHP CI required')
class Local9BridgeNativeTest(unittest.TestCase):
    """Exercise the real writer and accepted reader against independent MySQL fixtures."""
    def test_atomic_apply_rollback_guards_uncertainty_and_actual_consumers(self):
        consumer=Path(os.environ['MATCH_LOCAL9_CONSUMER_ROOT'])/'v2/data'
        for name,digest in {
            'local-tv-catalog-v1.php':'abbed95e0343ce402cdcfb11ef7819da0a12e5bafb7e5bf7f22cbb9260167b11',
            'hotel-details-v1.php':'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
        }.items():
            self.assertEqual(digest,hashlib.sha256((consumer/name).read_bytes()).hexdigest())
        lint=subprocess.run(['php','-l',str(ROOT/m.LOCAL9_RUNNER)],capture_output=True,text=True,timeout=20)
        self.assertEqual(0,lint.returncode,lint.stderr)
        code=r'''define('ANYTOUR_LOCAL9_BRIDGE_APPLY_LIBRARY_ONLY',true);require $argv[1];
        require $argv[3];
        class Local9FaultPDO extends PDO {
          public bool $armed=false;public bool $committed=false;public int $updates=0;public string $fault='';
          public function prepare(string $query,array $options=[]): PDOStatement|false {
            if($this->armed && $this->committed && $this->fault==='readback'
                && preg_match('/^\s*SELECT\b/i',$query))throw new RuntimeException('fixture_postcommit_read_failure');
            return parent::prepare($query,$options);
          }
          public function commit(): bool {
            $ok=parent::commit();
            if($this->armed){$this->committed=true;if($this->fault==='commit_ack')throw new RuntimeException('fixture_commit_ack_lost');}
            return $ok;
          }
        }
        class Local9FaultStatement extends PDOStatement {
          protected function __construct(private Local9FaultPDO $owner) {}
          public function execute(?array $params=null): bool {
            if($this->owner->armed && preg_match('/^\s*UPDATE\s+local_tv_hotels\s/i',$this->queryString)){
              $this->owner->updates++;
              if($this->owner->fault==='mid_write' && $this->owner->updates===2)throw new RuntimeException('fixture_second_update_failure');
            }
            return parent::execute($params);
          }
        }
        function j9(mixed $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
        function need9(bool $v,string $why): void {if(!$v)throw new RuntimeException($why);}
        function ident9(array $x): array {
          $out=['name'=>$x['name']];
          foreach(['country','region'] as $key){
            if($x[$key]!==null){$out[$key]=['name'=>$x[$key]];if($x[$key.'_id']!==null)$out[$key]['id']=$x[$key.'_id'];}
          }
          return $out;
        }
        function hashes9(PDO $db): array {
          $out=[];
          foreach(['anytour_hotels'=>'id','anytour_hotel_sources'=>'id','local_tv_hotels'=>'id','local_tv_legacy_links'=>'old_local_id'] as $table=>$key)
            $out[$table]=hash('sha256',j9($db->query('SELECT * FROM '.$table.' ORDER BY '.$key)->fetchAll(PDO::FETCH_ASSOC)));
          return $out;
        }
        function seed9(PDO $db,array $proposal): array {
          $db->exec('CREATE TABLE anytour_hotels(id BIGINT UNSIGNED PRIMARY KEY,profile_json LONGTEXT NOT NULL,profile_sha256 CHAR(64) NOT NULL,revision BIGINT UNSIGNED NOT NULL,is_active TINYINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE anytour_hotel_sources(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,external_key VARBINARY(128) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,acquired_via VARCHAR(64) NOT NULL,source_json LONGTEXT NOT NULL,source_sha256 CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,UNIQUE KEY uq_source(namespace,external_key),KEY ix_owner(anytour_hotel_id),FOREIGN KEY(anytour_hotel_id) REFERENCES anytour_hotels(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE local_tv_hotels(id INT UNSIGNED PRIMARY KEY,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,pending_since DATETIME NOT NULL,state VARCHAR(24) NOT NULL,discovery_json LONGTEXT NOT NULL,content_json LONGTEXT NOT NULL,manual_json LONGTEXT NOT NULL,source_json LONGTEXT NULL,source_sha256 CHAR(64) NULL,source_fetched_at DATETIME NULL,source_absent_json LONGTEXT NOT NULL,revision BIGINT UNSIGNED NOT NULL,next_attempt_at DATETIME NULL,last_error VARCHAR(1000) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE local_tv_legacy_links(old_local_id BIGINT UNSIGNED PRIMARY KEY,tv_id INT UNSIGNED NOT NULL,snapshot_json LONGTEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,migration_issues_json LONGTEXT NOT NULL,migrated_at DATETIME NOT NULL,KEY ix_tv(tv_id),FOREIGN KEY(tv_id) REFERENCES local_tv_hotels(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $own=$db->prepare("INSERT INTO anytour_hotels VALUES(?,?,?,2,1,'2026-10-01 00:00:00','2026-11-21 12:34:56')");
          $src=$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES(?,?,?,?,?,?,'2026-10-01 00:00:00','2026-11-21 12:34:56')");
          $target=$db->prepare("INSERT INTO local_tv_hotels VALUES(?,'2026-10-02 00:00:00','2026-11-20 13:24:35','2026-10-02 00:00:00','pending',?,'{}','{}',NULL,NULL,NULL,'[]',1,'2026-12-01 01:02:03','private_schedule_sentinel')");
          foreach(['pairs','excluded_pairs'] as $group){
            foreach($proposal[$group] as &$row){
              $profile=ident9($row['before']['own']['identity']);
              $profile += $row['proposed_after']['target']['manual_json']??['description'=>'excluded_private_description','primaryImage'=>'https://example.invalid/excluded.jpg','images'=>['https://example.invalid/excluded.jpg']];
              $profile['private_fixture_sentinel']='do_not_emit_raw_profile';
              $raw=j9($profile);$hash=hash('sha256',$raw);$row['before']['own']['profile_sha256']=$hash;
              $own->execute([$row['own_id'],$raw,$hash]);
              foreach($row['before']['own_sources'] as &$source){
                $payload=$source['namespace']==='legacy_catalog'?(['id'=>$row['tv_id']]+ident9($source['identity'])):['fixtureProvenance'=>$source['namespace'],'privateFixtureValue'=>'do_not_emit_raw_source'];
                $raw=j9($payload);$hash=hash('sha256',$raw);$source['source_sha256']=$hash;
                $src->execute([$source['namespace'],$source['external_key'],$row['own_id'],$source['acquired_via'],$raw,$hash]);
              }
              unset($source);
              if($group==='pairs'){
                $discovery=j9(ident9($row['before']['target']['discovery_identity']));
                $row['before']['target']['discovery_sha256']=hash('sha256',$discovery);
                $target->execute([$row['tv_id'],$discovery]);
              }
            }
            unset($row);
          }
          return $proposal;
        }
        $template=json_decode(file_get_contents($argv[2]),true,64,JSON_THROW_ON_ERROR);$results=[];
        foreach(['success','mid_write','manual_drift','profile_drift','foreign_link','commit_ack','readback'] as $scenario){
          $db=new Local9FaultPDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
          $db->setAttribute(PDO::ATTR_STATEMENT_CLASS,[Local9FaultStatement::class,[$db]]);
          $name='match_local9_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
          try{
            $proposal=seed9($db,$template);
            if($scenario==='manual_drift')$db->exec("UPDATE local_tv_hotels SET manual_json='{\"description\":\"existing_manual\"}' WHERE id=12120");
            if($scenario==='profile_drift')$db->exec('UPDATE anytour_hotels SET revision=3 WHERE id=5371');
            if($scenario==='foreign_link'){
              $insert=$db->prepare("INSERT INTO local_tv_legacy_links VALUES(999999,12120,?,?,?,'2026-11-21 00:00:00')");
              $insert->execute(['{}',hash('sha256','{}'),'{}']);
            }
            $before=hashes9($db);$db->fault=$scenario;$db->armed=true;
            $out=AnyTourLocal9BridgeApplyV1::run($db,$proposal);
            $db->armed=false;need9(!$db->inTransaction(),'open_transaction_'.$scenario);
            $after=hashes9($db);$links=(int)$db->query('SELECT COUNT(*) FROM local_tv_legacy_links')->fetchColumn();
            $updated=(int)$db->query('SELECT COUNT(*) FROM local_tv_hotels WHERE revision=2')->fetchColumn();
            need9($before['anytour_hotels']===$after['anytour_hotels'] && $before['anytour_hotel_sources']===$after['anytour_hotel_sources'],'canonical_changed_'.$scenario);
            need9((int)$db->query('SELECT COUNT(*) FROM local_tv_hotels WHERE id IN(2526,7458)')->fetchColumn()===0,'excluded_created');
            if(in_array($scenario,['success','commit_ack','readback'],true)){
              need9($links===9 && $updated===9,'missing_atomic_rows_'.$scenario);
              foreach($proposal['pairs'] as $pair){
                $q=$db->prepare('SELECT * FROM local_tv_hotels WHERE id=?');$q->execute([$pair['tv_id']]);$target=$q->fetch(PDO::FETCH_ASSOC);
                need9($target['manual_json']===$pair['proposed_after']['target']['manual_json_exact_utf8'],'manual_raw_bytes');
                need9($target['state']==='pending' && $target['source_json']===null && $target['source_sha256']===null && $target['source_fetched_at']===null && $target['content_json']==='{}' && $target['source_absent_json']==='[]','native_state_changed');
                need9($target['next_attempt_at']==='2026-12-01 01:02:03' && $target['last_error']==='private_schedule_sentinel' && $target['last_seen_at']==='2026-11-20 13:24:35','scheduling_changed');
                $q=$db->prepare('SELECT * FROM local_tv_legacy_links WHERE old_local_id=?');$q->execute([$pair['own_id']]);$link=$q->fetch(PDO::FETCH_ASSOC);
                need9(hash('sha256',$link['snapshot_json'])===$link['snapshot_sha256'],'snapshot_hash');
                $snap=json_decode($link['snapshot_json'],true,64,JSON_THROW_ON_ERROR);
                need9(array_keys($snap)===['profile','sources'] && count($snap['profile'])===5 && count($snap['sources'])===3,'snapshot_shape');
                $q=$db->prepare('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?');$q->execute([$pair['own_id']]);
                need9($snap['profile']===$q->fetch(PDO::FETCH_ASSOC),'snapshot_original_profile');
                $q=$db->prepare('SELECT namespace,external_key,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources WHERE anytour_hotel_id=? ORDER BY namespace,external_key');$q->execute([$pair['own_id']]);
                need9($snap['sources']===$q->fetchAll(PDO::FETCH_ASSOC),'snapshot_original_sources');
              }
            }else{
              need9($before===$after,'partial_write_survived_'.$scenario);
              need9($out['database_writes']===0 && $out['mapping_writes']===0 && $out['native_updates']===0 && $out['manual_field_writes']===0,'failed_nonzero_counts');
            }
            if($scenario==='success'){
              need9($out['state']==='committed_verified' && $out['readback_verified']===true && $out['database_writes']===18,'success_terminal');
              $catalog=new LocalTvCatalogV1($db);
              $direct=$catalog->read(array_column($proposal['pairs'],'tv_id'));
              $old=$catalog->read(array_column($proposal['pairs'],'own_id'),true);
              need9(count($direct['items'])===9 && count($old['items'])===9 && count($old['links'])===9 && $direct['missingIds']===[] && $old['missingIds']===[],'real_reader_counts');
              foreach([$direct,$old] as $read){
                $items=[];foreach($read['items'] as $item)$items[$item['id']]=$item;
                foreach($proposal['pairs'] as $pair){
                  $item=$items[$pair['tv_id']];
                  foreach(['description','primaryImage','images'] as $field)need9($item[$field]===$pair['proposed_after']['target']['manual_json'][$field],'real_reader_value_'.$field);
                  need9($item['revision']===2 && $item['detailsAvailable']===false && $item['contentState']==='pending','real_reader_state');
                  need9($item['manualFields']===['description','primaryImage','images'],'real_reader_manual_fields');
                }
              }
              foreach($old['links'] as $i=>$link)need9($link===['oldLocalId'=>$proposal['pairs'][$i]['own_id'],'tourvisorHotelId'=>$proposal['pairs'][$i]['tv_id']],'real_reader_link');
              $out['native_consumer_verified']=true;
            }elseif($scenario==='mid_write'){
              need9($db->updates===2 && $out['state']==='rolled_back','rollback_not_exercised');
            }elseif($scenario==='commit_ack'){
              need9($out['state']==='commit_unknown' && $out['database_writes']===null && $out['mapping_writes']===null && $out['readback_verified']===false,'commit_unknown_misreported');
            }elseif($scenario==='readback'){
              need9($out['state']==='post_commit_readback_unknown' && $out['database_writes']===18 && $out['commit_acknowledged']===true && $out['readback_verified']===false,'postcommit_unknown_misreported');
            }else need9($out['state']==='rejected_before_write','drift_not_rejected');
            $results[$scenario]=$out;
          }finally{$db->armed=false;if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}
        }
        echo j9($results);'''
        run=subprocess.run(['php','-r',code,str(ROOT/m.LOCAL9_RUNNER),str(ROOT/m.LOCAL9_PROPOSAL),
                            str(consumer/'local-tv-catalog-v1.php')],capture_output=True,text=True,timeout=90)
        self.assertEqual(0,run.returncode,run.stderr)
        results=json.loads(run.stdout)
        self.assertEqual({'success','mid_write','manual_drift','profile_drift','foreign_link','commit_ack','readback'},set(results))
        self.assertTrue(results['success'].pop('native_consumer_verified'))
        for terminal in results.values():
            m.l9_terminal(terminal)
        self.assertEqual('committed_verified',results['success']['state'])
        for token in ('do_not_emit_raw_profile','do_not_emit_raw_source','excluded_private_description','private_schedule_sentinel'):
            self.assertNotIn(token,run.stdout)


class Local26IdentityControlTest(unittest.TestCase):
    """New receiving measurement; no legacy operation or native binding replay."""
    PARENT_ZLIB_BASE64 = 'eNrdVst2GzcM3ecrfNRtFPFNMH/Qr9ABSVCe49HMdB5KfXLy7wUVjTx24mbRbtqVbYBzQQD3Xvrrh4eHXez7p6Y7HRO27bT7/CA+1mjGGSNOdPwyNjPVuFXXREuY35xt6YTp+eXkLdrzqWNfSpMabJXjxFeOcwbT3PTddJweUdka32VNMkkbUArvNJBwIVpyJISOJWAWYElJgEhBR5SokofirQCRo0+Gdh9vyMMw9hcuO1KpuI/zPEyfD4dTMz8u8VPqz4fhGecnvtVh6JvpaT8v/MGe7z0fmmlaaDoYL8Jv19/5+Jm6ee+F98EYpcJaJ/H1lzONXAdzxPR0vNDYlIYyV53Hhd6ee2k1RlFyEmAyRWnJUFQeYnRClZClkwq1Vi7ISNJYDznYkqMOEaz2RBY3V5jHvq3IFVYlbW1KwepstEPnHRYpGSV7GYPIJJRTHFo/f2+/nKJx7Mdj6jNxtFva9hbn9lpefb9083RfZoWiKY3NUHfKYeU+ronmjKcrtLL32DBydHz+vaaumWvi27bEdFwGvt51lv6G9nOOcfyMXVN4e5sJezA6Kps0s4S3AwUBSu0ewYsMaFzwymPSDmQRGpCMhBggCe2t1LC7Qw9DFcYPNfuBRqztHpt6x13DHMEuj/2ZMu6vvN+/8H7PMO3zXvECpFRqf7nvoB+bU9MxWwdmEM9j00NIFNGDjZAi98IMIINISVDSJmuhydqQI2ltvZfeFg+oAEJyWiYf7oLov3RXlibiBW1ZqI0k5WNmphiTQCFJr5PJniEzSGdktDUFomhBJrlotEaINjqIzq34Q4vdBhaC0cZEFguATiC1DBq0Txad8BKSYfYL5J9WqMT4AYuLRJH/ZhXJ+7WHkSYaL5SP3xmxGT4LvDTthrcr4dbMlj7b3EyJg0csM41b+rKQSnP6NDwOtQPJNqN5+jJYR8wfA0VgMN4CqaS0DSpoIiGzEzK5ULwSqILhzjL6zK5zZ3pV2CFH3veKnWQCI7OG5BQYG5mBdY1cMKP0JIxkE0w25hxEsNKXkoSKtsTIuypS715p5aWlSKUf6X/S04U67NI7+700melcXf31E8TZXzjxSH+wuc9bRt295SW3UmhbdkqPdMYKO313OPk68YM9TP0yJlqN2RgrLQgZhSy2DsfLwhrm58Q7kjmzJHLKIq7Un2bm7nWy/Po0c73VvaP1yMKO0vAg7jP4cBvhO561O38389073nSzpH/H2Oqe8nJ94t+IjfEy/bkyBwQF5h0INImf//r0SfAURCkZUsmYEwlw1geQOcpgVCgAirLK1Y9WVu4u6oBDs7+oG/DmxeLUtuTrTP1HZo8ZB77i66/v09x08kZj/+VWli49Ynd6pZD3bPVvFPmOMH4ui38iiqsklummiaGlmW7xH3Xw7cNfQeEdgQ=='

    def parent(self):
        import base64,zlib
        return json.loads(zlib.decompress(base64.b64decode(self.PARENT_ZLIB_BASE64)))

    def command(self):
        c=core();m.register_parser(c);p=m.LOCAL26_IDENTITY
        body=c.PREFIX+' '.join([p['source_sha'],'match-retained-current',p['operation_id'],p['cohort'],p['parent_operation_id']])
        return c,c.parse_command(body),body

    def snapshot(self):
        p=m.LOCAL26_IDENTITY;owns=[];sources=[];targets=[]
        for own,tv,name,country,region in p['pairs']:
            identity={'name':name,'country':country,'region':region,'country_id':None,'region_id':None}
            fields={key:{'present':True,'sha256':value,'expected_sha256':value,'matches':True}
                for key,value in p['expected_fields'][str(own)].items()}
            owns.append({'own_id':own,'revision':2,'active':True,'identity':identity,'profile_sha256':'1'*64,
                'profile_integrity':True,'approved_fields':fields})
            sources.append({'own_id':own,'namespace':'legacy_catalog','external_key':str(tv),'acquired_via':'saved_catalog',
                'source_sha256':'2'*64,'source_integrity':True,'source_identity_id':tv,'identity':copy.deepcopy(identity)})
            empty={k:None for k in identity}
            targets.append({'tv_id':tv,'state':'pending','revision':1,'discovery_identity':copy.deepcopy(identity),
                'content_identity':empty,'manual_identity':copy.deepcopy(empty),'manual_fields':[],
                'discovery_sha256':'3'*64,'content_sha256':'4'*64,'manual_sha256':'4'*64,'source_absent_sha256':'5'*64,
                'source_present':False,'source_sha256':None,'source_integrity':None,'source_identity_id':None})
        return {'schema_version':1,'state':'local26_identity_snapshot','captured_at_utc':'2026-11-23T02:00:00+00:00',
            'database_snapshot':'repeatable-read-read-only','own_rows':owns,'source_rows':sources,'link_rows':[],
            'target_rows':targets,'supplier_calls':0,'provider_http_calls':0,'database_writes':0,'mapping_writes':0,
            'approved_manifest_sha256':p['approved_manifest_sha256'],'actions_sha256':p['actions_sha256']}

    def link(self,own,tv):
        return {'own_id':own,'tv_id':tv,'snapshot_sha256':'6'*64,'snapshot_integrity':True,'snapshot_own_id':own,
            'snapshot_revision':1,'snapshot_profile_sha256':'7'*64,'snapshot_profile_integrity':True,
            'migration_issue_fields':['originKnown','preservedFields'],'migration_issues_sha256':'8'*64,
            'snapshot_source_count':3,'snapshot_sources_integrity':True}

    def fixture(self,root):
        p=m.LOCAL26_IDENTITY;c,command,body=self.command()
        private=root/'private';target=private/p['parent_operation_id'];target.mkdir(parents=True)
        op=private/p['operation_id'];op.mkdir();stage=op/'source';stage.mkdir()
        project=root/'project';(project/'data').mkdir(parents=True)
        (project/'config.php').write_text('PRIVATE_LOCAL26_CONFIG_SENTINEL')
        (project/'data/db-v1.php').write_bytes((SOURCE/'v2/data/db-v1.php').read_bytes())
        (target/'result.json').write_bytes(m.l11_json(self.parent()))
        (target/'reservation.json').write_text(json.dumps({'operation_id':p['parent_operation_id'],
            'source_sha':p['parent_source_sha'],'mode':'local-official26-apply-v1','reserved_at':1795346000}))
        def fail(reason):raise ValueError(reason)
        space={'LOCAL26_IDENTITY':p,'LOCAL26_HASHES':m.LOCAL26_HASHES,'LOCAL26_RUNNER':m.LOCAL26_RUNNER,
            'private':private,'op':op,'project':project,'source':p['source_sha'],'operation':p['operation_id'],
            'payload':dict(command,match_retained_control_sha=CONTROL),'mode':'match-retained-current',
            'json':json,'hashlib':hashlib,'os':os,'re':__import__('re'),'subprocess':subprocess,'fail':fail,
            'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=bound}
        exec(m.LOCAL11_VALIDATOR+m.LOCAL26_VALIDATOR+m.LOCAL26_HANDLER,space)
        raw=self.snapshot()
        def child(args,**kwargs):
            self.assertEqual({'pairs':[r[:2] for r in p['pairs']]},json.loads((op/'local26-scope.json').read_text()))
            self.assertEqual(str(stage/m.LOCAL26_RUNNER),args[-1]);self.assertEqual(project,kwargs['cwd'])
            self.assertIn('allow_url_fopen=0',args)
            self.assertIn('disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen',args)
            self.assertLessEqual(set(kwargs['env']),{'HOME','PATH','LANG','LC_ALL','MATCH_RETAINED_DIR','MATCH_RETAINED_SOURCE_ROOT'})
            self.assertEqual(120,kwargs['timeout']);self.assertIs(False,kwargs['text'])
            return subprocess.CompletedProcess(args,0,m.l11_json(raw),b'')
        return command,space,child,target,op,stage,raw

    def result(self,command,evidence):
        before={k:'a'*64 for k in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        return {'schema_version':1,**{k:command[k] for k in ('operation_id','source_sha','mode')},
            'status':'complete','production_unchanged':True,'supplier_calls':0,'database_writes':0,'mapping_writes':0,
            'booking_calls':0,'lead_calls':0,'production_before':before,'production_after':copy.deepcopy(before),'match_retained':evidence}

    def test_exact_read_admission_bundle_and_all_consumed_generated_commands_stay_frozen(self):
        import base64,zlib
        c,command,body=self.command();p=m.LOCAL26_IDENTITY
        for bad in (body+' extra',body.replace(p['source_sha'],SHA),body.replace('match-retained-current','match-retained-acquire'),
            body.replace(p['operation_id'],p['operation_id']+'-retry'),body.replace(p['cohort'],'local11-identity-20261122'),
            body.replace(p['parent_operation_id'],m.LOCAL11_IDENTITY['parent_operation_id'])):
            with self.subTest(command=bad),self.assertRaises(ValueError):c.parse_command(bad)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(body)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,command)
        ast.parse(c.REMOTE);self.assertIn('def current_local26_identity(stage):',c.REMOTE)
        self.assertNotIn('def apply_local9_bridge(stage):',c.REMOTE);self.assertNotIn('def current_local11_identity(stage):',c.REMOTE)
        self.assertIn("globals().get('local26_reserved') is True",c.REMOTE)
        self.assertEqual(2,c.REMOTE.count("if mode not in ('match-retained-current','reconcile',"))
        encoded=base64.b64encode(zlib.compress(c.REMOTE.encode(),9)).decode()
        self.assertLessEqual(len(("python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'").encode()),65536)
        bundle,hashes=c.bundle_source(SOURCE);self.assertEqual(m.LOCAL26_HASHES,hashes)
        with tarfile.open(fileobj=io.BytesIO(bundle),mode='r:gz') as archive:
            self.assertEqual({*m.LOCAL26_HASHES,'manifest.json'},set(archive.getnames()))
            self.assertTrue(all(item.mode==0o600 for item in archive.getmembers()))
            self.assertEqual((ROOT/m.LOCAL26_MANIFEST).read_bytes(),archive.extractfile(m.LOCAL26_MANIFEST).read())
        old,legacy,_=Local9ApplyControlTest().command()
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(old,legacy)
        self.assertEqual('a7a8e12e3072516270c5abf1b0999d69bab344026999e745a1a162451b45d8c2',hashlib.sha256(old.REMOTE.encode()).hexdigest())
        # Existing LOCAL11 and OWNER7 tests independently pin their complete remote bytes and SOURCE17.
        old_ids={pair[0] for pair in m.LOCAL11_IDENTITY['pairs']}
        self.assertFalse(old_ids.intersection(pair[0] for pair in p['pairs']))

    def test_actual_consumed_parent_and_manifest_field_hashes_are_exact(self):
        parent=self.parent();p=m.LOCAL26_IDENTITY;m.l26_parent(parent,p)
        self.assertEqual('82eebab327b6e43bb9c3342d4b6696d7769632b65eb956af1516509ef31fbd55',hashlib.sha256(m.l11_json(parent)).hexdigest())
        for mutate in (lambda d:d['local_official26'].update(control_sha=CONTROL),
            lambda d:d['local_official26'].update(fields_updated=78),lambda d:d.update(mapping_writes=1),
            lambda d:d['local_official26'].update(consumer_readback_verified=False)):
            changed=copy.deepcopy(parent);mutate(changed)
            with self.assertRaisesRegex(ValueError,'local26_actual_parent_material'):m.l26_parent(changed,p)
        manifest=json.loads((ROOT/m.LOCAL26_MANIFEST).read_bytes())
        fields={str(a['anytour_hotel_id']):{v['field']:hashlib.sha256(json.dumps(v['after'],ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
            for v in a['field_changes']} for a in manifest['proposed_actions']}
        self.assertEqual(fields,p['expected_fields']);self.assertEqual(76,sum(len(v) for v in fields.values()))
        self.assertEqual({'description'},set(fields['4172']))

    def test_approved_values_are_observed_independently_of_historical_revision_and_identity(self):
        raw=self.snapshot();p=m.LOCAL26_IDENTITY;data=m.l26_project(raw,p)
        self.assertEqual((76,76,26),(data['summary']['approved_fields_requested'],data['summary']['approved_fields_matching'],data['summary']['approved_profiles_all_fields_matching']))
        hilton=next(r for r in raw['own_rows'] if r['own_id']==4172)
        hilton['revision']=3;hilton['approved_fields']['description'].update(sha256='f'*64,matches=False)
        data=m.l26_project(raw,p);row=next(r for r in data['rows'] if r['own_id']==4172)
        self.assertEqual(75,data['summary']['approved_fields_matching']);self.assertTrue(row['own']['profile_integrity'])
        self.assertEqual('approved_fields_missing_or_changed',row['approved_content_status'])
        self.assertNotIn('official26_revision_not_observed',row['reasons'])
        hilton['approved_fields']['images']={'present':True,'sha256':'a'*64,'expected_sha256':'a'*64,'matches':True}
        with self.assertRaisesRegex(ValueError,'local26_approved_field_set'):m.l26_project(raw,p)

    def test_actual_bridge_leg_and_all_its_owners_are_separate_from_candidate(self):
        raw=self.snapshot();p=m.LOCAL26_IDENTITY;first,second=p['pairs'][:2]
        raw['link_rows']=[self.link(first[0],first[1]),self.link(second[0],500000),self.link(999998,500000)]
        raw['target_rows'][0]['state']='excluded'
        other=copy.deepcopy(raw['target_rows'][1]);other['tv_id']=500000;raw['target_rows'].append(other)
        rows=m.l26_project(raw,p)['rows']
        self.assertEqual(('candidate_target','target_excluded','historical_profile'),
            tuple(rows[0][k] for k in ('bridge_status','reverse_visibility','snapshot_profile_relation')))
        self.assertEqual('different_target',rows[1]['bridge_status']);self.assertEqual(500000,rows[1]['linked_target']['tv_id'])
        self.assertEqual([second[0],999998],rows[1]['linked_target_link_owners'])
        self.assertIn('actual_target_linked_to_other_own',rows[1]['reasons'])
        self.assertEqual('observed_candidate_edge',rows[1]['legacy_candidate_status'])
        self.assertEqual(0,m.l26_project(raw,p)['accepted_native_bindings'])
        raw['link_rows'][1]['snapshot_sources_integrity']=False
        self.assertEqual('invalid',m.l26_project(raw,p)['rows'][1]['snapshot_profile_relation'])

    def test_raw_scope_duplicate_ownership_and_field_match_forgery_are_rejected(self):
        for mutate in (lambda d:d.update(database_writes=True),lambda d:d.update(approved_manifest_sha256='a'*64),
            lambda d:d['own_rows'].append(copy.deepcopy(d['own_rows'][0])),
            lambda d:d['own_rows'][0]['approved_fields']['description'].update(expected_sha256='f'*64),
            lambda d:d['own_rows'][0]['approved_fields']['description'].update(matches=False),
            lambda d:d['target_rows'][0].update(tv_id=999999),
            lambda d:d['source_rows'][0].update(own_id=999999,namespace='operator_315')):
            raw=self.snapshot();mutate(raw)
            with self.subTest(mutate=mutate),self.assertRaises(ValueError):m.l26_project(raw,m.LOCAL26_IDENTITY)

    def test_runtime_terminal_preserves_parent_rejects_replay_and_rehashed_forgery(self):
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage,raw=self.fixture(Path(td));before={p.name:p.read_bytes() for p in target.iterdir()}
            with mock.patch.object(subprocess,'run',side_effect=child) as call:
                evidence=space['current_local26_identity'](stage)
                with self.assertRaises(FileExistsError):space['current_local26_identity'](stage)
            self.assertEqual(1,call.call_count);self.assertEqual(before,{p.name:p.read_bytes() for p in target.iterdir()})
            result=self.result(command,evidence);self.assertTrue(m.completed_result(command,result,CONTROL))
            self.assertNotIn('PRIVATE_LOCAL26_CONFIG_SENTINEL',json.dumps(result))
            self.assertEqual(hashlib.sha256((op/'local26-raw.json').read_bytes()).hexdigest(),evidence['data']['current_raw_sha256'])
            for mutate in (lambda d:d['summary'].update(approved_fields_matching=75),
                lambda d:d.update(accepted_native_bindings=26),lambda d:d.update(safe_to_write_now=True),
                lambda d:d.update(parent_canonical_sha256='f'*64),
                lambda d:d['rows'][0].update(legacy_candidate_status='accepted_native'),
                lambda d:d['rows'][0]['own']['approved_fields']['description'].update(expected_sha256='f'*64),
                lambda d:d['protected_after'].update(**{'config.php':'f'*64})):
                changed=copy.deepcopy(result);mutate(changed['match_retained']['data'])
                changed['match_retained']['evidence_sha256']=hashlib.sha256(m.l11_json(changed['match_retained']['data'])).hexdigest()
                self.assertFalse(m.completed_result(command,changed,CONTROL))

    def test_parent_reservation_and_postread_protected_drift_fail_closed(self):
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage,raw=self.fixture(Path(td))
            (target/'reservation.json').write_text('{}')
            with mock.patch.object(subprocess,'run') as call,self.assertRaisesRegex(ValueError,'local26_parent_reservation'):
                space['current_local26_identity'](stage)
            call.assert_not_called()
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage,raw=self.fixture(Path(td))
            def drift(*args,**kwargs):
                response=child(*args,**kwargs);(target/'result.json').write_text('{}');return response
            with mock.patch.object(subprocess,'run',side_effect=drift),self.assertRaisesRegex(ValueError,'local26_protected_drift'):
                space['current_local26_identity'](stage)
            self.assertFalse((op/'retained-evidence.json').exists())

    @unittest.skipUnless(__import__('shutil').which('php'),'native PHP required')
    def test_cli_manifest_tamper_is_rejected_before_private_configuration(self):
        with tempfile.TemporaryDirectory() as td:
            home=Path(td);project=home/'www/anytoour.ru';project.mkdir(parents=True)
            op=home/'.anytoour-int-executor'/m.LOCAL26_IDENTITY['operation_id'];stage=op/'source'
            for rel in m.LOCAL26_HASHES:
                path=stage/rel;path.parent.mkdir(parents=True,exist_ok=True)
                path.write_bytes((SOURCE/rel).read_bytes() if rel=='v2/data/db-v1.php' else (ROOT/rel).read_bytes())
            (op/'local26-scope.json').write_text(json.dumps({'pairs':[r[:2] for r in m.LOCAL26_IDENTITY['pairs']]}))
            (stage/m.LOCAL26_MANIFEST).write_bytes((stage/m.LOCAL26_MANIFEST).read_bytes()+b'\n')
            marker=home/'configuration_loaded';(project/'config.php').write_text('<?php file_put_contents('+json.dumps(str(marker))+',"unexpected");')
            env={k:os.environ[k] for k in ('PATH','LANG','LC_ALL') if k in os.environ}
            env.update(HOME=str(home),MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage))
            call=subprocess.run(['php',str(stage/m.LOCAL26_RUNNER)],cwd=project,env=env,capture_output=True,text=True,timeout=20)
            self.assertEqual(1,call.returncode,call.stderr);self.assertEqual('',call.stderr)
            result=json.loads(call.stdout);self.assertEqual('failed_read_only',result['state']);self.assertFalse(marker.exists())
            self.assertEqual(hashlib.sha256(b'local26_manifest_hash').hexdigest(),result['error_sha256'])


@unittest.skipUnless(os.environ.get('MATCH_RETAINED_NATIVE_CI')=='1','native MySQL/PHP CI required')
class Local26IdentityNativeTest(unittest.TestCase):
    def test_actual_readonly_snapshot_provenance_approved_fields_and_all_linked_owners(self):
        lint=subprocess.run(['php','-l',str(ROOT/m.LOCAL26_RUNNER)],capture_output=True,text=True,timeout=20)
        self.assertEqual(0,lint.returncode,lint.stderr)
        code=r'''define('MATCH_LOCAL26_IDENTITY_LIBRARY_ONLY',true);require $argv[1];
        class Local26ReadProbePDO extends PDO {
          public bool $probe=false;public bool $denied=false;
          public function prepare(string $query,array $options=[]): PDOStatement|false {
            if($this->probe && str_contains($query,'FROM anytour_hotels')){
              $this->probe=false;
              try{$this->exec('UPDATE anytour_hotels SET revision=revision+1 WHERE id=4511');}
              catch(PDOException $error){$this->denied=($error->errorInfo[1]??null)===1792;}
              if(!$this->denied)throw new RuntimeException('fixture_read_only_dml_not_denied');
            }
            return parent::prepare($query,$options);
          }
        }
        function json26(mixed $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
        function need26(bool $v,string $reason): void {if(!$v)throw new RuntimeException($reason);}
        function hashes26(PDO $db): array {
          $out=[];
          foreach(['anytour_hotels'=>'id','anytour_hotel_sources'=>'anytour_hotel_id,namespace,external_key','local_tv_legacy_links'=>'old_local_id','local_tv_hotels'=>'id'] as $table=>$order)
            $out[$table]=hash('sha256',json26($db->query('SELECT * FROM '.$table.' ORDER BY '.$order)->fetchAll(PDO::FETCH_ASSOC)));
          return $out;
        }
        function identity26(array $identity): array {
          return ['name'=>$identity['name'],'country'=>['name'=>$identity['country']],'region'=>['name'=>$identity['region']]];
        }
        $manifest=json_decode(file_get_contents($argv[2]),true,64,JSON_THROW_ON_ERROR);
        $scope=['pairs'=>MatchLocal26IdentityV1::PAIRS];$actions=$identities=[];
        foreach($manifest['proposed_actions'] as $a)$actions[$a['anytour_hotel_id']]=$a;
        foreach($manifest['targets'] as $target)$identities[$target['anytourHotelId']]=$target['expectedIdentity'];
        $db=new Local26ReadProbePDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $name='match_local26_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
        try{
          $db->exec('CREATE TABLE anytour_hotels(id BIGINT UNSIGNED PRIMARY KEY,profile_json LONGTEXT NOT NULL,profile_sha256 CHAR(64) NOT NULL,revision BIGINT UNSIGNED NOT NULL,is_active TINYINT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE anytour_hotel_sources(anytour_hotel_id BIGINT UNSIGNED NOT NULL,namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,external_key VARBINARY(128) NOT NULL,acquired_via VARCHAR(64) NOT NULL,source_json LONGTEXT NOT NULL,source_sha256 CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,UNIQUE KEY(namespace,external_key),KEY ix_own(anytour_hotel_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE local_tv_legacy_links(old_local_id BIGINT UNSIGNED PRIMARY KEY,tv_id INT UNSIGNED NOT NULL,snapshot_json LONGTEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,migration_issues_json LONGTEXT NOT NULL,KEY ix_tv(tv_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE local_tv_hotels(id INT UNSIGNED PRIMARY KEY,state VARCHAR(24) NOT NULL,revision BIGINT UNSIGNED NOT NULL,discovery_json LONGTEXT NOT NULL,content_json LONGTEXT NOT NULL,manual_json LONGTEXT NOT NULL,source_json LONGTEXT NULL,source_sha256 CHAR(64) NULL,source_absent_json LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $insertOwn=$db->prepare('INSERT INTO anytour_hotels VALUES(?,?,?,?,?)');
          $insertSource=$db->prepare("INSERT INTO anytour_hotel_sources VALUES(?,?,?,?,?,?,'2026-10-01 00:00:00','2026-11-22 11:00:00')");
          $insertTarget=$db->prepare('INSERT INTO local_tv_hotels VALUES(?,?,?,?,?,?,?,?,?)');
          foreach($scope['pairs'] as $index=>[$own,$tv]){
            $profile=identity26($identities[$own]);
            foreach($actions[$own]['field_changes'] as $change)$profile[$change['field']]=$change['after'];
            if($own===4172){
              $profile['primaryImage']='https://example.invalid/retained-hilton-primary.jpg';$profile['images']=[];
              for($i=0;$i<44;$i++)$profile['images'][]='https://example.invalid/retained-hilton-'.($i+1).'.jpg';
            }
            $profile['private_fixture_note']='PRIVATE_LOCAL26_PROFILE_SENTINEL';
            $raw=json26($profile);$insertOwn->execute([$own,$raw,hash('sha256',$raw),2,1]);
            foreach([['anytour_local_id',(string)$tv,'canonical_local_alias_v1'],['legacy_catalog',(string)$tv,'saved_catalog'],['profile_enrichment:official_review_v1',$manifest['proposed_actions_sha256'].':'.$own,'profile_fill_official_v1']] as [$ns,$key,$via]){
              $payload=$ns==='legacy_catalog'?(['id'=>$tv]+identity26($identities[$own])):['fixture'=>'PRIVATE_LOCAL26_SOURCE_SENTINEL'];
              $raw=json26($payload);$hash=($index===5 && $ns==='legacy_catalog')?str_repeat('f',64):hash('sha256',$raw);
              $insertSource->execute([$own,$ns,$key,$via,$raw,$hash]);
            }
            if($index===2)continue;
            $discovery=identity26($identities[$own]);if($index===8)$discovery['name']='ANOTHER HOTEL';
            $manual=$index===3?json26(['description'=>'PRIVATE_LOCAL26_MANUAL_SENTINEL','country.name'=>'Manual country']):'{}';
            $source=$index===4?json26(['id'=>999991,'private'=>'PRIVATE_LOCAL26_NATIVE_SENTINEL']):null;
            $insertTarget->execute([$tv,$index===1?'excluded':($index===4?'ready':'pending'),1,json26($discovery),'{}',$manual,$source,$source===null?null:hash('sha256',$source),'[]']);
          }
          foreach([999996,999997,999998] as $id){$raw='{}';$insertOwn->execute([$id,$raw,hash('sha256',$raw),1,1]);}
          // The candidate legacy key is owned by somebody else, independently of the own-ID namespace.
          $db->exec("DELETE FROM anytour_hotel_sources WHERE anytour_hotel_id=14932 AND namespace='legacy_catalog'");
          $raw=json26(['id'=>113544]+identity26($identities[14932]));
          $insertSource->execute([999996,'legacy_catalog','113544','saved_catalog',$raw,hash('sha256',$raw)]);
          $raw='{"id":4511}';$insertSource->execute([4511,'operator_315','4511','native_fixture',$raw,hash('sha256',$raw)]);
          // A linked non-candidate target and its other owner must both be measured, without recursion.
          $insertTarget->execute([500000,'pending',1,json26(['name'=>'Alternate linked target']),'{}','{}',null,null,'[]']);
          $insertLink=$db->prepare('INSERT INTO local_tv_legacy_links VALUES(?,?,?,?,?)');
          foreach([[4511,2490,1],[7599,500000,2],[999997,2490,1],[999998,500000,1]] as [$own,$tv,$revision]){
            $q=$db->prepare('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?');$q->execute([$own]);$profile=$q->fetch(PDO::FETCH_ASSOC);$profile['revision']=$revision;
            $q=$db->prepare("SELECT namespace,external_key,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources WHERE anytour_hotel_id=? AND namespace<>'operator_315' ORDER BY namespace,external_key");$q->execute([$own]);$sources=$q->fetchAll(PDO::FETCH_ASSOC);
            $raw=json26(['profile'=>$profile,'sources'=>$sources]);
            $insertLink->execute([$own,$tv,$raw,hash('sha256',$raw),json26(['preservedFields'=>[],'originKnown'=>true])]);
          }
          $before=hashes26($db);$db->probe=true;$db->denied=false;
          $first=MatchLocal26IdentityV1::run($db,$scope,$manifest);
          need26($db->denied && !$db->inTransaction(),'read_only_transaction_not_closed');
          need26($before===hashes26($db),'first_snapshot_changed_database');
          $own=[];foreach($first['own_rows'] as $row)$own[$row['own_id']]=$row;
          $matches=0;foreach($own as $row)foreach($row['approved_fields'] as $field){need26($field['present'] && $field['matches'] && $field['sha256']===$field['expected_sha256'],'approved_field_mismatch');$matches++;}
          need26($matches===76 && array_keys($own[4172]['approved_fields'])===['description'],'approved_field_count_or_hilton_scope');
          // A validly re-hashed canonical edit is measured as value drift, not hidden by profile integrity.
          $q=$db->query('SELECT profile_json FROM anytour_hotels WHERE id=4511');$profile=json_decode($q->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
          $profile['description'].=' fixture drift';$raw=json26($profile);
          $q=$db->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=? WHERE id=4511');$q->execute([$raw,hash('sha256',$raw)]);
          $driftBefore=hashes26($db);$db->probe=true;$db->denied=false;
          $drift=MatchLocal26IdentityV1::run($db,$scope,$manifest);
          need26($db->denied && !$db->inTransaction() && $driftBefore===hashes26($db),'drift_snapshot_not_read_only');
          $bad=$scope;$bad['pairs'][0]=[2490,4511];$scopeDenied=false;
          try{MatchLocal26IdentityV1::run($db,$bad,$manifest);}catch(RuntimeException $e){$scopeDenied=$e->getMessage()==='local26_scope';}
          need26($scopeDenied && !$db->inTransaction(),'cross_namespace_scope_accepted');
          $db->exec('ALTER TABLE anytour_hotel_sources ENGINE=MyISAM');$engineDenied=false;
          try{MatchLocal26IdentityV1::run($db,$scope,$manifest);}catch(RuntimeException $e){$engineDenied=$e->getMessage()==='local26_snapshot_engine';}
          need26($engineDenied && !$db->inTransaction(),'nontransactional_source_accepted');
          echo json26(['snapshot'=>$first,'drift'=>$drift,'dml_denied'=>true,'unchanged'=>true,'scope_denied'=>$scopeDenied,'engine_denied'=>$engineDenied]);
        }finally{if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}'''
        run=subprocess.run(['php','-r',code,str(ROOT/m.LOCAL26_RUNNER),str(ROOT/m.LOCAL26_MANIFEST)],capture_output=True,text=True,timeout=90)
        self.assertEqual(0,run.returncode,run.stderr)
        data=json.loads(run.stdout);raw=data['snapshot'];drift=data['drift']
        for key in ('dml_denied','unchanged','scope_denied','engine_denied'):self.assertTrue(data[key])
        self.assertEqual('repeatable-read-read-only',raw['database_snapshot'])
        self.assertEqual((26,79,4,26),tuple(len(raw[k]) for k in ('own_rows','source_rows','link_rows','target_rows')))
        for key in ('supplier_calls','provider_http_calls','database_writes','mapping_writes'):self.assertEqual(0,raw[key])
        own={r['own_id']:r for r in raw['own_rows']}
        self.assertEqual(76,sum(field['matches'] for row in own.values() for field in row['approved_fields'].values()))
        self.assertEqual(['description'],list(own[4172]['approved_fields']))
        changed={r['own_id']:r for r in drift['own_rows']}
        self.assertTrue(changed[4511]['profile_integrity']);self.assertFalse(changed[4511]['approved_fields']['description']['matches'])
        self.assertEqual(75,sum(field['matches'] for row in changed.values() for field in row['approved_fields'].values()))
        sources=raw['source_rows'];legacy={r['external_key']:r for r in sources if r['namespace']=='legacy_catalog'}
        self.assertEqual(999996,legacy['113544']['own_id']);self.assertFalse(legacy['74509']['source_integrity'])
        self.assertTrue(any(r['namespace']=='operator_315' and r['external_key']=='4511' for r in sources))
        links={r['own_id']:r for r in raw['link_rows']}
        self.assertEqual({4511,7599,999997,999998},set(links))
        self.assertEqual((2490,1,3,True),(links[4511]['tv_id'],links[4511]['snapshot_revision'],links[4511]['snapshot_source_count'],links[4511]['snapshot_sources_integrity']))
        self.assertEqual((500000,500000),(links[7599]['tv_id'],links[999998]['tv_id']))
        targets={r['tv_id']:r for r in raw['target_rows']}
        self.assertNotIn(7831,targets);self.assertIn(500000,targets)
        self.assertEqual('excluded',targets[22940]['state'])
        self.assertEqual('Manual country',targets[4283]['manual_identity']['country'])
        self.assertIn('description',targets[4283]['manual_fields'])
        self.assertEqual((True,True,999991),(targets[97001]['source_present'],targets[97001]['source_integrity'],targets[97001]['source_identity_id']))
        self.assertEqual('ANOTHER HOTEL',targets[52337]['discovery_identity']['name'])
        projected=m.l26_project(raw,m.LOCAL26_IDENTITY)
        rows={row['own_id']:row for row in projected['rows']}
        self.assertEqual([7599,999998],rows[7599]['linked_target_link_owners'])
        self.assertEqual('different_target',rows[7599]['bridge_status'])
        self.assertEqual([999996],rows[14932]['expected_legacy_owners'])
        self.assertTrue(all(row['approved_content_status']=='exact_approved_values' for row in rows.values()))
        changed_rows={row['own_id']:row for row in m.l26_project(drift,m.LOCAL26_IDENTITY)['rows']}
        self.assertEqual('approved_fields_missing_or_changed',changed_rows[4511]['approved_content_status'])
        for sentinel in ('PRIVATE_LOCAL26_PROFILE_SENTINEL','PRIVATE_LOCAL26_SOURCE_SENTINEL','PRIVATE_LOCAL26_MANUAL_SENTINEL','PRIVATE_LOCAL26_NATIVE_SENTINEL','retained-hilton-'):
            self.assertNotIn(sentinel,run.stdout)


@unittest.skipUnless(os.environ.get('MATCH_RETAINED_NATIVE_CI')=='1','native MySQL/PHP CI required')
class Local19BridgeNativeTest(unittest.TestCase):
    """The prepared writer must preserve the full26 cohort, including Hilton."""

    def test_cli_without_exact_execution_admission_never_loads_config(self):
        with tempfile.TemporaryDirectory() as td:
            root=Path(td);marker=root/'config-was-loaded'
            (root/'config.php').write_text('<?php file_put_contents('+json.dumps(str(marker))+',"called");')
            env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
            env.update(MATCH_RETAINED_DIR=str(root/'operation'),MATCH_RETAINED_SOURCE_ROOT=str(ROOT))
            for admission in (None,'0','true','01'):
                current=dict(env)
                if admission is not None:current['MATCH_LOCAL19_EXECUTION_ADMITTED']=admission
                run=subprocess.run(['php',str(ROOT/'scripts/catalog/anytour_local19_bridge_apply_v1.php')],
                                   cwd=root,env=current,capture_output=True,text=True,timeout=20)
                self.assertEqual(1,run.returncode,run.stderr)
                terminal=json.loads(run.stdout)
                self.assertEqual('rejected_before_write',terminal['state'])
                self.assertEqual(0,terminal['database_writes'])
                self.assertFalse(terminal['commit_acknowledged'])
                self.assertNotIn('approval_ref',terminal)
                self.assertEqual('local19_execution_not_admitted',terminal['error']['code'])
                self.assertFalse(marker.exists(),'unadmitted CLI loaded project config')
                self.assertFalse((root/'operation').exists(),'unadmitted CLI created operation state')

    def test_atomic19_preserves_hilton6_and_handles_drift_rollback_unknown_and_real_consumers(self):
        consumer=Path(os.environ['MATCH_LOCAL9_CONSUMER_ROOT'])/'v2/data'
        for name,digest in {
            'local-tv-catalog-v1.php':'abbed95e0343ce402cdcfb11ef7819da0a12e5bafb7e5bf7f22cbb9260167b11',
            'hotel-details-v1.php':'137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
        }.items():self.assertEqual(digest,hashlib.sha256((consumer/name).read_bytes()).hexdigest())
        runner=ROOT/'scripts/catalog/anytour_local19_bridge_apply_v1.php'
        proposal_path=ROOT/'scripts/catalog/anytour-local19-bridge-proposal-20261123.json'
        self.assertEqual('02d3df3ecd94d775e58fd3e506cf81032b64179fa3b4db1ff43de9e515ec2192',
                         hashlib.sha256(proposal_path.read_bytes()).hexdigest())
        lint=subprocess.run(['php','-l',str(runner)],capture_output=True,text=True,timeout=20)
        self.assertEqual(0,lint.returncode,lint.stderr)
        code=r'''define('ANYTOUR_LOCAL19_BRIDGE_APPLY_LIBRARY_ONLY',true);require $argv[1];
        require $argv[3];
        class Local19FaultPDO extends PDO {
          public bool $armed=false;public bool $committed=false;
          public int $updates=0;public int $commits=0;public int $rollbacks=0;public int $begins=0;
          public string $fault='';
          public function beginTransaction(): bool {
            if($this->armed)$this->begins++;
            return parent::beginTransaction();
          }
          public function prepare(string $query,array $options=[]): PDOStatement|false {
            if($this->armed && $this->committed && $this->fault==='readback'
                && preg_match('/^\s*SELECT\b/i',$query))throw new RuntimeException('fixture_postcommit_read_failure');
            return parent::prepare($query,$options);
          }
          public function commit(): bool {
            $ok=parent::commit();
            if($this->armed){$this->commits++;$this->committed=true;
              if($this->fault==='commit_ack')throw new RuntimeException('fixture_commit_ack_lost');}
            return $ok;
          }
          public function rollBack(): bool {
            $ok=parent::rollBack();
            if($this->armed){$this->rollbacks++;
              if($this->fault==='rollback_ack')throw new RuntimeException('fixture_rollback_ack_lost');}
            return $ok;
          }
        }
        class Local19FaultStatement extends PDOStatement {
          protected function __construct(private Local19FaultPDO $owner) {}
          public function execute(?array $params=null): bool {
            if($this->owner->armed && preg_match('/^\s*UPDATE\s+local_tv_hotels\s/i',$this->queryString)){
              $this->owner->updates++;
              if(in_array($this->owner->fault,['mid_write','rollback_ack'],true) && $this->owner->updates===10)
                throw new RuntimeException('fixture_tenth_update_failure');
            }
            return parent::execute($params);
          }
        }
        function j19(mixed $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
        function need19(bool $v,string $why): void {if(!$v)throw new RuntimeException($why);}
        function ident19(array $x): array {
          $out=[];if($x['name']!==null)$out['name']=$x['name'];
          foreach(['country','region'] as $key){
            if($x[$key]!==null){$out[$key]=['name'=>$x[$key]];if($x[$key.'_id']!==null)$out[$key]['id']=$x[$key.'_id'];}
          }
          return $out;
        }
        function hashes19(PDO $db): array {
          $out=[];
          foreach(['anytour_hotels'=>'id','anytour_hotel_sources'=>'id','local_tv_hotels'=>'id','local_tv_legacy_links'=>'old_local_id'] as $table=>$key)
            $out[$table]=hash('sha256',j19($db->query('SELECT * FROM '.$table.' ORDER BY '.$key)->fetchAll(PDO::FETCH_ASSOC)));
          return $out;
        }
        function preserved19(PDO $db): string {
          $pairs=[...AnyTourLocal19BridgeApplyV1::EXCLUDED_PAIRS,...AnyTourLocal19BridgeApplyV1::UNCHANGED_PAIRS];
          $own=implode(',',array_column($pairs,0));$tv=implode(',',array_column($pairs,1));$out=[];
          foreach(['own'=>['anytour_hotels','id',$own,'id'],
                   'sources'=>['anytour_hotel_sources','anytour_hotel_id',$own,'id'],
                   'target'=>['local_tv_hotels','id',$tv,'id'],
                   'bridge'=>['local_tv_legacy_links','old_local_id',$own,'old_local_id']] as $key=>[$table,$column,$ids,$order])
            $out[$key]=$db->query('SELECT * FROM '.$table.' WHERE '.$column.' IN('.$ids.') ORDER BY '.$order)->fetchAll(PDO::FETCH_ASSOC);
          return hash('sha256',j19($out));
        }
        function seed19(PDO $db,array $proposal,array $manifest): array {
          $db->exec('CREATE TABLE anytour_hotels(id BIGINT UNSIGNED PRIMARY KEY,profile_json LONGTEXT NOT NULL,profile_sha256 CHAR(64) NOT NULL,revision BIGINT UNSIGNED NOT NULL,is_active TINYINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE anytour_hotel_sources(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,namespace VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,external_key VARBINARY(128) NOT NULL,anytour_hotel_id BIGINT UNSIGNED NOT NULL,acquired_via VARCHAR(64) NOT NULL,source_json LONGTEXT NOT NULL,source_sha256 CHAR(64) NOT NULL,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,UNIQUE KEY uq_source(namespace,external_key),KEY ix_owner(anytour_hotel_id),FOREIGN KEY(anytour_hotel_id) REFERENCES anytour_hotels(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE local_tv_hotels(id INT UNSIGNED PRIMARY KEY,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,pending_since DATETIME NOT NULL,state VARCHAR(24) NOT NULL,discovery_json LONGTEXT NOT NULL,content_json LONGTEXT NOT NULL,manual_json LONGTEXT NOT NULL,source_json LONGTEXT NULL,source_sha256 CHAR(64) NULL,source_fetched_at DATETIME NULL,source_absent_json LONGTEXT NOT NULL,revision BIGINT UNSIGNED NOT NULL,next_attempt_at DATETIME NULL,last_error VARCHAR(1000) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $db->exec('CREATE TABLE local_tv_legacy_links(old_local_id BIGINT UNSIGNED PRIMARY KEY,tv_id INT UNSIGNED NOT NULL,snapshot_json LONGTEXT NOT NULL,snapshot_sha256 CHAR(64) NOT NULL,migration_issues_json LONGTEXT NOT NULL,migrated_at DATETIME NOT NULL,KEY ix_tv(tv_id),FOREIGN KEY(tv_id) REFERENCES local_tv_hotels(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
          $actions=[];foreach($manifest['proposed_actions'] as $action)$actions[$action['anytour_hotel_id']]=$action;
          $own=$db->prepare("INSERT INTO anytour_hotels VALUES(?,?,?,2,1,'2026-10-01 00:00:00','2026-11-21 12:34:56')");
          $src=$db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES(?,?,?,?,?,?,'2026-10-01 00:00:00','2026-11-21 12:34:56')");
          $target=$db->prepare('INSERT INTO local_tv_hotels VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
          foreach(['pairs','excluded_pairs','unchanged_existing_pairs'] as $group){
            foreach($proposal[$group] as &$row){
              $profile=ident19($row['before']['own']['identity']);
              foreach($actions[$row['own_id']]['field_changes'] as $change)$profile[$change['field']]=$change['after'];
              if($row['own_id']===4172){
                $profile['primaryImage']='https://example.invalid/local19-retained-hilton.jpg';
                $profile['images']=array_fill(0,44,'https://example.invalid/local19-retained-hilton-gallery.jpg');
              }
              $profile['private_fixture_sentinel']='PRIVATE_LOCAL19_PROFILE';
              $raw=j19($profile);$hash=hash('sha256',$raw);$row['before']['own']['profile_sha256']=$hash;
              foreach($row['before']['own']['approved_fields'] as $field=>$pin)
                need19(hash('sha256',j19($profile[$field]))===$pin['sha256'],'fixture_approved_field_'.$field);
              $own->execute([$row['own_id'],$raw,$hash]);
              foreach($row['before']['own_sources'] as &$source){
                $payload=$source['namespace']==='legacy_catalog'?(['id'=>$row['tv_id']]+ident19($source['identity'])):
                  ['fixtureProvenance'=>$source['namespace'],'privateFixtureValue'=>'PRIVATE_LOCAL19_SOURCE'];
                $raw=j19($payload);$hash=hash('sha256',$raw);$source['source_sha256']=$hash;
                $src->execute([$source['namespace'],$source['external_key'],$row['own_id'],$source['acquired_via'],$raw,$hash]);
              }
              unset($source);
              if($group==='excluded_pairs')continue;
              $pin=&$row['before']['target'];$hilton=$group==='unchanged_existing_pairs';
              $fields=['discovery'=>$hilton?'[]':j19(ident19($pin['discovery_identity'])),
                'content'=>$hilton?j19(ident19($pin['content_identity'])+['description'=>'PRIVATE_LOCAL19_HILTON_NATIVE']):'{}',
                'manual'=>$hilton?'[]':'{}','source_absent'=>$hilton?'["private_fixture_absent_field"]':'[]'];
              foreach($fields as $field=>$bytes)$pin[$field.'_sha256']=hash('sha256',$bytes);
              $native=$hilton?j19(['id'=>80008]+ident19($pin['content_identity'])):null;
              $pin['source_sha256']=$native===null?null:hash('sha256',$native);
              if(!$hilton)$row['proposed_after']['target']['preserved_discovery_sha256']=$pin['discovery_sha256'];
              $target->execute([$row['tv_id'],'2026-10-02 00:00:00','2026-11-20 13:24:35','2026-10-02 00:00:00',
                $hilton?'ready':'pending',$fields['discovery'],$fields['content'],$fields['manual'],$native,$pin['source_sha256'],
                $hilton?'2026-11-20 01:02:03':null,$fields['source_absent'],$hilton?3:1,'2026-12-01 01:02:03','PRIVATE_LOCAL19_SCHEDULE']);
              unset($pin);
              if($hilton){
                $q=$db->prepare('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?');$q->execute([4172]);
                $historical=$q->fetch(PDO::FETCH_ASSOC);$historical['revision']=1;
                $oldProfile=ident19($row['before']['own']['identity'])+['description'=>'PRIVATE_LOCAL19_HISTORICAL_PROFILE'];
                $historical['profile_json']=j19($oldProfile);$historical['profile_sha256']=hash('sha256',$historical['profile_json']);
                $q=$db->prepare("SELECT namespace,external_key,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources WHERE anytour_hotel_id=? AND namespace<>'profile_enrichment:official_review_v1' ORDER BY namespace,external_key");$q->execute([4172]);
                $sources=$q->fetchAll(PDO::FETCH_ASSOC);need19(count($sources)===2,'fixture_hilton_source_count');
                $snapshot=j19(['profile'=>$historical,'sources'=>$sources]);$issues=j19(['preservedFields'=>[],'originKnown'=>true]);
                $bridge=&$row['before']['bridge'];$bridge['snapshot_sha256']=hash('sha256',$snapshot);
                $bridge['snapshot_profile_sha256']=$historical['profile_sha256'];$bridge['migration_issues_sha256']=hash('sha256',$issues);
                $q=$db->prepare("INSERT INTO local_tv_legacy_links VALUES(4172,80008,?,?,?,'2026-10-02 01:02:03')");
                $q->execute([$snapshot,$bridge['snapshot_sha256'],$issues]);unset($bridge);
              }
            }
            unset($row);
          }
          need19((int)$db->query('SELECT COUNT(*) FROM anytour_hotels')->fetchColumn()===26,'fixture_26_profiles');
          need19((int)$db->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn()===78,'fixture_78_sources');
          need19((int)$db->query('SELECT COUNT(*) FROM local_tv_hotels')->fetchColumn()===20,'fixture_20_targets');
          need19((int)$db->query('SELECT COUNT(*) FROM local_tv_legacy_links')->fetchColumn()===1,'fixture_existing_hilton');
          return $proposal;
        }
        $template=json_decode(file_get_contents($argv[2]),true,64,JSON_THROW_ON_ERROR);
        $manifest=json_decode(file_get_contents($argv[4]),true,64,JSON_THROW_ON_ERROR);$results=[];
        $scenarios=['success','mid_write','manual_drift','profile_drift','source_drift','approved_value_drift','foreign_link',
          'foreign_legacy_owner','missing_target','hilton_manual','hilton_revision','hilton_bridge','hilton_snapshot',
          'hilton_source','hilton_approved_value','write_trigger','commit_ack','rollback_ack','readback'];
        foreach($scenarios as $scenario){
          $db=new Local19FaultPDO(getenv('MATCH_RETAINED_TEST_DSN'),'root',getenv('MATCH_RETAINED_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
          $db->setAttribute(PDO::ATTR_STATEMENT_CLASS,[Local19FaultStatement::class,[$db]]);
          $name='match_local19_'.bin2hex(random_bytes(6));$db->exec('CREATE DATABASE '.$name);$db->exec('USE '.$name);
          try{
            $proposal=seed19($db,$template,$manifest);
            if($scenario==='manual_drift')$db->exec("UPDATE local_tv_hotels SET manual_json='{\"description\":\"existing_manual\"}' WHERE id=2490");
            if($scenario==='profile_drift')$db->exec('UPDATE anytour_hotels SET revision=3 WHERE id=4511');
            if($scenario==='source_drift')$db->exec("UPDATE anytour_hotel_sources SET source_json='{}' WHERE anytour_hotel_id=4511 AND namespace='anytour_local_id'");
            if(in_array($scenario,['approved_value_drift','hilton_approved_value'],true)){
              $id=$scenario==='approved_value_drift'?4511:4172;$group=$id===4511?'pairs':'unchanged_existing_pairs';
              $q=$db->prepare('SELECT profile_json FROM anytour_hotels WHERE id=?');$q->execute([$id]);$profile=json_decode($q->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
              $profile['description'].=' PRIVATE_LOCAL19_CHANGED_APPROVED_VALUE';$raw=j19($profile);$hash=hash('sha256',$raw);
              $q=$db->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=? WHERE id=?');$q->execute([$raw,$hash,$id]);
              // A valid whole-profile hash must not replace immutable per-field authority.
              foreach($proposal[$group] as &$row)if($row['own_id']===$id)$row['before']['own']['profile_sha256']=$hash;unset($row);
            }
            if($scenario==='foreign_link'){
              $q=$db->prepare("INSERT INTO local_tv_legacy_links VALUES(999999,2490,?,?,?,'2026-11-21 00:00:00')");$q->execute(['{}',hash('sha256','{}'),'{}']);
            }
            if($scenario==='foreign_legacy_owner'){
              $q=$db->prepare("INSERT INTO anytour_hotels VALUES(999999,?,?,1,1,'2026-10-01 00:00:00','2026-11-21 00:00:00')");$q->execute(['{}',hash('sha256','{}')]);
              $db->exec("UPDATE anytour_hotel_sources SET anytour_hotel_id=999999 WHERE namespace='legacy_catalog' AND external_key='2490'");
            }
            if($scenario==='missing_target')$db->exec("INSERT INTO local_tv_hotels SELECT 4283,first_seen_at,last_seen_at,pending_since,state,discovery_json,content_json,manual_json,source_json,source_sha256,source_fetched_at,source_absent_json,revision,next_attempt_at,last_error FROM local_tv_hotels WHERE id=2490");
            if($scenario==='hilton_manual')$db->exec("UPDATE local_tv_hotels SET manual_json='{\"description\":\"PRIVATE_LOCAL19_HILTON_MANUAL\"}' WHERE id=80008");
            if($scenario==='hilton_revision')$db->exec('UPDATE local_tv_hotels SET revision=4 WHERE id=80008');
            if($scenario==='hilton_bridge')$db->exec('UPDATE local_tv_legacy_links SET tv_id=2490 WHERE old_local_id=4172');
            if($scenario==='hilton_snapshot'){
              $q=$db->query('SELECT snapshot_json FROM local_tv_legacy_links WHERE old_local_id=4172');$snapshot=json_decode($q->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
              $snapshot['profile']['revision']=2;$raw=j19($snapshot);$q=$db->prepare('UPDATE local_tv_legacy_links SET snapshot_json=?,snapshot_sha256=? WHERE old_local_id=4172');$q->execute([$raw,hash('sha256',$raw)]);
            }
            if($scenario==='hilton_source'){
              $raw='{"id":80009}';$q=$db->prepare('UPDATE local_tv_hotels SET source_json=?,source_sha256=? WHERE id=80008');$q->execute([$raw,hash('sha256',$raw)]);
            }
            if($scenario==='write_trigger')$db->exec("CREATE TRIGGER local19_fixture_noop BEFORE UPDATE ON local_tv_hotels FOR EACH ROW SET NEW.last_error=OLD.last_error");
            $before=hashes19($db);$preserved=preserved19($db);$db->fault=$scenario;$db->armed=true;
            $out=AnyTourLocal19BridgeApplyV1::run($db,$proposal);
            $expectedStates=['success'=>'committed_verified','mid_write'=>'rolled_back','commit_ack'=>'commit_unknown',
              'rollback_ack'=>'rollback_unknown','readback'=>'post_commit_readback_unknown'];
            need19($out['state']===($expectedStates[$scenario]??'rejected_before_write'),
              'terminal_'.$scenario.'_'.$out['state'].'_'.($out['error']['code']??'none'));
            $db->armed=false;need19(!$db->inTransaction(),'open_transaction_'.$scenario);$after=hashes19($db);
            need19($before['anytour_hotels']===$after['anytour_hotels'] && $before['anytour_hotel_sources']===$after['anytour_hotel_sources'],'canonical_changed_'.$scenario);
            need19($preserved===preserved19($db),'hilton_or_excluded_changed_'.$scenario);
            $actuallyCommitted=in_array($scenario,['success','commit_ack','readback'],true);
            if($actuallyCommitted){
              need19((int)$db->query('SELECT COUNT(*) FROM local_tv_legacy_links')->fetchColumn()===20,'new19_plus_hilton_count');
              need19((int)$db->query('SELECT COUNT(*) FROM local_tv_hotels WHERE revision=2')->fetchColumn()===19,'updated19_count');
              need19($db->updates===19 && $db->commits===1,'writer_retried_or_wrong_write_count');
              foreach($proposal['pairs'] as $pair){
                $q=$db->prepare('SELECT * FROM local_tv_hotels WHERE id=?');$q->execute([$pair['tv_id']]);$target=$q->fetch(PDO::FETCH_ASSOC);
                need19($target['manual_json']===$pair['proposed_after']['target']['manual_json_exact_utf8'],'manual_raw_bytes');
                need19($target['state']==='pending' && $target['source_json']===null && $target['source_sha256']===null && $target['source_fetched_at']===null && $target['content_json']==='{}' && $target['source_absent_json']==='[]','native_state_changed');
                need19($target['next_attempt_at']==='2026-12-01 01:02:03' && $target['last_error']==='PRIVATE_LOCAL19_SCHEDULE' && $target['last_seen_at']==='2026-11-20 13:24:35','scheduling_changed');
                $q=$db->prepare('SELECT * FROM local_tv_legacy_links WHERE old_local_id=?');$q->execute([$pair['own_id']]);$link=$q->fetch(PDO::FETCH_ASSOC);
                need19(hash('sha256',$link['snapshot_json'])===$link['snapshot_sha256'],'snapshot_hash');
                $snapshot=json_decode($link['snapshot_json'],true,64,JSON_THROW_ON_ERROR);
                need19(array_keys($snapshot)===['profile','sources'] && count($snapshot['profile'])===5 && count($snapshot['sources'])===3,'snapshot_shape');
                $q=$db->prepare('SELECT id,profile_json,profile_sha256,revision,is_active FROM anytour_hotels WHERE id=?');$q->execute([$pair['own_id']]);
                need19($snapshot['profile']===$q->fetch(PDO::FETCH_ASSOC),'snapshot_original_profile');
                $q=$db->prepare('SELECT namespace,external_key,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at FROM anytour_hotel_sources WHERE anytour_hotel_id=? ORDER BY namespace,external_key');$q->execute([$pair['own_id']]);
                need19($snapshot['sources']===$q->fetchAll(PDO::FETCH_ASSOC),'snapshot_original_sources');
              }
            }else{
              need19($before===$after,'partial_write_survived_'.$scenario);
              if($scenario==='rollback_ack'){
                need19($out['state']==='rollback_unknown' && $db->updates===10 && $db->rollbacks===1,'rollback_unknown_not_exercised');
                foreach(['database_writes','mapping_writes','native_updates','manual_field_writes'] as $key)need19($out[$key]===null,'rollback_unknown_reported_zero');
              }else foreach(['database_writes','mapping_writes','native_updates','manual_field_writes'] as $key)need19($out[$key]===0,'failed_nonzero_counts');
            }
            if($scenario==='success'){
              need19($out['state']==='committed_verified' && $out['readback_verified']===true && $out['database_writes']===38,'success_terminal');
              need19($db->begins===2,'readback_not_separate_transaction');
              need19($out['mapping_writes']===19 && $out['native_updates']===19 && $out['manual_field_writes']===57,'success_counts');
              foreach(['supplier_calls','provider_http_calls','canonical_profile_writes','source_writes','new_target_inserts','accepted_native_bindings'] as $key)need19($out[$key]===0,'forbidden_effect_'.$key);
              $catalog=new LocalTvCatalogV1($db);$direct=$catalog->read(array_column($proposal['pairs'],'tv_id'));
              $old=$catalog->read(array_column($proposal['pairs'],'own_id'),true);
              need19(count($direct['items'])===19 && count($old['items'])===19 && count($old['links'])===19 && $direct['missingIds']===[] && $old['missingIds']===[],'real_reader_counts');
              foreach([$direct,$old] as $read){
                $items=[];foreach($read['items'] as $item)$items[$item['id']]=$item;
                foreach($proposal['pairs'] as $pair){
                  $item=$items[$pair['tv_id']];
                  foreach(['description','primaryImage','images'] as $field)need19($item[$field]===$pair['proposed_after']['target']['manual_json'][$field],'real_reader_value_'.$field);
                  need19($item['revision']===2 && $item['detailsAvailable']===false && $item['contentState']==='pending','real_reader_state');
                  need19($item['manualFields']===['description','primaryImage','images'],'real_reader_manual_fields');
                }
              }
              foreach($old['links'] as $i=>$link)need19($link===['oldLocalId'=>$proposal['pairs'][$i]['own_id'],'tourvisorHotelId'=>$proposal['pairs'][$i]['tv_id']],'real_reader_link');
              $once=hashes19($db);$replay=AnyTourLocal19BridgeApplyV1::run($db,$proposal);
              need19($replay['state']==='rejected_before_write' && $replay['database_writes']===0 && $once===hashes19($db),'second_application_not_refused');
              $out['native_consumer_verified']=true;$out['reapplication_refused']=true;
            }elseif($scenario==='mid_write'){
              need19($db->updates===10 && $out['state']==='rolled_back' && $db->rollbacks===1,'rollback_not_exercised');
            }elseif($scenario==='commit_ack'){
              need19($out['state']==='commit_unknown' && $out['database_writes']===null && $out['mapping_writes']===null && $out['readback_verified']===false,'commit_unknown_misreported');
            }elseif($scenario==='readback'){
              need19($out['state']==='post_commit_readback_unknown' && $out['database_writes']===38 && $out['commit_acknowledged']===true && $out['readback_verified']===false,'postcommit_unknown_misreported');
            }elseif($scenario!=='rollback_ack')need19($out['state']==='rejected_before_write' && $db->updates===0 && $db->commits===0,'drift_not_rejected_before_writes');
            need19(!array_key_exists('approval_ref',$out),'fabricated_php_approval');$results[$scenario]=$out;
          }finally{$db->armed=false;if($db->inTransaction())$db->rollBack();$db->exec('DROP DATABASE '.$name);}
        }
        echo j19($results);'''
        run=subprocess.run(['php','-r',code,str(runner),str(proposal_path),str(consumer/'local-tv-catalog-v1.php'),
                            str(ROOT/m.LOCAL26_MANIFEST)],capture_output=True,text=True,timeout=150)
        self.assertEqual(0,run.returncode,run.stderr)
        results=json.loads(run.stdout)
        self.assertEqual({'success','mid_write','manual_drift','profile_drift','source_drift','approved_value_drift','foreign_link',
                          'foreign_legacy_owner','missing_target','hilton_manual','hilton_revision','hilton_bridge','hilton_snapshot',
                          'hilton_source','hilton_approved_value','write_trigger','commit_ack','rollback_ack','readback'},set(results))
        self.assertTrue(results['success'].pop('native_consumer_verified'))
        self.assertTrue(results['success'].pop('reapplication_refused'))
        for terminal in results.values():m.l19_terminal(terminal)
        self.assertEqual(19,len(results['success']['pairs']))
        for token in ('PRIVATE_LOCAL19_','local19-retained-hilton','private_fixture_absent_field'):
            self.assertNotIn(token,run.stdout)


class Local19ApplyControlTest(unittest.TestCase):
    """Admission, actual consumed-parent binding and honest write receipts."""
    # The sanitized actual read receipt is embedded solely as an immutable test
    # input. Runtime consumes the private parent's original result and reservation.
    PARENT_ZLIB_BASE64 = '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'

    def setUp(self):
        # Synthetic approval exists only in this isolated in-memory fixture.
        # The checked-in production profile is always restored by cleanup.
        self.checked_in_profile=copy.deepcopy(m.LOCAL19_APPLY)
        patcher=mock.patch.dict(m.LOCAL19_APPLY,{
            'execution_admitted':True,
            'approval_ref':'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-999999999999999',
            'approved_proposal_sha256':m.LOCAL19_APPLY['proposal_sha256']})
        patcher.start();self.addCleanup(patcher.stop)
    def test_checked_in_admission_binds_exact_owner_approval_and_proposal(self):
        p=self.checked_in_profile
        approved_sha='02d3df3ecd94d775e58fd3e506cf81032b64179fa3b4db1ff43de9e515ec2192'
        self.assertIs(True,p['execution_admitted'])
        self.assertEqual('https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7088365340',p['approval_ref'])
        self.assertEqual(approved_sha,p['proposal_sha256'])
        self.assertEqual(approved_sha,p['approved_proposal_sha256'])
        self.assertEqual(approved_sha,hashlib.sha256((ROOT/m.LOCAL19_PROPOSAL).read_bytes()).hexdigest())
        self.assertEqual(19,len(p['pairs']))
        with mock.patch.dict(m.LOCAL19_APPLY,p,clear=True):
            m.l19_admission(m.LOCAL19_APPLY)
            _,command,_=self.command()
        self.assertEqual('local19-bridge-apply-v1',command['mode'])
        self.assertEqual('int-andromeda-local19-bridge-apply-20261123-v1',command['operation_id'])

    def test_preparation_fixture_denies_before_bundle_or_remote_mutation(self):
        c=core();m.register_parser(c);p=copy.deepcopy(self.checked_in_profile)
        p.update(execution_admitted=False,approval_ref=None,approved_proposal_sha256=None)
        body=c.PREFIX+' '.join([p['source_sha'],p['mode'],p['operation_id'],p['cohort'],p['parent_operation_id']])
        command=dict(source_sha=p['source_sha'],mode=p['mode'],operation_id=p['operation_id'],
                     cohort=p['cohort'],target_operation_id=p['parent_operation_id'])
        self.assertIs(False,p['execution_admitted']);self.assertIsNone(p['approval_ref'])
        self.assertIsNone(p['approved_proposal_sha256'])
        remote=c.REMOTE
        with mock.patch.dict(m.LOCAL19_APPLY,p,clear=True), \
                mock.patch.object(Path,'read_bytes',side_effect=AssertionError('no file read before approval')):
            for action in (lambda:c.parse_command(body),lambda:m.activate(c,dict(command)),
                           lambda:m.bundle_local19_source(SOURCE)):
                with self.assertRaisesRegex(ValueError,'local19_owner_approval_required'):action()
        self.assertEqual(remote,c.REMOTE)
    def test_partial_approval_or_prior_content_or_local9_approval_does_not_admit(self):
        _,_,body=self.command()
        for change in ({'execution_admitted':False},{'approval_ref':None},
                       {'approval_ref':m.LOCAL9_APPLY['approval_ref']},
                       {'approval_ref':'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7077944229'},
                       {'approval_ref':'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7088077135'},
                       {'approved_proposal_sha256':m.LOCAL9_APPLY['proposal_sha256']}):
            c=core();m.register_parser(c)
            with self.subTest(change=change),mock.patch.dict(m.LOCAL19_APPLY,change), \
                    self.assertRaisesRegex(ValueError,'local19_owner_approval_required'):
                c.parse_command(body)
    def parent(self):
        import base64,zlib
        return json.loads(zlib.decompress(base64.b64decode(self.PARENT_ZLIB_BASE64)))

    def command(self):
        p=m.LOCAL19_APPLY;c=core();m.register_parser(c)
        body=c.PREFIX+' '.join([p['source_sha'],p['mode'],p['operation_id'],p['cohort'],p['parent_operation_id']])
        return c,c.parse_command(body),body

    def terminal(self,state='committed_verified'):
        p=m.LOCAL19_APPLY;committed=state in ('committed_verified','post_commit_readback_unknown')
        verified=state=='committed_verified';unknown=state in ('commit_unknown','rollback_unknown')
        hashes={'canonical_rows_sha256':'1'*64,'target_preserved_rows_sha256':'2'*64,'excluded_rows_sha256':'3'*64}
        counts=dict(zip(('database_writes','mapping_writes','native_updates','manual_field_writes'),
            [None]*4 if unknown else [38,19,19,57] if committed else [0]*4))
        return {'schema_version':'anytour_local19_bridge_apply_v1','operation_id':p['operation_id'],
            'state':state,'completed_at_utc':'2026-11-23T00:00:00+00:00','proposal_sha256':p['proposal_sha256'],
            'no_replay':True,'supplier_calls':0,'provider_http_calls':0,**counts,
            'canonical_profile_writes':0,'source_writes':0,'new_target_inserts':0,'accepted_native_bindings':0,
            'commit_acknowledged':committed,'readback_verified':verified,'before':hashes,
            'after':copy.deepcopy(hashes) if verified else None,
            'checks':{'fresh_parent_match':True,'canonical_unchanged':True if verified else None,
                'target_preserved':True if verified else None,'excluded_unchanged':True if verified else None,
                'direct_tv_sql':verified,'own_to_tv_sql':verified,'snapshots_verified':verified},
            'pairs':[{'own_id':own,'tv_id':tv,'revision_from':1,'revision_to':2,
                'manual_fields':['description','primaryImage','images'],'manual_sha256':manual,
                'snapshot_sha256':hashlib.sha256(str(own).encode()).hexdigest(),
                'direct_tv_sql':verified,'own_to_tv_sql':verified} for own,tv,manual in p['pairs']] if verified else [],
            'error':None if verified else {'code':'local19_fixture_failure','message_sha256':'e'*64}}

    def fixture(self,root,terminal=None):
        p=m.LOCAL19_APPLY;c,command,body=self.command()
        private=root/'private';target=private/p['parent_operation_id'];target.mkdir(parents=True)
        op=private/p['operation_id'];op.mkdir();stage=op/'source';stage.mkdir()
        project=root/'project';(project/'data').mkdir(parents=True)
        (project/'config.php').write_text('PRIVATE_CONFIGURATION_SENTINEL')
        (project/'data/db-v1.php').write_bytes((SOURCE/'v2/data/db-v1.php').read_bytes())
        (target/'result.json').write_bytes(m.l11_json(self.parent()))
        (target/'reservation.json').write_text(json.dumps({'operation_id':p['parent_operation_id'],
            'source_sha':p['parent_source_sha'],'mode':'match-retained-current','reserved_at':1795344304}))
        def fail(reason):raise ValueError(reason)
        space={'LOCAL19_APPLY':p,'LOCAL19_HASHES':m.LOCAL19_HASHES,'LOCAL19_RUNNER':m.LOCAL19_RUNNER,
            'LOCAL11_IDENTITY':m.LOCAL11_IDENTITY,'LOCAL11_HASHES':m.LOCAL11_HASHES,
            'LOCAL26_IDENTITY':m.LOCAL26_IDENTITY,'LOCAL26_HASHES':m.LOCAL26_HASHES,
            'completed_local26_identity':m.completed_local26_identity,'private':private,'op':op,'project':project,
            'source':p['source_sha'],'operation':p['operation_id'],'payload':dict(command,match_retained_control_sha=CONTROL),
            'mode':p['mode'],'json':json,'hashlib':hashlib,'os':os,'re':__import__('re'),'subprocess':subprocess,'fail':fail,
            'result':{'supplier_calls':0,'database_writes':0,'mapping_writes':0},
            'safe_file':lambda path,bound:path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=bound}
        exec(m.LOCAL11_VALIDATOR+m.LOCAL26_VALIDATOR+m.LOCAL19_VALIDATOR+m.LOCAL19_HANDLER,space)
        terminal=terminal or self.terminal()
        def child(args,**kwargs):
            self.assertEqual(str(stage/m.LOCAL19_RUNNER),args[-1]);self.assertEqual(project,kwargs['cwd'])
            self.assertIn('allow_url_fopen=0',args)
            self.assertIn('disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen',args)
            self.assertLessEqual(set(kwargs['env']),{'HOME','PATH','LANG','LC_ALL','MATCH_RETAINED_DIR','MATCH_RETAINED_SOURCE_ROOT','MATCH_LOCAL19_EXECUTION_ADMITTED'})
            self.assertEqual('1',kwargs['env']['MATCH_LOCAL19_EXECUTION_ADMITTED'])
            self.assertEqual(120,kwargs['timeout']);self.assertIs(False,kwargs['text'])
            reservation=json.loads((op/'local19-child-reservation.json').read_text())
            self.assertEqual(p['approval_ref'],reservation['approval_ref']);self.assertTrue(reservation['no_replay'])
            return subprocess.CompletedProcess(args,0 if terminal['state']=='committed_verified' else 1,m.l11_json(terminal),b'')
        return command,space,child,target,op,stage

    def result(self,command,evidence):
        before={k:'a'*64 for k in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        terminal=evidence['data']['terminal']
        return {'schema_version':1,**{k:command[k] for k in ('operation_id','source_sha','mode')},
            'status':'complete','production_unchanged':True,'supplier_calls':0,
            'database_writes':terminal['database_writes'],'mapping_writes':terminal['mapping_writes'],
            'booking_calls':0,'lead_calls':0,'production_before':before,'production_after':copy.deepcopy(before),
            'match_retained':evidence}

    def rehash(self,result):
        result['match_retained']['evidence_sha256']=hashlib.sha256(m.l11_json(result['match_retained']['data'])).hexdigest()

    def test_dedicated_exact_write_command_and_frozen_current_read_command(self):
        import base64,zlib
        c,command,body=self.command();p=m.LOCAL19_APPLY
        for bad in (body+' extra',body.replace(p['source_sha'],SHA),body.replace(p['mode'],'match-retained-current'),
            body.replace(p['operation_id'],p['operation_id']+'-replay'),body.replace(p['cohort'],'common4-retained'),
            body.replace(p['parent_operation_id'],m.LOCAL11_IDENTITY['parent_operation_id'])):
            with self.subTest(command=bad),self.assertRaises(ValueError):c.parse_command(bad)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(body)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(c,command)
        ast.parse(c.REMOTE)
        self.assertIn('def apply_local19_bridge(stage):',c.REMOTE);self.assertNotIn('def run_match_retained(stage):',c.REMOTE)
        self.assertIn("result['database_writes']=result['mapping_writes']=None",c.REMOTE)
        self.assertEqual(2,c.REMOTE.count("if mode not in ('local19-bridge-apply-v1','reconcile',"))
        self.assertIn("globals().get('local19_reserved') is True",c.REMOTE)
        self.assertIn('len(members)!=4',c.REMOTE)
        encoded=base64.b64encode(zlib.compress(c.REMOTE.encode(),9)).decode()
        self.assertLessEqual(len(("python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'").encode()),65536)
        old,read_command=Local11IdentityTest().command()
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL}):m.activate(old,read_command)
        self.assertEqual('b9bdbe96297307e3a9919c6c075beff720509f4099e26b89bf2a241e6dcaa1ab',hashlib.sha256(old.REMOTE.encode()).hexdigest())

    def test_bundle_uses_main_writer_and_immutable_proposal_not_feature_copies(self):
        with tempfile.TemporaryDirectory() as td:
            source=Path(td)/'source';control=Path(td)/'control'
            for rel in m.LOCAL19_HASHES:
                path=source/rel;path.parent.mkdir(parents=True,exist_ok=True)
                path.write_bytes((SOURCE/rel).read_bytes() if rel=='v2/data/db-v1.php' else b'FEATURE CANNOT SELECT THE WRITER OR PROPOSAL')
                if rel!='v2/data/db-v1.php':
                    path=control/rel;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes((ROOT/rel).read_bytes())
            bundle,hashes=m.bundle_local19_source(source)
            with tarfile.open(fileobj=io.BytesIO(bundle),mode='r:gz') as archive:
                self.assertEqual({*m.LOCAL19_HASHES,'manifest.json'},set(archive.getnames()))
                self.assertEqual((ROOT/m.LOCAL19_PROPOSAL).read_bytes(),archive.extractfile(m.LOCAL19_PROPOSAL).read())
                self.assertTrue(all(item.mode==0o600 for item in archive.getmembers()))
            self.assertEqual(m.LOCAL19_HASHES,hashes)
            (control/m.LOCAL19_PROPOSAL).write_bytes((control/m.LOCAL19_PROPOSAL).read_bytes()+b'\n')
            with mock.patch.object(m,'__file__',str(control/'scripts/deploy/controller.py')):
                with self.assertRaisesRegex(ValueError,'local19_source_drift'):m.bundle_local19_source(source)

    def test_actual_parent_receipt_pin_rejects_even_rehashed_substitution(self):
        prior=self.parent();m.l19_parent(prior)
        self.assertEqual(m.LOCAL19_APPLY['parent_evidence_sha256'],prior['match_retained']['evidence_sha256'])
        for mutate in (lambda d:d.update(control_source_sha=CONTROL),lambda d:d.update(current_raw_sha256='9'*64),
            lambda d:d['rows'][0]['own'].update(profile_sha256='e'*64)):
            changed=copy.deepcopy(prior);mutate(changed['match_retained']['data']);self.rehash(changed)
            with self.assertRaises(ValueError):m.l19_parent(changed)

    @unittest.skipUnless(__import__('shutil').which('php'),'native PHP required')
    def test_cli_rejects_reencoded_or_repinned_proposal_before_loading_private_configuration(self):
        proposal=json.loads((ROOT/m.LOCAL19_PROPOSAL).read_bytes())
        proposals=[json.dumps(proposal,ensure_ascii=False).encode()]
        changed=copy.deepcopy(proposal);changed['pairs'][0]['before']['own']['profile_sha256']='1'*64
        proposals.append(json.dumps(changed,ensure_ascii=False).encode())
        for raw in proposals:
            with self.subTest(raw_sha256=hashlib.sha256(raw).hexdigest()),tempfile.TemporaryDirectory() as td:
                home=Path(td);project=home/'www/anytoour.ru';project.mkdir(parents=True)
                op=home/'.anytoour-int-executor'/m.LOCAL19_APPLY['operation_id'];stage=op/'source'
                for rel in m.LOCAL19_HASHES:
                    path=stage/rel;path.parent.mkdir(parents=True,exist_ok=True)
                    path.write_bytes((SOURCE/rel).read_bytes() if rel=='v2/data/db-v1.php' else (ROOT/rel).read_bytes())
                (stage/m.LOCAL19_PROPOSAL).write_bytes(raw)
                marker=home/'configuration_loaded'
                (project/'config.php').write_text('<?php file_put_contents('+json.dumps(str(marker))+',"unexpected");')
                env={k:os.environ[k] for k in ('PATH','LANG','LC_ALL') if k in os.environ}
                env.update(HOME=str(home),MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage),MATCH_LOCAL19_EXECUTION_ADMITTED='1')
                call=subprocess.run(['php',str(stage/m.LOCAL19_RUNNER)],cwd=project,env=env,capture_output=True,text=True,timeout=20)
                self.assertEqual(1,call.returncode,call.stderr);self.assertEqual('',call.stderr)
                terminal=json.loads(call.stdout);m.l19_terminal(terminal)
                self.assertEqual('rejected_before_write',terminal['state'])
                self.assertEqual('local19_proposal_hash',terminal['error']['code'])
                self.assertEqual(0,terminal['database_writes']);self.assertFalse(marker.exists())

    def test_success_preserves_actual_parent_and_is_not_replayable(self):
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage=self.fixture(Path(td))
            before={path.name:path.read_bytes() for path in target.iterdir()}
            with mock.patch.object(subprocess,'run',side_effect=child) as call:
                evidence=space['apply_local19_bridge'](stage)
                with self.assertRaises(FileExistsError):space['apply_local19_bridge'](stage)
            self.assertEqual(1,call.call_count);self.assertEqual(before,{path.name:path.read_bytes() for path in target.iterdir()})
            self.assertEqual((38,19),(space['result']['database_writes'],space['result']['mapping_writes']))
            result=self.result(command,evidence);self.assertTrue(m.completed_result(command,result,CONTROL))
            self.assertNotIn('PRIVATE_CONFIGURATION_SENTINEL',json.dumps(result))
            self.assertEqual(evidence['data']['raw_sha256'],hashlib.sha256((op/'local19-raw.json').read_bytes()).hexdigest())
            for mutate in (lambda d:d['terminal'].update(database_writes=True),
                lambda d:d['terminal'].update(provider_http_calls=1),lambda d:d['terminal'].update(accepted_native_bindings=9),
                lambda d:d['terminal']['pairs'][0].update(manual_sha256='f'*64),
                lambda d:d['terminal']['pairs'][0].update(own_id=4516),
                lambda d:d['terminal']['after'].update(excluded_rows_sha256='f'*64),
                lambda d:d.update(approval_ref='https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7063885208')):
                changed=copy.deepcopy(result);mutate(changed['match_retained']['data']);self.rehash(changed)
                self.assertFalse(m.completed_result(command,changed,CONTROL))

    def test_failed_child_receipts_and_unknown_commit_counts_are_preserved(self):
        for state in ('rejected_before_write','rolled_back','commit_unknown','rollback_unknown','post_commit_readback_unknown'):
            with self.subTest(state=state),tempfile.TemporaryDirectory() as td:
                terminal=self.terminal(state);command,space,child,target,op,stage=self.fixture(Path(td),terminal)
                with mock.patch.object(subprocess,'run',side_effect=child):evidence=space['apply_local19_bridge'](stage)
                self.assertEqual(terminal,evidence['data']['terminal'])
                self.assertEqual(terminal['database_writes'],space['result']['database_writes'])
                self.assertEqual(terminal['mapping_writes'],space['result']['mapping_writes'])
                self.assertEqual(1,evidence['data']['child_exit_code'])
                self.assertFalse(m.completed_result(command,self.result(command,evidence),CONTROL))
                self.assertEqual(terminal,json.loads((op/'local19-raw.json').read_bytes()))
                if state in ('commit_unknown','rollback_unknown'):
                    bad=copy.deepcopy(terminal);bad.update(database_writes=0,mapping_writes=0,native_updates=0,manual_field_writes=0)
                    with self.assertRaisesRegex(ValueError,'local19_write_counts'):m.l19_terminal(bad)

    def test_timeout_and_postwrite_guard_failure_cannot_be_reported_as_zero(self):
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage=self.fixture(Path(td))
            timeout=subprocess.TimeoutExpired('php',120,output=b'{"partial":')
            with mock.patch.object(subprocess,'run',side_effect=timeout),self.assertRaisesRegex(ValueError,'timeout_no_replay'):
                space['apply_local19_bridge'](stage)
            self.assertIsNone(space['result']['database_writes']);self.assertIsNone(space['result']['mapping_writes'])
            self.assertEqual(b'{"partial":',(op/'local19-raw.json').read_bytes())
        with tempfile.TemporaryDirectory() as td:
            command,space,child,target,op,stage=self.fixture(Path(td))
            def drift(*args,**kwargs):
                response=child(*args,**kwargs);(target/'reservation.json').write_text('{}');return response
            with mock.patch.object(subprocess,'run',side_effect=drift),self.assertRaisesRegex(ValueError,'local19_protected_inputs'):
                space['apply_local19_bridge'](stage)
            self.assertEqual(38,space['result']['database_writes']);self.assertEqual(19,space['result']['mapping_writes'])
            self.assertEqual('committed_verified',space['result']['match_retained']['data']['terminal']['state'])
            self.assertFalse(space['result']['match_retained']['data']['protected_inputs_unchanged'])


if __name__=='__main__':unittest.main()


