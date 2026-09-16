#!/usr/bin/env python3
"""Build v2: strong-fuzzy CURRENT guard for exactly 37 v1 name-only holds."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_current_bundle as v1

OPERATION_ID = "hotel-match-received-nonanex-fuzzy-1971-20260916-v2"
FOCUS = {
    ("115","610086681"),("115","640012567"),("115","625846723"),("115","632923406"),
    ("115","648021912"),("115","632923857"),("115","610151217"),("115","610179244"),
    ("115","610212430"),("115","610136225"),("115","610200060"),("115","610086346"),
    ("115","610161936"),("115","610192330"),("115","610205967"),("115","626024989"),
    ("115","650739823"),("115","610159360"),("115","675591225"),("115","610117107"),
    ("115","610157515"),("115","625076536"),("115","632905334"),("115","649472645"),
    ("115","610075983"),("115","610161157"),("115","632804966"),("115","632937632"),
    ("115","649577500"),("115","675225016"),("115","610159877"),("115","610233069"),
    ("115","632907836"),("115","632908555"),("115","632926294"),("115","649377409"),
    ("115","669980685"),
}
V1_RESULT_SHA256 = "9c851185282fb85bb56e281a65fb56f6c3daf26686cd3b6a72a7dbdc355f41e6"


def focus_plan() -> list[dict]:
    rows = [r for r in v1.build_plan() if (r["operator_key"], r["native_hotel_id"]) in FOCUS]
    if len(rows) != 37 or {(r["operator_key"], r["native_hotel_id"]) for r in rows} != FOCUS:
        raise SystemExit("focus_mismatch")
    if any(r["prior_dependency_reasons"] or r["prior_review_signals"] or r["prior_inherited_holds"] for r in rows):
        raise SystemExit("focus_contains_prior_hold")
    return rows


EXTRA_PHP = r'''
function hm_chars(string $s):array{return preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[];}
function hm_edit1(string $a,string $b):int{
    $aa=hm_chars($a);$bb=hm_chars($b);$n=count($aa);$m=count($bb);if(abs($n-$m)>1)return 2;
    $prev=range(0,$m);for($i=1;$i<=$n;$i++){$cur=[$i];$rowMin=$i;for($j=1;$j<=$m;$j++){$cost=$aa[$i-1]===$bb[$j-1]?0:1;$cur[$j]=min($cur[$j-1]+1,$prev[$j]+1,$prev[$j-1]+$cost);$rowMin=min($rowMin,$cur[$j]);}if($rowMin>1)return 2;$prev=$cur;}return min(2,$prev[$m]);
}
function hm_fuzzy_score(array $a,array $b):float{
    if($a['qualifiers']!==$b['qualifiers']||$a['numbers']!==$b['numbers'])return 0.0;$at=$a['tokens'];$bt=$b['tokens'];if(count($at)!==count($bt)||!$at)return 0.0;$diff=0;
    foreach($at as $i=>$x){$y=$bt[$i];if($x===$y)continue;if(in_array($x,HM_QUALIFIERS,true)||in_array($y,HM_QUALIFIERS,true))return 0.0;if(hm_edit1($x,$y)!==1)return 0.0;if(++$diff>1)return 0.0;}
    $max=max(count(hm_chars($a['compact'])),count(hm_chars($b['compact'])));if($max===0)return 0.0;$d=hm_edit1($a['compact'],$b['compact']);if($d>1)return 0.0;return 1.0-($d/$max);
}
function hm_rank_fuzzy(array $sourceNames,array $forms,int $target):array{
    $scores=[];$matched=[];foreach($sourceNames as $name)foreach(hm_forms($name) as $sf)foreach($forms as $lid=>$tforms)foreach($tforms as $tf){$score=hm_fuzzy_score($sf,$tf);if($score<=0)continue;if(!isset($scores[$lid])||$score>$scores[$lid])$scores[$lid]=$score;if((int)$lid===$target&&$score>=0.94)$matched[]=['source'=>$sf['raw'],'target'=>$tf['raw'],'score'=>$score];}
    arsort($scores,SORT_NUMERIC);$ids=array_keys($scores);$winner=$ids[0]??null;$best=$winner===null?0.0:(float)$scores[$winner];$second=count($ids)>1?(float)$scores[$ids[1]]:0.0;$margin=$best-$second;
    return ['pass'=>$winner===$target&&$best>=0.94&&$margin>=0.12,'winner'=>$winner,'best_score'=>$best,'second_score'=>$second,'margin'=>$margin,'top'=>array_slice($scores,0,5,true),'matched'=>$matched];
}
'''

OLD_BLOCK = r'''$sourceNames=array_values(array_unique(array_filter([$p['hotel_name'],$p['original_name']],static fn($x)=>trim($x)!=='')));$ids=[];$matched=[];
    foreach($sourceNames as $name)foreach(hm_forms($name) as $key=>$sf)foreach($countryIndex[$p['country_id']][$key]??[] as $lid=>$tforms)foreach($tforms as $tf)if($sf['qualifiers']===$tf['qualifiers']&&$sf['numbers']===$tf['numbers']){$ids[(int)$lid]=true;if((int)$lid===$p['local_hotel_id'])$matched[]=['source'=>$sf['raw'],'target'=>$tf['raw'],'compact'=>$key];}
    if(count($ids)!==1||!isset($ids[$p['local_hotel_id']]))$why[]='countrywide_exact_alias_not_unique_at_target';else$support[]='countrywide_unique_exact_primary_or_former_alias';'''

NEW_BLOCK = r'''$sourceNames=array_values(array_unique(array_filter([$p['hotel_name'],$p['original_name']],static fn($x)=>trim($x)!=='')));$rank=hm_rank_fuzzy($sourceNames,$countryForms[$p['country_id']]??[],$p['local_hotel_id']);$matched=$rank['matched'];$ids=array_map('intval',array_keys($rank['top']));
    if(!$rank['pass'])$why[]='strong_fuzzy_winner_or_margin_failed';else$support[]='countrywide_strong_fuzzy_one_edit_margin_ge_012';'''

OLD_INDEX = r'''$countryIndex=[];foreach($locals as $h){$lid=(int)$h['id'];$names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;foreach(array_unique($names) as $n)foreach(hm_forms((string)$n) as $key=>$f)$countryIndex[(int)$h['country_id']][$key][$lid][]=$f;}'''
NEW_INDEX = r'''$countryIndex=[];$countryForms=[];foreach($locals as $h){$lid=(int)$h['id'];$names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;foreach(array_unique($names) as $n)foreach(hm_forms((string)$n) as $key=>$f){$countryIndex[(int)$h['country_id']][$key][$lid][]=$f;$countryForms[(int)$h['country_id']][$lid][]=$f;}}'''


def build() -> str:
    plan = focus_plan()
    compact = json.dumps(plan, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    compact = compact.replace("\\", "\\\\").replace("'", "\\'")
    source = v1.PHP.replace("__OP__", OPERATION_ID).replace("__PLAN__", compact)
    marker = "function hm_current_row(array $index,string $ns,string $id):?array"
    if source.count(marker) != 1:
        raise SystemExit("function_marker")
    source = source.replace(marker, EXTRA_PHP + "\n" + marker)
    if source.count(OLD_BLOCK) != 1 or source.count(OLD_INDEX) != 1:
        raise SystemExit("v1_source_drift")
    source = source.replace(OLD_BLOCK, NEW_BLOCK).replace(OLD_INDEX, NEW_INDEX)
    source = source.replace("'schema'=>'hotel-match-received-nonanex-current/1'", "'schema'=>'hotel-match-received-nonanex-fuzzy-current/2'")
    return source


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit("usage: fuzzy_bundle.py OUTPUT.php")
    out = Path(sys.argv[1])
    source = build()
    out.write_text(source, encoding="utf-8")
    print(json.dumps({"operation_id": OPERATION_ID, "plan_count": 37, "parent_result_sha256": V1_RESULT_SHA256, "bundle_sha256": hashlib.sha256(source.encode()).hexdigest(), "database_writes": 0}, sort_keys=True))


if __name__ == "__main__":
    main()
