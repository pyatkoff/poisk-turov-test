#!/usr/bin/env python3
"""One-shot real HTTP acceptance for Andromeda search -> quote -> flight selection -> calc.

The browser-visible contract is exercised through the installed isolated preview APIs.
No supplier UID is accepted or emitted here; flight continuation uses opaque flight_ref only.
"""
from __future__ import annotations

import copy
import http.cookiejar
import json
import re
import sys
import urllib.error
import urllib.request
from typing import Any, Callable

OPERATION = "andromeda-http-flight-selection-e2e-1717-v6-turkey-2026-12-21-2a1c7-8n"
RUNTIME_SOURCE = "812f838191d138f391059aaa6d0d3f77ce8c7d27"
BASE_URL = "https://anytoour.ru/_preview/search3-anex-candidate/"
FLIGHT_REF = re.compile(r"^flight_[a-f0-9]{32}$")
LISTING_REF = re.compile(r"^listing_[a-f0-9]{64}$")
OFFER_REF = re.compile(r"^offer_[a-f0-9]{64}$")
MONEY = re.compile(r"^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$")


def scenario_request() -> dict[str, Any]:
    return {
        "generation": 17171221,
        "page": 1,
        "params": {
            "countryId": "4",
            "departureId": "1",
            "dateFrom": "2026-12-21",
            "dateTo": "2026-12-21",
            "nightsFrom": 8,
            "nightsTo": 8,
            "adults": 2,
            "childs": [7],
            "meal": "",
            "hotelCategory": "",
            "hotelIds": [],
            "regionIds": [],
            "subregionIds": [],
            "operatorIds": [],
            "currency": "RUB",
        },
        "andromeda_operator_ids": ["5"],
    }


def _money(value: Any) -> dict[str, str]:
    if not isinstance(value, dict):
        raise RuntimeError("money_missing")
    amount, currency = value.get("amount"), value.get("currency")
    if not isinstance(amount, str) or not MONEY.fullmatch(amount) or not re.search(r"[1-9]", amount):
        raise RuntimeError("money_invalid")
    if not isinstance(currency, str) or not re.fullmatch(r"[A-Z0-9_]{2,8}", currency):
        raise RuntimeError("money_invalid")
    return {"amount": amount, "currency": currency}


def _api(reply: tuple[int, dict[str, Any]], phase: str) -> dict[str, Any]:
    status, body = reply
    if not isinstance(status, int) or not isinstance(body, dict):
        raise RuntimeError(f"{phase}_response_invalid")
    if status != 200 or body.get("ok") is not True or not isinstance(body.get("data"), dict):
        error = body.get("error") if isinstance(body.get("error"), str) else "invalid"
        error = re.sub(r"[^A-Za-z0-9_.:-]", "_", error)[:64]
        raise RuntimeError(f"{phase}_http_{status}_{error}")
    return body["data"]


def _pick(data: dict[str, Any]) -> tuple[int, dict[str, Any]]:
    hotels = data.get("hotels")
    if not isinstance(hotels, list):
        raise RuntimeError("search_shape_invalid")
    for hotel in hotels:
        if not isinstance(hotel, dict) or not isinstance(hotel.get("local_id"), int) or hotel["local_id"] < 1:
            continue
        tours = hotel.get("tours")
        if not isinstance(tours, list):
            continue
        for tour in tours:
            if not isinstance(tour, dict):
                continue
            context = tour.get("offer_context")
            listing = tour.get("listing_price_ref")
            if (
                isinstance(context, dict)
                and context.get("provider") == "andromeda"
                and isinstance(context.get("offer_ref"), str)
                and OFFER_REF.fullmatch(context["offer_ref"])
                and isinstance(listing, str)
                and LISTING_REF.fullmatch(listing)
            ):
                _money(tour.get("price"))
                return hotel["local_id"], tour
    raise RuntimeError("no_mapped_offer")


def _first_refs(quote: dict[str, Any]) -> tuple[str, str, dict[str, int]]:
    flights = quote.get("flights")
    if not isinstance(flights, list) or not flights:
        raise RuntimeError("flight_options_missing")
    chosen: dict[str, str] = {}
    counts = {"0": 0, "1": 0}
    for row in flights:
        if not isinstance(row, dict):
            continue
        direction, ref = row.get("direction"), row.get("flight_ref")
        if direction not in counts:
            continue
        counts[direction] += 1
        if direction not in chosen:
            if not isinstance(ref, str) or not FLIGHT_REF.fullmatch(ref):
                raise RuntimeError("flight_ref_invalid")
            chosen[direction] = ref
        if any(key in row for key in ("uid", "claim", "supplier_offer_id")):
            raise RuntimeError("private_flight_data_leaked")
    if set(chosen) != {"0", "1"}:
        raise RuntimeError("flight_directions_missing")
    return chosen["0"], chosen["1"], counts


def _verify_final(quote: dict[str, Any], listed_price: dict[str, Any]) -> dict[str, Any]:
    if (
        quote.get("provider") != "andromeda"
        or quote.get("state") != "quote_verified"
        or quote.get("final_price_verified") is not True
        or quote.get("booking_enabled") is not False
        or quote.get("flight_selection_required") is not False
    ):
        raise RuntimeError("quote_not_verified")
    final_price = _money(quote.get("final_price"))
    search_price = _money(quote.get("search_price"))
    observation = quote.get("served_price_observation")
    if (
        not isinstance(observation, dict)
        or observation.get("provider") != "andromeda"
        or observation.get("state") != "comparable"
        or observation.get("basis") != "search_api_response"
        or observation.get("final_price_verified") is not True
        or _money(observation.get("final_price")) != final_price
    ):
        raise RuntimeError("served_price_observation_invalid")
    served_price = _money(observation.get("served_price"))
    if served_price != _money(listed_price):
        raise RuntimeError("served_price_changed")
    return {
        "search_price": search_price,
        "served_price": served_price,
        "final_price": final_price,
        "price_basis": observation.get("price_basis"),
        "signed_delta_amount": observation.get("signed_delta_amount"),
        "absolute_delta_amount": observation.get("absolute_delta_amount"),
        "relative_delta_bps": observation.get("relative_delta_bps"),
        "final_price_verified": True,
    }


Post = Callable[[str, dict[str, Any]], tuple[int, dict[str, Any]]]


def run_flow(post: Post) -> dict[str, Any]:
    request = scenario_request()
    result: dict[str, Any] = {
        "status": "unknown",
        "operation": OPERATION,
        "runtime_source": RUNTIME_SOURCE,
        "scenario": {
            "country": "Turkey",
            "date": "2026-12-21",
            "nights": 8,
            "adults": 2,
            "children": [7],
            "operator": "ANEX",
        },
        "http_calls": 0,
        "booking_calls": 0,
        "mapping_writes": 0,
    }
    try:
        search = _api(post("api-andromeda-search3-preview.php", request), "search")
        result["http_calls"] += 1
        local_id, tour = _pick(search)
        result["local_hotel_id"] = local_id
        result["received_offers"] = search.get("received_offers")
        result["mapped_offers"] = search.get("mapped_offers")
        result["listed_price"] = _money(tour["price"])
        result["offer_ref_sha256"] = __import__("hashlib").sha256(tour["offer_context"]["offer_ref"].encode()).hexdigest()
        result["listing_price_ref_sha256"] = __import__("hashlib").sha256(tour["listing_price_ref"].encode()).hexdigest()

        quote_request = copy.deepcopy(request)
        quote_request.update({
            "action": "quote",
            "offer_context": tour["offer_context"],
            "listing_price_ref": tour["listing_price_ref"],
        })
        quote = _api(post("api-andromeda-quote-preview.php", quote_request), "quote")
        result["http_calls"] += 1
        result["initial_quote_state"] = quote.get("state")
        result["used_flight_selection"] = False

        if quote.get("state") == "flight_selection_required":
            outbound, inbound, counts = _first_refs(quote)
            result["flight_option_counts"] = counts
            continuation = copy.deepcopy(quote_request)
            continuation["action"] = "quote_select_flights"
            continuation["flight_selection"] = {
                "provider": "andromeda",
                "outbound_ref": outbound,
                "return_ref": inbound,
            }
            quote = _api(post("api-andromeda-quote-preview.php", continuation), "quote_continue")
            result["http_calls"] += 1
            result["used_flight_selection"] = True

        evidence = _verify_final(quote, tour["price"])
        result.update({
            "status": "complete",
            "outcome": "served_quote_compared",
            "quote_observation": evidence,
            "final_quote_state": quote.get("state"),
            "fuel_surcharges_reported_count": len(quote.get("fuel_surcharges_reported") or []),
            "calc_money_facts_reported_count": len(quote.get("calc_money_facts_reported") or []),
        })
    except Exception as exc:  # preserve one-shot evidence even on unknown outcome
        reason = re.sub(r"[^A-Za-z0-9_.:-]", "_", str(exc))[:96] or "operation_unconfirmed"
        result["reason"] = reason
    return result


class HttpSession:
    def __init__(self, base_url: str = BASE_URL) -> None:
        self.base_url = base_url
        jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    def post(self, endpoint: str, payload: dict[str, Any]) -> tuple[int, dict[str, Any]]:
        body = json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
        request = urllib.request.Request(
            self.base_url + endpoint,
            data=body,
            method="POST",
            headers={
                "Content-Type": "application/json",
                "X-Requested-With": "AnyTourSearch3",
                "Origin": "https://anytoour.ru",
                "User-Agent": "AnyTour-Work-INT-v6/1.0",
            },
        )
        try:
            with self.opener.open(request, timeout=45) as response:
                raw = response.read(2_000_001)
                if len(raw) > 2_000_000:
                    raise RuntimeError("http_body_too_large")
                parsed = json.loads(raw.decode("utf-8"))
                return int(response.status), parsed if isinstance(parsed, dict) else {}
        except urllib.error.HTTPError as error:
            raw = error.read(65_537)
            try:
                parsed = json.loads(raw.decode("utf-8"))
            except Exception:
                parsed = {"ok": False, "error": "non_json_error"}
            return int(error.code), parsed if isinstance(parsed, dict) else {}


def main() -> int:
    session = HttpSession()
    result = run_flow(session.post)
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
