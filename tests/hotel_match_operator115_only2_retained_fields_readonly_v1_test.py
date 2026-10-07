#!/usr/bin/env python3
"""Fixed synthetic file/real CLI checks; no supplier or database access."""
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

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts/diagnostics/hotel_match_operator115_only2_retained_fields_readonly_v1.py"
spec = importlib.util.spec_from_file_location("operator115_only2_test", SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
SHA = "a" * 40


def response():
    rows = [{"unselected": True} for _ in range(20)]
    for i, (_, native, pointer) in enumerate(m.ROSTER):
        rows[int(pointer.rsplit("/", 1)[1])] = {
            "hotelKey": int(native), "operatorKey": 115, "isOperatorHotelKey": True,
            "hotel": "Synthetic hotel " + str(i) + " Beach Adults Only",
            "town": "Synthetic town", "hotelUrl": "https://bgoperator.ru/hotel?code=12345",
            "original": {"hotelKey": int(native), "operatorKey": 115,
                         "tourKey": "opaque-" + native + "-private"}}
    return {"PRICES": rows}


def case(temp, data=None, raw=None):
    home = Path(temp).resolve() / "home"
    project = home / "www/anytoour.ru"
    project.mkdir(parents=True)
    private = home / ".anytoour-match"
    opdir = private / "operations" / m.OP
    opdir.mkdir(parents=True, mode=0o700)
    raw = m.private_bytes(response() if data is None else data) if raw is None else raw
    fixture = m.manifest()
    fixture["raw_source"]["sha256"] = hashlib.sha256(raw).hexdigest()
    stage = Path(temp).resolve() / "stage"
    folder = stage / "scripts/diagnostics"
    folder.mkdir(parents=True)
    for helper in m.HELPERS:
        shutil.copyfile(SOURCE.with_name(helper), folder / helper)
    fixture_path = folder / "fixtures" / m.FIXTURE.name
    fixture_path.parent.mkdir()
    fixture_raw = m.n.enc(fixture)
    fixture_path.write_bytes(fixture_raw)
    runner = folder / SOURCE.name
    runner.write_text(SOURCE.read_text().replace(m.MANIFEST_SHA, hashlib.sha256(fixture_raw).hexdigest()))
    original = private / fixture["raw_source"]["source_file"]
    original.parent.mkdir(parents=True)
    original.write_bytes(raw)
    os.chmod(original, 0o600)
    m.n.save(opdir / "reservation.json", {
        "operation": m.OP, "batch": m.BATCH, "source_sha": SHA,
        "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"})
    env = {k: os.environ[k] for k in ("PATH", "HOME", "LANG", "LC_ALL") if k in os.environ}
    env.update(ANYTOUR_ROOT=str(project), MATCH_OPERATION_DIR=str(opdir),
               MATCH_MANIFEST_PATH=str(fixture_path), MATCH_SOURCE_SHA=SHA)
    spec = importlib.util.spec_from_file_location("synthetic_operator115_only2", runner)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return dict(home=home, project=project, opdir=opdir, original=original, raw=raw,
                runner=runner, fixture=fixture, fixture_path=fixture_path, env=env, module=module)


def run(c):
    return subprocess.run([os.sys.executable, str(c["runner"]), "--execute"],
                          cwd=c["project"], env=c["env"], capture_output=True, text=True, timeout=30)


def read(c):
    return {name: json.loads((c["opdir"] / (name + ".json")).read_bytes())
            for name in ("current-input", "result", "receipt")}


def field(row, name):
    return next(f for f in row["fields"] if f["field_name"] == name)


class OperatorOnly2Test(unittest.TestCase):
    def test_stock_layout_true_operator_flag_never_promotes_catalog(self):
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp)
            self.assertEqual(run(c).returncode, 0)
            d = read(c)
            result = d["result"]
            self.assertEqual(result["rows_examined"], 2)
            self.assertEqual(result["raw_references_verified"], 2)
            self.assertEqual((c["opdir"] / "retained-original.json").read_bytes(), c["raw"])
            for row in result["rows"]:
                self.assertTrue(row["raw_verified"])
                self.assertIs(field(row, "row.isOperatorHotelKey")["value"], True)
                self.assertIn("Beach Adults Only", field(row, "row.hotel")["value"])
                self.assertEqual(row["target_namespace"], "not_established")
                self.assertNotIn("catalog_id", row)
                self.assertTrue(all(row[k] is False for k in m.FALSE_FLAGS))
            self.assertTrue(all(type(result[k]) is int and result[k] == 0 for k in m.NO_EFFECTS))
            self.assertTrue(c["module"].validate_result(result, d["receipt"], SHA, d["current-input"]))
            for name in ("retained-original", "current-input", "result", "receipt"):
                self.assertEqual((c["opdir"] / (name + ".json")).stat().st_mode & 0o777, 0o600)

    def test_optional_object_and_lone_surrogate_hold_one_row_after_exact_capture(self):
        data = response()
        data["PRICES"][3]["hotel"] = "bad\ud800"
        data["PRICES"][3]["hotelUrl"] = {"bad_optional": "keep-exact"}
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp, data)
            self.assertEqual(run(c).returncode, 0)
            d = read(c)
            rows = d["result"]["rows"]
            self.assertEqual(field(rows[0], "row.hotel")["hold"], "optional_field_invalid_unicode")
            self.assertEqual(field(rows[0], "row.hotelUrl")["hold"], "optional_field_not_scalar")
            self.assertEqual(field(rows[1], "row.hotel")["value"], data["PRICES"][19]["hotel"])
            self.assertTrue(rows[1]["raw_verified"])
            self.assertEqual(d["current-input"]["rows"][0]["original_row"], data["PRICES"][3])
            self.assertEqual((c["opdir"] / "retained-original.json").read_bytes(), c["raw"])
            self.assertNotIn("keep-exact", json.dumps(d["result"]))

    def test_secret_url_and_opaque_key_do_not_export_or_create_identity(self):
        data = response()
        data["PRICES"][3]["hotelUrl"] = "https://bgoperator.ru/hotel?token=SECRETVALUE&code=12345"
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp, data)
            self.assertEqual(run(c).returncode, 0)
            result = read(c)["result"]
            public = json.dumps(result)
            self.assertNotIn("SECRETVALUE", public)
            self.assertNotIn("opaque-", public)
            self.assertEqual(field(result["rows"][0], "row.hotelUrl")["projection"]["origin_state"],
                             "private_parameters_redacted")
            self.assertEqual(field(result["rows"][0], "original.tourKey")["projection"]["representation"],
                             "opaque_or_non_url")

    def test_one_row_identity_conflict_does_not_accept_or_block_other(self):
        data = response()
        data["PRICES"][3]["original"]["operatorKey"] = 342
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp, data)
            self.assertEqual(run(c).returncode, 0)
            rows = read(c)["result"]["rows"]
            self.assertFalse(rows[0]["raw_verified"])
            self.assertEqual(rows[0]["fields"], [])
            self.assertTrue(rows[1]["raw_verified"])

    def test_missing_optional_original_keeps_known_row_locator_and_name_only(self):
        data = response()
        data["PRICES"][3].pop("original")
        data["PRICES"][19]["original"]["hotelKey"] = None
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp, data)
            self.assertEqual(run(c).returncode, 0)
            result = read(c)["result"]
            for row in result["rows"]:
                self.assertTrue(row["raw_verified"])
                self.assertIsNotNone(field(row, "row.hotel")["value"])
                self.assertIsNotNone(field(row, "original.hotelKey")["hold"])
                self.assertFalse(row["namespace_bridge_verified"])
            self.assertEqual(result["accepted"], 0)

    def test_source_digest_drift_is_terminal_and_not_retried(self):
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp)
            c["original"].write_bytes(c["raw"] + b" ")
            self.assertEqual(run(c).returncode, 2)
            d = read(c)
            self.assertEqual(d["result"]["state"], "terminal_failed_no_replay")
            self.assertEqual(d["result"]["rows_examined"], 0)
            before = {p.name: p.read_bytes() for p in c["opdir"].iterdir()}
            self.assertNotEqual(run(c).returncode, 0)
            self.assertEqual(before, {p.name: p.read_bytes() for p in c["opdir"].iterdir()})

    def test_valid_first_read_is_consumed(self):
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp)
            self.assertEqual(run(c).returncode, 0)
            before = {p.name: p.read_bytes() for p in c["opdir"].iterdir()}
            self.assertNotEqual(run(c).returncode, 0)
            self.assertEqual(before, {p.name: p.read_bytes() for p in c["opdir"].iterdir()})

    def test_duplicate_json_key_preserves_bytes_then_fails_schema(self):
        raw = b'{"PRICES":[],"PRICES":[]}'
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp, raw=raw)
            self.assertEqual(run(c).returncode, 2)
            d = read(c)["result"]
            self.assertEqual(d["failure_stage"], "retained_schema_invalid")
            self.assertEqual((c["opdir"] / "retained-original.json").read_bytes(), raw)
            self.assertTrue(d["original_bytes_preserved"])

    def test_nonstock_root_and_symlink_rejected_before_source_read(self):
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp)
            wrong = c["home"] / "anytoour.ru"
            wrong.mkdir()
            c["env"]["ANYTOUR_ROOT"] = str(wrong)
            self.assertNotEqual(run(c).returncode, 0)
            self.assertFalse((c["opdir"] / "retained-original.json").exists())
            c["env"]["ANYTOUR_ROOT"] = str(c["project"])
            original = c["original"].with_name("other.json")
            c["original"].rename(original)
            c["original"].symlink_to(original)
            self.assertEqual(run(c).returncode, 2)
            self.assertFalse((c["opdir"] / "retained-original.json").exists())

    def test_private_provenance_and_typed_counter_tampering_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            c = case(temp)
            self.assertEqual(run(c).returncode, 0)
            d = read(c)
            for mutation in ("counter", "field", "private", "receipt"):
                result, receipt, private = copy.deepcopy(d["result"]), copy.deepcopy(d["receipt"]), copy.deepcopy(d["current-input"])
                if mutation == "counter":
                    result["database_reads"] = False
                elif mutation == "field":
                    field(result["rows"][0], "row.hotel")["value"] = "Changed"
                elif mutation == "private":
                    private["rows"][0]["original_row"]["hotel"] = "Changed"
                else:
                    receipt["result_sha256"] = "b" * 64
                with self.subTest(mutation=mutation), self.assertRaises(ValueError):
                    c["module"].validate_result(result, receipt, SHA, private)

    def test_bool_identifier_and_catalog_number_guess_rejected(self):
        spec = m.manifest()["rows"][0]
        raw = response()["PRICES"][3]
        self.assertTrue(m.raw_binding(raw, spec))
        for value in (True, "0610284976", 610284975, "102610284976"):
            bad = copy.deepcopy(raw)
            bad["hotelKey"] = value
            self.assertFalse(m.raw_binding(bad, spec))


if __name__ == "__main__":
    unittest.main()
