#!/usr/bin/env python3
"""Capture new passive common4 observations; DB projections are never raw supplier proof."""
import collections
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import subprocess
import sys
import urllib.parse

OP = "int-andromeda-match-user-search-delta-20261004-v1"
BATCH = "user-search-delta-20261004"
MANIFEST_SHA = "a58f13ec8513a612e5ae93302c81491ac132079b0f654ae72344385e6edf8057"
LOWER = "2026-10-02 12:46:00"
UPPER = "2026-10-03 09:23:17"
CAP = 5000
CONTEXT_CAP = 10000
OPERATORS = (13, 18, 25, 43)
PREVIOUS = {"operation": "int-andromeda-match-live30-target-catalog-v2-20261001-v1", "captured_at_utc": "2026-10-01T12:46:00Z", "result_sha256": "724af5102c39d7ea25513f3d7698586fb13a3309d010237145552421c159351e"}
NO_EFFECTS = ("provider_http_calls", "physical_http_attempts", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written")
FALSE_FLAGS = ("safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated")
RECEIPT_KEYS = ("operation", "batch", "source_sha", "state", "private_input_sha256", "provider_http_calls", "physical_http_attempts", "database_reads", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written", "safe_to_write_now", "acceptance_evaluated", "global_uniqueness_evaluated", "no_replay")
PHP_DISABLED = "curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail,exec,system,shell_exec,passthru,proc_open,popen,pcntl_exec"
SECRET = re.compile(r"token|jwt|auth|pass|secret|session|sid|cookie|signature|api[_-]?key", re.I)
SELECTOR_KEYS = {"hotellist", "hotelcode", "hotel", "hotels", "hotelinc", "hotelid", "hotel_id", "f4"}
RULES = {
    13: ("anextour.ru", {"hotellist", "hotelcode"}, "operator_5"),
    18: ("bgoperator.ru", {"f4"}, "bg_full_f4_unproven"),
    25: ("fstravel.com", {"hotel", "hotels", "hotelinc"}, "operator_315"),
    43: ("intourist.ru", {"hotelid", "hotelcode", "hotels", "hotelinc"}, "operator_342"),
}

# The only DB entry point. No provider clients, schema installation or write statement.
PHP_CAPTURE = r'''
declare(strict_types=1);
$root=(string)getenv('ANYTOUR_ROOT');
$f=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
if(!is_file($f)||is_link($f))throw new RuntimeException('db_entrypoint');
require_once $f;
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('START TRANSACTION READ ONLY');
try{
 $q=function(string $sql,array $p=[])use($db):array{$s=$db->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);};
 $clock=$q('SELECT UTC_TIMESTAMP() db_utc,NOW() db_local,@@session.time_zone session_timezone,@@global.time_zone global_timezone')[0];
 $clock['php_timezone']=date_default_timezone_get();$clock['php_offset_seconds']=(new DateTimeImmutable('now'))->getOffset();
 $rows=$q("SELECT id,fingerprint,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,operator_link,operator_link_host,operator_link_path,operator_link_query,native_id_type,native_id_value,native_id_conflict FROM tour_operator_identity_observations WHERE source='user_search' AND operator_id IN(13,18,25,43) AND first_seen_at>? AND first_seen_at<=? ORDER BY id LIMIT 5001",['2026-10-02 12:46:00','2026-10-03 09:23:17']);
 $overflow=count($rows)>5000;$rows=array_slice($rows,0,5000);
 $wanted=array_values(array_unique(array_map(fn($r)=>(int)$r['hotel_id'],$rows)));
 $hotels=[];$owners=[];$manual=[];$exclusions=[];$anex=[];
 foreach(array_chunk($wanted,250) as $ids){$ph=implode(',',array_fill(0,count($ids),'?'));
  foreach($q("SELECT id,name,country_id,country_name,region_name,subregion_name,is_active,latitude,longitude FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids)as$r)$hotels[(string)$r['id']]=$r;
  foreach($q("SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE local_hotel_id IN ($ph) ORDER BY supplier_namespace,external_hotel_id",$ids)as$r)$owners[(string)$r['local_hotel_id']][]=$r;
  foreach($q("SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids)as$r)$manual[(string)$r['catalog_hotel_id']][]=$r;
  foreach($q("SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids)as$r)$exclusions[(string)$r['catalog_hotel_id']][]=$r;
  foreach($q("SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE catalog_hotel_id IN ($ph) ORDER BY anex_hotel_id",$ids)as$r)$anex[(string)$r['catalog_hotel_id']][]=$r;
 }
 $contextExists=(bool)$q("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_price_observations'");
 $contexts=[];$contextOverflow=[];
 if($contextExists){foreach(array_chunk($rows,250)as$chunk){$terms=[];$params=['2026-10-02 12:46:00','2026-10-03 09:23:17'];$index=[];
  foreach($chunk as$r){if(!$r['search_id']||!$r['tour_id'])continue;
   $key=json_encode([(string)$r['hotel_id'],(string)$r['operator_id'],(string)$r['search_id'],(string)$r['tour_id']],JSON_THROW_ON_ERROR);
   $index[$key][]=(string)$r['id'];$terms[]='(hotel_id=? AND operator_id=? AND search_id=? AND tour_id=?)';array_push($params,$r['hotel_id'],$r['operator_id'],$r['search_id'],$r['tour_id']);
  }
  if(!$terms)continue;
  $matches=$q("SELECT fingerprint,observed_at,source,search_id,hotel_id,operator_id,tour_id,departure_id,country_id,region_id,subregion_id,departure_date,nights,adults,children_count,child_ages_signature FROM tour_price_observations WHERE source='user_search' AND observed_at>? AND observed_at<=? AND (".implode(' OR ',$terms).") ORDER BY observed_at DESC,fingerprint LIMIT 10001",$params);
  if(count($matches)>10000){foreach($chunk as$r)$contextOverflow[(string)$r['id']]=true;continue;}
  foreach($matches as$c){$key=json_encode([(string)$c['hotel_id'],(string)$c['operator_id'],(string)$c['search_id'],(string)$c['tour_id']],JSON_THROW_ON_ERROR);foreach($index[$key]??[]as$id){if(count($contexts[$id]??[])<2)$contexts[$id][]=$c;}}
 }}
 $db->rollBack();
 echo json_encode(['schema'=>'match-user-search-delta-db-projection/1','clock'=>$clock,'rows'=>$rows,'overflow'=>$overflow,'hotels'=>(object)$hotels,'owners'=>(object)$owners,'manual'=>(object)$manual,'exclusions'=>(object)$exclusions,'anex'=>(object)$anex,'price_context_table_present'=>$contextExists,'contexts'=>(object)$contexts,'context_overflow'=>(object)$contextOverflow],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw new RuntimeException('delta_read_failed');}
'''


def enc(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode()


def save(path, value):
    path = pathlib.Path(path)
    if not path.parent.is_dir() or path.parent.is_symlink():
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


def read_json(path):
    path = pathlib.Path(path)
    if not path.is_file() or path.is_symlink():
        raise RuntimeError("input_path")
    out = json.loads(path.read_bytes())
    if not isinstance(out, dict):
        raise RuntimeError("input_shape")
    return out


def manifest(path):
    path = pathlib.Path(path)
    value = read_json(path)
    if hashlib.sha256(path.read_bytes()).hexdigest() != MANIFEST_SHA:
        raise RuntimeError("manifest_hash")
    if (value.get("operation"), value.get("batch"), value.get("mode"), value.get("source"), value.get("operator_ids"), value.get("row_cap")) != (OP, BATCH, "match-user-search-delta-readonly", "user_search", list(OPERATORS), CAP):
        raise RuntimeError("manifest_scope")
    if value.get("window_civil") != {"lower_exclusive": LOWER, "upper_inclusive": UPPER}:
        raise RuntimeError("manifest_window")
    if value.get("schema") != "match-user-search-delta-readonly/1" or value.get("previous_capture") != PREVIOUS or value.get("fixed_upper_utc") != "2026-10-04T09:23:17Z":
        raise RuntimeError("manifest_baseline")
    if any(value.get(k) != 0 for k in ("provider_http_calls", "maximum_writes", "database_writes", "mapping_writes")) or value.get("safe_to_write_now") is not False or value.get("acceptance_evaluated") is not False:
        raise RuntimeError("manifest_authority")
    return value


def pos(value):
    text = str(value)
    return int(text) if re.fullmatch(r"[1-9][0-9]{0,19}", text) else None


def civil(value):
    if not isinstance(value, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}", value):
        raise RuntimeError("invalid_civil_timestamp")
    parsed = dt.datetime.strptime(value, "%Y-%m-%d %H:%M:%S")
    if parsed.strftime("%Y-%m-%d %H:%M:%S") != value:
        raise RuntimeError("invalid_civil_timestamp")
    return parsed


def native_projection(operator_id, url):
    out = {"link_state": "missing_operator_link", "operator_link_sha256": None,
           "operator_link_host": None, "operator_link_path": None, "raw_identity_values": [],
           "raw_identity_tokens": [], "positive_native_candidates": [], "namespace_candidate": None,
           "namespace_bridge_verified": False}
    if not isinstance(url, str) or not url:
        return out
    out["operator_link_sha256"] = hashlib.sha256(url.encode()).hexdigest()
    if len(url) > 2048 or re.search(r"[\x00-\x20\x7f]", url):
        out["link_state"] = "invalid_origin"
        return out
    try:
        parsed = urllib.parse.urlsplit(url)
        if parsed.scheme.lower() != "https" or not parsed.hostname or parsed.username or parsed.password or parsed.fragment or parsed.port not in (None, 443):
            raise ValueError()
        pairs = urllib.parse.parse_qsl(parsed.query, keep_blank_values=True, max_num_fields=200)
    except ValueError:
        out["link_state"] = "invalid_origin"
        return out
    if any(SECRET.search(key) for key, _ in pairs):
        out["link_state"] = "secret_query_redacted"
        return out
    rule = RULES.get(operator_id)
    if rule is None or not (parsed.hostname.lower() == rule[0] or parsed.hostname.lower().endswith("." + rule[0])):
        out["link_state"] = "unexpected_operator_host"
        return out
    # A path is reported only if it cannot carry private path credentials/tokens.
    if SECRET.search(urllib.parse.unquote(parsed.path)) or not re.fullmatch(r"/[A-Za-z0-9/._%~-]{0,511}|", parsed.path) or any(len(s) > 64 for s in parsed.path.split("/")):
        out["link_state"] = "secret_path_redacted"
        return out
    out.update(operator_link_host=parsed.hostname.lower(), operator_link_path=parsed.path,
               namespace_candidate=rule[2])
    values = [value for key, value in pairs if key.lower() in SELECTOR_KEYS]
    known = [key.lower() in rule[1] for key, _ in pairs if key.lower() in SELECTOR_KEYS]
    tokens = [token for value in values for token in value.split(",")]
    # Unrecognized/signature-like data never enters a public identity-token field.
    if any(not re.fullmatch(r"[+-]?[0-9]{1,20}", token) for token in tokens):
        out["link_state"] = "unknown_selector_redacted"
        return out
    out.update(raw_identity_values=values, raw_identity_tokens=tokens,
               positive_native_candidates=[token for token in tokens if re.fullmatch(r"[1-9][0-9]{0,19}", token)])
    out["link_state"] = "captured_selector_candidate" if len(tokens) == 1 and known == [True] and len(out["positive_native_candidates"]) == 1 else "captured_ambiguous_or_unknown_selector"
    if operator_id == 18 and tokens:
        out["link_state"] = "captured_bg_full_code_bridge_unproven"
    return out


def context_projection(row, matches):
    out = {"state": "price_context_missing", "context": None}
    if not isinstance(matches, list) or not matches:
        return out
    if len(matches) != 1:
        out["state"] = "price_context_ambiguous"
        return out
    c = matches[0]
    exact = all(str(c.get(k)) == str(row.get(k)) for k in ("hotel_id", "operator_id", "tour_id", "search_id", "country_id"))
    if c.get("source") != "user_search" or not exact:
        out["state"] = "price_context_identity_mismatch"
        return out
    try:
        if not civil(LOWER) < civil(c.get("observed_at")) <= civil(UPPER):
            raise ValueError()
        if not pos(c.get("departure_id")) or not pos(c.get("nights")) or not pos(c.get("adults")):
            raise ValueError()
        date = dt.date.fromisoformat(c.get("departure_date"))
        if str(date) != c.get("departure_date") or not 1 <= int(c["nights"]) <= 28 or not 1 <= int(c["adults"]) <= 6:
            raise ValueError()
        ages = c.get("child_ages_signature") or ""
        count = int(c.get("children_count"))
        if not 0 <= count <= 3 or (ages != "" and not re.fullmatch(r"[0-9]{1,2}(?:,[0-9]{1,2}){0,2}", ages)):
            raise ValueError()
        parsed = [] if not ages else [int(x) for x in ages.split(",")]
        if len(parsed) != count or any(x > 17 for x in parsed):
            raise ValueError()
    except (ValueError, TypeError, RuntimeError):
        out["state"] = "price_context_invalid_or_outside_window"
        return out
    out["state"] = "exact_saved_price_context_candidate"
    out["context"] = {k: c.get(k) for k in ("observed_at", "country_id", "departure_id", "departure_date", "nights", "adults", "children_count", "child_ages_signature")}
    return out


def public_projection(value):
    """Keep typed DB fields while withholding oversized text and URLs per row."""
    if isinstance(value, str):
        return None if len(value) > 512 or re.search(r"https?://|[\x00-\x1f\x7f]", value, re.I) else value
    if value is None or type(value) in (int, float, bool):
        return value
    if isinstance(value, dict):
        return {k: public_projection(v) for k, v in value.items()}
    if isinstance(value, list):
        return [public_projection(v) for v in value]
    return None


def review_capture(capture, private_sha):
    raw_rows = capture.get("rows")
    if capture.get("schema") != "match-user-search-delta-db-projection/1" or not isinstance(raw_rows, list) or len(raw_rows) > CAP or not isinstance(capture.get("overflow"), bool):
        raise RuntimeError("capture_shape")
    if any(not isinstance(capture.get(k), dict) for k in ("hotels", "owners", "manual", "exclusions", "anex", "contexts", "context_overflow")):
        raise RuntimeError("capture_maps")
    offset = capture.get("clock", {}).get("php_offset_seconds")
    if type(offset) is not int or abs(offset) > 14 * 3600:
        raise RuntimeError("capture_clock_offset")
    ids = set()
    fingerprints = set()
    rows = []
    for raw in raw_rows:
        obs_id, target, op = pos(raw.get("id")), pos(raw.get("hotel_id")), pos(raw.get("operator_id"))
        if not obs_id or not target or op not in OPERATORS or obs_id in ids or raw.get("source") != "user_search":
            raise RuntimeError("delta_membership")
        ids.add(obs_id)
        if raw.get("fingerprint") != hashlib.sha256(f"tourvisor|{target}|{op}".encode()).hexdigest() or raw.get("fingerprint") in fingerprints:
            raise RuntimeError("observation_fingerprint")
        fingerprints.add(raw["fingerprint"])
        first, last = civil(raw.get("first_seen_at")), civil(raw.get("last_seen_at"))
        if not civil(LOWER) < first <= civil(UPPER) or first > last:
            raise RuntimeError("delta_timestamp_scope")
        holds = ["historical_datetime_timezone_unverified", "raw_supplier_response_not_exported", "global_identity_uniqueness_not_evaluated", "independent_samo_identity_proof_required"]
        if last > civil(UPPER):
            holds.append("latest_enrichment_after_window")
        if int(raw.get("native_id_conflict") or 0):
            holds.append("native_id_conflict")
        link = native_projection(op, raw.get("operator_link"))
        if link["link_state"] != "captured_selector_candidate":
            holds.append(link["link_state"])
        context = context_projection(raw, capture.get("contexts", {}).get(str(obs_id), []))
        if capture["context_overflow"].get(str(obs_id)) is True:
            context = {"state": "price_context_capture_incomplete", "context": None}
        if context["state"] != "exact_saved_price_context_candidate":
            holds.append(context["state"])
        h = capture.get("hotels", {}).get(str(target))
        if h is None:
            holds.append("target_catalog_missing")
        elif int(h.get("is_active") or 0) != 1:
            holds.append("target_inactive")
        elif str(h.get("country_id")) != str(raw.get("country_id")):
            holds.append("target_country_conflict")
        if h and str(h.get("country_name", "")).strip().lower() in ("россия", "абхазия", "russia", "russian federation", "abkhazia"):
            holds.append("excluded_country")
        owners = capture.get("owners", {}).get(str(target), [])
        if owners:
            holds.append("target_existing_registry_ownership_review")
        if any(str(x.get("external_hotel_id")) == "2000086118" and x.get("supplier_namespace") == "andromeda_catalog" for x in owners):
            holds.append("protected_source_2000086118")
        manual = capture.get("manual", {}).get(str(target), [])
        exclusions = capture.get("exclusions", {}).get(str(target), [])
        if manual:
            holds.append("target_manual_decision_protected")
        if exclusions:
            holds.append("target_pair_exclusion_protected")
        public_facts = public_projection({"target_catalog": h, "current_target_owners": owners, "current_manual_decisions": manual, "current_pair_exclusions": exclusions, "current_anex_mappings": capture.get("anex", {}).get(str(target), [])})
        if public_facts != {"target_catalog": h, "current_target_owners": owners, "current_manual_decisions": manual, "current_pair_exclusions": exclusions, "current_anex_mappings": capture.get("anex", {}).get(str(target), [])}:
            holds.append("current_projection_text_redacted")
        rows.append({"observation_id": obs_id, "target_tv_hotel_id": target, "target_id_namespace": "tourvisor", "independent_anytour_local_id": None,
                     "operator_id": op, "first_seen_civil": raw["first_seen_at"], "last_seen_civil": raw["last_seen_at"], "source": "user_search",
                     "country_id": pos(raw.get("country_id")), **public_facts,
                     "native_id_conflict": bool(int(raw.get("native_id_conflict") or 0)), "operator_link": link, "price_context": context,
                     "private_input_pointer": {"sha256": private_sha, "json_pointer": f"/rows/{len(rows)}"},
                     "holds": holds, "safe_to_write_now": False, "acceptance_evaluated": False, "global_uniqueness_evaluated": False})
    return rows


def validate_result(data, receipt=None, expected_source=None):
    """Validate the public contract without DB access, raw URLs or provider calls."""
    keys = {"schema", "operation", "batch", "source_sha", "state", "reason", "captured_at_utc", "private_input_sha256", "previous_capture", "window_civil", "timestamp_basis", "conservative_interior_window", "observations_selected", "distinct_target_tv_ids", "overflow", "operator_ids", "rows", "hold_counts", "database_reads", "no_replay", *NO_EFFECTS, *FALSE_FLAGS}
    if not isinstance(data, dict) or set(data) != keys:
        raise RuntimeError("public_result_shape")
    states = ("completed_read_only_delta", "completed_read_only_delta_incomplete", "terminal_failed_no_replay")
    if data["schema"] != "match-user-search-delta-readonly-result/1" or data["operation"] != OP or data["batch"] != BATCH or data["state"] not in states:
        raise RuntimeError("public_result_scope")
    if not re.fullmatch(r"[0-9a-f]{40}", data["source_sha"] or "") or (expected_source is not None and data["source_sha"] != expected_source):
        raise RuntimeError("public_result_source")
    if not re.fullmatch(r"[0-9a-f]{64}", data["private_input_sha256"] or "") or data["previous_capture"] != PREVIOUS or data["window_civil"] != {"lower_exclusive": LOWER, "upper_inclusive": UPPER}:
        raise RuntimeError("public_result_input")
    if data["timestamp_basis"] != "historical_php_civil_timezone_unverified" or data["conservative_interior_window"] is not True or data["operator_ids"] != list(OPERATORS) or data["no_replay"] is not True:
        raise RuntimeError("public_result_authority")
    if any(type(data[k]) is not int or data[k] != 0 for k in NO_EFFECTS) or any(data[k] is not False for k in FALSE_FLAGS) or data["database_reads"] != 1 or type(data["database_reads"]) is not int:
        raise RuntimeError("public_result_effects")
    if not isinstance(data["captured_at_utc"], str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", data["captured_at_utc"]):
        raise RuntimeError("public_result_timestamp")
    dt.datetime.strptime(data["captured_at_utc"], "%Y-%m-%dT%H:%M:%SZ")
    rows = data["rows"]
    if not isinstance(rows, list) or len(rows) > CAP or type(data["observations_selected"]) is not int or data["observations_selected"] != len(rows) or type(data["distinct_target_tv_ids"]) is not int or not isinstance(data["overflow"], bool):
        raise RuntimeError("public_result_count")
    failed = data["state"] == "terminal_failed_no_replay"
    if (failed and (rows or data["reason"] != "delta_capture_or_validation_failed")) or (not failed and data["reason"] is not None) or (not failed and (data["state"] == "completed_read_only_delta_incomplete") != data["overflow"]):
        raise RuntimeError("public_result_state")
    row_keys = {"observation_id", "target_tv_hotel_id", "target_id_namespace", "independent_anytour_local_id", "operator_id", "first_seen_civil", "last_seen_civil", "source", "country_id", "target_catalog", "native_id_conflict", "operator_link", "price_context", "current_target_owners", "current_manual_decisions", "current_pair_exclusions", "current_anex_mappings", "private_input_pointer", "holds", *FALSE_FLAGS}
    link_keys = {"link_state", "operator_link_sha256", "operator_link_host", "operator_link_path", "raw_identity_values", "raw_identity_tokens", "positive_native_candidates", "namespace_candidate", "namespace_bridge_verified"}
    link_states = {"missing_operator_link", "invalid_origin", "secret_query_redacted", "unexpected_operator_host", "secret_path_redacted", "unknown_selector_redacted", "captured_selector_candidate", "captured_ambiguous_or_unknown_selector", "captured_bg_full_code_bridge_unproven"}
    context_states = {"price_context_missing", "price_context_ambiguous", "price_context_identity_mismatch", "price_context_invalid_or_outside_window", "price_context_capture_incomplete", "exact_saved_price_context_candidate"}
    targets, observations, pairs = set(), set(), set()
    for i, row in enumerate(rows):
        if not isinstance(row, dict) or set(row) != row_keys or type(row["observation_id"]) is not int or not pos(row["observation_id"]) or row["observation_id"] in observations or type(row["target_tv_hotel_id"]) is not int or not pos(row["target_tv_hotel_id"]) or row["operator_id"] not in OPERATORS or type(row["operator_id"]) is not int:
            raise RuntimeError("public_row_scope")
        observations.add(row["observation_id"])
        targets.add(row["target_tv_hotel_id"])
        pair = (row["target_tv_hotel_id"], row["operator_id"])
        if pair in pairs or (row["country_id"] is not None and (type(row["country_id"]) is not int or not pos(row["country_id"]))):
            raise RuntimeError("public_row_identity")
        pairs.add(pair)
        first, last = civil(row["first_seen_civil"]), civil(row["last_seen_civil"])
        if not civil(LOWER) < first <= civil(UPPER) or first > last or row["source"] != "user_search" or row["target_id_namespace"] != "tourvisor" or row["independent_anytour_local_id"] is not None or any(row[k] is not False for k in FALSE_FLAGS):
            raise RuntimeError("public_row_authority")
        if row["private_input_pointer"] != {"sha256": data["private_input_sha256"], "json_pointer": f"/rows/{i}"} or not isinstance(row["native_id_conflict"], bool):
            raise RuntimeError("public_row_pointer")
        holds = row["holds"]
        required_holds = {"historical_datetime_timezone_unverified", "raw_supplier_response_not_exported", "global_identity_uniqueness_not_evaluated", "independent_samo_identity_proof_required"}
        if not isinstance(holds, list) or len(holds) != len(set(holds)) or not required_holds.issubset(holds) or any(not isinstance(h, str) or not re.fullmatch(r"[a-z0-9_]{1,100}", h) for h in holds):
            raise RuntimeError("public_row_holds")
        conditional_holds = {"latest_enrichment_after_window": last > civil(UPPER), "native_id_conflict": row["native_id_conflict"], "target_existing_registry_ownership_review": bool(row["current_target_owners"]), "target_manual_decision_protected": bool(row["current_manual_decisions"]), "target_pair_exclusion_protected": bool(row["current_pair_exclusions"])}
        if any(needed and hold not in holds for hold, needed in conditional_holds.items()):
            raise RuntimeError("public_row_conditional_holds")
        link = row["operator_link"]
        if not isinstance(link, dict) or set(link) != link_keys or link["link_state"] not in link_states or link["namespace_bridge_verified"] is not False:
            raise RuntimeError("public_link_shape")
        if link["namespace_candidate"] not in (None, RULES[row["operator_id"]][2]):
            raise RuntimeError("public_link_namespace")
        if link["operator_link_sha256"] is not None and not re.fullmatch(r"[0-9a-f]{64}", link["operator_link_sha256"] or ""):
            raise RuntimeError("public_link_digest")
        host, path = link["operator_link_host"], link["operator_link_path"]
        if host is not None and (not isinstance(host, str) or not (host == RULES[row["operator_id"]][0] or host.endswith("." + RULES[row["operator_id"]][0]))):
            raise RuntimeError("public_link_host")
        if path is not None and (not isinstance(path, str) or SECRET.search(urllib.parse.unquote(path)) or not re.fullmatch(r"/[A-Za-z0-9/._%~-]{0,511}|", path) or any(len(s) > 64 for s in path.split("/"))):
            raise RuntimeError("public_link_path")
        values, tokens = link["raw_identity_values"], link["raw_identity_tokens"]
        if not isinstance(values, list) or not isinstance(tokens, list) or len(tokens) > 2000 or any(not isinstance(v, str) for v in values) or tokens != [t for v in values for t in v.split(",")] or any(not re.fullmatch(r"[+-]?[0-9]{1,20}", t) for t in tokens) or link["positive_native_candidates"] != [t for t in tokens if re.fullmatch(r"[1-9][0-9]{0,19}", t)]:
            raise RuntimeError("public_link_tokens")
        if link["link_state"] == "captured_selector_candidate" and (row["operator_id"] == 18 or len(tokens) != 1 or len(link["positive_native_candidates"]) != 1 or host is None or path is None or link["operator_link_sha256"] is None or link["namespace_candidate"] != RULES[row["operator_id"]][2]):
            raise RuntimeError("public_link_candidate")
        if link["link_state"] != "captured_selector_candidate" and link["link_state"] not in holds:
            raise RuntimeError("public_link_hold")
        c = row["price_context"]
        if not isinstance(c, dict) or set(c) != {"state", "context"} or c["state"] not in context_states or (c["context"] is not None and (not isinstance(c["context"], dict) or set(c["context"]) != {"observed_at", "country_id", "departure_id", "departure_date", "nights", "adults", "children_count", "child_ages_signature"})):
            raise RuntimeError("public_context_shape")
        if (c["state"] == "exact_saved_price_context_candidate") != (c["context"] is not None):
            raise RuntimeError("public_context_state")
        if c["state"] != "exact_saved_price_context_candidate" and c["state"] not in holds:
            raise RuntimeError("public_context_hold")
        if row["target_catalog"] is not None and (not isinstance(row["target_catalog"], dict) or set(row["target_catalog"]) != {"id", "name", "country_id", "country_name", "region_name", "subregion_name", "is_active", "latitude", "longitude"}):
            raise RuntimeError("public_target_shape")
        mapping_keys = {"current_target_owners": {"supplier_namespace", "external_hotel_id", "local_hotel_id", "decision_status"}, "current_manual_decisions": {"anex_hotel_id", "decision_status", "catalog_hotel_id"}, "current_pair_exclusions": {"anex_hotel_id", "catalog_hotel_id"}, "current_anex_mappings": {"anex_hotel_id", "catalog_hotel_id", "enabled", "scope", "approval_policy"}}
        for field, allowed in mapping_keys.items():
            if not isinstance(row[field], list) or any(not isinstance(v, dict) or set(v) != allowed for v in row[field]):
                raise RuntimeError("public_ownership_shape")
    if len(targets) != data["distinct_target_tv_ids"] or data["hold_counts"] != dict(collections.Counter(h for r in rows for h in r["holds"])):
        raise RuntimeError("public_result_aggregate")
    # A raw URL or URL query can only live in the private immutable capture.
    def check_public(value):
        if isinstance(value, str) and (re.search(r"https?://|[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]", value, re.I) or len(value) > 1024):
            raise RuntimeError("public_private_data")
        if isinstance(value, dict):
            for v in value.values():
                check_public(v)
        elif isinstance(value, list):
            for v in value:
                check_public(v)
    check_public(data)
    if receipt is not None:
        if not isinstance(receipt, dict) or set(receipt) != {*RECEIPT_KEYS, "result_sha256"} or any(receipt[k] != data[k] for k in RECEIPT_KEYS) or receipt["result_sha256"] != hashlib.sha256(enc(data)).hexdigest():
            raise RuntimeError("public_receipt_binding")
    return data


def capture_db(root):
    done = subprocess.run(["php", "-d", "allow_url_fopen=0", "-d", "allow_url_include=0", "-d", "disable_functions=" + PHP_DISABLED, "-r", PHP_CAPTURE], env=dict(os.environ, ANYTOUR_ROOT=str(root)), capture_output=True, timeout=180)
    if done.returncode != 0 or len(done.stdout) > 32 * 1024 * 1024:
        raise RuntimeError("delta_db_capture_failed")
    value = json.loads(done.stdout)
    if not isinstance(value, dict):
        raise RuntimeError("delta_db_capture_shape")
    return value


def execute(root, opdir, manifest_path):
    root, opdir = pathlib.Path(root), pathlib.Path(opdir)
    data = manifest(manifest_path)
    head = os.environ.get("MATCH_SOURCE_SHA", "")
    expected_fixture = pathlib.Path(__file__).resolve().with_name("fixtures") / "hotel_match_user_search_delta_readonly_v1.json"
    if not root.is_dir() or root.is_symlink() or root.name != "anytoour.ru" or root.resolve() != root or not opdir.is_dir() or opdir.is_symlink() or opdir.resolve() != opdir or opdir.name != OP or opdir.parent.name != "operations" or opdir.parent.parent.name != ".anytoour-match" or pathlib.Path(manifest_path) != expected_fixture or not re.fullmatch(r"[0-9a-f]{40}", head):
        raise RuntimeError("runtime_scope")
    res = read_json(opdir / "reservation.json")
    expected = {"operation": OP, "batch": BATCH, "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_db_read"}
    if any(res.get(k) != v for k, v in expected.items()) or res.get("source_sha") != head:
        raise RuntimeError("reservation_scope")
    for name in ("execution-started.json", "current-input.json", "result.json", "receipt.json"):
        if (opdir / name).exists():
            raise RuntimeError("terminal_no_replay")
    save(opdir / "execution-started.json", {"operation": OP, "batch": BATCH, "source_sha": head, "state": "reserved_before_db_read", "no_replay": True})
    rows, capture, private_sha, reason, reads = [], None, None, None, 0
    state = "terminal_failed_no_replay"
    try:
        reads = 1
        capture = capture_db(root)
        private_sha = save(opdir / "current-input.json", capture)
        rows = review_capture(capture, private_sha)
        state = "completed_read_only_delta_incomplete" if capture["overflow"] else "completed_read_only_delta"
    except Exception:
        # Subprocess/DB exceptions can contain private configuration or URL values.
        reason = "delta_capture_or_validation_failed"
        if private_sha is None:
            private_sha = save(opdir / "current-input.json", {"schema": "match-user-search-delta-db-projection/1", "state": "capture_failed", "reason": reason})
    output = {"schema": "match-user-search-delta-readonly-result/1", "operation": OP, "batch": BATCH, "source_sha": head,
              "state": state, "reason": reason, "captured_at_utc": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
              "private_input_sha256": private_sha, "previous_capture": data["previous_capture"], "window_civil": data["window_civil"],
              "timestamp_basis": "historical_php_civil_timezone_unverified", "conservative_interior_window": True,
              "observations_selected": len(rows), "distinct_target_tv_ids": len({r["target_tv_hotel_id"] for r in rows}),
              "overflow": bool(capture and capture.get("overflow")), "operator_ids": list(OPERATORS), "rows": rows,
              "hold_counts": dict(collections.Counter(h for r in rows for h in r["holds"])),
              "provider_http_calls": 0, "physical_http_attempts": 0, "database_reads": reads, "database_writes": 0, "mapping_writes": 0,
              "booking_calls": 0, "lead_calls": 0, "accepted": 0, "written": 0, "safe_to_write_now": False,
              "acceptance_evaluated": False, "global_uniqueness_evaluated": False, "no_replay": True}
    try:
        validate_result(output)
    except Exception:
        output.update(state="terminal_failed_no_replay", reason="delta_capture_or_validation_failed", rows=[], observations_selected=0, distinct_target_tv_ids=0, hold_counts={})
        validate_result(output)
    digest = save(opdir / "result.json", output)
    receipt = {k: output[k] for k in RECEIPT_KEYS} | {"result_sha256": digest}
    validate_result(output, receipt, head)
    save(opdir / "receipt.json", receipt)
    print(json.dumps({k: output[k] for k in ("state", "observations_selected", "accepted", "written")}, sort_keys=True))
    return 0 if output["state"].startswith("completed_read_only_delta") else 2


def self_test():
    manifest(pathlib.Path(__file__).with_name("fixtures") / "hotel_match_user_search_delta_readonly_v1.json")
    p = native_projection(13, "https://agent.anextour.ru/x?HOTELLIST=804,44562&HOTELLIST=-804")
    assert p["raw_identity_tokens"] == ["804", "44562", "-804"]
    assert native_projection(18, "https://www.bgoperator.ru/?f4=102625414997")["namespace_bridge_verified"] is False
    assert native_projection(13, "https://agent.anextour.ru/x?HOTELLIST=secret&token=private")["raw_identity_tokens"] == []
    print("MATCH_USER_SEARCH_DELTA_READONLY_V1_SELFTEST_OK")


if __name__ == "__main__":
    if "--self-test" in sys.argv:
        self_test()
    elif "--print-php" in sys.argv:
        print("<?php\n" + PHP_CAPTURE)
    elif "--lint-php" in sys.argv:
        raise SystemExit(subprocess.run(["php", "-l"], input=("<?php\n" + PHP_CAPTURE).encode()).returncode)
    elif "--execute" in sys.argv:
        raise SystemExit(execute(os.environ["ANYTOUR_ROOT"], os.environ["MATCH_OPERATION_DIR"], os.environ["MATCH_MANIFEST_PATH"]))
    else:
        raise SystemExit("disabled")
