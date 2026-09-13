#!/usr/bin/env python3
"""Saved Tourvisor multi-date saturation for MATCH #1971.

Offline only: consumes a current ANEX residual JSON plus saved Tourvisor result JSONs.
Never performs network/DB access and never authorizes mappings.
"""
from __future__ import annotations
import argparse, collections, hashlib, json, re, unicodedata
from pathlib import Path
from rapidfuzz import fuzz

GENERIC={"hotel","hotels","resort","resorts","spa","the","otel"}
PRODUCT_IDS={"17097","28869","817","815","5173","29000"}

def norm(value):
    text=unicodedata.normalize("NFKD",str(value or "").lower())
    text="".join(c for c in text if not unicodedata.combining(c)).replace("&"," and ")
    text=re.sub(r"['’`]","",text)
    text=re.sub(r"[^a-z0-9]+"," ",text)
    return re.sub(r"\s+"," ",text).strip()

def toks(value):
    return [t for t in norm(value).split() if t not in GENERIC]

def variants(hotel):
    values=[hotel.get("name","")]+list(hotel.get("aliases") or [])
    return [(v,toks(v)) for v in values if toks(v)]

def candidate_score(source_name, hotel):
    st=toks(source_name); ss=set(st)
    if not st: return None
    best=None
    for raw,tt in variants(hotel):
        ts=set(tt)
        per=[max(fuzz.ratio(x,y) for y in tt) for x in st] if tt else []
        minimum=min(per) if per else 0.0
        average=sum(per)/len(per) if per else 0.0
        if ss==ts:
            score=1000+10*len(ss); relation="exact_tokens"
        elif len(ss)>=2 and ss <= ts:
            score=900+10*len(ss)-2*(len(ts)-len(ss)); relation="source_plus_geo"
        elif len(ss)==1 and next(iter(ss)) in ts and len(next(iter(ss)))>=5:
            score=850-3*(len(ts)-1); relation="one_token_plus_geo"
        elif len(ss)>=2 and minimum>=77 and average>=88:
            score=800+average; relation="near_spelling"
        else:
            continue
        row=(score,relation,raw,round(minimum,3),round(average,3))
        if best is None or row[0]>best[0]:
            best=row
    return best

def source_date(doc, fallback):
    criteria=doc.get("criteria") or {}
    return criteria.get("date") or criteria.get("date_from") or fallback

def tourvisor_hotels(doc):
    return ((doc.get("providers") or {}).get("tourvisor") or {}).get("hotels") or []

def build(residual_rows, snapshots):
    physical=[r for r in residual_rows if str(r["anex_hotel_id"]) not in PRODUCT_IDS]
    by_date=collections.defaultdict(dict)
    provenance=collections.defaultdict(list)
    for label,doc in snapshots:
        date=source_date(doc,label)
        for h in tourvisor_hotels(doc):
            by_date[date][str(h["id"])]=h
        provenance[date].append(label)

    matches=[]
    for row in physical:
        per_date=[]
        for date,hotels in sorted(by_date.items()):
            scored=[]
            for hid,h in hotels.items():
                c=candidate_score(row["hotel_name"],h)
                if c:
                    scored.append((c[0],hid,h,c))
            scored.sort(key=lambda x:(-x[0],x[1]))
            if not scored: continue
            top=scored[0]
            second=scored[1] if len(scored)>1 else None
            per_date.append({
                "date":date,"tourvisor_hotel_id":top[1],"tourvisor_name":top[2]["name"],
                "relation":top[3][1],"matched_variant":top[3][2],
                "score":round(float(top[0]),3),
                "margin_to_second":round(float(top[0]-(second[0] if second else 0)),3),
            })
        counts=collections.Counter(x["tourvisor_hotel_id"] for x in per_date)
        if not counts: continue
        tv_id,support=counts.most_common(1)[0]
        support_rows=[x for x in per_date if x["tourvisor_hotel_id"]==tv_id]
        matches.append({
            "anex_hotel_id":str(row["anex_hotel_id"]),"anex_name":row["hotel_name"],
            "search_count":int(row["search_count"]),"tourvisor_hotel_id":tv_id,
            "tourvisor_name":support_rows[0]["tourvisor_name"],"unique_date_support":support,
            "support_dates":[x["date"] for x in support_rows],
            "relations":sorted(set(x["relation"] for x in support_rows)),
        })

    stable=[r for r in matches if r["unique_date_support"]>=2]
    targets=collections.defaultdict(list)
    for r in stable: targets[r["tourvisor_hotel_id"]].append(r["anex_hotel_id"])
    for r in stable:
        if len(targets[r["tourvisor_hotel_id"]])>1:
            r["status"]="duplicate_provider_family_hold"
            r["duplicate_anex_ids"]=sorted(targets[r["tourvisor_hotel_id"]])
        else:
            r["status"]="repeatable_saved_tv_candidate_only"

    collision=[]
    for row in physical:
        if str(row["anex_hotel_id"])=="32880":
            emir_dates=[d for d,hotels in by_date.items() if "17444" in hotels]
            emin_dates=[d for d,hotels in by_date.items() if "17443" in hotels]
            collision.append({
                "anex_hotel_id":"32880","anex_name":row["hotel_name"],
                "accepted_candidate_tv_id":"17443","accepted_candidate_name":"GRAND EMIN HOTEL LALELI",
                "exact_saved_dates":sorted(emin_dates),
                "collision_tv_id":"17444","collision_name":"GRAND EMIR",
                "collision_dates":sorted(emir_dates),
                "status":"name_collision_hold_for_recurrence_only",
                "reason":"EMIN and EMIR are distinct meaningful tokens; higher recurrence cannot override exact EMIN evidence",
            })

    single=[r for r in matches if r["unique_date_support"]==1]
    return {
        "schema":"hotel-match-saved-tourvisor-saturation/1",
        "status":"prepared_only_not_write_authority",
        "counts":{
            "snapshot_unique_dates":len(by_date),
            "physical_residual_ids":len(physical),
            "physical_residual_occurrences":sum(int(r["search_count"]) for r in physical),
            "any_saved_tv_candidate_ids":len(matches),
            "any_saved_tv_candidate_occurrences":sum(r["search_count"] for r in matches),
            "repeatable_same_tv_id_ids":len(stable),
            "repeatable_same_tv_id_occurrences":sum(r["search_count"] for r in stable),
            "repeatable_unambiguous_ids":sum(r["status"]=="repeatable_saved_tv_candidate_only" for r in stable),
            "repeatable_unambiguous_occurrences":sum(r["search_count"] for r in stable if r["status"]=="repeatable_saved_tv_candidate_only"),
            "duplicate_family_ids":sum(r["status"]=="duplicate_provider_family_hold" for r in stable),
        },
        "snapshot_provenance_by_date":dict(sorted(provenance.items())),
        "repeatable_rows":sorted(stable,key=lambda r:(-r["search_count"],r["anex_hotel_id"])),
        "single_date_rows":sorted(single,key=lambda r:(-r["search_count"],r["anex_hotel_id"])),
        "collision_dossiers":collision,
        "policy":{
            "saved_data_only":True,"network_calls":0,"database_writes":0,"mapping_writes":0,
            "unique_dates_not_artifact_count":True,
            "repeatability_is_prioritization_not_identity":True,
            "duplicate_provider_ids_same_tv_target":"hold",
            "hotelcode_still_required":"operator/card→ANEX hotelCode or equivalent independent direct identity evidence before mapping",
            "future_write":"NEW CURRENT guarded transaction + preservation guards + per-row post-COMMIT readback",
        },
    }

def sha(obj):
    return hashlib.sha256(json.dumps(obj,ensure_ascii=False,sort_keys=True,separators=(",",":")).encode()).hexdigest()

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument("residual")
    ap.add_argument("snapshots", nargs="+")
    ap.add_argument("--output",required=True)
    args=ap.parse_args()
    residual=json.load(open(args.residual,encoding="utf-8"))
    rows=residual.get("rows",residual)
    snaps=[(Path(p).name,json.load(open(p,encoding="utf-8"))) for p in args.snapshots]
    out=build(rows,snaps)
    out["result_sha256"]=sha(out)
    Path(args.output).write_text(json.dumps(out,ensure_ascii=False,sort_keys=True,indent=2)+"\n",encoding="utf-8")
if __name__=="__main__": main()
