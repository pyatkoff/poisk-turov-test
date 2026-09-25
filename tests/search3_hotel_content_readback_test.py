"""Fictional fixtures only. These tests never contact AnyTour or a supplier."""
import copy
import importlib.util
from pathlib import Path
import unittest
from unittest.mock import patch

PATH = Path(__file__).resolve().parents[1] / "scripts/diagnostics/search3_hotel_content_readback.py"
SPEC = importlib.util.spec_from_file_location("readback", PATH)
reader = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(reader)


def fixtures():
    saved = {"ok": True, "source": "anytour-local-hotel", "requestedIds": [101, 102], "missingIds": [],
             "items": [{"id": n, "description": "Описание из сохранённой карточки", "primaryImage": "https://fixture.invalid/a.jpg",
                        "images": ["https://fixture.invalid/a.jpg", "https://fixture.invalid/b.jpg"]} for n in [101, 102]]}
    canonical = {"ok": True, "source": "anytour-canonical-catalog", "catalog": "anytour", "requestedLegacyIds": [101, 102],
                 "missingLegacyIds": [], "links": [{"legacyHotelId": 101, "anytourHotelId": 901}, {"legacyHotelId": 102, "anytourHotelId": 902}],
                 "items": [{"id": n, "catalog": "anytour", "name": "Собственный отель", "revision": 3,
                            "description": None, "primaryImage": None, "images": []} for n in [901, 902]]}
    return saved, canonical


class ContentReadbackTest(unittest.TestCase):
    def result(self, saved=None, canonical=None, errors=None):
        a, b = fixtures()
        return reader.analyze([101, 102], a if saved is None else saved, b if canonical is None else canonical, errors)

    def test_saved_content_not_applied(self):
        result = self.result()
        self.assertEqual(result["status"], "readback_complete")
        self.assertEqual(result["summary"]["images"], {"saved_present_canonical_missing": 2})
        self.assertEqual(result["rows"][0]["anytourHotelId"], 901)
        self.assertEqual(result["rows"][0]["saved"]["imageReferenceCount"], 2)

    def test_existing_own_content_is_preserved_not_compared_as_equality(self):
        saved, own = fixtures()
        own["items"][0].update(description="Собственный проверенный текст", primaryImage="https://fixture.invalid/own.jpg")
        before = copy.deepcopy((saved, own))
        row = self.result(saved, own)["rows"][0]
        self.assertEqual(set(row["reasons"].values()), {"canonical_present_check_display"})
        self.assertEqual((saved, own), before)

    def test_saved_absence_is_not_claimed_as_tv_absence(self):
        saved, own = fixtures()
        saved["items"][0].update(description=None, primaryImage=None, images=[])
        self.assertEqual(self.result(saved, own)["rows"][0]["reasons"]["description"], "saved_content_missing_requires_source_review")

    def test_partial_content_is_classified_per_field(self):
        saved, own = fixtures()
        own["items"][0]["images"] = ["https://fixture.invalid/photo.jpg"]
        row = self.result(saved, own)["rows"][0]
        self.assertEqual(row["reasons"]["images"], "canonical_present_check_display")
        self.assertEqual(row["reasons"]["description"], "saved_present_canonical_missing")

    def test_http_or_transport_failure_is_unknown_not_empty(self):
        for code in ["http_503", "transport_unavailable", "http_403", "invalid_json"]:
            with self.subTest(code=code):
                result = self.result(errors={"canonical": code})
                self.assertEqual(result["status"], "readback_partial")
                self.assertEqual(result["rows"][0]["reasons"]["images"], "canonical_read_unknown")

    def test_source_failure_preserves_known_canonical_content(self):
        saved, own = fixtures()
        own["items"][0]["description"] = "Есть описание"
        result = self.result(saved, own, {"saved": "http_503"})
        self.assertEqual(result["rows"][0]["reasons"]["description"], "canonical_present_check_display")
        self.assertEqual(result["rows"][0]["reasons"]["images"], "saved_read_unknown")

    def test_missing_canonical_profile_is_explicit(self):
        saved, own = fixtures()
        own["items"].pop(); own["links"].pop(); own["missingLegacyIds"] = [102]
        row = self.result(saved, own)["rows"][1]
        self.assertIsNone(row["anytourHotelId"])
        self.assertEqual(row["reasons"]["images"], "canonical_profile_unavailable")

    def test_many_legacy_ids_may_link_to_one_own_profile(self):
        saved, own = fixtures()
        own["items"].pop(); own["links"][1]["anytourHotelId"] = 901
        result = self.result(saved, own)
        self.assertEqual(result["status"], "readback_complete")
        self.assertEqual([r["anytourHotelId"] for r in result["rows"]], [901, 901])

    def test_invalid_identity_or_incomplete_batch_fails_closed(self):
        mutations = [lambda p: p.update(source="tourvisor"), lambda p: p["links"][0].update(legacyHotelId=999),
                     lambda p: p["links"].pop(), lambda p: p.update(missingLegacyIds=[101]),
                     lambda p: p["items"].append(copy.deepcopy(p["items"][0])),
                     lambda p: p["items"][0].update(revision=True), lambda p: p.update(requestedLegacyIds=[102, 101])]
        for change in mutations:
            saved, own = fixtures(); change(own)
            with self.subTest(change=change):
                result = self.result(saved, own)
                self.assertIn("canonical", result["errors"])
                self.assertEqual(result["rows"][0]["reasons"]["description"], "canonical_read_unknown")

    def test_wrong_source_batch_does_not_invent_content_gap(self):
        saved, own = fixtures(); saved["items"].pop()
        result = self.result(saved, own)
        self.assertEqual(result["rows"][0]["reasons"]["images"], "saved_read_unknown")

    def test_invalid_content_shape_is_not_blank(self):
        for value in [0, False, {"url": "https://fixture.invalid/a.jpg"}]:
            saved, own = fixtures(); own["items"][0]["description"] = value
            with self.subTest(value=value):
                self.assertEqual(self.result(saved, own)["rows"][0]["reasons"]["description"], "canonical_read_unknown")

    def test_report_does_not_export_content_urls_or_claim_ui_acceptance(self):
        import json
        result = self.result(); output = json.dumps(result, ensure_ascii=False)
        self.assertNotIn("fixture.invalid", output)
        self.assertNotIn("Описание из", output)
        self.assertEqual(result["search3Dom"], "not_checked")
        self.assertEqual(result["imageReachability"], "not_checked")
        self.assertEqual(result["mutationsRequested"], 0)

    def test_input_is_bounded_and_ids_are_not_guessed(self):
        for value in [[], list(range(1, 22)), [1, 1], [True], ["001"], [0], ["abc"], [2**53]]:
            with self.subTest(value=value):
                with self.assertRaises(reader.InvalidSnapshot): reader.ids(value)
        self.assertEqual(reader.ids([102, "101"]), [101, 102])

    def test_network_error_is_bounded_and_does_not_leak_exception(self):
        from urllib.error import URLError
        with patch.object(reader, "build_opener") as opener:
            opener.return_value.open.side_effect = URLError("private internal transport detail")
            payload, error, digest = reader.read_batch([101], True)
            self.assertEqual(opener.return_value.open.call_count, 1)
            self.assertEqual(error, "transport_unavailable")
            self.assertIsNone(payload); self.assertIsNone(digest)

    def test_redirect_is_not_followed(self):
        from urllib.error import HTTPError
        from urllib.request import Request
        with self.assertRaises(HTTPError):
            reader.NoRedirect().redirect_request(Request(reader.ENDPOINT), None, 302, "redirect", {}, "https://other.invalid/")


if __name__ == "__main__":
    unittest.main()
