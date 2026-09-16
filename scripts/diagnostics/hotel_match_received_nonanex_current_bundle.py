#!/usr/bin/env python3
"""Build a read-only CURRENT guard pass for PR2535 non-ANEX relation dossiers."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
REPORT = ROOT / "reports/hotel-match-received-union-current-20260915.json"
REPORT_SHA256 = "d9a9a9cbbb8b4d3f77600c309fac8aa4334ebcb0812888725504c580bbdba8c6"
OPERATION_ID = "hotel-match-received-nonanex-current-1971-20260916-v1"
ALLOWED_OPERATORS = {"115", "315", "342"}


def sha256(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def build_plan() -> list[dict]:
    raw = REPORT.read_bytes()
    if sha256(raw) != REPORT_SHA256:
        raise SystemExit("report_digest_mismatch")
    report = json.loads(raw)
    if report.get("apply_manifest") is not False or report.get("auto_accept") is not False:
        raise SystemExit("report_authority_changed")
    rows = []
    for c in report.get("candidates", []):
        fact = c.get("fact") or {}
        op = str(fact.get("operator_key", ""))
        if op == "5":
            continue
        if op not in ALLOWED_OPERATORS:
            raise SystemExit("unexpected_operator")
        anchor = c.get("current_anchor") or {}
        local = anchor.get("local") or {}
        if not all(str(fact.get(k, "")).isdigit() for k in ("native_hotel_id", "andromeda_hotel_id")):
            raise SystemExit("invalid_identity")
        if not isinstance(anchor.get("local_hotel_id"), int):
            raise SystemExit("missing_local_target")
        rows.append({
            "operator_key": op,
            "supplier_namespace": "operator_" + op,
            "native_hotel_id": str(fact["native_hotel_id"]),
            "andromeda_hotel_id": str(fact["andromeda_hotel_id"]),
            "country_id": int(fact["country_id"]),
            "local_hotel_id": int(anchor["local_hotel_id"]),
            "anchor_evidence_sha256": str(anchor.get("identity_evidence_sha256") or ""),
            "hotel_name": str(fact.get("hotel_name") or ""),
            "original_name": str(fact.get("original_name") or ""),
            "town": str(fact.get("town") or ""),
            "request_sha256": str(fact.get("request_sha256") or ""),
            "response_sha256": str(fact.get("response_sha256") or ""),
            "frequency": int(c.get("frequency") or 0),
            "prior_dependency_reasons": sorted(set(map(str, c.get("dependency_reasons") or []))),
            "prior_review_signals": c.get("review_signals") or [],
            "prior_inherited_holds": c.get("inherited_identity_holds") or [],
        })
    rows.sort(key=lambda r: (-r["frequency"], r["operator_key"], r["native_hotel_id"]))
    if len(rows) != 93:
        raise SystemExit(f"nonanex_count:{len(rows)}")
    by_op = {}
    for r in rows:
        by_op[r["operator_key"]] = by_op.get(r["operator_key"], 0) + 1
    if by_op != {"115": 82, "315": 7, "342": 4}:
        raise SystemExit(f"operator_counts:{by_op}")
    if len({(r["operator_key"], r["native_hotel_id"], r["andromeda_hotel_id"]) for r in rows}) != len(rows):
        raise SystemExit("duplicate_relation")
    return rows


PHP = r'''<?php
declare(strict_types=1);
const HM_OP = '__OP__';
const HM_CORE = [1,2,4,8,9,10,12,16];
const HM_GENERIC = ['hotel','hotels','resort','resorts','spa','the','and','by','otel','отель','спа'];
const HM_QUALIFIERS = ['annex','annexe','beach','garden','gardens','north','south','east','west','posh','family','junior','deluxe','aqua','park','palace','royal','grand','premium','select','bay','island','village','adult','adults','club','mountain','palm','palms'];

function hm_write(string $dir,string $name,array $doc):string{
    $raw=json_encode($doc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    $f=fopen($dir.'/'.$name,'x+b');if(!$f)throw new RuntimeException('exclusive_output');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('output_failed');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('output_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function hm_fold(string $s):string{
    $s=mb_strtolower($s,'UTF-8');$s=str_replace(['’','`'],"'",$s);$s=preg_replace('/[^\p{L}\p{N}]+/u',' ',trim($s))??'';return trim(preg_replace('/\s+/u',' ',$s)??'');
}
function hm_forms(string $name):array{
    $name=preg_replace('/\(\s*(?:ex\.?|former|formerly|бывш\.?)\s*([^)]*)\)/iu',' | $1 ',$name)??$name;
    $parts=preg_split('/\s+\|\s+|\b(?:ex\.?|former|formerly)\b\.?/iu',$name)?:[];$out=[];
    foreach($parts as $part){$fold=hm_fold($part);if($fold==='')continue;$tokens=preg_split('/\s+/u',$fold,-1,PREG_SPLIT_NO_EMPTY)?:[];
        $tokens=array_values(array_filter($tokens,static fn($t)=>!in_array($t,HM_GENERIC,true)));
        if(!$tokens)continue;$norm=[];foreach($tokens as $t){if($t==='adults')$t='adult';if($t==='gardens')$t='garden';if($t==='palms')$t='palm';$norm[]=$t;}
        $compact=implode('',$norm);if(mb_strlen($compact,'UTF-8')<4)continue;$q=[];foreach($norm as $t)if(in_array($t,HM_QUALIFIERS,true))$q[]=$t;sort($q);preg_match_all('/\d+/u',implode(' ',$norm),$m);$out[$compact]=['compact'=>$compact,'tokens'=>$norm,'qualifiers'=>$q,'numbers'=>$m[0],'raw'=>$part];
    }return$out;
}
function hm_truthy($v):bool{if(is_bool($v))return$v;if(is_numeric($v))return(float)$v!==0.0;if(is_string($v))return trim($v)!==''&&!in_array(mb_strtolower(trim($v),'UTF-8'),['false','none','no','null','0'],true);return false;}
function hm_protected($v,string $key='',int $depth=0):bool{
    if($depth>20)return true;if(is_array($v)){foreach($v as $k=>$x)if(hm_protected($x,(string)$k,$depth+1))return true;return false;}
    if(!preg_match('/manual|exclude|exclusion|conflict|reject/i',$key))return false;return hm_truthy($v);
}
function hm_points($v,array &$out,int $depth=0):void{
    if(!is_array($v)||$depth>20)return;$a=$v['latitude']??$v['lat']??null;$b=$v['longitude']??$v['lng']??$v['lon']??null;
    if(is_numeric($a)&&is_numeric($b)&&abs((float)$a)<=90&&abs((float)$b)<=180&&!((float)$a===0.0&&(float)$b===0.0))$out[]=[(float)$a,(float)$b];
    foreach($v as $x)if(is_array($x))hm_points($x,$out,$depth+1);
}
function hm_distance(array $p,array $h):?float{
    if(!is_numeric($h['latitude']??null)||!is_numeric($h['longitude']??null))return null;$lat1=deg2rad($p[0]);$lat2=deg2rad((float)$h['latitude']);$dlat=$lat2-$lat1;$dlon=deg2rad((float)$h['longitude']-$p[1]);$a=sin($dlat/2)**2+cos($lat1)*cos($lat2)*sin($dlon/2)**2;return 6371*2*atan2(sqrt($a),sqrt(max(0,1-$a)));
}
function hm_geo_parts(string $s):array{
    $parts=preg_split('/[\/;,|]+/u',$s)?:[];$out=[];foreach($parts as $p){$f=hm_fold($p);$f=preg_replace('/\b(?:city|town|region|province|район|город)\b/u','',$f)??$f;$f=str_replace(' ','',$f);if(mb_strlen($f,'UTF-8')>=3)$out[$f]=true;}return array_keys($out);
}
function hm_geo_ok(string $source,array $anchor,array $local):bool{
    $s=hm_geo_parts($source);if(!$s)return true;$c=[];foreach(['town','townLName','region_name','subregion_name'] as $k){$v=$anchor[$k]??$local[$k]??null;if(is_string($v))$c=array_merge($c,hm_geo_parts($v));}
    if(!$c)return true;foreach($s as $a)foreach($c as $b)if($a===$b||str_contains($a,$b)||str_contains($b,$a))return true;return false;
}
function hm_names_from_evidence(array $ev):array{
    $out=[];$src=is_array($ev['source']??null)?$ev['source']:[];foreach(['name','lName','hotel_name','hotelName'] as $k)if(is_string($src[$k]??null)&&trim($src[$k])!=='')$out[]=$src[$k];return array_values(array_unique($out));
}
function hm_current_row(array $index,string $ns,string $id):?array{return $index[$ns.'|'.$id]??null;}
function hm_classify(array $p,array $rows,array $locals,array $countryIndex,array $occupancy):array{
    $why=[];$support=[];$ns=$p['supplier_namespace'];$native=hm_current_row($rows,$ns,$p['native_hotel_id']);$anchor=hm_current_row($rows,'andromeda_catalog',$p['andromeda_hotel_id']);$target=$locals[(string)$p['local_hotel_id']]??null;
    if($p['prior_dependency_reasons']||$p['prior_review_signals']||$p['prior_inherited_holds'])$why[]='prior_dossier_hold';
    if(!$anchor)$why[]='current_anchor_missing';else{
        if(($anchor['decision_status']??null)!=='accepted'||(int)($anchor['local_hotel_id']??0)!==$p['local_hotel_id'])$why[]='current_anchor_changed';
        if(!hash_equals($p['anchor_evidence_sha256'],(string)($anchor['evidence_sha256']??'')))$why[]='anchor_evidence_changed';
        if(hm_protected($anchor['evidence']??[]))$why[]='anchor_protected_marker';
    }
    if(!$target||!(int)$target['is_active'])$why[]='current_local_missing_or_inactive';elseif((int)$target['country_id']!==$p['country_id'])$why[]='country_conflict';
    if($native){
        if(($native['decision_status']??null)==='accepted'&&(int)($native['local_hotel_id']??0)===$p['local_hotel_id'])return ['route'=>'already_mapped_same','holds'=>[],'support'=>['current_mapping_same'],'safe_to_write_now'=>false];
        if(($native['decision_status']??null)!=='pending'||$native['local_hotel_id']!==null)$why[]='native_protected_or_mapped';
        if(hm_protected($native['evidence']??[]))$why[]='native_protected_marker';
    }
    foreach($occupancy[$ns][$p['local_hotel_id']]??[] as $other)if($other!==$p['native_hotel_id'])$why[]='same_provider_target_occupied';
    if(!$target)return ['route'=>'held','holds'=>array_values(array_unique($why)),'support'=>$support,'safe_to_write_now'=>false];
    $sourceNames=array_values(array_unique(array_filter([$p['hotel_name'],$p['original_name']],static fn($x)=>trim($x)!=='')));$ids=[];$matched=[];
    foreach($sourceNames as $name)foreach(hm_forms($name) as $key=>$sf)foreach($countryIndex[$p['country_id']][$key]??[] as $lid=>$tforms)foreach($tforms as $tf)if($sf['qualifiers']===$tf['qualifiers']&&$sf['numbers']===$tf['numbers']){$ids[(int)$lid]=true;if((int)$lid===$p['local_hotel_id'])$matched[]=['source'=>$sf['raw'],'target'=>$tf['raw'],'compact'=>$key];}
    if(count($ids)!==1||!isset($ids[$p['local_hotel_id']]))$why[]='countrywide_exact_alias_not_unique_at_target';else$support[]='countrywide_unique_exact_primary_or_former_alias';
    $anchorEv=$anchor['evidence']??[];$anchorSource=is_array($anchorEv['source']??null)?$anchorEv['source']:[];
    if(!hm_geo_ok($p['town'],$anchorSource,$target))$why[]='provider_geography_conflict';else$support[]='provider_geography_not_conflicting';
    $points=[];hm_points($anchorEv,$points);$dist=[];foreach($points as $pt){$d=hm_distance($pt,$target);if($d!==null){$dist[]=$d;if($d>5)$why[]='coordinate_conflict_gt5km';}}
    if($points)$support[]='saved_coordinate_checked';
    $why=array_values(array_unique($why));return ['route'=>$why?'held':'guard_passed_prepared','holds'=>$why,'support'=>$support,'matched_forms'=>$matched,'countrywide_ids'=>array_keys($ids),'distances_km'=>$dist,'safe_to_write_now'=>false];
}

if(in_array('--self-test',$argv??[],true)){
    $a=hm_forms('Arsi Hotel');$b=hm_forms('ARSI HOTEL');if(!array_intersect_key($a,$b))throw new RuntimeException('generic_exact');
    if(array_intersect_key(hm_forms('Royal Beach 2'),hm_forms('Royal Garden 2')))throw new RuntimeException('qualifier');
    $x=hm_forms('Sea Garden Adults');$y=hm_forms('SEA GARDEN ADULT');if(!array_intersect_key($x,$y))throw new RuntimeException('adult_morphology');
    if(!isset(hm_forms('New Name (EX. Old Name)')['oldname']))throw new RuntimeException('former');
    if(!hm_protected(['manual'=>true])||hm_protected(['conflict'=>false]))throw new RuntimeException('protected');
    if(!hm_geo_ok('Kemer / Кемер',['town'=>'Кемер'],['region_name'=>'Кемер']))throw new RuntimeException('geo');
    echo "6 non-ANEX CURRENT guard self-tests PASS\n";exit;
}

$plan=json_decode('__PLAN__',true,64,JSON_THROW_ON_ERROR);$op=getenv('MATCH_OPERATION_ID')?:'';$sha=getenv('MATCH_SOURCE_SHA')?:'';$dir=getenv('HOME').'/.anytoour-match/operations/'.HM_OP;
if($op!==HM_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha)||!is_file($dir.'/reservation.json'))throw new RuntimeException('reservation_required');
$res=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);if(($res['operation_id']??null)!==$op||($res['source_sha']??null)!==$sha||($res['state']??null)!=='reserved_before_db_access')throw new RuntimeException('reservation_mismatch');
$phase='bootstrap';$db=null;
try{
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $phase='current';$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    $all=$db->query('SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id')->fetchAll(PDO::FETCH_ASSOC);$rows=[];$occupancy=[];$acceptedAliases=[];
    foreach($all as $r){$r['evidence']=json_decode((string)($r['evidence_json']??''),true)?:[];unset($r['evidence_json']);$rows[$r['supplier_namespace'].'|'.$r['external_hotel_id']]=$r;if($r['local_hotel_id']!==null)$occupancy[$r['supplier_namespace']][(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];if($r['supplier_namespace']==='andromeda_catalog'&&$r['decision_status']==='accepted'&&$r['local_hotel_id']!==null)foreach(hm_names_from_evidence($r['evidence']) as $n)$acceptedAliases[(int)$r['local_hotel_id']][]=$n;}
    $locals=[];foreach($db->query('SELECT id,country_id,name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE country_id IN (1,2,4,8,9,10,12,16) AND is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $h)$locals[(string)$h['id']]=$h;
    $countryIndex=[];foreach($locals as $h){$lid=(int)$h['id'];$names=[$h['name']];foreach($acceptedAliases[$lid]??[] as $n)$names[]=$n;foreach(array_unique($names) as $n)foreach(hm_forms((string)$n) as $key=>$f)$countryIndex[(int)$h['country_id']][$key][$lid][]=$f;}
    $out=[];$counts=[];$reasons=[];$passedFreq=0;foreach($plan as $p){$r=hm_classify($p,$rows,$locals,$countryIndex,$occupancy);$r['operator_key']=$p['operator_key'];$r['supplier_namespace']=$p['supplier_namespace'];$r['native_hotel_id']=$p['native_hotel_id'];$r['andromeda_hotel_id']=$p['andromeda_hotel_id'];$r['local_hotel_id']=$p['local_hotel_id'];$r['frequency']=$p['frequency'];$r['request_sha256']=$p['request_sha256'];$r['response_sha256']=$p['response_sha256'];$out[]=$r;$counts[$r['route']]=($counts[$r['route']]??0)+1;foreach($r['holds'] as $w)$reasons[$w]=($reasons[$w]??0)+1;if($r['route']==='guard_passed_prepared')$passedFreq+=(int)$p['frequency'];}
    usort($out,static fn($a,$b)=>($b['frequency']<=>$a['frequency'])?:strcmp($a['supplier_namespace'].'|'.$a['native_hotel_id'],$b['supplier_namespace'].'|'.$b['native_hotel_id']));ksort($counts);arsort($reasons);$db->exec('ROLLBACK');$phase='receipt';
    $result=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','schema'=>'hotel-match-received-nonanex-current/1','plan_count'=>count($plan),'route_counts'=>$counts,'reason_counts'=>$reasons,'guard_passed_frequency'=>$passedFreq,'rows'=>$out,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true,'safe_to_write_now'=>false];$digest=hm_write($dir,'result.json',$result);hm_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$digest,'readback_verified'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);echo json_encode(['plan_count'=>count($plan),'route_counts'=>$counts,'reason_counts'=>$reasons,'guard_passed_frequency'=>$passedFreq],JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$digest=hm_write($dir,'result.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','phase'=>$phase,'error_class'=>get_class($e),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);hm_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','result_sha256'=>$digest,'readback_verified'=>true,'no_replay'=>true]);fwrite(STDERR,'NONANEX_CURRENT_FAILED:'.$phase."\n");exit(2);}
'''


def build() -> str:
    plan = build_plan()
    compact = json.dumps(plan, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    # JSON is embedded in a single-quoted PHP string: escape PHP string metacharacters.
    compact = compact.replace("\\", "\\\\").replace("'", "\\'")
    return PHP.replace("__OP__", OPERATION_ID).replace("__PLAN__", compact)


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit("usage: bundle.py OUTPUT.php")
    out = Path(sys.argv[1])
    source = build()
    out.write_text(source, encoding="utf-8")
    print(json.dumps({"operation_id": OPERATION_ID, "plan_count": 93, "bundle_sha256": hashlib.sha256(source.encode()).hexdigest(), "database_writes": 0}, sort_keys=True))


if __name__ == "__main__":
    main()
