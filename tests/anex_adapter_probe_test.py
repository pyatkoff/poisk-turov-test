#!/usr/bin/env python3
"""Adapter report and SSH credential boundary, no network or PHP invocation."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import unittest
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("adapter_access", ROOT / "scripts/diagnostics/anex_access_probe.py")
probe = importlib.util.module_from_spec(spec)
spec.loader.exec_module(probe)
exec((ROOT / "scripts/diagnostics/anex_adapter_probe.py").read_text(), probe.__dict__)


class AdapterBoundary(unittest.TestCase):
    def setUp(self):
        probe.SENSITIVE_VALUES = ("fixture-secret",)
        self.report = {"mode": "adapter", "ok": True, "status": "ok",
            "checks": [{"check": name, "status": "ok", "http_status": 200} for name in
                       ("api_townfroms", "api_states", "api_checkin", "api_currencies", "api_nights")],
            "price_hotel_card_id_agrees": True,
            "samples": [{"anex_hotel_id": "469", "local_hotel_id": 245, "hotel": "Jaz Sharm Dreams",
                         "kind": "concrete", "price": {"amount": "1234.50", "currency": "EUR"},
                         "supplier_offer_id": "private-reference", "url": "https://private.invalid/", "nights": 7}],
            "flights": {"status": "ok", "route_count": 2, "option_count": 3, "routes": []}}

    def test_only_public_evidence_leaves_boundary(self):
        self.report["token"] = "fixture-secret"
        self.report["samples"][0]["room"] = "fixture-secret"
        clean = probe.clean_report(self.report)
        self.assertTrue(clean["ok"])
        self.assertIsNone(clean["samples"][0]["room"])
        text = json.dumps(clean)
        for value in ("fixture-secret", "private-reference", "private.invalid", "supplier_offer_id"):
            self.assertNotIn(value, text)
        self.assertFalse(clean["samples"][0]["final_price_verified"])

    def test_incomplete_evidence_cannot_pass(self):
        self.report["price_hotel_card_id_agrees"] = False
        self.assertFalse(probe.clean_report(self.report)["ok"])
        self.report["price_hotel_card_id_agrees"] = True
        self.report["samples"] = []
        self.assertFalse(probe.clean_report(self.report)["ok"])

    def test_ssh_transfers_sources_without_remote_files_or_token_arguments(self):
        env = {"ANEX_API_TOKEN": "fixture-secret", "ANEX_REFERENCE_TOKEN": "reference-secret",
               "ANYTOOUR_DEPLOY_SSH_KEY": "fixture-key", "ANYTOOUR_DEPLOY_HOST": "example.invalid",
               "ANYTOOUR_DEPLOY_USER": "fixture-user"}
        with mock.patch.dict(os.environ, env), mock.patch.object(probe.sys, "argv", ["probe", "--adapter"]), \
             mock.patch.object(probe.subprocess, "run", return_value=subprocess.CompletedProcess([], 0, json.dumps(self.report), "")) as run:
            result = probe.ssh_probe()
        self.assertTrue(result["ok"])
        args, kwargs = run.call_args
        command = args[0]
        self.assertIn('--adapter', command[-1])
        self.assertIn('cd "$HOME/www/anytoour.ru"', command[-1])
        self.assertNotIn("fixture-secret", " ".join(command))
        self.assertEqual(json.loads(kwargs["input"])["ANEX_API_TOKEN"], "fixture-secret")
        self.assertTrue(set(env).isdisjoint(kwargs["env"]))
        self.assertLess(len(command[-1].encode()), 100_000)


if __name__ == "__main__":
    unittest.main()
