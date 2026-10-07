#!/usr/bin/env python3
"""Read two fixed operator-only candidate rows from one retained response; never accept."""
import collections
import datetime as dt
import hashlib
import importlib.util
import json
import os
import pathlib
import re
import sys

OP = "int-andromeda-match-operator115-only2-retained-fields-20261007-v1"
BATCH = "operator115-only2-retained-20261007"
MODE = "match-operator115-only2-retained-fields-readonly"
MANIFEST_SHA = "40df08d71eb09f6185bdb31213686e4771262871205c3329025a75150f1f11df"
HELPERS = {
    "hotel_match_nonbg7_unexported_fields_readonly_v1.py": "89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128",
    "hotel_match_bg5_unexported_fields_readonly_v1.py": "c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196",
}


def helper(name):
    path = pathlib.Path(__file__).resolve().with_name(name)
    if path.is_symlink() or hashlib.sha256(path.read_bytes()).hexdigest() != HELPERS[name]:
        raise RuntimeError("helper_binding")
    spec = importlib.util.spec_from_file_location("operator115_only2_" + name[:-3], path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


n = helper("hotel_match_nonbg7_unexported_fields_readonly_v1.py")
bg = helper("hotel_match_bg5_unexported_fields_readonly_v1.py")
FIELDS = ("row.hotel", "row.hotelKey", "row.operatorKey", "row.isOperatorHotelKey",
          "row.town", "row.townKey", "row.stateKey", "row.countryKey", "row.starKey",
          "row.latitude", "row.longitude", "row.address", "row.hotelUrl",
          "original.hotelKey", "original.operatorKey", "original.isOperatorHotelKey", "original.tourKey")
NO_EFFECTS = n.NO_EFFECTS
FALSE_FLAGS = n.FALSE_FLAGS + ("namespace_bridge_verified",)
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", *NO_EFFECTS, *FALSE_FLAGS, "no_replay")
STATES = ("completed_read_only_operator115_only2_fields", "completed_read_only_operator115_only2_fields_incomplete", "terminal_failed_no_replay")
FAILURES = ("retained_source_unavailable_or_digest", "protected_capture_failed", "retained_schema_invalid", "public_result_validation_failed")
ROSTER = (("610284976", "610284976", "/PRICES/3"), ("610274422", "610274422", "/PRICES/19"))
ROW_KEYS = ("retained_catalog_label", "source_native_id", "json_pointer")
BASE_HOLDS = ("operator_only_catalog_bridge_not_established", "current_registry_checks_not_performed", "independent_local_and_tv_ids_not_established")
MAX_PUBLIC_FIELD = 65536
MAX_PUBLIC = 524288
FIXTURE = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_operator115_only2_retained_fields_readonly_v1.json"


def manifest(path=FIXTURE):
    value = n.read_pinned(path, MANIFEST_SHA, 65536)
    n.require_fields(value, {"schema": "match-operator115-only2-retained-fields-fixture/1", "operation": OP, "batch": BATCH,
                             "mode": MODE, "source_namespace": "operator_115", "requested_rows": 2,
                             "projection_fields": list(FIELDS), "target_namespace": "not_established",
                             "current_readiness": "not_evaluated"})
    if tuple(tuple(r[k] for k in ROW_KEYS) for r in value["rows"]) != ROSTER:
        raise ValueError("fixture_roster")
    if any(type(value[k]) is not int or value[k] != 0 for k in ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "maximum_writes")) or any(value[k] is not False for k in FALSE_FLAGS):
        raise ValueError("fixture_authority")
    return value


def durable_bytes(path, raw):
    """Protect exact source bytes before JSON parsing or any optional-field filtering."""
    path = pathlib.Path(path)
    if path.parent.resolve() != path.parent or path.parent.is_symlink() or not path.parent.is_dir():
        raise ValueError("protected_directory")
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0), 0o600)
    with os.fdopen(fd, "wb") as stream:
        if stream.write(raw) != len(raw):
            raise RuntimeError("protected_short_write")
        stream.flush()
        os.fsync(stream.fileno())
    fd = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
    try:
        os.fsync(fd)
    finally:
        os.close(fd)
    if n.file_bytes(path, len(raw)) != raw:
        raise RuntimeError("protected_readback")
    return hashlib.sha256(raw).hexdigest()


def capture(private_root, opdir, fixture, stats):
    ref = fixture["raw_source"]
    stats["raw_files_attempted"] = 1
    try:
        raw = n.file_bytes(private_root / ref["source_file"], ref["maximum_bytes"])
        stats.update(raw_files_read=1, raw_bytes_read=len(raw))
        if hashlib.sha256(raw).hexdigest() != ref["sha256"]:
            raise ValueError("source_digest")
    except Exception:
        raise n.CaptureFailure("retained_source_unavailable_or_digest") from None
    try:
        digest = durable_bytes(opdir / "retained-original.json", raw)
        stats.update(original_bytes_preserved=True, original_source_sha256=digest)
    except Exception:
        raise n.CaptureFailure("protected_capture_failed") from None
    try:
        data = n.parsed(raw)
        if not isinstance(data.get("PRICES"), list):
            raise ValueError("prices_schema")
    except Exception:
        raise n.CaptureFailure("retained_schema_invalid") from None
    rows = []
    for spec in fixture["rows"]:
        row, failure = None, None
        try:
            row = n.pointer(data, spec["json_pointer"])
            expected = dict(spec, source_namespace="operator_115")
            if not raw_binding(row, expected):
                raise ValueError("identity_binding")
        except Exception:
            failure = "raw_pointer_or_identity_changed"
        # Keep complete selected row objects, including optional private values.
        rows.append({"spec": spec, "raw_verified": failure is None, "failure": failure, "original_row": row})
    return {"schema": "match-operator115-only2-retained-private-input/1", "operation": OP, "batch": BATCH,
            "raw_source": ref, "lineage": fixture["lineage"], "projection_fields": list(FIELDS), "rows": rows}


def private_bytes(value):
    # Preserve lone escaped surrogates privately; invalid optional display fields HOLD.
    return (json.dumps(value, ensure_ascii=True, sort_keys=True, indent=2, allow_nan=False) + "\n").encode()


def identifier(value):
    return (str(value) if type(value) is int and value > 0 else value
            if type(value) is str and re.fullmatch(r"[1-9][0-9]{0,31}", value) else None)


def raw_binding(raw, spec):
    # The repeated catalog label is only a retained row locator, never a catalog anchor.
    if not isinstance(raw, dict) or identifier(raw.get("hotelKey")) != spec["retained_catalog_label"]:
        return False
    original = raw.get("original")
    if original is None:
        original = {}
    if not isinstance(original, dict):
        return False
    if identifier(raw.get("operatorKey", original.get("operatorKey"))) != "115":
        return False
    if "operatorKey" in original and identifier(original["operatorKey"]) != "115":
        return False
    if original.get("hotelKey") is not None and identifier(original["hotelKey"]) != spec["source_native_id"]:
        return False
    return True  # isOperatorHotelKey is observed, not converted to catalog identity.


def optional_projection(name, present, value):
    encoded = private_bytes(value)
    kind = "null" if value is None else ("boolean" if type(value) is bool else
            "number" if type(value) in (int, float) else "string" if type(value) is str else
            "array" if type(value) is list else "object")
    out = {"field_name": name, "present": present, "value_type": kind,
           "value_sha256": hashlib.sha256(encoded).hexdigest(), "value_bytes": len(encoded),
           "value": None, "hold": None, "projection": None}
    if not present:
        out["hold"] = "optional_field_missing"
    elif type(value) not in (str, int, float, bool) or value is None:
        out["hold"] = "optional_field_not_scalar"
    elif type(value) is str:
        try:
            value.encode("utf-8")
            if len(value) > (16384 if name in ("row.hotelUrl", "original.tourKey") else 512):
                out["hold"] = "optional_field_resource_cap"
            elif not value or re.search(r"[\x00-\x1f\x7f]", value):
                out["hold"] = "optional_field_empty_or_controls"
            elif name in ("row.hotelUrl", "original.tourKey"):
                projection = bg.field_projection(name, {"present": True, "value": value}, None)
                if len(n.enc(projection)) > MAX_PUBLIC_FIELD:
                    out["hold"] = "optional_field_resource_cap"
                else:
                    out["projection"] = projection  # Existing redaction; no opaque-key substring guess.
            elif re.search(r"(?i)(?:https?://|eyJ[A-Za-z0-9_-]{20,}|(?:token|jwt|sid|auth|session|cookie|password|api[_-]?key|secret|signature)\s*[:=])", value):
                out["hold"] = "optional_field_private_pattern"
            else:
                out["value"] = value  # Full raw name/meaningful qualifiers; no normalization.
        except UnicodeError:
            out["hold"] = "optional_field_invalid_unicode"
    else:
        out["value"] = value
    return out


def project(private, fixture, private_sha):
    n.require_fields(private, {"schema": "match-operator115-only2-retained-private-input/1", "operation": OP, "batch": BATCH,
                               "raw_source": fixture["raw_source"], "lineage": fixture["lineage"], "projection_fields": list(FIELDS)})
    if len(private["rows"]) != 2:
        raise ValueError("private_roster")
    rows = []
    for i, (entry, spec) in enumerate(zip(private["rows"], fixture["rows"])):
        if not n.equal_typed(entry["spec"], spec):
            raise ValueError("private_spec")
        fields, holds = [], list(BASE_HOLDS)
        if entry["raw_verified"] is True:
            raw = entry["original_row"]
            if not raw_binding(raw, spec) or entry["failure"] is not None:
                raise ValueError("private_identity")
            for name in FIELDS:
                level, key = name.split(".")
                obj = raw if level == "row" else (raw.get("original") or {})
                field = optional_projection(name, key in obj, obj.get(key))
                fields.append(field)
                if field["hold"]:
                    holds.append(field["hold"])
        elif entry["raw_verified"] is False and entry["failure"] == "raw_pointer_or_identity_changed":
            holds.append(entry["failure"])
        else:
            raise ValueError("private_identity_status")
        rows.append({**spec, "target_namespace": "not_established", "source_namespace": "operator_115",
                     "raw_verified": entry["raw_verified"], "failure": entry["failure"], "fields": fields,
                     "private_input_pointer": {"sha256": private_sha, "json_pointer": f"/rows/{i}"},
                     "holds": list(dict.fromkeys(holds)), **dict.fromkeys(FALSE_FLAGS, False)})
    return rows


def validate_result(data, receipt=None, expected_source=None, private_input=None):
    fixture = manifest()
    keys = {"schema", "operation", "batch", "source_sha", "state", "failure_stage", "captured_at_utc", "private_input_sha256",
            "raw_source", "lineage", "projection_fields", "requested_rows", "source_namespace", "rows_examined", "rows",
            "raw_files_attempted", "raw_files_read", "raw_bytes_read", "original_bytes_preserved", "original_source_sha256",
            "raw_references_verified", "hold_counts", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}
    if not isinstance(data, dict) or set(data) != keys or len(n.enc(data)) > MAX_PUBLIC:
        raise ValueError("result_shape_or_cap")
    n.require_fields(data, {"schema": "match-operator115-only2-retained-fields-result/1", "operation": OP, "batch": BATCH,
                            "requested_rows": 2, "source_namespace": "operator_115", "raw_source": fixture["raw_source"],
                            "lineage": fixture["lineage"], "projection_fields": list(FIELDS), "no_replay": True,
                            **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)})
    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or not n.SHA.fullmatch(data["private_input_sha256"] or "") or (expected_source is not None and data["source_sha"] != expected_source):
        raise ValueError("result_digest_or_source")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    if data["state"] not in STATES or (data["state"] == STATES[2]) != (data["failure_stage"] in FAILURES) or (data["state"] != STATES[2] and data["failure_stage"] is not None):
        raise ValueError("result_state")
    for key, maximum in (("raw_files_attempted", 1), ("raw_files_read", 1), ("raw_bytes_read", fixture["raw_source"]["maximum_bytes"]), ("rows_examined", 2), ("raw_references_verified", 2)):
        if type(data[key]) is not int or not 0 <= data[key] <= maximum:
            raise ValueError("result_counter_type_or_cap")
    if data["raw_files_read"] > data["raw_files_attempted"] or bool(data["raw_files_read"]) != bool(data["raw_bytes_read"]):
        raise ValueError("result_read_counters")
    if type(data["original_bytes_preserved"]) is not bool or (data["original_source_sha256"] != fixture["raw_source"]["sha256"] if data["original_bytes_preserved"] else data["original_source_sha256"] is not None):
        raise ValueError("original_byte_binding")
    if data["original_bytes_preserved"] and data["raw_files_read"] != 1:
        raise ValueError("original_byte_read_binding")
    failed = data["state"] == STATES[2]
    if failed:
        if data["rows"] != [] or data["rows_examined"] != 0 or data["raw_references_verified"] != 0 or data["hold_counts"] != {}:
            raise ValueError("failure_projection")
    else:
        if not data["original_bytes_preserved"] or data["raw_files_read"] != 1 or data["rows_examined"] != 2 or not isinstance(data["rows"], list) or len(data["rows"]) != 2:
            raise ValueError("success_capture_binding")
        for i, (row, spec) in enumerate(zip(data["rows"], fixture["rows"])):
            required = {*spec, "target_namespace", "source_namespace", "raw_verified", "failure", "fields", "private_input_pointer", "holds", *FALSE_FLAGS}
            if not isinstance(row, dict) or set(row) != required:
                raise ValueError("result_row_shape")
            n.require_fields(row, {**spec, "target_namespace": "not_established", "source_namespace": "operator_115",
                                   "private_input_pointer": {"sha256": data["private_input_sha256"], "json_pointer": f"/rows/{i}"},
                                   **dict.fromkeys(FALSE_FLAGS, False)})
            if type(row["raw_verified"]) is not bool or (row["raw_verified"] and row["failure"] is not None) or (not row["raw_verified"] and (row["failure"] != "raw_pointer_or_identity_changed" or row["fields"] != [])):
                raise ValueError("result_row_identity")
        if sum(r["raw_verified"] for r in data["rows"]) != data["raw_references_verified"] or not n.equal_typed(data["hold_counts"], dict(collections.Counter(h for r in data["rows"] for h in r["holds"]))):
            raise ValueError("result_aggregate")
        incomplete = any(not r["raw_verified"] or any(f["hold"] is not None for f in r["fields"]) for r in data["rows"])
        if (data["state"] == STATES[1]) != incomplete:
            raise ValueError("result_completeness")
    if private_input is None:
        raise ValueError("private_capture_required")
    if private_input is not None:
        if hashlib.sha256(private_bytes(private_input)).hexdigest() != data["private_input_sha256"]:
            raise ValueError("private_capture_digest")
        if not failed and not n.equal_typed(project(private_input, fixture, data["private_input_sha256"]), data["rows"]):
            raise ValueError("private_projection_binding")
    if receipt is not None:
        if set(receipt) != {*RECEIPT_KEYS, "result_sha256"} or receipt["result_sha256"] != hashlib.sha256(n.enc(data)).hexdigest() or any(not n.equal_typed(receipt[k], data[k]) for k in RECEIPT_KEYS):
            raise ValueError("receipt_binding")
    return True


def execute(project_root, opdir, fixture_path):
    project_root, opdir = pathlib.Path(project_root), pathlib.Path(opdir)
    private_root = project_root.parent.parent / ".anytoour-match"
    if project_root != private_root.parent / "www" / "anytoour.ru" or project_root.resolve() != project_root or opdir.resolve() != opdir or opdir != private_root / "operations" / OP or fixture_path != FIXTURE or not opdir.is_dir() or opdir.is_symlink() or (opdir.stat().st_mode & 0o077):
        raise ValueError("execution_paths")
    head = os.environ.get("MATCH_SOURCE_SHA", "")
    if not re.fullmatch(r"[0-9a-f]{40}", head):
        raise ValueError("execution_source")
    fixture = manifest(fixture_path)
    reservation = n.parsed(n.file_bytes(opdir / "reservation.json", 65536))
    n.require_fields(reservation, {"operation": OP, "source_sha": head, "batch": BATCH, "provider_http_calls": 0,
                                   "maximum_writes": 0, "state": "reserved_before_retained_read"})
    if any((opdir / name).exists() or (opdir / name).is_symlink() for name in ("retained-original.json", "current-input.json", "result.json", "receipt.json")):
        raise ValueError("operation_consumed_no_replay")
    stats = {"raw_files_attempted": 0, "raw_files_read": 0, "raw_bytes_read": 0, "original_bytes_preserved": False, "original_source_sha256": None}
    failure, rows = None, []
    try:
        private = capture(private_root, opdir, fixture, stats)
        if len(private_bytes(private)) > fixture["limits"]["private_capture_bytes"]:
            raise n.CaptureFailure("protected_capture_failed")
        private_sha = durable_bytes(opdir / "current-input.json", private_bytes(private))
        rows = project(private, fixture, private_sha)
    except Exception as exc:
        failure = exc.stage if isinstance(exc, n.CaptureFailure) else "protected_capture_failed"
        if (opdir / "current-input.json").exists():
            private = n.parsed(n.file_bytes(opdir / "current-input.json", fixture["limits"]["private_capture_bytes"]))
            private_sha = hashlib.sha256(private_bytes(private)).hexdigest()
        else:
            private = {"schema": "match-operator115-only2-retained-private-input/1", "operation": OP, "batch": BATCH, "failure_stage": failure}
            private_sha = durable_bytes(opdir / "current-input.json", private_bytes(private))
        rows = []
    incomplete = any(not r["raw_verified"] or any(f["hold"] for f in r["fields"]) for r in rows)
    result = {"schema": "match-operator115-only2-retained-fields-result/1", "operation": OP, "batch": BATCH, "source_sha": head,
              "state": STATES[2] if failure else STATES[1] if incomplete else STATES[0], "failure_stage": failure,
              "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha,
              "raw_source": fixture["raw_source"], "lineage": fixture["lineage"], "projection_fields": list(FIELDS),
              "requested_rows": 2, "source_namespace": "operator_115", "rows_examined": len(rows), "rows": rows, **stats,
              "raw_references_verified": sum(r["raw_verified"] for r in rows),
              "hold_counts": dict(collections.Counter(h for r in rows for h in r["holds"])), "no_replay": True,
              **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}
    try:
        validate_result(result, expected_source=head, private_input=private)
    except Exception:
        result.update(state=STATES[2], failure_stage="public_result_validation_failed", rows=[], rows_examined=0, raw_references_verified=0, hold_counts={})
        validate_result(result, expected_source=head, private_input=private)
    result_sha = n.save(opdir / "result.json", result)
    receipt = {k: result[k] for k in RECEIPT_KEYS} | {"result_sha256": result_sha}
    validate_result(result, receipt, head, private)
    n.save(opdir / "receipt.json", receipt)
    print(json.dumps({k: result[k] for k in ("state", "rows_examined", "accepted", "written")}))
    return 2 if result["state"] == STATES[2] else 0


if __name__ == "__main__":
    if sys.argv[1:] == ["--self-test"]:
        manifest()
        print("MATCH_OPERATOR115_ONLY2_RETAINED_FIELDS_READONLY_V1_SELFTEST_OK")
    elif sys.argv[1:] == ["--execute"]:
        sys.exit(execute(pathlib.Path(os.environ["ANYTOUR_ROOT"]), pathlib.Path(os.environ["MATCH_OPERATION_DIR"]), pathlib.Path(os.environ["MATCH_MANIFEST_PATH"])))
    else:
        raise SystemExit("exact --self-test or --execute required")
