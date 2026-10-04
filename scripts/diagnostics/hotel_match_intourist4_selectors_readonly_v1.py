#!/usr/bin/env python3
"""Fixed Intourist target-selector acquisition. Evidence only; never writes mappings."""
import collections
import datetime as dt
import fcntl
import hashlib
import json
import os
import pathlib
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from zoneinfo import ZoneInfo

OP = "int-tourvisor-match-intourist4-selectors-readonly-20261001-v1"
BATCH = "intourist4-official-context-20261001"
MANIFEST_SHA = "3f05ddb13707866e8b3442da61528a1713b0ac710b53840a8b43ef5181337778"
HTTP_CAP = 14
DAILY_LIMIT = 3000
ACCOUNT = "TOURVISOR_ANEX_JWT"
ACCOUNT_LEDGER = "tourvisor-anex"
BASE = "https://api.tourvisor.ru/search/api/v1"
BODY_LIMIT = 16 * 1024 * 1024
PROTECTED_SOURCE = "2000086118"
SECRET = re.compile(r"(?:token|jwt|auth|pass|password|secret|session|sid|cookie|signature|api[_-]?key)", re.I)
HOTEL_KEYS = {"hotel", "hotels", "hotelid", "hotel_id", "hotelcode", "hotel_code", "hotellist", "hotelkey", "hotel_key"}


def enc(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode()


def fsync_dir(path):
    fd = os.open(path, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
    try:
        os.fsync(fd)
    finally:
        os.close(fd)


def save(path, value):
    path = pathlib.Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    raw = enc(value)
    with open(path, "xb") as handle:
        os.chmod(path, 0o600)
        if handle.write(raw) != len(raw):
            raise RuntimeError("short_write")
        handle.flush()
        os.fsync(handle.fileno())
    fsync_dir(path.parent)
    if path.read_bytes() != raw:
        raise RuntimeError("durable_readback")
    return hashlib.sha256(raw).hexdigest()


def read_json(path):
    path = pathlib.Path(path)
    if not path.is_file() or path.is_symlink():
        raise RuntimeError("json_path")
    value = json.loads(path.read_text())
    if not isinstance(value, dict):
        raise RuntimeError("json_shape")
    return value


def pos(value):
    if isinstance(value, dict):
        value = value.get("id")
    raw = str(value)
    return int(raw) if re.fullmatch(r"[1-9][0-9]{0,20}", raw) else None


def ident(value, key):
    if not isinstance(value, dict):
        return None
    return pos(value.get(key)) or pos(value.get(key + "Id")) or pos(value.get(key + "_id"))


def unwrap(value):
    if isinstance(value, dict) and isinstance(value.get("data"), (dict, list)):
        return value["data"]
    return value


def hotel_rows(value):
    value = unwrap(value)
    if isinstance(value, list):
        return value
    if isinstance(value, dict):
        for key in ("hotels", "results", "items"):
            if isinstance(value.get(key), list):
                return value[key]
    raise RuntimeError("results_shape")


def ready(value):
    value = unwrap(value)
    if not isinstance(value, dict):
        return False
    if isinstance(value.get("progress"), (int, float)) and value["progress"] >= 100:
        return True
    if str(value.get("status", "")).lower() in ("complete", "completed", "done", "ready"):
        return True
    return any(ready(v) for v in value.values() if isinstance(v, dict))


def safe_payload(value, token):
    raw = json.dumps(value, ensure_ascii=False)
    if (token and token in raw) or re.search(
        r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:', raw, re.I
    ):
        return False
    pending = [value]
    while pending:
        item = pending.pop()
        if isinstance(item, dict):
            pending.extend(item.values())
        elif isinstance(item, list):
            pending.extend(item)
        elif isinstance(item, str) and item.startswith(("http://", "https://")):
            try:
                pairs = urllib.parse.parse_qsl(urllib.parse.urlsplit(item).query, keep_blank_values=True)
            except ValueError:
                continue
            if any(SECRET.search(key or "") for key, _ in pairs):
                return False
    return True


def manifest(path):
    raw = pathlib.Path(path).read_bytes()
    if hashlib.sha256(raw).hexdigest() != MANIFEST_SHA:
        raise RuntimeError("manifest_hash")
    value = json.loads(raw)
    if value.get("schema") != "match-intourist4-selectors-readonly/1" or value.get("operation") != OP or value.get("batch") != BATCH:
        raise RuntimeError("manifest_header")
    request = value.get("request")
    expected_request = {
        "operator_id": 43,
        "departure_id": 1,
        "date": "2026-11-15",
        "nights": 7,
        "adults": 2,
        "children": [],
        "currency": "RUB",
        "only_charter": False,
    }
    if request != expected_request or value.get("http_cap") != HTTP_CAP:
        raise RuntimeError("manifest_request")
    expected = {
        "2000034121": ("549", 1151, 4),
        "2000062084": ("18273", 70943, 4),
        "2000052591": ("25728", 128, 1),
        "2000073045": ("29363", 80964, 1),
    }
    rows = value.get("rows")
    if not isinstance(rows, list) or len(rows) != 4:
        raise RuntimeError("manifest_rows")
    observed = {}
    for row in rows:
        if not isinstance(row, dict):
            raise RuntimeError("manifest_row")
        source = row.get("source_catalog_id")
        if source not in expected or source in observed:
            raise RuntimeError("manifest_membership")
        native, target, country = expected[source]
        if (
            row.get("source_namespace") != "operator_342"
            or row.get("source_native_id") != native
            or row.get("target_tv_hotel_id") != target
            or row.get("country_id") != country
        ):
            raise RuntimeError("manifest_identity")
        observed[source] = row
    if set(observed) != set(expected) or value.get("database_writes") != 0 or value.get("mapping_writes") != 0 or value.get("safe_to_write_now") is not False:
        raise RuntimeError("manifest_authority")
    return value


def groups(value):
    out = []
    for country in (4, 1):
        rows = [dict(row) for row in value["rows"] if row["country_id"] == country]
        rows.sort(key=lambda row: row["target_tv_hotel_id"])
        if len(rows) != 2:
            raise RuntimeError("group_shape")
        out.append({"country_id": country, "rows": rows})
    return out


PREFLIGHT_PHP = r'''
$root=$argv[1];$rows=json_decode($argv[2],true,32,JSON_THROW_ON_ERROR);
$dbf=is_file($root."/data/db-v1.php")?$root."/data/db-v1.php":$root."/v2/data/db-v1.php";
require_once $dbf;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$q=function($sql,$args=[])use($db){$s=$db->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);};
$out=[];$db->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");$db->beginTransaction();
try{foreach($rows as $row){$holds=[];$source=(string)$row["source_catalog_id"];$target=(int)$row["target_tv_hotel_id"];
 $s=$q("SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? ORDER BY external_hotel_id",[$source]);
 if(count($s)!==1||$s[0]["decision_status"]!=="pending"||$s[0]["local_hotel_id"]!==null)$holds[]="current_source_not_pending_null";
 else{$h=json_decode((string)$s[0]["evidence_json"],true);if(!is_array($h)||!is_array($h["source"]??null)||(string)($h["source"]["id"]??"")!==$source)$holds[]="current_source_history_review";}
 $hotel=$q("SELECT id,country_id,is_active FROM catalog_hotels WHERE id=?",[$target]);
 if(count($hotel)!==1||(int)$hotel[0]["is_active"]!==1)$holds[]="target_missing_or_inactive";
 elseif((int)$hotel[0]["country_id"]!==(int)$row["country_id"])$holds[]="target_country_changed";
 if($q("SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id=? LIMIT 1",[$target]))$holds[]="target_manual";
 if($q("SELECT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id=? LIMIT 1",[$target]))$holds[]="target_excluded";
 $owners=$q("SELECT external_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id=?",[$target]);
 foreach($owners as $owner)if((string)$owner["external_hotel_id"]!==$source)$holds[]="target_occupied";
 $anex=$q("SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE catalog_hotel_id=?",[$target]);
 foreach($anex as $owner)if((string)$owner["anex_hotel_id"]!==$source)$holds[]="target_occupied";
 if($source==="2000086118")$holds[]="protected_source";
 $holds=array_values(array_unique($holds));sort($holds,SORT_STRING);
 $out[]=["source_catalog_id"=>$source,"target_tv_hotel_id"=>$target,"state"=>$holds===[]?"eligible":"hold","holds"=>$holds,"safe_to_write_now"=>false];
 }$db->rollBack();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
'''


def current_preflight(root, rows):
    proc = subprocess.run(
        ["php", "-r", PREFLIGHT_PHP, "--", str(root), json.dumps(rows, ensure_ascii=False, separators=(",", ":"))],
        capture_output=True,
        timeout=45,
    )
    if proc.returncode != 0:
        raise RuntimeError("current_preflight_failed")
    value = json.loads(proc.stdout)
    if not isinstance(value, list) or len(value) != len(rows):
        raise RuntimeError("current_preflight_shape")
    expected = {(str(r["source_catalog_id"]), int(r["target_tv_hotel_id"])) for r in rows}
    actual = {(str(r.get("source_catalog_id")), pos(r.get("target_tv_hotel_id"))) for r in value}
    if actual != expected or any(r.get("safe_to_write_now") is not False or r.get("state") not in ("eligible", "hold") for r in value):
        raise RuntimeError("current_preflight_identity")
    return value


def native_projection(url):
    if not isinstance(url, str) or not url.strip():
        return {"namespace": "operator_342", "link_state": "missing", "positive_native_candidates": []}
    raw = url.strip()
    digest = hashlib.sha256(raw.encode()).hexdigest()
    try:
        parsed = urllib.parse.urlsplit(raw)
    except ValueError:
        return {"namespace": "operator_342", "link_state": "invalid", "operator_link_sha256": digest, "positive_native_candidates": []}
    out = {"namespace": "operator_342", "operator_link_sha256": digest, "operator_link_host": parsed.hostname or ""}
    if parsed.scheme.lower() != "https" or not parsed.hostname or parsed.username or parsed.password or parsed.fragment:
        out.update(link_state="invalid_origin", positive_native_candidates=[])
        return out
    host = parsed.hostname.lower()
    if host != "intourist.ru" and not host.endswith(".intourist.ru"):
        out.update(link_state="unexpected_intourist_host", positive_native_candidates=[])
        return out
    pairs = urllib.parse.parse_qsl(parsed.query, keep_blank_values=True)
    if any(SECRET.search(k or "") for k, _ in pairs):
        out.update(link_state="secret_bearing_link", positive_native_candidates=[])
        return out
    values = [
        value.strip()
        for key, value in pairs
        if urllib.parse.unquote(key).lower() in HOTEL_KEYS and re.fullmatch(r"[1-9][0-9]{0,19}", value.strip())
    ]
    ids = sorted({int(value) for value in values})
    out["positive_native_candidates"] = ids
    out["query_keys"] = sorted({urllib.parse.unquote(key).lower() for key, _ in pairs})[:40]
    out["link_state"] = "captured_single_native" if len(ids) == 1 and len(values) == 1 else ("captured_ambiguous_native" if values else "missing_native")
    return out


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Provider:
    def __init__(self, root, opdir):
        self.root = pathlib.Path(root)
        self.opdir = pathlib.Path(opdir)
        self.day_value = dt.datetime.now(ZoneInfo("Europe/Moscow")).date().isoformat()
        self.quota = pathlib.Path(os.environ["HOME"]) / ".anytoour-match/provider-quotas"
        self.quota.mkdir(parents=True, exist_ok=True)
        if self.quota.is_symlink():
            raise RuntimeError("quota_path")
        os.chmod(self.quota, 0o700)
        fsync_dir(self.quota.parent)
        self.day = self.quota / f"{ACCOUNT_LEDGER}-{self.day_value}.json"
        self.lock = self.quota / f"{ACCOUNT_LEDGER}-{self.day_value}.lock"
        self.used = 0
        self.tariff_used = 0
        self.counts = collections.Counter()
        self.preflight = []
        self.preflight_seq = 0
        token = subprocess.run(
            [
                "php",
                "-r",
                'require $argv[1];$a=defined("TOURVISOR_ANEX_JWT")?TOURVISOR_ANEX_JWT:getenv("TOURVISOR_ANEX_JWT");$b=defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT");if(!$a||$a===$b){exit(42);}fwrite(STDOUT,(string)$a);',
                "--",
                str(self.root / "config.php"),
            ],
            capture_output=True,
        )
        if token.returncode != 0 or not token.stdout:
            raise RuntimeError("tourvisor_account_guard")
        self.token = token.stdout.decode().strip()
        self.open = urllib.request.build_opener(NoRedirect())

    def reserve(self, action):
        if self.day.is_symlink() or self.lock.is_symlink():
            raise RuntimeError("quota_path")
        with open(self.lock, "a+b") as lock:
            os.chmod(self.lock, 0o600)
            fcntl.flock(lock, fcntl.LOCK_EX)
            state = read_json(self.day) if self.day.exists() else {
                "provider": ACCOUNT_LEDGER,
                "provider_day": self.day_value,
                "owner_daily_limit": DAILY_LIMIT,
                "tariff_search_units": 0,
                "physical_http_attempts": 0,
                "operations": {},
            }
            if state.get("provider_day") != self.day_value or state.get("owner_daily_limit") != DAILY_LIMIT:
                raise RuntimeError("provider_day_ledger_mismatch")
            physical = state.get("physical_http_attempts")
            tariff = state.get("tariff_search_units")
            if not isinstance(physical, int) or physical < 0 or not isinstance(tariff, int) or tariff < 0:
                raise RuntimeError("ledger_counter")
            charge = 1 if action == "search_start" else 0
            if physical >= DAILY_LIMIT or tariff + charge > DAILY_LIMIT or self.used >= HTTP_CAP:
                raise RuntimeError("quota_exhausted")
            self.used += 1
            self.tariff_used += charge
            state["physical_http_attempts"] = physical + 1
            state["tariff_search_units"] = tariff + charge
            state.setdefault("operations", {})[OP] = {
                "physical_http_attempts": self.used,
                "tariff_search_units": self.tariff_used,
                "last_action": action,
            }
            tmp = self.day.with_name(self.day.name + "." + os.urandom(6).hex())
            try:
                save(tmp, state)
                os.replace(tmp, self.day)
                fsync_dir(self.quota)
                if read_json(self.day) != state:
                    raise RuntimeError("ledger_readback")
            finally:
                if tmp.exists():
                    tmp.unlink()

    def check(self, action, rows):
        current = current_preflight(self.root, rows)
        self.preflight_seq += 1
        snapshot = {"sequence": self.preflight_seq, "next_http_call": self.used + 1, "action": action, "rows": current}
        self.preflight.append(snapshot)
        save(self.opdir / f"current-preflight-{self.preflight_seq:02d}.json", snapshot)
        return current

    def call(self, action, path, params, rows):
        current = self.check(action, rows)
        eligible = {(r["source_catalog_id"], r["target_tv_hotel_id"]) for r in current if r["state"] == "eligible"}
        wanted = {(str(r["source_catalog_id"]), int(r["target_tv_hotel_id"])) for r in rows}
        if not eligible:
            raise RuntimeError("current_rows_hold")
        if eligible != wanted:
            raise RuntimeError("current_rows_changed")
        if dt.datetime.now(ZoneInfo("Europe/Moscow")).date().isoformat() != self.day_value:
            raise RuntimeError("provider_day_changed")
        self.reserve(action)
        self.counts[action] += 1
        save(self.opdir / f"tv-request-{self.used:02d}.json", {"call": self.used, "action": action, "path": path, "params": params})
        pairs = [(key, str(item).lower() if isinstance(item, bool) else str(item)) for key, value in params.items() for item in (value if isinstance(value, list) else [value])]
        url = BASE + path + ("?" + urllib.parse.urlencode(pairs) if pairs else "")
        request = urllib.request.Request(url, headers={"Authorization": "Bearer " + self.token, "Accept": "application/json"})
        try:
            response = self.open.open(request, timeout=55)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            code = response.code
            raw = response.read(BODY_LIMIT + 1)
        try:
            data = json.loads(raw)
        except Exception:
            data = {"unparsed_body_sha256": hashlib.sha256(raw).hexdigest()}
        if not safe_payload(data, self.token):
            save(self.opdir / f"tv-response-{self.used:02d}.json", {"http_status": code, "withheld_sensitive_body_sha256": hashlib.sha256(raw).hexdigest()})
            raise RuntimeError("sensitive_response")
        save(self.opdir / f"tv-response-{self.used:02d}.json", {"http_status": code, "raw_sha256": hashlib.sha256(raw).hexdigest(), "data": data})
        if code == 429:
            raise RuntimeError("quota_http_429")
        if code in (401, 403):
            raise RuntimeError("auth_http_" + str(code))
        if code != 200 and not (action == "tour_detail" and code == 404):
            raise RuntimeError("http_" + str(code))
        return code, data


def run_group(provider, opdir, index, group, request):
    rows = group["rows"]
    active = provider.check("group_preflight", rows)
    eligible_keys = {(r["source_catalog_id"], r["target_tv_hotel_id"]) for r in active if r["state"] == "eligible"}
    active_rows = [r for r in rows if (r["source_catalog_id"], r["target_tv_hotel_id"]) in eligible_keys]
    if not active_rows:
        return {"group": index, "country_id": group["country_id"], "state": "preflight_hold", "sent": 0, "initial_preflight": active, "edges": []}
    params = {
        "departureId": request["departure_id"],
        "countryId": group["country_id"],
        "dateFrom": request["date"],
        "dateTo": request["date"],
        "nightsFrom": request["nights"],
        "nightsTo": request["nights"],
        "adults": request["adults"],
        "childs": request["children"],
        "currency": request["currency"],
        "onlyCharter": request["only_charter"],
        "operatorIds": [43],
        "hotelIds": [r["target_tv_hotel_id"] for r in active_rows],
    }
    _, started = provider.call("search_start", "/tours/search", params, active_rows)
    started = unwrap(started)
    search_id = pos(started.get("searchId") if isinstance(started, dict) else None) or pos(started.get("id") if isinstance(started, dict) else None)
    if not search_id:
        raise RuntimeError("search_id_missing")
    complete = False
    for delay in (2, 4, 7):
        time.sleep(delay)
        _, status = provider.call("search_status", f"/tours/search/{search_id}/status", {"operatorStatus": False}, active_rows)
        if ready(status):
            complete = True
            break
    _, result = provider.call("search_results", f"/tours/search/{search_id}", {"limit": 10000}, active_rows)
    wanted = {r["target_tv_hotel_id"]: r for r in active_rows}
    edges = []
    for hotel in hotel_rows(result):
        if not isinstance(hotel, dict):
            continue
        hotel_id = pos(hotel.get("id"))
        if hotel_id not in wanted:
            continue
        tours = hotel.get("tours") or []
        matches = [tour for tour in tours if isinstance(tour, dict) and ident(tour, "operator") == 43 and pos(tour.get("id") or tour.get("tourId"))]
        if not matches:
            continue
        tour_id = str(matches[0].get("id") or matches[0].get("tourId"))
        row = wanted[hotel_id]
        edge = {
            "source_catalog_id": row["source_catalog_id"],
            "source_native_id": row["source_native_id"],
            "target_tv_hotel_id": hotel_id,
            "operator_id": 43,
            "namespace": "operator_342",
            "operator_tour_count": len(matches),
            "tour_id_sha256": hashlib.sha256(tour_id.encode()).hexdigest(),
            "state": "operator_tour_returned",
            "safe_to_write_now": False,
        }
        try:
            code, detail = provider.call("tour_detail", "/tours/" + tour_id, {"currency": "RUB"}, [row])
            detail = unwrap(detail)
            edge["tour_detail_http"] = code
            if code == 404:
                edge["state"] = "detail_404"
            elif not isinstance(detail, dict) or ident(detail, "hotel") != hotel_id or ident(detail, "operator") != 43:
                edge["state"] = "detail_identity_mismatch"
            else:
                edge["state"] = "detail_identity_verified"
                edge.update(native_projection(detail.get("operatorLink")))
                edge["matches_source_native"] = edge.get("positive_native_candidates") == [int(row["source_native_id"])]
        except RuntimeError as error:
            if str(error) not in ("current_rows_hold", "current_rows_changed"):
                raise
            edge.update(state="current_hold_before_detail", link_state="not_read", matches_source_native=False)
        save(opdir / f"tv-edge-{hotel_id}-43.json", edge)
        edges.append(edge)
    return {
        "group": index,
        "country_id": group["country_id"],
        "state": "completed_read_only",
        "sent": len(active_rows),
        "initial_preflight": active,
        "search_complete": complete,
        "returned_targets": len({e["target_tv_hotel_id"] for e in edges}),
        "edges": edges,
    }


def execute(root, opdir, manifest_path):
    root = pathlib.Path(root)
    opdir = pathlib.Path(opdir)
    data = manifest(manifest_path)
    reservation = read_json(opdir / "reservation.json")
    if reservation.get("operation") != OP or reservation.get("batch") != BATCH or reservation.get("provider_http_calls") != HTTP_CAP:
        raise RuntimeError("reservation_scope")
    for path_key, sha_key in (("official_context_path", "official_context_sha256"), ("negative_ledger_path", "negative_ledger_sha256")):
        path = root / data["inputs"][path_key]
        if not path.is_file() or hashlib.sha256(path.read_bytes()).hexdigest() != data["inputs"][sha_key]:
            raise RuntimeError("immutable_input_hash")
    provider = None
    results = []
    state = "failed_before_provider_access"
    reason = None
    try:
        provider = Provider(root, opdir)
        for index, group in enumerate(groups(data), 1):
            save(opdir / f"group-{index}-reservation.json", {
                "operation": OP,
                "batch": BATCH,
                "group": index,
                "country_id": group["country_id"],
                "operator_id": 43,
                "target_tv_hotel_ids": [row["target_tv_hotel_id"] for row in group["rows"]],
                "state": "reserved_before_current_and_provider",
            })
            try:
                results.append(run_group(provider, opdir, index, group, data["request"]))
            except RuntimeError as error:
                if str(error) not in ("current_rows_hold", "current_rows_changed"):
                    raise
                results.append({
                    "group": index,
                    "country_id": group["country_id"],
                    "state": str(error),
                    "sent": 0,
                    "edges": [],
                })
        state = "completed_read_only"
    except Exception as error:
        reason = (type(error).__name__ + ":" + str(error))[:180]
        state = "terminal_failed_no_replay" if provider and provider.used else "failed_before_provider_access"
    edges = [edge for group in results for edge in group.get("edges", [])]
    output = {
        "schema": "match-intourist4-selectors-readonly-result/1",
        "operation": OP,
        "batch": BATCH,
        "source_sha": os.environ.get("MATCH_SOURCE_SHA"),
        "state": state,
        "reason": reason,
        "captured_at_utc": dt.datetime.now(dt.timezone.utc).isoformat(),
        "requested_rows": 4,
        "groups": results,
        "preflight_snapshots": provider.preflight if provider else [],
        "provider_http_calls": provider.used if provider else 0,
        "physical_http_attempts": provider.used if provider else 0,
        "database_reads": len(provider.preflight) if provider else 0,
        "call_counts": dict(provider.counts) if provider else {},
        "returned_edges": len(edges),
        "edge_state_counts": dict(collections.Counter(edge.get("state", "unknown") for edge in edges)),
        "tourvisor_account": ACCOUNT,
        "operator_ids": [43],
        "continue_calls": 0,
        "dates_calls": 0,
        "database_writes": 0,
        "mapping_writes": 0,
        "safe_to_write_now": False,
        "no_replay": bool(provider and provider.used),
    }
    digest = save(opdir / "result.json", output)
    save(opdir / "receipt.json", {
        "operation": OP,
        "batch": BATCH,
        "source_sha": output["source_sha"],
        "state": state,
        "result_sha256": digest,
        "provider_http_calls": output["provider_http_calls"],
        "database_reads": output["database_reads"],
        "database_writes": 0,
        "mapping_writes": 0,
        "safe_to_write_now": False,
        "no_replay": output["no_replay"],
    })
    print(json.dumps({key: output[key] for key in ("state", "reason", "requested_rows", "provider_http_calls", "returned_edges", "edge_state_counts")}, ensure_ascii=False))
    return 0 if state == "completed_read_only" else 2


def self_test():
    path = pathlib.Path(__file__).with_name("fixtures") / "hotel_match_intourist4_selectors_readonly_v1.json"
    value = manifest(path)
    assert [group["country_id"] for group in groups(value)] == [4, 1]
    assert [row["target_tv_hotel_id"] for row in groups(value)[0]["rows"]] == [1151, 70943]
    assert native_projection("https://b2b.intourist.ru/hotel?hotelId=549")["positive_native_candidates"] == [549]
    assert native_projection("https://intourist.ru/hotel?hotelId=549&hotelCode=18273")["link_state"] == "captured_ambiguous_native"
    print("MATCH_INTOURIST4_SELECTORS_READONLY_V1_SELFTEST_OK")


if __name__ == "__main__":
    if "--self-test" in sys.argv:
        self_test()
    elif "--execute" in sys.argv:
        raise SystemExit(execute(os.environ["ANYTOUR_ROOT"], os.environ["MATCH_OPERATION_DIR"], os.environ["MATCH_MANIFEST_PATH"]))
    else:
        raise SystemExit("disabled")
