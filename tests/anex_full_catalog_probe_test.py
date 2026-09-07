#!/usr/bin/env python3
"""Offline matching and artifact checks; no supplier, DB or SSH access."""
import csv
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("catalog_access", ROOT / "scripts/diagnostics/anex_access_probe.py")
probe = importlib.util.module_from_spec(spec)
spec.loader.exec_module(probe)
exec((ROOT / "scripts/diagnostics/anex_full_catalog_probe.py").read_text(), probe.__dict__)


class FullCatalog(unittest.TestCase):
    def setUp(self):
        probe.SENSITIVE_VALUES = ("fixture-secret",)
        self.states = [{"inc": "1", "name": "Египет"}]
        self.towns = [{"inc": "10", "name": "Шарм Эль Шейх"}]
        self.links = [{"town": "10", "state": "1"}]
        self.locals = [
            {"id": 195, "name": "Dreams Beach Resort & Aqua Park Sharm El Sheikh",
             "normalized_name": "dreams beach resort aqua park sharm el sheikh", "country_name": "Египет",
             "region_name": "Шарм-эль-Шейх", "subregion_name": "Хадаба"},
            {"id": 999, "name": "Dreams Beach", "normalized_name": "dreams beach",
             "country_name": "Турция", "region_name": "Анталья", "subregion_name": "Белек"},
            {"id": 245, "name": "Jaz Sharm Dreams", "normalized_name": "jaz sharm dreams",
             "country_name": "Египет", "region_name": "Шарм Эль Шейх", "subregion_name": "Наама Бей"},
        ]

    def test_only_unique_exact_name_and_geography_auto_verifies(self):
        hotels = [{"inc": "469", "name": "Jaz Sharm Dreams", "town": "10"},
                  {"inc": "465", "name": "Dreams Beach", "lname": "Dreams Beach Resort", "town": "10"},
                  {"inc": "777", "name": "Unknown fixture", "town": "10"}]
        rows = probe.match_catalog(hotels, self.towns, self.states, self.links, self.locals)
        self.assertEqual(rows[0]["status"], "verified_auto")
        self.assertEqual(rows[0]["catalog_hotel_id"], 245)
        self.assertEqual(rows[1]["status"], "review")
        self.assertIsNone(rows[1]["catalog_hotel_id"])
        self.assertEqual(rows[2]["status"], "unmatched")

    def test_deleted_supplier_hotel_is_never_mapped(self):
        rows = probe.match_catalog([{"inc": "469", "name": "Jaz Sharm Dreams", "town": "10", "status": "D"}],
                                   self.towns, self.states, self.links, self.locals)
        self.assertEqual(rows[0]["status"], "deleted")
        self.assertIsNone(rows[0]["catalog_hotel_id"])

    def test_artifacts_separate_verified_and_manual_queue(self):
        rows = probe.match_catalog([{"inc": "469", "name": "Jaz Sharm Dreams", "town": "10"},
                                    {"inc": "777", "name": "Unknown fixture", "town": "10"}],
                                   self.towns, self.states, self.links, self.locals)
        report = {"reference_stamp": "0x0000000000000001", "counts": {"anex_hotels": 2},
                  "pages": {"hotels": 1}, "matches": rows}
        with tempfile.TemporaryDirectory() as directory:
            probe.save_full_catalog_artifacts(report, directory)
            with Path(directory, "anex-hotel-verified.csv").open() as handle:
                verified = list(csv.DictReader(handle))
            with Path(directory, "anex-hotel-review.csv").open() as handle:
                review = list(csv.DictReader(handle))
            data = json.loads(Path(directory, "anex-hotel-catalog-match.json").read_text())
        self.assertEqual(verified[0]["external_hotel_id"], "469")
        self.assertEqual(review[0]["anex_id"], "777")
        self.assertEqual(len(data["matches"]), 2)

    def test_tokens_and_markup_do_not_enter_catalog(self):
        self.assertEqual(probe.catalog_text("Hotel fixture-secret"), "")
        self.assertEqual(probe.catalog_text("<b>Hotel</b>"), "")

    def test_broad_fuzzy_token_stays_in_manual_unmatched_queue(self):
        locals_ = [{"id": index + 1, "name": f"Grand Fixture {index}",
                    "normalized_name": f"grand fixture {index}", "country_name": "Египет",
                    "region_name": "Шарм Эль Шейх", "subregion_name": ""}
                   for index in range(1001)]
        rows = probe.match_catalog([{"inc": "880", "name": "Grand Fixture New", "town": "10"}],
                                   self.towns, self.states, self.links, locals_)
        self.assertEqual(rows[0]["status"], "unmatched")
        self.assertEqual(rows[0]["candidates"], [])


if __name__ == "__main__":
    unittest.main()
