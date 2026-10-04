#!/usr/bin/env python3
"""Focused metadata lineage, fixed scope and terminal durability checks."""
import contextlib
import copy
import importlib.util
import io
import json
import os
import pathlib
import tempfile
import unittest
from unittest import mock

SOURCE = pathlib.Path(__file__).resolve().parents[1] / "scripts/diagnostics/hotel_match_bg8_pin_bindings_readonly_v1.py"
spec = importlib.util.spec_from_file_location("bp8", SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
FIXTURE = SOURCE.with_name("fixtures") / "hotel_match_bg8_pin_bindings_readonly_v1.json"


class MetadataTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = pathlib.Path(self.temp.name)
        self.fixture = copy.deepcopy(m.manifest(FIXTURE))
        i = self.fixture["inputs"]
        b, n, c = i["bg18_private"], i["native_current"], i["consumed_bg8"]
        rows = []
        facts = {}
        for row in self.fixture["rows"]:
            rows.append({"catalog_id": row["catalog_id"], "tv_hotel_id": row["target_tv_hotel_id"], "samo_native_id": row["source_native_id"], "tv_native_id": row["full_bg_key"], "raw_references_examined": len(row["raw_references"]), "failures": [], "safe_to_write_now": False})
            facts[row["catalog_id"]] = [{"namespace": row["source_namespace"], "native_id": row["source_native_id"], "raw": {"raw_verified": True, "failures": [], "references": [ref | {"verified": True} for ref in row["raw_references"]]}}]
        rows.extend({"catalog_id": str(9000000000 + x)} for x in range(10))
        self.current = {"schema": n["schema"], "operation": n["operation"], "batch": n["batch"], "source_sha": n["source_sha"], "saved_evidence": {"source_facts": facts}, "provider_http_calls": 0, "database_writes": 0, "mapping_writes": 0, "safe_to_write_now": False, "no_replay": True}
        n["sha256"] = self.write(n["path"], m.enc(self.current))
        b["input_sha256"] = n["sha256"]
        self.bg = {"operation": b["operation"], "source_sha": b["source_sha"], "input_sha256": b["input_sha256"], "batch": "native110-20260928", "state": "completed_bg_original_fields_review", "raw_files_read": 22, "raw_bytes_read": 847514, "database_reads": 0, "database_writes": 0, "mapping_writes": 0, "provider_http_calls": 0, "safe_to_write_now": False, "no_replay": True, "rows": rows}
        # Compact child bytes differ from both outer envelope and canonical encoding.
        self.private_bg_bytes = json.dumps(self.bg, separators=(",", ":"), ensure_ascii=False).encode()
        self.write(b["path"], self.private_bg_bytes)
        b["canonical_payload_sha256"] = m.digest(m.canonical(self.bg))
        b["envelope_result_sha256"] = m.digest(m.enc({"match_native110_bg_evidence": self.bg, "status": "completed"}))
        old_input = {"schema": "match-bg8-unexported-private-input/1", "operation": c["operation"], "batch": c["batch"], "state": "capture_failed", "reason": "bg8_capture_or_validation_failed"}
        c["input_sha256"] = self.write(c["input_path"], m.enc(old_input))
        self.old_result = {"schema": "match-bg8-unexported-fields-readonly-result/1", "operation": c["operation"], "batch": c["batch"], "source_sha": c["source_sha"], "state": m.FAILED, "private_input_sha256": c["input_sha256"], "reason": "bg8_capture_or_validation_failed", "rows": [], "rows_examined": 0, "requested_rows": 8, "operator_ids": [18], "raw_files_attempted": 0, "raw_files_read": 0, "raw_bytes_read": 0, "raw_references_verified": 0, "selector_candidate_rows": 0, "hold_counts": {}, "source_namespace_bridge_verified": False, "no_replay": True, "inputs": {"native_current": {"sha256": n["sha256"]}, "bg18_terminal": {"sha256": b["envelope_result_sha256"]}}, **dict.fromkeys(m.NO_EFFECTS, 0), **dict.fromkeys(m.FALSE_FLAGS, False)}
        c["result_sha256"] = self.write(c["result_path"], m.enc(self.old_result))
        self.old_receipt = {k: self.old_result[k] for k in m.RECEIPT_KEYS} | {"result_sha256": c["result_sha256"]}
        self.write(c["receipt_path"], m.enc(self.old_receipt))

    def write(self, relpath, raw):
        path = self.root / relpath
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(raw)
        return m.digest(raw)

    def capture(self):
        return m.capture_metadata(self.root, self.fixture)

    def assert_stage(self, stage):
        with self.assertRaises(m.CaptureFailure) as caught:
            self.capture()
        self.assertEqual(caught.exception.stage, stage)

    def test_five_metadata_files_only_and_private_byte_role(self):
        opened = []
        real = m.file_bytes
        def observed(path, cap):
            opened.append(path)
            return real(path, cap)
        with mock.patch.object(m, "file_bytes", side_effect=observed):
            capture = self.capture()
        self.assertEqual(len(opened), 5)
        self.assertEqual({str(p.relative_to(self.root)) for p in opened}, {self.fixture["inputs"]["bg18_private"]["path"], self.fixture["inputs"]["native_current"]["path"], *[self.fixture["inputs"]["consumed_bg8"][k] for k in ("input_path", "result_path", "receipt_path")]})
        self.assertTrue(all("evidence-private" not in str(p) for p in opened))
        self.assertEqual(capture["original_raw_files_read"], 0)
        self.assertEqual(len(capture["rows"]), 8)
        binding = capture["bg18_binding"]
        self.assertEqual(binding["private_result_sha256"], m.digest(self.private_bg_bytes))
        self.assertNotEqual(binding["private_result_sha256"], binding["canonical_payload_sha256"])
        self.assertFalse(binding["private_bytes_equal_envelope_bytes"])
        self.assertTrue(binding["canonical_payload_matches_verified_envelope_payload"])
        self.assertFalse(any(r["source_namespace_bridge_verified"] for r in capture["rows"]))
        self.assertNotIn("source_file", m.enc(capture).decode())

    def test_outer_envelope_cannot_be_private_child(self):
        b = self.fixture["inputs"]["bg18_private"]
        self.write(b["path"], m.enc({"match_native110_bg_evidence": self.bg, "status": "completed"}))
        self.assert_stage("bg18_private_payload_mismatch")

    def test_canonical_payload_equality_is_type_sensitive(self):
        b = self.fixture["inputs"]["bg18_private"]
        self.bg["database_reads"] = False
        self.write(b["path"], m.enc(self.bg))
        self.assert_stage("bg18_private_payload_mismatch")
        self.assertNotEqual(m.canonical({"v": 0}), m.canonical({"v": False}))
        self.assertNotEqual(m.canonical({"v": 0}), m.canonical({"v": 0.0}))

    def test_reference_metadata_mismatch_never_follows_path(self):
        n = self.fixture["inputs"]["native_current"]
        self.current["saved_evidence"]["source_facts"]["205729"][0]["raw"]["references"][0]["source_file"] = "/never-open-secret-original.json"
        n["sha256"] = self.write(n["path"], m.enc(self.current))
        self.assert_stage("selected_reference_metadata_mismatch")

    def test_ambiguous_source_fact_is_hold_not_identity(self):
        n = self.fixture["inputs"]["native_current"]
        facts = self.current["saved_evidence"]["source_facts"]["205729"]
        facts.append(copy.deepcopy(facts[0]))
        n["sha256"] = self.write(n["path"], m.enc(self.current))
        self.assert_stage("selected_reference_metadata_mismatch")

    def test_consumed_result_wrong_source_rejected_even_new_byte_pin(self):
        c = self.fixture["inputs"]["consumed_bg8"]
        self.old_result["source_sha"] = "f" * 40
        c["result_sha256"] = self.write(c["result_path"], m.enc(self.old_result))
        self.assert_stage("consumed_bg8_result_lineage_mismatch")

    def test_consumed_receipt_bool_zero_rejected(self):
        c = self.fixture["inputs"]["consumed_bg8"]
        self.old_receipt["database_reads"] = False
        self.write(c["receipt_path"], m.enc(self.old_receipt))
        self.assert_stage("consumed_bg8_receipt_unavailable_or_binding")

    def test_safe_path_and_cap_and_duplicate_keys(self):
        b = self.fixture["inputs"]["bg18_private"]
        path = self.root / b["path"]
        path.unlink()
        target = self.root / "alternate.json"
        target.write_bytes(self.private_bg_bytes)
        path.symlink_to(target)
        self.assert_stage("bg18_private_unavailable")
        path.unlink()
        path.write_bytes(b"x" * 262145)
        self.assert_stage("bg18_private_unavailable")
        path.write_bytes(b'{"state": 0, "state": 1}')
        self.assert_stage("bg18_private_unavailable")

    def public_success(self):
        f = m.manifest(FIXTURE)
        # Public validator binds production immutable input digests independently.
        capture = self.capture()
        records = capture["metadata_files"]
        for record, pin in zip(records[1:4], (f["inputs"]["native_current"]["sha256"], f["inputs"]["consumed_bg8"]["input_sha256"], f["inputs"]["consumed_bg8"]["result_sha256"])):
            record["sha256"] = pin
        capture["bg18_binding"].update(canonical_payload_sha256=f["inputs"]["bg18_private"]["canonical_payload_sha256"], envelope_result_sha256=f["inputs"]["bg18_private"]["envelope_result_sha256"])
        capture["consumed_bg8_binding"].update(private_input_sha256=f["inputs"]["consumed_bg8"]["input_sha256"], result_sha256=f["inputs"]["consumed_bg8"]["result_sha256"])
        return m.result(capture, "a" * 64, "b" * 40, f)

    def test_strict_public_result_pins_types_and_no_authority(self):
        output = self.public_success()
        receipt = {k: output[k] for k in m.RECEIPT_KEYS} | {"result_sha256": m.digest(m.enc(output))}
        m.validate_result(output, receipt, "b" * 40)
        for path, value in [("original_raw_files_read", False), ("database_reads", False), ("metadata_bytes_bound", 0), ("source_namespace_bridge_verified", True)]:
            bad = copy.deepcopy(output)
            bad[path] = value
            with self.assertRaises(ValueError):
                m.validate_result(bad)
        receipt["accepted"] = False
        with self.assertRaises(ValueError):
            m.validate_result(output, receipt)
        bad = copy.deepcopy(output)
        bad["rows"][0]["source_file"] = "never-public"
        with self.assertRaises(ValueError):
            m.validate_result(bad)

    def test_terminal_failure_is_durable_constant_stage_no_replay(self):
        project = self.root / "anytoour.ru"
        project.mkdir()
        private = self.root / ".anytoour-match"
        opdir = private / "operations" / m.OP
        opdir.mkdir(parents=True)
        reservation = {"operation": m.OP, "batch": m.BATCH, "source_sha": "b" * 40, "state": "reserved_before_retained_read", "provider_http_calls": 0, "maximum_writes": 0}
        m.save(opdir / "reservation.json", reservation)
        stdout = io.StringIO()
        with mock.patch.dict(os.environ, {"MATCH_SOURCE_SHA": "b" * 40}), contextlib.redirect_stdout(stdout), mock.patch.object(os, "fsync", wraps=os.fsync) as fsync:
            code = m.execute(project, opdir, FIXTURE)
        self.assertEqual(code, 2)
        self.assertGreaterEqual(fsync.call_count, 8)
        output = m.parsed((opdir / "result.json").read_bytes())
        receipt = m.parsed((opdir / "receipt.json").read_bytes())
        private_input = m.parsed((opdir / "current-input.json").read_bytes())
        self.assertEqual(output["failure_stage"], "bg18_private_unavailable")
        self.assertEqual(private_input["failure_stage"], output["failure_stage"])
        self.assertEqual(output["private_input_sha256"], m.digest((opdir / "current-input.json").read_bytes()))
        m.validate_result(output, receipt, "b" * 40)
        self.assertEqual(set(json.loads(stdout.getvalue())), {"state", "rows_examined", "accepted", "written"})
        with mock.patch.dict(os.environ, {"MATCH_SOURCE_SHA": "b" * 40}), self.assertRaisesRegex(ValueError, "terminal_no_replay"):
            m.execute(project, opdir, FIXTURE)


if __name__ == "__main__":
    unittest.main()
