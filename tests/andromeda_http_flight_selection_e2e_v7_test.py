#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts" / "diagnostics" / "andromeda_http_flight_selection_e2e_v7.py"
spec = importlib.util.spec_from_file_location("andromeda_http_v7", SOURCE)
assert spec and spec.loader
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)

request = mod.scenario_request()
assert mod.OPERATION == "andromeda-http-flight-selection-e2e-1717-v7-turkey-2026-12-22-2a-9n"
assert mod.RUNTIME_SOURCE == "3275b3e8b1f9bfe50cc0ce521beaa43602a408bf"
assert request["generation"] == 17171222
assert request["params"]["dateFrom"] == request["params"]["dateTo"] == "2026-12-22"
assert request["params"]["nightsFrom"] == request["params"]["nightsTo"] == 9
assert request["params"]["adults"] == 2 and request["params"]["childs"] == []
assert request["andromeda_operator_ids"] == ["5"]

# The already-published browser-safe category is retained as explicit evidence.
calls = []
def supplier_auth(endpoint, payload):
    calls.append((endpoint, payload))
    return 502, {"ok": False, "error": "supplier_unavailable", "failure_category": "supplier_auth"}

unknown = mod.run_flow(supplier_auth)
assert unknown["status"] == "unknown", unknown
assert unknown["operation"] == mod.OPERATION
assert unknown["runtime_source"] == mod.RUNTIME_SOURCE
assert unknown["failure_category"] == "supplier_auth"
assert unknown["reason"] == "search_http_502_supplier_unavailable"
assert unknown["scenario"] == {
    "country": "Turkey", "date": "2026-12-22", "nights": 9,
    "adults": 2, "children": [], "operator": "ANEX",
}
assert len(calls) == 1
assert unknown["booking_calls"] == 0 and unknown["mapping_writes"] == 0

# Unknown category text is never copied into the result.
def unsafe_category(endpoint, payload):
    return 502, {
        "ok": False,
        "error": "supplier_unavailable",
        "failure_category": "sid=SECRET https://supplier.invalid raw-body",
    }

unsafe = mod.run_flow(unsafe_category)
assert unsafe["failure_category"] == "invalid_public_category"
assert "SECRET" not in str(unsafe)
assert "supplier.invalid" not in str(unsafe)
assert "raw-body" not in str(unsafe)

# Missing category remains missing rather than being guessed.
def old_contract(endpoint, payload):
    return 502, {"ok": False, "error": "supplier_unavailable"}

missing = mod.run_flow(old_contract)
assert "failure_category" not in missing

# Happy flight-selection path is still the checked v6 flow, only with the v7 tuple/pin.
OFFER = "offer_" + "a" * 64
LISTING = "listing_" + "b" * 64
OUT = "flight_" + "c" * 32
BACK = "flight_" + "d" * 32
CTX = {"provider": "andromeda", "search_ref": "e" * 64, "generation": 17171222, "page": 1, "offer_ref": OFFER}
search = {
    "received_offers": 50,
    "mapped_offers": 44,
    "hotels": [{"local_id": 123, "tours": [{
        "offer_context": CTX,
        "listing_price_ref": LISTING,
        "price": {"amount": "150000", "currency": "RUB"},
    }]}],
}
initial = {
    "provider": "andromeda", "state": "flight_selection_required",
    "final_price_verified": False, "booking_enabled": False,
    "flight_selection_required": True,
    "flights": [
        {"direction": "0", "flight_ref": OUT},
        {"direction": "1", "flight_ref": BACK},
    ],
}
final = {
    "provider": "andromeda", "state": "quote_verified",
    "final_price_verified": True, "booking_enabled": False,
    "flight_selection_required": False,
    "search_price": {"amount": "150000", "currency": "RUB"},
    "final_price": {"amount": "152500", "currency": "RUB"},
    "served_price_observation": {
        "provider": "andromeda", "state": "comparable", "basis": "search_api_response",
        "price_basis": "search_base", "served_price": {"amount": "150000", "currency": "RUB"},
        "final_price": {"amount": "152500", "currency": "RUB"},
        "final_price_verified": True, "signed_delta_amount": "2500",
        "absolute_delta_amount": "2500", "relative_delta_bps": 167,
    },
    "fuel_surcharges_reported": [], "calc_money_facts_reported": [],
}
flow_calls = []
def happy(endpoint, payload):
    flow_calls.append((endpoint, payload))
    if len(flow_calls) == 1:
        return 200, {"ok": True, "data": search}
    if len(flow_calls) == 2:
        assert payload["action"] == "quote"
        return 200, {"ok": True, "data": initial}
    if len(flow_calls) == 3:
        assert payload["action"] == "quote_select_flights"
        assert payload["flight_selection"] == {
            "provider": "andromeda", "outbound_ref": OUT, "return_ref": BACK,
        }
        assert "uid" not in str(payload).lower()
        return 200, {"ok": True, "data": final}
    raise AssertionError("unexpected HTTP call")

complete = mod.run_flow(happy)
assert complete["status"] == "complete", complete
assert complete["used_flight_selection"] is True
assert complete["http_calls"] == 3
assert complete["booking_calls"] == 0 and complete["mapping_writes"] == 0
assert "failure_category" not in complete

print("andromeda HTTP flight-selection v7 classified canary: OK")
