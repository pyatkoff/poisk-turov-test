#!/usr/bin/env python3
import importlib.util
from pathlib import Path
P=Path(__file__).resolve().parents[1]/"scripts/diagnostics/hotel_match_tourvisor_multi_operator_links.py"
s=importlib.util.spec_from_file_location("m",P);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
Q={"rows":[{"proposed_local_id":6319,"country_id":1,"search_count":55}]}
def row(opid,name,link): return {"id":"t1","operator":{"id":opid,"name":name},"operatorLink":link,"hotel":{"id":6319,"name":"APERION BEACH","country":{"id":1},"region":{"name":"Side"},"common":{"latitude":36.7,"longitude":31.56}}}
def test_multi_operator_kept():
 r=m.build(Q,[row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121"),row(7,"FUN&SUN","https://fstravel.com/hotel/123")],"2026-10-05");assert r["capture_count"]==2 and len(r["operator_counts"])==2
def test_no_anex_requirement():
 r=m.build(Q,[row(7,"FUN&SUN","https://fstravel.com/hotel/123")],"2026-10-05");assert r["operator_link_ready"]==1
def test_secret_rejected_but_identity_retained():
 r=m.build(Q,[row(7,"FUN&SUN","https://fstravel.com/hotel/123?token=x")],"2026-10-05");assert r["capture_count"]==1 and r["operator_link_ready"]==0 and r["captures"][0]["status"]=="operator_identity_ready_link_missing"
def test_country_guard():
 x=row(7,"FUN&SUN","https://fstravel.com/hotel/123");x["hotel"]["country"]["id"]=4;r=m.build(Q,[x],"2026-10-05");assert r["capture_count"]==0 and r["rejected"][0]["reason"]=="country_conflict"
def test_ambiguous_same_operator_links_fail_closed():
 a=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121");b=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=9999");b["id"]="t2";r=m.build(Q,[a,b],"2026-10-05");assert r["capture_count"]==0 and r["ambiguous_operator_targets"]==1
if __name__=="__main__":
 for n,f in sorted(globals().items()):
  if n.startswith("test_") and callable(f):f();print(n+": PASS")
