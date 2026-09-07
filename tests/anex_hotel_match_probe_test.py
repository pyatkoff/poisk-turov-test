#!/usr/bin/env python3
"""Offline checks for the bounded, read-only hotel matching diagnostic."""

import importlib.util
import json
from pathlib import Path
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_hotel_probe_under_test", ROOT / "scripts/diagnostics/anex_access_probe.py")
probe = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(probe)
HELPER = ROOT / "scripts/diagnostics/anex_hotel_match_probe.py"
exec(compile(HELPER.read_text(encoding="utf-8"), str(HELPER), "exec"), probe.__dict__)


class HotelMatchProbeTest(unittest.TestCase):
    def setUp(self):
        probe.SENSITIVE_VALUES = ()
        self.tokens = {"ANEX_API_TOKEN": "  api-test-secret  ",
                       "ANEX_REFERENCE_TOKEN": " reference-test-secret "}
        self.api = {"id": 11, "name": "Blue Garden Hotel", "country": "Turkey",
                    "town_id": 7, "latitude": 36.85, "longitude": 30.7}
        self.xml = {"id": 11, "name": "Blue Garden Hotel", "town_id": 7}
        self.candidate = {"id": 9011, "name": "Blue Garden Hotel", "country_name": "Турция",
                          "latitude": 36.8501, "longitude": 30.7001}

    def run_sample(self, catalog_status="ok"):
        deleted = '<hotel inc="999" name="Deleted Hotel" town="7" status="D"/>'
        records = ''.join('<hotel inc="%d" name="Garden Hotel %d" town="7"/>' % (i, i)
                          for i in range(11, 22))
        xml_body = ('<Response><Data>' + deleted + records + '</Data></Response>').encode()

        def fake_request(params, token, post=False):
            self.assertFalse(post)
            if params.get("samo_action") == "reference":
                self.assertEqual(params["type"], "hotel")
                self.assertEqual(token, "reference-test-secret")
                return {"status": "ok", "http_status": 200}, xml_body
            self.assertEqual(params["action"], "Hotels_DETAILS")
            self.assertEqual(token, "api-test-secret")
            identifier = params["HOTELINC"]
            self.assertIn(identifier, range(11, 21))
            payload = {"Hotels_DETAILS": {"id": identifier, "name": "Garden Hotel %d" % identifier,
                       "state": "Turkey", "townKey": 7, "latitude": 36.85, "longitude": 30.7}}
            return {"status": "ok", "http_status": 200}, json.dumps(payload).encode()

        def fake_catalog(queries):
            self.assertEqual([query["key"] for query in queries], list(range(11, 21)))
            return {"status": catalog_status, "items": [] if catalog_status != "ok" else [
                {"key": query["key"], "candidates": [{"id": query["key"] + 9000,
                 "name": "Garden Hotel %d" % query["key"], "country_name": "Турция",
                 "latitude": 36.8501, "longitude": 30.7001}]} for query in queries]}

        with patch.object(probe, "reference_check", return_value=(
                {"check": "reference_currentstamp", "status": "ok"}, "0x0000000000001234")) as stamp, \
                patch.object(probe, "request", side_effect=fake_request) as requests, \
                patch.object(probe, "read_catalog", side_effect=fake_catalog) as catalog, \
                patch.object(probe.subprocess, "run", side_effect=AssertionError("Unexpected process")), \
                patch.object(probe.urllib.request, "build_opener", side_effect=AssertionError("Network forbidden")):
            raw = probe.remote_hotel_probe(self.tokens)
            stamp.assert_called_once_with("reference-test-secret", "currentstamp")
            self.assertEqual(requests.call_count, 11)
            catalog.assert_called_once()
        return probe.clean_hotel_report(raw)

    def test_ten_live_xml_records_details_and_catalog_are_bounded_and_read_only(self):
        report = self.run_sample()
        self.assertTrue(report["ok"])
        self.assertEqual(len(report["hotels"]), 10)
        self.assertEqual([row["xml"]["id"] for row in report["hotels"]], list(range(11, 21)))
        self.assertTrue(all(row["api_xml_relation"] == "same_record" for row in report["hotels"]))
        self.assertTrue(all(row["match_status"] == "confirmed" for row in report["hotels"]))
        self.assertTrue(all(row["xml"]["id"] != row["candidates"][0]["id"] for row in report["hotels"]))

    def test_supplier_identity_and_name_or_town_conflicts(self):
        self.assertEqual(probe.xml_relation(self.xml, self.api), "same_record")
        for changes, expected in (({"id": 12}, "id_conflict"),
                                  ({"name": "Completely Different Palace"}, "name_conflict"),
                                  ({"town_id": 8}, "town_conflict")):
            with self.subTest(expected=expected):
                self.assertEqual(probe.xml_relation(self.xml, dict(self.api, **changes)), expected)
        self.assertEqual(probe.xml_relation(self.xml, {}), "unverified")

    def test_empty_details_are_reported_as_missing_not_success(self):
        for value in (None, False, "", [], {}):
            with self.subTest(value=value), patch.object(probe, "request", return_value=(
                    {"status": "ok", "http_status": 200}, json.dumps({"Hotels_DETAILS": value}).encode())):
                checks = []
                with self.assertRaises(probe.StopProbe):
                    probe.api_data("test", "Hotels_DETAILS", {"HOTELINC": 1}, checks)
                self.assertEqual(checks[0]["status"], "no_details")

    def test_confirmed_requires_name_and_nearby_coordinates(self):
        ranked = probe.candidate_rank(self.api, self.xml, self.candidate)
        self.assertLess(ranked["distance_m"], 200)
        self.assertEqual(probe.classify_candidates([ranked]), "confirmed")
        different = probe.candidate_rank(self.api, self.xml,
                                        dict(self.candidate, name="Completely Different Palace"))
        self.assertEqual(probe.classify_candidates([different]), "unmatched")

    def test_duplicate_candidates_remain_ambiguous(self):
        first = probe.candidate_rank(self.api, self.xml, self.candidate)
        second = probe.candidate_rank(self.api, self.xml, dict(self.candidate, id=9012))
        self.assertEqual(probe.classify_candidates([first, second]), "ambiguous")

    def test_country_and_large_distance_conflicts_are_not_matches(self):
        for changes in ({"country_name": "Египет"}, {"latitude": 39.0, "longitude": 32.0}):
            with self.subTest(changes=changes):
                candidate = probe.candidate_rank(self.api, self.xml, dict(self.candidate, **changes))
                self.assertEqual(probe.classify_candidates([candidate]), "geo_conflict")

    def test_missing_coordinates_only_probable_and_missing_candidates_unmatched(self):
        candidate = probe.candidate_rank(self.api, self.xml,
                                        dict(self.candidate, latitude=None, longitude=None))
        self.assertIsNone(candidate["distance_m"])
        self.assertEqual(probe.classify_candidates([candidate]), "probable")
        self.assertEqual(probe.classify_candidates([]), "unmatched")

    def test_output_sanitizes_raw_and_padded_secrets_urls_and_error_fields(self):
        self.run_sample()
        report = {"mode": "hotels", "checks": [{"check": "catalog", "status": "catalog_unavailable",
                  "error": "private supplier diagnostic", "url": "https://private.example"}], "hotels": [
            {"xml": {"id": 11, "name": "api-test-secret", "alternate_name": "  api-test-secret  ",
                     "address": "https://private.example/?oauth_token=reference-test-secret"},
             "api": {"name": " reference-test-secret ", "address": "www.private.example"},
             "candidates": [{"id": 9011, "name": "Blue Garden Hotel", "error": "private supplier diagnostic",
                             "raw": "reference-test-secret"}]}]}
        encoded = json.dumps(probe.clean_hotel_report(report))
        for forbidden in ("api-test-secret", "reference-test-secret", "private.example",
                          "private supplier diagnostic", "oauth_token", '"raw"', '"error"'):
            self.assertNotIn(forbidden, encoded)
        self.assertIn("Blue Garden Hotel", encoded)

    def test_catalog_failure_cannot_report_success(self):
        report = self.run_sample(catalog_status="catalog_unavailable")
        self.assertFalse(report["ok"])
        self.assertEqual(report["checks"][-1]["status"], "catalog_unavailable")
        self.assertTrue(all(row["match_status"] == "unmatched" for row in report["hotels"]))


if __name__ == "__main__":
    unittest.main()
