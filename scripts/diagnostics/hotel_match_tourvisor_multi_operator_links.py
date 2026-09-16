#!/usr/bin/env python3
"""MATCH-only offline adapter for targeted multi-operator Tourvisor evidence.
Consumes saved Tourvisor result/detail rows; performs no network or DB writes.
"""
import argparse,json,re
from datetime import date
from urllib.parse import urlsplit,parse_qsl

CORE8={1,2,4,6,8,9,10,16}
SECRET=re.compile(r"(?:token|jwt|auth|pass|password|secret|key|session|sid|cookie)",re.I)

def _rows(x):
    if isinstance(x,list): return x
    if isinstance(x,dict):
        for k in ("results","rows","tours","data"):
            if isinstance(x.get(k),list): return x[k]
    return []

def _operator(row):
    op=row.get("operator") if isinstance(row.get("operator"),dict) else {}
    oid=op.get("id",row.get("operatorId",row.get("operator_id")))
    name=op.get("name") or op.get("fullName") or op.get("russianName") or row.get("operatorName")
    return (str(oid) if oid not in (None,"") else None, str(name).strip() if name else None)

def _hotel(row):
    h=row.get("hotel") if isinstance(row.get("hotel"),dict) else {}
    hid=h.get("id",row.get("hotelId",row.get("hotel_id")))
    c=h.get("country") if isinstance(h.get("country"),dict) else {}
    cid=c.get("id",row.get("countryId",row.get("country_id")))
    return h,hid,cid

def _safe_link(v):
    if not isinstance(v,str) or not v.strip(): return None,"missing_operator_link"
    try: u=urlsplit(v.strip())
    except Exception: return None,"invalid_operator_link"
    if u.scheme.lower()!="https" or not u.hostname: return None,"invalid_operator_link"
    for k,_ in parse_qsl(u.query,keep_blank_values=True):
        if SECRET.search(k): return None,"secret_bearing_operator_link"
    if len(v)>2048: return None,"oversize_operator_link"
    return v.strip(),None

def build(queue,tv,date_from):
    date.fromisoformat(date_from)
    targets={}
    for q in queue.get("rows",[]):
        try: local=int(q.get("proposed_local_id",q.get("local_hotel_id"))); country=int(q.get("country_id"))
        except Exception: continue
        if country not in CORE8: continue
        targets[local]={"country_id":country,"frequency":int(q.get("search_count",q.get("frequency",0)) or 0),"source":q}
    captures=[]; rejected=[]; by_key={}
    for r in _rows(tv):
        if not isinstance(r,dict): continue
        h,hid,cid=_hotel(r)
        try: local=int(hid); country=int(cid)
        except Exception: continue
        if local not in targets: continue
        if country!=targets[local]["country_id"]:
            rejected.append({"local_hotel_id":local,"reason":"country_conflict"}); continue
        oid,oname=_operator(r)
        if oid is None and oname is None:
            rejected.append({"local_hotel_id":local,"reason":"missing_operator_identity"}); continue
        link=r.get("operatorLink",r.get("operator_link"))
        safe,why=_safe_link(link)
        status="operator_link_ready_for_native_extractor" if safe else "operator_identity_ready_link_missing"
        cap={"local_hotel_id":local,"country_id":country,"live_frequency":targets[local]["frequency"],"tourvisor_hotel_name":h.get("name"),"tour_id":str(r.get("id",r.get("tourId",r.get("tour_id")))) if r.get("id",r.get("tourId",r.get("tour_id"))) not in (None,"") else None,"operator_id":oid,"operator_name":oname,"operator_link":safe,"region":(h.get("region") or {}).get("name") if isinstance(h.get("region"),dict) else None,"subregion":(h.get("subRegion") or {}).get("name") if isinstance(h.get("subRegion"),dict) else None,"tourvisor_latitude":(h.get("common") or {}).get("latitude") if isinstance(h.get("common"),dict) else h.get("latitude"),"tourvisor_longitude":(h.get("common") or {}).get("longitude") if isinstance(h.get("common"),dict) else h.get("longitude"),"date_from":date_from,"status":status}
        key=(local,oid,oname or "")
        by_key.setdefault(key,[]).append(cap)
        if why: rejected.append({"local_hotel_id":local,"operator_id":oid,"operator_name":oname,"reason":why})
    ambiguous=0
    for key,vals in by_key.items():
        links={x["operator_link"] for x in vals if x["operator_link"]}
        if len(links)>1:
            ambiguous+=1
            rejected.extend({"local_hotel_id":x["local_hotel_id"],"operator_id":x["operator_id"],"reason":"ambiguous_operator_links"} for x in vals)
            continue
        captures.append(vals[0])
    captures.sort(key=lambda x:(-x["live_frequency"],x["local_hotel_id"],x["operator_id"] or ""))
    ops={}
    for c in captures:
        k=c["operator_id"] or c["operator_name"] or "unknown"; ops[k]=ops.get(k,0)+1
    return {"schema_version":1,"mode":"targeted_multi_operator","date_from":date_from,"queue_targets":len(targets),"captures":captures,"capture_count":len(captures),"operator_link_ready":sum(c["operator_link"] is not None for c in captures),"operator_counts":ops,"ambiguous_operator_targets":ambiguous,"rejected":rejected,"database_writes":0,"mapping_writes":0,"tourvisor_calls":0}

def main():
    p=argparse.ArgumentParser();p.add_argument("--queue",required=True);p.add_argument("--tourvisor",required=True);p.add_argument("--date",required=True);p.add_argument("--out",required=True);a=p.parse_args()
    with open(a.queue,encoding="utf-8") as f:q=json.load(f)
    with open(a.tourvisor,encoding="utf-8") as f:t=json.load(f)
    out=build(q,t,a.date)
    with open(a.out,"w",encoding="utf-8") as f:json.dump(out,f,ensure_ascii=False,indent=2,sort_keys=True);f.write("\n")
if __name__=="__main__": main()
