#!/usr/bin/env python3
"""One sealed spelling-variant delta; existing CURRENT protection gates stay mandatory."""
import hashlib, json, sys
from pathlib import Path
import hotel_match_post50_current_guards_bundle as parent
ROOT=Path(__file__).resolve().parents[2]
OP='hotel-match-spelling-geo-delta-1971-20260915-v1'
PLAN='reports/hotel-match-spelling-geo-delta-plan-20260915.json'
CLAIM=5686624661
PHP=r'''
function sgd_forms(string $name):array{
 $out=[];foreach(preg_split('/\b(?:ex|former|formerly)\b\.?/iu',$name)?:[] as $part){
  $s=str_replace(["'",'’'],'',mcr_fold($part));$s=preg_replace('/\baquapark\b/u','aqua park',$s)??$s;
  $tokens=array_values(array_diff(preg_split('/[^\p{L}\p{N}]+/u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[],['hotel','hotels','resort','resorts','spa','отель']));
  if(!$tokens)continue;$key=implode('',$tokens);$out[$key]=['compact'=>$key,'tokens'=>$tokens,'raw'=>$part];
 }return$out;
}
function sgd_score(string $a,string $b):float{
 if(!preg_match('/^[a-z0-9]+$/D',$a)||!preg_match('/^[a-z0-9]+$/D',$b))return 0.0;
 $n=max(strlen($a),strlen($b));return$n?1.0-levenshtein($a,$b)/$n:0.0;
}
function sgd_one_token(array $a,array $b):bool{
 if(count($a)<2||count($a)!==count($b))return false;$changed=0;
 $protected=['annex','annexe','beach','garden','gardens','north','south','east','west','mountain','posh','family','junior','deluxe','aqua','park','palace','royal','grand','premium','select','bay','island','village','pool','sea','adult','adults'];
 foreach($a as $i=>$t){$u=$b[$i];if($t===$u)continue;
  if(++$changed>1||min(strlen($t),strlen($u))<4||!preg_match('/^[a-z]+$/D',$t)||!preg_match('/^[a-z]+$/D',$u)||in_array($t,$protected,true)||in_array($u,$protected,true)||levenshtein($t,$u)!==1)return false;
 }return$changed===1;
}
function sgd_rank(array $names,int $cid,array $hotels,array $forms):array{
 $source=[];foreach($names as $name)$source+=sgd_forms($name);$rank=[];$matches=[];
 foreach($hotels as $id=>$h){if((int)$h['country_id']!==$cid)continue;$best=0.0;
  foreach($forms[$id]??[] as $name)foreach(sgd_forms($name) as $lf)foreach($source as $sf){
   $score=sgd_score($sf['compact'],$lf['compact']);if($score>$best){$best=$score;$matches[$id]=['source'=>$sf,'local'=>$lf];}
  }$rank[$id]=$best;
 }arsort($rank,SORT_NUMERIC);$ids=array_keys($rank);$id=(int)($ids[0]??0);$score=$rank[$id]??0.0;$runner=isset($ids[1])?$rank[$ids[1]]:0.0;
 return['target'=>$id,'score'=>$score,'runner_up_score'=>$runner,'margin'=>$score-$runner,'match'=>$matches[$id]??null];
}
function sgd_review(array $row,array $ev,int $cid,array $allow,array $plan,array $hotels,array $index,array $occupancy,array $forms):array{
 $g=p50g_review($row,$ev,$cid,$allow,$plan,$hotels,$index,$occupancy);
 if($g['route']==='guard_passed_prepared')return$g;
 $required=['countrywide_primary_identity_not_unique_at_proposed_target','primary_identity_semantics_unproven'];
 if(count($g['holds'])!==2||array_diff($g['holds'],$required)||$plan[(string)$row['external_hotel_id']]['holds'])return$g;
 $s=mcr_source($ev);$names=[];foreach(['name','lName','hotel_name','hotelName'] as $k)if(is_string($s[$k]??null))$names[]=$s[$k];
 $rank=sgd_rank($names,$cid,$hotels,$forms);$g['spelling_rank']=$rank;$match=$rank['match'];
 if($rank['target']!==$g['target']||$rank['score']<0.94||$rank['margin']<0.12||!is_array($match)||!sgd_one_token($match['source']['tokens'],$match['local']['tokens']))return$g;
 // Qualifiers and numeric groups must agree on the actual winning forms.
 $a=p50g_forms($match['source']['raw']);$b=p50g_forms($match['local']['raw']);$af=$a[$match['source']['compact']]??null;$bf=$b[$match['local']['compact']]??null;
 if(!$af||!$bf||$af['qualifiers']!==$bf['qualifiers']||$af['numbers']!==$bf['numbers'])return$g;
 $g['route']='guard_passed_prepared';$g['reason']='single_letter_spelling_and_countrywide_margin_and_current_geo';$g['holds']=[];
 $g['support'][]='single_letter_nonqualifier_token';$g['support'][]='unfiltered_countrywide_levenshtein_winner';$g['matched_forms']=[$match];return$g;
}
'''

WRITE=r'''
 $byId=[];foreach($pending as $r)$byId[(string)$r['external_hotel_id']]=$r;
 $collisions=[];foreach($plan as $id=>$p)$collisions[(int)$p['local_id']][]=(string)$id;
 $valid=[];$holds=[];
 foreach($candidates as $c){$id=(string)$c['external_hotel_id'];$r=$byId[$id];$e=mcr_evidence((string)$r['evidence_json']);
  if(count($collisions[(int)$c['target']])!==1){$holds[]=['id'=>$id,'reason'=>'duplicate_planned_target'];continue;}
  if(!isset(SGD_ELIGIBLE[$id])||SGD_ELIGIBLE[$id]!==$c['target']){$holds[]=['id'=>$id,'reason'=>'outside_sealed_eligible_delta'];continue;}
  if(array_key_exists('spelling_geo_acceptance',$e)){$holds[]=['id'=>$id,'reason'=>'prior_operation_protected'];continue;}
  $prior=(string)$r['evidence_sha256'];$e['spelling_geo_acceptance']=['operation_id'=>MPG_OP,'source_sha'=>$sha,'target_local_hotel_id'=>(int)$c['target'],'prior_evidence_sha256'=>$prior,'plan_sha256'=>SGD_PLAN_SHA,'parent_result_sha256'=>'b51edba926e5e8c974b04d12adb23e5c3029691716641ea52e929b268b47e074','rule'=>$c['reason'],'guard_evidence'=>$c,'server_current_revalidated'=>true];
  $json=mpg_json($e);$valid[]=['external_hotel_id'=>$id,'local_hotel_id'=>(int)$c['target'],'prior_evidence_sha256'=>$prior,'new_evidence_sha256'=>hash('sha256',$json),'evidence_json'=>$json];
 }
 mpg_write($dir.'/precommit.json',['operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'validated_before_commit','no_replay'=>true,'planned_count'=>count($plan),'writes'=>array_map(function($r){unset($r['evidence_json']);return$r;},$valid),'holds'=>$holds,'not_current_pending_ids'=>$missing]);
 $up=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
 foreach($valid as $w){$up->execute([$w['local_hotel_id'],$w['new_evidence_sha256'],$w['evidence_json'],$w['external_hotel_id'],$w['prior_evidence_sha256']]);if($up->rowCount()!==1)throw new RuntimeException('conditional_write_mismatch');}
 $db->commit();$committed=true;$readback=[];
 foreach($valid as $w){$rows=mpg_query($db,"SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$w['external_hotel_id']]);
  if(count($rows)!==1)throw new RuntimeException('readback_missing');$r=$rows[0];$e=mcr_evidence((string)$r['evidence_json']);
  if($r['decision_status']!=='accepted'||(int)$r['local_hotel_id']!==$w['local_hotel_id']||$r['evidence_sha256']!==$w['new_evidence_sha256']||hash('sha256',(string)$r['evidence_json'])!==$w['new_evidence_sha256']||($e['spelling_geo_acceptance']['operation_id']??'')!==MPG_OP)throw new RuntimeException('post_commit_readback_mismatch');
  $readback[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$w['external_hotel_id'],'local_hotel_id'=>(int)$r['local_hotel_id'],'decision_status'=>$r['decision_status'],'evidence_sha256'=>$r['evidence_sha256']];
 }
 $result=['schema'=>'spelling-geo-guarded-delta/1','operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'completed_committed','server_current'=>true,'transaction'=>'SERIALIZABLE with locked identities','plan_sha256'=>SGD_PLAN_SHA,'planned_count'=>count($plan),'written'=>count($readback),'post_commit_readback'=>$readback,'not_current_pending_ids'=>$missing,'guard_holds'=>$routes['held']??[],'write_holds'=>$holds,'supplier_calls'=>0,'tourvisor_calls'=>0,'db_writes'=>count($readback),'mapping_writes'=>count($readback),'operator_5_writes'=>0,'no_replay'=>true,'created_at'=>gmdate('c')];
 $hash=mpg_write($dir.'/result.json',$result);if(hash_file('sha256',$dir.'/result.json')!==$hash)throw new RuntimeException('result_hash');
 mpg_write($dir.'/receipt.json',['operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'completed_committed','result_sha256'=>$hash,'readback_verified'=>true,'written'=>count($readback),'no_replay'=>true,'created_at'=>gmdate('c')]);
 echo 'MATCH_SPELLING_GEO_COMMITTED '.count($readback)."\n";
'''

def build():
 raw=(ROOT/PLAN).read_bytes();p=json.loads(raw);digest=hashlib.sha256(raw).hexdigest()
 assert p['operation_id']==OP and p['planned_count']==104
 assert p['parent_result_sha256']=='b51edba926e5e8c974b04d12adb23e5c3029691716641ea52e929b268b47e074'
 eligible={str(x['external_hotel_id']):int(x['local_id']) for x in p['eligible_rows']}
 assert eligible and len(eligible)==len(p['eligible_rows'])==len(set(eligible.values()))
 s=parent.build();once=parent.parent.once;s=once(s,parent.OP,OP)
 # Tests can load only the complete functions, without entering DB code.
 s=once(s,"if(in_array('--self-test'",PHP+"\nif(getenv('MATCH_SPELLING_LIBRARY')==='1')return;\nif(in_array('--self-test'")
 s=once(s,'$sel=p50g_review($r,$e,$cid,$allow,$plan,$hotels,$index,$occupancy)','$sel=sgd_review($r,$e,$cid,$allow,$plan,$hotels,$index,$occupancy,$forms)')
 constants="const SGD_PLAN_SHA='"+digest+"';\nconst SGD_ELIGIBLE="+repr(eligible).replace('{','[').replace('}',']').replace(':','=>')+";\n"
 s=once(s,"if(PHP_SAPI!=='cli')",constants+"if(PHP_SAPI!=='cli')")
 s=once(s,"throw new RuntimeException('reservation');$root=","throw new RuntimeException('reservation');if(($res['plan_sha256']??'')!==SGD_PLAN_SHA||($res['planned_rows']??0)!==104)throw new RuntimeException('reservation_plan');$root=")
 s=once(s,"$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');","$db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$committed=false;")
 s=once(s,"try{\n $core=[];","try{\n $eng=mpg_query($db,\"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels','hotel_aliases','catalog_countries')\");if(count($eng)!==4)throw new RuntimeException('table_contract');foreach($eng as $t)if(strtoupper((string)$t['ENGINE'])!=='INNODB')throw new RuntimeException('nontransactional_table');\n mpg_query($db,\"SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id FOR UPDATE\");\n $core=[];")
 a=s.index(" $result=['schema'=>'hotel-match-post50-current-guards/1'")
 s=s[:a]+WRITE+"\n}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if(!is_file($dir.'/failure.json'))mpg_write($dir.'/failure.json',['operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>$committed?'unknown_after_commit':'rolled_back','error_class'=>get_class($e),'no_replay'=>true]);throw$e;}\n"
 assert s.count('UPDATE andromeda_hotel_identities SET')==1 and s.count('$db->commit()')==1
 return s

if __name__=='__main__':
 s=build();Path(sys.argv[1]).write_text(s);print(json.dumps({'operation_id':OP,'bundle_sha256':hashlib.sha256(s.encode()).hexdigest()}))
