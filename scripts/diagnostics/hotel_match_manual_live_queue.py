#!/usr/bin/env python3
"""Read-only CURRENT DB export for MATCH #1971 manual handoff.

No supplier/Tourvisor calls and no DB writes. The script reserves one immutable
operation directory before DB access, reads CURRENT data in a READ ONLY
transaction through the deployed PHP DB bootstrap, ranks unresolved live-observed
ANEX + Andromeda rows, and writes JSON/CSV/receipt artifacts.
"""
from __future__ import annotations

import csv
import difflib
import hashlib
import json
import math
import os
import re
import subprocess
import sys
import tempfile
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

CORE8 = {1, 2, 4, 8, 9, 10, 12, 16}
GENERIC = {"hotel", "hotels", "resort", "resorts", "spa", "отель", "отели", "резорт", "ресорт", "спа"}
QUALIFIERS = {"annex", "beach", "garden", "north", "south", "east", "west", "club", "palace", "royal", "grand", "premium", "select", "family", "adults"}
FIELDS = [
    "rank", "priority_score", "provider", "external_hotel_id", "search_count", "last_seen_utc",
    "country_id", "source_name", "source_aliases", "source_region", "source_star", "source_latitude",
    "source_longitude", "candidate_local_id", "candidate_name", "candidate_country", "candidate_region",
    "candidate_subregion", "candidate_star", "candidate_latitude", "candidate_longitude", "distance_km",
    "name_score", "second_name_score", "margin", "evidence_method", "qualifier_conflict", "numeric_conflict",
    "pair_excluded", "existing_provider_bridges", "auto_block_reason", "manual_action_hint"
]


def dump(obj: Any) -> str:
    return json.dumps(obj, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def write_exclusive(path: Path, raw: bytes) -> str:
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL
    fd = os.open(path, flags, 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as fh:
            fh.write(raw); fh.flush(); os.fsync(fh.fileno())
    finally:
        os.close(fd)
    if path.read_bytes() != raw:
        raise RuntimeError("durable_readback_failed")
    return sha(raw)


def norm(s: Any) -> str:
    text = unicodedata.normalize("NFKD", str(s or "")).casefold()
    text = re.sub(r"\(\s*(?:ex|ех)\s*\.?\s+([^()]*)\)", r" \1 ", text, flags=re.I)
    text = "".join(ch if ch.isalnum() else " " for ch in text)
    toks = [t for t in text.split() if t not in GENERIC]
    return " ".join(toks)


def tokens(s: Any) -> list[str]:
    return norm(s).split()


def nums(s: Any) -> set[str]:
    return set(re.findall(r"\b\d+\b", norm(s)))


def quals(s: Any) -> set[str]:
    return set(tokens(s)) & QUALIFIERS


def score_name(a: str, b: str) -> float:
    aa, bb = norm(a), norm(b)
    if not aa or not bb:
        return 0.0
    if aa == bb:
        return 1.0
    sa, sb = set(aa.split()), set(bb.split())
    jac = len(sa & sb) / max(1, len(sa | sb))
    seq = difflib.SequenceMatcher(None, aa, bb, autojunk=False).ratio()
    return round(0.55 * seq + 0.45 * jac, 6)


def number(v: Any) -> float | None:
    try:
        if v is None or str(v).strip() == "": return None
        x = float(v)
        return x if math.isfinite(x) else None
    except (TypeError, ValueError): return None


def coord_from(d: dict[str, Any]) -> tuple[float | None, float | None]:
    for a, b in (("latitude", "longitude"), ("lat", "lng"), ("lat", "lon"),
                 ("api_latitude", "api_longitude"), ("hotelLatitude", "hotelLongitude")):
        x, y = number(d.get(a)), number(d.get(b))
        if x is not None and y is not None and abs(x) <= 90 and abs(y) <= 180:
            return x, y
    return None, None


def haversine_km(a: float | None, b: float | None, c: float | None, d: float | None) -> float | None:
    if None in (a, b, c, d): return None
    r = 6371.0088
    p1, p2 = math.radians(a), math.radians(c)
    dp, dl = math.radians(c-a), math.radians(d-b)
    q = math.sin(dp/2)**2 + math.cos(p1)*math.cos(p2)*math.sin(dl/2)**2
    return round(2*r*math.asin(min(1, math.sqrt(q))), 3)


def parse_json(v: Any) -> dict[str, Any]:
    if isinstance(v, dict): return v
    if not isinstance(v, str) or not v: return {}
    try:
        out = json.loads(v)
        return out if isinstance(out, dict) else {}
    except Exception: return {}


def first(d: dict[str, Any], *keys: str) -> Any:
    for k in keys:
        if d.get(k) not in (None, ""): return d[k]
    return None


def php_reader() -> str:
    # SELECT-only payload; table rows are capped and transaction is explicitly read-only.
    return r'''<?php
    declare(strict_types=1);
    error_reporting(0); ob_start();
    function q(PDO $db,string $sql): array {
      if(!str_starts_with(ltrim($sql),'SELECT ') || str_contains($sql,';')) throw new RuntimeException('select_only');
      $r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC); if(count($r)>100000) throw new RuntimeException('row_cap'); return $r;
    }
    try {
      $root=realpath(getcwd()); if(!$root || basename($root)!=='anytoour.ru') throw new RuntimeException('root_guard');
      require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
      $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
      $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
      $out=[];
      $out['anex_obs']=q($db,'SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id');
      $out['anex_stage']=q($db,'SELECT * FROM anex_hotels ORDER BY anex_hotel_id');
      $out['anex_manual']=q($db,'SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id');
      $out['anex_map']=q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id');
      $out['anex_excl']=q($db,'SELECT * FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id');
      $out['anex_candidates']=q($db,'SELECT * FROM anex_hotel_candidates WHERE candidate_rank<=5 ORDER BY anex_hotel_id,candidate_rank');
      $out['and_id']=q($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");
      $out['and_obs']=q($db,"SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id");
      $out['catalog']=q($db,'SELECT id,country_id,country_name,name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_id IN (1,2,4,8,9,10,12,16) AND is_active=1 ORDER BY id');
      $db->exec('ROLLBACK');
      ob_end_clean(); echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); exit(0);
    } catch(Throwable $e) { try { if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK'); }catch(Throwable $x){} ob_end_clean(); fwrite(STDERR,'read_failed\n'); exit(2); }
    '''


def current_snapshot() -> dict[str, Any]:
    proc = subprocess.run(["php", "-d", "allow_url_fopen=0", "-d", "display_errors=0", "-r", php_reader().replace("<?php", "", 1)],
                          stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False, timeout=120)
    if proc.returncode != 0:
        raise RuntimeError("current_db_read_failed")
    try:
        doc = json.loads(proc.stdout)
    except Exception as exc:
        raise RuntimeError("current_db_json_invalid") from exc
    if not isinstance(doc, dict): raise RuntimeError("current_db_json_invalid")
    return doc


def self_test() -> int:
    assert norm("SUNRISE Arabian BEACH RESORT & SPA") == "sunrise arabian beach"
    assert score_name("A Hotel", "A Resort") == 1.0
    assert nums("Discovery 2") == {"2"}
    assert "beach" in quals("Foo Beach Hotel")
    assert haversine_km(36.713018,31.563078,36.713018,31.563078) == 0.0
    src = php_reader().upper()
    for word in ("INSERT ", "UPDATE ", "DELETE ", "REPLACE ", "ALTER ", "DROP ", "CREATE ", "TRUNCATE "):
        assert word not in src
    print("manual-live-queue self-test: PASS")
    return 0


def main() -> int:
    if "--self-test" in sys.argv: return self_test()
    op = os.environ.get("OPERATION_ID", "")
    source_sha = os.environ.get("MATCH_SOURCE_SHA", "")
    if not re.fullmatch(r"hotel-match-manual-live-queue-1971-20260914-v\d+", op): raise RuntimeError("operation_id_required")
    if not re.fullmatch(r"[0-9a-f]{40}", source_sha): raise RuntimeError("source_sha_required")
    root = Path.cwd().resolve(); home = Path.home().resolve()
    if root.name != "anytoour.ru": raise RuntimeError("root_guard")
    base = home / ".anytoour-match" / "operations"
    if not base.is_dir(): raise RuntimeError("operations_root_missing")
    out = base / op
    out.mkdir(mode=0o700, exist_ok=False)
    reservation = {"operation_id":op,"source_sha":source_sha,"state":"reserved_before_db_access","read_only":True,"supplier_calls":0,"tourvisor_calls":0,"database_writes":0,"no_replay":True}
    write_exclusive(out/"reservation.json", (dump(reservation)+"\n").encode())
    try:
        doc = current_snapshot()
        catalog = {int(x["id"]): x for x in doc.get("catalog", []) if int(x.get("country_id") or 0) in CORE8}
        by_country: dict[int, list[dict[str, Any]]] = defaultdict(list)
        token_index: dict[int, dict[str, set[int]]] = defaultdict(lambda: defaultdict(set))
        for h in catalog.values():
            c=int(h["country_id"]); by_country[c].append(h)
            for t in set(tokens(h.get("name"))): token_index[c][t].add(int(h["id"]))

        anex_stage={int(x["anex_hotel_id"]):x for x in doc.get("anex_stage",[]) if x.get("anex_hotel_id")}
        manual_ids={int(x["anex_hotel_id"]) for x in doc.get("anex_manual",[]) if x.get("anex_hotel_id")}
        mapped_ids={int(x["anex_hotel_id"]) for x in doc.get("anex_map",[]) if x.get("anex_hotel_id")}
        excluded: dict[int,set[int]]=defaultdict(set)
        for x in doc.get("anex_excl",[]):
            if x.get("anex_hotel_id") and x.get("catalog_hotel_id"): excluded[int(x["anex_hotel_id"])].add(int(x["catalog_hotel_id"]))
        acands: dict[int,list[dict[str,Any]]]=defaultdict(list)
        for x in doc.get("anex_candidates",[]):
            if x.get("anex_hotel_id"): acands[int(x["anex_hotel_id"])].append(x)

        and_rows={str(x["external_hotel_id"]):x for x in doc.get("and_id",[]) if x.get("external_hotel_id") is not None}
        and_accepted={str(k):int(v["local_hotel_id"]) for k,v in and_rows.items() if v.get("decision_status")=="accepted" and v.get("local_hotel_id")}
        anex_local_bridges: dict[int,list[str]]=defaultdict(list)
        for x in doc.get("anex_map",[]):
            if x.get("catalog_hotel_id"): anex_local_bridges[int(x["catalog_hotel_id"])].append(str(x.get("anex_hotel_id")))
        and_local_bridges: dict[int,list[str]]=defaultdict(list)
        for k,v in and_accepted.items(): and_local_bridges[v].append(k)

        and_obs_groups: dict[str,list[dict[str,Any]]]=defaultdict(list)
        for x in doc.get("and_obs",[]): and_obs_groups[str(x.get("external_hotel_id"))].append(x)

        rows=[]
        def choose(country:int, names:list[str], source:dict[str,Any], excluded_targets:set[int], preferred:list[int]|None=None):
            names=[str(x) for x in names if str(x or "").strip()]
            ids=[]
            if preferred:
                ids=[x for x in preferred if x in catalog and int(catalog[x]["country_id"])==country]
            if not ids:
                pool=set()
                for n in names:
                    for t in set(tokens(n)): pool |= token_index[country].get(t,set())
                if not pool: pool={int(h["id"]) for h in by_country.get(country,[])}
                ids=list(pool)
            scored=[]
            for hid in ids:
                h=catalog[hid]
                best=max((score_name(n,h["name"]) for n in names),default=0.0)
                scored.append((best,hid))
            scored.sort(reverse=True)
            top=scored[0] if scored else (0.0,None); second=scored[1] if len(scored)>1 else (0.0,None)
            if top[1] is None: return None,0.0,0.0,0.0,None,False,False
            h=catalog[top[1]]; slat,slon=coord_from(source); tlat,tlon=coord_from(h); dist=haversine_km(slat,slon,tlat,tlon)
            source_join=" ".join(names); qc=bool(quals(source_join)^quals(h["name"])) and bool(quals(source_join)|quals(h["name"]))
            nc=bool(nums(source_join) or nums(h["name"])) and nums(source_join)!=nums(h["name"])
            return h,top[0],second[0],round(top[0]-second[0],6),dist,qc,nc

        for o in doc.get("anex_obs",[]):
            aid=int(o.get("anex_hotel_id") or 0); country=int(o.get("country_id") or 0); count=int(o.get("search_count") or 0)
            if aid<=0 or count<=0 or country not in CORE8 or aid in manual_ids or aid in mapped_ids: continue
            s=anex_stage.get(aid,{})
            names=[o.get("hotel_name"),s.get("api_name"),s.get("xml_name"),s.get("xml_alternate_name")]
            source={**s,**{k:v for k,v in o.items() if v not in (None,"")}}
            pref=[int(x["catalog_hotel_id"]) for x in acands.get(aid,[]) if x.get("catalog_hotel_id")]
            h,sc,sc2,margin,dist,qc,nc=choose(country,[str(x) for x in names if x],source,excluded[aid],pref)
            hid=int(h["id"]) if h else None; pair=hid in excluded[aid] if hid else False
            reason="no_candidate" if not h else "pair_excluded" if pair else "coordinate_conflict_gt5km" if dist is not None and dist>5 else "meaningful_qualifier_conflict" if qc or nc else "ambiguous_or_insufficient_independent_evidence" if sc<0.94 or margin<0.08 else "strong_candidate_held_for_aggregate_or_manual"
            rows.append({"provider":"anex","external_hotel_id":str(aid),"search_count":count,"last_seen_utc":o.get("last_seen_utc"),"country_id":country,"source_name":str(o.get("hotel_name") or first(s,"api_name","xml_name") or ""),"source_aliases":[str(x) for x in names[1:] if x],"source_region":first(s,"api_region","api_town"),"source_star":first(s,"api_star","star","stars"),"source_latitude":coord_from(source)[0],"source_longitude":coord_from(source)[1],"candidate":h,"distance_km":dist,"name_score":sc,"second_name_score":sc2,"margin":margin,"evidence_method":"saved_candidate+current_name_rank" if pref else "current_name_rank","qualifier_conflict":qc,"numeric_conflict":nc,"pair_excluded":pair,"auto_block_reason":reason})

        for ext, ident in and_rows.items():
            if ident.get("decision_status")!="pending" or ident.get("local_hotel_id") not in (None,"",0,"0"): continue
            obs=and_obs_groups.get(ext,[])
            if not obs: continue
            latest=obs[0]; count=len(obs); country=int(latest.get("country_id") or 0)
            if country not in CORE8: continue
            ev=parse_json(ident.get("evidence_json")); src=ev.get("source") if isinstance(ev.get("source"),dict) else {}
            geo=ev.get("geography") if isinstance(ev.get("geography"),dict) else {}
            names=[latest.get("hotel_name"),src.get("name"),src.get("lName")]
            source={**src,**geo,**{k:v for k,v in latest.items() if v not in (None,"")}}
            h,sc,sc2,margin,dist,qc,nc=choose(country,[str(x) for x in names if x],source,set())
            reason="no_candidate" if not h else "coordinate_conflict_gt5km" if dist is not None and dist>5 else "meaningful_qualifier_conflict" if qc or nc else "ambiguous_or_insufficient_independent_evidence" if sc<0.94 or margin<0.08 else "strong_candidate_held_for_aggregate_or_manual"
            rows.append({"provider":"andromeda","external_hotel_id":ext,"search_count":count,"last_seen_utc":latest.get("observed_at_utc"),"country_id":country,"source_name":str(latest.get("hotel_name") or src.get("name") or src.get("lName") or ""),"source_aliases":[str(x) for x in names[1:] if x],"source_region":first(src,"town","region","parent") or first(latest,"region_name"),"source_star":first(src,"category","star","stars","starName"),"source_latitude":coord_from(source)[0],"source_longitude":coord_from(source)[1],"candidate":h,"distance_km":dist,"name_score":sc,"second_name_score":sc2,"margin":margin,"evidence_method":"current_identity_evidence+live_name_rank","qualifier_conflict":qc,"numeric_conflict":nc,"pair_excluded":False,"auto_block_reason":reason})

        # Business priority: live frequency dominates, then confidence and freshness ordering already stable.
        rows.sort(key=lambda r:(-int(r["search_count"]), -float(r["name_score"]), str(r.get("last_seen_utc") or ""), r["provider"], r["external_hotel_id"]))
        flat=[]
        for i,r in enumerate(rows,1):
            h=r.pop("candidate",None); hid=int(h["id"]) if h else None
            bridges=[]
            if hid:
                if anex_local_bridges.get(hid): bridges.append("anex:"+",".join(anex_local_bridges[hid][:5]))
                if and_local_bridges.get(hid): bridges.append("andromeda:"+",".join(and_local_bridges[hid][:5]))
            priority=round(math.log2(1+int(r["search_count"]))*10 + float(r["name_score"])*5,3)
            flat.append({"rank":i,"priority_score":priority,**r,"candidate_local_id":hid,"candidate_name":h.get("name") if h else None,"candidate_country":h.get("country_name") if h else None,"candidate_region":h.get("region_name") if h else None,"candidate_subregion":h.get("subregion_name") if h else None,"candidate_star":h.get("category") if h else None,"candidate_latitude":number(h.get("latitude")) if h else None,"candidate_longitude":number(h.get("longitude")) if h else None,"existing_provider_bridges":bridges,"manual_action_hint":"accept_candidate_or_choose_other" if h else "choose_local_or_defer"})

        counts=Counter(r["provider"] for r in flat); reasons=Counter(r["auto_block_reason"] for r in flat)
        result={"schema":"hotel-match-manual-live-queue/1","operation_id":op,"source_sha":source_sha,"status":"read_only_complete","generated_at_utc":__import__('datetime').datetime.now(__import__('datetime').timezone.utc).isoformat(),"queue_count":len(flat),"provider_counts":dict(counts),"reason_counts":dict(reasons),"live_frequency_sum":sum(int(r["search_count"]) for r in flat),"top25":flat[:25],"database_writes":0,"mapping_writes":0,"supplier_calls":0,"tourvisor_calls":0,"manual_decisions_preserved":True,"pair_exclusions_preserved":True,"current_mappings_preserved":True,"no_replay":True}
        qraw=(json.dumps({"schema":"hotel-match-manual-live-queue/1","rows":flat},ensure_ascii=False,sort_keys=True,indent=2)+"\n").encode(); qhash=write_exclusive(out/"queue.json",qraw)
        with tempfile.NamedTemporaryFile("w",encoding="utf-8",newline="",delete=False) as tf:
            w=csv.DictWriter(tf,fieldnames=FIELDS,extrasaction="ignore"); w.writeheader()
            for r in flat:
                x=dict(r); x["source_aliases"]=" | ".join(x.get("source_aliases") or []); x["existing_provider_bridges"]=" | ".join(x.get("existing_provider_bridges") or []); w.writerow(x)
            temp=Path(tf.name)
        craw=temp.read_bytes(); temp.unlink(); chash=write_exclusive(out/"queue.csv",craw)
        result["queue_json_sha256"]=qhash; result["queue_csv_sha256"]=chash
        rraw=(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+"\n").encode(); rhash=write_exclusive(out/"result.json",rraw)
        receipt={"operation_id":op,"state":"read_only_complete","result_sha256":rhash,"queue_json_sha256":qhash,"queue_csv_sha256":chash,"readback_verified":True,"database_writes":0,"supplier_calls":0,"tourvisor_calls":0,"no_replay":True}
        write_exclusive(out/"receipt.json",(dump(receipt)+"\n").encode())
        summary=(f"MATCH #1971 manual live queue: {len(flat)} unresolved live-observed rows; ANEX {counts.get('anex',0)}, Andromeda {counts.get('andromeda',0)}; "
                 f"frequency sum {result['live_frequency_sum']}; DB/mapping/supplier/Tourvisor writes/calls 0. Sorted by live frequency; CURRENT read-only; manual/mappings/exclusions preserved.\n")
        write_exclusive(out/"summary.md",summary.encode())
        print("MATCH_QUEUE_RESULT:"+dump(result))
        return 0
    except Exception as exc:
        failure={"operation_id":op,"status":"failed","reason":str(exc) if str(exc) in {"current_db_read_failed","current_db_json_invalid"} else "export_stopped","database_writes":0,"supplier_calls":0,"tourvisor_calls":0,"no_replay":True}
        try: write_exclusive(out/"failure.json",(dump(failure)+"\n").encode())
        except Exception: pass
        raise


if __name__ == "__main__":
    raise SystemExit(main())
