#!/usr/bin/env python3
"""Fail-closed successor for non-ANEX geo-edge CURRENT review."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_geo_current_v2_bundle as v2

OPERATION_ID = "hotel-match-received-nonanex-geo-current-1971-20260916-v3"
PARENT_OPERATION_ID = "hotel-match-received-nonanex-current-1971-20260916-v1"
PARENT_RUN_ID = 35049809798
PARENT_RESULT_SHA256 = "9c851185282fb85bb56e281a65fb56f6c3daf26686cd3b6a72a7dbdc355f41e6"


def build() -> str:
    s = v2.build()
    if s.count(v2.OPERATION_ID) != 1:
        raise SystemExit(f"operation_marker_count:{s.count(v2.OPERATION_ID)}")
    s = s.replace(v2.OPERATION_ID, OPERATION_ID, 1)

    old_index = "$countryIndex=[];foreach($locals as $h){$lid=(int)$h['id'];$names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;foreach(array_unique($names) as $n)foreach(hm_target_forms((string)$n,$h) as $key=>$f)$countryIndex[(int)$h['country_id']][$key][$lid][]=$f;}"
    new_index = "$countryIndex=[];$geoFormErrors=[];foreach($locals as $h){$lid=(int)$h['id'];$names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;foreach(array_unique($names) as $n){try{$forms=hm_target_forms((string)$n,$h);}catch(Throwable $e){$geoFormErrors[]=['local_hotel_id'=>$lid,'error_class'=>get_class($e),'error_sha256'=>hash('sha256',$e->getMessage())];$forms=hm_forms((string)$n);}foreach($forms as $key=>$f)$countryIndex[(int)$h['country_id']][$key][$lid][]=$f;}}"
    if s.count(old_index) != 1:
        raise SystemExit("country_index_marker_changed")
    s = s.replace(old_index, new_index, 1)

    old_call = "$r=hm_classify($p,$rows,$locals,$countryIndex,$occupancy);"
    new_call = "try{$r=hm_classify($p,$rows,$locals,$countryIndex,$occupancy);}catch(Throwable $e){$r=['route'=>'held','holds'=>['geo_edge_runtime_error'],'support'=>[],'matched_forms'=>[],'countrywide_ids'=>[],'distances_km'=>[],'runtime_error_class'=>get_class($e),'runtime_error_sha256'=>hash('sha256',$e->getMessage()),'safe_to_write_now'=>false];}"
    if s.count(old_call) != 1:
        raise SystemExit("classify_call_marker_changed")
    s = s.replace(old_call, new_call, 1)

    old_result = "'safe_to_write_now'=>false];$digest=hm_write($dir,'result.json',$result);"
    new_result = "'safe_to_write_now'=>false,'geo_form_errors'=>$geoFormErrors];$digest=hm_write($dir,'result.json',$result);"
    if s.count(old_result) != 1:
        raise SystemExit(f"result_marker_count:{s.count(old_result)}")
    s = s.replace(old_result, new_result, 1)

    if "START TRANSACTION READ ONLY" not in s or "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ" not in s:
        raise SystemExit("read_only_contract_missing")
    if "geo_edge_runtime_error" not in s or "$geoFormErrors" not in s:
        raise SystemExit("fail_closed_contract_missing")
    upper = s.upper()
    for verb in ("INSERT INTO", "UPDATE ", "DELETE FROM", "REPLACE INTO", "ALTER TABLE", "DROP TABLE", "TRUNCATE TABLE"):
        if verb in upper:
            raise SystemExit(f"mutation_token:{verb}")
    if "operator_5" in s:
        raise SystemExit("anex_scope_leak")
    return s


if __name__ == "__main__":
    s = build()
    Path(sys.argv[1]).write_text(s)
    print(json.dumps({
        "operation_id": OPERATION_ID,
        "parent_operation_id": PARENT_OPERATION_ID,
        "parent_run_id": PARENT_RUN_ID,
        "parent_result_sha256": PARENT_RESULT_SHA256,
        "plan_count": 93,
        "database_writes": 0,
        "mapping_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "bundle_sha256": hashlib.sha256(s.encode()).hexdigest(),
        "fail_closed": True,
        "rule": "target_side_region_subregion_edge_alias_countrywide_unique_with_per_local_and_per_candidate_runtime_hold",
    }, sort_keys=True))
