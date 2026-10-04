#!/usr/bin/env python3
"""First URL-path projection of five already captured NONBG7 values; metadata only."""
import collections
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import stat
import sys
import urllib.parse

OP = "int-andromeda-match-nonbg5-retained-url-paths-20261004-v1"
BATCH = "nonbg5-retained-url-paths-20261004"
MODE = "match-nonbg5-retained-url-paths-readonly"
MANIFEST_SHA = "a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269"
OLD_OP = "int-andromeda-match-nonbg7-unexported-fields-20261004-v1"
OLD_BATCH = "nonbg7-unexported-fields-20261004"
OLD_SOURCE = "bcd25c42a899071b09abce06890b2ce0c3eeb465"
OLD_INPUT_SHA = "8ac39c85f19a0fd944536b4f9d85cbf41450eea46d2f6c16e2c0cd98344fc1ce"
OLD_RESULT_SHA = "8336b824bf48df8771685c00d744bb8a720fa062addb1d71a93ec23c4e394604"
ROW_KEYS = ("catalog_id", "source_namespace", "source_native_id", "target_tv_hotel_id", "target_operator_id")
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", *NO_EFFECTS, *FALSE_FLAGS, "no_replay")
STATES = ("completed_read_only_nonbg5_url_paths", "completed_read_only_nonbg5_url_paths_incomplete", "terminal_failed_no_replay")
FAILURE_STAGES = ("prior_metadata_unavailable_or_digest", "prior_result_binding_mismatch", "prior_private_capture_binding_mismatch", "private_projection_or_save_failed", "public_result_validation_failed")
PATH_HOLDS = ("url_not_absolute_https", "url_origin_or_parameters_private", "url_host_mismatch", "url_path_resource_cap", "url_path_invalid_encoding", "url_path_unsafe_characters", "url_path_private_or_opaque", "url_path_navigation")
BASE_HOLDS = ("independent_operator_target_proof_not_evaluated", "current_registry_checks_not_performed", "current_global_uniqueness_not_evaluated")
SHA = re.compile(r"[0-9a-f]{64}")
HOSTS = {"operator_5": "agent.anextour.ru", "operator_315": "b2b.fstravel.com", "operator_342": "intourist.ru"}
SECRET = re.compile(r"(?:^|[._/-])(?:token|jwt|auth|password|passwd|secret|session|sid|cookie|signature|api[_-]?key)(?:$|[._/-])", re.I)
PUBLIC_PATH = re.compile(r"/[A-Za-z0-9/._%-]*")
DECODED_PATH = re.compile(r"/[A-Za-z0-9/._-]*")


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
    value = json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: (_ for _ in ()).throw(ValueError("nonfinite")))
    if not isinstance(value, dict):
        raise ValueError("metadata_object")
    return value


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
        with os.fdopen(fd, "rb", closefd=False) as handle:
            raw = handle.read(maximum + 1)
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
    with open(path, "xb") as handle:
        os.chmod(path, 0o600)
        if handle.write(raw) != len(raw):
            raise RuntimeError("short_write")
        handle.flush()
        os.fsync(handle.fileno())
    fd = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
    try:
        os.fsync(fd)
    finally:
        os.close(fd)
    if path.read_bytes() != raw:
        raise RuntimeError("durable_readback")
    return hashlib.sha256(raw).hexdigest()


def manifest(path):
    raw = file_bytes(pathlib.Path(path), 1048576)
    if hashlib.sha256(raw).hexdigest() != MANIFEST_SHA:
        raise ValueError("fixture_digest")
    value = parsed(raw)
    if value["schema"] != "match-nonbg5-retained-url-paths-fixture/1" or value["operation"] != OP or value["batch"] != BATCH or value["mode"] != MODE or not equal_typed(value["selected_rows"], [0, 1, 2, 3, 4]):
        raise ValueError("fixture_scope")
    prior = value["prior_result"]
    if len(enc(prior)) != 39042 or hashlib.sha256(enc(prior)).hexdigest() != OLD_RESULT_SHA or prior["private_input_sha256"] != OLD_INPUT_SHA or prior["source_sha"] != OLD_SOURCE or prior["operation"] != OLD_OP or prior["batch"] != OLD_BATCH or len(prior["rows"]) != 7:
        raise ValueError("fixture_prior_result")
    return value


class CaptureFailure(Exception):
    def __init__(self, stage):
        self.stage = stage
        super().__init__(stage)


def phase(stage, action):
    try:
        return action()
    except Exception:
        raise CaptureFailure(stage) from None


def validate_prior_capture(capture, prior):
    keys = {"schema", "operation", "batch", "inputs", "projection_fields", "raw_files_attempted", "raw_files_read", "raw_bytes_read", "rows"}
    expected = {"schema": "match-nonbg7-unexported-private-input/1", "operation": OLD_OP, "batch": OLD_BATCH, "inputs": prior["inputs"], "projection_fields": prior["projection_fields"], "raw_files_attempted": prior["raw_files_attempted"], "raw_files_read": prior["raw_files_read"], "raw_bytes_read": prior["raw_bytes_read"]}
    if not isinstance(capture, dict) or set(capture) != keys or any(not equal_typed(capture.get(k), v) for k, v in expected.items()) or not isinstance(capture["rows"], list) or len(capture["rows"]) != 7:
        raise ValueError("capture_header")
    for i, (row, old) in enumerate(zip(capture["rows"], prior["rows"])):
        if not isinstance(row, dict) or set(row) != {*ROW_KEYS, "references"} or any(not equal_typed(row[k], old[k]) for k in ROW_KEYS) or not isinstance(row["references"], list) or len(row["references"]) != len(old["references"]):
            raise ValueError("capture_row")
        for j, (ref, old_ref) in enumerate(zip(row["references"], old["references"])):
            keys = {"source_file", "sha256", "json_pointer", "raw_verified", "failure", "fields"}
            if not isinstance(ref, dict) or set(ref) != keys or any(not equal_typed(ref[k], old_ref[k]) for k in keys - {"fields"}) or ref["raw_verified"] is not True or ref["failure"] is not None or not isinstance(ref["fields"], dict) or set(ref["fields"]) != {"row.hotelUrl", "original.tourKey"}:
                raise ValueError("capture_reference")
            if not equal_typed(old_ref["private_input_pointer"], {"sha256": OLD_INPUT_SHA, "json_pointer": f"/rows/{i}/references/{j}"}):
                raise ValueError("capture_pointer")
            for field in old_ref["fields"]:
                actual = ref["fields"][field["field_name"]]
                if not isinstance(actual, dict) or set(actual) != {"present", "value"} or type(actual["present"]) is not bool or actual["present"] != field["present"]:
                    raise ValueError("capture_field")
                v = actual["value"]
                kind = "null" if v is None else ("boolean" if type(v) is bool else ("number" if type(v) in (int, float) else ("string" if type(v) is str else ("array" if type(v) is list else "object"))))
                raw = enc(v)
                if kind != field["value_type"] or len(raw) != field["value_bytes"] or hashlib.sha256(raw).hexdigest() != field["value_sha256"]:
                    raise ValueError("capture_field_digest_or_type")
                if field["field_name"] == "row.hotelUrl" and (type(v) is not str or not v or actual["present"] is not True):
                    raise ValueError("capture_url_type")
    return capture


def path_projection(value, expected_host):
    out = {"state": "hold", "hold_reason": None, "source_url_candidate": None, "path": None, "decoded_path": None, "path_segments": [], "numeric_path_tokens": [], "positive_numeric_path_candidates": [], "namespace_bridge_verified": False, "target_native_identity_verified": False}
    def hold(reason):
        out["hold_reason"] = reason
        return out
    # urlsplit removes some literal C0 controls before parsing. Reject them in
    # the original captured value so a safe projection never republishes them.
    if type(value) is not str or re.search(r"[\x00-\x20\x7f]", value):
        return hold("url_path_unsafe_characters")
    try:
        p = urllib.parse.urlsplit(value)
        if p.scheme.lower() != "https" or not p.netloc:
            return hold("url_not_absolute_https")
        if p.username is not None or p.password is not None or p.query or p.fragment or "?" in value or "#" in value or p.port not in (None, 443) or ":" in p.netloc:
            return hold("url_origin_or_parameters_private")
        if p.hostname != expected_host or p.netloc.lower() != expected_host:
            return hold("url_host_mismatch")
        path = p.path
        if not path.startswith("/") or len(path) > 2048:
            return hold("url_path_resource_cap")
        if re.search(r"%(?:2f|5c|25)", path, re.I) or re.search(r"%(?![0-9a-f]{2})", path, re.I):
            return hold("url_path_invalid_encoding")
        decoded = urllib.parse.unquote_to_bytes(path).decode("ascii")
        if not PUBLIC_PATH.fullmatch(path) or not DECODED_PATH.fullmatch(decoded):
            return hold("url_path_unsafe_characters")
        segments = decoded.split("/")[1:]
        if len(segments) > 20 or any(len(s) > 128 for s in segments):
            return hold("url_path_resource_cap")
        if any(s in (".", "..") for s in segments):
            return hold("url_path_navigation")
        if SECRET.search(decoded) or any(re.fullmatch(r"[0-9a-fA-F]{32,}", s) or re.fullmatch(r"[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{2,}\.[A-Za-z0-9_-]{8,}", s) or (len(s) >= 32 and re.fullmatch(r"[A-Za-z0-9_-]+", s) and re.search(r"[A-Z]", s) and re.search(r"[a-z]", s) and re.search(r"[0-9]", s)) for s in segments):
            return hold("url_path_private_or_opaque")
        tokens = [s for s in segments if re.fullmatch(r"-?[0-9]+", s)]
        positives = list(dict.fromkeys(s for s in tokens if re.fullmatch(r"[0-9]+", s) and int(s) > 0))
        out.update(state="safe_absolute_operator_path_candidate", source_url_candidate=value, path=path, decoded_path=decoded, path_segments=segments, numeric_path_tokens=tokens, positive_numeric_path_candidates=positives)
        return out
    except (ValueError, UnicodeError, OverflowError):
        return hold("url_path_invalid_encoding")


def project_capture(capture, fixture):
    prior = fixture["prior_result"]
    rows = []
    for i in fixture["selected_rows"]:
        old = prior["rows"][i]
        row = capture["rows"][i]
        refs = []
        holds = list(BASE_HOLDS)
        if old["dated_operator_ownership_fact"]["current_identity_count"] > 0:
            holds.append("scoped_operator_identity_present_dated")
        for j, ref in enumerate(row["references"]):
            field = next(f for f in old["references"][j]["fields"] if f["field_name"] == "row.hotelUrl")
            projection = path_projection(ref["fields"]["row.hotelUrl"]["value"], HOSTS[row["source_namespace"]])
            if projection["hold_reason"]:
                holds.append(projection["hold_reason"])
            refs.append({**{k: ref[k] for k in ("source_file", "sha256", "json_pointer")}, "field_name": "row.hotelUrl", "value_sha256": field["value_sha256"], "value_bytes": field["value_bytes"], "prior_private_value_pointer": {"sha256": OLD_INPUT_SHA, "json_pointer": f"/rows/{i}/references/{j}/fields/row.hotelUrl/value"}, "prior_result_field_pointer": {"sha256": OLD_RESULT_SHA, "json_pointer": f"/rows/{i}/references/{j}/fields/0"}, "projection": projection})
        rows.append({**{k: old[k] for k in ROW_KEYS}, "target_id_namespace": "tourvisor", "independent_anytour_local_id": None, "dated_operator_ownership_fact": old["dated_operator_ownership_fact"], "references": refs, "holds": list(dict.fromkeys(holds)), "source_namespace_bridge_verified": False, "target_native_identity_verified": False, **dict.fromkeys(FALSE_FLAGS, False)})
    return rows


def capture_retained(private_root, fixture):
    pins = fixture["inputs"]
    def reads():
        old_raw = file_bytes(private_root / pins["prior_result"]["path"], 39042)
        value_raw = file_bytes(private_root / pins["prior_private_input"]["path"], 16777216)
        if len(old_raw) != 39042 or hashlib.sha256(old_raw).hexdigest() != OLD_RESULT_SHA or hashlib.sha256(value_raw).hexdigest() != OLD_INPUT_SHA:
            raise ValueError("prior_digest")
        return old_raw, value_raw
    old_raw, value_raw = phase("prior_metadata_unavailable_or_digest", reads)
    old = phase("prior_result_binding_mismatch", lambda: parsed(old_raw))
    phase("prior_result_binding_mismatch", lambda: equal_or_fail(old, fixture["prior_result"]))
    capture = phase("prior_private_capture_binding_mismatch", lambda: validate_prior_capture(parsed(value_raw), old))
    rows = project_capture(capture, fixture)
    return {"schema": "match-nonbg5-retained-url-paths-private-input/1", "operation": OP, "batch": BATCH, "inputs": pins, "metadata_files_bound": 2, "metadata_bytes_bound": len(old_raw) + len(value_raw), "selected_rows": fixture["selected_rows"], "rows": rows, "complete_source_values_retained_in_prior_private_input": True, "original_raw_files_read": 0}


def equal_or_fail(actual, expected):
    if not equal_typed(actual, expected):
        raise ValueError("typed_binding")
    return True


def validate_result(data, receipt=None, expected_source=None):
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_retained_url_paths_readonly_v1.json")
    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "failure_stage", "captured_at_utc", "private_input_sha256", "inputs", "requested_rows", "requested_sources", "distinct_source_count", "rows_examined", "operator_ids", "rows", "metadata_files_bound", "metadata_bytes_bound", "original_raw_files_read", "references_bound", "safe_path_candidate_references", "safe_path_candidate_rows", "hold_counts", "global_saved_context_only", "source_namespace_bridge_verified", "target_native_identity_verified", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}
    if not isinstance(data, dict) or set(data) != keys or len(enc(data)) > 2097152 or data["schema"] != "match-nonbg5-retained-url-paths-readonly-result/1" or data["operation"] != OP or data["batch"] != BATCH or data["state"] not in STATES:
        raise ValueError("public_scope")
    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or (expected_source is not None and data["source_sha"] != expected_source) or not SHA.fullmatch(data["private_input_sha256"] or "") or not equal_typed(data["inputs"], fixture["inputs"]):
        raise ValueError("public_lineage")
    if any(type(data[k]) is not int or data[k] != 0 for k in (*NO_EFFECTS, "original_raw_files_read")) or any(data[k] is not False for k in (*FALSE_FLAGS, "source_namespace_bridge_verified", "target_native_identity_verified")) or data["no_replay"] is not True or data["global_saved_context_only"] is not True:
        raise ValueError("public_authority")
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", data["captured_at_utc"] or ""):
        raise ValueError("public_timestamp")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    for k, v in (("requested_rows", 5), ("requested_sources", 4)):
        if type(data[k]) is not int or data[k] != v:
            raise ValueError("public_roster_size")
    if not equal_typed(data["operator_ids"], [13, 25, 43]) or not isinstance(data["rows"], list) or type(data["rows_examined"]) is not int or data["rows_examined"] != len(data["rows"]):
        raise ValueError("public_roster")
    failed = data["state"] == "terminal_failed_no_replay"
    if failed:
        if data["rows"] or data["reason"] != "nonbg5_url_paths_capture_or_validation_failed" or data["failure_stage"] not in FAILURE_STAGES or any(type(data[k]) is not int or data[k] != 0 for k in ("distinct_source_count", "metadata_files_bound", "metadata_bytes_bound", "references_bound", "safe_path_candidate_references", "safe_path_candidate_rows")) or data["hold_counts"] != {}:
            raise ValueError("public_failed_state")
    else:
        if data["reason"] is not None or data["failure_stage"] is not None or len(data["rows"]) != 5 or type(data["distinct_source_count"]) is not int or data["distinct_source_count"] != 4 or type(data["metadata_files_bound"]) is not int or data["metadata_files_bound"] != 2 or type(data["metadata_bytes_bound"]) is not int or not 39042 < data["metadata_bytes_bound"] <= 39042 + 16777216:
            raise ValueError("public_completed_state")
        count = 0
        candidate_rows = 0
        hold_counter = collections.Counter()
        for i, (row, source_index) in enumerate(zip(data["rows"], fixture["selected_rows"])):
            old = fixture["prior_result"]["rows"][source_index]
            row_keys = {*ROW_KEYS, "target_id_namespace", "independent_anytour_local_id", "dated_operator_ownership_fact", "references", "holds", "source_namespace_bridge_verified", "target_native_identity_verified", *FALSE_FLAGS}
            if not isinstance(row, dict) or set(row) != row_keys or any(not equal_typed(row[k], old[k]) for k in ROW_KEYS) or not equal_typed(row["dated_operator_ownership_fact"], old["dated_operator_ownership_fact"]) or row["target_id_namespace"] != "tourvisor" or row["independent_anytour_local_id"] is not None or any(row[k] is not False for k in (*FALSE_FLAGS, "source_namespace_bridge_verified", "target_native_identity_verified")) or not isinstance(row["references"], list) or len(row["references"]) != len(old["references"]):
                raise ValueError("public_row_binding")
            holds = list(BASE_HOLDS)
            if old["dated_operator_ownership_fact"]["current_identity_count"] > 0:
                holds.append("scoped_operator_identity_present_dated")
            candidates = 0
            for j, (ref, old_ref) in enumerate(zip(row["references"], old["references"])):
                field = old_ref["fields"][0]
                expected = {**{k: old_ref[k] for k in ("source_file", "sha256", "json_pointer")}, "field_name": "row.hotelUrl", "value_sha256": field["value_sha256"], "value_bytes": field["value_bytes"], "prior_private_value_pointer": {"sha256": OLD_INPUT_SHA, "json_pointer": f"/rows/{source_index}/references/{j}/fields/row.hotelUrl/value"}, "prior_result_field_pointer": {"sha256": OLD_RESULT_SHA, "json_pointer": f"/rows/{source_index}/references/{j}/fields/0"}}
                if not isinstance(ref, dict) or set(ref) != {*expected, "projection"} or any(not equal_typed(ref[k], v) for k, v in expected.items()):
                    raise ValueError("public_ref_binding")
                p = ref["projection"]
                projection_keys = {"state", "hold_reason", "source_url_candidate", "path", "decoded_path", "path_segments", "numeric_path_tokens", "positive_numeric_path_candidates", "namespace_bridge_verified", "target_native_identity_verified"}
                if not isinstance(p, dict) or set(p) != projection_keys or p["namespace_bridge_verified"] is not False or p["target_native_identity_verified"] is not False:
                    raise ValueError("public_projection")
                if p["state"] == "safe_absolute_operator_path_candidate":
                    if not isinstance(p["source_url_candidate"], str) or not equal_typed(p, path_projection(p["source_url_candidate"], HOSTS[row["source_namespace"]])) or hashlib.sha256(enc(p["source_url_candidate"])).hexdigest() != ref["value_sha256"] or len(enc(p["source_url_candidate"])) != ref["value_bytes"]:
                        raise ValueError("public_safe_projection")
                    # The exact URL bytes are captured proof of this path representation;
                    # they establish neither native identity nor independent target proof.
                    candidates += 1
                elif p["state"] == "hold" and p["hold_reason"] in PATH_HOLDS:
                    expected_hold = {k: v for k, v in path_projection("", HOSTS[row["source_namespace"]]).items()}
                    expected_hold["hold_reason"] = p["hold_reason"]
                    if not equal_typed(p, expected_hold):
                        raise ValueError("public_hold_projection")
                    holds.append(p["hold_reason"])
                else:
                    raise ValueError("public_projection_state")
            if not equal_typed(row["holds"], list(dict.fromkeys(holds))):
                raise ValueError("public_holds")
            hold_counter.update(row["holds"])
            count += candidates
            candidate_rows += candidates > 0
        expected_state = "completed_read_only_nonbg5_url_paths" if count == 8 else "completed_read_only_nonbg5_url_paths_incomplete"
        if data["state"] != expected_state or type(data["references_bound"]) is not int or data["references_bound"] != 8 or type(data["safe_path_candidate_references"]) is not int or data["safe_path_candidate_references"] != count or type(data["safe_path_candidate_rows"]) is not int or data["safe_path_candidate_rows"] != candidate_rows or not equal_typed(data["hold_counts"], dict(hold_counter)):
            raise ValueError("public_aggregate")
    if receipt is not None:
        expected = {k: data[k] for k in RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(enc(data)).hexdigest()}
        equal_or_fail(receipt, expected)
    return True


def execute(root, opdir, manifest_path):
    root, opdir = pathlib.Path(root), pathlib.Path(opdir)
    fixture = manifest(manifest_path)
    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_retained_url_paths_readonly_v1.json"
    head = os.environ.get("MATCH_SOURCE_SHA", "")
    if not root.is_dir() or root.is_symlink() or root.resolve() != root or root.name != "anytoour.ru" or not opdir.is_dir() or opdir.is_symlink() or opdir.resolve() != opdir or opdir.name != OP or opdir.parent.name != "operations" or opdir.parent.parent.name != ".anytoour-match" or pathlib.Path(manifest_path) != expected_fixture or not re.fullmatch(r"[0-9a-f]{40}", head):
        raise ValueError("runtime_scope")
    reservation = parsed(file_bytes(opdir / "reservation.json", 1048576))
    expected = {"operation": OP, "batch": BATCH, "source_sha": head, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}
    if any(not equal_typed(reservation.get(k), v) for k, v in expected.items()):
        raise ValueError("reservation_scope")
    for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):
        if (opdir / name).exists() or (opdir / name).is_symlink():
            raise ValueError("terminal_no_replay")
    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "no_replay": True})
    capture, private_sha, rows = None, None, []
    state, reason, failure_stage = "terminal_failed_no_replay", "nonbg5_url_paths_capture_or_validation_failed", None
    try:
        capture = capture_retained(opdir.parent.parent, fixture)
        if len(enc(capture)) > 16777216:
            raise ValueError("private_projection_cap")
        private_sha = save(opdir / "current-input.json", capture)
        rows = capture["rows"]
        state = "completed_read_only_nonbg5_url_paths_incomplete" if any(r["projection"]["state"] == "hold" for row in rows for r in row["references"]) else "completed_read_only_nonbg5_url_paths"
        reason = None
    except Exception as failure:
        failure_stage = failure.stage if isinstance(failure, CaptureFailure) else "private_projection_or_save_failed"
        if private_sha is None:
            private_sha = save(opdir / "current-input.json", {"schema": "match-nonbg5-retained-url-paths-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "reason": reason, "failure_stage": failure_stage})
        rows = []
    output = {"schema": "match-nonbg5-retained-url-paths-readonly-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": state, "reason": reason, "failure_stage": failure_stage, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "inputs": fixture["inputs"], "requested_rows": 5, "requested_sources": 4, "distinct_source_count": len({r["catalog_id"] for r in rows}), "rows_examined": len(rows), "operator_ids": [13, 25, 43], "rows": rows, "metadata_files_bound": 2 if rows else 0, "metadata_bytes_bound": capture["metadata_bytes_bound"] if rows else 0, "original_raw_files_read": 0, "references_bound": sum(len(r["references"]) for r in rows), "safe_path_candidate_references": sum(ref["projection"]["state"] == "safe_absolute_operator_path_candidate" for row in rows for ref in row["references"]), "safe_path_candidate_rows": sum(any(ref["projection"]["state"] == "safe_absolute_operator_path_candidate" for ref in row["references"]) for row in rows), "hold_counts": dict(collections.Counter(h for r in rows for h in r["holds"])), "global_saved_context_only": True, "source_namespace_bridge_verified": False, "target_native_identity_verified": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}
    try:
        validate_result(output)
    except Exception:
        output.update(state="terminal_failed_no_replay", reason="nonbg5_url_paths_capture_or_validation_failed", failure_stage="public_result_validation_failed", rows=[], rows_examined=0, distinct_source_count=0, metadata_files_bound=0, metadata_bytes_bound=0, references_bound=0, safe_path_candidate_references=0, safe_path_candidate_rows=0, hold_counts={})
        validate_result(output)
    digest = save(opdir / "result.json", output)
    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}
    validate_result(output, receipt, head)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: output[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))
    return 2 if output["state"] == "terminal_failed_no_replay" else 0


def self_test():
    p = path_projection("https://b2b.fstravel.com/hotels/354014", "b2b.fstravel.com")
    if p["numeric_path_tokens"] != ["354014"] or p["namespace_bridge_verified"] is not False or path_projection("https://intourist.ru/info/a/%2fsecret", "intourist.ru")["state"] != "hold":
        raise RuntimeError("self_test")
    manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg5_retained_url_paths_readonly_v1.json")
    print("self_test_passed_nonbg5_retained_url_paths")


if __name__ == "__main__":
    if sys.argv[1:] == ["--self-test"]:
        self_test()
    elif sys.argv[1:] == ["--execute"]:
        raise SystemExit(execute(os.environ.get("ANYTOUR_ROOT", ""), os.environ.get("MATCH_OPERATION_DIRECTORY", ""), os.environ.get("MATCH_MANIFEST", "")))
    else:
        raise SystemExit("use --self-test or exact reviewed --execute")
