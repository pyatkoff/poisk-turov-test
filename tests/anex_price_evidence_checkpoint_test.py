#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_price_evidence_checkpoint",
    ROOT / "scripts/diagnostics/anex_price_evidence_checkpoint.py")
checkpoint = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(checkpoint)


class PriceEvidenceCheckpointTest(unittest.TestCase):
    def report(self):
        return {
            "ok": True,
            "mode": "price_evidence",
            "search": {"destination": "Турция", "requested_hotels": 3},
            "requested_hotel_ids": [11, 12, 13],
            "returned_hotel_ids": [11, 13],
            "missing_hotel_ids": [12],
            "unexpected_offer_count": 1,
            "external_results_not_loaded": False,
            "evidence": [
                {"external_id": 11, "offer_count": 2, "hotel": "One", "star": "5*",
                 "rooms": ["Standard"], "meals": ["AI"]},
                {"external_id": 13, "offer_count": 1, "hotel": "Three", "star": "4*",
                 "rooms": [], "meals": []},
            ],
        }

    def test_merges_offer_and_no_offer_rows_without_accepting_identity(self):
        result = checkpoint.merge_checkpoint(checkpoint.empty_checkpoint(), self.report(),
                                             checked_at="2026-09-08T00:00:00+00:00")
        self.assertEqual(result["counts"], {
            "checked_ids": 3, "new_ids": 3, "repeated_ids": 0,
            "no_offer": 1, "offer_seen": 2,
        })
        self.assertEqual([row["status"] for row in result["rows"]],
                         ["offer_seen", "no_offer", "offer_seen"])
        self.assertEqual(result["decision_policy"], "diagnostic_only")
        self.assertNotIn("accepted", result)

    def test_repeated_batch_is_idempotent_by_external_id(self):
        first = checkpoint.merge_checkpoint(checkpoint.empty_checkpoint(), self.report(),
                                            checked_at="first")
        second = checkpoint.merge_checkpoint(first, self.report(), checked_at="second")
        self.assertEqual(second["counts"]["checked_ids"], 3)
        self.assertEqual(second["counts"]["new_ids"], 0)
        self.assertEqual(second["counts"]["repeated_ids"], 3)
        self.assertEqual(len(second["rows"]), 3)
        self.assertTrue(all(row["first_checked_at"] == "first" for row in second["rows"]))

    def test_rejects_incomplete_partition_and_failed_report(self):
        report = self.report()
        report["missing_hotel_ids"] = []
        with self.assertRaises(ValueError):
            checkpoint.merge_checkpoint(checkpoint.empty_checkpoint(), report)
        report = self.report()
        report["ok"] = False
        with self.assertRaises(ValueError):
            checkpoint.merge_checkpoint(checkpoint.empty_checkpoint(), report)

    def test_invalid_existing_checkpoint_never_resets_to_empty(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "checkpoint.json"
            path.write_text('{"rows": []}', encoding="utf-8")
            with self.assertRaises(ValueError):
                checkpoint.load_checkpoint(path)


if __name__ == "__main__":
    unittest.main()
