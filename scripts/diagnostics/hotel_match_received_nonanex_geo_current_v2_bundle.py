#!/usr/bin/env python3
"""Build immutable read-only CURRENT pass with conservative target-side geo-edge aliases."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_current_bundle as parent

OPERATION_ID = "hotel-match-received-nonanex-geo-current-1971-20260916-v2"
PARENT_OPERATION_ID = "hotel-match-received-nonanex-current-1971-20260916-v1"
PARENT_RUN_ID = 35049809798
PARENT_RESULT_SHA256 = "9c851185282fb85bb56e281a65fb56f6c3daf26686cd3b6a72a7dbdc355f41e6"

GEO_HELPERS = r'''
function hm_geo_translit(string $s):string{
    $s=hm_fold($s);
    return strtr($s,[
        'щ'=>'shch','ш'=>'sh','ч'=>'ch','ж'=>'zh','ю'=>'yu','я'=>'ya','ё'=>'e','х'=>'h','ц'=>'ts',
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','ы'=>'y','э'=>'e','ъ'=>'','ь'=>''
    ]);
}
function hm_geo_spellings(array $h):array{
    $known=[
        'стамбул'=>['istanbul'],'лалели'=>['laleli'],'султанахмет'=>['sultanahmet'],'сиркеджи'=>['sirkeci'],'аксарай'=>['aksaray'],'фатих'=>['fatih'],'беязыт'=>['beyazit'],'бейоглу'=>['beyoglu'],'шишли'=>['sisli'],'таксим'=>['taksim'],
        'анталья'=>['antalya'],'алания'=>['alanya'],'кемер'=>['kemer'],'бодрум'=>['bodrum'],'торба'=>['torba'],'коньяалты'=>['konyaalti','konyaalty'],
        'хургада'=>['hurghada'],'шарм эль шейх'=>['sharm el sheikh'],'наама бей'=>['naama bay'],'наама бэй'=>['naama bay'],'набк'=>['nabq'],'набк бей'=>['nabq bay'],'набк бэй'=>['nabq bay'],'макади'=>['makadi'],'макади бей'=>['makadi bay'],'макади бэй'=>['makadi bay'],'марса алам'=>['marsa alam'],'сахль хашиш'=>['sahl hasheesh','sahl hashish'],'сахл хашиш'=>['sahl hasheesh','sahl hashish'],'эль гуна'=>['el gouna'],
        'пхукет'=>['phuket'],'паттайя'=>['pattaya'],'самуи'=>['samui'],'нячанг'=>['nha trang'],'фукуок'=>['phu quoc'],'дананг'=>['da nang'],'дубай'=>['dubai'],'шарджа'=>['sharjah'],'абу даби'=>['abu dhabi']
    ];
    $out=[];
    foreach(['region_name','subregion_name'] as $field){
        $raw=hm_fold((string)($h[$field]??''));if($raw==='')continue;
        $parts=[$raw];foreach(preg_split('/[\\/,;()]+/u',(string)($h[$field]??''))?:[] as $p){$f=hm_fold($p);if($f!=='')$parts[]=$f;}
        foreach(array_unique($parts) as $p){
            $variants=[$p,hm_geo_translit($p)];foreach($known[$p]??[] as $v)$variants[]=$v;
            foreach(array_unique($variants) as $v){
                $v=hm_fold($v);$v=preg_replace('/\\b(?:city|town|region|province|center|centre|город|район|область|центр)\\b/u',' ',$v)??$v;$v=trim(preg_replace('/\\s+/u',' ',$v)??'');
                if(mb_strlen(str_replace(' ','',$v),'UTF-8')<4)continue;
                $tokens=preg_split('/\\s+/u',$v,-1,PREG_SPLIT_NO_EMPTY)?:[];$blocked=false;foreach($tokens as $t)if(in_array($t,HM_QUALIFIERS,true)){$blocked=true;break;}if($blocked)continue;
                $out[$v]=['field'=>$field,'value'=>(string)($h[$field]??'')];
            }
        }
    }
    return $out;
}
function hm_target_forms(string $name,array $h):array{
    $out=hm_forms($name);$start=hm_fold($name);if($start==='')return $out;$states=[$start=>[]];$geos=hm_geo_spellings($h);
    for($round=0;$round<3;$round++){
        $next=$states;
        foreach($states as $current=>$removed)foreach($geos as $g=>$proof){
            $candidate=null;
            if(str_starts_with($current,$g.' '))$candidate=trim(substr($current,strlen($g)+1));
            elseif(str_ends_with($current,' '.$g))$candidate=trim(substr($current,0,strlen($current)-strlen($g)-1));
            if($candidate===null||$candidate===''||isset($next[$candidate]))continue;
            $next[$candidate]=array_merge($removed,[['spelling'=>$g,'field'=>$proof['field'],'value'=>$proof['value']]]);
        }
        if(count($next)===count($states))break;$states=$next;
    }
    foreach($states as $candidate=>$removed){if(!$removed)continue;foreach(hm_forms($candidate) as $key=>$f){$f['target_geo_edge_removed']=$removed;$f['target_geo_edge_original']=$name;$out[$key]=$f;}}
    return $out;
}
'''


def build() -> str:
    plan = parent.build_plan()
    if len(plan) != 93:
        raise SystemExit(f"plan_count:{len(plan)}")
    if {p["operator_key"] for p in plan} != {"115", "315", "342"}:
        raise SystemExit("operator_scope_changed")

    compact = json.dumps(plan, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    compact = compact.replace("\\", "\\\\").replace("'", "\\'")
    s = parent.PHP.replace("__PLAN__", compact).replace("__OP__", OPERATION_ID)

    marker = "function hm_current_row(array $index,string $ns,string $id):?array{return $index[$ns.'|'.$id]??null;}\n"
    if s.count(marker) != 1:
        raise SystemExit("helper_marker_changed")
    s = s.replace(marker, GEO_HELPERS + "\n" + marker, 1)

    old_index = "$countryIndex=[];foreach($locals as $h){$lid=(int)$h['id'];$names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;foreach(array_unique($names) as $n)foreach(hm_forms((string)$n) as $key=>$f)$countryIndex[(int)$h['country_id']][$key][$lid][]=$f;}"
    new_index = "$countryIndex=[];foreach($locals as $h){$lid=(int)$h['id'];$names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;foreach(array_unique($names) as $n)foreach(hm_target_forms((string)$n,$h) as $key=>$f)$countryIndex[(int)$h['country_id']][$key][$lid][]=$f;}"
    if s.count(old_index) != 1:
        raise SystemExit("country_index_marker_changed")
    s = s.replace(old_index, new_index, 1)

    old_match = "$matched[]=['source'=>$sf['raw'],'target'=>$tf['raw'],'compact'=>$key];"
    new_match = "$matched[]=['source'=>$sf['raw'],'target'=>$tf['raw'],'compact'=>$key,'target_geo_edge_removed'=>$tf['target_geo_edge_removed']??[],'target_geo_edge_original'=>$tf['target_geo_edge_original']??null];"
    if s.count(old_match) != 1:
        raise SystemExit("match_marker_changed")
    s = s.replace(old_match, new_match, 1)

    strict_support = "$support[]='countrywide_unique_exact_primary_or_former_alias';"
    geo_support = "$support[]=array_reduce($matched,static fn($carry,$m)=>$carry||!empty($m['target_geo_edge_removed']),false)?'countrywide_unique_exact_after_target_geo_edge':'countrywide_unique_exact_primary_or_former_alias';"
    if s.count(strict_support) != 1:
        raise SystemExit("support_marker_changed")
    s = s.replace(strict_support, geo_support, 1)

    s = s.replace("'schema'=>'hotel-match-received-nonanex-current/1'", "'schema'=>'hotel-match-received-nonanex-geo-current/1'", 1)

    test_marker = "    if(!hm_geo_ok('Kemer / Кемер',['town'=>'Кемер'],['region_name'=>'Кемер']))throw new RuntimeException('geo');\n"
    extra_test = test_marker + "    $gf=hm_target_forms('Malkoc Hotel Laleli',['region_name'=>'Стамбул','subregion_name'=>'Лалели']);if(!isset($gf['malkoc'])||empty($gf['malkoc']['target_geo_edge_removed']))throw new RuntimeException('target_geo_edge');\n"
    if s.count(test_marker) != 1:
        raise SystemExit("self_test_marker_changed")
    s = s.replace(test_marker, extra_test, 1)

    if "START TRANSACTION READ ONLY" not in s or "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ" not in s:
        raise SystemExit("read_only_contract_missing")
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
        "rule": "target_side_region_subregion_edge_alias_only_countrywide_unique_all_existing_guards_preserved",
    }, sort_keys=True))
