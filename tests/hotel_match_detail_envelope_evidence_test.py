#!/usr/bin/env python3
"""Offline regressions for persisted Tourvisor HTTP detail envelopes."""
import copy
import importlib.util
from pathlib import Path

BASE=Path(__file__).with_name("hotel_match_tourvisor_multi_operator_links_test.py")
s=importlib.util.spec_from_file_location("saved_link_base_tests",BASE)
base=importlib.util.module_from_spec(s);s.loader.exec_module(base)
m=base.m;Q=base.Q;row=base.row

def envelope(detail):
    return {"accounted_after":801,"http_status":200,"raw_body_sha256":"a"*64,"data":detail}

def nested():
    return {"id":6319,"name":"PARENT HOTEL","country":{"id":1},"region":{"name":"Side"},"tours":[{"id":"12345678901234","operator":{"id":13,"name":"Anex"}}]}

def test_wrapped_single_detail_is_consumed():
    d=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121")
    payload=envelope(d);before=m.json.dumps(payload,sort_keys=True)
    result=m.build(Q,payload,"2026-10-05")
    assert result["capture_count"]==result["operator_link_ready"]==1
    cap=result["captures"][0]
    assert (cap["local_hotel_id"],cap["tour_id"],cap["operator_id"])==(6319,"t1","13")
    assert cap["operator_link"]==d["operatorLink"]
    assert m.json.dumps(payload,sort_keys=True)==before

def test_wrapped_nested_hotel_result_is_consumed_without_parent_inference():
    h=nested();payload=envelope(h)
    result=m.build(Q,payload,"2026-10-05")
    assert result["capture_count"]==1 and result["operator_link_ready"]==0
    cap=result["captures"][0]
    assert cap["local_hotel_id"]==6319 and cap["tour_id"]=="12345678901234"
    assert cap["tourvisor_hotel_name"]=="PARENT HOTEL" and cap["operator_id"]=="13"

def test_arbitrary_data_object_is_not_recursively_reinterpreted():
    d=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121")
    for bad in (
        {"data":{"rows":[d]}},
        {"data":{"results":[d]}},
        {"data":{"data":d}},
        {"data":{"operatorLink":d["operatorLink"],"operator":d["operator"]}},
    ):
        assert m.build(Q,bad,"2026-10-05")["capture_count"]==0

def test_malformed_wrapped_detail_cannot_bypass_existing_guards():
    d=row(13,"Anex","https://agent.anextour.ru/x?HOTELLIST=8121")
    wrong=copy.deepcopy(d);wrong["hotel"]["country"]["id"]=4
    result=m.build(Q,envelope(wrong),"2026-10-05")
    assert result["capture_count"]==0 and result["rejected"][0]["reason"]=="country_conflict"
    secret=copy.deepcopy(d);secret["operatorLink"]="https://agent.anextour.ru/x?token=DO_NOT_PERSIST"
    result=m.build(Q,envelope(secret),"2026-10-05")
    assert result["capture_count"]==1 and result["operator_link_ready"]==0
    assert any(x["reason"]=="secret_bearing_operator_link" for x in result["rejected"])
    assert "DO_NOT_PERSIST" not in m.json.dumps(result)

def test_detail180_structural_shape_keeps_exact_hotel_tour_operator_link():
    detail={
        "id":"13279173063352",
        "hotel":{"id":28482,"name":"KLEOPATRA IKIZ","country":{"id":4,"name":"Турция"},"region":{"name":"Аланья"},"common":{"latitude":36.5510127,"longitude":31.9786427}},
        "operator":{"id":13,"name":"Anex"},
        "operatorLink":"https://agent.anextour.ru/search/tour?HOTELLIST=19158",
    }
    q={"rows":[{"local_hotel_id":28482,"country_id":4,"frequency":1}]}
    result=m.build(q,{"accounted_after":801,"http_status":200,"data":detail},"2026-09-22")
    assert result["capture_count"]==result["operator_link_ready"]==1
    cap=result["captures"][0]
    assert (cap["local_hotel_id"],cap["tour_id"],cap["operator_id"])==(28482,"13279173063352","13")
    assert cap["operator_link"]==detail["operatorLink"]

if __name__=="__main__":
    for name,fn in sorted(globals().items()):
        if name.startswith("test_") and callable(fn):
            fn();print(name+": PASS")
