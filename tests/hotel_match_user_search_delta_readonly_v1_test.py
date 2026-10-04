import contextlib
import copy
import hashlib
import importlib.util
import io
import json
import os
import pathlib
import tempfile
import unittest
from unittest import mock

MODULE_PATH = pathlib.Path(__file__).resolve().parents[1] / "scripts/diagnostics/hotel_match_user_search_delta_readonly_v1.py"
SPEC = importlib.util.spec_from_file_location("match_delta", MODULE_PATH)
m = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(m)
FIXTURE = MODULE_PATH.with_name("fixtures") / "hotel_match_user_search_delta_readonly_v1.json"


def row(obs=1, target=90001, operator=13):
    return {"id": str(obs), "fingerprint": hashlib.sha256(f"tourvisor|{target}|{operator}".encode()).hexdigest(),
            "hotel_id": str(target), "operator_id": str(operator), "first_seen_at": "2026-10-02 13:00:00",
            "last_seen_at": "2026-10-02 13:01:00", "source": "user_search", "country_id": "4",
            "search_id": "810", "tour_id": "tour-example", "native_id_conflict": "0",
            "operator_link": "https://agent.anextour.ru/search?HOTELLIST=804", "native_id_value": "999999"}


def context(r):
    return {"hotel_id": r["hotel_id"], "operator_id": r["operator_id"], "tour_id": r["tour_id"],
            "search_id": r["search_id"], "country_id": r["country_id"], "source": "user_search",
            "observed_at": "2026-10-02 13:00:00", "departure_id": "1", "departure_date": "2026-10-11",
            "nights": "7", "adults": "2", "children_count": "2", "child_ages_signature": "0,17"}


def capture(rows=None):
    rows = rows if rows is not None else [row()]
    return {"schema": "match-user-search-delta-db-projection/1", "clock": {"php_offset_seconds": 10800},
            "rows": rows, "overflow": False, "hotels": {r["hotel_id"]: {
                "id": r["hotel_id"], "name": "Example hotel", "country_id": "4", "country_name": "Турция",
                "region_name": "Мармарис", "subregion_name": None, "is_active": "1", "latitude": "36.85", "longitude": "28.25"} for r in rows},
            "owners": {}, "manual": {}, "exclusions": {}, "anex": {}, "price_context_table_present": True,
            "contexts": {r["id"]: [context(r)] for r in rows}, "context_overflow": {}}


class DeltaTest(unittest.TestCase):
    def test_manifest_pins_new_window_baseline_and_zero_authority(self):
        value = m.manifest(FIXTURE)
        self.assertEqual(value["previous_capture"], m.PREVIOUS)
        self.assertEqual(hashlib.sha256(FIXTURE.read_bytes()).hexdigest(), m.MANIFEST_SHA)
        with tempfile.TemporaryDirectory() as folder:
            altered = copy.deepcopy(value)
            altered["window_civil"]["lower_exclusive"] = "2026-10-01 00:00:00"
            p = pathlib.Path(folder) / "bad.json"
            p.write_bytes(m.enc(altered))
            with self.assertRaisesRegex(RuntimeError, "manifest_hash"):
                m.manifest(p)

    def test_duplicate_signed_multiple_selectors_keep_order_and_never_accept(self):
        p = m.native_projection(13, "https://agent.anextour.ru/search?HOTELLIST=804,44562&HOTELLIST=-804&hotelCode=%2B804")
        self.assertEqual(p["raw_identity_values"], ["804,44562", "-804", "+804"])
        self.assertEqual(p["raw_identity_tokens"], ["804", "44562", "-804", "+804"])
        self.assertEqual(p["positive_native_candidates"], ["804", "44562"])
        self.assertEqual(p["link_state"], "captured_ambiguous_or_unknown_selector")
        self.assertFalse(p["namespace_bridge_verified"])
        # Flattened native_id_value is deliberately irrelevant to raw link tokens.
        c = capture()
        c["rows"][0]["operator_link"] = "https://agent.anextour.ru/search?HOTELLIST=804,44562"
        projected = m.review_capture(c, "a" * 64)[0]
        self.assertEqual(projected["operator_link"]["positive_native_candidates"], ["804", "44562"])
        self.assertNotIn("999999", json.dumps(projected))

    def test_operator_aliases_are_candidates_and_bg_never_arithmetic_bridge(self):
        for op, url in ((13, "https://anextour.ru/x?hotelCode=835"), (25, "https://b2b.fstravel.com/x?HOTELS=98"),
                        (43, "https://b2b.intourist.ru/x?hotelId=24402")):
            self.assertEqual(m.native_projection(op, url)["link_state"], "captured_selector_candidate")
        bg = m.native_projection(18, "https://www.bgoperator.ru/price.shtml?F4=102625414997&F4=-102625414997")
        self.assertEqual(bg["raw_identity_tokens"], ["102625414997", "-102625414997"])
        self.assertEqual(bg["positive_native_candidates"], ["102625414997"])
        self.assertEqual(bg["link_state"], "captured_bg_full_code_bridge_unproven")
        self.assertFalse(bg["namespace_bridge_verified"])

    def test_origin_and_secret_projection_does_not_disclose_query_credentials(self):
        for url in ("http://anextour.ru/x?HOTELLIST=804", "https://anextour.ru.evil.example/x?HOTELLIST=804",
                    "https://name:private@anextour.ru/x?HOTELLIST=804", "https://anextour.ru:444/x?HOTELLIST=804",
                    "https://anextour.ru/x?HOTELLIST=804&access_token=private-value", "https://anextour.ru/%74oken/private-value?HOTELLIST=804",
                    "https://anextour.ru/x?HOTELLIST=opaque-private-value"):
            p = m.native_projection(13, url)
            self.assertEqual(p["raw_identity_tokens"], [])
            self.assertNotIn("private-value", json.dumps(p))
            self.assertNotIn("https://", json.dumps(p))

    def test_exact_price_context_rejects_identity_party_and_window_mismatch(self):
        r, c = row(), context(row())
        self.assertEqual(m.context_projection(r, [c])["state"], "exact_saved_price_context_candidate")
        for field, value in (("operator_id", "18"), ("tour_id", "wrong"), ("search_id", "wrong"), ("country_id", "1")):
            changed = dict(c, **{field: value})
            self.assertEqual(m.context_projection(r, [changed])["state"], "price_context_identity_mismatch")
        for field, value in (("observed_at", m.LOWER), ("departure_date", "2026-02-30"), ("child_ages_signature", "0,18"),
                             ("children_count", "1"), ("adults", "0")):
            changed = dict(c, **{field: value})
            self.assertEqual(m.context_projection(r, [changed])["state"], "price_context_invalid_or_outside_window")
        self.assertEqual(m.context_projection(r, [c, c])["state"], "price_context_ambiguous")
        self.assertEqual(m.context_projection(r, [])["state"], "price_context_missing")

    def test_new_membership_rejects_old_cohort_duplicates_bad_timestamp_and_clock(self):
        for mutate in (lambda c: c["rows"][0].update(first_seen_at=m.LOWER),
                       lambda c: c["rows"][0].update(first_seen_at="2026-10-03 09:23:18"),
                       lambda c: c["rows"][0].update(last_seen_at="2026-10-02 12:00:00"),
                       lambda c: c["rows"].append(copy.deepcopy(c["rows"][0])),
                       lambda c: c["rows"][0].update(fingerprint="0" * 64),
                       lambda c: c["clock"].update(php_offset_seconds=50401)):
            c = capture()
            mutate(c)
            with self.assertRaises((RuntimeError, ValueError)):
                m.review_capture(c, "a" * 64)
        with self.assertRaisesRegex(RuntimeError, "capture_shape"):
            m.review_capture(capture([row()] * 5001), "a" * 64)

    def test_scope_holds_separate_protected_occupied_manual_excluded_and_latest(self):
        c = capture()
        r = c["rows"][0]
        r.update(native_id_conflict="1", last_seen_at="2026-10-04 01:00:00")
        c["owners"][r["hotel_id"]] = [{"supplier_namespace": "andromeda_catalog", "external_hotel_id": "2000086118", "local_hotel_id": r["hotel_id"], "decision_status": "accepted"}]
        c["manual"][r["hotel_id"]] = [{"anex_hotel_id": "804", "catalog_hotel_id": r["hotel_id"], "decision_status": "manual"}]
        c["exclusions"][r["hotel_id"]] = [{"anex_hotel_id": "804", "catalog_hotel_id": r["hotel_id"]}]
        c["context_overflow"][r["id"]] = True
        p = m.review_capture(c, "a" * 64)[0]
        for reason in ("target_existing_registry_ownership_review", "protected_source_2000086118", "target_manual_decision_protected", "target_pair_exclusion_protected", "latest_enrichment_after_window", "native_id_conflict", "price_context_capture_incomplete"):
            self.assertIn(reason, p["holds"])
        for flag in m.FALSE_FLAGS:
            self.assertIs(p[flag], False)
        self.assertEqual(p["target_id_namespace"], "tourvisor")
        self.assertIsNone(p["independent_anytour_local_id"])

    def _execute(self, capture_value=None, fail=None):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        base = pathlib.Path(temp.name)
        root = base / "anytoour.ru"
        root.mkdir()
        opdir = base / ".anytoour-match" / "operations" / m.OP
        opdir.mkdir(parents=True)
        m.save(opdir / "reservation.json", {"operation": m.OP, "batch": m.BATCH, "source_sha": "b" * 40,
                                           "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_db_read"})
        stdout = io.StringIO()
        kwargs = {"side_effect": fail} if fail else {"return_value": capture_value or capture()}
        with mock.patch.dict(os.environ, MATCH_SOURCE_SHA="b" * 40), mock.patch.object(m, "capture_db", **kwargs) as call, contextlib.redirect_stdout(stdout):
            code = m.execute(root, opdir, FIXTURE)
        self.assertEqual(call.call_count, 1)
        result = m.read_json(opdir / "result.json")
        receipt = m.read_json(opdir / "receipt.json")
        m.validate_result(result, receipt, "b" * 40)
        self.assertEqual(set(json.loads(stdout.getvalue())), {"state", "observations_selected", "accepted", "written"})
        for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):
            self.assertEqual((opdir / name).stat().st_mode & 0o777, 0o600)
        self.assertEqual(hashlib.sha256((opdir / "current-input.json").read_bytes()).hexdigest(), result["private_input_sha256"])
        with mock.patch.dict(os.environ, MATCH_SOURCE_SHA="b" * 40), mock.patch.object(m, "capture_db") as call:
            with self.assertRaisesRegex(RuntimeError, "terminal_no_replay"):
                m.execute(root, opdir, FIXTURE)
            call.assert_not_called()
        return code, result, receipt, opdir

    def test_durable_execution_private_raw_public_no_effects_and_no_replay(self):
        c = capture()
        c["rows"][0]["operator_link"] += "&access_token=private-token-example"
        code, result, receipt, opdir = self._execute(c)
        self.assertEqual(code, 0)
        self.assertEqual(result["observations_selected"], 1)
        self.assertIn("private-token-example", (opdir / "current-input.json").read_text())
        self.assertNotIn("private-token-example", json.dumps(result))
        self.assertNotIn("https://", json.dumps(result))
        self.assertEqual(set(receipt), {*m.RECEIPT_KEYS, "result_sha256"})
        for counter in m.NO_EFFECTS:
            self.assertEqual(result[counter], 0)
        altered = copy.deepcopy(result)
        altered["rows"][0]["safe_to_write_now"] = True
        with self.assertRaisesRegex(RuntimeError, "public_row_authority"):
            m.validate_result(altered)

    def test_capture_failure_saves_private_placeholder_terminal_receipt_without_exception(self):
        code, result, _, opdir = self._execute(fail=RuntimeError("password=private-token-example"))
        self.assertEqual(code, 2)
        self.assertEqual(result["state"], "terminal_failed_no_replay")
        self.assertEqual(result["observations_selected"], 0)
        self.assertEqual(m.read_json(opdir / "current-input.json")["state"], "capture_failed")
        self.assertNotIn("private-token-example", (opdir / "result.json").read_text())

    def test_overflow_is_incomplete_and_bad_public_text_holds_one_row(self):
        c = capture([row(), row(2, 90002)])
        c["overflow"] = True
        c["hotels"]["90001"]["name"] = "https://private.example/path"
        code, result, _, _ = self._execute(c)
        self.assertEqual(code, 0)
        self.assertEqual(result["state"], "completed_read_only_delta_incomplete")
        self.assertEqual(result["observations_selected"], 2)
        self.assertIn("current_projection_text_redacted", result["rows"][0]["holds"])
        self.assertNotIn("current_projection_text_redacted", result["rows"][1]["holds"])

    def test_php_capture_has_no_write_or_supplier_capability_and_queries_are_batched(self):
        self.assertIn("START TRANSACTION READ ONLY", m.PHP_CAPTURE)
        self.assertIn("LIMIT 5001", m.PHP_CAPTURE)
        self.assertIn("array_chunk($rows,250)", m.PHP_CAPTURE)
        self.assertIn("LIMIT 10001", m.PHP_CAPTURE)
        self.assertNotRegex(m.PHP_CAPTURE, r"(?i)\b(INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|REPLACE|COMMIT)\b")
        with mock.patch.object(m.subprocess, "run", return_value=mock.Mock(returncode=0, stdout=m.enc(capture()))) as run:
            m.capture_db(pathlib.Path("/safe/anytoour.ru"))
        args = run.call_args.args[0]
        self.assertIn("allow_url_fopen=0", args)
        self.assertIn("allow_url_include=0", args)
        self.assertIn("disable_functions=" + m.PHP_DISABLED, args)


if __name__ == "__main__":
    unittest.main()
