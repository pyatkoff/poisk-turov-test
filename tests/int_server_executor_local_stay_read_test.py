from __future__ import annotations

import ast
import copy
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tarfile
import tempfile
import unittest
from unittest import mock
import zlib

ROOT = Path(__file__).resolve().parents[1]
SOURCE = Path(os.environ.get('LOCAL_STAY_SOURCE_ROOT', ROOT / 'local-stay-source'))
CONTROL = 'b' * 40
spec = importlib.util.spec_from_file_location('local_stay_control', ROOT / 'scripts/deploy/int_server_executor_local_stay_read.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


def wrapper():
    spec = importlib.util.spec_from_file_location('local_stay_wrapper', ROOT / 'scripts/deploy/int_server_executor_anex_secret_transport.py')
    w = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(w)
    return w


def command(c):
    return c.parse_command(f'{c.PREFIX}{m.SOURCE_SHA} {m.MODE} {m.OPERATION}')


def missing_data():
    return {'receipt': {
        'schemaVersion': 1, 'source': 'anytour-hotel-stay-anchor-read-v1', 'state': 'schema_missing',
        'tourvisorTourId': m.TOUR, 'supplierCalls': 0, 'databaseWrites': 0, 'automaticAccepts': 0,
        'anchorRetainedRows': 0, 'anchorExpiredRows': 0, 'currentValidatedOffers': 0,
        'returnedCohorts': 0, 'reviewItems': 0, 'missingTables': ['anytour_hotel_stay_mappings_v2'],
        'inventorySha256': None, 'reviewPacketSha256': None, 'operationId': m.OPERATION,
        'sourceSha': m.SOURCE_SHA, 'controlSha': CONTROL,
    }, 'inventory': None, 'reviewPacket': None}


def receipt(data):
    raw = (json.dumps(data, ensure_ascii=False, sort_keys=True, separators=(',', ':')) + '\n').encode()
    public = dict.fromkeys(('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'), 'a' * 64)
    protected = dict.fromkeys(('config.php','data/db-v1.php'), 'c' * 64)
    return {'schema_version': 1, 'mode': m.MODE, 'operation_id': m.OPERATION, 'source_sha': m.SOURCE_SHA,
            'status': 'complete', 'supplier_calls': 0, 'database_writes': 0, 'booking_calls': 0,
            'lead_calls': 0, 'profile_writes': 0, 'mapping_writes': 0, 'schema_writes': 0,
            'production_before': public, 'production_after': dict(public), 'production_unchanged': True,
            'local_stay_read': {'data': data, 'fileSha256': hashlib.sha256(raw).hexdigest(),
                                'protectedBefore': protected, 'protectedAfter': dict(protected)}}


class Admission(unittest.TestCase):
    def test_proved_anchor_with_no_current_cohorts_is_distinct_from_missing_anchor(self):
        # Explicit fictional data tests the controller's successful proof branch.
        # It is not a real hotel mapping or a source/current database observation.
        digest = lambda x: hashlib.sha256(json.dumps(x, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
        anchor = {'hotelId': 11, 'legacyHotelId': 101, 'provider': 'tourvisor',
                  'providerHotelRefDigest': hashlib.sha256(b'tourvisor:hotel:101').hexdigest(),
                  'offerRefDigest': hashlib.sha256(('tourvisor:tour:' + m.TOUR).encode()).hexdigest(),
                  'retainedRows': 1, 'expiredRows': 1, 'identityOnly': True, 'profileSha256': 'e' * 64,
                  'retainedFacts': [{'payloadSha256': 'f' * 64, 'operatorRaw': 'FICTIONAL OPERATOR',
                                     'roomRaw': 'FICTIONAL ROOM', 'mealRaw': 'AI',
                                     'lastSeenAt': '2026-10-20 12:00:00', 'expiresAt': '2026-10-21 12:00:00',
                                     'expired': True, 'active': False}]}
        inventory = {'status': 'read_only_current_offer_review_inventory', 'source': 'anytour-current-complete-offer-snapshots',
                     'generatedAt': '2026-10-25T17:00:00Z', 'currentIdentityValidatedOffers': 0, 'totalExactCohorts': 0,
                     'returnedCohorts': 0, 'omittedMissingOperatorOffers': 0, 'omittedMissingStayFactOffers': 0,
                     'invalidPayloads': 0, 'reviewNeededCohorts': 0, 'heldCohorts': 0, 'existingNegativeCohorts': 0,
                     'statusCounts': {}, 'cohorts': [], 'hotelConcepts': [{'hotelId': 11, 'rooms': [], 'meals': []}],
                     'writes': 0, 'supplierCalls': 0, 'automaticAccepts': 0, 'anchorEvidence': anchor}
        packet = {'schemaVersion': 1, 'source': 'anytour-hotel-stay-review-packet-v2', 'inventorySource': inventory['source'],
                  'inventoryGeneratedAt': inventory['generatedAt'], 'batchSize': 100, 'actionableItemCount': 0,
                  'blockedItemCount': 0, 'batches': [], 'blocked': [], 'rankedChoices': False, 'automaticDecisions': 0}
        packet['packetSha256'] = digest(packet)
        data = missing_data()
        data.update(inventory=inventory, reviewPacket=packet)
        data['receipt'].update(state='no_current_cohorts', missingTables=[], anchorRetainedRows=1, anchorExpiredRows=1,
                               inventorySha256=digest(inventory), reviewPacketSha256=digest(packet))
        parsed = command(wrapper().core)
        self.assertTrue(m.completed(parsed, receipt(data), CONTROL))
        data['receipt'].update(anchorRetainedRows=0, anchorExpiredRows=0)
        anchor.update(retainedRows=0, expiredRows=0, retainedFacts=[])
        data['receipt']['inventorySha256'] = digest(inventory)
        self.assertFalse(m.completed(parsed, receipt(data), CONTROL))

    def test_fixed_scope_and_old_parser_and_first_attempt(self):
        c = wrapper().core
        with mock.patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '1'}):
            parsed = command(c)
            self.assertEqual(parsed['maximum_cohorts'], 50)
            self.assertIs(type(parsed['provider_http_calls']), int)
            for invalid in (f'{c.PREFIX}{"a"*40} {m.MODE} {m.OPERATION}',
                            f'{c.PREFIX}{m.SOURCE_SHA} {m.MODE} {m.OPERATION}-v2',
                            f'{c.PREFIX}{m.SOURCE_SHA} {m.MODE} {m.OPERATION} 534',
                            f'{c.PREFIX}{m.SOURCE_SHA} {m.MODE} {m.OPERATION} --replay'):
                with self.subTest(body=invalid), self.assertRaises(ValueError):
                    c.parse_command(invalid)
            old = c.parse_command(f'{c.PREFIX}{"a"*40} local-tv-content-v1 int-andromeda-local-tv-content-fill-20261010-v1')
            self.assertEqual(old['provider_http_calls'], 200)
        with mock.patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '2'}), self.assertRaises(ValueError):
            command(c)

    def test_owner_main_release_guards_remain_actual(self):
        c = wrapper().core
        body = f'{c.PREFIX}{m.SOURCE_SHA} {m.MODE} {m.OPERATION}'
        event = {'issue': {'number': 4709}, 'comment': {'id': 101, 'user': {'id': 226193297},
                 'author_association': 'OWNER', 'body': body}}
        def api(path, token):
            if path == '/issues/comments/101': return {'body': body, 'user': {'id': 226193297}}
            if path == '/git/ref/heads/main': return {'object': {'sha': CONTROL}}
            if path == '/git/ref/heads/release/search3-production-ready-v1': return {'object': {'sha': m.SOURCE_SHA}}
            raise AssertionError(path)
        with mock.patch.object(c, 'api_get', side_effect=api):
            self.assertEqual(c.checked_event('fixture', event, CONTROL), command(c))
            for invalid in (dict(event, issue={'number': 4217}), dict(event, issue={'number': 4709, 'pull_request': {'url': 'fixture'}})):
                with self.assertRaises(ValueError): c.checked_event('fixture', invalid, CONTROL)
            with self.assertRaisesRegex(ValueError, 'main_changed'): c.checked_event('fixture', event, 'c' * 40)

    def test_exact_bundle_and_changed_source_are_rejected(self):
        raw, hashes = m.bundle_source(SOURCE)
        self.assertEqual(hashes, m.SOURCE_HASHES)
        with tarfile.open(fileobj=io.BytesIO(raw), mode='r:gz') as archive:
            self.assertEqual(set(archive.getnames()), {*m.BUNDLE_FILES, 'manifest.json'})
            self.assertTrue(all(x.isfile() and x.mode == 0o600 for x in archive.getmembers()))
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            for relative in m.BUNDLE_FILES:
                path = directory / relative
                path.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(SOURCE / relative, path)
            (directory / m.BUNDLE_FILES[0]).write_text('unreviewed')
            with self.assertRaisesRegex(ValueError, 'reviewed_source_changed'): m.bundle_source(directory)

    def test_mode_activation_keeps_stock_no_replay_and_rejects_extra_authority(self):
        c = wrapper().core
        parsed = command(c)
        with mock.patch.dict(os.environ, {'GITHUB_SHA': CONTROL, 'GITHUB_RUN_ATTEMPT': '1'}):
            for key, value in (('provider_http_calls', False), ('database_writes', 1), ('maximum_cohorts', 51), ('apply', True)):
                with self.subTest(key=key), self.assertRaises(ValueError): m.activate(c, parsed | {key: value})
            m.activate(c, parsed)
        ast.parse(c.REMOTE)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')", c.REMOTE)
        self.assertIn("if globals().get('local_stay_reserved') is True and op.exists():", c.REMOTE)
        self.assertIn('disable_functions=', c.REMOTE)
        self.assertNotIn('StrictHostKeyChecking=no', c.REMOTE)
        encoded = __import__('base64').b64encode(zlib.compress(c.REMOTE.encode(), 9)).decode()
        self.assertLess(len(encoded) + 150, 65536)

    def test_strict_terminal_preserves_typed_zero_counts_pins_and_missing_fact(self):
        c = wrapper().core
        parsed = command(c)
        data = missing_data()
        valid = receipt(data)
        self.assertTrue(m.completed(parsed, valid, CONTROL))
        for section, key, value in ((None, 'supplier_calls', False), (None, 'database_writes', 1),
                                    (None, 'source_sha', 'a' * 40), ('receipt', 'databaseWrites', False),
                                    ('receipt', 'missingTables', ['private_secrets']), ('receipt', 'controlSha', 'c' * 40)):
            changed = copy.deepcopy(valid)
            target = changed if section is None else changed['local_stay_read']['data'][section]
            target[key] = value
            self.assertFalse(m.completed(parsed, changed, CONTROL), (section, key))
        changed = copy.deepcopy(valid)
        changed['local_stay_read']['fileSha256'] = '0' * 64
        self.assertFalse(m.completed(parsed, changed, CONTROL))
        changed = copy.deepcopy(valid)
        changed['local_stay_read']['data']['credentials'] = 'forbidden'
        self.assertFalse(m.completed(parsed, changed, CONTROL))
        changed = copy.deepcopy(valid)
        changed['local_stay_read']['protectedAfter']['config.php'] = 'f' * 64
        self.assertFalse(m.completed(parsed, changed, CONTROL))
        for invalid in (['config.php','data/db-v1.php'], {'config.php':0,'data/db-v1.php':0}):
            changed = copy.deepcopy(valid)
            changed['local_stay_read']['protectedBefore'] = changed['local_stay_read']['protectedAfter'] = invalid
            self.assertFalse(m.completed(parsed, changed, CONTROL))
        changed = copy.deepcopy(valid)
        changed['production_before']['v2/index.php'] = changed['production_after']['v2/index.php'] = None
        self.assertTrue(m.completed(parsed, changed, CONTROL))
        changed['production_before']['index.php'] = changed['production_after']['index.php'] = 7
        self.assertFalse(m.completed(parsed, changed, CONTROL))


def schema_command(c):
    return c.parse_command(f'{c.PREFIX}{m.SCHEMA_SOURCE_SHA} {m.SCHEMA_MODE} {m.SCHEMA_OPERATION}')


def schema_missing_data():
    tables = ['anytour_hotels','anytour_hotel_sources','anytour_hotel_room_concepts_v2',
              'anytour_hotel_meal_concepts_v2','anytour_hotel_stay_mappings_v2']
    inventory = {'server': {'family': 'mysql', 'version': '8.0.36', 'checkEnforcement': 'supported', 'triggerVisibilityProven': True},
                 'tables': [{'name':name,'role':'parent' if i<2 else 'target','exists':False,
                             'tableType':None,'engine':None,'collation':None,'createSha256':None,
                             'partitioned':False,'triggerCount':0,'columns':[],'indexes':[],'foreignKeys':[],'checks':[],
                             'compatibility':'absent','issues':['absent']} for i,name in enumerate(tables)]}
    plan = {'state':'blocked_parent_prerequisites','createTables':tables[2:], 'existingCompatibleTables':[],
            'blockedTables':tables[:2],'requiresExplicitReview':True,'applyAuthorized':False,
            'migrationSha256':m.SCHEMA_MIGRATION_SHA,'noDdlExecuted':True}
    digest = lambda x: hashlib.sha256(json.dumps(x,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    return {'receipt': {'schemaVersion':1,'source':'anytour-hotel-stay-schema-read-v1',
                        'state':'completed_read_only','supplierCalls':0,'databaseWrites':0,'schemaWrites':0,
                        'automaticAccepts':0,'migrationSha256':m.SCHEMA_MIGRATION_SHA,
                        'inventorySha256':digest(inventory),'planSha256':digest(plan),
                        'operationId':m.SCHEMA_OPERATION,'sourceSha':m.SCHEMA_SOURCE_SHA,'controlSha':CONTROL},
            'inventory':inventory,'plan':plan}


def schema_receipt(data):
    result = receipt(data)
    result.update(mode=m.SCHEMA_MODE,operation_id=m.SCHEMA_OPERATION,source_sha=m.SCHEMA_SOURCE_SHA)
    result['local_stay_schema_read'] = result.pop('local_stay_read')
    return result


class SchemaAdmission(unittest.TestCase):
    def test_fixed_read_scope_and_attempt_and_old_profile_are_preserved(self):
        c = wrapper().core
        with mock.patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '1'}):
            parsed = schema_command(c)
            self.assertEqual(parsed, {'source_sha': m.SCHEMA_SOURCE_SHA, 'mode': m.SCHEMA_MODE,
                                     'operation_id': m.SCHEMA_OPERATION, 'maximum_schema_tables': 5,
                                     'provider_http_calls': 0, 'database_writes': 0})
            self.assertEqual(command(c), {'source_sha': m.SOURCE_SHA, 'mode': m.MODE,
                                        'operation_id': m.OPERATION, 'maximum_cohorts': 50,
                                        'provider_http_calls': 0, 'database_writes': 0})
            for body in (f'{c.PREFIX}{m.SOURCE_SHA} {m.SCHEMA_MODE} {m.SCHEMA_OPERATION}',
                         f'{c.PREFIX}{m.SCHEMA_SOURCE_SHA} {m.SCHEMA_MODE} {m.SCHEMA_OPERATION}-v2',
                         f'{c.PREFIX}{m.SCHEMA_SOURCE_SHA} {m.SCHEMA_MODE} {m.SCHEMA_OPERATION} --apply'):
                with self.subTest(body=body), self.assertRaises(ValueError): c.parse_command(body)
        with mock.patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '2'}), self.assertRaises(ValueError): schema_command(c)

    def test_owner_main_and_fresh_release_are_still_checked(self):
        c = wrapper().core
        body = f'{c.PREFIX}{m.SCHEMA_SOURCE_SHA} {m.SCHEMA_MODE} {m.SCHEMA_OPERATION}'
        event = {'issue': {'number': 4709}, 'comment': {'id': 102, 'user': {'id': 226193297},
                 'author_association': 'OWNER', 'body': body}}
        def api(path, token):
            if path == '/issues/comments/102': return {'body': body, 'user': {'id': 226193297}}
            if path == '/git/ref/heads/main': return {'object': {'sha': CONTROL}}
            if path == '/git/ref/heads/release/search3-production-ready-v1': return {'object': {'sha': m.SCHEMA_SOURCE_SHA}}
            raise AssertionError(path)
        with mock.patch.object(c, 'api_get', side_effect=api):
            self.assertEqual(c.checked_event('fixture', event, CONTROL), schema_command(c))
            for invalid in (dict(event, issue={'number': 4217}),
                            dict(event, comment=event['comment'] | {'author_association': 'COLLABORATOR'}),
                            dict(event, comment=event['comment'] | {'user': {'id': 2}})):
                with self.assertRaises(ValueError): c.checked_event('fixture', invalid, CONTROL)
            with self.assertRaisesRegex(ValueError, 'main_changed'): c.checked_event('fixture', event, 'c' * 40)

    def test_closed_split_bundle_and_pinned_migration(self):
        raw, hashes = m.bundle_schema_source(SOURCE)
        self.assertEqual(hashes, m.schema_hashes())
        self.assertEqual(len(hashes), 3)
        with tarfile.open(fileobj=io.BytesIO(raw), mode='r:gz') as archive:
            self.assertEqual(set(archive.getnames()), {*hashes, 'manifest.json'})
            self.assertEqual(len(archive.getmembers()), 4)
            for info in archive.getmembers():
                self.assertTrue(info.isfile())
                self.assertEqual(info.mode, 0o600)
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            for relative in m.SCHEMA_SOURCE_HASHES:
                path = directory / relative
                path.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(SOURCE / relative, path)
            (directory / m.SCHEMA_SQL).write_text('unreviewed migration')
            with self.assertRaisesRegex(ValueError, 'reviewed_source_changed'): m.bundle_schema_source(directory)

    def test_exact_schema_activation_preserves_reservation_and_rejects_extra_authority(self):
        c = wrapper().core
        parsed = schema_command(c)
        with mock.patch.dict(os.environ, {'GITHUB_SHA': CONTROL, 'GITHUB_RUN_ATTEMPT': '1'}):
            for key, value in (('provider_http_calls', False), ('database_writes', 1),
                               ('maximum_schema_tables', 6), ('apply', True)):
                with self.subTest(key=key), self.assertRaises(ValueError): m.activate(c, parsed | {key: value})
            m.activate(c, parsed)
        ast.parse(c.REMOTE)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')", c.REMOTE)
        self.assertIn("if globals().get('local_stay_schema_reserved') is True and op.exists():", c.REMOTE)
        self.assertIn("if len(members)!=4", c.REMOTE)
        self.assertIn('disable_functions=', c.REMOTE)
        encoded = __import__('base64').b64encode(zlib.compress(c.REMOTE.encode(), 9)).decode()
        self.assertLess(len(encoded) + 150, 65536)


    def test_strict_metadata_and_no_apply_receipt(self):
        parsed = schema_command(wrapper().core)
        data = schema_missing_data()
        self.assertTrue(m.completed_schema(parsed,schema_receipt(data),CONTROL))
        for section,key,value in (('receipt','schemaWrites',False),('receipt','migrationSha256','0'*64),
                                  ('receipt','controlSha','c'*40),('plan','applyAuthorized',True),
                                  ('plan','blockedTables',[]),('plan','state','create_only_after_review')):
            changed = copy.deepcopy(data)
            changed[section][key] = value
            for field in ('inventory','plan'):
                changed['receipt'][field+'Sha256'] = hashlib.sha256(json.dumps(changed[field],ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
            self.assertFalse(m.completed_schema(parsed,schema_receipt(changed),CONTROL),(section,key))
        changed = copy.deepcopy(data)
        changed['inventory']['tables'][0]['createSql'] = 'forbidden raw SQL'
        changed['receipt']['inventorySha256'] = hashlib.sha256(json.dumps(changed['inventory'],ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        self.assertFalse(m.completed_schema(parsed,schema_receipt(changed),CONTROL))
        for state in ('metadata_limit','schema_changed'):
            diagnostic = copy.deepcopy(data)
            diagnostic.update(inventory=None,plan=None)
            diagnostic['receipt'].update(state=state,inventorySha256=None,planSha256=None)
            self.assertTrue(m.completed_schema(parsed,schema_receipt(diagnostic),CONTROL))
        diagnostic['receipt']['state'] = 'read_failed'
        self.assertFalse(m.completed_schema(parsed,schema_receipt(diagnostic),CONTROL))
        changed = schema_receipt(data)
        changed['local_stay_schema_read']['protectedAfter']['config.php'] = 'f'*64
        self.assertFalse(m.completed_schema(parsed,changed,CONTROL))
        changed = schema_receipt(data)
        changed['database_writes'] = False
        self.assertFalse(m.completed_schema(parsed,changed,CONTROL))
        hidden = copy.deepcopy(data)
        hidden['inventory']['server']['triggerVisibilityProven'] = False
        hidden['plan']['state'] = 'blocked_server_capability'
        for field in ('inventory','plan'):
            hidden['receipt'][field+'Sha256'] = hashlib.sha256(json.dumps(hidden[field],ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        self.assertTrue(m.completed_schema(parsed,schema_receipt(hidden),CONTROL))
        hidden['plan']['state'] = 'create_only_after_review'
        hidden['receipt']['planSha256'] = hashlib.sha256(json.dumps(hidden['plan'],ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        self.assertFalse(m.completed_schema(parsed,schema_receipt(hidden),CONTROL))


class NativeStockRead(unittest.TestCase):
    def setUp(self):
        self.assertIsNotNone(shutil.which('php'), 'Actual PHP is required; no skip or substitute')
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.home = Path(self.temp.name)
        self.project = self.home / 'www/anytoour.ru'
        (self.project / 'data').mkdir(parents=True)
        (self.project / '_preview/search3-anex-candidate').mkdir(parents=True)
        for name in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'):
            path = self.project / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('fixture protected ' + name)
        shutil.copyfile(SOURCE / 'v2/data/db-v1.php', self.project / 'data/db-v1.php')
        # An empty disposable schema proves the actual PHP -> MySQL -> typed terminal
        # boundary without duplicating the source packet's full native fixtures.
        self.database = 'anytour_stay_anchor_control_fixture'
        server = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4'
        password = os.environ.get('LOCAL_STAY_TEST_PASSWORD', 'local_tv_schema_test_only')
        init = subprocess.run(['php', '-r', '$p=new PDO($argv[1],"root",$argv[2]);$p->exec("DROP DATABASE IF EXISTS anytour_stay_anchor_control_fixture");$p->exec("CREATE DATABASE anytour_stay_anchor_control_fixture");', server, password], capture_output=True, text=True)
        self.assertEqual(init.returncode, 0, init.stderr)
        dsn = 'mysql:host=127.0.0.1;port=3306;dbname=' + self.database + ';charset=utf8mb4'
        config = '<?php\ndefine("ANYTOUR_DATA_DSN",' + json.dumps(dsn) + ');\ndefine("ANYTOUR_DATA_DB_USER","root");\ndefine("ANYTOUR_DATA_DB_PASSWORD",' + json.dumps(password) + ');\n'
        (self.project / 'config.php').write_text(config)
        self.c = wrapper().core
        with mock.patch.dict(os.environ, {'GITHUB_SHA': CONTROL, 'GITHUB_RUN_ATTEMPT': '1'}):
            self.command = command(self.c)
            m.activate(self.c, self.command)
        archive, hashes = m.bundle_source(SOURCE)
        self.archive = self.home / 'source.tar.gz'
        self.archive.write_bytes(archive)
        digest = hashlib.sha256(json.dumps(hashes, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
        self.payload = self.command | {'archive': str(self.archive), 'manifest_sha256': digest}

    def run_remote(self):
        env = dict(os.environ, HOME=str(self.home))
        encoded = __import__('base64').b64encode(zlib.compress(self.c.REMOTE.encode(), 9)).decode()
        loader = 'import base64,zlib;exec(zlib.decompress(base64.b64decode(' + repr(encoded) + ')))'
        run = subprocess.run(['python3', '-c', loader], input=json.dumps(self.payload),
                             text=True, capture_output=True, env=env, timeout=60)
        self.assertEqual(run.returncode, 0, run.stderr)
        return json.loads(run.stdout)

    def test_actual_read_missing_schema_and_repeat_preserves_original_terminal(self):
        result = self.run_remote()
        self.assertTrue(m.completed(self.command, result, CONTROL), result)
        data = result['local_stay_read']['data']
        self.assertEqual(data['receipt']['state'], 'schema_missing')
        self.assertEqual(len(data['receipt']['missingTables']), 7)
        self.assertIsNone(data['inventory'])
        operation = self.home / '.anytoour-int-executor' / m.OPERATION
        original = (operation / 'result.json').read_bytes()
        repeated = self.run_remote()
        self.assertEqual(repeated.get('reason'), 'operation_exists_no_replay')
        self.assertFalse(m.completed(self.command, repeated, CONTROL))
        self.assertEqual((operation / 'result.json').read_bytes(), original)

    def test_actual_remote_rejects_boolean_cap_before_php_or_db(self):
        self.payload['provider_http_calls'] = False
        result = self.run_remote()
        self.assertFalse(m.completed(self.command, result, CONTROL))
        self.assertEqual(result.get('reason'), 'local_stay_payload_types')
        operation = self.home / '.anytoour-int-executor' / m.OPERATION
        self.assertFalse((operation / 'local-stay-anchor-read.json').exists())

    def test_actual_remote_rejects_unmanifested_config_before_php_or_db(self):
        # An extra staged config could redirect the DB helper despite six valid hashes.
        with tarfile.open(self.archive, 'r:gz') as old:
            members = [(x, old.extractfile(x).read()) for x in old.getmembers()]
        with tarfile.open(self.archive, 'w:gz') as altered:
            for info, raw in members:
                altered.addfile(info, io.BytesIO(raw))
            extra = b'<?php throw new RuntimeException("unmanifested configuration");'
            info = tarfile.TarInfo('v2/config.php'); info.size = len(extra)
            altered.addfile(info, io.BytesIO(extra))
        result = self.run_remote()
        self.assertFalse(m.completed(self.command, result, CONTROL))
        self.assertEqual(result.get('reason'), 'local_stay_archive_scope')
        operation = self.home / '.anytoour-int-executor' / m.OPERATION
        self.assertFalse((operation / 'local-stay-anchor-read.json').exists())

class NativeStockSchemaRead(unittest.TestCase):
    def setUp(self):
        self.assertIsNotNone(shutil.which('php'),'Actual PHP is required; no skip or substitute')
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.home = Path(self.temp.name)
        self.project = self.home / 'www/anytoour.ru'
        (self.project / 'data').mkdir(parents=True)
        (self.project / '_preview/search3-anex-candidate').mkdir(parents=True)
        for name in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'):
            path = self.project / name
            path.parent.mkdir(parents=True,exist_ok=True)
            path.write_text('fixture protected '+name)
        shutil.copyfile(SOURCE / 'v2/data/db-v1.php',self.project / 'data/db-v1.php')
        self.database = 'anytour_stay_schema_control_fixture'
        self.server = 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4'
        self.password = os.environ.get('LOCAL_STAY_TEST_PASSWORD','local_tv_schema_test_only')
        init = subprocess.run(['php','-r','$p=new PDO($argv[1],"root",$argv[2]);$p->exec("DROP DATABASE IF EXISTS anytour_stay_schema_control_fixture");$p->exec("CREATE DATABASE anytour_stay_schema_control_fixture");',
                               self.server,self.password],capture_output=True,text=True)
        self.assertEqual(init.returncode,0,init.stderr)
        self.dsn = 'mysql:host=127.0.0.1;port=3306;dbname='+self.database+';charset=utf8mb4'
        config = '<?php\ndefine("ANYTOUR_DATA_DSN",'+json.dumps(self.dsn)+');\ndefine("ANYTOUR_DATA_DB_USER","root");\ndefine("ANYTOUR_DATA_DB_PASSWORD",'+json.dumps(self.password)+');\n'
        (self.project / 'config.php').write_text(config)
        self.c = wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):
            self.command = schema_command(self.c)
            m.activate(self.c,self.command)
        archive,hashes = m.bundle_schema_source(SOURCE)
        self.archive = self.home / 'schema-source.tar.gz'
        self.archive.write_bytes(archive)
        digest = hashlib.sha256(json.dumps(hashes,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        self.payload = self.command | {'archive':str(self.archive),'manifest_sha256':digest}

    run_remote = NativeStockRead.run_remote

    def test_actual_metadata_read_and_repeat_preserves_original_terminal(self):
        result = self.run_remote()
        self.assertTrue(m.completed_schema(self.command,result,CONTROL),result)
        data = result['local_stay_schema_read']['data']
        self.assertEqual(data['receipt']['state'],'completed_read_only')
        self.assertEqual(data['plan']['state'],'blocked_parent_prerequisites')
        self.assertTrue(all(t['exists'] is False for t in data['inventory']['tables']))
        self.assertFalse(data['plan']['applyAuthorized'])
        operation = self.home / '.anytoour-int-executor' / m.SCHEMA_OPERATION
        original = (operation / 'result.json').read_bytes()
        repeated = self.run_remote()
        self.assertEqual(repeated.get('reason'),'operation_exists_no_replay')
        self.assertFalse(m.completed_schema(self.command,repeated,CONTROL))
        self.assertEqual((operation / 'result.json').read_bytes(),original)

    def test_actual_existing_migration_is_validated_without_schema_writes(self):
        program = r"""$p=new PDO($argv[1],"root",$argv[2],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);foreach(array_slice($argv,3) as $path){$sql=preg_replace('/^\s*--.*$/m','',file_get_contents($path));foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){if(trim($statement)!=='')$p->exec(trim($statement));}}"""
        run = subprocess.run(['php','-r',program,self.dsn,self.password,
                              str(SOURCE / 'v2/data/migrations/20260916-anytour-canonical-catalog.sql'),
                              str(SOURCE / m.SCHEMA_SQL)],capture_output=True,text=True)
        self.assertEqual(run.returncode,0,run.stderr)
        result = self.run_remote()
        self.assertTrue(m.completed_schema(self.command,result,CONTROL),result)
        data = result['local_stay_schema_read']['data']
        # Only the fixture's already-sanitized semantic metadata is retained:
        # no CREATE SQL, schema/account names, credentials or application rows.
        diagnosis = {'server':data['inventory']['server'],'tables':{
            table['name']:{key:table[key] for key in
                           ('compatibility','issues','columns','indexes','foreignKeys','checks','triggerCount')}
            for table in data['inventory']['tables']}}
        self.assertEqual(data['plan']['state'],'ready_no_op',json.dumps(diagnosis,sort_keys=True))
        self.assertEqual(len(data['plan']['existingCompatibleTables']),3,json.dumps(diagnosis,sort_keys=True))
        self.assertEqual(data['plan']['createTables'],[])
        self.assertEqual(data['receipt']['schemaWrites'],0)
        self.assertEqual(sum(len(t['checks']) for t in data['inventory']['tables'] if t['role']=='target'),8)

    def test_actual_remote_rejects_boolean_cap_before_php_or_db(self):
        self.payload['provider_http_calls'] = False
        result = self.run_remote()
        self.assertFalse(m.completed_schema(self.command,result,CONTROL))
        self.assertEqual(result.get('reason'),'local_stay_schema_payload_types')
        operation = self.home / '.anytoour-int-executor' / m.SCHEMA_OPERATION
        self.assertFalse((operation / 'local-stay-schema-read.json').exists())

    def test_actual_remote_rejects_unmanifested_config_before_php_or_db(self):
        with tarfile.open(self.archive,'r:gz') as old:
            members = [(x,old.extractfile(x).read()) for x in old.getmembers()]
        with tarfile.open(self.archive,'w:gz') as altered:
            for info,raw in members: altered.addfile(info,io.BytesIO(raw))
            extra = b'<?php throw new RuntimeException("unmanifested configuration");'
            info = tarfile.TarInfo('v2/config.php');info.size = len(extra)
            altered.addfile(info,io.BytesIO(extra))
        result = self.run_remote()
        self.assertFalse(m.completed_schema(self.command,result,CONTROL))
        self.assertEqual(result.get('reason'),'local_stay_schema_archive_scope')
        operation = self.home / '.anytoour-int-executor' / m.SCHEMA_OPERATION
        self.assertFalse((operation / 'local-stay-schema-read.json').exists())

    def test_actual_php_metadata_contract_suite(self):
        env = dict(os.environ,ANYTOUR_STAY_SCHEMA_V1_TEST_DSN=self.server,
                   ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD=self.password,
                   LOCAL_STAY_SOURCE_ROOT=str(SOURCE))
        path = ROOT / 'tests/anytour_hotel_stay_schema_read_v1_test.php'
        run = subprocess.run(['php',str(path)],capture_output=True,text=True,env=env,timeout=120)
        self.assertEqual(run.returncode,0,run.stderr+'\n'+run.stdout)
        self.assertRegex(run.stdout,r'HOTEL_STAY_SCHEMA_READ_OK checks=[1-9][0-9]+ database=NATIVE_MYSQL')
        print(run.stdout.strip())


def install_command(c):
    return c.parse_command(f'{c.PREFIX}{m.INSTALL_SOURCE_SHA} {m.INSTALL_MODE} {m.INSTALL_OPERATION}')


def install_fixture_data():
    # Deliberately fictional structural data tests the protocol, not production
    # schema compatibility. Actual schema semantics are tested by native MySQL.
    before=schema_missing_data()['inventory']
    for table in before['tables'][:2]:
        table.update(exists=True,tableType='base_table',engine='InnoDB',collation='utf8mb4_bin',
                     createSha256='d'*64,compatibility='compatible',issues=[])
    after=copy.deepcopy(before)
    for table in after['tables'][2:]:
        table.update(exists=True,tableType='base_table',engine='InnoDB',collation='utf8mb4_bin',
                     createSha256='e'*64,compatibility='compatible',issues=[])
    digest=lambda x:hashlib.sha256(json.dumps(x,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    data={'receipt':{'schemaVersion':1,'source':'anytour-hotel-stay-schema-install-v1','state':'installed_empty','reason':None,
          'migrationSha256':m.SCHEMA_MIGRATION_SHA,'parentReadOperation':m.INSTALL_PARENT['operation'],
          'parentReadInventorySha256':digest(before),'databaseSha256':'f'*64,'beforeSha256':digest(before),'afterSha256':digest(after),
          'attemptedTables':list(m.INSTALL_TABLES),'createdTables':list(m.INSTALL_TABLES),'unknownTables':[],
          'tableRows':[{'name':name,'rows':0} for name in m.INSTALL_TABLES],
          'supplierCalls':0,'databaseWrites':0,'automaticAccepts':0,'schemaWrites':3,'ddlOutcomeUnknown':False,
          'parentMetadataUnchanged':True,'journalSha256':'a'*64,
          'operationId':m.INSTALL_OPERATION,'sourceSha':m.INSTALL_SOURCE_SHA,'controlSha':CONTROL},'before':before,'after':after}
    return data


def install_journal(data):
    r=data['receipt'];events=[{'stage':'start','operationId':r['operationId'],'migrationSha256':r['migrationSha256'],
          'databaseSha256':r['databaseSha256'],'beforeSha256':r['beforeSha256'],
          'parentReadInventorySha256':r['parentReadInventorySha256']}]
    hashes=['ae700b15382e9797ff20c351abeafd052783a549749431ad3f337c9881f098a8',
            '3dd522bc038f62e3161b15472d2081192bfae635ae9afcb154ece577f62e1762',
            '0789fec589f0ef74fa02a2502ab65d59bcd49576a1961371c09da6ee690ec5d5']
    for i,name in enumerate(r['attemptedTables']):
        events.append({'stage':'before_create','table':name,'statementSha256':hashes[i]})
        if name in r['createdTables']: events.append({'stage':'created','table':name,'acknowledgedSchemaWrites':i+1})
    events.append({'stage':'final',**{key:r[key] for key in ('state','reason','attemptedTables','createdTables','unknownTables')}})
    return events


def install_receipt(data):
    outer=receipt(missing_data());outer.update(mode=m.INSTALL_MODE,operation_id=m.INSTALL_OPERATION,source_sha=m.INSTALL_SOURCE_SHA,
           schema_writes=3,schema_writes_acknowledged=3,schema_write_outcome_unknown=False)
    del outer['local_stay_read']
    raw=(json.dumps(data,ensure_ascii=False,sort_keys=True,separators=(',',':'))+'\n').encode()
    outer['local_stay_schema_install']={'data':data,'fileSha256':hashlib.sha256(raw).hexdigest(),
          'protectedBefore':dict(m.INSTALL_PARENT['protected']),'protectedAfter':dict(m.INSTALL_PARENT['protected']),
          'parentResultSha256':m.INSTALL_PARENT['private_sha256'],'journalSha256':data['receipt']['journalSha256'],
          'journalEvents':install_journal(data)}
    return outer


class InstallAdmission(unittest.TestCase):
    def test_fixed_owner_main_release_command_and_no_new_caller_authority(self):
        c=wrapper().core;body=f'{c.PREFIX}{m.INSTALL_SOURCE_SHA} {m.INSTALL_MODE} {m.INSTALL_OPERATION}'
        parsed=install_command(c);self.assertEqual(parsed['maximum_schema_tables'],3)
        for invalid in (body+' --apply',body.replace(m.INSTALL_SOURCE_SHA,'a'*40),body.replace(m.INSTALL_OPERATION,m.INSTALL_OPERATION+'-v2')):
            with self.subTest(body=invalid),self.assertRaises(ValueError): c.parse_command(invalid)
        with mock.patch.dict(os.environ,{'GITHUB_RUN_ATTEMPT':'2'}),self.assertRaises(ValueError): c.parse_command(body)
        event={'issue':{'number':4709},'comment':{'id':111,'user':{'id':226193297},'author_association':'OWNER','body':body}}
        def api(path,token):
            if path=='/issues/comments/111': return {'body':body,'user':{'id':226193297}}
            if path=='/git/ref/heads/main': return {'object':{'sha':CONTROL}}
            if path=='/git/ref/heads/release/search3-production-ready-v1': return {'object':{'sha':m.INSTALL_SOURCE_SHA}}
            raise AssertionError(path)
        with mock.patch.object(c,'api_get',side_effect=api):
            self.assertEqual(c.checked_event('fixture',event,CONTROL),parsed)
            for wrong in (dict(event,issue={'number':4217}),dict(event,issue={'number':4709,'pull_request':{}})):
                if wrong['issue'].get('pull_request')=={}: wrong['issue']['pull_request']={'url':'fixture'}
                with self.assertRaises(ValueError): c.checked_event('fixture',wrong,CONTROL)
            with self.assertRaises(ValueError): c.checked_event('fixture',event,'c'*40)

    def test_split_bundle_closed_scope_and_exact_source_hashes(self):
        raw,hashes=m.bundle_install_source(SOURCE)
        self.assertEqual(hashes,m.install_hashes())
        with tarfile.open(fileobj=io.BytesIO(raw),mode='r:gz') as archive:
            self.assertEqual(len(archive.getmembers()),5)
            self.assertEqual(set(archive.getnames()),{*hashes,'manifest.json'})
            self.assertTrue(all(x.isfile() and x.mode==0o600 for x in archive.getmembers()))
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp)
            for relative in m.SCHEMA_SOURCE_HASHES:
                path=root/relative;path.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(SOURCE/relative,path)
            (root/m.SCHEMA_SQL).write_text('unreviewed migration')
            with self.assertRaisesRegex(ValueError,'reviewed_source_changed'): m.bundle_install_source(root)

    def test_activation_preserves_original_results_and_rejects_boolean_or_extra_write_caps(self):
        c=wrapper().core;parsed=install_command(c)
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):
            for key,value in (('maximum_schema_tables',True),('maximum_schema_tables',4),('provider_http_calls',1),('database_writes',1),('sql','CREATE')):
                with self.subTest(key=key),self.assertRaises(ValueError): m.activate(c,parsed|{key:value})
            m.activate(c,parsed)
        ast.parse(c.REMOTE)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",c.REMOTE)
        self.assertIn("if globals().get('local_stay_install_reserved') is True and op.exists():",c.REMOTE)
        self.assertIn("if len(members)!=5",c.REMOTE)
        self.assertIn("result['schema_writes']='unknown'",c.REMOTE)
        self.assertLess(len(__import__('base64').b64encode(zlib.compress(c.REMOTE.encode(),9)))+150,65536)

    def test_success_requires_three_empty_tables_protected_pins_and_durable_journal_order(self):
        data=install_fixture_data();pins=copy.deepcopy(m.INSTALL_PARENT);pins['inventory_sha256']=data['receipt']['beforeSha256']
        with mock.patch.object(m,'INSTALL_PARENT',pins):
            parsed=install_command(wrapper().core);valid=install_receipt(data)
            self.assertTrue(m.completed_install(parsed,valid,CONTROL))
            for field,key,value in (('receipt','schemaWrites',False),('receipt','ddlOutcomeUnknown',True),('receipt','tableRows',[]),('receipt','controlSha','c'*40)):
                changed=copy.deepcopy(data);changed[field][key]=value
                self.assertFalse(m.completed_install(parsed,install_receipt(changed),CONTROL))
            changed=copy.deepcopy(valid);changed['local_stay_schema_install']['journalEvents'][1]['table']=m.INSTALL_TABLES[1]
            self.assertFalse(m.completed_install(parsed,changed,CONTROL))
            changed=copy.deepcopy(valid);changed['local_stay_schema_install']['protectedAfter']['config.php']='0'*64
            self.assertFalse(m.completed_install(parsed,changed,CONTROL))
            changed=copy.deepcopy(valid);changed['schema_writes']=False
            self.assertFalse(m.completed_install(parsed,changed,CONTROL))

    def test_partial_acknowledgement_remains_unknown_and_never_completes(self):
        data=install_fixture_data();r=data['receipt'];r.update(state='ddl_unknown',reason='ddl_execution_failed',
            attemptedTables=list(m.INSTALL_TABLES[:2]),createdTables=list(m.INSTALL_TABLES[:1]),unknownTables=[m.INSTALL_TABLES[1]],
            tableRows=[],schemaWrites=1,ddlOutcomeUnknown=True,parentMetadataUnchanged=True)
        pins=copy.deepcopy(m.INSTALL_PARENT);pins['inventory_sha256']=r['beforeSha256']
        with mock.patch.object(m,'INSTALL_PARENT',pins):
            self.assertTrue(m.valid_install_payload(data,CONTROL))
            failed=install_receipt(data);failed.update(status='terminal_nonzero_no_replay',schema_writes='unknown',schema_writes_acknowledged=1,schema_write_outcome_unknown=True)
            self.assertFalse(m.completed_install(install_command(wrapper().core),failed,CONTROL))
            self.assertEqual(failed['local_stay_schema_install']['data']['receipt']['unknownTables'],[m.INSTALL_TABLES[1]])


class NativeStockSchemaInstall(unittest.TestCase):
    def setUp(self):
        self.assertIsNotNone(shutil.which('php'),'Actual PHP is required; no skip or substitute')
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup);self.home=Path(self.temp.name)
        self.project=self.home/'www/anytoour.ru';(self.project/'data').mkdir(parents=True)
        (self.project/'_preview/search3-anex-candidate').mkdir(parents=True)
        for name in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'):
            path=self.project/name;path.parent.mkdir(parents=True,exist_ok=True);path.write_text('fixture protected '+name)
        shutil.copyfile(SOURCE/'v2/data/db-v1.php',self.project/'data/db-v1.php')
        self.server='mysql:host=127.0.0.1;port=3306;charset=utf8mb4';self.password=os.environ.get('LOCAL_STAY_TEST_PASSWORD','local_tv_schema_test_only')
        self.dsn='mysql:host=127.0.0.1;port=3306;dbname=anytour_stay_schema_install_control_fixture;charset=utf8mb4'
        init=r'''$p=new PDO($argv[1],"root",$argv[2],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("DROP DATABASE IF EXISTS anytour_stay_schema_install_control_fixture");$p->exec("CREATE DATABASE anytour_stay_schema_install_control_fixture");$p->exec("USE anytour_stay_schema_install_control_fixture");$sql=preg_replace('/^\s*--.*$/m','',file_get_contents($argv[3]));foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $s){if(trim($s)!=='')$p->exec(trim($s));}'''
        run=subprocess.run(['php','-r',init,self.server,self.password,str(SOURCE/'v2/data/migrations/20260916-anytour-canonical-catalog.sql')],capture_output=True,text=True)
        self.assertEqual(run.returncode,0,run.stderr)
        config='<?php\ndefine("ANYTOUR_DATA_DSN",'+json.dumps(self.dsn)+');\ndefine("ANYTOUR_DATA_DB_USER","root");\ndefine("ANYTOUR_DATA_DB_PASSWORD",'+json.dumps(self.password)+');\n'
        (self.project/'config.php').write_text(config)
        stage=self.home/'parent-source';stage.mkdir()
        raw,_=m.bundle_schema_source(SOURCE)
        with tarfile.open(fileobj=io.BytesIO(raw),mode='r:gz') as archive: archive.extractall(stage,filter='data')
        read=r'''require $argv[1];$p=new PDO($argv[2],"root",$argv[3],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$d=AnyTourHotelStaySchemaReadV1::read($p);$d['receipt']+=['operationId'=>AnyTourHotelStaySchemaReadV1::OPERATION,'sourceSha'=>$argv[4],'controlSha'=>$argv[5]];echo AnyTourHotelStaySchemaReadV1::json($d);'''
        run=subprocess.run(['php','-r',read,str(stage/m.SCHEMA_RUNNER),self.dsn,self.password,m.INSTALL_PARENT['source'],m.INSTALL_PARENT['control']],capture_output=True,text=True)
        self.assertEqual(run.returncode,0,run.stderr);data=json.loads(run.stdout)
        self.assertEqual(data['plan']['state'],'create_only_after_review',data)
        parent=schema_receipt(data)
        public={name:hashlib.sha256((self.project/name).read_bytes()).hexdigest() for name in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        protected={name:hashlib.sha256((self.project/name).read_bytes()).hexdigest() for name in ('config.php','data/db-v1.php')}
        parent.update(production_before=public,production_after=dict(public))
        parent['local_stay_schema_read'].update(protectedBefore=protected,protectedAfter=dict(protected))
        parent_raw=json.dumps(parent,sort_keys=True,separators=(',',':')).encode()
        parent_path=self.home/'.anytoour-int-executor'/m.SCHEMA_OPERATION/'result.json';parent_path.parent.mkdir(parents=True,mode=0o700)
        parent_path.write_bytes(parent_raw);parent_path.chmod(0o600);self.parent_path=parent_path
        pins=copy.deepcopy(m.INSTALL_PARENT);pins.update(private_sha256=hashlib.sha256(parent_raw).hexdigest(),
            file_sha256=parent['local_stay_schema_read']['fileSha256'],inventory_sha256=data['receipt']['inventorySha256'],
            plan_sha256=data['receipt']['planSha256'],protected=protected)
        self.enterContext(mock.patch.object(m,'INSTALL_PARENT',pins))
        self.c=wrapper().core
        with mock.patch.dict(os.environ,{'GITHUB_SHA':CONTROL,'GITHUB_RUN_ATTEMPT':'1'}):
            self.command=install_command(self.c);m.activate(self.c,self.command)
        archive,hashes=m.bundle_install_source(SOURCE);self.archive=self.home/'install-source.tar.gz';self.archive.write_bytes(archive)
        self.payload=self.command|{'archive':str(self.archive),'manifest_sha256':hashlib.sha256(json.dumps(hashes,sort_keys=True,separators=(',',':')).encode()).hexdigest()}

    run_remote=NativeStockRead.run_remote

    def test_actual_three_empty_creates_and_repeat_preserves_original_terminal_and_journal(self):
        result=self.run_remote();self.assertTrue(m.completed_install(self.command,result,CONTROL),result)
        receipt=result['local_stay_schema_install']['data']['receipt']
        self.assertEqual(receipt['createdTables'],list(m.INSTALL_TABLES));self.assertEqual(result['schema_writes'],3)
        self.assertEqual(receipt['tableRows'],[{'name':name,'rows':0} for name in m.INSTALL_TABLES])
        operation=self.home/'.anytoour-int-executor'/m.INSTALL_OPERATION
        original={name:(operation/name).read_bytes() for name in ('result.json','local-stay-schema-install.json','local-stay-schema-install-journal.jsonl')}
        repeated=self.run_remote();self.assertEqual(repeated.get('reason'),'operation_exists_no_replay')
        self.assertFalse(m.completed_install(self.command,repeated,CONTROL))
        self.assertTrue(all((operation/name).read_bytes()==raw for name,raw in original.items()))

    def test_actual_parent_bytes_drift_stops_before_php_or_ddl(self):
        self.parent_path.write_bytes(self.parent_path.read_bytes()+b' ')
        result=self.run_remote();self.assertEqual(result.get('reason'),'local_stay_install_parent_hash')
        self.assertFalse(m.completed_install(self.command,result,CONTROL));self.assertEqual(result.get('schema_writes'),0)
        self.assertNotIn('local_stay_schema_install',result)

    def test_actual_preexisting_target_refusal_is_retained_with_zero_ddl(self):
        run=subprocess.run(['php','-r','$p=new PDO($argv[1],"root",$argv[2]);$p->exec("CREATE TABLE anytour_hotel_room_concepts_v2(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB");',self.dsn,self.password],capture_output=True,text=True)
        self.assertEqual(run.returncode,0,run.stderr)
        result=self.run_remote();self.assertEqual(result['status'],'terminal_nonzero_no_replay',result)
        data=result['local_stay_schema_install']['data'];self.assertEqual(data['receipt']['state'],'preflight_refused')
        self.assertEqual(result['schema_writes'],0);self.assertFalse(m.completed_install(self.command,result,CONTROL))

    def test_actual_boolean_cap_is_rejected_before_php_or_ddl(self):
        self.payload['provider_http_calls']=False;result=self.run_remote()
        self.assertEqual(result.get('reason'),'local_stay_install_payload_types')
        self.assertFalse((self.home/'.anytoour-int-executor'/m.INSTALL_OPERATION/'local-stay-schema-install.json').exists())

    def test_actual_extra_staged_config_is_rejected_before_extraction(self):
        with tarfile.open(self.archive,'r:gz') as original: members=[(x,original.extractfile(x).read()) for x in original.getmembers()]
        with tarfile.open(self.archive,'w:gz') as changed:
            for info,raw in members: changed.addfile(info,io.BytesIO(raw))
            raw=b'<?php throw new RuntimeException("unmanifested");';info=tarfile.TarInfo('v2/config.php');info.size=len(raw);changed.addfile(info,io.BytesIO(raw))
        result=self.run_remote();self.assertEqual(result.get('reason'),'local_stay_install_archive_scope')
        self.assertFalse((self.home/'.anytoour-int-executor'/m.INSTALL_OPERATION/'source/v2/config.php').exists())

    def test_actual_php_create_only_contract_and_partial_acknowledgement_suite(self):
        env=dict(os.environ,ANYTOUR_STAY_SCHEMA_INSTALL_V1_TEST_DSN=self.server,
                 ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD=self.password,LOCAL_STAY_SOURCE_ROOT=str(SOURCE))
        run=subprocess.run(['php',str(ROOT/'tests/anytour_hotel_stay_schema_install_v1_test.php')],capture_output=True,text=True,env=env,timeout=120)
        self.assertEqual(run.returncode,0,run.stderr+'\n'+run.stdout)
        self.assertRegex(run.stdout,r'HOTEL_STAY_SCHEMA_INSTALL_OK checks=[1-9][0-9]+ database=NATIVE_MYSQL')
        print(run.stdout.strip())


if __name__ == '__main__':
    unittest.main()
