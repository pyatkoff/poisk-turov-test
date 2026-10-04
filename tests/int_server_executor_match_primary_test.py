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
FROZEN_OBSERVED_PAGE1_FILES = [('scripts/diagnostics/hotel_match_observed_page1_identity_readonly_v1.py', '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', '447a5362ef6746496890c3f156cc3756ba6d4c0847f981a1571a2cf25e67873c'), ('scripts/diagnostics/fixtures/hotel_match_observed_page1_identity_readonly_v1.json', 'eNp9U01z2jAQvedXaHTGqSTrw+aWEJcwpYSh5JCTR5bXQS2WGNmENpn898pAk7SH3lb79j097a5eLhDCndlAq/EY4Vb3ZpP4qoPwBHWy049AE1uD623/K2nsz34f4BPFo4HmdxB0b70bmNb1iXZ18C3UOvm/DiNMUkJE8nRWqobyQeUfxrmQJ1SJnItT8cG62h/KrtehjxyqckpTqTL2EQVXv2MZ4UfMBNA91KX+iydOjznfXDofWr21zzH0TQOhi7WCnPm2h2CHTr3gyW0x+TJblNfFFI/xySnlePQGFIubN4CRCCxm09v1t/Lz6u4rHqu383o2nx/PVzf38zUes0FiNo9kEqP71apYTB5miwkeS56O8PJq8qVYrx+WxbFgeTWNAR3h6eruflleP+Bxyl6PdncBzD50Phz9dn4fDJTdJtrHKRG8TnlViUwZLTNeCzCVEU1qUiIzQWNbasmG5xjtvLNGb0vj2zZOsLSxtSLP0jTlKo++w94dc6liTLKc5EPyu6+OSUqpIFJQSmQ0H0dmG236M5RSRjhTmfyAPNvdYJIJGX020RbkoAzhWhjBZFNlXBrgTAtOFCUKgOcZFQ1VWjKSS1pBXlNGleQ6NdF/gG6/7d8lY22e0kxHJQpKZzxLBavqrK6E1rwBUNAQHhvAjYjCGQMAyhpaM2FUrjN8am4L2ln3OCztAg7ofWvQMBGKNr6HLTqtfGPjGo2QgycIyAf7aJ3eoqAPaLmaTYqYQtoY2MXdRH8+CdoF75tLdLc9KTIUfxeyfYfiEteVNj9i0GrrkPGu28dfd4kvXi9+AxPbHcc=', '771fba36e04ad0c051158ac0c08e8e228eef7e1e9e2aa2850d719e3d915dab94'), ('tests/hotel_match_observed_page1_identity_readonly_v1_test.py', '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', 'd27156b2a002bcef68726ac1b9fe6d844d8dc9d19ec741352045a7ba848b7618')]
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
                            'assert a[:4]==["-n","-d","allow_url_fopen=0","-d"]\n'
                            'assert a[4]=="open_basedir="+str(pathlib.Path(a[-1]).parent)\n'
                            'assert a[5]=="-d" and "disable_functions=" in a[6]\n'
                            'assert "curl_exec" in a[6] and "proc_open" in a[6] and "stream_socket_client" in a[6]\n'
                            'assert a[7]=="-r" and pathlib.Path(a[-1]).name==".andromeda-private.php"\n'
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


if __name__=='__main__':unittest.main()
