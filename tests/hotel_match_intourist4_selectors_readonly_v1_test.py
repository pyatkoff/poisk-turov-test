#!/usr/bin/env python3
import importlib.util
import json
import pathlib
import tempfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
PATH = ROOT / "scripts/diagnostics/hotel_match_intourist4_selectors_readonly_v1.py"
SPEC = importlib.util.spec_from_file_location("intourist4", PATH)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

MANIFEST = ROOT / "scripts/diagnostics/fixtures/hotel_match_intourist4_selectors_readonly_v1.json"
data = MODULE.manifest(MANIFEST)
assert data["operation"] == MODULE.OP
assert data["batch"] == MODULE.BATCH
assert data["request"]["operator_id"] == 43
assert data["http_cap"] == 14
groups = MODULE.groups(data)
assert [g["country_id"] for g in groups] == [4, 1]
assert [[r["target_tv_hotel_id"] for r in g["rows"]] for g in groups] == [[1151, 70943], [128, 80964]]


class FakeProvider:
    def __init__(self):
        self.root = ROOT
        self.calls = []

    def check(self, action, rows):
        return MODULE.current_preflight(self.root, rows)

    def call(self, action, path, params, rows):
        self.calls.append((action, path, params, rows))
        if action == "search_start":
            return 200, {"searchId": 77}
        if action == "search_status":
            return 200, {"status": "ready"}
        if action == "search_results":
            return 200, {
                "hotels": [
                    {"id": 1151, "tours": [{"id": 99, "operator": {"id": 43}}]},
                    {"id": 70943, "tours": [{"id": 100, "operatorId": 43}]},
                ]
            }
        if action == "tour_detail":
            hotel = 1151 if path.endswith("/99") else 70943
            native = "549" if hotel == 1151 else "18273"
            return 200, {
                "hotel": {"id": hotel},
                "operator": {"id": 43},
                "operatorLink": "https://intourist.example/hotel?hotelId=" + native,
            }
        raise AssertionError(action)


old_preflight = MODULE.current_preflight
old_sleep = MODULE.time.sleep
MODULE.current_preflight = lambda root, rows: [
    {
        "source_catalog_id": row["source_catalog_id"],
        "target_tv_hotel_id": row["target_tv_hotel_id"],
        "state": "eligible",
        "holds": [],
        "safe_to_write_now": False,
    }
    for row in rows
]
MODULE.time.sleep = lambda seconds: None
try:
    with tempfile.TemporaryDirectory() as tmp:
        provider = FakeProvider()
        result = MODULE.run_group(provider, pathlib.Path(tmp), 1, groups[0], data["request"])
        assert result["state"] == "completed_read_only"
        assert result["returned_targets"] == 2
        start = provider.calls[0]
        assert start[0] == "search_start"
        assert start[2]["operatorIds"] == [43]
        assert start[2]["hotelIds"] == [1151, 70943]
        assert all(call[0] != "search_continue" for call in provider.calls)
        assert all(call[0] != "dates" for call in provider.calls)
        edges = result["edges"]
        assert [edge["positive_native_candidates"] for edge in edges] == [[549], [18273]]
        assert [edge["matches_source_native"] for edge in edges] == [True, True]
        assert all(edge["safe_to_write_now"] is False for edge in edges)
finally:
    MODULE.current_preflight = old_preflight
    MODULE.time.sleep = old_sleep


with tempfile.TemporaryDirectory() as tmp:
    quota = pathlib.Path(tmp)
    provider = MODULE.Provider.__new__(MODULE.Provider)
    provider.day_value = "2026-10-04"
    provider.quota = quota
    provider.day = quota / "tourvisor-anex-2026-10-04.json"
    provider.lock = quota / "tourvisor-anex-2026-10-04.lock"
    provider.used = 0
    provider.tariff_used = 0
    provider.reserve("search_start")
    provider.reserve("search_status")
    ledger = json.loads(provider.day.read_text())
    assert ledger["physical_http_attempts"] == 2
    assert ledger["tariff_search_units"] == 1
    assert ledger["operations"][MODULE.OP]["physical_http_attempts"] == 2
    assert provider.day.stat().st_mode & 0o777 == 0o600

with tempfile.TemporaryDirectory() as tmp:
    quota = pathlib.Path(tmp)
    provider = MODULE.Provider.__new__(MODULE.Provider)
    provider.day_value = "2026-10-04"
    provider.quota = quota
    provider.day = quota / "tourvisor-anex-2026-10-04.json"
    provider.lock = quota / "tourvisor-anex-2026-10-04.lock"
    provider.used = 0
    provider.tariff_used = 0
    provider.day.write_text(json.dumps({
        "provider": MODULE.ACCOUNT_LEDGER,
        "provider_day": "2026-10-04",
        "owner_daily_limit": MODULE.DAILY_LIMIT,
        "tariff_search_units": 0,
        "physical_http_attempts": MODULE.DAILY_LIMIT,
        "operations": {},
    }))
    try:
        provider.reserve("search_status")
        raise AssertionError("physical daily cap accepted")
    except RuntimeError as error:
        assert str(error) == "quota_exhausted"

source = PATH.read_text()
assert "current = self.check(action, rows)" in source
assert source.index("current = self.check(action, rows)") < source.index("self.reserve(action)")
assert "os.replace(tmp, self.day)" in source
assert "fsync_dir(self.quota)" in source
assert "read_json(self.day) != state" in source
assert "physical >= DAILY_LIMIT" in source
assert '"operatorIds": [43]' in source
assert '"continue_calls": 0' in source
assert '"database_writes": 0' in source
assert '"database_reads": len(provider.preflight)' in source
assert '"mapping_writes": 0' in source
assert MODULE.safe_payload({"operatorLink": "https://example.test/hotel?hotelId=549"}, "token")
assert not MODULE.safe_payload({"operatorLink": "https://example.test/hotel?session=secret"}, "token")
print("MATCH_INTOURIST4_SELECTORS_READONLY_V1_TEST_OK")
