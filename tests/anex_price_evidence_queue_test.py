#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import subprocess
import sys
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

    def test_excludes_completed_ids_without_resetting_queue(self):
        rows = [self.row(1), self.row(2), self.row(3)]
        catalog = {"matches": [{"external_id": value} for value in (1, 2, 3)]}
        result = queue.build_queue(catalog, {"rows": rows}, completed={1, 3},
                                   generated_at="fixed")
        self.assertEqual(result["counts"]["already_checked"], 2)
        self.assertEqual(result["counts"]["eligible"], 1)
        self.assertEqual(result["batches"][0]["hotel_ids"], [2])

    def test_rejects_invalid_checkpoint_instead_of_starting_over(self):
        with self.assertRaises(ValueError):
            queue.completed_ids({"schema_version": 1, "rows": []})
        with self.assertRaises(ValueError):
            queue.completed_ids({
                "schema_version": 1,
                "mode": "price_evidence_checkpoint",
                "decision_policy": "diagnostic_only",
                "rows": [{"external_id": 7}, {"external_id": 7}],
            })

    def test_rejects_oversized_configured_batch(self):
        with self.assertRaises(ValueError):
            queue.build_queue({"matches": []}, {"rows": []}, batch_size=31)

    def exact(self, external_id, country="Турция"):
        row = self.row(external_id, country)
        return {"external_id": external_id, "status": "verified_auto",
                "country": country, "name": "Exact Hotel", "town": "Town",
                "catalog_hotel_id": external_id + 1000,
                "candidates": [dict(row["candidates"][0], score=1.4)]}

    def checkpoint(self, rows):
        return {"schema_version": 1, "mode": "price_evidence_checkpoint",
                "decision_policy": "diagnostic_only", "rows": rows}

    def test_sales_first_includes_exact_and_strong_without_accepting_them(self):
        exact = self.exact(10)
        exact["candidates"].append({"id": 42, "country": "Египет", "score": 0.7})
        strong = self.row(20)
        strong["status"] = "strong_candidate"
        review = self.row(30)
        catalog = {"matches": [exact]}
        geo = {"rows": [review, strong]}
        result = queue.build_queue(catalog, geo, sales_first=True, generated_at="fixed")
        self.assertEqual(result["batches"][0]["hotel_ids"], [10, 20, 30])
        self.assertEqual(result["counts"]["queued_by_matching_status"],
                         {"review": 1, "strong_candidate": 1, "verified_auto": 1})
        for entry in result["batches"][0]["entries"]:
            self.assertEqual(entry["decision_policy"], "diagnostic_only")
            self.assertNotIn("accepted", entry)
        legacy = queue.build_queue(catalog, geo)
        self.assertEqual(legacy["batches"][0]["hotel_ids"], [30])

    def test_observed_offer_destinations_first_keeps_unobserved_backlog(self):
        checkpoint = self.checkpoint([
            {"external_id": 1, "status": "offer_seen", "destination": "Турция"},
            {"external_id": 2, "status": "no_offer", "destination": "Абхазия"},
            {"external_id": 3, "status": "probe_unavailable", "destination": "Египет"},
        ])
        observed = queue.observed_offer_destinations(checkpoint)
        self.assertEqual(observed, {"Турция"})
        rows = [self.row(1), self.row(2, "Абхазия"), self.row(3, "Египет"),
                self.row(4, "Абхазия"), self.row(5), self.row(6, "Египет")]
        result = queue.build_queue({"matches": []}, {"rows": rows}, sales_first=True,
                                   completed=queue.completed_ids(checkpoint),
                                   observed_destinations=observed, generated_at="fixed")
        self.assertEqual([batch["destination"] for batch in result["batches"]],
                         ["Турция", "Абхазия", "Египет"])
        self.assertEqual([identifier for b in result["batches"] for identifier in b["hotel_ids"]],
                         [5, 4, 6])
        self.assertEqual(result["counts"]["already_checked"], 3)
        self.assertEqual(result["counts"]["checkpoint_completed_ids"], 3)

    def test_sales_first_keeps_geo_conflict_and_identity_guards(self):
        rows = []
        for identifier, reason in enumerate(sorted(queue.BLOCKED_REASONS), start=1):
            row = self.row(identifier, reason=reason)
            row["status"] = "strong_candidate"
            rows.append(row)
        bad_identity = self.row(20)
        bad_identity["status"] = "strong_candidate"
        bad_identity["api"]["id"] = 999
        rows.extend([bad_identity, self.row(30), self.row(30)])
        catalog = {"matches": [self.exact(row["external_id"]) for row in rows]}
        result = queue.build_queue(catalog, {"rows": rows}, sales_first=True)
        self.assertEqual(result["batches"][0]["hotel_ids"], [30])
        self.assertEqual(result["counts"]["excluded_by_reason"]["incomplete_evidence"], 2)
        for reason in queue.BLOCKED_REASONS:
            self.assertEqual(result["counts"]["excluded_by_reason"][reason], 1)

    def test_invalid_exact_proposals_not_queued(self):
        rows = [self.exact(i) for i in range(1, 7)]
        rows[0]["catalog_hotel_id"] = 999
        rows[1]["candidates"][0]["country"] = "Египет"
        rows[2]["candidates"][0]["score"] = 1.2
        rows[3]["candidates"].append(dict(rows[3]["candidates"][0]))
        rows[4]["catalog_hotel_id"] = 0
        result = queue.build_queue({"matches": rows}, {"rows": []}, sales_first=True)
        self.assertEqual(result["batches"][0]["hotel_ids"], [6])
        self.assertEqual(result["counts"]["excluded_by_reason"]["incomplete_exact_evidence"], 5)

    def test_invalid_checkpoint_status_counts_or_observation_fail_closed(self):
        with self.assertRaises(ValueError):
            queue.completed_ids(self.checkpoint([{"external_id": 1, "status": "unknown"}]))
        checkpoint = self.checkpoint([{"external_id": 1, "status": "no_offer"}])
        checkpoint["counts"] = {"completed_ids": 0}
        with self.assertRaises(ValueError):
            queue.completed_ids(checkpoint)
        with self.assertRaises(ValueError):
            queue.observed_offer_destinations(self.checkpoint([
                {"external_id": 1, "status": "offer_seen", "destination": ""}]))

    def test_sales_first_missing_checkpoint_cannot_start_from_zero(self):
        result = subprocess.run([
            sys.executable, str(ROOT / "scripts/diagnostics/anex_price_evidence_queue.py"),
            "--sales-first", "--catalog", "missing-catalog.json", "--geo", "missing-geo.json",
            "--output", "unused.json", "--checkpoint", "missing-checkpoint.json",
        ], capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("requires an existing restored price checkpoint", result.stderr)


if __name__ == "__main__":
    unittest.main()
