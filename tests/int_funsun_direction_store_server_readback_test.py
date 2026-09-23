#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts/diagnostics/int_funsun_direction_store_server_readback.py"
spec = importlib.util.spec_from_file_location("direction_store_readback", SCRIPT)
assert spec and spec.loader
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


def preflight(status: str = "ready", reason=None) -> dict:
    ready = status == "ready"
    return {
        "status": status,
        "reason": reason,
        "valid_probe_count": 2 if ready else 1,
        "normalized_probe_count": 2 if ready else 0,
        "independent_spo": ready,
        "independent_offer": ready,
        "receipt_sha256s": ["e" * 64, "f" * 64] if ready else ["e" * 64],
        "evidence_pair_sha256": "1" * 64 if ready else None,
        "runtime_evidence_sha256": "2" * 64 if ready else None,
        "store_dir_writable": True,
        "fresh_exchange_count": 2 if ready else 0,
    }


def base_remote(status: str = "absent") -> dict:
    return {
        "schema_version": 1,
        "source": "int-funsun-direction-store-server-readback-v1",
        "status": status,
        "direction": {
            "operator_family": "fun_and_sun",
            "market": "departure:1",
            "destination": "country:4",
        },
        "target_store_count": 0,
        "target_stores": [],
        "seed_preflight": preflight(),
        "supplier_calls": 0,
        "database_reads": 0,
        "database_writes": 0,
        "store_writes": 0,
        "runtime_writes": 0,
        "booking_calls": 0,
        "lead_calls": 0,
        "final_price_verified": False,
        "server_time": 1,
    }


class DirectionStoreReadbackTest(unittest.TestCase):
    def test_command_is_exact_current_source_shape(self):
        command = m.parse_command(
            "/run-int-direction-store-readback-v1 "
            + "f" * 40
            + " int-andromeda-funsun-direction-store-readback-20260923-v1"
        )
        self.assertEqual(command["source_sha"], "f" * 40)
        self.assertEqual(
            command["operation_id"],
            "int-andromeda-funsun-direction-store-readback-20260923-v1",
        )
        for bad in [
            "/run-int-direction-store-readback-v1 " + "f" * 39
            + " int-andromeda-funsun-direction-store-readback-20260923-v1",
            "/run-int-direction-store-readback-v1 " + "f" * 40
            + " int-andromeda-other-direction-store-readback-20260923-v1",
            "/run-int-server-v1 " + "f" * 40
            + " int-andromeda-funsun-direction-store-readback-20260923-v1",
        ]:
            with self.assertRaises(RuntimeError):
                m.parse_command(bad)

    def test_remote_contract_accepts_only_sanitized_confirmed_rule(self):
        digest = "a" * 64
        remote = base_remote("confirmed")
        remote["target_store_count"] = 1
        remote["target_stores"] = [{
            "direction_sha256": digest,
            "store_sha256": "b" * 64,
            "observation_count": 2,
            "independent_offer_count": 2,
            "independent_evidence_count": 2,
            "offer_set_sha256": "c" * 64,
            "evidence_set_sha256": "d" * 64,
            "facts": ["140.00|EUR|per_person_one_way|excluded"],
            "exchange_rates": ["102.7"],
            "fresh_exchange_rates": ["102.7"],
            "fresh_exchange_count": 2,
            "latest_exchange_observed_at": 1,
            "latest_exchange_expires_at": 2,
        }]
        self.assertEqual(m.validate_remote(remote)["status"], "confirmed")
        contaminated = dict(remote)
        contaminated["supplier_calls"] = 1
        with self.assertRaises(RuntimeError):
            m.validate_remote(contaminated)

    def test_seed_preflight_ready_requires_two_independent_normalized_receipts(self):
        remote = base_remote()
        self.assertEqual(m.validate_remote(remote)["seed_preflight"]["status"], "ready")
        broken = base_remote()
        broken["seed_preflight"] = preflight("ready")
        broken["seed_preflight"]["normalized_probe_count"] = 1
        with self.assertRaises(RuntimeError):
            m.validate_remote(broken)
        failed = base_remote()
        failed["seed_preflight"] = preflight("failed", "target_contract")
        self.assertEqual(m.validate_remote(failed)["seed_preflight"]["reason"], "target_contract")

    def test_remote_script_is_strictly_read_only_and_private_ids_are_not_emitted(self):
        source = m.REMOTE_PHP
        lower = source.lower()
        for forbidden in [
            "file_put_contents",
            "rename(",
            "unlink(",
            "mkdir(",
            "curl_",
            "http://",
            "https://",
            "insert into ",
            "update anytour_",
            "delete from ",
            "replace into ",
        ]:
            self.assertNotIn(forbidden, lower)
        self.assertIn("idsr_seed_preflight", source)
        self.assertIn("'valid_probe_count'", source)
        self.assertIn("'normalized_probe_count'", source)
        self.assertIn("'evidence_pair_sha256'", source)
        self.assertIn("'runtime_evidence_sha256'", source)
        self.assertIn("$offers[$offer]=true", source)
        self.assertIn("$evidence[$ev]=true", source)
        self.assertIn("'offer_set_sha256'", source)
        self.assertIn("'evidence_set_sha256'", source)
        self.assertNotIn("'spo_key'=>", source)
        self.assertNotIn("'selected_offer_ref_sha256'=>", source)
        self.assertIn("'supplier_calls'=>0", source)
        self.assertIn("'database_reads'=>0", source)
        self.assertIn("'store_writes'=>0", source)
        self.assertIn("'runtime_writes'=>0", source)
        self.assertIn("'final_price_verified'=>false", source)

    def test_insufficient_or_absent_is_a_valid_readback_not_false_confirmation(self):
        base = base_remote()
        self.assertEqual(m.validate_remote(base)["status"], "absent")
        failed = dict(base)
        failed.update({"status": "failed", "reason": "store_root_invalid"})
        failed.pop("direction")
        failed.pop("target_store_count")
        failed.pop("target_stores")
        failed.pop("seed_preflight")
        self.assertEqual(m.validate_remote(failed)["status"], "failed")


if __name__ == "__main__":
    unittest.main()
