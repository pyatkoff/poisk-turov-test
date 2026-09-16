#!/usr/bin/env python3
"""Add source-name own-target geography edge evidence to the fail-closed CURRENT review."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_geo_current_v3_bundle as v3

OPERATION_ID = "hotel-match-received-nonanex-source-geo-current-1971-20260916-v4"
PARENT_OPERATION_ID = "hotel-match-received-nonanex-geo-current-1971-20260916-v3"
PARENT_RUN_ID = 35061758070
PARENT_RESULT_SHA256 = "6ccb043d00478e4a833ec7f1389bd0c2a55586adb1974a0b86068c4222a5e259"

SOURCE_HELPER = r'''
function hm_source_forms(string $name,array $target):array{
    $forms=hm_target_forms($name,$target);$out=[];
    foreach($forms as $key=>$f){
        if(!empty($f['target_geo_edge_removed'])){
            $f['source_geo_edge_removed']=$f['target_geo_edge_removed'];
            $f['source_geo_edge_original']=$f['target_geo_edge_original']??$name;
        }
        unset($f['target_geo_edge_removed'],$f['target_geo_edge_original']);
        $out[$key]=$f;
    }
    return $out;
}
'''


def build() -> str:
    s = v3.build()
    if s.count(v3.OPERATION_ID) != 1:
        raise SystemExit(f"operation_marker_count:{s.count(v3.OPERATION_ID)}")
    s = s.replace(v3.OPERATION_ID, OPERATION_ID, 1)

    marker = "function hm_current_row(array $index,string $ns,string $id):?array{return $index[$ns.'|'.$id]??null;}\n"
    if s.count(marker) != 1:
        raise SystemExit("helper_marker_changed")
    s = s.replace(marker, SOURCE_HELPER + "\n" + marker, 1)

    old_source = "foreach($sourceNames as $name)foreach(hm_forms($name) as $key=>$sf)"
    new_source = "foreach($sourceNames as $name)foreach(hm_source_forms((string)$name,$target) as $key=>$sf)"
    if s.count(old_source) != 1:
        raise SystemExit(f"source_loop_marker_count:{s.count(old_source)}")
    s = s.replace(old_source, new_source, 1)

    old_match = "$matched[]=['source'=>$sf['raw'],'target'=>$tf['raw'],'compact'=>$key,'target_geo_edge_removed'=>$tf['target_geo_edge_removed']??[],'target_geo_edge_original'=>$tf['target_geo_edge_original']??null];"
    new_match = "$matched[]=['source'=>$sf['raw'],'target'=>$tf['raw'],'compact'=>$key,'source_geo_edge_removed'=>$sf['source_geo_edge_removed']??[],'source_geo_edge_original'=>$sf['source_geo_edge_original']??null,'target_geo_edge_removed'=>$tf['target_geo_edge_removed']??[],'target_geo_edge_original'=>$tf['target_geo_edge_original']??null];"
    if s.count(old_match) != 1:
        raise SystemExit(f"match_marker_count:{s.count(old_match)}")
    s = s.replace(old_match, new_match, 1)

    old_support = "$support[]=array_reduce($matched,static fn($carry,$m)=>$carry||!empty($m['target_geo_edge_removed']),false)?'countrywide_unique_exact_after_target_geo_edge':'countrywide_unique_exact_primary_or_former_alias';"
    new_support = "$support[]=array_reduce($matched,static fn($carry,$m)=>$carry||!empty($m['source_geo_edge_removed'])||!empty($m['target_geo_edge_removed']),false)?'countrywide_unique_exact_after_source_or_target_geo_edge':'countrywide_unique_exact_primary_or_former_alias';"
    if s.count(old_support) != 1:
        raise SystemExit(f"support_marker_count:{s.count(old_support)}")
    s = s.replace(old_support, new_support, 1)

    test_marker = "    $gf=hm_target_forms('Malkoc Hotel Laleli',['region_name'=>'Стамбул','subregion_name'=>'Лалели']);if(!isset($gf['malkoc'])||empty($gf['malkoc']['target_geo_edge_removed']))throw new RuntimeException('target_geo_edge');\n"
    extra_test = test_marker + "    $sf=hm_source_forms('Der Inn Hotel Konyaalti',['region_name'=>'Анталья','subregion_name'=>'Коньяалты']);if(!isset($sf['derinn'])||empty($sf['derinn']['source_geo_edge_removed']))throw new RuntimeException('source_geo_edge');\n"
    if s.count(test_marker) != 1:
        raise SystemExit("self_test_marker_changed")
    s = s.replace(test_marker, extra_test, 1)

    if "START TRANSACTION READ ONLY" not in s or "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ" not in s:
        raise SystemExit("read_only_contract_missing")
    if "geo_edge_runtime_error" not in s or "$geoFormErrors" not in s or "source_geo_edge_removed" not in s:
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
        "rule": "source_geo_edge_only_when_equal_to_current_target_region_or_subregion_plus_countrywide_unique_exact_and_all_existing_guards",
    }, sort_keys=True))
