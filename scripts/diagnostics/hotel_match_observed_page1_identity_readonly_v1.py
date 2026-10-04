#!/usr/bin/env python3
"""One sealed file-only export of newly observed normalized PAGE1 identities.

This never resumes the search and never treats normalized fields as raw SAMO
evidence, CURRENT registry ownership or admission. No supplier or DB modules.
"""
from __future__ import annotations

import datetime as dt
import hashlib
import ipaddress
import json
import os
import pathlib
import re
import stat
import subprocess
import sys
from urllib.parse import parse_qsl, unquote, urlsplit

OP = "int-andromeda-match-observed-page1-identity-20261005-v1"
BATCH = "observed-page1-20261004-175945"
FIXTURE_NAME = "hotel_match_observed_page1_identity_readonly_v1.json"
STATES = ("completed_read_only", "terminal_failed_no_replay")
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("acceptance_evaluated", "global_uniqueness_evaluated", "raw_samo_evidence_verified", "session_identity_verified", "route_identity_verified", "current_registry_verified", "safe_to_write_now")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", "no_replay", *NO_EFFECTS, *FALSE_FLAGS)
SHA = re.compile(r"[a-f0-9]{64}\Z")
ID = re.compile(r"[A-Za-z0-9_-]{1,128}\Z")
MAX_BYTES = 3 * 1024 * 1024
MAX_ENTRIES = 20000
PRODUCER_BLOBS = {"normalizer": "83f5dc66b640d43f7c2048fbb8632b4e5ed73405", "offer_store": "513316e94d8b35965066102e6178694e71c00fad", "endpoint": "06f48fd510fc0dd721899679f5b045f1d81a684c"}
FAILURE_STAGES = {"unsafe_file_path", "unsafe_file_type_or_size", "file_changed", "retained_file_unavailable", "config_directory_read", "searches_scope", "producer_source_changed", "inventory_bound", "page_symlink", "page_missing", "page_ambiguous", "page_header", "page_filter_keys", "page_criteria", "page_route_ids", "page_operator_filter", "page_store_binding", "page_snapshot_binding", "page_offer_count", "page_offer_reference_set", "offer_context", "offer_reference", "offer_private_reference", "offer_namespace", "offer_local_id", "identity_text", "identity_secret_like_text", "offer_hotel_content", "offer_region", "offer_category", "hotel_url", "offer_reference_set", "duplicate_json_key", "nonfinite_json", "capture_unclassified"}


def need(value, reason):
    if not value:
        raise ValueError(reason)


def enc(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False).encode() + b"\n"


def typed_equal(a, b):
    return type(a) is type(b) and enc(a) == enc(b)


def parsed(raw):
    def pairs(values):
        out = {}
        for key, value in values:
            need(key not in out, "duplicate_json_key")
            out[key] = value
        return out
    return json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: (_ for _ in ()).throw(ValueError("nonfinite_json")))


def file_bytes(path, maximum=MAX_BYTES):
    path = pathlib.Path(path)
    need(path.is_absolute() and path.resolve() == path, "unsafe_file_path")
    before = path.lstat()
    need(stat.S_ISREG(before.st_mode) and 0 < before.st_size <= maximum, "unsafe_file_type_or_size")
    fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    try:
        with os.fdopen(fd, "rb") as stream:
            opened = os.fstat(stream.fileno())
            need((opened.st_dev, opened.st_ino, opened.st_size) == (before.st_dev, before.st_ino, before.st_size), "file_changed")
            raw = stream.read(maximum + 1)
            after = os.fstat(stream.fileno())
        need(len(raw) == before.st_size and (after.st_size, after.st_mtime_ns) == (before.st_size, before.st_mtime_ns), "file_changed")
        return raw
    except Exception:
        raise ValueError("retained_file_unavailable") from None


def save(path, value):
    raw = enc(value)
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, "wb") as stream:
        need(stream.write(raw) == len(raw), "private_short_write")
        stream.flush()
        os.fsync(stream.fileno())
    fd = os.open(path.parent, os.O_RDONLY | os.O_DIRECTORY)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)
    need(file_bytes(path, max(MAX_BYTES * 2, len(raw))) == raw, "private_write_readback")
    return hashlib.sha256(raw).hexdigest()


def manifest(path):
    raw = file_bytes(path, 16384)
    data = parsed(raw)
    keys = {"schema", "operation", "batch", "window_start", "window_end", "created_at", "observed_normalized_offers", "criteria", "precursor", "meaning"}
    need(isinstance(data, dict) and set(data) == keys and data.get("schema") == "match-observed-page1-identity-fixture/1" and data.get("operation") == OP and data.get("batch") == BATCH, "fixture_scope")
    need(all(typed_equal(data[k], v) for k, v in {"created_at": 1791136785, "window_start": 1791136782, "window_end": 1791136804, "observed_normalized_offers": 50}.items()), "fixture_window")
    expected_criteria = {"CHECKIN_BEG": "20261014", "CHECKIN_END": "20261020", "NIGHTS_FROM": 7, "NIGHTS_TILL": 7, "ADULT": 2, "CHILD": 0, "CURRENCYINC": 643, "PACKETTYPE": 0, "PAGE": 1, "GROUP_BY": 32}
    precursor = {"source_sha": "3054d34bb587ca684d5ecbc5f3c306851785d624", "canonical_comment_id": 5983334797, "run_id": 37226290997, "job_id": 111506511063, "artifact_id": 11312042786, "artifact_zip_sha256": "f5ece9e7c04a5c526fb846ce42a5407107ee49815f17a620961be9d121764a3c", "result_sha256": "15f9318afb81e7a848352bd8db5aa4fee7ef041784c581582eee12f1d25c79a8"}
    need(typed_equal(data["criteria"], expected_criteria) and typed_equal(data["precursor"], precursor), "fixture_bindings")
    return data, hashlib.sha256(raw).hexdigest()


def positive(value, maximum=2147483647):
    return type(value) is int and 1 <= value <= maximum


def text(value, maximum=4096):
    need(isinstance(value, str) and 0 < len(value.encode()) <= maximum and not re.search(r"[\x00-\x1f\x7f]", value), "identity_text")
    need(not re.search(r"(?:password|passwd|secret|api[_-]?key|authorization|session|token|sid)\s*[:=]|bearer\s+|https?://", value, re.I), "identity_secret_like_text")
    return value


def public_url(value):
    if value is None:
        return None
    need(isinstance(value, str) and len(value.encode()) <= 2048 and not re.search(r"[\x00-\x20\x7f]", value), "hotel_url")
    try:
        u = urlsplit(value)
        host = u.hostname
        need(u.scheme == "https" and host and u.username is None and u.password is None and u.port is None, "hotel_url_authority")
        need(re.fullmatch(r"[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}", host) and not re.search(r"\.(?:local|localhost|internal|lan|invalid|test|example)$", host), "hotel_url_host")
        try:
            ipaddress.ip_address(host)
            raise ValueError("hotel_url_ip")
        except ValueError as error:
            need(str(error) != "hotel_url_ip", "hotel_url_ip")
        path = unquote(u.path)
        need(len(path.encode()) <= 1400 and not re.search(r"[\x00-\x20\x7f]|(?:password|secret|auth|api[_-]?key|token|sid|session)[=/]", path, re.I), "hotel_url_path")
        need(not u.fragment and not re.search(r"[\x00-\x20\x7f]", unquote(u.query)), "hotel_url_query")
        parts = parse_qsl(u.query, keep_blank_values=True, strict_parsing=True, max_num_fields=40) if u.query else []
        need(all(re.fullmatch(r"[A-Za-z0-9_.-]{1,80}", key) and len(value.encode()) <= 1024 and not re.search(r"(?:password|passwd|secret|api[_-]?key|authorization|session|token|sid|signature|credential|csrf|bearer)", key + "=" + value, re.I) for key, value in parts), "hotel_url_secret_query")
        # Preserve all safe selector tokens, including repeated/multiple native
        # selectors. Unsafe query/fragment stays only in immutable private input.
        return value
    except Exception:
        return None


def searches_directory(project):
    home = project.parent.parent
    runtime = project / "_preview/search3-anex-candidate"
    config = runtime / ".andromeda-private.php"
    file_bytes(config, 65536)
    # Existing trusted return-only config: discard every other key/output.
    # No supplier/auth/session/DB module is loaded. No network-capable functions.
    php = r"""error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
try{$c=require $argv[1];$p=$c['catalog_path']??null;
if(!is_array($c)||($c['enabled']??null)!==true||!is_string($p)||$p===''||$p[0]!=='/')throw new RuntimeException();
$r=['directory'=>dirname($p)];}catch(Throwable $e){$r=['blocked'=>true];}
while(ob_get_level())ob_end_clean();echo json_encode($r);"""
    call = subprocess.run(["php", "-n", "-d", "allow_url_fopen=0", "-d", "open_basedir=" + str(runtime), "-d", "disable_functions=curl_init,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,exec,shell_exec,system,passthru,popen,proc_open,mail", "-r", php, str(config)], capture_output=True, text=True, timeout=20)
    need(call.returncode == 0 and not call.stderr.strip() and len(call.stdout.encode()) < 4096, "config_directory_read")
    data = parsed(call.stdout)
    need(isinstance(data, dict) and set(data) == {"directory"} and isinstance(data["directory"], str), "config_directory_read")
    directory = pathlib.Path(data["directory"]) / "searches"
    need(directory.is_absolute() and directory.resolve() == directory and directory.is_dir() and not directory.is_symlink() and home in directory.parents and not (home / "www" in directory.parents and project not in directory.parents), "searches_scope")
    return directory, runtime


def producer_hashes(project, runtime):
    app = runtime / "app/integrations"
    if not (app / "andromeda-offer-store.php").exists():
        app = runtime.parent / "app/integrations"
    paths = {"normalizer": app / "andromeda-normalizer.php", "offer_store": app / "andromeda-offer-store.php", "endpoint": runtime / "api-andromeda-search3-preview.php"}
    result = {}
    for key, path in paths.items():
        raw = file_bytes(path, 2 * 1024 * 1024)
        blob = hashlib.sha1(b"blob " + str(len(raw)).encode() + b"\0" + raw).hexdigest()
        need(blob == PRODUCER_BLOBS[key], "producer_source_changed")
        result[key] = hashlib.sha256(raw).hexdigest()
    return result


def validate_page(data, ref, fixture):
    need(isinstance(data, dict) and type(data.get("version")) is int and data["version"] == 1 and data.get("search_ref") == ref and positive(data.get("generation")) and data.get("status") in ("partial", "complete") and data.get("error") is None and "error_code" not in data, "page_header")
    c = data.get("criteria")
    expected = fixture["criteria"]
    need(isinstance(c, dict) and set(c) in (set(expected) | {"TOWNFROMINC", "STATEINC"}, set(expected) | {"TOWNFROMINC", "STATEINC", "OPERATORS"}), "page_filter_keys")
    need(all(typed_equal(c.get(k), v) for k, v in expected.items()), "page_criteria")
    need(all((positive(c.get(k)) or isinstance(c.get(k), str) and re.fullmatch(r"[1-9][0-9]{0,9}", c[k]) and int(c[k]) <= 2147483647) for k in ("TOWNFROMINC", "STATEINC")), "page_route_ids")
    need("OPERATORS" not in c or isinstance(c["OPERATORS"], str) and len(c["OPERATORS"]) <= 300 and re.fullmatch(r"[1-9][0-9]*(?:,[1-9][0-9]*)*", c["OPERATORS"]), "page_operator_filter")
    store = data.get("store")
    need(isinstance(store, dict) and type(store.get("version")) is int and store["version"] == 1 and store.get("search_ref") == ref and typed_equal(store.get("generation"), data["generation"]) and typed_equal(store.get("created_at"), fixture["created_at"]) and typed_equal(store.get("expires_at"), fixture["created_at"] + 900) and typed_equal(store.get("criteria"), c), "page_store_binding")
    snapshot = store.get("snapshot")
    need(isinstance(snapshot, dict) and snapshot.get("provider") == "andromeda" and snapshot.get("search_ref") == ref and typed_equal(snapshot.get("generation"), data["generation"]) and type(snapshot.get("page")) is int and snapshot["page"] == 1 and snapshot.get("selection_enabled") is False and snapshot.get("status") == data["status"] and positive(snapshot.get("pages_count"), 1000), "page_snapshot_binding")
    offers, rejected = snapshot.get("offers"), snapshot.get("rejected")
    need(isinstance(offers, list) and len(offers) == fixture["observed_normalized_offers"] and isinstance(rejected, list) and len(rejected) <= 2000, "page_offer_count")
    raw_ids = store.get("raw_ids")
    need(isinstance(raw_ids, dict) and len(raw_ids) == len(offers), "page_offer_reference_set")
    grouped, seen = {}, set()
    for offer in offers:
        need(isinstance(offer, dict) and offer.get("provider") == "andromeda" and offer.get("search_ref") == ref and typed_equal(offer.get("generation"), data["generation"]) and offer.get("selection_enabled") is False, "offer_context")
        oref = offer.get("offer_ref")
        need(isinstance(oref, str) and re.fullmatch(r"offer_[a-f0-9]{64}", oref) and oref not in seen and oref in raw_ids, "offer_reference")
        # Supplier offer IDs remain private, never exported or interpreted.
        need(isinstance(raw_ids[oref], str) and 0 < len(raw_ids[oref].encode()) <= 2048, "offer_private_reference")
        seen.add(oref)
        ns, external, operator = offer.get("supplier_namespace"), offer.get("external_hotel_id"), offer.get("operator_ref")
        need(isinstance(external, str) and ID.fullmatch(external) and isinstance(operator, str) and ID.fullmatch(operator) and ns in ("andromeda_catalog", "operator_" + operator), "offer_namespace")
        local = offer.get("local_hotel_id")
        need(local is None or positive(local), "offer_local_id")
        name, op_name = text(offer.get("hotel")), text(offer.get("operator"))
        hc = offer.get("hotel_content")
        need(isinstance(hc, dict) and set(hc) == {"source", "image_url", "hotel_url", "region", "category"} and hc["source"] == "andromeda", "offer_hotel_content")
        region = hc["region"]
        need(isinstance(region, str) and len(region.encode()) <= 720, "offer_region")
        if region:
            text(region, 720)
        category = hc["category"]
        need(category is None or type(category) is int and 1 <= category <= 5, "offer_category")
        url = public_url(hc["hotel_url"])
        row = grouped.setdefault((ns, external), {"supplier_namespace": ns, "external_hotel_id": external, "hotel_names": set(), "operator_refs": set(), "operator_names": set(), "local_hotel_ids": set(), "regions": set(), "categories": set(), "hotel_urls": set(), "redacted_hotel_url_sha256": set(), "offer_count": 0, "unresolved_offer_count": 0, "resolved_offer_count": 0, "operators": {}})
        row["hotel_names"].add(name); row["operator_refs"].add(operator); row["operator_names"].add(op_name)
        if region: row["regions"].add(region)
        if category is not None: row["categories"].add(category)
        if url is not None: row["hotel_urls"].add(url)
        elif hc["hotel_url"] is not None: row["redacted_hotel_url_sha256"].add(hashlib.sha256(hc["hotel_url"].encode()).hexdigest())
        if local is not None: row["local_hotel_ids"].add(local)
        row["offer_count"] += 1
        row["unresolved_offer_count" if local is None else "resolved_offer_count"] += 1
        op = row["operators"].setdefault(operator, {"operator_ref": operator, "operator_names": set(), "offer_count": 0, "unresolved_offer_count": 0, "resolved_offer_count": 0, "local_hotel_ids": set()})
        op["operator_names"].add(op_name); op["offer_count"] += 1
        op["unresolved_offer_count" if local is None else "resolved_offer_count"] += 1
        if local is not None: op["local_hotel_ids"].add(local)
    roster = []
    for key in sorted(grouped):
        row = grouped[key]
        operators = row.pop("operators")
        row["operator_observations"] = [operators[op] for op in sorted(operators)]
        for item in [row, *row["operator_observations"]]:
            for k, value in list(item.items()):
                if isinstance(value, set): item[k] = sorted(value)
        roster.append(row)
    need(seen == set(raw_ids), "offer_reference_set")
    return roster, len(rejected)


def capture(project, fixture):
    directory, runtime = searches_directory(project)
    hashes = producer_hashes(project, runtime)
    candidates = []
    for index, path in enumerate(directory.iterdir()):
        need(index < MAX_ENTRIES, "inventory_bound")
        if not re.fullmatch(r"[a-f0-9]{64}-1\.json", path.name): continue
        need(not path.is_symlink(), "page_symlink")
        if path.is_file() and fixture["window_start"] - 2 <= int(path.stat().st_mtime) <= fixture["window_end"] + 2:
            candidates.append(path)
    need(len(candidates) == 1, "page_missing" if not candidates else "page_ambiguous")
    path = candidates[0]
    raw = file_bytes(path)
    ref = path.name[:-7]
    data = parsed(raw)
    roster, rejected = validate_page(data, ref, fixture)
    return {"schema": "match-observed-page1-identity-private-input/1", "operation": OP, "batch": BATCH, "precursor": fixture["precursor"], "page_binding": {"relative_filename": path.name, "search_ref": ref, "generation": data["generation"], "created_at": fixture["created_at"], "expires_at": fixture["created_at"] + 900, "criteria": data["criteria"], "retained_page_sha256": hashlib.sha256(raw).hexdigest(), "retained_page_bytes": len(raw)}, "retained_page_utf8": raw.decode("utf-8"), "producer_sha256": hashes, "hotel_roster": roster, "rejected_rows": rejected}


def validate_result(data, receipt=None, expected_source=None):
    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "failure_stage", "captured_at_utc", "private_input_sha256", "fixture_sha256", "precursor", "retained_page_sha256", "retained_page_bytes", "producer_sha256", "created_at_utc", "source_semantics", "search_ref_filename_verified", "unsafe_hotel_urls_private_only", "examined_offers", "unique_hotel_identities", "unresolved_offer_count", "resolved_offer_count", "rejected_rows", "hotel_roster", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}
    need(isinstance(data, dict) and set(data) == keys and len(enc(data)) <= 2 * 1024 * 1024 and data["schema"] == "match-observed-page1-identity-result/1" and data["operation"] == OP and data["batch"] == BATCH and data["state"] in STATES, "public_scope")
    need(isinstance(data["source_sha"], str) and re.fullmatch(r"[a-f0-9]{40}", data["source_sha"]) and (expected_source is None or data["source_sha"] == expected_source), "public_source")
    need(SHA.fullmatch(data["private_input_sha256"] or "") and SHA.fullmatch(data["fixture_sha256"] or ""), "public_digests")
    fixture, digest = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / FIXTURE_NAME)
    need(data["fixture_sha256"] == digest and typed_equal(data["precursor"], fixture["precursor"]), "public_fixture")
    need(all(type(data[k]) is int and data[k] == 0 for k in NO_EFFECTS) and all(data[k] is False for k in FALSE_FLAGS) and data["no_replay"] is True and data["unsafe_hotel_urls_private_only"] is True and data["source_semantics"] == "normalized_page1_identity_only", "public_authority")
    need(isinstance(data["captured_at_utc"], str) and re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", data["captured_at_utc"]), "public_time")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    need(data["created_at_utc"] == "2026-10-04T17:59:45Z", "public_created")
    need(isinstance(data["hotel_roster"], list) and type(data["unique_hotel_identities"]) is int and data["unique_hotel_identities"] == len(data["hotel_roster"]), "public_roster")
    if data["state"] == STATES[1]:
        need(data["reason"] == "retained_page1_identity_capture_failed" and data["failure_stage"] in FAILURE_STAGES and data["hotel_roster"] == [] and data["search_ref_filename_verified"] is False and data["retained_page_sha256"] is None and type(data["retained_page_bytes"]) is int and data["retained_page_bytes"] == 0 and data["producer_sha256"] == {} and all(type(data[k]) is int and data[k] == 0 for k in ("examined_offers", "unresolved_offer_count", "resolved_offer_count", "rejected_rows")), "public_failed")
    else:
        need(data["reason"] is None and data["failure_stage"] is None and data["search_ref_filename_verified"] is True and SHA.fullmatch(data["retained_page_sha256"] or "") and type(data["retained_page_bytes"]) is int and 0 < data["retained_page_bytes"] <= MAX_BYTES and set(data["producer_sha256"]) == set(PRODUCER_BLOBS) and all(SHA.fullmatch(v) for v in data["producer_sha256"].values()), "public_complete")
        validate_roster(data["hotel_roster"])
        need(type(data["examined_offers"]) is int and data["examined_offers"] == 50 and data["examined_offers"] == sum(r["offer_count"] for r in data["hotel_roster"]) and type(data["unresolved_offer_count"]) is int and data["unresolved_offer_count"] == sum(r["unresolved_offer_count"] for r in data["hotel_roster"]) and type(data["resolved_offer_count"]) is int and data["resolved_offer_count"] == sum(r["resolved_offer_count"] for r in data["hotel_roster"]) and type(data["rejected_rows"]) is int and 0 <= data["rejected_rows"] <= 2000, "public_counts")
    if receipt is not None:
        need(typed_equal(receipt, {k: data[k] for k in RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(enc(data)).hexdigest()}), "public_receipt")
    return True


def validate_roster(roster):
    row_keys = {"supplier_namespace", "external_hotel_id", "hotel_names", "operator_refs", "operator_names", "local_hotel_ids", "regions", "categories", "hotel_urls", "redacted_hotel_url_sha256", "offer_count", "unresolved_offer_count", "resolved_offer_count", "operator_observations"}
    op_keys = {"operator_ref", "operator_names", "offer_count", "unresolved_offer_count", "resolved_offer_count", "local_hotel_ids"}
    previous = None
    for row in roster:
        need(isinstance(row, dict) and set(row) == row_keys and isinstance(row["external_hotel_id"], str) and ID.fullmatch(row["external_hotel_id"]), "public_hotel_row")
        key = (row["supplier_namespace"], row["external_hotel_id"])
        need(previous is None or key > previous, "public_hotel_order")
        previous = key
        need(isinstance(row["operator_observations"], list) and 0 < len(row["operator_observations"]) <= 50, "public_operator_rows")
        refs, names, locals_ = [], set(), set()
        for op in row["operator_observations"]:
            need(isinstance(op, dict) and set(op) == op_keys and isinstance(op["operator_ref"], str) and ID.fullmatch(op["operator_ref"]), "public_operator_row")
            refs.append(op["operator_ref"])
            need(row["supplier_namespace"] in ("andromeda_catalog", "operator_" + op["operator_ref"]), "public_namespace")
            for item in (op,):
                need(all(type(item[k]) is int and 0 <= item[k] <= 50 for k in ("offer_count", "unresolved_offer_count", "resolved_offer_count")) and item["offer_count"] > 0 and item["offer_count"] == item["unresolved_offer_count"] + item["resolved_offer_count"], "public_operator_counts")
            need(isinstance(op["operator_names"], list) and op["operator_names"] and op["operator_names"] == sorted(set(op["operator_names"])), "public_operator_names")
            for name in op["operator_names"]: text(name)
            need(isinstance(op["local_hotel_ids"], list) and op["local_hotel_ids"] == sorted(set(op["local_hotel_ids"])) and all(positive(v) for v in op["local_hotel_ids"]) and (bool(op["local_hotel_ids"]) == bool(op["resolved_offer_count"])), "public_operator_local_ids")
            names.update(op["operator_names"]); locals_.update(op["local_hotel_ids"])
        need(refs == sorted(set(refs)) and row["operator_refs"] == refs and row["operator_names"] == sorted(names) and row["local_hotel_ids"] == sorted(locals_), "public_operator_relations")
        need(all(type(row[k]) is int and row[k] == sum(o[k] for o in row["operator_observations"]) for k in ("offer_count", "resolved_offer_count", "unresolved_offer_count")), "public_row_counts")
        for k in ("hotel_names", "regions", "hotel_urls", "categories"):
            need(isinstance(row[k], list) and row[k] == sorted(set(row[k])), "public_row_list")
        need(bool(row["hotel_names"]), "public_names")
        for name in row["hotel_names"]: text(name)
        for region in row["regions"]: text(region, 720)
        need(all(type(v) is int and 1 <= v <= 5 for v in row["categories"]), "public_categories")
        for url in row["hotel_urls"]: need(public_url(url) == url, "public_url")
        need(isinstance(row["redacted_hotel_url_sha256"], list) and row["redacted_hotel_url_sha256"] == sorted(set(row["redacted_hotel_url_sha256"])) and all(SHA.fullmatch(v) for v in row["redacted_hotel_url_sha256"]), "public_redacted_urls")


def execute(project, opdir, manifest_path, source_root, result_path, head):
    project, opdir, source_root = map(pathlib.Path, (project, opdir, source_root))
    own_root = pathlib.Path(__file__).resolve().parents[2]
    expected_fixture = own_root / "scripts/diagnostics/fixtures" / FIXTURE_NAME
    need(project.is_absolute() and project.resolve() == project and project.is_dir() and project.name == "anytoour.ru" and project.parent.name == "www" and source_root == own_root and pathlib.Path(manifest_path) == expected_fixture and isinstance(head, str) and re.fullmatch(r"[a-f0-9]{40}", head), "runtime_scope")
    need(opdir.resolve() == opdir and opdir.is_dir() and not opdir.is_symlink() and opdir == project.parent.parent / ".anytoour-match/operations" / OP and pathlib.Path(result_path) == opdir / "result.json", "private_operation_scope")
    fixture, fixture_sha = manifest(expected_fixture)
    expected_reservation = {"operation": OP, "batch": BATCH, "source_sha": head, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_retained_read"}
    need(typed_equal(parsed(file_bytes(opdir / "reservation.json", 16384)), expected_reservation), "reservation_scope")
    need(not any((opdir / name).exists() or (opdir / name).is_symlink() for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json")), "terminal_no_replay")
    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "no_replay": True})
    captured = None
    failure_stage = None
    try:
        captured = capture(project, fixture)
        private_sha = save(opdir / "current-input.json", captured)
    except Exception as error:
        failure_stage = str(error) if type(error) is ValueError and str(error) in FAILURE_STAGES else "capture_unclassified"
        private_sha = save(opdir / "current-input.json", {"schema": "match-observed-page1-identity-private-input/1", "operation": OP, "batch": BATCH, "state": "capture_failed", "reason": "retained_page1_identity_capture_failed", "failure_stage": failure_stage})
        captured = None
    roster = captured["hotel_roster"] if captured else []
    binding = captured["page_binding"] if captured else {}
    result = {"schema": "match-observed-page1-identity-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": STATES[0] if captured else STATES[1], "reason": None if captured else "retained_page1_identity_capture_failed", "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": private_sha, "fixture_sha256": fixture_sha, "precursor": fixture["precursor"], "retained_page_sha256": binding.get("retained_page_sha256"), "retained_page_bytes": binding.get("retained_page_bytes", 0), "producer_sha256": captured["producer_sha256"] if captured else {}, "created_at_utc": "2026-10-04T17:59:45Z", "source_semantics": "normalized_page1_identity_only", "search_ref_filename_verified": captured is not None, "unsafe_hotel_urls_private_only": True, "examined_offers": sum(r["offer_count"] for r in roster), "unique_hotel_identities": len(roster), "unresolved_offer_count": sum(r["unresolved_offer_count"] for r in roster), "resolved_offer_count": sum(r["resolved_offer_count"] for r in roster), "rejected_rows": captured["rejected_rows"] if captured else 0, "hotel_roster": roster, "no_replay": True, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}
    result["failure_stage"] = failure_stage
    validate_result(result)
    digest = save(opdir / "result.json", result)
    receipt = {k: result[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}
    validate_result(result, receipt, head)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: result[k] for k in ("state", "examined_offers", "accepted", "written")}, sort_keys=True))
    return 0 if captured else 2


if __name__ == "__main__":
    if sys.argv[1:] == ["--self-test"]:
        manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / FIXTURE_NAME)
        print("self_test_passed_observed_page1_identity")
    elif sys.argv[1:] == ["--execute"]:
        try:
            raise SystemExit(execute(os.environ.get("ANYTOUR_ROOT", ""), os.environ.get("MATCH_PRIVATE_DIRECTORY", ""), os.environ.get("MATCH_CURRENT_MANIFEST_PATH", ""), os.environ.get("MATCH_SOURCE_ROOT", ""), os.environ.get("MATCH_RESULT_PATH", ""), os.environ.get("MATCH_SOURCE_SHA", "")))
        except Exception:
            raise SystemExit("observed_page1_identity_contract_refused") from None
    else:
        raise SystemExit("use --self-test or exact reviewed --execute")
