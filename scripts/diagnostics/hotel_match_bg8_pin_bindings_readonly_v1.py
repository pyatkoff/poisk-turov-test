#!/usr/bin/env python3
"""Verify five retained metadata files. Never open original reference paths."""
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import stat
import sys

OP = "int-andromeda-match-bg8-pin-bindings-readonly-20261004-v1"
BATCH = "bg8-pin-bindings-20261004"
MODE = "match-bg8-pin-bindings-readonly"
MANIFEST_SHA = "4a14f5b4c641d80e378e1358341da09de17dc35478087bff134f63879c14ec1c"
SCHEMA = "match-bg8-pin-bindings-readonly-result/1"
SUCCESS = "completed_read_only_bg8_pins"
FAILED = "terminal_failed_no_replay"
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", *NO_EFFECTS, *FALSE_FLAGS, "no_replay")
SHA = re.compile(r"[0-9a-f]{64}")
HEAD = re.compile(r"[0-9a-f]{40}")
STAGES = ("bg18_private_unavailable", "bg18_private_payload_mismatch", "native_current_unavailable_or_digest", "native_current_lineage_mismatch", "selected_reference_metadata_mismatch", "bg18_selected_metadata_mismatch", "consumed_bg8_input_unavailable_or_digest", "consumed_bg8_input_lineage_mismatch", "consumed_bg8_result_unavailable_or_digest", "consumed_bg8_result_lineage_mismatch", "consumed_bg8_receipt_unavailable_or_binding", "metadata_capture_failed", "public_result_validation_failed")
ROSTER = (("205729", "625414997", 9283, "102625414997"), ("2000041008", "610121438", 62868, "102610121438"), ("2000052316", "610144591", 70457, "102610144591"), ("2000059209", "610155352", 67000, "102610155352"), ("2000060910", "610175943", 72889, "102610175943"), ("2000062548", "610149698", 72865, "102610149698"), ("2000062557", "610179507", 75791, "102610179507"), ("2000086021", "668981793", 316, "102668981793"))


def enc(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False) + "\n").encode()


def canonical(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False) + "\n").encode()


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def equal_typed(actual, expected):
    if type(actual) is not type(expected):
        return False
    if isinstance(expected, dict):
        return set(actual) == set(expected) and all(equal_typed(actual[k], v) for k, v in expected.items())
    if isinstance(expected, list):
        return len(actual) == len(expected) and all(equal_typed(a, b) for a, b in zip(actual, expected))
    return actual == expected


def require_fields(value, expected):
    if not isinstance(value, dict) or any(k not in value or not equal_typed(value[k], v) for k, v in expected.items()):
        raise ValueError("metadata_binding")


def parsed(raw):
    def pairs(items):
        out = {}
        for k, v in items:
            if k in out:
                raise ValueError("duplicate_key")
            out[k] = v
        return out
    value = json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: (_ for _ in ()).throw(ValueError("nonfinite")))
    if not isinstance(value, dict):
        raise ValueError("metadata_object")
    return value


def file_bytes(path, maximum):
    path = pathlib.Path(path)
    if not path.is_absolute() or path.resolve() != path or path.is_symlink():
        raise ValueError("metadata_path")
    fd = os.open(path, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))
    try:
        info = os.fstat(fd)
        if not stat.S_ISREG(info.st_mode) or not 0 < info.st_size <= maximum:
            raise ValueError("metadata_cap")
        with os.fdopen(fd, "rb", closefd=False) as handle:
            raw = handle.read(maximum + 1)
        if len(raw) != info.st_size or not 0 < len(raw) <= maximum:
            raise ValueError("metadata_changed_or_cap")
        return raw
    finally:
        os.close(fd)


def save(path, value):
    path = pathlib.Path(path)
    if path.parent.resolve() != path.parent or not path.parent.is_dir() or path.parent.is_symlink():
        raise ValueError("output_scope")
    raw = enc(value)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0), 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as handle:
            if handle.write(raw) != len(raw):
                raise ValueError("short_write")
            handle.flush()
            os.fsync(fd)
    finally:
        os.close(fd)
    parent = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
    try:
        os.fsync(parent)
    finally:
        os.close(parent)
    if file_bytes(path, len(raw)) != raw:
        raise ValueError("durable_readback")
    return digest(raw)


def manifest(path):
    raw = file_bytes(path, 65536)
    if digest(raw) != MANIFEST_SHA:
        raise ValueError("manifest_digest")
    fixture = parsed(raw)
    require_fields(fixture, {"schema": "match-bg8-pin-bindings-readonly/1", "operation": OP, "batch": BATCH, "mode": MODE, "requested_rows": 8, "operator_ids": [18], **dict.fromkeys(FALSE_FLAGS, False), "source_namespace_bridge_verified": False, **dict.fromkeys(("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "maximum_writes"), 0)})
    rows = fixture["rows"]
    if not isinstance(rows, list) or len(rows) != 8 or tuple((r["catalog_id"], r["source_native_id"], r["target_tv_hotel_id"], r["full_bg_key"]) for r in rows) != ROSTER:
        raise ValueError("manifest_roster")
    for row in rows:
        require_fields(row, {"source_namespace": "operator_115", "target_operator_id": 18})
        if type(row["target_tv_hotel_id"]) is not int:
            raise ValueError("manifest_id_type")
    refs = [r for row in rows for r in row["raw_references"]]
    if len(refs) != 18 or len({r["source_file"] for r in refs}) != 15:
        raise ValueError("manifest_reference_count")
    require_fields(fixture["limits"], {"native_current_bytes": 8388608, "bg18_private_bytes": 262144, "consumed_metadata_file_bytes": 1048576, "metadata_files": 5, "original_raw_files": 0})
    return fixture


class CaptureFailure(Exception):
    def __init__(self, stage):
        super().__init__(stage)
        self.stage = stage


def checked(stage, action):
    try:
        return action()
    except Exception:
        raise CaptureFailure(stage) from None


def ref_key(ref):
    return (ref["source_file"], ref["sha256"], ref["json_pointer"])


def capture_metadata(private_root, fixture):
    """Exactly five fixed metadata files; source_file strings are compared only."""
    private_root = pathlib.Path(private_root)
    inputs, limits = fixture["inputs"], fixture["limits"]
    records = []
    def read(role, relpath, cap, pin=None):
        # These paths come only from the SHA-pinned fixture, never from row refs.
        raw = file_bytes(private_root / relpath, cap)
        if pin is not None and digest(raw) != pin:
            raise ValueError("metadata_digest")
        value = parsed(raw)
        records.append({"role": role, "sha256": digest(raw), "bytes": len(raw)})
        return value
    b = inputs["bg18_private"]
    bg = checked("bg18_private_unavailable", lambda: read("bg18_private_result", b["path"], limits["bg18_private_bytes"]))
    def bg_header():
        if digest(canonical(bg)) != b["canonical_payload_sha256"] or records[0]["sha256"] == b["envelope_result_sha256"]:
            raise ValueError("artifact_role_or_payload")
        require_fields(bg, {"operation": b["operation"], "source_sha": b["source_sha"], "input_sha256": b["input_sha256"], "batch": "native110-20260928", "state": "completed_bg_original_fields_review", "raw_files_read": 22, "raw_bytes_read": 847514, "database_reads": 0, "database_writes": 0, "mapping_writes": 0, "provider_http_calls": 0, "safe_to_write_now": False, "no_replay": True})
    checked("bg18_private_payload_mismatch", bg_header)
    n = inputs["native_current"]
    current = checked("native_current_unavailable_or_digest", lambda: read("native_current_manifest", n["path"], limits["native_current_bytes"], n["sha256"]))
    checked("native_current_lineage_mismatch", lambda: require_fields(current, {k: n[k] for k in ("schema", "operation", "batch", "source_sha")} | {"provider_http_calls": 0, "database_writes": 0, "mapping_writes": 0, "safe_to_write_now": False, "no_replay": True}))
    rows = []
    def selected_refs():
        facts = current["saved_evidence"]["source_facts"]
        if not isinstance(facts, dict):
            raise ValueError("facts")
        for row in fixture["rows"]:
            selected = [f for f in facts.get(row["catalog_id"], []) if f.get("namespace") == row["source_namespace"] and f.get("native_id") == row["source_native_id"]]
            if len(selected) != 1:
                raise ValueError("fact_scope")
            raw = selected[0]["raw"]
            require_fields(raw, {"raw_verified": True, "failures": []})
            refs = raw["references"]
            if sorted(ref_key(r) for r in refs) != sorted(ref_key(r) for r in row["raw_references"]) or any(r.get("verified") is not True for r in refs):
                raise ValueError("reference_metadata")
    checked("selected_reference_metadata_mismatch", selected_refs)
    def selected_bg():
        if not isinstance(bg["rows"], list) or len(bg["rows"]) != 18:
            raise ValueError("bg_rows")
        for row in fixture["rows"]:
            found = [r for r in bg["rows"] if r.get("catalog_id") == row["catalog_id"]]
            if len(found) != 1:
                raise ValueError("bg_row_scope")
            require_fields(found[0], {"tv_hotel_id": row["target_tv_hotel_id"], "samo_native_id": row["source_native_id"], "tv_native_id": row["full_bg_key"], "raw_references_examined": len(row["raw_references"]), "failures": [], "safe_to_write_now": False})
            rows.append({"catalog_id": row["catalog_id"], "source_namespace": "operator_115", "source_native_id": row["source_native_id"], "target_tv_hotel_id": row["target_tv_hotel_id"], "target_operator_id": 18, "target_id_namespace": "tourvisor", "independent_anytour_local_id": None, "expected_reference_count": len(row["raw_references"]), "native_reference_metadata_agrees": True, "bg18_row_metadata_agrees": True, "source_namespace_bridge_verified": False, **dict.fromkeys(FALSE_FLAGS, False)})
    checked("bg18_selected_metadata_mismatch", selected_bg)
    c = inputs["consumed_bg8"]
    old_input = checked("consumed_bg8_input_unavailable_or_digest", lambda: read("consumed_bg8_input", c["input_path"], limits["consumed_metadata_file_bytes"], c["input_sha256"]))
    checked("consumed_bg8_input_lineage_mismatch", lambda: require_fields(old_input, {"schema": "match-bg8-unexported-private-input/1", "operation": c["operation"], "batch": c["batch"], "state": "capture_failed", "reason": "bg8_capture_or_validation_failed"}))
    old_result = checked("consumed_bg8_result_unavailable_or_digest", lambda: read("consumed_bg8_result", c["result_path"], limits["consumed_metadata_file_bytes"], c["result_sha256"]))
    def old_lineage():
        require_fields(old_result, {"schema": "match-bg8-unexported-fields-readonly-result/1", "operation": c["operation"], "batch": c["batch"], "source_sha": c["source_sha"], "state": FAILED, "private_input_sha256": c["input_sha256"], "reason": "bg8_capture_or_validation_failed", "rows": [], "rows_examined": 0, "requested_rows": 8, "operator_ids": [18], "raw_files_attempted": 0, "raw_files_read": 0, "raw_bytes_read": 0, "raw_references_verified": 0, "selector_candidate_rows": 0, "hold_counts": {}, "source_namespace_bridge_verified": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)})
        if old_result.get("inputs", {}).get("native_current", {}).get("sha256") != n["sha256"] or old_result.get("inputs", {}).get("bg18_terminal", {}).get("sha256") != b["envelope_result_sha256"]:
            raise ValueError("old_input_binding")
    checked("consumed_bg8_result_lineage_mismatch", old_lineage)
    old_receipt = checked("consumed_bg8_receipt_unavailable_or_binding", lambda: read("consumed_bg8_receipt", c["receipt_path"], limits["consumed_metadata_file_bytes"]))
    expected_receipt = {k: old_result[k] for k in RECEIPT_KEYS} | {"result_sha256": c["result_sha256"]}
    checked("consumed_bg8_receipt_unavailable_or_binding", lambda: None if equal_typed(old_receipt, expected_receipt) else (_ for _ in ()).throw(ValueError("receipt_binding")))
    return {"schema": "match-bg8-pin-bindings-private-input/1", "operation": OP, "batch": BATCH, "state": "metadata_bound", "metadata_files": records, "rows": rows, "bg18_binding": {"private_result_sha256": records[0]["sha256"], "private_result_bytes": records[0]["bytes"], "canonical_payload_sha256": b["canonical_payload_sha256"], "envelope_result_sha256": b["envelope_result_sha256"], "private_bytes_equal_envelope_bytes": False, "canonical_payload_matches_verified_envelope_payload": True}, "consumed_bg8_binding": {"operation": c["operation"], "source_sha": c["source_sha"], "control_sha": c["control_sha"], "control_binding_basis": "canonical_terminal_receipt_context", "private_input_sha256": c["input_sha256"], "result_sha256": c["result_sha256"], "receipt_sha256": records[-1]["sha256"], "receipt_bytes": records[-1]["bytes"], "terminal_receipt_matches_result": True, "consumed_operation_reinvoked": False}, "original_raw_files_read": 0}


def result(capture, private_sha, source_sha, fixture, stage=None):
    return {"schema": SCHEMA, "operation": OP, "batch": BATCH, "source_sha": source_sha, "state": FAILED if stage else SUCCESS, "reason": "bg8_metadata_pin_binding_failed" if stage else None, "failure_stage": stage, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "fixture_sha256": MANIFEST_SHA, "requested_rows": 8, "operator_ids": [18], "rows_examined": 0 if stage else 8, "rows": [] if stage else capture["rows"], "metadata_files_bound": 0 if stage else 5, "metadata_bytes_bound": 0 if stage else sum(r["bytes"] for r in capture["metadata_files"]), "metadata_files": [] if stage else capture["metadata_files"], "bg18_binding": None if stage else capture["bg18_binding"], "consumed_bg8_binding": None if stage else capture["consumed_bg8_binding"], "original_raw_files_read": 0, "global_saved_context_only": True, "source_namespace_bridge_verified": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}


def validate_result(data, receipt=None, expected_source=None):
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_bg8_pin_bindings_readonly_v1.json")
    base = result(None, "0" * 64, "0" * 40, fixture, "metadata_capture_failed")
    if not isinstance(data, dict) or set(data) != set(base):
        raise ValueError("result_keys")
    require_fields(data, {"schema": SCHEMA, "operation": OP, "batch": BATCH, "fixture_sha256": MANIFEST_SHA, "requested_rows": 8, "operator_ids": [18], "original_raw_files_read": 0, "global_saved_context_only": True, "source_namespace_bridge_verified": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)})
    if not isinstance(data["source_sha"], str) or not HEAD.fullmatch(data["source_sha"]) or (expected_source is not None and data["source_sha"] != expected_source) or not isinstance(data["private_input_sha256"], str) or not SHA.fullmatch(data["private_input_sha256"]):
        raise ValueError("result_digest_or_source")
    if not isinstance(data["captured_at_utc"], str) or not re.fullmatch(r"[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z", data["captured_at_utc"]):
        raise ValueError("result_timestamp")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    if data["state"] == FAILED:
        require_fields(data, {"reason": "bg8_metadata_pin_binding_failed", "rows_examined": 0, "rows": [], "metadata_files_bound": 0, "metadata_bytes_bound": 0, "metadata_files": [], "bg18_binding": None, "consumed_bg8_binding": None})
        if data["failure_stage"] not in STAGES:
            raise ValueError("result_failure_stage")
    elif data["state"] == SUCCESS:
        require_fields(data, {"reason": None, "failure_stage": None, "rows_examined": 8, "metadata_files_bound": 5})
        rows = data["rows"]
        expected_rows = []
        for row in fixture["rows"]:
            expected_rows.append({"catalog_id": row["catalog_id"], "source_namespace": "operator_115", "source_native_id": row["source_native_id"], "target_tv_hotel_id": row["target_tv_hotel_id"], "target_operator_id": 18, "target_id_namespace": "tourvisor", "independent_anytour_local_id": None, "expected_reference_count": len(row["raw_references"]), "native_reference_metadata_agrees": True, "bg18_row_metadata_agrees": True, "source_namespace_bridge_verified": False, **dict.fromkeys(FALSE_FLAGS, False)})
        if not equal_typed(rows, expected_rows):
            raise ValueError("result_rows")
        records = data["metadata_files"]
        roles = ("bg18_private_result", "native_current_manifest", "consumed_bg8_input", "consumed_bg8_result", "consumed_bg8_receipt")
        caps = (262144, 8388608, 1048576, 1048576, 1048576)
        if not isinstance(records, list) or len(records) != 5:
            raise ValueError("result_metadata_count")
        for record, role, cap in zip(records, roles, caps):
            if not isinstance(record, dict) or set(record) != {"role", "sha256", "bytes"} or record["role"] != role or not isinstance(record["sha256"], str) or not SHA.fullmatch(record["sha256"]) or type(record["bytes"]) is not int or not 0 < record["bytes"] <= cap:
                raise ValueError("result_metadata_shape")
        if type(data["metadata_bytes_bound"]) is not int or data["metadata_bytes_bound"] != sum(r["bytes"] for r in records):
            raise ValueError("result_metadata_bytes")
        b, c, n = fixture["inputs"]["bg18_private"], fixture["inputs"]["consumed_bg8"], fixture["inputs"]["native_current"]
        if [r["sha256"] for r in records[1:4]] != [n["sha256"], c["input_sha256"], c["result_sha256"]] or records[0]["sha256"] == b["envelope_result_sha256"]:
            raise ValueError("result_input_pins")
        eb = {"private_result_sha256": records[0]["sha256"], "private_result_bytes": records[0]["bytes"], "canonical_payload_sha256": b["canonical_payload_sha256"], "envelope_result_sha256": b["envelope_result_sha256"], "private_bytes_equal_envelope_bytes": False, "canonical_payload_matches_verified_envelope_payload": True}
        ec = {"operation": c["operation"], "source_sha": c["source_sha"], "control_sha": c["control_sha"], "control_binding_basis": "canonical_terminal_receipt_context", "private_input_sha256": c["input_sha256"], "result_sha256": c["result_sha256"], "receipt_sha256": records[-1]["sha256"], "receipt_bytes": records[-1]["bytes"], "terminal_receipt_matches_result": True, "consumed_operation_reinvoked": False}
        if not equal_typed(data["bg18_binding"], eb) or not equal_typed(data["consumed_bg8_binding"], ec):
            raise ValueError("result_binding_shape")
    else:
        raise ValueError("result_state")
    if receipt is not None and not equal_typed(receipt, {k: data[k] for k in RECEIPT_KEYS} | {"result_sha256": digest(enc(data))}):
        raise ValueError("result_receipt_binding")
    return data


def execute(root, opdir, manifest_path):
    root, opdir, manifest_path = pathlib.Path(root), pathlib.Path(opdir), pathlib.Path(manifest_path)
    fixture = manifest(manifest_path)
    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_bg8_pin_bindings_readonly_v1.json"
    source = os.environ.get("MATCH_SOURCE_SHA", "")
    if not root.is_dir() or root.resolve() != root or root.is_symlink() or root.name != "anytoour.ru" or not opdir.is_dir() or opdir.resolve() != opdir or opdir.is_symlink() or opdir.name != OP or opdir.parent.name != "operations" or opdir.parent.parent.name != ".anytoour-match" or manifest_path != expected_fixture or not HEAD.fullmatch(source):
        raise ValueError("runtime_scope")
    reservation = parsed(file_bytes(opdir / "reservation.json", 1048576))
    require_fields(reservation, {"operation": OP, "batch": BATCH, "source_sha": source, "state": "reserved_before_retained_read", "provider_http_calls": 0, "maximum_writes": 0})
    if any((opdir / name).exists() or (opdir / name).is_symlink() for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json")):
        raise ValueError("terminal_no_replay")
    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": source, "no_replay": True})
    capture, stage = None, None
    try:
        capture = capture_metadata(opdir.parent.parent, fixture)
    except CaptureFailure as failure:
        stage = failure.stage
    except Exception:
        stage = "metadata_capture_failed"
    if stage:
        capture = {"schema": "match-bg8-pin-bindings-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "failure_stage": stage, "reason": "bg8_metadata_pin_binding_failed", "original_raw_files_read": 0}
    private_sha = save(opdir / "current-input.json", capture)
    output = result(capture, private_sha, source, fixture, stage)
    try:
        validate_result(output, expected_source=source)
    except Exception:
        output = result(None, private_sha, source, fixture, "public_result_validation_failed")
        validate_result(output, expected_source=source)
    result_sha = save(opdir / "result.json", output)
    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": result_sha}
    validate_result(output, receipt, source)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: output[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))
    return 2 if output["state"] == FAILED else 0


def self_test():
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_bg8_pin_bindings_readonly_v1.json")
    assert canonical({"zero": 0}) != canonical({"zero": False})
    assert not equal_typed({"zero": 0}, {"zero": False})
    output = result(None, "0" * 64, "0" * 40, fixture, "metadata_capture_failed")
    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest(enc(output))}
    validate_result(output, receipt, "0" * 40)
    print("MATCH_BG8_PIN_BINDINGS_READONLY_V1_SELFTEST_OK")


if __name__ == "__main__":
    if sys.argv[1:] == ["--self-test"]:
        self_test()
    elif sys.argv[1:] == ["--execute"]:
        raise SystemExit(execute(os.environ["ANYTOUR_ROOT"], os.environ["MATCH_OPERATION_DIR"], os.environ["MATCH_MANIFEST_PATH"]))
    else:
        raise SystemExit("disabled")
