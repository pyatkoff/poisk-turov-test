#!/usr/bin/env python3
"""Build a one-shot locked apply of the sealed 52-row residual union."""
import hashlib,json,re,sys
from pathlib import Path
import hotel_match_residual_guards_bundle as guards
ROOT=Path(__file__).resolve().parents[2]
OP='hotel-match-residual52-apply-1971-20260915-v1'
PLAN='reports/hotel-match-residual52-apply-plan-20260915.json'

WRITE=r'''
 $byId=[];foreach($pending as $r)$byId[(string)$r['external_hotel_id']]=$r;
 $collisions=[];foreach($plan as $id=>$p)$collisions[(int)$p['local_id']][]=(string)$id;
 $valid=[];$holds=[];
 foreach($candidates as $c){$id=(string)$c['external_hotel_id'];$r=$byId[$id];$e=mcr_evidence((string)$r['evidence_json']);
  if(count($collisions[(int)$c['target']])!==1){$holds[]=['id'=>$id,'reason'=>'duplicate_planned_target'];continue;}
  if(array_key_exists('residual52_acceptance',$e)){$holds[]=['id'=>$id,'reason'=>'prior_operation_protected'];continue;}
  $prior=(string)$r['evidence_sha256'];$e['residual52_acceptance']=['operation_id'=>MPG_OP,'source_sha'=>$sha,'target_local_hotel_id'=>(int)$c['target'],'prior_evidence_sha256'=>$prior,'plan_sha256'=>R52_PLAN_SHA,'parent_result_sha256'=>$plan[$id]['source_result_sha256'],'rule'=>'countrywide_exact_compact_alias_and_current_unanimous_geo','server_current_revalidated'=>true];
  $json=mpg_json($e);$valid[]=['external_hotel_id'=>$id,'local_hotel_id'=>(int)$c['target'],'prior_evidence_sha256'=>$prior,'new_evidence_sha256'=>hash('sha256',$json),'evidence_json'=>$json];
 }
 $checkpoint=['operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'validated_before_commit','no_replay'=>true,'planned_count'=>count($plan),'writes'=>array_map(function($r){unset($r['evidence_json']);return$r;},$valid),'holds'=>$holds,'not_current_pending_ids'=>$missing];
 mpg_write($dir.'/precommit.json',$checkpoint);
 $up=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
 foreach($valid as $w){$up->execute([$w['local_hotel_id'],$w['new_evidence_sha256'],$w['evidence_json'],$w['external_hotel_id'],$w['prior_evidence_sha256']]);if($up->rowCount()!==1)throw new RuntimeException('conditional_write_mismatch');}
 $db->commit();$committed=true;$readback=[];
 foreach($valid as $w){$rows=mpg_query($db,"SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$w['external_hotel_id']]);
  if(count($rows)!==1)throw new RuntimeException('readback_missing');$r=$rows[0];$e=mcr_evidence((string)$r['evidence_json']);
  if($r['decision_status']!=='accepted'||(int)$r['local_hotel_id']!==$w['local_hotel_id']||$r['evidence_sha256']!==$w['new_evidence_sha256']||hash('sha256',(string)$r['evidence_json'])!==$w['new_evidence_sha256']||($e['residual52_acceptance']['operation_id']??'')!==MPG_OP)throw new RuntimeException('post_commit_readback_mismatch');
  $readback[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$w['external_hotel_id'],'local_hotel_id'=>(int)$r['local_hotel_id'],'decision_status'=>$r['decision_status'],'evidence_sha256'=>$r['evidence_sha256']];
 }
 $result=['schema'=>'residual52-guarded-apply/1','operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'completed_committed','server_current'=>true,'transaction'=>'SERIALIZABLE with locked identities','plan_sha256'=>R52_PLAN_SHA,'planned_count'=>count($plan),'written'=>count($readback),'post_commit_readback'=>$readback,'not_current_pending_ids'=>$missing,'guard_holds'=>$routes['held']??[],'write_holds'=>$holds,'supplier_calls'=>0,'tourvisor_calls'=>0,'db_writes'=>count($readback),'mapping_writes'=>count($readback),'operator_5_writes'=>0,'no_replay'=>true,'created_at'=>gmdate('c')];
 $hash=mpg_write($dir.'/result.json',$result);if(hash_file('sha256',$dir.'/result.json')!==$hash)throw new RuntimeException('result_hash');
 mpg_write($dir.'/receipt.json',['operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>'completed_committed','result_sha256'=>$hash,'readback_verified'=>true,'written'=>count($readback),'no_replay'=>true,'created_at'=>gmdate('c')]);
 echo 'MATCH_RESIDUAL52_COMMITTED '.count($readback)."\n";
'''

def build():
 raw=(ROOT/PLAN).read_bytes();packet=json.loads(raw)
 assert hashlib.sha256(raw).hexdigest()=='9eb82d75127de06ed84fa021ec7ddf8558a34558582d748da5deb22928052f32', 'immutable_plan_changed'
 assert packet['operation_id']==OP and len(packet['rows'])==52
 plan={r['external_hotel_id']:r for r in packet['rows']}
 assert len(plan)==52 and len({r['local_id'] for r in plan.values()})==52
 s=guards.build();once=guards.parent.once
 s=once(s,guards.OP,OP)
 a=s.index("$plan=json_decode('");b=s.index(";",a)
 s=s[:a]+"$plan=json_decode('"+json.dumps(plan,separators=(',',':'))+"',true,32,JSON_THROW_ON_ERROR)"+s[b:]
 s=once(s,"if(PHP_SAPI!=='cli')", "const R52_PLAN_SHA='"+hashlib.sha256(raw).hexdigest()+"';\nif(PHP_SAPI!=='cli')")
 s=once(s,"throw new RuntimeException('reservation');$root=", "throw new RuntimeException('reservation');if(($res['plan_sha256']??'')!==R52_PLAN_SHA||($res['planned_rows']??0)!==52)throw new RuntimeException('reservation_plan');$root=")
 s=once(s,"$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');", "$db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$committed=false;")
 # Lock the full identity namespace before any snapshot reads; preserves occupancy
 # and accepted-country/geo anchors throughout conditional update and commit.
 s=once(s,"try{\n $core=[];", "try{\n $eng=mpg_query($db,\"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels','hotel_aliases','catalog_countries')\");if(count($eng)!==4)throw new RuntimeException('table_contract');foreach($eng as $t)if(strtoupper((string)$t['ENGINE'])!=='INNODB')throw new RuntimeException('nontransactional_table');\n mpg_query($db,\"SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id FOR UPDATE\");\n $core=[];")
 a=s.index(" $result=['schema'=>'hotel-match-residual-current-guards/1'")
 s=s[:a]+WRITE+"\n}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if(!is_file($dir.'/failure.json'))mpg_write($dir.'/failure.json',['operation_id'=>MPG_OP,'source_sha'=>$sha,'state'=>$committed?'unknown_after_commit':'rolled_back','error_class'=>get_class($e),'no_replay'=>true]);throw$e;}\n"
 assert s.count('UPDATE andromeda_hotel_identities SET')==1
 assert s.count('$db->commit()')==1 and 'START TRANSACTION READ ONLY' not in s
 assert 'operator_315' not in WRITE and 'operator_342' not in WRITE
 return s

if __name__=='__main__':
 s=build();Path(sys.argv[1]).write_text(s);print(json.dumps({'operation_id':OP,'planned_count':52,'bundle_sha256':hashlib.sha256(s.encode()).hexdigest()}))
