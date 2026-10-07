#!/usr/bin/env python3
"""Synthetic file/actual-CLI checks; no supplier response or DB access."""
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py"
SPEC = importlib.util.spec_from_file_location("alias3_fields_test_source", SOURCE)
m = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(m)
SHA = "a" * 40


def raw_response():
    rows = [{"unselected": True} for _ in range(45)]
    for spec in m.manifest()["rows"]:
        rows[int(spec["json_pointer"].split("/")[-1])] = {
            "hotelKey": int(spec["catalog_id"]), "operatorKey": 115,
            "hotelUrl": "https://bgoperator.ru/hotel?code=" + (spec["retained_bgoperator_code"] or "123"),
            "original": {"hotelKey": int(spec["source_native_id"]), "operatorKey": 115,
                         "tourKey": "https://bgoperator.ru/tour?f4=" + (spec["retained_bgoperator_code"] or "456")},
            "hotel": "Synthetic hotel identity", "unchanged_optional": "private-original-value"}
    return m.n.enc({"PRICES": rows})


def cli_case(base, raw=None):
    """Freeze only synthetic bytes; never modify a runtime/repository fixture."""
    home = Path(base).resolve() / "home"
    project = home / "www" / "anytoour.ru"
    project.mkdir(parents=True)
    private_root = home / ".anytoour-match"
    opdir = private_root / "operations" / m.OP
    opdir.mkdir(parents=True, mode=0o700)
    raw = raw_response() if raw is None else raw
    fixture = m.manifest()
    fixture["raw_source"]["sha256"] = hashlib.sha256(raw).hexdigest()
    stage = Path(base).resolve() / "stage"
    for helper in m.HELPERS:
        target = stage / "scripts/diagnostics" / helper
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(SOURCE.with_name(helper), target)
    fixture_path = stage / "scripts/diagnostics/fixtures" / m.FIXTURE.name
    fixture_path.parent.mkdir()
    fixture_bytes = m.n.enc(fixture)
    fixture_path.write_bytes(fixture_bytes)
    runner = stage / SOURCE.relative_to(ROOT)
    runner.write_text(SOURCE.read_text().replace(m.MANIFEST_SHA, hashlib.sha256(fixture_bytes).hexdigest()))
    original = private_root / fixture["raw_source"]["source_file"]
    original.parent.mkdir(parents=True)
    original.write_bytes(raw)
    m.n.save(opdir / "reservation.json", dict(operation=m.OP, batch=m.BATCH, source_sha=SHA,
                                             provider_http_calls=0, maximum_writes=0,
                                             state="reserved_before_retained_read"))
    env = {k: os.environ[k] for k in ("PATH", "HOME", "LANG", "LC_ALL") if k in os.environ}
    env.update(ANYTOUR_ROOT=str(project), MATCH_OPERATION_DIR=str(opdir), MATCH_MANIFEST_PATH=str(fixture_path), MATCH_SOURCE_SHA=SHA)
    spec = importlib.util.spec_from_file_location("synthetic_alias3_source", runner)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return dict(home=home, project=project, opdir=opdir, fixture=fixture, runner=runner,
                fixture_path=fixture_path, raw=raw, original=original, env=env, module=module, stage=stage)


def run_case(case):
    return subprocess.run([os.sys.executable, str(case["runner"]), "--execute"], cwd=case["project"],
                          env=case["env"], capture_output=True, text=True, timeout=30)


class Alias3RetainedFieldsTest(unittest.TestCase):
    def test_nonstock_project_layout_is_rejected_before_retained_read(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp)
            wrong_project = c["home"] / "anytoour.ru"
            wrong_project.mkdir()
            c["env"]["ANYTOUR_ROOT"] = str(wrong_project)
            reservation = (c["opdir"] / "reservation.json").read_bytes()
            result = run_case(c)
            self.assertEqual(result.returncode, 1)
            self.assertIn("ValueError: execution_paths", result.stderr)
            self.assertEqual((c["opdir"] / "reservation.json").read_bytes(), reservation)
            self.assertEqual(c["original"].read_bytes(), c["raw"])
            self.assertEqual({p.name for p in c["opdir"].iterdir()}, {"reservation.json"})

    def test_fixed_roster_namespaces_and_no_old_scope(self):
        f = m.manifest()
        self.assertEqual(len(f["rows"]), 3)
        self.assertEqual(len({r["catalog_id"] for r in f["rows"]}), 3)
        self.assertEqual(f["limits"]["unique_raw_files"], 1)
        self.assertEqual(f["target_namespace"], "anytour_local")
        self.assertTrue(all(r["independent_tv_hotel_id"] is None for r in f["rows"]))
        self.assertIsNone(f["rows"][0]["retained_bgoperator_code"])
        self.assertFalse(any(r["catalog_id"] in {"205729", "2000041008", "2000052316", "2000059209", "2000060910", "2000062548", "2000062557", "2000086021"} for r in f["rows"]))
        self.assertNotIn("observed-page1", json.dumps(f))

    def test_actual_cli_full_bytes_and_rows_preserved_before_projection(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp)
            result = run_case(c)
            self.assertEqual((result.returncode, result.stderr), (0, ""))
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            receipt = json.loads((c["opdir"] / "receipt.json").read_bytes())
            private = json.loads((c["opdir"] / "current-input.json").read_bytes())
            self.assertEqual((c["opdir"] / "retained-original.json").read_bytes(), c["raw"])
            self.assertTrue(all(r["original_row"]["unchanged_optional"] == "private-original-value" for r in private["rows"]))
            self.assertNotIn("private-original-value", json.dumps(data))
            self.assertEqual((data["raw_files_attempted"], data["raw_files_read"], data["raw_references_verified"], data["rows_examined"]), (1, 1, 3, 3))
            self.assertEqual(data["raw_bytes_read"], len(c["raw"]))
            self.assertTrue(c["module"].validate_result(data, receipt, SHA, private))
            self.assertTrue(all(data[k] == 0 and type(data[k]) is int for k in m.NO_EFFECTS))
            self.assertTrue(all(data[k] is False for k in m.FALSE_FLAGS))
            self.assertTrue(all((c["opdir"] / name).stat().st_mode & 0o077 == 0 for name in ("retained-original.json", "current-input.json", "result.json", "receipt.json")))
            self.assertEqual(json.loads(result.stdout), {k: data[k] for k in ("state", "rows_examined", "accepted", "written")})

    def test_durable_bytes_exist_before_json_parsing(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp)
            original = m.n.parsed
            def parsed(raw):
                self.assertEqual((c["opdir"] / "retained-original.json").read_bytes(), c["raw"])
                return original(raw)
            stats = dict(raw_files_attempted=0, raw_files_read=0, raw_bytes_read=0, original_bytes_preserved=False, original_source_sha256=None)
            with patch.object(m.n, "parsed", side_effect=parsed):
                private = m.capture(c["home"] / ".anytoour-match", c["opdir"], c["fixture"], stats)
            self.assertEqual(len(private["rows"]), 3)

    def test_optional_object_field_holds_one_row_and_keeps_full_object(self):
        raw = json.loads(raw_response())
        raw["PRICES"][44]["hotelUrl"] = {"private_optional": "keep-exactly"}
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp, m.n.enc(raw))
            self.assertEqual(run_case(c).returncode, 0)
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            private = json.loads((c["opdir"] / "current-input.json").read_bytes())
            self.assertEqual(data["state"], m.STATES[1])
            self.assertEqual(data["rows"][0]["fields"][0]["hold"], "optional_field_not_string")
            self.assertTrue(all(f["hold"] is None for r in data["rows"][1:] for f in r["fields"]))
            self.assertEqual(private["rows"][0]["original_row"]["hotelUrl"], {"private_optional": "keep-exactly"})
            self.assertNotIn("keep-exactly", json.dumps(data))

    def test_secret_query_and_opaque_tour_key_stay_private(self):
        raw = json.loads(raw_response())
        raw["PRICES"][22]["hotelUrl"] = "https://bgoperator.ru/hotel?token=SECRETVALUE&code=102625076456"
        raw["PRICES"][22]["original"]["tourKey"] = "opaque-625076456-DO-NOT-EXTRACT"
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp, m.n.enc(raw))
            self.assertEqual(run_case(c).returncode, 0)
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            raw_private = (c["opdir"] / "current-input.json").read_text()
            self.assertIn("SECRETVALUE", raw_private)
            self.assertIn("DO-NOT-EXTRACT", raw_private)
            self.assertNotIn("SECRETVALUE", json.dumps(data))
            self.assertNotIn("DO-NOT-EXTRACT", json.dumps(data))
            fields = data["rows"][1]["fields"]
            self.assertEqual(fields[0]["projection"]["origin_state"], "private_parameters_redacted")
            self.assertEqual(fields[1]["projection"]["raw_selector_tokens"], [])

    def test_one_identity_change_leaves_two_independent_rows(self):
        raw = json.loads(raw_response()); raw["PRICES"][44]["original"]["hotelKey"] = 7
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp, m.n.enc(raw))
            self.assertEqual(run_case(c).returncode, 0)
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            self.assertEqual(data["raw_references_verified"], 2)
            self.assertFalse(data["rows"][0]["raw_verified"])
            self.assertEqual(data["rows"][0]["fields"], [])
            self.assertTrue(all(r["raw_verified"] for r in data["rows"][1:]))

    def test_changed_digest_records_actual_read_without_preserving_unbound_bytes(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp); c["original"].write_bytes(c["raw"] + b"\n")
            run = run_case(c)
            self.assertEqual((run.returncode, run.stderr), (2, ""))
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            self.assertEqual(data["failure_stage"], "retained_source_unavailable_or_digest")
            self.assertEqual((data["raw_files_attempted"], data["raw_files_read"], data["raw_bytes_read"], data["rows_examined"]), (1, 1, len(c["raw"]) + 1, 0))
            self.assertFalse((c["opdir"] / "retained-original.json").exists())

    def test_symlink_is_not_read(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp); other = c["original"].with_suffix(".original"); c["original"].rename(other); c["original"].symlink_to(other)
            self.assertEqual(run_case(c).returncode, 2)
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            self.assertEqual((data["raw_files_attempted"], data["raw_files_read"], data["raw_bytes_read"]), (1, 0, 0))

    def test_invalid_schema_preserves_bytes_but_produces_no_rows(self):
        for raw in (b'{"PRICES":[],"PRICES":[]}\n', b'{"PRICES":[]} BAD-SECRET', b'{"PRICES":[NaN]}\n'):
            with tempfile.TemporaryDirectory() as temp:
                c = cli_case(temp, raw)
                run = run_case(c)
                self.assertEqual((run.returncode, run.stderr), (2, ""))
                data = json.loads((c["opdir"] / "result.json").read_bytes())
                self.assertEqual(data["failure_stage"], "retained_schema_invalid")
                self.assertEqual((c["opdir"] / "retained-original.json").read_bytes(), raw)
                self.assertEqual(data["rows"], [])
                self.assertNotIn("BAD-SECRET", run.stdout + json.dumps(data))

    def test_consumed_operation_cannot_retry_even_if_public_files_deleted(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp); self.assertEqual(run_case(c).returncode, 0)
            original = (c["opdir"] / "retained-original.json").read_bytes()
            for name in ("result.json", "receipt.json"):
                (c["opdir"] / name).unlink()
            result = run_case(c)
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual((c["opdir"] / "retained-original.json").read_bytes(), original)
            self.assertFalse((c["opdir"] / "result.json").exists())

    def test_result_mutations_cannot_change_identity_authority_or_private_projection(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp); self.assertEqual(run_case(c).returncode, 0)
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            private = json.loads((c["opdir"] / "current-input.json").read_bytes())
            mutations = [lambda d:d.update(accepted=True), lambda d:d.update(raw_files_read=True),
                         lambda d:d.update(namespace_bridge_verified=True), lambda d:d.update(current_readiness="ready"),
                         lambda d:d["rows"][0].update(target_namespace="tourvisor"),
                         lambda d:d["rows"][1]["fields"][0]["projection"].update(raw_selector_values=["999"]),
                         lambda d:d["rows"][1]["fields"][0].update(extra="private-field"),
                         lambda d:d["rows"][2].update(retained_accepted_catalog_id="7")]
            for mutate in mutations:
                changed = copy.deepcopy(data); mutate(changed)
                with self.assertRaises((ValueError, KeyError)):
                    c["module"].validate_result(changed, expected_source=SHA, private_input=private)
            with self.assertRaisesRegex(ValueError, "private_capture_required"):
                c["module"].validate_result(data)

    def test_receipt_digest_and_boolean_counter_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            c = cli_case(temp); self.assertEqual(run_case(c).returncode, 0)
            data = json.loads((c["opdir"] / "result.json").read_bytes())
            private = json.loads((c["opdir"] / "current-input.json").read_bytes())
            receipt = json.loads((c["opdir"] / "receipt.json").read_bytes())
            for key, value in (("result_sha256", "b" * 64), ("accepted", False), ("source_sha", "b" * 40)):
                changed = dict(receipt); changed[key] = value
                with self.assertRaises(ValueError):
                    c["module"].validate_result(data, changed, SHA, private)

    def test_original_duplicate_signed_selector_tokens_preserved_candidate_only(self):
        value = "https://bgoperator.ru/hotel?F4=102625076456,-102625076456,102625076456&F4=77"
        p = m.optional_projection("row.hotelUrl", True, value, "102625076456")["projection"]
        self.assertEqual(p["raw_selector_tokens"], ["102625076456", "-102625076456", "102625076456", "77"])
        self.assertIs(p["namespace_bridge_verified"], False)
        self.assertIsNone(m.manifest()["rows"][0]["retained_bgoperator_code"])

    def test_missing_empty_controls_oversized_fields_keep_independent_values(self):
        for present, value, expected in ((False, None, "optional_field_missing"), (True, "", "optional_field_empty"),
                                        (True, "https://bgoperator.ru/a\n", "optional_field_controls"),
                                        (True, "x" * 16385, "optional_field_resource_cap")):
            field = m.optional_projection("row.hotelUrl", present, value, None)
            self.assertEqual(field["hold"], expected)
            self.assertIsNone(field["projection"])
            self.assertEqual(field["value_sha256"], hashlib.sha256(m.n.enc(value)).hexdigest())


if __name__ == "__main__":
    unittest.main()
