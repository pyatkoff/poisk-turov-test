#!/usr/bin/env python3
import importlib.util
from pathlib import Path

P = Path(__file__).resolve().parents[1] / "scripts/diagnostics/hotel_match_core8_cold_coordinate_evidence.py"
spec = importlib.util.spec_from_file_location("cold", P)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

rows = []
for i in range(1399):
    rows.append({
        "external_hotel_id": str(100000 + i),
        "country_id": 1,
        "names": ["Unrelated Place"],
        "points": [],
        "geo_anchors": [],
        "frequency": 0,
    })
excluded = {str(100000 + i) for i in range(156)}
rows[156] = {
    "external_hotel_id": "100156",
    "country_id": 1,
    "names": ["Alpha Beach Hotel 2"],
    "points": [{"latitude": 25.0, "longitude": 34.0}],
    "geo_anchors": [{"scope": "region", "scope_id": 11}],
    "frequency": 7,
}
rows[157] = {
    "external_hotel_id": "100157",
    "country_id": 1,
    "names": ["Alpha Garden Hotel 2"],
    "points": [{"latitude": 25.0, "longitude": 34.0}],
    "geo_anchors": [{"scope": "region", "scope_id": 11}],
    "frequency": 9,
}
result = {
    "operation_id": "fixture",
    "source_sha": "fixture",
    "routes": {"needs_extra_evidence": rows},
    "local_hotels": {
        "501": {
            "id": 501, "country_id": 1, "name": "ALPHA BEACH RESORT 2",
            "latitude": "25.0005", "longitude": "34.0005",
            "region_id": 11, "subregion_id": None,
        },
        "502": {
            "id": 502, "country_id": 1, "name": "BETA HOTEL",
            "latitude": "25.0007", "longitude": "34.0007",
            "region_id": 11, "subregion_id": None,
        },
    },
    "local_alias_forms": {
        "501": ["Alpha Beach Resort 2"],
        "502": ["Beta Hotel"],
    },
}
report = m.analyze(result, excluded)
assert report["examined_cold_rows"] == 1243
assert report["cold_rows_with_saved_coordinates"] == 2
assert report["prepared_coordinate_dossiers"] == 1
assert report["strong_prepared_coordinate_dossiers"] == 1
d = report["dossiers"][0]
assert d["source"]["external_hotel_id"] == "100156"
assert d["local_id"] == 501
assert d["geo_guard"]["status"] == "match"
assert d["runner_up"] is None
assert d["auto_accept"] is False
print("hotel_match_core8_cold_coordinate_evidence_test: ok")
