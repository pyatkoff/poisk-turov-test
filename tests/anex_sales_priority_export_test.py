#!/usr/bin/env python3
import copy
import csv
import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts/diagnostics/anex_sales_priority_export.py"
SPEC = importlib.util.spec_from_file_location("anex_sales_priority_export", SCRIPT)
export = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(export)


class SalesPriorityTest(unittest.TestCase):
    def inputs(self):
        catalog = {"matches": [{"external_id": i, "status": "verified_auto" if i == 1 else "unmatched",
                                "catalog_hotel_id": 101 if i == 1 else None,
                                "candidates": [{"id": 101, "name": "Candidate"}] if i == 1 else []}
                               for i in range(1, 8)]}
        geo = {"rows": [{"external_id": 2, "status": "strong_candidate", "candidates": [{"id": 102}]},
                         {"external_id": 3, "status": "review", "reason": "details_unavailable"},
                         {"external_id": 4, "status": "future_status"}]}
        checkpoint = {"schema_version": 1, "mode": "price_evidence_checkpoint", "decision_policy": "diagnostic_only",
                      "updated_at": "2026-09-08T12:00:00+00:00", "rows": [
                          {"external_id": i, "status": "offer_seen", "hotel": "=formula", "offer_count": 1,
                           "last_checked_at": "2026-09-08T11:00:00+00:00"} for i in range(1, 6)] + [
                          {"external_id": 6, "status": "no_offer"},
                          {"external_id": 7, "status": "probe_unavailable", "reason": "timeout"}]}
        return catalog, geo, checkpoint

    def test_full_catalog_priority_and_no_automatic_acceptance(self):
        inputs = self.inputs()
        original = copy.deepcopy(inputs)
        report = export.build_report(*inputs)
        self.assertEqual([row["external_id"] for row in report["rows"]], [1, 2, 3, 5, 4])
        self.assertEqual(report["counts"]["priority_ids"], 5)
        self.assertEqual(report["counts"]["catalog_verified_unique_anytour_candidates"], 1)
        self.assertEqual(report["counts"]["no_offer"], 1)
        self.assertEqual(report["counts"]["checkpoint_deferred_ids"], 1)
        self.assertTrue(all(row["mapping_decision"] == "not_accepted_by_export" for row in report["rows"]))
        self.assertTrue(all(row["global_availability_known"] is False for row in report["rows"]))
        self.assertIn("not globally unsellable", report["no_offer_policy"])
        self.assertEqual(inputs, original)
        self.assertEqual(report, export.build_report(*inputs))

    def test_search_scope_is_unknown_for_legacy_and_allowlisted_for_new(self):
        catalog, geo, checkpoint = self.inputs()
        checkpoint["rows"][0]["search"] = {"departure_id": 1, "checkin_begin": "20261001", "token": "secret"}
        checkpoint["rows"][0]["evidence_scope"] = "requested_search_first_page"
        report = export.build_report(catalog, geo, checkpoint)
        self.assertEqual(report["rows"][0]["scope_status"], "recorded_search_only")
        self.assertNotIn("token", report["rows"][0]["search"])
        self.assertEqual(report["rows"][1]["scope_status"], "unknown")

    def test_duplicate_invalid_and_missing_source_ids_fail_closed(self):
        for source_index, key in ((0, "matches"), (1, "rows"), (2, "rows")):
            inputs = self.inputs()
            inputs[source_index][key].append(copy.deepcopy(inputs[source_index][key][0]))
            with self.assertRaises(ValueError):
                export.build_report(*inputs)
        for identifier in (True, 0, 999):
            inputs = self.inputs()
            inputs[2]["rows"][0]["external_id"] = identifier
            with self.assertRaises(ValueError):
                export.build_report(*inputs)

    def test_cli_provenance_csv_safety_missing_checkpoint_and_immutable_inputs(self):
        with tempfile.TemporaryDirectory() as tmp:
            directory = Path(tmp)
            command = [sys.executable, str(SCRIPT)]
            hashes = {}
            for key, value in zip(("catalog", "geo", "checkpoint"), self.inputs()):
                path = directory / (key + ".json")
                path.write_text(json.dumps(value), encoding="utf-8")
                hashes[key] = hashlib.sha256(path.read_bytes()).hexdigest()
                command += ["--" + key, str(path)]
            command += ["--output-dir", str(directory / "out")]
            result = subprocess.run(command, capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            report = json.loads((directory / "out/anex-sales-priority.json").read_text())
            self.assertEqual(report["sources"], {key + "_sha256": value for key, value in hashes.items()})
            with (directory / "out/anex-sales-priority.csv").open(encoding="utf-8-sig", newline="") as handle:
                self.assertEqual(next(csv.DictReader(handle))["supplier_name"], "'=formula")
            for key, digest in hashes.items():
                self.assertEqual(hashlib.sha256((directory / (key + ".json")).read_bytes()).hexdigest(), digest)
            (directory / "checkpoint.json").unlink()
            self.assertNotEqual(subprocess.run(command, capture_output=True).returncode, 0)
            (directory / "checkpoint.json").write_text("{}")
            self.assertNotEqual(subprocess.run(command, capture_output=True).returncode, 0)


if __name__ == "__main__":
    unittest.main()
