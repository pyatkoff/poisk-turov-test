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

def batch_held():
    targets=m.batch_policy(CONTROL)['targets']
    items=[{'hotelId':x['hotelId'],'countryId':x['countryId'],'recordSha256':x['recordSha256'],'state':'held','reason':'retained_source_unavailable',
            'source':None,'consumer':None,'gaps':None} for x in targets]
    r={'schemaVersion':1,'source':'anytour-hotel-profile-batch-read-v1','state':'completed_read_only','reason':None,'observedAt':1792994000,
       'manifestSha256':m.BATCH_MANIFEST_SHA,'producerArtifactSha256':'c67a9aca2c2efd00e4a187cc43a3b6d258bd5629457778ee03d82e28f0d63b00',
       'producerJsonSha256':'0b79183d8e4d8615cd810151431859ca1cd43c5e1c066fb9d0b2a9e0930fe251','requestedCount':20,'observedCount':0,'heldCount':20,'possibleGapCount':0,
       'databaseSha256Before':m.DATABASE,'databaseSha256After':m.DATABASE,'protectedBefore':m.PROTECTED,'protectedAfter':m.PROTECTED,
       **dict.fromkeys(('supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts'),0),
       'priceAuthority':False,'importAllowed':False,'itemsSha256':hashlib.sha256(canonical(items)).hexdigest(),
       'operationId':m.BATCH_OPERATION,'sourceSha':m.SOURCE_SHA,'controlSha':CONTROL}
    return {'receipt':r,'items':items}

def batch_receipt(data):
    r=receipt(data);r.update(mode=m.BATCH_MODE,operation_id=m.BATCH_OPERATION)
    r['local_profile_batch']=r.pop('local_profile_completeness');return r

class BatchAdmission(unittest.TestCase):
    def test_fixed20_parser_and_original_programs_preserved(self):
        c=wrapper().core;body=c.PREFIX+' '.join([m.SOURCE_SHA,m.BATCH_MODE,m.BATCH_OPERATION])
        self.assertEqual(c.parse_command(body),m.batch_command())
        for bad in (body+' 21',body.replace(m.BATCH_OPERATION,m.OPERATION),body.replace(m.SOURCE_SHA,'a'*40)):
            with self.assertRaises(ValueError):c.parse_command(bad)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(body)
        self.assertEqual(c.parse_command(c.PREFIX+' '.join([m.SOURCE_SHA,m.MODE,m.OPERATION])),m.fixed_command())
        for k,digest in {'VALIDATOR':'bd2ea59c3252a6ed9f2a1c4221ecb42b32611bd26dba6dcc81e564661ca4011a',
                         'REMOTE_HANDLER':'9aa5f773b4e8ab5800fc1b6cc9fbffdaa588031a15940efd3296d2a06394f454',
                         'REMOTE_DISPATCH':'00b8fa6a5d6476cf1bd58a3ccde10e3513e29ce6a71ed8d11a208fb384a8e1c5'}.items():
            self.assertEqual(hashlib.sha256(getattr(m,k).encode()).hexdigest(),digest)
        self.assertEqual(m.RUNNER_SHA,'11e54194b60ec03b2d6bebf8492953685d524a782ac9d80657006e2d54b9f8d3')

    def test_closed_manifest_and_bundle_never_select_alternate_hotel(self):
        policy=m.batch_policy(CONTROL);ids=[x['hotelId']for x in policy['targets']]
        self.assertEqual(ids,[485,1451,9432,1351,465,1361,12114,1102,986,1141,15872,1574,1143,111129,5535,9270,1029,28660,88750,65659])
        raw,pins=m.bundle_batch_source(SOURCE)
        with tarfile.open(fileobj=io.BytesIO(raw),mode='r:gz')as package:
            self.assertEqual(set(package.getnames()),{*pins,'manifest.json'});self.assertEqual(len(package.getnames()),8)
        with mock.patch.object(m,'BATCH_MANIFEST_SHA','0'*64),self.assertRaisesRegex(ValueError,'manifest_pin'):m.batch_policy(CONTROL)

    def test_strict_partition_order_and_zero_authority_receipt(self):
        data=batch_held();r=batch_receipt(data);self.assertTrue(m.completed_batch(m.batch_command(),r,CONTROL))
        for field,value in (('heldCount',False),('observedCount',1),('importAllowed',True),('possibleGapCount',1),('producerArtifactSha256','0'*64)):
            changed=copy.deepcopy(data);changed['receipt'][field]=value;self.assertFalse(m.valid_batch_payload(changed,CONTROL))
        for mutation in ('order','extra','wrong_id'):
            changed=copy.deepcopy(data)
            if mutation=='order':changed['items'].reverse()
            elif mutation=='extra':changed['items'][0]['raw_url']='forbidden'
            else:changed['items'][0]['hotelId']=475
            changed['receipt']['itemsSha256']=hashlib.sha256(canonical(changed['items'])).hexdigest()
            self.assertFalse(m.valid_batch_payload(changed,CONTROL))
        namespace={'json':json,'hashlib':hashlib,'re':__import__('re')};exec(m.BATCH_VALIDATOR,namespace)
        with self.assertRaises(ValueError):namespace['profile_read_unique']([('a',1),('a',2)])

    def test_activation_keeps_old_no_replay_and_closed_archive(self):
        c=wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):m.activate(c,m.batch_command())
        ast.parse(c.REMOTE)
        self.assertIn("operation_exists_no_replay",c.REMOTE);self.assertIn("local_profile_batch_manifest",c.REMOTE)
        self.assertNotIn('str(stage/\'scripts/catalog/anytour_hotel_profile_completeness_read_v1.php\')',c.REMOTE)
        import base64,zlib
        self.assertLess(len(base64.b64encode(zlib.compress(c.REMOTE.encode(),9)))+150,65536)

    def test_actual_batch_private_refusal_preserves_original(self):
        c=wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):m.activate(c,m.batch_command())
        raw,pins=m.bundle_batch_source(SOURCE)
        with tempfile.TemporaryDirectory()as temp:
            home=Path(temp);project=home/'www/anytoour.ru';(project/'data').mkdir(parents=True);(project/'index.php').write_text('public')
            (project/'_preview/search3-anex-candidate').mkdir(parents=True)
            (project/'config.php').write_text('different config');(project/'data/db-v1.php').write_text('different helper')
            archive=home/'source.tar.gz';archive.write_bytes(raw)
            payload=m.batch_command()|{'archive':str(archive),'manifest_sha256':hashlib.sha256(canonical(pins)).hexdigest()}
            program=c.REMOTE.replace('home=pathlib.Path.home()','home=pathlib.Path('+repr(str(home))+')',1)
            import base64,zlib
            encoded=base64.b64encode(zlib.compress(program.encode(),9)).decode()
            def run():
                p=subprocess.run([sys.executable,'-c',"import base64,zlib;exec(zlib.decompress(base64.b64decode('"+encoded+"')))"],input=json.dumps(payload),text=True,capture_output=True,timeout=30)
                return json.loads(p.stdout)
            first=run();self.assertEqual(first['reason'],'local_profile_batch_private_drift',first)
            self.assertEqual(first['database_writes'],0);self.assertIsNone(first['local_profile_batch']['data'])
            path=home/'.anytoour-int-executor'/m.BATCH_OPERATION/'result.json';original=path.read_bytes()
            self.assertEqual(run()['reason'],'operation_exists_no_replay');self.assertEqual(path.read_bytes(),original)

class NativeBatch(unittest.TestCase):
    def test_actual_native20_source_and_consumer_gap_partition(self):
        env=dict(os.environ,LOCAL_PROFILE_SOURCE_ROOT=str(SOURCE))
        run=subprocess.run(['php',str(ROOT/'tests/anytour_hotel_profile_batch_read_v1_test.php')],env=env,capture_output=True,text=True,timeout=120)
        self.assertEqual(run.returncode,0,run.stdout+'\n'+run.stderr)
        self.assertRegex(run.stdout,r'LOCAL_PROFILE_BATCH_OK checks=[1-9][0-9]+ database=NATIVE_MYSQL')
        import base64
        data=json.loads(base64.b64decode(next(x.split('=',1)[1]for x in run.stdout.splitlines()if x.startswith('PROFILE_BATCH_READ_PAYLOAD='))))
        with mock.patch.object(m,'DATABASE',data['receipt']['databaseSha256Before']),mock.patch.object(m,'PROTECTED',data['receipt']['protectedBefore']):
            self.assertTrue(m.valid_batch_payload(data,CONTROL),data)
            self.assertTrue(m.completed_batch(m.batch_command(),batch_receipt(data),CONTROL))
        print(next(x for x in run.stdout.splitlines()if x.startswith('LOCAL_PROFILE_BATCH_OK')))

def evidence_held():
    return {'receipt':{'schemaVersion':1,'source':'anytour-hotel-profile-88750-evidence-v1','state':'held','reason':'no_evidence',
        'observedAt':1792999000,'hotelId':88750,'countryId':4,'parentResultSha256':m.EVIDENCE_PARENT_SHA,
        'parentTargetSha256':'2022f9f9592c1f27cdefb3a9370c9ee84cee3de4139d5a33f5428b713e899a50',
        'rawSha256Before':None,'rawSha256After':None,'sourceTimeSha256':None,'databaseSha256Before':None,'databaseSha256After':None,
        'protectedBefore':None,'protectedAfter':None,'blockCount':0,'textBytes':0,'evidenceSha256':None,
        **dict.fromkeys(('supplierCalls','databaseWrites','profileWrites','mappingWrites','schemaWrites','automaticAccepts'),0),
        'priceAuthority':False,'importAllowed':False,'operationId':m.EVIDENCE_OPERATION,'sourceSha':m.EVIDENCE_SOURCE,'controlSha':CONTROL},'evidence':None}

def evidence_success():
    d=evidence_held();r=d['receipt'];text='Пляж отеля; сохранённый факт.'
    block={'sourcePath':'$.infrastructure.beach','consumerPath':'hotelInformation.infrastructure.beach','text':text,
        'sha256':hashlib.sha256(text.encode()).hexdigest(),'byteLength':len(text.encode()),
        'consumerComparison':{'state':'equal','sha256':hashlib.sha256(text.encode()).hexdigest(),'byteLength':len(text.encode())}}
    e={'hotelId':88750,'countryId':4,'name':'THE X BELEK','rawSha256':m.EVIDENCE_RAW_SHA,'sourceTimeSha256':m.EVIDENCE_TIME_SHA,
       'consumerRevision':3,'consumerState':'ready','manualFieldCount':0,'sourceAbsentCount':1,
       'sourceAbsentSha256':'8f515b15e10dfa8d9181889456d432e3b6b5d40458b1c41fbd864e9fa628a4d2','blocks':[block]}
    r.update(state='completed_read_only',reason=None,rawSha256Before=m.EVIDENCE_RAW_SHA,rawSha256After=m.EVIDENCE_RAW_SHA,
        sourceTimeSha256=m.EVIDENCE_TIME_SHA,databaseSha256Before=m.DATABASE,databaseSha256After=m.DATABASE,
        protectedBefore=m.PROTECTED,protectedAfter=m.PROTECTED,blockCount=1,textBytes=len(text.encode()),evidenceSha256=hashlib.sha256(canonical(e)).hexdigest())
    d['evidence']=e;return d

def evidence_receipt(data):
    r=receipt(data);r.update(mode=m.EVIDENCE_MODE,operation_id=m.EVIDENCE_OPERATION,source_sha=m.EVIDENCE_SOURCE)
    r['local_profile_88750_evidence']=r.pop('local_profile_completeness');return r

class EvidenceAdmission(unittest.TestCase):
    def test_new_scope_fresh_source_and_old_capsules_preserved(self):
        c=wrapper().core;body=c.PREFIX+' '.join([m.EVIDENCE_SOURCE,m.EVIDENCE_MODE,m.EVIDENCE_OPERATION])
        self.assertEqual(c.parse_command(body),m.evidence_command())
        for bad in (body+' 88751',body.replace(m.EVIDENCE_SOURCE,m.SOURCE_SHA),body.replace(m.EVIDENCE_OPERATION,m.BATCH_OPERATION)):
            with self.assertRaises(ValueError):c.parse_command(bad)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError):c.parse_command(body)
        self.assertEqual(c.parse_command(c.PREFIX+' '.join([m.SOURCE_SHA,m.BATCH_MODE,m.BATCH_OPERATION])),m.batch_command())
        self.assertEqual(m.BATCH_RUNNER_SHA,'92d95e5920449375836de22ea2f312b9226480bc2f1fc2dd629031fafdf0e67e')
        self.assertEqual(hashlib.sha256(m.BATCH_VALIDATOR.encode()).hexdigest(),'75dba2e50684b62325f1392326b9a745221721752bdea2e85acfed40b798a44a')

    def test_exact_closed_bundle_with_current_verified_dependency_bytes(self):
        raw,pins=m.bundle_evidence_source(SOURCE)
        with tarfile.open(fileobj=io.BytesIO(raw),mode='r:gz')as package:
            self.assertEqual(set(package.getnames()),{*pins,'manifest.json'});self.assertEqual(len(package.getnames()),7)
        with mock.patch.object(m,'EVIDENCE_RUNNER_SHA','0'*64),self.assertRaisesRegex(ValueError,'source_drift'):m.bundle_evidence_source(SOURCE)

    def test_exact_text_provenance_and_forged_authority_rejected(self):
        d=evidence_success();self.assertTrue(m.valid_evidence_payload(d,CONTROL));self.assertTrue(m.completed_evidence(m.evidence_command(),evidence_receipt(d),CONTROL))
        self.assertTrue(m.valid_evidence_payload(evidence_held(),CONTROL));self.assertFalse(m.completed_evidence(m.evidence_command(),evidence_receipt(evidence_held()),CONTROL))
        changes=[(('receipt','hotelId'),88751),(('receipt','parentResultSha256'),'0'*64),(('receipt','profileWrites'),True),
            (('receipt','importAllowed'),True),(('receipt','rawSha256After'),'0'*64),(('evidence','consumerRevision'),4),
            (('evidence','blocks',0,'sourcePath'),'$.common.phone'),(('evidence','blocks',0,'sourcePath'),'$.meals.secret'),
            (('evidence','blocks',0,'sourcePath'),'$[1].infrastructure.beach'),(('evidence','blocks',0,'text'),'invented'),
            (('evidence','blocks',0,'consumerComparison','state'),'different')]
        for path,value in changes:
            changed=copy.deepcopy(d);target=changed
            for k in path[:-1]:target=target[k]
            target[path[-1]]=value
            changed['receipt']['evidenceSha256']=hashlib.sha256(canonical(changed['evidence'])).hexdigest()
            self.assertFalse(m.valid_evidence_payload(changed,CONTROL),path)
        extra=copy.deepcopy(d);extra['evidence']['rawPayload']={};self.assertFalse(m.valid_evidence_payload(extra,CONTROL))

    def test_parent_gate_before_php_and_original_no_replay(self):
        c=wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):m.activate(c,m.evidence_command())
        ast.parse(c.REMOTE)
        import base64,zlib
        self.assertLess(len(base64.b64encode(zlib.compress(c.REMOTE.encode(),9)))+150,65536)
        self.assertIn('local_profile_88750_parent_drift',c.REMOTE);self.assertIn('profile_88750_batch_valid',c.REMOTE)
        raw,pins=m.bundle_evidence_source(SOURCE)
        with tempfile.TemporaryDirectory()as temp:
            home=Path(temp);project=home/'www/anytoour.ru';(project/'data').mkdir(parents=True);(project/'index.php').write_text('public')
            (project/'_preview/search3-anex-candidate').mkdir(parents=True)
            archive=home/'source.tar.gz';archive.write_bytes(raw)
            payload=m.evidence_command()|{'archive':str(archive),'manifest_sha256':hashlib.sha256(canonical(pins)).hexdigest()}
            program=c.REMOTE.replace('home=pathlib.Path.home()','home=pathlib.Path('+repr(str(home))+')',1)
            encoded=base64.b64encode(zlib.compress(program.encode(),9)).decode()
            def run():
                p=subprocess.run([sys.executable,'-c',"import base64,zlib;exec(zlib.decompress(base64.b64decode('"+encoded+"')))"],input=json.dumps(payload),text=True,capture_output=True,timeout=30)
                return json.loads(p.stdout)
            first=run();self.assertEqual(first['reason'],'local_profile_88750_parent_unavailable',first)
            self.assertNotIn('local_profile_88750_evidence',first);self.assertEqual(first['supplier_calls'],0)
            path=home/'.anytoour-int-executor'/m.EVIDENCE_OPERATION/'result.json';original=path.read_bytes()
            self.assertEqual(run()['reason'],'operation_exists_no_replay');self.assertEqual(path.read_bytes(),original)

    def test_real_owner_main_and_fresh_release_guard(self):
        c=wrapper().core;body=c.PREFIX+' '.join([m.EVIDENCE_SOURCE,m.EVIDENCE_MODE,m.EVIDENCE_OPERATION])
        event={'issue':{'number':4709},'comment':{'id':101,'user':{'id':226193297},'author_association':'OWNER','body':body}}
        def api(path,token):return {'body':body,'user':{'id':226193297}}if path=='/issues/comments/101'else{'object':{'sha':CONTROL if path=='/git/ref/heads/main'else m.EVIDENCE_SOURCE}}
        with mock.patch.object(c,'api_get',side_effect=api):
            self.assertEqual(c.checked_event('fixture',event,CONTROL),m.evidence_command())
            with self.assertRaises(ValueError):c.checked_event('fixture',event|{'issue':{'number':4217}},CONTROL)

class NativeEvidence(unittest.TestCase):
    def test_actual_single_hotel_evidence_and_native_readonly_guards(self):
        env=dict(os.environ,LOCAL_PROFILE_SOURCE_ROOT=str(SOURCE))
        run=subprocess.run(['php',str(ROOT/'tests/anytour_hotel_profile_88750_evidence_v1_test.php')],env=env,capture_output=True,text=True,timeout=120)
        self.assertEqual(run.returncode,0,run.stdout+'\n'+run.stderr)
        self.assertRegex(run.stdout,r'LOCAL_PROFILE_88750_EVIDENCE_OK checks=[1-9][0-9]+ database=NATIVE_MYSQL')
        import base64
        data=json.loads(base64.b64decode(next(x.split('=',1)[1]for x in run.stdout.splitlines()if x.startswith('PROFILE_88750_EVIDENCE_PAYLOAD='))))
        with mock.patch.object(m,'DATABASE',data['receipt']['databaseSha256Before']),mock.patch.object(m,'PROTECTED',data['receipt']['protectedBefore']),\
             mock.patch.object(m,'EVIDENCE_RAW_SHA',data['receipt']['rawSha256Before']),mock.patch.object(m,'EVIDENCE_TIME_SHA',data['receipt']['sourceTimeSha256']):
            self.assertTrue(m.valid_evidence_payload(data,CONTROL),data)
            self.assertTrue(m.completed_evidence(m.evidence_command(),evidence_receipt(data),CONTROL))
        print(next(x for x in run.stdout.splitlines()if x.startswith('LOCAL_PROFILE_88750_EVIDENCE_OK')))

if __name__=='__main__':unittest.main()
