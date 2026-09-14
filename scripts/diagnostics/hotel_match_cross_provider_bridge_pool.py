#!/usr/bin/env python3
"""MATCH #1971 read-only CURRENT ANEX↔Andromeda bridge pool.

No supplier/API calls and no writes. The operation reconciles every live unresolved
ANEX/core8 hotel against already-accepted Andromeda→local identities, current
catalog candidates, exclusions and coordinates, then emits one immutable pool.
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
QUALIFIERS = {"annex", "beach", "garden", "north", "south", "east", "west", "club", "palace", "royal", "grand", "premium", "select", "family", "adults", "adult"}
FIELDS = [
    "rank","status","anex_hotel_id","country_id","search_count","anex_name","candidate_local_id","catalog_name",
    "andromeda_ids","andromeda_names","anex_catalog_score","anex_andromeda_score","andromeda_catalog_score","candidate_margin",
    "anex_catalog_distance_km","andromeda_catalog_min_distance_km","qualifier_conflict","numeric_conflict","pair_excluded",
    "coordinate_conflict","accepted_andromeda_bridge_count","reason"
]


def dump(v: Any) -> str:
    return json.dumps(v, ensure_ascii=False, sort_keys=True, separators=(",", ":"))


def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def write_exclusive(path: Path, raw: bytes) -> str:
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    try:
        with os.fdopen(fd, "wb", closefd=False) as fh:
            fh.write(raw); fh.flush(); os.fsync(fh.fileno())
    finally:
        os.close(fd)
    if path.read_bytes() != raw: raise RuntimeError("durable_readback_failed")
    return sha(raw)


def norm(s: Any) -> str:
    t = unicodedata.normalize("NFKD", str(s or "")).casefold()
    t = re.sub(r"\(\s*(?:ex|ех)\s*\.?\s+([^()]*)\)", r" \1 ", t, flags=re.I)
    t = "".join(ch if ch.isalnum() else " " for ch in t)
    return " ".join(x for x in t.split() if x not in GENERIC)


def tokens(s: Any) -> list[str]: return norm(s).split()
def nums(s: Any) -> set[str]: return set(re.findall(r"\b\d+\b", norm(s)))
def quals(s: Any) -> set[str]: return set(tokens(s)) & QUALIFIERS


def score(a: Any, b: Any) -> float:
    aa, bb = norm(a), norm(b)
    if not aa or not bb: return 0.0
    if aa == bb: return 1.0
    sa, sb = set(aa.split()), set(bb.split())
    jac = len(sa & sb) / max(1, len(sa | sb))
    seq = difflib.SequenceMatcher(None, aa, bb, autojunk=False).ratio()
    return round(0.55 * seq + 0.45 * jac, 6)


def number(v: Any) -> float | None:
    try:
        if v is None or str(v).strip()=="": return None
        x=float(v); return x if math.isfinite(x) else None
    except Exception: return None


def coord(d: dict[str, Any]) -> tuple[float|None,float|None]:
    for a,b in (("latitude","longitude"),("lat","lng"),("lat","lon"),("api_latitude","api_longitude"),("hotelLatitude","hotelLongitude")):
        x,y=number(d.get(a)),number(d.get(b))
        if x is not None and y is not None and abs(x)<=90 and abs(y)<=180: return x,y
    return None,None


def hav(a: float|None,b: float|None,c: float|None,d: float|None) -> float|None:
    if None in (a,b,c,d): return None
    r=6371.0088; p1,p2=math.radians(a),math.radians(c); dp=math.radians(c-a); dl=math.radians(d-b)
    q=math.sin(dp/2)**2+math.cos(p1)*math.cos(p2)*math.sin(dl/2)**2
    return round(2*r*math.asin(min(1,math.sqrt(q))),3)


def parse_json(v: Any) -> dict[str,Any]:
    if isinstance(v,dict): return v
    if not isinstance(v,str) or not v: return {}
    try:
        x=json.loads(v); return x if isinstance(x,dict) else {}
    except Exception: return {}


def first(d: dict[str,Any], *keys: str) -> Any:
    for k in keys:
        if d.get(k) not in (None,""): return d[k]
    return None


def php_reader() -> str:
    return r'''<?php
    declare(strict_types=1); error_reporting(0); ob_start();
    function q(PDO $db,string $sql): array { if(!str_starts_with(ltrim($sql),'SELECT ') || str_contains($sql,';')) throw new RuntimeException('select_only'); $r=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC); if(count($r)>100000) throw new RuntimeException('row_cap'); return $r; }
    try {
      $root=realpath(getcwd()); if(!$root || basename($root)!=='anytoour.ru') throw new RuntimeException('root_guard');
      require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
      $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
      $o=[];
      $o['anex_obs']=q($db,'SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id');
      $o['anex_stage']=q($db,'SELECT * FROM anex_hotels ORDER BY anex_hotel_id');
      $o['anex_map']=q($db,'SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id');
      $o['anex_manual']=q($db,'SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id');
      $o['anex_excl']=q($db,'SELECT * FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id');
      $o['anex_candidates']=q($db,'SELECT * FROM anex_hotel_candidates WHERE candidate_rank<=5 ORDER BY anex_hotel_id,candidate_rank');
      $o['and_id']=q($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");
      $o['and_obs']=q($db,"SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id");
      $o['catalog']=q($db,'SELECT id,country_id,country_name,name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_id IN (1,2,4,8,9,10,12,16) AND is_active=1 ORDER BY id');
      $db->exec('ROLLBACK'); ob_end_clean(); echo json_encode($o,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); exit(0);
    } catch(Throwable $e) { try { if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK'); }catch(Throwable $x){} ob_end_clean(); fwrite(STDERR,'read_failed\n'); exit(2); }
    '''


def snapshot() -> dict[str,Any]:
    p=subprocess.run(["php","-d","allow_url_fopen=0","-d","display_errors=0","-r",php_reader().replace("<?php","",1)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=120)
    if p.returncode!=0: raise RuntimeError("current_db_read_failed")
    x=json.loads(p.stdout)
    if not isinstance(x,dict): raise RuntimeError("current_db_json_invalid")
    return x


def names_from_andromeda(ident: dict[str,Any], obs: list[dict[str,Any]]) -> tuple[list[str],dict[str,Any]]:
    ev=parse_json(ident.get("evidence_json")); src=ev.get("source") if isinstance(ev.get("source"),dict) else {}; geo=ev.get("geography") if isinstance(ev.get("geography"),dict) else {}
    names=[]
    for v in (src.get("name"),src.get("lName"),src.get("hotelName")):
        if v and str(v) not in names: names.append(str(v))
    for row in obs[:5]:
        for k in ("hotel_name","name","hotelName"):
            v=row.get(k)
            if v and str(v) not in names: names.append(str(v))
    source={**src,**geo}
    for row in obs[:1]: source.update({k:v for k,v in row.items() if v not in (None,"")})
    return names,source


def main() -> int:
    if "--self-test" in sys.argv:
        assert norm("SUNRISE Beach Resort & SPA") == "sunrise beach"
        assert score("Tango Arjaan By Rotana","Tango Arjaan By Rotana Hotel")==1.0
        assert hav(36.7,31.5,36.7,31.5)==0.0
        src=php_reader().upper()
        for w in ("INSERT ","UPDATE ","DELETE ","REPLACE ","ALTER ","DROP ","CREATE ","TRUNCATE "): assert w not in src
        print("cross-provider-bridge-pool self-test: PASS"); return 0
    op=os.environ.get("OPERATION_ID",""); source_sha=os.environ.get("MATCH_SOURCE_SHA","")
    if op!="hotel-match-cross-provider-bridge-pool-1971-20260914-v1": raise RuntimeError("operation_id_required")
    if not re.fullmatch(r"[0-9a-f]{40}",source_sha): raise RuntimeError("source_sha_required")
    root=Path.cwd().resolve(); home=Path.home().resolve()
    if root.name!="anytoour.ru": raise RuntimeError("root_guard")
    base=home/".anytoour-match"/"operations"
    if not base.is_dir(): raise RuntimeError("operations_root_missing")
    out=base/op; out.mkdir(mode=0o700,exist_ok=False)
    reservation={"operation_id":op,"source_sha":source_sha,"state":"reserved_before_db_access","read_only":True,"supplier_calls":0,"andromeda_calls":0,"tourvisor_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True}
    write_exclusive(out/"reservation.json",(dump(reservation)+"\n").encode())
    try:
        d=snapshot()
        catalog={int(x["id"]):x for x in d.get("catalog",[]) if x.get("id")}
        stage={int(x["anex_hotel_id"]):x for x in d.get("anex_stage",[]) if x.get("anex_hotel_id")}
        mapped={int(x["anex_hotel_id"]) for x in d.get("anex_map",[]) if x.get("anex_hotel_id")}
        manual={int(x["anex_hotel_id"]) for x in d.get("anex_manual",[]) if x.get("anex_hotel_id")}
        excluded: dict[int,set[int]]=defaultdict(set)
        for x in d.get("anex_excl",[]):
            if x.get("anex_hotel_id") and x.get("catalog_hotel_id"): excluded[int(x["anex_hotel_id"])].add(int(x["catalog_hotel_id"]))
        cands: dict[int,list[dict[str,Any]]]=defaultdict(list)
        for x in d.get("anex_candidates",[]):
            if x.get("anex_hotel_id") and x.get("catalog_hotel_id"): cands[int(x["anex_hotel_id"])].append(x)

        and_obs: dict[str,list[dict[str,Any]]]=defaultdict(list)
        for x in d.get("and_obs",[]): and_obs[str(x.get("external_hotel_id"))].append(x)
        and_by_local: dict[int,list[dict[str,Any]]]=defaultdict(list)
        for ident in d.get("and_id",[]):
            if ident.get("decision_status")!="accepted" or not ident.get("local_hotel_id"): continue
            lid=int(ident["local_hotel_id"])
            ext=str(ident.get("external_hotel_id"))
            ns,src=names_from_andromeda(ident,and_obs.get(ext,[]))
            and_by_local[lid].append({"external_hotel_id":ext,"names":ns,"source":src})

        rows=[]
        for o in d.get("anex_obs",[]):
            aid=int(o.get("anex_hotel_id") or 0); country=int(o.get("country_id") or 0); count=int(o.get("search_count") or 0)
            if aid<=0 or country not in CORE8 or count<=0 or aid in mapped or aid in manual: continue
            s=stage.get(aid,{})
            anex_names=[]
            for v in (o.get("hotel_name"),s.get("api_name"),s.get("xml_name"),s.get("xml_alternate_name")):
                if v and str(v) not in anex_names: anex_names.append(str(v))
            anex_name=anex_names[0] if anex_names else ""
            asource={**s,**{k:v for k,v in o.items() if v not in (None,"")}}
            candidate_rows=cands.get(aid,[])
            # If saved candidates are missing, this pass deliberately does not invent a new local target.
            for idx,cr in enumerate(candidate_rows):
                lid=int(cr["catalog_hotel_id"]); h=catalog.get(lid)
                if not h or int(h.get("country_id") or 0)!=country: continue
                bridges=and_by_local.get(lid,[])
                if not bridges: continue
                ac=max((score(n,h.get("name")) for n in anex_names),default=0.0)
                # saved rank margin between current candidate and next candidate's catalog name score
                other_scores=[]
                for cr2 in candidate_rows:
                    lid2=int(cr2["catalog_hotel_id"]); h2=catalog.get(lid2)
                    if h2 and lid2!=lid: other_scores.append(max((score(n,h2.get("name")) for n in anex_names),default=0.0))
                margin=round(ac-max(other_scores),6) if other_scores else ac
                best_aa=0.0; best_dc=0.0; best_bridge=None; best_and_name=""; min_and_dist=None
                hlat,hlon=coord(h); alat,alon=coord(asource); adist=hav(alat,alon,hlat,hlon)
                for b in bridges:
                    bnames=b["names"]
                    aa=max((score(an,bn) for an in anex_names for bn in bnames),default=0.0)
                    dc=max((score(bn,h.get("name")) for bn in bnames),default=0.0)
                    blat,blon=coord(b["source"]); bdist=hav(blat,blon,hlat,hlon)
                    if bdist is not None and (min_and_dist is None or bdist<min_and_dist): min_and_dist=bdist
                    if (aa+dc)>(best_aa+best_dc):
                        best_aa,best_dc,best_bridge=aa,dc,b; best_and_name=max(bnames,key=lambda n:score(anex_name,n),default="")
                aq=quals(" ".join(anex_names)); cq=quals(h.get("name")); bq=quals(best_and_name)
                anums=nums(" ".join(anex_names)); cnums=nums(h.get("name")); bnums=nums(best_and_name)
                qconf=bool((aq^cq) or (aq^bq)) and bool(aq|cq|bq)
                nsets=[x for x in (anums,cnums,bnums) if x]
                nconf=len(nsets)>=2 and any(x!=nsets[0] for x in nsets[1:])
                pair=lid in excluded[aid]
                coord_conf=(adist is not None and adist>5) or (min_and_dist is not None and min_and_dist>5)
                # Conservative preparation gate: independent accepted Andromeda→local bridge plus strong three-way name agreement.
                safe=(not pair and not coord_conf and not qconf and not nconf and best_bridge is not None
                      and best_aa>=0.90 and ac>=0.78 and best_dc>=0.78 and (margin>=0.06 or best_aa>=0.97))
                medium=(not safe and not pair and not coord_conf and not qconf and not nconf and best_bridge is not None
                        and best_aa>=0.84 and ac>=0.70 and best_dc>=0.70)
                status="safe_bridge_candidate" if safe else "medium_bridge_candidate" if medium else "blocked_or_weak"
                reason=("accepted_andromeda_bridge+strong_three_way_name" if safe else "accepted_andromeda_bridge+moderate_three_way_name" if medium else
                        "pair_excluded" if pair else "coordinate_conflict_gt5km" if coord_conf else "meaningful_qualifier_conflict" if qconf else "numeric_conflict" if nconf else "weak_three_way_name")
                rows.append({"status":status,"anex_hotel_id":aid,"country_id":country,"search_count":count,"anex_name":anex_name,"candidate_local_id":lid,"catalog_name":h.get("name"),
                             "andromeda_ids":[x["external_hotel_id"] for x in bridges],"andromeda_names":sorted({n for x in bridges for n in x["names"]})[:12],
                             "anex_catalog_score":ac,"anex_andromeda_score":best_aa,"andromeda_catalog_score":best_dc,"candidate_margin":margin,
                             "anex_catalog_distance_km":adist,"andromeda_catalog_min_distance_km":min_and_dist,"qualifier_conflict":qconf,"numeric_conflict":nconf,"pair_excluded":pair,"coordinate_conflict":coord_conf,
                             "accepted_andromeda_bridge_count":len(bridges),"reason":reason})
        # Keep only the best local target per ANEX row, preferring safe/medium then combined confidence.
        status_rank={"safe_bridge_candidate":2,"medium_bridge_candidate":1,"blocked_or_weak":0}
        best: dict[int,dict[str,Any]]={}
        for r in rows:
            key=(status_rank[r["status"]],r["anex_andromeda_score"]+r["anex_catalog_score"]+r["andromeda_catalog_score"],r["candidate_margin"])
            cur=best.get(r["anex_hotel_id"])
            if cur is None:
                best[r["anex_hotel_id"]]=r
            else:
                ckey=(status_rank[cur["status"]],cur["anex_andromeda_score"]+cur["anex_catalog_score"]+cur["andromeda_catalog_score"],cur["candidate_margin"])
                if key>ckey: best[r["anex_hotel_id"]]=r
        outrows=list(best.values())
        outrows.sort(key=lambda r:(-status_rank[r["status"]],-int(r["search_count"]),-(r["anex_andromeda_score"]+r["anex_catalog_score"]+r["andromeda_catalog_score"]),int(r["anex_hotel_id"])))
        for i,r in enumerate(outrows,1): r["rank"]=i
        counts=Counter(r["status"] for r in outrows)
        result={"schema":"hotel-match-cross-provider-bridge-pool/1","operation_id":op,"source_sha":source_sha,"status":"read_only_complete",
                "live_unresolved_anex_count":sum(1 for o in d.get("anex_obs",[]) if int(o.get("anex_hotel_id") or 0)>0 and int(o.get("country_id") or 0) in CORE8 and int(o.get("search_count") or 0)>0 and int(o.get("anex_hotel_id") or 0) not in mapped and int(o.get("anex_hotel_id") or 0) not in manual),
                "bridge_pool_count":len(outrows),"status_counts":dict(counts),"safe_search_weight":sum(int(r["search_count"]) for r in outrows if r["status"]=="safe_bridge_candidate"),
                "medium_search_weight":sum(int(r["search_count"]) for r in outrows if r["status"]=="medium_bridge_candidate"),
                "database_writes":0,"mapping_writes":0,"supplier_calls":0,"andromeda_calls":0,"tourvisor_calls":0,"manual_decisions_preserved":True,"pair_exclusions_preserved":True,"current_mappings_preserved":True,"no_replay":True,
                "top_safe": [r for r in outrows if r["status"]=="safe_bridge_candidate"][:30]}
        jraw=(json.dumps({"schema":"hotel-match-cross-provider-bridge-pool/1","rows":outrows},ensure_ascii=False,sort_keys=True,indent=2)+"\n").encode(); jhash=write_exclusive(out/"bridge-pool.json",jraw)
        with tempfile.NamedTemporaryFile("w",encoding="utf-8",newline="",delete=False) as tf:
            w=csv.DictWriter(tf,fieldnames=FIELDS,extrasaction="ignore"); w.writeheader()
            for r in outrows:
                x=dict(r); x["andromeda_ids"]=" | ".join(x["andromeda_ids"]); x["andromeda_names"]=" | ".join(x["andromeda_names"]); w.writerow(x)
            temp=Path(tf.name)
        craw=temp.read_bytes(); temp.unlink(); chash=write_exclusive(out/"bridge-pool.csv",craw)
        result["bridge_pool_json_sha256"]=jhash; result["bridge_pool_csv_sha256"]=chash
        rraw=(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+"\n").encode(); rhash=write_exclusive(out/"result.json",rraw)
        receipt={"operation_id":op,"source_sha":source_sha,"state":"read_only_complete","result_sha256":rhash,"bridge_pool_json_sha256":jhash,"bridge_pool_csv_sha256":chash,"readback_verified":sha((out/"result.json").read_bytes())==rhash,"database_writes":0,"mapping_writes":0,"supplier_calls":0,"andromeda_calls":0,"tourvisor_calls":0,"no_replay":True}
        write_exclusive(out/"receipt.json",(dump(receipt)+"\n").encode())
        print("MATCH_BRIDGE_RESULT:"+dump(result)); return 0
    except Exception as exc:
        try: write_exclusive(out/"failure.json",(dump({"operation_id":op,"source_sha":source_sha,"status":"failed_closed","reason":type(exc).__name__,"database_writes":0,"mapping_writes":0,"supplier_calls":0,"andromeda_calls":0,"tourvisor_calls":0,"no_replay":True})+"\n").encode())
        except Exception: pass
        raise


if __name__=="__main__": raise SystemExit(main())
