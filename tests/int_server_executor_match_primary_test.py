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
import shutil
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

class Bg8PinBindingsRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.BP8_MODE+' '+registration.BP8_OPERATION+' '+registration.BP8_BATCH

    def test_exact_readonly_scope_and_cross_scope_rejected(self):
        value=self.core.parse_command(self.body())
        self.assertEqual(value,dict(source_sha=SOURCE,mode=registration.BP8_MODE,operation_id=registration.BP8_OPERATION,batch=registration.BP8_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in [self.body().replace(registration.BP8_OPERATION,registration.ANEX2_OPERATION),self.body().replace(registration.BP8_BATCH,registration.NATIVE_BATCH),self.body()+' 1']:
            with self.assertRaises(ValueError):self.core.parse_command(body)

    def test_emitted_remote_and_staged_paths_preserve_stock_executor(self):
        before=self.core.REMOTE;fixed=list(self.core.FIXED)
        registration.activate(self.core,self.core.parse_command(self.body()))
        ast.parse(self.core.REMOTE)
        self.assertIn('def run_match_bg8_pins(stage):',self.core.REMOTE)
        self.assertIn("if mode=='match-bg8-pin-bindings-readonly':",self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.BP8_SOURCE_FILES))
        self.assertIn('os.O_WRONLY|os.O_CREAT|os.O_EXCL',registration.REMOTE_BP8_HANDLER)
        self.assertIn('os.fsync(fd)',registration.REMOTE_BP8_HANDLER)
        self.assertIn('bg8_pins_reservation_readback',registration.REMOTE_BP8_HANDLER)
        self.assertIn('validate_source(data)',registration.REMOTE_BP8_HANDLER)
        collectors=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                collectors.append(eval(compile(ast.Expression(node.test),'<collector>','eval'),{},dict(mode=registration.BP8_MODE)))
        self.assertEqual(collectors,[False,False])
        self.assertIn('reserved_before_retained_read',registration.REMOTE_BP8_HANDLER)
        self.assertNotIn('def run_match_user_delta(stage):',self.core.REMOTE)


    def fixture(self):
        data=dict(schema='match-bg8-pin-bindings-readonly-result/1',operation=registration.BP8_OPERATION,batch=registration.BP8_BATCH,source_sha=SOURCE,provider_http_calls=0,physical_http_attempts=0,database_reads=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,acceptance_evaluated=False,global_uniqueness_evaluated=False,no_replay=True,operator_ids=[18],requested_rows=8,private_input_sha256='c'*64,state='completed_read_only_bg8_pins')
        keys=['operation','batch','source_sha','state','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads','database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay']
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_BP8_HANDLER,env)
        return env['validate_match_bg8_pins'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

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
            (folder/'hotel_match_bg8_pin_bindings_readonly_v1.py').write_text('pass\n')
            raw=b'{}';(folder/'fixtures'/'hotel_match_bg8_pin_bindings_readonly_v1.json').write_bytes(raw)
            code=registration.REMOTE_BP8_HANDLER.replace(registration.BP8_MANIFEST_SHA,hashlib.sha256(raw).hexdigest())
            env=dict(home=home,project=project,operation=registration.BP8_OPERATION,source=SOURCE,payload=dict(batch=registration.BP8_BATCH,maximum_writes=0,provider_http_calls=0),os=os,json=json,hashlib=hashlib,subprocess=subprocess,safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and p.stat().st_size<=limit,fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
            exec(code,env);synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kw):
                self.assertIn(ops,synced)
                self.assertNotIn('GH_TOKEN',kw['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):env['run_match_bg8_pins'](stage)
                self.assertTrue((home/'.anytoour-match'/'bg8-pin-bindings-batch-20261004.json').is_file())
                self.assertTrue((ops/registration.BP8_OPERATION/'reservation.json').is_file())
                with self.assertRaises(RuntimeError):env['run_match_bg8_pins'](stage)
                self.assertEqual(call.call_count,1)

class Bg5UnexportedFieldsRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.BF5_MODE+' '+registration.BF5_OPERATION+' '+registration.BF5_BATCH

    def test_exact_readonly_scope_and_cross_scope_rejected(self):
        value=self.core.parse_command(self.body())
        self.assertEqual(value,dict(source_sha=SOURCE,mode=registration.BF5_MODE,operation_id=registration.BF5_OPERATION,batch=registration.BF5_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in [self.body().replace(registration.BF5_OPERATION,registration.ANEX2_OPERATION),self.body().replace(registration.BF5_BATCH,registration.NATIVE_BATCH),self.body()+' 1']:
            with self.assertRaises(ValueError):self.core.parse_command(body)

    def test_emitted_remote_and_staged_paths_preserve_stock_executor(self):
        before=self.core.REMOTE;fixed=list(self.core.FIXED)
        registration.activate(self.core,self.core.parse_command(self.body()))
        ast.parse(self.core.REMOTE)
        self.assertIn('def run_match_bg5_fields(stage):',self.core.REMOTE)
        self.assertIn("if mode=='match-bg5-unexported-fields-readonly':",self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.BF5_SOURCE_FILES))
        self.assertIn('os.O_WRONLY|os.O_CREAT|os.O_EXCL',registration.REMOTE_BF5_HANDLER)
        self.assertIn('os.fsync(fd)',registration.REMOTE_BF5_HANDLER)
        self.assertIn('bg5_fields_reservation_readback',registration.REMOTE_BF5_HANDLER)
        self.assertIn('validate_source(data)',registration.REMOTE_BF5_HANDLER)
        collectors=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                collectors.append(eval(compile(ast.Expression(node.test),'<collector>','eval'),{},dict(mode=registration.BF5_MODE)))
        self.assertEqual(collectors,[False,False])
        self.assertIn('reserved_before_retained_read',registration.REMOTE_BF5_HANDLER)
        self.assertNotIn('def run_match_user_delta(stage):',self.core.REMOTE)


    def fixture(self):
        data=dict(schema='match-bg5-unexported-fields-readonly-result/1',operation=registration.BF5_OPERATION,batch=registration.BF5_BATCH,source_sha=SOURCE,provider_http_calls=0,physical_http_attempts=0,database_reads=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,acceptance_evaluated=False,global_uniqueness_evaluated=False,no_replay=True,operator_ids=[18],requested_rows=5,private_input_sha256='c'*64,state='completed_read_only_bg5_fields')
        keys=['operation','batch','source_sha','state','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads','database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay']
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_BF5_HANDLER,env)
        return env['validate_match_bg5_fields'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

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
            (folder/'hotel_match_bg5_unexported_fields_readonly_v1.py').write_text('pass\n')
            raw=b'{}';(folder/'fixtures'/'hotel_match_bg5_unexported_fields_readonly_v1.json').write_bytes(raw)
            code=registration.REMOTE_BF5_HANDLER.replace(registration.BF5_MANIFEST_SHA,hashlib.sha256(raw).hexdigest())
            env=dict(home=home,project=project,operation=registration.BF5_OPERATION,source=SOURCE,payload=dict(batch=registration.BF5_BATCH,maximum_writes=0,provider_http_calls=0),os=os,json=json,hashlib=hashlib,subprocess=subprocess,safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and p.stat().st_size<=limit,fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
            exec(code,env);synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kw):
                self.assertIn(ops,synced)
                self.assertNotIn('GH_TOKEN',kw['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):env['run_match_bg5_fields'](stage)
                self.assertTrue((home/'.anytoour-match'/'bg5-unexported-fields-batch-20261004.json').is_file())
                self.assertTrue((ops/registration.BF5_OPERATION/'reservation.json').is_file())
                with self.assertRaises(RuntimeError):env['run_match_bg5_fields'](stage)
                self.assertEqual(call.call_count,1)

class Nonbg7UnexportedFieldsRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.NF7_MODE+' '+registration.NF7_OPERATION+' '+registration.NF7_BATCH

    def test_exact_readonly_scope_and_cross_scope_rejected(self):
        value=self.core.parse_command(self.body())
        self.assertEqual(value,dict(source_sha=SOURCE,mode=registration.NF7_MODE,operation_id=registration.NF7_OPERATION,batch=registration.NF7_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in [self.body().replace(registration.NF7_OPERATION,registration.ANEX2_OPERATION),self.body().replace(registration.NF7_BATCH,registration.NATIVE_BATCH),self.body()+' 1']:
            with self.assertRaises(ValueError):self.core.parse_command(body)

    def test_emitted_remote_and_staged_paths_preserve_stock_executor(self):
        before=self.core.REMOTE;fixed=list(self.core.FIXED)
        registration.activate(self.core,self.core.parse_command(self.body()))
        ast.parse(self.core.REMOTE)
        self.assertIn('def run_match_nonbg7_fields(stage):',self.core.REMOTE)
        self.assertIn("if mode=='match-nonbg7-unexported-fields-readonly':",self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.NF7_SOURCE_FILES))
        self.assertIn('os.O_WRONLY|os.O_CREAT|os.O_EXCL',registration.REMOTE_NF7_HANDLER)
        self.assertIn('os.fsync(fd)',registration.REMOTE_NF7_HANDLER)
        self.assertIn('nonbg7_fields_reservation_readback',registration.REMOTE_NF7_HANDLER)
        self.assertIn('validate_source(data)',registration.REMOTE_NF7_HANDLER)
        collectors=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                collectors.append(eval(compile(ast.Expression(node.test),'<collector>','eval'),{},dict(mode=registration.NF7_MODE)))
        self.assertEqual(collectors,[False,False])
        self.assertIn('reserved_before_retained_read',registration.REMOTE_NF7_HANDLER)
        self.assertNotIn('def run_match_user_delta(stage):',self.core.REMOTE)


    def fixture(self):
        data=dict(schema='match-nonbg7-unexported-fields-readonly-result/1',operation=registration.NF7_OPERATION,batch=registration.NF7_BATCH,source_sha=SOURCE,provider_http_calls=0,physical_http_attempts=0,database_reads=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,acceptance_evaluated=False,global_uniqueness_evaluated=False,no_replay=True,operator_ids=[13,25,43],requested_rows=7,requested_sources=6,private_input_sha256='c'*64,state='completed_read_only_nonbg7_fields')
        keys=['operation','batch','source_sha','state','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads','database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay']
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_NF7_HANDLER,env)
        return env['validate_match_nonbg7_fields'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

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
            (folder/'hotel_match_nonbg7_unexported_fields_readonly_v1.py').write_text('pass\n')
            raw=b'{}';(folder/'fixtures'/'hotel_match_nonbg7_unexported_fields_readonly_v1.json').write_bytes(raw)
            code=registration.REMOTE_NF7_HANDLER.replace(registration.NF7_MANIFEST_SHA,hashlib.sha256(raw).hexdigest())
            env=dict(home=home,project=project,operation=registration.NF7_OPERATION,source=SOURCE,payload=dict(batch=registration.NF7_BATCH,maximum_writes=0,provider_http_calls=0),os=os,json=json,hashlib=hashlib,subprocess=subprocess,safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and p.stat().st_size<=limit,fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
            exec(code,env);synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kw):
                self.assertIn(ops,synced)
                self.assertNotIn('GH_TOKEN',kw['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):env['run_match_nonbg7_fields'](stage)
                self.assertTrue((home/'.anytoour-match'/'nonbg7-unexported-fields-batch-20261004.json').is_file())
                self.assertTrue((ops/registration.NF7_OPERATION/'reservation.json').is_file())
                with self.assertRaises(RuntimeError):env['run_match_nonbg7_fields'](stage)
                self.assertEqual(call.call_count,1)


class Nonbg5RetainedURLPathsRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.NU5_MODE+' '+registration.NU5_OPERATION+' '+registration.NU5_BATCH

    def test_exact_readonly_scope_and_cross_scope_rejected(self):
        value=self.core.parse_command(self.body())
        self.assertEqual(value,dict(source_sha=SOURCE,mode=registration.NU5_MODE,operation_id=registration.NU5_OPERATION,batch=registration.NU5_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in [self.body().replace(registration.NU5_OPERATION,registration.ANEX2_OPERATION),self.body().replace(registration.NU5_BATCH,registration.NATIVE_BATCH),self.body()+' 1']:
            with self.assertRaises(ValueError):self.core.parse_command(body)

    def test_emitted_remote_and_staged_paths_preserve_stock_executor(self):
        before=self.core.REMOTE;fixed=list(self.core.FIXED)
        registration.activate(self.core,self.core.parse_command(self.body()))
        ast.parse(self.core.REMOTE)
        self.assertIn('def run_match_nonbg5_url_paths(stage):',self.core.REMOTE)
        self.assertIn("if mode=='match-nonbg5-retained-url-paths-readonly':",self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.NU5_SOURCE_FILES))
        self.assertIn('os.O_WRONLY|os.O_CREAT|os.O_EXCL',registration.REMOTE_NU5_HANDLER)
        self.assertIn('os.fsync(fd)',registration.REMOTE_NU5_HANDLER)
        self.assertIn('nonbg5_url_paths_reservation_readback',registration.REMOTE_NU5_HANDLER)
        self.assertIn('validate_source(data)',registration.REMOTE_NU5_HANDLER)
        collectors=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                collectors.append(eval(compile(ast.Expression(node.test),'<collector>','eval'),{},dict(mode=registration.NU5_MODE)))
        self.assertEqual(collectors,[False,False])
        self.assertIn('reserved_before_retained_read',registration.REMOTE_NU5_HANDLER)
        self.assertNotIn('def run_match_user_delta(stage):',self.core.REMOTE)


    def fixture(self):
        data=dict(schema='match-nonbg5-retained-url-paths-readonly-result/1',operation=registration.NU5_OPERATION,batch=registration.NU5_BATCH,source_sha=SOURCE,provider_http_calls=0,physical_http_attempts=0,database_reads=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,acceptance_evaluated=False,global_uniqueness_evaluated=False,no_replay=True,operator_ids=[13,25,43],requested_rows=5,requested_sources=4,private_input_sha256='c'*64,state='completed_read_only_nonbg5_url_paths')
        keys=['operation','batch','source_sha','state','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads','database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay']
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_NU5_HANDLER,env)
        return env['validate_match_nonbg5_url_paths'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

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
            (folder/'hotel_match_nonbg5_retained_url_paths_readonly_v1.py').write_text('pass\n')
            raw=b'{}';(folder/'fixtures'/'hotel_match_nonbg5_retained_url_paths_readonly_v1.json').write_bytes(raw)
            code=registration.REMOTE_NU5_HANDLER.replace(registration.NU5_MANIFEST_SHA,hashlib.sha256(raw).hexdigest())
            env=dict(home=home,project=project,operation=registration.NU5_OPERATION,source=SOURCE,payload=dict(batch=registration.NU5_BATCH,maximum_writes=0,provider_http_calls=0),os=os,json=json,hashlib=hashlib,subprocess=subprocess,safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and p.stat().st_size<=limit,fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
            exec(code,env);synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kw):
                self.assertIn(ops,synced)
                self.assertNotIn('GH_TOKEN',kw['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):env['run_match_nonbg5_url_paths'](stage)
                self.assertTrue((home/'.anytoour-match'/'nonbg5-retained-url-paths-batch-20261004.json').is_file())
                self.assertTrue((ops/registration.NU5_OPERATION/'reservation.json').is_file())
                with self.assertRaises(RuntimeError):env['run_match_nonbg5_url_paths'](stage)
                self.assertEqual(call.call_count,1)


# Frozen public feature bytes for real control-to-source CLI integration; no private runtime inputs.
FROZEN_URL5_READER = '#!/usr/bin/env python3\n"""First URL-path projection of five already captured NONBG7 values; metadata only."""\nimport collections\nimport datetime as dt\nimport hashlib\nimport json\nimport os\nimport pathlib\nimport re\nimport stat\nimport sys\nimport urllib.parse\n\nOP = "int-andromeda-match-nonbg5-retained-url-paths-20261004-v1"\nBATCH = "nonbg5-retained-url-paths-20261004"\nMODE = "match-nonbg5-retained-url-paths-readonly"\nMANIFEST_SHA = "a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269"\nOLD_OP = "int-andromeda-match-nonbg7-unexported-fields-20261004-v1"\nOLD_BATCH = "nonbg7-unexported-fields-20261004"\nOLD_SOURCE = "bcd25c42a899071b09abce06890b2ce0c3eeb465"\nOLD_INPUT_SHA = "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\nOLD_RESULT_SHA = "8336b824bf48df8771685c00d744bb8a720fa062addb1d71a93ec23c4e394604"\nROW_KEYS = ("catalog_id", "source_namespace", "source_native_id", "target_tv_hotel_id", "target_operator_id")\nNO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")\nFALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")\nRECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", *NO_EFFECTS, *FALSE_FLAGS, "no_replay")\nSTATES = ("completed_read_only_nonbg5_url_paths", "completed_read_only_nonbg5_url_paths_incomplete", "terminal_failed_no_replay")\nFAILURE_STAGES = ("prior_metadata_unavailable_or_digest", "prior_result_binding_mismatch", "prior_private_capture_binding_mismatch", "private_projection_or_save_failed", "public_result_validation_failed")\nPATH_HOLDS = ("url_not_absolute_https", "url_origin_or_parameters_private", "url_host_mismatch", "url_path_resource_cap", "url_path_invalid_encoding", "url_path_unsafe_characters", "url_path_private_or_opaque", "url_path_navigation")\nBASE_HOLDS = ("independent_operator_target_proof_not_evaluated", "current_registry_checks_not_performed", "current_global_uniqueness_not_evaluated")\nSHA = re.compile(r"[0-9a-f]{64}")\nHOSTS = {"operator_5": "agent.anextour.ru", "operator_315": "b2b.fstravel.com", "operator_342": "intourist.ru"}\nSECRET = re.compile(r"(?:^|[._/-])(?:token|jwt|auth|password|passwd|secret|session|sid|cookie|signature|api[_-]?key)(?:$|[._/-])", re.I)\nPUBLIC_PATH = re.compile(r"/[A-Za-z0-9/._%-]*")\nDECODED_PATH = re.compile(r"/[A-Za-z0-9/._-]*")\nUUID_SEGMENT = re.compile(r"[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}")\nCOMPACT_TOKEN_SEGMENT = re.compile(r"[A-Za-z0-9_-]{8,}\\.[A-Za-z0-9_-]{2,}\\.[A-Za-z0-9_-]*")\n\n\ndef enc(value):\n    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False) + "\\n").encode()\n\n\ndef parsed(raw):\n    def pairs(items):\n        out = {}\n        for k, v in items:\n            if k in out:\n                raise ValueError("duplicate_key")\n            out[k] = v\n        return out\n    value = json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: (_ for _ in ()).throw(ValueError("nonfinite")))\n    if not isinstance(value, dict):\n        raise ValueError("metadata_object")\n    return value\n\n\ndef equal_typed(actual, expected):\n    if type(actual) is not type(expected):\n        return False\n    if isinstance(expected, dict):\n        return set(actual) == set(expected) and all(equal_typed(actual[k], v) for k, v in expected.items())\n    if isinstance(expected, list):\n        return len(actual) == len(expected) and all(equal_typed(a, b) for a, b in zip(actual, expected))\n    return actual == expected\n\n\ndef file_bytes(path, maximum):\n    path = pathlib.Path(path)\n    if not path.is_absolute() or path.resolve() != path or path.is_symlink():\n        raise ValueError("metadata_path")\n    fd = os.open(path, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))\n    try:\n        info = os.fstat(fd)\n        if not stat.S_ISREG(info.st_mode) or not 0 < info.st_size <= maximum:\n            raise ValueError("metadata_cap")\n        with os.fdopen(fd, "rb", closefd=False) as handle:\n            raw = handle.read(maximum + 1)\n        if len(raw) != info.st_size or len(raw) > maximum:\n            raise ValueError("metadata_changed_or_cap")\n        return raw\n    finally:\n        os.close(fd)\n\n\ndef save(path, value):\n    path = pathlib.Path(path)\n    if path.parent.resolve() != path.parent or path.parent.is_symlink() or not path.parent.is_dir():\n        raise ValueError("output_directory")\n    raw = enc(value)\n    with open(path, "xb") as handle:\n        os.chmod(path, 0o600)\n        if handle.write(raw) != len(raw):\n            raise RuntimeError("short_write")\n        handle.flush()\n        os.fsync(handle.fileno())\n    fd = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))\n    try:\n        os.fsync(fd)\n    finally:\n        os.close(fd)\n    if path.read_bytes() != raw:\n        raise RuntimeError("durable_readback")\n    return hashlib.sha256(raw).hexdigest()\n\n\ndef manifest(path):\n    raw = file_bytes(pathlib.Path(path), 1048576)\n    if hashlib.sha256(raw).hexdigest() != MANIFEST_SHA:\n        raise ValueError("fixture_digest")\n    value = parsed(raw)\n    if value["schema"] != "match-nonbg5-retained-url-paths-fixture/1" or value["operation"] != OP or value["batch"] != BATCH or value["mode"] != MODE or not equal_typed(value["selected_rows"], [0, 1, 2, 3, 4]):\n        raise ValueError("fixture_scope")\n    prior = value["prior_result"]\n    if len(enc(prior)) != 39042 or hashlib.sha256(enc(prior)).hexdigest() != OLD_RESULT_SHA or prior["private_input_sha256"] != OLD_INPUT_SHA or prior["source_sha"] != OLD_SOURCE or prior["operation"] != OLD_OP or prior["batch"] != OLD_BATCH or len(prior["rows"]) != 7:\n        raise ValueError("fixture_prior_result")\n    return value\n\n\nclass CaptureFailure(Exception):\n    def __init__(self, stage):\n        self.stage = stage\n        super().__init__(stage)\n\n\ndef phase(stage, action):\n    try:\n        return action()\n    except Exception:\n        raise CaptureFailure(stage) from None\n\n\ndef validate_prior_capture(capture, prior):\n    keys = {"schema", "operation", "batch", "inputs", "projection_fields", "raw_files_attempted", "raw_files_read", "raw_bytes_read", "rows"}\n    expected = {"schema": "match-nonbg7-unexported-private-input/1", "operation": OLD_OP, "batch": OLD_BATCH, "inputs": prior["inputs"], "projection_fields": prior["projection_fields"], "raw_files_attempted": prior["raw_files_attempted"], "raw_files_read": prior["raw_files_read"], "raw_bytes_read": prior["raw_bytes_read"]}\n    if not isinstance(capture, dict) or set(capture) != keys or any(not equal_typed(capture.get(k), v) for k, v in expected.items()) or not isinstance(capture["rows"], list) or len(capture["rows"]) != 7:\n        raise ValueError("capture_header")\n    for i, (row, old) in enumerate(zip(capture["rows"], prior["rows"])):\n        if not isinstance(row, dict) or set(row) != {*ROW_KEYS, "references"} or any(not equal_typed(row[k], old[k]) for k in ROW_KEYS) or not isinstance(row["references"], list) or len(row["references"]) != len(old["references"]):\n            raise ValueError("capture_row")\n        for j, (ref, old_ref) in enumerate(zip(row["references"], old["references"])):\n            keys = {"source_file", "sha256", "json_pointer", "raw_verified", "failure", "fields"}\n            if not isinstance(ref, dict) or set(ref) != keys or any(not equal_typed(ref[k], old_ref[k]) for k in keys - {"fields"}) or ref["raw_verified"] is not True or ref["failure"] is not None or not isinstance(ref["fields"], dict) or set(ref["fields"]) != {"row.hotelUrl", "original.tourKey"}:\n                raise ValueError("capture_reference")\n            if not equal_typed(old_ref["private_input_pointer"], {"sha256": OLD_INPUT_SHA, "json_pointer": f"/rows/{i}/references/{j}"}):\n                raise ValueError("capture_pointer")\n            for field in old_ref["fields"]:\n                actual = ref["fields"][field["field_name"]]\n                if not isinstance(actual, dict) or set(actual) != {"present", "value"} or type(actual["present"]) is not bool or actual["present"] != field["present"]:\n                    raise ValueError("capture_field")\n                v = actual["value"]\n                kind = "null" if v is None else ("boolean" if type(v) is bool else ("number" if type(v) in (int, float) else ("string" if type(v) is str else ("array" if type(v) is list else "object"))))\n                raw = enc(v)\n                if kind != field["value_type"] or len(raw) != field["value_bytes"] or hashlib.sha256(raw).hexdigest() != field["value_sha256"]:\n                    raise ValueError("capture_field_digest_or_type")\n                if field["field_name"] == "row.hotelUrl" and (type(v) is not str or not v or actual["present"] is not True):\n                    raise ValueError("capture_url_type")\n    return capture\n\n\ndef path_projection(value, expected_host):\n    out = {"state": "hold", "hold_reason": None, "source_url_candidate": None, "path": None, "decoded_path": None, "path_segments": [], "numeric_path_tokens": [], "positive_numeric_path_candidates": [], "namespace_bridge_verified": False, "target_native_identity_verified": False}\n    def hold(reason):\n        out["hold_reason"] = reason\n        return out\n    # urlsplit removes some literal C0 controls before parsing. Reject them in\n    # the original captured value so a safe projection never republishes them.\n    if type(value) is not str or re.search(r"[\\x00-\\x20\\x7f]", value):\n        return hold("url_path_unsafe_characters")\n    try:\n        p = urllib.parse.urlsplit(value)\n        if p.scheme.lower() != "https" or not p.netloc:\n            return hold("url_not_absolute_https")\n        if p.username is not None or p.password is not None or p.query or p.fragment or "?" in value or "#" in value or p.port not in (None, 443) or ":" in p.netloc:\n            return hold("url_origin_or_parameters_private")\n        if p.hostname != expected_host or p.netloc.lower() != expected_host:\n            return hold("url_host_mismatch")\n        path = p.path\n        if not path.startswith("/") or len(path) > 2048:\n            return hold("url_path_resource_cap")\n        if re.search(r"%(?:2f|5c|25)", path, re.I) or re.search(r"%(?![0-9a-f]{2})", path, re.I):\n            return hold("url_path_invalid_encoding")\n        decoded = urllib.parse.unquote_to_bytes(path).decode("ascii")\n        if not PUBLIC_PATH.fullmatch(path) or not DECODED_PATH.fullmatch(decoded):\n            return hold("url_path_unsafe_characters")\n        segments = decoded.split("/")[1:]\n        if len(segments) > 20 or any(len(s) > 128 for s in segments):\n            return hold("url_path_resource_cap")\n        if any(s in (".", "..") for s in segments):\n            return hold("url_path_navigation")\n        if SECRET.search(decoded) or any((re.fullmatch(r"[0-9a-fA-F]{32,}", s) and re.search(r"[a-fA-F]", s)) or UUID_SEGMENT.fullmatch(s) or COMPACT_TOKEN_SEGMENT.fullmatch(s) or (len(s) >= 32 and re.fullmatch(r"[A-Za-z0-9_-]+", s) and ((re.search(r"[A-Za-z]", s) and re.search(r"[0-9]", s)) or (re.search(r"[A-Z]", s) and re.search(r"[a-z]", s)))) for s in segments):\n            return hold("url_path_private_or_opaque")\n        tokens = [s for s in segments if re.fullmatch(r"-?[0-9]+", s)]\n        positives = list(dict.fromkeys(s for s in tokens if re.fullmatch(r"[0-9]+", s) and int(s) > 0))\n        out.update(state="safe_absolute_operator_path_candidate", source_url_candidate=value, path=path, decoded_path=decoded, path_segments=segments, numeric_path_tokens=tokens, positive_numeric_path_candidates=positives)\n        return out\n    except (ValueError, UnicodeError, OverflowError):\n        return hold("url_path_invalid_encoding")\n\n\ndef project_capture(capture, fixture):\n    prior = fixture["prior_result"]\n    rows = []\n    for i in fixture["selected_rows"]:\n        old = prior["rows"][i]\n        row = capture["rows"][i]\n        refs = []\n        holds = list(BASE_HOLDS)\n        if old["dated_operator_ownership_fact"]["current_identity_count"] > 0:\n            holds.append("scoped_operator_identity_present_dated")\n        for j, ref in enumerate(row["references"]):\n            field = next(f for f in old["references"][j]["fields"] if f["field_name"] == "row.hotelUrl")\n            projection = path_projection(ref["fields"]["row.hotelUrl"]["value"], HOSTS[row["source_namespace"]])\n            if projection["hold_reason"]:\n                holds.append(projection["hold_reason"])\n            refs.append({**{k: ref[k] for k in ("source_file", "sha256", "json_pointer")}, "field_name": "row.hotelUrl", "value_sha256": field["value_sha256"], "value_bytes": field["value_bytes"], "prior_private_value_pointer": {"sha256": OLD_INPUT_SHA, "json_pointer": f"/rows/{i}/references/{j}/fields/row.hotelUrl/value"}, "prior_result_field_pointer": {"sha256": OLD_RESULT_SHA, "json_pointer": f"/rows/{i}/references/{j}/fields/0"}, "projection": projection})\n        rows.append({**{k: old[k] for k in ROW_KEYS}, "target_id_namespace": "tourvisor", "independent_anytour_local_id": None, "dated_operator_ownership_fact": old["dated_operator_ownership_fact"], "references": refs, "holds": list(dict.fromkeys(holds)), "source_namespace_bridge_verified": False, "target_native_identity_verified": False, **dict.fromkeys(FALSE_FLAGS, False)})\n    return rows\n\n\ndef capture_retained(private_root, fixture):\n    pins = fixture["inputs"]\n    def reads():\n        old_raw = file_bytes(private_root / pins["prior_result"]["path"], 39042)\n        value_raw = file_bytes(private_root / pins["prior_private_input"]["path"], 16777216)\n        if len(old_raw) != 39042 or hashlib.sha256(old_raw).hexdigest() != OLD_RESULT_SHA or hashlib.sha256(value_raw).hexdigest() != OLD_INPUT_SHA:\n            raise ValueError("prior_digest")\n        return old_raw, value_raw\n    old_raw, value_raw = phase("prior_metadata_unavailable_or_digest", reads)\n    old = phase("prior_result_binding_mismatch", lambda: parsed(old_raw))\n    phase("prior_result_binding_mismatch", lambda: equal_or_fail(old, fixture["prior_result"]))\n    capture = phase("prior_private_capture_binding_mismatch", lambda: validate_prior_capture(parsed(value_raw), old))\n    rows = project_capture(capture, fixture)\n    return {"schema": "match-nonbg5-retained-url-paths-private-input/1", "operation": OP, "batch": BATCH, "inputs": pins, "metadata_files_bound": 2, "metadata_bytes_bound": len(old_raw) + len(value_raw), "selected_rows": fixture["selected_rows"], "rows": rows, "complete_source_values_retained_in_prior_private_input": True, "original_raw_files_read": 0}\n\n\ndef equal_or_fail(actual, expected):\n    if not equal_typed(actual, expected):\n        raise ValueError("typed_binding")\n    return True\n\n\ndef validate_result(data, receipt=None, expected_source=None):\n    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_retained_url_paths_readonly_v1.json")\n    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "failure_stage", "captured_at_utc", "private_input_sha256", "inputs", "requested_rows", "requested_sources", "distinct_source_count", "rows_examined", "operator_ids", "rows", "metadata_files_bound", "metadata_bytes_bound", "original_raw_files_read", "references_bound", "safe_path_candidate_references", "safe_path_candidate_rows", "hold_counts", "global_saved_context_only", "source_namespace_bridge_verified", "target_native_identity_verified", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}\n    if not isinstance(data, dict) or set(data) != keys or len(enc(data)) > 2097152 or data["schema"] != "match-nonbg5-retained-url-paths-readonly-result/1" or data["operation"] != OP or data["batch"] != BATCH or data["state"] not in STATES:\n        raise ValueError("public_scope")\n    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or (expected_source is not None and data["source_sha"] != expected_source) or not SHA.fullmatch(data["private_input_sha256"] or "") or not equal_typed(data["inputs"], fixture["inputs"]):\n        raise ValueError("public_lineage")\n    if any(type(data[k]) is not int or data[k] != 0 for k in (*NO_EFFECTS, "original_raw_files_read")) or any(data[k] is not False for k in (*FALSE_FLAGS, "source_namespace_bridge_verified", "target_native_identity_verified")) or data["no_replay"] is not True or data["global_saved_context_only"] is not True:\n        raise ValueError("public_authority")\n    if not re.fullmatch(r"\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z", data["captured_at_utc"] or ""):\n        raise ValueError("public_timestamp")\n    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")\n    for k, v in (("requested_rows", 5), ("requested_sources", 4)):\n        if type(data[k]) is not int or data[k] != v:\n            raise ValueError("public_roster_size")\n    if not equal_typed(data["operator_ids"], [13, 25, 43]) or not isinstance(data["rows"], list) or type(data["rows_examined"]) is not int or data["rows_examined"] != len(data["rows"]):\n        raise ValueError("public_roster")\n    failed = data["state"] == "terminal_failed_no_replay"\n    if failed:\n        if data["rows"] or data["reason"] != "nonbg5_url_paths_capture_or_validation_failed" or data["failure_stage"] not in FAILURE_STAGES or any(type(data[k]) is not int or data[k] != 0 for k in ("distinct_source_count", "metadata_files_bound", "metadata_bytes_bound", "references_bound", "safe_path_candidate_references", "safe_path_candidate_rows")) or data["hold_counts"] != {}:\n            raise ValueError("public_failed_state")\n    else:\n        if data["reason"] is not None or data["failure_stage"] is not None or len(data["rows"]) != 5 or type(data["distinct_source_count"]) is not int or data["distinct_source_count"] != 4 or type(data["metadata_files_bound"]) is not int or data["metadata_files_bound"] != 2 or type(data["metadata_bytes_bound"]) is not int or not 39042 < data["metadata_bytes_bound"] <= 39042 + 16777216:\n            raise ValueError("public_completed_state")\n        count = 0\n        candidate_rows = 0\n        hold_counter = collections.Counter()\n        for i, (row, source_index) in enumerate(zip(data["rows"], fixture["selected_rows"])):\n            old = fixture["prior_result"]["rows"][source_index]\n            row_keys = {*ROW_KEYS, "target_id_namespace", "independent_anytour_local_id", "dated_operator_ownership_fact", "references", "holds", "source_namespace_bridge_verified", "target_native_identity_verified", *FALSE_FLAGS}\n            if not isinstance(row, dict) or set(row) != row_keys or any(not equal_typed(row[k], old[k]) for k in ROW_KEYS) or not equal_typed(row["dated_operator_ownership_fact"], old["dated_operator_ownership_fact"]) or row["target_id_namespace"] != "tourvisor" or row["independent_anytour_local_id"] is not None or any(row[k] is not False for k in (*FALSE_FLAGS, "source_namespace_bridge_verified", "target_native_identity_verified")) or not isinstance(row["references"], list) or len(row["references"]) != len(old["references"]):\n                raise ValueError("public_row_binding")\n            holds = list(BASE_HOLDS)\n            if old["dated_operator_ownership_fact"]["current_identity_count"] > 0:\n                holds.append("scoped_operator_identity_present_dated")\n            candidates = 0\n            for j, (ref, old_ref) in enumerate(zip(row["references"], old["references"])):\n                field = old_ref["fields"][0]\n                expected = {**{k: old_ref[k] for k in ("source_file", "sha256", "json_pointer")}, "field_name": "row.hotelUrl", "value_sha256": field["value_sha256"], "value_bytes": field["value_bytes"], "prior_private_value_pointer": {"sha256": OLD_INPUT_SHA, "json_pointer": f"/rows/{source_index}/references/{j}/fields/row.hotelUrl/value"}, "prior_result_field_pointer": {"sha256": OLD_RESULT_SHA, "json_pointer": f"/rows/{source_index}/references/{j}/fields/0"}}\n                if not isinstance(ref, dict) or set(ref) != {*expected, "projection"} or any(not equal_typed(ref[k], v) for k, v in expected.items()):\n                    raise ValueError("public_ref_binding")\n                p = ref["projection"]\n                projection_keys = {"state", "hold_reason", "source_url_candidate", "path", "decoded_path", "path_segments", "numeric_path_tokens", "positive_numeric_path_candidates", "namespace_bridge_verified", "target_native_identity_verified"}\n                if not isinstance(p, dict) or set(p) != projection_keys or p["namespace_bridge_verified"] is not False or p["target_native_identity_verified"] is not False:\n                    raise ValueError("public_projection")\n                if p["state"] == "safe_absolute_operator_path_candidate":\n                    if not isinstance(p["source_url_candidate"], str) or not equal_typed(p, path_projection(p["source_url_candidate"], HOSTS[row["source_namespace"]])) or hashlib.sha256(enc(p["source_url_candidate"])).hexdigest() != ref["value_sha256"] or len(enc(p["source_url_candidate"])) != ref["value_bytes"]:\n                        raise ValueError("public_safe_projection")\n                    # The exact URL bytes are captured proof of this path representation;\n                    # they establish neither native identity nor independent target proof.\n                    candidates += 1\n                elif p["state"] == "hold" and p["hold_reason"] in PATH_HOLDS:\n                    expected_hold = {k: v for k, v in path_projection("", HOSTS[row["source_namespace"]]).items()}\n                    expected_hold["hold_reason"] = p["hold_reason"]\n                    if not equal_typed(p, expected_hold):\n                        raise ValueError("public_hold_projection")\n                    holds.append(p["hold_reason"])\n                else:\n                    raise ValueError("public_projection_state")\n            if not equal_typed(row["holds"], list(dict.fromkeys(holds))):\n                raise ValueError("public_holds")\n            hold_counter.update(row["holds"])\n            count += candidates\n            candidate_rows += candidates > 0\n        expected_state = "completed_read_only_nonbg5_url_paths" if count == 8 else "completed_read_only_nonbg5_url_paths_incomplete"\n        if data["state"] != expected_state or type(data["references_bound"]) is not int or data["references_bound"] != 8 or type(data["safe_path_candidate_references"]) is not int or data["safe_path_candidate_references"] != count or type(data["safe_path_candidate_rows"]) is not int or data["safe_path_candidate_rows"] != candidate_rows or not equal_typed(data["hold_counts"], dict(hold_counter)):\n            raise ValueError("public_aggregate")\n    if receipt is not None:\n        expected = {k: data[k] for k in RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(enc(data)).hexdigest()}\n        equal_or_fail(receipt, expected)\n    return True\n\n\ndef execute(root, opdir, manifest_path):\n    root, opdir = pathlib.Path(root), pathlib.Path(opdir)\n    fixture = manifest(manifest_path)\n    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_retained_url_paths_readonly_v1.json"\n    head = os.environ.get("MATCH_SOURCE_SHA", "")\n    if not root.is_dir() or root.is_symlink() or root.resolve() != root or root.name != "anytoour.ru" or not opdir.is_dir() or opdir.is_symlink() or opdir.resolve() != opdir or opdir.name != OP or opdir.parent.name != "operations" or opdir.parent.parent.name != ".anytoour-match" or pathlib.Path(manifest_path) != expected_fixture or not re.fullmatch(r"[0-9a-f]{40}", head):\n        raise ValueError("runtime_scope")\n    reservation = parsed(file_bytes(opdir / "reservation.json", 1048576))\n    expected = {"operation": OP, "batch": BATCH, "source_sha": head, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}\n    if any(not equal_typed(reservation.get(k), v) for k, v in expected.items()):\n        raise ValueError("reservation_scope")\n    for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):\n        if (opdir / name).exists() or (opdir / name).is_symlink():\n            raise ValueError("terminal_no_replay")\n    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "no_replay": True})\n    capture, private_sha, rows = None, None, []\n    state, reason, failure_stage = "terminal_failed_no_replay", "nonbg5_url_paths_capture_or_validation_failed", None\n    try:\n        capture = capture_retained(opdir.parent.parent, fixture)\n        if len(enc(capture)) > 16777216:\n            raise ValueError("private_projection_cap")\n        private_sha = save(opdir / "current-input.json", capture)\n        rows = capture["rows"]\n        state = "completed_read_only_nonbg5_url_paths_incomplete" if any(r["projection"]["state"] == "hold" for row in rows for r in row["references"]) else "completed_read_only_nonbg5_url_paths"\n        reason = None\n    except Exception as failure:\n        failure_stage = failure.stage if isinstance(failure, CaptureFailure) else "private_projection_or_save_failed"\n        if private_sha is None:\n            private_sha = save(opdir / "current-input.json", {"schema": "match-nonbg5-retained-url-paths-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "reason": reason, "failure_stage": failure_stage})\n        rows = []\n    output = {"schema": "match-nonbg5-retained-url-paths-readonly-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": state, "reason": reason, "failure_stage": failure_stage, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "inputs": fixture["inputs"], "requested_rows": 5, "requested_sources": 4, "distinct_source_count": len({r["catalog_id"] for r in rows}), "rows_examined": len(rows), "operator_ids": [13, 25, 43], "rows": rows, "metadata_files_bound": 2 if rows else 0, "metadata_bytes_bound": capture["metadata_bytes_bound"] if rows else 0, "original_raw_files_read": 0, "references_bound": sum(len(r["references"]) for r in rows), "safe_path_candidate_references": sum(ref["projection"]["state"] == "safe_absolute_operator_path_candidate" for row in rows for ref in row["references"]), "safe_path_candidate_rows": sum(any(ref["projection"]["state"] == "safe_absolute_operator_path_candidate" for ref in row["references"]) for row in rows), "hold_counts": dict(collections.Counter(h for r in rows for h in r["holds"])), "global_saved_context_only": True, "source_namespace_bridge_verified": False, "target_native_identity_verified": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}\n    try:\n        validate_result(output)\n    except Exception:\n        output.update(state="terminal_failed_no_replay", reason="nonbg5_url_paths_capture_or_validation_failed", failure_stage="public_result_validation_failed", rows=[], rows_examined=0, distinct_source_count=0, metadata_files_bound=0, metadata_bytes_bound=0, references_bound=0, safe_path_candidate_references=0, safe_path_candidate_rows=0, hold_counts={})\n        validate_result(output)\n    digest = save(opdir / "result.json", output)\n    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}\n    validate_result(output, receipt, head)\n    save(opdir / "receipt.json", receipt)\n    print(json.dumps({k: output[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))\n    return 2 if output["state"] == "terminal_failed_no_replay" else 0\n\n\ndef self_test():\n    p = path_projection("https://b2b.fstravel.com/hotels/354014", "b2b.fstravel.com")\n    if p["numeric_path_tokens"] != ["354014"] or p["namespace_bridge_verified"] is not False or path_projection("https://intourist.ru/info/a/%2fsecret", "intourist.ru")["state"] != "hold":\n        raise RuntimeError("self_test")\n    manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_retained_url_paths_readonly_v1.json")\n    print("self_test_passed_nonbg5_retained_url_paths")\n\n\nif __name__ == "__main__":\n    if sys.argv[1:] == ["--self-test"]:\n        self_test()\n    elif sys.argv[1:] == ["--execute"]:\n        raise SystemExit(execute(os.environ.get("ANYTOUR_ROOT", ""), os.environ.get("MATCH_OPERATION_DIRECTORY", ""), os.environ.get("MATCH_MANIFEST", "")))\n    else:\n        raise SystemExit("use --self-test or exact reviewed --execute")\n'
FROZEN_URL5_READER_SHA = '8437cdb48cc571ff273cfdb95f9e8c5aca9cde5a6586cb93d99f60303961b356'
FROZEN_URL5_FIXTURE = '{\n  "batch": "nonbg5-retained-url-paths-20261004",\n  "guards": {\n    "acceptance_evaluated": false,\n    "accepted": 0,\n    "booking_calls": 0,\n    "database_reads": 0,\n    "database_writes": 0,\n    "global_uniqueness_evaluated": false,\n    "lead_calls": 0,\n    "mapping_writes": 0,\n    "no_replay": true,\n    "physical_http_attempts": 0,\n    "provider_http_calls": 0,\n    "safe_to_write_now": false,\n    "source_namespace_bridge_verified": false,\n    "target_native_identity_verified": false,\n    "written": 0\n  },\n  "inputs": {\n    "prior_fixture_sha256": "220cfc26cab422113d2caf6ce61080e8020a0fb5f9f2548916e828fa3cad43be",\n    "prior_private_input": {\n      "maximum_bytes": 16777216,\n      "path": "operations/int-andromeda-match-nonbg7-unexported-fields-20261004-v1/current-input.json",\n      "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n    },\n    "prior_result": {\n      "bytes": 39042,\n      "path": "operations/int-andromeda-match-nonbg7-unexported-fields-20261004-v1/result.json",\n      "sha256": "8336b824bf48df8771685c00d744bb8a720fa062addb1d71a93ec23c4e394604"\n    },\n    "prior_source_sha": "bcd25c42a899071b09abce06890b2ce0c3eeb465"\n  },\n  "limits": {\n    "metadata_files": 2,\n    "original_raw_files_read": 0,\n    "path_characters": 2048,\n    "path_segments": 20,\n    "private_capture_bytes": 16777216,\n    "public_result_bytes": 2097152,\n    "segment_characters": 128,\n    "selected_catalog_sources": 4,\n    "selected_facts": 5,\n    "selected_references": 8\n  },\n  "mode": "match-nonbg5-retained-url-paths-readonly",\n  "operation": "int-andromeda-match-nonbg5-retained-url-paths-20261004-v1",\n  "prior_result": {\n    "acceptance_evaluated": false,\n    "accepted": 0,\n    "batch": "nonbg7-unexported-fields-20261004",\n    "booking_calls": 0,\n    "captured_at_utc": "2026-10-04T12:13:41Z",\n    "database_reads": 0,\n    "database_writes": 0,\n    "distinct_source_count": 6,\n    "failure_stage": null,\n    "global_saved_context_only": true,\n    "global_uniqueness_evaluated": false,\n    "hold_counts": {\n      "current_global_uniqueness_not_evaluated": 7,\n      "current_registry_checks_not_performed": 7,\n      "independent_operator_target_proof_not_evaluated": 7,\n      "scoped_operator_identity_present_dated": 6\n    },\n    "inputs": {\n      "global_v77_reread": false,\n      "native_current": {\n        "batch": "native110-20260928",\n        "bytes": 251866,\n        "operation": "int-andromeda-match-native-current-20261001-v1",\n        "path": "operations/int-andromeda-match-native-current-20261001-v1/native110-current-manifest.json",\n        "schema": "native110-current-review/1",\n        "sha256": "59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e",\n        "source_sha": "9c82d143ccd6173ade0d6b1d52c6e3a41657d460"\n      }\n    },\n    "lead_calls": 0,\n    "mapping_writes": 0,\n    "no_replay": true,\n    "operation": "int-andromeda-match-nonbg7-unexported-fields-20261004-v1",\n    "operator_ids": [\n      13,\n      25,\n      43\n    ],\n    "physical_http_attempts": 0,\n    "private_input_sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce",\n    "projection_fields": [\n      "row.hotelUrl",\n      "original.tourKey"\n    ],\n    "provider_http_calls": 0,\n    "raw_bytes_read": 1214179,\n    "raw_files_attempted": 11,\n    "raw_files_read": 11,\n    "raw_references_verified": 12,\n    "reason": null,\n    "requested_rows": 7,\n    "requested_sources": 6,\n    "rows": [\n      {\n        "acceptance_evaluated": false,\n        "catalog_id": "2000029745",\n        "dated_operator_ownership_fact": {\n          "current_identity_count": 0,\n          "current_local_hotel_ids": [],\n          "namespace": "operator_5",\n          "native_id": "44562"\n        },\n        "dated_original_fact_check": {\n          "failures": [],\n          "global_saved_unique": true,\n          "namespace": "operator_5",\n          "native_id": "44562",\n          "raw_verified": true\n        },\n        "global_uniqueness_evaluated": false,\n        "holds": [\n          "independent_operator_target_proof_not_evaluated",\n          "current_registry_checks_not_performed",\n          "current_global_uniqueness_not_evaluated"\n        ],\n        "independent_anytour_local_id": null,\n        "references": [\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "agent.anextour.ru",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 83,\n                "value_sha256": "59bf2afcf1e83f084cc3b7d12ffa3a9a0909545294c59eb1848619796ada0afe",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 5,\n                "value_sha256": "59980b05fb1c482d1800544748cf95d6013fb95e7be02b0b72d16aa9d1d09ed8",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/6",\n            "private_input_pointer": {\n              "json_pointer": "/rows/0/references/0",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "a16240130914e51841096033f3efd843ee5480d6f8df2df208f21206ece0d7d0",\n            "source_file": "operations/hotel-match-common4-retained-context-acquire-1971-20260925-v26b/evidence-private/context-4-3-5-20261005-20261005-7-none-page-2.json"\n          }\n        ],\n        "safe_to_write_now": false,\n        "source_namespace": "operator_5",\n        "source_namespace_bridge_verified": false,\n        "source_native_id": "44562",\n        "target_id_namespace": "tourvisor",\n        "target_operator_id": 13,\n        "target_tv_hotel_id": 159\n      },\n      {\n        "acceptance_evaluated": false,\n        "catalog_id": "2000109038",\n        "dated_operator_ownership_fact": {\n          "current_identity_count": 1,\n          "current_local_hotel_ids": [],\n          "namespace": "operator_5",\n          "native_id": "43661"\n        },\n        "dated_original_fact_check": {\n          "failures": [],\n          "global_saved_unique": true,\n          "namespace": "operator_5",\n          "native_id": "43661",\n          "raw_verified": true\n        },\n        "global_uniqueness_evaluated": false,\n        "holds": [\n          "independent_operator_target_proof_not_evaluated",\n          "current_registry_checks_not_performed",\n          "current_global_uniqueness_not_evaluated",\n          "scoped_operator_identity_present_dated"\n        ],\n        "independent_anytour_local_id": null,\n        "references": [\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "agent.anextour.ru",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 69,\n                "value_sha256": "6fca2e2c31d05b09b3cfa765017943e5d86257b411a395d61479edb28a622a70",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 5,\n                "value_sha256": "a110eccd163766a1ed074111f030f94042572774205f61f3035c7644ff863d8b",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/6",\n            "private_input_pointer": {\n              "json_pointer": "/rows/1/references/0",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "eda7e9e87b746c635f0a69cb8b856e4e8a25c2059a226dc2edb06a82a81ee780",\n            "source_file": "operations/hotel-match-common4-retained-context-acquire-1971-20260925-v26b/evidence-private/context-6-5-5-20261011-20261011-7-none-page-2.json"\n          }\n        ],\n        "safe_to_write_now": false,\n        "source_namespace": "operator_5",\n        "source_namespace_bridge_verified": false,\n        "source_native_id": "43661",\n        "target_id_namespace": "tourvisor",\n        "target_operator_id": 13,\n        "target_tv_hotel_id": 109380\n      },\n      {\n        "acceptance_evaluated": false,\n        "catalog_id": "2000037261",\n        "dated_operator_ownership_fact": {\n          "current_identity_count": 1,\n          "current_local_hotel_ids": [],\n          "namespace": "operator_315",\n          "native_id": "354014"\n        },\n        "dated_original_fact_check": {\n          "failures": [],\n          "global_saved_unique": true,\n          "namespace": "operator_315",\n          "native_id": "354014",\n          "raw_verified": true\n        },\n        "global_uniqueness_evaluated": false,\n        "holds": [\n          "independent_operator_target_proof_not_evaluated",\n          "current_registry_checks_not_performed",\n          "current_global_uniqueness_not_evaluated",\n          "scoped_operator_identity_present_dated"\n        ],\n        "independent_anytour_local_id": null,\n        "references": [\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "b2b.fstravel.com",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 40,\n                "value_sha256": "c8c65f719fb5f5aad23653c5e93ffcc3a98e8048b942fbcef51cb0a4fed3742a",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 3,\n                "value_sha256": "b03c70ff0d641a363f1a021413a098dce02e4c68b75f3a47aa10044f42ee1784",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/21",\n            "private_input_pointer": {\n              "json_pointer": "/rows/2/references/0",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "c7cec8ac3277528ca271148cc93ab05a1d1c9aa4a07fe2bbb442300433a329b2",\n            "source_file": "operations/hotel-match-common4-retained-context-acquire-1971-20260925-v26b/evidence-private/context-3-5-315-20261016-20261016-7-none-page-18.json"\n          },\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "b2b.fstravel.com",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 40,\n                "value_sha256": "c8c65f719fb5f5aad23653c5e93ffcc3a98e8048b942fbcef51cb0a4fed3742a",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 3,\n                "value_sha256": "b03c70ff0d641a363f1a021413a098dce02e4c68b75f3a47aa10044f42ee1784",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/16",\n            "private_input_pointer": {\n              "json_pointer": "/rows/2/references/1",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "47a309905769048269c16644c62e492eb8d77780a9f28b728455e13160cbf0a0",\n            "source_file": "operations/hotel-match-common4-retained-context-acquire-1971-20260925-v26/evidence-private/context-5-315-7-page-18.json"\n          }\n        ],\n        "safe_to_write_now": false,\n        "source_namespace": "operator_315",\n        "source_namespace_bridge_verified": false,\n        "source_native_id": "354014",\n        "target_id_namespace": "tourvisor",\n        "target_operator_id": 25,\n        "target_tv_hotel_id": 59115\n      },\n      {\n        "acceptance_evaluated": false,\n        "catalog_id": "2000068203",\n        "dated_operator_ownership_fact": {\n          "current_identity_count": 1,\n          "current_local_hotel_ids": [],\n          "namespace": "operator_315",\n          "native_id": "789636"\n        },\n        "dated_original_fact_check": {\n          "failures": [],\n          "global_saved_unique": true,\n          "namespace": "operator_315",\n          "native_id": "789636",\n          "raw_verified": true\n        },\n        "global_uniqueness_evaluated": false,\n        "holds": [\n          "independent_operator_target_proof_not_evaluated",\n          "current_registry_checks_not_performed",\n          "current_global_uniqueness_not_evaluated",\n          "scoped_operator_identity_present_dated"\n        ],\n        "independent_anytour_local_id": null,\n        "references": [\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "b2b.fstravel.com",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 40,\n                "value_sha256": "815dbc12b32b50a48822b2ebc66f556282bbf2e02efa6a9fa918c2cd5ce5e0b4",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 6,\n                "value_sha256": "a0343ca3ecf7301c38d6575e78601fcd1b914d548cbbc791952828cb0df939f0",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/23",\n            "private_input_pointer": {\n              "json_pointer": "/rows/3/references/0",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "491952920ca566be56a4762b321cd54cf1dced0e4a052308de0149950d4bd480",\n            "source_file": "operations/hotel-match-common4-retained-context-acquire-1971-20260925-v26b/evidence-private/context-3-5-315-20261016-20261016-7-none-page-10.json"\n          },\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "b2b.fstravel.com",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 40,\n                "value_sha256": "815dbc12b32b50a48822b2ebc66f556282bbf2e02efa6a9fa918c2cd5ce5e0b4",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 6,\n                "value_sha256": "a0343ca3ecf7301c38d6575e78601fcd1b914d548cbbc791952828cb0df939f0",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/24",\n            "private_input_pointer": {\n              "json_pointer": "/rows/3/references/1",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "2e186b92dca1bb3bbdee6e3c1f57ba10db05ce696eaf6567adb6f11e239c5346",\n            "source_file": "operations/hotel-match-common4-retained-context-acquire-1971-20260925-v26b/evidence-private/context-2-5-315-20261011-20261011-7-none-page-10.json"\n          },\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "b2b.fstravel.com",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 40,\n                "value_sha256": "815dbc12b32b50a48822b2ebc66f556282bbf2e02efa6a9fa918c2cd5ce5e0b4",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 6,\n                "value_sha256": "a0343ca3ecf7301c38d6575e78601fcd1b914d548cbbc791952828cb0df939f0",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/44",\n            "private_input_pointer": {\n              "json_pointer": "/rows/3/references/2",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "847b533751468772924c1b40e2740fcf66582bb7f1260372095ab4f1e1c1eff1",\n            "source_file": "operations/hotel-match-common4-retained-context-acquire-1971-20260925-v26/evidence-private/context-5-315-7-page-9.json"\n          }\n        ],\n        "safe_to_write_now": false,\n        "source_namespace": "operator_315",\n        "source_namespace_bridge_verified": false,\n        "source_native_id": "789636",\n        "target_id_namespace": "tourvisor",\n        "target_operator_id": 25,\n        "target_tv_hotel_id": 70782\n      },\n      {\n        "acceptance_evaluated": false,\n        "catalog_id": "2000068203",\n        "dated_operator_ownership_fact": {\n          "current_identity_count": 1,\n          "current_local_hotel_ids": [],\n          "namespace": "operator_342",\n          "native_id": "17173"\n        },\n        "dated_original_fact_check": {\n          "failures": [],\n          "global_saved_unique": true,\n          "namespace": "operator_342",\n          "native_id": "17173",\n          "raw_verified": true\n        },\n        "global_uniqueness_evaluated": false,\n        "holds": [\n          "independent_operator_target_proof_not_evaluated",\n          "current_registry_checks_not_performed",\n          "current_global_uniqueness_not_evaluated",\n          "scoped_operator_identity_present_dated"\n        ],\n        "independent_anytour_local_id": null,\n        "references": [\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "intourist.ru",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 75,\n                "value_sha256": "07813e02671b983fd968e2663be83410a6d28a9c226c17e51132b5266cd9f443",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 4,\n                "value_sha256": "cc1bdefa1677283e90c64275f66ccd52fe41900b9c53763e2e1058dc971c01b6",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/0",\n            "private_input_pointer": {\n              "json_pointer": "/rows/4/references/0",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "1ebf98425a6776f169b040ac7546d5d9bbb873ac7c9a627a8717c17644d3e06b",\n            "source_file": "operations/hotel-match-samo-live30-common4-retry-1971-20260924-v2/evidence-private/batch-059-page-1.json"\n          }\n        ],\n        "safe_to_write_now": false,\n        "source_namespace": "operator_342",\n        "source_namespace_bridge_verified": false,\n        "source_native_id": "17173",\n        "target_id_namespace": "tourvisor",\n        "target_operator_id": 43,\n        "target_tv_hotel_id": 70782\n      },\n      {\n        "acceptance_evaluated": false,\n        "catalog_id": "2000052591",\n        "dated_operator_ownership_fact": {\n          "current_identity_count": 1,\n          "current_local_hotel_ids": [],\n          "namespace": "operator_342",\n          "native_id": "25728"\n        },\n        "dated_original_fact_check": {\n          "failures": [],\n          "global_saved_unique": true,\n          "namespace": "operator_342",\n          "native_id": "25728",\n          "raw_verified": true\n        },\n        "global_uniqueness_evaluated": false,\n        "holds": [\n          "independent_operator_target_proof_not_evaluated",\n          "current_registry_checks_not_performed",\n          "current_global_uniqueness_not_evaluated",\n          "scoped_operator_identity_present_dated"\n        ],\n        "independent_anytour_local_id": null,\n        "references": [\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "intourist.ru",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 75,\n                "value_sha256": "9af1170324b7146129557d3dac1ba6391be8deb47376b88335c29cfbd67a07bb",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 4,\n                "value_sha256": "140154083d726867f5e43282aeaf021f9300ec6287d4d2c874493ee655a766e7",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/8",\n            "private_input_pointer": {\n              "json_pointer": "/rows/5/references/0",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "cd6bc52c3b7160bcca41788111a9e48525fc0b31de166b13d521c582edda6aac",\n            "source_file": "operations/hotel-match-samo-live30-common4-acquire-1971-20260924-v1/evidence-private/batch-008-page-1.json"\n          }\n        ],\n        "safe_to_write_now": false,\n        "source_namespace": "operator_342",\n        "source_namespace_bridge_verified": false,\n        "source_native_id": "25728",\n        "target_id_namespace": "tourvisor",\n        "target_operator_id": 43,\n        "target_tv_hotel_id": 128\n      },\n      {\n        "acceptance_evaluated": false,\n        "catalog_id": "2000073045",\n        "dated_operator_ownership_fact": {\n          "current_identity_count": 1,\n          "current_local_hotel_ids": [],\n          "namespace": "operator_342",\n          "native_id": "29363"\n        },\n        "dated_original_fact_check": {\n          "failures": [],\n          "global_saved_unique": true,\n          "namespace": "operator_342",\n          "native_id": "29363",\n          "raw_verified": true\n        },\n        "global_uniqueness_evaluated": false,\n        "holds": [\n          "independent_operator_target_proof_not_evaluated",\n          "current_registry_checks_not_performed",\n          "current_global_uniqueness_not_evaluated",\n          "scoped_operator_identity_present_dated"\n        ],\n        "independent_anytour_local_id": null,\n        "references": [\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "intourist.ru",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 94,\n                "value_sha256": "4866003bee070d5668e00cb032447b8282b3a84b94225f7ffdd18460ae535a1c",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 4,\n                "value_sha256": "0590dbaeaad31be1063b2b2392e61fa3373fd004461eb50f77b8d67738092901",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/46",\n            "private_input_pointer": {\n              "json_pointer": "/rows/6/references/0",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "3d893ec0927bf3f5b9f1dfd3c9e26626835ce713610857a0f60491c86116c293",\n            "source_file": "operations/hotel-match-common4-broad-recovery-acquire-1971-20260925-v21/evidence-private/context-3-342-14-page-2.json"\n          },\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "intourist.ru",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 94,\n                "value_sha256": "4866003bee070d5668e00cb032447b8282b3a84b94225f7ffdd18460ae535a1c",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 4,\n                "value_sha256": "0590dbaeaad31be1063b2b2392e61fa3373fd004461eb50f77b8d67738092901",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/44",\n            "private_input_pointer": {\n              "json_pointer": "/rows/6/references/1",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "850ea8ba876df9f2533ef17680cf55c1c608e9a2b1c77f5c46dc1cbec26a4f01",\n            "source_file": "operations/hotel-match-common4-broad-recovery-acquire-1971-20260925-v21/evidence-private/context-3-342-10-page-2.json"\n          },\n          {\n            "failure": null,\n            "fields": [\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "row.hotelUrl",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": "intourist.ru",\n                "origin_state": "absolute_operator_host_candidate",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "url_or_relative_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": "https",\n                "value_bytes": 94,\n                "value_sha256": "4866003bee070d5668e00cb032447b8282b3a84b94225f7ffdd18460ae535a1c",\n                "value_type": "string"\n              },\n              {\n                "exact_source_native_candidate_observed": false,\n                "field_name": "original.tourKey",\n                "namespace_bridge_verified": false,\n                "opaque_selector_token_counts": [],\n                "operator_host": null,\n                "origin_state": "not_established",\n                "positive_selector_candidates": [],\n                "present": true,\n                "projection_hold": null,\n                "raw_selector_parameters": [],\n                "raw_selector_tokens": [],\n                "raw_selector_values": [],\n                "representation": "opaque_or_non_url",\n                "selector_token_positions": [],\n                "selector_value_sha256": [],\n                "url_scheme": null,\n                "value_bytes": 4,\n                "value_sha256": "87615fab5bd53deabdd9791b1d7c1c4360d752656d14090fc5cdff1341811765",\n                "value_type": "number"\n              }\n            ],\n            "json_pointer": "/PRICES/18",\n            "private_input_pointer": {\n              "json_pointer": "/rows/6/references/2",\n              "sha256": "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"\n            },\n            "raw_verified": true,\n            "sha256": "cd6bc52c3b7160bcca41788111a9e48525fc0b31de166b13d521c582edda6aac",\n            "source_file": "operations/hotel-match-samo-live30-common4-acquire-1971-20260924-v1/evidence-private/batch-008-page-1.json"\n          }\n        ],\n        "safe_to_write_now": false,\n        "source_namespace": "operator_342",\n        "source_namespace_bridge_verified": false,\n        "source_native_id": "29363",\n        "target_id_namespace": "tourvisor",\n        "target_operator_id": 43,\n        "target_tv_hotel_id": 80964\n      }\n    ],\n    "rows_examined": 7,\n    "safe_to_write_now": false,\n    "schema": "match-nonbg7-unexported-fields-readonly-result/1",\n    "selector_candidate_rows": 0,\n    "source_namespace_bridge_verified": false,\n    "source_sha": "bcd25c42a899071b09abce06890b2ce0c3eeb465",\n    "state": "completed_read_only_nonbg7_fields",\n    "written": 0\n  },\n  "schema": "match-nonbg5-retained-url-paths-fixture/1",\n  "scope": "first_safe_path_projection_of_five_unrecovered_nonbg7_private_urls",\n  "selected_rows": [\n    0,\n    1,\n    2,\n    3,\n    4\n  ]\n}\n'
FROZEN_URL5_FIXTURE_SHA = 'a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269'
FROZEN_NR5_READER = '#!/usr/bin/env python3\n"""Reconcile six old URL5 metadata records; never run a URL-field projection."""\nimport collections\nimport datetime as dt\nimport hashlib\nimport importlib.util\nimport json\nimport os\nimport pathlib\nimport re\nimport stat\nimport sys\n\nOP = "int-andromeda-match-nonbg5-url-paths-terminal-readback-20261004-v1"\nBATCH = "nonbg5-url-paths-terminal-readback-20261004"\nMODE = "match-nonbg5-url-paths-terminal-readback"\nMANIFEST_SHA = "515ccfc283244713f6ecd3b87c3bc5829e1173d9468a66e24d2fa54379950151"\nOLD_OP = "int-andromeda-match-nonbg5-retained-url-paths-20261004-v1"\nOLD_BATCH = "nonbg5-retained-url-paths-20261004"\nOLD_SOURCE = "5e802eacbe43c0925902ce159ef5669b5cd0c7eb"\nOLD_SOURCE_SHA256 = "8437cdb48cc571ff273cfdb95f9e8c5aca9cde5a6586cb93d99f60303961b356"\nOLD_FIXTURE_SHA256 = "a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269"\nNO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")\nFALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")\nRECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", *NO_EFFECTS, *FALSE_FLAGS, "no_replay")\nSTATES = ("completed_read_only_nonbg5_url_paths_terminal_readback", "completed_read_only_nonbg5_url_paths_terminal_readback_incomplete", "terminal_failed_no_replay")\nOLD_STATES = ("completed_read_only_nonbg5_url_paths", "completed_read_only_nonbg5_url_paths_incomplete", "terminal_failed_no_replay")\nFAILURE_STAGES = ("private_root_or_metadata_unavailable", "private_capture_or_save_failed", "public_result_validation_failed")\nROLES = ("batch_marker", "reservation", "execution_started", "private_input", "result", "receipt")\nRECORD_KEYS = {"role", "relative_path", "presence", "file_type", "size_bytes", "sha256", "json_type", "binding_state"}\nBINDING_STATES = ("absent", "bound_old_header", "unsafe_or_unavailable", "resource_cap", "json_invalid", "old_header_mismatch")\nCLASSIFICATIONS = ("pre_entrypoint_terminal_files_absent_observed", "existing_terminal_verified", "partial_or_unbound_old_terminal_metadata", "capture_failed")\nSHA = re.compile(r"[0-9a-f]{64}")\n\n\ndef enc(value):\n    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False) + "\\n").encode()\n\n\ndef parsed(raw):\n    def pairs(items):\n        out = {}\n        for k, v in items:\n            if k in out:\n                raise ValueError("duplicate_key")\n            out[k] = v\n        return out\n    return json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: (_ for _ in ()).throw(ValueError("nonfinite")))\n\n\ndef equal_typed(actual, expected):\n    if type(actual) is not type(expected):\n        return False\n    if isinstance(expected, dict):\n        return set(actual) == set(expected) and all(equal_typed(actual[k], v) for k, v in expected.items())\n    if isinstance(expected, list):\n        return len(actual) == len(expected) and all(equal_typed(a, b) for a, b in zip(actual, expected))\n    return actual == expected\n\n\ndef file_bytes(path, maximum):\n    path = pathlib.Path(path)\n    if not path.is_absolute() or path.resolve() != path or path.is_symlink():\n        raise ValueError("metadata_path")\n    fd = os.open(path, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))\n    try:\n        info = os.fstat(fd)\n        if not stat.S_ISREG(info.st_mode) or not 0 < info.st_size <= maximum:\n            raise ValueError("metadata_cap")\n        with os.fdopen(fd, "rb", closefd=False) as stream:\n            raw = stream.read(maximum + 1)\n        if len(raw) != info.st_size or len(raw) > maximum:\n            raise ValueError("metadata_changed_or_cap")\n        return raw\n    finally:\n        os.close(fd)\n\n\ndef save(path, value):\n    path = pathlib.Path(path)\n    if path.parent.resolve() != path.parent or path.parent.is_symlink() or not path.parent.is_dir():\n        raise ValueError("output_directory")\n    raw = enc(value)\n    with open(path, "xb") as stream:\n        os.chmod(path, 0o600)\n        if stream.write(raw) != len(raw):\n            raise ValueError("short_write")\n        stream.flush()\n        os.fsync(stream.fileno())\n    fd = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))\n    try:\n        os.fsync(fd)\n    finally:\n        os.close(fd)\n    if path.read_bytes() != raw:\n        raise ValueError("durable_readback")\n    return hashlib.sha256(raw).hexdigest()\n\n\ndef manifest(path):\n    raw = file_bytes(pathlib.Path(path), 1048576)\n    if hashlib.sha256(raw).hexdigest() != MANIFEST_SHA:\n        raise ValueError("fixture_digest")\n    value = parsed(raw)\n    if not isinstance(value, dict) or value["schema"] != "match-nonbg5-url-paths-terminal-readback-fixture/1" or value["operation"] != OP or value["batch"] != BATCH or value["mode"] != MODE or tuple(r["role"] for r in value["metadata_records"]) != ROLES:\n        raise ValueError("fixture_scope")\n    expected_paths = ("nonbg5-retained-url-paths-batch-20261004.json", *("operations/" + OLD_OP + "/" + n for n in ("reservation.json", "execution-started.json", "current-input.json", "result.json", "receipt.json")))\n    if tuple(r["relative_path"] for r in value["metadata_records"]) != expected_paths or value["inputs"]["old_operation"] != OLD_OP or value["inputs"]["old_batch"] != OLD_BATCH or value["inputs"]["old_source_sha"] != OLD_SOURCE:\n        raise ValueError("fixture_old_scope")\n    return value\n\n\ndef old_reservation():\n    return {"operation": OLD_OP, "source_sha": OLD_SOURCE, "batch": OLD_BATCH, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}\n\n\ndef header_valid(role, data):\n    if not isinstance(data, dict):\n        return False\n    if role in ("batch_marker", "reservation"):\n        return equal_typed(data, old_reservation())\n    if role == "execution_started":\n        return equal_typed(data, {"operation": OLD_OP, "batch": OLD_BATCH, "source_sha": OLD_SOURCE, "no_replay": True})\n    if role == "private_input":\n        return data.get("schema") == "match-nonbg5-retained-url-paths-private-input/1" and data.get("operation") == OLD_OP and data.get("batch") == OLD_BATCH\n    if role == "result":\n        return data.get("schema") == "match-nonbg5-retained-url-paths-readonly-result/1" and data.get("operation") == OLD_OP and data.get("batch") == OLD_BATCH and data.get("source_sha") == OLD_SOURCE and data.get("state") in OLD_STATES\n    if role == "receipt":\n        return set(data) == {*RECEIPT_KEYS, "result_sha256"} and data.get("operation") == OLD_OP and data.get("batch") == OLD_BATCH and data.get("source_sha") == OLD_SOURCE and data.get("state") in OLD_STATES\n    return False\n\n\ndef read_record(private_root, pin):\n    path = private_root / pin["relative_path"]\n    descriptor = {"role": pin["role"], "relative_path": pin["relative_path"], "presence": "unknown", "file_type": "unknown", "size_bytes": None, "sha256": None, "json_type": None, "binding_state": "unsafe_or_unavailable"}\n    # No lstat/open through an unexpected parent; symlink targets are never read.\n    if path.parent.resolve() != path.parent or path.parent.is_symlink():\n        return descriptor, None\n    try:\n        info = path.lstat()\n    except FileNotFoundError:\n        descriptor.update(presence="absent", file_type="absent", binding_state="absent")\n        return descriptor, None\n    except OSError:\n        return descriptor, None\n    descriptor.update(presence="present", size_bytes=info.st_size)\n    if stat.S_ISLNK(info.st_mode):\n        descriptor["file_type"] = "symlink"\n        return descriptor, None\n    if not stat.S_ISREG(info.st_mode):\n        descriptor["file_type"] = "directory" if stat.S_ISDIR(info.st_mode) else "other"\n        return descriptor, None\n    descriptor["file_type"] = "regular"\n    if not 0 < info.st_size <= pin["maximum_bytes"]:\n        descriptor["binding_state"] = "resource_cap"\n        return descriptor, None\n    try:\n        raw = file_bytes(path, pin["maximum_bytes"])\n    except (OSError, ValueError):\n        return descriptor, None\n    descriptor.update(size_bytes=len(raw), sha256=hashlib.sha256(raw).hexdigest())\n    try:\n        data = parsed(raw)\n    except (ValueError, UnicodeError):\n        descriptor["binding_state"] = "json_invalid"\n        return descriptor, None\n    descriptor["json_type"] = "object" if type(data) is dict else ("array" if type(data) is list else ("null" if data is None else ("boolean" if type(data) is bool else ("number" if type(data) in (int, float) else "string"))))\n    descriptor["binding_state"] = "bound_old_header" if header_valid(pin["role"], data) else "old_header_mismatch"\n    return descriptor, data\n\n\ndef load_old_validator(fixture):\n    # Import only frozen definitions under a non-main module name. No old\n    # execute/capture_retained/project_capture entry point is called.\n    stage = pathlib.Path(__file__).resolve().parents[2]\n    for role, expected_sha, expected_size in (("old_source_validator", OLD_SOURCE_SHA256, 28459), ("old_source_fixture", OLD_FIXTURE_SHA256, 42821)):\n        pin = fixture["inputs"][role]\n        raw = file_bytes(stage / pin["path"], expected_size)\n        if len(raw) != expected_size or hashlib.sha256(raw).hexdigest() != expected_sha:\n            raise ValueError("frozen_old_validator_binding")\n    runner = stage / fixture["inputs"]["old_source_validator"]["path"]\n    spec = importlib.util.spec_from_file_location("checked_old_url5_terminal_validator", runner)\n    module = importlib.util.module_from_spec(spec)\n    spec.loader.exec_module(module)\n    return module.validate_result\n\n\ndef validate_old_private(data, result):\n    if result["state"] == "terminal_failed_no_replay":\n        expected = {"schema": "match-nonbg5-retained-url-paths-private-input/1", "operation": OLD_OP, "batch": OLD_BATCH, "state": "capture_failed", "reason": result["reason"], "failure_stage": result["failure_stage"]}\n        if equal_typed(data, expected):\n            return True\n        # The failed public result has no row projection against which a\n        # successful-shaped private object can be bound. Preserve only its\n        # metadata presence/digest; never reopen NF7 or reproject those values.\n        raise ValueError("old_failed_result_requires_failed_private_placeholder")\n    expected_keys = {"schema", "operation", "batch", "inputs", "metadata_files_bound", "metadata_bytes_bound", "selected_rows", "rows", "complete_source_values_retained_in_prior_private_input", "original_raw_files_read"}\n    if not isinstance(data, dict) or set(data) != expected_keys or data["schema"] != "match-nonbg5-retained-url-paths-private-input/1" or data["operation"] != OLD_OP or data["batch"] != OLD_BATCH or not equal_typed(data["inputs"], result["inputs"]) or not equal_typed(data["selected_rows"], [0, 1, 2, 3, 4]) or data["complete_source_values_retained_in_prior_private_input"] is not True or type(data["original_raw_files_read"]) is not int or data["original_raw_files_read"] != 0 or type(data["metadata_files_bound"]) is not int or data["metadata_files_bound"] != 2:\n        raise ValueError("old_private_shape")\n    if not equal_typed(data["rows"], result["rows"]) or not equal_typed(data["metadata_bytes_bound"], result["metadata_bytes_bound"]):\n        raise ValueError("old_private_result_relation")\n    return True\n\n\ndef capture_metadata(private_root, fixture):\n    if private_root.resolve() != private_root or private_root.is_symlink() or not private_root.is_dir():\n        raise ValueError("private_root")\n    records, payloads = [], {}\n    for pin in fixture["metadata_records"]:\n        record, data = read_record(private_root, pin)\n        records.append(record)\n        payloads[pin["role"]] = data\n    recovered = None\n    reason = None\n    complete = all(r["binding_state"] == "bound_old_header" for r in records)\n    if complete:\n        try:\n            result, receipt, inp = payloads["result"], payloads["receipt"], payloads["private_input"]\n            by_role = {r["role"]: r for r in records}\n            if receipt["result_sha256"] != by_role["result"]["sha256"] or result["private_input_sha256"] != by_role["private_input"]["sha256"] or receipt["private_input_sha256"] != by_role["private_input"]["sha256"]:\n                raise ValueError("old_terminal_digests")\n            if hashlib.sha256(enc(receipt)).hexdigest() != by_role["receipt"]["sha256"]:\n                raise ValueError("old_canonical_receipt_digest")\n            validator = load_old_validator(fixture)\n            validator(result, receipt, OLD_SOURCE)\n            validate_old_private(inp, result)\n            recovered = result\n        except Exception:\n            reason = "existing_old_terminal_validation_failed"\n    markers_bound = all(r["binding_state"] == "bound_old_header" for r in records[:2])\n    terminal_absent = all(r["presence"] == "absent" for r in records[2:])\n    classification = "existing_terminal_verified" if recovered is not None else ("pre_entrypoint_terminal_files_absent_observed" if markers_bound and terminal_absent else "partial_or_unbound_old_terminal_metadata")\n    if classification == "partial_or_unbound_old_terminal_metadata" and reason is None:\n        reason = "old_terminal_metadata_incomplete_or_unbound"\n    return {"schema": "match-nonbg5-url-paths-terminal-readback-private-input/1", "operation": OP, "batch": BATCH, "inputs": fixture["inputs"], "metadata_records": records, "classification": classification, "recovery_reason": reason, "existing_terminal_summary": recovered, "original_raw_files_read": 0, "nonbg7_private_files_read": 0}\n\n\ndef validate_result(data, receipt=None, expected_source=None):\n    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_url_paths_terminal_readback_v1.json")\n    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "failure_stage", "captured_at_utc", "private_input_sha256", "inputs", "requested_records", "rows_examined", "metadata_records", "classification", "recovery_reason", "existing_terminal_summary", "existing_terminal_result_sha256", "existing_terminal_verified", "recovered_path_candidate_references", "recovered_path_candidate_rows", "metadata_files_bound", "metadata_bytes_bound", "original_raw_files_read", "nonbg7_private_files_read", "old_supplier_calls", "old_database_writes", "old_operation_replayed", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}\n    if not isinstance(data, dict) or set(data) != keys or len(enc(data)) > 2097152 or data["schema"] != "match-nonbg5-url-paths-terminal-readback-result/1" or data["operation"] != OP or data["batch"] != BATCH or data["state"] not in STATES:\n        raise ValueError("public_scope")\n    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or (expected_source is not None and data["source_sha"] != expected_source) or not SHA.fullmatch(data["private_input_sha256"] or "") or not equal_typed(data["inputs"], fixture["inputs"]):\n        raise ValueError("public_lineage")\n    if any(type(data[k]) is not int or data[k] != 0 for k in (*NO_EFFECTS, "original_raw_files_read", "nonbg7_private_files_read")) or any(data[k] is not False for k in (*FALSE_FLAGS, "old_operation_replayed")) or data["no_replay"] is not True or data["old_supplier_calls"] != "unknown" or data["old_database_writes"] != "unknown":\n        raise ValueError("public_authority")\n    if not re.fullmatch(r"\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z", data["captured_at_utc"] or ""):\n        raise ValueError("public_timestamp")\n    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")\n    if type(data["requested_records"]) is not int or data["requested_records"] != 6 or type(data["rows_examined"]) is not int or not isinstance(data["metadata_records"], list) or data["rows_examined"] != len(data["metadata_records"]) or data["classification"] not in CLASSIFICATIONS:\n        raise ValueError("public_records")\n    failed = data["state"] == "terminal_failed_no_replay"\n    if failed:\n        if data["metadata_records"] or data["classification"] != "capture_failed" or data["reason"] != "nonbg5_url_paths_terminal_readback_failed" or data["failure_stage"] not in FAILURE_STAGES or data["existing_terminal_summary"] is not None or data["existing_terminal_result_sha256"] is not None or data["existing_terminal_verified"] is not False or data["recovery_reason"] is not None:\n            raise ValueError("public_failed_state")\n        if any(type(data[k]) is not int or data[k] != 0 for k in ("metadata_files_bound", "metadata_bytes_bound", "recovered_path_candidate_references", "recovered_path_candidate_rows")):\n            raise ValueError("public_failed_counts")\n    else:\n        if len(data["metadata_records"]) != 6 or data["reason"] is not None or data["failure_stage"] is not None:\n            raise ValueError("public_completed_state")\n        for record, pin in zip(data["metadata_records"], fixture["metadata_records"]):\n            if not isinstance(record, dict) or set(record) != RECORD_KEYS or record["role"] != pin["role"] or record["relative_path"] != pin["relative_path"] or record["presence"] not in ("present", "absent", "unknown") or record["file_type"] not in ("regular", "directory", "symlink", "other", "absent", "unknown") or record["binding_state"] not in BINDING_STATES or record["json_type"] not in (None, "object", "array", "string", "number", "boolean", "null"):\n                raise ValueError("public_descriptor")\n            if record["size_bytes"] is not None and (type(record["size_bytes"]) is not int or record["size_bytes"] < 0):\n                raise ValueError("public_descriptor_size")\n            if record["sha256"] is not None and (not isinstance(record["sha256"], str) or not SHA.fullmatch(record["sha256"]) or record["presence"] != "present" or record["file_type"] != "regular" or not 0 < record["size_bytes"] <= pin["maximum_bytes"]):\n                raise ValueError("public_descriptor_digest")\n            if record["presence"] == "absent" and not equal_typed(record, {"role": pin["role"], "relative_path": pin["relative_path"], "presence": "absent", "file_type": "absent", "size_bytes": None, "sha256": None, "json_type": None, "binding_state": "absent"}):\n                raise ValueError("public_absent_descriptor")\n            if record["presence"] == "unknown" and not equal_typed(record, {"role": pin["role"], "relative_path": pin["relative_path"], "presence": "unknown", "file_type": "unknown", "size_bytes": None, "sha256": None, "json_type": None, "binding_state": "unsafe_or_unavailable"}):\n                raise ValueError("public_unknown_descriptor")\n            if record["presence"] == "present" and (record["file_type"] in ("unknown", "absent") or type(record["size_bytes"]) is not int or record["binding_state"] == "absent"):\n                raise ValueError("public_present_descriptor")\n            if record["file_type"] in ("symlink", "directory", "other") and (record["sha256"] is not None or record["json_type"] is not None or record["binding_state"] != "unsafe_or_unavailable"):\n                raise ValueError("public_unread_descriptor")\n            if record["binding_state"] in ("resource_cap", "unsafe_or_unavailable") and (record["sha256"] is not None or record["json_type"] is not None):\n                raise ValueError("public_unavailable_descriptor")\n            if record["binding_state"] == "resource_cap" and (record["file_type"] != "regular" or 0 < record["size_bytes"] <= pin["maximum_bytes"]):\n                raise ValueError("public_resource_cap")\n            if record["binding_state"] == "json_invalid" and (record["sha256"] is None or record["json_type"] is not None):\n                raise ValueError("public_invalid_json_descriptor")\n            if record["binding_state"] == "old_header_mismatch" and (record["sha256"] is None or record["json_type"] is None):\n                raise ValueError("public_mismatched_header_descriptor")\n            if record["binding_state"] == "bound_old_header" and (record["json_type"] != "object" or record["sha256"] is None):\n                raise ValueError("public_bound_descriptor")\n        readable = [r for r in data["metadata_records"] if r["sha256"] is not None]\n        if type(data["metadata_files_bound"]) is not int or data["metadata_files_bound"] != len(readable) or type(data["metadata_bytes_bound"]) is not int or data["metadata_bytes_bound"] != sum(r["size_bytes"] for r in readable):\n            raise ValueError("public_metadata_counts")\n        summary = data["existing_terminal_summary"]\n        if summary is None:\n            if data["existing_terminal_verified"] is not False or data["existing_terminal_result_sha256"] is not None or any(type(data[k]) is not int or data[k] != 0 for k in ("recovered_path_candidate_references", "recovered_path_candidate_rows")):\n                raise ValueError("public_unrecovered_authority")\n        else:\n            if data["existing_terminal_verified"] is not True or not all(r["binding_state"] == "bound_old_header" for r in data["metadata_records"]):\n                raise ValueError("public_recovered_gate")\n            by_role = {r["role"]: r for r in data["metadata_records"]}\n            if hashlib.sha256(enc(summary)).hexdigest() != by_role["result"]["sha256"] or data["existing_terminal_result_sha256"] != by_role["result"]["sha256"] or summary["private_input_sha256"] != by_role["private_input"]["sha256"]:\n                raise ValueError("public_recovered_digest")\n            expected_old_receipt = {k: summary[k] for k in RECEIPT_KEYS} | {"result_sha256": by_role["result"]["sha256"]}\n            if hashlib.sha256(enc(expected_old_receipt)).hexdigest() != by_role["receipt"]["sha256"]:\n                raise ValueError("public_recovered_receipt_digest")\n            load_old_validator(fixture)(summary, None, OLD_SOURCE)\n            if type(data["recovered_path_candidate_references"]) is not int or data["recovered_path_candidate_references"] != summary["safe_path_candidate_references"] or type(data["recovered_path_candidate_rows"]) is not int or data["recovered_path_candidate_rows"] != summary["safe_path_candidate_rows"]:\n                raise ValueError("public_recovered_counts")\n        absent = all(r["presence"] == "absent" for r in data["metadata_records"][2:])\n        markers = all(r["binding_state"] == "bound_old_header" for r in data["metadata_records"][:2])\n        classification = "existing_terminal_verified" if summary is not None else ("pre_entrypoint_terminal_files_absent_observed" if markers and absent else "partial_or_unbound_old_terminal_metadata")\n        expected_state = STATES[0] if classification in ("existing_terminal_verified", "pre_entrypoint_terminal_files_absent_observed") else STATES[1]\n        if data["classification"] != classification or data["state"] != expected_state or (classification != "partial_or_unbound_old_terminal_metadata" and data["recovery_reason"] is not None) or (classification == "partial_or_unbound_old_terminal_metadata" and data["recovery_reason"] not in ("existing_old_terminal_validation_failed", "old_terminal_metadata_incomplete_or_unbound")):\n            raise ValueError("public_classification")\n    if receipt is not None:\n        expected = {k: data[k] for k in RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(enc(data)).hexdigest()}\n        if not equal_typed(receipt, expected):\n            raise ValueError("public_receipt")\n    return True\n\n\ndef execute(root, opdir, manifest_path):\n    root, opdir = pathlib.Path(root), pathlib.Path(opdir)\n    fixture = manifest(manifest_path)\n    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_url_paths_terminal_readback_v1.json"\n    head = os.environ.get("MATCH_SOURCE_SHA", "")\n    if not root.is_dir() or root.is_symlink() or root.resolve() != root or root.name != "anytoour.ru" or not opdir.is_dir() or opdir.is_symlink() or opdir.resolve() != opdir or opdir.name != OP or opdir.parent.name != "operations" or opdir.parent.parent.name != ".anytoour-match" or pathlib.Path(manifest_path) != expected_fixture or not re.fullmatch(r"[0-9a-f]{40}", head):\n        raise ValueError("runtime_scope")\n    reservation = parsed(file_bytes(opdir / "reservation.json", 1048576))\n    expected = {"operation": OP, "batch": BATCH, "source_sha": head, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}\n    if not equal_typed(reservation, expected):\n        raise ValueError("reservation_scope")\n    for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):\n        if (opdir / name).exists() or (opdir / name).is_symlink():\n            raise ValueError("terminal_no_replay")\n    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "no_replay": True})\n    capture, private_sha = None, None\n    state, reason, failure_stage = "terminal_failed_no_replay", "nonbg5_url_paths_terminal_readback_failed", None\n    try:\n        capture = capture_metadata(opdir.parent.parent, fixture)\n        if len(enc(capture)) > 16777216:\n            raise ValueError("private_capture_cap")\n        private_sha = save(opdir / "current-input.json", capture)\n        state = STATES[1] if capture["classification"] == "partial_or_unbound_old_terminal_metadata" else STATES[0]\n        reason = None\n    except Exception:\n        failure_stage = "private_capture_or_save_failed"\n        if private_sha is None:\n            private_sha = save(opdir / "current-input.json", {"schema": "match-nonbg5-url-paths-terminal-readback-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "reason": reason, "failure_stage": failure_stage})\n        capture = None\n    records = capture["metadata_records"] if capture else []\n    summary = capture["existing_terminal_summary"] if capture else None\n    readable = [r for r in records if r["sha256"] is not None]\n    result_digest = next((r["sha256"] for r in records if r["role"] == "result"), None) if summary is not None else None\n    output = {"schema": "match-nonbg5-url-paths-terminal-readback-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": state, "reason": reason, "failure_stage": failure_stage, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "inputs": fixture["inputs"], "requested_records": 6, "rows_examined": len(records), "metadata_records": records, "classification": capture["classification"] if capture else "capture_failed", "recovery_reason": capture["recovery_reason"] if capture else None, "existing_terminal_summary": summary, "existing_terminal_result_sha256": result_digest, "existing_terminal_verified": summary is not None, "recovered_path_candidate_references": summary["safe_path_candidate_references"] if summary else 0, "recovered_path_candidate_rows": summary["safe_path_candidate_rows"] if summary else 0, "metadata_files_bound": len(readable), "metadata_bytes_bound": sum(r["size_bytes"] for r in readable), "original_raw_files_read": 0, "nonbg7_private_files_read": 0, "old_supplier_calls": "unknown", "old_database_writes": "unknown", "old_operation_replayed": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}\n    try:\n        validate_result(output)\n    except Exception:\n        output.update(state="terminal_failed_no_replay", reason="nonbg5_url_paths_terminal_readback_failed", failure_stage="public_result_validation_failed", metadata_records=[], rows_examined=0, classification="capture_failed", recovery_reason=None, existing_terminal_summary=None, existing_terminal_result_sha256=None, existing_terminal_verified=False, recovered_path_candidate_references=0, recovered_path_candidate_rows=0, metadata_files_bound=0, metadata_bytes_bound=0)\n        validate_result(output)\n    digest = save(opdir / "result.json", output)\n    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}\n    validate_result(output, receipt, head)\n    save(opdir / "receipt.json", receipt)\n    print(json.dumps({k: output[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))\n    return 2 if output["state"] == "terminal_failed_no_replay" else 0\n\n\ndef self_test():\n    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_url_paths_terminal_readback_v1.json")\n    if not header_valid("reservation", old_reservation()) or len(fixture["metadata_records"]) != 6:\n        raise ValueError("self_test")\n    print("self_test_passed_nonbg5_url_paths_terminal_readback")\n\n\nif __name__ == "__main__":\n    if sys.argv[1:] == ["--self-test"]:\n        self_test()\n    elif sys.argv[1:] == ["--execute"]:\n        raise SystemExit(execute(os.environ.get("ANYTOUR_ROOT", ""), os.environ.get("MATCH_OPERATION_DIR", ""), os.environ.get("MATCH_MANIFEST_PATH", "")))\n    else:\n        raise SystemExit("use --self-test or exact reviewed --execute")\n'
FROZEN_NR5_READER_SHA = '78c6f3545ba1996eed069599126d4420d76ed631d21f510642fd867c8baa1b80'
FROZEN_NR5_FIXTURE = '{\n  "batch": "nonbg5-url-paths-terminal-readback-20261004",\n  "guards": {\n    "acceptance_evaluated": false,\n    "global_uniqueness_evaluated": false,\n    "no_replay": true,\n    "old_operation_replayed": false,\n    "safe_to_write_now": false\n  },\n  "inputs": {\n    "old_batch": "nonbg5-retained-url-paths-20261004",\n    "old_operation": "int-andromeda-match-nonbg5-retained-url-paths-20261004-v1",\n    "old_source_fixture": {\n      "bytes": 42821,\n      "path": "scripts/diagnostics/fixtures/hotel_match_nonbg5_retained_url_paths_readonly_v1.json",\n      "sha256": "a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269"\n    },\n    "old_source_sha": "5e802eacbe43c0925902ce159ef5669b5cd0c7eb",\n    "old_source_validator": {\n      "bytes": 28459,\n      "path": "scripts/diagnostics/hotel_match_nonbg5_retained_url_paths_readonly_v1.py",\n      "sha256": "8437cdb48cc571ff273cfdb95f9e8c5aca9cde5a6586cb93d99f60303961b356"\n    },\n    "unknown_outer_wrapper": {\n      "artifact_id": 11304162523,\n      "control_sha": "6cf524d0910adcb81fc6388d93146b82139f107a",\n      "database_writes": "unknown",\n      "job_id": 111443430748,\n      "reason": "nonbg5_url_paths_terminal_missing_no_replay",\n      "run_id": 37204727071,\n      "sha256": "cc9d9d17caf4307cea45c008fd1c5fbbf60866885172050346387ccbdeed54bc",\n      "source_sha": "5e802eacbe43c0925902ce159ef5669b5cd0c7eb",\n      "status": "unknown_no_replay",\n      "supplier_calls": "unknown"\n    }\n  },\n  "limits": {\n    "nonbg7_private_files_read": 0,\n    "original_raw_files_read": 0,\n    "private_capture_bytes": 16777216,\n    "private_metadata_records": 6,\n    "public_result_bytes": 2097152\n  },\n  "metadata_records": [\n    {\n      "maximum_bytes": 1048576,\n      "relative_path": "nonbg5-retained-url-paths-batch-20261004.json",\n      "role": "batch_marker"\n    },\n    {\n      "maximum_bytes": 1048576,\n      "relative_path": "operations/int-andromeda-match-nonbg5-retained-url-paths-20261004-v1/reservation.json",\n      "role": "reservation"\n    },\n    {\n      "maximum_bytes": 65536,\n      "relative_path": "operations/int-andromeda-match-nonbg5-retained-url-paths-20261004-v1/execution-started.json",\n      "role": "execution_started"\n    },\n    {\n      "maximum_bytes": 16777216,\n      "relative_path": "operations/int-andromeda-match-nonbg5-retained-url-paths-20261004-v1/current-input.json",\n      "role": "private_input"\n    },\n    {\n      "maximum_bytes": 2097152,\n      "relative_path": "operations/int-andromeda-match-nonbg5-retained-url-paths-20261004-v1/result.json",\n      "role": "result"\n    },\n    {\n      "maximum_bytes": 65536,\n      "relative_path": "operations/int-andromeda-match-nonbg5-retained-url-paths-20261004-v1/receipt.json",\n      "role": "receipt"\n    }\n  ],\n  "mode": "match-nonbg5-url-paths-terminal-readback",\n  "operation": "int-andromeda-match-nonbg5-url-paths-terminal-readback-20261004-v1",\n  "schema": "match-nonbg5-url-paths-terminal-readback-fixture/1",\n  "scope": "six_old_url5_terminal_metadata_records_only_no_nf7_or_raw_projection"\n}\n'
FROZEN_NR5_FIXTURE_SHA = '515ccfc283244713f6ecd3b87c3bc5829e1173d9468a66e24d2fa54379950151'

class Nonbg5TerminalReadbackRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.NR5_MODE+' '+registration.NR5_OPERATION+' '+registration.NR5_BATCH

    def test_exact_readonly_scope_and_cross_scope_rejected(self):
        value=self.core.parse_command(self.body())
        self.assertEqual(value,dict(source_sha=SOURCE,mode=registration.NR5_MODE,operation_id=registration.NR5_OPERATION,batch=registration.NR5_BATCH,maximum_writes=0,provider_http_calls=0))
        for body in [self.body().replace(registration.NR5_OPERATION,registration.ANEX2_OPERATION),self.body().replace(registration.NR5_BATCH,registration.NATIVE_BATCH),self.body()+' 1']:
            with self.assertRaises(ValueError):self.core.parse_command(body)

    def test_emitted_remote_and_staged_paths_preserve_stock_executor(self):
        before=self.core.REMOTE;fixed=list(self.core.FIXED)
        registration.activate(self.core,self.core.parse_command(self.body()))
        ast.parse(self.core.REMOTE)
        self.assertIn('def run_match_nonbg5_terminal_readback(stage):',self.core.REMOTE)
        self.assertIn("if mode=='match-nonbg5-url-paths-terminal-readback':",self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.NR5_SOURCE_FILES))
        self.assertIn('os.O_WRONLY|os.O_CREAT|os.O_EXCL',registration.REMOTE_NR5_HANDLER)
        self.assertIn('os.fsync(fd)',registration.REMOTE_NR5_HANDLER)
        self.assertIn('nonbg5_terminal_readback_reservation_readback',registration.REMOTE_NR5_HANDLER)
        self.assertIn('validate_source(data)',registration.REMOTE_NR5_HANDLER)
        collectors=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                collectors.append(eval(compile(ast.Expression(node.test),'<collector>','eval'),{},dict(mode=registration.NR5_MODE)))
        self.assertEqual(collectors,[False,False])
        self.assertIn('reserved_before_retained_read',registration.REMOTE_NR5_HANDLER)
        self.assertNotIn('def run_match_user_delta(stage):',self.core.REMOTE)


    def fixture(self):
        data=dict(schema='match-nonbg5-url-paths-terminal-readback-result/1',operation=registration.NR5_OPERATION,batch=registration.NR5_BATCH,source_sha=SOURCE,provider_http_calls=0,physical_http_attempts=0,database_reads=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,acceptance_evaluated=False,global_uniqueness_evaluated=False,no_replay=True,requested_records=6,private_input_sha256='c'*64,state='completed_read_only_nonbg5_url_paths_terminal_readback')
        keys=['operation','batch','source_sha','state','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads','database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay']
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_NR5_HANDLER,env)
        return env['validate_match_nonbg5_terminal_readback'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

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
            (folder/'hotel_match_nonbg5_url_paths_terminal_readback_v1.py').write_text('pass\n')
            raw=b'{}';(folder/'fixtures'/'hotel_match_nonbg5_url_paths_terminal_readback_v1.json').write_bytes(raw)
            (folder/'hotel_match_nonbg5_retained_url_paths_readonly_v1.py').write_text(FROZEN_URL5_READER)
            (folder/'fixtures'/'hotel_match_nonbg5_retained_url_paths_readonly_v1.json').write_text(FROZEN_URL5_FIXTURE)
            code=registration.REMOTE_NR5_HANDLER.replace(registration.NR5_MANIFEST_SHA,hashlib.sha256(raw).hexdigest())
            env=dict(home=home,project=project,operation=registration.NR5_OPERATION,source=SOURCE,payload=dict(batch=registration.NR5_BATCH,maximum_writes=0,provider_http_calls=0),os=os,json=json,hashlib=hashlib,subprocess=subprocess,safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and p.stat().st_size<=limit,fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
            exec(code,env);synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kw):
                self.assertIn(ops,synced)
                self.assertNotIn('GH_TOKEN',kw['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):env['run_match_nonbg5_terminal_readback'](stage)
                self.assertTrue((home/'.anytoour-match'/'nonbg5-url-paths-terminal-readback-batch-20261004.json').is_file())
                self.assertTrue((ops/registration.NR5_OPERATION/'reservation.json').is_file())
                with self.assertRaises(RuntimeError):env['run_match_nonbg5_terminal_readback'](stage)
                self.assertEqual(call.call_count,1)



class RetainedURLActualCLIIntegrationTest(unittest.TestCase):
    def run_lane(self, prefix, mode):
        with tempfile.TemporaryDirectory() as tmp:
            home=Path(tmp)/'home';ops=home/'.anytoour-match'/'operations';ops.mkdir(parents=True)
            project=home/'www'/'anytoour.ru';project.mkdir(parents=True)
            stage=Path(tmp)/'stage';folder=stage/'scripts'/'diagnostics';(folder/'fixtures').mkdir(parents=True)
            snapshots=[('hotel_match_nonbg5_retained_url_paths_readonly_v1.py',FROZEN_URL5_READER,FROZEN_URL5_READER_SHA),('fixtures/hotel_match_nonbg5_retained_url_paths_readonly_v1.json',FROZEN_URL5_FIXTURE,FROZEN_URL5_FIXTURE_SHA),('hotel_match_nonbg5_url_paths_terminal_readback_v1.py',FROZEN_NR5_READER,FROZEN_NR5_READER_SHA),('fixtures/hotel_match_nonbg5_url_paths_terminal_readback_v1.json',FROZEN_NR5_FIXTURE,FROZEN_NR5_FIXTURE_SHA)]
            for relative,value,digest in snapshots:
                self.assertEqual(hashlib.sha256(value.encode()).hexdigest(),digest)
                (folder/relative).write_text(value)
            if prefix=='NR5':
                old_op=ops/registration.NU5_OPERATION;old_op.mkdir()
                old_reservation={'operation':registration.NU5_OPERATION,'batch':registration.NU5_BATCH,'source_sha':'5e802eacbe43c0925902ce159ef5669b5cd0c7eb','provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
                raw=(json.dumps(old_reservation,sort_keys=True,separators=(',',':'))+'\n').encode()
                (old_op/'reservation.json').write_bytes(raw)
                (home/'.anytoour-match'/'nonbg5-retained-url-paths-batch-20261004.json').write_bytes(raw)
            operation=getattr(registration,prefix+'_OPERATION');batch=getattr(registration,prefix+'_BATCH')
            env=dict(home=home,project=project,operation=operation,source=SOURCE,payload=dict(batch=batch,maximum_writes=0,provider_http_calls=0),os=os,json=json,hashlib=hashlib,subprocess=subprocess,safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and 0<p.stat().st_size<=limit,safe_json=lambda p,limit:json.loads(p.read_bytes()),fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
            exec(getattr(registration,'REMOTE_'+prefix+'_HANDLER'),env)
            # A real Python subprocess runs the frozen feature CLI with the handler's environment.
            # No subprocess mock, no direct execute() call; sensitive ambient keys cannot enter.
            with patch.dict(os.environ,{'GH_TOKEN':'ambient-test-token','MATCH_OPERATION_DIRECTORY':'wrong-ambient-dir','MATCH_MANIFEST':'wrong-ambient-manifest'}):
                lane=env['run_match_'+mode](stage)
            child=ops/operation
            self.assertTrue((child/'execution-started.json').is_file())
            for filename in ('current-input.json','result.json','receipt.json'):
                self.assertTrue((child/filename).is_file())
            self.assertEqual(lane['summary']['source_sha'],SOURCE)
            self.assertEqual(lane['summary']['provider_http_calls'],0)
            self.assertEqual(lane['summary']['written'],0)
            self.assertEqual(lane['summary']['database_writes'],0)
            before={f.name:hashlib.sha256(f.read_bytes()).hexdigest() for f in child.iterdir() if f.is_file()}
            with self.assertRaises(RuntimeError):env['run_match_'+mode](stage)
            self.assertEqual(before,{f.name:hashlib.sha256(f.read_bytes()).hexdigest() for f in child.iterdir() if f.is_file()})
            return lane

    def test_fixed_old_cli_keys_reach_typed_terminal_without_replaying_production(self):
        lane=self.run_lane('NU5','nonbg5_url_paths')
        self.assertFalse(lane['successful'])
        self.assertEqual(lane['summary']['state'],'terminal_failed_no_replay')
        self.assertEqual(lane['summary']['failure_stage'],'prior_metadata_unavailable_or_digest')
        self.assertEqual(lane['summary']['rows_examined'],0)

    def test_new_readback_actual_cli_recovers_reserved_not_started_snapshot(self):
        lane=self.run_lane('NR5','nonbg5_terminal_readback')
        self.assertTrue(lane['successful'])
        self.assertEqual(lane['summary']['rows_examined'],6)
        self.assertEqual(lane['summary']['recovered_path_candidate_references'],0)
        self.assertEqual(lane['summary']['old_supplier_calls'],'unknown')
        self.assertEqual(lane['summary']['old_database_writes'],'unknown')

# BEGIN observed PAGE1 frozen reviewed CLI snapshots
FROZEN_OBSERVED_PAGE1_FILES = [('scripts/diagnostics/hotel_match_observed_page1_identity_readonly_v1.py', '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', '5c8bc6632295afa46a9faa23c7646a14659148b090f6ba8585d6803be5e978d0'), ('scripts/diagnostics/fixtures/hotel_match_observed_page1_identity_readonly_v1.json', 'eNp9U01z2jAQvedXaHTGqSTrw+aWEJcwpYSh5JCTR5bXQS2WGNmENpn898pAk7SH3lb79j097a5eLhDCndlAq/EY4Vb3ZpP4qoPwBHWy049AE1uD623/K2nsz34f4BPFo4HmdxB0b70bmNb1iXZ18C3UOvm/DiNMUkJE8nRWqobyQeUfxrmQJ1SJnItT8cG62h/KrtehjxyqckpTqTL2EQVXv2MZ4UfMBNA91KX+iydOjznfXDofWr21zzH0TQOhi7WCnPm2h2CHTr3gyW0x+TJblNfFFI/xySnlePQGFIubN4CRCCxm09v1t/Lz6u4rHqu383o2nx/PVzf38zUes0FiNo9kEqP71apYTB5miwkeS56O8PJq8qVYrx+WxbFgeTWNAR3h6eruflleP+Bxyl6PdncBzD50Phz9dn4fDJTdJtrHKRG8TnlViUwZLTNeCzCVEU1qUiIzQWNbasmG5xjtvLNGb0vj2zZOsLSxtSLP0jTlKo++w94dc6liTLKc5EPyu6+OSUqpIFJQSmQ0H0dmG236M5RSRjhTmfyAPNvdYJIJGX020RbkoAzhWhjBZFNlXBrgTAtOFCUKgOcZFQ1VWjKSS1pBXlNGleQ6NdF/gG6/7d8lY22e0kxHJQpKZzxLBavqrK6E1rwBUNAQHhvAjYjCGQMAyhpaM2FUrjN8am4L2ln3OCztAg7ofWvQMBGKNr6HLTqtfGPjGo2QgycIyAf7aJ3eoqAPaLmaTYqYQtoY2MXdRH8+CdoF75tLdLc9KTIUfxeyfYfiEteVNj9i0GrrkPGu28dfd4kvXi9+AxPbHcc=', '771fba36e04ad0c051158ac0c08e8e228eef7e1e9e2aa2850d719e3d915dab94'), ('tests/hotel_match_observed_page1_identity_readonly_v1_test.py', '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', 'c01966ae238c8da1b71a85ba53bd9bc002af28d675426693eef29fda66435163')]
FROZEN_OBSERVED_PAGE1_PRODUCERS = [('app/integrations/andromeda-normalizer.php', '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', '83f5dc66b640d43f7c2048fbb8632b4e5ed73405'), ('app/integrations/andromeda-offer-store.php', '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', '513316e94d8b35965066102e6178694e71c00fad'), ('api-andromeda-search3-preview.php', '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', '06f48fd510fc0dd721899679f5b045f1d81a684c')]
# END observed PAGE1 frozen reviewed CLI snapshots

class ObservedPage1RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core()
        registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.OBSERVED_PAGE1_MODE+' '+registration.OBSERVED_PAGE1_OPERATION+' '+registration.OBSERVED_PAGE1_BATCH

    def test_one_fixed_new_scope_rejects_old_scope_extra_arguments_and_aliases(self):
        expected=dict(source_sha=SOURCE,mode=registration.OBSERVED_PAGE1_MODE,
                      operation_id=registration.OBSERVED_PAGE1_OPERATION,
                      batch=registration.OBSERVED_PAGE1_BATCH,maximum_writes=0,provider_http_calls=0)
        self.assertEqual(self.core.parse_command(self.body()),expected)
        for body in [self.body().replace(registration.OBSERVED_PAGE1_BATCH,registration.NATIVE_BATCH),
                     self.body().replace(registration.OBSERVED_PAGE1_OPERATION,registration.DELTA_OPERATION),
                     self.body().replace('20261005-v1','20261005-v2'),
                     self.body()+' 1',self.body().replace(SOURCE,'bad')]:
            with self.assertRaises(ValueError):self.core.parse_command(body)
        for key,value in [('maximum_writes',1),('provider_http_calls',True),
                          ('maximum_writes',False),('provider_http_calls',False)]:
            bad=copy.deepcopy(expected);bad[key]=value
            with self.assertRaises(ValueError):registration.activate(self.core,bad)

    def test_actor_current_heads_and_canonical_comment_are_still_required(self):
        body=self.body()
        event={'issue':{'number':self.core.ISSUE},'comment':{'id':456,'body':body,
               'user':{'id':226193297},'author_association':'OWNER'}}
        def api(path,token):
            if path=='/issues/comments/456':return copy.deepcopy(event['comment'])
            if path=='/git/ref/heads/main':return {'object':{'sha':CONTROL}}
            if path=='/git/ref/heads/'+self.core.FEATURE:return {'object':{'sha':SOURCE}}
            raise AssertionError(path)
        with patch.object(self.core,'api_get',side_effect=api):
            value=self.core.checked_event('fixture',event,CONTROL)
            self.assertEqual(value['provider_http_calls'],0)
            for mutate in [lambda e:e['issue'].update(number=3419),
                           lambda e:e['comment']['user'].update(id=1),
                           lambda e:e['comment'].update(author_association='NONE')]:
                bad=copy.deepcopy(event);mutate(bad)
                with self.assertRaises(ValueError):self.core.checked_event('fixture',bad,CONTROL)
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,'c'*40)
        with patch.object(self.core,'api_get',side_effect=lambda p,t:{'body':'changed','user':{'id':226193297}}):
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,CONTROL)

    def test_activation_stages_only_fixed_bytes_and_excludes_both_generic_collectors(self):
        fixed=list(self.core.FIXED)
        with patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,self.core.parse_command(self.body()))
            slot.assert_not_called()
        ast.parse(self.core.REMOTE)
        self.assertEqual(self.core.FIXED,fixed+list(registration.OBSERVED_PAGE1_SOURCE_FILES))
        self.assertIn('def run_match_observed_page1_identity(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_user_delta(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_nonbg5_url_paths(stage):',self.core.REMOTE)
        self.assertIn("r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*'",self.core.REMOTE)
        guards=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.If) and isinstance(node.test,ast.Compare) and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode' and isinstance(node.test.ops[0],ast.NotIn):
                guards.append(eval(compile(ast.Expression(node.test),'<collector>','eval'),{},dict(mode=registration.OBSERVED_PAGE1_MODE)))
        self.assertEqual(guards,[False,False])
        self.assertIn('observed_page1_reservation_readback',registration.REMOTE_OBSERVED_PAGE1_HANDLER)
        self.assertIn("'MATCH_PRIVATE_DIRECTORY':str(child)",registration.REMOTE_OBSERVED_PAGE1_HANDLER)
        self.assertIn("'MATCH_CURRENT_MANIFEST_PATH':str(manifest)",registration.REMOTE_OBSERVED_PAGE1_HANDLER)
        self.assertIn("'MATCH_RESULT_PATH':str(result_path)",registration.REMOTE_OBSERVED_PAGE1_HANDLER)

    def fixture(self):
        data=dict(schema='match-observed-page1-identity-result/1',operation=registration.OBSERVED_PAGE1_OPERATION,
                  batch=registration.OBSERVED_PAGE1_BATCH,source_sha=SOURCE,provider_http_calls=0,
                  physical_http_attempts=0,database_reads=0,database_writes=0,mapping_writes=0,
                  booking_calls=0,lead_calls=0,accepted=0,written=0,safe_to_write_now=False,
                  acceptance_evaluated=False,global_uniqueness_evaluated=False,
                  raw_samo_evidence_verified=False,session_identity_verified=False,
                  route_identity_verified=False,current_registry_verified=False,no_replay=True,
                  private_input_sha256='c'*64,state='completed_read_only')
        keys=set(data)-{'schema'}
        receipt={k:data[k] for k in keys};receipt['result_sha256']='d'*64
        return data,receipt

    def validate(self,data,receipt,validator=lambda x:None):
        env={'fail':lambda reason:(_ for _ in ()).throw(RuntimeError(reason))}
        exec(registration.REMOTE_OBSERVED_PAGE1_HANDLER,env)
        return env['validate_match_observed_page1_identity'](data,receipt,'d'*64,'c'*64,SOURCE,validator)

    def test_private_input_result_source_and_receipt_bytes_are_bound(self):
        d,r=self.fixture();self.assertEqual(self.validate(d,r),d)
        for key,value in [('source_sha','e'*40),('private_input_sha256','f'*64),('result_sha256','f'*64),('state','invented_success')]:
            d,r=self.fixture();r[key]=value
            with self.assertRaises(RuntimeError):self.validate(d,r)
        d,r=self.fixture();r['unreviewed_field']=0
        with self.assertRaises(RuntimeError):self.validate(d,r)

    def test_normalized_snapshot_cannot_claim_raw_route_session_current_or_write_authority(self):
        for key,value in [('provider_http_calls',1),('physical_http_attempts',1),('database_reads',True),
                          ('database_writes',1),('mapping_writes',1),('accepted',True),('written',1),
                          ('safe_to_write_now',True),('raw_samo_evidence_verified',True),
                          ('session_identity_verified',True),('route_identity_verified',True),
                          ('current_registry_verified',True),('global_uniqueness_evaluated',True),
                          ('acceptance_evaluated',True),('no_replay',False)]:
            d,r=self.fixture();d[key]=value;r[key]=value
            with self.assertRaises(RuntimeError):self.validate(d,r)

    def test_reviewed_source_validator_failure_is_not_ignored(self):
        d,r=self.fixture()
        with self.assertRaises(RuntimeError):
            self.validate(d,r,lambda x:(_ for _ in ()).throw(ValueError('private_output')))

    def stage(self,tmp):
        home=Path(tmp)/'home';ops=home/'.anytoour-match'/'operations';ops.mkdir(parents=True)
        project=home/'www'/'anytoour.ru';project.mkdir(parents=True)
        stage=Path(tmp)/'stage'
        for relative,encoded,digest in FROZEN_OBSERVED_PAGE1_FILES:
            raw=zlib.decompress(base64.b64decode(encoded))
            self.assertEqual(hashlib.sha256(raw).hexdigest(),digest)
            self.assertIn(digest,registration.REMOTE_OBSERVED_PAGE1_HANDLER)
            path=stage/relative;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(raw)
        env=dict(home=home,project=project,operation=registration.OBSERVED_PAGE1_OPERATION,
                 source=SOURCE,payload=dict(batch=registration.OBSERVED_PAGE1_BATCH,maximum_writes=0,provider_http_calls=0),
                 os=os,json=json,hashlib=hashlib,subprocess=subprocess,
                 safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and 0<p.stat().st_size<=limit,
                 safe_json=lambda p,limit:json.loads(p.read_bytes()),
                 fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
        exec(registration.REMOTE_OBSERVED_PAGE1_HANDLER,env)
        return home,project,stage,ops,env

    def test_changed_or_symlinked_source_fixture_or_test_fails_before_reservation(self):
        for relative in registration.OBSERVED_PAGE1_SOURCE_FILES:
            for mutation in ('bytes','symlink'):
                with tempfile.TemporaryDirectory() as tmp:
                    home,project,stage,ops,env=self.stage(tmp)
                    path=stage/relative
                    if mutation=='bytes':path.write_bytes(path.read_bytes()+b'\n')
                    else:
                        original=path.with_suffix(path.suffix+'.original');path.rename(original);path.symlink_to(original)
                    with patch.object(subprocess,'run') as call:
                        with self.assertRaisesRegex(RuntimeError,'observed_page1_source_binding'):
                            env['run_match_observed_page1_identity'](stage)
                        call.assert_not_called()
                    self.assertEqual(list(ops.iterdir()),[])
                    self.assertFalse((home/'.anytoour-match'/'observed-page1-batch-20261004-175945.json').exists())

    def test_timeout_after_durable_op_and_batch_consumes_scope_and_strips_ambient_keys(self):
        with tempfile.TemporaryDirectory() as tmp:
            home,project,stage,ops,env=self.stage(tmp)
            synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kw):
                self.assertIn(ops,synced)
                child=ops/registration.OBSERVED_PAGE1_OPERATION
                self.assertIn(child,synced)
                self.assertTrue((home/'.anytoour-match'/'observed-page1-batch-20261004-175945.json').is_file())
                self.assertTrue((child/'reservation.json').is_file())
                self.assertEqual(kw['cwd'],project)
                self.assertEqual(kw['env']['MATCH_PRIVATE_DIRECTORY'],str(child))
                self.assertEqual(kw['env']['MATCH_RESULT_PATH'],str(child/'result.json'))
                self.assertEqual(kw['env']['MATCH_CURRENT_MANIFEST_PATH'],str(stage/registration.OBSERVED_PAGE1_SOURCE_FILES[1]))
                self.assertEqual(kw['env']['MATCH_SOURCE_ROOT'],str(stage))
                self.assertEqual(kw['env']['MATCH_SOURCE_SHA'],SOURCE)
                self.assertNotIn('GH_TOKEN',kw['env'])
                self.assertNotIn('MATCH_MANIFEST_PATH',kw['env'])
                self.assertNotIn('MATCH_OPERATION_DIR',kw['env'])
                self.assertNotIn('PYTHONPATH',kw['env'])
                self.assertEqual(set(kw['env'])-{'PATH','HOME','LANG','LC_ALL'},
                                 {'ANYTOUR_ROOT','MATCH_SOURCE_ROOT','MATCH_PRIVATE_DIRECTORY',
                                  'MATCH_CURRENT_MANIFEST_PATH','MATCH_RESULT_PATH','MATCH_SOURCE_SHA'})
                raise subprocess.TimeoutExpired('python3',300)
            with patch.dict(os.environ,{'GH_TOKEN':'ambient-secret','MATCH_PRIVATE_DIRECTORY':'wrong-dir',
                                       'MATCH_CURRENT_MANIFEST_PATH':'wrong-fixture','MATCH_RESULT_PATH':'wrong-result',
                                       'MATCH_MANIFEST_PATH':'old-env','MATCH_OPERATION_DIR':'old-env',
                                       'PYTHONPATH':'wrong-site'}),patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):env['run_match_observed_page1_identity'](stage)
                with self.assertRaisesRegex(RuntimeError,'observed_page1_child_exists_no_replay'):
                    env['run_match_observed_page1_identity'](stage)
                self.assertEqual(call.call_count,1)

    def test_distinct_batch_stays_consumed_even_if_child_directory_is_removed(self):
        with tempfile.TemporaryDirectory() as tmp:
            home,project,stage,ops,env=self.stage(tmp)
            marker=home/'.anytoour-match'/'observed-page1-batch-20261004-175945.json'
            marker.write_bytes(b'previous-reservation')
            with patch.object(subprocess,'run') as call:
                with self.assertRaises(FileExistsError):env['run_match_observed_page1_identity'](stage)
                call.assert_not_called()
            self.assertEqual(marker.read_bytes(),b'previous-reservation')
            self.assertEqual(list(ops.iterdir()),[])

    def retained(self,project,with_page=True):
        runtime=project/'_preview'/'search3-anex-candidate';runtime.mkdir(parents=True)
        private=project.parent.parent/'.andromeda-fixture';searches=private/'searches';searches.mkdir(parents=True)
        config=runtime/'.andromeda-private.php'
        config.write_text("<?php return ['enabled'=>true,'catalog_path'=>"+repr(str(private/'catalog.json'))+"];\n")
        for relative,encoded,blob in FROZEN_OBSERVED_PAGE1_PRODUCERS:
            raw=zlib.decompress(base64.b64decode(encoded))
            self.assertEqual(hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest(),blob)
            path=runtime/relative;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(raw)
        ref='3'*64
        criterion={'CHECKIN_BEG':'20261014','CHECKIN_END':'20261020','NIGHTS_FROM':7,'NIGHTS_TILL':7,
                   'ADULT':2,'CHILD':0,'CURRENCYINC':643,'PACKETTYPE':0,'PAGE':1,'GROUP_BY':32,
                   'TOWNFROMINC':12,'STATEINC':6}
        # Fifty actual normalized offer rows; supplier price/offer/session fields remain private.
        offers=[];ids={}
        for i in range(50):
            operator=('5','315','342','367')[i%4]
            oref='offer_'+format(i+1,'064x')
            offers.append({'provider':'andromeda','search_ref':ref,'generation':1,'selection_enabled':False,
                           'offer_ref':oref,'supplier_namespace':'operator_'+operator if i<8 else 'andromeda_catalog',
                           'external_hotel_id':str(70000+i),'operator_ref':operator,
                           'local_hotel_id':None if i<8 else 90000+i,
                           'hotel':'Fixture Property '+str(i),'operator':'Fixture Operator '+operator,
                           'hotel_content':{'source':'andromeda','image_url':None,
                                            'hotel_url':'https://operator.example.com/hotel/'+str(i),
                                            'region':'Fixture Region','category':5},
                           'price':123456+i,'currency':'RUB','booking_ref':'PRIVATE_DO_NOT_EXPORT'})
            ids[oref]='supplier-private-offer-'+str(i)
        snapshot={'provider':'andromeda','search_ref':ref,'generation':1,'page':1,
                  'selection_enabled':False,'status':'partial','pages_count':12,'offers':offers,'rejected':[]}
        page={'version':1,'search_ref':ref,'generation':1,'status':'partial','error':None,
              'criteria':criterion,'store':{'version':1,'search_ref':ref,'generation':1,
              'created_at':1791136785,'expires_at':1791137685,'criteria':criterion,
              'snapshot':snapshot,'raw_ids':ids}}
        selected=searches/(ref+'-1.json')
        if with_page:
            selected.write_text(json.dumps(page,ensure_ascii=False))
            os.utime(selected,(1791136785,1791136785))
        unrelated=searches/(ref+'-2.json');unrelated.write_bytes(b'OLD_FAILED_PAGE2_PRIVATE_DATA')
        if shutil.which('php'):
            php_path=None
        else:
            # Only the local PHP executable dependency is stubbed. The checked source
            # Python CLI, actual files, reservations, environment and validators run.
            # Source CI and this control workflow require installed real PHP.
            php_path=project.parent.parent/'php-dependency-stub';php_path.mkdir()
            stub=php_path/'php'
            stub.write_text('#!'+__import__('sys').executable+'\nimport json, pathlib, sys\n'
                            'a=sys.argv[1:]\n'
                            'assert a[:3]==["-d","allow_url_fopen=0","-d"]\n'
                            'assert "-n" not in a and not any(v.startswith("open_basedir=") for v in a)\n'
                            'assert "disable_functions=" in a[3]\n'
                            'assert "curl_exec" in a[3] and "proc_open" in a[3] and "stream_socket_client" in a[3]\n'
                            'assert a[4]=="-r" and pathlib.Path(a[-1]).name==".andromeda-private.php"\n'
                            'print(json.dumps({"directory":'+repr(str(private))+'}))\n')
            stub.chmod(0o700)
        return selected,unrelated,php_path

    def actual_lane(self,with_page=True,stdout_mutation=None,artifact_mutation=None):
        with tempfile.TemporaryDirectory() as tmp:
            home,project,stage,ops,env=self.stage(tmp)
            selected,unrelated,php_path=self.retained(project,with_page)
            unrelated_before=hashlib.sha256(unrelated.read_bytes()).hexdigest()
            additions={'GH_TOKEN':'ambient-private-token','PYTHONPATH':'wrong-python-path',
                       'MATCH_PRIVATE_DIRECTORY':'wrong-directory','MATCH_CURRENT_MANIFEST_PATH':'wrong-fixture',
                       'MATCH_RESULT_PATH':'wrong-result'}
            if php_path is not None:additions['PATH']=str(php_path)+os.pathsep+os.environ.get('PATH','')
            real_run=subprocess.run
            def change_stdout(*args,**kw):
                process=real_run(*args,**kw)
                if stdout_mutation:process.stdout=stdout_mutation(process.stdout)
                if artifact_mutation:artifact_mutation(ops/registration.OBSERVED_PAGE1_OPERATION)
                return process
            with patch.dict(os.environ,additions):
                if stdout_mutation or artifact_mutation:
                    with patch.object(subprocess,'run',side_effect=change_stdout):
                        with self.assertRaises(RuntimeError):env['run_match_observed_page1_identity'](stage)
                    lane=None
                else:
                    # This is a real checked feature Python CLI launched by the emitted
                    # main handler; neither Python subprocess nor execute() is mocked.
                    lane=env['run_match_observed_page1_identity'](stage)
            child=ops/registration.OBSERVED_PAGE1_OPERATION
            self.assertTrue((child/'execution-started.json').is_file())
            self.assertEqual(hashlib.sha256(unrelated.read_bytes()).hexdigest(),unrelated_before)
            for filename in ('current-input.json','result.json','receipt.json'):
                self.assertTrue((child/filename).is_file())
            public=json.loads((child/'result.json').read_bytes())
            public_bytes=(child/'result.json').read_bytes()
            self.assertNotIn(b'PRIVATE_DO_NOT_EXPORT',public_bytes)
            self.assertNotIn(b'supplier-private-offer',public_bytes)
            self.assertNotIn(b'offer_ref',public_bytes)
            self.assertNotIn(b'search_ref"',public_bytes)
            self.assertNotIn(str(home).encode(),public_bytes)
            self.assertNotIn(b'123456',public_bytes)
            self.assertEqual(public['source_sha'],SOURCE)
            self.assertEqual(public['provider_http_calls'],0)
            self.assertEqual(public['database_reads'],0)
            self.assertEqual(public['written'],0)
            before={f.name:hashlib.sha256(f.read_bytes()).hexdigest() for f in child.iterdir() if f.is_file()}
            with self.assertRaises(RuntimeError):env['run_match_observed_page1_identity'](stage)
            self.assertEqual(before,{f.name:hashlib.sha256(f.read_bytes()).hexdigest() for f in child.iterdir() if f.is_file()})
            return lane,public

    def test_paired_checked_source_cli_exports_all_50_normalized_offer_rows_and_all_namespaces(self):
        lane,data=self.actual_lane()
        self.assertTrue(lane['successful'])
        self.assertEqual(data['state'],'completed_read_only')
        self.assertEqual(data['examined_offers'],50)
        self.assertEqual(data['unique_hotel_identities'],50)
        self.assertEqual(data['unresolved_offer_count'],8)
        self.assertEqual(data['resolved_offer_count'],42)
        self.assertEqual({op['operator_ref'] for r in data['hotel_roster'] for op in r['operator_observations']},
                         {'5','315','342','367'})
        self.assertFalse(data['raw_samo_evidence_verified'])
        self.assertFalse(data['route_identity_verified'])
        self.assertFalse(data['current_registry_verified'])
        self.assertFalse(data['safe_to_write_now'])

    def test_paired_checked_source_cli_missing_first_page_saves_typed_failure_and_consumes_once(self):
        lane,data=self.actual_lane(with_page=False)
        self.assertFalse(lane['successful'])
        self.assertEqual(data['state'],'terminal_failed_no_replay')
        self.assertEqual(data['hotel_roster'],[])
        self.assertEqual(data['examined_offers'],0)

    def test_bool_float_and_duplicate_stdout_counters_rejected_after_actual_cli_remain_consumed(self):
        for replacement in ('false','0.0'):
            self.actual_lane(stdout_mutation=lambda raw,r=replacement:raw.replace('"accepted": 0','"accepted": '+r))
        self.actual_lane(stdout_mutation=lambda raw:raw.replace('"accepted": 0','"accepted": 0, "accepted": 0'))

    def test_duplicate_result_or_receipt_keys_rejected_before_source_validation_after_actual_cli(self):
        def changed_result(child):
            path=child/'result.json'
            raw=path.read_bytes().replace(b'"accepted":0',b'"accepted":0,"accepted":0')
            path.write_bytes(raw)
            receipt_path=child/'receipt.json';receipt=json.loads(receipt_path.read_bytes())
            receipt['result_sha256']=hashlib.sha256(raw).hexdigest()
            receipt_path.write_text(json.dumps(receipt,separators=(',',':'))+'\n')
        def changed_receipt(child):
            path=child/'receipt.json'
            path.write_bytes(path.read_bytes().replace(b'"accepted":0',b'"accepted":0,"accepted":0'))
        self.actual_lane(artifact_mutation=changed_result)
        self.actual_lane(artifact_mutation=changed_receipt)


# Exact independent source PR4431 head1334b4cf bytes for paired offline contract/actual-CLI evidence.
FROZEN_PASSIVE_OCT4_FILES = (('scripts/diagnostics/hotel_match_passive_oct4_frontier_readonly_v1.py', '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', 'c86a8b34ef288303f19d93599ff842d7e8b99299231b3dbeb37e364f618af4b3'), ('scripts/diagnostics/fixtures/hotel_match_passive_oct4_frontier_readonly_v1.json', 'eNqFVE2P5CYQvc+vQL7k0r0BbAzuWw7RKpcoh71bfBTTKDZYgLtbWs1/T9nemZ3ZjRLJaqmpeq+qHq/4+kRIU+wVZt1cSDPraq/nRZcSbnBOtnZnn1OsAfLZh0ddM/zKmtMGSgtkXUOKGy7EetbR5TSD0+f/YuGU94xScb594zFb8sbxId+ATxmYFEPHj7w5OfifFo/Eqs0EY9Tznv7W1VhAZ3sdr6nCNCZTIN/2/ssBK2nNFsYlpxtEHS286wlP4Rbg/srxDn2A7yG6dB/XahH1tZnSHfIYop3WDd5cmm3sM6Nn2hFKL/vXnJp1QRFHePxrHpMXMVxw/Je9REZ+qxfkF5TS/WjrNThkuNa6YHCaCoaP2KwfYV7n8Z5Dhe/HCHGrRchzqKOZktmmFN6ZVoDjXHFmobfWWN5pp3pgHETXg8RvGD4IZXSBsVx32+iWdYIBDJz3krIWcarvjTFWtS3IVgvuBFPqIECtYAH8iXUT1q65pLzrZnVMMeAgo03zvMWDa1AFxVqqBKen5vDFmNe4h1rJGW2HQXH+PQZlnerWGhc9Sqq994PqwPrBSGaYY73jTAnlup717cB77rtO04FJ4xUgSILUUihqudVuuyftK0r2oSgOxvv+LfRDTRhaSXveGj4wOgClinLuJRWgOcqplJDgO8WdE7rrqQOQ4IRvFdiu61uDNWfQMcRnJPtyBVJg0bhuQFh3EYzotaaib0DeOZHs1i1Ekwh3kqHqEMGRw7LE4orAo55ITJXUayhk35NfCoFHKBXQ8CRlEtfZQCbJkzXiUGm6IcW+MuXTNyPudnXg8I5iWXG1xkU/AzsuMAM26UZdmwuTA0MrSCVOzTvpuk4qTiVD6XINXtvjkjGVDeiifkBRf5KTCtcJdNLQeqeo71vonOCt51Z5D4JirUGizphPJTWgDROaAhPcAtP6g5x/1ELeHi+C7wNBo4UMx8KRv377/Dt7lYtoPH+d8xP5M6HI4bbdgw8TnEiIy4qa2muY3Ak1txAW/I9CohDOaPs3wcdpJqESVFxbC6UgUfPy9PL0D2Qbupg=', '64d4b3e497988068676d29b8c6318f6b02ef042f109cef3dda118cfcf164b3d2'), ('tests/hotel_match_passive_oct4_frontier_readonly_v1_test.py', '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'), ('app/integrations/andromeda-hotel-observations.php', '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'), ('v2/data/db-v1.php', 'eNqlVulu20YQ/q+n2ABuVnJtK22KIrWrGIpFNwJsSaBotEHiEityaTGlSGZ3KVtOBPS+D/RNeqX38Qr2G3VnyaUoiXJSVD+kj7Mz3zczu9zR67vxMK7U19craB01w4kVJQx5PuNiMyZMTJBLBEGtO2hIg5iyLekGntbQ59ItoEj++qGgofCjkATBRBqigAjqIo9FIySGFAX+mCJOCXOGKCZiuIXaAg0JR2EEXNx3KaKeRx3BUSKJAkTP4sB3fCHpGH2U+EzSDSaIpNnQ0I0jKVrfO2ijh9EAkqpXKi51AsJolQvmO8IWk5jyxku1nUrFS0IH8kPjl21gsN2B7USh559Ua9uIMEYmlccVJD9rMfPHMvs9tYoayPVZSEa0atuttmnbNbSFcD2N3ZKdwzsqzPdQ1ec2NKQ6T1Gr6QrsKHTogoDMTanScCy1uCDCd1CeraokPEFrkIFMNHtMM4UPoyJhIZLmUTVzrp1QuRnjahpTS9Obpj9rMm+pEYr/oeVSzw+pm/Gj3XlxLaDl0TbCOM8hTcLlodSHkqu42blndY9Mu9W0mnar38G1WT9Tx0ZDMtTyqFygNDQVSDhlKxTu2Ed9w5xTSb21jI5dpTMjyI4L4fw0Yq6MWdiApcBes99/s2u25tRn8SoDdP163uErCGqFfSlLYXX2i0lMK+X9LvAPIy5WtvNut29prpwojdAt1fGrk9IkM004PSs1O81DY0kzjdCaOn61piYp9DFiq+vsdc3lOtMIranjr2j+c5PgmzdvvIoL2c36ei0/KGmV15Z2rPCWcXnbhMKrzq3BB48m/FGwDYyNF/gOyMKvOwBOQM6QME5FIxHerdHgFbyxRKHSKTEDV4kZiOeshUZMi2cxu2ju56tY1oJR47YqasaM4V1N7YAKC/qNSBf1U+pwvFOZlg4EmAS9VjebA9n1uBa7kWxjmARB8aWVRl9tskMjD6JqOmtY03eDo2dIydQpsKWm+6rKY30PPHmC8gVV53HJqymGLDpFIT1FJozNETXOHBpDXfLsZaNcDUz4GhCuhnUYCZQyJ3KqLtwDul5JKatayG1jMaWCIW+5NM52TnJsbzcty7QN0zzstgzYEGXMnm3jrT2jZ7W7nY2SoJax3zw6sOx9w9q7a8/FpyZ5mXX3yiKNw6ODpmXYPdPoNU2jD3EeCTgtc+5bZrvzRnv/Xiq05H2sr4m5PS47RWHERiTwz6kt6JnIR+qYBMlspup/Gsoqm60G6Ghgy1URBdEpZdV0bQPhI2t/8xbWYzwPka42o3FAHFrFl99i6XnxVH5nSgveMaMnM/f6/bcfxI8PpvKrMz1+sZ5ANJoFo93dDF5N84A/O7b4/yTPraxxPEhOnrNdK3o9X/qIxNK1cIVcfIcbtzHBG/jie0ADQD8AGgP6EdAJoJ8AuYCeAqKAfgZ0PgT4i4KAfgXkA/oN0KRwQ+KL38H0Diz+ASgA9CegEaC/AIWA/gYUAfoHUCzR5buAGKD3AHFA7wMSgD4AlBTFLj8EkweLHwGCPC8/BuQA+kQhZfxU8Sn4mYKp+XPAAL7ICsGXX2rTV1kTCnJfK68EVr9RkOD8al08pWJ2lmFPnnkyyeb5jc3X5KmEg7X5Xw6W8len61/3Xa7P', 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13'))

class PassiveOct4RegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.PASSIVE_OCT4_MODE+' '+registration.PASSIVE_OCT4_OPERATION+' '+registration.PASSIVE_OCT4_BATCH

    def test_fixed_scope_and_old_modes_remain_separate(self):
        expected=dict(source_sha=SOURCE,mode=registration.PASSIVE_OCT4_MODE,
                      operation_id=registration.PASSIVE_OCT4_OPERATION,
                      batch=registration.PASSIVE_OCT4_BATCH,maximum_writes=0,provider_http_calls=0)
        self.assertEqual(self.core.parse_command(self.body()),expected)
        for altered in [self.body()+' 1',self.body().replace(SOURCE,'invalid'),
                        self.body().replace(registration.PASSIVE_OCT4_BATCH,registration.OBSERVED_PAGE1_BATCH),
                        self.body().replace(registration.PASSIVE_OCT4_OPERATION,registration.OBSERVED_PAGE1_OPERATION),
                        self.body().replace('20261005-v1','20261005-v2')]:
            with self.assertRaises(ValueError):self.core.parse_command(altered)
        for key,value in [('maximum_writes',True),('maximum_writes',1),('provider_http_calls',False),('provider_http_calls',1)]:
            changed=expected.copy();changed[key]=value
            with self.assertRaises(ValueError):registration.activate(self.core,changed)

    def test_original_actor_issue_comment_and_fresh_heads_guard(self):
        event={'issue':{'number':4217},'comment':{'id':123,'body':self.body(),
               'user':{'id':226193297},'author_association':'OWNER'}}
        def api(path,token):
            if path=='/issues/comments/123':return copy.deepcopy(event['comment'])
            if path=='/git/ref/heads/main':return {'object':{'sha':CONTROL}}
            if path=='/git/ref/heads/'+self.core.FEATURE:return {'object':{'sha':SOURCE}}
            raise AssertionError(path)
        with patch.object(self.core,'api_get',side_effect=api):
            self.assertEqual(self.core.checked_event('fixture',event,CONTROL)['maximum_writes'],0)
            for change in [lambda e:e['issue'].update(number=3419),lambda e:e['issue'].update(number=1971),
                           lambda e:e['comment']['user'].update(id=1),lambda e:e['comment'].update(author_association='NONE')]:
                altered=copy.deepcopy(event);change(altered)
                with self.assertRaises(ValueError):self.core.checked_event('fixture',altered,CONTROL)
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,'c'*40)
        with patch.object(self.core,'api_get',side_effect=lambda path,token:{'body':'changed','user':{'id':226193297}}):
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,CONTROL)
        def moved(path,token):
            return {'object':{'sha':'c'*40}} if path.endswith(self.core.FEATURE) else api(path,token)
        with patch.object(self.core,'api_get',side_effect=moved):
            with self.assertRaises(ValueError):self.core.checked_event('fixture',event,CONTROL)

    def test_only_passive_source_is_staged_without_supplier_or_generic_collectors(self):
        fixed=list(self.core.FIXED)
        with patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,self.core.parse_command(self.body()));slot.assert_not_called()
        self.assertEqual(self.core.FIXED,fixed+list(registration.PASSIVE_OCT4_SOURCE_FILES))
        self.assertIn('def run_match_passive_oct4(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_observed_page1_identity(stage):',self.core.REMOTE)
        guards=[node.test for node in ast.walk(ast.parse(self.core.REMOTE))
                if isinstance(node,ast.If) and isinstance(node.test,ast.Compare)
                and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode'
                and isinstance(node.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:self.assertFalse(eval(compile(ast.Expression(guard),'<collector>','eval'),{},dict(mode=registration.PASSIVE_OCT4_MODE)))
        remote_command=base64.b64encode(zlib.compress(self.core.REMOTE.encode(),9))
        self.assertLess(len(remote_command)+100,65536)

    def stage(self,tmp):
        stage=Path(tmp)/'stage'
        for relative,encoded,digest in FROZEN_PASSIVE_OCT4_FILES:
            raw=zlib.decompress(base64.b64decode(encoded))
            self.assertEqual(hashlib.sha256(raw).hexdigest(),digest)
            path=stage/relative;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(raw)
            if relative in registration.PASSIVE_OCT4_SOURCE_FILES:self.assertIn(digest,registration.REMOTE_PASSIVE_OCT4_HANDLER)
        spec=importlib.util.spec_from_file_location('paired_passive_source_tests',stage/registration.PASSIVE_OCT4_SOURCE_FILES[2])
        source_tests=importlib.util.module_from_spec(spec);spec.loader.exec_module(source_tests)
        case=source_tests.build_cli_case(Path(tmp)/'fixture')
        # These are newly created synthetic test reservations, never runtime data.
        shutil.rmtree(case['opdir']);case['marker'].unlink()
        ns=dict(home=case['home'],project=case['project'],operation=registration.PASSIVE_OCT4_OPERATION,
                source=SOURCE,payload=dict(batch=registration.PASSIVE_OCT4_BATCH,maximum_writes=0,provider_http_calls=0),
                os=os,json=json,hashlib=hashlib,subprocess=subprocess,
                safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and 0<p.stat().st_size<=limit,
                fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
        exec(registration.REMOTE_PASSIVE_OCT4_HANDLER,ns)
        return stage,source_tests,case,ns

    def run_case(self,case,stage,ns):
        with patch.dict(os.environ,{'PATH':case['env'].get('PATH','')}):
            return ns['run_match_passive_oct4'](stage)

    def test_changed_source_fixture_test_or_symlink_fails_before_reservation(self):
        for relative in registration.PASSIVE_OCT4_SOURCE_FILES:
            for kind in ('bytes','symlink'):
                with tempfile.TemporaryDirectory() as tmp:
                    stage,t,case,ns=self.stage(tmp);path=stage/relative
                    if kind=='bytes':path.write_bytes(path.read_bytes()+b'\n')
                    else:
                        target=path.with_suffix('.original');path.rename(target);path.symlink_to(target)
                    with patch.object(subprocess,'run') as call:
                        with self.assertRaisesRegex(RuntimeError,'passive_oct4_source_binding'):ns['run_match_passive_oct4'](stage)
                        call.assert_not_called()
                    self.assertFalse(case['marker'].exists());self.assertFalse(case['opdir'].exists())

    def test_timeout_consumes_operation_and_batch_before_any_child_with_minimal_env(self):
        with tempfile.TemporaryDirectory() as tmp:
            stage,t,case,ns=self.stage(tmp);synced=[];real=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real(fd)
            def child(*args,**kwargs):
                self.assertIn(case['marker'].parent,synced);self.assertIn(case['opdir'].parent,synced)
                self.assertIn(case['opdir'],synced)
                self.assertEqual(json.loads(case['marker'].read_bytes())['state'],'reserved_before_database_read')
                self.assertTrue((case['opdir']/'reservation.json').is_file())
                self.assertEqual(set(kwargs['env'])-{'PATH','HOME','LANG','LC_ALL'},
                                 {'ANYTOUR_ROOT','MATCH_SOURCE_ROOT','MATCH_PRIVATE_DIRECTORY','MATCH_CURRENT_MANIFEST_PATH','MATCH_RESULT_PATH','MATCH_SOURCE_SHA'})
                self.assertNotIn('GH_TOKEN',kwargs['env']);self.assertNotIn('PYTHONPATH',kwargs['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.dict(os.environ,{'GH_TOKEN':'fixture-secret','PYTHONPATH':'wrong','MATCH_OPERATION_DIR':'wrong'}),patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):ns['run_match_passive_oct4'](stage)
                with self.assertRaisesRegex(RuntimeError,'passive_oct4_child_exists_no_replay'):ns['run_match_passive_oct4'](stage)
                self.assertEqual(call.call_count,1)
            shutil.rmtree(case['opdir'])
            with patch.object(subprocess,'run') as call:
                with self.assertRaises(FileExistsError):ns['run_match_passive_oct4'](stage)
                call.assert_not_called()

    def test_actual_pair_keeps_full_raw_before_row_hold_and_window_excludes_page1(self):
        with tempfile.TemporaryDirectory() as tmp:
            stage,t,case,ns=self.stage(tmp)
            rows=[t.valid_row(1),t.valid_row(2,hotel_url='https://operator.com/hotel?token=opaque'),
                  t.valid_row(3,observed_at_utc='2026-10-04 17:59:42'),
                  t.valid_row(4,observed_at_utc='2026-10-03 23:59:59')]
            t.write_db(case,rows)
            lane=self.run_case(case,stage,ns);data=lane['summary']
            self.assertTrue(lane['successful']);self.assertEqual(data['state'],'completed_with_holds')
            self.assertEqual((data['rows_captured'],data['rows_retained'],data['rows_held']),(2,1,1))
            self.assertEqual((data['database_reads'],data['database_read_attempts'],data['read_transaction_rolled_back']),(1,1,True))
            raw=(case['opdir']/'current-input.json').read_bytes();private=json.loads(raw)
            self.assertEqual(lane['private_input_sha256'],hashlib.sha256(raw).hexdigest())
            self.assertEqual(len(private['db_projection']['rows']),2)
            retained={row['external_hotel_id']:row for row in private['db_projection']['rows']}
            self.assertIn('token=opaque',retained['9002']['hotel_url'])
            self.assertNotIn('token=opaque',json.dumps(data))
            before={p.name:p.read_bytes() for p in case['opdir'].iterdir()}
            with self.assertRaisesRegex(RuntimeError,'passive_oct4_child_exists_no_replay'):self.run_case(case,stage,ns)
            self.assertEqual(before,{p.name:p.read_bytes() for p in case['opdir'].iterdir()})

    def test_actual_overflow_preserves_full_5001_rows_and_nonzero_exit(self):
        with tempfile.TemporaryDirectory() as tmp:
            stage,t,case,ns=self.stage(tmp);t.write_db(case,[t.valid_row(i) for i in range(5001)])
            lane=self.run_case(case,stage,ns)
            self.assertFalse(lane['successful']);self.assertEqual(lane['summary']['state'],'held_overflow_no_replay')
            self.assertEqual(lane['summary']['rows_captured'],5001)
            self.assertEqual(len(json.loads((case['opdir']/'current-input.json').read_bytes())['db_projection']['rows']),5001)

    def test_actual_missing_table_records_known_attempt_without_fabricating_read(self):
        with tempfile.TemporaryDirectory() as tmp:
            stage,t,case,ns=self.stage(tmp);t.write_db(case,[],with_table=False)
            lane=self.run_case(case,stage,ns);data=lane['summary']
            self.assertFalse(lane['successful']);self.assertEqual(data['failure_stage'],'db_table_missing')
            self.assertEqual((data['database_reads'],data['database_read_attempts'],data['php_invocations']),(0,1,1))

    def test_unknown_php_outcome_preserves_null_counters_through_control_dispatch(self):
        with tempfile.TemporaryDirectory() as tmp:
            stage,t,case,ns=self.stage(tmp);t.write_db(case,[t.valid_row()])
            if t.PHP:
                case['config'].write_text('<?php exit(77);\n')
            else:
                (case['home']/'stub-bin/php').write_text('#!/bin/sh\nexit 77\n')
            lane=self.run_case(case,stage,ns);data=lane['summary']
            self.assertFalse(lane['successful']);self.assertEqual(data['failure_stage'],'db_subprocess_unclassified')
            for key in ('database_reads','database_read_attempts','read_transaction_rolled_back'):self.assertIsNone(data[key])
            result={};dispatch=dict(ns,result=result,mode=registration.PASSIVE_OCT4_MODE,stage=stage,before={'fixture':'unchanged'},fingerprints=lambda:{'fixture':'unchanged'},run_match_passive_oct4=lambda _:lane)
            exec(registration.REMOTE_PASSIVE_OCT4_DISPATCH.strip(),dispatch)
            self.assertEqual(result['status'],'terminal_nonzero_no_replay');self.assertIsNone(result['database_reads'])
            self.assertIsNone(result['database_read_attempts']);self.assertEqual(result['mapping_writes'],0)

    def test_actual_terminal_tampering_receipt_stdout_exit_and_authority_rejected(self):
        mutations=['receipt','stdout','exit','duplicate','authority','private']
        for mutation in mutations:
            with self.subTest(mutation=mutation),tempfile.TemporaryDirectory() as tmp:
                stage,t,case,ns=self.stage(tmp);t.write_db(case,[t.valid_row()]);real_run=subprocess.run
                def child(*args,**kwargs):
                    run=real_run(*args,**kwargs)
                    path=case['opdir']/'result.json';receipt_path=case['opdir']/'receipt.json'
                    if mutation=='receipt':
                        receipt=json.loads(receipt_path.read_bytes());receipt['source_sha']='f'*40;receipt_path.write_bytes(t.m.enc(receipt))
                    elif mutation=='stdout':run.stdout='{"state":"completed_read_only","rows_examined":2,"accepted":0,"written":0}'
                    elif mutation=='exit':run.returncode=2
                    elif mutation=='duplicate':path.write_bytes(path.read_bytes().replace(b'"accepted":0',b'"accepted":0,"accepted":0'))
                    elif mutation=='private':
                        with (case['opdir']/'current-input.json').open('ab') as stream:stream.write(b'\n')
                    else:
                        data=json.loads(path.read_bytes());data['raw_samo_evidence_verified']=True;path.write_bytes(t.m.enc(data))
                        receipt=json.loads(receipt_path.read_bytes());receipt['raw_samo_evidence_verified']=True;receipt['result_sha256']=hashlib.sha256(path.read_bytes()).hexdigest();receipt_path.write_bytes(t.m.enc(receipt))
                    return run
                with patch.object(subprocess,'run',side_effect=child):
                    with self.assertRaises(RuntimeError):self.run_case(case,stage,ns)

    def test_mandatory_paired_source_suite_uses_real_php_pdo_helper_in_ci(self):
        with tempfile.TemporaryDirectory() as tmp:
            stage,t,case,ns=self.stage(tmp)
            env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL','CI') if key in os.environ}
            if os.environ.get('CI')=='true' or os.environ.get('MATCH_REQUIRE_REAL_PHP')=='1':env['MATCH_REQUIRE_REAL_PHP']='1'
            run=subprocess.run(['python3',str(stage/registration.PASSIVE_OCT4_SOURCE_FILES[2])],cwd=stage,env=env,capture_output=True,text=True,timeout=90)
            self.assertEqual(run.returncode,0,run.stdout+run.stderr)
            if env.get('MATCH_REQUIRE_REAL_PHP')=='1':
                self.assertIn('database_dependency=real_php_pdo_sqlite',run.stdout)
                self.assertNotIn('skipped=',run.stderr)
            else:self.assertIn('database_dependency=',run.stdout)
            self.assertIn('Ran 43 tests',run.stderr)
            print('paired_passive_source=43tests; '+run.stdout.strip())


# Frozen paired source allows main CI to exercise the feature reader's actual CLI.
# All runtime bindings remain fixed; synthetic digest substitutions exist only here.
FROZEN_ALIAS3_FILES = [["scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py","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","f47fa828f1f6aa6393376fb800850ea405a4949d579acefa67ca026fab138002"],["scripts/diagnostics/fixtures/hotel_match_alias3_retained_fields_readonly_v1.json","eNqtVsFu4zYQvecrAp+jhKRIUeq16KHopWjRU1EQFDmytZBELUU5MRb59w5Fy1aS7SYB6oMNk4/DN4+cefx2c3u7m8wBer376XbX62AOme5aPeWZh6DbAWzWtNDZCX+ewuzhge7u4io3gtehdUNc2A4h04P1rgersx+GYYQVlBCZHc+B6oiOQd5ZkNC9s/AuVQ/auqE7pSWTm70BNegeplGbZXli77yiVCSUh68zTAGs8u5xQkyehvWjSgFw6BuOXAM2bbeJhUpMDwcXoDvnb1zfu4FntXfaIiXjjuBPmTZf59ZDRitJl9xIxUR2ZPQBjq2FwUA2+vaoAzwYNwR4CpnIkGVGSTbqPS68/zKh6ndnLgfNRBFp0LwpMJQxubSmKeva2FqKsqQV42VlJNRWs6bG8bLMedFAbhvWAONGkoZYukbs9VPbz72qTwGiDowgU8Fw8nlRpEOlkcdVjqOU6sVt2KrQtUfIyfWEBkQdIZsHxL7QAC+ElCuHGNLDNHdBXRO0nDQ1MCCMEy1sQyuuTZmXuSx0zpnQZcOhJA3jrNBSSFEQTLKoCKWUyOZVcAPtGFQ8JBgChhelEJwzytgZVu/Vyvotl7yi0uaFkXVRCEtrzuuSgzSEVAYs4zmjVcnyQmpNhYBcYHAjrGUNk4IKvXJJciA/dS4vVXeujjuIWhIjaa41nlvNNaVgS0M4sIbLgjaVsKTRVb27HMzo3Rcw8RBUqgMM83faBq/0/XIqf/lu3dr5dt8OursPeJ1/g1MM9E+686kA0tp0yDhqdNCd26vWRnq8opyfQ21rLKZzhhRMsEJUQlxh8eaq0WG7AB8hD7//8evPv/z5sA11Eb1zRndqoZ0icjzG/C1OGwNjLNyXDBnBDysYzb8Tu95fOoBJHWWYu+6CawcLI+DXEFQ4bjlE2IJ6vvuRPMvmORLO39WIyILjpXpXI8Y+ohHuWH1OIslkXn5Ioh2Ner4h/P+IxbmU7ENiFfx9sXL6IbFyVn5SLCxr/lmxtoQ/ItalEIP2ewgvnUsPp1iwKZlkXWb2PsaLtodspli8u8EFBUfdzegkdrc2iGgxXh1CGDG7rotIkuYOp6ld5IlzOgTox3CdtihFrSdY9vjO8KNvk1uk8V6PYzvs3w4na3k1POkGVHBpWA3uEWca3U2wzKbj0GiMm3y2gD12TCSOloIOHtP/D9xFRlX71u5BoR+32Clforq2b5fEz+6Wwqr4DoiGH2fo6r3QYb999WJYdF78GxUeU0s/O2nBsGmKYkXNddea1VpWjED3KstLT9/P2m8bOboE7rd5gCh8dqlw8ABrGUy7Cw2YwGP1rJ0+baJqaByyutrFxRHG+A9xi33g3cQvvNUxvYtfOTXMPepmrrcSb/yAUk6bSAi7VFF89iwnu5mcQHc4Fd8zFOlh+ZRJxCskbn55VSh4AjMvXGNx3Dzf/AvGyT4d","758f38da8a9d57f9e770a8b422f4115ea28650bc0fe97164e96c319c467cc36a"],["scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py","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","89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128"],["scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py","eNrNPWtz48aR3/UrYKQcAzZFkXqLd4xr7dXaW9mstnbl3Dk0CwWSoASLBBgA1ErZVX779WNmMDMYkNReXXJORUvMe3q6e3r6MfOHrw7WZXEwSbODJLv3Vo/VbZ4d7fm+/67If0+mlZdni0ev+ph7+TKtqmTmFUkVpxn8+OGnE2+eJotZ+R9elnurIr9PZ0nh5YX38gcvyari0VvlaVZ1obm9dLnKi8qb5osFNJvmWSmTZnGVVOky8eLSm1Uy9TYubxfpRH7+XuaZ/J2rqqu40gsVifxVPqoyZRWrRtfFAop3V3FRJnt7V++8oefDAPfjbFbky2QW7y/janq7P7k52V9nyQNWSmb7PMv9w97hab/XO96/7/t7P7y4/vFnrL+5rL/3l6uXl1huU8tFEs8Q0FD6xdvXry4/XEcffn6Btc76J/F5cn4+S46mk2n/9PQwOT/qX8ym+N0/ms7OTuB3P+73J+fTw3n/4qJ3fDGbxZP49Ox0djL19169vnzz8gO0FfhF/rF7m1fJ4pdi4Xc8Py/SmzSLF90qXxd/Th79cO/tVXT56tXlj9dcQy5qdFtVq2gaLxYlVlzdPpYpfHFyDHixXFWUA4sJfZdJhFMyUz4WaZVQ0jJerdLsRkuZ5PkdpqgeFlC9/oqn02QF8MLfWKlKMhjrqxdvPlxGr968+IkHW8bzJKpybjbK8o911TibJlFyHy/WsWjmZpFPYALrLP37OsmSstSyw733lz9evn53Hf358lduO18lRYxoS6PFpcQfJcANGi5vY/oCTEsIPEV6Dz+jNFutK8w9PDnl9P+30Ox432rghIwsh05XixiR4sP1i+tLhsM0X64WCVSmIUWItBHgdMSIjC1uLgEwkQWwcJUUS8TAaB6nC6ii9/rDCxjOz1cSeQWss3iZlKsYfk2KdHaTwAreJ0UKrdN8puuiANYDrdykJXCgaHqbTO9KaLiKYAnnebE0CzbxAIsaqJLP5+k0xfVB2olmKfGvGBoHgqLikicirIhui6SL04Q5BYU/6u1fxPvz8afT4ycscfnj+8tru1CV3yXZ598/Vp/jdXX7eRWX5ecymULL8E9ZQoefp7isyecyvcnial0kn+NVOor2x9/fAel2sLnXiLmvoncvrn+221cIXB7QNASnG8X7/3ix/zcYITT03UHw/SBBFAVy2RdYfBB+rxXqQqnfusiNkUquPlxfvsfVCb457R0dnn/T8b45PTzpnx72+0fwcXF4fAhJfWCGKjXseME3hz3476x/ftSjKlCg3zvu9eGjf3LRk3VUcl3nvN/vnck6h2dnPWwAOGLvtK7DyVqd0/7hhajTP+31j/Dj7OTk6FzVEclanbPz0yPVz2HvFOtcXJyca2Pj5DDc29ubJXPY8aYBok0SDvY8+A+Wbl1kXoDA6s7Wy1XJ2R0oWcLyRXE5TdPhq3hRQloJG0IEC1kOrwssk2awCtUQugOyRTSLMy4aet95/m8A/i50mM+SQPZfxvdJgDtix9OHgSmwRGKr7L6Df6lUSLnpnHJwS4TuumkZlY/LRZrdBSFu5HpekZT5AroIva+GegaWAyKw25mlRSCGQNCI0zLx3q8z3OoviyIvgKmuK2SQUBJEgrxAoueiH2HANTgp8WMK0wAkzsQU/YeJH6LIcAub9yKpO8rL7vR2mc9EuV5+2uuFKhfmyxW6xC4D6Ivms4CG8XfdTtugy1tcKart1+2KRueLdXkbhPpg5uUjzETmAzFmeRByifkM5glF1LQE+DqYeBW9f3n19s2v3mfvBthLVRVBXsK8r6KXr2F7ur56/ysQfU80Baxu0Ox1PhMdIY9dmCWmi7xMVAmJB8SzJ4+wkfA6A0w2r+FsXcSTBe9Qk3h6J9eQkV9IcV3eAwnC3dvkYZbeJGWlMBehIjrlRVvGD+lyvdwVgxX2AdrFE8DSdZVo+NtAXJWjo7s9zb8i7olJLmEBcOeNsJbfsnhbVu3t1aurN2+u/qtt0dJsnnOLcxQk1Mpoc8T07ofo9Yf3lz8FWL5bVhGgehJKGux5/+nJjDL9R+L951AC04XZzjlO45WG2Ex3MKgZTXQ+g8kUE5gE4c98JpmSgxJrWhbYjzgSiOEAF+sbE5QUiItkTEGbmirzJdOCQdyAiJEX1gwFqkK7O9CKYPV/X4M4UD2uklkQTyv4AKb+sAIulswEIsGMMF9kh15a0iwozSqqDYKAKeunZZqVJLqqGh0PpY9mvTKpVE/DIX2qTjwAPm4iQXPUo7sxbBahB2KRdwe/APBqHl1gcMtSsqq24SxAymoOB9dJGw5+bhlOx5vwMPAXDuMf6aoJWoO3cC62L/PF6hTQMmwpQuJUm25jfXA9tEmJcgRgRLo4ewzuuFDGO6pERX3klLETHHdiMHASn4Ho7ktMo6PqTNuaODEtyoDa1VqF3RRI7dOTStBHQ4VNWgEI3GEW1DMz3EOcrVcLOKXAseYuedTIR3QNIIDe721MgBxKYvgN6RzfXeRwmsE5AcucoJYhohmBfJ3fDelnhyceTXNanWq4iJeTWexFAy+IaGIRDh2g2q1uQQoP9JFmeQZETPtzGO6w2DstDA/U3N2oEYV0sHHCOQxOAWI34E3O3syYIbbueC3DlW3BkUbxejhpdOfrxYIEeVGCMrdsushg+ffmnV0eaiIubE5dw0sBgGWcpXPsgLbmgbHqTeDoeo6OdwrC+KmaOy9OF/ZPkLbg/LaM/VDItJxYH8bNdD6Xm2m4PVpJyB9goHhCzT+WzrZhk0hndtaKVWLQrzzuhgTMYCfdzkEfNs2rdx2PNEcAgauXlx3vpOON+udjZqQB62rCLWKzhHRUTmG0cl1gKkiA2jRxctD8WAGWtk9IpVGfIKpUQNRJEBRcA6g7XuQ3MHWcuUhU5+4qvU/MrCou4N+ouhcnYyMTURMO/swtmDUWSLNqAHx63HGqRQ4LVigcnONcRwXKjthu/lG2zN+Qzt8jHzAUZNN5UuCZtvTHYwMY0BCN5QKBgSmfIGkk54xU6o+NJqHCE9U4k1sEkqI8dGv0CCUN+FFbIDshLJwEXFdQ+iKtLJzltaL+wbv3r3+8/HCAqoXxd3T4F7WRwUakdgVwiTasCeyKXhrYFArhjAmutjoG4ARQqcmn3z/xPV6bkUQUjba4eP9cFdFwb4x7ui+P7v1z317kXSdQD84Yv9yypUxGQhfvlrSpWJqwmyRLinQarQAe6UNUrIF1r1BvVQn9UJEsmDhYnwrn+hJO+8QnjHL5KgbGE6GyFakiQj1LlVaP0HKez7WyoZI/SFyU41VCZMqHbjUPgGRPG/+/TM8oNi2ZsjPfQg0XwKqSYkSZLEhUQvEFgIzACYrQTdqthM1yDwoM66W7KSXHAbaJYsCgziN3S4Asalx/rNsVC1NYGFs3nTxMF+tZgvpWmV9KJvJsBJbqUJPVO+Tbkb9IAXugp473yYbNwLuA1WIVJ80WmREmn6EKNodhUirJI5B6fnx20j/G9ZXCjyzfSFR1+r3j85OzU035DYcsVFKqEoe9i7P+yeFTqwQlmJYUzTQxoikPsRChS0PbGKTWXKvAAzCQnFOc8UlRUNAJABsY9QfjbglyMDDaA19rjgoN6Z8urRiM0f8n7vhYTkvqYdI/NfFZiki8itB5gI2EY+vEJYBCxy0vQX7FFbDw+Atk3NaZbxV0hSAgDiosxQNOix6kSckj1ZEQqUSa2WagjVSWkKcvPCLC4gaqCZIv/kxqbtrRYINo7BrGiaTRgtx7uBFl+XLkYQfUg497WKNVs7g8H6p5A8Zgv/J7ZJQe1+2GzuGa42qbdUMka85dzTstr8QAfq5bEzobMfaANc596IUwtoJPX6m1FRdN5pZiuykqkRwykhKM/DREkjG2O13AHun9yBziVZwu4J/g8gHNUrBraifdKMKDXBQFwIrnSPDxTaLhcrmGuQVhty5GBep8qNWlNMBH+hc6f/Xi9Ztf3l9GH65f/CSsWgKSiu+us/geRkWKTRBXxAEIrWJWwQUckKDVaJmWS2kUnNz0zyPJBVtbMko5m1mdCwNiextQpEjK9WJLGVfzcm+TWKTYurMQjdZZxGb30Ll+TiKzHpVbTxYgRInhAkNJZ7FeRCk84PSa8Dp2UMVT44OhLa11QFBAKNwTQiBP4ZHN8yx8Y1zx5kW+9N7mmeRv9USYFAM5QxDS4AQOIiBmizH5vv8KCMCToPEEWyw7XnWbZMJ74jYBvlZWaTatoP59In0p+IhIEgH5SLAaGJa8JBUBdTTyOUXQeNbxJrCZpfD/AlGXM1EI1OQ/saTCxKmwTRpbJVoYZmo7kRfKFwoU0RRucbQ+O1MM620GpgZAA6h34GUjn9TqwC4ynXfAB8sOYzGIlq4d2F33akhKYhYgId0NoHkTaELT0PFMW79p4hcahifvM0hZLjF74PUc0jOnWgI0JzadFgaeMAlqNviBhzz6SQBiclMvxE7cZodVmNSrMNFXYWKvAtIIHOtvoanEsPCRac3QPOmmz8lNx2H2tKyeZQLyDO5V5TDwO/C/gR92bPvnd6b1s6HbmqBYkOUZHXVW8SNqG6UTxhj3Z21+uKXCZ75GyUfwJpm33RQoQSg7KfJFYhgWDOwDCADS1Mg1oJ7V59hENsrVvjHb8CcZmNQiUHMg96h+v0dOSL2Lw/PaOWWg+2fAKkomJ70ziuQ+TchvRh0T6EyI8vuhSCV0kKnqrGAdIZ9NB+3E9DwK0TnFtj1WobGoBWd3ArFGXVs34R1Ia5XWtIW/60UTp6aw7p9x0BzAli1+lxEU2ggKfQT45aBv6LVJ4BY+K3AxP4WZ7cRQDRaqsLI2gOTrbKY7p0UNTOw9hZvGxOASgyqeM6gWjy7F7h1eXQNrRUnrGiUP8ZL8gYBASEFkKJw50VA0D4QWeNOs69E1fKCUB1Q7ZXS8b7/Fw1UX5R3kuEHt6ocW6Wa+oQVj1qsBXgg6qGfxTZmhBo2xkdilpF6g3mDQK6uNcQ82s3WommT3yQKA6hqFk79rQ2KWxmY91Y4coIRos3fiI1BRgr+uK0pIrmQb8nUDYo2yI2ZY0gSImy//DF2GR0V+7dW272BI6NPbdDHTDI/byH1k64XGox5rnXD7G1hHGyFHIvK60aKJB09fNIa+MQZTBFbCojGOzDmOzB6Hxolde4hilUKXSSQKZCG6Fjr9+B7RQ7jWQbVPT6FpHoCTTMnpiguLOSDPsOQsS9HDbTQ0Pa0eTHqXoWEuFupWdfYgfmVJQlQPzS9sfCGzAjdJM2roZcgEhaMWFgpNK0/aENNkgMoLVVCZnDaoPsa2URvNODRGkgb7bbbtVpgYulbTj4WaBWwXBrb4o7ZiLShL+jHWyGpcmjmyP+ejKPH/sdUO6p/JkBi49NhKv6OpeQnQNOlNFV26c2VvELY7NVJlcsABG72PfKOBZ0EZjVC+QVy7aCM0cjBOItvpY3KjWURDqUUVFr/JjURzxpfz7QSkvJZtTNmVhGQDZMSswVoPBSfhMMo69Z5OApA9PIcG1LTQrdlJBCZqy/KC/etm4IFh99Nz6JgTL3ONhAdtpI26yPtmQd2yPBaHkhoZdeFLGLwdCG8T36ZzxpMbVVt1YgInheYkBhGh9sshawt89oQmFf3yQeKt6iS244/Ge7siVD2vup6qu8ESbnF19EwFzkzeGrZe18YwVVhokGmSTSQTuEi5ofenoXfWLLPJJEEWJgKP5bIn/yOV25C1d3aeWN62bEObqP8nvU3NE5ScsbOKdGkciuOwt8/r7Cw82er+0xgq4cx37Ec3cZfRMAkK9p1lmgqaSUN5Yujv3QB6nr9Qy5IJ/6GW2TgcoKCaU8DZBY9gPg1etllh7EakGimts7jwLfUb9Qn1RxJ50ENOzEQ0ao7JyEIR0qxt0SvyYNPhrxWvAZ4Ed9jMkRieR4XA2UrzZGAKRdJuy6N3Wnxa1nezKXHX8Sk3iuYRpgksMwZuQIfXpEQtORwaVLqQcWBTIMsnbjyGJRLLhE+uCDqrRZmshCZlCx2bjes5wrlKxuQ9PT2Ht23H6bqy/AUbhWkCLmqgOlG73ka68WqVZLPg07ffQqLYjDVBV3YhMK/edus8TGN3uoFYKP0AiLYR2Ycu9gw8x0HDN5we6g/T6eCT1AINWqIyBe/fp6Mm+e0Zalt04lPqVuHNJ600A2HAYY2m5S44MLz8DCWr8MZRUgvvmS49bM3pXfpY2iuEGophVD7t7e39wXuHq8D2pnpgHgh5sE7eOhPLzNYw1gJjiO+rc3mg/fDzC9gTvPPkeD6fnkxOk/nh0fT4KJn0Dntw+j05To77k/7J6cX0bDqbHk3is3h2fHR8ejyPj47j2cXh+fl57yzuioAPGIdmJwyQv3U4ueGpLT0kKHckiGbcHil0B2wAmXUGYqKPrIYbEBjIzhMBxkgukjjzlaM+t4DFMEsWy9bLCfCwRin0X8AVmC/yWHpkgGxYFciA3L4b5K0iCsZFET/6W308fOmMIR2ZhY83EwzpAVFjQcDT2I6AlExQrIY0R5CPEFJpSh2yxW1YVZCKEhmMQWQn+pIkIn3dgJNkgP9rjjeGfyIiPRyEYAfCZ04pgin6Er4mAIZbNh2jmj4vq7oO7ekkhqO1OS5g/sC2lCBvZNOYaxnfSK7nbmZSLCZwwzKlgEmZL+ZkFSNSdPdNBVQWt3evNTCNYcPC8HdVBs4uU5i/IICITjpo5orySZkU96aGd5sW+GmzX5DuPaXCG3BRBY7/yeufHp0fk4dm0i2TuCDPqtFvD73e/m8Ph73fHs7mY9+MNnS4/osBsGcyDKGoSgwqCmAvrapVOTg4wEXG3+Kn+It/vjedClXDnvcH74qW4wAoFN0zPbFfksIiAVB4PGLkaThJUapcT5hIy64kqJGNviimEa7mqDMWvp2Iwk03hBUU1S8V6MIH+4dpDEn1o+E/9rHq8kd3kX9ESwtC2tjPMSivu4aFp7MW+qJ1MS74Y17M+GtexDdLEYIJeXjFgfTpYVI573W84+MjS6KiwRiER1OWRx7c/jPy0BD2QXvzN5aXz0xpUdqg4AiOv5eLYNWFhSoeO95dkqyiySLO7gRdimBXOP5EsEJinxweyohNIczUARmDvf/9HJroiZovjsqWaH6nYnki9v2D+dH6mMXMpc/+vgbhMMBFqm7DcOex1hwM0G0WI+X7W4hJos7WPmzf5HV2l+UfBTSSBWGYhYUSg4gkJWmWvuIVqy5yY4mSmBLUSaqVIV2NIXW73WLtM5LaBbsg2TE/8Ltm+R0gSDIbs8otq4zbqdFcd71CzhvojQ59GT0aiT2nZtHCXAxpw+YkGm7Mo+BO5jUCw5hYMPpKAYvAjXwewT0/9sNxLfmQVueeY55EC7KjsVAp4S7T8dR+1fF4pwIWjyqtuhVSxcv/K/0SRpo/dKSUlIE4hbwSYcM1dTfXNGE1k2Dm7Anb0eOg0xmcHJQ2SznRirF1eLRmN9xq2IhOs1x6R9/tj78nf95P/c5h78kXjTmOiwwSeW7gUo1CCl6y3EhAQmZYB1cThawJG0oXBr9slktoTon1osgiJJiieoYbq/17heZRSA60iBUBlEiUp+kCVR+gxKDqdfoXBCqBUxrqt4hRw9GdwfwUtnU8h2Q11KbT8Zzy1XBkiZb3Lc472Ou9cqUvx1p7lkg2bCC7WywbSkpwyGVDk3DcgtlQwR7PJVuEs6HMIzKXFY2zJ/Ij4ejIpx/pOBmIf5WHYUepIAFqtSO8KGZGx1HozU5HWWTCRhNaLB22cvWuUUI4bGAuX3pkF3DExdGVCtpZt1FHHJepYMPTEUuPXOYPZSkx2qoD3SgUZlSMelq8BYeZjTc7wDeCFgxVUlMnn3Y8VleVsOChyc4wclm0I5X1HVt7b0q1FM3WatHTxcGR06QHDYiZO3JxhI4wuN2921Q1h4KNb/Aw7Q63OevaaP3rS3xMK9nvHWmbqAFnxaYZirtRi8qA1I0C68YjUpN2xJQNMxEDhIQW6JOxkq1sMnDOUFyRHoBsnsSLRw0LCIqMc+2IjeXrOLI5G2xwVI7diQAkWb9fEytTT4u+VR+qVKFta5uBIwqPGyHbsHK1ak239ww8l2t/7ThhOfmb6t6BSwXcVAxSISNtbCgHzbE3dYSNa7bq/j/VI9VYaHOcc/8AqfHgU/p0UNPGwaffn/yn3VSQjGiWDrIRGTmwfBz0MrVpk9tyG0GbZlRZvsXCKpLTmTkMPCPfp2VOkaEo7eCk0D8G0JnCEhc5+jdR+0LX4gjdHHh9dGDT6WvgojlbH8sohweKXNOGmk5nlBWGz/J1291xTV71ASsqdmERiCD9lJQRY5qkq2rIMFA7Pg+JUgXpCbbuDc3wd3VNTcTmoiisL6Hp4mGHpgWEzNVxHzwQ5gd2KqMb02zOwEpeulDtvi/v4CKNJ8ya1IJf4t1IqxSXXFIQXESxEXRNGm9ksyiuonU13XS7ndi9nZrvphNkw1XSdomUSuwWNXlTNd5Uhzd9A/Sr4prSnhoZYqFU7NV3BbIT1zQH9vFQ0cV2/i54ajhjArbqfpd6qHGbuk4zfVJ0WcJYSqITrTwkYsJILv+4XRy07iTYZ6wXciE3onmk1wIhZzEejQ1JUPQszuRCd8AXBm4RuYyAIN3dRIDAPtPIW/SO6egn+tUc5MmMxWqKwCJZ6cVE5yz0MHPU/mpoE3rbTRtU10kF+hhsf02uJkXcTlPq3VCrSVD2jRH1Ulh0Nq7vekD7hbuMHVnuHoVBnmO+uCJ8ziKb8qOQouphNSPcRbIV4F4TkPJbkwV3i+jfSrI6PGvaHRvOcKpAO3cwKjwHUHaA/Kzqyltyu2VVrPCHWBSbQeOu+/Wv+18v97+eXX/98+Drvwy+/vC3jZT12+zT8dM+/D0Uf6/p70D7+zdFcY3+dsMATAEmsZRePBz1B5uWyT9Qa9h+J6i6IEbUrinZ3LcUHzJDPZk1YNbW+hq/2G1+ZgPGsVGQHBFaC4vHTM0tUadUY490E4hdSN1nSDet4Jy1+VKqzi1o6+ctA4UOLY7TEaTpBGHQvFfGalwH527wFJEXe9o9VtLLCgk6aJEJzvAGz6ZgUCcb0gF7a6k8t5xwQfntwsJJaPqebuJn4h67oWJsm++xc0JGiCWKoAWozSmPvT81Mmo4jXfkRdLRoO5MxySd79o3kHyB2BWGG+jTOQu6hseNkUELVIC/9PjOprqAhhKiwLaRuGaGEO85R2x0je02Wd6WW5V5Ti3tyruINkzIzHaPHi9B2pE2JYtgz8yGqixSxxHtYOw6F7vOwe7TbsuJdtsh1n18tY6u5ilVnU53k+yb8rtQwYnzmHn3U31Wsm9pMhQhmh6k1nu06jue9pSirO5Y8w/RPENMPxDbA8Ry8Gi6dZgeHLbvhuaoscFFo8U9o9U1Y5NbxjaXjBZ3jG2uGDu5YWz0v+AlkZ/KGGYyPJVc34gOSfUFoELNLLy/NS2peX8Q6l302wJYzhil4w3xEFDGOlCi1yW5/0ryVXfDoZedGULZQtVbKdi6EE7dAAdjVUJPa9SAvZN+8R1kBgPh8rVWbMcLyxpDNco5h7qRTxkCUuvsGmhmHXdkCA8vmPskpLGrcGeJA5HCPpHoin4aLXPNTVhHJazgG1b3SYEVMVEoAKW0hEmaDaGblujBo8rpl/DpXW28Gwqvs8e77NGO3O+RNuGW7QO3CCNu+VnQ4cmHDZNOboVJbaJJqKIB519q6JHynrTz2LE/inEZDOqZphznlG02lMxlFELNhojYmtYCRWbkrsmxLqOWPZKAppkFNqlwnmcnUB0rU4XlgbU1ZqCz3feaxUYHDEiStboXfq41CZkQr61ljcubn4Ujbmd7pWBoDNURlKDve6ghcYUJGCNuBzYR4O5hA43ZYH1H3ABqc9LMGpna1xuBNmiglSa6WgALa5OgMZuxbSR/1krYKkFnwJ6748He9ngbmzLnwlKvpExJmboRtEGS85EucNakwb7Z/FINeWGjOMWe1mSXIC9qfKmG3KQ7tR+0WyOrunGoYeUgddG27Vzu2eXwfC5Cung2DU9RNaNWh2fbiTQUTRnOoBbPcHjezRv+bzUwmy7T7W6Qnc1+h512l8GOy98OF2mTz1xDQlc9yVk9R76Ri7ldPjdx8dmcQZBUS9wYzhJl7pE68YxdJOX2D+A7BFut/gZHc9HQVyq0Qc+UCCskKQyJqHeBJn18NbTd/esauktW+GzIiXmIWbXwVXR1QxjaZpmGHJfbl55bolzw/UBIc/QuUfht7UL6G/qQdqifL5iGHL4zjlF6u9kOZHRrvSApxg/XWRdtPyPnyZRzNh5OHZW10/W43clR3kevHOZ4gK2Ha+7IfSjnvI1n7/EOe4wEoxJ6rXwJ3rb8euLWqaLOUEcL4coqzwurmnvqbrfsq8pOunUTolUeDsZF4NXUsqX7BhLbsxQIbDk3th1dNuO7ywc2tB1C+eUbgUV0XPhyn1HBPnZguCSLOrxAdF9I5yrWGGouokxXa6jNzvk8gt2CSG3DAbuNja8rMJDJsVM12xyH9DY1hsGJbaOgLW3aaiiY4h+MwzmhAUzprllu8At5muA1TqbsAMvKXhaayKEx/KwxfJCl2B5Art4rxkh0y/yTCYSa3urV1tzFS+0yE7ME3dXPGqqVq5knBe+63rMBVrNYxRYdscVI1+lu/vPmPQMFX6OjRkhXGLGzepvTPPqWagQjKQLDOGQjPdL536fjtsh6FQ4puVZ7QD2UZmQbQXts5Gm+L8KxAK3XMQpyoRbEddqlOvlYsNIiCsINcf67LpsQjVrDwlUMjDZJaS+hXULAVGItjR3f39PKmwslooWw2POuKbCHzmIynu7ah4/GRubmctsJguYxAe1LG0R0qusUxuwziismSJiXnZ22nSOMHtt7Kxvm4mexOXE+CVsE82bI39AVtMpSaQM8uJky5B1nObfvwI6DF0OQKiCXTkJTxtW/d96mm0o9tkZ8h6+uVYFKVSZfpe74arjViEhe/kazqlKb2Xfc7m+ke+GNWR8RaO9Hd3/ErKQIbs3HFWpNbqHU0i0m3WebYvXna8VLdfJ7N9tpfHNTJDe1bwNFggjaj+dJYMfStkeSK8+LOly3d9iI1iU6/X5wcPBZxu3250bcrng3dnd9t9QeIOj8cPNAHfe1mGJwl7fIwEEoBlTqfohftwTNb+pppw70JPKx3FNqSHII3npgFeUaam1KJYT59K3+tjSpW/RbKZ8MwUpU1D1JTPcSWm1ZRhGbbq/TezPKWx236QQIDG06gY26VerGdg9gB2xsVD6l+JBM12TWxNvV89UsLTrKmTrSXu3QCtiPgWJW2DHTqGDY5qRtdiBijSXXVMX/pa7cNAi8SZIfAU2y+7TIM9Ym/wW9baMPV7+8//ESX0zjByEMlz4AgXp1l02IefM5X0qkIBjUIpEdMl+r4FzKNR5LpcuvBH8mgBpdqBSjD041muFFU3lyAOxazGni3WA1tvq5ar9RyC7blRPhN619+byrWjpztQ1PX7nYLcdt0+cYV2fbSyr8Zb3Xg5svzcZ8AVG7BoxBdEDsQBZmvLBvidbdZvXCiCh3ockn7gXF26UQBPdGUPKn7TfcGJeTIyg23tptvQ0l7i+Wt3zwkPASvQRGkagH1Nlr6an1zUz7xjE1MQPgeqhX4DOHgTL7dPlEMpNwlW/Ac3CkSmW+qH0SIxPxFsbOrFYNOwu7yQNsQiVTgZXlfmh460WMteuriDDHJ74VqrRO7AsXs+UydRWYagRTCRcWNljwXxElR4uMYgU6f6rrzCL5DMsG/97ODi6onfqKDOMmDlGJhGLrAQ8H86jf8bCfIMY9T7QQolClHGvk+1qjlheunhFRqRzojEsNNfDiazXGUjsxVQ7TiFSjqwufE1m857THkieJw+DcuA5209OL+oWbtcNSxWeXZ0jaJFzWo+Orkbb4TGq+GIiH+lVp9hVp+Ha1wNJ6DW20Fd/iNSFTBlUXmZkvzMhLnHZ6Ise49EXDBOeVfc9Glf+TK8/qJyXExOrXfoTn90BxAcvDfmDC90ndbrUSF1xtGW5rHNNO43bwPjkXwbyeOwFHpNzAiNrI8o8BfOPvf8BydqFEiKEccwrlcEZstIXa2VGt6ua5RkTRjpfQ2a8TnDieMVAu/q0vF2g3zrXebGeG7Ts9+HuhdssBE1DPdQ1eS1PCs39DK8Z1eY1WjPiADa249B+APetlG9d8Psfc4CvPPYkY9F1sM5s6avESaby/GVqBkYOtGhlrpreqe6WV2Rhcqa5J/7c/e9EUN+y4YWZd294hE0H34v4VcefQJnmIuc9wF7HI4EfDre+tsQQ3xLuADFof9viyFAeCY1YLRmKWhhtD/VL8TaBinUJjDzNFcL2CVMQM6XkXzmnVd/C7LfZ7INzn01772FQAuDjyOWRv41SgygstUoGaVO3tq5ahBloEth0KHU8Refg3HqGqJKMbZ80Hs0JDq3JI9hruZ8eIOsHUhC4Gn0uMKlLyMM7+O4PaV/LKT/3SD/P63o5x2a5gFvJSXXmjoHGp2AGszRSEuNtqufgemeSw3zs8PTw57h9fXJz98dXxcF9P8PGOXyOBxxaXwFkrb9XixoFAH5n1oB2z5THpD1db/Z9qf1vRaRMmjUuIN8NFmBi2zHO3fWW3AT5j0TYvkHOczzA9aeQpdGo//HQS/fL28r/fXb2/vnwZsVQUvb988fLq7Ztfo7/2ow+Xb15dX364jq7+TI9UpvgqKS5aFFFfESA3dBH56oomf38fKWkfKYlufi4fy25c3NwPjIdJBaXVN/FBPaENbanFZ8oPjyCsLS8f0iqQytNaXzjyX7z99frql/fR+6uraxQB9Tye89W7y/cvrl9fvY1evn7vLvKXF29fv8JJ46v26mkw8wa0xmj8WVqidzN6fP0P52449w==","c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196"]]

class Alias3RetainedFieldsRegistrationTest(unittest.TestCase):
    def setUp(self):
        self.core=fresh_core();registration.register_parser(self.core)

    def body(self):
        return self.core.PREFIX+SOURCE+' '+registration.ALIAS3_MODE+' '+registration.ALIAS3_OPERATION+' '+registration.ALIAS3_BATCH

    def stage(self,tmp,raw_change=None):
        stage=Path(tmp)/'stage'
        for relative,encoded,digest in FROZEN_ALIAS3_FILES:
            raw=zlib.decompress(base64.b64decode(encoded))
            self.assertEqual(hashlib.sha256(raw).hexdigest(),digest)
            path=stage/relative;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(raw)
            self.assertIn(digest,registration.REMOTE_ALIAS3_HANDLER)
        runner=stage/registration.ALIAS3_SOURCE_FILES[0]
        spec=importlib.util.spec_from_file_location('paired_alias3_original',runner)
        module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
        fixture=module.manifest()
        prices=[{'unselected':True} for _ in range(45)]
        for row in fixture['rows']:
            prices[int(row['json_pointer'].split('/')[-1])]={
                'hotelKey':int(row['catalog_id']),'operatorKey':115,
                'hotelUrl':'https://bgoperator.ru/hotel?code='+(row['retained_bgoperator_code'] or '123'),
                'original':{'hotelKey':int(row['source_native_id']),'operatorKey':115,
                            'tourKey':'opaque-value-kept-private'}}
        if raw_change:raw_change(prices)
        raw=module.n.enc({'PRICES':prices})
        fixture['raw_source']['sha256']=hashlib.sha256(raw).hexdigest()
        fixture_bytes=module.n.enc(fixture)
        fixture_path=stage/registration.ALIAS3_SOURCE_FILES[1];fixture_path.write_bytes(fixture_bytes)
        fixture_digest=hashlib.sha256(fixture_bytes).hexdigest()
        runner.write_text(runner.read_text().replace(module.MANIFEST_SHA,fixture_digest))
        # Same-length test-only digest replacement can retain timestamp .pyc.
        shutil.rmtree(runner.parent/'__pycache__',ignore_errors=True)
        runner_digest=hashlib.sha256(runner.read_bytes()).hexdigest()
        handler=registration.REMOTE_ALIAS3_HANDLER.replace(module.MANIFEST_SHA,fixture_digest)
        handler=handler.replace(FROZEN_ALIAS3_FILES[0][2],runner_digest)
        home=Path(tmp)/'home';project=home/'anytoour.ru';project.mkdir(parents=True)
        root=home/'.anytoour-match';operations=root/'operations';operations.mkdir(parents=True)
        raw_path=root/fixture['raw_source']['source_file'];raw_path.parent.mkdir(parents=True);raw_path.write_bytes(raw)
        opdir=operations/registration.ALIAS3_OPERATION
        marker=root/'alias3-retained-fields-batch-20261007.json'
        ns=dict(home=home,project=project,operation=registration.ALIAS3_OPERATION,source=SOURCE,
                payload=dict(batch=registration.ALIAS3_BATCH,maximum_writes=0,provider_http_calls=0),
                os=os,json=json,hashlib=hashlib,subprocess=subprocess,
                safe_file=lambda p,limit:p.is_file() and not p.is_symlink() and 0<p.stat().st_size<=limit,
                safe_json=lambda p,limit:json.loads(p.read_bytes()),
                fail=lambda reason:(_ for _ in ()).throw(RuntimeError(reason)))
        exec(handler,ns)
        return dict(stage=stage,home=home,project=project,root=root,opdir=opdir,marker=marker,
                    raw=raw,raw_path=raw_path,ns=ns,handler=handler)

    def run_case(self,c):
        return c['ns']['run_match_alias3_fields'](c['stage'])

    def test_exact_scope_types_collector_bypass_and_staged_source_pins(self):
        command=self.core.parse_command(self.body())
        self.assertEqual((command['maximum_writes'],command['provider_http_calls']),(0,0))
        for body in (self.body()+' 1',self.body().replace(registration.ALIAS3_BATCH,registration.BF8_BATCH),
                     self.body().replace('20261007-v1','20261007-v2'),self.body().replace(SOURCE,'bad')):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        for key,value in (('maximum_writes',True),('maximum_writes',1),('provider_http_calls',False),('provider_http_calls',1)):
            altered=command.copy();altered[key]=value
            with self.assertRaises(ValueError):registration.activate(self.core,altered)
        fixed=list(self.core.FIXED)
        with patch.object(self.core,'ensure_supplier_slot') as slot:
            registration.activate(self.core,command);slot.assert_not_called()
        self.assertEqual(self.core.FIXED,fixed+list(registration.ALIAS3_SOURCE_FILES))
        self.assertIn('def run_match_alias3_fields(stage):',self.core.REMOTE)
        self.assertNotIn('def run_match_bg8_fields(stage):',self.core.REMOTE)
        guards=[node.test for node in ast.walk(ast.parse(self.core.REMOTE))
                if isinstance(node,ast.If) and isinstance(node.test,ast.Compare)
                and isinstance(node.test.left,ast.Name) and node.test.left.id=='mode'
                and isinstance(node.test.ops[0],ast.NotIn)]
        self.assertEqual(len(guards),2)
        for guard in guards:self.assertFalse(eval(compile(ast.Expression(guard),'<collector>','eval'),{},dict(mode=registration.ALIAS3_MODE)))
        self.assertLess(len(base64.b64encode(zlib.compress(self.core.REMOTE.encode(),9)))+100,65536)

    def test_actual_cli_keeps_full_private_bytes_and_never_accepts_aliases(self):
        with tempfile.TemporaryDirectory() as tmp:
            c=self.stage(tmp);lane=self.run_case(c);data=lane['summary']
            self.assertTrue(lane['successful'])
            self.assertEqual((data['rows_examined'],data['raw_references_verified'],data['raw_files_read']),(3,3,1))
            self.assertEqual((c['opdir']/'retained-original.json').read_bytes(),c['raw'])
            self.assertIn('opaque-value-kept-private',(c['opdir']/'current-input.json').read_text())
            self.assertNotIn('opaque-value-kept-private',json.dumps(lane))
            self.assertTrue(all(r['target_namespace']=='anytour_local' and r['independent_tv_hotel_id'] is None for r in data['rows']))
            self.assertEqual((data['provider_http_calls'],data['database_reads'],data['mapping_writes'],data['accepted'],data['written']),(0,0,0,0,0))
            self.assertIs(data['acceptance_evaluated'],False)
            with self.assertRaisesRegex(RuntimeError,'alias3_fields_child_exists_no_replay'):self.run_case(c)

    def test_changed_source_fixture_or_helpers_fail_before_reservation(self):
        for relative in registration.ALIAS3_SOURCE_FILES:
            for kind in ('bytes','symlink'):
                with tempfile.TemporaryDirectory() as tmp:
                    c=self.stage(tmp);p=c['stage']/relative
                    if kind=='bytes':p.write_bytes(p.read_bytes()+b'\n')
                    else:
                        original=p.with_suffix('.original');p.rename(original);p.symlink_to(original)
                    with patch.object(subprocess,'run') as child:
                        with self.assertRaisesRegex(RuntimeError,'alias3_fields_source_binding'):self.run_case(c)
                        child.assert_not_called()
                    self.assertFalse(c['marker'].exists());self.assertFalse(c['opdir'].exists())

    def test_timeout_reserves_before_child_strips_credentials_and_keeps_no_replay(self):
        with tempfile.TemporaryDirectory() as tmp:
            c=self.stage(tmp);synced=[];real_fsync=os.fsync
            def fsync(fd):
                synced.append(Path(os.readlink('/proc/self/fd/'+str(fd))));real_fsync(fd)
            def child(*args,**kwargs):
                self.assertTrue(c['marker'].exists());self.assertTrue((c['opdir']/'reservation.json').exists())
                self.assertIn(c['root'],synced);self.assertIn(c['opdir'].parent,synced);self.assertIn(c['opdir'],synced)
                self.assertEqual(set(kwargs['env'])-{'PATH','HOME','LANG','LC_ALL'},
                                 {'ANYTOUR_ROOT','MATCH_SOURCE_ROOT','MATCH_OPERATION_DIR','MATCH_MANIFEST_PATH','MATCH_SOURCE_SHA'})
                self.assertNotIn('GH_TOKEN',kwargs['env']);self.assertNotIn('PYTHONPATH',kwargs['env'])
                raise subprocess.TimeoutExpired('python3',300)
            with patch.dict(os.environ,{'GH_TOKEN':'fixture-secret','PYTHONPATH':'wrong'}),patch.object(os,'fsync',side_effect=fsync),patch.object(subprocess,'run',side_effect=child) as call:
                with self.assertRaises(subprocess.TimeoutExpired):self.run_case(c)
                with self.assertRaisesRegex(RuntimeError,'alias3_fields_child_exists_no_replay'):self.run_case(c)
                self.assertEqual(call.call_count,1)
            shutil.rmtree(c['opdir'])
            with patch.object(subprocess,'run') as call:
                with self.assertRaises(FileExistsError):self.run_case(c)
                call.assert_not_called()

    def test_widened_payload_fails_before_any_child_or_reservation(self):
        for key,value in (('maximum_writes',True),('maximum_writes',1),('provider_http_calls',False),('provider_http_calls',1),('batch',registration.BF8_BATCH)):
            with tempfile.TemporaryDirectory() as tmp:
                c=self.stage(tmp);c['ns']['payload'][key]=value
                with patch.object(subprocess,'run') as call:
                    with self.assertRaisesRegex(RuntimeError,'alias3_fields_scope'):self.run_case(c)
                    call.assert_not_called()
                self.assertFalse(c['marker'].exists())

    def test_one_optional_hold_keeps_other_raw_proofs_and_all_original_values(self):
        def change(rows):rows[44]['hotelUrl']={'private':'keep-exactly'}
        with tempfile.TemporaryDirectory() as tmp:
            c=self.stage(tmp,change);lane=self.run_case(c);data=lane['summary']
            self.assertTrue(lane['successful']);self.assertEqual(data['state'],'completed_read_only_alias3_fields_incomplete')
            self.assertEqual(data['raw_references_verified'],3)
            self.assertEqual(data['rows'][0]['fields'][0]['hold'],'optional_field_not_string')
            self.assertTrue(all(f['hold'] is None for r in data['rows'][1:] for f in r['fields']))
            self.assertIn('keep-exactly',(c['opdir']/'current-input.json').read_text())
            self.assertNotIn('keep-exactly',json.dumps(lane))

    def test_result_cannot_forge_private_projection_even_with_rehashed_receipt(self):
        with tempfile.TemporaryDirectory() as tmp:
            c=self.stage(tmp);real_run=subprocess.run
            def child(*args,**kwargs):
                out=real_run(*args,**kwargs)
                p=c['opdir']/'result.json';data=json.loads(p.read_bytes())
                data['rows'][1]['fields'][0]['projection']['raw_selector_values']=['999']
                # Matching serialization/digests cannot make false original fields true.
                raw=(json.dumps(data,ensure_ascii=False,sort_keys=True,indent=2,allow_nan=False)+'\n').encode();p.write_bytes(raw)
                receipt_path=c['opdir']/'receipt.json';receipt=json.loads(receipt_path.read_bytes());receipt['result_sha256']=hashlib.sha256(raw).hexdigest()
                receipt_path.write_text(json.dumps(receipt))
                return out
            with patch.object(subprocess,'run',side_effect=child):
                with self.assertRaisesRegex(RuntimeError,'alias3_fields_source_validation'):self.run_case(c)

    def test_private_and_public_forgery_rehashed_together_cannot_replace_original_rows(self):
        with tempfile.TemporaryDirectory() as tmp:
            c=self.stage(tmp);real_run=subprocess.run
            def child(*args,**kwargs):
                out=real_run(*args,**kwargs)
                runner=c['stage']/registration.ALIAS3_SOURCE_FILES[0]
                spec=importlib.util.spec_from_file_location('forged_alias3_test_source',runner)
                module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
                private_path=c['opdir']/'current-input.json';private=json.loads(private_path.read_bytes())
                private['rows'][1]['original_row']['hotelUrl']='https://bgoperator.ru/hotel?code=999'
                private_raw=module.n.enc(private);private_path.write_bytes(private_raw)
                private_sha=hashlib.sha256(private_raw).hexdigest()
                result_path=c['opdir']/'result.json';data=json.loads(result_path.read_bytes())
                data['private_input_sha256']=private_sha
                data['rows']=module.project(private,module.manifest(),private_sha)
                result_raw=module.n.enc(data);result_path.write_bytes(result_raw)
                receipt_path=c['opdir']/'receipt.json';receipt=json.loads(receipt_path.read_bytes())
                receipt.update(private_input_sha256=private_sha,result_sha256=hashlib.sha256(result_raw).hexdigest())
                receipt_path.write_bytes(module.n.enc(receipt))
                return out
            with patch.object(subprocess,'run',side_effect=child):
                with self.assertRaisesRegex(RuntimeError,'alias3_fields_original_row_binding'):self.run_case(c)

    def test_original_bytes_and_stdout_boolean_counters_are_bound(self):
        for kind in ('original','stdout_bool'):
            with tempfile.TemporaryDirectory() as tmp:
                c=self.stage(tmp);real_run=subprocess.run
                def child(*args,**kwargs):
                    out=real_run(*args,**kwargs)
                    if kind=='original':
                        p=c['opdir']/'retained-original.json';p.write_bytes(p.read_bytes()+b' ')
                    else:
                        stdout=json.loads(out.stdout);stdout['accepted']=False;out.stdout=json.dumps(stdout)
                    return out
                with patch.object(subprocess,'run',side_effect=child):
                    with self.assertRaisesRegex(RuntimeError,'alias3_fields_original_binding' if kind=='original' else 'alias3_fields_stdout_binding'):self.run_case(c)


if __name__=='__main__':unittest.main()
