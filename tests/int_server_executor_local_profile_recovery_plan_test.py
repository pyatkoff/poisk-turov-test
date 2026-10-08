"""The stopped LOCAL recovery stays blocked at parsing and direct activation."""
from __future__ import annotations

import ast
import importlib.util
from pathlib import Path
import re
import subprocess
import types
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT/'scripts/deploy/int_server_executor_local_profile_recovery_plan.py'
spec = importlib.util.spec_from_file_location('local_profile_recovery_test_subject', SCRIPT)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
SHA = 'a'*40


class ParserAndActivationTest(unittest.TestCase):
    def core(self):
        previous=mock.Mock(side_effect=ValueError('previous_parser'))
        return types.SimpleNamespace(PREFIX='/run-int-server-v1 ',SHA_RE=re.compile(r'\A[a-f0-9]{40}\Z'),
                                     parse_command=previous),previous

    def test_stopped_exact_pair_is_rejected_before_previous_parser(self):
        core,previous=self.core();m.register_parser(core)
        with self.assertRaisesRegex(ValueError,m.BLOCKED_REASON):
            core.parse_command(f'{core.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}')
        previous.assert_not_called()

    def test_invalid_scope_and_apply_flags_fail_closed(self):
        core,_=self.core();m.register_parser(core)
        exact=f'{core.PREFIX}{SHA} {m.MODE} {m.OPERATION} {m.BATCH}'
        for value in (exact+' --apply',exact.replace(m.BATCH,'other'),exact.replace(m.OPERATION,m.OPERATION+'-2'),
                      exact.replace(SHA,'z'*40)):
            with self.subTest(value=value),self.assertRaises(ValueError):
                core.parse_command(value)

    def test_unrelated_commands_keep_previous_parser(self):
        core,previous=self.core();m.register_parser(core)
        for body in ('unrelated text',core.PREFIX+SHA,
                     f'{core.PREFIX}{SHA} {m.MODE} unrelated-operation {m.BATCH}',
                     f'{core.PREFIX}{SHA} unrelated-mode {m.OPERATION} {m.BATCH}'):
            with self.subTest(body=body),self.assertRaisesRegex(ValueError,'previous_parser'):
                core.parse_command(body)
            previous.assert_called_with(body)
        self.assertEqual(4,previous.call_count)

    def test_saved_or_widened_payload_rejected_before_remote_generation(self):
        core,_=self.core()
        core.REMOTE='unchanged';core.bundle_source=object()
        before_bundle=core.bundle_source
        plan=types.SimpleNamespace(remote_with_plan=mock.Mock())
        for writes in (0,1):
            command={'source_sha':SHA,'mode':m.MODE,'operation_id':m.OPERATION,
                     'batch':m.BATCH,'maximum_writes':writes,'provider_http_calls':0}
            with self.subTest(writes=writes),self.assertRaisesRegex(ValueError,m.BLOCKED_REASON):
                m.activate(core,command,plan)
        plan.remote_with_plan.assert_not_called()
        self.assertEqual('unchanged',core.REMOTE)
        self.assertIs(before_bundle,core.bundle_source)


class RecoveryContractTest(unittest.TestCase):
    def test_remote_handler_is_read_only_and_bounded(self):
        ast.parse(m.REMOTE_HANDLER)
        self.assertIn("'maximum_writes']!=0",m.REMOTE_HANDLER)
        self.assertIn("'provider_http_calls']!=0",m.REMOTE_HANDLER)
        self.assertIn("verified_pre_main_no_recorded_scope",m.REMOTE_HANDLER)
        self.assertIn("failed_operation_replay_allowed') is not False",m.REMOTE_HANDLER)
        self.assertIn("profiles_with_delta':2000",m.REMOTE_HANDLER)
        self.assertIn("source_plans_prepared':2000",m.REMOTE_HANDLER)
        self.assertIn("database_writes']=0",m.REMOTE_HANDLER)
        self.assertNotIn("curl_exec(",m.REMOTE_HANDLER)
        self.assertNotIn("requests.",m.REMOTE_HANDLER)

    def test_php_runner_verifies_evidence_before_database_and_never_applies(self):
        path=ROOT/'scripts/diagnostics/local_profile_mass_recovery_plan_4191.php'
        source=path.read_text()
        self.assertLess(source.index('$evidence=lpmr_evidence($home)'),source.index('$db=v2_data_db()'))
        self.assertIn("LPMR_INSPECTION_INPUT_SHA='3caa94dc41a2eaec57de720c2655ec5ec6c042ae2f4552668e68190e37a40d27'",source)
        self.assertIn("LPMR_FAILED_RUNNER_SHA='02f55562e01c4874d8abe0dd7754873bd051af9a4f7ac389c8c8b36601b0abf3'",source)
        self.assertIn("!array_key_exists('scripts/diagnostics/local_profile_mass_apply2_4191.php'",source)
        self.assertIn("SET SESSION TRANSACTION READ ONLY",source)
        self.assertIn("'safe_to_apply'=>false",source)
        self.assertNotIn('->apply(',source)
        self.assertNotIn('curl_',source)
        self.assertNotIn('file_get_contents(',source)
        lint=subprocess.run(['php','-l',str(path)],capture_output=True,text=True,timeout=30)
        self.assertEqual(0,lint.returncode,lint.stderr)

    def test_direct_activation_leaves_stock_dispatch_unchanged(self):
        baseline="prefix\nresult['local_profile_plan']=run_local_profile_plan_4191(stage)\nsuffix\n"
        plan=types.SimpleNamespace(remote_with_plan=mock.Mock(return_value=baseline),bundle_source=object())
        previous=mock.Mock(side_effect=ValueError('previous_parser'))
        core=types.SimpleNamespace(PREFIX='/run-int-server-v1 ',SHA_RE=re.compile(r'\A[a-f0-9]{40}\Z'),
                                   parse_command=previous,REMOTE='unchanged',bundle_source=None)
        m.register_parser(core)
        command={'source_sha':SHA,'mode':m.MODE,'operation_id':m.OPERATION,
                 'batch':m.BATCH,'maximum_writes':0,'provider_http_calls':0}
        with self.assertRaisesRegex(ValueError,m.BLOCKED_REASON):
            m.activate(core,command,plan)
        plan.remote_with_plan.assert_not_called()
        self.assertEqual('unchanged',core.REMOTE)
        self.assertIsNone(core.bundle_source)


class StockWiringTest(unittest.TestCase):
    def test_real_wrapper_rejects_stopped_recovery_before_dispatch(self):
        path=ROOT/'scripts/deploy/int_server_executor_anex_secret_transport.py'
        spec=importlib.util.spec_from_file_location('local_recovery_real_wrapper',path)
        wrapper=importlib.util.module_from_spec(spec);spec.loader.exec_module(wrapper)
        core,plan=wrapper.core,wrapper.local_profile_plan
        saved=core.REMOTE;before_bundle=core.bundle_source
        exact=core.PREFIX+f'{SHA} {m.MODE} {m.OPERATION} {m.BATCH}'
        with self.assertRaisesRegex(ValueError,m.BLOCKED_REASON):
            core.parse_command(exact)
        command={'source_sha':SHA,'mode':m.MODE,'operation_id':m.OPERATION,
                 'batch':m.BATCH,'maximum_writes':0,'provider_http_calls':0}
        with mock.patch.object(plan,'remote_with_plan') as remote:
            with self.assertRaisesRegex(ValueError,m.BLOCKED_REASON):
                wrapper.activate_local_plan(command)
            remote.assert_not_called()
        self.assertEqual(saved,core.REMOTE)
        self.assertIs(before_bundle,core.bundle_source)
        self.assertIn(m.RUNNER,plan.BUNDLE_FILES)
        self.assertNotIn(m.MODE,wrapper.SUPPLIER_SLOT_MODES)
        self.assertNotIn(m.MODE,wrapper.DIRECT_ANEX_MODES)


if __name__=='__main__':
    unittest.main()
