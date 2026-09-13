#!/usr/bin/env python3
import importlib.util
from pathlib import Path

P = Path(__file__).resolve().parents[1] / "scripts" / "diagnostics" / "hotel_match_tourvisor_operator_links.py"
spec = importlib.util.spec_from_file_location("tvlinks", P)
mod = importlib.util.module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(mod)


def queue():
    return {"rows": [
        {"anex_hotel_id": 8121, "proposed_local_id": 6319, "country_id": 1, "target_name": "APERION BEACH", "search_count": 50},
        {"anex_hotel_id": 32692, "proposed_local_id": 70291, "country_id": 4, "target_name": "ALMINA INN", "search_count": 27},
    ]}


def tv_row(local_id=6319, country_id=1, op="ANEX Tour", link="https://agent.anextour.ru/search/tour?HOTELLIST=8121", tour_id="abc"):
    return {
        "id": tour_id,
        "operator": {"id": 7, "name": op, "fullName": op, "russianName": "АНЕКС" if "ANEX" in op else op},
        "operatorLink": link,
        "hotel": {
            "id": local_id,
            "name": "APERION BEACH",
            "country": {"id": country_id, "name": "Turkey"},
            "region": {"id": 10, "name": "Kemer"},
            "subRegion": {"id": 11, "name": "Side"},
            "common": {"latitude": 36.7, "longitude": 31.56},
        },
    }


def test_happy_path():
    r = mod.build(queue(), [tv_row()], "2026-09-20")
    assert r["operator_link_ready"] == 1, r
    c = r["captures"][0]
    assert c["anex_hotel_id"] == 8121
    assert c["local_hotel_id"] == 6319
    assert c["operator"] == "ANEX"
    assert c["operator_filter"] == "ANEX"
    assert c["date_from"] == c["date_to"] == "2026-09-20"
    assert c["tourvisor_latitude"] == 36.7
    assert c["status"] == "operator_link_ready_for_anex_card_hotelcode"
    assert r["database_writes"] == r["mapping_writes"] == r["tourvisor_calls"] == 0


def test_non_anex_ignored():
    r = mod.build(queue(), [tv_row(op="FUN&SUN")], "2026-09-20")
    assert r["operator_link_ready"] == 0


def test_country_conflict_rejected():
    r = mod.build(queue(), [tv_row(country_id=4)], "2026-09-20")
    assert r["operator_link_ready"] == 0
    assert r["rejected"][0]["reason"] == "country_conflict"


def test_missing_link_rejected():
    r = mod.build(queue(), [tv_row(link=None)], "2026-09-20")
    assert r["operator_link_ready"] == 0
    assert r["rejected"][0]["reason"] == "missing_or_invalid_operator_link"


def test_foreign_host_rejected():
    r = mod.build(queue(), [tv_row(link="https://example.com/search/tour?HOTELLIST=8121")], "2026-09-20")
    assert r["operator_link_ready"] == 0


def test_secret_query_rejected():
    r = mod.build(queue(), [tv_row(link="https://agent.anextour.ru/search/tour?HOTELLIST=8121&token=x")], "2026-09-20")
    assert r["operator_link_ready"] == 0


def test_duplicate_identical_collapses():
    row = tv_row()
    r = mod.build(queue(), [row, dict(row)], "2026-09-20")
    assert r["operator_link_ready"] == 1


def test_ambiguous_links_fail_closed():
    r = mod.build(queue(), [tv_row(tour_id="a"), tv_row(tour_id="b", link="https://agent.anextour.ru/search/tour?HOTELLIST=9999")], "2026-09-20")
    assert r["operator_link_ready"] == 0
    assert r["ambiguous_anex_ids"] == 1
    assert all(x["reason"] == "ambiguous_operator_links" for x in r["rejected"])


def test_unexpected_hotel_not_consumed():
    r = mod.build(queue(), [tv_row(local_id=999999)], "2026-09-20")
    assert r["operator_link_ready"] == 0


def test_wrapped_results_shape():
    r = mod.build(queue(), {"results": [tv_row()]}, "2026-09-20")
    assert r["operator_link_ready"] == 1


def test_bad_date_fails():
    try:
        mod.build(queue(), [tv_row()], "2026-99-99")
    except ValueError:
        pass
    else:
        raise AssertionError("bad date accepted")


def test_core8_scope():
    q = {"rows": [{"anex_hotel_id": 1, "proposed_local_id": 6319, "country_id": 999, "search_count": 1}]}
    r = mod.build(q, [tv_row()], "2026-09-20")
    assert r["queue_targets"] == 0


def grouped_row(**kwargs):
    row = tv_row(**kwargs)
    hotel = row.pop("hotel")
    return dict(hotel, tours=[row])


def must_reject_payload(payload):
    try:
        mod.build(queue(), payload, "2026-09-20")
    except ValueError:
        return
    raise AssertionError("malformed grouped payload accepted")


def test_grouped_list_and_all_supported_envelopes():
    group = grouped_row()
    expected = mod.build(queue(), [tv_row()], "2026-09-20")["captures"]
    for payload in ([group], {"hotels": [group]}, {"results": [group]},
                    {"items": [group]}, {"data": [group]}):
        result = mod.build(queue(), payload, "2026-09-20")
        assert result["captures"] == expected, result
        assert result["source_results_sha256"] == mod.sha(payload)
        assert result["source_queue_sha256"] == mod.sha(queue())


def test_grouped_mixed_operators_and_missing_links():
    group = grouped_row()
    other = grouped_row(op="FUN&SUN")["tours"][0]
    missing = grouped_row(tour_id="missing", link=None)["tours"][0]
    group["tours"] += [other, missing]
    result = mod.build(queue(), {"hotels": [group]}, "2026-09-20")
    assert result["operator_link_ready"] == 1
    assert result["input_result_rows"] == 3
    assert result["rejected"] == [{"anex_hotel_id": 8121, "local_hotel_id": 6319,
                                   "reason": "missing_or_invalid_operator_link"}]


def test_grouped_parent_identity_never_overwritten():
    for nested in ({"id": 9999}, {"id": 6319}, None):
        group = grouped_row()
        group["tours"][0]["hotel"] = nested
        must_reject_payload([group])
    group = grouped_row()
    group["tours"][0]["country"] = {"id": 4}
    must_reject_payload([group])
    group = grouped_row()
    group["tours"][0]["country"] = {"id": 1}
    assert mod.build(queue(), [group], "2026-09-20")["operator_link_ready"] == 1


def test_malformed_group_cannot_be_reported_as_empty():
    for tours in (None, {}, "bad", [None], ["bad"]):
        group = grouped_row()
        group["tours"] = tours
        must_reject_payload({"hotels": [group]})
    group = grouped_row()
    group["id"] = None
    must_reject_payload([group])
    must_reject_payload({"hotels": [], "results": [tv_row()]})
    for payload in ([], {"hotels": []}, [dict(grouped_row(), tours=[])]):
        assert mod.build(queue(), payload, "2026-09-20")["operator_link_ready"] == 0


def test_top_level_coordinate_pair_and_legacy_fallback():
    group = grouped_row()
    del group["common"]
    group.update(latitude="36.7000000", longitude="31.5600000")
    result = mod.build(queue(), [group], "2026-09-20")
    c = result["captures"][0]
    assert (c["tourvisor_latitude"], c["tourvisor_longitude"]) == ("36.7000000", "31.5600000")
    group["common"] = {"latitude": 36.7, "longitude": 31.56}
    assert mod.build(queue(), [group], "2026-09-20")["operator_link_ready"] == 1
    del group["latitude"], group["longitude"]
    c = mod.build(queue(), [group], "2026-09-20")["captures"][0]
    assert (c["tourvisor_latitude"], c["tourvisor_longitude"]) == (36.7, 31.56)
    del group["common"]
    c = mod.build(queue(), [group], "2026-09-20")["captures"][0]
    assert c["tourvisor_latitude"] is c["tourvisor_longitude"] is None


def test_coordinate_sources_not_cherry_picked_or_mixed():
    variants = [
        {"latitude": 26.7, "longitude": 33.56},  # >5km disagreement
        {"latitude": 36.7},  # never fill longitude from another source
        {"latitude": True, "longitude": 31.56},
        {"latitude": "NaN", "longitude": 31.56},
        {"latitude": 91, "longitude": 31.56},
        {"latitude": 36.7, "longitude": 181},
    ]
    for patch in variants:
        group = dict(grouped_row(), **patch)
        result = mod.build(queue(), [group], "2026-09-20")
        assert result["operator_link_ready"] == 0, patch
        assert result["rejected"][0]["reason"] in {"invalid_coordinates", "coordinate_source_conflict"}


def test_grouped_keeps_country_and_duplicate_guards():
    group = grouped_row(country_id=4)
    assert mod.build(queue(), [group], "2026-09-20")["operator_link_ready"] == 0
    group = grouped_row()
    group["country"] = 1
    assert mod.build(queue(), [group], "2026-09-20")["operator_link_ready"] == 1
    group["tours"] += grouped_row(link="https://agent.anextour.ru/search/tour?HOTELLIST=9999", tour_id="two")["tours"]
    result = mod.build(queue(), [group], "2026-09-20")
    assert result["operator_link_ready"] == 0 and result["ambiguous_anex_ids"] == 1
    q = queue()
    del q["rows"][0]["country_id"]
    assert mod.build(q, [grouped_row()], "2026-09-20")["operator_link_ready"] == 0


def test_grouped_input_unchanged_and_deterministic():
    import copy
    payload = {"hotels": [grouped_row()]}
    original = copy.deepcopy(payload)
    q = queue()
    q_original = copy.deepcopy(q)
    one = mod.build(q, payload, "2026-09-20")
    two = mod.build(q, payload, "2026-09-20")
    assert one == two and payload == original and q == q_original


def test_3000_grouped_hotels_preserve_every_anex_child():
    # Synthetic scale fixture only. No supplier/DB requests or real identities.
    groups, targets = [], []
    for i in range(3000):
        local_id, anex_id = 1000000 + i, 2000000 + i
        link = f"https://agent.anextour.ru/search/tour?HOTELLIST={anex_id}"
        group = grouped_row(local_id=local_id, country_id=4, link=link, tour_id=f"{i}-a")
        second = dict(group["tours"][0], id=f"{i}-b")
        group["tours"] += [second, grouped_row(op="FUN&SUN")["tours"][0]]
        groups.append(group)
        targets.append({"anex_hotel_id": anex_id, "proposed_local_id": local_id, "country_id": 4})
    result = mod.build({"rows": targets}, {"hotels": groups}, "2026-09-20")
    assert result["input_result_rows"] == 9000
    assert result["operator_link_ready"] == 6000
    assert result["rejected_count"] == result["ambiguous_anex_ids"] == 0
    assert len({r["local_hotel_id"] for r in result["captures"]}) == 3000
    assert all(r["not_write_authority"] for r in result["captures"])
    assert result["database_writes"] == result["mapping_writes"] == result["supplier_calls"] == result["tourvisor_calls"] == 0


if __name__ == "__main__":
    tests = sorted((n, f) for n, f in globals().items() if n.startswith("test_") and callable(f))
    for name, fn in tests:
        fn()
        print(name + ": PASS")
    print(f"hotel_match_tourvisor_operator_links_test: {len(tests)} tests PASS")
