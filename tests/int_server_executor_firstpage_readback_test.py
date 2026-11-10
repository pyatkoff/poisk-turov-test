#!/usr/bin/env python3
"""Exercise retained files/logs and the permanent executor, without server access."""
from __future__ import annotations

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
import sys
import tempfile
import types
import unittest
from unittest import mock
import zlib

ROOT = Path(__file__).resolve().parents[1]


def load(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / 'scripts/deploy' / (name + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


extension = load('int_server_executor_firstpage_readback')


class CommandTest(unittest.TestCase):
    def setUp(self):
        self.core = load('int_server_executor')
        extension.register_parser(self.core)
        self.body = f'{self.core.PREFIX}{extension.SOURCE} {extension.MODE} {extension.OPERATION}'

    def test_only_captured_source_and_exact_operation_without_options(self):
        command = self.core.parse_command(self.body)
        self.assertEqual({'source_sha': extension.SOURCE, 'mode': extension.MODE,
                          'operation_id': extension.OPERATION}, command)
        for body in [self.body + ' extra', self.body.replace(extension.SOURCE, 'a' * 40),
                     self.body.replace('-v1', '-v2'), self.body.rsplit(' ', 1)[0]]:
            with self.subTest(body=body), self.assertRaises(ValueError):
                self.core.parse_command(body)
        ordinary = (f'{self.core.PREFIX}{extension.SOURCE} local-readback int-andromeda-fixture-readback-v1 '
                    '1 4 2026-10-13 2026-10-19 7 2 - 0')
        self.assertEqual('local-readback', self.core.parse_command(ordinary)['mode'])

    def test_activation_leaves_other_modes_and_bundle_unchanged(self):
        remote, bundle = self.core.REMOTE, self.core.bundle_source
        extension.activate(self.core, {'mode': 'local-readback'})
        self.assertEqual(remote, self.core.REMOTE)
        command = self.core.parse_command(self.body)
        with self.assertRaises(ValueError):
            extension.activate(self.core, command | {'window_end': 9999999999})
        extension.activate(self.core, command)
        self.assertIs(bundle, self.core.bundle_source)
        self.assertIn('operation_exists_no_replay', self.core.REMOTE)
        self.assertLess(len(base64.b64encode(zlib.compress(self.core.REMOTE.encode(), 9))) + 1024, 65536)

    def test_wrapper_registers_read_only_mode_without_supplier_or_secret_slot(self):
        wrapper = load('int_server_executor_anex_secret_transport')
        self.assertEqual(extension.MODE, wrapper.core.parse_command(self.body)['mode'])
        self.assertNotIn(extension.MODE, wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(extension.MODE, wrapper.DIRECT_ANEX_MODES)

    def test_existing_owner_main_and_current_int_checks_still_apply(self):
        comment = {'id': 123, 'body': self.body, 'user': {'id': self.core.OWNER_ID},
                   'author_association': 'OWNER'}
        replies = {'/issues/comments/123': comment,
                   '/git/ref/heads/main': {'object': {'sha': 'a' * 40}},
                   '/git/ref/heads/' + self.core.FEATURE: {'object': {'sha': extension.SOURCE}}}
        event = {'issue': {'number': self.core.ISSUE}, 'comment': comment}
        with mock.patch.object(self.core, 'api_get', side_effect=lambda path, token: replies[path]):
            self.assertEqual(extension.MODE, self.core.checked_event('fixture', event, 'a' * 40)['mode'])
            replies['/git/ref/heads/' + self.core.FEATURE]['object']['sha'] = 'b' * 40
            with self.assertRaisesRegex(ValueError, 'feature_changed'):
                self.core.checked_event('fixture', event, 'a' * 40)
            with self.assertRaisesRegex(ValueError, 'main_changed'):
                self.core.checked_event('fixture', event, 'c' * 40)

    def test_new_failure_command_is_separate_and_has_no_window_or_path_options(self):
        body = f'{self.core.PREFIX}{extension.FAILURE_SOURCE} {extension.FAILURE_MODE} {extension.FAILURE_OPERATION}'
        command = self.core.parse_command(body)
        self.assertEqual({'source_sha': extension.FAILURE_SOURCE, 'mode': extension.FAILURE_MODE,
                          'operation_id': extension.FAILURE_OPERATION}, command)
        for bad in [body + ' 1791127647', body + ' /private/page.json',
                    body.replace(extension.FAILURE_SOURCE, extension.SOURCE),
                    body.replace(extension.FAILURE_OPERATION, extension.OPERATION),
                    self.body.replace(extension.OPERATION, extension.FAILURE_OPERATION)]:
            with self.subTest(body=bad), self.assertRaises(ValueError):
                self.core.parse_command(bad)
        wrapper = load('int_server_executor_anex_secret_transport')
        self.assertEqual(command, wrapper.core.parse_command(body))
        self.assertNotIn(extension.FAILURE_MODE, wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(extension.FAILURE_MODE, wrapper.DIRECT_ANEX_MODES)
        remote = self.core.REMOTE
        with self.assertRaises(ValueError):
            extension.activate(self.core, command | {'path': '/private/page.json'})
        self.assertEqual(remote, self.core.REMOTE)
        extension.activate(self.core, command)
        self.assertIn('initial_failure_readback', self.core.REMOTE)
        self.assertLess(len(base64.b64encode(zlib.compress(self.core.REMOTE.encode(), 9))) + 1024, 65536)

    def test_old_incident_is_still_sealed_and_new_reader_requires_current_source(self):
        self.assertEqual('598092cd292b66b7b94e4a7913a3e2d1f5b550ae', extension.SOURCE)
        self.assertEqual('int-andromeda-firstpage-receipt-20261004-v1', extension.OPERATION)
        original = extension.remote_with_readback(self.core)
        self.assertIn('FP_START=1791053925', original)
        self.assertIn('FP_END=1791053946', original)
        self.assertNotIn('IF_START=', original)
        body = f'{self.core.PREFIX}{extension.FAILURE_SOURCE} {extension.FAILURE_MODE} {extension.FAILURE_OPERATION}'
        comment = {'id': 123, 'body': body, 'user': {'id': self.core.OWNER_ID},
                   'author_association': 'OWNER'}
        replies = {'/issues/comments/123': comment, '/git/ref/heads/main': {'object': {'sha': 'a' * 40}},
                   '/git/ref/heads/' + self.core.FEATURE: {'object': {'sha': extension.FAILURE_SOURCE}}}
        event = {'issue': {'number': self.core.ISSUE}, 'comment': comment}
        with mock.patch.object(self.core, 'api_get', side_effect=lambda path, token: replies[path]):
            self.assertEqual(extension.FAILURE_MODE, self.core.checked_event('fixture', event, 'a' * 40)['mode'])
            replies['/git/ref/heads/' + self.core.FEATURE]['object']['sha'] = extension.SOURCE
            with self.assertRaisesRegex(ValueError, 'feature_changed'):
                self.core.checked_event('fixture', event, 'a' * 40)
            replies['/git/ref/heads/' + self.core.FEATURE]['object']['sha'] = extension.FAILURE_SOURCE
            comment['user']['id'] = 0
            with self.assertRaises(ValueError):
                self.core.checked_event('fixture', event, 'a' * 40)


class EvidenceTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.home = self.root / 'home'
        self.project = self.home / 'www/anytoour.ru'
        self.runtime = self.project / '_preview/search3-anex-candidate'
        self.app = self.runtime / 'app/integrations'
        self.directory = self.home / 'private-catalog/searches'
        self.app.mkdir(parents=True)
        self.directory.mkdir(parents=True)
        for name in ['index.php', 'v2/index.php', 'v2/api-v2.php', 'v2/lead-adapter-v2.php']:
            path = self.project / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('<?php /* protected-production-fixture */')
        self.config = self.runtime / '.andromeda-private.php'
        self.config.write_text("<?php echo 'credential-canary'; return ['enabled'=>true,'catalog_path'=>'"
                               + str(self.directory.parent / 'catalog.json') + "','password'=>'credential-canary'];")
        self.ingest = self.project / '_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php'
        self.ingest.parent.mkdir(parents=True)
        self.ingest.write_text('<?php class AnyTourOfferSnapshotIngestV1 {function mergePartialSnapshot(){}}')
        for name in ['andromeda-client.php', 'andromeda-anytour-offer-autosave.php',
                     'andromeda-saved-package-runtime.php', 'anytour-offer-snapshot-producer.php']:
            (self.app / name).write_text('<?php /* runtime-canary */')
        (self.runtime / 'api-andromeda-search3-preview.php').write_text('<?php /* endpoint */')
        self.php = mock.Mock(return_value=subprocess.CompletedProcess(
            [], 0, json.dumps({'directory': str(self.directory.parent)}), ''))
        self.namespace = {'home': self.home, 'project': self.project, 'runtime': self.runtime,
                          'pathlib': __import__('pathlib'), 'hashlib': hashlib, 'json': json, 're': re,
                          'time': __import__('time'), 'subprocess': types.SimpleNamespace(run=self.php),
                          'safe_file': lambda p, n: p.is_file() and not p.is_symlink() and p.stat().st_size <= n,
                          'fail': self.fail_remote}
        exec(extension.REMOTE_READER, self.namespace)
        self.start, self.end = self.namespace['FP_START'], self.namespace['FP_END']
        self.ref = 'a' * 64
        self.generation = 2147483647
        snapshot = {'provider': 'andromeda', 'search_ref': self.ref, 'generation': self.generation,
                    'page': 1, 'pages_count': 10, 'selection_enabled': False,
                    'offers': [{'offer_ref': 'private-offer-canary'} for _ in range(42)], 'rejected': []}
        self.state = {'status': 'partial', 'search_ref': self.ref, 'generation': self.generation,
                      'store': {'version': 1, 'search_ref': self.ref, 'generation': self.generation,
                                'created_at': self.start, 'expires_at': self.start + 900, 'snapshot': snapshot}}
        self.page_path = self.directory / (self.ref + '-1.json')
        self.write_page()

    @staticmethod
    def fail_remote(reason):
        raise RuntimeError(reason)

    def write_page(self, value=None, path=None, at=None):
        path = path or self.page_path
        path.write_text(json.dumps(value if value is not None else self.state))
        stamp = self.end if at is None else at
        os.utime(path, (stamp, stamp))

    def read(self):
        return self.namespace['firstpage_readback']()

    def test_expired_page_is_inspected_without_reuse_or_public_identity(self):
        before = {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()}
        result = self.read()
        self.assertEqual(42, result['page']['normalized_offers'])
        self.assertTrue(result['page']['expired_now'])
        self.assertEqual({'status': 'missing'}, result['page']['checkpoint'])
        self.assertTrue(result['ingest_source_declares_partial_method'])
        for field in ['supplier_calls', 'database_reads', 'database_writes', 'runtime_writes']:
            self.assertEqual(0, result[field])
        self.assertFalse(result['expired_context_reused'])
        for value in ['credential-canary', 'private-offer-canary', str(self.home), self.ref]:
            self.assertNotIn(value, json.dumps(result))
        self.assertEqual(before, {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()})
        self.php.assert_called_once()
        args = self.php.call_args.args[0]
        self.assertEqual(['php', '-d', 'allow_url_fopen=0', '-r'], args[:4])
        self.assertNotIn('PDO', args[4])
        self.assertNotIn('consume(', args[4])

    def test_checkpoint_counts_require_matching_page_and_generation(self):
        path = self.directory / f'{self.ref}-{self.start}-page-1-anytour-offer-autosave-v1.json'
        value = {'version': 1, 'provider': 'andromeda', 'search_ref': self.ref,
                 'generation': self.generation, 'received_page': 1, 'snapshot_mode': 'partial_additive',
                 'cohort_digest': 'b' * 64, 'published_at': self.end,
                 'ready_offer_count': 0, 'confirmation_required_offer_count': 39}
        path.write_text(json.dumps(value))
        self.assertEqual({'status': 'published', 'published_at': self.end, 'ready_offers': 0,
                          'confirmation_offers': 39}, self.read()['page']['checkpoint'])
        for key, bad in [('generation', 1), ('received_page', 2), ('cohort_digest', 'canary'),
                         ('confirmation_required_offer_count', -1), ('version', True)]:
            with self.subTest(key=key):
                path.write_text(json.dumps(value | {key: bad}))
                self.assertEqual('invalid', self.read()['page']['checkpoint']['status'])

    def test_mixed_rollout_is_reported_without_loading_local_ingest(self):
        self.ingest.write_text('<?php throw new Exception("do-not-load");')
        result = self.read()
        self.assertFalse(result['ingest_source_declares_partial_method'])
        self.assertIsNotNone(result['runtime_sha256']['local_ingest'])
        self.ingest.unlink()
        self.assertIsNone(self.read()['runtime_sha256']['local_ingest'])

    def test_multiple_pages_in_window_fail_closed(self):
        self.write_page(path=self.directory / ('b' * 64 + '-1.json'))
        with self.assertRaisesRegex(RuntimeError, 'firstpage_cohort_ambiguous'):
            self.read()

    def test_other_times_auth_and_other_pages_are_not_read(self):
        self.page_path.write_text('not-json-private-canary')
        os.utime(self.page_path, (self.start - 3, self.start - 3))
        for name in [self.ref + '-auth.json', self.ref + '-2.json', 'quote.json']:
            (self.directory / name).write_text('not-json-credential-canary')
        result = self.read()
        self.assertIsNone(result['page'])
        self.assertEqual(0, result['matched_first_pages'])

    def test_bad_retained_contract_is_not_accepted(self):
        for change in [lambda d: d.update(generation=True),
                       lambda d: d.update(generation=2147483648),
                       lambda d: d['store'].update(version=True),
                       lambda d: d['store'].update(expires_at=self.start + 901),
                       lambda d: d['store']['snapshot'].update(page=True),
                       lambda d: d['store']['snapshot'].update(selection_enabled=True)]:
            state = copy.deepcopy(self.state)
            change(state)
            self.write_page(state)
            with self.assertRaisesRegex(RuntimeError, 'firstpage_retained_contract'):
                self.read()

    def test_rejection_output_is_bounded_to_known_classes(self):
        self.state['store']['snapshot']['rejected'] = [
            {'reason': 'MISSING_FIELD', 'missing_field': 'private-canary'},
            {'reason': 'THREE_PROVIDER_ROOM_LABEL'}, {'reason': {'secret': 'private-canary'}}]
        self.write_page()
        result = self.read()
        self.assertEqual({'MISSING_FIELD': 1, 'THREE_PROVIDER_ROOM_LABEL': 1, 'unclassified': 1},
                         result['page']['rejection_classes'])
        self.assertNotIn('private-canary', json.dumps(result))

    def test_symlinked_page_and_oversize_evidence_are_rejected(self):
        self.page_path.unlink()
        target = self.root / 'outside.json'
        target.write_text('private-canary')
        self.page_path.symlink_to(target)
        with self.assertRaisesRegex(RuntimeError, 'firstpage_evidence_symlink'):
            self.read()
        self.page_path.unlink()
        self.page_path.write_bytes(b'x' * (3 * 1024 * 1024 + 1))
        os.utime(self.page_path, (self.end, self.end))
        with self.assertRaisesRegex(RuntimeError, 'firstpage_evidence_file'):
            self.read()

    def test_catalog_must_stay_in_account_and_out_of_other_projects(self):
        for base in [self.root / 'outside', self.home / 'www/another-project']:
            (base / 'searches').mkdir(parents=True)
            self.php.return_value.stdout = json.dumps({'directory': str(base)})
            with self.subTest(base=base), self.assertRaisesRegex(RuntimeError, 'firstpage_catalog_scope'):
                self.read()

    def test_logs_use_exact_utc_window_and_redact_failure_text(self):
        receipt = 'ANDROMEDA_ANYTOUR_AUTOSAVE_RESULT published=0 mode=partial_additive reason=partial_ingest_unavailable received=0 owned=0 ready=0 confirmation=0'
        failure = 'ANDROMEDA_ANYTOUR_AUTOSAVE_FAILED credential-canary-user-id-1234'
        (self.project / 'error_log').write_text('\n'.join([
            '[03-Oct-2026 18:58:44 UTC] ' + receipt,
            '[03-Oct-2026 18:58:45 UTC] ' + receipt,
            '[03-Oct-2026 21:59:06 Europe/Moscow] ' + failure,
            '[03-Oct-2026 18:59:07 UTC] ' + receipt,
            '[03-Oct-2026 18:58:50 Unknown/Zone] ' + receipt,
            '[03-Oct-2026 18:58:50 UTC] unrelated credential-canary']))
        result = self.read()
        logs = result['logs']
        self.assertEqual(1, len(logs['receipts']))
        self.assertEqual('partial_ingest_unavailable', logs['receipts'][0]['reason'])
        self.assertEqual(self.end, logs['failures'][0]['at'])
        self.assertEqual('unclassified', logs['failures'][0]['code'])
        self.assertEqual('time_window_only', logs['correlation'])
        self.assertNotIn('credential-canary', json.dumps(result))

    def test_user_ini_only_reads_last_in_project_target_and_never_external_logs(self):
        inside = self.project / 'php-error.log'
        outside = self.home / 'account-error.log'
        outside.write_text('credential-canary')
        self.project.joinpath('.user.ini').write_text('error_log = ' + str(outside)
            + '\nerror_log=unused.log\nerror_log="php-error.log"\n')
        inside.write_text('[03-Oct-2026 18:58:50 UTC] ANDROMEDA_ANYTOUR_AUTOSAVE_FAILED ANDROMEDA_ANYTOUR_CHECKPOINT_WRITE')
        logs = self.read()['logs']
        self.assertEqual(1, logs['files_read'])
        self.assertEqual('ANDROMEDA_ANYTOUR_CHECKPOINT_WRITE', logs['failures'][0]['code'])

    def test_too_many_incident_log_receipts_fail_closed(self):
        (self.project / 'error_log').write_text(
            '[03-Oct-2026 18:58:50 UTC] ANDROMEDA_ANYTOUR_AUTOSAVE_FAILED unknown\n' * 21)
        with self.assertRaisesRegex(RuntimeError, 'firstpage_log_window_ambiguous'):
            self.read()

    def test_directory_inventory_is_bounded(self):
        self.namespace['FP_MAX_ENTRIES'] = 1
        (self.directory / 'unrelated.json').write_text('{}')
        with self.assertRaisesRegex(RuntimeError, 'firstpage_inventory_bound'):
            self.read()

    @unittest.skipUnless(shutil.which('php'), 'PHP unavailable locally; CI exercises real private-config reader')
    def test_real_php_config_reader_discards_unexpected_output(self):
        self.namespace['subprocess'] = subprocess
        result = self.read()
        self.assertEqual(42, result['page']['normalized_offers'])
        self.assertNotIn('credential-canary', json.dumps(result))

    def remote_fixture(self, mode=extension.MODE):
        core = load('int_server_executor')
        source = self.root / 'source'
        app = source / 'app/integrations'
        app.mkdir(parents=True)
        for i in range(21):
            (app / f'x{i}.php').write_text('<?php /* fixture */')
        for name in core.FIXED:
            path = source / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('<?php /* fixture */')
        bundle, manifest = core.bundle_source(source)
        archive = self.root / 'source.tar.gz'
        archive.write_bytes(bundle)
        bindir = self.root / 'bin'
        bindir.mkdir()
        php = bindir / 'php'
        # A strict stub proves that only the config call runs. A DB summary or
        # collector PHP call fails; the real PHP reader is exercised separately.
        php.write_text('#!' + sys.executable + '\nimport json,sys\n'
            'assert sys.argv[1:4]==["-d","allow_url_fopen=0","-r"]\n'
            'assert len(sys.argv)==6 and "ob_start()" in sys.argv[4]\n'
            'print(' + repr(json.dumps({'directory': str(self.directory.parent)})) + ')\n')
        php.chmod(0o755)
        source_sha, operation = {
            extension.MODE: (extension.SOURCE, extension.OPERATION),
            extension.FAILURE_MODE: (extension.FAILURE_SOURCE, extension.FAILURE_OPERATION),
            extension.CONTINUATION_MODE: (extension.CONTINUATION_SOURCE, extension.CONTINUATION_OPERATION),
        }[mode]
        payload = {'source_sha': source_sha, 'mode': mode,
                   'operation_id': operation, 'archive': str(archive),
                   'manifest_sha256': hashlib.sha256(json.dumps(manifest, sort_keys=True,
                                                               separators=(',', ':')).encode()).hexdigest()}
        env = dict(os.environ, HOME=str(self.home), PATH=str(bindir) + os.pathsep + os.environ.get('PATH', ''))
        remote = self.root / 'remote.py'
        remote.write_text(extension.remote_with_readback(core, mode))

        def run(overrides=None):
            call = subprocess.run([sys.executable, str(remote)], input=json.dumps(payload | (overrides or {})),
                                  text=True, capture_output=True, env=env, timeout=30)
            self.assertEqual(0, call.returncode, call.stderr)
            self.assertEqual('', call.stderr)
            return json.loads(call.stdout)

        return run

    def test_full_executor_reads_once_without_db_collectors_or_runtime_changes(self):
        run = self.remote_fixture()
        before = {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()}
        result = run()
        self.assertEqual('complete', result['status'])
        self.assertEqual(42, result['firstpage_readback']['page']['normalized_offers'])
        self.assertTrue(result['production_unchanged'])
        self.assertTrue(all(re.fullmatch(r'[a-f0-9]{64}', value)
                            for value in result['production_before'].values()))
        for field in ['supplier_calls', 'database_reads', 'database_writes', 'runtime_writes']:
            self.assertEqual(0, result[field])
        self.assertNotIn('before_db', result)
        self.assertNotIn('collector', result)
        self.assertEqual(before, {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()})
        reservation = self.home / '.anytoour-int-executor' / extension.OPERATION / 'reservation.json'
        reserved = reservation.read_bytes()
        blocked = run()
        self.assertEqual('operation_exists_no_replay', blocked['reason'])
        self.assertNotIn('firstpage_readback', blocked)
        self.assertEqual(reserved, reservation.read_bytes())

    def test_full_executor_sanitizes_errors_and_never_retries_failed_read(self):
        run = self.remote_fixture()
        self.page_path.write_text('private-malformed-json-canary')
        os.utime(self.page_path, (self.end, self.end))
        result = run()
        self.assertEqual('unknown_no_replay', result['status'])
        self.assertEqual('firstpage_readback_unclassified', result['reason'])
        self.assertEqual(0, result['database_reads'])
        for value in ['private-malformed-json-canary', str(self.home), self.ref]:
            self.assertNotIn(value, json.dumps(result))
        self.assertEqual('operation_exists_no_replay', run()['reason'])


class InitialFailureEvidenceTest(unittest.TestCase):
    fail_remote = staticmethod(EvidenceTest.fail_remote)
    write_page = EvidenceTest.write_page
    remote_fixture = EvidenceTest.remote_fixture

    def setUp(self):
        EvidenceTest.setUp(self)
        exec(extension.REMOTE_FAILURE_READER, self.namespace)
        self.start, self.end = self.namespace['IF_START'], self.namespace['IF_END']
        self.state = {'version': 1, 'search_ref': self.ref, 'generation': self.generation,
                      'status': 'unavailable', 'error': 'supplier_result_unavailable',
                      'error_code': 'ANDROMEDA_TRANSPORT_ERROR',
                      'criteria': self.namespace['IF_CRITERIA'] | {'TOWNFROMINC': 1, 'STATEINC': 3},
                      'store': {'version': 1, 'search_ref': self.ref, 'generation': self.generation,
                                'created_at': self.start, 'expires_at': self.start + 900,
                                'snapshot': None, 'criteria': [], 'raw_ids': []}}
        for name in ['andromeda-search.php', 'andromeda-transport.php', 'andromeda-offer-store.php']:
            (self.app / name).write_text('<?php throw new Exception("supplier-module-must-not-load");')
        self.write_page()

    def read(self):
        return self.namespace['initial_failure_readback']()

    def test_expired_failure_reports_only_code_and_never_reuses_state(self):
        before = {p: p.read_bytes() for p in self.home.rglob('*') if p.is_file()}
        result = self.read()
        self.assertEqual({'status': 'unavailable', 'error_code': 'ANDROMEDA_TRANSPORT_ERROR',
                          'created_at': self.start, 'expires_at': self.start + 900,
                          'expired_now': True, 'snapshot_present': False}, result['page'])
        self.assertEqual(1791127622, result['window_start'])
        self.assertEqual(1791127646, result['window_end'])
        self.assertEqual(1, result['matched_first_pages'])
        self.assertFalse(result['route_identity_verified'])
        self.assertFalse(result['expired_context_reused'])
        self.assertFalse(result['raw_payloads_exposed'])
        for field in ['supplier_calls', 'database_reads', 'database_writes', 'runtime_writes']:
            self.assertEqual(0, result[field])
        self.assertEqual(hashlib.sha256((self.app / 'andromeda-search.php').read_bytes()).hexdigest(),
                         result['runtime_sha256']['search'])
        for value in ['credential-canary', self.ref, str(self.home), 'TOWNFROMINC', 'STATEINC']:
            self.assertNotIn(value, json.dumps(result))
        self.assertNotIn('logs', result)
        self.assertEqual(before, {p: p.read_bytes() for p in self.home.rglob('*') if p.is_file()})
        self.php.assert_called_once()

    def test_pending_reservation_does_not_invent_a_failure_code(self):
        self.state.update(status='pending', error=None)
        del self.state['error_code']
        self.write_page()
        self.assertEqual('pending', self.read()['page']['status'])
        self.assertIsNone(self.read()['page']['error_code'])

    def test_unknown_and_malicious_codes_are_never_exposed(self):
        for code in ['ANDROMEDA_PASSWORD_CANARY_1234', 'password=credential-canary',
                     {'secret': 'credential-canary'}, None, 'ANDROMEDA_HTTP_ERROR']:
            with self.subTest(code=code):
                self.state['error_code'] = code
                self.write_page()
                result = self.read()
                self.assertEqual('ANDROMEDA_HTTP_ERROR' if code == 'ANDROMEDA_HTTP_ERROR'
                                 else 'ANDROMEDA_UNCLASSIFIED_ERROR', result['page']['error_code'])
                self.assertNotIn('canary', json.dumps(result).lower())

    def test_state_ref_generation_times_and_status_are_bound_strictly(self):
        changes = [lambda d: d.update(version=True), lambda d: d.update(generation=True),
                   lambda d: d.update(generation=2147483648), lambda d: d.update(search_ref='b' * 64),
                   lambda d: d.update(status='partial'), lambda d: d.update(error='private-canary'),
                   lambda d: d.pop('error_code'), lambda d: d['store'].update(version=True),
                   lambda d: d['store'].update(generation=True),
                   lambda d: d['store'].update(generation=1),
                   lambda d: d['store'].update(search_ref='b' * 64),
                   lambda d: d['store'].update(created_at=self.start - 1),
                   lambda d: d['store'].update(created_at=self.end + 1),
                   lambda d: d['store'].update(expires_at=self.start + 901),
                   lambda d: d['store'].pop('snapshot'), lambda d: d['store'].update(snapshot={}),
                   lambda d: d['store'].update(raw_ids=['private-canary'])]
        for change in changes:
            state = copy.deepcopy(self.state)
            change(state)
            self.write_page(state)
            with self.subTest(change=changes.index(change)), self.assertRaisesRegex(
                    RuntimeError, 'initial_failure_retained_contract'):
                self.read()

    def test_changed_party_dates_page_filters_and_bad_types_fail_closed(self):
        for key, value in [('CHECKIN_BEG', '20261014'), ('CHECKIN_END', '20261020'),
                           ('NIGHTS_FROM', 8), ('ADULT', 3), ('CHILD', 1), ('PAGE', 2),
                           ('GROUP_BY', True), ('TOWNFROMINC', True), ('STATEINC', '3'),
                           ('HOTELS', '123'), ('MEAL', '5'), ('OPERATORS', 'credential-canary')]:
            state = copy.deepcopy(self.state)
            state['criteria'][key] = value
            self.write_page(state)
            with self.subTest(key=key), self.assertRaisesRegex(RuntimeError, 'initial_failure_criteria_contract'):
                self.read()
        self.state['criteria']['OPERATORS'] = '5,6'
        self.write_page()
        self.assertEqual('unavailable', self.read()['page']['status'])

    def test_missing_ambiguous_or_outside_window_page_is_not_an_incident_receipt(self):
        self.page_path.unlink()
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_evidence_missing'):
            self.read()
        self.write_page(at=self.start - 3)
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_evidence_missing'):
            self.read()
        self.write_page()
        self.write_page(path=self.directory / ('b' * 64 + '-1.json'))
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_evidence_ambiguous'):
            self.read()

    def test_auth_other_pages_autosave_and_logs_are_not_read(self):
        for name in [self.ref + '-auth.json', self.ref + '-2.json',
                     self.ref + '-123-page-1-anytour-offer-autosave-result-v1.json']:
            (self.directory / name).write_text('not-json-private-canary')
        (self.project / 'error_log').write_text('private-canary')
        result = self.read()
        self.assertEqual(1, result['matched_first_pages'])
        self.assertNotIn('private-canary', json.dumps(result))
        self.assertNotIn('logs', result)

    def test_symlink_oversize_scope_and_inventory_guards_are_retained(self):
        self.page_path.unlink()
        target = self.root / 'outside.json'
        target.write_text('private-canary')
        self.page_path.symlink_to(target)
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_evidence_symlink'):
            self.read()
        self.page_path.unlink()
        self.page_path.write_bytes(b'x' * (3 * 1024 * 1024 + 1))
        os.utime(self.page_path, (self.end, self.end))
        with self.assertRaisesRegex(RuntimeError, 'firstpage_evidence_file'):
            self.read()
        self.write_page()
        outside = self.home / 'www/another-project'
        (outside / 'searches').mkdir(parents=True)
        self.php.return_value.stdout = json.dumps({'directory': str(outside)})
        with self.assertRaisesRegex(RuntimeError, 'firstpage_catalog_scope'):
            self.read()
        self.php.return_value.stdout = json.dumps({'directory': str(self.directory.parent)})
        self.namespace['FP_MAX_ENTRIES'] = 1
        (self.directory / 'unrelated.json').write_text('{}')
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_inventory_bound'):
            self.read()

    @unittest.skipUnless(shutil.which('php'), 'PHP unavailable locally; CI exercises real private-config reader')
    def test_real_php_config_reader_discards_credentials_without_loading_modules(self):
        self.namespace['subprocess'] = subprocess
        result = self.read()
        self.assertEqual('ANDROMEDA_TRANSPORT_ERROR', result['page']['error_code'])
        self.assertNotIn('credential-canary', json.dumps(result))

    def test_full_executor_failure_read_once_preserves_files_and_blocks_replay(self):
        run = self.remote_fixture(extension.FAILURE_MODE)
        before = {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()}
        retained = self.page_path.read_bytes()
        result = run()
        self.assertEqual('complete', result['status'])
        self.assertEqual('ANDROMEDA_TRANSPORT_ERROR', result['initial_failure_readback']['page']['error_code'])
        self.assertTrue(result['production_unchanged'])
        self.assertEqual(result['production_before'], result['production_after'])
        for field in ['supplier_calls', 'database_reads', 'database_writes', 'runtime_writes',
                      'booking_calls', 'lead_calls']:
            self.assertEqual(0, result[field])
        for field in ['collector', 'before_db', 'firstpage_readback']:
            self.assertNotIn(field, result)
        self.assertEqual(before, {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()})
        self.assertEqual(retained, self.page_path.read_bytes())
        reservation = self.home / '.anytoour-int-executor' / extension.FAILURE_OPERATION / 'reservation.json'
        reserved = reservation.read_bytes()
        blocked = run()
        self.assertEqual('operation_exists_no_replay', blocked['reason'])
        self.assertNotIn('initial_failure_readback', blocked)
        self.assertEqual(reserved, reservation.read_bytes())

    def test_full_executor_malformed_data_is_sanitized_and_not_retried(self):
        run = self.remote_fixture(extension.FAILURE_MODE)
        self.page_path.write_text('private-malformed-json-canary')
        os.utime(self.page_path, (self.end, self.end))
        result = run()
        self.assertEqual('unknown_no_replay', result['status'])
        self.assertEqual('initial_failure_readback_unclassified', result['reason'])
        for value in ['private-malformed-json-canary', str(self.home), self.ref]:
            self.assertNotIn(value, json.dumps(result))
        self.assertEqual(0, result['database_reads'])
        self.assertEqual('operation_exists_no_replay', run()['reason'])

    def test_full_executor_rejects_a_different_source_before_private_read(self):
        run = self.remote_fixture(extension.FAILURE_MODE)
        result = run({'source_sha': extension.SOURCE})
        self.assertEqual('initial_failure_sealed_scope', result['reason'])
        self.assertNotIn('initial_failure_readback', result)


class ContinuationFailureEvidenceTest(unittest.TestCase):
    fail_remote = staticmethod(EvidenceTest.fail_remote)
    write_page = EvidenceTest.write_page
    remote_fixture = EvidenceTest.remote_fixture

    def setUp(self):
        EvidenceTest.setUp(self)
        exec(extension.REMOTE_FAILURE_READER, self.namespace)
        self.first_path = self.page_path
        self.first = self.state
        self.first.update(version=1, criteria=self.namespace['CF_CRITERIA']
                          | {'PAGE': 1, 'TOWNFROMINC': 1, 'STATEINC': 3})
        created = self.namespace['CF_FIRST_START']
        self.first['store'].update(created_at=created, expires_at=created + 900)
        self.write_page(self.first, self.first_path, self.namespace['CF_FIRST_END'])
        self.start, self.end = self.namespace['CF_START'], self.namespace['CF_END']
        self.page_path = self.directory / f'{self.ref}-{created}-2.json'
        self.facts = {'source': 'andromeda_transport_error', 'action': 'price',
                      'reason_category': 'network_transfer', 'curl_errno': 28}
        self.state = {'version': 1, 'search_ref': self.ref, 'generation': self.generation,
                      'status': 'unavailable', 'error': 'supplier_result_unavailable',
                      'error_code': 'ANDROMEDA_TRANSPORT_ERROR', 'transport_failure': self.facts,
                      'criteria': self.first['criteria'] | {'PAGE': 2},
                      'store': {'version': 1, 'search_ref': self.ref, 'generation': self.generation,
                                'created_at': self.start, 'expires_at': self.start + 900,
                                'snapshot': None, 'criteria': [], 'raw_ids': []}}
        for name in ['andromeda-search.php', 'andromeda-transport.php', 'andromeda-offer-store.php',
                     'andromeda-network-transport-failure.php']:
            (self.app / name).write_text('<?php throw new Exception("supplier-module-must-not-load");')
        self.write_page()

    def read(self):
        return self.namespace['initial_failure_readback'](True)

    def test_exact_command_has_no_options_and_keeps_both_historical_seals(self):
        wrapper = load('int_server_executor_anex_secret_transport')
        core = wrapper.core
        body = f'{core.PREFIX}{extension.CONTINUATION_SOURCE} {extension.CONTINUATION_MODE} {extension.CONTINUATION_OPERATION}'
        command = core.parse_command(body)
        self.assertEqual(extension.CONTINUATION_MODE, command['mode'])
        for bad in [body + ' /private/page.json', body + ' 1791136899',
                    body.replace(extension.CONTINUATION_SOURCE, extension.FAILURE_SOURCE),
                    body.replace(extension.CONTINUATION_OPERATION, extension.FAILURE_OPERATION),
                    body.replace(extension.CONTINUATION_OPERATION, extension.OPERATION),
                    body.replace('-v1', '-v2')]:
            with self.subTest(body=bad), self.assertRaises(ValueError):
                core.parse_command(bad)
        self.assertNotIn(extension.CONTINUATION_MODE, wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(extension.CONTINUATION_MODE, wrapper.DIRECT_ANEX_MODES)
        for mode in (extension.MODE, extension.FAILURE_MODE):
            remote = extension.remote_with_readback(core, mode)
            self.assertNotIn("if mode=='andromeda-continuation-failure-readback':", remote)
            self.assertIn('FP_START=1791053925', remote)
            self.assertIn('FP_END=1791053946', remote)
        self.assertEqual('d1ad064fbc6fda65929cc57395581b1ba819d952', extension.FAILURE_SOURCE)
        self.assertEqual('int-andromeda-initial-failure-20261004-v1', extension.FAILURE_OPERATION)
        remote = core.REMOTE
        with self.assertRaises(ValueError):
            extension.activate(core, command | {'path': str(self.page_path)})
        self.assertEqual(remote, core.REMOTE)
        extension.activate(core, command)
        self.assertLess(len(base64.b64encode(zlib.compress(core.REMOTE.encode(), 9))) + 1024, 65536)

    def test_current_main_int_and_owner_checks_remain_required(self):
        core = load('int_server_executor')
        extension.register_parser(core)
        body = f'{core.PREFIX}{extension.CONTINUATION_SOURCE} {extension.CONTINUATION_MODE} {extension.CONTINUATION_OPERATION}'
        comment = {'id': 123, 'body': body, 'user': {'id': core.OWNER_ID}, 'author_association': 'OWNER'}
        event = {'issue': {'number': core.ISSUE}, 'comment': comment}
        replies = {'/issues/comments/123': comment, '/git/ref/heads/main': {'object': {'sha': 'a' * 40}},
                   '/git/ref/heads/' + core.FEATURE: {'object': {'sha': extension.CONTINUATION_SOURCE}}}
        with mock.patch.object(core, 'api_get', side_effect=lambda path, token: replies[path]):
            self.assertEqual(extension.CONTINUATION_MODE, core.checked_event('fixture', event, 'a' * 40)['mode'])
            with self.assertRaisesRegex(ValueError, 'main_changed'):
                core.checked_event('fixture', event, 'b' * 40)
            replies['/git/ref/heads/' + core.FEATURE]['object']['sha'] = extension.FAILURE_SOURCE
            with self.assertRaisesRegex(ValueError, 'feature_changed'):
                core.checked_event('fixture', event, 'a' * 40)

    def test_retained_facts_bind_to_page2_and_its_fresh_precursor_without_reuse(self):
        before = {p: p.read_bytes() for p in self.home.rglob('*') if p.is_file()}
        result = self.read()
        self.assertEqual({'status': 'retained', 'facts': self.facts}, result['transport_failure'])
        self.assertEqual(2, result['page']['number'])
        self.assertEqual(1, result['matched_continuations'])
        self.assertEqual(1791136876, result['window_start'])
        self.assertEqual(1791136898, result['window_end'])
        self.assertTrue(result['precursor']['context_fresh_at_continue'])
        self.assertFalse(result['route_identity_verified'])
        self.assertFalse(result['expired_context_reused'])
        for value in ['credential-canary', 'private-offer-canary', self.ref, str(self.home),
                      'TOWNFROMINC', 'STATEINC']:
            self.assertNotIn(value, json.dumps(result))
        for field in ['supplier_calls', 'database_reads', 'database_writes', 'runtime_writes']:
            self.assertEqual(0, result[field])
        self.assertEqual(before, {p: p.read_bytes() for p in self.home.rglob('*') if p.is_file()})
        self.php.assert_called_once()

    def test_missing_facts_are_not_fabricated_and_pending_is_not_called_failed(self):
        del self.state['transport_failure']
        self.write_page()
        self.assertEqual({'status': 'missing', 'facts': None}, self.read()['transport_failure'])
        self.state.update(status='pending', error=None)
        del self.state['error_code']
        self.write_page()
        result = self.read()
        self.assertEqual('pending', result['page']['status'])
        self.assertIsNone(result['page']['error_code'])
        self.assertEqual('missing', result['transport_failure']['status'])

    def test_untrusted_fact_shape_and_codes_cannot_leak_or_establish_network_provenance(self):
        malformed = [None, 'credential-canary', {'source': 'credential-canary'},
                     self.facts | {'curl_errno': True}, self.facts | {'curl_errno': 0},
                     self.facts | {'curl_errno': 1000}, self.facts | {'curl_errno': '28'},
                     self.facts | {'reason_category': 'request_budget'},
                     self.facts | {'action': 'private-canary'}, self.facts | {'source': 'private-canary'},
                     self.facts | {'url': 'private-canary'}, self.facts | {'reason_category': []}]
        for facts in malformed:
            self.state['transport_failure'] = facts
            self.write_page()
            with self.subTest(facts=facts):
                result = self.read()
                self.assertEqual({'status': 'invalid', 'facts': None}, result['transport_failure'])
                self.assertNotIn('canary', json.dumps(result))
        self.state.update(transport_failure=self.facts, error_code='ANDROMEDA_HTTP_ERROR')
        self.write_page()
        self.assertEqual('invalid', self.read()['transport_failure']['status'])

    def test_closed_non_network_categories_have_no_errno_and_missing_errno_stays_unknown(self):
        for category in ['endpoint_guard', 'action_guard', 'request_budget', 'monthly_quota',
                         'curl_unavailable', 'response_size_guard', 'unclassified', 'network_transfer']:
            self.state['transport_failure'] = self.facts | {'reason_category': category, 'curl_errno': None}
            self.write_page()
            with self.subTest(category=category):
                result = self.read()['transport_failure']
                self.assertEqual('retained', result['status'])
                self.assertIsNone(result['facts']['curl_errno'])

    def test_precursor_ref_generation_window_criteria_and_snapshot_are_required(self):
        changes = [lambda d: d.update(version=True), lambda d: d.update(search_ref='b' * 64),
                   lambda d: d.update(generation=1), lambda d: d.update(status='unavailable'),
                   lambda d: d['criteria'].update(PAGE=True),
                   lambda d: d['criteria'].update(ADULT=True),
                   lambda d: d['criteria'].update(STATEINC=5),
                   lambda d: d['store'].update(created_at=self.namespace['CF_FIRST_START'] - 1),
                   lambda d: d['store'].update(generation=True),
                   lambda d: d['store'].update(expires_at=1),
                   lambda d: d['store'].update(snapshot=None),
                   lambda d: d['store']['snapshot'].update(page=2),
                   lambda d: d['store']['snapshot'].update(generation=True),
                   lambda d: d['store']['snapshot'].update(pages_count=1),
                   lambda d: d['store']['snapshot'].update(selection_enabled=True)]
        for change in changes:
            first = copy.deepcopy(self.first)
            change(first)
            self.write_page(first, self.first_path, self.namespace['CF_FIRST_END'])
            with self.subTest(change=changes.index(change)), self.assertRaisesRegex(
                    RuntimeError, 'initial_failure_precursor_contract'):
                self.read()

    def test_wrong_failed_page_criteria_and_other_attempts_are_not_read(self):
        for key, value in [('CHECKIN_BEG', '20261013'), ('PAGE', 1), ('ADULT', 3),
                           ('CHILD', 1), ('MEAL', '5')]:
            state = copy.deepcopy(self.state)
            state['criteria'][key] = value
            self.write_page(state)
            with self.subTest(key=key), self.assertRaisesRegex(RuntimeError, 'initial_failure_criteria_contract'):
                self.read()
        self.write_page()
        for name in [self.ref + '-auth.json', self.ref + '-123-3.json', 'quote.json']:
            (self.directory / name).write_text('not-json-private-canary')
        self.assertEqual('retained', self.read()['transport_failure']['status'])
        self.page_path.unlink()
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_evidence_missing'):
            self.read()

    def test_failed_state_time_status_and_snapshot_are_bound(self):
        changes = [lambda d: d.update(generation=True), lambda d: d.update(version=True),
                   lambda d: d.update(status='partial'), lambda d: d.update(error='credential-canary'),
                   lambda d: d['store'].update(created_at=self.start - 1),
                   lambda d: d['store'].update(created_at=self.end + 1),
                   lambda d: d['store'].update(expires_at=self.start + 901),
                   lambda d: d['store'].update(snapshot={}), lambda d: d['store'].update(raw_ids=['canary'])]
        for change in changes:
            state = copy.deepcopy(self.state)
            change(state)
            self.write_page(state)
            with self.subTest(change=changes.index(change)), self.assertRaisesRegex(
                    RuntimeError, 'initial_failure_retained_contract'):
                self.read()

    def test_symlink_oversize_and_ambiguous_failed_pages_fail_closed(self):
        self.page_path.unlink()
        self.page_path.symlink_to(self.first_path)
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_evidence_symlink'):
            self.read()
        self.page_path.unlink()
        self.page_path.write_bytes(b'x' * (3 * 1024 * 1024 + 1))
        os.utime(self.page_path, (self.end, self.end))
        with self.assertRaisesRegex(RuntimeError, 'firstpage_evidence_file'):
            self.read()
        self.write_page()
        self.write_page(path=self.directory / f'{"b" * 64}-{self.namespace["CF_FIRST_START"]}-2.json')
        with self.assertRaisesRegex(RuntimeError, 'initial_failure_evidence_ambiguous'):
            self.read()

    def test_full_executor_reads_once_and_keeps_search_files_production_and_no_replay(self):
        run = self.remote_fixture(extension.CONTINUATION_MODE)
        before = {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()}
        retained = {p: p.read_bytes() for p in self.directory.iterdir() if p.is_file()}
        result = run()
        self.assertEqual('complete', result['status'])
        self.assertEqual(self.facts, result['continuation_failure_readback']['transport_failure']['facts'])
        self.assertEqual(result['production_before'], result['production_after'])
        for field in ['supplier_calls', 'database_reads', 'database_writes', 'runtime_writes',
                      'booking_calls', 'lead_calls']:
            self.assertEqual(0, result[field])
        for field in ['collector', 'before_db', 'initial_failure_readback', 'firstpage_readback']:
            self.assertNotIn(field, result)
        self.assertEqual(before, {p: p.read_bytes() for p in self.project.rglob('*') if p.is_file()})
        self.assertEqual(retained, {p: p.read_bytes() for p in self.directory.iterdir() if p.is_file()})
        reservation = self.home / '.anytoour-int-executor' / extension.CONTINUATION_OPERATION / 'reservation.json'
        reserved = reservation.read_bytes()
        self.assertEqual('operation_exists_no_replay', run()['reason'])
        self.assertEqual(reserved, reservation.read_bytes())

    def test_full_executor_sanitizes_malformed_evidence_and_never_retries(self):
        run = self.remote_fixture(extension.CONTINUATION_MODE)
        self.page_path.write_text('private-malformed-json-canary')
        os.utime(self.page_path, (self.end, self.end))
        result = run()
        self.assertEqual('unknown_no_replay', result['status'])
        self.assertEqual('continuation_failure_readback_unclassified', result['reason'])
        for value in ['private-malformed-json-canary', str(self.home), self.ref]:
            self.assertNotIn(value, json.dumps(result))
        self.assertEqual(0, result['database_reads'])
        self.assertEqual('operation_exists_no_replay', run()['reason'])

    def test_full_executor_rejects_wrong_source_before_reading(self):
        result = self.remote_fixture(extension.CONTINUATION_MODE)({'source_sha': extension.FAILURE_SOURCE})
        self.assertEqual('continuation_failure_sealed_scope', result['reason'])
        self.assertNotIn('continuation_failure_readback', result)

    @unittest.skipUnless(shutil.which('php'), 'PHP unavailable locally; CI exercises real private-config reader')
    def test_real_php_reader_discards_credentials_and_does_not_load_supplier_modules(self):
        self.namespace['subprocess'] = subprocess
        result = self.read()
        self.assertEqual('retained', result['transport_failure']['status'])
        self.assertNotIn('credential-canary', json.dumps(result))


RIA_HISTORICAL_REMOTE_HASHES = {'andromeda-firstpage-readback': 'c38143365593dfa26561b634670838c310d1a9dfdc9c1489ce3b4408ae2c6b38', 'andromeda-initial-failure-readback': '79f83c07b4e50545701ee0df620db89b14d8b01e282925cb85b478556e5f0576', 'andromeda-continuation-failure-readback': '4a6fc777fcba082d55803cb5c6a3cee74af94d8baad064a5ef598a2935356467'}

class RiaReadbackCommandTest(unittest.TestCase):
    def setUp(self):
        self.core=load('int_server_executor')
        extension.register_parser(self.core)
        self.command={'source_sha':extension.RIA_SOURCE,'mode':extension.RIA_MODE,'operation_id':extension.RIA_OPERATION}
        self.body=self.core.PREFIX+' '.join(self.command[key] for key in ('source_sha','mode','operation_id'))

    def test_fixed_three_token_command_and_no_path_or_payload_options(self):
        self.assertEqual(self.command,self.core.parse_command(self.body))
        for body in (self.body+' /tmp',self.body+' --path=/tmp',self.body.replace(extension.RIA_SOURCE,'a'*40),
                     self.body.replace(extension.RIA_OPERATION,extension.RIA_OPERATION+'-other')):
            with self.assertRaises(ValueError): self.core.parse_command(body)

    def test_existing_wrapper_registers_mode_without_supplier_or_secret_slot(self):
        wrapper=load('int_server_executor_anex_secret_transport')
        self.assertEqual(self.command,wrapper.core.parse_command(self.body))
        self.assertNotIn(extension.RIA_MODE,wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(extension.RIA_MODE,wrapper.DIRECT_ANEX_MODES)

    def test_historical_remote_bytes_remain_exact(self):
        for mode,digest in RIA_HISTORICAL_REMOTE_HASHES.items():
            self.assertEqual(digest,hashlib.sha256(extension.remote_with_readback(self.core,mode).encode()).hexdigest(),mode)

    def test_activation_preserves_bundle_and_excludes_both_collector_branches(self):
        import ast
        bundle=self.core.bundle_source
        extension.activate(self.core,self.command)
        self.assertIs(bundle,self.core.bundle_source)
        tree=ast.parse(self.core.REMOTE)
        excluded=[]
        for node in ast.walk(tree):
            if isinstance(node,ast.Compare) and isinstance(node.left,ast.Name) and node.left.id=='mode' and len(node.ops)==1 and isinstance(node.ops[0],ast.NotIn):
                values=node.comparators[0]
                if isinstance(values,ast.Tuple) and any(isinstance(item,ast.Constant) and item.value=='reconcile' for item in values.elts):
                    excluded.append(eval(compile(ast.Expression(node),'scope','eval'),{'mode':extension.RIA_MODE}))
        self.assertEqual([False,False],excluded)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",self.core.REMOTE)
        self.assertLess(self.core.REMOTE.index("(op/'reservation.json').write_text"),self.core.REMOTE.index("if mode=='anex-ria-apd-retained-readback':"))
        self.assertNotIn('ria_readback',self.core.REMOTE.split('try:\n    if not re.fullmatch',1)[0].split('def ria_readback()',1)[-1].split('def local_read',1)[-1])


class RiaReadbackEvidenceTest(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory(prefix='ria-controller-native-')
        self.addCleanup(self.temp.cleanup)
        self.root=Path(self.temp.name).resolve()
        self.sessions=self.root/'sessions';self.sessions.mkdir()
        self.cache=self.root/'anytour-anex-apd-v1'
        (self.cache/'2026-10-28').mkdir(parents=True)
        self.ns={'home':self.root,'pathlib':__import__('pathlib'),'hashlib':hashlib,'json':json,'re':re,
            'subprocess':subprocess,'time':__import__('time')}
        exec(extension.REMOTE_RIA_READER,self.ns)
        self.public={'path_scope':'single_fpm_default_candidate_only','vhost_pool_authority':False,
            'session_root_sha256':'a'*64,'cache_root_sha256':'b'*64,'serializer':'php',
            'cache_path_basis':'fpm_default_configured_candidate','other_roots_examined':0}
        self.ns['ria_runtime_scope']=lambda:(self.sessions,'php',self.cache,self.public)

    @staticmethod
    def php(value):
        if value is None:return b'N;'
        if type(value) is bool:return b'b:'+str(int(value)).encode()+b';'
        if type(value) is int:return b'i:'+str(value).encode()+b';'
        if isinstance(value,str):
            raw=value.encode();return b's:'+str(len(raw)).encode()+b':"'+raw+b'";'
        if isinstance(value,list):value=dict(enumerate(value))
        if isinstance(value,dict):return b'a:'+str(len(value)).encode()+b':{'+b''.join(RiaReadbackEvidenceTest.php(k)+RiaReadbackEvidenceTest.php(v) for k,v in value.items())+b'}'
        raise ValueError('fixture_type')

    def write_case(self):
        program,currency='778','1'
        digest=hashlib.sha256('\0'.join((program,currency,'2026-11-16','7')).encode()).hexdigest()
        offer={'kind':'concrete','hotel':{'name':'RIA SUITES HOTEL'},'room':'Standard Room','meal':'BB',
            'checkin':'2026-11-16','checkout':'2026-11-23','nights':7,'adults':2,'children':0,
            'infants':None,'price':{'amount':'73690','currency':'RUB'},'flight_type':'regular'}
        context={'gateway':{'saved_offers':{'offers':{'PRIVATE_OFFER':{'offer':offer,
            'supplier_tour_program_id':program,'supplier_currency_id':currency}}}},
            'additional_prices':{digest:{'status':'complete','evidence':{'observed_at':'2026-10-28T00:09:02Z','rows':[],
            'total_count':0,'truncated':False}}}}
        session={'canary':'NEVER_EXPORT_CREDENTIAL','anex_offer_contexts':{'PRIVATE_SEARCH':context}}
        raw=b''.join(key.encode()+b'|'+self.php(value) for key,value in session.items())
        target=self.sessions/('sess_'+'a'*26);target.write_bytes(raw)
        os.utime(target,(1793146140,1793146140))
        entry={'version':1,'day':'2026-10-28','context_digest':digest,'status':'complete','payload':{'data':[],'totalCount':0}}
        (self.cache/'2026-10-28'/(digest+'.json')).write_text(json.dumps(entry))
        return target

    def snapshot(self):
        return {str(p.relative_to(self.root)):(p.read_bytes(),p.stat().st_mtime_ns,p.stat().st_mode) for p in self.root.rglob('*') if p.is_file()}

    def test_real_embedded_reader_matches_one_case_without_mutation_or_exports(self):
        self.write_case();before=self.snapshot()
        receipt=self.ns['ria_readback']()
        self.assertEqual('retained_case_matched',receipt['status'])
        self.assertEqual('regular',receipt['flight_type'])
        self.assertTrue(receipt['cache']['matches_original_empty'])
        self.assertEqual(before,self.snapshot())
        self.assertEqual('not_established',receipt['upstream_empty_reason'])
        self.assertFalse(receipt['scope']['vhost_pool_authority'])
        output=json.dumps(receipt)
        for canary in ('NEVER_EXPORT_CREDENTIAL','PRIVATE_OFFER','PRIVATE_SEARCH','RIA SUITES','Standard Room','73690','"778"','"1"',str(self.root)):
            self.assertNotIn(canary,output)
        self.assertEqual(0,receipt['supplier_requests'])
        self.assertEqual(0,receipt['database_operations'])
        self.assertEqual(0,receipt['runtime_session_cache_writes'])

    def test_no_match_is_only_a_bounded_candidate_result(self):
        receipt=self.ns['ria_readback']()
        self.assertEqual('hold',receipt['status'])
        self.assertEqual('no_case_in_candidate_root',receipt['reason'])
        self.assertFalse(receipt['scope']['vhost_pool_authority'])

    def test_wrong_reader_pin_and_private_access_errors_are_sanitized(self):
        source=self.ns['RIA_PINNED_READER']
        self.ns['RIA_PINNED_READER']=source+'\n'
        self.assertEqual('reader_source_drift',self.ns['ria_readback']()['reason'])
        self.ns['RIA_PINNED_READER']=source
        self.ns['ria_runtime_scope']=mock.Mock(side_effect=OSError('NEVER_EXPORT_CREDENTIAL/private/path'))
        receipt=self.ns['ria_readback']()
        self.assertEqual('retained_file_access_or_shape',receipt['reason'])
        self.assertNotIn('NEVER_EXPORT_CREDENTIAL',json.dumps(receipt))

    def test_fixed_dispatch_checks_source_before_reading_and_preserves_runtime(self):
        self.write_case();before=self.snapshot()
        self.ns.update(mode=extension.RIA_MODE,operation=extension.RIA_OPERATION,source=extension.RIA_SOURCE,
            result={},before={'protected':'a'*64},fingerprints=lambda:{'protected':'a'*64},
            fail=lambda code:(_ for _ in ()).throw(ValueError(code)))
        exec(__import__('textwrap').dedent(extension.REMOTE_RIA_DISPATCH),self.ns)
        self.assertEqual('complete',self.ns['result']['status'])
        self.assertEqual('retained_case_matched',self.ns['result']['anex_ria_apd_readback']['status'])
        self.assertEqual(before,self.snapshot())
        for field in ('supplier_calls','database_reads','database_writes','runtime_writes','session_writes','cache_writes'):
            self.assertEqual(0,self.ns['result'][field])
        self.ns['source']='a'*40
        self.ns['ria_readback']=mock.Mock(side_effect=AssertionError('reader must not run'))
        with self.assertRaisesRegex(ValueError,'ria_sealed_scope'):exec(__import__('textwrap').dedent(extension.REMOTE_RIA_DISPATCH),self.ns)
        self.ns['ria_readback'].assert_not_called()

    def test_fpm_parser_discards_every_other_phpinfo_field(self):
        parser=self.ns['ria_fpm_values']
        text='SECRET => NEVER_EXPORT_CREDENTIAL => private\nsession.save_path => /tmp => /tmp\nsession.serialize_handler => php => php\nsys_temp_dir => no value => no value\n'
        values=parser(text)
        self.assertEqual({'session.save_path','session.serialize_handler','sys_temp_dir'},set(values))
        with self.assertRaisesRegex(RuntimeError,'metadata_format'):parser(text+'session.save_path => /tmp => /tmp\n')
        with self.assertRaisesRegex(RuntimeError,'metadata_format'):parser(text.replace('php => php','php => php_serialize'))

    def test_candidate_paths_are_absolute_closed_and_not_vhost_authority(self):
        candidate=self.ns['ria_candidate_path']
        for path in ('/tmp','/var/tmp','/var/lib/php/sessions'):
            self.assertEqual(path,candidate(path,'session').as_posix())
        for path in ('relative','/etc','/tmp/../etc','/tmp//../etc','/tmp\nsecret','https://example.test','/other/account/sessions'):
            with self.assertRaises(RuntimeError):candidate(path,'session')

    def test_discovery_uses_only_fixed_fpm_and_optional_clean_php_introspection(self):
        namespace=dict(self.ns)
        exec(extension.REMOTE_RIA_READER,namespace)
        calls=[]
        info='session.save_path => /tmp => /tmp\nsession.serialize_handler => php => php\nsys_temp_dir => no value => no value\n'
        namespace['ria_system_binary']=lambda path:path
        def capture(argv,maximum,timeout=10):
            calls.append((argv,maximum))
            return info if argv[-1]=='-i' else '/var/tmp'
        namespace['ria_capture']=capture
        sessions,serializer,cache,scope=namespace['ria_runtime_scope']()
        self.assertEqual('/tmp',sessions.as_posix())
        self.assertEqual('php',serializer)
        self.assertEqual('/var/tmp/anytour-anex-apd-v1',cache.as_posix())
        self.assertEqual([['/usr/sbin/php-fpm8.3','-i'],['/usr/bin/php8.3','-n','-d','allow_url_fopen=0','-d','auto_prepend_file=',
            '-d','auto_append_file=','-d','opcache.enable_cli=0','-r','echo sys_get_temp_dir();']],[call[0] for call in calls])
        self.assertEqual([1024*1024,4096],[call[1] for call in calls])
        self.assertFalse(scope['vhost_pool_authority'])
        self.assertEqual('php_system_default_candidate',scope['cache_path_basis'])
        self.assertNotIn('/tmp',json.dumps(scope))

    def test_metadata_process_has_a_real_byte_limit_and_timeout(self):
        capture=self.ns['ria_capture']
        self.assertEqual('bounded',capture([sys.executable,'-c',"print('bounded',end='')"],20))
        with self.assertRaisesRegex(RuntimeError,'metadata_output_limit'):
            capture([sys.executable,'-c',"print('X'*4096,end='')"],32)
        with self.assertRaisesRegex(RuntimeError,'metadata_timeout'):
            capture([sys.executable,'-c','import time; time.sleep(2)'],32,.1)


RIA2_HISTORICAL_HASHES = {'andromeda-firstpage-readback': 'c38143365593dfa26561b634670838c310d1a9dfdc9c1489ce3b4408ae2c6b38', 'andromeda-initial-failure-readback': '79f83c07b4e50545701ee0df620db89b14d8b01e282925cb85b478556e5f0576', 'andromeda-continuation-failure-readback': '4a6fc777fcba082d55803cb5c6a3cee74af94d8baad064a5ef598a2935356467', 'anex-ria-apd-retained-readback': 'ab4fd68c601592b1610f5b98e4e475ea6ecf6d50fa45d13cb714ea018ac91fb7'}

class Ria2CommandTest(unittest.TestCase):
    def setUp(self):
        self.core=load('int_server_executor');extension.register_parser(self.core)
        self.command={'source_sha':extension.RIA2_SOURCE,'mode':extension.RIA2_MODE,'operation_id':extension.RIA2_OPERATION}
        self.body=self.core.PREFIX+' '.join(self.command[key] for key in ('source_sha','mode','operation_id'))

    def test_exact_new_principal_mode_has_no_arbitrary_user_path_or_payload(self):
        self.assertEqual(self.command,self.core.parse_command(self.body))
        for body in (self.body+' root',self.body+' --session-root=/tmp',self.body.replace(extension.RIA2_SOURCE,'a'*40),
                     self.body.replace(extension.RIA2_OPERATION,extension.RIA_OPERATION)):
            with self.assertRaises(ValueError):self.core.parse_command(body)
        wrapper=load('int_server_executor_anex_secret_transport')
        self.assertEqual(self.command,wrapper.core.parse_command(self.body))
        self.assertNotIn(extension.RIA2_MODE,wrapper.DIRECT_ANEX_MODES)
        self.assertNotIn(extension.RIA2_MODE,wrapper.SUPPLIER_SLOT_MODES)

    def test_all_four_consumed_remote_outputs_are_byte_equal(self):
        for mode,digest in RIA2_HISTORICAL_HASHES.items():
            self.assertEqual(digest,hashlib.sha256(extension.remote_with_readback(self.core,mode).encode()).hexdigest(),mode)

    def test_new_mode_keeps_same_reservation_and_excludes_both_collectors(self):
        import ast
        before=self.core.bundle_source;extension.activate(self.core,self.command)
        self.assertIs(before,self.core.bundle_source)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",self.core.REMOTE)
        self.assertLess(self.core.REMOTE.index("(op/'reservation.json').write_text"),self.core.REMOTE.index("if mode=='anex-ria-apd-fpm-readback':"))
        values=[]
        for node in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(node,ast.Compare) and isinstance(node.left,ast.Name) and node.left.id=='mode' and len(node.ops)==1 and isinstance(node.ops[0],ast.NotIn):
                seq=node.comparators[0]
                if isinstance(seq,ast.Tuple) and any(isinstance(x,ast.Constant) and x.value=='reconcile' for x in seq.elts):
                    values.append(eval(compile(ast.Expression(node),'scope','eval'),{'mode':extension.RIA2_MODE}))
        self.assertEqual([False,False],values)


class Ria2EvidenceTest(RiaReadbackEvidenceTest):
    def setUp(self):
        super().setUp()
        exec(extension.REMOTE_RIA2_READER,self.ns)
        self.metadata_select=self.ns['ria2_select_actor']
        self.runner=self.ns['ria2_run_child']
        self.ns['ria2_select_actor']=lambda *args:'current'

    def module(self):return self.ns['ria2_module']()

    def child(self,payload,actor=True):
        import io
        child={'__name__':'child_fixture'}
        exec(self.ns['RIA2_CHILD_SOURCE'],child)
        child['sys']=types.SimpleNamespace(stdin=types.SimpleNamespace(buffer=io.BytesIO(payload)))
        child['child_actor']=lambda:actor
        child['child_deadline']=lambda deadline:None
        result=child['child_main']()
        result.update(child_actor_verified=child['ACTOR_VERIFIED'],child_principal='www-data')
        return result

    def test_current_actor_reads_once_and_permission_failure_never_retries_as_fpm(self):
        self.write_case();namespace=self.module();original=namespace['inspect'];calls=[]
        def inspect(*args):calls.append('current');return original(*args)
        namespace['inspect']=inspect;self.ns['ria2_module']=lambda:namespace
        self.ns['ria2_run_child']=mock.Mock(side_effect=AssertionError('no second actor'))
        receipt=self.ns['ria2_readback']()
        self.assertEqual('retained_case_matched',receipt['status']);self.assertEqual(['current'],calls)
        self.assertEqual('current_principal',receipt['actor'])
        def denied(*args):raise namespace['DiagnosticHold']('retained_permission_denied','permission_denied','session_file')
        namespace['inspect']=denied
        receipt=self.ns['ria2_readback']()
        self.assertEqual('hold',receipt['status']);self.assertEqual('permission_denied',receipt['failure_class'])
        self.assertEqual('session_file',receipt['stage']);self.ns['ria2_run_child'].assert_not_called()

    def test_metadata_selects_one_actor_before_any_session_content(self):
        self.write_case();namespace=self.module()
        namespace['read_file']=mock.Mock(side_effect=AssertionError('metadata must not read bytes'))
        namespace['inspect']=mock.Mock(side_effect=AssertionError('metadata must not inspect bytes'))
        self.ns['ria2_effective_access']=lambda path,directory=False,**kw:True
        self.assertEqual('current',self.metadata_select(namespace,self.sessions,self.cache))
        self.ns['ria2_effective_access']=lambda path,directory=False,**kw:directory
        self.assertEqual('default_fpm_www_data',self.metadata_select(namespace,self.sessions,self.cache))
        namespace['read_file'].assert_not_called();namespace['inspect'].assert_not_called()

    def test_cache_directories_need_traversal_not_listing_before_current_actor(self):
        self.write_case();namespace=self.module();calls=[]
        import os
        def access(path,flags,effective_ids=False):
            calls.append((Path(path),flags,effective_ids))
            if Path(path) in (self.cache,self.cache/'2026-10-28'):return flags==os.X_OK
            return True
        with mock.patch.object(os,'access',access),mock.patch.object(os,'supports_effective_ids',{access}):
            self.assertEqual('current',self.metadata_select(namespace,self.sessions,self.cache))
        self.assertTrue(all(item[2] for item in calls))
        self.assertTrue(all(flags==os.X_OK for path,flags,ids in calls if path in (self.cache,self.cache/'2026-10-28')))

    def test_fpm_actor_executes_only_child_reader_and_sanitized_receipt(self):
        self.write_case();before=self.snapshot();namespace=self.module()
        namespace['inspect']=mock.Mock(side_effect=AssertionError('parent session read forbidden'))
        self.ns['ria2_module']=lambda:namespace
        self.ns['ria2_select_actor']=lambda *args:'default_fpm_www_data'
        self.ns['ria2_fpm_actor']=lambda n:{'actor_basis':'fixed_root_owned_default_www_pool','sudo_rights_provisioned':False,'root_fallback':False}
        calls=[]
        def execute(payload,deadline):calls.append(payload);return json.dumps(self.child(payload)).encode()
        self.ns['ria2_run_child']=execute
        receipt=self.ns['ria2_readback']()
        self.assertEqual('retained_case_matched',receipt['status']);self.assertEqual('configured_fpm_principal',receipt['actor'])
        self.assertNotIn('www-data',json.dumps(receipt));self.assertNotIn('child_principal',receipt)
        self.assertTrue(receipt['child_actor_verified']);self.assertFalse(receipt['root_fallback'])
        self.assertEqual(1,len(calls));namespace['inspect'].assert_not_called();self.assertEqual(before,self.snapshot())
        for token in ('PRIVATE_OFFER','PRIVATE_SEARCH','NEVER_EXPORT_CREDENTIAL','73690',str(self.root)):
            self.assertNotIn(token,json.dumps(receipt))

    def test_fpm_pool_is_fixed_and_unknown_user_or_root_never_authorized(self):
        namespace=self.module();self.ns['ria2_root_owned_path']=mock.Mock(return_value=Path('/fixed'))
        fake=types.SimpleNamespace(pw_name='www-data',pw_uid=33)
        with mock.patch.dict(sys.modules,{'pwd':types.SimpleNamespace(getpwnam=lambda name:fake)}):
            namespace['read_file']=lambda path,limit:b'[www]\nuser = www-data\n'
            info=self.ns['ria2_fpm_actor'](namespace)
            self.assertFalse(info['sudo_rights_provisioned'])
            self.assertEqual(str(Path('/etc/php/8.3/fpm/pool.d/www.conf')),self.ns['ria2_root_owned_path'].call_args_list[0].args[0])
            for body in (b'[www]\nuser=root\n',b'[other]\nuser=www-data\n',b'[www]\nuser=www-data\ninclude=/other\n'):
                namespace['read_file']=lambda path,limit,body=body:body
                with self.assertRaises(Exception):self.ns['ria2_fpm_actor'](namespace)
            namespace['read_file']=lambda path,limit:b'[www]\nuser=www-data\n';fake.pw_uid=0
            with self.assertRaises(Exception):self.ns['ria2_fpm_actor'](namespace)

    def test_missing_or_denied_paths_are_distinct_fixed_stages_without_text(self):
        self.write_case();namespace=self.module();self.ns['ria2_module']=lambda:namespace
        for reason,category,stage in (('retained_permission_denied','permission_denied','session_file'),
            ('retained_path_not_found','not_found','cache_file'),('cache_shape_invalid','evidence_shape','cache_shape')):
            def reject(*args):raise namespace['DiagnosticHold'](reason,category,stage)
            namespace['inspect']=reject
            receipt=self.ns['ria2_readback']()
            self.assertEqual((reason,category,stage),(receipt['reason'],receipt['failure_class'],receipt['stage']))
        self.ns['ria_runtime_scope']=mock.Mock(side_effect=FileNotFoundError('PRIVATE_PATH_NEVER_EXPORT'))
        receipt=self.ns['ria2_readback']();self.assertEqual('not_found',receipt['failure_class'])
        self.assertEqual('metadata_discovery',receipt['stage']);self.assertNotIn('PRIVATE_PATH',json.dumps(receipt))

    def test_child_uid_guard_is_required_and_strict_schema_rejects_leaks(self):
        self.write_case();payload=self.ns['ria2_child_payload'](self.sessions,'php',self.cache,__import__('time').monotonic()+12)
        receipt=self.child(payload,False)
        self.assertEqual('child_actor_mismatch',receipt['reason']);self.assertFalse(receipt['child_actor_verified'])
        self.ns['ria2_validate_child'](self.module(),json.dumps(receipt).encode())
        good=self.child(payload)
        for item in (dict(good,extra='NEVER_EXPORT_CREDENTIAL'),dict(good,observed_at='PRIVATE'),
                     dict(good,child_principal='root'),dict(good,child_actor_verified=False),
                     dict(receipt,case='NEVER_EXPORT_CREDENTIAL')):
            with self.assertRaises(Exception):self.ns['ria2_validate_child'](self.module(),json.dumps(item).encode())

    def fake_process(self,raw=b'{}',code=0):
        import io
        class Input:
            closed=False
            def write(self,value):self.value=value
            def close(self):self.closed=True
        return types.SimpleNamespace(stdin=Input(),stdout=io.BytesIO(raw),poll=lambda:code,wait=lambda timeout:code,
            terminate=mock.Mock(),kill=mock.Mock())

    def test_child_version_cache_consistency_and_reason_class_binding_are_strict(self):
        import copy
        self.write_case();payload=self.ns['ria2_child_payload'](self.sessions,'php',self.cache,__import__('time').monotonic()+12)
        good=self.child(payload);bad=[]
        for version in (True,'1',{'PRIVATE':'NEVER_EXPORT_CREDENTIAL'},2):bad.append(dict(good,schema_version=version))
        for cache in ({'status':'complete'},dict(good['cache'],row_count=1),dict(good['cache'],matches_original_empty=False),
                      dict(good['cache'],sha256=1),dict(good['cache'],row_count=True),
                      {'status':'missing_in_supplied_root','sha256':'a'*64}):
            bad.append(dict(good,cache=cache))
        hold=self.child(payload,False)
        bad.append(dict(hold,failure_class='unsupported_shape'))
        bad.append(dict(hold,child_actor_verified=True))
        bad.append(dict(hold,stage='session_file'))
        for item in bad:
            with self.assertRaises(Exception):self.ns['ria2_validate_child'](self.module(),json.dumps(item).encode())
        accepted=self.ns['ria2_validate_child'](self.module(),json.dumps(good).encode())
        self.assertNotIn('child_principal',accepted)

    def test_existing_sudo_argv_is_closed_isolated_and_never_grants_permissions(self):
        child=self.fake_process(b'{}',0);popen=mock.Mock(return_value=child)
        self.ns['subprocess']=types.SimpleNamespace(Popen=popen,PIPE=subprocess.PIPE,DEVNULL=subprocess.DEVNULL,TimeoutExpired=subprocess.TimeoutExpired)
        self.assertEqual(b'{}',self.runner(b'{}',__import__('time').monotonic()+.1))
        argv=popen.call_args.args[0]
        self.assertEqual(['/usr/bin/sudo','-n','-u','www-data','--','/usr/bin/python3','-I','-S','-B','-c'],argv[:-1])
        self.assertEqual(extension.RIA2_CHILD_SHA256,hashlib.sha256(argv[-1].encode()).hexdigest())
        self.assertEqual('/',popen.call_args.kwargs['cwd'])
        self.assertEqual({'PATH','LANG','LC_ALL','HOME'},set(popen.call_args.kwargs['env']))
        child.terminate.assert_not_called();child.kill.assert_not_called()

    def test_sudo_denial_and_output_cap_do_not_retry_or_leak_stderr(self):
        for raw,code,reason in ((b'PRIVATE_STDOUT',1,'existing_sudo_denied_or_child_unavailable'),
                               (b'X'*32769,0,'child_output_invalid')):
            popen=mock.Mock(return_value=self.fake_process(raw,code))
            self.ns['subprocess']=types.SimpleNamespace(Popen=popen,PIPE=subprocess.PIPE,DEVNULL=subprocess.DEVNULL,TimeoutExpired=subprocess.TimeoutExpired)
            with self.assertRaisesRegex(Exception,reason):
                try:self.runner(b'{}',__import__('time').monotonic()-1)
                except self.ns['Ria2Failure'] as error:raise RuntimeError(error.reason)
            self.assertEqual(1,popen.call_count)

    def test_child_absolute_deadline_precedes_content_and_expires_late_start(self):
        import io
        child={'__name__':'deadline_fixture'};exec(self.ns['RIA2_CHILD_SOURCE'],child)
        events=[]
        class Stop(BaseException):pass
        child['time']=types.SimpleNamespace(monotonic=lambda:100.)
        child['os']=types.SimpleNamespace(_exit=lambda code:(_ for _ in ()).throw(Stop(code)))
        child['signal']=types.SimpleNamespace(SIGALRM=14,ITIMER_REAL=0,signal=lambda *x:events.append(('handler',x)),
            setitimer=lambda *x:events.append(('timer',x)))
        child['child_deadline'](101.)
        self.assertEqual('handler',events[0][0]);self.assertEqual(('timer',(0,1.)),events[1])
        payload=self.ns['ria2_child_payload'](self.sessions,'php',self.cache,99.)
        child['sys']=types.SimpleNamespace(stdin=types.SimpleNamespace(buffer=io.BytesIO(payload)))
        child['child_actor']=mock.Mock(side_effect=AssertionError('no UID/content after deadline'))
        with self.assertRaises(Stop):child['child_main']()
        child['child_actor'].assert_not_called()

    def test_new_dispatch_has_independent_seal_and_preserves_runtime(self):
        self.write_case();before=self.snapshot()
        self.ns.update(mode=extension.RIA2_MODE,operation=extension.RIA2_OPERATION,source=extension.RIA2_SOURCE,
            result={},before={'protected':'a'*64},fingerprints=lambda:{'protected':'a'*64},
            fail=lambda code:(_ for _ in ()).throw(ValueError(code)))
        exec(__import__('textwrap').dedent(extension.REMOTE_RIA2_DISPATCH),self.ns)
        self.assertEqual('complete',self.ns['result']['status']);self.assertEqual(before,self.snapshot())
        self.assertEqual('current_principal',self.ns['result']['anex_ria_apd_fpm_readback']['actor'])
        self.ns['operation']=extension.RIA_OPERATION
        self.ns['ria2_readback']=mock.Mock(side_effect=AssertionError('old operation cannot read'))
        with self.assertRaisesRegex(ValueError,'ria2_sealed_scope'):
            exec(__import__('textwrap').dedent(extension.REMOTE_RIA2_DISPATCH),self.ns)
        self.ns['ria2_readback'].assert_not_called()


RIA3_HISTORICAL_HASHES = {'andromeda-firstpage-readback': 'c38143365593dfa26561b634670838c310d1a9dfdc9c1489ce3b4408ae2c6b38', 'andromeda-initial-failure-readback': '79f83c07b4e50545701ee0df620db89b14d8b01e282925cb85b478556e5f0576', 'andromeda-continuation-failure-readback': '4a6fc777fcba082d55803cb5c6a3cee74af94d8baad064a5ef598a2935356467', 'anex-ria-apd-retained-readback': 'ab4fd68c601592b1610f5b98e4e475ea6ecf6d50fa45d13cb714ea018ac91fb7', 'anex-ria-apd-fpm-readback': 'b351a421932f5e7a84f01db05a1c4b5ce2a4c1541b267eeb929417d3a70c0eea'}

class Ria3CommandTest(unittest.TestCase):
    def setUp(self):
        self.core=load('int_server_executor');extension.register_parser(self.core)
        self.command={'source_sha':extension.RIA3_SOURCE,'mode':extension.RIA3_MODE,'operation_id':extension.RIA3_OPERATION}

    def test_fixed_new_command_and_five_prior_remote_payloads(self):
        body=self.core.PREFIX+' '.join(self.command[k] for k in ('source_sha','mode','operation_id'))
        self.assertEqual(self.command,self.core.parse_command(body))
        for wrong in (body+' /tmp',body+' root',body.replace(extension.RIA3_SOURCE,'a'*40),body.replace(extension.RIA3_OPERATION,extension.RIA2_OPERATION)):
            with self.assertRaises(ValueError):self.core.parse_command(wrong)
        for mode,digest in RIA3_HISTORICAL_HASHES.items():
            self.assertEqual(digest,hashlib.sha256(extension.remote_with_readback(self.core,mode).encode()).hexdigest(),mode)
        wrapper=load('int_server_executor_anex_secret_transport')
        self.assertNotIn(extension.RIA3_MODE,wrapper.DIRECT_ANEX_MODES)
        self.assertNotIn(extension.RIA3_MODE,wrapper.SUPPLIER_SLOT_MODES)

    def test_new_mode_keeps_reservation_no_replay_and_collector_exclusions(self):
        import ast
        bundle=self.core.bundle_source;extension.activate(self.core,self.command)
        self.assertIs(bundle,self.core.bundle_source)
        self.assertIn("if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')",self.core.REMOTE)
        flags=[]
        for n in ast.walk(ast.parse(self.core.REMOTE)):
            if isinstance(n,ast.Compare) and isinstance(n.left,ast.Name) and n.left.id=='mode' and len(n.ops)==1 and isinstance(n.ops[0],ast.NotIn):
                seq=n.comparators[0]
                if isinstance(seq,ast.Tuple) and any(isinstance(x,ast.Constant) and x.value=='reconcile' for x in seq.elts):
                    flags.append(eval(compile(ast.Expression(n),'scope','eval'),{'mode':extension.RIA3_MODE}))
        self.assertEqual([False,False],flags)


class Ria3EvidenceTest(unittest.TestCase):
    write_case=RiaReadbackEvidenceTest.write_case
    snapshot=RiaReadbackEvidenceTest.snapshot
    php=staticmethod(RiaReadbackEvidenceTest.php)
    child=Ria2EvidenceTest.child

    def setUp(self):
        RiaReadbackEvidenceTest.setUp(self)
        exec(extension.REMOTE_RIA2_READER,self.ns)
        self.ns['ria2_select_actor']=lambda *args:'current'
        exec(extension.REMOTE_RIA3_READER,self.ns)
        self.runner=self.ns['ria3_run_child']
        self.ns['RIA3_EXEC_FACTS']={}

    def fake(self,out=b'',err=b'',code=1):
        import io
        class Input:
            closed=False
            def write(self,value):self.value=value
            def close(self):self.closed=True
        return types.SimpleNamespace(stdin=Input(),stdout=io.BytesIO(out),stderr=io.BytesIO(err),
            poll=lambda:code,wait=lambda timeout:code,terminate=mock.Mock(),kill=mock.Mock())

    def subprocess(self,factory):
        self.ns['subprocess']=types.SimpleNamespace(Popen=factory,PIPE=subprocess.PIPE,DEVNULL=subprocess.DEVNULL,TimeoutExpired=subprocess.TimeoutExpired)

    def test_closed_access_and_python_error_classes_never_export_canaries(self):
        classify=self.ns['ria3_failure']
        cases=[(b'sudo: a password is required\n',1,'sudo_password_required'),
            (b'sudo: unable to resolve host PRIVATE_HOST: Name or service not known\nsudo: a password is required\n',1,'sudo_password_required'),
            (b'Sorry, user PRIVATE_USER is not allowed to execute /PRIVATE_PATH as www-data on PRIVATE_HOST.\n',1,'sudo_policy_denied'),
            (b'sudo: sorry, you must have a tty to run sudo\n',1,'sudo_tty_required'),
            (b'sudo: unknown user: www-data\n',1,'sudo_principal_unknown'),
            (b'sudo: unable to execute /usr/bin/python3: Permission denied\n',126,'principal_exec_unavailable'),
            (b'Traceback (most recent call last):\n  File "<string>", line 1\nNameError: PRIVATE_SECRET\n',1,'child_python_nameerror'),
            (b'Traceback (most recent call last):\nValueError: sudo: a password is required\n',1,'child_python_valueerror'),
            (b'PRIVATE_SECRET contains sudo: a password is required',1,'principal_failure_unclassified'),
            (b'sudo: a password is required\nsudo: unknown user www-data',1,'principal_failure_unclassified'),
            ('sudo: требуется пароль PRIVATE_SECRET'.encode(),1,'principal_failure_unclassified'),
            (b'sudo: a password is required',0,'principal_failure_unclassified')]
        for stderr,code,expected in cases:
            result=classify(stderr,code);self.assertEqual(expected,result[0])
            self.assertNotIn('PRIVATE',json.dumps(result));self.assertNotIn('www-data',json.dumps(result))

    def test_valid_zero_receipt_survives_stderr_warning_nonzero_never_succeeds(self):
        self.write_case();before=self.snapshot()
        payload=self.ns['ria2_child_payload'](self.sessions,'php',self.cache,__import__('time').monotonic()+12)
        good=json.dumps(self.child(payload)).encode()
        for code in (0,1):
            process=self.fake(good,b'sudo: harmless PRIVATE_HOST warning\n',code)
            factory=mock.Mock(return_value=process);self.subprocess(factory)
            if code==0:self.assertEqual(good,self.runner(payload,__import__('time').monotonic()-1))
            else:
                with self.assertRaises(Exception) as caught:self.runner(payload,__import__('time').monotonic()-1)
                self.assertEqual('child_receipt_process_nonzero',caught.exception.reason)
            self.assertEqual(1,factory.call_count)
            self.assertEqual(code,self.ns['RIA3_EXEC_FACTS']['returncode'])
            self.assertNotIn('PRIVATE',json.dumps(self.ns['RIA3_EXEC_FACTS']))
        self.assertEqual(before,self.snapshot())

    def test_actual_fixed_argv_and_sanitized_access_hold_without_content_retry(self):
        self.write_case();before=self.snapshot()
        namespace=self.ns['ria2_module']();namespace['inspect']=mock.Mock(side_effect=AssertionError('no current read'))
        self.ns['ria2_module']=lambda:namespace
        self.ns['ria2_select_actor']=lambda *args:'default_fpm_www_data'
        self.ns['ria2_fpm_actor']=lambda namespace:{'sudo_rights_provisioned':False,'root_fallback':False}
        process=self.fake(b'',b'sudo: a password is required\n',1);factory=mock.Mock(return_value=process);self.subprocess(factory)
        counter=[0]
        def clock():counter[0]+=100;return counter[0]
        self.ns['time']=types.SimpleNamespace(monotonic=clock,sleep=lambda n:None)
        original=self.ns['ria2_run_child'];receipt=self.ns['ria3_readback']()
        self.assertIs(original,self.ns['ria2_run_child'])
        self.assertEqual('hold',receipt['status']);self.assertEqual('sudo_password_required',receipt['reason'])
        self.assertEqual(1,factory.call_count);namespace['inspect'].assert_not_called();self.assertEqual(before,self.snapshot())
        argv=factory.call_args.args[0]
        self.assertEqual(['/usr/bin/sudo','-n','-u','www-data','--','/usr/bin/python3','-I','-S','-B','-c'],argv[:-1])
        self.assertEqual(extension.RIA2_CHILD_SHA256,hashlib.sha256(argv[-1].encode()).hexdigest())
        self.assertEqual(subprocess.PIPE,factory.call_args.kwargs['stderr'])
        self.assertEqual({'PATH','LANG','LC_ALL','HOME'},set(factory.call_args.kwargs['env']))
        facts=receipt['principal_diagnostics'];self.assertEqual(1,facts['returncode'])
        self.assertEqual(len(b'sudo: a password is required\n'),facts['stderr_bytes_observed'])
        self.assertNotIn('stderr_sha256',json.dumps(receipt));self.assertNotIn('www-data',json.dumps(receipt))

    def real_producer(self,source,deadline_offset=-1):
        original_popen=subprocess.Popen
        calls=[]
        def factory(argv,**kwargs):
            calls.append(argv)
            return original_popen([sys.executable,'-I','-S','-B','-c',source],**kwargs)
        self.subprocess(factory)
        try:self.runner(b'{}',__import__('time').monotonic()+deadline_offset)
        except self.ns['Ria2Failure'] as error:return error.reason,calls
        raise AssertionError('synthetic stream must not become a receipt')

    def test_both_pipes_fill_simultaneously_without_deadlock(self):
        reason,calls=self.real_producer("import sys;sys.stdout.buffer.write(b'A'*20000);sys.stdout.flush();sys.stderr.buffer.write(b'B'*7000);sys.stderr.flush();sys.exit(1)")
        self.assertEqual('principal_failure_unclassified',reason);self.assertEqual(1,len(calls))
        self.assertEqual(20000,self.ns['RIA3_EXEC_FACTS']['stdout_bytes_observed'])
        self.assertEqual(7000,self.ns['RIA3_EXEC_FACTS']['stderr_bytes_observed'])

    def test_one_stream_overflow_stops_even_when_other_is_silent(self):
        for name,limit in (('stdout',32768),('stderr',8192)):
            reason,calls=self.real_producer("import sys,time;sys."+name+".buffer.write(b'X'*70000);sys."+name+".flush();time.sleep(2)")
            self.assertEqual('principal_'+name+'_limit',reason);self.assertEqual(1,len(calls))
            self.assertEqual(limit+1,self.ns['RIA3_EXEC_FACTS'][name+'_bytes_observed'])

    def test_silent_timeout_and_early_exit_are_bounded_and_not_retried(self):
        reason,calls=self.real_producer('import time;time.sleep(2)',-3)
        self.assertEqual('child_timeout',reason);self.assertEqual(1,len(calls))
        reason,calls=self.real_producer("import sys;sys.stderr.write('sudo: a password is required\\n');sys.exit(1)")
        self.assertEqual('sudo_password_required',reason);self.assertEqual(1,len(calls))

    def test_source_and_operation_guard_precedes_any_capture_or_read(self):
        self.ns.update(mode=extension.RIA3_MODE,operation=extension.RIA2_OPERATION,source=extension.RIA3_SOURCE,
            result={},ria3_readback=mock.Mock(side_effect=AssertionError('no read')),
            fail=lambda code:(_ for _ in ()).throw(ValueError(code)))
        with self.assertRaisesRegex(ValueError,'ria3_sealed_scope'):
            exec(__import__('textwrap').dedent(extension.REMOTE_RIA3_DISPATCH),self.ns)
        self.ns['ria3_readback'].assert_not_called()

class V4OwnedSessionTest(unittest.TestCase):
    def setUp(self):
        self.ns={'os':os,'pathlib':__import__('pathlib'),'hashlib':hashlib,'json':json,'re':re,'time':__import__('time')}
        reader=extension.REMOTE_V4_READER.replace('@@V4_PARSER@@',repr(extension.RIA_PINNED_READER_SOURCE))
        exec(compile(reader,'<v4-test>','exec'),self.ns)
        self.parser={'__name__':'v4-decoder-test'}
        exec(extension.RIA_PINNED_READER_SOURCE,self.parser)
        self.created=self.ns['V4_START']+120
        self.key='anex_online:'+'a'*64
        self.offer={'offer_key':self.key,'kind':'concrete','hotel':{'name':'BEACH SAFARI NUBIAN RESORT','external_id':'101'},'room':'Standard Room','meal':'AI',
            'checkin':'2026-12-24','checkout':'2026-12-31','nights':7,'adults':2,'children':1,'infants':1,
            'price':{'amount':'107581','currency':'RUB'}}
        self.ref='a'*32
        self.context={'generation':1,'gateway':{'saved_offers':{'created_at':self.created,'search_ref':self.ref,
            'search':{'checkin_begin':'2026-12-20','checkin_end':'2026-12-26','nights_from':7,'nights_till':7,
                      'adults':2,'children':1,'child_ages':{0:1}},
            'offers':{self.key:{'offer':self.offer}}}},'package_quotes':{}}
        self.context['gateway']['search']={'context':copy.deepcopy(self.context['gateway']['saved_offers']['search']),
            'offers':{0:{'offer_key':self.key,'kind':'concrete','hotel_external_id':'101'}}}
        self.session={'anex_context_generation':1,'anex_offer_contexts':{self.ref:self.context}}

    def cases(self):return self.ns['v4_target_cases'](self.session,self.parser)

    def test_fixed_parser_and_closed_command(self):
        self.assertEqual(extension.RIA_READER_SHA256,hashlib.sha256(extension.RIA_PINNED_READER_SOURCE.encode()).hexdigest())
        import ast
        tree=ast.parse((ROOT/'scripts/deploy/int_server_executor.py').read_text())
        remote=next(ast.literal_eval(n.value) for n in tree.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='REMOTE' for t in n.targets))
        def reject(body):raise ValueError('ordinary')
        core=types.SimpleNamespace(PREFIX='/run-int-server-v1 ',parse_command=reject,REMOTE=remote)
        extension.register_parser(core)
        body=core.PREFIX+extension.V4_SOURCE+' '+extension.V4_MODE+' '+extension.V4_OPERATION
        command=core.parse_command(body)
        self.assertEqual(command,{'source_sha':extension.V4_SOURCE,'mode':extension.V4_MODE,'operation_id':extension.V4_OPERATION})
        for invalid in (body+' extra',body.replace(extension.V4_SOURCE,'a'*40),body.replace('-v1','-v2')):
            with self.assertRaises(ValueError):core.parse_command(invalid)
        extension.activate(core,command)
        ast.parse(core.REMOTE)
        self.assertIn('operation_exists_no_replay',core.REMOTE)
        self.assertIn('v4_owned_session_readback()',core.REMOTE)
        self.assertLess(len(base64.b64encode(zlib.compress(core.REMOTE.encode(),9)))+1024,65536)
        self.assertNotIn('sudo',extension.REMOTE_V4_READER)

    def test_ini_exact_absolute_no_interpolation_or_sharding(self):
        parse=self.ns['v4_config_value']
        self.assertEqual(parse('session.save_path = "/home/owner/sessions" ; comment'),'/home/owner/sessions')
        self.assertEqual(parse("session.save_path='0;0600;/home/owner/sessions'"),'/home/owner/sessions')
        self.assertIsNone(parse('memory_limit=256M'))
        for value in ('[PATH=/x]\nsession.save_path=/home/x','session.save_path=$HOME/tmp','session.save_path=relative',
                      'session.save_path="2;0600;/home/x"','session.save_path=/home/a\nsession.save_path=/home/b'):
            with self.subTest(value=value),self.assertRaises(ValueError):parse(value)

    def test_exact_retained_case_without_attempt_is_not_started(self):
        result=self.cases()
        self.assertEqual(result,[{'status':'quote_not_started','created_at':self.created,'supplier_stages':[]}])
        self.assertNotIn('PRIVATE_',json.dumps(result))

    def test_original_context_time_and_all_ages_are_required(self):
        saved=self.context['gateway']['saved_offers']
        for key,value in (('created_at',self.ns['V4_START']-1),('created_at',self.ns['V4_END']+1)):
            with self.subTest(value=value):
                saved[key]=value;self.assertEqual(self.cases(),[])
        saved['created_at']=self.created
        for ages in ({0:0},{0:2},{0:True},{0:1,1:8},{}):
            saved['search']['child_ages']=ages;self.assertEqual(self.cases(),[])

    def test_foreign_offer_conditions_and_counts_do_not_match(self):
        for key,value in (('room','Suite'),('meal','BB'),('checkin','2026-12-25'),('adults',True),('children',0),('infants',0)):
            before=self.offer[key]
            with self.subTest(key=key):
                self.offer[key]=value;self.assertEqual(self.cases(),[])
            self.offer[key]=before

    def test_unknown_and_completed_stages_are_retained_without_price_authority(self):
        self.context['package_quotes'][self.key]={'version':2,'stages':{'start':'complete','transports':'unknown'},
            'public':{'status':'quote_unknown','price':{'amount':'999999','currency':'RUB'}},'pairs':{}}
        result=self.cases()[0]
        self.assertEqual(result['status'],'quote_attempt_retained')
        self.assertEqual(result['stored_public_status'],'quote_unknown')
        self.assertEqual(result['supplier_stages'],[{'stage':'start','state':'complete'},{'stage':'transports','state':'unknown'}])
        self.assertNotIn('price',result)

    def test_retained_null_is_unknown_not_absent(self):
        self.context['package_quotes'][self.key]=None
        with self.assertRaisesRegex(ValueError,'quote_reference_or_shape'):self.cases()

    def test_sealed_verified_public_state_grants_no_verified_outcome(self):
        self.context['package_quotes'][self.key]={'version':2,'sealed':True,'public':{'status':'quote_verified'},'stages':{},'pairs':{}}
        result=self.cases()[0]
        self.assertEqual(result['status'],'quote_attempt_retained');self.assertTrue(result['sealed'])
        self.assertEqual(result['stored_public_status'],'quote_verified');self.assertNotIn('price',result)

    def test_opened_config_mode_is_checked_before_content(self):
        facts=types.SimpleNamespace(st_dev=1,st_ino=1,st_uid=7,st_mode=0o100666,st_size=14,
            st_mtime_ns=1,st_ctime_ns=1,st_mtime=1)
        parser=dict(self.parser,no_links=mock.Mock())
        with mock.patch.object(os,'lstat',return_value=facts),mock.patch.object(os,'fstat',return_value=facts), \
                mock.patch.object(os,'open',return_value=41),mock.patch.object(os,'close'),mock.patch.object(os,'read') as read:
            with self.assertRaisesRegex(ValueError,'config_ownership_or_mode'):
                self.ns['v4_owned_read'](Path('/synthetic/.user.ini'),65536,7,parser,config=True)
            read.assert_not_called()

    def test_permission_denial_has_fixed_stage_without_exception_text(self):
        self.ns.update(home=Path('/synthetic'),project=Path('/synthetic/www/anytoour.ru'),runtime=Path('/synthetic/runtime'))
        self.ns['v4_owned_read']=mock.Mock(side_effect=PermissionError('PRIVATE_EXCEPTION'))
        with mock.patch.object(os,'geteuid',return_value=7,create=True):result=self.ns['v4_owned_session_readback']()
        self.assertEqual(result['reason'],'retained_permission_denied');self.assertEqual(result['failure_class'],'permission_denied')
        self.assertEqual(result['stage'],'runtime_api_pin');self.assertNotIn('PRIVATE',json.dumps(result))

    def test_foreign_session_owner_is_denied_before_content(self):
        facts=types.SimpleNamespace(st_dev=1,st_ino=1,st_uid=8,st_mode=0o100644,st_size=14,
            st_mtime_ns=1,st_ctime_ns=1,st_mtime=1)
        parser=dict(self.parser,no_links=mock.Mock())
        with mock.patch.object(os,'lstat',return_value=facts),mock.patch.object(os,'fstat',return_value=facts), \
                mock.patch.object(os,'open',return_value=41),mock.patch.object(os,'close'),mock.patch.object(os,'read') as read:
            with self.assertRaisesRegex(ValueError,'session_ownership'):
                self.ns['v4_owned_read'](Path('/synthetic/sessions/sess_fixed'),65536,7,parser)
            read.assert_not_called()

    def test_reference_in_target_cannot_be_reported_as_missing(self):
        self.context['package_quotes']=self.parser['Ref']()
        with self.assertRaisesRegex(ValueError,'quote_reference_or_shape'):self.cases()

    def test_disconnected_saved_key_or_search_context_cannot_authorize_repeat(self):
        original=copy.deepcopy(self.context)
        mutations=(lambda c:c.update(generation=2),
            lambda c:c['gateway']['saved_offers'].update(search_ref='b'*32),
            lambda c:c['gateway']['search']['context'].update(adults=3),
            lambda c:c['gateway']['search']['offers'][0].update(kind='group_minimum'),
            lambda c:c['gateway']['search']['offers'][0].update(hotel_external_id='102'))
        for mutate in mutations:
            self.context=copy.deepcopy(original);self.session['anex_offer_contexts'][self.ref]=self.context
            mutate(self.context)
            with self.assertRaises(ValueError):self.cases()

    def test_unresolved_second_offer_cannot_be_silently_excluded(self):
        self.context['gateway']['saved_offers']['offers']['PRIVATE_ALIAS']={'offer':self.parser['Ref']()}
        with self.assertRaisesRegex(ValueError,'target_reference'):self.cases()

    def test_same_semantic_tour_is_ambiguous_not_first_match(self):
        key='anex_online:'+'b'*64
        self.context['gateway']['saved_offers']['offers'][key]={'offer':dict(self.offer,offer_key=key)}
        self.context['gateway']['search']['offers'][1]={'offer_key':key,'kind':'concrete','hotel_external_id':'101'}
        self.assertEqual(len(self.cases()),2)

    def test_runtime_pin_hold_has_no_secret_or_supplier_activity(self):
        home=Path('/synthetic');project=home/'www/anytoour.ru';runtime=project/'_preview/search3-anex-candidate'
        self.ns.update(home=home,project=project,runtime=runtime)
        self.ns['v4_owned_read']=mock.Mock(return_value=b'PRIVATE_WRONG_API')
        with mock.patch.object(os,'geteuid',return_value=7,create=True):result=self.ns['v4_owned_session_readback']()
        self.assertEqual(result['reason'],'runtime_api_pin')
        self.assertEqual(self.ns['v4_owned_read'].call_args.kwargs,{'require_owner':False},'only public pinned API source omits the same-UID content restriction')
        self.assertEqual(result['supplier_requests'],0);self.assertFalse(result['actor_escalation'])
        self.assertNotIn('PRIVATE_',json.dumps(result));self.assertNotIn(str(home),json.dumps(result))


class V4MappedConfigTest(unittest.TestCase):
    setUp=V4OwnedSessionTest.setUp

    def mapping(self,text):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        with mock.patch.object(Path,'resolve',autospec=True,side_effect=lambda path,*args,**kwargs:path):
            return self.ns['v4_nginx_target'](self.ns['v4_nginx_parse'](text),Path('/home/owner/www/anytoour.ru'))

    def server(self,extra='',host='anytoour.ru',root='/home/owner/www/anytoour.ru',address='unix:/run/php/php8.3-fpm.sock'):
        return 'server { server_name '+host+'; root "'+root+'"; location ~ \\.php$ { fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass '+address+'; } '+extra+' }'

    def test_exact_vhost_and_literal_socket(self):
        self.assertEqual(self.mapping('http { '+self.server()+' }'),Path('/run/php/php8.3-fpm.sock'))

    def test_redirect_and_same_pool_www_alias_are_not_ambiguous(self):
        redirect='server { server_name anytoour.ru; root /home/owner/www/anytoour.ru; return 301 https://www.anytoour.ru$request_uri; }'
        self.assertEqual(self.mapping(redirect+self.server(host='www.anytoour.ru')),Path('/run/php/php8.3-fpm.sock'))
        self.assertEqual(self.mapping(self.server()+self.server(host='www.anytoour.ru')),Path('/run/php/php8.3-fpm.sock'))

    def test_foreign_host_or_root_is_not_the_application_mapping(self):
        for text in (self.server(host='other.example'),self.server(root='/var/www/other')):
            with self.assertRaisesRegex(ValueError,'vhost_mapping_missing_or_ambiguous'):self.mapping(text)

    def test_ambiguous_pool_alias_php_override_and_variables_fail_closed(self):
        texts=(self.server(extra='location /static { alias /other; }'),self.server(extra='fastcgi_param PHP_VALUE "session.save_path=/other";'),
            self.server(root='$dynamic'),
            self.server()+self.server(host='www.anytoour.ru',address='unix:/run/php/other.sock'))
        for text in texts:
            with self.subTest(text=text),self.assertRaises(ValueError):self.mapping(text)

    def test_endpoint_proxy_exact_override_and_rewrite_cannot_select_unrelated_php(self):
        endpoint='/_preview/search3-anex-candidate/api-anex-search3-preview.php'
        cases=(self.server(extra='location ^~ /_preview/search3-anex-candidate/ { proxy_pass http://other_backend; }'),
               self.server(extra='location = '+endpoint+' { deny all; }'),
               self.server(extra='rewrite ^ /other.php last;'),
               self.server(extra='location ~ ^/_preview/.*$ { proxy_pass http://other_backend; }'))
        # The last regex follows the PHP regex and is irrelevant by Nginx order;
        # put it first to exercise a real matching override.
        cases=cases[:-1]+(cases[-1].replace('location ~ \\.php$', 'location ~ ^/_preview/.*$ { proxy_pass http://other_backend; } location ~ \\.php$'),)
        for text in cases:
            with self.subTest(text=text),self.assertRaises(ValueError):self.mapping(text)

    def test_unrelated_location_does_not_change_selected_php_handler(self):
        text=self.server(extra='location /unrelated { proxy_pass http://other_backend; }')
        self.assertEqual(self.mapping(text),Path('/run/php/php8.3-fpm.sock'))

    def test_unknown_optional_regex_prefix_cannot_be_ignored(self):
        for pattern in ('^/x?.*','/\\.?'):
            text=self.server().replace('location ~ \\.php$', 'location ~ '+pattern+' { proxy_pass http://other_backend; } location ~ \\.php$')
            with self.assertRaises(ValueError):self.mapping(text)

    def test_standard_static_and_hidden_rules_do_not_capture_api(self):
        text=self.server().replace('location ~ \\.php$', 'location ~* \\.(css|js|woff2?)$ { expires 1d; } location ~ /\\.ht { deny all; } location ~ \\.php$')
        self.assertEqual(self.mapping(text),Path('/run/php/php8.3-fpm.sock'))

    def test_only_single_unix_upstream_is_supported(self):
        text='upstream owned_php { server unix:/run/php/php8.3-fpm.sock; }'+self.server(address='owned_php')
        self.assertEqual(self.mapping(text),Path('/run/php/php8.3-fpm.sock'))
        with self.assertRaises(ValueError):self.mapping(self.server(address='127.0.0.1:9000'))

    def test_quoted_comments_and_syntax_limits(self):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        parsed=self.ns['v4_nginx_parse']('server { # comment { ignored\n server_name anytoour.ru; root "/quoted/#name"; }')
        self.assertEqual(parsed[0][2][1][1],['/quoted/#name'])
        for text in ('server {','server_name x','server { root "unterminated; }','a {'*18+'}'*18):
            with self.assertRaises(ValueError):self.ns['v4_nginx_parse'](text)

    def test_pool_identity_and_quoted_session_path(self):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        pools=self.ns['v4_pool_sections']('[www]\nuser=www-data\nlisten=/run/php/php8.3-fpm.sock\nphp_admin_value[session.save_path]="0;0600;/var/lib/php/sessions" ; note')
        self.assertEqual(pools[0]['user'],'www-data')
        self.assertEqual(self.ns['v4_config_value']('session.save_path="'+pools[0]['php_admin_value[session.save_path]']+'"'),'/var/lib/php/sessions')
        for text in ('[www]\nuser=a\nuser=b','[www]\n[www]','[www]\ninclude=/other'):
            with self.assertRaises(ValueError):self.ns['v4_pool_sections'](text)

    def test_fixed_mode_registration_and_carrier_size(self):
        import ast
        tree=ast.parse((ROOT/'scripts/deploy/int_server_executor.py').read_text())
        remote=next(ast.literal_eval(n.value) for n in tree.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='REMOTE' for t in n.targets))
        def reject(body):raise ValueError('ordinary')
        core=types.SimpleNamespace(PREFIX='/run-int-server-v1 ',parse_command=reject,REMOTE=remote)
        extension.register_parser(core)
        body=core.PREFIX+extension.V4_SOURCE+' '+extension.V4_MAP_MODE+' '+extension.V4_MAP_OPERATION
        command=core.parse_command(body)
        for invalid in (body+' /tmp/guess',body.replace(extension.V4_SOURCE,'a'*40),body.replace('-v1','-v2')):
            with self.assertRaises(ValueError):core.parse_command(invalid)
        extension.activate(core,command);ast.parse(core.REMOTE)
        self.assertLess(len(base64.b64encode(zlib.compress(core.REMOTE.encode(),9)))+1024,65536)
        self.assertIn('operation_exists_no_replay',core.REMOTE)
        self.assertNotIn('sudo',extension.REMOTE_V4_MAP_READER)

    @unittest.skipIf(os.name=='nt','POSIX ownership/config cache fixture; canonical CI is Linux')
    def test_config_cache_bounds_and_changes_before_reread(self):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory).resolve();config=root/'owned.conf';config.write_text('server { }');config.chmod(0o600)
            budget={'files':set(),'bytes':0};reader=self.ns['v4_owned_read'];self.ns['v4_owned_read']=mock.Mock(wraps=reader)
            first=self.ns['v4_config_trusted'](config,(root,),os.geteuid(),self.parser,budget)
            second=self.ns['v4_config_trusted'](config,(root,),os.geteuid(),self.parser,budget)
            self.assertEqual(first,second);self.assertEqual(self.ns['v4_owned_read'].call_count,1)
            config.write_text('server { root /changed; }')
            with self.assertRaisesRegex(ValueError,'config_changed'):self.ns['v4_config_trusted'](config,(root,),os.geteuid(),self.parser,budget)
            self.assertEqual(self.ns['v4_owned_read'].call_count,1)

    def test_untrusted_config_path_is_rejected_before_read(self):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        self.ns['v4_owned_read']=mock.Mock()
        with self.assertRaisesRegex(ValueError,'config_path_scope'):
            self.ns['v4_config_trusted'](Path('/outside/secret'),(Path('/allowed'),),7,self.parser,{'files':set(),'bytes':0})
        self.ns['v4_owned_read'].assert_not_called()

    def test_config_growth_cannot_exceed_remaining_budget_before_hold(self):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory).resolve();config=root/'owned.conf';config.write_text('x')
            small=types.SimpleNamespace(st_dev=1,st_ino=1,st_uid=7,st_mode=0o100600,st_size=1,st_mtime_ns=1,st_ctime_ns=1,st_mtime=1)
            big=types.SimpleNamespace(**(vars(small)|{'st_size':262144}))
            parent=types.SimpleNamespace(**(vars(small)|{'st_mode':0o40700,'st_size':0}))
            calls=0
            def lstat(path):
                nonlocal calls
                if Path(path)==config:
                    calls+=1;return small if calls==1 else big
                return parent
            parser=dict(self.parser,no_links=mock.Mock())
            budget={'files':set(),'bytes':2097151}
            with mock.patch.object(Path,'resolve',autospec=True,side_effect=lambda path,*args,**kwargs:path), \
                    mock.patch.object(os,'lstat',side_effect=lstat),mock.patch.object(os,'fstat',return_value=big), \
                    mock.patch.object(os,'open',return_value=41),mock.patch.object(os,'close'),mock.patch.object(os,'read') as read:
                with self.assertRaisesRegex(ValueError,'config_changed|session_total_limit'):
                    self.ns['v4_config_trusted'](config,(root,),7,parser,budget)
                read.assert_not_called()
            self.assertEqual(budget['bytes'],2097151)

    def test_repeated_fpm_include_stops_before_expansion(self):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        root=Path('/fixed-config')
        configs={root/'php-fpm.conf':b'[global]\ninclude=child.conf\ninclude=child.conf\n',
                 root/'child.conf':b'[global]\ninclude=leaf.conf\ninclude=leaf.conf\n',
                 root/'leaf.conf':b'[global]\n'}
        reader=mock.Mock(side_effect=lambda path,*args:(path,configs[path]))
        self.ns['v4_config_trusted']=reader
        self.ns['v4_config_matches']=lambda pattern,*args:[pattern]
        with self.assertRaisesRegex(ValueError,'config_include_repeated'):
            self.ns['v4_fpm_config_load'](root,7,self.parser,{'files':set(),'bytes':0})
        self.assertEqual(reader.call_count,4)

    @unittest.skipIf(os.name=='nt','POSIX ownership/include fixture; canonical CI is Linux')
    def test_only_pools_in_main_fpm_include_graph_are_admitted(self):
        exec(extension.REMOTE_V4_MAP_READER,self.ns)
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory).resolve();(root/'pool.d').mkdir();(root/'pool.d').chmod(0o700)
            (root/'php-fpm.conf').write_text('[global]\ninclude='+str(root/'pool.d/active.conf')+'\n')
            (root/'pool.d/active.conf').write_text('[active]\nuser=www-data\nlisten=/run/php/active.sock\n')
            (root/'pool.d/inactive.conf').write_text('[inactive]\nuser=other\nlisten=/run/php/other.sock\n')
            for path in root.rglob('*.conf'):path.chmod(0o600)
            pools=self.ns['v4_fpm_config_load'](root,os.geteuid(),self.parser,{'files':set(),'bytes':0})
            self.assertEqual([pool['listen'] for pool in pools if 'listen' in pool],['/run/php/active.sock'])




def before_v4_continuation_source(source):
    source=source.split('\n# Original-attempt continuation:',1)[0]
    source=source.replace("V4_CGI_CONT_MODE = 'anex-v4-cgi-retained-continuation'\nV4_CGI_CONT_OPERATION = 'int-anex-v4-cgi-retained-continuation-20261110-v1'\n",'')
    source=source.replace("         V4_CGI_MODE, V4_CGI_RESTORE_MODE, V4_CGI_CONT_MODE)","         V4_CGI_MODE, V4_CGI_RESTORE_MODE)")
    source=source.replace("            V4_CGI_CONT_MODE: (V4_SOURCE, V4_CGI_CONT_OPERATION),\n",'')
    return source.replace("    if mode == V4_CGI_CONT_MODE: return remote_with_v4_cgi_continuation(core)\n",'')

class V4CgiCommandTest(unittest.TestCase):
    def test_closed_commands_keep_stock_guards_and_every_prior_program(self):
        import ast
        source=before_v4_continuation_source((ROOT/'scripts/deploy/int_server_executor_firstpage_readback.py').read_text())
        baseline=source.split('\n# One new runtime-bound read;',1)[0]
        current="""V4_CGI_MODE = 'anex-v4-cgi-retained-readback'
V4_CGI_OPERATION = 'int-anex-v4-cgi-retained-20261110-v1'
V4_CGI_RESTORE_MODE = 'anex-v4-cgi-restore-only'
V4_CGI_RESTORE_OPERATION = 'int-anex-v4-cgi-restore-20261110-v1'
MODES = (MODE, FAILURE_MODE, CONTINUATION_MODE, RIA_MODE, RIA2_MODE, RIA3_MODE, V4_MODE, V4_MAP_MODE,
         V4_CGI_MODE, V4_CGI_RESTORE_MODE)"""
        prior="MODES = (MODE, FAILURE_MODE, CONTINUATION_MODE, RIA_MODE, RIA2_MODE, RIA3_MODE, V4_MODE, V4_MAP_MODE)"
        baseline=baseline.replace(current,prior).replace(
            "            V4_CGI_MODE: (V4_SOURCE, V4_CGI_OPERATION),\n            V4_CGI_RESTORE_MODE: (V4_SOURCE, V4_CGI_RESTORE_OPERATION),\n",'').replace(
            "    if mode in (V4_CGI_MODE, V4_CGI_RESTORE_MODE): return remote_with_v4_cgi_readback(core, mode)\n",'')
        raw=baseline.encode()
        self.assertEqual(hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest(),
                         '572f1578fc94431df740a5d099f580b76478146f')
        old=types.ModuleType('frozen_before_cgi');exec(compile(baseline,'<frozen-before-cgi>','exec'),old.__dict__)
        for mode in old.MODES:
            core=load('int_server_executor')
            self.assertEqual(old.remote_with_readback(core,mode),extension.remote_with_readback(core,mode))
        for mode,operation in ((extension.V4_CGI_MODE,extension.V4_CGI_OPERATION),
                               (extension.V4_CGI_RESTORE_MODE,extension.V4_CGI_RESTORE_OPERATION)):
            core=load('int_server_executor');extension.register_parser(core)
            body=core.PREFIX+extension.V4_SOURCE+' '+mode+' '+operation
            command=core.parse_command(body)
            for wrong in (body+' /tmp/guess',body.replace(extension.V4_SOURCE,'a'*40),
                          body.replace(operation,operation+'-retry'),body.replace(operation,extension.V4_OPERATION)):
                with self.assertRaises(ValueError):core.parse_command(wrong)
            extension.activate(core,command);ast.parse(core.REMOTE)
            self.assertIn('operation_exists_no_replay',core.REMOTE)
            self.assertLess(len(base64.b64encode(zlib.compress(core.REMOTE.encode(),9)))+1024,65536)
            self.assertIn("data=b'',method='POST'",core.REMOTE)
            self.assertNotIn('sudo',extension.REMOTE_V4_CGI_READER)
            self.assertNotIn('tv_token',extension.REMOTE_V4_CGI_READER)
            wrapper=load('int_server_executor_anex_secret_transport')
            self.assertNotIn(mode,wrapper.SUPPLIER_SLOT_MODES)
            self.assertNotIn(mode,wrapper.DIRECT_ANEX_MODES)


@unittest.skipIf(os.name=='nt','POSIX actor, locks and atomic replacement; canonical CI is Linux')
class V4CgiEvidenceTest(unittest.TestCase):
    def setUp(self):
        import ast
        V4OwnedSessionTest.setUp(self)
        exec(extension.REMOTE_V4_CGI_READER,self.ns)
        self.temp=tempfile.TemporaryDirectory(prefix='v4-cgi-unit-');self.addCleanup(self.temp.cleanup)
        self.root=Path(self.temp.name).resolve()
        self.runtime=self.root/'www/anytoour.ru/_preview/search3-anex-candidate';self.runtime.mkdir(parents=True)
        self.private=self.root/'.anytoour-int-executor';self.private.mkdir(mode=0o700)
        self.op=self.private/extension.V4_CGI_OPERATION;self.op.mkdir(mode=0o700)
        self.sessions=self.root/'sessions';self.sessions.mkdir(mode=0o700)
        self.actor=os.geteuid()
        self.api=self.runtime/'api-anex-search3-preview.php'
        self.original=self.ns['V4_CGI_HEADER']+b"echo 'normal-original';\n"
        self.api.write_bytes(self.original);self.api.chmod(0o644)
        self.ns.update(home=self.root,project=self.root/'www/anytoour.ru',runtime=self.runtime,
            private=self.private,op=self.op,source=extension.V4_SOURCE,operation=extension.V4_CGI_OPERATION,
            base64=base64,subprocess=subprocess)
        self.ns['V4_CGI_API_SHA']=hashlib.sha256(self.original).hexdigest()
        remote=load('int_server_executor').REMOTE
        node=next(n for n in ast.parse(remote).body if isinstance(n,ast.FunctionDef) and n.name=='stage_target_bytes')
        exec(compile(ast.Module(body=[node],type_ignores=[]),'<stock-stage>','exec'),self.ns)
        self.private_write('reservation.json',{'operation_id':extension.V4_CGI_OPERATION,
            'source_sha':extension.V4_SOURCE,'mode':extension.V4_CGI_MODE,'reserved_at':int(__import__('time').time())})
        (self.runtime/'.anex-private.php').write_text("<?php\ndefine('SAFE_LITERAL','value');\n")
        (self.runtime/'.anex-private.php').chmod(0o600)

    def private_write(self,name,value):
        path=self.op/name
        raw=value if isinstance(value,bytes) else json.dumps(value,separators=(',',':')).encode()
        path.write_bytes(raw);path.chmod(0o600);return path

    def root_identity(self):
        st=self.sessions.stat();return [st.st_dev,st.st_ino,st.st_uid,st.st_gid,st.st_mode]

    def prepare(self):
        original_identity=self.ns['v4_cgi_identity'](self.api,self.actor,self.parser)
        wrapper=self.original+b'# harmless fixture wrapper\n'
        self.private_write('cgi-api-original.php',self.original)
        self.private_write('cgi-gate',b'OPEN\n'+b'a'*64+b'\n')
        self.api.write_bytes(wrapper)
        intent={'schema_version':1,'parent_operation':extension.V4_CGI_OPERATION,'source_sha':extension.V4_SOURCE,
            'original_sha256':self.ns['V4_CGI_API_SHA'],'wrapper_sha256':hashlib.sha256(wrapper).hexdigest(),
            'wrapper_identity':self.ns['v4_cgi_identity'](self.api,self.actor,self.parser),
            'original_identity':original_identity,'nonce_hash':'a'*64,'deadline':int(__import__('time').time())+60}
        self.private_write('cgi-intent.json',intent);return intent

    def test_actual_private_read_checks_expected_stat_and_permissions(self):
        path=self.private_write('fixed.json',b'bounded')
        self.assertEqual(b'bounded',self.ns['v4_cgi_read'](path,self.actor,self.parser,16,0o600))
        path.chmod(0o644)
        with self.assertRaisesRegex(ValueError,'v4_cgi_file_contract'):
            self.ns['v4_cgi_read'](path,self.actor,self.parser,16,0o600)

    def test_guarded_restore_and_interrupted_restore_only_never_read_sessions(self):
        self.prepare()
        http=mock.Mock(side_effect=AssertionError('restore must not use HTTP'))
        self.ns['v4_cgi_http']=http
        self.ns['v4_cgi_retained']=mock.Mock(side_effect=AssertionError('restore must not read sessions'))
        self.ns['operation']=extension.V4_CGI_RESTORE_OPERATION
        result=self.ns['v4_cgi_restore_only']()
        self.assertEqual('restored_only',result['status'],result)
        self.assertTrue(result['api_restored'],result);self.assertEqual(self.original,self.api.read_bytes())
        self.assertEqual(b'CLOSED\n',(self.op/'cgi-gate').read_bytes())
        self.assertEqual(0,result['session_content_reads']);http.assert_not_called()
        self.ns['v4_cgi_retained'].assert_not_called()

    def test_foreign_writer_is_never_overwritten_even_with_same_file_inode(self):
        self.prepare();self.api.write_bytes(b'foreign-writer-canary')
        with self.assertRaisesRegex(ValueError,'v4_cgi_restore_foreign_writer'):
            self.ns['v4_cgi_restore'](self.op,self.actor,self.parser)
        self.assertEqual(b'foreign-writer-canary',self.api.read_bytes())

    def test_failed_probe_restores_original_before_any_metadata_or_session_read(self):
        self.ns['v4_cgi_dependencies']=mock.Mock()
        self.ns['v4_cgi_http']=mock.Mock(side_effect=ValueError('PRIVATE_HTTP_ERROR_CANARY'))
        self.ns['v4_cgi_metadata']=mock.Mock(side_effect=AssertionError('must not read metadata after failed POST'))
        self.ns['v4_cgi_retained']=mock.Mock(side_effect=AssertionError('must not read sessions'))
        with mock.patch.object(subprocess,'run',return_value=types.SimpleNamespace(returncode=0)), \
                mock.patch.object(self.ns['time'],'sleep'):
            result=self.ns['v4_cgi_readback']()
        self.assertTrue(result['api_restored'],result);self.assertEqual(self.original,self.api.read_bytes())
        self.assertEqual(2,result['runtime_probe_writes'])
        self.assertEqual('unverified',result['cgi_startup_side_effects'])
        self.assertNotIn('PRIVATE_HTTP_ERROR_CANARY',json.dumps(result))
        self.ns['v4_cgi_http'].assert_called_once()
        self.ns['v4_cgi_metadata'].assert_not_called();self.ns['v4_cgi_retained'].assert_not_called()

    def test_gate_lock_timeout_does_not_restore_or_read_content(self):
        import fcntl
        self.prepare();before=self.api.read_bytes()
        with mock.patch.object(fcntl,'flock',side_effect=BlockingIOError), \
                mock.patch.object(self.ns['time'],'monotonic',side_effect=[0,11]):
            with self.assertRaisesRegex(ValueError,'v4_cgi_gate_close_timeout'):
                self.ns['v4_cgi_restore'](self.op,self.actor,self.parser)
        self.assertEqual(before,self.api.read_bytes())
        self.assertTrue((self.op/'cgi-gate').read_bytes().startswith(b'OPEN\n'))

    def metadata(self,intent):
        cfg=(self.runtime/'.anex-private.php').read_bytes()
        return {'schema_version':1,'operation_id':extension.V4_CGI_OPERATION,'nonce_hash':intent['nonce_hash'],
            'at':intent['deadline']-30,'uid':self.actor,'sapi':'cgi-fcgi','script':str(self.api),
            'script_sha256':intent['wrapper_sha256'],
            'startup':{'session_status':1,'auto_start':'0','prepend_empty':True,'append_empty':True},
            'config_contract':True,'config_hashes':{'outer':hashlib.sha256(cfg).hexdigest()},
            'save_handler':'files','save_path':str(self.sessions),'serializer':'php',
            'effective_path':str(self.sessions),'path_basis':'cgi_files_configured','php_version_id':80300,
            'session_root':{'path':str(self.sessions),'identity':self.root_identity()}}

    def test_actual_serializer_and_cgi_root_namespace_are_required(self):
        intent=self.prepare();metadata=self.metadata(intent)
        self.private_write('cgi-metadata.json',metadata)
        root,serializer,identity=self.ns['v4_cgi_metadata'](self.op,intent,self.actor,self.parser)
        self.assertEqual((root,serializer,identity),(self.sessions,'php',self.root_identity()))
        variants=[('serializer','php_binary','serializer'),('save_handler','redis','handler'),
            ('save_path','','path'),('config_contract',False,'config')]
        for field,value,reason in variants:
            bad=copy.deepcopy(metadata);bad[field]=value;self.private_write('cgi-metadata.json',bad)
            with self.subTest(field=field),self.assertRaisesRegex(ValueError,reason):
                self.ns['v4_cgi_metadata'](self.op,intent,self.actor,self.parser)
        bad=copy.deepcopy(metadata);bad['session_root']['identity'][1]+=1
        self.private_write('cgi-metadata.json',bad)
        with self.assertRaisesRegex(ValueError,'namespace'):
            self.ns['v4_cgi_metadata'](self.op,intent,self.actor,self.parser)

    def test_empty_default_uses_only_attested_cgi_tempdir_basis_and_namespace(self):
        intent=self.prepare();metadata=self.metadata(intent)
        metadata.update(save_path='',path_basis='cgi_files_empty_path_runtime_tempdir',php_version_id=80323)
        self.private_write('cgi-metadata.json',metadata)
        self.assertEqual(self.sessions,self.ns['v4_cgi_metadata'](self.op,intent,self.actor,self.parser)[0])
        for fields in ({'path_basis':'guessed_tmp'},{'php_version_id':80200},{'effective_path':''},
                       {'save_path':' '},{'save_path':'0;0600;'}):
            bad=dict(metadata,**fields);self.private_write('cgi-metadata.json',bad)
            with self.subTest(fields=fields),self.assertRaises(ValueError):
                self.ns['v4_cgi_metadata'](self.op,intent,self.actor,self.parser)
        bad=copy.deepcopy(metadata);bad['session_root']['identity'][1]+=1
        self.private_write('cgi-metadata.json',bad)
        with self.assertRaisesRegex(ValueError,'namespace'):
            self.ns['v4_cgi_metadata'](self.op,intent,self.actor,self.parser)

    def test_startup_session_or_prepend_is_not_declared_zero(self):
        intent=self.prepare();metadata=self.metadata(intent)
        for field,value in [('session_status',2),('auto_start','1'),('prepend_empty',False),('append_empty',False)]:
            bad=copy.deepcopy(metadata);bad['startup'][field]=value;self.private_write('cgi-metadata.json',bad)
            with self.subTest(field=field),self.assertRaisesRegex(ValueError,'startup_unproven'):
                self.ns['v4_cgi_metadata'](self.op,intent,self.actor,self.parser)

    def test_new_file_window_keeps_original_context_window_and_explicit_serializer(self):
        raw=RiaReadbackEvidenceTest.php(self.session)
        path=self.sessions/('sess_'+'a'*26);path.write_bytes(raw);path.chmod(0o600)
        late=self.ns['V4_END']+86400;os.utime(path,(late,late))
        now=late+10
        with mock.patch.object(self.ns['time'],'time',return_value=now):
            result=self.ns['v4_cgi_retained'](self.sessions,'php_serialize',self.actor,self.parser,self.root_identity())
        self.assertEqual('retained_case_matched',result['status'])
        self.assertEqual('quote_not_started',result['retained_outcome']['status'])
        self.assertEqual([self.ns['V4_START'],now],result['file_mtime_window'])
        self.assertEqual([self.ns['V4_START'],self.ns['V4_END']],result['original_context_window'])
        self.assertEqual('unknown',result['historical_gross']['status']);self.assertFalse(result['full_price_verified_current'])
        binding=self.op/'v4-case-binding.json';private=json.loads(binding.read_bytes())
        self.assertEqual(0o600,binding.stat().st_mode&0o777)
        self.assertEqual(str(path),private['session_file']);self.assertEqual('php_serialize',private['serializer'])
        self.assertEqual(hashlib.sha256(binding.read_bytes()).hexdigest(),result['binding_record_sha256'])
        self.assertNotIn(str(path),json.dumps(result));self.assertNotIn(str(self.sessions),json.dumps(result))

        with mock.patch.object(self.ns['time'],'time',return_value=now),self.assertRaises(Exception):
            self.ns['v4_cgi_retained'](self.sessions,'php',self.actor,self.parser,self.root_identity())

    def test_ambiguous_match_has_no_private_binding_record(self):
        for name in ('a','b'):
            path=self.sessions/('sess_'+name*26)
            path.write_bytes(RiaReadbackEvidenceTest.php(self.session));path.chmod(0o600)
            os.utime(path,(self.created,self.created))
        with self.assertRaisesRegex(ValueError,'case_ambiguous'):
            self.ns['v4_cgi_retained'](self.sessions,'php_serialize',self.actor,self.parser,self.root_identity())
        self.assertFalse((self.op/'v4-case-binding.json').exists())

    def test_all_candidate_bounds_precede_content_without_truncation(self):
        for i in range(33):
            path=self.sessions/('sess_'+str(i).zfill(26));path.write_bytes(b'x');path.chmod(0o600)
            os.utime(path,(self.created,self.created))
        read=mock.Mock(side_effect=AssertionError('no file content before full preflight'))
        original=self.ns['v4_owned_read'];self.ns['v4_owned_read']=read
        try:
            with self.assertRaisesRegex(ValueError,'candidate_bound'):
                self.ns['v4_cgi_retained'](self.sessions,'php',self.actor,self.parser,self.root_identity())
            read.assert_not_called()
        finally:self.ns['v4_owned_read']=original


    def test_writable_session_modes_fail_full_preflight_and_opened_read(self):
        raw=RiaReadbackEvidenceTest.php(self.session)
        path=self.sessions/('sess_'+'a'*26)
        for permissions in (0o660,0o666):
            path.write_bytes(raw);path.chmod(permissions);os.utime(path,(self.created,self.created))
            read=mock.Mock(side_effect=AssertionError('candidate content must not open'))
            original=self.ns['v4_owned_read'];self.ns['v4_owned_read']=read
            try:
                with self.subTest(mode=permissions),self.assertRaisesRegex(ValueError,'candidate_contract'):
                    self.ns['v4_cgi_retained'](self.sessions,'php_serialize',self.actor,self.parser,self.root_identity())
                read.assert_not_called()
            finally:self.ns['v4_owned_read']=original
        path.chmod(0o600)
        def change_mode(*args,**kwargs):
            path.chmod(0o666)
            self.assertTrue(kwargs['config'])
            return original(*args,**kwargs)
        self.ns['v4_owned_read']=change_mode
        try:
            with mock.patch.object(os,'read',side_effect=AssertionError('unsafe opened bytes')), \
                    self.assertRaisesRegex(ValueError,'config_ownership_or_mode'):
                self.ns['v4_cgi_retained'](self.sessions,'php_serialize',self.actor,self.parser,self.root_identity())
        finally:self.ns['v4_owned_read']=original

    def test_root_and_ancestors_have_explicit_owner_and_sticky_write_policy(self):
        self.sessions.chmod(0o777)
        try:
            with self.assertRaisesRegex(ValueError,'root_writable'):
                self.ns['v4_cgi_root_trust'](self.sessions,self.actor,self.parser)
        finally:self.sessions.chmod(0o700)
        actual=os.lstat
        def root_stat(path):
            st=actual(path)
            if Path(path)!=self.sessions:return st
            return types.SimpleNamespace(st_mode=0o41777,st_uid=0)
        with mock.patch.object(os,'lstat',side_effect=root_stat):
            self.assertTrue(self.ns['v4_cgi_root_trust'](self.sessions,self.actor,self.parser))
        def foreign(path):
            st=actual(path)
            if Path(path)!=self.sessions:return st
            return types.SimpleNamespace(st_mode=0o40700,st_uid=max(1,self.actor+1))
        with mock.patch.object(os,'lstat',side_effect=foreign),self.assertRaisesRegex(ValueError,'root_trust'):
            self.ns['v4_cgi_root_trust'](self.sessions,self.actor,self.parser)
        path=self.sessions/('sess_'+'a'*26);path.write_bytes(b'x');path.chmod(0o644);os.utime(path,(self.created,self.created))
        self.ns['v4_cgi_root_trust']=lambda *args:True
        read=mock.Mock(side_effect=AssertionError('shared-root session must be 0600'));self.ns['v4_owned_read']=read
        with self.assertRaisesRegex(ValueError,'candidate_contract'):
            self.ns['v4_cgi_retained'](self.sessions,'php',self.actor,self.parser,self.root_identity())
        read.assert_not_called()

    def test_temp_cleanup_preserves_foreign_inode_and_reports_unlink_failure(self):
        path=self.private_write('temporary-fixture',b'owned')
        identity=self.ns['v4_cgi_identity'](path,self.actor,self.parser)
        path.unlink();self.private_write('temporary-fixture',b'foreign-new-inode')
        with self.assertRaisesRegex(ValueError,'temp_cleanup_foreign'):
            self.ns['v4_cgi_cleanup_temp'](path,identity,self.actor,self.parser)
        self.assertEqual(b'foreign-new-inode',path.read_bytes())
        identity=self.ns['v4_cgi_identity'](path,self.actor,self.parser)
        with mock.patch.object(Path,'unlink',side_effect=PermissionError('PRIVATE_CLEANUP_CANARY')), \
                self.assertRaisesRegex(ValueError,'temp_cleanup_failed'):
            self.ns['v4_cgi_cleanup_temp'](path,identity,self.actor,self.parser)
        self.assertTrue(path.exists())

    def test_temp_cleanup_accepts_absence_after_atomic_rename_only_with_trusted_parent(self):
        path=self.private_write('temporary-before-rename',b'owned-atomic-fixture')
        identity=self.ns['v4_cgi_identity'](path,self.actor,self.parser)
        activated=self.op/'renamed-fixture';os.replace(path,activated)
        self.ns['v4_cgi_cleanup_temp'](path,identity,self.actor,self.parser)
        self.assertEqual(b'owned-atomic-fixture',activated.read_bytes())
        with mock.patch.dict(self.parser,{'no_links':mock.Mock(side_effect=RuntimeError('untrusted parent'))}), \
                self.assertRaisesRegex(ValueError,'temp_cleanup_unproven'):
            self.ns['v4_cgi_cleanup_temp'](path,identity,self.actor,self.parser)

    def test_directory_durability_failure_cannot_ack_or_open_content(self):
        self.ns['v4_cgi_dependencies']=mock.Mock()
        self.ns['v4_cgi_http']=mock.Mock()
        self.ns['v4_cgi_metadata']=mock.Mock()
        self.ns['v4_cgi_retained']=mock.Mock()
        self.ns['v4_cgi_sync_dirs']=mock.Mock(side_effect=OSError('PRIVATE_FSYNC_CANARY'))
        with mock.patch.object(subprocess,'run',return_value=types.SimpleNamespace(returncode=0)):
            result=self.ns['v4_cgi_readback']()
        self.assertEqual('hold',result['status']);self.assertFalse(result['api_restored'])
        self.assertTrue(result['restore_only_required'])
        self.ns['v4_cgi_http'].assert_not_called();self.ns['v4_cgi_metadata'].assert_not_called()
        self.ns['v4_cgi_retained'].assert_not_called()
        self.assertNotIn('PRIVATE_FSYNC_CANARY',json.dumps(result))

    def test_successful_whole_fixture_restores_and_closes_before_retained_content(self):
        path=self.sessions/('sess_'+'a'*26);path.write_bytes(RiaReadbackEvidenceTest.php(self.session))
        path.chmod(0o600);os.utime(path,(self.created,self.created))
        self.ns['v4_cgi_dependencies']=mock.Mock()
        self.ns['v4_cgi_http']=mock.Mock()
        order=[];reader=self.ns['v4_cgi_retained']
        def metadata(*args):
            self.assertEqual(self.original,self.api.read_bytes())
            self.assertEqual(b'CLOSED\n',(self.op/'cgi-gate').read_bytes())
            order.append('metadata')
            return self.sessions,'php_serialize',self.root_identity()
        def retained(*args):
            self.assertEqual(['metadata'],order);self.assertEqual(self.original,self.api.read_bytes())
            self.assertEqual(b'CLOSED\n',(self.op/'cgi-gate').read_bytes())
            order.append('retained');return reader(*args)
        self.ns['v4_cgi_metadata']=metadata;self.ns['v4_cgi_retained']=retained
        with mock.patch.object(subprocess,'run',return_value=types.SimpleNamespace(returncode=0)), \
                mock.patch.object(self.ns['time'],'sleep'):
            result=self.ns['v4_cgi_readback']()
        self.assertEqual('retained_case_matched',result['status'],result);self.assertEqual(['metadata','retained'],order)
        self.assertTrue(result['api_restored'],result);self.assertEqual(2,result['runtime_probe_writes'])
        self.ns['v4_cgi_http'].assert_called_once();self.assertFalse(result['full_price_verified_current'])


    def priced_session(self,two_pairs=False):
        self.offer['hotel_place']='2ADL+1INF'
        saved=self.context['gateway']['saved_offers'];saved['expires_at']=self.created+1200
        saved['search']['currency_id']=1
        self.context['gateway']['search']['context']['currency_id']=1
        self.context['gateway']['search']['offers'][0]['supplier_offer_id']='unit-private-supplier-claim'
        saved['offers'][self.key]['supplier_currency_id']='3'
        binding={'generation':1,'search_ref':self.ref,'offer_ref':self.key,'offer':self.offer,
            'supplier_offer_id':'unit-private-supplier-claim','currency_id':'1',
            'child_ages':self.context['gateway']['search']['context']['child_ages']}
        maps=[{0:'unit-private-forward-uid',1:'unit-private-return-uid'}]
        if two_pairs:maps.append({0:'unit-private-other-forward',1:'unit-private-other-return'})
        encoded=self.ns['v4_cgi_php_hashes'](binding,maps,'1234567890123')
        def diagnostic(stage):
            return {'action':stage,'http_status':200,'curl_errno':0,'response_bytes':640,'elapsed_ms':123}
        diagnostics={stage:diagnostic(stage) for stage in ('start','transports','SetTransport','calcfull')}
        pairs={};choices={}
        for index,(ref,selection) in enumerate(zip(encoded['pair_refs'],maps)):
            display={'choice_ref':ref,'legs':{0:{'label':'Unit outbound flight'},1:{'label':'Unit return flight'}},'current':False}
            public={'status':'quote_verified','final_price_verified':True,'selection_state':'preview_only',
                'price':{'amount':'149876.50' if index==0 else '151234.00','currency':'RUB','basis':'supplier_gross_package'},
                'choice':display,'verified_at':self.created+90+index,'expires_at':self.created+600}
            choices[ref]={'selection':selection,'public':display}
            pairs[ref]={'stages':{'SetTransport':'complete','calcfull':'complete'},
                'diagnostics':{stage:diagnostic(stage) for stage in ('SetTransport','calcfull')},'public':public}
        attempt={'version':2,'context_digest':encoded['context_digest'],'reserved_calls':2+2*len(pairs),
            'pairs':pairs,'active_pair':None,'sealed':False,'id':'1234567890123','expires_at':self.created+600,
            'stages':{stage:'complete' for stage in diagnostics},'diagnostics':diagnostics,
            'choices':choices,'public':pairs[encoded['pair_refs'][-1]]['public']}
        self.context['package_quotes'][self.key]=attempt
        return attempt,binding

    @unittest.skipUnless(shutil.which('php'),'native PHP canonical money-digest unit fixture')
    def test_verified_family_gross_is_projected_in_same_read_historical_only(self):
        self.priced_session(two_pairs=True)
        path=self.sessions/('sess_'+'a'*26);path.write_bytes(RiaReadbackEvidenceTest.php(self.session))
        path.chmod(0o600);os.utime(path,(self.created,self.created))
        result=self.ns['v4_cgi_retained'](self.sessions,'php_serialize',self.actor,self.parser,self.root_identity())
        gross=result['historical_gross']
        self.assertEqual(('verified_historical_runtime_result','151234.00','RUB','supplier_gross_package'),
            (gross['status'],gross['amount'],gross['currency'],gross['basis']),gross)
        self.assertEqual({'adults':2,'children':0,'infants':1},gross['party'])
        self.assertTrue(gross['historical_only']);self.assertFalse(gross['current_validity'])
        self.assertFalse(gross['raw_participant_documents_retained'])
        self.assertEqual('accepted_runtime_retained_result',gross['proof_basis'])
        self.assertTrue(result['binding_record_saved']);self.assertFalse(result['full_price_verified_current'])
        for private in ('unit-private','1234567890123',self.ref,self.key,'anex_quote:'):
            self.assertNotIn(private,json.dumps(result))

    @unittest.skipUnless(shutil.which('php'),'native PHP canonical digest conflict unit fixture')
    def test_incomplete_or_conflicting_gross_stays_unknown_never_listing(self):
        attempt,binding=self.priced_session()
        original=copy.deepcopy(attempt);ref=next(iter(attempt['pairs']))
        variants=[
            lambda a:a.update(sealed=True),
            lambda a:a.update(active_pair=ref),
            lambda a:a.update(reserved_calls=8),
            lambda a:a['stages'].update(calcfull='unknown'),
            lambda a:a['pairs'][ref]['stages'].update(calcfull='unknown'),
            lambda a:a['pairs'][ref]['diagnostics']['calcfull'].update(action='SetTransport'),
            lambda a:a['public'].update(price={'amount':'107581','currency':'RUB','basis':'listing'}),
            lambda a:a.update(public=copy.deepcopy(a['public'])|{'price':dict(a['public']['price'],amount='1')}),
            lambda a:a['choices'][ref]['selection'].update({0:'foreign-uid'}),
            lambda a:a.update(context_digest=self.ns['v4_cgi_php_hashes'](dict(binding,currency_id=1),[],a['id'])['context_digest']),
        ]
        for index,change in enumerate(variants):
            bad=copy.deepcopy(original);change(bad);self.context['package_quotes'][self.key]=bad
            gross=self.ns['v4_cgi_historical_gross'](self.session,self.parser)
            with self.subTest(case=index):
                self.assertEqual('unknown',gross['status'],gross);self.assertIsNone(gross['amount'])
                self.assertNotIn('107581',json.dumps(gross))
        failed=copy.deepcopy(original);failed.update(public={'status':'quote_failed','reason':'ANEX_QUOTE_HTTP_ERROR','failure_stage':'calcfull'})
        self.context['package_quotes'][self.key]=failed
        failure=self.ns['v4_cgi_historical_gross'](self.session,self.parser)
        self.assertEqual(('unknown','ANEX_QUOTE_HTTP_ERROR','calcfull'),(failure['status'],failure['quote_reason'],failure['failure_stage']))
        self.context['package_quotes'].clear()
        self.assertEqual('unknown',self.ns['v4_cgi_historical_gross'](self.session,self.parser)['status'])

    @unittest.skipUnless(shutil.which('php'),'native PHP array key/number encoding fixture')
    def test_php_native_encoder_rejects_colliding_keys_and_integer_overflow(self):
        for binding in ({0:'integer-key','0':'numeric-string-key'},{'too_big':2**100}):
            with self.subTest(binding=binding),self.assertRaisesRegex(ValueError,'history_php_encoding'):
                self.ns['v4_cgi_php_hashes'](binding,[],'1234567890123')

    @unittest.skipUnless(shutil.which('php'),'native PHP syntax/tokenizer guard in existing CI')
    def test_native_retained_wrapper_gate_and_literal_config_without_imports(self):
        import time
        nonce='unit-only-nonce';digest=hashlib.sha256(nonce.encode()).hexdigest()
        self.private_write('cgi-gate',('OPEN\n'+digest+'\n').encode())
        st=(self.op/'cgi-gate').stat()
        spec={'operation_id':extension.V4_CGI_OPERATION,'nonce_hash':digest,'uid':self.actor,'deadline':int(time.time())+60,
            'script':str(self.api),'gate':str(self.op/'cgi-gate'),'metadata':str(self.op/'cgi-metadata.json'),
            'config':str(self.runtime/'.anex-private.php'),'bootstrap':str(self.root/'.anytoour-anex/search3-preview.php'),
            'gate_identity':[st.st_dev,st.st_ino,st.st_uid,st.st_gid,st.st_mode]}
        # Unit-only CLI fact substitution; production has no bypass or configurable SAPI.
        branch=self.ns['V4_CGI_PHP'].replace('@@SPEC@@',base64.b64encode(json.dumps(spec).encode()).decode())
        branch=branch.replace('php_sapi_name()',"'cgi-fcgi'")
        server="$_SERVER['HTTP_X_ANYTOUR_V4_METADATA']='unit-only-nonce';$_SERVER['REQUEST_METHOD']='POST';"+\
            "$_SERVER['HTTP_HOST']='anytoour.ru';$_SERVER['QUERY_STRING']='';$_SERVER['SCRIPT_FILENAME']=__FILE__;\n"
        self.api.write_text(self.ns['V4_CGI_HEADER'].decode()+server+branch+"throw new Exception('SUPPLIER_IMPORT_CANARY');\n")
        out=subprocess.run(['php',str(self.api)],capture_output=True,text=True,timeout=10)
        self.assertEqual((out.returncode,out.stdout,out.stderr),(0,'{"ok":true}',''))
        saved=(self.op/'cgi-metadata.json').read_bytes();data=json.loads(saved)
        self.assertTrue(data['config_contract']);self.assertEqual(self.actor,data['uid'])
        self.assertEqual('cgi-fcgi',data['sapi']);self.assertNotIn(str(self.root),out.stdout)
        (self.op/'cgi-gate').write_bytes(b'CLOSED\n')
        out=subprocess.run(['php',str(self.api)],capture_output=True,text=True,timeout=10)
        self.assertEqual(out.stdout,'{"ok":false}')
        self.assertEqual(saved,(self.op/'cgi-metadata.json').read_bytes())
        # Reopening is test-only; a dangerous dynamic call remains unexecuted, not token-whitelisted.
        (self.op/'cgi-gate').write_bytes(('OPEN\n'+digest+'\n').encode())
        (self.op/'cgi-metadata.json').unlink()
        canary=self.root/'must-not-be-written'
        (self.runtime/'.anex-private.php').write_text("<?php ('file_put_contents')("+repr(str(canary))+",'bad');")
        out=subprocess.run(['php',str(self.api)],capture_output=True,text=True,timeout=10)
        self.assertEqual(out.stdout,'{"ok":true}');self.assertFalse(canary.exists())
        self.assertFalse(json.loads((self.op/'cgi-metadata.json').read_bytes())['config_contract'])
        for label,changes in [('wrong_nonce',{'nonce_hash':'b'*64}),('expired',{'deadline':int(time.time())-1}),
                              ('other_uid',{'uid':self.actor+1}),('wrong_script',{'script':str(self.api)+'-other'})]:
            (self.op/'cgi-metadata.json').unlink()
            bad=dict(spec,**changes)
            code=self.ns['V4_CGI_PHP'].replace('@@SPEC@@',base64.b64encode(json.dumps(bad).encode()).decode())
            code=code.replace('php_sapi_name()',"'cgi-fcgi'")
            self.api.write_text(self.ns['V4_CGI_HEADER'].decode()+server+code+"throw new Exception('IMPORT_CANARY');\n")
            out=subprocess.run(['php',str(self.api)],capture_output=True,text=True,timeout=10)
            with self.subTest(guard=label):
                self.assertEqual((out.returncode,out.stdout,out.stderr),(0,'{"ok":false}',''))
                self.assertFalse((self.op/'cgi-metadata.json').exists());self.assertFalse(canary.exists())
            # Keep the next fixture's output path absent without reopening a live gate.
            if label!='wrong_script':self.private_write('cgi-metadata.json',b'unit-placeholder')
        # A real warning and malformed config cannot leak path/text into the HTTP-shaped body.
        (self.runtime/'.anex-private.php').write_text("<?php define('MALFORMED', ")
        code=self.ns['V4_CGI_PHP'].replace('@@SPEC@@',base64.b64encode(json.dumps(spec).encode()).decode())
        code=code.replace('php_sapi_name()',"'cgi-fcgi'").replace(
            "        v4cneed($v4buffered === true",
            "        trigger_error('PRIVATE_PATH_WARNING_CANARY', E_USER_WARNING);\n        v4cneed($v4buffered === true")
        self.api.write_text(self.ns['V4_CGI_HEADER'].decode()+server+code)
        out=subprocess.run(['php',str(self.api)],capture_output=True,text=True,timeout=10)
        self.assertEqual((out.returncode,out.stdout,out.stderr),(0,'{"ok":true}',''))
        self.assertFalse(json.loads((self.op/'cgi-metadata.json').read_bytes())['config_contract'])




ORIGINAL_V4_CGI_RESULT_JSON = "{\"anex_v4_cgi_readback\":{\"actor_escalation\":false,\"api_restored\":true,\"cgi_startup_side_effects\":\"unverified\",\"full_price_verified_current\":false,\"gate_closed\":true,\"historical_gross\":null,\"reason\":\"v4_cgi_guard_hold\",\"runtime_probe_writes\":2,\"script_session_open_intents\":0,\"script_supplier_intents\":0,\"stage\":\"metadata_probe\",\"status\":\"hold\"},\"booking_calls\":0,\"database_reads\":\"unknown\",\"database_writes\":\"unknown\",\"lead_calls\":0,\"mode\":\"anex-v4-cgi-retained-readback\",\"operation_id\":\"int-anex-v4-cgi-retained-20261110-v1\",\"production_after\":{\"index.php\":\"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f\",\"v2/api-v2.php\":null,\"v2/index.php\":null,\"v2/lead-adapter-v2.php\":null},\"production_before\":{\"index.php\":\"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f\",\"v2/api-v2.php\":null,\"v2/index.php\":null,\"v2/lead-adapter-v2.php\":null},\"production_unchanged\":true,\"schema_version\":1,\"source_sha\":\"6dcfb5053793caba4f353facfa7a899a30df2d17\",\"status\":\"complete\",\"supplier_calls\":\"unknown\"}"


class V4CgiContinuationCommandTest(unittest.TestCase):
    def test_fixed_child_scope_all_previous_programs_and_remote_size(self):
        import ast
        source=(ROOT/'scripts/deploy/int_server_executor_firstpage_readback.py').read_text()
        baseline=before_v4_continuation_source(source);raw=baseline.encode()
        self.assertEqual('3bfcb61f22e00e4949ee160fc3646f13fbda9cb5',
            hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest())
        prior=types.ModuleType('before_v4_retained_continuation')
        exec(compile(baseline,'<before-continuation>','exec'),prior.__dict__)
        for mode in prior.MODES:
            core=load('int_server_executor')
            self.assertEqual(prior.remote_with_readback(core,mode),extension.remote_with_readback(core,mode))
        core=load('int_server_executor');extension.register_parser(core)
        body=core.PREFIX+extension.V4_SOURCE+' '+extension.V4_CGI_CONT_MODE+' '+extension.V4_CGI_CONT_OPERATION
        command=core.parse_command(body)
        for invalid in (body+' /tmp/other',body.replace(extension.V4_SOURCE,'a'*40),
                        body.replace(extension.V4_CGI_CONT_OPERATION,extension.V4_CGI_OPERATION),
                        body.replace('-v1','-v2')):
            with self.assertRaises(ValueError):core.parse_command(invalid)
        extension.activate(core,command);ast.parse(core.REMOTE)
        self.assertLess(len(base64.b64encode(zlib.compress(core.REMOTE.encode(),9)))+1024,65536)
        self.assertIn('operation_exists_no_replay',core.REMOTE)
        self.assertNotIn('v4_cgi_http(',extension.REMOTE_V4_CGI_CONT_READER)
        self.assertNotIn('v4_cgi_restore(',extension.REMOTE_V4_CGI_CONT_READER)
        self.assertNotIn('stage_target_bytes(',extension.REMOTE_V4_CGI_CONT_READER)
        wrapper=load('int_server_executor_anex_secret_transport')
        self.assertNotIn(extension.V4_CGI_CONT_MODE,wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(extension.V4_CGI_CONT_MODE,wrapper.DIRECT_ANEX_MODES)


@unittest.skipIf(os.name=='nt','POSIX owned retained-control fixtures; canonical CI is Linux')
class V4CgiContinuationEvidenceTest(unittest.TestCase):
    private_write=V4CgiEvidenceTest.private_write
    root_identity=V4CgiEvidenceTest.root_identity
    prepare=V4CgiEvidenceTest.prepare
    metadata=V4CgiEvidenceTest.metadata

    def setUp(self):
        import time
        V4CgiEvidenceTest.setUp(self)
        exec(extension.REMOTE_V4_CGI_CONT_READER,self.ns)
        self.parent=self.op;intent=self.prepare()
        # Authenticated metadata is old. Current time must never revive/re-run its nonce.
        intent['deadline']=int(time.time())-300
        self.private_write('reservation.json',{'operation_id':extension.V4_CGI_OPERATION,'source_sha':extension.V4_SOURCE,
            'mode':extension.V4_CGI_MODE,'reserved_at':intent['deadline']-60})
        self.private_write('cgi-intent.json',intent)
        self.private_write('result.json',json.loads(ORIGINAL_V4_CGI_RESULT_JSON))
        self.private_write('cgi-metadata.json',self.metadata(intent))
        (self.parent/'cgi-gate').write_bytes(b'CLOSED\n')
        self.api.write_bytes(self.original)
        self.child=self.private/extension.V4_CGI_CONT_OPERATION;self.child.mkdir(mode=0o700)
        self.ns.update(op=self.child,operation=extension.V4_CGI_CONT_OPERATION)
        self.ns['v4_cgi_dependencies']=mock.Mock()
        self.http=mock.Mock(side_effect=AssertionError('continuation must not call HTTP'))
        self.restore=mock.Mock(side_effect=AssertionError('continuation must not restore or mutate API'))
        self.ns['v4_cgi_http']=self.http;self.ns['v4_cgi_restore']=self.restore
        self.source_read=self.ns['v4_cgi_read']
        self.read=mock.Mock(wraps=self.source_read);self.ns['v4_cgi_read']=self.read
        self.owned=mock.Mock(wraps=self.ns['v4_owned_read']);self.ns['v4_owned_read']=self.owned
        self.session_path=self.sessions/('sess_'+'a'*26)
        self.session_path.write_bytes(b''.join(key.encode()+b'|'+RiaReadbackEvidenceTest.php(value)
            for key,value in self.session.items()));self.session_path.chmod(0o600)
        os.utime(self.session_path,(self.created,self.created))

    def parent_snapshot(self):
        return {path.name:path.read_bytes() for path in self.parent.iterdir() if path.is_file()}

    def session_reads(self):
        return [call for call in self.owned.call_args_list if Path(call.args[0]).parent==self.sessions]

    def run_read(self):
        return self.ns['v4_cgi_continuation_readback']()

    def test_original_metadata_is_read_once_first_content_and_child_provenance_only(self):
        before=self.parent_snapshot();api=self.api.read_bytes()
        result=self.run_read()
        self.assertEqual('retained_case_matched',result['status'],result)
        self.assertTrue(result['retained_read_invoked']);self.assertEqual(1,len(self.session_reads()))
        self.assertEqual(1,sum(call.args[0]==self.parent/'cgi-metadata.json' for call in self.read.call_args_list))
        self.assertEqual(before,self.parent_snapshot());self.assertEqual(api,self.api.read_bytes())
        self.http.assert_not_called();self.restore.assert_not_called()
        record=self.child/'v4-case-binding.json';binding=json.loads(record.read_bytes())
        self.assertEqual(0o600,record.stat().st_mode&0o777)
        self.assertEqual(extension.V4_CGI_CONT_OPERATION,binding['operation_id'])
        self.assertEqual(extension.V4_CGI_OPERATION,binding['metadata_parent_operation_id'])
        self.assertEqual(self.ns['V4_CGI_CONT_PARENT_SHA'],binding['metadata_parent_result_sha256'])
        self.assertEqual(hashlib.sha256(record.read_bytes()).hexdigest(),result['binding_record_sha256'])
        self.assertEqual('unknown',result['parent_supplier_calls'])
        self.assertEqual('unverified',result['parent_cgi_startup_side_effects'])
        self.assertEqual(0,result['own_http_requests']);self.assertEqual(0,result['own_api_writes'])
        for private in (str(self.parent),str(self.sessions),str(self.session_path)):
            self.assertNotIn(private,json.dumps(result))

    def test_original_php_serialize_metadata_uses_whole_array_wire(self):
        metadata=json.loads((self.parent/'cgi-metadata.json').read_bytes());metadata['serializer']='php_serialize'
        self.private_write('cgi-metadata.json',metadata)
        self.session_path.write_bytes(RiaReadbackEvidenceTest.php(self.session));self.session_path.chmod(0o600)
        os.utime(self.session_path,(self.created,self.created))
        result=self.run_read()
        self.assertEqual('retained_case_matched',result['status'],result)
        self.assertEqual('php_serialize',result['serializer']);self.assertEqual(1,len(self.session_reads()))
        self.assertEqual(1,sum(call.args[0]==self.parent/'cgi-metadata.json' for call in self.read.call_args_list))
        self.http.assert_not_called();self.restore.assert_not_called()

    def test_absent_metadata_is_precise_terminal_without_content_or_parent_writes(self):
        (self.parent/'cgi-metadata.json').unlink();before=self.parent_snapshot();api=self.api.read_bytes()
        result=self.run_read()
        self.assertEqual('v4_cgi_cont_metadata_absent',result['reason'],result)
        self.assertFalse(result['retained_read_invoked']);self.assertFalse(result['metadata_present'])
        self.assertEqual([],self.session_reads());self.assertEqual(before,self.parent_snapshot())
        self.assertEqual(api,self.api.read_bytes());self.assertFalse((self.child/'v4-case-binding.json').exists())
        self.http.assert_not_called();self.restore.assert_not_called()

    def test_partial_wrong_nonce_parent_receipt_or_open_gate_never_reach_contents(self):
        snapshots=self.parent_snapshot()
        cases=[('cgi-metadata.json',b'{'),('cgi-metadata.json',json.dumps(dict(json.loads(snapshots['cgi-metadata.json']),nonce_hash='b'*64)).encode()),
            ('result.json',json.dumps(dict(json.loads(snapshots['result.json']),status='unknown')).encode()),
            ('cgi-gate',b'OPEN\n'+b'a'*64+b'\n')]
        for name,raw in cases:
            for filename,data in snapshots.items():self.private_write(filename,data)
            self.private_write(name,raw);before=self.parent_snapshot();self.owned.reset_mock();self.read.reset_mock()
            result=self.run_read()
            with self.subTest(file=name):
                self.assertEqual('hold',result['status'],result);self.assertFalse(result['retained_read_invoked'])
                self.assertEqual([],self.session_reads());self.assertEqual(before,self.parent_snapshot())
                self.assertFalse((self.child/'v4-case-binding.json').exists())
                self.assertLessEqual(sum(call.args[0]==self.parent/'cgi-metadata.json' for call in self.read.call_args_list),1)
        self.http.assert_not_called();self.restore.assert_not_called()

    def test_parent_change_after_metadata_validation_is_hold_before_content(self):
        validate=self.ns['v4_cgi_metadata']
        def change(*args):
            result=validate(*args)
            path=self.parent/'cgi-metadata.json';path.write_bytes(path.read_bytes()+b' ')
            return result
        self.ns['v4_cgi_metadata']=change
        result=self.run_read()
        self.assertEqual('v4_cgi_cont_parent_changed',result['reason'],result)
        self.assertFalse(result['retained_read_invoked']);self.assertEqual([],self.session_reads())
        self.http.assert_not_called();self.restore.assert_not_called()


if __name__ == '__main__':
    unittest.main()

