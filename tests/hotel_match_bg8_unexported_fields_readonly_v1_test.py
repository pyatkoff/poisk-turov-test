import collections
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

PATH = pathlib.Path(__file__).resolve().parents[1] / "scripts/diagnostics/hotel_match_bg8_unexported_fields_readonly_v1.py"
SPEC = importlib.util.spec_from_file_location("bg8_fields", PATH)
m = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(m)
FIXTURE = PATH.with_name("fixtures") / "hotel_match_bg8_unexported_fields_readonly_v1.json"


def capture(fixture):
    rows = []
    for row in fixture["rows"]:
        refs = []
        for ref in row["raw_references"]:
            refs.append({**ref, "raw_verified": True, "failure": None, "fields": {
                "row.hotelUrl": {"present": True, "value": "http://www.bgoperator.ru/price.shtml?code=" + row["full_bg_key"]},
                "original.tourKey": {"present": True, "value": "opaque_" + row["full_bg_key"]}}})
        rows.append({"catalog_id": row["catalog_id"], "references": refs})
    return {"schema": "match-bg8-unexported-private-input/1", "operation": m.OP, "batch": m.BATCH,
            "inputs": fixture["inputs"], "projection_fields": list(m.FIELDS), "raw_files_attempted": 15,
            "raw_files_read": 15, "raw_bytes_read": 10000, "rows": rows}


class BG8FieldsTest(unittest.TestCase):
    def setUp(self):
        self.fixture = m.manifest(FIXTURE)

    def test_immutable_scope_exact_eight_eighteen_fifteen_no_v77_read(self):
        self.assertEqual(hashlib.sha256(FIXTURE.read_bytes()).hexdigest(), m.MANIFEST_SHA)
        self.assertEqual(len(self.fixture["rows"]), 8)
        refs = [r for row in self.fixture["rows"] for r in row["raw_references"]]
        self.assertEqual(len(refs), 18)
        self.assertEqual(len({r["source_file"] for r in refs}), 15)
        self.assertIs(self.fixture["inputs"]["global_v77_reread"], False)
        self.assertEqual(self.fixture["projection_fields"], ["row.hotelUrl", "original.tourKey"])
        with tempfile.TemporaryDirectory() as folder:
            p = pathlib.Path(folder) / "altered.json"
            value = copy.deepcopy(self.fixture)
            value["rows"][0]["full_bg_key"] = "102000000000"
            p.write_bytes(m.enc(value))
            with self.assertRaisesRegex(RuntimeError, "retained_digest"):
                m.manifest(p)

    def test_raw_native_binding_rejects_catalog_operator_original_and_native_drift(self):
        row = self.fixture["rows"][0]
        raw = {"hotelKey": row["catalog_id"], "operatorKey": 115, "isOperatorHotelKey": False,
               "original": {"hotelKey": row["source_native_id"], "tourKey": "opaque"}}
        self.assertTrue(m.native_binding(raw, row))
        for alter in ({"hotelKey": "99"}, {"operatorKey": 5}, {"isOperatorHotelKey": True},
                      {"original": {"hotelKey": "99"}}, {"original": {"hotelKey": row["source_native_id"], "operatorKey": 5}}):
            self.assertFalse(m.native_binding(dict(raw, **alter), row))
        self.assertEqual(m.pointer({"PRICES": [raw]}, "/PRICES/0"), raw)
        with self.assertRaisesRegex(RuntimeError, "raw_pointer"):
            m.pointer({"PRICES": [raw]}, "/OTHER/0")

    def test_http_duplicate_signed_tokens_preserved_and_never_promoted(self):
        full = self.fixture["rows"][0]["full_bg_key"]
        value = "http://www.bgoperator.ru/price.shtml?code=" + full + ",102000000001&F4=-" + full + "&code=%2B" + full
        f = m.field_projection("row.hotelUrl", {"present": True, "value": value}, full)
        self.assertEqual(f["url_scheme"], "http")
        self.assertEqual(f["bg_host"], "www.bgoperator.ru")
        self.assertEqual(f["raw_selector_tokens"], [full, "102000000001", "-" + full, "+" + full])
        self.assertEqual(f["positive_selector_candidates"], [full, "102000000001"])
        self.assertEqual(f["raw_selector_parameters"], ["code", "f4", "code"])
        self.assertTrue(f["exact_expected_full_code_observed"])
        self.assertFalse(f["namespace_bridge_verified"])

    def test_relative_url_and_opaque_tour_key_never_invent_origin_or_substring_proof(self):
        full = self.fixture["rows"][0]["full_bg_key"]
        for value in ("/price.shtml?code=" + full, "//www.bgoperator.ru/price.shtml?code=" + full, "?F4=" + full):
            f = m.field_projection("row.hotelUrl", {"present": True, "value": value}, full)
            self.assertEqual(f["origin_state"], "relative_origin_unknown")
            self.assertIsNone(f["bg_host"])
            self.assertEqual(f["raw_selector_tokens"], [full])
            self.assertTrue(f["exact_expected_full_code_observed"])
            self.assertFalse(f["namespace_bridge_verified"])
        for value in (full, "prefix_" + full + "_suffix", int(full), {"code": full}):
            f = m.field_projection("original.tourKey", {"present": True, "value": value}, full)
            self.assertFalse(f["exact_expected_full_code_observed"])
            self.assertEqual(f["raw_selector_tokens"], [])

    def test_url_credentials_secrets_unknown_host_and_opaque_selectors_redacted(self):
        full = self.fixture["rows"][0]["full_bg_key"]
        for value in ("https://name:private@bgoperator.ru/?code=" + full,
                      "https://bgoperator.ru/?code=" + full + "&access_token=private-value",
                      "https://bgoperator.ru/%74oken/private-value?code=" + full,
                      "https://bgoperator.ru.evil.example/?code=" + full,
                      "https://bgoperator.ru/?F4=private-value", "https://bgoperator.ru:444/?code=" + full):
            f = m.field_projection("row.hotelUrl", {"present": True, "value": value}, full)
            self.assertEqual(f["raw_selector_tokens"], [])
            self.assertNotIn("private-value", json.dumps(f))
            self.assertNotIn("https://", json.dumps(f))

    def test_mixed_opaque_selector_preserves_safe_tokens_positions_hash_and_ambiguity(self):
        value = capture(self.fixture)
        full = self.fixture["rows"][0]["full_bg_key"]
        value["rows"][0]["references"][0]["fields"]["row.hotelUrl"]["value"] = "/price.shtml?code=" + full + ",private-value,-" + full + "&F4=&code=%2B" + full
        _, result, _, _ = self._execute(value)
        f = result["rows"][0]["references"][0]["fields"][0]
        self.assertEqual(f["raw_selector_tokens"], [full, "-" + full, "+" + full])
        self.assertEqual(f["selector_token_positions"], [[0, 0], [0, 2], [2, 0]])
        self.assertEqual(f["opaque_selector_token_counts"], [1, 1, 0])
        self.assertEqual(f["raw_selector_values"], [None, None, "+" + full])
        self.assertEqual(f["origin_state"], "relative_origin_unknown")
        self.assertFalse(f["namespace_bridge_verified"])
        self.assertNotIn("private-value", json.dumps(result))

    def test_safe_pinned_file_rejects_drift_and_symlink(self):
        with tempfile.TemporaryDirectory() as folder:
            p = pathlib.Path(folder) / "input.json"
            p.write_bytes(m.enc({"value": 1}))
            digest = hashlib.sha256(p.read_bytes()).hexdigest()
            self.assertEqual(m.read_pinned(p, digest, 1000), {"value": 1})
            with self.assertRaisesRegex(RuntimeError, "retained_digest"):
                m.read_pinned(p, "0" * 64, 1000)
            link = p.with_name("link.json")
            link.symlink_to(p)
            with self.assertRaisesRegex(RuntimeError, "retained_file_unavailable"):
                m.read_pinned(link, digest, 1000)
            with self.assertRaisesRegex(RuntimeError, "retained_file_unavailable"):
                m.read_pinned(p, digest, 1)

    def _execute(self, value=None, failure=None):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        base = pathlib.Path(temporary.name)
        root = base / "anytoour.ru"
        root.mkdir()
        opdir = base / ".anytoour-match" / "operations" / m.OP
        opdir.mkdir(parents=True)
        m.save(opdir / "reservation.json", {"operation": m.OP, "batch": m.BATCH, "source_sha": "b" * 40,
                                           "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"})
        stdout = io.StringIO()
        kwargs = {"side_effect": failure} if failure else {"return_value": value or capture(self.fixture)}
        with mock.patch.dict(os.environ, MATCH_SOURCE_SHA="b" * 40), mock.patch.object(m, "capture_original", **kwargs) as call, contextlib.redirect_stdout(stdout):
            code = m.execute(root, opdir, FIXTURE)
        self.assertEqual(call.call_count, 1)
        result = json.loads((opdir / "result.json").read_bytes())
        receipt = json.loads((opdir / "receipt.json").read_bytes())
        m.validate_result(result, receipt, "b" * 40)
        self.assertEqual(set(json.loads(stdout.getvalue())), {"state", "rows_examined", "accepted", "written"})
        self.assertEqual(hashlib.sha256((opdir / "current-input.json").read_bytes()).hexdigest(), result["private_input_sha256"])
        for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):
            self.assertEqual((opdir / name).stat().st_mode & 0o777, 0o600)
        with mock.patch.dict(os.environ, MATCH_SOURCE_SHA="b" * 40), mock.patch.object(m, "capture_original") as call:
            with self.assertRaisesRegex(RuntimeError, "terminal_no_replay"):
                m.execute(root, opdir, FIXTURE)
            call.assert_not_called()
        return code, result, receipt, opdir

    def test_durable_private_fields_public_candidate_only_effects_zero_and_no_replay(self):
        value = capture(self.fixture)
        value["rows"][0]["references"][0]["fields"]["original.tourKey"]["value"] = "private-opaque-tourkey"
        code, result, receipt, opdir = self._execute(value)
        self.assertEqual(code, 0)
        self.assertEqual(result["rows_examined"], 8)
        self.assertEqual(result["raw_references_verified"], 18)
        self.assertEqual(result["selector_candidate_rows"], 8)
        self.assertNotIn("private-opaque-tourkey", json.dumps(result))
        self.assertIn("private-opaque-tourkey", (opdir / "current-input.json").read_text())
        self.assertEqual(set(receipt), {*m.RECEIPT_KEYS, "result_sha256"})
        for k in m.NO_EFFECTS:
            self.assertEqual(result[k], 0)
        for row in result["rows"]:
            self.assertFalse(row["source_namespace_bridge_verified"])
            self.assertIsNone(row["independent_anytour_local_id"])
            self.assertTrue(set(m.BASE_HOLDS).issubset(row["holds"]))

    def test_missing_original_one_row_preserved_incomplete_and_others_continue(self):
        value = capture(self.fixture)
        for ref in value["rows"][0]["references"]:
            ref.update(raw_verified=False, failure="raw_file_unavailable_or_changed", fields={})
        code, result, _, _ = self._execute(value)
        self.assertEqual(code, 0)
        self.assertEqual(result["state"], "completed_read_only_bg8_fields_incomplete")
        self.assertEqual(result["rows_examined"], 8)
        self.assertEqual(result["raw_references_verified"], 15)
        self.assertEqual(result["selector_candidate_rows"], 7)
        self.assertIn("raw_file_unavailable_or_changed", result["rows"][0]["holds"])
        self.assertNotIn("raw_file_unavailable_or_changed", result["rows"][1]["holds"])

    def test_relative_tokens_and_missing_field_kept_under_independent_holds(self):
        value = capture(self.fixture)
        fields = value["rows"][0]["references"][0]["fields"]
        full = self.fixture["rows"][0]["full_bg_key"]
        fields["row.hotelUrl"]["value"] = "/price.shtml?code=" + full + "&F4=-" + full + "&code=" + full
        fields["original.tourKey"] = {"present": False, "value": None}
        code, result, _, _ = self._execute(value)
        self.assertEqual(code, 0)
        row = result["rows"][0]
        url = row["references"][0]["fields"][0]
        self.assertEqual(url["raw_selector_parameters"], ["code", "f4", "code"])
        self.assertEqual(url["raw_selector_tokens"], [full, "-" + full, full])
        self.assertEqual(url["origin_state"], "relative_origin_unknown")
        self.assertIsNone(url["bg_host"])
        self.assertIn("unexported_field_missing", row["holds"])
        self.assertFalse(row["source_namespace_bridge_verified"])

    def test_failed_capture_constant_terminal_placeholder_with_no_secret_exception(self):
        code, result, _, opdir = self._execute(failure=RuntimeError("password=private-value"))
        self.assertEqual(code, 2)
        self.assertEqual(result["state"], "terminal_failed_no_replay")
        self.assertEqual(result["rows_examined"], 0)
        self.assertEqual(json.loads((opdir / "current-input.json").read_bytes())["state"], "capture_failed")
        self.assertNotIn("private-value", (opdir / "result.json").read_text())

    def test_strict_validator_rejects_authority_origin_tokens_and_receipt_drift(self):
        _, result, receipt, _ = self._execute()
        mutations = [lambda d: d.update(database_reads=True), lambda d: d["rows"][0].update(source_namespace_bridge_verified=True),
                     lambda d: d["rows"][0].update(independent_anytour_local_id=9283),
                     lambda d: d["rows"][0]["references"][0]["fields"][0].update(origin_state="relative_origin_unknown"),
                     lambda d: d["rows"][0]["references"][0]["fields"][1].update(raw_selector_values=["102625414997"], raw_selector_tokens=["102625414997"], positive_selector_candidates=["102625414997"], exact_expected_full_code_observed=True)]
        for mutation in mutations:
            altered = copy.deepcopy(result)
            mutation(altered)
            with self.assertRaises(RuntimeError):
                m.validate_result(altered)
        wrong = dict(receipt, result_sha256="0" * 64)
        with self.assertRaisesRegex(RuntimeError, "public_receipt_binding"):
            m.validate_result(result, wrong)
        with self.assertRaisesRegex(RuntimeError, "public_receipt_binding"):
            m.validate_result(result, dict(receipt, accepted=False))
        for counts in ({"raw_files_attempted": 0, "raw_files_read": 0, "raw_bytes_read": 0}, {"raw_files_read": 14}):
            with self.assertRaisesRegex(RuntimeError, "public_capture_count_binding"):
                m.validate_result(dict(result, **counts))

    def test_actual_raw_reader_exact_refs_deduplicated_and_identity_hold_independent(self):
        fixture = copy.deepcopy(self.fixture)
        with tempfile.TemporaryDirectory() as folder:
            base = pathlib.Path(folder) / ".anytoour-match"
            base.mkdir()
            pages = {}
            for row in fixture["rows"]:
                for ref in row["raw_references"]:
                    page = pages.setdefault(ref["source_file"], {"PRICES": []})
                    index = int(ref["json_pointer"].split("/")[-1])
                    while len(page["PRICES"]) <= index:
                        page["PRICES"].append({})
                    page["PRICES"][index] = {"hotelKey": row["catalog_id"], "operatorKey": 115, "original": {"hotelKey": row["source_native_id"], "tourKey": "opaque"}, "hotelUrl": "http://bgoperator.ru/?code=" + row["full_bg_key"]}
            first = fixture["rows"][0]["raw_references"][0]
            pages[first["source_file"]]["PRICES"][0]["original"]["hotelKey"] = "99"
            for row in fixture["rows"]:
                for ref in row["raw_references"]:
                    path = base / ref["source_file"]
                    path.parent.mkdir(parents=True, exist_ok=True)
                    body = m.enc(pages[ref["source_file"]])
                    path.write_bytes(body)
                    ref["sha256"] = hashlib.sha256(body).hexdigest()
            current = {"operation": "int-andromeda-match-native-current-20261001-v1", "batch": "native110-20260928", "provider_http_calls": 0, "database_writes": 0, "mapping_writes": 0, "saved_evidence": {"source_facts": {}}}
            bg = {"operation": "int-andromeda-match-native110-bg-evidence-20261001-v1", "state": "completed_bg_original_fields_review", "input_sha256": fixture["inputs"]["native_current"]["sha256"], "provider_http_calls": 0, "database_writes": 0, "mapping_writes": 0, "rows": [{} for _ in range(10)]}
            for row in fixture["rows"]:
                current["saved_evidence"]["source_facts"][row["catalog_id"]] = [{"namespace": "operator_115", "native_id": row["source_native_id"], "raw": {"raw_verified": True, "failures": [], "references": [{**ref, "verified": True} for ref in row["raw_references"]]}}]
                bg["rows"].append({"catalog_id": row["catalog_id"], "tv_hotel_id": row["target_tv_hotel_id"], "samo_native_id": row["source_native_id"], "tv_native_id": row["full_bg_key"], "raw_references_examined": len(row["raw_references"]), "failures": [], "safe_to_write_now": False, "original_fields": ["tourKey"], "top_fields": ["hotelUrl"]})
            with mock.patch.object(m, "read_pinned", side_effect=[current, bg]) as calls:
                value = m.capture_original(base, fixture)
            self.assertEqual(calls.call_count, 2)
            self.assertEqual(value["raw_files_attempted"], 15)
            self.assertEqual(value["raw_files_read"], 15)
            self.assertLessEqual(value["raw_bytes_read"], 847514)
            self.assertFalse(value["rows"][0]["references"][0]["raw_verified"])
            self.assertTrue(value["rows"][1]["references"][0]["raw_verified"])
            self.assertEqual(value["rows"][0]["references"][0]["failure"], "raw_pointer_or_identity_changed")

    def test_no_provider_db_or_http_capability_import(self):
        source = PATH.read_text()
        self.assertNotRegex(source, r"(?m)^import (?:subprocess|requests|socket|sqlite3|pymysql)|^from (?:urllib.request|http.client|requests)")
        self.assertNotIn("urlopen(", source)
        self.assertNotIn("subprocess.run(", source)
        self.assertNotIn("v2_data_db(", source)


if __name__ == "__main__":
    unittest.main()
