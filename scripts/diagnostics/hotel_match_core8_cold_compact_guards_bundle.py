#!/usr/bin/env python3
"""Build one read-only CURRENT guard pass over 29 immutable cold compact/token-order proposals."""
import hashlib
import json
import sys
from pathlib import Path
import hotel_match_core8_residual_bundle as parent

ROOT = Path(__file__).resolve().parents[2]
OP = "hotel-match-core8-cold-compact-guards-1971-20260915-v1"
REPORT_SHA = "5da1d8a0149ea6cfb3d52a2650dedcab1be2ccaac41c20bf7b57f75f2e24063d"

PHP = r'''
function ccg_forms(string $name):array{
 $parts=preg_split('/\b(?:ex|former|formerly)\b\.?/iu',$name)?:[];$out=[];
 foreach($parts as $part){
  $s=mcr_fold($part);$s=str_replace(["'",'’'],'',$s);$s=preg_replace('/\baquapark\b/u','aqua park',$s)??$s;
  $tokens=preg_split('/[^\p{L}\p{N}]+/u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[];
  $tokens=array_values(array_diff($tokens,['hotel','hotels','resort','resorts','spa','отель','the','and','by']));
  $compact=implode('',$tokens);if(mb_strlen($compact,'UTF-8')<8)continue;
  $q=array_values(array_intersect($tokens,['annex','annexe','beach','garden','gardens','north','south','east','west','mountain','posh','family','junior','deluxe','aqua','park','palace','royal','grand','premium','select','bay','island','village']));sort($q);
  preg_match_all('/\d+/u',implode(' ',$tokens),$n);
  $sorted=$tokens;sort($sorted,SORT_STRING);
  $out[]=['compact'=>$compact,'sorted'=>implode(' ',$sorted),'qualifiers'=>$q,'numbers'=>$n[0],'raw'=>$part];
 }
 return$out;
}
function ccg_protected($v,string $key='',int $depth=0):bool{
 if($depth>20)return true;
 if(is_array($v)){foreach($v as $k=>$x)if(ccg_protected($x,(string)$k,$depth+1))return true;return false;}
 if(!preg_match('/manual|exclude|exclusion|conflict|reject|review/i',$key))return false;
 if(is_bool($v))return$v;if(is_numeric($v))return(float)$v!=0;
 return is_string($v)&&trim($v)!==''&&!in_array(strtolower(trim($v)),['false','none','no','null'],true);
}
function ccg_points($v,array &$out,int $depth=0):void{
 if(!is_array($v)||$depth>20)return;
 $a=$v['latitude']??$v['lat']??null;$b=$v['longitude']??$v['lng']??$v['lon']??null;
 if(is_numeric($a)&&is_numeric($b)&&abs((float)$a)<=90&&abs((float)$b)<=180&&!((float)$a==0&&(float)$b==0))$out[]=['latitude'=>(float)$a,'longitude'=>(float)$b];
 foreach($v as $x)if(is_array($x))ccg_points($x,$out,$depth+1);
}
function ccg_review(array $row,array $ev,int $cid,array $allow,array $plan,array $hotels,array $compactIndex,array $sortedIndex,array $occupancy,array $plannedTargets):array{
 $id=(string)$row['external_hotel_id'];$p=$plan[$id];$lid=(int)$p['local_id'];$h=$hotels[$lid]??null;$why=[];$support=[];$matched=[];
 if(!hash_equals($p['evidence_sha256'],(string)$row['evidence_sha256']))$why[]='source_evidence_changed_since_dossier';
 if(!$h)return['route'=>'held','reason'=>'current_local_missing','target'=>$lid,'holds'=>['current_local_missing'],'safe_to_write_now'=>false];
 if($cid!==$p['country_id']||$cid!==(int)$h['country_id'])$why[]='country_conflict';
 if(ccg_protected($ev))$why[]='manual_review_conflict_marker';
 if(count($plannedTargets[$lid]??[])!==1)$why[]='duplicate_planned_same_provider_target';
 foreach($occupancy[$lid]??[] as $other)if($other!==$id)$why[]='same_provider_target_occupied';
 $source=mcr_source($ev);$names=[];foreach(['name','lName','hotel_name','hotelName'] as $key)if(is_string($source[$key]??null))$names[]=$source[$key];
 $ids=[];$kind=$p['evidence_kind'];
 foreach($names as $name)foreach(ccg_forms($name) as $f){
  $bucket=$kind==='compact_exact'?($compactIndex[$cid][$f['compact']]??[]):($sortedIndex[$cid][$f['sorted']]??[]);
  foreach($bucket as $target=>$forms)foreach($forms as $lf){
   if($f['qualifiers']!==$lf['qualifiers']||$f['numbers']!==$lf['numbers'])continue;
   $ids[(int)$target]=true;if((int)$target===$lid)$matched[]=['kind'=>$kind,'source'=>$f['raw'],'local'=>$lf['raw'],'compact'=>$f['compact'],'sorted'=>$f['sorted']];
  }
 }
 if(count($ids)!==1||!isset($ids[$lid]))$why[]=$kind==='compact_exact'?'countrywide_exact_compact_not_unique_at_proposed_target':'countrywide_exact_token_order_not_unique_at_proposed_target';
 else$support[]=$kind==='compact_exact'?'countrywide_unique_exact_compact_alias':'countrywide_unique_exact_token_order_alias';
 if($allow['status']!=='ok')$why[]='independent_geography_unresolved';elseif(!in_array($lid,$allow['ids'],true))$why[]='independent_geography_conflict';else$support[]='current_unanimous_provider_geography';
 $points=[];ccg_points($ev,$points);$dist=[];foreach($points as $pnt){$d=mcr_distance($pnt,$h);if($d===null)$why[]='current_target_coordinate_missing';else{$dist[]=$d;if($d>5)$why[]='coordinate_conflict_gt5km';}}
 $why=array_values(array_unique($why));
 return['route'=>$why?'held':'guard_passed_prepared','reason'=>$why?'current_guard_hold':'exact_identity_and_independent_geo','target'=>$lid,'target_name'=>$h['name'],'holds'=>$why,'support'=>$support,'matched_forms'=>$matched,'exact_countrywide_ids'=>array_keys($ids),'occupancy'=>$occupancy[$lid]??[],'planned_target_ids'=>$plannedTargets[$lid]??[],'saved_point_count'=>count($points),'distances_km'=>$dist,'safe_to_write_now'=>false];
}
if(in_array('--self-test',$argv??[],true)){
 $a=ccg_forms('SeaPearl Hotel');$b=ccg_forms('SEA PEARL RESORT');if($a[0]['compact']!==$b[0]['compact'])throw new RuntimeException('compact_split');
 $a=ccg_forms('South Garden Hotel');$b=ccg_forms('Garden South Resort');if($a[0]['sorted']!==$b[0]['sorted'])throw new RuntimeException('token_order');
 $a=ccg_forms('Royal Beach 2');$b=ccg_forms('Royal Garden 2');if($a[0]['qualifiers']===$b[0]['qualifiers'])throw new RuntimeException('qualifier');
 $a=ccg_forms('Royal Beach 12');$b=ccg_forms('Royal Beach 1 2');if($a[0]['numbers']===$b[0]['numbers'])throw new RuntimeException('numeric_family');
 if(ccg_forms('Hotel Resort Spa')||ccg_forms('Crown Hotel'))throw new RuntimeException('generic_short');
 if(!ccg_protected(['source'=>['manual'=>true]])||ccg_protected(['conflict'=>false]))throw new RuntimeException('manual');
 echo "7 cold compact CURRENT guard self-tests PASS\n";exit;
}
'''

def build(report_path):
    raw = Path(report_path).read_bytes()
    assert hashlib.sha256(raw).hexdigest() == REPORT_SHA
    report = json.loads(raw)
    assert report["state"] == "prepared_not_safe"
    assert report["examined_cold_rows"] == 1243
    assert report["prepared_identity_dossiers"] == 29
    plan = {}
    for item in report["dossiers"]:
        kinds = {e["kind"] for e in item["evidence"]}
        kind = "compact_exact" if "compact_exact" in kinds else "token_order_exact"
        src = item["source"]
        plan[str(src["external_hotel_id"])] = {
            "local_id": int(item["local_id"]),
            "country_id": int(src["country_id"]),
            "evidence_sha256": src["evidence_sha256"],
            "evidence_kind": kind,
        }
    assert len(plan) == 29

    s, _ = parent.build()
    s = s.replace(parent.OP, OP)
    s = parent.once(s, "if(PHP_SAPI!=='cli')", PHP + "\nif(PHP_SAPI!=='cli')")
    init = "$routes=[];$candidates=[];$reasons=[];$withGeo=0;$freq=0;"
    planjson = json.dumps(plan, separators=(",", ":"))
    extra = "$plan=json_decode('" + planjson + "',true,32,JSON_THROW_ON_ERROR);$present=[];$pending=array_values(array_filter($pending,function($r)use($plan,&$present){$id=(string)$r['external_hotel_id'];if(!isset($plan[$id]))return false;$present[]=$id;return true;}));$missing=array_values(array_diff(array_map('strval',array_keys($plan)),$present));\n"
    extra += "$compactIndex=[];$sortedIndex=[];foreach($forms as $lid=>$list)foreach($list as $name)foreach(ccg_forms($name) as $f){$compactIndex[(int)$hotels[$lid]['country_id']][$f['compact']][(int)$lid][]=$f;$sortedIndex[(int)$hotels[$lid]['country_id']][$f['sorted']][(int)$lid][]=$f;}\n"
    extra += "$occupancy=[];foreach(mpg_query($db,\"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND local_hotel_id IS NOT NULL\") as $o)$occupancy[(int)$o['local_hotel_id']][]=(string)$o['external_hotel_id'];\n"
    extra += "$plannedTargets=[];foreach($plan as $pid=>$pp)$plannedTargets[(int)$pp['local_id']][]=(string)$pid;\n"
    s = parent.once(s, init, extra + init)
    start = "if($allow['status']==='geo_consensus_conflict')$sel="
    a = s.index(start)
    b = s.index(";$item=array_merge(", a)
    s = s[:a] + "$sel=ccg_review($r,$e,$cid,$allow,$plan,$hotels,$compactIndex,$sortedIndex,$occupancy,$plannedTargets)" + s[b:]
    s = s.replace("$item['route']==='auto_accept_candidate'", "$item['route']==='guard_passed_prepared'")
    s = parent.once(s, "'schema'=>'hotel-match-core8-residual-current/1'", "'schema'=>'hotel-match-core8-cold-compact-current-guards/1'")
    s = parent.once(s, "'total_pending_before_exclusion'=>$totalPending", "'planned_count'=>count($plan),'not_current_pending_ids'=>$missing,'total_pending_before_exclusion'=>$totalPending")
    return s, plan

if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("usage: builder compact-report.json output.php")
    bundle, plan = build(sys.argv[1])
    Path(sys.argv[2]).write_text(bundle)
    print(json.dumps({"operation_id": OP, "bundle_sha256": hashlib.sha256(bundle.encode()).hexdigest(), "planned_count": len(plan), "database_writes": 0}, sort_keys=True))
