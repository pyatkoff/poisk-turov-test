#!/usr/bin/env python3
import argparse, json, math, re, sys, unicodedata
from collections import Counter, defaultdict

CORE8={"egypt","turkey","thailand","uae","vietnam","srilanka","maldives","cuba"}
GENERIC={"hotel","hotels","resort","resorts","spa","the","by","otel","отель","отели","гостиница"}
SIGNIFICANT={"beach","garden","annex","north","south","east","west","palace","club","village","waterpark","aqua"}
COUNTRIES={
 "egypt":{"египет","egypt"},"turkey":{"турция","turkey","turkiye","türkiye"},"thailand":{"таиланд","thailand"},
 "uae":{"оаэ","united arab emirates","uae","объединенные арабские эмираты"},"vietnam":{"вьетнам","vietnam","viet nam"},
 "srilanka":{"шри ланка","sri lanka"},"maldives":{"мальдивы","maldives"},"cuba":{"куба","cuba"}}
FORMER_RE=re.compile(r"\(\s*(?:ex|ех|formerly|former|быв)\s*[.:\-]?\s*([^)]*)\)",re.I)

def text(v):
 s=unicodedata.normalize("NFKC",str(v or "")).casefold().replace("ё","е").replace("&"," and ")
 return " ".join(re.findall(r"[\w]+",s,flags=re.UNICODE))
def norm(v):
 xs=[x for x in text(v).split() if x not in GENERIC]
 return " ".join(sorted(xs))
def forms(v):
 s=str(v or ""); vals=[s,FORMER_RE.sub(" ",s)]+FORMER_RE.findall(s)
 return {norm(x) for x in vals if norm(x)}
def quality_name(n):
 xs=n.split(); return len(n)>=7 and (len(xs)>=2 or len(n)>=10)
def country(v):
 s=text(v)
 for k,vals in COUNTRIES.items():
  if s in vals:return k
 return None
def point(r):
 a=r.get("latitude",r.get("lat")); b=r.get("longitude",r.get("lon",r.get("lng")))
 try:a=float(a);b=float(b)
 except:return None
 if abs(a)>90 or abs(b)>180 or (a==0 and b==0):return None
 return a,b
def km(a,b):
 x=point(a);y=point(b)
 if not x or not y:return None
 p,q=map(math.radians,[x[0],y[0]]); dl=math.radians(y[1]-x[1]); dp=q-p
 z=math.sin(dp/2)**2+math.cos(p)*math.cos(q)*math.sin(dl/2)**2
 return 12742*math.asin(min(1,math.sqrt(z)))
def geo_forms(vals):
 out=set()
 for v in vals:
  n=norm(v)
  if n and country(v) is None:out.add(n)
 return out
def geo_ok(a,b):
 A=geo_forms(a);B=geo_forms(b)
 if not A or not B:return False
 if A&B:return True
 for x in A:
  X=set(x.split())
  for y in B:
   Y=set(y.split())
   if len(X&Y)>=1 and min(len(X),len(Y))<=2:return True
 return False
def star(v):
 m=re.search(r"(?<!\d)([1-5])(?:\s*\*|\s*stars?|\s*зв)?(?!\d)",str(v or ""),re.I)
 return int(m.group(1)) if m else None
def and_source(row):
 ext=str(row.get("external_hotel_id") or "")
 ss=[s for s in row.get("sources",[]) if str(s.get("id",s.get("hotelKey","")))==ext]
 if len(ss)!=1:return None
 s=ss[0];cc=country(s.get("state")) or row.get("country_class")
 if cc not in CORE8:return None
 return {"names":[s.get("name"),s.get("lName")],"cc":cc,"geo":[s.get("town"),s.get("region")],
         "latitude":s.get("latitude",s.get("lat")),"longitude":s.get("longitude",s.get("lon",s.get("lng"))),"star":s.get("star")}
def main():
 ap=argparse.ArgumentParser();ap.add_argument("--census",required=True);ap.add_argument("--output",required=True);ap.add_argument("--summary",required=True);a=ap.parse_args()
 C=json.load(open(a.census,encoding="utf-8")); assert C.get("status")=="completed"
 locals={int(r["id"]):r for r in C["local"] if r.get("country_class") in CORE8 and int(r.get("is_active",1))==1}
 aliases=defaultdict(set)
 for lid,r in locals.items():aliases[lid]|=forms(r.get("name"))
 for r in C.get("aliases",[]):
  lid=int(r.get("hotel_id") or 0)
  if lid in locals:aliases[lid]|=forms(r.get("alias"))
 idx=defaultdict(lambda:defaultdict(set))
 for lid,ns in aliases.items():
  cc=locals[lid]["country_class"]
  for n in ns:
   if quality_name(n):idx[cc][n].add(lid)
 maps=defaultdict(list);anex_by_local=defaultdict(list)
 for r in C.get("anex_mappings",[]):
  aid=str(r.get("anex_hotel_id")); maps[aid].append(r)
  if int(r.get("enabled") or 0)==1:anex_by_local[int(r["catalog_hotel_id"])].append(aid)
 protected={str(r.get("anex_hotel_id")) for r in C.get("anex_decisions",[])}|{str(r.get("anex_hotel_id")) for r in C.get("anex_exclusions",[])}
 obs=defaultdict(list);freq=Counter()
 for r in C.get("anex_observations",[]):
  aid=str(r.get("anex_hotel_id"));obs[aid].append(r);freq[aid]+=int(r.get("search_count") or 1)
 country_ids={str(k):v for k,v in C.get("country_ids",{}).items()}
 staging={str(r.get("anex_hotel_id")):r for r in C.get("anex",[])}
 anex_src={}
 for aid,r in staging.items():
  cc=country(r.get("api_country"))
  if not cc:
   cs={country_ids.get(str(o.get("country_id") or o.get("anex_country_id") or "")) for o in obs.get(aid,[])}- {None}
   if len(cs)==1:cc=next(iter(cs))
  if cc not in CORE8:continue
  anex_src[aid]={"names":[r.get("api_name"),r.get("xml_name"),r.get("xml_alternate_name")]+[o.get("hotel_name") for o in obs.get(aid,[])],
    "cc":cc,"geo":[r.get("api_town"),r.get("api_region")],"latitude":r.get("latitude"),"longitude":r.get("longitude")}
 accepted_and=defaultdict(list);pending=[]
 for r in C.get("andromeda",[]):
  if r.get("decision_status")=="accepted" and r.get("local_hotel_id") is not None:accepted_and[int(r["local_hotel_id"])].append(r)
  elif r.get("decision_status")=="pending" and r.get("local_hotel_id") is None:pending.append(r)
 cand=[];stats=Counter()
 def unique_local(src):
  ns=set().union(*(forms(v) for v in src["names"] if v))
  hits=set()
  for n in ns:
   if quality_name(n):hits |= idx[src["cc"]].get(n,set())
  return (next(iter(hits)),ns) if len(hits)==1 else (None,ns)
 # unresolved ANEX, strongest first: exact unique + accepted Andromeda bridge OR exact+<=1km+geo
 for aid,s in anex_src.items():
  if aid in maps or aid in protected:continue
  lid,ns=unique_local(s)
  if lid is None:stats["anex_not_unique_exact"]+=1;continue
  d=km(s,locals[lid])
  if d is not None and d>5:stats["coord_conflict"]+=1;continue
  bridges=[]
  for ar in accepted_and.get(lid,[]):
   bs=and_source(ar)
   if not bs or bs["cc"]!=s["cc"]:continue
   bns=set().union(*(forms(v) for v in bs["names"] if v))
   bd=km(bs,locals[lid])
   if ns&bns and (bd is None or bd<=5) and (geo_ok(s["geo"],bs["geo"]) or (d is not None and d<=1)):
    bridges.append(str(ar["external_hotel_id"]))
  rule=None;bridge=None
  if len(set(bridges))==1:rule="exact_unique_plus_accepted_andromeda_bridge";bridge=bridges[0]
  elif d is not None and d<=1 and geo_ok(s["geo"],[locals[lid].get("region_name"),locals[lid].get("subregion_name")]):rule="exact_unique_plus_coord_geo"
  if not rule:stats["anex_needs_extra"]+=1;continue
  cand.append({"provider":"anex","external_id":aid,"local_id":lid,"country":s["cc"],"live_observations":freq[aid],"distance_km":None if d is None else round(d,3),"bridge_id":bridge,"rule":rule})
 # pending Andromeda: exact unique + current enabled ANEX bridge, or exact+<=1km+geo
 for ar in pending:
  s=and_source(ar)
  if not s:stats["and_no_source"]+=1;continue
  lid,ns=unique_local(s)
  if lid is None:stats["and_not_unique_exact"]+=1;continue
  d=km(s,locals[lid])
  if d is not None and d>5:stats["coord_conflict"]+=1;continue
  ls=star(locals[lid].get("category")); ss=star(s.get("star"))
  if ls and ss and abs(ls-ss)>1:stats["star_conflict"]+=1;continue
  bridges=[]
  for aid in anex_by_local.get(lid,[]):
   aS=anex_src.get(aid)
   if not aS or aS["cc"]!=s["cc"]:continue
   ans=set().union(*(forms(v) for v in aS["names"] if v))
   ad=km(aS,locals[lid])
   if ns&ans and (ad is None or ad<=5) and (geo_ok(s["geo"],aS["geo"]) or (d is not None and d<=1)):
    bridges.append(aid)
  rule=None;bridge=None
  if len(set(bridges))==1:rule="exact_unique_plus_enabled_anex_bridge";bridge=bridges[0]
  elif d is not None and d<=1 and geo_ok(s["geo"],[locals[lid].get("region_name"),locals[lid].get("subregion_name")]):rule="exact_unique_plus_coord_geo"
  if not rule:stats["and_needs_extra"]+=1;continue
  cand.append({"provider":"andromeda","external_id":str(ar["external_hotel_id"]),"local_id":lid,"country":s["cc"],"live_observations":0,"distance_km":None if d is None else round(d,3),"bridge_id":bridge,"rule":rule})
 # one provider external id and one local target per provider; collisions are evidence for review, not autoaccept
 group=defaultdict(list)
 for i,r in enumerate(cand):group[(r["provider"],r["local_id"])].append(i)
 collisions={i for ids in group.values() if len(ids)>1 for i in ids}
 safe=[r for i,r in enumerate(cand) if i not in collisions]
 demoted=[dict(r,reason="provider_local_collision") for i,r in enumerate(cand) if i in collisions]
 safe.sort(key=lambda r:(-r["live_observations"],r["provider"],r["country"],r["external_id"]))
 out={"status":"completed","review_id":"hotel-match-current-exact-review-1971-20260912-v2","source_operation_id":C.get("operation_id"),
      "counts":{"safe":len(safe),"anex":sum(r["provider"]=="anex" for r in safe),"andromeda":sum(r["provider"]=="andromeda" for r in safe),"live_safe":sum(r["live_observations"]>0 for r in safe),"collision_demoted":len(demoted)},
      "by_country":dict(Counter(r["country"] for r in safe)),"rule_counts":dict(Counter(r["rule"] for r in safe)),"stats":dict(stats),"candidates":safe,"demoted":demoted,
      "database_writes":0,"mapping_writes":0,"supplier_calls":0,"tourvisor_calls":0}
 with open(a.output,"w",encoding="utf-8") as f:json.dump(out,f,ensure_ascii=False,sort_keys=True,indent=2);f.write("\n")
 c=out["counts"]
 summary=(f"MATCH #1971 fresh CURRENT review v2: prepared **{c['safe']}** guarded exact candidates "
          f"(ANEX **{c['anex']}**, Andromeda **{c['andromeda']}**, live-observed **{c['live_safe']}**), collision-demoted **{c['collision_demoted']}**. "
          f"Countries `{out['by_country']}`; rules `{out['rule_counts']}`. READ-ONLY: DB/mapping writes 0; supplier/Tourvisor calls 0/0.")
 open(a.summary,"w",encoding="utf-8").write(summary+"\n");print(summary)
if __name__=="__main__":
 if "--self-test" in sys.argv:
  assert norm("ABC HOTEL SPA")==norm("ABC RESORT") and norm("ABC BEACH")!=norm("ABC GARDEN")
  assert country("Турция")=="turkey" and country("Россия") is None
  print("MATCH current exact review self-test PASS");sys.exit(0)
 main()
