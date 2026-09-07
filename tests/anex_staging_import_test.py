import importlib.util
import json
from pathlib import Path
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
path = ROOT / "scripts/diagnostics/anex_staging_import.py"
spec = importlib.util.spec_from_file_location("anex_staging", path)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def row(identifier=10, status="strong_candidate"):
    candidates = [{"id": i, "name": "Hotel", "score": 1 - i / 1000}
                  for i in range(100, 107)]
    return {
        "external_id": identifier,
        "fingerprint": "a" * 24,
        "original_status": "review",
        "status": status,
        "reason": "name_country_coordinates",
        "checked_at": "2026-09-07T20:00:00+00:00",
        "api_xml_relation": "same_record",
        "xml": {"id": identifier, "name": "Hotel"},
        "api": {"id": identifier, "name": "Hotel", "country": "Turkey"},
        "candidates": candidates if status != "unmatched" else [],
    }


def checkpoint(rows):
    statuses = ("review", "strong_candidate", "unmatched")
    return {"schema_version": 2, "processed_total": len(rows), "remaining": 12,
            "counts": {status: sum(item["status"] == status for item in rows) for status in statuses},
            "rows": rows}


class StagingPayloadTest(unittest.TestCase):
    def write(self, data):
        temp = tempfile.NamedTemporaryFile(mode="w", encoding="utf-8", delete=False)
        json.dump(data, temp)
        temp.close()
        self.addCleanup(Path(temp.name).unlink)
        return temp.name

    def test_valid_checkpoint_is_compact_and_keeps_top_five(self):
        meta, rows = module.load_checkpoint(self.write(checkpoint([row()])))
        self.assertEqual(meta["processed_total"], 1)
        self.assertEqual(meta["strong_candidate_count"], 1)
        self.assertEqual(len(rows[0]["candidates"]), 5)
        self.assertEqual(rows[0]["candidate_count"], 7)
        self.assertRegex(rows[0]["row_digest"], r"^[0-9a-f]{64}$")
        self.assertNotIn("decision_status", rows[0])

    def test_duplicate_ids_and_bad_totals_are_rejected(self):
        with self.assertRaises(ValueError):
            module.load_checkpoint(self.write(checkpoint([row(), row()])))
        bad = checkpoint([row()])
        bad["processed_total"] = 2
        with self.assertRaises(ValueError):
            module.load_checkpoint(self.write(bad))

    def test_xml_online_conflict_is_rejected(self):
        item = row()
        item["api"]["id"] = 11
        with self.assertRaises(ValueError):
            module.load_checkpoint(self.write(checkpoint([item])))

    def test_writer_never_mutates_catalog_or_decisions(self):
        source = (ROOT / "scripts/diagnostics/anex_staging_writer.php").read_text(encoding="utf-8")
        self.assertNotRegex(source, r"(?i)(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+catalog_hotels")
        self.assertNotRegex(source, r"(?i)(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+anex_hotel_decisions")


if __name__ == "__main__":
    unittest.main()
