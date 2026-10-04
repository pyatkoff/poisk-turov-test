#!/usr/bin/env python3
"""Project only two omitted retained BG8 fields; no provider or DB entry point."""
import collections
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import sys
import urllib.parse

OP = "int-andromeda-match-bg8-unexported-fields-20261004-v1"
BATCH = "bg8-unexported-fields-20261004"
MODE = "match-bg8-unexported-fields-readonly"
MANIFEST_SHA = "8f51faf57a29343f3989c58315b939e10295e93befb5da8d6056763237e21207"
FIELDS = ("row.hotelUrl", "original.tourKey")
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", "provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written", *FALSE_FLAGS, "no_replay")
STATES = ("completed_read_only_bg8_fields", "completed_read_only_bg8_fields_incomplete", "terminal_failed_no_replay")
BASE_HOLDS = ("source_namespace_bridge_unverified", "current_registry_checks_not_performed", "current_global_uniqueness_not_evaluated", "official_hotel_dictionary_row_not_retained")
SHA = re.compile(r"[0-9a-f]{64}")
SECRET = re.compile(r"token|jwt|auth|pass|secret|session|cookie|signature|api[_-]?key", re.I)
REF_PATH = re.compile(r"operations/hotel-match-[a-zA-Z0-9_-]+/(?:evidence-private/)?[a-zA-Z0-9_.-]+\.json")
ROSTER = (("205729", "625414997", 9283, "102625414997"), ("2000041008", "610121438", 62868, "102610121438"), ("2000052316", "610144591", 70457, "102610144591"), ("2000059209", "610155352", 67000, "102610155352"), ("2000060910", "610175943", 72889, "102610175943"), ("2000062548", "610149698", 72865, "102610149698"), ("2000062557", "610179507", 75791, "102610179507"), ("2000086021", "668981793", 316, "102668981793"))


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
    if not path.is_file() or path.is_symlink() or path.resolve() != path or not 0 < path.stat().st_size <= maximum:
        raise RuntimeError("retained_file_unavailable")
    raw = path.read_bytes()
    if not 0 < len(raw) <= maximum:
        raise RuntimeError("retained_file_cap")
    return raw


def read_pinned(path, digest, maximum):
    raw = file_bytes(path, maximum)
    if not isinstance(digest, str) or not SHA.fullmatch(digest) or hashlib.sha256(raw).hexdigest() != digest:
        raise RuntimeError("retained_digest")
    value = json.loads(raw)
    if not isinstance(value, dict):
        raise RuntimeError("retained_shape")
    return value


def manifest(path):
    value = read_pinned(path, MANIFEST_SHA, 65536)
    if (value.get("schema"), value.get("operation"), value.get("batch"), value.get("mode"), value.get("requested_rows"), value.get("operator_ids"), value.get("projection_fields")) != ("match-bg8-unexported-fields-readonly/1", OP, BATCH, MODE, 8, [18], list(FIELDS)):
        raise RuntimeError("manifest_scope")
    rows = value.get("rows", [])
    if len(rows) != 8 or tuple((r.get("catalog_id"), r.get("source_native_id"), r.get("target_tv_hotel_id"), r.get("full_bg_key")) for r in rows) != ROSTER:
        raise RuntimeError("manifest_roster")
    refs = [ref for row in rows for ref in row["raw_references"]]
    if len(refs) != 18 or len({ref["source_file"] for ref in refs}) != 15 or any(not REF_PATH.fullmatch(ref.get("source_file", "")) or not SHA.fullmatch(ref.get("sha256", "")) or not re.fullmatch(r"/PRICES/[0-9]+", ref.get("json_pointer", "")) for ref in refs):
        raise RuntimeError("manifest_references")
    if any(row["source_namespace"] != "operator_115" or row["target_operator_id"] != 18 or row["catalog_id"] == "2000086118" for row in rows):
        raise RuntimeError("manifest_namespace")
    if any(value[k] is not False for k in (*FALSE_FLAGS, "generic_prefix_rule_permitted", "relative_origin_assumption_permitted", "opaque_tour_key_identity_proof_permitted")) or any(type(value[k]) is not int or value[k] != 0 for k in ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "maximum_writes")):
        raise RuntimeError("manifest_authority")
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
            and str(raw.get("operatorKey", original.get("operatorKey", ""))) == "115"
            and ("operatorKey" not in original or str(original["operatorKey"]) == "115")
            and str(original.get("hotelKey", "")) == row["source_native_id"]
            and raw.get("isOperatorHotelKey", False) not in (True, 1, "1", "true"))


def ref_key(ref):
    return (ref["source_file"], ref["sha256"], ref["json_pointer"])


def capture_original(private_root, fixture):
    """Read pinned metadata, then only selected original files, once each."""
    inputs = fixture["inputs"]
    current = read_pinned(private_root / inputs["native_current"]["path"], inputs["native_current"]["sha256"], 8388608)
    bg = read_pinned(private_root / inputs["bg18_terminal"]["path"], inputs["bg18_terminal"]["sha256"], 262144)
    if (current.get("operation") != "int-andromeda-match-native-current-20261001-v1" or current.get("batch") != "native110-20260928" or bg.get("operation") != "int-andromeda-match-native110-bg-evidence-20261001-v1" or bg.get("state") != "completed_bg_original_fields_review" or bg.get("input_sha256") != inputs["native_current"]["sha256"] or any(type(x.get(k)) is not int or x.get(k) != 0 for x in (current, bg) for k in ("provider_http_calls", "database_writes", "mapping_writes"))):
        raise RuntimeError("terminal_metadata_binding")
    source = current.get("saved_evidence", {}).get("source_facts", {})
    bg_rows = bg.get("rows")
    if not isinstance(source, dict) or not isinstance(bg_rows, list) or len(bg_rows) != 18:
        raise RuntimeError("terminal_metadata_shape")
    for row in fixture["rows"]:
        facts = [f for f in source.get(row["catalog_id"], []) if f.get("namespace") == "operator_115" and f.get("native_id") == row["source_native_id"]]
        terminal = [r for r in bg_rows if r.get("catalog_id") == row["catalog_id"]]
        if len(facts) != 1 or len(terminal) != 1:
            raise RuntimeError("terminal_row_scope")
        raw = facts[0].get("raw", {})
        expected = sorted(ref_key(r) for r in row["raw_references"])
        if raw.get("raw_verified") is not True or raw.get("failures") != [] or sorted(ref_key(r) for r in raw.get("references", [])) != expected or any(r.get("verified") is not True for r in raw["references"]):
            raise RuntimeError("terminal_raw_reference_binding")
        t = terminal[0]
        if (t.get("tv_hotel_id"), t.get("samo_native_id"), t.get("tv_native_id"), t.get("raw_references_examined"), t.get("failures"), t.get("safe_to_write_now")) != (row["target_tv_hotel_id"], row["source_native_id"], row["full_bg_key"], len(expected), [], False):
            raise RuntimeError("terminal_bg18_row_binding")
    cache = {}
    total = 0
    read_count = 0
    rows = []
    for row in fixture["rows"]:
        references = []
        for ref in row["raw_references"]:
            filename = ref["source_file"]
            if filename not in cache:
                if len(cache) >= 22:
                    raise RuntimeError("raw_file_count_cap")
                data = None
                failure = None
                try:
                    path = private_root / filename
                    maximum = 847514 - total
                    b = file_bytes(path, maximum)
                    total += len(b)
                    read_count += 1
                    if hashlib.sha256(b).hexdigest() != ref["sha256"]:
                        raise RuntimeError("retained_digest")
                    data = json.loads(b)
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
        rows.append({"catalog_id": row["catalog_id"], "references": references})
    return {"schema": "match-bg8-unexported-private-input/1", "operation": OP, "batch": BATCH, "inputs": inputs, "projection_fields": list(FIELDS), "raw_files_attempted": len(cache), "raw_files_read": read_count, "raw_bytes_read": total, "rows": rows}


def field_projection(name, field, expected):
    value = field["value"]
    raw = enc(value)
    kind = "null" if value is None else ("boolean" if type(value) is bool else ("number" if type(value) in (int, float) else ("string" if isinstance(value, str) else ("array" if isinstance(value, list) else "object"))))
    out = {"field_name": name, "present": field["present"], "value_type": kind, "value_sha256": hashlib.sha256(raw).hexdigest(), "value_bytes": len(raw), "representation": "opaque_or_non_url", "url_scheme": None, "origin_state": "not_established", "bg_host": None, "raw_selector_parameters": [], "raw_selector_values": [], "selector_value_sha256": [], "selector_token_positions": [], "opaque_selector_token_counts": [], "raw_selector_tokens": [], "positive_selector_candidates": [], "exact_expected_full_code_observed": False, "namespace_bridge_verified": False}
    if not isinstance(value, str) or not value or len(value) > 16384 or re.search(r"[\x00-\x20\x7f]", value):
        return out
    if not value.startswith(("https://", "http://", "//", "/", "?")):
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
    elif p.scheme.lower() not in ("http", "https") or not p.hostname or not (p.hostname.lower() == "bgoperator.ru" or p.hostname.lower().endswith(".bgoperator.ru")):
        out["origin_state"] = "unexpected_origin"
        return out
    else:
        out.update(origin_state="absolute_bg_host_candidate", bg_host=p.hostname.lower())
    selected = [(k.lower(), v) for k, v in pairs if k.lower() in ("code", "f4")]
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
        safe_values.append(None if hidden else value)
    positives = [t for t in tokens if re.fullmatch(r"[1-9][0-9]{0,19}", t)]
    out.update(raw_selector_parameters=[k for k, _ in selected], raw_selector_values=safe_values, selector_value_sha256=[hashlib.sha256(v.encode()).hexdigest() for v in values], selector_token_positions=positions, opaque_selector_token_counts=opaque, raw_selector_tokens=tokens, positive_selector_candidates=positives, exact_expected_full_code_observed=expected in positives)
    return out


def project_capture(capture, fixture, private_sha):
    if capture.get("schema") != "match-bg8-unexported-private-input/1" or capture.get("operation") != OP or capture.get("batch") != BATCH or capture.get("projection_fields") != list(FIELDS) or capture.get("inputs") != fixture["inputs"] or [r.get("catalog_id") for r in capture.get("rows", [])] != [r[0] for r in ROSTER]:
        raise RuntimeError("private_capture_binding")
    rows = []
    for i, (raw, spec) in enumerate(zip(capture["rows"], fixture["rows"])):
        refs = raw.get("references", [])
        if [ref_key(r) for r in refs] != [ref_key(r) for r in spec["raw_references"]]:
            raise RuntimeError("private_reference_binding")
        output = []
        holds = list(BASE_HOLDS)
        for j, ref in enumerate(refs):
            fields = [field_projection(name, ref["fields"][name], spec["full_bg_key"]) for name in FIELDS] if ref.get("raw_verified") is True else []
            if any(f["present"] is False for f in fields):
                holds.append("unexported_field_missing")
            if ref.get("failure"):
                holds.append(ref["failure"])
            output.append({"source_file": ref["source_file"], "sha256": ref["sha256"], "json_pointer": ref["json_pointer"], "raw_verified": ref["raw_verified"], "failure": ref["failure"], "fields": fields, "private_input_pointer": {"sha256": private_sha, "json_pointer": f"/rows/{i}/references/{j}"}})
        rows.append({"catalog_id": spec["catalog_id"], "source_namespace": "operator_115", "source_native_id": spec["source_native_id"], "target_tv_hotel_id": spec["target_tv_hotel_id"], "target_id_namespace": "tourvisor", "independent_anytour_local_id": None, "target_operator_id": 18, "full_bg_key": spec["full_bg_key"], "references": output, "holds": list(dict.fromkeys(holds)), "source_namespace_bridge_verified": False, **dict.fromkeys(FALSE_FLAGS, False)})
    return rows


def validate_result(data, receipt=None, expected_source=None):
    fixture = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_bg8_unexported_fields_readonly_v1.json")
    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "captured_at_utc", "private_input_sha256", "inputs", "projection_fields", "requested_rows", "rows_examined", "operator_ids", "rows", "raw_files_attempted", "raw_files_read", "raw_bytes_read", "raw_references_verified", "selector_candidate_rows", "hold_counts", "global_saved_context_only", "source_namespace_bridge_verified", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}
    if not isinstance(data, dict) or set(data) != keys or data["schema"] != "match-bg8-unexported-fields-readonly-result/1" or data["operation"] != OP or data["batch"] != BATCH or data["state"] not in STATES:
        raise RuntimeError("public_result_scope")
    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or (expected_source is not None and data["source_sha"] != expected_source) or not SHA.fullmatch(data["private_input_sha256"] or "") or data["inputs"] != fixture["inputs"] or data["projection_fields"] != list(FIELDS) or data["requested_rows"] != 8 or type(data["requested_rows"]) is not int or data["operator_ids"] != [18]:
        raise RuntimeError("public_result_binding")
    if any(type(data[k]) is not int or data[k] != 0 for k in NO_EFFECTS) or any(data[k] is not False for k in (*FALSE_FLAGS, "source_namespace_bridge_verified")) or data["no_replay"] is not True or data["global_saved_context_only"] is not True:
        raise RuntimeError("public_result_authority")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", data["captured_at_utc"]):
        raise RuntimeError("public_timestamp")
    failed = data["state"] == "terminal_failed_no_replay"
    rows = data["rows"]
    if not isinstance(rows, list) or type(data["rows_examined"]) is not int or data["rows_examined"] != len(rows) or (failed and (rows or data["reason"] != "bg8_capture_or_validation_failed")) or (not failed and (len(rows) != 8 or data["reason"] is not None)):
        raise RuntimeError("public_state")
    for k, maximum in (("raw_files_attempted", 22), ("raw_files_read", 22), ("raw_bytes_read", 847514), ("raw_references_verified", 18), ("selector_candidate_rows", 8)):
        if type(data[k]) is not int or not 0 <= data[k] <= maximum:
            raise RuntimeError("public_counts")
    if data["raw_files_read"] > data["raw_files_attempted"]:
        raise RuntimeError("public_read_count")
    if (failed and any(data[k] != 0 for k in ("raw_files_attempted", "raw_files_read", "raw_bytes_read", "raw_references_verified", "selector_candidate_rows"))) or (not failed and data["raw_files_attempted"] != 15) or (not failed and ((data["raw_files_read"] == 0) != (data["raw_bytes_read"] == 0))) or (not failed and data["raw_references_verified"] > 0 and data["raw_files_read"] == 0) or (data["state"] == "completed_read_only_bg8_fields" and (data["raw_files_read"] != 15 or data["raw_bytes_read"] == 0 or data["raw_references_verified"] != 18)):
        raise RuntimeError("public_capture_count_binding")
    row_keys = {"catalog_id", "source_namespace", "source_native_id", "target_tv_hotel_id", "target_id_namespace", "independent_anytour_local_id", "target_operator_id", "full_bg_key", "references", "holds", "source_namespace_bridge_verified", *FALSE_FLAGS}
    ref_keys = {"source_file", "sha256", "json_pointer", "raw_verified", "failure", "fields", "private_input_pointer"}
    field_keys = {"field_name", "present", "value_type", "value_sha256", "value_bytes", "representation", "url_scheme", "origin_state", "bg_host", "raw_selector_parameters", "raw_selector_values", "selector_value_sha256", "selector_token_positions", "opaque_selector_token_counts", "raw_selector_tokens", "positive_selector_candidates", "exact_expected_full_code_observed", "namespace_bridge_verified"}
    verified = 0
    candidate_rows = 0
    incomplete = False
    for i, row in enumerate(rows):
        spec = fixture["rows"][i]
        if not isinstance(row, dict) or set(row) != row_keys or tuple(row[k] for k in ("catalog_id", "source_native_id", "target_tv_hotel_id", "full_bg_key")) != ROSTER[i] or type(row["target_tv_hotel_id"]) is not int or row["source_namespace"] != "operator_115" or row["target_id_namespace"] != "tourvisor" or row["target_operator_id"] != 18 or type(row["target_operator_id"]) is not int or row["independent_anytour_local_id"] is not None or row["source_namespace_bridge_verified"] is not False or any(row[k] is not False for k in FALSE_FLAGS):
            raise RuntimeError("public_row_authority")
        holds = row["holds"]
        if not isinstance(holds, list) or len(holds) != len(set(holds)) or not set(BASE_HOLDS).issubset(holds) or any(not isinstance(h, str) or not re.fullmatch(r"[a-z0-9_]{1,100}", h) for h in holds):
            raise RuntimeError("public_row_holds")
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
                if not isinstance(f, dict) or set(f) != field_keys or type(f["present"]) is not bool or f["value_type"] not in ("null", "boolean", "number", "string", "array", "object") or not SHA.fullmatch(f["value_sha256"] or "") or type(f["value_bytes"]) is not int or not 0 < f["value_bytes"] <= 847514 or f["representation"] not in ("opaque_or_non_url", "url_or_relative_url") or f["url_scheme"] not in (None, "http", "https") or f["origin_state"] not in ("not_established", "private_or_invalid_origin", "private_parameters_redacted", "relative_origin_unknown", "unexpected_origin", "absolute_bg_host_candidate", "opaque_selector_redacted") or f["namespace_bridge_verified"] is not False or type(f["exact_expected_full_code_observed"]) is not bool:
                    raise RuntimeError("public_field_shape")
                host = f["bg_host"]
                if f["present"] is False and ("unexported_field_missing" not in holds or f["value_type"] != "null" or f["value_bytes"] != len(enc(None)) or f["value_sha256"] != hashlib.sha256(enc(None)).hexdigest()):
                    raise RuntimeError("public_missing_field_hold")
                if host is not None and (not isinstance(host, str) or not re.fullmatch(r"(?:[a-z0-9-]+\.)*bgoperator\.ru", host)):
                    raise RuntimeError("public_field_host")
                values, tokens, positive, parameters = f["raw_selector_values"], f["raw_selector_tokens"], f["positive_selector_candidates"], f["raw_selector_parameters"]
                positions, digests, opaque = f["selector_token_positions"], f["selector_value_sha256"], f["opaque_selector_token_counts"]
                if not isinstance(values, list) or not isinstance(tokens, list) or not isinstance(parameters, list) or len(parameters) != len(values) or any(p not in ("code", "f4") for p in parameters) or len(tokens) > 2000 or any(v is not None and not isinstance(v, str) for v in values) or any(not isinstance(t, str) or not re.fullmatch(r"[+-]?[0-9]{1,20}", t) for t in tokens) or positive != [t for t in tokens if re.fullmatch(r"[1-9][0-9]{0,19}", t)] or f["exact_expected_full_code_observed"] != (spec["full_bg_key"] in positive) or not isinstance(positions, list) or len(positions) != len(tokens) or not isinstance(digests, list) or len(digests) != len(values) or any(not isinstance(d, str) or not SHA.fullmatch(d) for d in digests) or not isinstance(opaque, list) or len(opaque) != len(values) or any(type(c) is not int or not 0 <= c <= 16385 for c in opaque):
                    raise RuntimeError("public_field_tokens")
                if any(not isinstance(p, list) or len(p) != 2 or any(type(n) is not int or n < 0 for n in p) or p[0] >= len(values) for p in positions) or positions != sorted(positions) or len({tuple(p) for p in positions}) != len(positions):
                    raise RuntimeError("public_selector_positions")
                for vi, value in enumerate(values):
                    parts = [(position[1], token) for position, token in zip(positions, tokens) if position[0] == vi]
                    if value is not None:
                        if opaque[vi] != 0 or hashlib.sha256(value.encode()).hexdigest() != digests[vi] or parts != list(enumerate(value.split(","))):
                            raise RuntimeError("public_selector_value_binding")
                    elif opaque[vi] == 0 or any(position >= len(parts) + opaque[vi] for position, _ in parts):
                        raise RuntimeError("public_selector_redaction_binding")
                if tokens and not ((f["origin_state"] == "absolute_bg_host_candidate" and host is not None and f["url_scheme"] in ("http", "https")) or (f["origin_state"] == "relative_origin_unknown" and host is None and f["url_scheme"] is None)):
                    raise RuntimeError("public_field_origin")
                if f["representation"] == "opaque_or_non_url" and (host is not None or tokens or f["url_scheme"] is not None):
                    raise RuntimeError("public_opaque_identity")
                candidate = candidate or f["exact_expected_full_code_observed"]
        candidate_rows += int(candidate)
    if verified != data["raw_references_verified"] or candidate_rows != data["selector_candidate_rows"] or data["hold_counts"] != dict(collections.Counter(h for r in rows for h in r["holds"])) or (not failed and (data["state"] == "completed_read_only_bg8_fields_incomplete") != incomplete):
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
    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_bg8_unexported_fields_readonly_v1.json"
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
    state, reason = "terminal_failed_no_replay", "bg8_capture_or_validation_failed"
    try:
        capture = capture_original(opdir.parent.parent, fixture)
        private_sha = save(opdir / "current-input.json", capture)
        rows = project_capture(capture, fixture, private_sha)
        incomplete = any(ref["raw_verified"] is not True for row in rows for ref in row["references"])
        state = "completed_read_only_bg8_fields_incomplete" if incomplete else "completed_read_only_bg8_fields"
        reason = None
    except Exception:
        if private_sha is None:
            private_sha = save(opdir / "current-input.json", {"schema": "match-bg8-unexported-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "reason": reason})
    output = {"schema": "match-bg8-unexported-fields-readonly-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": state, "reason": reason, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "inputs": fixture["inputs"], "projection_fields": list(FIELDS), "requested_rows": 8, "rows_examined": len(rows), "operator_ids": [18], "rows": rows, "raw_files_attempted": capture.get("raw_files_attempted", 0) if capture else 0, "raw_files_read": capture.get("raw_files_read", 0) if capture else 0, "raw_bytes_read": capture.get("raw_bytes_read", 0) if capture else 0, "raw_references_verified": sum(ref["raw_verified"] is True for row in rows for ref in row["references"]), "selector_candidate_rows": sum(any(f["exact_expected_full_code_observed"] for ref in row["references"] for f in ref["fields"]) for row in rows), "hold_counts": dict(collections.Counter(h for row in rows for h in row["holds"])), "global_saved_context_only": True, "source_namespace_bridge_verified": False, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}
    if state == "terminal_failed_no_replay":
        output.update(raw_files_attempted=0, raw_files_read=0, raw_bytes_read=0)
    try:
        validate_result(output)
    except Exception:
        output.update(state="terminal_failed_no_replay", reason="bg8_capture_or_validation_failed", rows=[], rows_examined=0, raw_files_attempted=0, raw_files_read=0, raw_bytes_read=0, raw_references_verified=0, selector_candidate_rows=0, hold_counts={})
        validate_result(output)
    digest = save(opdir / "result.json", output)
    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}
    validate_result(output, receipt, head)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: output[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))
    return 2 if output["state"] == "terminal_failed_no_replay" else 0


def self_test():
    manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_bg8_unexported_fields_readonly_v1.json")
    p = field_projection("row.hotelUrl", {"present": True, "value": "http://bgoperator.ru/price.shtml?code=102625414997&F4=-102625414997"}, "102625414997")
    assert p["raw_selector_tokens"] == ["102625414997", "-102625414997"] and p["namespace_bridge_verified"] is False
    assert field_projection("original.tourKey", {"present": True, "value": "opaque_102625414997"}, "102625414997")["exact_expected_full_code_observed"] is False
    assert field_projection("row.hotelUrl", {"present": True, "value": "/price.shtml?code=102625414997"}, "102625414997")["origin_state"] == "relative_origin_unknown"
    print("MATCH_BG8_UNEXPORTED_FIELDS_READONLY_V1_SELFTEST_OK")


if __name__ == "__main__":
    if "--self-test" in sys.argv:
        self_test()
    elif "--execute" in sys.argv:
        raise SystemExit(execute(os.environ["ANYTOUR_ROOT"], os.environ["MATCH_OPERATION_DIR"], os.environ["MATCH_MANIFEST_PATH"]))
    else:
        raise SystemExit("disabled")
