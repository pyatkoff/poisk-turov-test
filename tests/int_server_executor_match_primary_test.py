#!/usr/bin/env python3
from __future__ import annotations
import ast
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
        event={'issue':{'number':3419},'comment':{'id':123,'body':body,'user':{'id':226193297},'author_association':'OWNER'}}
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
        specs=[(420,'9501','operator_342','24402','hotel-match-live30-common4-acquire-1971-20260923-o0-n100-v1','1d10e02a1a541a242b7466b3eab99887203c005ee270469f2c351179a3387faa'),
               (16944,'2000034238','operator_315','211585','hotel-match-live30-common4-continuation-resume-1971-20260924-r2-n138-v1','8e42b3e76cdef4075f09c9f8da68a8dd3881b93a263b094c88b74cc69b25ce3d'),
               (42903,'3126','operator_315','849821','hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n899-v1','11408e926160b87a10ffdf04ebb56106f17fb7efc30f95611033a5cb427dc794')]
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

if __name__=='__main__':unittest.main()
