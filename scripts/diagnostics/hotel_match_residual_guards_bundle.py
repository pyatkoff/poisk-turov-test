#!/usr/bin/env python3
"""Build one read-only CURRENT guard pass over 156 immutable proposed pairs."""
import hashlib,json,sys
from pathlib import Path
import hotel_match_core8_residual_bundle as parent
ROOT=Path(__file__).resolve().parents[2]
OP='hotel-match-residual-guards-1971-20260915-v1'
PHP=r'''
function rgg_forms(string $name):array{
 $parts=preg_split('/\b(?:ex|former|formerly)\b\.?/iu',$name)?:[];$out=[];
 foreach($parts as $part){$s=mcr_fold($part);$s=str_replace(["'",'’'],'',$s);$s=preg_replace('/\baquapark\b/u','aqua park',$s)??$s;
 $tokens=preg_split('/[^\p{L}\p{N}]+/u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[];
 $tokens=array_values(array_diff($tokens,['hotel','hotels','resort','resorts','spa','отель']));
 $compact=implode('',$tokens);if(count($tokens)<2||mb_strlen($compact,'UTF-8')<8)continue;
 $q=array_values(array_intersect($tokens,['annex','annexe','beach','garden','gardens','north','south','east','west','mountain','posh','family','junior','deluxe','aqua','park','palace','royal','grand','premium','select','bay','island','village']));sort($q);
 preg_match_all('/\d+/u',$compact,$n);$out[$compact]=['compact'=>$compact,'qualifiers'=>$q,'numbers'=>$n[0],'raw'=>$part];
 }return$out;
}
function rgg_protected($v,string $key='',int $depth=0):bool{
 if($depth>20)return true;
 if(is_array($v)){foreach($v as $k=>$x)if(rgg_protected($x,(string)$k,$depth+1))return true;return false;}
 if(!preg_match('/manual|exclude|exclusion|conflict|reject|review/i',$key))return false;
 if(is_bool($v))return$v;if(is_numeric($v))return(float)$v!=0;
 return is_string($v)&&trim($v)!==''&&!in_array(strtolower(trim($v)),['false','none','no','null'],true);
}
function rgg_points($v,array &$out,int $depth=0):void{
 if(!is_array($v)||$depth>20)return;
 $a=$v['latitude']??$v['lat']??null;$b=$v['longitude']??$v['lng']??$v['lon']??null;
 if(is_numeric($a)&&is_numeric($b)&&abs((float)$a)<=90&&abs((float)$b)<=180&&!((float)$a==0&&(float)$b==0))$out[]=['latitude'=>(float)$a,'longitude'=>(float)$b];
 foreach($v as $x)if(is_array($x))rgg_points($x,$out,$depth+1);
}
function rgg_review(array $row,array $ev,int $cid,array $allow,array $plan,array $hotels,array $index,array $occupancy):array{
 $id=(string)$row['external_hotel_id'];$p=$plan[$id];$lid=(int)$p['local_id'];$h=$hotels[$lid]??null;$why=[];$support=[];$matched=[];
 if(!hash_equals($p['evidence_sha256'],(string)$row['evidence_sha256']))$why[]='source_evidence_changed_since_dossier';
 if(!$h)return['route'=>'held','reason'=>'current_local_missing','target'=>$lid,'holds'=>['current_local_missing']];
 if($cid!==$p['country_id']||$cid!==(int)$h['country_id'])$why[]='country_conflict';
 if(rgg_protected($ev))$why[]='manual_review_conflict_marker';
 foreach($occupancy[$lid]??[] as $other)if($other!==$id)$why[]='same_provider_target_occupied';
 $source=mcr_source($ev);$names=[];foreach(['name','lName','hotel_name','hotelName'] as $key)if(is_string($source[$key]??null))$names[]=$source[$key];
 $ids=[];foreach($names as $name)foreach(rgg_forms($name) as $key=>$f)foreach($index[$cid][$key]??[] as $target=>$forms){foreach($forms as $lf)if($f['qualifiers']===$lf['qualifiers']&&$f['numbers']===$lf['numbers']){$ids[(int)$target]=true;if((int)$target===$lid)$matched[]=['source'=>$f['raw'],'local'=>$lf['raw'],'compact'=>$key];}}
 if(count($ids)!==1||!isset($ids[$lid]))$why[]='countrywide_exact_alias_not_unique_at_proposed_target';else$support[]='countrywide_unique_exact_compact_alias';
 if($allow['status']!=='ok')$why[]='independent_geography_unresolved';elseif(!in_array($lid,$allow['ids'],true))$why[]='independent_geography_conflict';else$support[]='current_unanimous_provider_geography';
 $points=[];rgg_points($ev,$points);$dist=[];foreach($points as $pnt){$d=mcr_distance($pnt,$h);if($d===null)$why[]='current_target_coordinate_missing';else{$dist[]=$d;if($d>5)$why[]='coordinate_conflict_gt5km';}}
 $why=array_values(array_unique($why));return['route'=>$why?'held':'guard_passed_prepared','reason'=>$why?'current_guard_hold':'exact_alias_and_independent_geo','target'=>$lid,'target_name'=>$h['name'],'holds'=>$why,'support'=>$support,'matched_forms'=>$matched,'exact_countrywide_ids'=>array_keys($ids),'occupancy'=>$occupancy[$lid]??[],'saved_point_count'=>count($points),'distances_km'=>$dist,'safe_to_write_now'=>false];
}
if(in_array('--self-test',$argv??[],true)){
 $a=rgg_forms("Rocky's Boutique Resort");$b=rgg_forms('ROCKYS BOUTIQUE HOTEL');if(!array_intersect_key($a,$b))throw new RuntimeException('apostrophe');
 $a=rgg_forms('AMARINA JANNAH AQUAPARK');$b=rgg_forms('Amarina Jannah Aqua Park');if($a['amarinajannahaquapark']['qualifiers']!==$b['amarinajannahaquapark']['qualifiers'])throw new RuntimeException('compound_qualifier');
 if(array_intersect_key(rgg_forms('Royal Beach 2'),rgg_forms('Royal Garden 2')))throw new RuntimeException('qualifier');
 if(array_intersect_key(rgg_forms('Royal Beach 12'),rgg_forms('Royal Beach 1 2'))){$a=rgg_forms('Royal Beach 12');$b=rgg_forms('Royal Beach 1 2');if($a['royalbeach12']['numbers']===$b['royalbeach12']['numbers'])throw new RuntimeException('numeric_family');}
 if(rgg_forms('Hotel Resort Spa')||rgg_forms('Crown Hotel'))throw new RuntimeException('generic');
 if(!isset(rgg_forms('NEW DISTINCT NAME (EX. OLD DISTINCT NAME)')['olddistinctname']))throw new RuntimeException('former_alias');
 if(!rgg_protected(['source'=>['manual'=>true]])||rgg_protected(['conflict'=>false]))throw new RuntimeException('manual');
 $row=['external_hotel_id'=>'1','evidence_sha256'=>str_repeat('a',64)];$ev=['source'=>['name'=>'Royal Beach 2']];$plan=['1'=>['local_id'=>10,'country_id'=>4,'evidence_sha256'=>str_repeat('a',64)]];
 $hotels=[10=>['id'=>10,'country_id'=>4,'name'=>'Royal Beach 2','latitude'=>36,'longitude'=>31]];$index=[4=>['royalbeach2'=>[10=>array_values(rgg_forms('Royal Beach 2'))]]];$allow=['status'=>'ok','ids'=>[10]];
 $g=rgg_review($row,$ev,4,$allow,$plan,$hotels,$index,[]);if($g['route']!=='guard_passed_prepared'||$g['safe_to_write_now']!==false)throw new RuntimeException('prepared_only');
 foreach(['occupied','manual','geo','coordinate','changed','ambiguous'] as $case){$e=$ev;$a=$allow;$occ=[];$rr=$row;$ii=$index;
 if($case==='occupied')$occ=[10=>['2']];if($case==='manual')$e['manual']=true;if($case==='geo')$a['ids']=[11];if($case==='coordinate')$e['source']+=['latitude'=>1,'longitude'=>1];if($case==='changed')$rr['evidence_sha256']=str_repeat('b',64);if($case==='ambiguous')$ii[4]['royalbeach2'][11]=array_values(rgg_forms('Royal Beach 2'));
 if(rgg_review($rr,$e,4,$a,$plan,$hotels,$ii,$occ)['route']!=='held')throw new RuntimeException('hold_'.$case);
 }
 echo "14 residual identity guard self-tests PASS\n";exit;
}
'''
def build():
 raw=(ROOT/'reports/hotel-match-core8-residual-names-20260915.json').read_bytes()
 assert hashlib.sha256(raw).hexdigest()=='9291e4d74a1c74ac78b17a2ec0e7f8722c019b35183525453c68e83ec515ab3d'
 report=json.loads(raw);plan={x['source']['external_hotel_id']:{'local_id':x['local_id'],'country_id':x['source']['country_id'],'evidence_sha256':x['source']['evidence_sha256']} for x in report['candidates']};assert len(plan)==156
 s,_=parent.build();s=s.replace(parent.OP,OP)
 s=parent.once(s,"if(PHP_SAPI!=='cli')",PHP+"\nif(PHP_SAPI!=='cli')")
 # Keep numeric groups before compacting whitespace; compact equality alone is insufficient.
 s=parent.once(s,"preg_match_all('/\\d+/u',$compact,$n)","preg_match_all('/\\d+/u',implode(' ',$tokens),$n)")
 init="$routes=[];$candidates=[];$reasons=[];$withGeo=0;$freq=0;"
 planjson=json.dumps(plan,separators=(',',':'))
 extra="$plan=json_decode('"+planjson+"',true,32,JSON_THROW_ON_ERROR);$present=[];$pending=array_values(array_filter($pending,function($r)use($plan,&$present){$id=(string)$r['external_hotel_id'];if(!isset($plan[$id]))return false;$present[]=$id;return true;}));$missing=array_values(array_diff(array_map('strval',array_keys($plan)),$present));\n"
 extra+="$index=[];foreach($forms as $lid=>$list)foreach($list as $name)foreach(rgg_forms($name) as $key=>$f)$index[(int)$hotels[$lid]['country_id']][$key][(int)$lid][]=$f;\n"
 extra+="$occupancy=[];foreach(mpg_query($db,\"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND local_hotel_id IS NOT NULL\") as $o)$occupancy[(int)$o['local_hotel_id']][]=(string)$o['external_hotel_id'];\n"
 s=parent.once(s,init,extra+init)
 start="if($allow['status']==='geo_consensus_conflict')$sel=";a=s.index(start);b=s.index(";$item=array_merge(",a)
 s=s[:a]+"$sel=rgg_review($r,$e,$cid,$allow,$plan,$hotels,$index,$occupancy)"+s[b:]
 s=s.replace("$item['route']==='auto_accept_candidate'","$item['route']==='guard_passed_prepared'")
 s=parent.once(s,"'schema'=>'hotel-match-core8-residual-current/1'","'schema'=>'hotel-match-residual-current-guards/1'")
 s=parent.once(s,"'total_pending_before_exclusion'=>$totalPending","'planned_count'=>count($plan),'not_current_pending_ids'=>$missing,'total_pending_before_exclusion'=>$totalPending")
 return s
if __name__=='__main__':
 s=build();Path(sys.argv[1]).write_text(s);print(json.dumps({'operation_id':OP,'bundle_sha256':hashlib.sha256(s.encode()).hexdigest(),'planned_count':156,'database_writes':0}))
