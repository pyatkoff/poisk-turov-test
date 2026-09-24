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


if __name__ == '__main__':
    unittest.main()
