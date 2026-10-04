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

PATH = pathlib.Path(__file__).resolve().parents[1] / "scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py"
SPEC = importlib.util.spec_from_file_location("nonbg7_fields", PATH)
m = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(m)
FIXTURE = PATH.with_name("fixtures") / "hotel_match_nonbg7_unexported_fields_readonly_v1.json"


def url(row):
    return "https://" + m.RULES[row["source_namespace"]] + "/search?HOTEL=" + row["source_native_id"]


def capture(fixture):
    return {"schema": "match-nonbg7-unexported-private-input/1", "operation": m.OP, "batch": m.BATCH,
            "inputs": fixture["inputs"], "projection_fields": list(m.FIELDS), "raw_files_attempted": 11,
            "raw_files_read": 11, "raw_bytes_read": 20000, "rows": [
                {**{k: row[k] for k in m.ROW_KEYS}, "references": [
                    {**ref, "raw_verified": True, "failure": None, "fields": {
                        "row.hotelUrl": {"present": True, "value": url(row)},
                        "original.tourKey": {"present": True, "value": 12345}}}
                    for ref in row["raw_references"]]} for row in fixture["rows"]]}


class NonBG7FieldsTest(unittest.TestCase):
    def setUp(self):
        self.fixture = m.manifest(FIXTURE)

    def test_immutable_scope_seven_facts_six_sources_twelve_refs_eleven_files(self):
        self.assertEqual(hashlib.sha256(FIXTURE.read_bytes()).hexdigest(), m.MANIFEST_SHA)
        self.assertEqual(len(self.fixture["rows"]), 7)
        self.assertEqual(len({r["catalog_id"] for r in self.fixture["rows"]}), 6)
        self.assertEqual(set(self.fixture["inputs"]), {"native_current", "global_v77_reread"})
        self.assertIs(self.fixture["inputs"]["global_v77_reread"], False)
        refs = [ref for row in self.fixture["rows"] for ref in row["raw_references"]]
        self.assertEqual(len(refs), 12)
        self.assertEqual(len({ref["source_file"] for ref in refs}), 11)
        self.assertEqual([r["source_namespace"] for r in self.fixture["rows"] if r["catalog_id"] == "2000068203"], ["operator_315", "operator_342"])
        with tempfile.TemporaryDirectory() as folder:
            altered = pathlib.Path(folder) / "altered.json"
            value = copy.deepcopy(self.fixture)
            value["rows"][0]["source_native_id"] = "804"
            altered.write_bytes(m.enc(value))
            with self.assertRaisesRegex(RuntimeError, "retained_digest"):
                m.manifest(altered)

    def test_native_binding_checks_each_operator_catalog_original_and_native(self):
        for row in self.fixture["rows"]:
            operator = int(row["source_namespace"].split("_")[1])
            raw = {"hotelKey": row["catalog_id"], "operatorKey": operator,
                   "original": {"hotelKey": row["source_native_id"], "operatorKey": operator}}
            self.assertTrue(m.native_binding(raw, row))
            for change in ({"hotelKey": "99"}, {"operatorKey": 115}, {"isOperatorHotelKey": True},
                           {"original": {"hotelKey": "99"}},
                           {"original": {"hotelKey": row["source_native_id"], "operatorKey": 115}}):
                self.assertFalse(m.native_binding(dict(raw, **change), row))
        with self.assertRaisesRegex(RuntimeError, "raw_pointer"):
            m.pointer({"PRICES": []}, "/OTHER/0")

    def test_duplicate_signed_mixed_opaque_selectors_preserve_positions_not_proof(self):
        f = m.field_projection("row.hotelUrl", {"present": True, "value":
            "https://agent.anextour.ru/?HOTELLIST=804,44562,private-text&HOTELLIST=-44562&HOTEL=%2B44562&HOTEL="}, "44562", "operator_5")
        self.assertEqual(f["raw_selector_tokens"], ["804", "44562", "-44562", "+44562"])
        self.assertEqual(f["positive_selector_candidates"], ["804", "44562"])
        self.assertEqual(f["selector_token_positions"], [[0, 0], [0, 1], [1, 0], [2, 0]])
        self.assertEqual(f["opaque_selector_token_counts"], [1, 0, 0, 1])
        self.assertEqual(f["raw_selector_values"], [None, "-44562", "+44562", None])
        self.assertEqual(f["raw_selector_parameters"], ["hotellist", "hotellist", "hotel", "hotel"])
        self.assertTrue(f["exact_source_native_candidate_observed"])
        self.assertFalse(f["namespace_bridge_verified"])
        self.assertNotIn("private-text", json.dumps(f))

    def test_relative_or_opaque_does_not_invent_origin_or_tour_key_substring(self):
        for value in ("/search?HOTELS=354014", "//fstravel.com/search?HOTELS=354014", "?HOTEL=354014"):
            f = m.field_projection("row.hotelUrl", {"present": True, "value": value}, "354014", "operator_315")
            self.assertEqual(f["origin_state"], "relative_origin_unknown")
            self.assertIsNone(f["operator_host"])
            self.assertEqual(f["raw_selector_tokens"], ["354014"])
            self.assertFalse(f["namespace_bridge_verified"])
        for value in ("354014", 354014, "prefix_354014", {"HOTEL": "354014"}, None, False, [354014], "ftp://fstravel.com/?HOTEL=354014"):
            f = m.field_projection("original.tourKey", {"present": True, "value": value}, "354014", "operator_315")
            self.assertEqual(f["raw_selector_tokens"], [])
            self.assertFalse(f["exact_source_native_candidate_observed"])

    def test_secrets_unknown_host_cross_operator_and_credentials_are_redacted(self):
        for value in ("https://user:private-value@intourist.ru/?HOTEL=25728",
                      "https://intourist.ru/?HOTEL=25728&access_token=private-value",
                      "https://intourist.ru/%74oken/private-value?HOTEL=25728",
                      "https://intourist.ru:444/?HOTEL=25728", "https://intourist.ru.evil.example/?HOTEL=25728",
                      "https://fstravel.com/?HOTEL=25728", "https://intourist.ru/?HOTEL=private-value"):
            f = m.field_projection("row.hotelUrl", {"present": True, "value": value}, "25728", "operator_342")
            self.assertEqual(f["raw_selector_tokens"], [])
            self.assertNotIn("private-value", json.dumps(f))
            self.assertFalse(f["namespace_bridge_verified"])

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

    def test_durable_capture_keeps_mydream_two_facts_and_dated_ownership_separate(self):
        value = capture(self.fixture)
        value["rows"][0]["references"][0]["fields"]["original.tourKey"]["value"] = "private-opaque-tourkey"
        code, result, receipt, opdir = self._execute(value)
        self.assertEqual(code, 0)
        self.assertEqual((result["rows_examined"], result["distinct_source_count"], result["raw_references_verified"]), (7, 6, 12))
        self.assertEqual(result["selector_candidate_rows"], 7)
        self.assertEqual([row["source_namespace"] for row in result["rows"] if row["catalog_id"] == "2000068203"], ["operator_315", "operator_342"])
        self.assertNotIn("private-opaque-tourkey", json.dumps(result))
        self.assertIn("private-opaque-tourkey", (opdir / "current-input.json").read_text())
        self.assertEqual(set(receipt), {*m.RECEIPT_KEYS, "result_sha256"})
        self.assertEqual(len(receipt), 19)
        for k in m.NO_EFFECTS:
            self.assertEqual(result[k], 0)
        for row in result["rows"]:
            self.assertFalse(row["source_namespace_bridge_verified"])
            self.assertIsNone(row["independent_anytour_local_id"])
            self.assertTrue(set(m.BASE_HOLDS).issubset(row["holds"]))
            self.assertEqual("scoped_operator_identity_present_dated" in row["holds"], row["dated_operator_ownership_fact"]["current_identity_count"] > 0)

    def test_reference_hold_and_missing_field_do_not_block_other_facts(self):
        value = capture(self.fixture)
        value["rows"][0]["references"][0].update(raw_verified=False, failure="raw_pointer_or_identity_changed", fields={})
        value["rows"][1]["references"][0]["fields"]["row.hotelUrl"] = {"present": False, "value": None}
        code, result, _, _ = self._execute(value)
        self.assertEqual(code, 0)
        self.assertEqual(result["state"], "completed_read_only_nonbg7_fields_incomplete")
        self.assertEqual(result["raw_references_verified"], 11)
        self.assertIn("raw_pointer_or_identity_changed", result["rows"][0]["holds"])
        self.assertIn("unexported_field_missing", result["rows"][1]["holds"])
        self.assertTrue(all("raw_pointer_or_identity_changed" not in row["holds"] for row in result["rows"][1:]))

    def test_failure_private_placeholder_no_secret_and_exit_two(self):
        code, result, _, opdir = self._execute(failure=m.CaptureFailure("native_manifest_unavailable_or_digest"))
        self.assertEqual(code, 2)
        self.assertEqual(result["distinct_source_count"], 0)
        self.assertEqual(json.loads((opdir / "current-input.json").read_bytes())["state"], "capture_failed")
        code, result, _, opdir = self._execute(failure=RuntimeError("password=private-value"))
        self.assertEqual(code, 2)
        self.assertEqual(result["failure_stage"], "private_capture_or_projection_failed")
        self.assertNotIn("private-value", (opdir / "result.json").read_text())

    def test_strict_validator_rejects_promoted_or_collapsed_and_type_drift(self):
        _, result, receipt, _ = self._execute()
        mutations = [lambda d: d.update(database_reads=True), lambda d: d.update(distinct_source_count=7),
            lambda d: d.update(requested_sources=6.0), lambda d: d["rows"][0].update(target_tv_hotel_id=159.0),
            lambda d: d["rows"][0].update(source_namespace_bridge_verified=True),
            lambda d: d["rows"][3].update(source_namespace="operator_342"),
            lambda d: d["rows"][1]["dated_operator_ownership_fact"].update(current_identity_count=0),
            lambda d: d["rows"][1]["holds"].remove("scoped_operator_identity_present_dated"),
            lambda d: d["rows"][0]["references"][0]["fields"][0].update(origin_state="relative_origin_unknown"),
            lambda d: d["rows"][0]["references"][0]["fields"][0].update(value_type="number"),
            lambda d: d["rows"][0]["references"][0]["fields"][0].update(representation="opaque_or_non_url"),
            lambda d: d.update(raw_bytes_read=1)]
        for mutation in mutations:
            altered = copy.deepcopy(result)
            mutation(altered)
            with self.assertRaises(RuntimeError):
                m.validate_result(altered)
        for change in ({"result_sha256": "0" * 64}, {"accepted": False}, {"extra": 1}):
            with self.assertRaisesRegex(RuntimeError, "public_receipt_binding"):
                m.validate_result(result, dict(receipt, **change))

    def test_failed_read_counts_and_public_validation_failure_exit_are_bound(self):
        _, failed, _, _ = self._execute(failure=RuntimeError("private"))
        with self.assertRaisesRegex(RuntimeError, "public_read_count"):
            m.validate_result(dict(failed, raw_bytes_read=1))
        value = capture(self.fixture)
        value["rows"][0]["references"][0]["fields"]["row.hotelUrl"]["present"] = 1
        code, result, _, _ = self._execute(value)
        self.assertEqual(code, 2)
        self.assertEqual(result["state"], "terminal_failed_no_replay")
        self.assertEqual(result["failure_stage"], "public_result_validation_failed")
        self.assertEqual(result["distinct_source_count"], 0)

    def test_long_numeric_selector_redacts_duplicate_literal_preserving_all_tokens(self):
        value = capture(self.fixture)
        native = self.fixture["rows"][0]["source_native_id"]
        joined = ",".join([native] * 600)
        value["rows"][0]["references"][0]["fields"]["row.hotelUrl"]["value"] = "https://anextour.ru/?HOTELLIST=" + joined
        code, result, _, _ = self._execute(value)
        self.assertEqual(code, 0)
        field = result["rows"][0]["references"][0]["fields"][0]
        self.assertEqual(field["raw_selector_tokens"], [native] * 600)
        self.assertEqual(field["raw_selector_values"], [None])
        self.assertEqual(field["opaque_selector_token_counts"], [0])
        self.assertEqual(field["selector_value_sha256"], [hashlib.sha256(joined.encode()).hexdigest()])
        self.assertEqual(field["selector_token_positions"], [[0, i] for i in range(600)])
        self.assertIsNone(field["projection_hold"])
        self.assertFalse(field["namespace_bridge_verified"])
        altered = copy.deepcopy(result)
        altered["rows"][0]["references"][0]["fields"][0]["selector_value_sha256"][0] = "0" * 64
        with self.assertRaises(RuntimeError):
            m.validate_result(altered)

    def test_too_many_tokens_is_explicit_field_hold_other_rows_continue(self):
        value = capture(self.fixture)
        native = self.fixture["rows"][0]["source_native_id"]
        full = "https://anextour.ru/?HOTELLIST=" + ",".join([native] * 2200)
        value["rows"][0]["references"][0]["fields"]["row.hotelUrl"]["value"] = full
        code, result, _, opdir = self._execute(value)
        self.assertEqual(code, 0)
        self.assertEqual(result["raw_references_verified"], 12)
        self.assertEqual(result["selector_candidate_rows"], 6)
        field = result["rows"][0]["references"][0]["fields"][0]
        self.assertEqual(field["projection_hold"], "field_selector_resource_cap")
        self.assertEqual(field["raw_selector_tokens"], [])
        self.assertFalse(field["exact_source_native_candidate_observed"])
        self.assertIn("field_selector_resource_cap", result["rows"][0]["holds"])
        self.assertTrue(all("field_selector_resource_cap" not in row["holds"] for row in result["rows"][1:]))
        self.assertIn(full, (opdir / "current-input.json").read_text())
        field = m.field_projection("row.hotelUrl", {"present": True, "value": "https://anextour.ru/?HOTEL=" + "1" * 17000}, native, "operator_5")
        self.assertEqual(field["projection_hold"], "field_selector_resource_cap")

    def test_numeric_original_tour_key_cannot_claim_url_selector_candidate(self):
        _, result, _, _ = self._execute()
        field = m.field_projection("original.tourKey", {"present": True, "value": "https://agent.anextour.ru/?HOTELLIST=44562"}, "44562", "operator_5")
        field.update(value_type="number", value_sha256=hashlib.sha256(m.enc(44562)).hexdigest(), value_bytes=len(m.enc(44562)))
        result["rows"][0]["references"][0]["fields"][1] = field
        with self.assertRaisesRegex(RuntimeError, "public_url_type"):
            m.validate_result(result)

    def test_opaque_original_tour_key_cannot_claim_redacted_selector_vector(self):
        _, result, _, _ = self._execute()
        field = result["rows"][0]["references"][0]["fields"][1]
        field.update(raw_selector_parameters=["hotel"], raw_selector_values=[None],
                     selector_value_sha256=[hashlib.sha256(b"private-text").hexdigest()], opaque_selector_token_counts=[1])
        with self.assertRaisesRegex(RuntimeError, "public_opaque_identity"):
            m.validate_result(result)

    def test_all_fields_near_cap_keep_complete_private_input_and_bound_public_envelope(self):
        value = capture(self.fixture)
        token = "+12345678901234567890"
        total_fields = 0
        for row in value["rows"]:
            host = m.RULES[row["source_namespace"]]
            for ref in row["references"]:
                for field in ref["fields"].values():
                    field["value"] = "https://" + host + "/search?HOTEL=" + ",".join(["%2B" + token[1:]] * 650)
                    total_fields += 1
        self.assertEqual(total_fields, 24)
        code, result, receipt, opdir = self._execute(value)
        self.assertEqual(code, 0)
        self.assertEqual(result["raw_references_verified"], 12)
        self.assertLessEqual(len(m.enc(result)), 2097152)
        m.validate_result(result, receipt, "b" * 40)
        fields = [f for row in result["rows"] for ref in row["references"] for f in ref["fields"]]
        self.assertTrue(any(f["projection_hold"] == "field_selector_resource_cap" for f in fields))
        self.assertTrue(any(f["raw_selector_tokens"] == [token] * 650 for f in fields))
        stored = json.loads((opdir / "current-input.json").read_bytes())
        self.assertEqual(stored, value)
        for f in fields:
            self.assertFalse(f["namespace_bridge_verified"])
            if f["projection_hold"]:
                self.assertEqual(f["raw_selector_tokens"], [])
                self.assertEqual(f["positive_selector_candidates"], [])
        self.assertTrue(all(row["safe_to_write_now"] is False for row in result["rows"]))

    def test_existing_delta_hotelinc_alias_only_for_funsun_and_intourist(self):
        for namespace, native in (("operator_315", "354014"), ("operator_342", "25728")):
            field = m.field_projection("row.hotelUrl", {"present": True, "value": "https://" + m.RULES[namespace] + "/search?HOTELINC=" + native}, native, namespace)
            self.assertEqual(field["raw_selector_tokens"], [native])
            self.assertFalse(field["namespace_bridge_verified"])
        field = m.field_projection("row.hotelUrl", {"present": True, "value": "https://anextour.ru/?HOTELINC=44562"}, "44562", "operator_5")
        self.assertEqual(field["raw_selector_tokens"], [])

    def test_near_full_projection_budget_reserves_all_remaining_hold_descriptors(self):
        value = capture(self.fixture)
        native = self.fixture["rows"][0]["source_native_id"]
        descriptor = m.field_projection("row.hotelUrl", value["rows"][0]["references"][0]["fields"]["row.hotelUrl"], native, "operator_5")
        one_weight = m.public_projection_weight(descriptor)
        with mock.patch.object(m, "MAX_PROJECTION_PUBLIC_WEIGHT", one_weight):
            code, result, receipt, _ = self._execute(value)
            self.assertEqual(code, 0)
            m.validate_result(result, receipt, "b" * 40)
            fields = [f for row in result["rows"] for ref in row["references"] for f in ref["fields"]]
            self.assertEqual(sum(f["projection_hold"] is None for f in fields), 1)
            self.assertEqual(sum(f["projection_hold"] == "field_selector_resource_cap" for f in fields), 23)
            self.assertLess(len(m.enc(result)), 2097152)

    def test_uppercase_url_scheme_and_domain_preserve_query_tokens_case(self):
        field = m.field_projection("row.hotelUrl", {"present": True, "value": "HTTP://B2B.INTOURIST.RU/search?HOTEL=25728&HOTEL=-25728"}, "25728", "operator_342")
        self.assertEqual(field["operator_host"], "b2b.intourist.ru")
        self.assertEqual(field["url_scheme"], "http")
        self.assertEqual(field["raw_selector_tokens"], ["25728", "-25728"])
        self.assertFalse(field["namespace_bridge_verified"])

    def _real_metadata_and_pages(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        base = pathlib.Path(temporary.name) / ".anytoour-match"
        base.mkdir()
        fixture = copy.deepcopy(self.fixture)
        pages = {}
        for row in fixture["rows"]:
            for ref in row["raw_references"]:
                page = pages.setdefault(ref["source_file"], {"PRICES": []})
                index = int(ref["json_pointer"].split("/")[-1])
                while len(page["PRICES"]) <= index:
                    page["PRICES"].append({"original": {"tourKey": "UNSELECTED_OLD_ROW_PRIVATE"}})
                page["PRICES"][index] = {"hotelKey": row["catalog_id"], "operatorKey": int(row["source_namespace"].split("_")[1]),
                    "original": {"hotelKey": row["source_native_id"], "tourKey": 12345}, "hotelUrl": url(row)}
        def write(rel, value):
            path = base / rel
            path.parent.mkdir(parents=True, exist_ok=True)
            raw = m.enc(value)
            path.write_bytes(raw)
            return hashlib.sha256(raw).hexdigest(), len(raw)
        for row in fixture["rows"]:
            for ref in row["raw_references"]:
                ref["sha256"], _ = write(ref["source_file"], pages[ref["source_file"]])
        n = fixture["inputs"]["native_current"]
        current = {k: n[k] for k in ("operation", "batch", "schema", "source_sha")} | {
            "provider_http_calls": 0, "database_writes": 0, "mapping_writes": 0,
            "saved_evidence": {"source_facts": {}}, "review_rows": [], "safe_to_write_now": False, "no_replay": True}
        reviews = {}
        for row in fixture["rows"]:
            current["saved_evidence"]["source_facts"].setdefault(row["catalog_id"], []).append({
                "namespace": row["source_namespace"], "native_id": row["source_native_id"], "unique_catalog_in_saved_union": True,
                "raw": {"raw_verified": True, "failures": [], "references": [{**ref, "verified": True} for ref in row["raw_references"]]}})
            review = reviews.setdefault(row["catalog_id"], {"catalog_id": row["catalog_id"], "holds": [], "safe_to_write_now": False,
                "catalog_digest_matches_saved": True, "evidence_digest_matches_saved": True, "source_history_id_matches": True,
                "native_checks": [], "operator_checks": []})
            review["native_checks"].append(copy.deepcopy(row["dated_original_fact_check"]))
            review["operator_checks"].append(copy.deepcopy(row["dated_operator_ownership_fact"]))
        current["review_rows"] = list(reviews.values())
        n["sha256"], n["bytes"] = write(n["path"], current)
        return base, fixture, pages, current, write

    def test_actual_capture_one_metadata_eleven_pages_twelve_ptrs_no_unselected_values(self):
        base, fixture, _, _, _ = self._real_metadata_and_pages()
        opened = []
        real = m.file_bytes
        def observed(path, cap):
            opened.append(path)
            return real(path, cap)
        with mock.patch.object(m, "file_bytes", side_effect=observed):
            value = m.capture_original(base, fixture)
        self.assertEqual((len(opened), len(set(opened))), (12, 12))
        self.assertEqual((value["raw_files_read"], value["raw_files_attempted"]), (11, 11))
        self.assertEqual(sum(len(r["references"]) for r in value["rows"]), 12)
        self.assertTrue(all(ref["raw_verified"] for row in value["rows"] for ref in row["references"]))
        self.assertEqual([r["source_native_id"] for r in value["rows"] if r["catalog_id"] == "2000068203"], ["789636", "17173"])
        self.assertNotIn("UNSELECTED_OLD_ROW_PRIVATE", m.enc(value).decode())

    def test_actual_wrong_metadata_digest_stops_before_any_original_page(self):
        base, fixture, _, _, _ = self._real_metadata_and_pages()
        fixture["inputs"]["native_current"]["sha256"] = "0" * 64
        real = m.file_bytes
        with mock.patch.object(m, "file_bytes", wraps=real) as opened, self.assertRaises(m.CaptureFailure) as caught:
            m.capture_original(base, fixture)
        self.assertEqual(caught.exception.stage, "native_manifest_unavailable_or_digest")
        self.assertEqual(opened.call_count, 1)
        self.assertNotIn("evidence-private", str(opened.call_args[0][0]))

    def test_actual_dated_ownership_and_verified_reference_drift_stop_before_raw(self):
        for kind in ("ownership", "ref"):
            base, fixture, _, current, write = self._real_metadata_and_pages()
            if kind == "ownership":
                current["review_rows"][1]["operator_checks"][0]["current_identity_count"] = 2
            else:
                current["saved_evidence"]["source_facts"]["2000068203"][1]["raw"]["references"][0]["verified"] = False
            n = fixture["inputs"]["native_current"]
            n["sha256"], n["bytes"] = write(n["path"], current)
            with mock.patch.object(m, "file_bytes", wraps=m.file_bytes) as opened, self.assertRaises(m.CaptureFailure) as caught:
                m.capture_original(base, fixture)
            self.assertEqual(caught.exception.stage, "selected_native_metadata_mismatch")
            self.assertEqual(opened.call_count, 1)

    def test_actual_one_raw_identity_failure_is_held_without_collapsing_other_operator(self):
        base, fixture, pages, current, write = self._real_metadata_and_pages()
        row = fixture["rows"][3]
        ref = row["raw_references"][0]
        pages[ref["source_file"]]["PRICES"][int(ref["json_pointer"].split("/")[-1])]["operatorKey"] = 342
        ref["sha256"], _ = write(ref["source_file"], pages[ref["source_file"]])
        current["saved_evidence"]["source_facts"][row["catalog_id"]][0]["raw"]["references"] = [{**r, "verified": True} for r in row["raw_references"]]
        n = fixture["inputs"]["native_current"]
        n["sha256"], n["bytes"] = write(n["path"], current)
        value = m.capture_original(base, fixture)
        self.assertFalse(value["rows"][3]["references"][0]["raw_verified"])
        self.assertTrue(value["rows"][4]["references"][0]["raw_verified"])
        self.assertTrue(all(ref["raw_verified"] for row in value["rows"][5:] for ref in row["references"]))

    def test_safe_file_rejects_symlinks_digest_and_size(self):
        with tempfile.TemporaryDirectory() as folder:
            p = pathlib.Path(folder) / "input.json"
            p.write_bytes(m.enc({"value": 1}))
            digest = hashlib.sha256(p.read_bytes()).hexdigest()
            self.assertEqual(m.read_pinned(p, digest, 1000), {"value": 1})
            with self.assertRaisesRegex(RuntimeError, "retained_digest"):
                m.read_pinned(p, "0" * 64, 1000)
            link = p.with_name("link.json")
            link.symlink_to(p)
            with self.assertRaisesRegex(ValueError, "metadata_path"):
                m.read_pinned(link, digest, 1000)
            with self.assertRaisesRegex(ValueError, "metadata_cap"):
                m.read_pinned(p, digest, 1)

    def test_no_provider_db_or_http_capability_import(self):
        source = PATH.read_text()
        self.assertNotRegex(source, r"(?m)^import (?:subprocess|requests|socket|sqlite3|pymysql)|^from (?:urllib.request|http.client|requests)")
        self.assertNotIn("urlopen(", source)
        self.assertNotIn("subprocess.run(", source)
        self.assertNotIn("v2_data_db(", source)


if __name__ == "__main__":
    unittest.main()
