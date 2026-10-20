#!/usr/bin/env python3
"""Mass read-only passive Tourvisor operator evidence for MATCH #1971.

Consumes the hash-pinned CURRENT v8 unresolved queue already stored on the server,
removes known non-hotel products, and reads only existing
`tour_operator_identity_observations`. No Tourvisor/supplier calls and no DB writes.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

OPERATION_ID = "hotel-match-passive-tv-operator-evidence-1971-20260915-v1"
V8_OPERATION_ID = "hotel-match-manual-live-queue-1971-20260914-v8"
V8_QUEUE_SHA256 = "f17ca8e8d9d3945f6aea3d232fca96708fc6f119c504461f95a4cc51af8ed3ed"
V8_RESULT_SHA256 = "52acba203042e86a1650ece5b0ae079dac6cfb49f25b5bc6747cdaefd316b09d"
NONHOTEL_ANEX_IDS = {"17097","817","28869","5173","815","29000","17194","17195","17196","17443","20963"}
SENSITIVE_KEYS = re.compile(r"token|password|passwd|secret|session|auth|credential|bearer|api[_-]?key|signature|sig", re.I)
ANEX_NAME = re.compile(r"\b(?:anex|анекс)\b", re.I)
ANEX_HOST = re.compile(r"(?:^|\.)anextour\.ru$", re.I)


def canon(obj: Any) -> bytes:
    return (json.dumps(obj, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode()


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def write_exclusive(path: Path, obj: Any) -> str:
    raw = canon(obj)
    fd = os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as fh:
            fh.write(raw); fh.flush(); os.fsync(fh.fileno())
    finally:
        os.close(fd)
    if path.read_bytes() != raw:
        raise RuntimeError("durable_readback")
    return sha(raw)


def safe_url(value: Any) -> str | None:
    if not isinstance(value, str) or not value or len(value) > 2048:
        return None
    try:
        p = urlparse(value)
    except Exception:
        return None
    if p.scheme.lower() != "https" or not p.hostname or p.username or p.password:
        return None
    q = parse_qs(p.query, keep_blank_values=True)
    if any(SENSITIVE_KEYS.search(str(k)) for k in q):
        return None
    return value


def native_ids(value: Any) -> tuple[int, ...]:
    url = safe_url(value)
    if not url:
        return ()
    p = urlparse(url)
    q = parse_qs(p.query, keep_blank_values=True)
    out: set[int] = set()
    for key, vals in q.items():
        if key.lower() not in {"hotellist", "hotelcode", "hotels", "hotel"}:
            continue
        for val in vals:
            for token in re.split(r"[,;\s]+", str(val)):
                if re.fullmatch(r"[1-9][0-9]{0,19}", token):
                    out.add(int(token))
    return tuple(sorted(out))


def is_anex(row: dict[str, Any]) -> bool:
    name = str(row.get("operator_name") or "")
    host = str(row.get("operator_link_host") or "").lower()
    return bool(ANEX_NAME.search(name) or ANEX_HOST.search(host))


def load_v8(home: Path) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    root = home / ".anytoour-match" / "operations" / V8_OPERATION_ID
    queue_path = root / "queue.json"
    result_path = root / "result.json"
    receipt_path = root / "receipt.json"
    for p in (queue_path, result_path, receipt_path):
        if not p.is_file() or p.is_symlink():
            raise RuntimeError("v8_input_missing")
    qraw, rraw = queue_path.read_bytes(), result_path.read_bytes()
    if sha(qraw) != V8_QUEUE_SHA256 or sha(rraw) != V8_RESULT_SHA256:
        raise RuntimeError("v8_input_hash")
    queue, result, receipt = json.loads(qraw), json.loads(rraw), json.loads(receipt_path.read_bytes())
    if result.get("operation_id") != V8_OPERATION_ID or result.get("status") != "read_only_complete":
        raise RuntimeError("v8_result_state")
    if receipt.get("readback_verified") is not True or receipt.get("result_sha256") != V8_RESULT_SHA256:
        raise RuntimeError("v8_receipt")
    rows = queue.get("rows")
    if not isinstance(rows, list) or len(rows) != 198:
        raise RuntimeError("v8_queue_shape")
    targets = []
    for r in rows:
        if not isinstance(r, dict) or r.get("provider") != "anex":
            continue
        ext = str(r.get("external_hotel_id") or "")
        if ext in NONHOTEL_ANEX_IDS:
            continue
        local = r.get("candidate_local_id")
        if not re.fullmatch(r"[1-9][0-9]{0,19}", ext) or not isinstance(local, int) or local <= 0:
            raise RuntimeError("target_identity")
        targets.append(r)
    if len(targets) != 97:
        raise RuntimeError("target_count")
    return targets, {"queue_sha256":sha(qraw),"result_sha256":sha(rraw),"generated_at_utc":result.get("generated_at_utc")}


def php_reader() -> str:
    return r'''<?php
    declare(strict_types=1);
    error_reporting(0); ob_start();
    try {
      $root=realpath(getcwd()); if(!$root || basename($root)!=='anytoour.ru') throw new RuntimeException('root');
      require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
      $raw=(string)getenv('MATCH_LOCAL_IDS'); if(!preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D',$raw)) throw new RuntimeException('ids');
      $ids=array_values(array_unique(array_map('intval',explode(',',$raw)))); if(!$ids || count($ids)>100) throw new RuntimeException('scope');
      $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
      $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
      $exists=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_operator_identity_observations'")->fetchColumn();
      if((int)$exists!==1) throw new RuntimeException('table_missing');
      $ph=implode(',',array_fill(0,count($ids),'?'));
      $sql="SELECT id,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,operator_link,operator_link_host,operator_link_path,operator_link_query FROM tour_operator_identity_observations WHERE hotel_id IN ($ph) ORDER BY hotel_id,last_seen_at DESC,id DESC LIMIT 100000";
      $q=$db->prepare($sql); $q->execute($ids); $rows=$q->fetchAll(PDO::FETCH_ASSOC); if(count($rows)>=100000) throw new RuntimeException('row_cap');
      $db->exec('ROLLBACK'); ob_end_clean(); echo json_encode(['rows'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); exit(0);
    } catch(Throwable $e) { try{if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable $x){} ob_end_clean(); fwrite(STDERR,'read_failed\n'); exit(2); }
    '''


def read_current(local_ids: list[int]) -> list[dict[str, Any]]:
    env = os.environ.copy(); env["MATCH_LOCAL_IDS"] = ",".join(map(str, local_ids))
    code = php_reader().replace("<?php", "", 1)
    p = subprocess.run(["php","-d","display_errors=0","-r",code], stdout=subprocess.PIPE, stderr=subprocess.PIPE, env=env, timeout=120)
    if p.returncode != 0:
        raise RuntimeError("db_read_failed")
    doc = json.loads(p.stdout)
    rows = doc.get("rows")
    if not isinstance(rows, list):
        raise RuntimeError("db_shape")
    return rows


def classify(target: dict[str, Any], rows: list[dict[str, Any]]) -> dict[str, Any]:
    ext = int(target["external_hotel_id"]); local = int(target["candidate_local_id"]); country = int(target["country_id"])
    applicable = [r for r in rows if int(r.get("hotel_id") or 0) == local and int(r.get("country_id") or 0) == country and is_anex(r)]
    links=[]; singleton=[]; membership=[]; conflicts=[]; unparsed=[]; tours=[]
    seen_links=set(); seen_tours=set()
    for r in applicable:
        tour=str(r.get("tour_id") or "")
        if tour and tour not in seen_tours:
            seen_tours.add(tour); tours.append(tour)
        link=safe_url(r.get("operator_link"))
        if not link:
            continue
        ids=native_ids(link); key=(link,ids)
        if key in seen_links: continue
        seen_links.add(key)
        item={"url":link,"native_ids":list(ids),"operator_id":r.get("operator_id"),"operator_name":r.get("operator_name"),"tour_id":tour or None,"last_seen_at":r.get("last_seen_at")}
        links.append(item)
        if ids == (ext,): singleton.append(item)
        elif ext in ids: membership.append(item)
        elif ids: conflicts.append(item)
        else: unparsed.append(item)
    if conflicts:
        route="conflicting_native_link_hold"
    elif singleton:
        route="exact_singleton_native_link_candidate"
    elif membership:
        route="exact_multi_native_membership_candidate"
    elif tours:
        route="saved_anex_tour_detail_fallback"
    else:
        route="no_passive_anex_observation"
    return {
        "external_anex_id":str(ext),"source_name":target.get("source_name"),"search_count":int(target.get("search_count") or 0),
        "country_id":country,"candidate_local_id":local,"candidate_name":target.get("candidate_name"),
        "current_hold_reason":target.get("auto_block_reason"),"source_region":target.get("source_region"),
        "passive_anex_observation_rows":len(applicable),"saved_anex_tour_ids":tours[:20],"operator_links":links[:30],
        "exact_singleton_link_count":len(singleton),"exact_multi_membership_count":len(membership),"conflicting_parsed_link_count":len(conflicts),"unparsed_link_count":len(unparsed),"route":route,
    }


def self_test() -> int:
    assert native_ids("https://agent.anextour.ru/search/tour?HOTELLIST=4158") == (4158,)
    assert native_ids("https://x.test/search?HOTELS=12114,12232") == (12114,12232)
    assert native_ids("https://online.anextour.ru/search?hotelCode=4158") == (4158,)
    assert native_ids("https://x.test/?token=x&HOTELS=1") == ()
    assert safe_url("http://x.test/") is None
    assert is_anex({"operator_name":"Anex Tour"})
    assert is_anex({"operator_link_host":"agent.anextour.ru"})
    assert not is_anex({"operator_name":"Intourist","operator_link_host":"intourist.ru"})
    assert "UPDATE " not in php_reader().upper() and "INSERT " not in php_reader().upper() and "DELETE " not in php_reader().upper()
    print("passive-tv-operator-evidence self-test: PASS")
    return 0


def main() -> int:
    if "--self-test" in sys.argv:
        return self_test()
    source_sha=os.environ.get("MATCH_SOURCE_SHA","")
    if os.environ.get("OPERATION_ID") != OPERATION_ID or not re.fullmatch(r"[0-9a-f]{40}",source_sha):
        raise RuntimeError("execution_guard")
    home=Path.home().resolve(); base=home/".anytoour-match"/"operations"
    if not base.is_dir(): raise RuntimeError("operations_root")
    out=base/OPERATION_ID; out.mkdir(mode=0o700,exist_ok=False)
    write_exclusive(out/"reservation.json",{"operation_id":OPERATION_ID,"source_sha":source_sha,"state":"reserved_before_db_access","read_only":True,"supplier_calls":0,"tourvisor_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True})
    targets,pins=load_v8(home); local_ids=sorted({int(r["candidate_local_id"]) for r in targets})
    rows=read_current(local_ids)
    grouped=defaultdict(list)
    for r in rows: grouped[int(r.get("hotel_id") or 0)].append(r)
    evidence=[classify(t,grouped[int(t["candidate_local_id"])]) for t in targets]
    evidence.sort(key=lambda x:(-x["search_count"],int(x["external_anex_id"])))
    routes=Counter(x["route"] for x in evidence)
    result={"schema":"hotel-match-passive-tv-operator-evidence/1","operation_id":OPERATION_ID,"source_sha":source_sha,"state":"read_only_complete","input":pins,"target_count":len(targets),"unique_candidate_local_ids":len(local_ids),"passive_rows_read":len(rows),"route_counts":dict(routes),"evidence":evidence,"supplier_calls":0,"tourvisor_calls":0,"database_writes":0,"mapping_writes":0,"booking_calls":0,"production_changed":False,"no_replay":True}
    eh=write_exclusive(out/"evidence.json",{"schema":"hotel-match-passive-tv-operator-evidence-rows/1","rows":evidence})
    result["evidence_sha256"]=eh
    rh=write_exclusive(out/"result.json",result)
    write_exclusive(out/"receipt.json",{"operation_id":OPERATION_ID,"source_sha":source_sha,"state":"read_only_complete","result_sha256":rh,"evidence_sha256":eh,"readback_verified":sha((out/"result.json").read_bytes())==rh and sha((out/"evidence.json").read_bytes())==eh,"supplier_calls":0,"tourvisor_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True})
    print(json.dumps({"target_count":len(targets),"passive_rows_read":len(rows),"route_counts":dict(routes),"result_sha256":rh},sort_keys=True))
    return 0


if __name__ == "__main__":
    try: raise SystemExit(main())
    except Exception as exc:
        print(json.dumps({"operation_id":OPERATION_ID,"state":"failed_read_only","reason":type(exc).__name__,"supplier_calls":0,"tourvisor_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True}),file=sys.stderr)
        raise
