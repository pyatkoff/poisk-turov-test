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
            "sources": [{"name": f"ANCHOR {idx}", "lName": f"ANCHOR {idx}", "townKey": 700, "town": "Fatih", "starKey": 4, "star": "4"}],
        })
    # Accepted cross-provider name bridge for the live ANEX evidence lane.
    andromeda.append({
        "external_hotel_id": "9000",
        "country_class": "turkey",
        "decision_status": "accepted",
        "local_hotel_id": 101,
        "sources": [{"name": "Almina Inn Hotel", "lName": "Almina Inn Hotel", "townKey": 700, "town": "Fatih", "starKey": 3, "star": "3"}],
    })
    # Pending fuzzy row: high name score, strong learned parent geography, unique target.
    andromeda.append({
        "external_hotel_id": "9999",
        "country_class": "turkey",
        "decision_status": "pending",
        "local_hotel_id": None,
        "sources": [{"name": "DoubleTree by Hilton Istanbul Old Town", "lName": "DoubleTree by Hilton Istanbul Old Town", "townKey": 700, "town": "Fatih", "starKey": 5, "star": "5"}],
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


def check_names(source, target, pair=None, label="4", key=4):
    census = synthetic()
    census["local"][-2]["name"] = target
    pending = census["andromeda"][-1]
    pending["sources"] = [{"name": source, "lName": source, "star": label, "starKey": key}]
    row = {"external_hotel_id": "9999", "proposed_local_id": 100, "country": "turkey"}
    if pair:
        row["matched_name_pair"] = pair
    return mod.primary_recheck(census, [row], "andromeda")[0]


def test_one_sided_qualifiers():
    for qualifier in ("BEACH", "GARDEN", "ANNEX", "NORTH", "SOUTH", "POSH"):
        result = check_names("CORAL " + qualifier, "CORAL")
        assert "primary_qualifier_difference" in result["reason_codes"], qualifier
    assert not mod.qualifier_compatible(set(), {"beach"})
    assert not mod.qualifier_compatible({"north"}, {"south"})


def test_primary_not_lname():
    census = synthetic()
    census["local"][-2]["name"] = "SUNRISE DIAMOND BEACH"
    census["andromeda"][-1]["sources"] = [{"name": "POSH CLUB SUNRISE DIAMOND BEACH", "lName": "SUNRISE DIAMOND BEACH", "star": "5"}]
    row = {"external_hotel_id": "9999", "proposed_local_id": 100, "country": "turkey", "matched_name_pair": ["sunrise diamond beach"] * 2}
    result = mod.primary_recheck(census, [row], "andromeda")[0]
    assert "primary_qualifier_difference" in result["reason_codes"]


def test_real_subset_failure_classes():
    for source, target in (
        ("Tropitel Waves Naama Bay", "Tropitel Naama Bay"),
        ("Rixos Radamis Blue Planet Sharm El Sheikh", "Rixos Radamis Sharm El Sheikh"),
        ("Suum Bodrum Beach", "Bodrum Beach"),
    ):
        result = check_names(source, target, [source, target])
        assert "non_generic_subset_requires_evidence" in result["reason_codes"]
        assert result["not_write_authority"] is True


def test_generic_and_exact_former_names():
    for source, target in (
        ("Aperion Beach Hotel Resort & Spa", "APERION BEACH (EX. SEA PARADISE)"),
        ("Eken Resort", "SMART STAY BEACH BODRUM (EX. EKEN RESORT HOTEL)"),
        ("Cocobay Boutique", "COCO BAY BOUTIQUE"),
        ("Desert Islands Resort", "DESERT ISLAND RESORT"),
        ("Signature Apartments Marina", "SIGNATURE APARTMENT MARINA"),
    ):
        assert not check_names(source, target)["reason_codes"], (source, target)


def test_star_key_is_not_count():
    assert not check_names("SAME NAME", "SAME NAME", label="5", key=2000000000)["reason_codes"]
    for label in ("Apts", "HV", "Boutique", None, "16", True):
        assert "star_label_requires_semantics" in check_names("SAME NAME", "SAME NAME", label=label, key=5)["reason_codes"]
    assert "star_label_difference" in check_names("SAME NAME", "SAME NAME", label="2", key=5)["reason_codes"]
    assert mod.numeric_star_label(" 4★ ") == 4


def test_all_source_primaries():
    census = synthetic()
    census["local"][-2]["name"] = "CORAL BEACH"
    census["andromeda"][-1]["sources"] = [{"name": "CORAL BEACH", "star": "5"}, {"name": "CORAL GARDEN", "star": "5"}]
    row = {"external_hotel_id": "9999", "proposed_local_id": 100, "country": "turkey"}
    assert "primary_qualifier_difference" in mod.primary_recheck(census, [row], "andromeda")[0]["reason_codes"]


def test_protected_and_inactive():
    census = synthetic()
    census["andromeda"][-1]["decision_status"] = "accepted"
    census["andromeda"][-1]["local_hotel_id"] = 100
    census["local"][-2]["is_active"] = 0
    row = {"external_hotel_id": "9999", "proposed_local_id": 100, "country": "turkey"}
    reasons = mod.primary_recheck(census, [row], "andromeda")[0]["reason_codes"]
    assert "source_not_unmapped_pending" in reasons and "target_missing_or_inactive" in reasons
    census["anex_mappings"] = [{"anex_hotel_id": 555, "enabled": 0}]
    row = {"anex_hotel_id": "555", "proposed_local_id": 101, "country": "turkey"}
    assert "protected_mapping_decision_or_pair" in mod.primary_recheck(census, [row], "anex")[0]["reason_codes"]


def test_full_batch_deterministic():
    import copy
    census = synthetic()
    row = {"external_hotel_id": "9999", "proposed_local_id": 100, "country": "turkey"}
    rows = [dict(row, external_hotel_id=str(200000 + i)) for i in range(3000)]
    original = copy.deepcopy(census)
    first = mod.primary_recheck(census, rows, "andromeda")
    second = mod.primary_recheck(census, list(reversed(rows)), "andromeda")
    assert len(first) == 3000
    assert mod.canonical_sha(first) == mod.canonical_sha(second)
    assert census == original



def test_preserved_cohort_guards():
    import copy
    census = synthetic()
    previous = {"selected_count": 0, "candidate_sha256": "0" * 64, "source_census_sha256": "1" * 64, "rows": []}
    extension = mod.build_report(census, previous)
    # build_report already returns filtered rows with matching output hashes.
    original = copy.deepcopy(extension)
    checked = mod.apply_primary_review(census, previous, extension)
    assert extension == original and checked["not_write_authority"] is True
    for field, value, error in (
        ("source_census_sha256", "2" * 64, "PREPARED_CENSUS_MISMATCH"),
        ("andromeda_extension_sha256", "3" * 64, "EXTENSION_DIGEST_MISMATCH"),
        ("live_anex_bridge_sha256", "4" * 64, "LIVE_BRIDGE_DIGEST_MISMATCH"),
    ):
        bad = dict(extension, **{field: value})
        try:
            mod.apply_primary_review(census, previous, bad)
        except ValueError as exc:
            assert str(exc) == error
        else:
            raise AssertionError(error)


def test_country_and_pair_preservation():
    census = synthetic()
    row = {"anex_hotel_id": "555", "proposed_local_id": 101, "country": "turkey"}
    census["anex_exclusions"] = [{"anex_hotel_id": 555, "catalog_hotel_id": 101}]
    assert "protected_mapping_decision_or_pair" in mod.primary_recheck(census, [row], "anex")[0]["reason_codes"]
    row["country"] = "russia"
    assert "country_scope_conflict" in mod.primary_recheck(census, [row], "anex")[0]["reason_codes"]


if __name__ == "__main__":
    tests = sorted((name, fn) for name, fn in list(globals().items()) if name.startswith("test_") and callable(fn))
    for name, fn in tests:
        fn()
        print(name + ": PASS")
    print(f"hotel_match_post156_parent_geo_extension_test: {len(tests)} tests PASS")
