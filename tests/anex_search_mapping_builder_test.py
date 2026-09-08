#!/usr/bin/env python3
import copy
import csv
import importlib.util
import io
from pathlib import Path
import unittest


ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("builder", ROOT / "scripts/diagnostics/anex_search_mapping_builder.py")
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class SearchMappingBuilder(unittest.TestCase):
    def fixture(self):
        catalog = {"schema_version": 1, "provider": "anex_xml", "counts": {
            "anex_hotels": 4, "verified_auto": 1, "verified_unique_anytour": 1,
            "review": 2, "unmatched": 1}, "matches": [
            {"external_id": 3, "status": "verified_auto", "name": "Nika",
             "catalog_hotel_id": 99, "candidates": [{"id": 98}, {"id": 99}]},
            {"external_id": 2, "status": "review", "candidates": [{"id": 900}]},
            {"external_id": 4, "status": "review", "candidates": [{"id": 500}]},
            {"external_id": 1, "status": "unmatched", "candidates": []}]}
        geo = {"schema_version": 2, "processed_total": 2, "counts": {
            "strong_candidate": 1, "review": 1, "unmatched": 0}, "rows": [
            {"external_id": 2, "original_status": "review", "status": "strong_candidate",
             "reason": "name_country_coordinates", "candidates": [{"id": 200}, {"id": 900}],
             "xml": {"id": 2}, "api": {"id": 2}},
            {"external_id": 4, "original_status": "review", "status": "review",
             "reason": "needs_review", "candidates": [{"id": 500}]}]}
        return catalog, geo

    def build(self, catalog, geo):
        return builder.build_payload(catalog, geo, {"catalog_sha256": "a" * 64, "geo_sha256": "b" * 64})

    def test_saved_selection_and_short_exact_need_no_availability(self):
        catalog, geo = self.fixture()
        payload = self.build(catalog, geo)
        self.assertEqual(payload["approval_policy"], "owner_exact_and_strong_20260908")
        self.assertEqual(payload["scope"], "preview")
        self.assertEqual(payload["counts"], {"exact": 1, "strong": 1, "total": 2, "unique_catalog_hotels": 2})
        self.assertEqual([(r["anex_hotel_id"], r["catalog_hotel_id"], r["match_class"])
                          for r in payload["rows"]], [(2, 200, "strong_candidate"), (3, 99, "exact")])
        self.assertEqual(payload["rows"][0]["source_row_digest"], builder.source_digest(geo["rows"][0]))
        self.assertEqual(payload["rows"][1]["source_row_digest"], builder.source_digest(catalog["matches"][0]))
        self.assertEqual(len(list(csv.DictReader(io.StringIO(builder.csv_bytes(payload).decode())))), 2)
        catalog["matches"].reverse()
        geo["rows"].reverse()
        self.assertEqual(self.build(catalog, geo), payload)

    def test_newer_geo_review_suppresses_older_exact(self):
        catalog, geo = self.fixture()
        geo["rows"][1].update(external_id=3, original_status="verified_auto")
        payload = self.build(catalog, geo)
        self.assertEqual(payload["counts"]["exact"], 0)
        self.assertEqual([row["anex_hotel_id"] for row in payload["rows"]], [2])

    def test_conflicting_geo_exact_target_fails_closed(self):
        catalog, geo = self.fixture()
        geo["rows"][0].update(external_id=3, original_status="verified_auto", xml={"id": 3}, api={"id": 3})
        with self.assertRaisesRegex(ValueError, "older exact target"):
            self.build(catalog, geo)

    def test_duplicate_anex_in_either_source_is_rejected(self):
        for source, field in ((0, "matches"), (1, "rows")):
            reports = self.fixture()
            reports[source][field].append(copy.deepcopy(reports[source][field][0]))
            with self.assertRaisesRegex(ValueError, "duplicate ANEX"):
                self.build(*reports)

    def test_invalid_or_missing_selected_target_is_rejected(self):
        for invalid in (None, True, 0, -1, 2.5, "200"):
            catalog, geo = self.fixture()
            geo["rows"][0]["candidates"][0]["id"] = invalid
            with self.subTest(invalid=invalid), self.assertRaisesRegex(ValueError, "positive hotel id"):
                self.build(catalog, geo)
        catalog, geo = self.fixture()
        catalog["matches"][0]["catalog_hotel_id"] = 700
        with self.assertRaisesRegex(ValueError, "source candidates"):
            self.build(catalog, geo)

    def test_source_membership_identity_and_count_integrity(self):
        mutations = [
            (lambda c, g: g["rows"][0].update(external_id=77), "absent from source catalog"),
            (lambda c, g: g["rows"][0].update(original_status="unmatched"), "original status"),
            (lambda c, g: g["rows"][0]["api"].update(id=99), "identity conflict"),
            (lambda c, g: g["counts"].update(strong_candidate=2), "status count mismatch"),
            (lambda c, g: g.update(processed_total=3), "total count mismatch"),
            (lambda c, g: c["counts"].update(verified_unique_anytour=2), "unique catalog count mismatch")]
        for mutate, error in mutations:
            catalog, geo = self.fixture()
            mutate(catalog, geo)
            with self.subTest(error=error), self.assertRaisesRegex(ValueError, error):
                self.build(catalog, geo)

    def test_errors_are_excluded_and_shared_catalog_targets_are_allowed(self):
        catalog, geo = self.fixture()
        geo["rows"][1]["status"] = "error"
        geo["counts"].update(review=0, error=1)
        geo["rows"][0]["candidates"][0]["id"] = 99
        payload = self.build(catalog, geo)
        self.assertEqual(payload["counts"], {"exact": 1, "strong": 1, "total": 2, "unique_catalog_hotels": 1})


if __name__ == "__main__":
    unittest.main()
