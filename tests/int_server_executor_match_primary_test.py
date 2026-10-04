#!/usr/bin/env python3
from __future__ import annotations
import ast
import base64
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import time
import types
import unittest
import zlib
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[1]
SOURCE='a'*40
CONTROL='b'*40
OP='int-andromeda-match-primary-samo3-20260929-v1'

def load(name,relative):
    spec=importlib.util.spec_from_file_location(name,ROOT/relative)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module

registration=load('primary_registration','scripts/deploy/int_server_executor_match_primary.py')

def fresh_core():
    return load('primary_test_core','scripts/deploy/int_server_executor.py')

def command(core):
    return core.PREFIX+SOURCE+' '+registration.MODE+' '+OP+' '+registration.BATCH

class PrimaryRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();self.original=self.core.parse_command
        self.old_remote=self.core.REMOTE;self.old_files=list(self.core.FIXED)
        registration.register_parser(self.core)

    def test_fixed_command_and_old_mode_parity(self):
        new=self.core.parse_command(command(self.core))
        self.assertEqual(new,dict(source_sha=SOURCE,mode=registration.MODE,operation_id=OP,batch=registration.BATCH,maximum_writes=3,provider_http_calls=0))
        old=['match-coverage','match-coverage-v2','match-samo-live30-persistence-readback','match-common4-mass-current','match-common4-resume-readback','program-fuel-readback','install-runtime']
        for mode in old:
            body=self.core.PREFIX+SOURCE+' '+mode+' int-andromeda-fixture-old-mode-v1'
            self.assertEqual(self.core.parse_command(body),self.original(body))
        for tail in ['wrong-batch','samo3-20260929 4','samo3-20260929 /tmp/file','samo3-20260929;echo bad']:
            with self.assertRaises(ValueError):self.core.parse_command(command(self.core).rsplit(' ',1)[0]+' '+tail)
        with self.assertRaises(ValueError):self.core.parse_command(command(self.core).replace(OP,'int-anex-match-primary-samo3-20260929-v1'))
        with self.assertRaises(ValueError):self.core.parse_command(command(self.core).replace(SOURCE,'bad'))

    def test_authorization_is_still_checked_event(self):
        body=command(self.core)
        event={'issue':{'number':self.core.ISSUE},'comment':{'id':123,'body':body,'user':{'id':226193297},'author_association':'OWNER'}}
        def api(path,token):
            if path=='/issues/comments/123':return copy.deepcopy(event['comment'])
            if path=='/git/ref/heads/main':return {'object':{'sha':CONTROL}}
            if path=='/git/ref/heads/'+self.core.FEATURE:return {'object':{'sha':SOURCE}}
            raise AssertionError(path)
        with patch.object(self.core,'api_get',side_effect=api):
            self.assertEqual(self.core.checked_event('fixture',event,CONTROL)['maximum_writes'],3)
            for mutate in [lambda e:e['issue'].update(number=2530),lambda e:e['issue'].update(pull_request={'url':'fixture-pr'}),lambda e:e['comment']['user'].update(id=1),lambda e:e['comment'].update(author_association='NONE')]:
                bad=copy.deepcopy(event);mutate(bad)
                with self.assertRaises(ValueError):self.core.checked_event('fixture',bad,CONTROL)
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,'c'*40)
        with patch.object(self.core,'api_get',side_effect=lambda p,t:{'body':'changed','user':{'id':226193297}}):
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,CONTROL)
        def wrong_feature(path,token):
            value=api(path,token)
            if path.endswith(self.core.FEATURE):value={'object':{'sha':'c'*40}}
            return value
        with patch.object(self.core,'api_get',side_effect=wrong_feature):
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,CONTROL)
        self.assertEqual(self.core.REMOTE,self.old_remote)

    def test_new_mode_never_falls_into_generic_collector(self):
        registration.activate(self.core,self.core.parse_command(command(self.core)))
        tree=ast.parse(self.core.REMOTE)
        guards=[]
        for node in ast.walk(tree):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                guards.append(node.test)
        self.assertEqual(len(guards),2)
        for guard in guards:
            self.assertFalse(eval(compile(ast.Expression(guard),'<guard>','eval'),{},dict(mode=registration.MODE)))
        self.assertEqual(self.core.FIXED,self.old_files+list(registration.SOURCE_FILES))
        self.assertEqual(self.core.REMOTE.count('def run_match_primary_candidate(stage):'),1)
        old=self.old_remote.replace("    if mode not in ('reconcile',","    if mode not in ('match-primary-candidate','reconcile',")
        expected=old.replace('def run_match942(stage, mode, offset, limit):\n',registration.REMOTE_HANDLER+'def run_match942(stage, mode, offset, limit):\n',1).replace("    if mode=='match-tv942-write':\n",registration.REMOTE_DISPATCH+"    if mode=='match-tv942-write':\n",1)
        self.assertEqual(self.core.REMOTE,expected)

    def test_old_activation_noop_and_registration_drift_closed(self):
        registration.activate(self.core,dict(mode='match-coverage'))
        self.assertEqual(self.core.REMOTE,self.old_remote);self.assertEqual(self.core.FIXED,self.old_files)
        self.core.REMOTE=self.old_remote.replace('def run_match942(stage, mode, offset, limit):','def unexpected_definition():')
        with self.assertRaises(ValueError):registration.activate(self.core,self.core.parse_command(command(self.core)))
        self.assertEqual(self.core.FIXED,self.old_files)

    def test_existing_entrypoint_uses_stock_authorization_first(self):
        entry=load('primary_stock_entry','scripts/deploy/int_server_executor_anex_secret_transport.py')
        self.assertNotIn(registration.MODE,entry.DIRECT_ANEX_MODES)
        self.assertNotIn(registration.MODE,entry.SUPPLIER_SLOT_MODES)
        source=(ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py').read_text()
        self.assertLess(source.index('command = core.checked_event'),source.index('match_primary.activate(core, command)'))
        self.assertIn('result = core.execute(command, Path(args.source_root))',source)
        self.assertEqual(entry.core.execute.__code__.co_code,self.core.execute.__code__.co_code)

    def handler_namespace(self,home):
        ns=dict(home=home,project=home/'www/anytoour.ru',operation=OP,source=SOURCE,
                payload=dict(batch=registration.BATCH,maximum_writes=3,provider_http_calls=0),
                os=os,re=re,json=json,hashlib=hashlib,time=time,subprocess=subprocess)
        tree=ast.parse(self.old_remote)
        nodes=[n for n in tree.body if isinstance(n,ast.FunctionDef) and n.name in ('fail','safe_file','safe_json')]
        exec(compile(ast.Module(body=nodes,type_ignores=[]),'<stock_helpers>','exec'),ns)
        exec(registration.REMOTE_HANDLER,ns)
        ns['project'].mkdir(parents=True)
        return ns

    def test_private_receipt_and_batch_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            home=Path(tmp);ns=self.handler_namespace(home);stage=home/'stage'
            runner=stage/registration.SOURCE_FILES[-1];runner.parent.mkdir(parents=True);runner.write_text('<?php // fixture only')
            def child(argv,**kwargs):
                self.assertEqual(argv,['php',str(runner),'--execute'])
                self.assertEqual(set(kwargs['env'])-{'PATH','HOME','LANG','LC_ALL'},{'ANYTOUR_ROOT','MATCH_OPERATION_DIR','MATCH_SOURCE_SHA'})
                child_dir=Path(kwargs['env']['MATCH_OPERATION_DIR']);res=json.loads((child_dir/'reservation.json').read_text())
                self.assertEqual(res['maximum_writes'],3)
                data=dict(operation=ns['operation'],source_sha=SOURCE,batch=registration.BATCH,requested_candidates=3,current_candidates_evaluated=3,
                          provider_http_calls=0,no_replay=True,state='completed_no_new_writes',database_writes=0,mapping_writes=0,readback_verified=True,
                          rows=[],already=[],held=[dict(catalog_id=c,local_hotel_id=i,reasons=['fixture_hold']) for c,i in [('9501',420),('2000034238',16944),('3126',42903)]])
                raw=json.dumps(data).encode();(child_dir/'result.json').write_bytes(raw)
                receipt={k:data[k] for k in ('operation','source_sha','batch','provider_http_calls','no_replay','state','database_writes','mapping_writes','readback_verified')}
                receipt['result_sha256']=hashlib.sha256(raw).hexdigest();(child_dir/'receipt.json').write_text(json.dumps(receipt))
                return types.SimpleNamespace(returncode=0,stdout='',stderr='')
            with patch.object(subprocess,'run',side_effect=child) as run:
                result=ns['run_match_primary_candidate'](stage)
                self.assertTrue(result['successful']);self.assertEqual(result['summary']['mapping_writes'],0)
                ns['operation']=OP.replace('-v1','-v2')
                with self.assertRaises(FileExistsError):ns['run_match_primary_candidate'](stage)
                self.assertEqual(run.call_count,1)

    def test_payload_widening_never_starts_php(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns=self.handler_namespace(Path(tmp));ns['payload']['maximum_writes']=4
            with patch.object(subprocess,'run') as run:
                with self.assertRaises(RuntimeError):ns['run_match_primary_candidate'](Path(tmp)/'stage')
                run.assert_not_called()

    def proof_command(self):
        return self.core.PREFIX+SOURCE+' '+registration.READBACK_MODE+' '+OP.replace('samo3-20260929','proof-readback-20261001')+' '+registration.BATCH

    def proof_fixture(self):
        specs=[(420,'9501','operator_342','24402','hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4','2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564'),
               (16944,'2000034238','operator_315','211585','hotel-match-residual2041-search30-common4-1971-20260921-v3','76c740c4efbb95a2c2c44fd7fe69ecea30b5091c0e17dfec2bb4cf30da75cea2'),
               (42903,'3126','operator_315','849821','hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4','2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564')]
        return dict(state='completed_saved_proof_audit',batch=registration.BATCH,provider_http_calls=0,database_writes=0,mapping_writes=0,safe_to_write_now=False,
                    rows=[dict(zip(['tv_hotel_id','catalog_id','supplier_namespace','native_id','source_operation','source_result_sha256'],s),
                               safe_to_write_now=False,state='proof_hold',failures=['verified_edge_missing'],proof_matches=0,source_targets=[s[0]],target_natives=[s[3]],
                               edge_checks=[dict(json_pointer='/edges/0',verified=False,failed_fields=['operator_link_host'])],invalid_edge_rows=0,edge_checks_omitted=0) for s in specs])

    def proof_namespace(self,home):
        ns=self.handler_namespace(home)
        ns['payload']=dict(batch=registration.BATCH,maximum_writes=0,provider_http_calls=0)
        ns['operation']=OP.replace('samo3-20260929','proof-readback-20261001')
        exec(registration.REMOTE_PROOF_HANDLER,ns)
        root=home/'.anytoour-match/operations';root.mkdir(parents=True)
        (home/'.anytoour-match/primary-batch-samo3-20260929.json').write_text('immutable-marker')
        (root/'retained-proof.json').write_text('immutable-proof')
        stage=home/'stage';runner=stage/registration.PROOF_SOURCE_FILES[-1]
        runner.parent.mkdir(parents=True);runner.write_text('<?php // fixture')
        return ns,stage,root,runner

    def test_proof_command_zero_authority_and_no_collector(self):
        command=self.core.parse_command(self.proof_command())
        self.assertEqual(command['maximum_writes'],0)
        bad=dict(command,maximum_writes=3)
        with self.assertRaises(ValueError):registration.activate(self.core,bad)
        self.assertEqual(self.core.REMOTE,self.old_remote)
        registration.activate(self.core,command)
        self.assertNotIn('def run_match_primary_candidate(stage):',self.core.REMOTE)
        self.assertEqual(self.core.FIXED,self.old_files+list(registration.PROOF_SOURCE_FILES))
        guards=[n.test for n in ast.walk(ast.parse(self.core.REMOTE)) if isinstance(n,ast.If) and isinstance(n.test,ast.Compare)
                and isinstance(n.test.left,ast.Name) and n.test.left.id=='mode' and isinstance(n.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:self.assertFalse(eval(compile(ast.Expression(guard),'<guard>','eval'),{},dict(mode=registration.READBACK_MODE)))
        entry=load('proof_stock_entry','scripts/deploy/int_server_executor_anex_secret_transport.py')
        self.assertNotIn(registration.READBACK_MODE,entry.DIRECT_ANEX_MODES)
        self.assertNotIn(registration.READBACK_MODE,entry.SUPPLIER_SLOT_MODES)
        for extra in [' /tmp/path',' supplier=1',' 4']:
            with self.assertRaises(ValueError):self.core.parse_command(self.proof_command()+extra)

    def test_proof_read_does_not_create_child_or_touch_batch(self):
        with tempfile.TemporaryDirectory() as tmp:
            home=Path(tmp);ns,stage,root,runner=self.proof_namespace(home)
            def snapshot():return {str(p.relative_to(home)):p.read_bytes() for p in (home/'.anytoour-match').rglob('*') if p.is_file()}
            before=snapshot();data=self.proof_fixture()
            def child(argv,**kwargs):
                self.assertEqual(argv,['php','-d','display_errors=0','-d','log_errors=0',str(runner),'--read-saved',str(root)])
                self.assertLessEqual(set(kwargs['env']),{'PATH','HOME','LANG','LC_ALL'})
                self.assertNotIn('MATCH_OPERATION_DIR',kwargs['env'])
                return types.SimpleNamespace(returncode=0,stdout=json.dumps(data),stderr='')
            with patch.dict(os.environ,{'ANEX_API_TOKEN':'fixture-secret','DB_PASSWORD':'fixture-secret'}),patch.object(subprocess,'run',side_effect=child) as run:
                self.assertEqual(ns['run_match_primary_proof_readback'](stage),data)
                self.assertEqual(run.call_count,1)
            self.assertEqual(snapshot(),before)
            self.assertFalse((root/ns['operation']).exists())

    def test_proof_rejects_widened_or_secret_projection(self):
        mutations=[lambda d:d.update(mapping_writes=1),lambda d:d.update(database_writes=True),lambda d:d.update(provider_http_calls=1),
                   lambda d:d['rows'].append(copy.deepcopy(d['rows'][0])),lambda d:d['rows'][0].update(tv_hotel_id=421),
                   lambda d:d['rows'][0].update(native_id='999'),lambda d:d['rows'][0].update(source_result_sha256='a'*64),
                   lambda d:d['rows'][0].update(operatorLink='fixture-secret'),lambda d:d['rows'][0]['edge_checks'][0].update(raw='fixture-secret'),
                   lambda d:d['rows'][0]['edge_checks'][0].update(json_pointer='fixture-secret'),lambda d:d['rows'][0].update(safe_to_write_now=True)]
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.proof_namespace(Path(tmp))
            for mutate in mutations:
                data=self.proof_fixture();mutate(data)
                with patch.object(subprocess,'run',return_value=types.SimpleNamespace(returncode=0,stdout=json.dumps(data),stderr='')):
                    with self.assertRaises(RuntimeError):ns['run_match_primary_proof_readback'](stage)
            ns['payload']['maximum_writes']=3
            with patch.object(subprocess,'run') as run:
                with self.assertRaises(RuntimeError):ns['run_match_primary_proof_readback'](stage)
                run.assert_not_called()

    def test_proof_missing_inputs_are_a_read_result_not_write_authority(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.proof_namespace(Path(tmp));data=self.proof_fixture()
            for row in data['rows']:
                for key in ('proof_matches','source_targets','target_natives','edge_checks','invalid_edge_rows','edge_checks_omitted'):del row[key]
                row.update(state='producer_unavailable',failures=['retained_file'])
            with patch.object(subprocess,'run',return_value=types.SimpleNamespace(returncode=0,stdout=json.dumps(data),stderr='')):
                self.assertEqual(ns['run_match_primary_proof_readback'](stage),data)
            root.rename(root.with_name('retired'));root.symlink_to(root.with_name('retired'),target_is_directory=True)
            with patch.object(subprocess,'run') as run:
                with self.assertRaises(RuntimeError):ns['run_match_primary_proof_readback'](stage)
                run.assert_not_called()

    def test_origin_inventory_is_bounded_and_sanitized(self):
        data=self.proof_fixture()
        ref=dict(source_operation='hotel-match-fixture',file='tv-edge-420-43.json',sha256='a'*64,json_pointer='',verified=True,failed_fields=[])
        data['origin_lookup']=dict(state='completed_bounded_inventory',files_read=1,bytes_read=256,skipped_large_files=0,invalid_files=0,
                                   rows=[dict(tv_hotel_id=i,references=[ref] if i==420 else []) for i in (420,16944,42903)])
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.proof_namespace(Path(tmp))
            with patch.object(subprocess,'run',return_value=types.SimpleNamespace(returncode=0,stdout=json.dumps(data),stderr='')):
                self.assertEqual(ns['run_match_primary_proof_readback'](stage),data)
            for mutate in [lambda d:d['origin_lookup'].update(bytes_read=536870913),
                           lambda d:d['origin_lookup']['rows'][0]['references'][0].update(raw='fixture-secret'),
                           lambda d:d['origin_lookup']['rows'][0]['references'][0].update(file='../result.json'),
                           lambda d:d['origin_lookup']['rows'][0].update(tv_hotel_id=421)]:
                bad=copy.deepcopy(data);mutate(bad)
                with patch.object(subprocess,'run',return_value=types.SimpleNamespace(returncode=0,stdout=json.dumps(bad),stderr='')):
                    with self.assertRaises(RuntimeError):ns['run_match_primary_proof_readback'](stage)

class Native110RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();self.original_remote=self.core.REMOTE
        registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.NATIVE_MODE+' '+registration.NATIVE_OPERATION+' '+registration.NATIVE_BATCH

    def test_fixed_manifest_command_rejects_generic_scope(self):
        parsed=self.core.parse_command(self.body)
        self.assertEqual(parsed,dict(source_sha=SOURCE,mode=registration.NATIVE_MODE,operation_id=registration.NATIVE_OPERATION,
                                     batch=registration.NATIVE_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in (self.body+' 1',self.body.replace(registration.NATIVE_BATCH,registration.BATCH),
                     self.body.replace(registration.NATIVE_OPERATION,registration.NATIVE_OPERATION+'-retry'),
                     self.body.replace(SOURCE,'bad')):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        for field,value in [('maximum_writes',1),('provider_http_calls',1),('batch',registration.BATCH)]:
            bad=copy.deepcopy(parsed);bad[field]=value
            with self.assertRaises(ValueError):registration.activate(self.core,bad)

    def test_existing_collectors_and_writer_are_not_called(self):
        registration.activate(self.core,self.core.parse_command(self.body))
        tree=ast.parse(self.core.REMOTE)
        guards=[n.test for n in ast.walk(tree) if isinstance(n,ast.If) and isinstance(n.test,ast.Compare)
                and isinstance(n.test.left,ast.Name) and n.test.left.id=='mode' and isinstance(n.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:
            self.assertFalse(eval(compile(ast.Expression(guard),'<guard>','eval'),{},dict(mode=registration.NATIVE_MODE)))
        self.assertNotIn('def run_match_primary_candidate(stage):',self.core.REMOTE)
        self.assertNotIn('primary-batch-samo3-20260929.json',self.core.REMOTE)
        self.assertTrue(set(registration.NATIVE_SOURCE_FILES).issubset(self.core.FIXED))
        entry=load('native110_stock_entry','scripts/deploy/int_server_executor_anex_secret_transport.py')
        self.assertNotIn(registration.NATIVE_MODE,entry.DIRECT_ANEX_MODES)
        self.assertNotIn(registration.NATIVE_MODE,entry.SUPPLIER_SLOT_MODES)

    def namespace(self,tmp):
        home=Path(tmp);root=home/'.anytoour-match/operations';root.mkdir(parents=True)
        project=home/'www/anytoour.ru';project.mkdir(parents=True)
        stage=home/'stage';runner=stage/'scripts/diagnostics/hotel_match_native110_current_v1.php'
        runner.parent.mkdir(parents=True);runner.write_text('<?php // fixture only')
        ns=dict(home=home,project=project,operation=registration.NATIVE_OPERATION,source=SOURCE,
                payload=dict(batch=registration.NATIVE_BATCH,maximum_writes=0,provider_http_calls=0),
                os=os,re=re,json=json,hashlib=hashlib,time=time,subprocess=subprocess)
        helpers=[n for n in ast.parse(self.original_remote).body if isinstance(n,ast.FunctionDef) and n.name in ('fail','safe_file','safe_json')]
        exec(compile(ast.Module(body=helpers,type_ignores=[]),'<stock_helpers>','exec'),ns)
        exec(registration.REMOTE_NATIVE_HANDLER,ns)
        return ns,stage,root,runner

    def response(self,kwargs,mutate=None):
        child=Path(kwargs['env']['MATCH_OPERATION_DIR']);manifest=b'{"fixture":"private full review"}\n'
        (child/'native110-current-manifest.json').write_bytes(manifest)
        data=dict(state='completed_native110_current_review',operation=registration.NATIVE_OPERATION,source_sha=SOURCE,
                  batch=registration.NATIVE_BATCH,manifest_sha256=hashlib.sha256(manifest).hexdigest(),
                  sources_requested=110,sources_examined=109,protected_skipped=1,current_rows_returned=110,
                  raw_verified_facts=107,raw_files_read=117,raw_bytes_read=100000,native_facts_examined=3262,
                  provider_http_calls=0,database_writes=0,mapping_writes=0,safe_to_write_now=False,
                  no_replay=True,acceptance_policy_changed=False,
                  review_rows=[dict(catalog_id=str(i),state='current_review_observed',safe_to_write_now=False,
                    holds=[],catalog_digest_matches_saved=True,evidence_digest_matches_saved=True,
                    source_history_id_matches=True,native_checks=[],operator_checks=[],targets=[],tv_checks=[]) for i in range(1,110)]
                    +[dict(catalog_id='2000086118',state='protected_not_examined',safe_to_write_now=False,
                    holds=[],catalog_digest_matches_saved=False,evidence_digest_matches_saved=False,
                    source_history_id_matches=False,native_checks=[],operator_checks=[],targets=[],tv_checks=[])])
        first=data['review_rows'][0]
        first['native_checks']=[dict(namespace='operator_315',native_id='849821',global_saved_unique=True,raw_verified=True,failures=[])]
        first['operator_checks']=[dict(namespace='operator_315',native_id='849821',current_identity_count=1,current_local_hotel_ids=[42903])]
        first['targets']=[dict(kind='tv_candidate',id=42903,tv_live30_observed=True,holds=[]),
                          dict(kind='independent_local_anchor',id=56551,tv_live30_observed=False,holds=[])]
        first['tv_checks']=[dict(tv_hotel_id=42903,operator='funsun',native_id='849821',tv_native_id='849821',
            state='saved_producers_reviewed',producers=[dict(source_operation='hotel-match-fixture',
                source_result_sha256='a'*64,state='saved_tv_proof_verified',failures=[])])]
        if mutate:mutate(data)
        (child/'native110-current-summary.json').write_text(json.dumps(data))
        return types.SimpleNamespace(returncode=0,stdout=json.dumps(data),stderr='')

    def test_reserved_private_read_and_network_disabled_php(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            def call(argv,**kwargs):
                self.assertEqual(argv[-2:],[str(runner),'--current'])
                self.assertIn('allow_url_fopen=0',argv)
                self.assertTrue(any(a.startswith('disable_functions=') and 'curl_exec' in a and 'proc_open' in a for a in argv))
                self.assertEqual(set(kwargs['env'])-{'PATH','HOME','LANG','LC_ALL'},{'ANYTOUR_ROOT','MATCH_OPERATION_DIR','MATCH_SOURCE_SHA'})
                child=Path(kwargs['env']['MATCH_OPERATION_DIR']);reservation=json.loads((child/'reservation.json').read_text())
                self.assertEqual(reservation['maximum_writes'],0)
                self.assertEqual(reservation['state'],'reserved_before_db_read')
                self.assertEqual((child/'reservation.json').stat().st_mode&0o777,0o600)
                return self.response(kwargs)
            with patch.object(subprocess,'run',side_effect=call) as run:
                self.assertEqual(ns['run_match_native110_current'](stage)['sources_examined'],109)
                with self.assertRaises(RuntimeError):ns['run_match_native110_current'](stage)
                self.assertEqual(run.call_count,1)

    def test_untrusted_summary_fields_counts_and_authority_rejected(self):
        mutations=[lambda d:d.update(raw='fixture-secret'),lambda d:d.update(mapping_writes=1),
                   lambda d:d.update(provider_http_calls=True),lambda d:d.update(sources_examined=110),
                   lambda d:d.update(raw_bytes_read=536870913),lambda d:d.update(source_sha='c'*40),
                   lambda d:d.update(safe_to_write_now=True),lambda d:d.update(acceptance_policy_changed=True),
                   lambda d:d['review_rows'][0].update(raw='fixture-secret'),
                   lambda d:d['review_rows'][0].update(catalog_id='2000086118'),
                   lambda d:d['review_rows'][-1].update(native_checks=[{'raw':'fixture-secret'}]),
                   lambda d:d['review_rows'][0].update(targets=[{'id':42,'kind':'tv_candidate','tv_live30_observed':True,'holds':[],'raw':'fixture-secret'}]),
                   lambda d:d['review_rows'][0].update(holds=['https://fixture-secret']),
                   lambda d:d.update(manifest_sha256='a'*64)]
        for mutate in mutations:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,runner=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_native110_current'](stage)

    def test_failed_or_unknown_read_is_not_replayed(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=subprocess.TimeoutExpired('fixture',240)) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_native110_current'](stage)
                with self.assertRaises(RuntimeError):ns['run_match_native110_current'](stage)
                self.assertEqual(call.call_count,1)
            self.assertTrue((root/registration.NATIVE_OPERATION/'reservation.json').is_file())

class GuardedNative110RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.GUARDED_MODE+' '+registration.GUARDED_OPERATION+' '+registration.NATIVE_BATCH

    def test_only_exact_new_intake_is_authorized(self):
        parsed=self.core.parse_command(self.body)
        self.assertEqual(parsed['maximum_writes'],4);self.assertEqual(parsed['input_sha256'],registration.GUARDED_INPUT_SHA)
        for body in (self.body+' 1',self.body.replace(registration.NATIVE_BATCH,registration.BATCH),
                     self.body.replace(registration.GUARDED_OPERATION,OP),self.body.replace(SOURCE,'bad')):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        for key,value in [('input_sha256','b'*64),('maximum_writes',5),('provider_http_calls',1)]:
            bad=copy.deepcopy(parsed);bad[key]=value
            with self.assertRaises(ValueError):registration.activate(self.core,bad)
        registration.activate(self.core,parsed)
        self.assertIn('def run_match_native110_write(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_primary_candidate(stage):',self.core.REMOTE)
        self.assertNotIn('primary-batch-samo3-20260929.json',self.core.REMOTE)
        self.assertTrue(set(registration.GUARDED_SOURCE_FILES).issubset(self.core.FIXED))
        guards=[n.test for n in ast.walk(ast.parse(self.core.REMOTE)) if isinstance(n,ast.If) and isinstance(n.test,ast.Compare)
            and isinstance(n.test.left,ast.Name) and n.test.left.id=='mode' and isinstance(n.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:self.assertFalse(eval(compile(ast.Expression(guard),'<guard>','eval'),{},dict(mode=registration.GUARDED_MODE)))

    def namespace(self,tmp):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.GUARDED_OPERATION
        ns['payload']=dict(batch=registration.NATIVE_BATCH,maximum_writes=4,provider_http_calls=0,input_sha256=registration.GUARDED_INPUT_SHA)
        runner=stage/'scripts/diagnostics/hotel_match_native110_guarded_v1.php';runner.write_text('<?php // fixture only')
        exec(registration.REMOTE_GUARDED_HANDLER,ns);return ns,stage,root,runner

    def response(self,kwargs,mutate=None,state='committed_readback_verified'):
        child=Path(kwargs['env']['MATCH_OPERATION_DIR']);pairs={'3126':42903,'9501':420,'475947':28529,'2000034238':16944}
        rows=[dict(catalog_id=c,local_hotel_id=i,name='Fixture Hotel',catalog_sha256='a'*64,
            evidence_sha256='b'*64,prior_evidence_sha256='c'*64,proof_operator_count=1) for c,i in pairs.items()]
        data=dict(operation=registration.GUARDED_OPERATION,source_sha=SOURCE,batch=registration.NATIVE_BATCH,
            input_sha256=registration.GUARDED_INPUT_SHA,provider_http_calls=0,no_replay=True,state=state,
            current_candidates_evaluated=4,rows=rows,held=[],database_writes=4,mapping_writes=4,readback_verified=True,
            commit_attempted=True,commit_completed=True,effective_resolver_verified=True,
            prior_evidence_preserved=True,unrelated_identities_unchanged=True,
            coverage_before=dict(tv_total=4,full_triple=0,samo_only=0,anex_only=1,neither=3),
            coverage_after=dict(tv_total=4,full_triple=1,samo_only=3,anex_only=0,neither=0),new_full_triples=1)
        if state=='completed_no_new_writes':
            data=dict(operation=registration.GUARDED_OPERATION,source_sha=SOURCE,batch=registration.NATIVE_BATCH,
                input_sha256=registration.GUARDED_INPUT_SHA,provider_http_calls=0,no_replay=True,state=state,
                current_candidates_evaluated=4,rows=[],held=[dict(catalog_id=c,local_hotel_id=i,status='hold',reasons=['coordinate_conflict_over_5km']) for c,i in pairs.items()],
                database_writes=0,mapping_writes=0,readback_verified=True)
        if state=='commit_outcome_unknown_no_replay':
            data=dict(operation=registration.GUARDED_OPERATION,source_sha=SOURCE,batch=registration.NATIVE_BATCH,
                input_sha256=registration.GUARDED_INPUT_SHA,provider_http_calls=0,no_replay=True,state=state,
                current_candidates_evaluated=4,rows=[],held=[],database_writes=None,mapping_writes=None,
                readback_verified=False,commit_attempted=True,commit_completed=False,reason='fixture_commit_response_loss')
        if mutate:mutate(data)
        raw=json.dumps(data);(child/'result.json').write_text(raw)
        receipt={k:data[k] for k in ('operation','source_sha','batch','input_sha256','provider_http_calls','no_replay','state','database_writes','mapping_writes','readback_verified')}
        receipt['result_sha256']=hashlib.sha256(raw.encode()).hexdigest();(child/'receipt.json').write_text(json.dumps(receipt))
        return types.SimpleNamespace(returncode=0 if data['state'] in ('committed_readback_verified','completed_no_new_writes') else 2,stdout=raw,stderr='')

    def test_reserved_network_disabled_write_and_input_consumption(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            def call(argv,**kwargs):
                self.assertEqual(argv[-2:],[str(runner),'--execute']);self.assertIn('allow_url_fopen=0',argv)
                self.assertEqual(set(kwargs['env'])-{'PATH','HOME','LANG','LC_ALL'},{'ANYTOUR_ROOT','MATCH_OPERATION_DIR','MATCH_SOURCE_SHA'})
                child=Path(kwargs['env']['MATCH_OPERATION_DIR']);r=json.loads((child/'reservation.json').read_text())
                self.assertEqual(r['input_sha256'],registration.GUARDED_INPUT_SHA)
                marker=root.parent/('native110-input-'+registration.GUARDED_INPUT_SHA+'-consumed.json')
                self.assertEqual(json.loads(marker.read_text()),r);self.assertEqual(marker.stat().st_mode&0o777,0o600)
                return self.response(kwargs)
            with patch.object(subprocess,'run',side_effect=call) as call:
                self.assertTrue(ns['run_match_native110_write'](stage)['successful'])
                with self.assertRaises(RuntimeError):ns['run_match_native110_write'](stage)
                self.assertEqual(call.call_count,1)

    def test_held_and_unknown_outcomes_preserve_their_meaning(self):
        for state in ('completed_no_new_writes','commit_outcome_unknown_no_replay'):
            with self.subTest(state=state),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,runner=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,state=state)):
                    out=ns['run_match_native110_write'](stage)
                self.assertEqual(out['successful'],state=='completed_no_new_writes')
                self.assertEqual(out['summary']['mapping_writes'],0 if out['successful'] else None)
                with self.assertRaises(RuntimeError):ns['run_match_native110_write'](stage)

    def test_untrusted_terminal_scope_fields_and_false_readback_rejected(self):
        changes=[lambda d:d.update(raw='fixture-secret'),lambda d:d.update(input_sha256='f'*64),
            lambda d:d.update(mapping_writes=5),lambda d:d.update(provider_http_calls=False),
            lambda d:d.update(effective_resolver_verified=False),lambda d:d.update(readback_verified=False),
            lambda d:d['rows'][0].update(catalog_id='2000086118'),lambda d:d['rows'][0].update(raw='fixture-secret'),
            lambda d:d['rows'][0].update(local_hotel_id=144804),lambda d:d['rows'].pop(),
            lambda d:d.update(no_replay=1),lambda d:d['coverage_after'].update(raw='fixture-secret')]
        for mutate in changes:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,runner=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_native110_write'](stage)

    def test_timeout_cannot_replay_or_free_the_input_marker(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=subprocess.TimeoutExpired('fixture',240)) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_native110_write'](stage)
                with self.assertRaises(RuntimeError):ns['run_match_native110_write'](stage)
                self.assertEqual(call.call_count,1)
            self.assertTrue((root.parent/('native110-input-'+registration.GUARDED_INPUT_SHA+'-consumed.json')).is_file())

class BGOriginalEvidenceRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.BG_MODE+' '+registration.BG_OPERATION+' '+registration.NATIVE_BATCH

    def test_bound_to_exact_missing_evidence_task(self):
        p=self.core.parse_command(self.body);self.assertEqual(p['maximum_writes'],0)
        self.assertEqual(p['input_sha256'],registration.GUARDED_INPUT_SHA)
        for body in (self.body+' 18',self.body.replace(registration.BG_OPERATION,registration.NATIVE_OPERATION),self.body.replace(registration.NATIVE_BATCH,registration.BATCH)):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        registration.activate(self.core,p)
        self.assertIn('def run_match_native110_bg_evidence(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_native110_current(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_native110_write(stage):',self.core.REMOTE)
        self.assertTrue(set(registration.BG_SOURCE_FILES).issubset(self.core.FIXED))
        guards=[n.test for n in ast.walk(ast.parse(self.core.REMOTE)) if isinstance(n,ast.If) and isinstance(n.test,ast.Compare)
            and isinstance(n.test.left,ast.Name) and n.test.left.id=='mode' and isinstance(n.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:self.assertFalse(eval(compile(ast.Expression(guard),'<guard>','eval'),{},dict(mode=registration.BG_MODE)))

    def namespace(self,tmp):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.BG_OPERATION;ns['payload']=dict(batch=registration.NATIVE_BATCH,maximum_writes=0,provider_http_calls=0,input_sha256=registration.GUARDED_INPUT_SHA)
        runner=stage/'scripts/diagnostics/hotel_match_native110_bg_evidence_v1.php';runner.write_text('<?php // fixture only')
        exec(registration.REMOTE_BG_HANDLER,ns);return ns,stage,root,runner

    def response(self,kwargs,mutate=None):
        rows=[dict(catalog_id=c,tv_hotel_id=v[0],samo_native_id=v[1],tv_native_id=v[2],raw_references_examined=2,
            top_fields=['hotelKey','original'],original_fields=['hotelKey','hotelUrl'],
            location_fields=[dict(source_field='original.country',value='Турция')],
            bg_links=[dict(source_field='original.hotelUrl',host='www.bgoperator.ru',url_sha256='b'*64,signed_parameters_present=True,
                hotel_selectors=[dict(parameter='tid',positive_tokens=[v[2]],opaque_tokens=0,value_sha256='a'*64)])],
            failures=[],safe_to_write_now=False) for c,v in registration.BG_EXPECTED.items()]
        data=dict(state='completed_bg_original_fields_review',operation=registration.BG_OPERATION,source_sha=SOURCE,batch=registration.NATIVE_BATCH,
            input_sha256=registration.GUARDED_INPUT_SHA,rows=rows,raw_files_read=1,raw_bytes_read=1000,
            provider_http_calls=0,database_reads=0,database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True)
        if mutate:mutate(data)
        (Path(kwargs['env']['MATCH_OPERATION_DIR'])/'result.json').write_text(json.dumps(data))
        return types.SimpleNamespace(returncode=0,stdout=json.dumps(data),stderr='')

    def test_whole18_read_without_db_or_supplier_environment(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            def call(argv,**kw):
                self.assertEqual(argv[-2:],[str(runner),'--read-saved']);self.assertIn('allow_url_fopen=0',argv)
                self.assertEqual(set(kw['env'])-{'PATH','HOME','LANG','LC_ALL'},{'MATCH_OPERATION_DIR','MATCH_SOURCE_SHA'})
                r=json.loads((Path(kw['env']['MATCH_OPERATION_DIR'])/'reservation.json').read_text())
                self.assertEqual(r['maximum_writes'],0);self.assertEqual(r['state'],'reserved_before_saved_read')
                return self.response(kw)
            with patch.object(subprocess,'run',side_effect=call) as call:
                out=ns['run_match_native110_bg_evidence'](stage)
                self.assertEqual(len(out['rows']),18);self.assertEqual(out['database_reads'],0)
                with self.assertRaises(RuntimeError):ns['run_match_native110_bg_evidence'](stage)
                self.assertEqual(call.call_count,1)

    def test_untrusted_fields_authority_scope_and_raw_values_are_rejected(self):
        changes=[lambda d:d.update(database_reads=1),lambda d:d.update(mapping_writes=1),lambda d:d.update(safe_to_write_now=True),
            lambda d:d.update(raw='fixture-secret'),lambda d:d['rows'][0].update(catalog_id='2000086118'),
            lambda d:d['rows'][0].update(samo_native_id=d['rows'][0]['tv_native_id']),lambda d:d['rows'][0].update(raw='fixture-secret'),
            lambda d:d['rows'][0]['location_fields'][0].update(source_field='original.api_token',value='fixture-secret'),
            lambda d:d['rows'][0]['bg_links'][0].update(url='https://www.bgoperator.ru/?token=fixture-secret'),
            lambda d:d['rows'][0]['bg_links'][0].update(host='www.bgoperator.ru.evil.test'),
            lambda d:d['rows'][0]['bg_links'][0]['hotel_selectors'][0].update(positive_tokens=['signed-fixture-secret'])]
        for mutate in changes:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,runner=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_native110_bg_evidence'](stage)

    def test_timeout_is_terminal_not_replayed(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=subprocess.TimeoutExpired('fixture',240)) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_native110_bg_evidence'](stage)
                with self.assertRaises(RuntimeError):ns['run_match_native110_bg_evidence'](stage)
                self.assertEqual(call.call_count,1)

class ShamsGeographyEvidenceRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.SHAMS_GEO_MODE+' '+registration.SHAMS_GEO_OPERATION+' '+registration.NATIVE_BATCH

    def test_bound_to_exact_saved_geography_task(self):
        p=self.core.parse_command(self.body);self.assertEqual(p['maximum_writes'],0)
        self.assertEqual(p['input_sha256'],registration.GUARDED_INPUT_SHA)
        for body in (self.body+' 9501',self.body.replace(registration.SHAMS_GEO_OPERATION,registration.BG_OPERATION),
                     self.body.replace(registration.NATIVE_BATCH,registration.BATCH)):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        registration.activate(self.core,p)
        self.assertIn('def run_match_shams_geo_evidence(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_native110_bg_evidence(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_native110_write(stage):',self.core.REMOTE)
        self.assertTrue(set(registration.SHAMS_GEO_SOURCE_FILES).issubset(self.core.FIXED))
        guards=[n.test for n in ast.walk(ast.parse(self.core.REMOTE)) if isinstance(n,ast.If) and isinstance(n.test,ast.Compare)
            and isinstance(n.test.left,ast.Name) and n.test.left.id=='mode' and isinstance(n.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:self.assertFalse(eval(compile(ast.Expression(guard),'<guard>','eval'),{},dict(mode=registration.SHAMS_GEO_MODE)))

    def namespace(self,tmp):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.SHAMS_GEO_OPERATION
        ns['payload']=dict(batch=registration.NATIVE_BATCH,maximum_writes=0,provider_http_calls=0,input_sha256=registration.GUARDED_INPUT_SHA)
        runner=stage/'scripts/diagnostics/hotel_match_shams_geography_saved_v1.php';runner.write_text('<?php // fixture only')
        exec(registration.REMOTE_SHAMS_GEO_HANDLER,ns);return ns,stage,root,runner

    def result_data(self,mutate=None):
        refs=[]
        for namespace,native in (('operator_5','835'),('operator_342','24402')):
            refs.append(dict(namespace=namespace,native_id=native,page_sha256='a'*64,json_pointer='/PRICES/0',
                field_names=['hotelKey','original','town'],original_field_names=['hotelKey','townName'],
                location_fields=[dict(source_field='row.town',value='Marsa Alam'),dict(source_field='original.townName',value='Марса-Алам')],
                raw_verified=True,failures=[]))
        data=dict(schema='match-shams-saved-geography/1',state='completed_saved_geography_evidence',
            operation=registration.SHAMS_GEO_OPERATION,batch=registration.NATIVE_BATCH,source_sha=SOURCE,
            input_sha256=registration.GUARDED_INPUT_SHA,no_replay=True,catalog_id='9501',tv_hotel_id=420,
            snapshot_captured_at_utc='2026-10-01T07:50:00.123456+00:00',
            saved_target_geography=[dict(source_field='saved_target.country_name',value='Египет')],
            source_history_geography_exported=False,raw_files_read=1,raw_bytes_read=1000,references_examined=2,references=refs,
            database_reads=0,provider_http_calls=0,database_writes=0,mapping_writes=0,safe_to_write_now=False)
        if mutate:mutate(data)
        return data

    def response(self,kwargs,mutate=None):
        data=self.result_data(mutate)
        (Path(kwargs['env']['MATCH_OPERATION_DIR'])/'result.json').write_text(json.dumps(data))
        return types.SimpleNamespace(returncode=0,stdout=json.dumps(data),stderr='')

    def test_exact_pair_read_without_db_or_supplier_environment(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            def call(argv,**kw):
                self.assertEqual(argv[-2:],[str(runner),'--read-saved']);self.assertIn('allow_url_fopen=0',argv)
                self.assertEqual(set(kw['env'])-{'PATH','HOME','LANG','LC_ALL'},{'MATCH_OPERATION_DIR','MATCH_SOURCE_SHA'})
                reservation=json.loads((Path(kw['env']['MATCH_OPERATION_DIR'])/'reservation.json').read_text())
                self.assertEqual(reservation['maximum_writes'],0);self.assertEqual(reservation['state'],'reserved_before_saved_read')
                return self.response(kw)
            with patch.object(subprocess,'run',side_effect=call) as call:
                out=ns['run_match_shams_geo_evidence'](stage)
                self.assertEqual({(r['namespace'],r['native_id']) for r in out['references']},{('operator_5','835'),('operator_342','24402')})
                self.assertEqual(out['database_reads'],0)
                with self.assertRaises(RuntimeError):ns['run_match_shams_geo_evidence'](stage)
                self.assertEqual(call.call_count,1)

    def test_untrusted_fields_authority_and_exact_pair_are_rejected(self):
        changes=[lambda d:d.update(database_reads=1),lambda d:d.update(mapping_writes=1),lambda d:d.update(safe_to_write_now=True),
            lambda d:d.update(raw='fixture-secret'),lambda d:d.update(catalog_id='9502'),lambda d:d.update(tv_hotel_id=421),
            lambda d:d.update(source_history_geography_exported=True),lambda d:d['references'][0].update(namespace='operator_115'),
            lambda d:d['references'][0].update(native_id='9501'),lambda d:d['references'][0].update(raw='fixture-secret'),
            lambda d:d['references'][0]['location_fields'][0].update(source_field='row.api_token',value='fixture-secret'),
            lambda d:d['saved_target_geography'][0].update(source_field='saved_target.api_token',value='fixture-secret'),
            lambda d:(d['references'].pop(),d.update(references_examined=1)),
            lambda d:d['references'][0].update(raw_verified=False),
            lambda d:d['references'][0].update(failures=['unexpected_failure'])]
        for mutate in changes:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,runner=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_shams_geo_evidence'](stage)

    def test_timeout_is_terminal_not_replayed(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=subprocess.TimeoutExpired('fixture',240)) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_shams_geo_evidence'](stage)
                with self.assertRaises(RuntimeError):ns['run_match_shams_geo_evidence'](stage)
                self.assertEqual(call.call_count,1)

class ShamsGeographyReadbackRegistrationTest(unittest.TestCase):
    EVIDENCE_SOURCE='12dc06dbdfd047c05caa346092cb9bd1c1dd0323'

    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.SHAMS_GEO_READBACK_MODE+' '+registration.SHAMS_GEO_READBACK_OPERATION+' '+registration.NATIVE_BATCH

    def namespace(self,tmp,mutate=None):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.SHAMS_GEO_READBACK_OPERATION
        ns['payload']=dict(batch=registration.NATIVE_BATCH,maximum_writes=0,provider_http_calls=0,input_sha256=registration.GUARDED_INPUT_SHA)
        evidence=root/registration.SHAMS_GEO_OPERATION;evidence.mkdir()
        reservation=dict(operation=registration.SHAMS_GEO_OPERATION,source_sha=self.EVIDENCE_SOURCE,batch=registration.NATIVE_BATCH,
            input_sha256=registration.GUARDED_INPUT_SHA,maximum_writes=0,provider_http_calls=0)
        (evidence/'reservation.json').write_text(json.dumps(reservation))
        helper=ShamsGeographyEvidenceRegistrationTest();data=helper.result_data(mutate);data['source_sha']=self.EVIDENCE_SOURCE
        (evidence/'result.json').write_text(json.dumps(data))
        exec(registration.REMOTE_SHAMS_GEO_READBACK_HANDLER,ns)
        return ns,stage,root,evidence

    def test_exact_terminal_readback_without_source_execution(self):
        parsed=self.core.parse_command(self.body)
        self.assertEqual(parsed['maximum_writes'],0);self.assertEqual(parsed['input_sha256'],registration.GUARDED_INPUT_SHA)
        for body in (self.body+' retry',self.body.replace(registration.SHAMS_GEO_READBACK_OPERATION,registration.SHAMS_GEO_OPERATION),
                     self.body.replace(registration.NATIVE_BATCH,registration.BATCH)):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        registration.activate(self.core,parsed)
        self.assertIn('def run_match_shams_geo_readback(stage):',self.core.REMOTE)
        self.assertTrue(set(registration.SHAMS_GEO_SOURCE_FILES).issubset(self.core.FIXED))
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,evidence=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=AssertionError('source must not execute')) as run:
                out=ns['run_match_shams_geo_readback'](stage)
                self.assertEqual(out['state'],'completed_saved_geography_readback')
                self.assertEqual(out['evidence']['snapshot_captured_at_utc'],'2026-10-01T07:50:00.123456+00:00')
                self.assertEqual(out['provider_http_calls'],0);self.assertEqual(out['database_reads'],0)
                self.assertEqual(run.call_count,0)
                with self.assertRaises(RuntimeError):ns['run_match_shams_geo_readback'](stage)

    def test_terminal_binding_and_inner_projection_are_rechecked(self):
        mutations=[lambda d:d.update(snapshot_captured_at_utc='private timestamp'),lambda d:d.update(mapping_writes=1),
                   lambda d:d['references'][0].update(native_id='9501'),lambda d:d.update(raw='fixture-secret')]
        for mutate in mutations:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,evidence=self.namespace(tmp,mutate)
                with self.assertRaises(RuntimeError):ns['run_match_shams_geo_readback'](stage)
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,evidence=self.namespace(tmp)
            reservation=json.loads((evidence/'reservation.json').read_text());reservation['source_sha']='0'*40
            (evidence/'reservation.json').write_text(json.dumps(reservation))
            with self.assertRaises(RuntimeError):ns['run_match_shams_geo_readback'](stage)

class ShamsGuardedWriteRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.SHAMS_WRITE_MODE+' '+registration.SHAMS_WRITE_OPERATION+' '+registration.SHAMS_WRITE_BATCH

    def namespace(self,tmp):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.SHAMS_WRITE_OPERATION
        ns['payload']=dict(batch=registration.SHAMS_WRITE_BATCH,maximum_writes=1,provider_http_calls=0,
            input_sha256=registration.GUARDED_INPUT_SHA,geography_operation=registration.SHAMS_GEO_READBACK_OPERATION)
        geo=root/registration.SHAMS_GEO_READBACK_OPERATION;geo.mkdir()
        (geo/'result.json').write_text(json.dumps(dict(state='completed_saved_geography_readback',
            operation=registration.SHAMS_GEO_READBACK_OPERATION,source_sha='12dc06dbdfd047c05caa346092cb9bd1c1dd0323',
            input_sha256=registration.GUARDED_INPUT_SHA,no_replay=True,mapping_writes=0,provider_http_calls=0)))
        runner=stage/'scripts/diagnostics/hotel_match_shams_guarded_v1.php';runner.write_text('<?php // fixture only')
        exec(registration.REMOTE_SHAMS_WRITE_HANDLER,ns);return ns,stage,root,runner

    def response(self,kwargs,state='committed_readback_verified',mutate=None):
        child=Path(kwargs['env']['MATCH_OPERATION_DIR'])
        common=dict(operation=registration.SHAMS_WRITE_OPERATION,source_sha=SOURCE,batch=registration.SHAMS_WRITE_BATCH,
            input_sha256=registration.GUARDED_INPUT_SHA,geography_operation=registration.SHAMS_GEO_READBACK_OPERATION,
            provider_http_calls=0,no_replay=True,current_candidates_evaluated=1)
        if state=='committed_readback_verified':
            data=common|dict(state=state,rows=[dict(catalog_id='9501',local_hotel_id=420,name='SHAMS SAFAGA',catalog_sha256='a'*64,
                evidence_sha256='b'*64,prior_evidence_sha256='c'*64,proof_operator_count=1)],held=[],database_writes=1,mapping_writes=1,
                readback_verified=True,commit_attempted=True,commit_completed=True,effective_resolver_verified=True,
                prior_evidence_preserved=True,unrelated_identities_unchanged=True)
        elif state=='completed_no_new_writes':
            data=common|dict(state=state,rows=[],held=[dict(catalog_id='9501',local_hotel_id=420,status='hold',reasons=['target_catalog_occupied'])],
                database_writes=0,mapping_writes=0,readback_verified=True)
        else:
            data=common|dict(state=state,rows=[],held=[],database_writes=None,mapping_writes=None,readback_verified=False,
                reason='fixture_commit_loss',commit_attempted=True,commit_completed=False)
        if mutate:mutate(data)
        raw=json.dumps(data);(child/'result.json').write_text(raw)
        receipt={k:data[k] for k in ('operation','source_sha','batch','input_sha256','geography_operation','provider_http_calls','no_replay','state','database_writes','mapping_writes','readback_verified')}
        receipt['result_sha256']=hashlib.sha256(raw.encode()).hexdigest();(child/'receipt.json').write_text(json.dumps(receipt))
        return types.SimpleNamespace(returncode=0 if state in ('committed_readback_verified','completed_no_new_writes') else 2,stdout=raw,stderr='')

    def test_exact_one_row_contract_and_terminal_marker(self):
        parsed=self.core.parse_command(self.body)
        self.assertEqual(parsed['maximum_writes'],1);self.assertEqual(parsed['geography_operation'],registration.SHAMS_GEO_READBACK_OPERATION)
        for body in (self.body+' retry',self.body.replace(registration.SHAMS_WRITE_BATCH,registration.NATIVE_BATCH),self.body.replace(registration.SHAMS_WRITE_OPERATION,registration.GUARDED_OPERATION)):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        registration.activate(self.core,parsed);self.assertIn('def run_match_shams_write(stage):',self.core.REMOTE)
        self.assertTrue(set(registration.SHAMS_WRITE_SOURCE_FILES).issubset(self.core.FIXED))
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root,runner=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw)) as call:
                out=ns['run_match_shams_write'](stage);self.assertTrue(out['successful']);self.assertEqual(out['summary']['mapping_writes'],1)
                marker=root.parent/'shams9501-geo-20261001-consumed.json';self.assertTrue(marker.is_file());self.assertEqual(marker.stat().st_mode&0o777,0o600)
                with self.assertRaises(RuntimeError):ns['run_match_shams_write'](stage)
                self.assertEqual(call.call_count,1)

    def test_hold_unknown_and_untrusted_scope(self):
        for state in ('completed_no_new_writes','commit_outcome_unknown_no_replay'):
            with self.subTest(state=state),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,runner=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,state=state)):
                    out=ns['run_match_shams_write'](stage)
                self.assertEqual(out['successful'],state=='completed_no_new_writes')
        changes=[lambda d:d.update(mapping_writes=2),lambda d:d.update(no_replay=1),lambda d:d['rows'][0].update(catalog_id='3126'),
            lambda d:d['rows'][0].update(local_hotel_id=42903),lambda d:d.update(readback_verified=False)]
        for mutate in changes:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root,runner=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate=mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_shams_write'](stage)

class Live30TargetCatalogRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.TARGET_MODE+' '+registration.TARGET_OPERATION+' '+registration.TARGET_BATCH

    def namespace(self,tmp):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.TARGET_OPERATION
        ns['payload']=dict(batch=registration.TARGET_BATCH,maximum_writes=0,provider_http_calls=0)
        runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v1.php';runner.write_text('<?php // fixture only')
        exec(registration.REMOTE_TARGET_HANDLER,ns);return ns,stage,root

    def response(self,kwargs,mutate=None):
        child=Path(kwargs['env']['MATCH_OPERATION_DIR'])
        data=dict(state='completed_tv_live30_target_catalog',operation=registration.TARGET_OPERATION,source_sha=SOURCE,
            batch=registration.TARGET_BATCH,captured_at_utc='2026-10-01T12:00:00Z',row_count=1,
            provider_http_calls=0,database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True,
            rows=[dict(id=420,name='SHAMS ALAM RESORT',country_id='5',country_name='Египет',region_name='Марса Алам',
                subregion_name=None,category='4',is_active=True,latitude=24.6907006,longitude=35.0835745,
                accepted_samo_ids=['9501'],manual_hold=False,exclusion_hold=False)])
        if mutate:mutate(data)
        raw=json.dumps(data);(child/'result.json').write_text(raw)
        receipt={k:v for k,v in data.items() if k!='rows'}
        receipt['result_sha256']=hashlib.sha256(raw.encode()).hexdigest();(child/'receipt.json').write_text(json.dumps(receipt))
        return types.SimpleNamespace(returncode=0,stdout=raw,stderr='')

    def test_scope_collector_bypass_and_source_inventory(self):
        parsed=self.core.parse_command(self.body)
        self.assertEqual(parsed['maximum_writes'],0);self.assertEqual(parsed['provider_http_calls'],0)
        for body in (self.body+' retry',self.body.replace(registration.TARGET_BATCH,registration.NATIVE_BATCH),
                     self.body.replace(registration.TARGET_OPERATION,registration.TARGET_OPERATION+'-retry')):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        for key,value in (('maximum_writes',1),('provider_http_calls',1),('batch',registration.NATIVE_BATCH)):
            with self.assertRaises(ValueError):registration.activate(self.core,parsed|{key:value})
        registration.activate(self.core,parsed)
        self.assertTrue(set(registration.TARGET_SOURCE_FILES).issubset(self.core.FIXED))
        self.assertNotIn('def run_match_primary_candidate(stage):',self.core.REMOTE)
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if (isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name)
                    and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn)):
                self.assertFalse(eval(compile(ast.Expression(node.test),'<guard>','eval'),{},dict(mode=registration.TARGET_MODE)))
        entry=load('target_catalog_stock_entry','scripts/deploy/int_server_executor_anex_secret_transport.py')
        self.assertNotIn(registration.TARGET_MODE,entry.DIRECT_ANEX_MODES)
        self.assertNotIn(registration.TARGET_MODE,entry.SUPPLIER_SLOT_MODES)

    def test_bound_occupied_target_result_and_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw)) as call:
                out=ns['run_match_tv_live30_target_catalog'](stage)
                self.assertEqual(out['summary']['rows'][0]['accepted_samo_ids'],['9501'])
                self.assertFalse(out['summary']['safe_to_write_now'])
                self.assertEqual((root/registration.TARGET_OPERATION/'reservation.json').stat().st_mode&0o777,0o600)
                with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_catalog'](stage)
                self.assertEqual(call.call_count,1)
                argv=call.call_args.args[0];self.assertIn('allow_url_fopen=0',argv)
                self.assertNotIn('ANEX',str(call.call_args.kwargs['env']))

    def test_untrusted_projection_cannot_become_authority(self):
        changes=[lambda d:d.update(mapping_writes=1),lambda d:d.update(no_replay=1),lambda d:d.update(safe_to_write_now=0),
            lambda d:d.update(row_count=2),lambda d:d['rows'][0].update(latitude=91),
            lambda d:d['rows'][0].update(raw_history='secret'),lambda d:d['rows'][0].update(name='https://secret.example/'),
            lambda d:d['rows'][0].update(accepted_samo_ids=['native:9501']),
            lambda d:d.update(rows=d['rows']*2,row_count=2)]
        for mutate in changes:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_catalog'](stage)

    def test_saved_target_readback_never_executes_php_or_db(self):
        for terminal in (False,True):
            with self.subTest(terminal=terminal),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp)
                ns['operation']=registration.TARGET_READBACK_OPERATION
                evidence=root/registration.TARGET_OPERATION;evidence.mkdir()
                original_source='6b49c5ac61ca21e7bb413d30d6badd9f29518cb4'
                reservation=dict(operation=registration.TARGET_OPERATION,source_sha=original_source,batch=registration.TARGET_BATCH,
                    maximum_writes=0,provider_http_calls=0,state='reserved_before_db_read')
                (evidence/'reservation.json').write_text(json.dumps(reservation))
                (evidence/'execution-started.json').write_text(json.dumps(dict(operation=registration.TARGET_OPERATION,source_sha=original_source)))
                if terminal:self.response(dict(env={'MATCH_OPERATION_DIR':str(evidence)}),lambda d:d.update(source_sha=original_source))
                exec(registration.REMOTE_TARGET_READBACK_HANDLER,ns)
                body=self.core.PREFIX+SOURCE+' '+registration.TARGET_READBACK_MODE+' '+registration.TARGET_READBACK_OPERATION+' '+registration.TARGET_BATCH
                core=fresh_core();registration.register_parser(core)
                registration.activate(core,core.parse_command(body))
                with patch.object(subprocess,'run',side_effect=AssertionError('no PHP or DB process')) as call:
                    out=ns['run_match_tv_live30_target_readback'](stage)
                    self.assertEqual(out['terminal_verified'],terminal);self.assertFalse(out['original_read_reexecuted'])
                    self.assertEqual(out['database_reads'],0);self.assertEqual(out['database_writes'],0)
                    self.assertEqual(out['catalog'] is not None,terminal)
                    with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_readback'](stage)
                    call.assert_not_called()

    def test_saved_target_readback_rejects_wrong_producer(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp);ns['operation']=registration.TARGET_READBACK_OPERATION
            evidence=root/registration.TARGET_OPERATION;evidence.mkdir()
            (evidence/'reservation.json').write_text(json.dumps(dict(operation=registration.TARGET_OPERATION,source_sha=SOURCE)))
            exec(registration.REMOTE_TARGET_READBACK_HANDLER,ns)
            with patch.object(subprocess,'run',side_effect=AssertionError('no PHP or DB process')) as call:
                with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_readback'](stage)
                call.assert_not_called()

    def test_unknown_read_outcome_stays_consumed(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=subprocess.TimeoutExpired('php',240)) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_tv_live30_target_catalog'](stage)
                with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_catalog'](stage)
                self.assertEqual(call.call_count,1)

class Live30TargetPreflightRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.TARGET_PREFLIGHT_MODE+' '+registration.TARGET_PREFLIGHT_OPERATION+' '+registration.TARGET_PREFLIGHT_BATCH

    def namespace(self,tmp):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.TARGET_PREFLIGHT_OPERATION
        ns['payload']=dict(batch=registration.TARGET_PREFLIGHT_BATCH,maximum_writes=0,provider_http_calls=0)
        runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_preflight_v1.php';runner.write_text('<?php // fixture only')
        exec(registration.REMOTE_TARGET_PREFLIGHT_HANDLER,ns);return ns,stage,root

    def response(self,kwargs,mutate=None):
        child=Path(kwargs['env']['MATCH_OPERATION_DIR'])
        names=['cohort','invalid_coordinates','invalid_text','invalid_accepted_native','max_accepted_aliases','manual_targets','excluded_targets']
        missing={name:[] for name in ('catalog_hotels','tour_operator_identity_observations','andromeda_hotel_identities','anex_hotel_decisions','anex_review_pair_exclusions')}
        data=dict(state='completed_tv_live30_target_preflight',operation=registration.TARGET_PREFLIGHT_OPERATION,source_sha=SOURCE,
            batch=registration.TARGET_PREFLIGHT_BATCH,captured_at_utc='2026-10-01T12:00:00Z',schema_complete=True,
            missing_columns=missing,query_status={name:True for name in names},metrics={name:1 for name in names},
            provider_http_calls=0,database_reads=1,database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True)
        if mutate:mutate(data)
        raw=json.dumps(data);(child/'result.json').write_text(raw)
        receipt={k:v for k,v in data.items() if k not in ('missing_columns','query_status','metrics')}
        receipt['result_sha256']=hashlib.sha256(raw.encode()).hexdigest();(child/'receipt.json').write_text(json.dumps(receipt))
        return types.SimpleNamespace(returncode=0,stdout=raw,stderr='')

    def test_scope_collector_bypass_and_source_inventory(self):
        parsed=self.core.parse_command(self.body)
        self.assertEqual(parsed['maximum_writes'],0);self.assertEqual(parsed['provider_http_calls'],0)
        for body in (self.body+' retry',self.body.replace(registration.TARGET_PREFLIGHT_BATCH,registration.TARGET_BATCH),
                     self.body.replace(registration.TARGET_PREFLIGHT_OPERATION,registration.TARGET_PREFLIGHT_OPERATION+'-retry')):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        registration.activate(self.core,parsed)
        self.assertTrue(set(registration.TARGET_PREFLIGHT_SOURCE_FILES).issubset(self.core.FIXED))
        self.assertNotIn('def run_match_primary_candidate(stage):',self.core.REMOTE)
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if (isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name)
                    and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn)):
                self.assertFalse(eval(compile(ast.Expression(node.test),'<guard>','eval'),{},dict(mode=registration.TARGET_PREFLIGHT_MODE)))
        entry=load('target_preflight_stock_entry','scripts/deploy/int_server_executor_anex_secret_transport.py')
        self.assertNotIn(registration.TARGET_PREFLIGHT_MODE,entry.DIRECT_ANEX_MODES)
        self.assertNotIn(registration.TARGET_PREFLIGHT_MODE,entry.SUPPLIER_SLOT_MODES)

    def test_aggregate_result_and_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw)) as call:
                out=ns['run_match_tv_live30_target_preflight'](stage)
                self.assertTrue(out['summary']['schema_complete']);self.assertEqual(out['summary']['database_reads'],1)
                self.assertFalse(out['summary']['safe_to_write_now'])
                self.assertEqual((root/registration.TARGET_PREFLIGHT_OPERATION/'reservation.json').stat().st_mode&0o777,0o600)
                with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_preflight'](stage)
                self.assertEqual(call.call_count,1);self.assertNotIn('ANEX',str(call.call_args.kwargs['env']))

    def test_rejects_untrusted_or_inconsistent_aggregates(self):
        changes=[lambda d:d.update(mapping_writes=1),lambda d:d.update(no_replay=1),lambda d:d.update(safe_to_write_now=0),
            lambda d:d.update(database_reads=2),lambda d:d.update(extra='unsafe'),
            lambda d:d['missing_columns']['catalog_hotels'].append('password'),
            lambda d:d['query_status'].update(cohort=1),lambda d:d['metrics'].update(cohort=-1),
            lambda d:(d.update(schema_complete=False),d['missing_columns']['catalog_hotels'].append('id')),
            lambda d:(d.update(schema_complete=False),d['missing_columns']['catalog_hotels'].append('id'),d['query_status'].update(cohort=True))]
        for mutate in changes:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_preflight'](stage)

    def test_schema_incomplete_requires_all_queries_skipped(self):
        def incomplete(data):
            data['schema_complete']=False;data['missing_columns']['catalog_hotels']=['name']
            data['query_status']={name:False for name in data['query_status']};data['metrics']={name:None for name in data['metrics']}
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,incomplete)):
                out=ns['run_match_tv_live30_target_preflight'](stage)
                self.assertFalse(out['summary']['schema_complete']);self.assertFalse(any(out['summary']['query_status'].values()))

    def test_unknown_read_outcome_stays_consumed(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=subprocess.TimeoutExpired('php',240)) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_tv_live30_target_preflight'](stage)
                with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_preflight'](stage)
                self.assertEqual(call.call_count,1)

    def test_saved_preflight_readback_never_executes_php_or_db(self):
        original_source='12ee0d4961db14a0a1bcddcd41229e5c6dff9aa5'
        body=self.core.PREFIX+SOURCE+' '+registration.TARGET_PREFLIGHT_READBACK_MODE+' '+registration.TARGET_PREFLIGHT_READBACK_OPERATION+' '+registration.TARGET_PREFLIGHT_BATCH
        parsed=self.core.parse_command(body);self.assertEqual(parsed['maximum_writes'],0)
        core=fresh_core();registration.register_parser(core);registration.activate(core,parsed)
        self.assertIn('def run_match_tv_live30_target_preflight_readback(stage):',core.REMOTE)
        for terminal in (False,True):
            with self.subTest(terminal=terminal),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp);ns['operation']=registration.TARGET_PREFLIGHT_READBACK_OPERATION
                ns['payload']=dict(batch=registration.TARGET_PREFLIGHT_BATCH,maximum_writes=0,provider_http_calls=0)
                evidence=root/registration.TARGET_PREFLIGHT_OPERATION;evidence.mkdir()
                reservation=dict(operation=registration.TARGET_PREFLIGHT_OPERATION,source_sha=original_source,
                    batch=registration.TARGET_PREFLIGHT_BATCH,maximum_writes=0,provider_http_calls=0,state='reserved_before_db_read')
                (evidence/'reservation.json').write_text(json.dumps(reservation))
                (evidence/'execution-started.json').write_text(json.dumps(dict(operation=registration.TARGET_PREFLIGHT_OPERATION,source_sha=original_source)))
                if terminal:self.response(dict(env={'MATCH_OPERATION_DIR':str(evidence)}),lambda d:d.update(source_sha=original_source))
                exec(registration.REMOTE_TARGET_PREFLIGHT_READBACK_HANDLER,ns)
                with patch.object(subprocess,'run',side_effect=AssertionError('no PHP or DB process')) as call:
                    out=ns['run_match_tv_live30_target_preflight_readback'](stage)
                    self.assertEqual(out['terminal_verified'],terminal);self.assertFalse(out['original_read_reexecuted'])
                    self.assertEqual(out['database_reads'],0);self.assertEqual(out['database_writes'],0)
                    self.assertEqual(out['preflight'] is not None,terminal)
                    with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_preflight_readback'](stage)
                    call.assert_not_called()

    def test_saved_preflight_readback_rejects_wrong_producer(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp);ns['operation']=registration.TARGET_PREFLIGHT_READBACK_OPERATION
            ns['payload']=dict(batch=registration.TARGET_PREFLIGHT_BATCH,maximum_writes=0,provider_http_calls=0)
            evidence=root/registration.TARGET_PREFLIGHT_OPERATION;evidence.mkdir()
            (evidence/'reservation.json').write_text(json.dumps(dict(operation=registration.TARGET_PREFLIGHT_OPERATION,source_sha=SOURCE)))
            exec(registration.REMOTE_TARGET_PREFLIGHT_READBACK_HANDLER,ns)
            with patch.object(subprocess,'run',side_effect=AssertionError('no PHP or DB process')) as call:
                with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_preflight_readback'](stage)
                call.assert_not_called()

class Live30TargetCatalogV2RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)
        self.body=self.core.PREFIX+SOURCE+' '+registration.TARGET_V2_MODE+' '+registration.TARGET_V2_OPERATION+' '+registration.TARGET_V2_BATCH

    def namespace(self,tmp):
        native=Native110RegistrationTest();native.setUp();ns,stage,root,_=native.namespace(tmp)
        ns['operation']=registration.TARGET_V2_OPERATION
        ns['payload']=dict(batch=registration.TARGET_V2_BATCH,maximum_writes=0,provider_http_calls=0)
        runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v2.php';runner.write_text('<?php // fixture only')
        exec(registration.REMOTE_TARGET_V2_HANDLER,ns);return ns,stage,root

    def response(self,kwargs,mutate=None):
        child=Path(kwargs['env']['MATCH_OPERATION_DIR'])
        data=dict(state='completed_tv_live30_target_catalog_v2',operation=registration.TARGET_V2_OPERATION,source_sha=SOURCE,
            batch=registration.TARGET_V2_BATCH,captured_at_utc='2026-10-01T12:00:00Z',cohort_count=2,row_count=1,held_count=1,
            provider_http_calls=0,database_reads=1,database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True,
            rows=[dict(id=420,name='SHAMS ALAM RESORT',country_id='5',country_name='Египет',region_name='Марса Алам',
                subregion_name=None,category='4',is_active=True,latitude=24.6907006,longitude=35.0835745,
                accepted_samo_ids=['9501'],manual_hold=False,exclusion_hold=False)],
            held=[dict(id=999,reasons=['invalid_coordinates'])])
        if mutate:mutate(data)
        raw=json.dumps(data);(child/'result.json').write_text(raw)
        receipt={k:v for k,v in data.items() if k not in ('rows','held')}
        receipt['result_sha256']=hashlib.sha256(raw.encode()).hexdigest();(child/'receipt.json').write_text(json.dumps(receipt))
        return types.SimpleNamespace(returncode=0,stdout=raw,stderr='')

    def test_fixed_scope_collector_bypass_and_source_inventory(self):
        parsed=self.core.parse_command(self.body);self.assertEqual(parsed['maximum_writes'],0)
        for body in (self.body+' retry',self.body.replace(registration.TARGET_V2_BATCH,registration.TARGET_BATCH),
                     self.body.replace(registration.TARGET_V2_OPERATION,registration.TARGET_V2_OPERATION+'-retry')):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        registration.activate(self.core,parsed)
        self.assertTrue(set(registration.TARGET_V2_SOURCE_FILES).issubset(self.core.FIXED))
        self.assertNotIn('def run_match_primary_candidate(stage):',self.core.REMOTE)
        entry=load('target_v2_stock_entry','scripts/deploy/int_server_executor_anex_secret_transport.py')
        self.assertNotIn(registration.TARGET_V2_MODE,entry.DIRECT_ANEX_MODES);self.assertNotIn(registration.TARGET_V2_MODE,entry.SUPPLIER_SLOT_MODES)

    def test_complete_partition_strict_hold_and_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw)) as call:
                out=ns['run_match_tv_live30_target_catalog_v2'](stage)
                self.assertEqual(out['summary']['cohort_count'],2);self.assertEqual(out['summary']['held'][0]['reasons'],['invalid_coordinates'])
                self.assertFalse(out['summary']['safe_to_write_now']);self.assertEqual(out['summary']['database_reads'],1)
                with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_catalog_v2'](stage)
                self.assertEqual(call.call_count,1);self.assertNotIn('ANEX',str(call.call_args.kwargs['env']))

    def test_rejects_widened_projection_partition_and_hold(self):
        changes=[lambda d:d.update(mapping_writes=1),lambda d:d.update(no_replay=1),lambda d:d.update(safe_to_write_now=0),
            lambda d:d.update(database_reads=2),lambda d:d.update(cohort_count=3),lambda d:d.update(extra='unsafe'),
            lambda d:d['held'][0].update(reasons=[]),lambda d:d['held'][0].update(reasons=['geo_review']),
            lambda d:d['held'][0].update(id=420),lambda d:d['rows'][0].update(latitude=91),
            lambda d:d['rows'][0].update(name='https://secret.example/')]
        for mutate in changes:
            with self.subTest(mutate=mutate),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp)
                with patch.object(subprocess,'run',side_effect=lambda *a,**kw:self.response(kw,mutate)):
                    with self.assertRaises(RuntimeError):ns['run_match_tv_live30_target_catalog_v2'](stage)


SOURCE3_FIXTURE = "{\n  \"schema\": \"match-source3-native-current/1\",\n  \"batch\": \"source3-native-20261001\",\n  \"request\": {\n    \"townfrominc\": 1,\n    \"stateinc\": 5,\n    \"checkin_beg\": \"20261008\",\n    \"checkin_end\": \"20261029\",\n    \"nights\": 7,\n    \"adults\": 2,\n    \"children\": 0,\n    \"currencyinc\": 643,\n    \"packettype\": 0,\n    \"group_by\": 32,\n    \"page\": 1\n  },\n  \"rows\": [\n    {\n      \"catalog_id\": \"163887\",\n      \"operator_id\": 5,\n      \"supplier_namespace\": \"operator_5\",\n      \"target_tv_hotel_id\": 1124,\n      \"target_native_id_for_comparison\": \"8319\",\n      \"expected_country_id\": \"4\",\n      \"catalog_sha256\": \"6d881964267edf37b6a04877a6f5c7da9fe237e7f699dc6ea8766639d7609d99\",\n      \"evidence_sha256\": \"68805396c525fbbebfc77f5de1c9264625c96e2149bf4089c741a70c6051fa46\"\n    },\n    {\n      \"catalog_id\": \"2000057636\",\n      \"operator_id\": 342,\n      \"supplier_namespace\": \"operator_342\",\n      \"target_tv_hotel_id\": 21679,\n      \"target_native_id_for_comparison\": \"24891\",\n      \"expected_country_id\": \"4\",\n      \"catalog_sha256\": \"6d881964267edf37b6a04877a6f5c7da9fe237e7f699dc6ea8766639d7609d99\",\n      \"evidence_sha256\": \"7f19e9a7087358ed130d9a29ab814e9217c6eff4e2461086c34a072d2cf273a9\"\n    },\n    {\n      \"catalog_id\": \"2000073063\",\n      \"operator_id\": 5,\n      \"supplier_namespace\": \"operator_5\",\n      \"target_tv_hotel_id\": 60766,\n      \"target_native_id_for_comparison\": null,\n      \"expected_country_id\": \"4\",\n      \"catalog_sha256\": \"6d881964267edf37b6a04877a6f5c7da9fe237e7f699dc6ea8766639d7609d99\",\n      \"evidence_sha256\": \"0f8a5e6d4314d386f50706bf98be6fb363380d9906853298bbbb8ca033c5e67a\"\n    }\n  ]\n}\n"

class Source3RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();self.old_remote=self.core.REMOTE;self.old_files=list(self.core.FIXED)
        registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.SOURCE3_MODE+' '+registration.SOURCE3_OPERATION+' '+registration.SOURCE3_BATCH

    def activate(self):
        with patch.dict(os.environ,{'GH_TOKEN':'fixture-token'}),patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,self.core.parse_command(self.body()))
            slot.assert_called_once_with('fixture-token')

    def test_exact_scope_and_supplier_slot_after_authorization(self):
        cmd=self.core.parse_command(self.body())
        self.assertEqual(cmd,dict(source_sha=SOURCE,mode=registration.SOURCE3_MODE,operation_id=registration.SOURCE3_OPERATION,
                                  batch=registration.SOURCE3_BATCH,maximum_writes=0,provider_http_calls=3))
        for altered in [self.body()+' 4',self.body().replace('-v1','-v2'),self.body().replace('source3-native-20261001','native110-20260928')]:
            with self.assertRaises(ValueError):self.core.parse_command(altered)
        with patch.dict(os.environ,{},clear=True),patch.object(self.core,'ensure_supplier_slot') as slot:
            with self.assertRaises(ValueError):registration.activate(self.core,cmd)
            slot.assert_not_called()
        self.assertEqual(self.core.REMOTE,self.old_remote)
        with patch.dict(os.environ,{'GH_TOKEN':'fixture-token'}),patch.object(self.core,'ensure_supplier_slot',side_effect=ValueError('supplier_slot_busy')):
            with self.assertRaises(ValueError):registration.activate(self.core,cmd)
        self.assertEqual(self.core.REMOTE,self.old_remote);self.assertEqual(self.core.FIXED,self.old_files)
        self.activate()
        self.assertTrue(set(registration.SOURCE3_SOURCE_FILES).issubset(self.core.FIXED))
        self.assertNotIn('def run_match_primary_candidate(stage):',self.core.REMOTE)
        guards=[n.test for n in ast.walk(ast.parse(self.core.REMOTE)) if isinstance(n,ast.If) and isinstance(n.test,ast.Compare)
                and isinstance(n.test.left,ast.Name) and n.test.left.id=='mode' and isinstance(n.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:self.assertFalse(eval(compile(ast.Expression(guard),'<guard>','eval'),{},dict(mode=registration.SOURCE3_MODE)))
        encoded=base64.b64encode(zlib.compress(self.core.REMOTE.encode(),9)).decode()
        remote_command="python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'"
        self.assertLessEqual(len(remote_command.encode()),65536)

    def test_source3_keeps_owner_canonical_and_current_head_authorization(self):
        body=self.body();event={'issue':{'number':4217},'comment':{'id':123,'body':body,'user':{'id':226193297},'author_association':'OWNER'}}
        def api(path,token):
            if path=='/issues/comments/123':return copy.deepcopy(event['comment'])
            if path=='/git/ref/heads/main':return {'object':{'sha':CONTROL}}
            if path=='/git/ref/heads/'+self.core.FEATURE:return {'object':{'sha':SOURCE}}
            raise AssertionError(path)
        with patch.object(self.core,'api_get',side_effect=api):
            self.assertEqual(self.core.checked_event('fixture',event,CONTROL)['provider_http_calls'],3)
            for change in [lambda e:e['issue'].update(number=3419),lambda e:e['issue'].update(number=1971),
                           lambda e:e['comment']['user'].update(id=1),lambda e:e['comment'].update(author_association='NONE')]:
                bad=copy.deepcopy(event);change(bad)
                with self.assertRaises(ValueError):self.core.checked_event('fixture',bad,CONTROL)
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,'c'*40)

    def namespace(self,tmp):
        home=Path(tmp);project=home/'www/anytoour.ru';project.mkdir(parents=True)
        root=home/'.anytoour-match/operations';root.mkdir(parents=True)
        stage=home/'stage'
        manifest=stage/registration.SOURCE3_SOURCE_FILES[-1];manifest.parent.mkdir(parents=True)
        manifest.write_bytes(SOURCE3_FIXTURE.encode())
        self.assertEqual(hashlib.sha256(manifest.read_bytes()).hexdigest(),registration.SOURCE3_MANIFEST_SHA)
        runner=stage/'scripts/diagnostics/hotel_match_source3_native_current_v1.php';runner.write_text('<?php // fixture')
        ns=dict(home=home,project=project,operation=registration.SOURCE3_OPERATION,source=SOURCE,
                payload=dict(batch=registration.SOURCE3_BATCH,maximum_writes=0,provider_http_calls=3),
                os=os,re=re,json=json,hashlib=hashlib,time=time,subprocess=subprocess)
        nodes=[n for n in ast.parse(self.old_remote).body if isinstance(n,ast.FunctionDef) and n.name in ('fail','safe_file','safe_json')]
        exec(compile(ast.Module(body=nodes,type_ignores=[]),'<stock_helpers>','exec'),ns)
        exec(registration.REMOTE_SOURCE3_HANDLER,ns)
        return ns,stage,root

    def result_fixture(self,held=()):
        specs=[('163887',5,'operator_5',1124,'8319'),('2000057636',342,'operator_342',21679,'24891'),('2000073063',5,'operator_5',60766,None)]
        pre=[];rows=[]
        for cat,op,namespace,target,native in specs:
            hold=['target_occupied'] if cat in held else []
            base=dict(catalog_id=cat,operator_id=op,supplier_namespace=namespace,target_tv_hotel_id=target,
                      target_native_id_for_comparison=native,holds=hold,safe_to_write_now=False)
            pre.append(dict(base,state='hold' if hold else 'eligible_for_source_evidence'))
            native_ids=[] if hold else [native or '9999']
            rows.append(dict(base,state='preflight_hold' if hold else 'captured_single_native',price_rows=0 if hold else 1,
                native_ids=native_ids,references=[] if hold else [dict(private_file='operator-'+str(op)+'-page-1.json',sha256=str(op%10)*64,json_pointer='/PRICES/0')],
                matches_target_native=not hold and native is not None))
        ops=sorted({r['operator_id'] for r in pre if not r['holds']})
        responses=[dict(operator_id=op,catalog_ids=sorted([r['catalog_id'] for r in pre if r['operator_id']==op and not r['holds']],key=int),sha256=str(op%10)*64) for op in ops]
        calls=1+len(ops) if ops else 0
        return dict(schema='match-source3-native-current-result/1',state='completed_source3_native_current',reason=None,
            operation=registration.SOURCE3_OPERATION,source_sha=SOURCE,batch=registration.SOURCE3_BATCH,captured_at_utc='2026-10-04T00:00:00+00:00',
            requested_sources=3,preflight_rows=pre,evidence_rows=rows,responses=responses,provider_http_calls=calls,tourvisor_http_calls=0,
            database_reads=1,database_writes=0,mapping_writes=0,safe_to_write_now=False,acceptance_evaluated=False,no_replay=calls>0)

    def child(self,data,mutate_receipt=None):
        def run(argv,**kw):
            self.assertEqual(argv[-1],'--acquire-source-evidence')
            self.assertEqual(set(kw['env'])-{'PATH','HOME','LANG','LC_ALL'},{'ANYTOUR_ROOT','MATCH_OPERATION_DIR','MATCH_SOURCE_SHA'})
            child=Path(kw['env']['MATCH_OPERATION_DIR']);res=json.loads((child/'reservation.json').read_text())
            self.assertEqual(res['state'],'reserved_before_db_and_provider');self.assertEqual(res['provider_http_calls'],3)
            self.assertEqual(res['maximum_writes'],0)
            marker=child.parents[1]/'source3-native-current-batch-source3-native-20261001.json'
            self.assertEqual(json.loads(marker.read_text()),res)
            raw=json.dumps(data).encode();(child/'result.json').write_bytes(raw)
            keys=('state','operation','source_sha','batch','provider_http_calls','tourvisor_http_calls','database_reads','database_writes','mapping_writes','safe_to_write_now','no_replay')
            receipt={k:data[k] for k in keys};receipt['result_sha256']=hashlib.sha256(raw).hexdigest()
            if mutate_receipt:mutate_receipt(receipt)
            (child/'receipt.json').write_text(json.dumps(receipt))
            counts={}
            for row in data['evidence_rows']:counts[row['state']]=counts.get(row['state'],0)+1
            out=dict(state=data['state'],reason=data['reason'],requested_sources=3,provider_http_calls=data['provider_http_calls'],evidence_states=counts,safe_to_write_now=False)
            return types.SimpleNamespace(returncode=0 if data['state']=='completed_source3_native_current' else 2,stdout=json.dumps(out),stderr='')
        return run

    def test_success_exact_strings_offset_timestamp_and_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp);data=self.result_fixture()
            with patch.dict(os.environ,{'ANEX_API_TOKEN':'secret','DB_PASSWORD':'secret'}),patch.object(subprocess,'run',side_effect=self.child(data)) as call:
                result=ns['run_match_source3'](stage)
                self.assertTrue(result['successful']);self.assertTrue(result['no_replay'])
                self.assertEqual(result['summary']['provider_http_calls'],3)
                self.assertTrue(result['summary']['evidence_rows'][0]['matches_target_native'])
                self.assertNotIn('reason',result['summary'])
                with self.assertRaises(RuntimeError):ns['run_match_source3'](stage)
                self.assertEqual(call.call_count,1)
            marker=root.parent/'source3-native-current-batch-source3-native-20261001.json'
            self.assertEqual(marker.stat().st_mode&0o777,0o600)

    def test_one_hold_leaves_independent_rows_and_all_holds_use_zero_http(self):
        for held in [('163887',),('163887','2000073063'),('163887','2000057636','2000073063')]:
            with self.subTest(held=held),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp);data=self.result_fixture(held)
                with patch.object(subprocess,'run',side_effect=self.child(data)):
                    result=ns['run_match_source3'](stage)
                self.assertTrue(result['successful'])
                self.assertEqual(sum(r['state']=='preflight_hold' for r in result['summary']['evidence_rows']),len(held))
                self.assertTrue(result['no_replay'])

    def test_ambiguous_native_preserves_tokens_and_false_match(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp);data=self.result_fixture();row=data['evidence_rows'][0]
            row.update(native_ids=['804','8319','44562'],price_rows=3,state='captured_ambiguous_native',matches_target_native=False,
                       references=[dict(row['references'][0],json_pointer='/PRICES/'+str(i)) for i in range(3)])
            with patch.object(subprocess,'run',side_effect=self.child(data)):
                result=ns['run_match_source3'](stage)
            self.assertEqual(result['summary']['evidence_rows'][0]['native_ids'],['804','8319','44562'])

    def test_failure_receipt_charges_calls_and_omits_exception_text(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp);data=self.result_fixture()
            data.update(state='terminal_failed_no_replay',reason='fixture_private_exception',evidence_rows=[],responses=[],provider_http_calls=2)
            with patch.object(subprocess,'run',side_effect=self.child(data)) as call:
                result=ns['run_match_source3'](stage)
                self.assertFalse(result['successful']);self.assertEqual(result['summary']['provider_http_calls'],2)
                self.assertNotIn('fixture_private_exception',json.dumps(result))
                with self.assertRaises(RuntimeError):ns['run_match_source3'](stage)
                self.assertEqual(call.call_count,1)

    def test_widened_payload_or_manifest_never_starts_child(self):
        for key,value in [('maximum_writes',1),('provider_http_calls',4),('provider_http_calls',True),('batch','other')]:
            with self.subTest(key=key),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp);ns['payload'][key]=value
                with patch.object(subprocess,'run') as call:
                    with self.assertRaises(RuntimeError):ns['run_match_source3'](stage)
                    call.assert_not_called()
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp);(stage/registration.SOURCE3_SOURCE_FILES[-1]).write_text('{}')
            with patch.object(subprocess,'run') as call:
                with self.assertRaises(RuntimeError):ns['run_match_source3'](stage)
                call.assert_not_called()

    def test_terminal_mutations_are_rejected_and_marker_remains(self):
        changes=[lambda d:d.update(mapping_writes=1),lambda d:d.update(database_writes=True),lambda d:d.update(provider_http_calls=4),
                 lambda d:d.update(acceptance_evaluated=True),lambda d:d.update(safe_to_write_now=0),lambda d:d.update(no_replay=1),
                 lambda d:d.update(source_sha='c'*40),lambda d:d.update(captured_at_utc='2026-13-04T00:00:00+00:00'),
                 lambda d:d['evidence_rows'][0].update(native_ids=[8319]),lambda d:d['evidence_rows'][0].update(matches_target_native=False),
                 lambda d:d['evidence_rows'][0].update(references=[]),
                 lambda d:d['evidence_rows'][0]['references'][0].update(private_file='/tmp/secret'),
                 lambda d:d['responses'][0].update(catalog_ids=['163887']),lambda d:d.update(extra='private')]
        for change in changes:
            with self.subTest(change=change),tempfile.TemporaryDirectory() as tmp:
                ns,stage,root=self.namespace(tmp);data=self.result_fixture();change(data)
                with patch.object(subprocess,'run',side_effect=self.child(data)):
                    with self.assertRaises(RuntimeError):ns['run_match_source3'](stage)
                self.assertTrue((root.parent/'source3-native-current-batch-source3-native-20261001.json').is_file())
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=self.child(self.result_fixture(),lambda r:r.update(result_sha256='0'*64))):
                with self.assertRaises(RuntimeError):ns['run_match_source3'](stage)

    def test_timeout_and_consumed_batch_cannot_retry(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            with patch.object(subprocess,'run',side_effect=subprocess.TimeoutExpired('fixture',240)) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_source3'](stage)
                with self.assertRaises(RuntimeError):ns['run_match_source3'](stage)
                self.assertEqual(call.call_count,1)
        with tempfile.TemporaryDirectory() as tmp:
            ns,stage,root=self.namespace(tmp)
            marker=root.parent/'source3-native-current-batch-source3-native-20261001.json';marker.write_text('already consumed')
            with patch.object(subprocess,'run') as call:
                with self.assertRaises(FileExistsError):ns['run_match_source3'](stage)
                call.assert_not_called();self.assertFalse((root/registration.SOURCE3_OPERATION).exists())

class Intourist4RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core()
        self.old_files=list(self.core.FIXED)
        self.old_remote=self.core.REMOTE
        registration.register_parser(self.core)

    def body(self, source=SOURCE, mode=None, operation=None, batch=None):
        return self.core.PREFIX+' '.join([
            source,
            mode or registration.INTOURIST4_MODE,
            operation or registration.INTOURIST4_OPERATION,
            batch or registration.INTOURIST4_BATCH,
        ])

    def test_exact_fixed_parser_scope(self):
        expected=dict(
            source_sha=SOURCE,
            mode=registration.INTOURIST4_MODE,
            operation_id=registration.INTOURIST4_OPERATION,
            batch=registration.INTOURIST4_BATCH,
            maximum_writes=0,
            provider_http_calls=14,
        )
        self.assertEqual(self.core.parse_command(self.body()),expected)
        bad=[
            self.body(operation=registration.INTOURIST4_OPERATION+'-changed'),
            self.body(batch=registration.INTOURIST4_BATCH+'-changed'),
            self.body(source='bad'),
            self.body()+' extra',
        ]
        for body in bad:
            with self.subTest(body=body),self.assertRaises(ValueError):
                self.core.parse_command(body)

    def test_activation_is_supplier_gated_and_exact(self):
        command=self.core.parse_command(self.body())
        with patch.dict(os.environ,{'GH_TOKEN':'fixture-token'},clear=False),patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,command)
        slot.assert_called_once_with('fixture-token')
        self.assertTrue(set(registration.INTOURIST4_SOURCE_FILES).issubset(set(self.core.FIXED)))
        self.assertEqual(self.core.REMOTE.count('def run_match_intourist4(stage):'),1)
        self.assertEqual(self.core.REMOTE.count("if mode=='match-intourist4-selectors-readonly':"),1)
        self.assertIn("if mode not in ('match-intourist4-selectors-readonly','reconcile',",self.core.REMOTE)
        ast.parse(self.core.REMOTE)

    def test_missing_or_busy_supplier_slot_fails_before_mutation(self):
        command=self.core.parse_command(self.body())
        with patch.dict(os.environ,{},clear=True),self.assertRaises(ValueError):
            registration.activate(self.core,command)
        self.assertEqual(self.core.FIXED,self.old_files)
        self.assertEqual(self.core.REMOTE,self.old_remote)
        with patch.dict(os.environ,{'GH_TOKEN':'fixture-token'},clear=False),patch.object(self.core,'ensure_supplier_slot',side_effect=ValueError('busy')) as slot,self.assertRaises(ValueError):
            registration.activate(self.core,command)
        slot.assert_called_once_with('fixture-token')
        self.assertEqual(self.core.FIXED,self.old_files)
        self.assertEqual(self.core.REMOTE,self.old_remote)



class Intourist4ReadbackRegistrationTest(unittest.TestCase):
    SOURCE_SHA='a82516771252fab0ac4bd079480156c684766e05'

    def setUp(self):
        self.core=fresh_core();self.old_files=list(self.core.FIXED);self.old_remote=self.core.REMOTE
        registration.register_parser(self.core)

    def body(self,operation=None,batch=None):
        return self.core.PREFIX+' '.join([
            self.SOURCE_SHA,registration.INTOURIST4_READBACK_MODE,
            operation or registration.INTOURIST4_READBACK_OPERATION,
            batch or registration.INTOURIST4_READBACK_BATCH,
        ])

    def test_exact_zero_call_parser_and_activation(self):
        command=self.core.parse_command(self.body())
        self.assertEqual(command,dict(source_sha=self.SOURCE_SHA,mode=registration.INTOURIST4_READBACK_MODE,
            operation_id=registration.INTOURIST4_READBACK_OPERATION,batch=registration.INTOURIST4_READBACK_BATCH,
            maximum_writes=0,provider_http_calls=0))
        for body in (self.body(operation='changed'),self.body(batch='changed'),self.body()+' extra'):
            with self.subTest(body=body),self.assertRaises(ValueError):self.core.parse_command(body)
        with patch.dict(os.environ,{},clear=True),patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,command)
        slot.assert_not_called()
        self.assertEqual(self.core.FIXED,self.old_files)
        self.assertEqual(self.core.REMOTE.count('def run_match_intourist4_readback(stage):'),1)
        self.assertEqual(self.core.REMOTE.count("if mode=='match-intourist4-selectors-readback':"),1)
        self.assertIn("if mode not in ('match-intourist4-selectors-readback','reconcile',",self.core.REMOTE)
        ast.parse(self.core.REMOTE)
        handler=registration.REMOTE_INTOURIST4_READBACK_HANDLER
        for forbidden in ('subprocess.run','urlopen(','requests.','curl ','MATCH_SOURCE_ROOT','TOURVISOR_ANEX_JWT'):
            self.assertNotIn(forbidden,handler)

    def test_private_terminal_readback_is_sanitized_and_no_replay(self):
        def fail(reason):raise RuntimeError(reason)
        def safe_file(path,limit):
            return path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=limit
        def safe_json(path,limit):
            self.assertTrue(safe_file(path,limit));return json.loads(path.read_text())
        with tempfile.TemporaryDirectory() as tmp:
            home=Path(tmp);parent=home/'.anytoour-match';root=parent/'operations'
            source_operation='int-tourvisor-match-intourist4-selectors-readonly-20261001-v1'
            source_child=root/source_operation;source_child.mkdir(parents=True)
            reservation={'operation':source_operation,'source_sha':self.SOURCE_SHA,
                'batch':'intourist4-official-context-20261001','maximum_writes':0,
                'provider_http_calls':14,'state':'reserved_before_db_and_provider','reserved_at':1}
            marker_path=parent/'intourist4-selectors-batch-intourist4-official-context-20261001.json'
            marker_path.write_text(json.dumps(reservation));(source_child/'reservation.json').write_text(json.dumps(reservation))
            result={'state':'terminal_failed_no_replay','reason':'fixture-private-reason',
                'provider_http_calls':2,'physical_http_attempts':2,'database_reads':2,
                'database_writes':0,'mapping_writes':0,'returned_edges':0,'no_replay':True,
                'safe_to_write_now':False,'call_counts':{'search_start':2},'edge_state_counts':{},'groups':[]}
            result_path=source_child/'result.json';result_path.write_text(json.dumps(result))
            result_sha=hashlib.sha256(result_path.read_bytes()).hexdigest()
            receipt={'operation':source_operation,'batch':'intourist4-official-context-20261001',
                'source_sha':self.SOURCE_SHA,'state':'terminal_failed_no_replay','result_sha256':result_sha,
                'provider_http_calls':2,'database_reads':2,'database_writes':0,'mapping_writes':0,
                'safe_to_write_now':False,'no_replay':True}
            (source_child/'receipt.json').write_text(json.dumps(receipt))
            ns=dict(os=os,json=json,hashlib=hashlib,re=re,time=time,home=home,project=home/'project',
                operation=registration.INTOURIST4_READBACK_OPERATION,
                payload={'batch':registration.INTOURIST4_READBACK_BATCH,'maximum_writes':0,'provider_http_calls':0},
                source=self.SOURCE_SHA,fail=fail,safe_file=safe_file,safe_json=safe_json)
            exec(registration.REMOTE_INTOURIST4_READBACK_HANDLER,ns)
            lane=ns['run_match_intourist4_readback'](home/'stage');summary=lane['summary']
            self.assertTrue(lane['successful']);self.assertTrue(lane['no_replay'])
            self.assertEqual(summary['source_result_state'],'terminal_failed_no_replay')
            self.assertEqual(summary['source_result_counters']['provider_http_calls'],2)
            self.assertEqual(summary['source_receipt_counters']['provider_http_calls'],2)
            self.assertEqual(summary['source_result_reason_sha256'],hashlib.sha256(b'fixture-private-reason').hexdigest())
            self.assertNotIn('fixture-private-reason',json.dumps(summary))
            self.assertEqual(summary['shape_errors'],[])
            self.assertEqual(summary['provider_http_calls'],0);self.assertEqual(summary['database_writes'],0)
            with self.assertRaises(RuntimeError):
                ns['run_match_intourist4_readback'](home/'stage')


class FunSun2RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();self.old_files=list(self.core.FIXED);self.old_remote=self.core.REMOTE
        registration.register_parser(self.core)

    def body(self,source=SOURCE,operation=None,batch=None):
        return self.core.PREFIX+' '.join([source,registration.FUNSUN2_MODE,
            operation or registration.FUNSUN2_OPERATION,batch or registration.FUNSUN2_BATCH])

    def test_exact_fixed_scope_supplier_gate_and_registration(self):
        command=self.core.parse_command(self.body())
        self.assertEqual(command,dict(source_sha=SOURCE,mode=registration.FUNSUN2_MODE,
            operation_id=registration.FUNSUN2_OPERATION,batch=registration.FUNSUN2_BATCH,
            maximum_writes=0,provider_http_calls=7))
        for body in (self.body(operation='changed'),self.body(batch='changed'),self.body(source='bad'),self.body()+' extra'):
            with self.subTest(body=body),self.assertRaises(ValueError):self.core.parse_command(body)
        with patch.dict(os.environ,{},clear=True),patch.object(self.core,'ensure_supplier_slot') as slot,self.assertRaises(ValueError):
            registration.activate(self.core,command)
        slot.assert_not_called();self.assertEqual(self.core.FIXED,self.old_files);self.assertEqual(self.core.REMOTE,self.old_remote)
        with patch.dict(os.environ,{'GH_TOKEN':'fixture-token'},clear=False),patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,command)
        slot.assert_called_once_with('fixture-token')
        self.assertTrue(set(registration.FUNSUN2_SOURCE_FILES).issubset(set(self.core.FIXED)))
        self.assertEqual(self.core.REMOTE.count('def run_match_funsun2(stage):'),1)
        self.assertEqual(self.core.REMOTE.count("if mode=='match-funsun2-selectors-readonly':"),1)
        self.assertIn("if mode not in ('match-funsun2-selectors-readonly','reconcile',",self.core.REMOTE)
        ast.parse(self.core.REMOTE)
        encoded=base64.b64encode(zlib.compress(self.core.REMOTE.encode(),9)).decode()
        remote_command="python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'"
        self.assertLessEqual(len(remote_command.encode()),65536)

    def result(self):
        rows=[dict(source_catalog_id='2000037261',target_tv_hotel_id=59115,state='eligible',holds=[],safe_to_write_now=False),
              dict(source_catalog_id='2000068203',target_tv_hotel_id=70782,state='eligible',holds=[],safe_to_write_now=False)]
        actions=['group_preflight','search_start','search_status','search_results','tour_detail']
        snapshots=[dict(sequence=i+1,next_http_call=max(1,i),action=action,rows=copy.deepcopy(rows))
                   for i,action in enumerate(actions)]
        edge=dict(source_catalog_id='2000037261',source_native_id='354014',target_tv_hotel_id=59115,
            operator_id=25,namespace='operator_315',operator_tour_count=1,tour_id_sha256='1'*64,
            state='detail_identity_verified',safe_to_write_now=False,tour_detail_http=200,
            operator_link_sha256='2'*64,operator_link_host='b2b.fstravel.com',
            positive_native_candidates=[354014,789636],raw_identity_values=['354014,789636'],
            raw_identity_tokens=['354014','789636'],query_keys=['hotels'],
            link_state='captured_ambiguous_native',matches_source_native=False)
        return dict(schema='match-funsun2-selectors-readonly-result/1',
            operation=registration.FUNSUN2_OPERATION,batch=registration.FUNSUN2_BATCH,source_sha=SOURCE,
            state='completed_read_only',reason=None,captured_at_utc='2026-10-04T08:30:00+00:00',
            requested_rows=2,groups=[dict(group=1,country_id=4,state='completed_read_only',sent=2,
                initial_preflight=copy.deepcopy(rows),search_complete=True,returned_targets=1,edges=[edge])],
            preflight_snapshots=snapshots,provider_http_calls=4,physical_http_attempts=4,database_reads=5,
            call_counts={'search_start':1,'search_status':1,'search_results':1,'tour_detail':1},
            returned_edges=1,edge_state_counts={'detail_identity_verified':1},
            tourvisor_account='TOURVISOR_ANEX_JWT',operator_ids=[25],continue_calls=0,dates_calls=0,
            database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True)

    def validate(self,data):
        raw=json.dumps(data,separators=(',',':')).encode();digest=hashlib.sha256(raw).hexdigest()
        keys=('operation','batch','source_sha','state','provider_http_calls','database_reads',
              'database_writes','mapping_writes','safe_to_write_now','no_replay')
        receipt={key:data[key] for key in keys};receipt['result_sha256']=digest
        def fail(reason):raise RuntimeError(reason)
        ns=dict(re=re,json=json,hashlib=hashlib,fail=fail)
        exec(registration.REMOTE_FUNSUN2_HANDLER,ns)
        return ns['validate_match_funsun2'](data,receipt,digest,SOURCE)

    def test_terminal_validator_preserves_raw_tokens_and_rejects_widening(self):
        summary=self.validate(self.result())
        self.assertEqual(summary['groups'][0]['edges'][0]['raw_identity_tokens'],['354014','789636'])
        self.assertNotIn('reason',summary);self.assertIsNone(summary['reason_sha256'])
        changes=[lambda d:d.update(mapping_writes=1),lambda d:d.update(provider_http_calls=8),
            lambda d:d.update(operator_ids=[25,43]),lambda d:d['groups'][0].update(country_id=1),
            lambda d:d['groups'][0]['edges'][0].update(namespace='operator_342'),
            lambda d:d['groups'][0]['edges'][0].update(operator_link_host='intourist.ru'),
            lambda d:d['groups'][0]['edges'][0].update(raw_identity_tokens=['354014']),
            lambda d:d['groups'][0]['edges'][0].update(positive_native_candidates=[789636,354014]),
            lambda d:d.update(extra='unsafe')]
        for change in changes:
            with self.subTest(change=change):
                data=self.result();change(data)
                with self.assertRaises(RuntimeError):self.validate(data)

    def test_handler_has_exact_manifest_roster_and_no_write_authority(self):
        handler=registration.REMOTE_FUNSUN2_HANDLER
        for required in (registration.FUNSUN2_OPERATION,registration.FUNSUN2_BATCH,registration.FUNSUN2_MANIFEST_SHA,
                "'2000037261':('354014',59115,4)","'2000068203':('789636',70782,4)",
                "'operator_ids':[25]","'namespace']!='operator_315'","maximum_writes']!=0"):
            self.assertIn(required,handler)
        for forbidden in ('database_writes=1','mapping_writes=1','--continue','date-walk','full-drain'):
            self.assertNotIn(forbidden,handler)


class Anex2RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();self.old_files=list(self.core.FIXED);self.old_remote=self.core.REMOTE
        registration.register_parser(self.core)

    def body(self,source=SOURCE,operation=None,batch=None):
        return self.core.PREFIX+' '.join([source,registration.ANEX2_MODE,
            operation or registration.ANEX2_OPERATION,batch or registration.ANEX2_BATCH])

    def test_exact_fixed_scope_supplier_gate_and_registration(self):
        command=self.core.parse_command(self.body())
        self.assertEqual(command,dict(source_sha=SOURCE,mode=registration.ANEX2_MODE,
            operation_id=registration.ANEX2_OPERATION,batch=registration.ANEX2_BATCH,
            maximum_writes=0,provider_http_calls=12))
        for body in (self.body(operation='changed'),self.body(batch='changed'),self.body(source='bad'),self.body()+' extra'):
            with self.subTest(body=body),self.assertRaises(ValueError):self.core.parse_command(body)
        with patch.dict(os.environ,{},clear=True),patch.object(self.core,'ensure_supplier_slot') as slot,self.assertRaises(ValueError):
            registration.activate(self.core,command)
        slot.assert_not_called();self.assertEqual(self.core.FIXED,self.old_files);self.assertEqual(self.core.REMOTE,self.old_remote)
        with patch.dict(os.environ,{'GH_TOKEN':'fixture-token'},clear=False),patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,command)
        slot.assert_called_once_with('fixture-token')
        self.assertTrue(set(registration.ANEX2_SOURCE_FILES).issubset(set(self.core.FIXED)))
        self.assertEqual(self.core.REMOTE.count('def run_match_anex2(stage):'),1)
        self.assertEqual(self.core.REMOTE.count("if mode=='match-anex2-selectors-readonly':"),1)
        self.assertIn("if mode not in ('match-anex2-selectors-readonly','reconcile',",self.core.REMOTE)
        ast.parse(self.core.REMOTE)
        encoded=base64.b64encode(zlib.compress(self.core.REMOTE.encode(),9)).decode()
        remote_command="python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\""+encoded+"\")))'"
        self.assertLessEqual(len(remote_command.encode()),65536)

    def result(self):
        row=dict(source_catalog_id='2000029745',target_tv_hotel_id=159,state='eligible',holds=[],safe_to_write_now=False)
        snapshots=[dict(sequence=i+1,next_http_call=max(1,i),action=action,rows=[copy.deepcopy(row)])
                   for i,action in enumerate(['group_preflight','group_preflight','search_start','search_status','search_results','tour_detail'])]
        edge=dict(source_catalog_id='2000029745',source_native_id='44562',target_tv_hotel_id=159,
            operator_id=13,namespace='operator_5',operator_tour_count=1,tour_id_sha256='1'*64,
            state='detail_identity_verified',safe_to_write_now=False,tour_detail_http=200,
            prior_identity_tokens=['804','44562'],operator_link_sha256='2'*64,operator_link_host='agent.anextour.ru',
            positive_native_candidates=[804,44562],raw_identity_values=['804,44562'],
            raw_identity_tokens=['804','44562'],query_keys=['hotellist'],
            link_state='captured_ambiguous_native',matches_source_native=False)
        return dict(schema='match-anex2-selectors-readonly-result/1',
            operation=registration.ANEX2_OPERATION,batch=registration.ANEX2_BATCH,source_sha=SOURCE,
            state='completed_read_only',reason=None,captured_at_utc='2026-10-04T09:10:00.123456+00:00',
            requested_rows=2,prior_identity_tokens={'159':['804','44562'],'109380':[]},
            groups=[dict(group=1,country_id=4,state='preflight_hold',sent=0,edges=[]),
                    dict(group=2,country_id=1,state='completed_read_only',sent=1,initial_preflight=[copy.deepcopy(row)],search_complete=True,returned_targets=1,edges=[edge])],
            preflight_snapshots=snapshots,provider_http_calls=4,physical_http_attempts=4,database_reads=6,
            call_counts={'search_start':1,'search_status':1,'search_results':1,'tour_detail':1},
            returned_edges=1,edge_state_counts={'detail_identity_verified':1},
            tourvisor_account='TOURVISOR_ANEX_JWT',operator_ids=[13],continue_calls=0,dates_calls=0,
            database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True)

    def validate(self,data):
        raw=json.dumps(data,separators=(',',':')).encode();digest=hashlib.sha256(raw).hexdigest()
        keys=('operation','batch','source_sha','state','provider_http_calls','database_reads',
              'database_writes','mapping_writes','safe_to_write_now','no_replay')
        receipt={key:data[key] for key in keys};receipt['result_sha256']=digest
        def fail(reason):raise RuntimeError(reason)
        ns=dict(re=re,json=json,hashlib=hashlib,fail=fail)
        exec(registration.REMOTE_ANEX2_HANDLER,ns)
        return ns['validate_match_anex2'](data,receipt,digest,SOURCE)

    def test_terminal_validator_preserves_raw_tokens_and_rejects_widening(self):
        summary=self.validate(self.result())
        self.assertEqual(summary['groups'][1]['edges'][0]['raw_identity_tokens'],['804','44562'])
        self.assertNotIn('reason',summary);self.assertIsNone(summary['reason_sha256'])
        changes=[lambda d:d.update(mapping_writes=1),lambda d:d.update(provider_http_calls=13),
            lambda d:d.update(operator_ids=[13,43]),lambda d:d['groups'][1].update(country_id=4),
            lambda d:d['groups'][1]['edges'][0].update(namespace='operator_342'),
            lambda d:d['groups'][1]['edges'][0].update(operator_link_host='intourist.ru'),
            lambda d:d['groups'][1]['edges'][0].update(raw_identity_tokens=['44562']),
            lambda d:d['groups'][1]['edges'][0].update(positive_native_candidates=[44562,804]),
            lambda d:d.update(extra='unsafe')]
        for change in changes:
            with self.subTest(change=change):
                data=self.result();change(data)
                with self.assertRaises(RuntimeError):self.validate(data)

    def test_handler_has_exact_manifest_roster_and_no_write_authority(self):
        handler=registration.REMOTE_ANEX2_HANDLER
        for required in (registration.ANEX2_OPERATION,registration.ANEX2_BATCH,registration.ANEX2_MANIFEST_SHA,
                "'2000109038':('43661',109380,4)","'2000029745':('44562',159,1)",
                "'operator_ids':[13]","'namespace']!='operator_5'","maximum_writes']!=0"):
            self.assertIn(required,handler)
        for forbidden in ('database_writes=1','mapping_writes=1','--continue','date-walk','full-drain'):
            self.assertNotIn(forbidden,handler)

    def test_signed_tokens_and_prior_ambiguity_cannot_be_promoted(self):
        data=self.result();edge=data['groups'][1]['edges'][0]
        edge.update(raw_identity_values=['-804,44562'],raw_identity_tokens=['-804','44562'],positive_native_candidates=[44562])
        self.assertEqual(self.validate(data)['groups'][1]['edges'][0]['raw_identity_tokens'],['-804','44562'])
        edge['matches_source_native']=True
        with self.assertRaises(RuntimeError):self.validate(data)
        data=self.result();data['prior_identity_tokens']['159']=['44562']
        with self.assertRaises(RuntimeError):self.validate(data)
        data=self.result();data['groups'][1]['edges'][0]['prior_identity_tokens']=['44562']
        with self.assertRaises(RuntimeError):self.validate(data)



class ExactTourvisorRemoteGuardTest(unittest.TestCase):
    SCOPES = (
        (registration.INTOURIST4_MODE, registration.INTOURIST4_OPERATION,
         registration.INTOURIST4_BATCH, 'intourist4'),
        (registration.INTOURIST4_READBACK_MODE, registration.INTOURIST4_READBACK_OPERATION,
         registration.INTOURIST4_READBACK_BATCH, 'intourist4_readback'),
        (registration.FUNSUN2_MODE, registration.FUNSUN2_OPERATION,
         registration.FUNSUN2_BATCH, 'funsun2'),
        (registration.ANEX2_MODE, registration.ANEX2_OPERATION,
         registration.ANEX2_BATCH, 'anex2'),
    )

    @staticmethod
    def first_guard(remote):
        block = next(node for node in ast.parse(remote).body if isinstance(node, ast.Try))
        guard = block.body[0]
        assert isinstance(guard, ast.If)
        assert ast.unparse(guard.body[0]) == "fail('operation_invalid')"
        return guard

    def run_guard(self, remote, mode, operation, batch):
        def fail(reason):
            raise ValueError(reason)
        namespace = dict(re=re, mode=mode, operation=operation,
                         payload={'batch': batch}, fail=fail)
        # Execute only the emitted first guard: no filesystem, SSH, DB or HTTP.
        guard = self.first_guard(remote)
        exec(compile(ast.Module(body=[guard], type_ignores=[]), '<remote-first-guard>', 'exec'),
             namespace)

    def test_registered_exact_triples_pass_emitted_remote_guard(self):
        for mode, operation, batch, flag in self.SCOPES:
            with self.subTest(mode=mode):
                core = fresh_core()
                registration.register_parser(core)
                command = core.parse_command(core.PREFIX + ' '.join([SOURCE, mode, operation, batch]))
                with patch.dict(os.environ, {'GH_TOKEN': 'fixture'}), \
                        patch.object(core, 'ensure_supplier_slot'):
                    registration.activate(core, command)
                self.run_guard(core.REMOTE, mode, operation, batch)
                self.assertLess(core.REMOTE.index("fail('operation_invalid')"),
                                core.REMOTE.index('private.mkdir('))
                self.assertIn("fail('operation_exists_no_replay')", core.REMOTE)

    def test_emitted_guard_rejects_wrong_mode_operation_or_batch(self):
        for mode, operation, batch, flag in self.SCOPES:
            remote = registration.remote_with_primary(fresh_core(), **{flag: True})
            bad_triples = [
                ('match-coverage', operation, batch),
                (mode, operation.replace('-v1', '-v2'), batch),
                (mode, 'int-tourvisor-fixture-arbitrary-v1', batch),
                (mode, 'int-andromeda-fixture-arbitrary-v1', batch),
                (mode, operation, batch + '-other'),
                (mode, operation, None),
            ]
            bad_triples.extend((mode, other_op, other_batch)
                               for other_mode, other_op, other_batch, _ in self.SCOPES
                               if other_mode != mode)
            for triple in bad_triples:
                with self.subTest(mode=mode, triple=triple), self.assertRaisesRegex(ValueError, 'operation_invalid'):
                    self.run_guard(remote, *triple)

    def test_legacy_remote_operation_guard_stays_identical(self):
        core = fresh_core()
        old = ast.dump(self.first_guard(core.REMOTE))
        for kwargs in ({}, {'source3': True}, {'native': True}, {'guarded': True}):
            remote = registration.remote_with_primary(core, **kwargs)
            self.assertEqual(ast.dump(self.first_guard(remote)), old)
            self.run_guard(remote, 'fixture', 'int-andromeda-fixture-legacy-v1', None)
            self.run_guard(remote, 'fixture', 'int-anex-fixture-legacy-v1', None)
            with self.assertRaisesRegex(ValueError, 'operation_invalid'):
                self.run_guard(remote, 'fixture', 'int-tourvisor-fixture-legacy-v1', None)

    def test_exact_guard_registration_fails_closed_on_anchor_drift(self):
        for _, _, _, flag in self.SCOPES:
            for mutation in ('missing', 'duplicate'):
                core = fresh_core()
                anchor = "    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):\n"
                core.REMOTE = (core.REMOTE.replace(anchor, anchor.replace('8,80', '8,81'))
                               if mutation == 'missing' else core.REMOTE + anchor)
                with self.subTest(flag=flag, mutation=mutation), \
                        self.assertRaisesRegex(ValueError, 'primary_operation_guard_source_drift'):
                    registration.remote_with_primary(core, **{flag: True})



class UserSearchDeltaRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.DELTA_MODE+' '+registration.DELTA_OPERATION+' '+registration.DELTA_BATCH

    def test_exact_readonly_scope_and_cross_scope_rejected(self):
        value=self.core.parse_command(self.body())
        self.assertEqual(value,dict(source_sha=SOURCE,mode=registration.DELTA_MODE,operation_id=registration.DELTA_OPERATION,batch=registration.DELTA_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in [self.body().replace(registration.DELTA_OPERATION,registration.ANEX2_OPERATION),self.body().replace(registration.DELTA_BATCH,registration.NATIVE_BATCH),self.body()+' 1']:
            with self.assertRaises(ValueError):self.core.parse_command(body)

    def test_emitted_remote_and_staged_paths_preserve_stock_executor(self):
        before=self.core.REMOTE;fixed=list(self.core.FIXED)
        registration.activate(self.core,self.core.parse_command(self.body()))
        ast.parse(self.core.REMOTE)
        self.assertIn('def run_match_user_delta(stage):',self.core.REMOTE)
        self.assertIn("if mode=='match-user-search-delta-readonly':",self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.DELTA_SOURCE_FILES))
        self.assertIn('os.O_WRONLY|os.O_CREAT|os.O_EXCL',registration.REMOTE_DELTA_HANDLER)
        self.assertIn('os.fsync(fd)',registration.REMOTE_DELTA_HANDLER)
        self.assertIn('delta_reservation_readback',registration.REMOTE_DELTA_HANDLER)
        self.assertIn('validate_source(data)',registration.REMOTE_DELTA_HANDLER)

    def fixture(self):
        data=dict(schema='match-user-search-delta-readonly-result/1',operation=registration.DELTA_OPERATION,batch=registration.DELTA_BATCH,source_sha=SOURCE,provider_http_calls=0,physical_http_attempts=0,database_reads=1,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,acceptance_evaluated=False,global_uniqueness_evaluated=False,no_replay=True,operator_ids=[13,18,25,43],window_civil={'lower_exclusive':'2026-10-02 12:46:00','upper_inclusive':'2026-10-03 09:23:17'},private_input_sha256='c'*64,state='completed_read_only_delta')
        keys=['operation','batch','source_sha','state','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads','database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay']
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_DELTA_HANDLER,env)
        return env['validate_match_user_delta'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

    def test_digest_receipt_source_bindings(self):
        data,receipt=self.fixture();self.assertEqual(self.validate(data,receipt),data)
        for change in [('source_sha','e'*40),('result_sha256','f'*64),('private_input_sha256','f'*64)]:
            d,r=copy.deepcopy(data),copy.deepcopy(receipt);r[change[0]]=change[1]
            with self.assertRaises(RuntimeError):self.validate(d,r)

    def test_no_authority_promotion_or_boolean_counter(self):
        for key,value in [('database_writes',1),('provider_http_calls',1),('written',1),('accepted',True),('database_reads',True),('safe_to_write_now',True),('no_replay',False)]:
            d,r=self.fixture();d[key]=value
            if key in r:r[key]=value
            with self.assertRaises(RuntimeError):self.validate(d,r)

    def test_source_validator_failure_cannot_be_ignored(self):
        d,r=self.fixture()
        with self.assertRaises(RuntimeError):self.validate(d,r,lambda x:(_ for _ in ()).throw(ValueError('unsafe_row')))
        r['unknown_field']=0
        with self.assertRaises(RuntimeError):self.validate(d,r)

class Bg8UnexportedFieldsRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.BF8_MODE+' '+registration.BF8_OPERATION+' '+registration.BF8_BATCH

    def test_exact_readonly_scope_and_cross_scope_rejected(self):
        value=self.core.parse_command(self.body())
        self.assertEqual(value,dict(source_sha=SOURCE,mode=registration.BF8_MODE,operation_id=registration.BF8_OPERATION,batch=registration.BF8_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in [self.body().replace(registration.BF8_OPERATION,registration.ANEX2_OPERATION),self.body().replace(registration.BF8_BATCH,registration.NATIVE_BATCH),self.body()+' 1']:
            with self.assertRaises(ValueError):self.core.parse_command(body)

    def test_emitted_remote_and_staged_paths_preserve_stock_executor(self):
        before=self.core.REMOTE;fixed=list(self.core.FIXED)
        registration.activate(self.core,self.core.parse_command(self.body()))
        ast.parse(self.core.REMOTE)
        self.assertIn('def run_match_bg8_fields(stage):',self.core.REMOTE)
        self.assertIn("if mode=='match-bg8-unexported-fields-readonly':",self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.BF8_SOURCE_FILES))
        self.assertIn('os.O_WRONLY|os.O_CREAT|os.O_EXCL',registration.REMOTE_BF8_HANDLER)
        self.assertIn('os.fsync(fd)',registration.REMOTE_BF8_HANDLER)
        self.assertIn('bg8_fields_reservation_readback',registration.REMOTE_BF8_HANDLER)
        self.assertIn('validate_source(data)',registration.REMOTE_BF8_HANDLER)
        collectors=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                collectors.append(eval(compile(ast.Expression(node.test),'<collector>','eval'),{},dict(mode=registration.BF8_MODE)))
        self.assertEqual(collectors,[False,False])
        self.assertIn('reserved_before_retained_read',registration.REMOTE_BF8_HANDLER)
        self.assertNotIn('def run_match_user_delta(stage):',self.core.REMOTE)


    def fixture(self):
        data=dict(schema='match-bg8-unexported-fields-readonly-result/1',operation=registration.BF8_OPERATION,batch=registration.BF8_BATCH,source_sha=SOURCE,provider_http_calls=0,physical_http_attempts=0,database_reads=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,acceptance_evaluated=False,global_uniqueness_evaluated=False,no_replay=True,operator_ids=[18],requested_rows=8,private_input_sha256='c'*64,state='completed_read_only_bg8_fields')
        keys=['operation','batch','source_sha','state','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads','database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay']
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_BF8_HANDLER,env)
        return env['validate_match_bg8_fields'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

    def test_digest_receipt_source_bindings(self):
        data,receipt=self.fixture();self.assertEqual(self.validate(data,receipt),data)
        for change in [('source_sha','e'*40),('result_sha256','f'*64),('private_input_sha256','f'*64)]:
            d,r=copy.deepcopy(data),copy.deepcopy(receipt);r[change[0]]=change[1]
            with self.assertRaises(RuntimeError):self.validate(d,r)

    def test_no_authority_promotion_or_boolean_counter(self):
        for key,value in [('database_writes',1),('provider_http_calls',1),('written',1),('accepted',True),('database_reads',True),('safe_to_write_now',True),('no_replay',False)]:
            d,r=self.fixture();d[key]=value
            if key in r:r[key]=value
            with self.assertRaises(RuntimeError):self.validate(d,r)

    def test_source_validator_failure_cannot_be_ignored(self):
        d,r=self.fixture()
        with self.assertRaises(RuntimeError):self.validate(d,r,lambda x:(_ for _ in ()).throw(ValueError('unsafe_row')))
        r['unknown_field']=0
        with self.assertRaises(RuntimeError):self.validate(d,r)

    def test_child_parent_is_durable_before_read_and_timeout_stays_consumed(self):
        with tempfile.TemporaryDirectory() as tmp:
            home=Path(tmp)/'home';ops=home/'.anytoour-match'/'operations';ops.mkdir(parents=True)
            project=home/'www'/'anytoour.ru';project.mkdir(parents=True)
            stage=Path(tmp)/'stage';folder=stage/'scripts'/'diagnostics';(folder/'fixtures').mkdir(parents=True)
            (folder/'hotel_match_bg8_unexported_fields_readonly_v1.py').write_text('pass\n')
            raw=b'{}';(folder/'fixtures'/'hotel_match_bg8_unexported_fields_readonly_v1.json').write_bytes(raw)
            code=registration.REMOTE_BF8_HANDLER.replace(registration.BF8_MANIFEST_SHA,hashlib.sha256(raw).hexdigest())
            env=dict(home=home,project=project,operation=registration.BF8_OPERATION,source=SOURCE,payload=dict(batch=registration.BF8_BATCH,maximum_writes=0,provider_http_calls=0),os=os,json=json,hashlib=hashlib,subprocess=subprocess,safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and p.stat().st_size<=limit,fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
            exec(code,env);synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kw):
                self.assertIn(ops,synced)
                self.assertNotIn('GH_TOKEN',kw['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):env['run_match_bg8_fields'](stage)
                self.assertTrue((home/'.anytoour-match'/'bg8-unexported-fields-batch-20261004.json').is_file())
                self.assertTrue((ops/registration.BF8_OPERATION/'reservation.json').is_file())
                with self.assertRaises(RuntimeError):env['run_match_bg8_fields'](stage)
                self.assertEqual(call.call_count,1)

if __name__=='__main__':unittest.main()
