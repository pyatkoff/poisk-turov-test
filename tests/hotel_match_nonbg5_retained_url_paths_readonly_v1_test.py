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

ROOT = pathlib.Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts/diagnostics/hotel_match_nonbg5_retained_url_paths_readonly_v1.py"
spec = importlib.util.spec_from_file_location("url5", SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
FIXTURE = SOURCE.with_name("fixtures") / "hotel_match_nonbg5_retained_url_paths_readonly_v1.json"


class UrlPaths(unittest.TestCase):
    def setUp(self):
        self.fixture = copy.deepcopy(m.manifest(FIXTURE))
        old = self.fixture["prior_result"]
        rows = []
        for i, row in enumerate(old["rows"]):
            refs = []
            host = m.HOSTS[row["source_namespace"]]
            for j, ref in enumerate(row["references"]):
                value = "https://" + host + "/hotel/" + str(i + 1)
                values = {"row.hotelUrl": {"present": True, "value": value}, "original.tourKey": {"present": True, "value": 42}}
                for field in ref["fields"]:
                    v = values[field["field_name"]]["value"]
                    field["value_sha256"] = hashlib.sha256(m.enc(v)).hexdigest()
                    field["value_bytes"] = len(m.enc(v))
                refs.append({**{k: ref[k] for k in ("source_file", "sha256", "json_pointer", "raw_verified", "failure")}, "fields": values})
            rows.append({**{k: row[k] for k in m.ROW_KEYS}, "references": refs})
        self.capture = {"schema": "match-nonbg7-unexported-private-input/1", "operation": m.OLD_OP, "batch": m.OLD_BATCH, **{k: old[k] for k in ("inputs", "projection_fields", "raw_files_attempted", "raw_files_read", "raw_bytes_read")}, "rows": rows}
        self.input_bytes = m.enc(self.capture)
        self.input_sha = hashlib.sha256(self.input_bytes).hexdigest()
        old["private_input_sha256"] = self.input_sha
        for i, row in enumerate(old["rows"]):
            for j, ref in enumerate(row["references"]):
                ref["private_input_pointer"]["sha256"] = self.input_sha
        self.result_bytes = m.enc(old)
        self.assertEqual(len(self.result_bytes), 39042)
        self.result_sha = hashlib.sha256(self.result_bytes).hexdigest()
        self.fixture["inputs"]["prior_private_input"]["sha256"] = self.input_sha
        self.fixture["inputs"]["prior_result"]["sha256"] = self.result_sha
        self.patches = [mock.patch.object(m, "OLD_INPUT_SHA", self.input_sha), mock.patch.object(m, "OLD_RESULT_SHA", self.result_sha)]
        for p in self.patches:
            p.start()
            self.addCleanup(p.stop)

    def result(self, rows=None):
        rows = m.project_capture(self.capture, self.fixture) if rows is None else rows
        count = sum(ref["projection"]["state"] == "safe_absolute_operator_path_candidate" for row in rows for ref in row["references"])
        return {"schema": "match-nonbg5-retained-url-paths-readonly-result/1", "operation": m.OP, "batch": m.BATCH, "source_sha": "a" * 40, "state": "completed_read_only_nonbg5_url_paths" if count == 8 else "completed_read_only_nonbg5_url_paths_incomplete", "reason": None, "failure_stage": None, "captured_at_utc": "2026-10-04T13:00:00Z", "private_input_sha256": "b" * 64, "inputs": self.fixture["inputs"], "requested_rows": 5, "requested_sources": 4, "distinct_source_count": 4, "rows_examined": 5, "operator_ids": [13, 25, 43], "rows": rows, "metadata_files_bound": 2, "metadata_bytes_bound": len(self.input_bytes) + len(self.result_bytes), "original_raw_files_read": 0, "references_bound": 8, "safe_path_candidate_references": count, "safe_path_candidate_rows": sum(any(r["projection"]["state"] == "safe_absolute_operator_path_candidate" for r in row["references"]) for row in rows), "hold_counts": dict(m.collections.Counter(h for row in rows for h in row["holds"])), "global_saved_context_only": True, "source_namespace_bridge_verified": False, "target_native_identity_verified": False, "no_replay": True, **dict.fromkeys(m.NO_EFFECTS, 0), **dict.fromkeys(m.FALSE_FLAGS, False)}

    def check_result(self, value):
        with mock.patch.object(m, "manifest", return_value=self.fixture):
            return m.validate_result(value)

    def test_fixture_contains_exact_reviewed_parent_and_scope(self):
        self.assertEqual(self.fixture["selected_rows"], [0, 1, 2, 3, 4])
        self.assertEqual([r["source_namespace"] for r in self.fixture["prior_result"]["rows"][:5]], ["operator_5", "operator_5", "operator_315", "operator_315", "operator_342"])
        self.assertNotIn("2000052591", [r["catalog_id"] for r in self.result()["rows"]])
        self.assertNotIn("2000073045", [r["catalog_id"] for r in self.result()["rows"]])

    def test_capture_reads_two_metadata_files_no_original_pages(self):
        with tempfile.TemporaryDirectory() as temp:
            root = pathlib.Path(temp).resolve()
            for pin, raw in (("prior_result", self.result_bytes), ("prior_private_input", self.input_bytes)):
                path = root / self.fixture["inputs"][pin]["path"]
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(raw)
            calls = []
            original = m.file_bytes
            def read(path, maximum):
                calls.append(str(path))
                return original(path, maximum)
            with mock.patch.object(m, "file_bytes", side_effect=read):
                capture = m.capture_retained(root, self.fixture)
            self.assertEqual(len(calls), 2)
            self.assertTrue(all(m.OLD_OP in p for p in calls))
            self.assertFalse(any("evidence-private" in p for p in calls))
            self.assertEqual(capture["original_raw_files_read"], 0)
            self.assertEqual(capture["metadata_files_bound"], 2)
            self.assertEqual(len(capture["rows"]), 5)
            self.assertEqual(sum(len(r["references"]) for r in capture["rows"]), 8)
            self.assertTrue(capture["complete_source_values_retained_in_prior_private_input"])

    def test_wrong_private_digest_fails_before_projection(self):
        with mock.patch.object(m, "file_bytes", side_effect=[self.result_bytes, self.input_bytes + b" "]), mock.patch.object(m, "project_capture") as projection:
            with self.assertRaises(m.CaptureFailure) as e:
                m.capture_retained(pathlib.Path("/unused"), self.fixture)
            self.assertEqual(e.exception.stage, "prior_metadata_unavailable_or_digest")
            projection.assert_not_called()

    def test_wrong_result_digest_fails_before_projection(self):
        with mock.patch.object(m, "file_bytes", side_effect=[self.result_bytes[:-1] + b" ", self.input_bytes]), mock.patch.object(m, "project_capture") as projection:
            with self.assertRaises(m.CaptureFailure):
                m.capture_retained(pathlib.Path("/unused"), self.fixture)
            projection.assert_not_called()

    def test_capture_closed_shape(self):
        bad = copy.deepcopy(self.capture)
        bad["fresh_global_verified"] = True
        with self.assertRaises(ValueError):
            m.validate_prior_capture(bad, self.fixture["prior_result"])

    def test_selected_identity_mismatch_rejected(self):
        bad = copy.deepcopy(self.capture)
        bad["rows"][3]["source_native_id"] = "17173"
        with self.assertRaises(ValueError):
            m.validate_prior_capture(bad, self.fixture["prior_result"])

    def test_nonselected_rows_still_bound(self):
        bad = copy.deepcopy(self.capture)
        bad["rows"][6]["references"][0]["fields"]["row.hotelUrl"]["value"] += "/changed"
        with self.assertRaises(ValueError):
            m.validate_prior_capture(bad, self.fixture["prior_result"])

    def test_bool_cannot_replace_numeric_original(self):
        bad = copy.deepcopy(self.capture)
        bad["rows"][0]["references"][0]["fields"]["original.tourKey"]["value"] = True
        with self.assertRaises(ValueError):
            m.validate_prior_capture(bad, self.fixture["prior_result"])

    def test_duplicate_json_keys_rejected(self):
        with self.assertRaises(ValueError):
            m.parsed(b'{"rows":[],"rows":[]}')

    def test_my_dream_two_fact_namespaces_preserved(self):
        rows = self.result()["rows"]
        same = [r for r in rows if r["catalog_id"] == "2000068203"]
        self.assertEqual([(r["source_namespace"], r["source_native_id"]) for r in same], [("operator_315", "789636"), ("operator_342", "17173")])
        self.assertEqual(len({r["catalog_id"] for r in rows}), 4)

    def test_dated_ownership_count_one_remains_dated_false_authority(self):
        rows = self.result()["rows"]
        self.assertEqual(rows[1]["dated_operator_ownership_fact"]["current_identity_count"], 1)
        self.assertIn("scoped_operator_identity_present_dated", rows[1]["holds"])
        self.assertIs(rows[1]["global_uniqueness_evaluated"], False)

    def test_numeric_path_tokens_multiple_duplicate_negative_candidate_only(self):
        p = m.path_projection("https://b2b.fstravel.com/hotel/354014/-354014/354014/0", "b2b.fstravel.com")
        self.assertEqual(p["numeric_path_tokens"], ["354014", "-354014", "354014", "0"])
        self.assertEqual(p["positive_numeric_path_candidates"], ["354014"])
        self.assertIs(p["namespace_bridge_verified"], False)
        self.assertIs(p["target_native_identity_verified"], False)

    def test_uppercase_scheme_domain_exact_value_preserved(self):
        url = "HTTPS://INTOURIST.RU/hotel/MyDream"
        p = m.path_projection(url, "intourist.ru")
        self.assertEqual(p["source_url_candidate"], url)
        self.assertEqual(p["path"], "/hotel/MyDream")

    def test_urlsplit_stripped_literal_controls_are_held_before_export(self):
        for url in ("\nhttps://intourist.ru/hotel/a", "https://intourist.ru/hotel/a\r", "https://intourist.ru/ho\ttel/a", " https://intourist.ru/hotel/a", "https://intourist.ru/hotel/a\x00", "https://intourist.ru/hotel/a\x7f"):
            p = m.path_projection(url, "intourist.ru")
            self.assertEqual(p["state"], "hold")
            self.assertEqual(p["hold_reason"], "url_path_unsafe_characters")
            self.assertIsNone(p["source_url_candidate"])

    def test_query_fragment_userinfo_port_hold_without_secret(self):
        for url in ("https://user:pw@intourist.ru/hotel/a", "https://intourist.ru:443/hotel/a", "https://intourist.ru/hotel/a?token=VERYPRIVATE", "https://intourist.ru/hotel/a#VERYPRIVATE", "https://intourist.ru/hotel/a?"):
            p = m.path_projection(url, "intourist.ru")
            self.assertEqual(p["state"], "hold")
            self.assertNotIn("VERYPRIVATE", m.enc(p).decode())
            self.assertIsNone(p["source_url_candidate"])

    def test_secret_segment_and_long_opaque_segment_hold(self):
        for path in ("/auth/value", "/hotel/" + "abcdef0123456789" * 3, "/hotel/Ab12Cd34Ef56Gh78Ij90Kl12Mn34Op56"):
            p = m.path_projection("https://intourist.ru" + path, "intourist.ru")
            self.assertEqual(p["hold_reason"], "url_path_private_or_opaque")
            self.assertIsNone(p["path"])

    def test_jwt_shaped_dotted_segment_is_private_but_property_filename_remains(self):
        for segment in ("eyJhbGciOiJIUzI1NiJ9.e30.ABCdef123456", "eyJhbGciOiJub25lIn0.eyJzdWIiOiJwcml2YXRlIn0.", "eyJhbGciOiJIUzI1NiJ9.e30.x", "eyJhbGciOiJub25lIn0.e30.", "eyJhbGciOiJub25lIn0%2ee30%2e"):
            p = m.path_projection("https://agent.anextour.ru/a/" + segment, "agent.anextour.ru")
            self.assertEqual(p["hold_reason"], "url_path_private_or_opaque")
            self.assertIsNone(p["source_url_candidate"])
            self.assertNotIn("eyJhbGci", m.enc(p).decode())
        for path in ("/hotel/mydream.html", "/hotel/123.45.678"):
            self.assertEqual(m.path_projection("https://intourist.ru" + path, "intourist.ru")["state"], "safe_absolute_operator_path_candidate")

    def test_uuid_and_base64_like_paths_hold_with_private_original_preserved(self):
        for segment in ("qwertyuiopasdfghjklzxcvbnm12345678", "AbCdEfGhIjKlMnOpQrStUvWxYzAbCdEf", "01234567-89ab-cdef-0123-456789abcdef", "01234567-8901-2345-6789-012345678901"):
            url = "https://intourist.ru/hotel/" + segment
            p = m.path_projection(url, "intourist.ru")
            self.assertEqual(p["hold_reason"], "url_path_private_or_opaque")
            self.assertIsNone(p["source_url_candidate"])
            self.assertNotIn(segment, m.enc(p).decode())

    def test_lowercase_hyphenated_property_slug_and_numeric_id_are_candidates(self):
        for path in ("/hotel/sunrise-juman-beach-resort-hurghada", "/hotel/grand-bagoz-hotel-istanbul", "/hotel/789636", "/hotel/" + "1234567890" * 4):
            p = m.path_projection("https://intourist.ru" + path, "intourist.ru")
            self.assertEqual(p["state"], "safe_absolute_operator_path_candidate")
            self.assertIs(p["namespace_bridge_verified"], False)

    def test_seaside_legitimate_slug_not_rejected_by_sid_substring(self):
        self.assertEqual(m.path_projection("https://intourist.ru/hotel/seaside", "intourist.ru")["state"], "safe_absolute_operator_path_candidate")

    def test_traversal_encoded_slash_backslash_double_encoding_hold(self):
        for path in ("/hotel/..", "/hotel/%2e%2e/a", "/hotel/%2Fa", "/hotel/%5ca", "/hotel/%252f", "/hotel/\\a", "/hotel/%00", "/hotel/%xy"):
            self.assertEqual(m.path_projection("https://intourist.ru" + path, "intourist.ru")["state"], "hold")

    def test_percent_encoded_safe_ascii_preserves_original_path(self):
        p = m.path_projection("https://intourist.ru/hotel/my%2Ddream", "intourist.ru")
        self.assertEqual(p["path"], "/hotel/my%2Ddream")
        self.assertEqual(p["decoded_path"], "/hotel/my-dream")

    def test_relative_http_other_host_unicode_or_space_hold(self):
        for url in ("/hotel/1", "http://intourist.ru/hotel/1", "https://example.com/hotel/1", "https://intourist.ru/hotel/отель", "https://intourist.ru/hotel/a b"):
            self.assertEqual(m.path_projection(url, "intourist.ru")["state"], "hold")

    def test_path_resource_caps_each_do_not_return_partial(self):
        for path in ("/hotel/" + "a" * 129, "/" + "/".join(["a"] * 21), "/" + "a" * 2048):
            p = m.path_projection("https://intourist.ru" + path, "intourist.ru")
            self.assertEqual(p["hold_reason"], "url_path_resource_cap")
            self.assertEqual(p["path_segments"], [])

    def test_one_field_hold_does_not_block_other_facts(self):
        changed = copy.deepcopy(self.capture)
        changed["rows"][0]["references"][0]["fields"]["row.hotelUrl"]["value"] = "https://agent.anextour.ru/hotel/a?token=EXTREMELYPRIVATEVALUE"
        rows = m.project_capture(changed, self.fixture)
        self.assertEqual(rows[0]["references"][0]["projection"]["state"], "hold")
        self.assertTrue(all(ref["projection"]["state"] == "safe_absolute_operator_path_candidate" for row in rows[1:] for ref in row["references"]))
        self.assertNotIn("EXTREMELYPRIVATEVALUE", m.enc(rows).decode())

    def test_closed_result_and_authority_counter_types(self):
        value = self.result()
        self.assertTrue(self.check_result(value))
        for k, v in (("mapping_writes", True), ("safe_to_write_now", True), ("metadata_files_bound", True), ("references_bound", True)):
            bad = copy.deepcopy(value)
            bad[k] = v
            with self.assertRaises(ValueError):
                self.check_result(bad)
        bad = copy.deepcopy(value)
        bad["new_field"] = "unsafe"
        with self.assertRaises(ValueError):
            self.check_result(bad)

    def test_result_cannot_substitute_safe_path_keep_old_hash(self):
        value = self.result()
        value["rows"][0]["references"][0]["projection"] = m.path_projection("https://agent.anextour.ru/hotel/999", "agent.anextour.ru")
        with self.assertRaises(ValueError):
            self.check_result(value)

    def test_result_receipt_exact_digest_types(self):
        value = self.result()
        receipt = {k: value[k] for k in m.RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(m.enc(value)).hexdigest()}
        with mock.patch.object(m, "manifest", return_value=self.fixture):
            self.assertTrue(m.validate_result(value, receipt, "a" * 40))
            receipt["accepted"] = False
            with self.assertRaises(ValueError):
                m.validate_result(value, receipt, "a" * 40)

    def test_execute_failure_durable_placeholder_exit_two_and_no_error_text(self):
        with tempfile.TemporaryDirectory() as temp:
            base = pathlib.Path(temp).resolve()
            root = base / "anytoour.ru"
            root.mkdir()
            opdir = base / ".anytoour-match" / "operations" / m.OP
            opdir.mkdir(parents=True)
            m.save(opdir / "reservation.json", {"operation": m.OP, "batch": m.BATCH, "source_sha": "a" * 40, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"})
            out = io.StringIO()
            with mock.patch.dict(os.environ, {"MATCH_SOURCE_SHA": "a" * 40}), mock.patch.object(m, "manifest", return_value=self.fixture), mock.patch.object(m, "capture_retained", side_effect=m.CaptureFailure("prior_metadata_unavailable_or_digest")), contextlib.redirect_stdout(out):
                self.assertEqual(m.execute(root, opdir, FIXTURE), 2)
            stdout = json.loads(out.getvalue())
            self.assertEqual(set(stdout), {"state", "rows_examined", "accepted", "written"})
            self.assertEqual(stdout["rows_examined"], 0)
            inp = json.loads((opdir / "current-input.json").read_bytes())
            result = json.loads((opdir / "result.json").read_bytes())
            self.assertEqual(inp["state"], "capture_failed")
            self.assertEqual(result["private_input_sha256"], hashlib.sha256((opdir / "current-input.json").read_bytes()).hexdigest())
            self.assertTrue((opdir / "receipt.json").is_file())
            with mock.patch.dict(os.environ, {"MATCH_SOURCE_SHA": "a" * 40}), mock.patch.object(m, "manifest", return_value=self.fixture):
                with self.assertRaisesRegex(ValueError, "terminal_no_replay"):
                    m.execute(root, opdir, FIXTURE)

    def test_invalid_timestamp_rejected(self):
        value = self.result()
        value["captured_at_utc"] = "2026-10-04T13:00:00.100Z"
        with self.assertRaises(ValueError):
            self.check_result(value)

    def test_execute_success_two_files_private_capture_durable(self):
        with tempfile.TemporaryDirectory() as temp:
            base = pathlib.Path(temp).resolve()
            root = base / "anytoour.ru"
            root.mkdir()
            private = base / ".anytoour-match"
            opdir = private / "operations" / m.OP
            opdir.mkdir(parents=True)
            for pin, raw in (("prior_result", self.result_bytes), ("prior_private_input", self.input_bytes)):
                path = private / self.fixture["inputs"][pin]["path"]
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(raw)
            m.save(opdir / "reservation.json", {"operation": m.OP, "batch": m.BATCH, "source_sha": "a" * 40, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"})
            out = io.StringIO()
            with mock.patch.dict(os.environ, {"MATCH_SOURCE_SHA": "a" * 40}), mock.patch.object(m, "manifest", return_value=self.fixture), contextlib.redirect_stdout(out):
                self.assertEqual(m.execute(root, opdir, FIXTURE), 0)
            result = json.loads((opdir / "result.json").read_bytes())
            receipt = json.loads((opdir / "receipt.json").read_bytes())
            with mock.patch.object(m, "manifest", return_value=self.fixture):
                self.assertTrue(m.validate_result(result, receipt, "a" * 40))
            self.assertEqual(json.loads(out.getvalue())["rows_examined"], 5)
            self.assertEqual(result["references_bound"], 8)
            self.assertEqual(result["original_raw_files_read"], 0)
            self.assertLess(len((opdir / "result.json").read_bytes()), 2097152)
            self.assertEqual((opdir / "current-input.json").stat().st_mode & 0o777, 0o600)
            self.assertEqual(receipt["private_input_sha256"], hashlib.sha256((opdir / "current-input.json").read_bytes()).hexdigest())


if __name__ == "__main__":
    unittest.main()
