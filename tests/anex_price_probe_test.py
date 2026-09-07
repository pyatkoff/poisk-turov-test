#!/usr/bin/env python3
"""Offline price-probe checks; no ANEX, SSH, or booking requests."""

import copy
import datetime as dt
import importlib.util
import json
from pathlib import Path
import unittest
from unittest import mock


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_price_probe", ROOT / "scripts/diagnostics/anex_access_probe.py")
probe = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(probe)

API_TOKEN = "test-api-token-DO-NOT-LOG"
REFERENCE_TOKEN = "test-reference-token-DO-NOT-LOG"
TOKENS = {"ANEX_API_TOKEN": API_TOKEN, "ANEX_REFERENCE_TOKEN": REFERENCE_TOKEN}
METHODS = ["SearchTour_TOWNFROMS", "SearchTour_STATES", "SearchTour_CHECKIN",
           "SearchTour_CURRENCIES", "SearchTour_NIGHTS", "SearchTour_PRICES"]


def reply(action, data):
    return {"status": "ok", "http_status": 200}, json.dumps({action: data}).encode()


class PriceProbeTest(unittest.TestCase):
    def setUp(self):
        self.today = dt.datetime.now(dt.timezone.utc).date()
        self.start = self.today + dt.timedelta(days=10)
        self.row = {
            "id": "private-native-offer-id", "hotel": "Example Resort",
            "star": "5*", "meal": "AI", "room": "Standard",
            "checkIn": self.start.strftime("%Y%m%d"),
            "checkOut": (self.start + dt.timedelta(days=7)).strftime("%Y%m%d"),
            "nights": 7, "adult": 2, "child": 0, "packetType": 0,
            "price": "1250.50", "currency": "USD", "convertedPrice": "112 545.00 RUB",
            "bron": 1, "grouped": 0, "hotelAvailability": "Y",
            "freights": {"econom": {"in": "Y", "out": "R"}},
            "message": API_TOKEN, "url": "https://private.invalid/?oauth_token=" + API_TOKEN,
        }
        self.payloads = [
            [{"id": 1, "name": "Москва"}],
            {"items": [{"id": 2, "name": "Турция"}]},
            {"start": self.start.strftime("%Y%m%d"), "valid": "22222"},
            {"items": [{"id": 3, "name": "Рубли", "alias": "RUB"}]},
            {"nights": [7], "places": [7]},
            {"prices": [self.row]},
        ]

    def run_probe(self, payloads=None, tokens=None):
        replies = [reply(action, data) for action, data in zip(METHODS, payloads or self.payloads)]
        with mock.patch.object(probe, "request", side_effect=replies) as request:
            result = probe.clean_report(probe.remote_price_probe(tokens or TOKENS))
        return result, request

    def test_documented_wrappers_six_gets_and_real_currency_sample(self):
        result, request = self.run_probe()
        self.assertTrue(result["ok"])
        self.assertEqual(request.call_count, 6)
        self.assertEqual([call.args[0]["action"] for call in request.call_args_list], METHODS)
        for call in request.call_args_list:
            self.assertEqual(call.args[1], API_TOKEN)
            self.assertFalse(call.kwargs.get("post", False))
            self.assertEqual(call.args[0]["samo_action"], "api")
        params = request.call_args_list[-1].args[0]
        self.assertEqual({key: params[key] for key in ("ADULT", "CHILD", "CURRENCY", "FREIGHT")},
                         {"ADULT": 2, "CHILD": 0, "CURRENCY": 3, "FREIGHT": 1})
        self.assertEqual(params["CHECKIN_BEG"], self.start.strftime("%Y%m%d"))
        self.assertEqual(params["CHECKIN_END"], params["CHECKIN_BEG"])
        self.assertEqual((params["NIGHTS_FROM"], params["NIGHTS_TILL"]), (7, 7))
        self.assertEqual((result["valid_offers"], result["bookable_offers"]), (1, 1))
        sample = result["samples"][0]
        self.assertEqual((sample["price"], sample["currency"]), (1250.50, "USD"))
        self.assertEqual((sample["converted_price"], sample["converted_currency"]), (112545.0, "RUB"))
        self.assertEqual((sample["flight_outbound"], sample["flight_return"]), ("Y", "R"))
        serialized = json.dumps(result, ensure_ascii=False)
        for private in (API_TOKEN, REFERENCE_TOKEN, "private-native-offer-id", "private.invalid", "oauth_token"):
            self.assertNotIn(private, serialized)

    def test_available_places_nights_take_precedence_over_full_nights(self):
        payloads = copy.deepcopy(self.payloads)
        payloads[4] = {"nights": [7, 10], "places": [10]}
        payloads[-1]["prices"][0].update(nights=10,
            checkOut=(self.start + dt.timedelta(days=10)).strftime("%Y%m%d"))
        result, request = self.run_probe(payloads)
        self.assertTrue(result["ok"])
        self.assertEqual(request.call_args_list[-1].args[0]["NIGHTS_FROM"], 10)

    def test_unavailable_dates_are_not_selected(self):
        data = {"start": self.start.strftime("%Y%m%d"), "valid": "0720"}
        dates = probe.available_dates(data, self.today)
        self.assertEqual(dates, [(self.start + dt.timedelta(days=2), "2")])
        self.assertEqual(probe.available_dates(dict(data, valid="0707"), self.today), [])

    def test_supplier_error_and_html_stop_without_raw_error(self):
        for body, status in [(json.dumps({"SearchTour_TOWNFROMS": {
                "error": 403, "message": API_TOKEN}}).encode(), "supplier_error"),
                (("<html>" + API_TOKEN + "</html>").encode(), "invalid_response")]:
            with self.subTest(status=status), mock.patch.object(probe, "request", return_value=(
                    {"status": "ok", "http_status": 200}, body)) as request:
                result = probe.clean_report(probe.remote_price_probe(TOKENS))
            self.assertFalse(result["ok"])
            self.assertEqual(request.call_count, 1)
            self.assertEqual(result["checks"][0]["status"], status)
            self.assertNotIn(API_TOKEN, json.dumps(result))

    def test_no_offers_or_missing_price_key_never_pass(self):
        for data in ({"prices": []}, {}, {"prices": "not a list"}):
            with self.subTest(data=data):
                payloads = copy.deepcopy(self.payloads)
                payloads[-1] = data
                result, _ = self.run_probe(payloads)
                self.assertFalse(result["ok"])
                self.assertFalse(result["samples"])

    def test_bad_offer_constraints_cannot_confirm_prices(self):
        cases = [{"price": 0}, {"price": -1}, {"price": "NaN"}, {"adult": 1},
                 {"child": 1}, {"packetType": 1}, {"nights": 14},
                 {"checkIn": (self.start + dt.timedelta(days=1)).strftime("%Y%m%d")},
                 {"checkOut": self.start.strftime("%Y%m%d")}, {"currency": "invalid"}]
        for fields in cases:
            with self.subTest(fields=fields):
                payloads = copy.deepcopy(self.payloads)
                payloads[-1]["prices"][0].update(fields)
                result, _ = self.run_probe(payloads)
                self.assertFalse(result["ok"])
                self.assertEqual(result["valid_offers"], 0)

    def test_secret_hotel_label_is_suppressed(self):
        for tokens in (TOKENS, dict(TOKENS, ANEX_API_TOKEN="  " + API_TOKEN + "  ")):
            with self.subTest(padded=tokens != TOKENS):
                payloads = copy.deepcopy(self.payloads)
                payloads[-1]["prices"][0]["hotel"] = "Hotel " + API_TOKEN
                result, _ = self.run_probe(payloads, tokens)
                self.assertFalse(result["ok"])
                self.assertNotIn(API_TOKEN, json.dumps(result))

    def test_clean_report_drops_extra_fields_and_requires_offer(self):
        report = {"mode": "prices", "checks": [{"check": "api_prices", "status": "ok",
                  "message": API_TOKEN}], "search": {"departure": "Москва", "url": API_TOKEN},
                  "token": API_TOKEN, "samples": [], "valid_offers": 0}
        result = probe.clean_report(report)
        self.assertFalse(result["ok"])
        self.assertNotIn(API_TOKEN, json.dumps(result))
        report["checks"][0]["status"] = API_TOKEN
        with self.assertRaises(ValueError):
            probe.clean_report(report)


if __name__ == "__main__":
    unittest.main()
