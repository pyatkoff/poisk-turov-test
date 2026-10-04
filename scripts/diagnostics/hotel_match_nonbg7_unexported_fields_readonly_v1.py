#!/usr/bin/env python3
"""Project only two omitted retained NONBG7 fields; no provider or DB entry point."""
import collections
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import sys
import stat
import urllib.parse

OP = "int-andromeda-match-nonbg7-unexported-fields-20261004-v1"
BATCH = "nonbg7-unexported-fields-20261004"
MODE = "match-nonbg7-unexported-fields-readonly"
MANIFEST_SHA = "220cfc26cab422113d2caf6ce61080e8020a0fb5f9f2548916e828fa3cad43be"
FIELDS = ("row.hotelUrl", "original.tourKey")
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", "provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written", *FALSE_FLAGS, "no_replay")
STATES = ("completed_read_only_nonbg7_fields", "completed_read_only_nonbg7_fields_incomplete", "terminal_failed_no_replay")
BASE_HOLDS = ("independent_operator_target_proof_not_evaluated", "current_registry_checks_not_performed", "current_global_uniqueness_not_evaluated")
SHA = re.compile(r"[0-9a-f]{64}")
SECRET = re.compile(r"token|jwt|auth|pass|secret|session|sid|cookie|signature|api[_-]?key", re.I)
REF_PATH = re.compile(r"operations/hotel-match-[a-zA-Z0-9_-]+/(?:evidence-private/)?[a-zA-Z0-9_.-]+\.json")
ROSTER = (('2000029745', 'operator_5', '44562', 159, 13), ('2000109038', 'operator_5', '43661', 109380, 13), ('2000037261', 'operator_315', '354014', 59115, 25), ('2000068203', 'operator_315', '789636', 70782, 25), ('2000068203', 'operator_342', '17173', 70782, 43), ('2000052591', 'operator_342', '25728', 128, 43), ('2000073045', 'operator_342', '29363', 80964, 43))
RULES = {"operator_5": "anextour.ru", "operator_315": "fstravel.com", "operator_342": "intourist.ru"}
HOTEL_KEYS = {"hotel", "hotels", "hotelid", "hotel_id", "hotelcode", "hotel_code", "hotellist", "hotelkey", "hotel_key"}  # Existing reviewed namespace tooling aliases; candidate-only.
KEYS_BY_NAMESPACE = {"operator_5": HOTEL_KEYS, "operator_315": HOTEL_KEYS | {"hotelinc"}, "operator_342": HOTEL_KEYS | {"hotelinc"}}  # HOTELINC is also retained by reviewed user_search_delta rules.
MAX_FIELD_PUBLIC_WEIGHT = 262144
MAX_PROJECTION_PUBLIC_WEIGHT = 1572864  # Non-HOLD field budget; <=24 descriptors separately bounded.
MAX_CAP_DESCRIPTOR_WEIGHT = 8192
ROW_KEYS = ("catalog_id", "source_namespace", "source_native_id", "target_tv_hotel_id", "target_operator_id")
MAX_RAW_BYTES = 9654037



def enc(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False) + "\n").encode()


def save(path, value):
    path = pathlib.Path(path)
    if path.parent.is_symlink() or path.parent.resolve() != path.parent or not path.parent.is_dir():
        raise RuntimeError("output_directory")
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


def read_pinned(path, digest, maximum):
    raw = file_bytes(path, maximum)
    if not isinstance(digest, str) or not SHA.fullmatch(digest) or hashlib.sha256(raw).hexdigest() != digest:
        raise RuntimeError("retained_digest")
    return parsed(raw)


def manifest(path):
    value = read_pinned(path, MANIFEST_SHA, 65536)
    require_fields(value, {"schema": "match-nonbg7-unexported-fields-readonly/1", "operation": OP, "batch": BATCH, "mode": MODE, "requested_rows": 7, "requested_sources": 6, "operator_ids": [13, 25, 43], "projection_fields": list(FIELDS)})
    rows = value.get("rows", [])
    if len(rows) != 7 or tuple(tuple(r.get(k) for k in ROW_KEYS) for r in rows) != ROSTER or len({r["catalog_id"] for r in rows}) != 6:
        raise RuntimeError("manifest_roster")
    refs = [ref for row in rows for ref in row["raw_references"]]
    if len(refs) != 12 or len({ref["source_file"] for ref in refs}) != 11 or len({ref_key(r) for r in refs}) != 12 or any(not REF_PATH.fullmatch(ref.get("source_file", "")) or not SHA.fullmatch(ref.get("sha256", "")) or not re.fullmatch(r"/PRICES/[0-9]+", ref.get("json_pointer", "")) for ref in refs):
        raise RuntimeError("manifest_references")
    by_path = collections.defaultdict(set)
    for ref in refs:
        by_path[ref["source_file"]].add(ref["sha256"])
    if any(len(v) != 1 for v in by_path.values()) or any(row["catalog_id"] == "2000086118" or row["source_namespace"] not in RULES for row in rows):
        raise RuntimeError("manifest_namespace_or_digest")
    if any(value[k] is not False for k in (*FALSE_FLAGS, "relative_origin_assumption_permitted", "opaque_tour_key_identity_proof_permitted")) or any(type(value[k]) is not int or value[k] != 0 for k in ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "maximum_writes")):
        raise RuntimeError("manifest_authority")
    require_fields(value["limits"], {"raw_references": 12, "unique_raw_files": 11, "total_raw_bytes": MAX_RAW_BYTES, "metadata_files": 1, "metadata_file_bytes": 1048576, "private_capture_bytes": 16777216})
    if set(value["inputs"]) != {"native_current", "global_v77_reread"} or value["inputs"]["global_v77_reread"] is not False:
        raise RuntimeError("manifest_inputs")
    return value


def pointer(value, path):
    if not isinstance(path, str) or not re.fullmatch(r"/PRICES/[0-9]+", path):
        raise RuntimeError("raw_pointer")
    for part in path[1:].split("/"):
        part = part.replace("~1", "/").replace("~0", "~")
        value = value[int(part)] if isinstance(value, list) else value[part]
    if not isinstance(value, dict):
        raise RuntimeError("raw_pointer_object")
    return value


def native_binding(raw, row):
    original = raw.get("original")
    return (isinstance(original, dict) and str(raw.get("hotelKey", "")) == row["catalog_id"]
            and str(raw.get("operatorKey", original.get("operatorKey", ""))) == row["source_namespace"].removeprefix("operator_")
            and ("operatorKey" not in original or str(original["operatorKey"]) == row["source_namespace"].removeprefix("operator_"))
            and str(original.get("hotelKey", "")) == row["source_native_id"]
            and raw.get("isOperatorHotelKey", False) not in (True, 1, "1", "true"))


def ref_key(ref):
    return (ref["source_file"], ref["sha256"], ref["json_pointer"])


class CaptureFailure(Exception):
    def __init__(self, stage):
        super().__init__(stage)
        self.stage = stage


FAILURE_STAGES = ("native_manifest_unavailable_or_digest", "native_manifest_lineage_mismatch", "selected_native_metadata_mismatch", "private_capture_or_projection_failed", "public_result_validation_failed")


def phase(stage, action):
    try:
        return action()
    except Exception:
        raise CaptureFailure(stage) from None


def capture_original(private_root, fixture):
    """One pinned metadata file, then only the seven omitted-field native facts."""
    inputs = fixture["inputs"]
    n = inputs["native_current"]
    current = phase("native_manifest_unavailable_or_digest", lambda: read_pinned(private_root / n["path"], n["sha256"], n["bytes"]))
    phase("native_manifest_lineage_mismatch", lambda: require_fields(current, {k: n[k] for k in ("schema", "operation", "source_sha", "batch")} | {"provider_http_calls": 0, "database_writes": 0, "mapping_writes": 0, "safe_to_write_now": False, "no_replay": True}))
    source = current.get("saved_evidence", {}).get("source_facts", {})
    def native_rows():
        if not isinstance(source, dict) or not isinstance(current.get("review_rows"), list):
            raise RuntimeError("source_facts")
        for row in fixture["rows"]:
            facts = [f for f in source.get(row["catalog_id"], []) if f.get("namespace") == row["source_namespace"] and f.get("native_id") == row["source_native_id"]]
            if len(facts) != 1:
                raise RuntimeError("source_fact_scope")
            raw = facts[0].get("raw", {})
            require_fields(raw, {"raw_verified": True, "failures": []})
            if facts[0].get("unique_catalog_in_saved_union") is not True or sorted(ref_key(r) for r in raw.get("references", [])) != sorted(ref_key(r) for r in row["raw_references"]) or any(r.get("verified") is not True for r in raw["references"]):
                raise RuntimeError("source_refs")
            reviews = [r for r in current["review_rows"] if r.get("catalog_id") == row["catalog_id"]]
            if len(reviews) != 1:
                raise RuntimeError("dated_review_scope")
            review = reviews[0]
            require_fields(review, {"catalog_digest_matches_saved": True, "evidence_digest_matches_saved": True, "source_history_id_matches": True, "holds": [], "safe_to_write_now": False})
            for key, expected in (("native_checks", row["dated_original_fact_check"]), ("operator_checks", row["dated_operator_ownership_fact"])):
                selected = [r for r in review.get(key, []) if r.get("namespace") == row["source_namespace"] and r.get("native_id") == row["source_native_id"]]
                if len(selected) != 1 or not equal_typed(selected[0], expected):
                    raise RuntimeError("dated_fact_binding")
    phase("selected_native_metadata_mismatch", native_rows)
    cache = {}
    total = 0
    read_count = 0
    rows = []
    for row in fixture["rows"]:
        references = []
        for ref in row["raw_references"]:
            filename = ref["source_file"]
            if filename not in cache:
                if len(cache) >= 11:
                    raise RuntimeError("raw_file_count_cap")
                data = None
                failure = None
                try:
                    path = private_root / filename
                    maximum = MAX_RAW_BYTES - total
                    b = file_bytes(path, maximum)
                    total += len(b)
                    read_count += 1
                    if hashlib.sha256(b).hexdigest() != ref["sha256"]:
                        raise RuntimeError("retained_digest")
                    data = parsed(b)
                    if not isinstance(data, dict):
                        raise RuntimeError("raw_shape")
                except Exception:
                    failure = "raw_file_unavailable_or_changed"
                cache[filename] = (data, failure)
            data, failure = cache[filename]
            fields = {}
            try:
                if data is None:
                    raise RuntimeError("raw_missing")
                raw = pointer(data, ref["json_pointer"])
                if not native_binding(raw, row):
                    raise RuntimeError("raw_identity_binding")
                fields = {"row.hotelUrl": {"present": "hotelUrl" in raw, "value": raw.get("hotelUrl")}, "original.tourKey": {"present": "tourKey" in raw["original"], "value": raw["original"].get("tourKey")}}
                failure = None
            except Exception:
                failure = failure or "raw_pointer_or_identity_changed"
            references.append({**ref, "raw_verified": failure is None, "failure": failure, "fields": fields})
        rows.append({**{k: row[k] for k in ROW_KEYS}, "references": references})
    return {"schema": "match-nonbg7-unexported-private-input/1", "operation": OP, "batch": BATCH, "inputs": inputs, "projection_fields": list(FIELDS), "raw_files_attempted": len(cache), "raw_files_read": read_count, "raw_bytes_read": total, "rows": rows}


# File/durability helpers and positional numeric-redaction projection adapted from
# reviewed BG5 SHA c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196.
# This new namespace parser retains all duplicate/signed candidates; it establishes no bridge.
def field_projection(name, field, expected, namespace):
    value = field["value"]
    raw = enc(value)
    kind = "null" if value is None else ("boolean" if type(value) is bool else ("number" if type(value) in (int, float) else ("string" if isinstance(value, str) else ("array" if isinstance(value, list) else "object"))))
    out = {"field_name": name, "present": field["present"], "value_type": kind, "value_sha256": hashlib.sha256(raw).hexdigest(), "value_bytes": len(raw), "representation": "opaque_or_non_url", "url_scheme": None, "origin_state": "not_established", "operator_host": None, "raw_selector_parameters": [], "raw_selector_values": [], "selector_value_sha256": [], "selector_token_positions": [], "opaque_selector_token_counts": [], "raw_selector_tokens": [], "positive_selector_candidates": [], "exact_source_native_candidate_observed": False, "namespace_bridge_verified": False, "projection_hold": None}
    if isinstance(value, str) and len(value) > 16384 and value.lower().startswith(("https://", "http://", "//", "/", "?")):
        out.update(representation="url_or_relative_url", projection_hold="field_selector_resource_cap")
        return out
    if not isinstance(value, str) or not value or len(value) > 16384 or re.search(r"[\x00-\x20\x7f]", value):
        return out
    if not value.lower().startswith(("https://", "http://", "//", "/", "?")):
        return out  # Opaque/numeric tourKey is never searched for numeric substrings.
    out["representation"] = "url_or_relative_url"
    try:
        p = urllib.parse.urlsplit(value)
        out["url_scheme"] = p.scheme.lower() or None
        if p.username or p.password or p.fragment or p.port not in (None, 80, 443):
            out["origin_state"] = "private_or_invalid_origin"
            return out
        pairs = urllib.parse.parse_qsl(p.query, keep_blank_values=True, max_num_fields=200)
    except ValueError:
        out["origin_state"] = "private_or_invalid_origin"
        return out
    if any(SECRET.search(k) for k, _ in pairs) or SECRET.search(urllib.parse.unquote(p.path)):
        out["origin_state"] = "private_parameters_redacted"
        return out
    if not p.scheme:
        out["origin_state"] = "relative_origin_unknown"
    elif p.scheme.lower() not in ("http", "https") or not p.hostname or not (p.hostname.lower() == RULES[namespace] or p.hostname.lower().endswith("." + RULES[namespace])):
        out["origin_state"] = "unexpected_origin"
        return out
    else:
        out.update(origin_state="absolute_operator_host_candidate", operator_host=p.hostname.lower())
    selected = [(k.lower(), v) for k, v in pairs if k.lower() in KEYS_BY_NAMESPACE[namespace]]
    values = [v for _, v in selected]
    tokens, positions, opaque, safe_values = [], [], [], []
    for index, value in enumerate(values):
        pieces = value.split(",")
        hidden = 0
        for position, token in enumerate(pieces):
            if re.fullmatch(r"[+-]?[0-9]{1,20}", token):
                tokens.append(token)
                positions.append([index, position])
            else:
                hidden += 1
        opaque.append(hidden)
        safe_values.append(None if hidden or len(value) > 1024 else value)
    if len(tokens) > 2000:
        out.update(url_scheme=None, origin_state="not_established", operator_host=None, projection_hold="field_selector_resource_cap")
        return out  # Complete values stay private; no truncation or identity verdict.
    positives = [t for t in tokens if re.fullmatch(r"[1-9][0-9]{0,19}", t)]
    out.update(raw_selector_parameters=[k for k, _ in selected], raw_selector_values=safe_values, selector_value_sha256=[hashlib.sha256(v.encode()).hexdigest() for v in values], selector_token_positions=positions, opaque_selector_token_counts=opaque, raw_selector_tokens=tokens, positive_selector_candidates=positives, exact_source_native_candidate_observed=expected in positives)
    return out


def public_projection_weight(field):
    raw = enc(field)
    # Source public envelope adds at most 12 spaces per line for field nesting.
    # A 24-space allowance plus fixed metadata slack bounds that without
    # treating pretty-print overhead as supplier evidence.
    return len(raw) + 24 * raw.count(b"\n") + 4096


def cap_projection(field):
    field = dict(field)
    field.update(url_scheme=None, origin_state="not_established", operator_host=None,
                 raw_selector_parameters=[], raw_selector_values=[], selector_value_sha256=[],
                 selector_token_positions=[], opaque_selector_token_counts=[], raw_selector_tokens=[],
                 positive_selector_candidates=[], exact_source_native_candidate_observed=False,
                 projection_hold="field_selector_resource_cap")
    return field


def project_capture(capture, fixture, private_sha):
    if capture.get("schema") != "match-nonbg7-unexported-private-input/1" or capture.get("operation") != OP or capture.get("batch") != BATCH or capture.get("projection_fields") != list(FIELDS) or capture.get("inputs") != fixture["inputs"] or tuple(tuple(r.get(k) for k in ROW_KEYS) for r in capture.get("rows", [])) != ROSTER:
        raise RuntimeError("private_capture_binding")
    rows = []
    projection_weight = 0
    for i, (raw, spec) in enumerate(zip(capture["rows"], fixture["rows"])):
        refs = raw.get("references", [])
        if [ref_key(r) for r in refs] != [ref_key(r) for r in spec["raw_references"]]:
            raise RuntimeError("private_reference_binding")
        output = []
        holds = list(BASE_HOLDS)
        if spec["dated_operator_ownership_fact"]["current_identity_count"] > 0:
            holds.append("scoped_operator_identity_present_dated")
        for j, ref in enumerate(refs):
            fields = [field_projection(name, ref["fields"][name], spec["source_native_id"], spec["source_namespace"]) for name in FIELDS] if ref.get("raw_verified") is True else []
            for index, field in enumerate(fields):
                weight = public_projection_weight(field)
                if field["projection_hold"] is None and (weight > MAX_FIELD_PUBLIC_WEIGHT or projection_weight + weight > MAX_PROJECTION_PUBLIC_WEIGHT):
                    field = cap_projection(field)
                    fields[index] = field
                if field["projection_hold"] is None:
                    projection_weight += weight
            if any(f["projection_hold"] is not None for f in fields):
                holds.extend(f["projection_hold"] for f in fields if f["projection_hold"] is not None)
            if any(f["present"] is False for f in fields):
                holds.append("unexported_field_missing")
            if ref.get("failure"):
                holds.append(ref["failure"])
            output.append({"source_file": ref["source_file"], "sha256": ref["sha256"], "json_pointer": ref["json_pointer"], "raw_verified": ref["raw_verified"], "failure": ref["failure"], "fields": fields, "private_input_pointer": {"sha256": private_sha, "json_pointer": f"/rows/{i}/references/{j}"}})
        rows.append({**{k: spec[k] for k in ROW_KEYS}, "target_id_namespace": "tourvisor", "independent_anytour_local_id": None, "dated_operator_ownership_fact": spec["dated_operator_ownership_fact"], "dated_original_fact_check": spec["dated_original_fact_check"], "references": output, "holds": list(dict.fromkeys(holds)), "source_namespace_bridge_verified": False, **dict.fromkeys(FALSE_FLAGS, False)})
    return rows


def validate_result(data, receipt=None, expected_source=None):
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg7_unexported_fields_readonly_v1.json")
    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "failure_stage", "captured_at_utc", "private_input_sha256", "inputs", "projection_fields", "requested_rows", "requested_sources", "distinct_source_count", "rows_examined", "operator_ids", "rows", "raw_files_attempted", "raw_files_read", "raw_bytes_read", "raw_references_verified", "selector_candidate_rows", "hold_counts", "global_saved_context_only", "source_namespace_bridge_verified", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}
    if len(enc(data)) > 2097152:
        raise RuntimeError("public_result_cap")
    if not isinstance(data, dict) or set(data) != keys or data["schema"] != "match-nonbg7-unexported-fields-readonly-result/1" or data["operation"] != OP or data["batch"] != BATCH or data["state"] not in STATES:
        raise RuntimeError("public_result_scope")
    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or (expected_source is not None and data["source_sha"] != expected_source) or not SHA.fullmatch(data["private_input_sha256"] or "") or not equal_typed(data["inputs"], fixture["inputs"]) or not equal_typed(data["projection_fields"], list(FIELDS)) or data["requested_rows"] != 7 or type(data["requested_rows"]) is not int or not equal_typed(data["operator_ids"], [13, 25, 43]) or type(data["requested_sources"]) is not int or data["requested_sources"] != 6:

        raise RuntimeError("public_result_binding")
    if any(type(data[k]) is not int or data[k] != 0 for k in NO_EFFECTS) or any(data[k] is not False for k in (*FALSE_FLAGS, "source_namespace_bridge_verified")) or data["no_replay"] is not True or data["global_saved_context_only"] is not True:
        raise RuntimeError("public_result_authority")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", data["captured_at_utc"]):
        raise RuntimeError("public_timestamp")
    failed = data["state"] == "terminal_failed_no_replay"
    if (failed and data["failure_stage"] not in FAILURE_STAGES) or (not failed and data["failure_stage"] is not None):
        raise RuntimeError("public_failure_stage")
    rows = data["rows"]
    if not isinstance(rows, list) or type(data["rows_examined"]) is not int or data["rows_examined"] != len(rows) or (failed and (rows or data["reason"] != "nonbg7_capture_or_validation_failed")) or (not failed and (len(rows) != 7 or data["reason"] is not None)):
        raise RuntimeError("public_state")
    if type(data["distinct_source_count"]) is not int or data["distinct_source_count"] != len({r["catalog_id"] for r in rows}):
        raise RuntimeError("public_distinct_source_count")
    for k, maximum in (("raw_files_attempted", 11), ("raw_files_read", 11), ("raw_bytes_read", MAX_RAW_BYTES), ("raw_references_verified", 12), ("selector_candidate_rows", 7)):
        if type(data[k]) is not int or not 0 <= data[k] <= maximum:
            raise RuntimeError("public_counts")
    if data["raw_files_read"] > data["raw_files_attempted"] or data["raw_bytes_read"] < data["raw_files_read"] or ((data["raw_files_read"] == 0) != (data["raw_bytes_read"] == 0)):
        raise RuntimeError("public_read_count")
    if (failed and any(data[k] != 0 for k in ("raw_references_verified", "selector_candidate_rows"))) or (not failed and data["raw_files_attempted"] != 11) or (not failed and ((data["raw_files_read"] == 0) != (data["raw_bytes_read"] == 0))) or (not failed and data["raw_references_verified"] > 0 and data["raw_files_read"] == 0) or (data["state"] == "completed_read_only_nonbg7_fields" and (data["raw_files_read"] != 11 or data["raw_bytes_read"] == 0 or data["raw_references_verified"] != 12)):
        raise RuntimeError("public_capture_count_binding")
    row_keys = {"catalog_id", "source_namespace", "source_native_id", "target_tv_hotel_id", "target_id_namespace", "independent_anytour_local_id", "target_operator_id", "dated_operator_ownership_fact", "dated_original_fact_check", "references", "holds", "source_namespace_bridge_verified", *FALSE_FLAGS}
    ref_keys = {"source_file", "sha256", "json_pointer", "raw_verified", "failure", "fields", "private_input_pointer"}
    field_keys = {"field_name", "present", "value_type", "value_sha256", "value_bytes", "representation", "url_scheme", "origin_state", "operator_host", "raw_selector_parameters", "raw_selector_values", "selector_value_sha256", "selector_token_positions", "opaque_selector_token_counts", "raw_selector_tokens", "positive_selector_candidates", "exact_source_native_candidate_observed", "namespace_bridge_verified", "projection_hold"}
    verified = 0
    projection_weight = 0
    candidate_rows = 0
    incomplete = False
    for i, row in enumerate(rows):
        spec = fixture["rows"][i]
        if not isinstance(row, dict) or set(row) != row_keys or not equal_typed({k: row[k] for k in ROW_KEYS}, dict(zip(ROW_KEYS, ROSTER[i]))) or row["target_id_namespace"] != "tourvisor" or row["independent_anytour_local_id"] is not None or row["source_namespace_bridge_verified"] is not False or any(row[k] is not False for k in FALSE_FLAGS) or not equal_typed(row["dated_operator_ownership_fact"], spec["dated_operator_ownership_fact"]) or not equal_typed(row["dated_original_fact_check"], spec["dated_original_fact_check"]):
            raise RuntimeError("public_row_authority")
        holds = row["holds"]
        if not isinstance(holds, list) or len(holds) != len(set(holds)) or not set(BASE_HOLDS).issubset(holds) or any(not isinstance(h, str) or not re.fullmatch(r"[a-z0-9_]{1,100}", h) for h in holds):
            raise RuntimeError("public_row_holds")
        if (spec["dated_operator_ownership_fact"]["current_identity_count"] > 0) != ("scoped_operator_identity_present_dated" in holds):
            raise RuntimeError("public_dated_ownership_hold")
        refs = row["references"]
        if not isinstance(refs, list) or [ref_key(r) for r in refs] != [ref_key(r) for r in spec["raw_references"]]:
            raise RuntimeError("public_reference_scope")
        candidate = False
        for j, ref in enumerate(refs):
            if not isinstance(ref, dict) or set(ref) != ref_keys or type(ref["raw_verified"]) is not bool or ref["private_input_pointer"] != {"sha256": data["private_input_sha256"], "json_pointer": f"/rows/{i}/references/{j}"} or ref["failure"] not in (None, "raw_file_unavailable_or_changed", "raw_pointer_or_identity_changed") or (ref["raw_verified"] != (ref["failure"] is None)) or not isinstance(ref["fields"], list):
                raise RuntimeError("public_reference_binding")
            if not ref["raw_verified"]:
                incomplete = True
                if ref["fields"] or ref["failure"] not in holds:
                    raise RuntimeError("public_reference_hold")
                continue
            verified += 1
            if [f.get("field_name") for f in ref["fields"]] != list(FIELDS):
                raise RuntimeError("public_projection_field_scope")
            for f in ref["fields"]:
                weight = public_projection_weight(f)
                if f.get("projection_hold") is not None:
                    if weight > MAX_CAP_DESCRIPTOR_WEIGHT:
                        raise RuntimeError("public_hold_descriptor_resource_budget")
                else:
                    if weight > MAX_FIELD_PUBLIC_WEIGHT:
                        raise RuntimeError("public_field_resource_budget")
                    projection_weight += weight
                    if projection_weight > MAX_PROJECTION_PUBLIC_WEIGHT:
                        raise RuntimeError("public_projection_resource_budget")
                if not isinstance(f, dict) or set(f) != field_keys or type(f["present"]) is not bool or f["value_type"] not in ("null", "boolean", "number", "string", "array", "object") or not SHA.fullmatch(f["value_sha256"] or "") or type(f["value_bytes"]) is not int or not 0 < f["value_bytes"] <= MAX_RAW_BYTES or f["representation"] not in ("opaque_or_non_url", "url_or_relative_url") or f["url_scheme"] not in (None, "http", "https") or f["origin_state"] not in ("not_established", "private_or_invalid_origin", "private_parameters_redacted", "relative_origin_unknown", "unexpected_origin", "absolute_operator_host_candidate", "opaque_selector_redacted") or f["namespace_bridge_verified"] is not False or type(f["exact_source_native_candidate_observed"]) is not bool:
                    raise RuntimeError("public_field_shape")
                if f["projection_hold"] not in (None, "field_selector_resource_cap") or (f["projection_hold"] is not None and f["projection_hold"] not in holds):
                    raise RuntimeError("public_field_projection_hold")
                if f["representation"] == "url_or_relative_url" and (f["present"] is not True or f["value_type"] != "string"):
                    raise RuntimeError("public_url_type")
                host = f["operator_host"]
                if f["present"] is False and ("unexported_field_missing" not in holds or f["value_type"] != "null" or f["value_bytes"] != len(enc(None)) or f["value_sha256"] != hashlib.sha256(enc(None)).hexdigest()):
                    raise RuntimeError("public_missing_field_hold")
                if host is not None and (not isinstance(host, str) or not re.fullmatch(r"(?:[a-z0-9-]+\.)*" + re.escape(RULES[spec["source_namespace"]]), host)):
                    raise RuntimeError("public_field_host")
                values, tokens, positive, parameters = f["raw_selector_values"], f["raw_selector_tokens"], f["positive_selector_candidates"], f["raw_selector_parameters"]
                positions, digests, opaque = f["selector_token_positions"], f["selector_value_sha256"], f["opaque_selector_token_counts"]
                if not isinstance(values, list) or not isinstance(tokens, list) or not isinstance(parameters, list) or len(parameters) != len(values) or any(p not in KEYS_BY_NAMESPACE[spec["source_namespace"]] for p in parameters) or len(tokens) > 2000 or any(v is not None and not isinstance(v, str) for v in values) or any(not isinstance(t, str) or not re.fullmatch(r"[+-]?[0-9]{1,20}", t) for t in tokens) or positive != [t for t in tokens if re.fullmatch(r"[1-9][0-9]{0,19}", t)] or f["exact_source_native_candidate_observed"] != (spec["source_native_id"] in positive) or not isinstance(positions, list) or len(positions) != len(tokens) or not isinstance(digests, list) or len(digests) != len(values) or any(not isinstance(d, str) or not SHA.fullmatch(d) for d in digests) or not isinstance(opaque, list) or len(opaque) != len(values) or any(type(c) is not int or not 0 <= c <= 16385 for c in opaque):
                    raise RuntimeError("public_field_tokens")
                if any(not isinstance(p, list) or len(p) != 2 or any(type(n) is not int or n < 0 for n in p) or p[0] >= len(values) for p in positions) or positions != sorted(positions) or len({tuple(p) for p in positions}) != len(positions):
                    raise RuntimeError("public_selector_positions")
                for vi, value in enumerate(values):
                    parts = [(position[1], token) for position, token in zip(positions, tokens) if position[0] == vi]
                    if value is not None:
                        if opaque[vi] != 0 or hashlib.sha256(value.encode()).hexdigest() != digests[vi] or parts != list(enumerate(value.split(","))):
                            raise RuntimeError("public_selector_value_binding")
                    elif opaque[vi] == 0:
                        reconstructed = ",".join(token for _, token in parts)
                        if parts != list(enumerate(reconstructed.split(","))) or len(reconstructed) <= 1024 or hashlib.sha256(reconstructed.encode()).hexdigest() != digests[vi]:
                            raise RuntimeError("public_long_selector_value_binding")
                    elif any(position >= len(parts) + opaque[vi] for position, _ in parts):
                        raise RuntimeError("public_selector_redaction_binding")
                if tokens and not ((f["origin_state"] == "absolute_operator_host_candidate" and host is not None and f["url_scheme"] in ("http", "https")) or (f["origin_state"] == "relative_origin_unknown" and host is None and f["url_scheme"] is None)):
                    raise RuntimeError("public_field_origin")
                if f["projection_hold"] is not None and (tokens or values or parameters or positive or positions or digests or opaque or host is not None or f["url_scheme"] is not None or f["origin_state"] != "not_established" or f["exact_source_native_candidate_observed"]):
                    raise RuntimeError("public_cap_hold_authority")
                if f["representation"] == "opaque_or_non_url" and (host is not None or tokens or values or positive or parameters or positions or digests or opaque or f["url_scheme"] is not None or f["origin_state"] != "not_established" or f["exact_source_native_candidate_observed"]):
                    raise RuntimeError("public_opaque_identity")
                candidate = candidate or f["exact_source_native_candidate_observed"]
        candidate_rows += int(candidate)
    if verified != data["raw_references_verified"] or candidate_rows != data["selector_candidate_rows"] or not equal_typed(data["hold_counts"], dict(collections.Counter(h for r in rows for h in r["holds"]))) or (not failed and (data["state"] == "completed_read_only_nonbg7_fields_incomplete") != incomplete):
        raise RuntimeError("public_aggregate")
    def public_safe(value):
        if isinstance(value, str) and (len(value) > 1024 or re.search(r"https?://|[\x00-\x1f\x7f]", value, re.I)):
            raise RuntimeError("public_private_data")
        if isinstance(value, dict):
            for v in value.values():
                public_safe(v)
        elif isinstance(value, list):
            for v in value:
                public_safe(v)
    public_safe(data)
    if receipt is not None and (not isinstance(receipt, dict) or set(receipt) != {*RECEIPT_KEYS, "result_sha256"} or any(type(receipt[k]) is not type(data[k]) or receipt[k] != data[k] for k in RECEIPT_KEYS) or receipt["result_sha256"] != hashlib.sha256(enc(data)).hexdigest()):
        raise RuntimeError("public_receipt_binding")
    return data


def execute(root, opdir, manifest_path):
    root, opdir = pathlib.Path(root), pathlib.Path(opdir)
    fixture = manifest(manifest_path)
    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg7_unexported_fields_readonly_v1.json"
    head = os.environ.get("MATCH_SOURCE_SHA", "")
    if not root.is_dir() or root.is_symlink() or root.name != "anytoour.ru" or root.resolve() != root or not opdir.is_dir() or opdir.is_symlink() or opdir.resolve() != opdir or opdir.name != OP or opdir.parent.name != "operations" or opdir.parent.parent.name != ".anytoour-match" or pathlib.Path(manifest_path) != expected_fixture or not re.fullmatch(r"[0-9a-f]{40}", head):
        raise RuntimeError("runtime_scope")
    reservation = json.loads(file_bytes(opdir / "reservation.json", 1048576))
    if any(type(reservation.get(k)) is not type(v) or reservation.get(k) != v for k, v in {"operation": OP, "batch": BATCH, "source_sha": head, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}.items()):
        raise RuntimeError("reservation_scope")
    for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):
        if (opdir / name).exists() or (opdir / name).is_symlink():
            raise RuntimeError("terminal_no_replay")
    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "no_replay": True})
    capture, private_sha, rows = None, None, []
    state, reason, failure_stage = "terminal_failed_no_replay", "nonbg7_capture_or_validation_failed", None
    try:
        capture = capture_original(opdir.parent.parent, fixture)
        if len(enc(capture)) > fixture["limits"]["private_capture_bytes"]:
            raise RuntimeError("private_capture_cap")
        private_sha = save(opdir / "current-input.json", capture)
        rows = project_capture(capture, fixture, private_sha)
        incomplete = any(ref["raw_verified"] is not True for row in rows for ref in row["references"])
        state = "completed_read_only_nonbg7_fields_incomplete" if incomplete else "completed_read_only_nonbg7_fields"
        reason = None
    except Exception as failure:
        failure_stage = failure.stage if isinstance(failure, CaptureFailure) else "private_capture_or_projection_failed"
        if private_sha is None:
            private_sha = save(opdir / "current-input.json", {"schema": "match-nonbg7-unexported-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "reason": reason, "failure_stage": failure_stage})
    output = {"schema": "match-nonbg7-unexported-fields-readonly-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": state, "reason": reason, "failure_stage": failure_stage, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "inputs": fixture["inputs"], "projection_fields": list(FIELDS), "requested_rows": 7, "requested_sources": 6, "distinct_source_count": len({row["catalog_id"] for row in rows}), "rows_examined": len(rows), "operator_ids": [13, 25, 43], "rows": rows, "raw_files_attempted": capture.get("raw_files_attempted", 0) if capture else 0, "raw_files_read": capture.get("raw_files_read", 0) if capture else 0, "raw_bytes_read": capture.get("raw_bytes_read", 0) if capture else 0, "raw_references_verified": sum(ref["raw_verified"] is True for row in rows for ref in row["references"]), "selector_candidate_rows": sum(any(f["exact_source_native_candidate_observed"] for ref in row["references"] for f in ref["fields"]) for row in rows), "hold_counts": dict(collections.Counter(h for row in rows for h in row["holds"])), "global_saved_context_only": True, "source_namespace_bridge_verified": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}
    try:
        validate_result(output)
    except Exception:
        output.update(state="terminal_failed_no_replay", reason="nonbg7_capture_or_validation_failed", failure_stage="public_result_validation_failed", rows=[], rows_examined=0, distinct_source_count=0, raw_references_verified=0, selector_candidate_rows=0, hold_counts={})
        validate_result(output)
    digest = save(opdir / "result.json", output)
    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}
    validate_result(output, receipt, head)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: output[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))
    return 2 if output["state"] == "terminal_failed_no_replay" else 0


def self_test():
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_nonbg7_unexported_fields_readonly_v1.json")
    p = field_projection("row.hotelUrl", {"present": True, "value": "https://agent.anextour.ru/?HOTELLIST=804,44562&HOTELLIST=-44562"}, "44562", "operator_5")
    assert p["raw_selector_tokens"] == ["804", "44562", "-44562"] and p["namespace_bridge_verified"] is False
    assert field_projection("original.tourKey", {"present": True, "value": "opaque_44562"}, "44562", "operator_5")["exact_source_native_candidate_observed"] is False
    assert len(fixture["rows"]) == 7 and len({r["catalog_id"] for r in fixture["rows"]}) == 6
    print("MATCH_NONBG7_UNEXPORTED_FIELDS_READONLY_V1_SELFTEST_OK")

if __name__ == "__main__":
    if "--self-test" in sys.argv:
        self_test()
    elif "--execute" in sys.argv:
        raise SystemExit(execute(os.environ["ANYTOUR_ROOT"], os.environ["MATCH_OPERATION_DIR"], os.environ["MATCH_MANIFEST_PATH"]))
    else:
        raise SystemExit("disabled")
