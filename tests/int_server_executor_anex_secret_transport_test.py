#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import unittest
from pathlib import Path


def load_module():
    path = Path(__file__).resolve().parents[1] / "scripts/deploy/int_server_executor_anex_secret_transport.py"
    spec = importlib.util.spec_from_file_location("int_server_executor_anex_secret_transport", path)
    if spec is None or spec.loader is None:
        raise RuntimeError("test_import")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


m = load_module()


class SecretTransportTest(unittest.TestCase):
    def test_direct_modes_require_both_existing_secrets(self):
        command = {"mode": "anex-range", "operation_id": "int-anex-example-20260924-v1"}
        with self.assertRaisesRegex(RuntimeError, "anex_secret_transport_missing"):
            m.attach_transport(command, {})
        with self.assertRaisesRegex(RuntimeError, "anex_secret_transport_missing"):
            m.attach_transport(command, {"ANEX_API_TOKEN": "api"})
        with self.assertRaisesRegex(RuntimeError, "anex_secret_transport_missing"):
            m.attach_transport(command, {"ANEX_B2B_TOKEN": "b2b"})

        out = m.attach_transport(
            command,
            {"ANEX_API_TOKEN": " api-secret ", "ANEX_B2B_TOKEN": " b2b-secret "},
        )
        self.assertEqual("api-secret", out[m.TRANSPORT_API_KEY])
        self.assertEqual("b2b-secret", out[m.TRANSPORT_B2B_KEY])
        self.assertNotIn(m.TRANSPORT_API_KEY, command)
        self.assertNotIn(m.TRANSPORT_B2B_KEY, command)

    def test_non_direct_mode_never_carries_anex_secrets(self):
        command = {"mode": "local-readback", "operation_id": "int-andromeda-example-20260924-v1"}
        out = m.attach_transport(
            command,
            {"ANEX_API_TOKEN": "api-secret", "ANEX_B2B_TOKEN": "b2b-secret"},
        )
        self.assertEqual(command, out)
        self.assertNotIn(m.TRANSPORT_API_KEY, out)
        self.assertNotIn(m.TRANSPORT_B2B_KEY, out)

    def test_remote_patch_pops_transport_before_receipt_and_restores_child_env(self):
        base = m.load_executor()
        patched = m.patch_remote(base.REMOTE)
        self.assertIn(
            "_transport_anex_api_token=payload.pop('__int_anex_api_token',None)",
            patched,
        )
        self.assertIn(
            "_transport_anex_b2b_token=payload.pop('__int_anex_b2b_token',None)",
            patched,
        )
        self.assertIn("env['ANEX_API_TOKEN']=_transport_anex_api_token", patched)
        self.assertIn("env['ANEX_B2B_TOKEN']=_transport_anex_b2b_token", patched)
        self.assertIn("fail('anex_secret_transport_missing')", patched)
        self.assertEqual(1, patched.count("__int_anex_api_token"))
        self.assertEqual(1, patched.count("__int_anex_b2b_token"))

    def test_patch_fails_closed_on_executor_drift(self):
        with self.assertRaisesRegex(RuntimeError, "secret_transport_payload_anchor"):
            m.patch_remote("payload={}\n")


if __name__ == "__main__":
    unittest.main()
