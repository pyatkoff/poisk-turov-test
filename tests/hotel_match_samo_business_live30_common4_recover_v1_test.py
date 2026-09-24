import importlib.util,pathlib,tempfile,json
p=pathlib.Path(__file__).resolve().parents[1]/"scripts/diagnostics/hotel_match_samo_business_live30_common4_recover_v1.py"
spec=importlib.util.spec_from_file_location("m",p);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.self_test()
rows=[{"catalog_id":"1","stateinc":3,"missing_operator_ids":[5,115]},{"catalog_id":"2","stateinc":3,"missing_operator_ids":[5]}]
# generic chunk/group semantics stay deterministic
g=[]
from collections import defaultdict
b=defaultdict(set)
for r in rows:
    for op in r["missing_operator_ids"]:b[(r["stateinc"],op)].add(r["catalog_id"])
for (state,op),ids in b.items():
    for c in m.chunks(ids):g.append((state,op,c))
g.sort(key=lambda x:(x[0],x[1],int(x[2][0])))
assert g==[(3,5,["1","2"]),(3,115,["1"])]
assert m.bridge({"operatorKey":115,"hotelKey":"1","isOperatorHotelKey":"0"},115,{"1"})==("catalog_only","1",None)
print("MATCH_SAMO_BUSINESS_LIVE30_RECOVER_V1_TEST_OK")
