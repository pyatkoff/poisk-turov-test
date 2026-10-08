#!/usr/bin/env python3
from __future__ import annotations

import base64
import importlib.util
import os
from pathlib import Path
import unittest
from unittest import mock

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/deploy/int_server_executor_anex_secret_transport.py'
spec = importlib.util.spec_from_file_location('int_server_executor_anex_secret_transport', SCRIPT)
assert spec is not None and spec.loader is not None
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

completion_spec = importlib.util.spec_from_file_location(
    'int_executor_completion_regressions', Path(__file__).with_name('int_server_executor_test.py'))
assert completion_spec is not None and completion_spec.loader is not None
completion_tests = importlib.util.module_from_spec(completion_spec)
completion_spec.loader.exec_module(completion_tests)


class WrappedCompletionTest(completion_tests.CompletionTest):
    ENTRYPOINT = m
    CONTROL = m.core


class AnexSecretTransportTest(unittest.TestCase):
    def test_existing_secret_names_are_encoded_without_value_logging(self):
        with mock.patch.dict(os.environ, {
            'ANEX_API_TOKEN': 'api-token-value',
            'ANEX_B2B_TOKEN': 'b2b-token-value',
        }, clear=False):
            payload = m.validated_anex_secret_payload()
        self.assertEqual('api-token-value', base64.b64decode(payload['_anex_api_token_b64']).decode())
        self.assertEqual('b2b-token-value', base64.b64decode(payload['_anex_b2b_token_b64']).decode())
        self.assertNotIn('api-token-value', repr(m.patched_remote()))
        self.assertNotIn('b2b-token-value', repr(m.patched_remote()))

    def test_missing_or_invalid_secrets_fail_before_transport(self):
        cases = [
            {'ANEX_API_TOKEN': '', 'ANEX_B2B_TOKEN': 'b2b'},
            {'ANEX_API_TOKEN': 'api', 'ANEX_B2B_TOKEN': ''},
            {'ANEX_API_TOKEN': 'api', 'ANEX_B2B_TOKEN': 'Bearer bad'},
            {'ANEX_API_TOKEN': 'api', 'ANEX_B2B_TOKEN': 'bad token'},
        ]
        for env in cases:
            with self.subTest(env=env), mock.patch.dict(os.environ, env, clear=True):
                with self.assertRaises(ValueError):
                    m.validated_anex_secret_payload()

    def test_remote_patch_is_exact_and_direct_only(self):
        patched = m.patched_remote()
        self.assertEqual(1, patched.count("payload.pop('_anex_api_token_b64',None)"))
        self.assertEqual(1, patched.count("payload.pop('_anex_b2b_token_b64',None)"))
        self.assertIn("if mode in ('anex-demand','anex-range'):", patched)
        self.assertIn("env['ANEX_API_TOKEN']=api_token", patched)
        self.assertIn("env['ANEX_B2B_TOKEN']=b2b_token", patched)
        self.assertEqual({'anex-demand', 'anex-range'}, set(m.DIRECT_ANEX_MODES))

    def test_wrapper_preserves_existing_supplier_slot_contract(self):
        self.assertIn('anex-range', m.SUPPLIER_SLOT_MODES)
        self.assertIn('program-fuel-probe', m.SUPPLIER_SLOT_MODES)
        self.assertIn('match-common4-acquire', m.SUPPLIER_SLOT_MODES)
        self.assertIn('match-common4-continuation-remainder', m.SUPPLIER_SLOT_MODES)
        self.assertIn('andromeda-scope', m.SUPPLIER_SLOT_MODES)
        self.assertIn('andromeda-external-group', m.SUPPLIER_SLOT_MODES)
        self.assertIn('andromeda-operator-scope', m.SUPPLIER_SLOT_MODES)
        self.assertNotIn('local-readback', m.SUPPLIER_SLOT_MODES)

    def test_workflow_uses_existing_github_secrets_and_wrapper(self):
        workflow = (Path(__file__).resolve().parents[1] / '.github/workflows/int-server-executor.yml').read_text()
        self.assertIn('ANEX_API_TOKEN: ${{ secrets.ANEX_API_TOKEN }}', workflow)
        self.assertIn('ANEX_B2B_TOKEN: ${{ secrets.ANEX_B2B_TOKEN }}', workflow)
        self.assertIn('python3 scripts/deploy/int_server_executor_anex_secret_transport.py --source-root source', workflow)
        self.assertIn('python3 tests/int_server_executor_anex_secret_transport_test.py -v', workflow)


def load_tests(loader, tests, pattern):
    # Extend the already-required stock check; do not add another workflow.
    for name, module_name in (
        ('int_server_executor_local_profile_phase3_inspection_test.py', 'local_phase3_inspection_regressions'),
        ('int_server_executor_local_profile_recovery_plan_test.py', 'local_profile_recovery_regressions'),
        ('int_server_executor_local_profile_metadata_test.py', 'local_profile_metadata_regressions'),
    ):
        path = Path(__file__).with_name(name)
        spec = importlib.util.spec_from_file_location(module_name, path)
        assert spec is not None and spec.loader is not None
        suite = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(suite)
        tests.addTests(loader.loadTestsFromModule(suite))
    return tests


if __name__ == '__main__':
    unittest.main()
