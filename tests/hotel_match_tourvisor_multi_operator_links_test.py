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
def test_operator_id_without_label_reuses_named_detail():
 a=row(13,None,None);a["id"]="listing"
 b=row("13","Anex Tour","https://agent.anextour.ru/x?HOTELLIST=8121");b["id"]="saved-detail"
 b["hotel"]["name"]="SAVED DETAIL NAME";b["hotel"]["common"]["latitude"]=36.8
 for rows in ([a,b],[b,a]):
  result=m.build(Q,rows,"2026-10-05")
  assert result["capture_count"]==result["operator_link_ready"]==1
  assert result["operator_counts"]=={"13":1}
  cap=result["captures"][0]
  assert cap["tour_id"]=="saved-detail" and cap["operator_name"]=="Anex Tour"
  assert cap["tourvisor_hotel_name"]=="SAVED DETAIL NAME" and cap["tourvisor_latitude"]==36.8

def test_same_operator_id_label_variants_are_one_edge():
 link="https://agent.anextour.ru/x?HOTELLIST=8121"
 rows=[row(13,"Anex",link),row(13,"ANEX Tour",link)]
 result=m.build(Q,rows,"2026-10-05")
 assert result["capture_count"]==result["operator_link_ready"]==1
 assert result["operator_counts"]=={"13":1}

def test_label_variants_cannot_hide_conflicting_links():
 a=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121")
 b=row(13,"ANEX Tour","https://agent.anextour.ru/x?HOTELLIST=9999")
 for rows in ([a,b],[b,a]):
  result=m.build(Q,rows,"2026-10-05")
  assert result["capture_count"]==result["operator_link_ready"]==0
  assert result["ambiguous_operator_targets"]==1
  assert sum(x["reason"]=="ambiguous_operator_links" for x in result["rejected"])==2

def test_operator_name_does_not_assign_a_missing_id():
 link="https://agent.anextour.ru/x?HOTELLIST=8121"
 rows=[row(None,"Anex",None),row(13,"Anex",link)]
 result=m.build(Q,rows,"2026-10-05")
 assert result["capture_count"]==2 and result["operator_link_ready"]==1
 assert {c["operator_id"] for c in result["captures"]}=={None,"13"}

def test_idless_name_variants_remain_separate():
 link="https://example.invalid/hotel/123"
 result=m.build(Q,[row(None,"Anex",link),row(None,"ANEX Tour",link)],"2026-10-05")
 assert result["capture_count"]==2 and result["ambiguous_operator_targets"]==0

def test_shared_label_never_merges_different_operator_ids():
 link="https://example.invalid/hotel/123"
 result=m.build(Q,[row(13,"Operator",link),row(43,"Operator",link)],"2026-10-05")
 assert result["capture_count"]==2 and result["operator_counts"]=={"13":1,"43":1}

def test_label_variant_secret_rejection_retains_safe_capture():
 a=row(13,None,"https://example.invalid/hotel/123?token=DO_NOT_PERSIST")
 b=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121");b["id"]="safe-capture"
 result=m.build(Q,[a,b],"2026-10-05")
 assert result["capture_count"]==result["operator_link_ready"]==1
 assert result["captures"][0]["tour_id"]=="safe-capture"
 assert any(r["reason"]=="secret_bearing_operator_link" for r in result["rejected"])
 assert "DO_NOT_PERSIST" not in m.json.dumps(result)

def test_label_variants_preserve_signed_and_public_references():
 links=("https://agent.anextour.ru/x?HOTELLIST=-1575330%2C41074", "https://www.bgoperator.ru/price.shtml?F4=102667203663&F4=102610271843")
 for link in links:
  result=m.build(Q,[row(13,None,None),row(13,"Display label",link)],"2026-10-05")
  assert result["capture_count"]==1
  assert result["captures"][0]["operator_link"]==link
  assert "native_id" not in result["captures"][0]

def test_label_variant_batch_has_one_capture_per_hotel_operator():
 q={"rows":[]};rows=[]
 for hid in range(1000,2000):
  q["rows"].append({"local_hotel_id":hid,"country_id":1,"frequency":hid})
  for opid in (11,13,15,43):
   a=row(opid,None,None);a["hotel"]["id"]=hid
   b=row(opid,"Operator "+str(opid),"https://example.invalid/hotel/"+str(hid)+"?op="+str(opid))
   b["hotel"]["id"]=hid;b["id"]=str(hid)+"-"+str(opid)
   rows.extend([a,b])
 before=m.json.dumps([q,rows],sort_keys=True)
 result=m.build(q,rows,"2026-10-05")
 assert result["capture_count"]==result["operator_link_ready"]==4000
 assert len({(c["local_hotel_id"],c["operator_id"]) for c in result["captures"]})==4000
 assert all(c["tour_id"]==str(c["local_hotel_id"])+"-"+c["operator_id"] for c in result["captures"])
 assert result["operator_counts"]=={"11":1000,"13":1000,"15":1000,"43":1000}
 assert result["database_writes"]==result["mapping_writes"]==result["tourvisor_calls"]==0
 assert m.json.dumps([q,rows],sort_keys=True)==before

if __name__=="__main__":
 for n,f in sorted(globals().items()):
  if n.startswith("test_") and callable(f): f();print(n+": PASS")
