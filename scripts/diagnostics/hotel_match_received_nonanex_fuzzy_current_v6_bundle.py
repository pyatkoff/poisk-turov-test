#!/usr/bin/env python3
"""Add a bounded strong-fuzzy evidence tier to the sealed non-ANEX CURRENT review."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_source_geo_current_v5_bundle as v5

OPERATION_ID = "hotel-match-received-nonanex-fuzzy-current-1971-20260916-v6"
PARENT_OPERATION_ID = "hotel-match-received-nonanex-source-geo-current-1971-20260916-v5"
PARENT_RUN_ID = 35065292007
PARENT_RESULT_SHA256 = "d4aa102e6e52e81fdf2116f4514aa28eaa9ed9fb3db99984d4434fead3a2d21b"

FUZZY_HELPERS = r'''
function hm_fuzzy_score(string $a,string $b):float{
    if(!preg_match('/^[a-z0-9]+$/D',$a)||!preg_match('/^[a-z0-9]+$/D',$b))return 0.0;
    $n=max(strlen($a),strlen($b));if($n===0)return 0.0;
    return 1.0-(levenshtein($a,$b)/$n);
}
function hm_fuzzy_one_token(array $a,array $b):bool{
    if(count($a)<2||count($a)!==count($b))return false;
    $changed=0;
    foreach($a as $i=>$t){
        $u=$b[$i]??'';if($t===$u)continue;
        if(++$changed>1||min(strlen($t),strlen($u))<4)return false;
        if(!preg_match('/^[a-z]+$/D',$t)||!preg_match('/^[a-z]+$/D',$u))return false;
        if(in_array($t,HM_QUALIFIERS,true)||in_array($u,HM_QUALIFIERS,true))return false;
        if(levenshtein($t,$u)!==1)return false;
    }
    return $changed===1;
}
function hm_fuzzy_upgrade(array $p,array $r,array $locals,array $acceptedAliases):array{
    if(($r['route']??'')!=='held')return $r;
    $holds=array_values(array_unique($r['holds']??[]));sort($holds);
    if($holds!==['countrywide_exact_alias_not_unique_at_target'])return $r;
    $target=$locals[(string)$p['local_hotel_id']]??null;if(!$target)return $r;
    $source=[];
    foreach(array_values(array_unique(array_filter([$p['hotel_name'],$p['original_name']],static fn($x)=>trim((string)$x)!==''))) as $name){
        foreach(hm_source_forms((string)$name,$target) as $key=>$f)$source[$key]=$f;
    }
    if(!$source)return $r;
    $rank=[];$bestMatch=[];
    foreach($locals as $h){
        if((int)$h['country_id']!==(int)$p['country_id'])continue;
        $lid=(int)$h['id'];$best=-1.0;$winner=null;
        $names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;
        foreach(array_unique($names) as $n){
            try{$targetForms=hm_target_forms((string)$n,$h);}catch(Throwable $e){$targetForms=hm_forms((string)$n);}
            foreach($targetForms as $tf)foreach($source as $sf){
                $score=hm_fuzzy_score((string)$sf['compact'],(string)$tf['compact']);
                if($score>$best){$best=$score;$winner=['source'=>$sf,'target'=>$tf];}
            }
        }
        $rank[$lid]=$best;$bestMatch[$lid]=$winner;
    }
    arsort($rank,SORT_NUMERIC);$ids=array_keys($rank);$winner=(int)($ids[0]??0);$score=(float)($rank[$winner]??0.0);$runner=isset($ids[1])?(float)$rank[$ids[1]]:0.0;$margin=$score-$runner;
    $r['fuzzy_rank']=['winner_local_id'=>$winner,'score'=>$score,'runner_up_score'=>$runner,'margin'=>$margin];
    if($winner!==(int)$p['local_hotel_id']||$score<0.94||$margin<0.12)return $r;
    $m=$bestMatch[$winner]??null;if(!is_array($m)||!is_array($m['source']??null)||!is_array($m['target']??null))return $r;
    $sf=$m['source'];$tf=$m['target'];
    if(($sf['qualifiers']??[])!==($tf['qualifiers']??[])||($sf['numbers']??[])!==($tf['numbers']??[]))return $r;
    if(!hm_fuzzy_one_token($sf['tokens']??[],$tf['tokens']??[]))return $r;
    $r['route']='guard_passed_prepared';$r['holds']=[];
    $r['support'][]='countrywide_strong_fuzzy_single_letter_margin';
    $r['matched_forms']=[[
        'source'=>$sf['raw']??null,'target'=>$tf['raw']??null,
        'source_compact'=>$sf['compact']??null,'target_compact'=>$tf['compact']??null,
        'source_geo_edge_removed'=>$sf['source_geo_edge_removed']??[],
        'target_geo_edge_removed'=>$tf['target_geo_edge_removed']??[],
        'score'=>$score,'runner_up_score'=>$runner,'margin'=>$margin
    ]];
    return $r;
}
'''


def build() -> str:
    s = v5.build()
    if s.count(v5.OPERATION_ID) != 1:
        raise SystemExit(f"operation_marker_count:{s.count(v5.OPERATION_ID)}")
    s = s.replace(v5.OPERATION_ID, OPERATION_ID, 1)

    marker = "function hm_current_row(array $index,string $ns,string $id):?array{return $index[$ns.'|'.$id]??null;}\n"
    if s.count(marker) != 1:
        raise SystemExit("helper_marker_changed")
    s = s.replace(marker, FUZZY_HELPERS + "\n" + marker, 1)

    old_call = "try{$r=hm_classify($p,$rows,$locals,$countryIndex,$occupancy);}catch(Throwable $e){$r=['route'=>'held','holds'=>['geo_edge_runtime_error'],'support'=>[],'matched_forms'=>[],'countrywide_ids'=>[],'distances_km'=>[],'runtime_error_class'=>get_class($e),'runtime_error_sha256'=>hash('sha256',$e->getMessage()),'safe_to_write_now'=>false];}"
    new_call = "try{$r=hm_classify($p,$rows,$locals,$countryIndex,$occupancy);$r=hm_fuzzy_upgrade($p,$r,$locals,$acceptedAliases);}catch(Throwable $e){$r=['route'=>'held','holds'=>['fuzzy_runtime_error'],'support'=>[],'matched_forms'=>[],'countrywide_ids'=>[],'distances_km'=>[],'runtime_error_class'=>get_class($e),'runtime_error_sha256'=>hash('sha256',$e->getMessage()),'safe_to_write_now'=>false];}"
    if s.count(old_call) != 1:
        raise SystemExit(f"classify_marker_count:{s.count(old_call)}")
    s = s.replace(old_call, new_call, 1)

    old_schema = "'schema'=>'hotel-match-received-nonanex-geo-current/1'"
    if s.count(old_schema) != 1:
        raise SystemExit("schema_marker_changed")
    s = s.replace(old_schema, "'schema'=>'hotel-match-received-nonanex-fuzzy-current/1'", 1)

    test_marker = "    $sf=hm_source_forms('Der Inn Hotel Konyaalti',['region_name'=>'Анталья','subregion_name'=>'Коньяалты']);if(!isset($sf['derinn'])||empty($sf['derinn']['source_geo_edge_removed']))throw new RuntimeException('source_geo_edge');\n"
    extra_test = test_marker + "    if(hm_fuzzy_score('continentalairport','continentelairport')<0.94)throw new RuntimeException('fuzzy_score');\n    if(!hm_fuzzy_one_token(['continental','airport'],['continentel','airport']))throw new RuntimeException('fuzzy_one_token');\n    if(hm_fuzzy_one_token(['royal','beach'],['royal','garden']))throw new RuntimeException('fuzzy_qualifier');\n"
    if s.count(test_marker) != 1:
        raise SystemExit("self_test_marker_changed")
    s = s.replace(test_marker, extra_test, 1)

    if "START TRANSACTION READ ONLY" not in s or "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ" not in s:
        raise SystemExit("read_only_contract_missing")
    if "countrywide_strong_fuzzy_single_letter_margin" not in s or "fuzzy_runtime_error" not in s:
        raise SystemExit("fuzzy_contract_missing")
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
        "rule": "only_exact_hold_then_countrywide_winner_score_094_margin_012_single_nonqualifier_edit1_plus_all_existing_current_guards",
    }, sort_keys=True))
