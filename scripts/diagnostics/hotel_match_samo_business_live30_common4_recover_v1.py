#!/usr/bin/env python3
import hashlib,json,os,pathlib,re,sys
from collections import defaultdict,Counter

OP="hotel-match-samo-business-live30-common4-recover-1971-20260924-v1"
SEALED_OP="hotel-match-samo-business-live30-common4-acquire-1971-20260924-v2"
PLAN_OP="hotel-match-samo-business-live30-common4-plan-1971-20260924-v2"
EXPECTED_ROWS=1887
EXPECTED_READY=1753
EXPECTED_EDGES=5988
EXPECTED_GROUPS=502
OPS={5:"operator_5",115:"operator_115",315:"operator_315",342:"operator_342"}

def need(v,why):
    if not v: raise RuntimeError(why)

def load(p):
    return json.loads(pathlib.Path(p).read_text())

def save_new(p,obj):
    p=pathlib.Path(p); raw=(json.dumps(obj,ensure_ascii=False,separators=(",",":"),sort_keys=True)+"\n").encode()
    fd=os.open(p,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,"wb") as f:
        f.write(raw); f.flush(); os.fsync(f.fileno())
    return hashlib.sha256(raw).hexdigest()

def pos(v):
    s=str(v).strip()
    return s if re.fullmatch(r"[1-9][0-9]{0,21}",s) else None

def build_plan(plan):
    need(plan.get("operation")==PLAN_OP and plan.get("state")=="samo_business_live30_common4_plan_ready","plan_state")
    need(plan.get("catalog_live30_count")==1887 and plan.get("acquisition_ready_count")==1753,"plan_counts")
    need(plan.get("saved_catalog_ready_count")==1882 and plan.get("saved_catalog_not_ready_count")==5,"plan_saved_counts")
    need(plan.get("provider_http_calls")==plan.get("tourvisor_calls")==plan.get("samo_calls")==plan.get("anex_calls")==plan.get("andromeda_calls")==0,"plan_provider_zero")
    need(plan.get("database_writes")==plan.get("mapping_writes")==0 and plan.get("safe_to_write_now") is False,"plan_write_zero")
    rows=plan.get("rows"); need(isinstance(rows,list) and len(rows)==EXPECTED_ROWS,"plan_rows")
    ready=[]; edge_count=0
    for r in rows:
        if r.get("acquisition_ready") is not True: continue
        cid=pos(r.get("andromeda_catalog_id")); state=int(r.get("saved_stateinc") or 0); missing=r.get("missing_operator_ids")
        need(cid and state>0 and isinstance(missing,list) and missing,"ready_row")
        ops=sorted({int(x) for x in missing}); need(all(x in OPS for x in ops),"ready_ops")
        ready.append({"catalog_id":cid,"stateinc":state,"missing_operator_ids":ops})
        edge_count+=len(ops)
    need(len(ready)==EXPECTED_READY and edge_count==EXPECTED_EDGES,"ready_counts")
    return ready

def chunks(ids):
    ids=sorted({str(x) for x in ids},key=int); out=[]; cur=[]
    for x in ids:
        need(pos(x) is not None,"chunk_id")
        cand=cur+[x]
        if cur and (len(cand)>12 or len(",".join(cand).encode())>300):
            out.append(cur); cur=[x]
        else: cur=cand
        need(len(cur)<=12 and len(",".join(cur).encode())<=300,"chunk_guard")
    if cur: out.append(cur)
    return out

def build_groups(rows):
    buckets=defaultdict(set)
    for r in rows:
        for op in r["missing_operator_ids"]:
            buckets[(r["stateinc"],op)].add(r["catalog_id"])
    groups=[]
    for (state,op),ids in buckets.items():
        for c in chunks(ids):
            groups.append({"stateinc":state,"operator_id":op,"hotel_ids":c})
    groups.sort(key=lambda g:(g["stateinc"],g["operator_id"],int(g["hotel_ids"][0])))
    need(len(groups)==EXPECTED_GROUPS,"group_count")
    return groups

def process_alive():
    proc=pathlib.Path("/proc")
    if not proc.is_dir(): return False
    for p in proc.iterdir():
        if not p.name.isdigit(): continue
        try: raw=(p/"cmdline").read_bytes().replace(b"\0",b" ").decode("utf-8","ignore")
        except Exception: continue
        if SEALED_OP in raw and "hotel_match_samo_business_live30_common4_acquire_v2.php" in raw:
            return True
    return False

def bridge(row,operator,requested):
    if int(row.get("operatorKey") or 0)!=operator:return ("other_operator",None,None)
    hotel=pos(row.get("hotelKey")); flag=str(row.get("isOperatorHotelKey",""))
    original=row.get("original") if isinstance(row.get("original"),dict) else {}
    oh=pos(original.get("hotelKey")); oo=int(original.get("operatorKey") or 0)
    if hotel and hotel in requested and flag=="0":
        if oh and oo==operator:return ("exact_catalog_to_native",hotel,oh)
        return ("catalog_only",hotel,None)
    if flag=="1" and hotel:return ("unbound_operator_native",None,hotel)
    return ("unbound",None,None)

def recover(plan_path,sealed_dir,op_dir,source_sha):
    plan=load(plan_path); rows=build_plan(plan); groups=build_groups(rows)
    sealed=pathlib.Path(sealed_dir); opdir=pathlib.Path(op_dir)
    need(sealed.is_dir() and sealed.name==SEALED_OP,"sealed_dir")
    need(opdir.is_dir() and opdir.name==OP,"op_dir")
    need(not process_alive(),"sealed_provider_process_still_alive")
    reservation=load(opdir/"reservation.json"); need(reservation.get("state")=="reserved_before_recovery","reservation")
    # Original operation must remain non-terminal at wrapper level.
    need((sealed/"reservation.json").is_file(),"sealed_reservation")
    orig=load(sealed/"reservation.json"); need(orig.get("operation")==SEALED_OP and orig.get("state")=="reserved_before_provider","sealed_reservation_state")

    batch_res=defaultdict(set)
    for p in sealed.glob("batch-*-page-*-reserved.json"):
        m=re.fullmatch(r"batch-(\d+)-page-(\d+)-reserved\.json",p.name)
        if not m: continue
        b=int(m.group(1)); pg=int(m.group(2)); need(1<=b<=EXPECTED_GROUPS and pg>=1,"batch_reservation_range")
        batch_res[b].add(pg)
    http_res=list(sealed.glob("http-*-reserved.json"))
    evidence_dir=sealed/"evidence-private"
    evidence=defaultdict(dict)
    if evidence_dir.is_dir():
        for p in evidence_dir.glob("batch-*-page-*.json"):
            m=re.fullmatch(r"batch-(\d+)-page-(\d+)\.json",p.name)
            if not m: continue
            b=int(m.group(1));pg=int(m.group(2));need(1<=b<=EXPECTED_GROUPS and pg>=1,"evidence_range")
            evidence[b][pg]=p

    attempted=sorted(set(batch_res)|set(evidence))
    if attempted:
        need(attempted==list(range(1,max(attempted)+1)),"attempted_prefix_not_contiguous")
    max_attempt=max(attempted,default=0)
    states=[]; recovered_edges=[]; completed=partial=untouched=0
    for idx,g in enumerate(groups,1):
        req=set(g["hotel_ids"])
        if idx>max_attempt:
            state="not_attempted";untouched+=1
            for cid in g["hotel_ids"]:
                recovered_edges.append({"batch":idx,"catalog_id":cid,"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"state":"not_attempted","positive_native_candidates":[],"safe_to_write_now":False})
            states.append({"batch":idx,"state":state,"pages_saved":0,"pages_count":None});continue
        pages=evidence.get(idx,{})
        complete=False; pc=None
        if pages and 1 in pages:
            first=load(pages[1]); pc=int(first.get("PAGES_COUNT",-1))
            need(0<=pc<=1000,"pages_count")
            required=[1] if pc==0 else list(range(1,pc+1))
            complete=all(pg in pages for pg in required)
        if not complete:
            partial+=1
            for cid in g["hotel_ids"]:
                recovered_edges.append({"batch":idx,"catalog_id":cid,"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"state":"partial_unresolved_no_replay","positive_native_candidates":[],"safe_to_write_now":False})
            states.append({"batch":idx,"state":"partial_unresolved_no_replay","pages_saved":len(pages),"pages_count":pc});continue
        completed+=1
        flags={cid:{"returned":False,"native":set(),"price_rows":0} for cid in g["hotel_ids"]}
        required=[1] if pc==0 else list(range(1,pc+1))
        for pg in required:
            reply=load(pages[pg])
            need(int(reply.get("PAGES_COUNT",-1))==pc,"pages_count_drift")
            for price in reply.get("PRICES",[]) or []:
                if not isinstance(price,dict):continue
                st,cid,native=bridge(price,g["operator_id"],req)
                if cid in flags:
                    flags[cid]["price_rows"]+=1
                    if st=="exact_catalog_to_native":flags[cid]["returned"]=True;flags[cid]["native"].add(native)
                    elif st=="catalog_only":flags[cid]["returned"]=True
        for cid in g["hotel_ids"]:
            f=flags[cid]; ids=sorted(f["native"],key=int)
            st="captured_single_native" if len(ids)==1 else ("captured_ambiguous_native" if len(ids)>1 else ("catalog_only" if f["returned"] else "not_returned_in_context"))
            recovered_edges.append({"batch":idx,"catalog_id":cid,"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"state":st,"positive_native_candidates":ids,"price_rows":f["price_rows"],"safe_to_write_now":False})
        states.append({"batch":idx,"state":"fully_drained","pages_saved":len(pages),"pages_count":pc})
    need(len(recovered_edges)==EXPECTED_EDGES,"recovered_edge_total")
    continuation=next((i for i in range(1,EXPECTED_GROUPS+1) if i>max_attempt),None)
    counts=Counter(e["state"] for e in recovered_edges)
    out={"operation":OP,"state":"completed_read_only_recovery","source_sha":source_sha,"sealed_operation":SEALED_OP,
         "plan_operation":PLAN_OP,"plan_result_sha256":hashlib.sha256(pathlib.Path(plan_path).read_bytes()).hexdigest(),
         "expected_groups":EXPECTED_GROUPS,"expected_edges":EXPECTED_EDGES,"http_reservation_count":len(http_res),
         "attempted_batch_count":max_attempt,"completed_batch_count":completed,"partial_batch_count":partial,
         "untouched_batch_count":untouched,"continuation_start_batch":continuation,
         "edge_state_counts":dict(sorted(counts.items())),"batches":states,"edges":recovered_edges,
         "provider_http_calls":0,"tourvisor_calls":0,"samo_calls":0,"anex_calls":0,"andromeda_calls":0,
         "database_writes":0,"mapping_writes":0,"safe_to_write_now":False}
    h=save_new(opdir/"result.json",out)
    save_new(opdir/"receipt.json",{"operation":OP,"state":out["state"],"result_sha256":h,"readback_verified":hashlib.sha256((opdir/"result.json").read_bytes()).hexdigest()==h,"provider_accessed":False,"provider_http_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True})
    print(json.dumps({k:out[k] for k in ["state","http_reservation_count","attempted_batch_count","completed_batch_count","partial_batch_count","untouched_batch_count","continuation_start_batch","edge_state_counts"]},sort_keys=True))
    return 0

def self_test():
    ids=[str(100000000000000000+i) for i in range(25)]
    cc=chunks(ids);need(len(cc)==3 and all(len(x)<=12 and len(",".join(x).encode())<=300 for x in cc),"chunks")
    req={"10"};need(bridge({"operatorKey":5,"hotelKey":"10","isOperatorHotelKey":"0","original":{"hotelKey":"99","operatorKey":5}},5,req)==("exact_catalog_to_native","10","99"),"bridge")
    print("MATCH_SAMO_BUSINESS_LIVE30_RECOVER_V1_SELFTEST_OK")

if __name__=="__main__":
    if "--self-test" in sys.argv:self_test();sys.exit(0)
    need(len(sys.argv)==2 and sys.argv[1]=="--execute","disabled")
    root=pathlib.Path(os.environ["ANYTOUR_ROOT"]);opdir=pathlib.Path(os.environ["MATCH_OPERATION_DIR"]);sealed=pathlib.Path(os.environ["MATCH_SEALED_OPERATION_DIR"]);plan=pathlib.Path(os.environ["MATCH_PLAN_RESULT"]);sha=os.environ["MATCH_SOURCE_SHA"]
    need(root.is_dir() and root.name=="anytoour.ru" and re.fullmatch(r"[0-9a-f]{40}",sha),"runtime_scope")
    try:sys.exit(recover(plan,sealed,opdir,sha))
    except Exception as e:
        msg=re.sub(r"[^A-Za-z0-9_.:-]+","_",str(e))[:180]
        if opdir.is_dir() and not (opdir/"result.json").exists():
            fail={"operation":OP,"state":"failed_read_only_recovery","reason":msg,"provider_http_calls":0,"database_writes":0,"mapping_writes":0}
            h=save_new(opdir/"result.json",fail);save_new(opdir/"receipt.json",{"operation":OP,"state":fail["state"],"result_sha256":h,"readback_verified":True,"provider_accessed":False,"provider_http_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True})
        print(msg,file=sys.stderr);sys.exit(2)
