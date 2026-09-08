#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_price_evidence_queue",
    ROOT / "scripts/diagnostics/anex_price_evidence_queue.py")
queue = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(queue)


class PriceEvidenceQueueTest(unittest.TestCase):
    def row(self, external_id, country="Турция", reason="competing_candidates"):
        return {
            "external_id": external_id,
            "status": "review",
            "reason": reason,
            "api_xml_relation": "same_record",
            "api": {"id": external_id, "name": "Hotel " + str(external_id),
                    "country": country, "region": "Region", "town": "Town"},
            "candidates": [{
                "id": external_id + 1000,
                "name": "Candidate " + str(external_id),
                "country": country,
                "region": "Region",
                "town": "Town",
                "score": 0.9,
                "distance_m": 25.0,
            }],
        }

    def test_groups_by_destination_and_caps_batches_at_30(self):
        rows = [self.row(i) for i in range(1, 32)] + [self.row(50, "Египет")]
        catalog = {"matches": [{"external_id": row["external_id"]} for row in rows]}
        result = queue.build_queue(catalog, {"rows": rows}, generated_at="fixed")
        self.assertEqual(result["counts"]["eligible"], 32)
        self.assertEqual([len(batch["hotel_ids"]) for batch in result["batches"]], [1, 30, 1])
        self.assertEqual(result["decision_policy"], "diagnostic_only")
        self.assertTrue(all(len(batch["hotel_ids"]) <= 30 for batch in result["batches"]))

    def test_excludes_conflicts_missing_details_and_duplicate_ids(self):
        good = self.row(1)
        duplicate = self.row(1)
        conflict = self.row(2, reason="coordinate_conflict")
        missing = self.row(3)
        missing.pop("api")
        catalog = {"matches": [{"external_id": value} for value in (1, 2, 3)]}
        result = queue.build_queue(catalog, {"rows": [good, duplicate, conflict, missing]},
                                   generated_at="fixed")
        self.assertEqual(result["counts"]["eligible"], 1)
        self.assertEqual(result["counts"]["excluded_by_reason"]["coordinate_conflict"], 1)
        self.assertEqual(result["counts"]["excluded_by_reason"]["incomplete_evidence"], 2)
        self.assertEqual(result["batches"][0]["hotel_ids"], [1])
        self.assertNotIn("accepted", result)

    def test_rejects_oversized_configured_batch(self):
        with self.assertRaises(ValueError):
            queue.build_queue({"matches": []}, {"rows": []}, batch_size=31)


if __name__ == "__main__":
    unittest.main()
