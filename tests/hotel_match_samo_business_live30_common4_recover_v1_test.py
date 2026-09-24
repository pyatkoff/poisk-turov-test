#!/usr/bin/env python3
import importlib.util
import json
import pathlib
import tempfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
SRC = ROOT / "scripts/diagnostics/hotel_match_samo_business_live30_common4_recover_v1.py"
spec = importlib.util.spec_from_file_location("recover", SRC)
m = importlib.util.module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(m)

ids = [str(100000000000000000 + i) for i in range(31)]
chunks = m.chunks(ids)
assert len(chunks) == 3
assert sorted(x for chunk in chunks for x in chunk) == sorted(ids)
assert all(1 <= len(chunk) <= 12 for chunk in chunks)
assert all(len(",".join(chunk).encode()) <= 300 for chunk in chunks)

exact = m.bridge(
    {"operatorKey": 315, "hotelKey": "10", "isOperatorHotelKey": 0,
     "original": {"hotelKey": "900", "operatorKey": 315}},
    315, {"10", "11"},
)
assert exact == {"state": "exact_catalog_to_native", "catalog_id": "10", "native_id": "900"}

catalog_only = m.bridge(
    {"operatorKey": 315, "hotelKey": "11", "isOperatorHotelKey": 0},
    315, {"10", "11"},
)
assert catalog_only["state"] == "catalog_only"

with tempfile.TemporaryDirectory() as td:
    d = pathlib.Path(td)
    reservation = d / "batch-001-page-1-reserved.json"
    reservation.write_text(json.dumps({
        "operation": m.SEALED_OP, "batch": 1, "page": 1, "stateinc": 3,
        "operator_id": 315, "hotel_count": 2, "state": "reserved_before_price"
    }), encoding="utf-8")
    evidence = d / "batch-001-page-1.json"
    evidence.write_text(json.dumps({
        "PAGES_COUNT": "1",
        "PRICES": [
            {"operatorKey": 315, "hotelKey": "10", "isOperatorHotelKey": 0,
             "original": {"hotelKey": "900", "operatorKey": 315}},
            {"operatorKey": 315, "hotelKey": "11", "isOperatorHotelKey": 0},
        ],
    }), encoding="utf-8")
    g = {"stateinc": 3, "operator_id": 315, "hotel_ids": ["10", "11"]}
    state, edges, batch = m.simulate_batch(
        1, g, {(1, 1): reservation}, {(1, 1): evidence}
    )
    assert state == "fully_drained"
    assert batch["pages_saved"] == 1
    by_id = {e["catalog_id"]: e for e in edges}
    assert by_id["10"]["state"] == "captured_single_native"
    assert by_id["10"]["positive_native_candidates"] == ["900"]
    assert by_id["11"]["state"] == "catalog_only"

with tempfile.TemporaryDirectory() as td:
    d = pathlib.Path(td)
    reservation = d / "batch-001-page-1-reserved.json"
    reservation.write_text(json.dumps({
        "operation": m.SEALED_OP, "batch": 1, "page": 1, "stateinc": 3,
        "operator_id": 115, "hotel_count": 1, "state": "reserved_before_price"
    }), encoding="utf-8")
    g = {"stateinc": 3, "operator_id": 115, "hotel_ids": ["10"]}
    state, edges, batch = m.simulate_batch(1, g, {(1, 1): reservation}, {})
    assert state == "partial_unresolved_no_replay"
    assert edges[0]["state"] == "partial_unresolved_no_replay"
    assert batch["pages_reserved"] == 1 and batch["pages_saved"] == 0

print("MATCH_SAMO_BUSINESS_LIVE30_COMMON4_RECOVER_V1_TEST_OK")
