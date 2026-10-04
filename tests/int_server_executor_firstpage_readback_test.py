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


if __name__ == '__main__':
    unittest.main()
