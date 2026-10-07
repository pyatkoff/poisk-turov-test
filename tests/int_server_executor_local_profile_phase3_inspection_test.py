"""Real filesystem tests for one read-only inspection; no server or supplier calls."""
from __future__ import annotations

import ast
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import tempfile
import types
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'scripts/deploy/int_server_executor_local_profile_phase3_inspection.py'
spec = importlib.util.spec_from_file_location('local_phase3_inspection_test_subject', SCRIPT)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
TARGET = 'int-andromeda-local-profile-mass-plan3-4191-20261002-v1'
TARGET_SOURCE = '1dcda2f59b757d30bd062ab5808d0b46eac09019'
TARGET_CONTROL = '0155ebad6ccbd9fd284f888bbc8d4ceb8026e64d'
SHA, CONTROL = 'a' * 40, 'b' * 40
TERMINAL = {
    'booking_calls': 0, 'database_writes': 'unknown', 'lead_calls': 0,
    'mode': m.MODE, 'operation_id': TARGET,
    'production_before': {'index.php': '80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
                          'v2/api-v2.php': None, 'v2/index.php': None, 'v2/lead-adapter-v2.php': None},
    'reason': 'local_mass3_terminal_missing_no_replay', 'schema_version': 1,
    'source_sha': TARGET_SOURCE, 'status': 'unknown_no_replay', 'supplier_calls': 'unknown',
}


def encoded(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


class ParserTest(unittest.TestCase):
    def core(self):
        previous = mock.Mock(side_effect=ValueError('previous_parser'))
        return types.SimpleNamespace(PREFIX='/run-int-server-v1 ', SHA_RE=re.compile(r'\A[a-f0-9]{40}\Z'),
                                     parse_command=previous), previous

    def test_exact_pair_only_with_zero_authority(self):
        core, previous = self.core()
        m.register_parser(core)
        result = core.parse_command(f'{core.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        self.assertEqual({'source_sha': SHA, 'mode': m.MODE, 'operation_id': m.OPERATION,
                          'batch': m.BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}, result)
        previous.assert_not_called()

    def test_invalid_ids_limits_source_and_cross_pairs(self):
        core, _ = self.core()
        m.register_parser(core)
        cmd = f'{core.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}'
        for text in (cmd + ' --ids=5066', cmd + ' --apply', cmd.replace(SHA, 'z'*40),
                     cmd.replace(m.BATCH, 'arbitrary'), cmd.replace(m.OPERATION, m.OPERATION+'-renamed')):
            with self.subTest(text=text), self.assertRaises(ValueError):
                core.parse_command(text)

    def test_nonmatching_commands_preserve_original_parser(self):
        core, original = self.core()
        m.register_parser(core)
        commands = ('hello', f'{core.PREFIX}{SHA} {m.MODE} {TARGET} local4191-mass-retained3-20261002',
                    f'{core.PREFIX}{SHA} install-runtime int-other-operation-v1')
        for command in commands:
            with self.assertRaisesRegex(ValueError, 'previous_parser'):
                core.parse_command(command)
            original.assert_called_with(command)

    def test_activation_rejects_widened_payload_before_emission(self):
        core, _ = self.core()
        m.register_parser(core)
        command = core.parse_command(f'{core.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        command['maximum_writes'] = 1
        plan = types.SimpleNamespace(remote_with_plan=mock.Mock())
        with self.assertRaises(ValueError):
            m.activate(core, command, plan)
        plan.remote_with_plan.assert_not_called()


class FilesystemTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.home = Path(self.tmp.name).resolve()
        root = self.home / '.anytoour-int-executor'
        self.old, self.out = root / TARGET, root / m.OPERATION
        self.old.mkdir(parents=True, mode=0o700)
        self.out.mkdir(mode=0o700)
        self.payload = {'source_sha': SHA, 'mode': m.MODE, 'operation_id': m.OPERATION,
                        'batch': m.BATCH, 'maximum_writes': 0, 'provider_http_calls': 0,
                        'local_profile_control_sha': CONTROL}
        self.ns = {'operation': m.OPERATION, 'payload': self.payload, 'source': SHA,
                   'project': self.home/'www/anytoour.ru', 'op': self.out}
        exec(m.REMOTE_HANDLER, self.ns)
        self.put('reservation.json', {'source_sha': TARGET_SOURCE, 'operation_id': TARGET, 'mode': m.MODE})
        self.put('installed-source.json', {'source_sha': TARGET_SOURCE,
            'files': {'scripts/diagnostics/local_profile_mass_plan3_4191.php': 'c'*64}})
        self.put('result.json', TERMINAL)

    def put(self, name, data):
        (self.old / name).write_bytes(encoded(data))

    def run_inspection(self):
        with mock.patch.dict(os.environ, {'HOME': str(self.home)}):
            return self.ns['inspect_local_phase3'](self.out/'source')

    def add_plan(self):
        plan = {'operation_id': TARGET, 'source_sha': TARGET_SOURCE, 'control_source_sha': TARGET_CONTROL,
                'batch': 'local4191-mass-retained3-20261002', 'safe_to_apply': False, 'active_profiles': 3,
                'source_plans_prepared': 2, 'classification_counts': {
                    'RETAINED_DELTA_PREPARED': 1, 'SOURCE_MISSING': 1, 'PLAN_BOUND_DEFERRED': 1},
                'rows': [{'anytourHotelId': 990001, 'localHotelId': 880001, 'state': 'RETAINED_DELTA_PREPARED',
                          'privateDescription': 'DO_NOT_PUBLISH_THIS'},
                         {'anytourHotelId': 990002, 'localHotelId': 880002, 'state': 'SOURCE_MISSING'},
                         {'anytourHotelId': 990003, 'localHotelId': 880003, 'state': 'PLAN_BOUND_DEFERRED'}]}
        self.put('local-mass3-plan.json', plan)
        return plan

    def test_absence_is_not_an_empty_scope_or_zero_original_effects(self):
        before = {p.name: p.read_bytes() for p in self.old.iterdir()}
        result = self.run_inspection()
        self.assertEqual('inspected_read_only', result['state'])
        self.assertEqual('not_recorded', result['recorded_scope_state'])
        self.assertIsNone(result['recorded_candidate_profiles'])
        self.assertEqual('unknown', result['original_database_writes'])
        self.assertEqual('unknown', result['original_supplier_calls'])
        self.assertFalse(result['replay_allowed'])
        self.assertFalse(result['automatic_successor_allowed'])
        self.assertEqual(0, result['database_reads'])
        self.assertEqual(0, result['database_writes'])
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.old.iterdir()})
        self.assertEqual(3, sum(row['present'] for row in result['files']))

    def test_recorded_candidates_are_private_and_not_new_current_identities(self):
        self.add_plan()
        result = self.run_inspection()
        self.assertEqual(2, result['recorded_candidate_profiles'])
        text = json.dumps(result)
        self.assertNotIn('DO_NOT_PUBLISH_THIS', text)
        self.assertNotIn('990001', text)
        self.assertNotIn('880001', text)
        data = (self.out/'phase3-recorded-scope.json').read_bytes()
        self.assertEqual(result['recorded_scope_sha256'], hashlib.sha256(data).hexdigest())
        scope = json.loads(data)
        self.assertEqual([990001, 990002], [x['anytourHotelId'] for x in scope['rows']])
        self.assertFalse(scope['current_identity_verified'])
        self.assertFalse(scope['safe_to_apply'])

    def test_all_retained_originals_are_byte_equal_and_private(self):
        self.add_plan()
        result = self.run_inspection()
        for item in result['files']:
            if not item['present']:
                continue
            original = (self.old/item['file']).read_bytes()
            saved = self.out/('phase3-original-'+item['file'])
            self.assertEqual(original, saved.read_bytes())
            self.assertEqual(item['sha256'], hashlib.sha256(original).hexdigest())
        for saved in self.out.iterdir():
            self.assertEqual(0o600, saved.stat().st_mode & 0o777)
        receipt = json.loads((self.out/'phase3-inspection-receipt.json').read_bytes())
        self.assertEqual(result, receipt)

    def test_original_terminal_change_is_rejected(self):
        self.put('result.json', TERMINAL | {'database_writes': 0})
        with self.assertRaisesRegex(ValueError, 'original_terminal_changed'):
            self.run_inspection()
        self.assertFalse((self.out/'phase3-recorded-scope.json').exists())

    def test_wrong_source_is_rejected_after_preserving_originals(self):
        self.put('reservation.json', {'operation_id': TARGET, 'source_sha': SHA, 'mode': m.MODE})
        with self.assertRaisesRegex(ValueError, 'reservation'):
            self.run_inspection()
        self.assertTrue((self.out/'phase3-original-result.json').exists())

    def test_duplicate_and_nonfinite_json_are_rejected(self):
        (self.old/'reservation.json').write_bytes(b'{"source_sha":"x","source_sha":"y"}')
        with self.assertRaisesRegex(ValueError, 'duplicate_json_key'):
            self.run_inspection()

    def test_nonfinite_json_is_rejected(self):
        (self.old/'reservation.json').write_bytes(b'{"value":NaN}')
        with self.assertRaisesRegex(ValueError, 'nonfinite_json'):
            self.run_inspection()

    def test_input_symlink_cannot_escape(self):
        outside = self.home/'unrelated.json'
        outside.write_text('NEVER_READ')
        (self.old/'local-mass3-plan.json').symlink_to(outside)
        with self.assertRaises(OSError):
            self.run_inspection()
        self.assertFalse((self.out/'phase3-original-local-mass3-plan.json').exists())

    def test_input_hardlink_is_rejected(self):
        os.link(self.old/'result.json', self.old/'local-mass3-plan.json')
        with self.assertRaisesRegex(ValueError, 'input_file'):
            self.run_inspection()

    def test_fifo_is_rejected_without_blocking(self):
        os.mkfifo(self.old/'local-mass3-plan.json')
        with self.assertRaisesRegex(ValueError, 'input_file'):
            self.run_inspection()

    def test_oversize_input_is_rejected(self):
        (self.old/'installed-source.json').write_bytes(b' ' * 131073)
        with self.assertRaisesRegex(ValueError, 'input_file'):
            self.run_inspection()

    def test_second_invocation_does_not_reread_or_overwrite(self):
        self.run_inspection()
        before = {p.name: p.read_bytes() for p in self.out.iterdir()}
        with self.assertRaises(FileExistsError):
            self.run_inspection()
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.out.iterdir()})

    def test_wrong_root_and_public_output_directory_rejected(self):
        self.out.chmod(0o755)
        with self.assertRaisesRegex(ValueError, 'output_directory_mode'):
            self.run_inspection()
        self.assertEqual([], list(self.out.iterdir()))
        self.out.chmod(0o700)
        self.ns['op'] = self.old
        with self.assertRaisesRegex(ValueError, 'root'):
            self.run_inspection()

    def test_duplicate_identity_plan_rejected(self):
        plan = self.add_plan()
        plan['rows'][1]['anytourHotelId'] = plan['rows'][0]['anytourHotelId']
        self.put('local-mass3-plan.json', plan)
        with self.assertRaisesRegex(ValueError, 'row_identity'):
            self.run_inspection()

    def test_optional_receipt_must_bind_exact_plan_bytes(self):
        self.add_plan()
        self.put('local-mass3-receipt.json', {'operation_id': TARGET, 'source_sha': TARGET_SOURCE,
            'control_source_sha': TARGET_CONTROL, 'batch': 'local4191-mass-retained3-20261002',
            'safe_to_apply': False, 'private_plan_sha256': '0'*64})
        with self.assertRaisesRegex(ValueError, 'receipt_identity'):
            self.run_inspection()

    def test_plan_counts_are_not_inferred_from_filenames(self):
        plan = self.add_plan()
        plan['source_plans_prepared'] = 3
        self.put('local-mass3-plan.json', plan)
        with self.assertRaisesRegex(ValueError, 'prepared_count'):
            self.run_inspection()

    def test_snapshot_drift_is_rejected(self):
        original_stat = os.stat
        def changed_stat(path, *args, **kwargs):
            if path == 'local-mass3-receipt.json' and kwargs.get('dir_fd') is not None:
                (self.old/'local-mass3-receipt.json').write_text('{}')
            return original_stat(path, *args, **kwargs)
        with mock.patch.object(os, 'stat', side_effect=changed_stat):
            with self.assertRaisesRegex(ValueError, 'snapshot_drift'):
                self.run_inspection()


class StockWiringTest(unittest.TestCase):
    def test_real_stock_wrapper_routes_inspection_without_changing_old_lanes(self):
        path = ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py'
        spec = importlib.util.spec_from_file_location('phase3_real_stock_wrapper', path)
        wrapper = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(wrapper)
        core, plan = wrapper.core, wrapper.local_profile_plan
        old = core.parse_command(core.PREFIX + f'{SHA} {plan.MODE} {plan.MASS3_OPERATION} {plan.MASS3_BATCH}')
        baseline = plan.remote_with_plan(core)
        saved_remote = core.REMOTE
        wrapper.activate_local_plan(old)
        self.assertEqual(baseline, core.REMOTE)
        core.REMOTE = saved_remote
        command = core.parse_command(core.PREFIX + f'{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        wrapper.activate_local_plan(command)
        ast.parse(core.REMOTE)
        anchor = "result['local_profile_plan']=run_local_profile_plan_4191(stage)"
        restored = core.REMOTE.removeprefix(m.REMOTE_HANDLER + '\n').replace(
            "result['local_profile_plan']=inspect_local_phase3(stage)", anchor, 1)
        self.assertEqual(baseline, restored)
        self.assertNotIn(m.MODE, wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(m.MODE, wrapper.DIRECT_ANEX_MODES)
        self.assertIs(core.bundle_source, plan.bundle_source)


if __name__ == '__main__':
    unittest.main()
