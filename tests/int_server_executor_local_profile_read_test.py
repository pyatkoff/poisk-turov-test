from __future__ import annotations
import ast
import copy
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
SOURCE=Path(os.environ.get('LOCAL_PROFILE_SOURCE_ROOT',ROOT/'local-profile-source'))
CONTROL='b'*40
def module(name,path):
    spec=importlib.util.spec_from_file_location(name,path);value=importlib.util.module_from_spec(spec);spec.loader.exec_module(value);return value
m=module('profile_read',ROOT/'scripts/deploy/int_server_executor_local_profile_read.py')
def wrapper():return module('profile_wrapper',ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py')
def canonical(x):return json.dumps(x,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()
def held():
    return {'receipt':{'schemaVersion':1,'source':'anytour-hotel-profile-completeness-read-v1','state':'catalog_hotel_missing_or_inactive','observedAt':1792980000,
        'catalogHotelId':475,'countryId':1,'producerNamespace':'catalog_hotels','producerRecordSha256':'6e2dcaad78922dc71375504118ccfeb9be03a7e412604e2e52fd5e40e94ffe87',
        'producerArtifactSha256':'c67a9aca2c2efd00e4a187cc43a3b6d258bd5629457778ee03d82e28f0d63b00','databaseSha256Before':m.DATABASE,'databaseSha256After':m.DATABASE,
        'protectedBefore':m.PROTECTED,'protectedAfter':m.PROTECTED,'supplierCalls':0,'databaseWrites':0,'profileWrites':0,'mappingWrites':0,'schemaWrites':0,'automaticAccepts':0,
        'crossNamespaceBridge':'not_read_not_required','priceAuthority':False,'importAllowed':False,'profileSha256':None,'operationId':m.OPERATION,'sourceSha':m.SOURCE_SHA,'controlSha':CONTROL},'profile':None}
def receipt(data):
    public={k:'a'*64 for k in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
    return {'schema_version':1,'mode':m.MODE,'operation_id':m.OPERATION,'source_sha':m.SOURCE_SHA,'status':'complete',
        **dict.fromkeys(('supplier_calls','database_writes','booking_calls','lead_calls','profile_writes','mapping_writes','schema_writes'),0),
        'production_before':public,'production_after':dict(public),'production_unchanged':True,
        'local_profile_completeness':{'data':data,'fileSha256':hashlib.sha256(canonical(data)+b'\n').hexdigest(),'protectedBefore':m.PROTECTED,'protectedAfter':m.PROTECTED}}

class Admission(unittest.TestCase):
    def test_one_fixed_command_and_stock_parser_preserved(self):
        c=wrapper().core
        body=c.PREFIX+' '.join([m.SOURCE_SHA,m.MODE,m.OPERATION])
        self.assertEqual(c.parse_command(body),m.fixed_command())
        for bad in (body+' 476',body.replace('20261026','20261027'),body.replace(m.SOURCE_SHA,'a'*40)):
            with self.assertRaises(ValueError):c.parse_command(bad)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(body)
        self.assertEqual(c.parse_command(c.PREFIX+'a'*40+' local-tv-content-v1 int-andromeda-local-tv-content-fill-20261010-v1')['provider_http_calls'],200)

    def test_real_owner_main_source_guards(self):
        c=wrapper().core;body=c.PREFIX+' '.join([m.SOURCE_SHA,m.MODE,m.OPERATION])
        event={'issue':{'number':4709},'comment':{'id':101,'user':{'id':226193297},'author_association':'OWNER','body':body}}
        def api(path,token):
            return {'body':body,'user':{'id':226193297}} if path=='/issues/comments/101' else {'object':{'sha':CONTROL if path=='/git/ref/heads/main' else m.SOURCE_SHA}}
        with mock.patch.object(c,'api_get',side_effect=api):
            self.assertEqual(c.checked_event('fixture',event,CONTROL),m.fixed_command())
            with self.assertRaisesRegex(ValueError,'main_changed'):c.checked_event('fixture',event,'c'*40)
            with self.assertRaises(ValueError):c.checked_event('fixture',event|{'issue':{'number':4217}},CONTROL)

    def test_closed_bundle_pins_and_native_source(self):
        raw,pins=m.bundle_source(SOURCE)
        with tarfile.open(fileobj=io.BytesIO(raw),mode='r:gz')as package:
            self.assertEqual(set(package.getnames()),{*pins,'manifest.json'})
            self.assertTrue(all(x.isfile() and x.mode==0o600 for x in package.getmembers()))
        with mock.patch.object(m,'RUNNER_SHA','0'*64),self.assertRaisesRegex(ValueError,'source_drift'):m.bundle_source(SOURCE)

    def test_activation_keeps_no_replay_and_bounded_stock_transport(self):
        c=wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):m.activate(c,m.fixed_command())
        ast.parse(c.REMOTE)
        self.assertIn("operation_exists_no_replay",c.REMOTE)
        self.assertIn("if globals().get('profile_read_reserved') is True and op.exists():",c.REMOTE)
        self.assertIn('disable_functions=',c.REMOTE)
        self.assertNotIn('StrictHostKeyChecking=no',c.REMOTE)
        import base64,zlib
        self.assertLess(len(base64.b64encode(zlib.compress(c.REMOTE.encode(),9)))+150,65536)

    def test_strict_held_receipt_and_forged_counts_or_content_rejected(self):
        data=held();r=receipt(data);self.assertTrue(m.completed(m.fixed_command(),r,CONTROL))
        for path,value in ((('supplier_calls',),False),(('database_writes',),1),(('local_profile_completeness','data','receipt','importAllowed'),True),
                           (('local_profile_completeness','data','receipt','catalogHotelId'),476),(('local_profile_completeness','fileSha256'),'0'*64)):
            changed=copy.deepcopy(r);target=changed
            for k in path[:-1]:target=target[k]
            target[path[-1]]=value;self.assertFalse(m.completed(m.fixed_command(),changed,CONTROL))
        self.assertFalse(m.valid_payload(data|{'rawDescription':'forbidden'},CONTROL))
        data['receipt']['state']='completed_read_only';self.assertFalse(m.valid_payload(data,CONTROL))

    def test_actual_remote_private_refusal_and_original_no_replay(self):
        c=wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):m.activate(c,m.fixed_command())
        raw,pins=m.bundle_source(SOURCE)
        with tempfile.TemporaryDirectory()as temp:
            home=Path(temp);project=home/'www/anytoour.ru';(project/'data').mkdir(parents=True);(project/'index.php').write_text('public')
            (project/'_preview/search3-anex-candidate').mkdir(parents=True)
            (project/'config.php').write_text('different current config');(project/'data/db-v1.php').write_text('different helper')
            archive=home/'source.tar.gz';archive.write_bytes(raw)
            payload=m.fixed_command()|{'archive':str(archive),'manifest_sha256':hashlib.sha256(canonical(pins)).hexdigest()}
            program=c.REMOTE.replace('home=pathlib.Path.home()','home=pathlib.Path('+repr(str(home))+')',1)
            import base64,zlib
            encoded=base64.b64encode(zlib.compress(program.encode(),9)).decode()
            def run():
                p=subprocess.run([sys.executable,'-c',"import base64,zlib;exec(zlib.decompress(base64.b64decode('"+encoded+"')))"],input=json.dumps(payload),text=True,capture_output=True,timeout=30)
                return json.loads(p.stdout)
            first=run();self.assertEqual(first['reason'],'local_profile_read_private_drift',first)
            self.assertEqual(first['supplier_calls'],0);self.assertIsNone(first['local_profile_completeness']['data'])
            path=home/'.anytoour-int-executor'/m.OPERATION/'result.json';original=path.read_bytes()
            self.assertEqual(run()['reason'],'operation_exists_no_replay');self.assertEqual(path.read_bytes(),original)

class Native(unittest.TestCase):
    def test_actual_native_mysql_consumer_and_source_provenance(self):
        env=dict(os.environ,LOCAL_PROFILE_SOURCE_ROOT=str(SOURCE))
        run=subprocess.run(['php',str(ROOT/'tests/anytour_hotel_profile_completeness_read_v1_test.php')],env=env,capture_output=True,text=True,timeout=120)
        self.assertEqual(run.returncode,0,run.stdout+'\n'+run.stderr)
        self.assertRegex(run.stdout,r'LOCAL_PROFILE_COMPLETENESS_OK checks=[1-9][0-9]+ database=NATIVE_MYSQL')
        import base64
        data=json.loads(base64.b64decode(next(x.split('=',1)[1] for x in run.stdout.splitlines() if x.startswith('PROFILE_READ_PAYLOAD='))))
        with mock.patch.object(m,'DATABASE',data['receipt']['databaseSha256Before']),mock.patch.object(m,'PROTECTED',data['receipt']['protectedBefore']):
            self.assertTrue(m.valid_payload(data,CONTROL),data)
            self.assertTrue(m.completed(m.fixed_command(),receipt(data),CONTROL))
        print(next(x for x in run.stdout.splitlines() if x.startswith('LOCAL_PROFILE_COMPLETENESS_OK')))

if __name__=='__main__':unittest.main()
