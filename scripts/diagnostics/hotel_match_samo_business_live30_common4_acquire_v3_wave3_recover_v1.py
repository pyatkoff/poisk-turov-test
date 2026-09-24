#!/usr/bin/env python3
import hashlib,json,os,pathlib,re,sys
from collections import defaultdict,Counter

OP="hotel-match-samo-business-live30-common4-acquire-v3-wave3-recover-1971-20260924-v1"
SEALED_OP="hotel-match-samo-business-live30-common4-acquire-1971-20260924-v3-wave3"
SEALED_SCRIPT="hotel_match_samo_business_live30_common4_acquire_v3_wave3.php"
PLAN_OP="hotel-match-samo-business-live30-common4-plan-1971-20260924-v2"
EXPECTED_ROWS=1887
EXPECTED_READY=1753
GLOBAL_GROUPS=502
GROUP_START=201
GROUP_END=300
OPS={5:"operator_5",115:"operator_115",315:"operator_315",342:"operator_342"}

def need(v,why):
    if not v: raise RuntimeError(why)

def load(p):
    return json.loads(pathlib.Path(p).read_text())

def save_new(p,obj):
    p=pathlib.Path(p); raw=(json.dumps(obj,ensure_ascii=False,separators=(",",":"),sort_keys=True)+"\n").encode()
    fd=os.open(p,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,"wb") as f:
        f.write(raw);f.flush();os.fsync(f.fileno())
    return hashlib.sha256(raw).hexdigest()

def pos(v):
    s=str(v).strip()
    return s if re.fullmatch(r"[1-9][0-9]{0,21}",s) else None

def build_plan(plan):
    need(plan.get("operation")==PLAN_OP and plan.get("state")=="samo_business_live30_common4_plan_ready","plan_state")
    need(plan.get("catalog_live30_count")==1887 and plan.get("acquisition_ready_count")==1753,"plan_counts")
    need(plan.get("saved_catalog_ready_count")==1882 and plan.get("saved_catalog_not_ready_count")==5,"plan_saved_counts")
    need(plan.get("database_writes")==plan.get("mapping_writes")==0 and plan.get("safe_to_write_now") is False,"plan_write_zero")
    rows=plan.get("rows");need(isinstance(rows,list) and len(rows)==EXPECTED_ROWS,"plan_rows")
    out=[];edges=0
    for r in rows:
        if r.get("acquisition_ready") is not True: continue
        cid=pos(r.get("andromeda_catalog_id"));state=int(r.get("saved_stateinc") or 0);missing=r.get("missing_operator_ids")
        need(cid and state>0 and isinstance(missing,list) and missing,"ready_row")
        ops=sorted({int(x) for x in missing});need(all(x in OPS for x in ops),"ready_ops")
        out.append({"catalog_id":cid,"stateinc":state,"missing_operator_ids":ops});edges+=len(ops)
    need(len(out)==EXPECTED_READY and edges==5988,"ready_counts")
    return out

def chunks(ids):
    ids=sorted({str(x) for x in ids},key=int);out=[];cur=[]
    for x in ids:
        need(pos(x) is not None,"chunk_id");cand=cur+[x]
        if cur and (len(cand)>12 or len(",".join(cand).encode())>300):
            out.append(cur);cur=[x]
        else:cur=cand
        need(len(cur)<=12 and len(",".join(cur).encode())<=300,"chunk_guard")
    if cur:out.append(cur)
    return out

def build_groups(rows):
    buckets=defaultdict(set)
    for r in rows:
        for op in r["missing_operator_ids"]:buckets[(r["stateinc"],op)].add(r["catalog_id"])
    groups=[]
    for (state,op),ids in buckets.items():
        for c in chunks(ids):groups.append({"stateinc":state,"operator_id":op,"hotel_ids":c})
    groups.sort(key=lambda g:(g["stateinc"],g["operator_id"],int(g["hotel_ids"][0])))
    need(len(groups)==GLOBAL_GROUPS,"group_count")
    return groups

def process_alive():
    proc=pathlib.Path("/proc")
    if not proc.is_dir():return False
    for p in proc.iterdir():
        if not p.name.isdigit():continue
        try:raw=(p/"cmdline").read_bytes().replace(b"\0",b" ").decode("utf-8","ignore")
        except Exception:continue
        if SEALED_OP in raw and SEALED_SCRIPT in raw:return True
    return False

def bridge(row,operator,requested):
    if int(row.get("operatorKey") or 0)!=operator:return ("other_operator",None,None)
    hotel=pos(row.get("hotelKey"));flag=str(row.get("isOperatorHotelKey",""))
    original=row.get("original") if isinstance(row.get("original"),dict) else {}
    native=pos(original.get("hotelKey"))
    if hotel and hotel in requested and flag=="0":
        if native:return ("exact_catalog_to_native",hotel,native)
        return ("catalog_only",hotel,None)
    if flag=="1" and hotel:return ("unbound_operator_native",None,hotel)
    return ("unbound",None,None)

def recover(plan_path,sealed_dir,op_dir,source_sha):
    rows=build_plan(load(plan_path));all_groups=build_groups(rows);groups=all_groups[GROUP_START-1:GROUP_END]
    need(len(groups)==100,"wave_group_count")
    expected_edges=sum(len(g["hotel_ids"]) for g in groups)
    sealed=pathlib.Path(sealed_dir);opdir=pathlib.Path(op_dir)
    need(sealed.is_dir() and sealed.name==SEALED_OP,"sealed_dir")
    need(opdir.is_dir() and opdir.name==OP,"op_dir")
    need(not process_alive(),"sealed_provider_process_still_alive")
    reservation=load(opdir/"reservation.json");need(reservation.get("state")=="reserved_before_recovery","reservation")
    need((sealed/"reservation.json").is_file(),"sealed_reservation")
    orig=load(sealed/"reservation.json");need(orig.get("operation")==SEALED_OP and orig.get("state")=="reserved_before_provider","sealed_reservation_state")

    batch_res=defaultdict(set)
    for p in sealed.glob("batch-*-page-*-reserved.json"):
        m=re.fullmatch(r"batch-(\d+)-page-(\d+)-reserved\.json",p.name)
        if not m:continue
        b=int(m.group(1));pg=int(m.group(2))
        if GROUP_START<=b<=GROUP_END:batch_res[b].add(pg)
    evidence=defaultdict(dict);evidence_dir=sealed/"evidence-private"
    if evidence_dir.is_dir():
        for p in evidence_dir.glob("batch-*-page-*.json"):
            m=re.fullmatch(r"batch-(\d+)-page-(\d+)\.json",p.name)
            if not m:continue
            b=int(m.group(1));pg=int(m.group(2))
            if GROUP_START<=b<=GROUP_END:evidence[b][pg]=p
    http_res=len(list(sealed.glob("http-*-reserved.json")))
    attempted=sorted(set(batch_res)|set(evidence))
    if attempted:
        need(attempted==list(range(GROUP_START,max(attempted)+1)),"attempted_prefix_not_contiguous")
    max_attempt=max(attempted,default=0)

    states=[];edges=[];completed=partial=untouched=0
    for offset,g in enumerate(groups):
        idx=GROUP_START+offset;requested=set(g["hotel_ids"])
        if idx>max_attempt:
            untouched+=1
            for cid in g["hotel_ids"]:
                edges.append({"batch":idx,"catalog_id":cid,"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"state":"not_attempted","positive_native_candidates":[],"safe_to_write_now":False})
            states.append({"batch":idx,"state":"not_attempted","pages_saved":0,"pages_count":None});continue
        pages=evidence.get(idx,{})
        complete=False;pc=None
        if 1 in pages:
            first=load(pages[1]);pc=int(first.get("PAGES_COUNT",-1));need(0<=pc<=1000,"pages_count")
            required=[1] if pc==0 else list(range(1,pc+1));complete=all(pg in pages for pg in required)
        if not complete:
            partial+=1
            for cid in g["hotel_ids"]:
                edges.append({"batch":idx,"catalog_id":cid,"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"state":"partial_unresolved_no_replay","positive_native_candidates":[],"safe_to_write_now":False})
            states.append({"batch":idx,"state":"partial_unresolved_no_replay","pages_saved":len(pages),"pages_count":pc});continue

        completed+=1;flags={cid:{"returned":False,"native":set(),"price_rows":0} for cid in g["hotel_ids"]}
        required=[1] if pc==0 else list(range(1,pc+1))
        for pg in required:
            reply=load(pages[pg]);need(int(reply.get("PAGES_COUNT",-1))==pc,"pages_count_drift")
            for price in reply.get("PRICES",[]) or []:
                if not isinstance(price,dict):continue
                st,cid,native=bridge(price,g["operator_id"],requested)
                if cid in flags:
                    flags[cid]["price_rows"]+=1
                    if st=="exact_catalog_to_native":flags[cid]["returned"]=True;flags[cid]["native"].add(native)
                    elif st=="catalog_only":flags[cid]["returned"]=True
        for cid in g["hotel_ids"]:
            f=flags[cid];ids=sorted(f["native"],key=int)
            st="captured_single_native" if len(ids)==1 else ("captured_ambiguous_native" if len(ids)>1 else ("catalog_only" if f["returned"] else "not_returned_in_context"))
            edges.append({"batch":idx,"catalog_id":cid,"operator_id":g["operator_id"],"namespace":OPS[g["operator_id"]],"state":st,"positive_native_candidates":ids,"price_rows":f["price_rows"],"safe_to_write_now":False})
        states.append({"batch":idx,"state":"fully_drained","pages_saved":len(pages),"pages_count":pc})

    need(len(edges)==expected_edges,"selected_edge_total")
    continuation=next((i for i in range(GROUP_START,GROUP_END+1) if i>max_attempt),None)
    counts=Counter(e["state"] for e in edges);byop=defaultdict(Counter);native_targets=defaultdict(set)
    for e in edges:
        byop[str(e["operator_id"])][e["state"]]+=1
        if e["state"]=="captured_single_native":native_targets[e["namespace"]+"|"+e["positive_native_candidates"][0]].add(e["catalog_id"])
    unique=sum(1 for v in native_targets.values() if len(v)==1);collide=sum(1 for v in native_targets.values() if len(v)>1)
    out={"operation":OP,"state":"completed_read_only_wave_recovery","source_sha":source_sha,"sealed_operation":SEALED_OP,
         "plan_operation":PLAN_OP,"plan_result_sha256":hashlib.sha256(pathlib.Path(plan_path).read_bytes()).hexdigest(),
         "global_group_count":GLOBAL_GROUPS,"group_start":GROUP_START,"group_end":GROUP_END,"expected_selected_edges":expected_edges,
         "http_reservation_count":http_res,"attempted_group_count":max(0,max_attempt-GROUP_START+1),"completed_group_count":completed,
         "partial_group_count":partial,"untouched_group_count":untouched,"continuation_start_group":continuation,
         "edge_state_counts":dict(sorted(counts.items())),"operator_state_counts":{k:dict(sorted(v.items())) for k,v in sorted(byop.items())},
         "single_native_source_unique_count":unique,"single_native_source_collision_count":collide,
         "batches":states,"edges":edges,"provider_http_calls":0,"tourvisor_calls":0,"samo_calls":0,"anex_calls":0,"andromeda_calls":0,
         "database_writes":0,"mapping_writes":0,"safe_to_write_now":False}
    h=save_new(opdir/"result.json",out)
    save_new(opdir/"receipt.json",{"operation":OP,"state":out["state"],"result_sha256":h,"readback_verified":hashlib.sha256((opdir/"result.json").read_bytes()).hexdigest()==h,"provider_accessed":False,"provider_http_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True})
    print(json.dumps({k:out[k] for k in ["state","expected_selected_edges","http_reservation_count","attempted_group_count","completed_group_count","partial_group_count","untouched_group_count","continuation_start_group","edge_state_counts","operator_state_counts","single_native_source_unique_count","single_native_source_collision_count"]},sort_keys=True))
    return 0

def self_test():
    req={"10"}
    need(bridge({"operatorKey":315,"hotelKey":"10","isOperatorHotelKey":"0","original":{"hotel":"X","hotelKey":"99","tourKey":7}},315,req)==("exact_catalog_to_native","10","99"),"correct_bridge")
    need(bridge({"operatorKey":315,"hotelKey":"10","isOperatorHotelKey":"0","original":{"hotel":"X","tourKey":7}},315,req)==("catalog_only","10",None),"catalog_only")
    need(bridge({"operatorKey":115,"hotelKey":"10","isOperatorHotelKey":"0","original":{"hotelKey":"99"}},315,req)==("other_operator",None,None),"operator_guard")
    cc=chunks([str(100000000000000000+i) for i in range(25)])
    need(len(cc)==3 and all(len(x)<=12 and len(",".join(x).encode())<=300 for x in cc),"chunks")
    print("MATCH_SAMO_BUSINESS_LIVE30_WAVE3_RECOVER_V1_SELFTEST_OK")

if __name__=="__main__":
    if "--self-test" in sys.argv:self_test();sys.exit(0)
    need(len(sys.argv)==2 and sys.argv[1]=="--execute","disabled")
    root=pathlib.Path(os.environ["ANYTOUR_ROOT"]);opdir=pathlib.Path(os.environ["MATCH_OPERATION_DIR"]);sealed=pathlib.Path(os.environ["MATCH_SEALED_OPERATION_DIR"]);plan=pathlib.Path(os.environ["MATCH_PLAN_RESULT"]);sha=os.environ["MATCH_SOURCE_SHA"]
    need(root.is_dir() and root.name=="anytoour.ru" and re.fullmatch(r"[0-9a-f]{40}",sha),"runtime_scope")
    try:sys.exit(recover(plan,sealed,opdir,sha))
    except Exception as e:
        msg=re.sub(r"[^A-Za-z0-9_.:-]+","_",str(e))[:180]
        if opdir.is_dir() and not (opdir/"result.json").exists():
            fail={"operation":OP,"state":"failed_read_only_wave_recovery","reason":msg,"provider_http_calls":0,"database_writes":0,"mapping_writes":0}
            h=save_new(opdir/"result.json",fail);save_new(opdir/"receipt.json",{"operation":OP,"state":fail["state"],"result_sha256":h,"readback_verified":True,"provider_accessed":False,"provider_http_calls":0,"database_writes":0,"mapping_writes":0,"no_replay":True})
        print(msg,file=sys.stderr);sys.exit(2)
