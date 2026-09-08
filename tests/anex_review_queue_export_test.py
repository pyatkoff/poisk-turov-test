#!/usr/bin/env python3
import json
from pathlib import Path
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts" / "diagnostics"))
import anex_review_queue_export as exporter


class QueueExportTest(unittest.TestCase):
    def row(self, identifier, status, reason, candidates=None):
        return {"external_id": identifier, "status": status, "reason": reason,
                "xml": {"name": f"Hotel {identifier}"}, "candidates": candidates or []}

    def test_splits_without_accepting_strong_candidates(self):
        checkpoint = {"schema_version": 2, "counts": {"strong_candidate": 1}, "rows": [
            self.row(4, "strong_candidate", "name_country_coordinates", [{"id": 44}]),
            self.row(3, "unmatched", "no_candidates"),
            self.row(2, "review", "details_unavailable"),
            self.row(1, "review", "competing_candidates", [{"id": 11, "name": "Candidate"}]),
        ]}
        queues = exporter.split_queues(checkpoint)
        self.assertEqual([row["anex_hotel_id"] for row in queues["review"]], [1])
        self.assertEqual([row["anex_hotel_id"] for row in queues["errors"]], [2])
        self.assertEqual([row["anex_hotel_id"] for row in queues["unmatched"]], [3])
        self.assertNotIn(4, [row["anex_hotel_id"] for rows in queues.values() for row in rows])

    def test_writes_json_csv_and_summary(self):
        checkpoint = {"schema_version": 2, "counts": {"strong_candidate": 0}, "rows": [
            self.row(1, "review", "catalog_unavailable")
        ]}
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            source = directory / "checkpoint.json"
            source.write_text(json.dumps(checkpoint), encoding="utf-8")
            summary = exporter.export(source, directory)
            self.assertEqual(summary["queues"]["errors"], 1)
            for name in ("review", "errors", "unmatched"):
                self.assertTrue((directory / f"anex-{name}-queue.json").is_file())
                self.assertTrue((directory / f"anex-{name}-queue.csv").is_file())

    def test_rejects_duplicates(self):
        checkpoint = {"schema_version": 2, "rows": [
            self.row(1, "review", "competing_candidates"),
            self.row(1, "review", "competing_candidates"),
        ]}
        with self.assertRaises(ValueError):
            exporter.split_queues(checkpoint)


if __name__ == "__main__":
    unittest.main()
