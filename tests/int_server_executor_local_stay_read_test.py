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


if __name__ == '__main__':
    unittest.main()
