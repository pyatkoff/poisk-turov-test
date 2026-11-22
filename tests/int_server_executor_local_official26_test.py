"""Exact LOCAL26 approval, existing carrier guards and canonical MySQL readback."""
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
BEFORE = ROOT / 'tests/fixtures/anytour-local-official26-before-20261122.json'
BEFORE_SHA = '487c0abc28ded737fb9d1ebc7aedecbd4101e183c6847d1c739a6235d5f9d21e'


def wrapper():
    path = ROOT / 'scripts/deploy/int_server_executor_anex_secret_transport.py'
    spec = importlib.util.spec_from_file_location('official26_test_wrapper', path)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()


def terminal(m):
    protected = {'config.php': 'c' * 64, 'data/db-v1.php': m.DB_HELPER_SHA}
    return {'schema_version': 1, 'operation_id': m.OPERATION26, 'source_sha': m.SOURCE_SHA26,
            'control_sha': CONTROL, 'manifest_sha256': m.APPROVED_SHA26,
            'actions_sha256': m.ACTIONS_SHA26, 'original_package_sha256': m.ORIGINAL_PACKAGE_SHA26,
            'approval_ref': m.APPROVAL_REF26, 'state': 'committed_verified',
            'requested_profiles': 26, 'requested_fields': 76, 'plan_sha256': 'd' * 64,
            'profiles_updated': 26, 'fields_updated': 76, 'field_counts': dict(m.FIELD_CAPS26),
            'preserved_fields': 0, 'profile_writes': 26, 'provenance_writes': 26,
            'database_writes': 52, 'supplier_calls': 0, 'provider_http_calls': 0,
            'mapping_writes': 0, 'legacy_writes': 0, 'schema_writes': 0, 'readback_verified': True,
            'consumer_readback_verified': True, 'consumer_sha256': 'e' * 64,
            'owner_receipt_sha256': 'f' * 64, 'protected_before': protected,
            'protected_after': dict(protected), 'error_code': None}


def outer(m, receipt):
    public = {'index.php': 'c' * 64, 'v2/index.php': None, 'v2/api-v2.php': None,
              'v2/lead-adapter-v2.php': None}
    return {'schema_version': 1, 'operation_id': m.OPERATION26, 'source_sha': m.SOURCE_SHA26,
            'mode': m.MODE26, 'status': 'complete', 'supplier_calls': 0,
            'database_writes': receipt['database_writes'], 'booking_calls': 0, 'lead_calls': 0,
            'production_before': public, 'production_after': dict(public), 'production_unchanged': True,
            'profile_writes': receipt['profile_writes'], 'provenance_writes': receipt['provenance_writes'],
            'mapping_writes': 0, 'legacy_writes': 0, 'schema_writes': 0, 'local_official26': receipt}


class Carrier26Contract(unittest.TestCase):
    def setUp(self):
        self.wrapper = wrapper()
        self.core, self.m = self.wrapper.core, self.wrapper.local_official11
        self.stock_remote = self.core.REMOTE
        self.fixture_sources = {name: ('<?php /* fixture ' + name + ' */\n').encode()
                                for name in self.m.SOURCE_HASHES26}
        patches = [mock.patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '1', 'GITHUB_SHA': CONTROL}),
                   mock.patch.object(self.m, 'SOURCE_SHA26', 'b' * 40),
                   mock.patch.object(self.m, 'RUNNER_SHA26', hashlib.sha256((ROOT / self.m.RUNNER26).read_bytes()).hexdigest()),
                   mock.patch.object(self.m, 'SOURCE_HASHES26', {k: hashlib.sha256(v).hexdigest()
                                                               for k, v in self.fixture_sources.items()})]
        for patch in patches:
            patch.start(); self.addCleanup(patch.stop)

    def body(self):
        return self.core.PREFIX + ' '.join([self.m.SOURCE_SHA26, self.m.MODE26, self.m.OPERATION26])

    def test_exact_reviewed_projection_and_hilton_single_field(self):
        m = self.m
        data = m.approved_manifest26()
        self.assertEqual(len(data['proposed_actions']), 26)
        self.assertEqual(sum(len(a['field_changes']) for a in data['proposed_actions']), 76)
        self.assertEqual(sum(len(t['photos']) for t in data['targets']), 50)
        self.assertEqual(hashlib.sha256(canonical(data['proposed_actions'])).hexdigest(), m.ACTIONS_SHA26)
        before_bytes = BEFORE.read_bytes()
        self.assertEqual(hashlib.sha256(before_bytes).hexdigest(), BEFORE_SHA)
        before = {x['id']: x for x in json.loads(before_bytes)['items']}
        for action, target in zip(data['proposed_actions'], data['targets']):
            ident = action['anytour_hotel_id']
            self.assertEqual(action['observed_public_profile_sha256'], hashlib.sha256(canonical(before[ident])).hexdigest())
            self.assertEqual(action['observed_revision'], before[ident]['revision'])
            for field in action['field_changes']:
                self.assertEqual(field['before'], before[ident][field['field']])
            if ident == 4172:
                self.assertEqual([f['field'] for f in action['field_changes']], ['description'])
                self.assertEqual(target['photos'], [])
                self.assertEqual(len(before[ident]['images']), 44)
        captures = [s for t in data['targets'] for s in t['sources']]
        self.assertEqual(sum(s['capture_format'] == 'retrieved_web_text_not_origin_html' for s in captures), 1)
        self.assertEqual(len(captures), 75)
        self.assertNotIn('/workspace/', (ROOT / m.APPROVED26).read_text())

    def test_old_eleven_pins_and_bytes_remain_unchanged(self):
        m = self.m
        self.assertEqual(m.SOURCE_SHA, '8b804957501d5b4715bd6f400fb794f02792d3de')
        self.assertEqual(m.OPERATION, 'int-andromeda-local-official11-apply-20261121-v1')
        self.assertEqual(m.APPROVED_SHA, '93e0ea33e64ce2c41d68ffb54e9efaf6e383f3cdde68f01c6aa4ab51f4e5a242')
        self.assertEqual(m.RUNNER_SHA, '94db2b7f7e6d38fce9550040a691e4c545928ae730b2387be166d3f91dc135cc')
        self.assertEqual(hashlib.sha256((ROOT / m.RUNNER).read_bytes()).hexdigest(), m.RUNNER_SHA)
        self.assertEqual(hashlib.sha256((ROOT / m.APPROVED).read_bytes()).hexdigest(), m.APPROVED_SHA)
        self.assertFalse(set(m.IDS).intersection(m.IDS26))
        old = self.core.PREFIX + ' '.join([m.SOURCE_SHA, m.MODE, m.OPERATION])
        self.assertEqual(self.core.parse_command(old), m.fixed_command())

    def test_finite_parser_and_closed_unreviewed_pin(self):
        m = self.m
        self.assertEqual(self.core.parse_command(self.body()), m.fixed_command26())
        for bad in (self.body() + ' 27', self.body().replace('-20261122-v1', '-20261122-v2'),
                    self.body().replace(m.SOURCE_SHA26, 'c' * 40), self.body().replace(m.OPERATION26, m.OPERATION)):
            with self.assertRaises(ValueError): self.core.parse_command(bad)
        for attempt in ('2', '0', ''):
            with mock.patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': attempt}), self.assertRaises(ValueError):
                self.core.parse_command(self.body())
        with mock.patch.object(m, 'SOURCE_SHA26', '0' * 40), self.assertRaises(ValueError):
            self.core.parse_command(self.body())
        with mock.patch.object(m, 'RUNNER_SHA26', '0' * 64), self.assertRaises(ValueError): m.pins26()

    def test_fresh_owner_main_release_gate_and_supplier_exclusion(self):
        m, c = self.m, self.core
        body = self.body()
        event = {'issue': {'number': 4709}, 'comment': {'id': 101, 'body': body,
                 'user': {'id': 226193297}, 'author_association': 'OWNER'}}
        paths = []
        def api(path, token):
            paths.append(path)
            if path == '/issues/comments/101': return {'body': body, 'user': {'id': 226193297}}
            return {'object': {'sha': CONTROL if path == '/git/ref/heads/main' else m.SOURCE_SHA26}}
        with mock.patch.object(c, 'api_get', side_effect=api):
            self.assertEqual(c.checked_event('fixture', event, CONTROL), m.fixed_command26())
            self.assertIn('/git/ref/heads/release/search3-production-ready-v1', paths)
            with self.assertRaisesRegex(ValueError, 'main_changed'): c.checked_event('fixture', event, 'd' * 40)
            with self.assertRaises(ValueError): c.checked_event('fixture', event | {'issue': {'number': 4217}}, CONTROL)
            with self.assertRaises(ValueError):
                c.checked_event('fixture', event | {'comment': event['comment'] | {'user': {'id': 1}}}, CONTROL)
        self.assertNotIn(m.MODE26, self.wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(m.MODE26, self.wrapper.DIRECT_ANEX_MODES)

    def fixture(self, temporary):
        m = self.m
        directory = Path(temporary); account = directory / 'account'; project = account / 'www/anytoour.ru'
        (project / 'data').mkdir(parents=True)
        (project / '_preview/search3-anex-candidate').mkdir(parents=True)
        (project / 'index.php').write_text('public')
        (project / 'config.php').write_text('<?php /* fixture */')
        (project / 'data/db-v1.php').write_bytes(b'wrong helper')
        source = directory / 'source'
        for name, raw in self.fixture_sources.items():
            path = source / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(raw)
        raw, pins = m.bundle_source26(source)
        archive = directory / 'source.tar.gz'; archive.write_bytes(raw)
        self.core.REMOTE = self.stock_remote
        m.activate(self.core, m.fixed_command26())
        program = self.core.REMOTE.replace('home=pathlib.Path.home()', 'home=pathlib.Path(' + repr(str(account)) + ')', 1)
        payload = m.fixed_command26() | {'archive': str(archive), 'manifest_sha256': hashlib.sha256(canonical(pins)).hexdigest(),
                                       'local_official26_control_sha': CONTROL}
        return account, archive, program, payload

    def invoke(self, program, payload):
        encoded = base64.b64encode(zlib.compress(program.encode(), 9)).decode()
        code = 'import base64,zlib;exec(zlib.decompress(base64.b64decode(' + repr(encoded) + ')))'
        run = subprocess.run([sys.executable, '-c', code], input=json.dumps(payload), text=True,
                             capture_output=True, timeout=20)
        self.assertEqual(run.returncode, 0, run.stderr); self.assertEqual(run.stderr, '')
        return json.loads(run.stdout)

    def test_closed_bundle_and_generated_program(self):
        with tempfile.TemporaryDirectory() as temporary:
            _, archive, program, _ = self.fixture(temporary)
            with tarfile.open(archive) as package:
                self.assertEqual(set(package.getnames()), {*self.m.pins26(), 'manifest.json'})
                self.assertTrue(all(x.isfile() and x.mode == 0o600 for x in package.getmembers()))
            ast.parse(program)
            self.assertLess(len(base64.b64encode(zlib.compress(program.encode(), 9))) + 150, 65536)
            self.assertEqual(program.count("if mode not in ('" + self.m.MODE26 + "','reconcile',"), 2)
            self.assertIn("if globals().get('official11_reserved') is True and op.exists():", program)

    def test_prewrite_helper_failure_and_no_replay_keep_original_receipt(self):
        with tempfile.TemporaryDirectory() as temporary:
            account, _, program, payload = self.fixture(temporary)
            first = self.invoke(program, payload)
            self.assertEqual(first['reason'], 'official11_db_helper_drift')
            operation = account / '.anytoour-int-executor' / self.m.OPERATION26
            saved = {p.name: p.read_bytes() for p in (operation / 'reservation.json', operation / 'result.json')}
            second = self.invoke(program, payload)
            self.assertEqual(second['reason'], 'operation_exists_no_replay')
            self.assertEqual(saved, {p.name: p.read_bytes() for p in (operation / 'reservation.json', operation / 'result.json')})
            self.assertFalse((operation / 'official26-apply-started.json').exists())

    def test_control_and_extra_archive_member_reject_before_php(self):
        for mutation in ('control', 'archive'):
            with self.subTest(mutation=mutation), tempfile.TemporaryDirectory() as temporary:
                _, archive, program, payload = self.fixture(temporary)
                if mutation == 'control': payload['local_official26_control_sha'] = 'd' * 40
                else:
                    with tarfile.open(archive) as package: entries = [(x, package.extractfile(x).read()) for x in package.getmembers()]
                    with tarfile.open(archive, 'w:gz') as package:
                        for info, raw in entries: package.addfile(info, io.BytesIO(raw))
                        info = tarfile.TarInfo('extra.php'); info.size = 5; package.addfile(info, io.BytesIO(b'wrong'))
                actual = self.invoke(program, payload)
                self.assertEqual(actual['reason'], 'official11_control' if mutation == 'control' else 'official11_archive_members')

    def test_terminal_76_and_exact_per_field_caps(self):
        m = self.m; good = terminal(m); p = m.policy26(CONTROL)
        self.assertTrue(m.completed(m.fixed_command26(), outer(m, good), CONTROL, p))
        for key, value in [('requested_profiles', 27), ('requested_fields', 78), ('fields_updated', 75),
                           ('database_writes', 51), ('consumer_readback_verified', False), ('supplier_calls', False)]:
            bad = copy.deepcopy(good); bad[key] = value
            self.assertFalse(m.completed(m.fixed_command26(), outer(m, bad), CONTROL, p), key)
        bad = copy.deepcopy(good); bad['field_counts'] = {'description': 25, 'primaryImage': 26, 'images': 25}
        self.assertFalse(m.official11_terminal(bad, p))
        unknown = copy.deepcopy(good)
        unknown.update(state='unknown_no_replay', field_counts=None, readback_verified=False,
                       consumer_readback_verified=False, consumer_sha256=None, owner_receipt_sha256=None,
                       error_code='ANYTOUR_PROFILE_OFFICIAL_COMMIT_UNKNOWN')
        for key in ('profiles_updated', 'fields_updated', 'preserved_fields', 'profile_writes', 'provenance_writes', 'database_writes'):
            unknown[key] = 'unknown'
        self.assertTrue(m.official11_terminal(unknown, p))
        self.assertFalse(m.completed(m.fixed_command26(), outer(m, unknown), CONTROL, p))

    def test_actual_php_helpers_do_not_enter_old_main(self):
        if shutil.which('php') is None: self.skipTest('PHP is required in CI')
        code = '<?php require ' + json.dumps(str(ROOT / self.m.RUNNER26)) + ';' + PHP_FAILURE_FIXTURE
        env = dict(os.environ, OFFICIAL26_TEST_MANIFEST=str(ROOT / self.m.APPROVED26))
        run = subprocess.run(['php'], input=code, env=env, text=True, capture_output=True, timeout=20)
        self.assertEqual(run.returncode, 0, run.stdout + run.stderr)
        self.assertEqual(run.stdout.strip(), 'OFFICIAL26_PHP_FAILURE_GUARDS_OK')


PHP_FAILURE_FIXTURE = r'''
$approved=lo26_envelope((string)getenv('OFFICIAL26_TEST_MANIFEST'));
$identity=['schema_version'=>1,'operation_id'=>LO26_OPERATION,'source_sha'=>str_repeat('b',40),'control_sha'=>str_repeat('a',40),
 'manifest_sha256'=>LO26_MANIFEST_SHA,'actions_sha256'=>LO26_ACTIONS_SHA,'original_package_sha256'=>LO26_ORIGINAL_SHA,'approval_ref'=>LO26_APPROVAL_REF];
$protected=['config.php'=>str_repeat('c',64),'data/db-v1.php'=>LO26_DB_HELPER_SHA];
class Official26FailureFixture {
 public function __construct(private bool $after){}
 public function planOfficial26(array $approved):array {
  if(!$this->after)throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_IDENTITY');
  return ['status'=>'prepared_read_only','writes'=>0,'supplierCalls'=>0,'planSha256'=>str_repeat('d',64),
    'selected'=>array_map(static fn(int $id):array=>['anytourHotelId'=>$id],LO26_IDS)];
 }
 public function applyOfficial26(string $op,array $approved,string $sha):array {throw new RuntimeException('ANYTOUR_PROFILE_OFFICIAL_COMMIT_UNKNOWN');}
}
foreach([false,true] as $after){
 $saved=0;$started=0;
 $receipt=lo26_execute_owner(new Official26FailureFixture($after),$approved,$identity,$protected,
  static function(array $plan)use(&$saved){++$saved;},static function(string $sha)use(&$started){++$started;},static fn():array=>$protected);
 if($receipt['state']!==($after?'unknown_no_replay':'held_before_write')||$saved!==($after?1:0)||$started!==($after?1:0)
   ||$receipt['database_writes']!==($after?'unknown':0))throw new RuntimeException('failure_semantics');
}
echo "OFFICIAL26_PHP_FAILURE_GUARDS_OK\n";
'''


NATIVE_PHP = r'''
<?php
require getenv('OFFICIAL26_TEST_RUNNER');
require getenv('LOCAL_OFFICIAL26_SOURCE_ROOT').'/v2/data/anytour-profile-enrichment-v1.php';
$dsn=(string)getenv('ANYTOUR_PROFILE_ENRICH_TEST_DSN');
if(!str_contains($dsn,'dbname=anytour_official11_fixture;'))throw new RuntimeException('disposable_database_required');
$pdo=new PDO($dsn,'root',(string)getenv('ANYTOUR_PROFILE_ENRICH_TEST_PASSWORD'),[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$sql=file_get_contents(getenv('LOCAL_OFFICIAL26_SOURCE_ROOT').'/v2/data/migrations/20260916-anytour-canonical-catalog.sql');
foreach(explode(';',preg_replace('/--[^\n]*/','',$sql)) as $part)if(trim($part)!=='')$pdo->exec($part);
$approved=lo26_envelope(getenv('OFFICIAL26_TEST_MANIFEST'));
$manifest=json_decode($approved['manifestJson'],true,128,JSON_THROW_ON_ERROR);
$raw=file_get_contents(getenv('OFFICIAL26_TEST_BEFORE'));
if(hash('sha256',$raw)!=='487c0abc28ded737fb9d1ebc7aedecbd4101e183c6847d1c739a6235d5f9d21e')throw new RuntimeException('before_fixture_drift');
$before=json_decode($raw,true,128,JSON_THROW_ON_ERROR);$original=[];
foreach($before['items'] as $row)$original[$row['id']]=$row;
$identity=['schema_version'=>1,'operation_id'=>LO26_OPERATION,'source_sha'=>getenv('OFFICIAL26_TEST_SOURCE_SHA'),
 'control_sha'=>str_repeat('a',40),'manifest_sha256'=>LO26_MANIFEST_SHA,'actions_sha256'=>LO26_ACTIONS_SHA,
 'original_package_sha256'=>LO26_ORIGINAL_SHA,'approval_ref'=>LO26_APPROVAL_REF];
$protected=['config.php'=>str_repeat('c',64),'data/db-v1.php'=>LO26_DB_HELPER_SHA];
foreach(['exact','stale_public','hilton_gallery_drift'] as $case){
 $pdo->exec('DELETE FROM anytour_hotel_sources');$pdo->exec('DELETE FROM anytour_hotels');
 $insert=$pdo->prepare('INSERT INTO anytour_hotels(id,profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
 foreach($original as $id=>$profile){
  unset($profile['id'],$profile['catalog'],$profile['revision']);
  if($case==='stale_public'&&$id===4511)$profile['description']='Сохранённое новое описание';
  if($case==='hilton_gallery_drift'&&$id===4172)$profile['images']=array_reverse($profile['images']);
  $json=lo11_json($profile);$insert->execute([$id,$json,hash('sha256',$json)]);
 }
 $saved=0;$started=0;$owner=new AnyTourProfileEnrichmentV1($pdo);
 $receipt=lo26_execute_owner($owner,$approved,$identity,$protected,
   static function(array $plan)use(&$saved){foreach($plan['selected'] as $row)if(!is_string($row['beforeProfileJson']??null))throw new RuntimeException('private_snapshot');++$saved;},
   static function(string $sha)use(&$started){++$started;},static fn():array=>$protected);
 if($case!=='exact'){
  if($receipt['state']!=='held_before_write'||$receipt['database_writes']!==0||$started!==0
    ||(int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn()!==0)throw new RuntimeException('drift_not_held:'.lo11_json($receipt));
  echo 'OFFICIAL26_NATIVE_HELD='.$case."\n";continue;
 }
 if($receipt['state']!=='committed_verified'||$receipt['profiles_updated']!==26||$receipt['fields_updated']!==76
   ||$receipt['preserved_fields']!==0||lo11_json($receipt['field_counts'])!==lo11_json(LO26_FIELD_CAPS)||$saved!==1||$started!==1)throw new RuntimeException('native_receipt:'.lo11_json($receipt));
 if((int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_sources')->fetchColumn()!==26)throw new RuntimeException('provenance_count');
 $actual=(new AnyTourCanonicalCatalog($pdo))->read(LO26_IDS);$byId=[];
 foreach($actual['items'] as $row)$byId[$row['id']]=$row;
 foreach($manifest['proposed_actions'] as $action){
  $id=$action['anytour_hotel_id'];$expected=$original[$id];$expected['revision']=2;
  foreach($action['field_changes'] as $field)$expected[$field['field']]=$field['after'];
  if(lo11_json($byId[$id])!==lo11_json($expected))throw new RuntimeException('native_exact_consumer_profile:'.$id);
 }
 if($byId[4172]['primaryImage']!==$original[4172]['primaryImage']||$byId[4172]['images']!==$original[4172]['images'])throw new RuntimeException('hilton44_mutated');
 echo 'OFFICIAL26_NATIVE_RECEIPT='.base64_encode(lo11_json($receipt))."\n";
}
echo "OFFICIAL26_NATIVE_MYSQL_OK cases=3 profiles=26 fields=76 hilton_gallery=44\n";
'''


class ActualSource26Native(unittest.TestCase):
    def test_exact_reviewed_source_bundle(self):
        source = os.environ.get('LOCAL_OFFICIAL26_SOURCE_ROOT')
        if CONTROL_ONLY or not source:
            if os.environ.get('LOCAL_OFFICIAL26_NATIVE_CI') == '1' and not CONTROL_ONLY: self.fail('SOURCE checkout is required')
            self.skipTest('SOURCE checkout is supplied by CI')
        m = wrapper().local_official11
        raw, pins = m.bundle_source26(source)
        with tarfile.open(fileobj=io.BytesIO(raw)) as archive:
            self.assertEqual(set(archive.getnames()), {*pins, 'manifest.json'})

    def test_native_mysql_actual_owner_and_runner(self):
        if CONTROL_ONLY: self.skipTest('Runtime precheck does not use a database')
        keys = ['LOCAL_OFFICIAL26_SOURCE_ROOT', 'ANYTOUR_PROFILE_ENRICH_TEST_DSN', 'ANYTOUR_PROFILE_ENRICH_TEST_PASSWORD']
        if any(not os.environ.get(k) for k in keys):
            if os.environ.get('LOCAL_OFFICIAL26_NATIVE_CI') == '1': self.fail('Native MySQL configuration required')
            self.skipTest('Native MySQL is required in CI')
        m = wrapper().local_official11
        env = dict(os.environ, OFFICIAL26_TEST_RUNNER=str(ROOT / m.RUNNER26), OFFICIAL26_TEST_MANIFEST=str(ROOT / m.APPROVED26),
                   OFFICIAL26_TEST_BEFORE=str(BEFORE), OFFICIAL26_TEST_SOURCE_SHA=m.SOURCE_SHA26)
        run = subprocess.run(['php'], input=NATIVE_PHP.lstrip(), env=env, text=True, capture_output=True, timeout=120)
        self.assertEqual(run.returncode, 0, run.stdout + run.stderr)
        receipts = [json.loads(base64.b64decode(line.split('=',1)[1])) for line in run.stdout.splitlines() if line.startswith('OFFICIAL26_NATIVE_RECEIPT=')]
        self.assertEqual(len(receipts), 1, run.stdout)
        self.assertTrue(m.completed(m.fixed_command26(), outer(m, receipts[0]), CONTROL, m.policy26(CONTROL)))
        self.assertIn('OFFICIAL26_NATIVE_HELD=stale_public', run.stdout)
        self.assertIn('OFFICIAL26_NATIVE_HELD=hilton_gallery_drift', run.stdout)
        self.assertIn('OFFICIAL26_NATIVE_MYSQL_OK cases=3 profiles=26 fields=76 hilton_gallery=44', run.stdout)
        print('OFFICIAL26_NATIVE_MYSQL_OK cases=3 profiles=26 fields=76 hilton_gallery=44')


if __name__ == '__main__':
    unittest.main()
