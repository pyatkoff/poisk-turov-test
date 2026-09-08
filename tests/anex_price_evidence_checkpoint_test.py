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
            "completed_ids": 3, "checked_ids": 3, "deferred_ids": 0,
            "new_ids": 3, "repeated_ids": 0,
            "no_offer": 1, "offer_seen": 2, "probe_unavailable": 0,
        })
        self.assertEqual([row["status"] for row in result["rows"]],
                         ["offer_seen", "no_offer", "offer_seen"])
        self.assertEqual(result["decision_policy"], "diagnostic_only")
        self.assertNotIn("accepted", result)

    def test_controlled_probe_failure_is_deferred_without_accepting_identity(self):
        report = {
            "ok": False,
            "mode": "price_evidence",
            "checks": [
                {"check": "api_townfroms", "status": "ok"},
                {"check": "selection", "status": "no_destinations"},
            ],
        }
        queue = {
            "schema_version": 1,
            "mode": "price_evidence_queue",
            "decision_policy": "diagnostic_only",
            "batches": [{"destination": "Азербайджан", "hotel_ids": [21, 22]}],
        }
        result = checkpoint.merge_failure_checkpoint(
            checkpoint.empty_checkpoint(), report, queue,
            checked_at="2026-09-08T00:00:00+00:00")
        self.assertEqual(result["counts"], {
            "completed_ids": 2, "checked_ids": 0, "deferred_ids": 2,
            "new_ids": 2, "repeated_ids": 0,
            "no_offer": 0, "offer_seen": 0, "probe_unavailable": 2,
        })
        self.assertTrue(all(row["status"] == "probe_unavailable"
                            and row["reason"] == "no_destinations"
                            for row in result["rows"]))
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

    def test_empty_filter_result_preserves_criteria_without_global_conclusion(self):
        report = self.report()
        report.update(returned_hotel_ids=[], missing_hotel_ids=[11, 12, 13], evidence=[],
                      empty_reason="no_hotels_for_filters")
        report["search"].update(departure="Москва", departure_id=1, destination_id=6,
                                checkin_begin="20261001", checkin_end="20261001",
                                nights_from=7, nights_till=7, price_page=1,
                                token="must-not-persist", searchKey="private")
        previous = checkpoint.empty_checkpoint()
        previous["rows"] = [{"external_id": 99, "destination": "Egypt",
                             "status": "offer_seen", "offer_count": 1}]
        result = checkpoint.merge_checkpoint(previous, report)
        self.assertEqual(result["rows"][-1], previous["rows"][0])
        self.assertEqual(result["counts"]["no_offer"], 3)
        for row in result["rows"][:3]:
            self.assertEqual(row["empty_reason"], "no_hotels_for_filters")
            self.assertEqual(row["search"]["checkin_begin"], "20261001")
            self.assertEqual(row["search"]["price_page"], 1)
            self.assertFalse(row["global_availability_known"])
            self.assertEqual(row["absence_interpretation"], "not_seen_in_requested_search_page")
            self.assertNotIn("token", row["search"])
            self.assertNotIn("searchKey", row["search"])
        self.assertEqual(result["last_batch"]["empty_reason"], "no_hotels_for_filters")

    def test_partial_page_absence_is_not_global_unavailability(self):
        report = self.report()
        report["external_results_not_loaded"] = True
        result = checkpoint.merge_checkpoint(checkpoint.empty_checkpoint(), report)
        absent = result["rows"][1]
        self.assertTrue(absent["external_results_not_loaded"])
        self.assertFalse(absent["global_availability_known"])
        self.assertEqual(absent["evidence_scope"], "requested_search_first_page")


if __name__ == "__main__":
    unittest.main()
