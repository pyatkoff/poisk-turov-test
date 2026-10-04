#!/usr/bin/env python3
"""Reconcile six old URL5 metadata records; never run a URL-field projection."""
import collections
import datetime as dt
import hashlib
import importlib.util
import json
import os
import pathlib
import re
import stat
import sys

OP = "int-andromeda-match-nonbg5-url-paths-terminal-readback-20261004-v1"
BATCH = "nonbg5-url-paths-terminal-readback-20261004"
MODE = "match-nonbg5-url-paths-terminal-readback"
MANIFEST_SHA = "515ccfc283244713f6ecd3b87c3bc5829e1173d9468a66e24d2fa54379950151"
OLD_OP = "int-andromeda-match-nonbg5-retained-url-paths-20261004-v1"
OLD_BATCH = "nonbg5-retained-url-paths-20261004"
OLD_SOURCE = "5e802eacbe43c0925902ce159ef5669b5cd0c7eb"
OLD_SOURCE_SHA256 = "8437cdb48cc571ff273cfdb95f9e8c5aca9cde5a6586cb93d99f60303961b356"
OLD_FIXTURE_SHA256 = "a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269"
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", *NO_EFFECTS, *FALSE_FLAGS, "no_replay")
STATES = ("completed_read_only_nonbg5_url_paths_terminal_readback", "completed_read_only_nonbg5_url_paths_terminal_readback_incomplete", "terminal_failed_no_replay")
OLD_STATES = ("completed_read_only_nonbg5_url_paths", "completed_read_only_nonbg5_url_paths_incomplete", "terminal_failed_no_replay")
FAILURE_STAGES = ("private_root_or_metadata_unavailable", "private_capture_or_save_failed", "public_result_validation_failed")
ROLES = ("batch_marker", "reservation", "execution_started", "private_input", "result", "receipt")
RECORD_KEYS = {"role", "relative_path", "presence", "file_type", "size_bytes", "sha256", "json_type", "binding_state"}
BINDING_STATES = ("absent", "bound_old_header", "unsafe_or_unavailable", "resource_cap", "json_invalid", "old_header_mismatch")
CLASSIFICATIONS = ("pre_entrypoint_terminal_files_absent_observed", "existing_terminal_verified", "partial_or_unbound_old_terminal_metadata", "capture_failed")
SHA = re.compile(r"[0-9a-f]{64}")


def enc(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False) + "\n").encode()


def parsed(raw):
    def pairs(items):
        out = {}
        for k, v in items:
            if k in out:
                raise ValueError("duplicate_key")
            out[k] = v
        return out
    return json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: (_ for _ in ()).throw(ValueError("nonfinite")))


def equal_typed(actual, expected):
    if type(actual) is not type(expected):
        return False
    if isinstance(expected, dict):
        return set(actual) == set(expected) and all(equal_typed(actual[k], v) for k, v in expected.items())
    if isinstance(expected, list):
        return len(actual) == len(expected) and all(equal_typed(a, b) for a, b in zip(actual, expected))
    return actual == expected


def file_bytes(path, maximum):
    path = pathlib.Path(path)
    if not path.is_absolute() or path.resolve() != path or path.is_symlink():
        raise ValueError("metadata_path")
    fd = os.open(path, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))
    try:
        info = os.fstat(fd)
        if not stat.S_ISREG(info.st_mode) or not 0 < info.st_size <= maximum:
            raise ValueError("metadata_cap")
        with os.fdopen(fd, "rb", closefd=False) as stream:
            raw = stream.read(maximum + 1)
        if len(raw) != info.st_size or len(raw) > maximum:
            raise ValueError("metadata_changed_or_cap")
        return raw
    finally:
        os.close(fd)


def save(path, value):
    path = pathlib.Path(path)
    if path.parent.resolve() != path.parent or path.parent.is_symlink() or not path.parent.is_dir():
        raise ValueError("output_directory")
    raw = enc(value)
    with open(path, "xb") as stream:
        os.chmod(path, 0o600)
        if stream.write(raw) != len(raw):
            raise ValueError("short_write")
        stream.flush()
        os.fsync(stream.fileno())
    fd = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
    try:
        os.fsync(fd)
    finally:
        os.close(fd)
    if path.read_bytes() != raw:
        raise ValueError("durable_readback")
    return hashlib.sha256(raw).hexdigest()


def manifest(path):
    raw = file_bytes(pathlib.Path(path), 1048576)
    if hashlib.sha256(raw).hexdigest() != MANIFEST_SHA:
        raise ValueError("fixture_digest")
    value = parsed(raw)
    if not isinstance(value, dict) or value["schema"] != "match-nonbg5-url-paths-terminal-readback-fixture/1" or value["operation"] != OP or value["batch"] != BATCH or value["mode"] != MODE or tuple(r["role"] for r in value["metadata_records"]) != ROLES:
        raise ValueError("fixture_scope")
    expected_paths = ("nonbg5-retained-url-paths-batch-20261004.json", *("operations/" + OLD_OP + "/" + n for n in ("reservation.json", "execution-started.json", "current-input.json", "result.json", "receipt.json")))
    if tuple(r["relative_path"] for r in value["metadata_records"]) != expected_paths or value["inputs"]["old_operation"] != OLD_OP or value["inputs"]["old_batch"] != OLD_BATCH or value["inputs"]["old_source_sha"] != OLD_SOURCE:
        raise ValueError("fixture_old_scope")
    return value


def old_reservation():
    return {"operation": OLD_OP, "source_sha": OLD_SOURCE, "batch": OLD_BATCH, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}


def header_valid(role, data):
    if not isinstance(data, dict):
        return False
    if role in ("batch_marker", "reservation"):
        return equal_typed(data, old_reservation())
    if role == "execution_started":
        return equal_typed(data, {"operation": OLD_OP, "batch": OLD_BATCH, "source_sha": OLD_SOURCE, "no_replay": True})
    if role == "private_input":
        return data.get("schema") == "match-nonbg5-retained-url-paths-private-input/1" and data.get("operation") == OLD_OP and data.get("batch") == OLD_BATCH
    if role == "result":
        return data.get("schema") == "match-nonbg5-retained-url-paths-readonly-result/1" and data.get("operation") == OLD_OP and data.get("batch") == OLD_BATCH and data.get("source_sha") == OLD_SOURCE and data.get("state") in OLD_STATES
    if role == "receipt":
        return set(data) == {*RECEIPT_KEYS, "result_sha256"} and data.get("operation") == OLD_OP and data.get("batch") == OLD_BATCH and data.get("source_sha") == OLD_SOURCE and data.get("state") in OLD_STATES
    return False


def read_record(private_root, pin):
    path = private_root / pin["relative_path"]
    descriptor = {"role": pin["role"], "relative_path": pin["relative_path"], "presence": "unknown", "file_type": "unknown", "size_bytes": None, "sha256": None, "json_type": None, "binding_state": "unsafe_or_unavailable"}
    # No lstat/open through an unexpected parent; symlink targets are never read.
    if path.parent.resolve() != path.parent or path.parent.is_symlink():
        return descriptor, None
    try:
        info = path.lstat()
    except FileNotFoundError:
        descriptor.update(presence="absent", file_type="absent", binding_state="absent")
        return descriptor, None
    except OSError:
        return descriptor, None
    descriptor.update(presence="present", size_bytes=info.st_size)
    if stat.S_ISLNK(info.st_mode):
        descriptor["file_type"] = "symlink"
        return descriptor, None
    if not stat.S_ISREG(info.st_mode):
        descriptor["file_type"] = "directory" if stat.S_ISDIR(info.st_mode) else "other"
        return descriptor, None
    descriptor["file_type"] = "regular"
    if not 0 < info.st_size <= pin["maximum_bytes"]:
        descriptor["binding_state"] = "resource_cap"
        return descriptor, None
    try:
        raw = file_bytes(path, pin["maximum_bytes"])
    except (OSError, ValueError):
        return descriptor, None
    descriptor.update(size_bytes=len(raw), sha256=hashlib.sha256(raw).hexdigest())
    try:
        data = parsed(raw)
    except (ValueError, UnicodeError):
        descriptor["binding_state"] = "json_invalid"
        return descriptor, None
    descriptor["json_type"] = "object" if type(data) is dict else ("array" if type(data) is list else ("null" if data is None else ("boolean" if type(data) is bool else ("number" if type(data) in (int, float) else "string"))))
    descriptor["binding_state"] = "bound_old_header" if header_valid(pin["role"], data) else "old_header_mismatch"
    return descriptor, data


def load_old_validator(fixture):
    # Import only frozen definitions under a non-main module name. No old
    # execute/capture_retained/project_capture entry point is called.
    stage = pathlib.Path(__file__).resolve().parents[2]
    for role, expected_sha, expected_size in (("old_source_validator", OLD_SOURCE_SHA256, 28459), ("old_source_fixture", OLD_FIXTURE_SHA256, 42821)):
        pin = fixture["inputs"][role]
        raw = file_bytes(stage / pin["path"], expected_size)
        if len(raw) != expected_size or hashlib.sha256(raw).hexdigest() != expected_sha:
            raise ValueError("frozen_old_validator_binding")
    runner = stage / fixture["inputs"]["old_source_validator"]["path"]
    spec = importlib.util.spec_from_file_location("checked_old_url5_terminal_validator", runner)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module.validate_result


def validate_old_private(data, result):
    if result["state"] == "terminal_failed_no_replay":
        expected = {"schema": "match-nonbg5-retained-url-paths-private-input/1", "operation": OLD_OP, "batch": OLD_BATCH, "state": "capture_failed", "reason": result["reason"], "failure_stage": result["failure_stage"]}
        if equal_typed(data, expected):
            return True
        # The failed public result has no row projection against which a
        # successful-shaped private object can be bound. Preserve only its
        # metadata presence/digest; never reopen NF7 or reproject those values.
        raise ValueError("old_failed_result_requires_failed_private_placeholder")
    expected_keys = {"schema", "operation", "batch", "inputs", "metadata_files_bound", "metadata_bytes_bound", "selected_rows", "rows", "complete_source_values_retained_in_prior_private_input", "original_raw_files_read"}
    if not isinstance(data, dict) or set(data) != expected_keys or data["schema"] != "match-nonbg5-retained-url-paths-private-input/1" or data["operation"] != OLD_OP or data["batch"] != OLD_BATCH or not equal_typed(data["inputs"], result["inputs"]) or not equal_typed(data["selected_rows"], [0, 1, 2, 3, 4]) or data["complete_source_values_retained_in_prior_private_input"] is not True or type(data["original_raw_files_read"]) is not int or data["original_raw_files_read"] != 0 or type(data["metadata_files_bound"]) is not int or data["metadata_files_bound"] != 2:
        raise ValueError("old_private_shape")
    if not equal_typed(data["rows"], result["rows"]) or not equal_typed(data["metadata_bytes_bound"], result["metadata_bytes_bound"]):
        raise ValueError("old_private_result_relation")
    return True


def capture_metadata(private_root, fixture):
    if private_root.resolve() != private_root or private_root.is_symlink() or not private_root.is_dir():
        raise ValueError("private_root")
    records, payloads = [], {}
    for pin in fixture["metadata_records"]:
        record, data = read_record(private_root, pin)
        records.append(record)
        payloads[pin["role"]] = data
    recovered = None
    reason = None
    complete = all(r["binding_state"] == "bound_old_header" for r in records)
    if complete:
        try:
            result, receipt, inp = payloads["result"], payloads["receipt"], payloads["private_input"]
            by_role = {r["role"]: r for r in records}
            if receipt["result_sha256"] != by_role["result"]["sha256"] or result["private_input_sha256"] != by_role["private_input"]["sha256"] or receipt["private_input_sha256"] != by_role["private_input"]["sha256"]:
                raise ValueError("old_terminal_digests")
            if hashlib.sha256(enc(receipt)).hexdigest() != by_role["receipt"]["sha256"]:
                raise ValueError("old_canonical_receipt_digest")
            validator = load_old_validator(fixture)
            validator(result, receipt, OLD_SOURCE)
            validate_old_private(inp, result)
            recovered = result
        except Exception:
            reason = "existing_old_terminal_validation_failed"
    markers_bound = all(r["binding_state"] == "bound_old_header" for r in records[:2])
    terminal_absent = all(r["presence"] == "absent" for r in records[2:])
    classification = "existing_terminal_verified" if recovered is not None else ("pre_entrypoint_terminal_files_absent_observed" if markers_bound and terminal_absent else "partial_or_unbound_old_terminal_metadata")
    if classification == "partial_or_unbound_old_terminal_metadata" and reason is None:
        reason = "old_terminal_metadata_incomplete_or_unbound"
    return {"schema": "match-nonbg5-url-paths-terminal-readback-private-input/1", "operation": OP, "batch": BATCH, "inputs": fixture["inputs"], "metadata_records": records, "classification": classification, "recovery_reason": reason, "existing_terminal_summary": recovered, "original_raw_files_read": 0, "nonbg7_private_files_read": 0}


def validate_result(data, receipt=None, expected_source=None):
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_url_paths_terminal_readback_v1.json")
    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "failure_stage", "captured_at_utc", "private_input_sha256", "inputs", "requested_records", "rows_examined", "metadata_records", "classification", "recovery_reason", "existing_terminal_summary", "existing_terminal_result_sha256", "existing_terminal_verified", "recovered_path_candidate_references", "recovered_path_candidate_rows", "metadata_files_bound", "metadata_bytes_bound", "original_raw_files_read", "nonbg7_private_files_read", "old_supplier_calls", "old_database_writes", "old_operation_replayed", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}
    if not isinstance(data, dict) or set(data) != keys or len(enc(data)) > 2097152 or data["schema"] != "match-nonbg5-url-paths-terminal-readback-result/1" or data["operation"] != OP or data["batch"] != BATCH or data["state"] not in STATES:
        raise ValueError("public_scope")
    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or (expected_source is not None and data["source_sha"] != expected_source) or not SHA.fullmatch(data["private_input_sha256"] or "") or not equal_typed(data["inputs"], fixture["inputs"]):
        raise ValueError("public_lineage")
    if any(type(data[k]) is not int or data[k] != 0 for k in (*NO_EFFECTS, "original_raw_files_read", "nonbg7_private_files_read")) or any(data[k] is not False for k in (*FALSE_FLAGS, "old_operation_replayed")) or data["no_replay"] is not True or data["old_supplier_calls"] != "unknown" or data["old_database_writes"] != "unknown":
        raise ValueError("public_authority")
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", data["captured_at_utc"] or ""):
        raise ValueError("public_timestamp")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    if type(data["requested_records"]) is not int or data["requested_records"] != 6 or type(data["rows_examined"]) is not int or not isinstance(data["metadata_records"], list) or data["rows_examined"] != len(data["metadata_records"]) or data["classification"] not in CLASSIFICATIONS:
        raise ValueError("public_records")
    failed = data["state"] == "terminal_failed_no_replay"
    if failed:
        if data["metadata_records"] or data["classification"] != "capture_failed" or data["reason"] != "nonbg5_url_paths_terminal_readback_failed" or data["failure_stage"] not in FAILURE_STAGES or data["existing_terminal_summary"] is not None or data["existing_terminal_result_sha256"] is not None or data["existing_terminal_verified"] is not False or data["recovery_reason"] is not None:
            raise ValueError("public_failed_state")
        if any(type(data[k]) is not int or data[k] != 0 for k in ("metadata_files_bound", "metadata_bytes_bound", "recovered_path_candidate_references", "recovered_path_candidate_rows")):
            raise ValueError("public_failed_counts")
    else:
        if len(data["metadata_records"]) != 6 or data["reason"] is not None or data["failure_stage"] is not None:
            raise ValueError("public_completed_state")
        for record, pin in zip(data["metadata_records"], fixture["metadata_records"]):
            if not isinstance(record, dict) or set(record) != RECORD_KEYS or record["role"] != pin["role"] or record["relative_path"] != pin["relative_path"] or record["presence"] not in ("present", "absent", "unknown") or record["file_type"] not in ("regular", "directory", "symlink", "other", "absent", "unknown") or record["binding_state"] not in BINDING_STATES or record["json_type"] not in (None, "object", "array", "string", "number", "boolean", "null"):
                raise ValueError("public_descriptor")
            if record["size_bytes"] is not None and (type(record["size_bytes"]) is not int or record["size_bytes"] < 0):
                raise ValueError("public_descriptor_size")
            if record["sha256"] is not None and (not isinstance(record["sha256"], str) or not SHA.fullmatch(record["sha256"]) or record["presence"] != "present" or record["file_type"] != "regular" or not 0 < record["size_bytes"] <= pin["maximum_bytes"]):
                raise ValueError("public_descriptor_digest")
            if record["presence"] == "absent" and not equal_typed(record, {"role": pin["role"], "relative_path": pin["relative_path"], "presence": "absent", "file_type": "absent", "size_bytes": None, "sha256": None, "json_type": None, "binding_state": "absent"}):
                raise ValueError("public_absent_descriptor")
            if record["presence"] == "unknown" and not equal_typed(record, {"role": pin["role"], "relative_path": pin["relative_path"], "presence": "unknown", "file_type": "unknown", "size_bytes": None, "sha256": None, "json_type": None, "binding_state": "unsafe_or_unavailable"}):
                raise ValueError("public_unknown_descriptor")
            if record["presence"] == "present" and (record["file_type"] in ("unknown", "absent") or type(record["size_bytes"]) is not int or record["binding_state"] == "absent"):
                raise ValueError("public_present_descriptor")
            if record["file_type"] in ("symlink", "directory", "other") and (record["sha256"] is not None or record["json_type"] is not None or record["binding_state"] != "unsafe_or_unavailable"):
                raise ValueError("public_unread_descriptor")
            if record["binding_state"] in ("resource_cap", "unsafe_or_unavailable") and (record["sha256"] is not None or record["json_type"] is not None):
                raise ValueError("public_unavailable_descriptor")
            if record["binding_state"] == "resource_cap" and (record["file_type"] != "regular" or 0 < record["size_bytes"] <= pin["maximum_bytes"]):
                raise ValueError("public_resource_cap")
            if record["binding_state"] == "json_invalid" and (record["sha256"] is None or record["json_type"] is not None):
                raise ValueError("public_invalid_json_descriptor")
            if record["binding_state"] == "old_header_mismatch" and (record["sha256"] is None or record["json_type"] is None):
                raise ValueError("public_mismatched_header_descriptor")
            if record["binding_state"] == "bound_old_header" and (record["json_type"] != "object" or record["sha256"] is None):
                raise ValueError("public_bound_descriptor")
        readable = [r for r in data["metadata_records"] if r["sha256"] is not None]
        if type(data["metadata_files_bound"]) is not int or data["metadata_files_bound"] != len(readable) or type(data["metadata_bytes_bound"]) is not int or data["metadata_bytes_bound"] != sum(r["size_bytes"] for r in readable):
            raise ValueError("public_metadata_counts")
        summary = data["existing_terminal_summary"]
        if summary is None:
            if data["existing_terminal_verified"] is not False or data["existing_terminal_result_sha256"] is not None or any(type(data[k]) is not int or data[k] != 0 for k in ("recovered_path_candidate_references", "recovered_path_candidate_rows")):
                raise ValueError("public_unrecovered_authority")
        else:
            if data["existing_terminal_verified"] is not True or not all(r["binding_state"] == "bound_old_header" for r in data["metadata_records"]):
                raise ValueError("public_recovered_gate")
            by_role = {r["role"]: r for r in data["metadata_records"]}
            if hashlib.sha256(enc(summary)).hexdigest() != by_role["result"]["sha256"] or data["existing_terminal_result_sha256"] != by_role["result"]["sha256"] or summary["private_input_sha256"] != by_role["private_input"]["sha256"]:
                raise ValueError("public_recovered_digest")
            expected_old_receipt = {k: summary[k] for k in RECEIPT_KEYS} | {"result_sha256": by_role["result"]["sha256"]}
            if hashlib.sha256(enc(expected_old_receipt)).hexdigest() != by_role["receipt"]["sha256"]:
                raise ValueError("public_recovered_receipt_digest")
            load_old_validator(fixture)(summary, None, OLD_SOURCE)
            if type(data["recovered_path_candidate_references"]) is not int or data["recovered_path_candidate_references"] != summary["safe_path_candidate_references"] or type(data["recovered_path_candidate_rows"]) is not int or data["recovered_path_candidate_rows"] != summary["safe_path_candidate_rows"]:
                raise ValueError("public_recovered_counts")
        absent = all(r["presence"] == "absent" for r in data["metadata_records"][2:])
        markers = all(r["binding_state"] == "bound_old_header" for r in data["metadata_records"][:2])
        classification = "existing_terminal_verified" if summary is not None else ("pre_entrypoint_terminal_files_absent_observed" if markers and absent else "partial_or_unbound_old_terminal_metadata")
        expected_state = STATES[0] if classification in ("existing_terminal_verified", "pre_entrypoint_terminal_files_absent_observed") else STATES[1]
        if data["classification"] != classification or data["state"] != expected_state or (classification != "partial_or_unbound_old_terminal_metadata" and data["recovery_reason"] is not None) or (classification == "partial_or_unbound_old_terminal_metadata" and data["recovery_reason"] not in ("existing_old_terminal_validation_failed", "old_terminal_metadata_incomplete_or_unbound")):
            raise ValueError("public_classification")
    if receipt is not None:
        expected = {k: data[k] for k in RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(enc(data)).hexdigest()}
        if not equal_typed(receipt, expected):
            raise ValueError("public_receipt")
    return True


def execute(root, opdir, manifest_path):
    root, opdir = pathlib.Path(root), pathlib.Path(opdir)
    fixture = manifest(manifest_path)
    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_url_paths_terminal_readback_v1.json"
    head = os.environ.get("MATCH_SOURCE_SHA", "")
    if not root.is_dir() or root.is_symlink() or root.resolve() != root or root.name != "anytoour.ru" or not opdir.is_dir() or opdir.is_symlink() or opdir.resolve() != opdir or opdir.name != OP or opdir.parent.name != "operations" or opdir.parent.parent.name != ".anytoour-match" or pathlib.Path(manifest_path) != expected_fixture or not re.fullmatch(r"[0-9a-f]{40}", head):
        raise ValueError("runtime_scope")
    reservation = parsed(file_bytes(opdir / "reservation.json", 1048576))
    expected = {"operation": OP, "batch": BATCH, "source_sha": head, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}
    if not equal_typed(reservation, expected):
        raise ValueError("reservation_scope")
    for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):
        if (opdir / name).exists() or (opdir / name).is_symlink():
            raise ValueError("terminal_no_replay")
    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "no_replay": True})
    capture, private_sha = None, None
    state, reason, failure_stage = "terminal_failed_no_replay", "nonbg5_url_paths_terminal_readback_failed", None
    try:
        capture = capture_metadata(opdir.parent.parent, fixture)
        if len(enc(capture)) > 16777216:
            raise ValueError("private_capture_cap")
        private_sha = save(opdir / "current-input.json", capture)
        state = STATES[1] if capture["classification"] == "partial_or_unbound_old_terminal_metadata" else STATES[0]
        reason = None
    except Exception:
        failure_stage = "private_capture_or_save_failed"
        if private_sha is None:
            private_sha = save(opdir / "current-input.json", {"schema": "match-nonbg5-url-paths-terminal-readback-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "reason": reason, "failure_stage": failure_stage})
        capture = None
    records = capture["metadata_records"] if capture else []
    summary = capture["existing_terminal_summary"] if capture else None
    readable = [r for r in records if r["sha256"] is not None]
    result_digest = next((r["sha256"] for r in records if r["role"] == "result"), None) if summary is not None else None
    output = {"schema": "match-nonbg5-url-paths-terminal-readback-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": state, "reason": reason, "failure_stage": failure_stage, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "inputs": fixture["inputs"], "requested_records": 6, "rows_examined": len(records), "metadata_records": records, "classification": capture["classification"] if capture else "capture_failed", "recovery_reason": capture["recovery_reason"] if capture else None, "existing_terminal_summary": summary, "existing_terminal_result_sha256": result_digest, "existing_terminal_verified": summary is not None, "recovered_path_candidate_references": summary["safe_path_candidate_references"] if summary else 0, "recovered_path_candidate_rows": summary["safe_path_candidate_rows"] if summary else 0, "metadata_files_bound": len(readable), "metadata_bytes_bound": sum(r["size_bytes"] for r in readable), "original_raw_files_read": 0, "nonbg7_private_files_read": 0, "old_supplier_calls": "unknown", "old_database_writes": "unknown", "old_operation_replayed": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}
    try:
        validate_result(output)
    except Exception:
        output.update(state="terminal_failed_no_replay", reason="nonbg5_url_paths_terminal_readback_failed", failure_stage="public_result_validation_failed", metadata_records=[], rows_examined=0, classification="capture_failed", recovery_reason=None, existing_terminal_summary=None, existing_terminal_result_sha256=None, existing_terminal_verified=False, recovered_path_candidate_references=0, recovered_path_candidate_rows=0, metadata_files_bound=0, metadata_bytes_bound=0)
        validate_result(output)
    digest = save(opdir / "result.json", output)
    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}
    validate_result(output, receipt, head)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: output[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))
    return 2 if output["state"] == "terminal_failed_no_replay" else 0


def self_test():
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_url_paths_terminal_readback_v1.json")
    if not header_valid("reservation", old_reservation()) or len(fixture["metadata_records"]) != 6:
        raise ValueError("self_test")
    print("self_test_passed_nonbg5_url_paths_terminal_readback")


if __name__ == "__main__":
    if sys.argv[1:] == ["--self-test"]:
        self_test()
    elif sys.argv[1:] == ["--execute"]:
        raise SystemExit(execute(os.environ.get("ANYTOUR_ROOT", ""), os.environ.get("MATCH_OPERATION_DIR", ""), os.environ.get("MATCH_MANIFEST_PATH", "")))
    else:
        raise SystemExit("use --self-test or exact reviewed --execute")
