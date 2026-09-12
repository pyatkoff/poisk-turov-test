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


if __name__ == "__main__":
    tests = sorted((n, f) for n, f in globals().items() if n.startswith("test_") and callable(f))
    for name, fn in tests:
        fn()
        print(name + ": PASS")
    print(f"hotel_match_tourvisor_operator_links_test: {len(tests)} tests PASS")
