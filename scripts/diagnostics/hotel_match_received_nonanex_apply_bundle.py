#!/usr/bin/env python3
"""Build one guarded server-CURRENT apply for the fresh non-ANEX operator_115 clean delta."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_current_bundle as parent

ROOT = Path(__file__).resolve().parents[2]
OPERATION_ID = "hotel-match-received-nonanex-apply-1971-20260916-v2"
PARENT_OPERATION_ID = "hotel-match-received-nonanex-current-1971-20260916-v1"
PARENT_RUN_ID = 35049809798
PARENT_RESULT_SHA256 = "9c851185282fb85bb56e281a65fb56f6c3daf26686cd3b6a72a7dbdc355f41e6"
ACCEPT_KEY = "received_nonanex_acceptance"

TRIPLES = {
    ("016472", "2000179576", 48397),
    ("034201", "2000007846", 36840),
    ("034934", "2000145691", 74673),
    ("035632", "2000027234", 68592),
    ("036954", "2000022701", 34318),
    ("039396", "2000053798", 75670),
    ("042883", "2000082843", 147693),
    ("043126", "2000043714", 72674),
    ("043717", "2000005871", 36236),
    ("043781", "2000154606", 114786),
    ("044054", "2000155884", 107156),
    ("044134", "2000023044", 34325),
    ("044200", "2000161488", 124331),
    ("044217", "2000200934", 159108),
    ("044416", "2000167095", 159710),
    ("044422", "2000099550", 135314),
    ("044702", "2000210043", 159837),
    ("045285", "2000243392", 160704),
    ("045421", "2000254761", 152245),
    ("047535", "2000214133", 161914),
}

NEW_TAIL = r'''
    usort($out,static fn($a,$b)=>($b['frequency']<=>$a['frequency'])?:strcmp($a['supplier_namespace'].'|'.$a['native_hotel_id'],$b['supplier_namespace'].'|'.$b['native_hotel_id']));ksort($counts);arsort($reasons);$phase='write';
    $valid=[];$writeHolds=[];
    foreach($out as $r){
        if($r['route']!=='guard_passed_prepared')continue;
        if($r['supplier_namespace']!=='operator_115'){$writeHolds[]=['native_hotel_id'=>$r['native_hotel_id'],'reason'=>'unexpected_namespace'];continue;}
        $q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='operator_115' AND external_hotel_id=? FOR UPDATE");$q->execute([$r['native_hotel_id']]);$cur=$q->fetch(PDO::FETCH_ASSOC);
        if(!$cur||$cur['decision_status']!=='pending'||$cur['local_hotel_id']!==null){$writeHolds[]=['native_hotel_id'=>$r['native_hotel_id'],'reason'=>'native_not_current_pending_null'];continue;}
        $ev=json_decode((string)($cur['evidence_json']??''),true);if(!is_array($ev))$ev=[];
        if(hm_protected($ev)||array_key_exists('__ACCEPT_KEY__',$ev)){$writeHolds[]=['native_hotel_id'=>$r['native_hotel_id'],'reason'=>'native_evidence_protected_or_prior_acceptance'];continue;}
        $prior=$cur['evidence_sha256'];
        $ev['__ACCEPT_KEY__']=['operation_id'=>HM_OP,'source_sha'=>$sha,'parent_operation_id'=>'__PARENT_OP__','parent_run_id'=>__PARENT_RUN__,'parent_result_sha256'=>'__PARENT_RESULT__','supplier_namespace'=>'operator_115','native_hotel_id'=>$r['native_hotel_id'],'andromeda_hotel_id'=>$r['andromeda_hotel_id'],'target_local_hotel_id'=>(int)$r['local_hotel_id'],'request_sha256'=>$r['request_sha256'],'response_sha256'=>$r['response_sha256'],'prior_evidence_sha256'=>$prior,'rule'=>'server_current_countrywide_exact_primary_or_former_alias_plus_anchor_geo_manual_occupancy_qualifier_numeric_coordinate_guards','server_current_revalidated'=>true];
        $json=json_encode($ev,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$new=hash('sha256',$json);
        $valid[]=['native_hotel_id'=>$r['native_hotel_id'],'andromeda_hotel_id'=>$r['andromeda_hotel_id'],'local_hotel_id'=>(int)$r['local_hotel_id'],'prior_evidence_sha256'=>$prior,'new_evidence_sha256'=>$new,'evidence_json'=>$json,'frequency'=>(int)$r['frequency']];
    }
    $targets=[];foreach($valid as $w){if(isset($targets[$w['local_hotel_id']]))throw new RuntimeException('duplicate_valid_target');$targets[$w['local_hotel_id']]=true;}
    $checkpoint=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'validated_before_commit','parent_operation_id'=>'__PARENT_OP__','parent_run_id'=>__PARENT_RUN__,'parent_result_sha256'=>'__PARENT_RESULT__','plan_count'=>count($plan),'guard_route_counts'=>$counts,'guard_reason_counts'=>$reasons,'planned_writes'=>array_map(static function($x){unset($x['evidence_json']);return $x;},$valid),'write_holds'=>$writeHolds,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
    hm_write($dir,'precommit.json',$checkpoint);
    $up=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='operator_115' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256 <=> ?");
    foreach($valid as $w){$up->execute([$w['local_hotel_id'],$w['new_evidence_sha256'],$w['evidence_json'],$w['native_hotel_id'],$w['prior_evidence_sha256']]);if($up->rowCount()!==1)throw new RuntimeException('conditional_write_mismatch');}
    $db->commit();$committed=true;$phase='readback';$readback=[];
    foreach($valid as $w){$q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='operator_115' AND external_hotel_id=?");$q->execute([$w['native_hotel_id']]);$cur=$q->fetch(PDO::FETCH_ASSOC);if(!$cur)throw new RuntimeException('readback_missing');$ev=json_decode((string)$cur['evidence_json'],true);
        if($cur['decision_status']!=='accepted'||(int)$cur['local_hotel_id']!==$w['local_hotel_id']||$cur['evidence_sha256']!==$w['new_evidence_sha256']||hash('sha256',(string)$cur['evidence_json'])!==$w['new_evidence_sha256']||!is_array($ev)||(($ev['__ACCEPT_KEY__']['operation_id']??'')!==HM_OP))throw new RuntimeException('post_commit_readback_mismatch');
        $readback[]=['supplier_namespace'=>'operator_115','native_hotel_id'=>$w['native_hotel_id'],'andromeda_hotel_id'=>$w['andromeda_hotel_id'],'local_hotel_id'=>(int)$cur['local_hotel_id'],'decision_status'=>$cur['decision_status'],'evidence_sha256'=>$cur['evidence_sha256'],'frequency'=>$w['frequency']];
    }
    $phase='receipt';$result=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_committed','schema'=>'hotel-match-received-nonanex-apply/1','server_current'=>true,'transaction'=>'SERIALIZABLE identities locked before classification/write','parent_operation_id'=>'__PARENT_OP__','parent_run_id'=>__PARENT_RUN__,'parent_result_sha256'=>'__PARENT_RESULT__','plan_count'=>count($plan),'guard_route_counts'=>$counts,'guard_reason_counts'=>$reasons,'guard_passed_frequency'=>$passedFreq,'written'=>count($readback),'written_frequency'=>array_sum(array_column($readback,'frequency')),'post_commit_readback'=>$readback,'write_holds'=>$writeHolds,'database_writes'=>count($readback),'mapping_writes'=>count($readback),'supplier_calls'=>0,'tourvisor_calls'=>0,'operator_5_writes'=>0,'no_replay'=>true];
    $digest=hm_write($dir,'result.json',$result);hm_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_committed','result_sha256'=>$digest,'readback_verified'=>true,'written'=>count($readback),'database_writes'=>count($readback),'mapping_writes'=>count($readback),'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);echo json_encode(['plan_count'=>count($plan),'guard_route_counts'=>$counts,'written'=>count($readback),'written_frequency'=>$result['written_frequency']],JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$state=$committed?'unknown_after_commit':'rolled_back';$digest=hm_write($dir,'failure.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>$state,'phase'=>$phase,'error_class'=>get_class($e),'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);hm_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>$state,'failure_sha256'=>$digest,'readback_verified'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);fwrite(STDERR,'NONANEX_APPLY_FAILED:'.$phase."\n");exit(2);}
'''


def build() -> str:
    full = parent.build_plan()
    plan = [
        p for p in full
        if (p["native_hotel_id"], p["andromeda_hotel_id"], p["local_hotel_id"]) in TRIPLES
    ]
    keys = {(p["native_hotel_id"], p["andromeda_hotel_id"], p["local_hotel_id"]) for p in plan}
    if keys != TRIPLES or len(plan) != 20:
        raise SystemExit(f"fresh_delta_mismatch:{len(plan)}")
    if {p["operator_key"] for p in plan} != {"115"} or {p["supplier_namespace"] for p in plan} != {"operator_115"}:
        raise SystemExit("namespace_mismatch")
    if len({p["local_hotel_id"] for p in plan}) != 20 or sum(p["frequency"] for p in plan) != 167:
        raise SystemExit("delta_shape_mismatch")

    s = parent.PHP.replace("__PLAN__", json.dumps(plan, ensure_ascii=False, separators=(",", ":"))).replace("__OP__", OPERATION_ID)
    old_tx = "$phase='current';$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');"
    new_tx = "$phase='current';$db->exec('SET SESSION innodb_lock_wait_timeout=30');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();$committed=false;$db->query(\"SELECT supplier_namespace,external_hotel_id FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE\")->fetchAll(PDO::FETCH_ASSOC);"
    if s.count(old_tx) != 1:
        raise SystemExit("transaction_marker_changed")
    s = s.replace(old_tx, new_tx, 1)
    bootstrap = "$phase='bootstrap';$db=null;"
    if s.count(bootstrap) != 1:
        raise SystemExit("bootstrap_marker_changed")
    s = s.replace(bootstrap, "$phase='bootstrap';$db=null;$committed=false;", 1)

    marker = "    usort($out,static fn($a,$b)=>($b['frequency']<=>$a['frequency'])?:strcmp($a['supplier_namespace'].'|'.$a['native_hotel_id'],$b['supplier_namespace'].'|'.$b['native_hotel_id']));ksort($counts);arsort($reasons);$db->exec('ROLLBACK');$phase='receipt';"
    idx = s.find(marker)
    if idx < 0:
        raise SystemExit("tail_marker_changed")
    tail = (NEW_TAIL
            .replace("__ACCEPT_KEY__", ACCEPT_KEY)
            .replace("__PARENT_OP__", PARENT_OPERATION_ID)
            .replace("__PARENT_RUN__", str(PARENT_RUN_ID))
            .replace("__PARENT_RESULT__", PARENT_RESULT_SHA256))
    s = s[:idx] + tail

    if s.count("UPDATE andromeda_hotel_identities SET") != 1:
        raise SystemExit("writer_count")
    if "START TRANSACTION READ ONLY" in s or "operator_5' AND external_hotel_id" in s:
        raise SystemExit("unsafe_generated_bundle")
    if s.count("$db->commit()") != 1 or "SERIALIZABLE" not in s or "FOR UPDATE" not in s:
        raise SystemExit("transaction_contract")
    return s


if __name__ == "__main__":
    s = build()
    Path(sys.argv[1]).write_text(s)
    print(json.dumps({
        "operation_id": OPERATION_ID,
        "parent_operation_id": PARENT_OPERATION_ID,
        "planned_count": 20,
        "planned_frequency": 167,
        "bundle_sha256": hashlib.sha256(s.encode()).hexdigest(),
        "maximum_database_writes": 20,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
    }, sort_keys=True))