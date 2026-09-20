#!/usr/bin/env python3
"""Offline regression of Tourvisor hotel->tours and raw-detail evidence inputs."""
import importlib.util
from pathlib import Path
P=Path(__file__).with_name("hotel_match_tourvisor_multi_operator_links_test.py")
s=importlib.util.spec_from_file_location("saved_link_base_tests",P)
base=importlib.util.module_from_spec(s);s.loader.exec_module(base)
m=base.m;Q=base.Q;row=base.row

def nested(tours=None):
 return {"id":6319,"name":"PARENT HOTEL","country":{"id":1},"region":{"name":"Side"},"subRegion":{"name":"Kizilot"},"latitude":36.7,"longitude":31.56,"tours":tours if tours is not None else [{"id":"12345678901234","name":"NOT A HOTEL NAME","operator":{"id":13,"name":"Anex"},"roomType":"STANDARD"}]}

def test_nested_result_keeps_hotel_and_tour_identity_separate():
 h=nested();r=m.build(Q,[h],"2026-10-05");c=r["captures"][0]
 assert r["capture_count"]==1 and r["operator_link_ready"]==0
 assert c["local_hotel_id"]==6319 and c["tour_id"]=="12345678901234"
 assert c["tourvisor_hotel_name"]=="PARENT HOTEL" and c["operator_id"]=="13"
 assert (c["region"],c["subregion"],c["tourvisor_latitude"],c["tourvisor_longitude"])==("Side","Kizilot",36.7,31.56)

def test_nested_single_hotel_and_existing_envelopes():
 h=nested()
 for payload in (h,[h],{"rows":[h]},{"results":[h]},{"data":[h]}):
  assert m.build(Q,payload,"2026-10-05")["capture_count"]==1

def test_raw_single_detail_object_is_consumed():
 d=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121")
 r=m.build(Q,d,"2026-10-05")
 assert r["operator_link_ready"]==1 and r["captures"][0]["tour_id"]=="t1"

def test_nested_operators_are_distinct_and_repeated_tours_are_deduped():
 tours=[{"id":str(100000+i),"operator":{"id":oid,"name":label}} for i,(oid,label) in enumerate(((13,"Anex"),(13,None),(25,"FUN&SUN"),(18,"Biblio"),(43,"Intourist")))]
 r=m.build(Q,[nested(tours)],"2026-10-05")
 assert r["capture_count"]==4 and r["operator_counts"]=={"13":1,"25":1,"18":1,"43":1}

def test_nested_listing_prefers_own_saved_detail_without_parent_mix():
 h=nested();d=row(13,"ANEX Tour","https://agent.anextour.ru/x?HOTELLIST=-1%2C8121")
 d["id"]="99999999999999";d["hotel"]["name"]="DETAIL HOTEL";d["hotel"]["common"]["latitude"]=36.9
 for payload in ([h,d],[d,h]):
  r=m.build(Q,payload,"2026-10-05");c=r["captures"][0]
  assert r["capture_count"]==r["operator_link_ready"]==1
  assert c["tour_id"]==d["id"] and c["tourvisor_hotel_name"]=="DETAIL HOTEL"
  assert c["tourvisor_latitude"]==36.9 and c["operator_link"]==d["operatorLink"]

def test_nested_parent_link_is_never_an_offer_link():
 h=nested();h["operatorLink"]="https://agent.anextour.ru/x?HOTELLIST=9999"
 r=m.build(Q,[h],"2026-10-05")
 assert r["capture_count"]==1 and r["operator_link_ready"]==0

def test_nested_parent_operator_is_not_inferred_for_child():
 h=nested();del h["tours"][0]["operator"];h["operator"]={"id":13,"name":"Anex"}
 r=m.build(Q,[h],"2026-10-05")
 assert r["capture_count"]==0 and any(x["reason"]=="missing_operator_identity" for x in r["rejected"])

def test_nested_conflicting_child_hotel_country_is_rejected():
 patches=({"hotelId":9999},{"hotel_id":9999},{"countryId":4},{"country_id":4},{"hotel":{"id":9999}},{"hotel":{"id":6319,"country":{"id":4}}},{"hotel":None},{"country":{"id":4}})
 for patch in patches:
  h=nested();h["tours"][0].update(patch);r=m.build(Q,[h],"2026-10-05")
  assert r["capture_count"]==0 and r["rejected"][0]["reason"]=="nested_hotel_identity_conflict"

def test_nested_agreeing_explicit_ids_keep_parent_facts():
 h=nested();h["tours"][0].update(hotelId="6319",countryId="1",hotel={"id":6319,"country":{"id":1},"name":"UNTRUSTED ALTERNATIVE"})
 c=m.build(Q,[h],"2026-10-05")["captures"][0]
 assert c["local_hotel_id"]==6319 and c["tourvisor_hotel_name"]=="PARENT HOTEL"

def test_nested_conflicting_tour_alias_is_rejected():
 h=nested();h["tours"][0]["tourId"]="22222222222222"
 r=m.build(Q,[h],"2026-10-05")
 assert r["capture_count"]==0 and r["rejected"][0]["reason"]=="nested_tour_identity_conflict"

def test_nested_malformed_children_do_not_hide_valid_sibling():
 h=nested();h["tours"]=[None,[],{"id":True},h["tours"][0],{"id":""}]
 r=m.build(Q,[h],"2026-10-05")
 assert r["capture_count"]==1 and sum(x["reason"]=="invalid_nested_tour" for x in r["rejected"])==4

def test_nested_malformed_parent_is_not_reinterpreted_as_tour():
 for key,value in (("id",True),("id",None),("country",None),("country",{"id":False}),("tours",{})):
  h=nested();h[key]=value;r=m.build(Q,[h],"2026-10-05")
  assert r["capture_count"]==0 and r["rejected"][0]["reason"]=="invalid_nested_hotel"

def test_nested_empty_result_does_not_make_dummy_tour():
 assert m.build(Q,[nested([])],"2026-10-05")["capture_count"]==0

def test_nested_country_and_target_guards_still_apply():
 h=nested();h["country"]["id"]=4;r=m.build(Q,[h],"2026-10-05")
 assert r["capture_count"]==0 and r["rejected"][0]["reason"]=="country_conflict"
 h=nested();h["id"]=123
 assert m.build(Q,[h],"2026-10-05")["capture_count"]==0
 q={"rows":[{"local_hotel_id":6319,"country_id":999}]};h=nested();h["country"]["id"]=999
 assert m.build(q,[h],"2026-10-05")["capture_count"]==0

def test_nested_unsafe_link_is_rejected_and_conflicts_remain_held():
 h=nested();h["tours"][0]["operatorLink"]="https://example.invalid/x?token=DO_NOT_COPY"
 r=m.build(Q,[h],"2026-10-05")
 assert r["capture_count"]==1 and r["operator_link_ready"]==0 and "DO_NOT_COPY" not in m.json.dumps(r)
 a=dict(h["tours"][0],operatorLink="https://agent.anextour.ru/x?HOTELLIST=8121")
 b=dict(a,id="22222222222222",operatorLink="https://agent.anextour.ru/x?HOTELLIST=9999")
 r=m.build(Q,[nested([a,b])],"2026-10-05")
 assert r["capture_count"]==0 and r["ambiguous_operator_targets"]==1

def test_nested_large_batch_is_nonmutating_and_hotel_local():
 q={"rows":[]};rows=[]
 for hid in range(1000,2000):
  q["rows"].append({"local_hotel_id":hid,"country_id":1,"frequency":hid})
  tours=[{"id":str(hid*100+oid),"operator":{"id":oid,"name":str(oid)}} for oid in (13,18,25,43)]
  h=nested(tours);h["id"]=hid;rows.append(h)
 before=m.json.dumps([q,rows],sort_keys=True);r=m.build(q,rows,"2026-10-05")
 assert r["capture_count"]==4000 and r["operator_link_ready"]==0
 assert len({(c["local_hotel_id"],c["operator_id"]) for c in r["captures"]})==4000
 assert all(c["tour_id"]==str(c["local_hotel_id"]*100+int(c["operator_id"])) for c in r["captures"])
 assert m.json.dumps([q,rows],sort_keys=True)==before
 assert r["database_writes"]==r["mapping_writes"]==r["tourvisor_calls"]==0

def test_archived_stale31_nested_result_contract():
 # Minimal structural fixture from artifact10582300921/server/response-032.json.
 # Values are retained evidence, not a new search or current availability.
 q={"rows":[{"local_hotel_id":40800,"country_id":4}]}
 h={"id":40800,"name":"SIDE LEGEND (EX. SIDE VIRGIN, SIDE ROSE)","country":{"id":4,"name":"Турция"},"region":{"id":23,"name":"Сиде"},"latitude":36.793242,"longitude":31.373113,"tours":[{"id":"18281432611532","operator":{"id":18,"name":"Biblioglobus"},"date":"2026-09-24","nights":7,"adults":2,"childs":2,"roomType":"family room"}]}
 r=m.build(q,[h],"2026-09-24");c=r["captures"][0]
 assert r["capture_count"]==1 and r["operator_link_ready"]==0
 assert (c["local_hotel_id"],c["tour_id"],c["operator_id"])==(40800,"18281432611532","18")
 assert c["tourvisor_hotel_name"]==h["name"] and c["region"]=="Сиде"
 assert (c["tourvisor_latitude"],c["tourvisor_longitude"])==(36.793242,31.373113)

if __name__=="__main__":
 for name,fn in sorted(globals().items()):
  if name.startswith("test_") and callable(fn): fn();print(name+": PASS")
