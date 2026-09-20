#!/usr/bin/env python3
import importlib.util
from pathlib import Path
P=Path(__file__).resolve().parents[1]/"scripts/diagnostics/hotel_match_tourvisor_multi_operator_links.py";s=importlib.util.spec_from_file_location("m",P);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
Q={"rows":[{"proposed_local_id":6319,"country_id":1,"search_count":55}]}
def row(opid,name,link): return {"id":"t1","operator":{"id":opid,"name":name},"operatorLink":link,"hotel":{"id":6319,"name":"APERION BEACH","country":{"id":1},"region":{"name":"Side"},"common":{"latitude":36.7,"longitude":31.56}}}
def test_multi_operator_kept():
 r=m.build(Q,[row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121"),row(7,"FUN&SUN","https://fstravel.com/hotel/123")],"2026-10-05");assert r["capture_count"]==2 and len(r["operator_counts"])==2
def test_no_anex_requirement():
 r=m.build(Q,[row(7,"FUN&SUN","https://fstravel.com/hotel/123")],"2026-10-05");assert r["operator_link_ready"]==1
def test_secret_rejected_but_identity_retained():
 r=m.build(Q,[row(7,"FUN&SUN","https://fstravel.com/hotel/123?token=x")],"2026-10-05");assert r["capture_count"]==1 and r["operator_link_ready"]==0
def test_country_guard():
 x=row(7,"FUN&SUN","https://fstravel.com/hotel/123");x["hotel"]["country"]["id"]=4;r=m.build(Q,[x],"2026-10-05");assert r["capture_count"]==0 and r["rejected"][0]["reason"]=="country_conflict"
def test_ambiguous_links_fail_closed():
 a=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121");b=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=9999");b["id"]="t2";r=m.build(Q,[a,b],"2026-10-05");assert r["capture_count"]==0 and r["ambiguous_operator_targets"]==1
def test_saved_link_survives_listing_before_detail():
 a=row(13,"Anex",None);b=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121");b["id"]="saved-detail"
 b["hotel"]["name"]="DETAIL HOTEL NAME";b["hotel"]["common"]["latitude"]=36.8
 for rows in ([a,b],[b,a]):
  result=m.build(Q,rows,"2026-10-05");capture=result["captures"][0]
  assert result["capture_count"]==result["operator_link_ready"]==1
  assert capture["operator_link"]==b["operatorLink"] and capture["tour_id"]=="saved-detail"
  assert capture["tourvisor_hotel_name"]=="DETAIL HOTEL NAME" and capture["tourvisor_latitude"]==36.8
  assert capture["status"]=="operator_link_ready_for_native_extractor"
def test_rejected_link_does_not_hide_saved_link():
 a=row(13,"Anex","https://agent.anextour.ru/x?token=SECRET");b=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121");b["id"]="safe-detail"
 result=m.build(Q,[a,b],"2026-10-05")
 assert result["operator_link_ready"]==1 and result["captures"][0]["tour_id"]=="safe-detail"
 assert any(x["reason"]=="secret_bearing_operator_link" for x in result["rejected"])
 assert "SECRET" not in m.json.dumps(result)
def test_linkless_group_remains_linkless():
 a=row(13,"Anex",None);b=row(13,"Anex","");b["id"]="t2"
 result=m.build(Q,[a,b],"2026-10-05")
 assert result["capture_count"]==1 and result["operator_link_ready"]==0
 assert result["captures"][0]["tour_id"]=="t1"
def test_missing_row_cannot_clear_multi_link_ambiguity():
 rows=[row(13,"Anex",None),row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121"),row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=9999")]
 result=m.build(Q,rows,"2026-10-05")
 assert result["capture_count"]==0 and result["ambiguous_operator_targets"]==1
 assert sum(x["reason"]=="ambiguous_operator_links" for x in result["rejected"])==3
def test_full_signed_multivalue_link_is_unchanged():
 link="https://agent.anextour.ru/x?HOTELLIST=-1575330%2C41074"
 result=m.build(Q,[row(13,"Anex",None),row(13,"Anex",link)],"2026-10-05")
 assert result["captures"][0]["operator_link"]==link
 assert "native_id" not in result["captures"][0]
def test_biblio_public_reference_is_not_native_identity():
 link="https://www.bgoperator.ru/price.shtml?F4=1026215268%2C102642547792"
 result=m.build(Q,[row(15,"Biblio Globus",None),row(15,"Biblio Globus",link)],"2026-10-05")
 assert result["captures"][0]["operator_link"]==link
 assert result["mapping_writes"]==result["database_writes"]==result["tourvisor_calls"]==0
def test_batch_preserves_all_links_without_cross_hotel_join():
 q={"rows":[]};rows=[]
 for hid in range(1000,2000):
  q["rows"].append({"proposed_local_id":hid,"country_id":1,"search_count":hid})
  for opid in (11,13,15,43):
   a=row(opid,str(opid),None);a["hotel"]["id"]=hid
   b=row(opid,str(opid),"https://example.invalid/hotel/"+str(hid)+"?op="+str(opid));b["hotel"]["id"]=hid;b["id"]=str(hid)+"-"+str(opid)
   rows.extend([a,b])
 result=m.build(q,rows,"2026-10-05")
 assert result["capture_count"]==result["operator_link_ready"]==4000
 for capture in result["captures"]:
  assert capture["tour_id"]==str(capture["local_hotel_id"])+"-"+capture["operator_id"]
 assert result["mapping_writes"]==result["database_writes"]==result["tourvisor_calls"]==0
if __name__=="__main__":
 for n,f in sorted(globals().items()):
  if n.startswith("test_") and callable(f): f();print(n+": PASS")
