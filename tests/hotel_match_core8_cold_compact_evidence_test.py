#!/usr/bin/env python3
import importlib.util
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts/diagnostics"))
P = ROOT / "scripts/diagnostics/hotel_match_core8_cold_compact_evidence.py"
spec = importlib.util.spec_from_file_location("compact", P)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

rows=[]
for i in range(1399):
    rows.append({"external_hotel_id":str(200000+i),"country_id":1,"names":["No Match"],"points":[],"geo_anchors":[],"frequency":0})
excluded={str(200000+i) for i in range(156)}
rows[156]={"external_hotel_id":"200156","country_id":1,"names":["SeaPearl Hotel"],"points":[],"geo_anchors":[{"scope":"region","scope_id":11}],"frequency":5}
rows[157]={"external_hotel_id":"200157","country_id":1,"names":["South Garden Hotel"],"points":[],"geo_anchors":[{"scope":"region","scope_id":11}],"frequency":4}
result={
 "operation_id":"fixture","source_sha":"fixture","routes":{"needs_extra_evidence":rows},
 "local_hotels":{
   "601":{"id":601,"country_id":1,"name":"SEA PEARL RESORT","region_id":11,"subregion_id":None},
   "602":{"id":602,"country_id":1,"name":"GARDEN SOUTH HOTEL","region_id":11,"subregion_id":None},
 },
 "local_alias_forms":{"601":["Sea Pearl Resort"],"602":["Garden South Hotel"]},
}
r=m.analyze(result,excluded)
assert r["examined_cold_rows"]==1243
assert r["prepared_identity_dossiers"]==2
by={d["source"]["external_hotel_id"]:d for d in r["dossiers"]}
assert by["200156"]["state"]=="compact_unique_prepared"
assert by["200156"]["local_id"]==601
assert by["200157"]["state"]=="token_order_unique_prepared"
assert by["200157"]["local_id"]==602
assert all(d["auto_accept"] is False for d in r["dossiers"])
print("hotel_match_core8_cold_compact_evidence_test: ok")
