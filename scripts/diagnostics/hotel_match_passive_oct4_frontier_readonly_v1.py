#!/usr/bin/env python3
"""One new UTC-bounded export of passive normalized SAMO hotel observations.

No provider client, installation, schema change, search replay or registry write.
Complete DB projection is sealed before individual row sanitization or HOLD.
"""
from __future__ import annotations
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import stat
import subprocess
import sys
from urllib.parse import parse_qsl, unquote, urlsplit

OP = "int-andromeda-match-passive-oct4-frontier-20261005-v1"
BATCH = "passive-oct4-before175942"
LOWER, UPPER = "2026-10-04 00:00:00", "2026-10-04 17:59:42"
CAP = 5000
MAX_DB_BYTES = 64 * 1024 * 1024
MAX_PRIVATE_BYTES = 136 * 1024 * 1024
MAX_PUBLIC_BYTES = 32 * 1024 * 1024
MAX_BYTES = MAX_PRIVATE_BYTES
FIXTURE_NAME = "hotel_match_passive_oct4_frontier_readonly_v1.json"
MANIFEST_SHA = "64d4b3e497988068676d29b8c6318f6b02ef042f109cef3dda118cfcf164b3d2"
PRODUCER_BLOB = "5fdb35ed22821ce6ccbc24ad86e12e546e76e799"
DB_ENTRYPOINT_BLOB = "4ac8258ee4933c74ac20c2c9b771dfc88ab7537c"
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated", "original_event_verified", "session_identity_verified", "route_identity_verified", "raw_samo_evidence_verified", "independent_tv_identity_verified", "current_registry_verified")
DB_FACTS = ("database_reads", "database_read_attempts", "read_transaction_rolled_back", "php_invocations")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", "no_replay", *NO_EFFECTS, *DB_FACTS, *FALSE_FLAGS)
STATES = ("completed_read_only", "completed_with_holds", "held_overflow_no_replay", "terminal_failed_no_replay")
SHA = re.compile(r"[a-f0-9]{64}\Z")
ID = re.compile(r"[A-Za-z0-9_-]{1,128}\Z")
COLUMNS = ("observation_sha256", "search_evidence_sha256", "supplier_namespace", "external_hotel_id", "hotel_name", "operator_refs_json", "operator_names_json", "country_id", "country_name", "region_name", "category", "description_text", "image_url", "hotel_url", "content_sha256", "observed_at_utc")
ROW_KEYS = {"row_index", "raw_row_sha256", "observation_sha256", "observation_sha256_verified", "content_sha256", "content_hash_verified", "supplier_namespace", "external_hotel_id", "hotel_name", "operator_refs", "operator_names", "country_id", "country_name", "region_name", "category", "hotel_url", "image_url", "redacted_hotel_url_sha256", "redacted_image_url_sha256", "description_sha256", "observed_at_utc", "state", "holds"}
HOLD_REASONS = {"row_schema", "observation_hash_invalid", "observation_hash_mismatch", "content_hash_invalid", "content_hash_mismatch", "content_hash_unverifiable", "namespace_unrecognized", "external_id_invalid", "operator_refs_invalid", "operator_names_invalid", "operator_namespace_conflict", "hotel_name_redacted", "country_name_redacted", "region_name_redacted", "country_id_invalid", "category_invalid", "description_invalid", "hotel_url_redacted", "image_url_redacted", "timestamp_invalid", "outside_fixed_window", "duplicate_observation", "query_order_invalid"}
FAILURE_STAGES = {"producer_source_changed", "db_entrypoint_changed", "db_config_path", "db_entrypoint", "db_driver", "db_table_missing", "db_capture_failed", "db_capture_resource_cap", "db_projection_invalid", "db_subprocess_unclassified", "private_capture_failed", "public_resource_cap", "capture_unclassified"}
PHP_DISABLED = "curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail,exec,system,shell_exec,passthru,proc_open,popen,pcntl_exec"


def need(value, reason):
    if not value: raise ValueError(reason)


def enc(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False).encode() + b"\n"


def parsed(raw):
    def pairs(values):
        out = {}
        for key, value in values:
            need(key not in out, "duplicate_json_key")
            out[key] = value
        return out
    return json.loads(raw, object_pairs_hook=pairs, parse_constant=lambda _: (_ for _ in ()).throw(ValueError("nonfinite_json")))


def typed_equal(a, b):
    return type(a) is type(b) and enc(a) == enc(b)


def file_bytes(path, maximum=MAX_PRIVATE_BYTES):
    path = pathlib.Path(path)
    need(path.is_absolute() and path.resolve() == path, "unsafe_file_path")
    before = path.lstat()
    need(stat.S_ISREG(before.st_mode) and 0 < before.st_size <= maximum, "unsafe_file_type_or_size")
    fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(fd, "rb") as stream:
        opened = os.fstat(stream.fileno())
        need((opened.st_dev, opened.st_ino, opened.st_size) == (before.st_dev, before.st_ino, before.st_size), "file_changed")
        raw = stream.read(maximum + 1)
        after = os.fstat(stream.fileno())
    need(len(raw) == before.st_size and (after.st_size, after.st_mtime_ns) == (before.st_size, before.st_mtime_ns), "file_changed")
    return raw


def save(path, value):
    raw = enc(value)
    need(len(raw) <= MAX_PRIVATE_BYTES, "private_capture_failed")
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, "wb") as stream:
        need(stream.write(raw) == len(raw), "private_short_write")
        stream.flush(); os.fsync(stream.fileno())
    fd = os.open(path.parent, os.O_RDONLY | os.O_DIRECTORY)
    try: os.fsync(fd)
    finally: os.close(fd)
    need(file_bytes(path) == raw, "private_write_readback")
    return hashlib.sha256(raw).hexdigest()


def manifest(path):
    raw = file_bytes(path, 16384)
    need(hashlib.sha256(raw).hexdigest() == MANIFEST_SHA, "fixture_digest")
    data = parsed(raw)
    need(data["schema"] == "match-passive-oct4-frontier-fixture/1" and data["operation"] == OP and data["batch"] == BATCH and data["mode"] == "match-passive-oct4-frontier" and data["table_name"] == "andromeda_search_hotel_observations" and data["source_provenance"] == "passive_preview_search_observation" and typed_equal(data["window_utc"], {"lower_inclusive": LOWER, "upper_exclusive": UPPER}) and type(data["row_cap"]) is int and data["row_cap"] == CAP, "fixture_scope")
    return data, MANIFEST_SHA


def blob(raw):
    return hashlib.sha1(b"blob " + str(len(raw)).encode() + b"\0" + raw).hexdigest()


PHP_CAPTURE = r'''
declare(strict_types=1);
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
$out=['schema'=>'match-passive-oct4-db-projection/1','rows'=>null,'database_reads'=>0,'database_read_attempts'=>0,'read_transaction_rolled_back'=>false,'failure_stage'=>null];$db=null;$stage='db_entrypoint';
try{
 $root=(string)getenv('ANYTOUR_ROOT');$f=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
 $stage='db_entrypoint_changed';
 if(!is_file($f)||is_link($f)||realpath($f)!==$f||hash_file('sha256',$f)!=='c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13')throw new RuntimeException();
 $cfg=dirname(dirname($f)).'/config.php';$stage='db_config_path';
 if((file_exists($cfg)||is_link($cfg))&&(!is_file($cfg)||is_link($cfg)||realpath($cfg)!==$cfg))throw new RuntimeException();
 $stage='db_entrypoint';
 require_once $f;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);$stage='db_driver';
 if($driver==='mysql'){$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');}
 elseif($driver==='sqlite'){$db->exec('PRAGMA query_only=ON');$db->beginTransaction();}
 else throw new RuntimeException();
 $stage='db_capture_failed';$out['database_read_attempts']=1;
 $q=$db->prepare('SELECT observation_sha256,search_evidence_sha256,supplier_namespace,external_hotel_id,hotel_name,operator_refs_json,operator_names_json,country_id,country_name,region_name,category,description_text,image_url,hotel_url,content_sha256,observed_at_utc FROM andromeda_search_hotel_observations WHERE observed_at_utc>=? AND observed_at_utc<? ORDER BY observed_at_utc,observation_sha256 LIMIT 5001');
 $q->execute(['2026-10-04 00:00:00','2026-10-04 17:59:42']);$out['rows']=$q->fetchAll(PDO::FETCH_ASSOC);$out['database_reads']=1;
 $db->rollBack();$out['read_transaction_rolled_back']=true;
 $stage='db_capture_resource_cap';$bytes=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 if(strlen($bytes)>67108864)throw new RuntimeException();
}catch(Throwable $e){
 if($db instanceof PDO&&$db->inTransaction()){try{$db->rollBack();$out['read_transaction_rolled_back']=true;}catch(Throwable $ignored){$out['read_transaction_rolled_back']=null;}}
 if($e instanceof PDOException&&($e->getCode()==='42S02'||($db instanceof PDO&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'&&str_contains($e->getMessage(),'no such table: andromeda_search_hotel_observations'))))$stage='db_table_missing';
 $out['rows']=null;$out['failure_stage']=$stage;$bytes=json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
while(ob_get_level())ob_end_clean();echo $bytes;
'''


def capture(project):
    runtime = project / "_preview/search3-anex-candidate"
    app = runtime / "app/integrations"
    if not (app / "andromeda-hotel-observations.php").exists(): app = runtime.parent / "app/integrations"
    producer_raw = file_bytes(app / "andromeda-hotel-observations.php", 2 * 1024 * 1024)
    need(blob(producer_raw) == PRODUCER_BLOB, "producer_source_changed")
    entry = project / "data/db-v1.php" if (project / "data/db-v1.php").exists() else project / "v2/data/db-v1.php"
    db_raw = file_bytes(entry, 2 * 1024 * 1024)
    need(blob(db_raw) == DB_ENTRYPOINT_BLOB, "db_entrypoint_changed")
    config = entry.parent.parent / "config.php"
    if config.exists() or config.is_symlink():
        need(config.resolve() == config and stat.S_ISREG(config.lstat().st_mode), "db_config_path")
    bound = {"producer_sha256": hashlib.sha256(producer_raw).hexdigest(), "db_entrypoint_sha256": hashlib.sha256(db_raw).hexdigest()}
    # The exact existing definitions-only PDO entry point is the sole include.
    # No supplier config/client, schema installer or pre-existing MATCH child.
    try:
        call = subprocess.run(["php", "-d", "allow_url_fopen=0", "-d", "display_errors=0", "-d", "log_errors=0", "-d", "memory_limit=256M", "-d", "disable_functions=" + PHP_DISABLED, "-r", PHP_CAPTURE], env={**os.environ, "ANYTOUR_ROOT": str(project)}, capture_output=True, timeout=90)
        raw = call.stdout
        if call.returncode or call.stderr.strip() or not 0 < len(raw) <= MAX_DB_BYTES:
            raise ValueError("db_subprocess_unclassified")
        data = parsed(raw)
        keys = {"schema", "rows", "database_reads", "database_read_attempts", "read_transaction_rolled_back", "failure_stage"}
        need(isinstance(data, dict) and set(data) == keys and data["schema"] == "match-passive-oct4-db-projection/1" and type(data["database_reads"]) is int and data["database_reads"] in (0, 1) and type(data["database_read_attempts"]) is int and data["database_read_attempts"] in (0, 1) and data["database_reads"] <= data["database_read_attempts"] and (type(data["read_transaction_rolled_back"]) is bool or data["read_transaction_rolled_back"] is None) and data["failure_stage"] in (None, "db_entrypoint_changed", "db_config_path", "db_entrypoint", "db_driver", "db_table_missing", "db_capture_failed", "db_capture_resource_cap"), "db_projection_invalid")
        if data["failure_stage"] is None:
            need(isinstance(data["rows"], list) and len(data["rows"]) <= CAP + 1 and data["database_reads"] == data["database_read_attempts"] == 1 and data["read_transaction_rolled_back"] is True, "db_projection_invalid")
        else: need(data["rows"] is None, "db_projection_invalid")
        return bound | {"db_projection": data, "db_projection_utf8": raw.decode("utf-8"), "php_invocations": 1}
    except Exception:
        return bound | {"db_projection": {"schema": "match-passive-oct4-db-projection/1", "rows": None, "database_reads": None, "database_read_attempts": None, "read_transaction_rolled_back": None, "failure_stage": "db_subprocess_unclassified"}, "db_projection_utf8": None, "php_invocations": 1}


def integer(value, maximum=2147483647):
    if type(value) is int and 1 <= value <= maximum: return value
    if isinstance(value, str) and re.fullmatch(r"[1-9][0-9]{0,9}", value) and int(value) <= maximum: return int(value)
    return None


def safe_text(value, maximum):
    if not isinstance(value, str) or len(value) > maximum or re.search(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]|https?://|(?:password|passwd|secret|api[_-]?key|authorization|auth(?:entication)?|cookie|session|token|sid|signature|credential|csrf|offer[_-]?(?:id|ref)|search[_-]?ref|price[_-]?ref|request[_-]?id)\s*[:=]|offer_[A-Za-z0-9_-]+|bearer\s+|(?:^|\s)(?:/[A-Za-z0-9._-]+){2,}", value, re.I): return None
    return value  # Benign tab/newline and empty optional values are preserved.


def public_url(value):
    if value is None: return None
    try:
        need(isinstance(value, str) and 0 < len(value.encode()) <= 2048 and not re.search(r"[\x00-\x20\x7f]", value), "url")
        u = urlsplit(value); host = u.hostname
        need(u.scheme == "https" and host and u.username is None and u.password is None and u.port is None and not u.fragment, "url")
        need(re.fullmatch(r"[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}", host) and not re.search(r"\.(?:local|localhost|internal|lan|invalid|test|example)$", host), "url")
        def decoded(text):
            for _ in range(5):
                next_text = unquote(text)
                if next_text == text: return text
                text = next_text
            need(unquote(text) == text, "url")
            return text
        secret = r"password|passwd|secret|api[_-]?key|authorization|auth(?:entication)?|cookie|session|token|sid|signature|credential|csrf|bearer|offer[_-]?(?:id|ref)|search[_-]?ref|price[_-]?ref|request[_-]?id"
        need(not re.search(r"[\x00-\x20\x7f]|(?:" + secret + r")[=/]|offer_[A-Za-z0-9_-]+", decoded(u.path), re.I), "url")
        pairs = parse_qsl(u.query, keep_blank_values=True, strict_parsing=True, max_num_fields=40) if u.query else []
        need(all(re.fullmatch(r"[A-Za-z0-9_.-]{1,80}", k) and len(v.encode()) <= 1024 and not re.search(r"[\x00-\x20\x7f]|(?:" + secret + r")|offer_[A-Za-z0-9_-]+", decoded(k + "=" + v), re.I) for k, v in pairs), "url")
        return value  # All actual safe query selector tokens remain intact.
    except Exception: return None


def php_content_hash(values):
    # PHP UNESCAPED_UNICODE|UNESCAPED_SLASHES still escapes line separators.
    raw = json.dumps(values, ensure_ascii=False, separators=(",", ":"), allow_nan=False).replace("\u2028", "\\u2028").replace("\u2029", "\\u2029").encode()
    return hashlib.sha256(raw).hexdigest()


def project_row(raw, index):
    out = dict.fromkeys(ROW_KEYS)
    out.update(row_index=index, raw_row_sha256=hashlib.sha256(enc(raw)).hexdigest(), observation_sha256_verified=False, content_hash_verified=False, operator_refs=[], operator_names=[], state="row_hold", holds=[])
    holds = out["holds"]
    if not isinstance(raw, dict) or set(raw) != set(COLUMNS): holds.append("row_schema")
    if not isinstance(raw, dict): return out
    for key in ("observation_sha256", "content_sha256"):
        value = raw.get(key)
        out[key] = value if isinstance(value, str) and SHA.fullmatch(value) else None
        if out[key] is None: holds.append("observation_hash_invalid" if key == "observation_sha256" else "content_hash_invalid")
    ns, external = raw.get("supplier_namespace"), raw.get("external_hotel_id")
    if isinstance(ns, str) and (ns == "andromeda_catalog" or re.fullmatch(r"operator_[A-Za-z0-9_-]{1,128}", ns)): out["supplier_namespace"] = ns
    else: holds.append("namespace_unrecognized")
    if isinstance(external, str) and ID.fullmatch(external): out["external_hotel_id"] = external
    else: holds.append("external_id_invalid")
    original_arrays = {}
    for field, source in (("operator_refs", "operator_refs_json"), ("operator_names", "operator_names_json")):
        try:
            values = parsed(raw.get(source))
            need(type(values) is list and len(values) <= 2000, "array")
            original_arrays[field] = values
            if field == "operator_refs":
                valid = [v for v in values if isinstance(v, str) and ID.fullmatch(v)]
                if valid != sorted(set(valid)) or len(valid) != len(values) or not valid: holds.append("operator_refs_invalid")
            else:
                valid = [v for v in values if safe_text(v, 300) is not None]
                if valid != sorted(set(valid)) or len(valid) != len(values): holds.append("operator_names_invalid")
            out[field] = sorted(set(valid))
        except Exception: holds.append("operator_refs_invalid" if field == "operator_refs" else "operator_names_invalid")
    if out["supplier_namespace"] and out["supplier_namespace"].startswith("operator_") and out["operator_refs"] != [out["supplier_namespace"][9:]]: holds.append("operator_namespace_conflict")
    for field, maximum, reason in (("hotel_name", 300, "hotel_name_redacted"), ("country_name", 160, "country_name_redacted"), ("region_name", 180, "region_name_redacted")):
        out[field] = safe_text(raw.get(field), maximum)
        if out[field] is None: holds.append(reason)
    out["country_id"] = integer(raw.get("country_id"))
    if out["country_id"] is None: holds.append("country_id_invalid")
    category = raw.get("category")
    out["category"] = integer(category, 5) if category is not None else None
    if category is not None and out["category"] is None: holds.append("category_invalid")
    description = raw.get("description_text")
    if description is None: out["description_sha256"] = None
    elif isinstance(description, str): out["description_sha256"] = hashlib.sha256(description.encode()).hexdigest()
    else: holds.append("description_invalid")
    for field, reason in (("hotel_url", "hotel_url_redacted"), ("image_url", "image_url_redacted")):
        value = raw.get(field)
        out[field] = public_url(value)
        out["redacted_" + field + "_sha256"] = hashlib.sha256(value.encode()).hexdigest() if isinstance(value, str) and out[field] is None else None
        if value is not None and out[field] is None: holds.append(reason)
    timestamp = raw.get("observed_at_utc")
    try:
        need(isinstance(timestamp, str) and dt.datetime.strptime(timestamp, "%Y-%m-%d %H:%M:%S").strftime("%Y-%m-%d %H:%M:%S") == timestamp, "time")
        out["observed_at_utc"] = timestamp.replace(" ", "T") + "Z"
        if not LOWER <= timestamp < UPPER: holds.append("outside_fixed_window")
    except Exception: holds.append("timestamp_invalid")
    event = raw.get("search_evidence_sha256")
    if isinstance(event, str) and SHA.fullmatch(event) and isinstance(ns, str) and isinstance(external, str) and out["observation_sha256"]:
        out["observation_sha256_verified"] = hashlib.sha256((event + "\0" + ns + "\0" + external).encode()).hexdigest() == out["observation_sha256"]
        if not out["observation_sha256_verified"]: holds.append("observation_hash_mismatch")
    else: holds.append("observation_hash_invalid")
    try:
        need(out["country_id"] is not None and (category is None or out["category"] is not None) and len(original_arrays) == 2 and all(isinstance(raw.get(k), str) for k in ("hotel_name", "country_name", "region_name")) and (description is None or isinstance(description, str)) and all(raw.get(k) is None or isinstance(raw.get(k), str) for k in ("image_url", "hotel_url")), "content")
        values = [raw["hotel_name"], out["country_id"], raw["country_name"], raw["region_name"], out["category"], description, raw["image_url"], raw["hotel_url"], original_arrays["operator_refs"], original_arrays["operator_names"]]
        out["content_hash_verified"] = php_content_hash(values) == out["content_sha256"]
        if not out["content_hash_verified"]: holds.append("content_hash_mismatch")
    except Exception: holds.append("content_hash_unverifiable")
    out["holds"] = sorted(set(holds))
    out["state"] = "row_hold" if out["holds"] else "retained_normalized_identity_candidate"
    return out


def validate_row(row, index):
    need(isinstance(row, dict) and set(row) == ROW_KEYS and type(row["row_index"]) is int and row["row_index"] == index and isinstance(row["raw_row_sha256"], str) and SHA.fullmatch(row["raw_row_sha256"]), "public_row")
    need(isinstance(row["holds"], list) and row["holds"] == sorted(set(row["holds"])) and all(v in HOLD_REASONS for v in row["holds"]) and row["state"] == ("row_hold" if row["holds"] else "retained_normalized_identity_candidate"), "public_row_state")
    for key in ("observation_sha256_verified", "content_hash_verified"): need(type(row[key]) is bool, "public_row_hash_flags")
    for key in ("observation_sha256", "content_sha256", "redacted_hotel_url_sha256", "redacted_image_url_sha256", "description_sha256"):
        need(row[key] is None or isinstance(row[key], str) and SHA.fullmatch(row[key]), "public_row_hash")
    need(row["supplier_namespace"] is None or isinstance(row["supplier_namespace"], str) and (row["supplier_namespace"] == "andromeda_catalog" or re.fullmatch(r"operator_[A-Za-z0-9_-]{1,128}", row["supplier_namespace"])), "public_namespace")
    need(row["external_hotel_id"] is None or isinstance(row["external_hotel_id"], str) and ID.fullmatch(row["external_hotel_id"]), "public_external")
    for key, maximum in (("hotel_name", 300), ("country_name", 160), ("region_name", 180)):
        need(row[key] is None or safe_text(row[key], maximum) == row[key], "public_text")
    for key in ("operator_refs", "operator_names"):
        need(isinstance(row[key], list) and len(row[key]) <= 2000 and row[key] == sorted(set(row[key])) and all(isinstance(v, str) and (ID.fullmatch(v) if key == "operator_refs" else safe_text(v, 300) is not None) for v in row[key]), "public_operator_sets")
    for key, maximum in (("country_id", 2147483647), ("category", 5)):
        need(row[key] is None or type(row[key]) is int and integer(row[key], maximum) == row[key], "public_numeric")
    for key in ("hotel_url", "image_url"):
        need(row[key] is None or isinstance(row[key], str) and public_url(row[key]) == row[key], "public_url")
    if row["observed_at_utc"] is not None:
        need(isinstance(row["observed_at_utc"], str) and re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", row["observed_at_utc"]), "public_row_time")
        dt.datetime.strptime(row["observed_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    if row["state"] == "retained_normalized_identity_candidate":
        need(row["content_hash_verified"] is True and row["observation_sha256_verified"] is True and row["content_sha256"] is not None and row["observation_sha256"] is not None and row["supplier_namespace"] is not None and row["external_hotel_id"] is not None and bool(row["operator_refs"]) and row["country_id"] is not None and all(row[k] is not None for k in ("hotel_name", "country_name", "region_name")) and row["observed_at_utc"] is not None and LOWER.replace(" ", "T") + "Z" <= row["observed_at_utc"] < UPPER.replace(" ", "T") + "Z", "public_candidate")
        need(not row["supplier_namespace"].startswith("operator_") or row["operator_refs"] == [row["supplier_namespace"][9:]], "public_candidate_namespace")
        need(all(row["redacted_" + k + "_sha256"] is None for k in ("hotel_url", "image_url")), "public_candidate_redaction")


def validate_result(data, receipt=None, expected_source=None):
    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "failure_stage", "captured_at_utc", "private_input_sha256", "fixture_sha256", "inputs", "source_provenance", "table_name", "window_utc", "row_cap", "producer_sha256", "db_entrypoint_sha256", "rows_captured", "rows_examined", "rows_retained", "rows_held", "overflow", "rows", "no_replay", *NO_EFFECTS, *DB_FACTS, *FALSE_FLAGS}
    need(isinstance(data, dict) and set(data) == keys and len(enc(data)) <= MAX_PUBLIC_BYTES and data["schema"] == "match-passive-oct4-frontier-result/1" and data["operation"] == OP and data["batch"] == BATCH and data["state"] in STATES, "public_scope")
    fixture, digest = manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / FIXTURE_NAME)
    need(isinstance(data["source_sha"], str) and re.fullmatch(r"[a-f0-9]{40}", data["source_sha"]) and (expected_source is None or data["source_sha"] == expected_source) and isinstance(data["private_input_sha256"], str) and SHA.fullmatch(data["private_input_sha256"]) and data["fixture_sha256"] == digest and typed_equal(data["inputs"], {k: fixture[k] for k in ("independent_precursor", "excluded_consumed_page1")}), "public_lineage")
    need(typed_equal(data["window_utc"], fixture["window_utc"]) and type(data["row_cap"]) is int and data["row_cap"] == CAP and data["table_name"] == fixture["table_name"] and data["source_provenance"] == fixture["source_provenance"] and data["no_replay"] is True and all(type(data[k]) is int and data[k] == 0 for k in NO_EFFECTS) and all(data[k] is False for k in FALSE_FLAGS), "public_authority")
    need(isinstance(data["captured_at_utc"], str) and re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", data["captured_at_utc"]), "public_timestamp")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    for key in ("producer_sha256", "db_entrypoint_sha256"): need(data[key] is None or isinstance(data[key], str) and SHA.fullmatch(data[key]), "public_code_digest")
    need(type(data["php_invocations"]) is int and data["php_invocations"] in (0, 1) and all(data[k] is None or type(data[k]) is int and data[k] in (0, 1) for k in ("database_reads", "database_read_attempts")) and (data["read_transaction_rolled_back"] is None or type(data["read_transaction_rolled_back"]) is bool), "public_database_counters")
    need(all(type(data[k]) is int and 0 <= data[k] <= CAP + 1 for k in ("rows_captured", "rows_examined", "rows_retained", "rows_held")) and isinstance(data["rows"], list) and type(data["overflow"]) is bool and data["rows_examined"] == len(data["rows"]) == data["rows_retained"] + data["rows_held"], "public_counts")
    if data["state"] == "terminal_failed_no_replay":
        need(data["reason"] == "passive_oct4_capture_failed" and data["failure_stage"] in FAILURE_STAGES and not data["rows"] and data["overflow"] is False, "public_failed")
    else:
        need(data["reason"] is None and data["failure_stage"] is None and data["database_reads"] == data["database_read_attempts"] == data["php_invocations"] == 1 and data["read_transaction_rolled_back"] is True and data["producer_sha256"] is not None and data["db_entrypoint_sha256"] is not None, "public_complete")
        if data["state"] == "held_overflow_no_replay": need(data["rows_captured"] == CAP + 1 and data["overflow"] is True and not data["rows"], "public_overflow")
        else:
            need(data["rows_captured"] == data["rows_examined"] <= CAP and data["overflow"] is False and data["rows_held"] == sum(r["state"] == "row_hold" for r in data["rows"]) and data["state"] == ("completed_with_holds" if data["rows_held"] else "completed_read_only"), "public_partition")
            for index, row in enumerate(data["rows"]): validate_row(row, index)
    if data["php_invocations"] == 0:
        need(data["state"] == "terminal_failed_no_replay" and data["database_reads"] == data["database_read_attempts"] == 0 and data["read_transaction_rolled_back"] is False and data["rows_captured"] == 0 and data["failure_stage"] in ("producer_source_changed", "db_entrypoint_changed", "db_config_path", "capture_unclassified"), "public_before_db_failure")
    else:
        need(data["producer_sha256"] is not None and data["db_entrypoint_sha256"] is not None, "public_database_binding")
        if data["failure_stage"] == "db_subprocess_unclassified":
            need(data["database_reads"] is None and data["database_read_attempts"] is None and data["read_transaction_rolled_back"] is None and data["rows_captured"] == 0, "public_database_unknown")
        else:
            need(type(data["database_reads"]) is int and type(data["database_read_attempts"]) is int and data["database_reads"] <= data["database_read_attempts"], "public_database_known")
            if data["failure_stage"] in ("db_entrypoint_changed", "db_config_path", "db_entrypoint", "db_driver"):
                need(data["database_reads"] == data["database_read_attempts"] == 0 and data["rows_captured"] == 0, "public_database_before_query")
            if data["failure_stage"] == "db_table_missing":
                need(data["database_reads"] == 0 and data["database_read_attempts"] == 1 and data["read_transaction_rolled_back"] in (True, None) and data["rows_captured"] == 0, "public_database_missing_table")
            if data["failure_stage"] in ("db_capture_resource_cap", "public_resource_cap"):
                need(data["database_reads"] == data["database_read_attempts"] == 1 and data["read_transaction_rolled_back"] is True, "public_database_capture_complete")
    if receipt is not None: need(typed_equal(receipt, {k: data[k] for k in RECEIPT_KEYS} | {"result_sha256": hashlib.sha256(enc(data)).hexdigest()}), "public_receipt")
    return True


def execute(project, opdir, manifest_path, source_root, result_path, head):
    project, opdir, source_root = map(pathlib.Path, (project, opdir, source_root))
    own_root = pathlib.Path(__file__).resolve().parents[2]
    expected_fixture = own_root / "scripts/diagnostics/fixtures" / FIXTURE_NAME
    need(project.is_absolute() and project.resolve() == project and project.is_dir() and project.name == "anytoour.ru" and project.parent.name == "www" and source_root == own_root and pathlib.Path(manifest_path) == expected_fixture and isinstance(head, str) and re.fullmatch(r"[a-f0-9]{40}", head), "runtime_scope")
    need(opdir.resolve() == opdir and opdir.is_dir() and not opdir.is_symlink() and opdir == project.parent.parent / ".anytoour-match/operations" / OP and pathlib.Path(result_path) == opdir / "result.json", "private_operation_scope")
    fixture, fixture_sha = manifest(expected_fixture)
    reservation = {"operation": OP, "batch": BATCH, "source_sha": head, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_database_read"}
    for path in (opdir / "reservation.json", opdir.parent.parent / (BATCH + "-batch.json")):
        need(typed_equal(parsed(file_bytes(path, 16384)), reservation), "reservation_scope")
    need(not any((opdir / name).exists() or (opdir / name).is_symlink() for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json")), "terminal_no_replay")
    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "no_replay": True})
    result = {"schema": "match-passive-oct4-frontier-result/1", "operation": OP, "batch": BATCH, "source_sha": head, "state": "terminal_failed_no_replay", "reason": "passive_oct4_capture_failed", "failure_stage": "capture_unclassified", "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"), "private_input_sha256": None, "fixture_sha256": fixture_sha, "inputs": {k: fixture[k] for k in ("independent_precursor", "excluded_consumed_page1")}, "source_provenance": fixture["source_provenance"], "table_name": fixture["table_name"], "window_utc": fixture["window_utc"], "row_cap": CAP, "producer_sha256": None, "db_entrypoint_sha256": None, "rows_captured": 0, "rows_examined": 0, "rows_retained": 0, "rows_held": 0, "overflow": False, "rows": [], "no_replay": True, "database_reads": 0, "database_read_attempts": 0, "read_transaction_rolled_back": False, "php_invocations": 0, **dict.fromkeys(NO_EFFECTS, 0), **dict.fromkeys(FALSE_FLAGS, False)}
    captured = None
    try:
        captured = capture(project)
        projection = captured["db_projection"]
        result.update({k: captured[k] for k in ("producer_sha256", "db_entrypoint_sha256", "php_invocations")})
        result.update({k: projection[k] for k in ("database_reads", "database_read_attempts", "read_transaction_rolled_back")})
        private = {"schema": "match-passive-oct4-frontier-private-input/1", "operation": OP, "batch": BATCH, "source_sha": head, "fixture_sha256": fixture_sha, **captured}
        # Every fetched field/row, including overflow sentinel and unsafe text,
        # is durable before any optional row projection can reject or redact it.
        result["private_input_sha256"] = save(opdir / "current-input.json", private)
        if projection["failure_stage"] is not None:
            result["failure_stage"] = projection["failure_stage"]
        else:
            raw_rows = projection["rows"]
            result["rows_captured"] = len(raw_rows)
            result.update(state="held_overflow_no_replay" if len(raw_rows) > CAP else "completed_read_only", reason=None, failure_stage=None, overflow=len(raw_rows) > CAP)
            if not result["overflow"]:
                seen, prior = set(), None
                for index, raw in enumerate(raw_rows):
                    row = project_row(raw, index)
                    obs = row["observation_sha256"]
                    if obs is not None and obs in seen: row["holds"].append("duplicate_observation")
                    if obs is not None: seen.add(obs)
                    order = (raw.get("observed_at_utc"), raw.get("observation_sha256")) if isinstance(raw, dict) else None
                    if order is not None and all(isinstance(v, str) for v in order):
                        if prior is not None and order < prior: row["holds"].append("query_order_invalid")
                        prior = order
                    row["holds"] = sorted(set(row["holds"]))
                    row["state"] = "row_hold" if row["holds"] else "retained_normalized_identity_candidate"
                    result["rows"].append(row)
                result["rows_examined"] = len(result["rows"])
                result["rows_held"] = sum(r["state"] == "row_hold" for r in result["rows"])
                result["rows_retained"] = result["rows_examined"] - result["rows_held"]
                result["state"] = "completed_with_holds" if result["rows_held"] else "completed_read_only"
                need(len(enc(result)) <= MAX_PUBLIC_BYTES, "public_resource_cap")
    except Exception as error:
        stage = str(error) if type(error) is ValueError and str(error) in FAILURE_STAGES else "capture_unclassified"
        result.update(state="terminal_failed_no_replay", reason="passive_oct4_capture_failed", failure_stage=stage, rows=[], rows_examined=0, rows_retained=0, rows_held=0, overflow=False)
        if result["private_input_sha256"] is None:
            result["private_input_sha256"] = save(opdir / "current-input.json", {"schema": "match-passive-oct4-frontier-private-input/1", "operation": OP, "batch": BATCH, "source_sha": head, "fixture_sha256": fixture_sha, "state": "capture_failed", "failure_stage": stage, **{k: result[k] for k in DB_FACTS}})
    validate_result(result)
    result_sha = save(opdir / "result.json", result)
    receipt = {k: result[k] for k in RECEIPT_KEYS} | {"result_sha256": result_sha}
    validate_result(result, receipt, head)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: result[k] for k in ("state", "rows_examined", "accepted", "written")}, sort_keys=True))
    return 0 if result["state"] in STATES[:2] else 2


if __name__ == "__main__":
    if sys.argv[1:] == ["--self-test"]:
        manifest(pathlib.Path(__file__).resolve().with_name("fixtures") / FIXTURE_NAME)
        print("self_test_passed_passive_oct4_frontier")
    elif sys.argv[1:] == ["--execute"]:
        try:
            raise SystemExit(execute(os.environ.get("ANYTOUR_ROOT", ""), os.environ.get("MATCH_PRIVATE_DIRECTORY", ""), os.environ.get("MATCH_CURRENT_MANIFEST_PATH", ""), os.environ.get("MATCH_SOURCE_ROOT", ""), os.environ.get("MATCH_RESULT_PATH", ""), os.environ.get("MATCH_SOURCE_SHA", "")))
        except Exception:
            raise SystemExit("passive_oct4_frontier_contract_refused") from None
    else: raise SystemExit("use --self-test or exact reviewed --execute")
