"""Finite carrier guards plus the actual canonical MySQL owner through the MAIN runner."""
from __future__ import annotations

import ast
import base64
import copy
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import tempfile
import unittest
from unittest import mock
import zlib

ROOT = Path(__file__).resolve().parents[1]
CONTROL = 'a' * 40
CONTROL_ONLY = '--control-only' in sys.argv
if CONTROL_ONLY:
    sys.argv.remove('--control-only')


def load_wrapper():
    path = ROOT / 'scripts/deploy/int_server_executor_anex_secret_transport.py'
    spec = importlib.util.spec_from_file_location('official11_test_wrapper', path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()


def terminal(module, fields=33, profiles=11):
    counts = {'description': 11, 'primaryImage': 11, 'images': 11}
    if fields == 0:
        counts = dict.fromkeys(counts, 0)
    elif fields == 31:
        counts.update(description=10, images=10)
    protected = {'config.php': 'c' * 64, 'data/db-v1.php': module.DB_HELPER_SHA}
    return {'schema_version': 1, 'operation_id': module.OPERATION, 'source_sha': module.SOURCE_SHA,
            'control_sha': CONTROL, 'manifest_sha256': module.APPROVED_SHA,
            'actions_sha256': module.ACTIONS_SHA, 'original_package_sha256': module.ORIGINAL_PACKAGE_SHA,
            'approval_ref': module.APPROVAL_REF, 'state': 'committed_verified',
            'requested_profiles': 11, 'requested_fields': 33, 'plan_sha256': 'd' * 64,
            'profiles_updated': profiles, 'fields_updated': fields, 'field_counts': counts,
            'preserved_fields': 33 - fields, 'profile_writes': profiles, 'provenance_writes': profiles,
            'database_writes': 2 * profiles, 'supplier_calls': 0, 'provider_http_calls': 0,
            'mapping_writes': 0, 'legacy_writes': 0, 'schema_writes': 0, 'readback_verified': True,
            'consumer_readback_verified': True, 'consumer_sha256': 'e' * 64,
            'owner_receipt_sha256': 'f' * 64, 'protected_before': protected,
            'protected_after': dict(protected), 'error_code': None}


def outer(module, data):
    public = {'index.php': 'c' * 64, 'v2/index.php': None, 'v2/api-v2.php': None,
              'v2/lead-adapter-v2.php': None}
    return {'schema_version': 1, 'operation_id': module.OPERATION, 'source_sha': module.SOURCE_SHA,
            'mode': module.MODE, 'status': 'complete', 'supplier_calls': 0,
            'database_writes': data['database_writes'], 'booking_calls': 0, 'lead_calls': 0,
            'production_before': public, 'production_after': dict(public), 'production_unchanged': True,
            'profile_writes': data['profile_writes'], 'provenance_writes': data['provenance_writes'],
            'mapping_writes': 0, 'legacy_writes': 0, 'schema_writes': 0, 'local_official11': data}


class CarrierContract(unittest.TestCase):
    def setUp(self):
        self.wrapper = load_wrapper()
        self.core, self.module = self.wrapper.core, self.wrapper.local_official11
        self.stock_remote = self.core.REMOTE
        self.patches = [mock.patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '1', 'GITHUB_SHA': CONTROL}),
                        mock.patch.object(self.module, 'SOURCE_SHA', 'b' * 40),
                        mock.patch.object(self.module, 'RUNNER_SHA', hashlib.sha256((ROOT / self.module.RUNNER).read_bytes()).hexdigest())]
        self.fixture_sources = {name: ('<?php /* fixture ' + name + ' */\n').encode()
                                for name in self.module.SOURCE_HASHES}
        self.patches.append(mock.patch.object(self.module, 'SOURCE_HASHES',
            {name: hashlib.sha256(raw).hexdigest() for name, raw in self.fixture_sources.items()}))
        for patch in self.patches:
            patch.start()
            self.addCleanup(patch.stop)

    def command_text(self):
        m = self.module
        return self.core.PREFIX + ' '.join([m.SOURCE_SHA, m.MODE, m.OPERATION])

    def test_exact_projection_and_finite_parser(self):
        m = self.module
        data = m.approved_manifest()
        self.assertEqual(len(data['proposed_actions']), 11)
        self.assertEqual(sum(len(a['fields']) for a in data['proposed_actions']), 33)
        self.assertEqual(sum(len(t['photos']) for t in data['targets']), 25)
        self.assertEqual(self.core.parse_command(self.command_text()), m.fixed_command())
        for bad in (self.command_text() + ' 12', self.command_text().replace('-20261121-v1', '-20261121-v2'),
                    self.command_text().replace(m.SOURCE_SHA, 'c' * 40),
                    self.command_text().replace(m.OPERATION, 'int-andromeda-local-profile-apply-4191-retained36-20261001-v1')):
            with self.assertRaises(ValueError):
                self.core.parse_command(bad)
        for attempt in ('2', '0', '', None):
            with mock.patch.dict(os.environ, clear=True):
                if attempt is not None:
                    os.environ['GITHUB_RUN_ATTEMPT'] = attempt
                with self.assertRaises(ValueError):
                    self.core.parse_command(self.command_text())
        raw = (ROOT / m.APPROVED).read_text()
        self.assertNotIn('/workspace/', raw)
        self.assertNotIn('independent_reviews', raw)
        self.assertNotIn('"facts":', raw)

    def test_fresh_owner_main_release_and_old_parser_preserved(self):
        m, c = self.module, self.core
        body = self.command_text()
        event = {'issue': {'number': 4709}, 'comment': {'id': 101, 'body': body,
                 'user': {'id': 226193297}, 'author_association': 'OWNER'}}
        calls = []
        def api(path, token):
            calls.append(path)
            if path == '/issues/comments/101':
                return {'body': body, 'user': {'id': 226193297}}
            return {'object': {'sha': CONTROL if path == '/git/ref/heads/main' else m.SOURCE_SHA}}
        with mock.patch.object(c, 'api_get', side_effect=api):
            self.assertEqual(c.checked_event('fixture', event, CONTROL), m.fixed_command())
            self.assertIn('/git/ref/heads/release/search3-production-ready-v1', calls)
            with self.assertRaisesRegex(ValueError, 'main_changed'):
                c.checked_event('fixture', event, 'd' * 40)
            with self.assertRaises(ValueError):
                c.checked_event('fixture', event | {'issue': {'number': 4217}}, CONTROL)
            with self.assertRaises(ValueError):
                c.checked_event('fixture', event | {'comment': event['comment'] | {'user': {'id': 1}}}, CONTROL)
        with mock.patch.dict(os.environ, {'GITHUB_ACTOR': 'other', 'GITHUB_TRIGGERING_ACTOR': 'pyatkoff',
                                         'INT_OWNER_COMMAND': body}), self.assertRaises(ValueError):
            c.checked_event('fixture', {}, CONTROL)
        self.assertEqual(c.parse_command(c.PREFIX + 'a' * 40 +
            ' local-tv-content-v1 int-andromeda-local-tv-content-fill-20261010-v1')['provider_http_calls'], 200)
        self.assertNotIn(m.MODE, self.wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(m.MODE, self.wrapper.DIRECT_ANEX_MODES)

    def fixture(self, temporary, helper_valid=False):
        m = self.module
        directory = Path(temporary)
        runtime_home = directory / 'account'
        project = runtime_home / 'www/anytoour.ru'
        (project / 'data').mkdir(parents=True)
        (project / '_preview/search3-anex-candidate').mkdir(parents=True)
        (project / 'index.php').write_text('public')
        (project / 'config.php').write_text('<?php /* private test config */')
        (project / 'data/db-v1.php').write_bytes((ROOT / 'v2/data/db-v1.php').read_bytes() if helper_valid else b'wrong helper')
        source = directory / 'source'
        for name, raw in self.fixture_sources.items():
            path = source / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(raw)
        raw, pins = m.bundle_source(source)
        archive = directory / 'source.tar.gz'
        archive.write_bytes(raw)
        self.core.REMOTE = self.stock_remote
        m.activate(self.core, m.fixed_command())
        program = self.core.REMOTE.replace('home=pathlib.Path.home()', 'home=pathlib.Path(' + repr(str(runtime_home)) + ')', 1)
        payload = m.fixed_command() | {'archive': str(archive), 'manifest_sha256': hashlib.sha256(canonical(pins)).hexdigest(),
                                     'local_official11_control_sha': CONTROL}
        return runtime_home, project, archive, program, payload

    def invoke(self, program, payload, env=None):
        encoded = base64.b64encode(zlib.compress(program.encode(), 9)).decode()
        code = "import base64,zlib;exec(zlib.decompress(base64.b64decode(" + repr(encoded) + ")))"
        run = subprocess.run([sys.executable, '-c', code], input=json.dumps(payload),
                             text=True, capture_output=True, timeout=20, env=env)
        self.assertEqual(run.returncode, 0, run.stderr)
        self.assertEqual(run.stderr, '')
        return json.loads(run.stdout)

    def test_bundle_and_generated_program_are_closed_and_bounded(self):
        with tempfile.TemporaryDirectory() as temporary:
            _, _, archive, program, _ = self.fixture(temporary)
            with tarfile.open(archive) as package:
                self.assertEqual(set(package.getnames()), {*self.module.pins(), 'manifest.json'})
                self.assertTrue(all(m.isfile() and m.mode == 0o600 for m in package.getmembers()))
            ast.parse(program)
            self.assertLess(len(base64.b64encode(zlib.compress(program.encode(), 9))) + 150, 65536)
            self.assertIn("if globals().get('official11_reserved') is True and op.exists():", program)
            self.assertIn(self.module.DB_HELPER_SHA, program)
            self.assertIn("helper=project/'data/db-v1.php'", program)
            self.assertNotIn("else '/v2/data/db-v1.php'", program)
            self.assertEqual(program.count("if mode not in ('" + self.module.MODE + "','reconcile',"), 2)

    def test_wrong_db_helper_stops_before_php_and_retry_cannot_rewrite_receipt(self):
        with tempfile.TemporaryDirectory() as temporary:
            runtime_home, _, _, program, payload = self.fixture(temporary)
            first = self.invoke(program, payload)
            self.assertEqual(first['reason'], 'official11_db_helper_drift')
            self.assertNotIn('local_official11', first)
            operation = runtime_home / '.anytoour-int-executor' / self.module.OPERATION
            original = {p.name: p.read_bytes() for p in (operation / 'reservation.json', operation / 'result.json')}
            second = self.invoke(program, payload)
            self.assertEqual(second['reason'], 'operation_exists_no_replay')
            self.assertEqual(original, {p.name: p.read_bytes() for p in (operation / 'reservation.json', operation / 'result.json')})
            self.assertFalse((operation / 'official11-apply-started.json').exists())

    def test_archive_addition_and_control_drift_never_execute_php(self):
        for mutation in ('archive', 'control'):
            with self.subTest(mutation=mutation), tempfile.TemporaryDirectory() as temporary:
                _, _, archive, program, payload = self.fixture(temporary)
                if mutation == 'archive':
                    with tarfile.open(archive) as package:
                        items = [(m, package.extractfile(m).read()) for m in package.getmembers()]
                    with tarfile.open(archive, 'w:gz') as package:
                        for info, raw in items:
                            package.addfile(info, io.BytesIO(raw))
                        info = tarfile.TarInfo('extra.php'); info.size = 5
                        package.addfile(info, io.BytesIO(b'wrong'))
                else:
                    payload['local_official11_control_sha'] = 'd' * 40
                result = self.invoke(program, payload)
                self.assertEqual(result['reason'], 'official11_archive_members' if mutation == 'archive' else 'official11_control')

    def test_strict_completion_all_filled_partial_preservation_and_noop(self):
        for fields, profiles in ((33, 11), (31, 11), (0, 0)):
            data = terminal(self.module, fields, profiles)
            self.assertTrue(self.module.completed(self.module.fixed_command(), outer(self.module, data), CONTROL))
        good = outer(self.module, terminal(self.module))
        for field, bad in (('state', 'unknown_no_replay'), ('fields_updated', 32), ('preserved_fields', 1),
                           ('consumer_readback_verified', False), ('readback_verified', False), ('manifest_sha256', '0' * 64),
                           ('actions_sha256', '0' * 64), ('database_writes', 21), ('supplier_calls', False),
                           ('owner_receipt_sha256', None), ('requested_profiles', 12)):
            with self.subTest(field=field):
                value = copy.deepcopy(good); value['local_official11'][field] = bad
                self.assertFalse(self.module.completed(self.module.fixed_command(), value, CONTROL))
        for field, bad in (('database_writes', 0), ('profile_writes', False), ('status', 'installed'), ('booking_calls', 1)):
            value = copy.deepcopy(good); value[field] = bad
            self.assertFalse(self.module.completed(self.module.fixed_command(), value, CONTROL))
        value = copy.deepcopy(good); value['local_official11']['protected_after']['data/db-v1.php'] = '0' * 64
        self.assertFalse(self.module.completed(self.module.fixed_command(), value, CONTROL))
        value = copy.deepcopy(good)
        for target in (value, value['local_official11']):
            target.update(profile_writes=1, provenance_writes=1, database_writes=2)
        value['local_official11']['profiles_updated'] = 1
        self.assertFalse(self.module.completed(self.module.fixed_command(), value, CONTROL))

    def test_unknown_receipt_is_valid_evidence_but_never_success(self):
        data = terminal(self.module)
        data.update(state='unknown_no_replay', field_counts=None, readback_verified=False,
                    consumer_readback_verified=False, consumer_sha256=None, owner_receipt_sha256=None,
                    error_code='ANYTOUR_PROFILE_OFFICIAL_COMMIT_UNKNOWN')
        for key in ('profiles_updated', 'fields_updated', 'preserved_fields', 'profile_writes', 'provenance_writes', 'database_writes'):
            data[key] = 'unknown'
        self.assertTrue(self.module.official11_terminal(data, self.module.policy(CONTROL)))
        self.assertFalse(self.module.completed(self.module.fixed_command(), outer(self.module, data), CONTROL))

    def test_source_and_runner_drift_rejected_before_transport(self):
        with tempfile.TemporaryDirectory() as temporary:
            source = Path(temporary)
            for name, raw in self.fixture_sources.items():
                path = source / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(raw)
            self.module.bundle_source(source)
            first = next(iter(self.fixture_sources)); (source / first).write_bytes(b'changed')
            with self.assertRaisesRegex(ValueError, 'official11_source_drift'):
                self.module.bundle_source(source)
        with mock.patch.object(self.module, 'RUNNER_SHA', '0' * 64), self.assertRaises(ValueError):
            self.module.pins()

    def test_actual_php_manifest_and_prewrite_versus_postcall_failure(self):
        if shutil.which('php') is None:
            self.skipTest('PHP unavailable locally; required by workflow setup')
        code = '<?php require ' + json.dumps(str(ROOT / self.module.RUNNER)) + ';\n' + PHP_FAILURE_FIXTURE
        env = dict(os.environ, OFFICIAL11_TEST_MANIFEST=str(ROOT / self.module.APPROVED))
        run = subprocess.run(['php'], input=code, env=env, capture_output=True, text=True, timeout=20)
        self.assertEqual(run.returncode, 0, run.stdout + run.stderr)
        self.assertEqual(run.stdout.strip(), 'OFFICIAL11_PHP_FAILURE_GUARDS_OK')


PHP_FAILURE_FIXTURE = r'''
$approved=lo11_envelope((string)getenv('OFFICIAL11_TEST_MANIFEST'));
if(hash('sha256',$approved['manifestJson'])!==LO11_MANIFEST_SHA)throw new RuntimeException('manifest');
$identity=['schema_version'=>1,'operation_id'=>LO11_OPERATION,'source_sha'=>str_repeat('b',40),'control_sha'=>str_repeat('a',40),
 'manifest_sha256'=>LO11_MANIFEST_SHA,'actions_sha256'=>LO11_ACTIONS_SHA,'original_package_sha256'=>LO11_ORIGINAL_SHA,'approval_ref'=>LO11_APPROVAL_REF];
$protected=['config.php'=>str_repeat('c',64),'data/db-v1.php'=>LO11_DB_HELPER_SHA];
class Official11FailureFixture {
 public function __construct(private bool $after){}
 public function planOfficial11(array $approved):array {
  if(!$this->after)throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_IDENTITY');
  return ['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0,'planSha256'=>str_repeat('d',64),
    'selected'=>array_map(static fn(int $id):array=>['anytourHotelId'=>$id],LO11_IDS)];
 }
 public function applyOfficial11(string $op,array $approved,string $sha):array {throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_COMMIT_UNKNOWN');}
}
foreach([false,true] as $after){
 $saved=0;$started=0;
 $receipt=lo11_execute_owner(new Official11FailureFixture($after),$approved,$identity,$protected,
  static function(array $plan)use(&$saved){++$saved;},static function(string $sha)use(&$started){++$started;},static fn():array=>$protected);
 if($receipt['state']!==($after?'unknown_no_replay':'held_before_write')||$receipt['readback_verified']!==false
    ||$receipt['consumer_readback_verified']!==false||$saved!==($after?1:0)||$started!==($after?1:0))throw new RuntimeException('state');
 if($receipt['database_writes']!==($after?'unknown':0))throw new RuntimeException('writes');
}
echo "OFFICIAL11_PHP_FAILURE_GUARDS_OK\n";
'''


NATIVE_PHP = r'''
<?php
require getenv('OFFICIAL11_TEST_RUNNER');
require getenv('LOCAL_OFFICIAL11_SOURCE_ROOT').'/v2/data/anytour-profile-enrichment-v1.php';
$dsn=(string)getenv('ANYTOUR_PROFILE_ENRICH_TEST_DSN');
if(!str_contains($dsn,'dbname=anytour_official11_fixture;'))throw new RuntimeException('disposable_database_required');
$pdo=new PDO($dsn,'root',(string)getenv('ANYTOUR_PROFILE_ENRICH_TEST_PASSWORD'),[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$sql=file_get_contents(getenv('LOCAL_OFFICIAL11_SOURCE_ROOT').'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');
foreach(explode(';',preg_replace('/--[^\n]*/','',$sql)) as $part)if(trim($part)!=='')$pdo->exec($part);
$approved=lo11_envelope(getenv('OFFICIAL11_TEST_MANIFEST'));
$manifest=json_decode($approved['manifestJson'],true,128,JSON_THROW_ON_ERROR);
$identity=['schema_version'=>1,'operation_id'=>LO11_OPERATION,'source_sha'=>getenv('OFFICIAL11_TEST_SOURCE_SHA'),
 'control_sha'=>str_repeat('a',40),'manifest_sha256'=>LO11_MANIFEST_SHA,'actions_sha256'=>LO11_ACTIONS_SHA,
 'original_package_sha256'=>LO11_ORIGINAL_SHA,'approval_ref'=>LO11_APPROVAL_REF];
$protected=['config.php'=>str_repeat('c',64),'data/db-v1.php'=>LO11_DB_HELPER_SHA];
foreach([false,true] as $preserve){
 $pdo->exec('DELETE FROM anytour_hotel_sources');$pdo->exec('DELETE FROM anytour_hotels');
 $insert=$pdo->prepare('INSERT INTO anytour_hotels(id,profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
 foreach($manifest['targets'] as $target){
  $id=$target['anytourHotelId'];$before=$target['expectedIdentity'];
  $profile=['name'=>$before['name'],'country'=>['name'=>$before['country']],
    'region'=>$before['region']===null?null:['name'=>$before['region']],
    'description'=>null,'primaryImage'=>null,'images'=>[],'detailsAvailable'=>false];
  if($preserve&&$id===5371)$profile['description']='Существующее ручное описание';
  if($preserve&&$id===8689)$profile['images']=['https://www.fourseasons.com/manual-preserved.jpg'];
  $json=lo11_json($profile);$insert->execute([$id,$json,hash('sha256',$json)]);
 }
 $saved=0;$started=0;$owner=new AnyTourProfileEnrichmentV1($pdo);
 $receipt=lo11_execute_owner($owner,$approved,$identity,$protected,
   static function(array $plan)use(&$saved){foreach($plan['selected'] as $row)if(!is_string($row['beforeProfileJson']??null))throw new RuntimeException('private_snapshot');++$saved;},
   static function(string $sha)use(&$started){++$started;},static fn():array=>$protected);
 if($receipt['state']!=='committed_verified'||$receipt['profiles_updated']!==11||$receipt['fields_updated']!==($preserve?31:33)
    ||$receipt['preserved_fields']!==($preserve?2:0)||$saved!==1||$started!==1)throw new RuntimeException('native_receipt:'.lo11_json($receipt));
 if((int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn()!==11)throw new RuntimeException('provenance_count');
 $actual=(new AnyTourCanonicalCatalog($pdo))->read(LO11_IDS);$byId=[];
 foreach($actual['items'] as $row)$byId[$row['id']]=$row;
 foreach($manifest['proposed_actions'] as $action)foreach($action['fields'] as $field){
  $id=$action['anytourHotelId'];$key=$field['field'];$expected=$field['proposed_value'];
  if($preserve&&$id===5371&&$key==='description')$expected='Существующее ручное описание';
  if($preserve&&$id===8689&&$key==='images')$expected=['https://www.fourseasons.com/manual-preserved.jpg'];
  if(($byId[$id][$key]??null)!==$expected)throw new RuntimeException('native_consumer_value');
 }
 foreach($byId as $row)if($row['detailsAvailable']!==false||$row['revision']!==2)throw new RuntimeException('protected_extra_field');
 echo 'OFFICIAL11_NATIVE_RECEIPT='.base64_encode(lo11_json($receipt))."\n";
}
echo "OFFICIAL11_NATIVE_MYSQL_OK cases=2 profiles=11 fields=33/31\n";
'''


class ReviewedSourceAndNative(unittest.TestCase):
    def test_actual_source_policy_accepts_real_manifest(self):
        source = os.environ.get('LOCAL_OFFICIAL11_SOURCE_ROOT')
        if CONTROL_ONLY or not source:
            if os.environ.get('LOCAL_OFFICIAL11_NATIVE_CI') == '1' and not CONTROL_ONLY:
                self.fail('SOURCE checkout required for actual manifest admission')
            self.skipTest('SOURCE checkout is supplied by native CI')
        m = load_wrapper().local_official11
        code = ('<?php require ' + json.dumps(str(ROOT / m.RUNNER)) + '; require ' +
                json.dumps(str(Path(source) / 'v2/data/anytour-profile-enrichment-v1.php')) + ';' +
                '$method=new ReflectionMethod(AnyTourProfileEnrichmentV1::class,"officialApproved");' +
                '$approved=lo11_envelope(' + json.dumps(str(ROOT / m.APPROVED)) + ');' +
                '$actual=$method->invoke(null,$approved);' +
                'if(count($actual["targets"])!==11)throw new RuntimeException("target_count");' +
                'echo "OFFICIAL11_REAL_MANIFEST_ACCEPTED\\n";')
        run = subprocess.run(['php'], input=code, text=True, capture_output=True, timeout=20)
        self.assertEqual(run.returncode, 0, run.stdout + run.stderr)
        self.assertEqual(run.stdout.strip(), 'OFFICIAL11_REAL_MANIFEST_ACCEPTED')

    def test_exact_reviewed_source_bundle(self):
        source = os.environ.get('LOCAL_OFFICIAL11_SOURCE_ROOT')
        if CONTROL_ONLY or not source:
            if os.environ.get('LOCAL_OFFICIAL11_NATIVE_CI') == '1' and not CONTROL_ONLY:
                self.fail('LOCAL_OFFICIAL11_SOURCE_ROOT required in native CI')
            self.skipTest('SOURCE checkout is supplied by the native CI step')
        m = load_wrapper().local_official11
        self.assertNotEqual(m.SOURCE_SHA, '0' * 40)
        raw, hashes = m.bundle_source(Path(source))
        with tarfile.open(fileobj=io.BytesIO(raw)) as package:
            self.assertEqual(set(package.getnames()), {*hashes, 'manifest.json'})

    def test_actual_mysql_canonical_owner_and_main_runner(self):
        if CONTROL_ONLY:
            self.skipTest('runtime control precheck has no test database')
        required = ('LOCAL_OFFICIAL11_SOURCE_ROOT', 'ANYTOUR_PROFILE_ENRICH_TEST_DSN', 'ANYTOUR_PROFILE_ENRICH_TEST_PASSWORD')
        if any(not os.environ.get(key) for key in required):
            if os.environ.get('LOCAL_OFFICIAL11_NATIVE_CI') == '1':
                self.fail('native MySQL test configuration is required')
            self.skipTest('native MySQL unavailable locally; required in CI')
        m = load_wrapper().local_official11
        env = dict(os.environ, OFFICIAL11_TEST_RUNNER=str(ROOT / m.RUNNER),
                   OFFICIAL11_TEST_MANIFEST=str(ROOT / m.APPROVED), OFFICIAL11_TEST_SOURCE_SHA=m.SOURCE_SHA)
        run = subprocess.run(['php'], input=NATIVE_PHP.lstrip(), env=env, capture_output=True, text=True, timeout=120)
        self.assertEqual(run.returncode, 0, run.stdout + run.stderr)
        receipts = [json.loads(base64.b64decode(line.split('=', 1)[1])) for line in run.stdout.splitlines()
                    if line.startswith('OFFICIAL11_NATIVE_RECEIPT=')]
        self.assertEqual(len(receipts), 2, run.stdout)
        for data in receipts:
            self.assertTrue(m.completed(m.fixed_command(), outer(m, data), CONTROL), data)
        self.assertIn('OFFICIAL11_NATIVE_MYSQL_OK cases=2 profiles=11 fields=33/31', run.stdout)
        print('OFFICIAL11_NATIVE_MYSQL_OK cases=2 profiles=11 fields=33/31')


if __name__ == '__main__':
    unittest.main()
