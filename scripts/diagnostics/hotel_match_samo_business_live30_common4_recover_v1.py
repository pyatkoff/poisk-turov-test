#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
import os
import pathlib
import re
import sys
from collections import Counter, defaultdict
from typing import Any, Iterable

RECOVERY_OP = "hotel-match-samo-business-live30-common4-recover-1971-20260924-v1"
SEALED_OP = "hotel-match-samo-business-live30-common4-acquire-1971-20260924-v2"
PLAN_OP = "hotel-match-samo-business-live30-common4-plan-1971-20260924-v2"
PLAN_SHA256 = "174cdd0bd45f48929fbda2e7c07383fbf81afccd18acccca990aee3ee94e6b23"
OPS = {5: "operator_5", 115: "operator_115", 315: "operator_315", 342: "operator_342"}
EXPECTED_PLAN_ROWS = 1887
EXPECTED_READY_ROWS = 1753
EXPECTED_READY_EDGES = 5988
EXPECTED_GROUPS = 502
MAX_HOTELS = 12
MAX_HOTELS_BYTES = 300
PAGE_CAP = 1000


def need(cond: bool, why: str) -> None:
    if not cond:
        raise RuntimeError(why)


def canonical_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), sort_keys=True)


def load_json(path: pathlib.Path) -> Any:
    with path.open("r", encoding="utf-8") as f:
        return json.load(f)


def write_exclusive_json(path: pathlib.Path, value: Any) -> str:
    raw = (canonical_json(value) + "\n").encode("utf-8")
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as f:
            f.write(raw)
            f.flush()
            os.fsync(f.fileno())
    finally:
        try:
            os.close(fd)
        except OSError:
            pass
    return hashlib.sha256(raw).hexdigest()


def pos_id(v: Any) -> str | None:
    s = str(v).strip()
    return s if re.fullmatch(r"[1-9][0-9]{0,21}", s) else None


def validate_plan(plan: dict[str, Any], plan_path: pathlib.Path) -> list[dict[str, Any]]:
    need(hashlib.sha256(plan_path.read_bytes()).hexdigest() == PLAN_SHA256, "plan_sha256")
    need(plan.get("operation") == PLAN_OP and plan.get("state") == "samo_business_live30_common4_plan_ready", "plan_state")
    need(plan.get("catalog_live30_count") == 1887, "plan_catalog_count")
    need(plan.get("mapped_source_count") == 1764 and plan.get("mapped_unique_local_count") == 1711, "plan_mapped_counts")
    need(plan.get("unresolved_source_count") == 123 and plan.get("collision_source_count") == 0, "plan_resolution_counts")
    need(plan.get("acquisition_ready_count") == EXPECTED_READY_ROWS, "plan_ready_count")
    need(plan.get("saved_catalog_ready_count") == 1882 and plan.get("saved_catalog_not_ready_count") == 5, "plan_saved_counts")
    need(plan.get("missing_lane_counts") == {"operator_5":1685,"operator_115":1820,"operator_315":1459,"operator_342":1516}, "plan_missing_counts")
    for k in ("provider_http_calls","tourvisor_calls","samo_calls","anex_calls","andromeda_calls","database_writes","mapping_writes"):
        need(plan.get(k) == 0, f"plan_zero_{k}")
    need(plan.get("safe_to_write_now") is False, "plan_safe_flag")
    rows = plan.get("rows")
    need(isinstance(rows, list) and len(rows) == EXPECTED_PLAN_ROWS, "plan_rows")
    ready: list[dict[str, Any]] = []
    seen: set[str] = set()
    lane_counts = Counter()
    for row in rows:
        need(isinstance(row, dict), "plan_row_shape")
        if row.get("acquisition_ready") is not True:
            continue
        cid = pos_id(row.get("andromeda_catalog_id"))
        stateinc = int(row.get("saved_stateinc") or 0)
        missing = row.get("missing_operator_ids")
        need(cid is not None and stateinc > 0 and isinstance(missing, list) and missing, "plan_ready_row")
        need(cid not in seen, "plan_ready_source_duplicate")
        seen.add(cid)
        ops: list[int] = []
        for op in missing:
            op = int(op)
            need(op in OPS, "plan_operator")
            if op not in ops:
                ops.append(op)
                lane_counts[op] += 1
        ops.sort()
        ready.append({
            "catalog_id": cid,
            "stateinc": stateinc,
            "missing_operator_ids": ops,
            "mapping_state": str(row.get("mapping_state") or ""),
            "local_hotel_id": row.get("local_hotel_id"),
        })
    need(len(ready) == EXPECTED_READY_ROWS, "plan_ready_rows")
    need(sum(lane_counts.values()) == EXPECTED_READY_EDGES, "plan_ready_edge_count")
    need(dict(sorted(lane_counts.items())) == {5:1562,115:1697,315:1336,342:1393}, "plan_ready_lane_counts")
    return ready


def chunks(ids: Iterable[str]) -> list[list[str]]:
    ordered = sorted(set(map(str, ids)), key=lambda x: int(x))
    out: list[list[str]] = []
    cur: list[str] = []
    for hotel_id in ordered:
        need(pos_id(hotel_id) is not None, "chunk_id")
        candidate = cur + [hotel_id]
        serialized = ",".join(candidate).encode("utf-8")
        if cur and (len(candidate) > MAX_HOTELS or len(serialized) > MAX_HOTELS_BYTES):
            need(len(cur) <= MAX_HOTELS and len(",".join(cur).encode("utf-8")) <= MAX_HOTELS_BYTES, "chunk_guard")
            out.append(cur)
            cur = [hotel_id]
        else:
            cur = candidate
        need(len(cur) <= MAX_HOTELS and len(",".join(cur).encode("utf-8")) <= MAX_HOTELS_BYTES, "chunk_member_guard")
    if cur:
        out.append(cur)
    return out


def groups(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    buckets: dict[tuple[int,int], set[str]] = defaultdict(set)
    for row in rows:
        for op in row["missing_operator_ids"]:
            buckets[(row["stateinc"], int(op))].add(row["catalog_id"])
    out: list[dict[str, Any]] = []
    for (stateinc, op), ids in buckets.items():
        for hotel_ids in chunks(ids):
            out.append({"stateinc": stateinc, "operator_id": op, "hotel_ids": hotel_ids})
    out.sort(key=lambda g: (g["stateinc"], g["operator_id"], int(g["hotel_ids"][0])))
    for g in out:
        need(1 <= len(g["hotel_ids"]) <= MAX_HOTELS, "group_hotel_count")
        need(len(",".join(g["hotel_ids"]).encode("utf-8")) <= MAX_HOTELS_BYTES, "group_hotel_bytes")
    need(len(out) == EXPECTED_GROUPS, "group_count")
    need(sum(len(g["hotel_ids"]) for g in out) == EXPECTED_READY_EDGES, "group_edge_count")
    return out


def bridge(row: dict[str, Any], operator: int, requested: set[str]) -> dict[str, Any]:
    if int(row.get("operatorKey") or 0) != operator:
        return {"state": "other_operator"}
    hotel = pos_id(row.get("hotelKey"))
    flag = str(row.get("isOperatorHotelKey", ""))
    original = row.get("original") if isinstance(row.get("original"), dict) else {}
    orig_hotel = pos_id(original.get("hotelKey"))
    orig_op = int(original.get("operatorKey") or 0)
    if hotel is not None and hotel in requested and flag == "0":
        if orig_hotel is not None and orig_op == operator:
            return {"state":"exact_catalog_to_native","catalog_id":hotel,"native_id":orig_hotel}
        return {"state":"catalog_only","catalog_id":hotel,"native_id":None}
    if flag == "1" and hotel is not None:
        return {"state":"unbound_operator_native","catalog_id":None,"native_id":hotel}
    return {"state":"unbound"}


def sealed_process_alive(sealed_dir: pathlib.Path) -> bool:
    marker = str(sealed_dir)
    script = "hotel_match_samo_business_live30_common4_acquire_v2.php"
    proc = pathlib.Path("/proc")
    if not proc.is_dir():
        return False
    for p in proc.iterdir():
        if not p.name.isdigit():
            continue
        try:
            raw = (p / "cmdline").read_bytes().replace(b"\0", b" ").decode("utf-8", "ignore")
        except (OSError, PermissionError):
            continue
        if marker in raw and script in raw:
            return True
    return False


def manifest_fingerprint(root: pathlib.Path) -> str:
    rows: list[tuple[str,int,int]] = []
    for p in root.rglob("*"):
        if p.is_symlink():
            raise RuntimeError("sealed_symlink_present")
        if p.is_file():
            st = p.stat()
            rows.append((str(p.relative_to(root)), st.st_size, st.st_mtime_ns))
    rows.sort()
    return hashlib.sha256(canonical_json(rows).encode("utf-8")).hexdigest()


def reservation_files(sealed_dir: pathlib.Path) -> tuple[dict[int, pathlib.Path], dict[tuple[int,int], pathlib.Path], dict[tuple[int,int], pathlib.Path]]:
    http: dict[int, pathlib.Path] = {}
    for p in sealed_dir.glob("http-*-reserved.json"):
        m = re.fullmatch(r"http-([0-9]{4})-reserved\.json", p.name)
        need(m is not None, "http_filename")
        n = int(m.group(1)); need(n not in http, "http_duplicate"); http[n] = p
    pages: dict[tuple[int,int], pathlib.Path] = {}
    for p in sealed_dir.glob("batch-*-page-*-reserved.json"):
        m = re.fullmatch(r"batch-([0-9]{3})-page-([0-9]+)-reserved\.json", p.name)
        need(m is not None, "batch_reservation_filename")
        key=(int(m.group(1)),int(m.group(2))); need(key not in pages,"batch_reservation_duplicate"); pages[key]=p
    evidence: dict[tuple[int,int], pathlib.Path] = {}
    edir=sealed_dir/"evidence-private"
    if edir.is_dir():
        for p in edir.glob("batch-*-page-*.json"):
            m=re.fullmatch(r"batch-([0-9]{3})-page-([0-9]+)\.json",p.name)
            need(m is not None,"evidence_filename")
            key=(int(m.group(1)),int(m.group(2))); need(key not in evidence,"evidence_duplicate"); evidence[key]=p
    return http,pages,evidence


def validate_reservations(http: dict[int,pathlib.Path], page_res: dict[tuple[int,int],pathlib.Path], groups_: list[dict[str,Any]]) -> tuple[int,list[int]]:
    if http:
        nums=sorted(http)
        need(nums == list(range(1, max(nums)+1)), "http_noncontiguous")
        for n,p in http.items():
            x=load_json(p); need(x.get("operation")==SEALED_OP and x.get("call")==n and x.get("state")=="reserved_before_http", "http_reservation_content")
    attempted_batches=sorted({b for b,_ in page_res})
    if attempted_batches:
        need(attempted_batches == list(range(1,max(attempted_batches)+1)), "batch_attempt_prefix_noncontiguous")
    for (b,page),p in page_res.items():
        need(1<=b<=len(groups_) and 1<=page<=PAGE_CAP,"batch_reservation_range")
        x=load_json(p);g=groups_[b-1]
        need(x.get("operation")==SEALED_OP and x.get("batch")==b and x.get("page")==page and x.get("state")=="reserved_before_price", "batch_reservation_content")
        need(x.get("stateinc")==g["stateinc"] and x.get("operator_id")==g["operator_id"] and x.get("hotel_count")==len(g["hotel_ids"]), "batch_reservation_binding")
    need(len(http) >= 1, "http_login_reservation_missing")
    need(len(http) <= len(page_res)+1, "http_page_reservation_overflow")
    return (max(attempted_batches) if attempted_batches else 0),attempted_batches


def simulate_batch(batch: int, g: dict[str,Any], page_res: dict[tuple[int,int],pathlib.Path], evidence: dict[tuple[int,int],pathlib.Path]) -> tuple[str,list[dict[str,Any]],dict[str,Any]]:
    reserved_pages=sorted(p for b,p in page_res if b==batch)
    evidence_pages=sorted(p for b,p in evidence if b==batch)
    if not reserved_pages and not evidence_pages:
        return "not_attempted", [], {"batch":batch,"state":"not_attempted","stateinc":g["stateinc"],"operator_id":g["operator_id"],"hotel_count":len(g["hotel_ids"]),"pages_reserved":0,"pages_saved":0}
    need(reserved_pages and reserved_pages[0]==1, "started_batch_missing_page1_reservation")
    need(reserved_pages == list(range(1,max(reserved_pages)+1)), "batch_page_reservations_noncontiguous")
    need(all(p in reserved_pages for p in evidence_pages), "evidence_without_reservation")
    requested=set(g["hotel_ids"])
    edge_map={cid:{"catalog_id":cid,"stateinc":g["stateinc"],"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"returned_catalog":False,"native_ids":set(),"price_rows":0,"safe_to_write_now":False} for cid in g["hotel_ids"]}
    hashes=[];rows_seen=0;unbound=set();used_pages=[];complete=False;terminal_pages_count=None
    for page in range(1,PAGE_CAP+1):
        key=(batch,page)
        if key not in evidence:
            break
        reply=load_json(evidence[key]); need(isinstance(reply,dict),"evidence_shape")
        try:
            pc=int(reply.get("PAGES_COUNT",-1))
        except (TypeError,ValueError):
            raise RuntimeError("pages_count")
        need(0<=pc<=PAGE_CAP,"pages_count")
        prices=reply.get("PRICES",[])
        if isinstance(prices,dict):
            prices=list(prices.values())
        need(isinstance(prices,list),"prices_shape")
        raw=evidence[key].read_bytes();hashes.append(hashlib.sha256(raw).hexdigest());used_pages.append(page);terminal_pages_count=pc
        for price in prices:
            if not isinstance(price,dict):
                continue
            br=bridge(price,g["operator_id"],requested)
            if br["state"]=="other_operator":
                continue
            rows_seen += 1
            cid=br.get("catalog_id")
            if cid is not None:
                if cid not in edge_map:
                    continue
                edge_map[cid]["price_rows"] += 1
                if br["state"]=="exact_catalog_to_native":
                    edge_map[cid]["returned_catalog"]=True
                    edge_map[cid]["native_ids"].add(br["native_id"])
                elif br["state"]=="catalog_only":
                    edge_map[cid]["returned_catalog"]=True
            elif br["state"]=="unbound_operator_native" and br.get("native_id") is not None:
                unbound.add(br["native_id"])
        if pc==0 or page>=pc:
            complete=True
            break
    if complete:
        need(used_pages == list(range(1,max(used_pages)+1)),"evidence_pages_noncontiguous")
        need(max(reserved_pages)==max(used_pages),"reservation_after_completed_batch")
        need(set(evidence_pages)==set(used_pages),"extra_evidence_after_completed_batch")
        edges=[]
        for cid in sorted(edge_map,key=int):
            e=edge_map[cid]
            ids=sorted(e.pop("native_ids"),key=int)
            e["positive_native_candidates"]=ids
            if len(ids)==1:
                e["state"]="captured_single_native"
            elif len(ids)>1:
                e["state"]="captured_ambiguous_native"
            elif e["returned_catalog"]:
                e["state"]="catalog_only"
            else:
                e["state"]="not_returned_in_context"
            e["recovery_batch"]=batch
            e["recovery_batch_state"]="fully_drained"
            edges.append(e)
        return "fully_drained",edges,{"batch":batch,"state":"fully_drained","stateinc":g["stateinc"],"operator_id":g["operator_id"],"hotel_count":len(g["hotel_ids"]),"pages_reserved":len(reserved_pages),"pages_saved":len(used_pages),"terminal_pages_count":terminal_pages_count,"price_rows_seen":rows_seen,"unbound_operator_native_count":len(unbound),"response_sha256s":hashes}
    edges=[]
    for cid in sorted(g["hotel_ids"],key=int):
        edges.append({"catalog_id":cid,"stateinc":g["stateinc"],"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"positive_native_candidates":[],"price_rows":0,"safe_to_write_now":False,"state":"partial_unresolved_no_replay","recovery_batch":batch,"recovery_batch_state":"partial_unresolved_no_replay"})
    return "partial_unresolved_no_replay",edges,{"batch":batch,"state":"partial_unresolved_no_replay","stateinc":g["stateinc"],"operator_id":g["operator_id"],"hotel_count":len(g["hotel_ids"]),"pages_reserved":len(reserved_pages),"pages_saved":len(evidence_pages),"last_reserved_page":max(reserved_pages),"last_saved_page":max(evidence_pages) if evidence_pages else 0}


def recover(plan_path: pathlib.Path,sealed_dir:pathlib.Path,source_sha:str) -> dict[str,Any]:
    need(re.fullmatch(r"[0-9a-f]{40}",source_sha) is not None,"source_sha")
    need(sealed_dir.is_dir() and not sealed_dir.is_symlink() and sealed_dir.name==SEALED_OP,"sealed_dir")
    need(not sealed_process_alive(sealed_dir),"sealed_provider_process_still_alive")
    need(not (sealed_dir/"result.json").exists() and not (sealed_dir/"receipt.json").exists(),"sealed_terminal_files_present")
    reservation=load_json(sealed_dir/"reservation.json")
    need(reservation.get("operation")==SEALED_OP and reservation.get("state")=="reserved_before_provider","sealed_reservation")
    before=manifest_fingerprint(sealed_dir)
    plan=load_json(plan_path)
    rows=validate_plan(plan,plan_path)
    groups_=groups(rows)
    http,page_res,evidence=reservation_files(sealed_dir)
    attempted_prefix,_=validate_reservations(http,page_res,groups_)
    states=Counter();batch_rows=[];edges=[]
    for batch,g in enumerate(groups_,start=1):
        state,es,br=simulate_batch(batch,g,page_res,evidence)
        states[state]+=1
        batch_rows.append(br)
        if state=="not_attempted":
            for cid in sorted(g["hotel_ids"],key=int):
                es.append({"catalog_id":cid,"stateinc":g["stateinc"],"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"positive_native_candidates":[],"price_rows":0,"safe_to_write_now":False,"state":"not_attempted","recovery_batch":batch,"recovery_batch_state":"not_attempted"})
        edges.extend(es)
    need(len(edges)==EXPECTED_READY_EDGES,"recovered_edge_count")
    partial=[b["batch"] for b in batch_rows if b["state"]=="partial_unresolved_no_replay"]
    untouched=[b["batch"] for b in batch_rows if b["state"]=="not_attempted"]
    if untouched:
        need(untouched==list(range(min(untouched),EXPECTED_GROUPS+1)),"not_attempted_not_suffix")
    continuation_start=min(untouched) if untouched else None
    for b in range(1,attempted_prefix+1):
        need(batch_rows[b-1]["state"] in ("fully_drained","partial_unresolved_no_replay"),"attempted_batch_state")
    edge_counts=Counter(e["state"] for e in edges)
    op_counts=defaultdict(Counter)
    native_targets=defaultdict(set)
    for e in edges:
        op_counts[str(e["operator_id"])][e["state"]]+=1
        if e["state"]=="captured_single_native":
            native_targets[e["namespace"]+"|"+e["positive_native_candidates"][0]].add(e["catalog_id"])
    unique=sum(1 for x in native_targets.values() if len(x)==1)
    colliding=sum(1 for x in native_targets.values() if len(x)>1)
    after=manifest_fingerprint(sealed_dir)
    need(after==before,"sealed_directory_changed_during_recovery")
    return {
        "operation":RECOVERY_OP,"state":"completed_read_only_recovery","source_sha":source_sha,
        "sealed_operation":SEALED_OP,"sealed_operation_no_replay":True,"plan_operation":PLAN_OP,"plan_result_sha256":PLAN_SHA256,
        "catalog_target_count":1887,"acquisition_ready_count":EXPECTED_READY_ROWS,"queried_edge_count":EXPECTED_READY_EDGES,"planned_batch_count":EXPECTED_GROUPS,
        "original_http_reservations":len(http),"original_batch_page_reservations":len(page_res),"saved_response_pages":len(evidence),"attempted_batch_prefix":attempted_prefix,
        "fully_drained_batch_count":states["fully_drained"],"partial_batch_count":states["partial_unresolved_no_replay"],"not_attempted_batch_count":states["not_attempted"],
        "partial_batches":partial,"continuation_start_batch":continuation_start,
        "edge_state_counts":dict(sorted(edge_counts.items())),"operator_state_counts":{k:dict(sorted(v.items())) for k,v in sorted(op_counts.items(),key=lambda x:int(x[0]))},
        "single_native_source_unique_count":unique,"single_native_source_collision_count":colliding,
        "batches":batch_rows,"edges":edges,
        "provider_http_calls":0,"tourvisor_calls":0,"samo_calls":0,"anex_calls":0,"andromeda_calls":0,"database_writes":0,"mapping_writes":0,"safe_to_write_now":False,
        "sealed_manifest_sha256":before,
    }


def main() -> None:
    ap=argparse.ArgumentParser()
    ap.add_argument("--self-test",action="store_true")
    ap.add_argument("--execute",action="store_true")
    args=ap.parse_args()
    if args.self_test:
        ids=[str(100000000000000000+i) for i in range(25)]
        cs=chunks(ids)
        need(len(cs)==3 and all(len(x)<=12 and len(",".join(x).encode())<=300 for x in cs),"self_chunks")
        b=bridge({"operatorKey":315,"hotelKey":"2001","isOperatorHotelKey":0,"original":{"hotelKey":"8123","operatorKey":315}},315,{"2001"})
        need(b=={"state":"exact_catalog_to_native","catalog_id":"2001","native_id":"8123"},"self_bridge")
        print("MATCH_SAMO_BUSINESS_LIVE30_COMMON4_RECOVER_V1_SELFTEST_OK")
        return
    need(args.execute,"disabled")
    opdir=pathlib.Path(os.environ.get("MATCH_OPERATION_DIR", ""))
    sealed=pathlib.Path(os.environ.get("MATCH_SEALED_OPERATION_DIR", ""))
    plan=pathlib.Path(os.environ.get("MATCH_PLAN_RESULT", ""))
    source_sha=os.environ.get("MATCH_SOURCE_SHA","")
    need(opdir.is_dir() and not opdir.is_symlink() and opdir.name==RECOVERY_OP,"operation_dir")
    reservation=load_json(opdir/"reservation.json")
    need(reservation.get("operation")==RECOVERY_OP and reservation.get("state")=="reserved_before_recovery","recovery_reservation")
    try:
        out=recover(plan,sealed,source_sha)
        h=write_exclusive_json(opdir/"result.json",out)
        receipt={"operation":RECOVERY_OP,"state":out["state"],"result_sha256":h,"readback_verified":hashlib.sha256((opdir/"result.json").read_bytes()).hexdigest()==h,"provider_accessed":False,"provider_http_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True}
        write_exclusive_json(opdir/"receipt.json",receipt)
        summary={k:out[k] for k in ("state","original_http_reservations","saved_response_pages","attempted_batch_prefix","fully_drained_batch_count","partial_batch_count","not_attempted_batch_count","partial_batches","continuation_start_batch","edge_state_counts","single_native_source_unique_count","single_native_source_collision_count")}
        print(canonical_json(summary))
    except Exception as e:
        reason=re.sub(r"[^A-Za-z0-9_.:-]+","_",str(e))[:180]
        fail={"operation":RECOVERY_OP,"state":"failed_read_only_recovery","reason":reason,"provider_http_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True}
        h=write_exclusive_json(opdir/"result.json",fail)
        write_exclusive_json(opdir/"receipt.json",{"operation":RECOVERY_OP,"state":fail["state"],"result_sha256":h,"readback_verified":True,"provider_accessed":False,"provider_http_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True})
        print(reason,file=sys.stderr)
        raise SystemExit(2)


if __name__=="__main__":
    main()
