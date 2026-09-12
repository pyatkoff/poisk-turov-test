#!/usr/bin/env python3
import importlib.util
from pathlib import Path

MODULE_PATH = Path(__file__).resolve().parents[1] / "scripts" / "diagnostics" / "hotel_match_post156_parent_geo_extension.py"
spec = importlib.util.spec_from_file_location("parent_geo_extension", MODULE_PATH)
mod = importlib.util.module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(mod)

def synthetic():
    local = []
    aliases = []
    # Five accepted rows establish townKey 700 -> region 10.
    for idx in range(1, 6):
        local.append({
            "id": idx,
            "country_class": "turkey",
            "country_id": 4,
            "country_name": "Turkey",
            "name": f"ANCHOR {idx}",
            "region_id": 10,
            "region_name": "Istanbul",
            "subregion_id": None,
            "subregion_name": None,
            "category": 4,
            "is_active": 1,
            "latitude": None,
            "longitude": None,
        })
    local += [
        {
            "id": 100,
            "country_class": "turkey",
            "country_id": 4,
            "country_name": "Turkey",
            "name": "DOUBLE TREE BY HILTON ISTANBUL OLD TOWN",
            "region_id": 10,
            "region_name": "Istanbul",
            "subregion_id": None,
            "subregion_name": None,
            "category": 5,
            "is_active": 1,
            "latitude": None,
            "longitude": None,
        },
        {
            "id": 101,
            "country_class": "turkey",
            "country_id": 4,
            "country_name": "Turkey",
            "name": "ALMINA INN BEYAZIT",
            "region_id": 10,
            "region_name": "Istanbul",
            "subregion_id": None,
            "subregion_name": None,
            "category": 3,
            "is_active": 1,
            "latitude": None,
            "longitude": None,
        },
    ]
    andromeda = []
    for idx in range(1, 6):
        andromeda.append({
            "external_hotel_id": str(1000 + idx),
            "country_class": "turkey",
            "decision_status": "accepted",
            "local_hotel_id": idx,
            "sources": [{"name": f"ANCHOR {idx}", "lName": f"ANCHOR {idx}", "townKey": 700, "town": "Fatih", "starKey": 4}],
        })
    # Accepted cross-provider name bridge for the live ANEX evidence lane.
    andromeda.append({
        "external_hotel_id": "9000",
        "country_class": "turkey",
        "decision_status": "accepted",
        "local_hotel_id": 101,
        "sources": [{"name": "Almina Inn Hotel", "lName": "Almina Inn Hotel", "townKey": 700, "town": "Fatih", "starKey": 3}],
    })
    # Pending fuzzy row: high name score, strong learned parent geography, unique target.
    andromeda.append({
        "external_hotel_id": "9999",
        "country_class": "turkey",
        "decision_status": "pending",
        "local_hotel_id": None,
        "sources": [{"name": "DoubleTree by Hilton Istanbul Old Town", "lName": "DoubleTree by Hilton Istanbul Old Town", "townKey": 700, "town": "Fatih", "starKey": 5}],
    })
    return {
        "operation_id": "synthetic-census",
        "local": local,
        "aliases": aliases,
        "andromeda": andromeda,
        "anex_mappings": [],
        "anex_decisions": [],
        "anex_observations": [{
            "anex_hotel_id": 555,
            "country_id": 4,
            "hotel_name": "Almina Inn Hotel.",
            "last_catalog_hotel_id": None,
            "search_count": 50,
        }],
    }

def test_build_report():
    previous = {
        "selected_count": 0,
        "candidate_sha256": "0" * 64,
        "source_census_sha256": "1" * 64,
        "rows": [],
    }
    report = mod.build_report(synthetic(), previous)
    assert report["status"] == "prepared_only"
    assert report["database_writes"] == 0
    assert report["mapping_writes"] == 0
    assert report["supplier_calls"] == 0
    assert report["tourvisor_calls"] == 0
    assert report["andromeda_extension_count"] == 1, report
    assert report["andromeda_extension_rows"][0]["external_hotel_id"] == "9999"
    assert report["andromeda_extension_rows"][0]["proposed_local_id"] == 100
    assert report["live_anex_bridge_count"] == 1, report
    assert report["live_anex_bridge_rows"][0]["anex_hotel_id"] == "555"
    assert report["live_anex_bridge_rows"][0]["proposed_local_id"] == 101
    assert report["consolidated_prioritized_queue_count"] == 2

def test_no_reuse_of_previous_target():
    census = synthetic()
    previous = {
        "selected_count": 1,
        "candidate_sha256": "0" * 64,
        "source_census_sha256": "1" * 64,
        "rows": [{"external_hotel_id": "old", "proposed_local_id": 100}],
    }
    report = mod.build_report(census, previous)
    assert report["andromeda_extension_count"] == 0
    assert report["previous_prepared_count"] == 1

if __name__ == "__main__":
    test_build_report()
    test_no_reuse_of_previous_target()
    print("hotel_match_post156_parent_geo_extension_test: PASS")
