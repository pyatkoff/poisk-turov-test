#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts" / "diagnostics" / "andromeda_http_flight_selection_e2e.py"
spec = importlib.util.spec_from_file_location("andromeda_http_v6", SOURCE)
assert spec and spec.loader
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)

OFFER = "offer_" + "a" * 64
LISTING = "listing_" + "b" * 64
OUT = "flight_" + "c" * 32
BACK = "flight_" + "d" * 32
CTX = {"provider": "andromeda", "search_ref": "e" * 64, "generation": 17171221, "page": 1, "offer_ref": OFFER}

search_data = {
    "provider": "andromeda",
    "received_offers": 50,
    "mapped_offers": 44,
    "hotels": [{
        "local_id": 123,
        "tours": [{
            "offer_ref": OFFER,
            "offer_context": CTX,
            "listing_price_ref": LISTING,
            "price": {"amount": "150000", "currency": "RUB", "kind": "offer", "fees": "unknown", "final": False},
        }],
    }],
}
initial_quote = {
    "provider": "andromeda",
    "state": "flight_selection_required",
    "final_price_verified": False,
    "booking_enabled": False,
    "flight_selection_required": True,
    "flights": [
        {"direction": "0", "flight_ref": OUT, "name": "OUT 100"},
        {"direction": "0", "flight_ref": "flight_" + "f" * 32, "name": "OUT 200"},
        {"direction": "1", "flight_ref": BACK, "name": "BACK 100"},
    ],
}
final_quote = {
    "provider": "andromeda",
    "state": "quote_verified",
    "final_price_verified": True,
    "booking_enabled": False,
    "flight_selection_required": False,
    "search_price": {"amount": "140000", "currency": "RUB"},
    "final_price": {"amount": "152500", "currency": "RUB"},
    "served_price_observation": {
        "provider": "andromeda",
        "state": "comparable",
        "basis": "search_api_response",
        "price_basis": "search_base",
        "served_price": {"amount": "150000", "currency": "RUB"},
        "final_price": {"amount": "152500", "currency": "RUB"},
        "final_price_verified": True,
        "signed_delta_amount": "2500",
        "absolute_delta_amount": "2500",
        "relative_delta_bps": 167,
    },
    "fuel_surcharges_reported": [{"amount": "80", "currency": "USD", "route_index": "0"}],
    "calc_money_facts_reported": [{"currency": "RUB", "gross_amount": "152500"}],
}

calls = []
def post(endpoint, payload):
    calls.append((endpoint, payload))
    if len(calls) == 1:
        return 200, {"ok": True, "data": search_data}
    if len(calls) == 2:
        assert payload["action"] == "quote"
        assert payload["listing_price_ref"] == LISTING
        assert payload["offer_context"] == CTX
        return 200, {"ok": True, "data": initial_quote}
    if len(calls) == 3:
        assert payload["action"] == "quote_select_flights"
        assert payload["flight_selection"] == {"provider": "andromeda", "outbound_ref": OUT, "return_ref": BACK}
        assert "uid" not in str(payload).lower()
        return 200, {"ok": True, "data": final_quote}
    raise AssertionError("unexpected extra HTTP call")

result = mod.run_flow(post)
assert result["status"] == "complete", result
assert result["used_flight_selection"] is True
assert result["flight_option_counts"] == {"0": 2, "1": 1}
assert result["http_calls"] == 3
assert result["quote_observation"]["served_price"] == {"amount": "150000", "currency": "RUB"}
assert result["quote_observation"]["final_price"] == {"amount": "152500", "currency": "RUB"}
assert result["fuel_surcharges_reported_count"] == 1
assert result["calc_money_facts_reported_count"] == 1
assert result["booking_calls"] == 0 and result["mapping_writes"] == 0
assert len(calls) == 3

# A direct/unambiguous quote may finish without the continuation call.
direct_calls = []
def direct_post(endpoint, payload):
    direct_calls.append((endpoint, payload))
    if len(direct_calls) == 1:
        return 200, {"ok": True, "data": search_data}
    if len(direct_calls) == 2:
        return 200, {"ok": True, "data": final_quote}
    raise AssertionError("direct quote must not make a third call")

direct = mod.run_flow(direct_post)
assert direct["status"] == "complete", direct
assert direct["used_flight_selection"] is False
assert direct["http_calls"] == 2

# Raw/malformed refs must fail closed before continuation.
bad = dict(initial_quote)
bad["flights"] = [{"direction": "0", "flight_ref": "supplier_uid_123"}, {"direction": "1", "flight_ref": BACK}]
bad_calls = []
def bad_post(endpoint, payload):
    bad_calls.append((endpoint, payload))
    if len(bad_calls) == 1:
        return 200, {"ok": True, "data": search_data}
    return 200, {"ok": True, "data": bad}

blocked = mod.run_flow(bad_post)
assert blocked["status"] == "unknown"
assert blocked["reason"] == "flight_ref_invalid"
assert len(bad_calls) == 2

# Supplier/API errors are retained as sanitized one-shot evidence, never retried.
error_calls = []
def error_post(endpoint, payload):
    error_calls.append((endpoint, payload))
    return 502, {"ok": False, "error": "supplier_unavailable"}

unknown = mod.run_flow(error_post)
assert unknown["status"] == "unknown"
assert unknown["reason"] == "search_http_502_supplier_unavailable"
assert len(error_calls) == 1

print("andromeda HTTP flight-selection E2E: OK")
