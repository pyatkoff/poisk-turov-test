import copy
import contextlib
import hashlib
import importlib.util
import io
import json
import os
import pathlib
import subprocess
import tempfile
import unittest
from unittest import mock

ROOT = pathlib.Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts/diagnostics/hotel_match_nonbg5_url_paths_terminal_readback_v1.py"
FIXTURE = SOURCE.with_name("fixtures") / "hotel_match_nonbg5_url_paths_terminal_readback_v1.json"
spec = importlib.util.spec_from_file_location("readback", SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


class TerminalReadback(unittest.TestCase):
    def setUp(self):
        self.fixture = m.manifest(FIXTURE)
        self.temp = tempfile.TemporaryDirectory(prefix="url5-readback-only-")
        self.addCleanup(self.temp.cleanup)
        self.base = pathlib.Path(self.temp.name).resolve()
        self.private = self.base / ".anytoour-match"
        self.private.mkdir()
        self.old = self.private / "operations" / m.OLD_OP
        self.old.mkdir(parents=True)
        self.paths = {p["role"]: self.private / p["relative_path"] for p in self.fixture["metadata_records"]}
        for role in ("batch_marker", "reservation"):
            self.paths[role].write_bytes(m.enc(m.old_reservation()))

    def capture(self):
        return m.capture_metadata(self.private, self.fixture)

    def full_old_failed_terminal(self):
        inp = {"schema": "match-nonbg5-retained-url-paths-private-input/1", "operation": m.OLD_OP, "batch": m.OLD_BATCH, "state": "capture_failed", "reason": "nonbg5_url_paths_capture_or_validation_failed", "failure_stage": "prior_metadata_unavailable_or_digest"}
        self.paths["private_input"].write_bytes(m.enc(inp))
        old_fixture = json.loads((ROOT / self.fixture["inputs"]["old_source_fixture"]["path"]).read_bytes())
        result = {"schema": "match-nonbg5-retained-url-paths-readonly-result/1", "operation": m.OLD_OP, "batch": m.OLD_BATCH, "source_sha": m.OLD_SOURCE, "state": "terminal_failed_no_replay", "reason": inp["reason"], "failure_stage": inp["failure_stage"], "captured_at_utc": "2026-10-04T13:00:00Z", "private_input_sha256": hashlib.sha256(m.enc(inp)).hexdigest(), "inputs": old_fixture["inputs"], "requested_rows": 5, "requested_sources": 4, "distinct_source_count": 0, "rows_examined": 0, "operator_ids": [13, 25, 43], "rows": [], "metadata_files_bound": 0, "metadata_bytes_bound": 0, "original_raw_files_read": 0, "references_bound": 0, "safe_path_candidate_references": 0, "safe_path_candidate_rows": 0, "hold_counts": {}, "global_saved_context_only": True, "source_namespace_bridge_verified": False, "target_native_identity_verified": False, "no_replay": True, **dict.fromkeys(m.NO_EFFECTS, 0), **dict.fromkeys(m.FALSE_FLAGS, False)}
        self.paths["result"].write_bytes(m.enc(result))
        receipt = {k: result[k] for k in m.RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(m.enc(result)).hexdigest()}
        self.paths["receipt"].write_bytes(m.enc(receipt))
        self.paths["execution_started"].write_bytes(m.enc({"operation": m.OLD_OP, "batch": m.OLD_BATCH, "source_sha": m.OLD_SOURCE, "no_replay": True}))
        return result

    def output(self, capture=None):
        capture = self.capture() if capture is None else capture
        records = capture["metadata_records"]
        summary = capture["existing_terminal_summary"]
        readable = [r for r in records if r["sha256"] is not None]
        return {"schema": "match-nonbg5-url-paths-terminal-readback-result/1", "operation": m.OP, "batch": m.BATCH, "source_sha": "a" * 40, "state": m.STATES[1] if capture["classification"] == "partial_or_unbound_old_terminal_metadata" else m.STATES[0], "reason": None, "failure_stage": None, "captured_at_utc": "2026-10-04T14:00:00Z", "private_input_sha256": "b" * 64, "inputs": self.fixture["inputs"], "requested_records": 6, "rows_examined": 6, "metadata_records": records, "classification": capture["classification"], "recovery_reason": capture["recovery_reason"], "existing_terminal_summary": summary, "existing_terminal_result_sha256": next(r["sha256"] for r in records if r["role"] == "result") if summary is not None else None, "existing_terminal_verified": summary is not None, "recovered_path_candidate_references": summary["safe_path_candidate_references"] if summary else 0, "recovered_path_candidate_rows": summary["safe_path_candidate_rows"] if summary else 0, "metadata_files_bound": len(readable), "metadata_bytes_bound": sum(r["size_bytes"] for r in readable), "original_raw_files_read": 0, "nonbg7_private_files_read": 0, "old_supplier_calls": "unknown", "old_database_writes": "unknown", "old_operation_replayed": False, "no_replay": True, **dict.fromkeys(m.NO_EFFECTS, 0), **dict.fromkeys(m.FALSE_FLAGS, False)}

    def cli(self):
        project = self.base / "anytoour.ru"
        project.mkdir(exist_ok=True)
        child = self.private / "operations" / m.OP
        child.mkdir()
        m.save(child / "reservation.json", {"operation": m.OP, "batch": m.BATCH, "source_sha": "a" * 40, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"})
        env = {k: os.environ[k] for k in ("PATH", "HOME", "LANG", "LC_ALL") if k in os.environ}
        env.update({"ANYTOUR_ROOT": str(project), "MATCH_SOURCE_ROOT": str(ROOT), "MATCH_OPERATION_DIR": str(child), "MATCH_MANIFEST_PATH": str(FIXTURE), "MATCH_SOURCE_SHA": "a" * 40})
        result = subprocess.run(["python3", str(SOURCE), "--execute"], cwd=project, env=env, capture_output=True, text=True, timeout=10)
        return result, child, env

    def test_frozen_six_paths_only(self):
        self.assertEqual(len(self.fixture["metadata_records"]), 6)
        self.assertTrue(all(m.OLD_OP in p["relative_path"] or p["role"] == "batch_marker" for p in self.fixture["metadata_records"]))
        self.assertFalse(any("nonbg7" in p["relative_path"] or "evidence-private" in p["relative_path"] for p in self.fixture["metadata_records"]))

    def test_markers_present_terminal_absent_is_observation_not_old_effect_zero(self):
        data = self.output()
        self.assertTrue(m.validate_result(data))
        self.assertEqual(data["classification"], "pre_entrypoint_terminal_files_absent_observed")
        self.assertEqual(data["metadata_files_bound"], 2)
        self.assertEqual([r["presence"] for r in data["metadata_records"]], ["present", "present", "absent", "absent", "absent", "absent"])
        self.assertEqual(data["old_supplier_calls"], "unknown")
        self.assertEqual(data["old_database_writes"], "unknown")
        self.assertEqual(data["recovered_path_candidate_references"], 0)

    def test_old_validator_not_imported_when_terminal_absent(self):
        with mock.patch.object(m, "load_old_validator", side_effect=AssertionError("old validator must not load")):
            self.assertIsNone(self.capture()["existing_terminal_summary"])

    def test_each_six_regular_metadata_read_scope_only(self):
        self.full_old_failed_terminal()
        calls = []
        original = m.file_bytes
        def read(path, maximum):
            calls.append(str(path))
            return original(path, maximum)
        with mock.patch.object(m, "file_bytes", side_effect=read):
            capture = self.capture()
        private_reads = [p for p in calls if str(self.private) in p]
        self.assertEqual(len(private_reads), 6)
        self.assertEqual(set(private_reads), set(str(p) for p in self.paths.values()))
        self.assertFalse(any("nonbg7" in p or "evidence-private" in p for p in private_reads))
        self.assertEqual(capture["classification"], "existing_terminal_verified")

    def test_existing_complete_failed_terminal_real_frozen_validator_only(self):
        old = self.full_old_failed_terminal()
        capture = self.capture()
        self.assertEqual(capture["existing_terminal_summary"], old)
        self.assertTrue(m.validate_result(self.output(capture)))
        self.assertEqual(old["state"], "terminal_failed_no_replay")

    def test_partial_terminal_never_loads_old_validator(self):
        self.paths["execution_started"].write_bytes(m.enc({"operation": m.OLD_OP, "batch": m.OLD_BATCH, "source_sha": m.OLD_SOURCE, "no_replay": True}))
        with mock.patch.object(m, "load_old_validator", side_effect=AssertionError("must not load")):
            data = self.output()
        self.assertEqual(data["state"], m.STATES[1])
        self.assertEqual(data["classification"], "partial_or_unbound_old_terminal_metadata")
        self.assertIsNone(data["existing_terminal_summary"])

    def test_wrong_old_reservation_source_header_hold(self):
        bad = m.old_reservation()
        bad["source_sha"] = "f" * 40
        self.paths["reservation"].write_bytes(m.enc(bad))
        data = self.output()
        self.assertEqual(data["metadata_records"][1]["binding_state"], "old_header_mismatch")
        self.assertEqual(data["classification"], "partial_or_unbound_old_terminal_metadata")

    def test_bool_cannot_replace_numeric_old_reservation(self):
        bad = m.old_reservation()
        bad["provider_http_calls"] = False
        self.assertFalse(m.header_valid("reservation", bad))

    def test_record_raw_private_values_never_exported(self):
        self.paths["private_input"].write_bytes(m.enc({"VERY_PRIVATE_KEY": "DO_NOT_EXPORT_THIS_VALUE"}))
        data = self.output()
        encoded = m.enc(data).decode()
        self.assertNotIn("VERY_PRIVATE_KEY", encoded)
        self.assertNotIn("DO_NOT_EXPORT_THIS_VALUE", encoded)
        self.assertEqual(data["metadata_records"][3]["json_type"], "object")

    def test_symlink_never_followed(self):
        outside = self.base / "outside-secret.json"
        outside.write_text('{"secret":"DO_NOT_OPEN"}')
        self.paths["private_input"].symlink_to(outside)
        with mock.patch.object(m, "file_bytes", wraps=m.file_bytes) as read:
            capture = self.capture()
        self.assertFalse(any(str(self.paths["private_input"]) == str(c.args[0]) for c in read.call_args_list))
        record = capture["metadata_records"][3]
        self.assertEqual(record["file_type"], "symlink")
        self.assertIsNone(record["sha256"])
        self.assertNotIn("DO_NOT_OPEN", m.enc(capture).decode())

    def test_unsafe_parent_never_reads_neighbor(self):
        self.paths["reservation"].unlink()
        self.old.rmdir()
        outside = self.base / "neighbor"
        outside.mkdir()
        (outside / "reservation.json").write_bytes(m.enc(m.old_reservation()))
        self.old.symlink_to(outside, target_is_directory=True)
        capture = self.capture()
        self.assertTrue(all(r["presence"] == "unknown" for r in capture["metadata_records"][1:]))

    def test_directory_and_empty_file_are_safe_holds(self):
        self.paths["private_input"].mkdir()
        self.paths["receipt"].touch()
        capture = self.capture()
        self.assertEqual(capture["metadata_records"][3]["file_type"], "directory")
        self.assertEqual(capture["metadata_records"][5]["binding_state"], "resource_cap")

    def test_duplicate_json_keys_and_nonfinite_hold(self):
        for raw in (b'{"operation":"a","operation":"b"}', b'{"value":NaN}'):
            self.paths["result"].write_bytes(raw)
            capture = self.capture()
            self.assertEqual(capture["metadata_records"][4]["binding_state"], "json_invalid")

    def test_wrong_result_receipt_digest_is_not_recovered(self):
        self.full_old_failed_terminal()
        r = json.loads(self.paths["receipt"].read_bytes())
        r["result_sha256"] = "f" * 64
        self.paths["receipt"].write_bytes(m.enc(r))
        data = self.output()
        self.assertIsNone(data["existing_terminal_summary"])
        self.assertEqual(data["recovery_reason"], "existing_old_terminal_validation_failed")

    def test_typed_receipt_object_with_noncanonical_bytes_not_recovered(self):
        self.full_old_failed_terminal()
        receipt = json.loads(self.paths["receipt"].read_bytes())
        self.paths["receipt"].write_text(json.dumps(receipt, separators=(",", ":")) + "\n")
        self.assertIsNone(self.output()["existing_terminal_summary"])

    def test_wrong_input_digest_is_not_recovered(self):
        self.full_old_failed_terminal()
        self.paths["private_input"].write_bytes(self.paths["private_input"].read_bytes() + b" ")
        data = self.output()
        self.assertIsNone(data["existing_terminal_summary"])
        self.assertEqual(data["classification"], "partial_or_unbound_old_terminal_metadata")

    def test_frozen_old_module_or_fixture_mismatch_blocks_import(self):
        self.full_old_failed_terminal()
        old = m.file_bytes
        def bad_read(path, maximum):
            raw = old(path, maximum)
            return raw[:-1] + b" " if str(path).endswith("hotel_match_nonbg5_retained_url_paths_readonly_v1.py") else raw
        with mock.patch.object(m, "file_bytes", side_effect=bad_read), mock.patch.object(m.importlib.util, "spec_from_file_location") as loader:
            data = self.output()
        loader.assert_not_called()
        self.assertIsNone(data["existing_terminal_summary"])

    def test_imported_old_execute_capture_projection_never_called(self):
        self.full_old_failed_terminal()
        real = m.load_old_validator
        called = []
        def load(fixture):
            validate = real(fixture)
            module_globals = validate.__globals__
            for name in ("execute", "capture_retained", "project_capture"):
                module_globals[name] = lambda *a, **kw: (_ for _ in ()).throw(AssertionError("forbidden old entry point"))
            def v(*args):
                called.append(True)
                return validate(*args)
            return v
        with mock.patch.object(m, "load_old_validator", side_effect=load):
            self.assertEqual(self.capture()["classification"], "existing_terminal_verified")
        self.assertEqual(called, [True])

    def test_actual_cli_common_control_env_no_alias_names(self):
        run, child, env = self.cli()
        self.assertNotIn("MATCH_OPERATION_DIRECTORY", env)
        self.assertNotIn("MATCH_MANIFEST", env)
        self.assertEqual(run.returncode, 0, run.stderr)
        self.assertEqual(run.stderr, "")
        stdout = json.loads(run.stdout)
        self.assertEqual(set(stdout), {"state", "rows_examined", "accepted", "written"})
        self.assertEqual(stdout["rows_examined"], 6)
        self.assertEqual(stdout["accepted"], 0)
        self.assertEqual(stdout["written"], 0)
        data = json.loads((child / "result.json").read_bytes())
        receipt = json.loads((child / "receipt.json").read_bytes())
        self.assertTrue(m.validate_result(data, receipt, "a" * 40))
        self.assertEqual(receipt["private_input_sha256"], hashlib.sha256((child / "current-input.json").read_bytes()).hexdigest())
        self.assertEqual((child / "current-input.json").stat().st_mode & 0o777, 0o600)
        self.assertTrue((child / "execution-started.json").is_file())

    def test_actual_cli_never_replays_same_new_operation(self):
        run, child, env = self.cli()
        self.assertEqual(run.returncode, 0)
        before = {p.name: p.read_bytes() for p in child.iterdir()}
        again = subprocess.run(["python3", str(SOURCE), "--execute"], cwd=self.base / "anytoour.ru", env=env, capture_output=True, text=True, timeout=10)
        self.assertNotEqual(again.returncode, 0)
        self.assertIn("terminal_no_replay", again.stderr)
        self.assertEqual(before, {p.name: p.read_bytes() for p in child.iterdir()})

    def test_actual_cli_can_only_recover_already_written_terminal(self):
        self.full_old_failed_terminal()
        old_files = {p.name: p.read_bytes() for p in self.old.iterdir()}
        run, child, env = self.cli()
        self.assertEqual(run.returncode, 0, run.stderr)
        data = json.loads((child / "result.json").read_bytes())
        self.assertEqual(data["classification"], "existing_terminal_verified")
        self.assertEqual(data["recovered_path_candidate_references"], 0)
        self.assertEqual(old_files, {p.name: p.read_bytes() for p in self.old.iterdir()})

    def test_public_descriptor_and_closed_schema_rejection(self):
        data = self.output()
        for key, value in (("old_supplier_calls", 0), ("provider_http_calls", False), ("safe_to_write_now", True), ("rows_examined", True), ("nonbg7_private_files_read", 1)):
            bad = copy.deepcopy(data)
            bad[key] = value
            with self.assertRaises(ValueError):
                m.validate_result(bad)
        bad = copy.deepcopy(data)
        bad["extra"] = "DO_NOT_EXPORT"
        with self.assertRaises(ValueError):
            m.validate_result(bad)

    def test_absent_descriptor_cannot_promote_header_or_digest(self):
        data = self.output()
        data["metadata_records"][2]["sha256"] = "a" * 64
        with self.assertRaises(ValueError):
            m.validate_result(data)

    def test_unknown_or_unread_descriptor_cannot_promote_type(self):
        data = self.output()
        data["metadata_records"][2].update(presence="unknown", file_type="regular", size_bytes=20, binding_state="unsafe_or_unavailable")
        with self.assertRaises(ValueError):
            m.validate_result(data)

    def test_public_recovery_requires_complete_six_record_gate(self):
        self.full_old_failed_terminal()
        data = self.output()
        data["metadata_records"][2]["binding_state"] = "old_header_mismatch"
        with self.assertRaises(ValueError):
            m.validate_result(data)

    def test_receipt_exact_types_and_digest(self):
        data = self.output()
        receipt = {k: data[k] for k in m.RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(m.enc(data)).hexdigest()}
        self.assertTrue(m.validate_result(data, receipt, "a" * 40))
        receipt["accepted"] = False
        with self.assertRaises(ValueError):
            m.validate_result(data, receipt, "a" * 40)

    def test_strict_timestamp(self):
        data = self.output()
        data["captured_at_utc"] += ".123"
        with self.assertRaises(ValueError):
            m.validate_result(data)

    def test_capture_failure_durable_placeholder_receipt_exit_two_private_error_hidden(self):
        project = self.base / "anytoour.ru"
        project.mkdir()
        child = self.private / "operations" / m.OP
        child.mkdir()
        m.save(child / "reservation.json", {"operation": m.OP, "batch": m.BATCH, "source_sha": "a" * 40, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"})
        stdout = io.StringIO()
        with mock.patch.dict(os.environ, {"MATCH_SOURCE_SHA": "a" * 40}), mock.patch.object(m, "capture_metadata", side_effect=ValueError("VERY_PRIVATE_ERROR_VALUE")), contextlib.redirect_stdout(stdout):
            self.assertEqual(m.execute(project, child, FIXTURE), 2)
        data = json.loads((child / "result.json").read_bytes())
        receipt = json.loads((child / "receipt.json").read_bytes())
        self.assertTrue(m.validate_result(data, receipt, "a" * 40))
        self.assertEqual(data["classification"], "capture_failed")
        self.assertEqual(json.loads((child / "current-input.json").read_bytes())["state"], "capture_failed")
        self.assertEqual(receipt["private_input_sha256"], hashlib.sha256((child / "current-input.json").read_bytes()).hexdigest())
        self.assertNotIn("VERY_PRIVATE_ERROR_VALUE", stdout.getvalue())
        self.assertNotIn("VERY_PRIVATE_ERROR_VALUE", (child / "result.json").read_text())


if __name__ == "__main__":
    unittest.main()
