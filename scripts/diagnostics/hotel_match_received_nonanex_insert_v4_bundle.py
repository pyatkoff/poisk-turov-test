#!/usr/bin/env python3
"""Build one insert-only server-CURRENT transaction for the verified missing Biblio identities."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_apply_bundle as base

OPERATION_ID = "hotel-match-received-nonanex-insert-1971-20260916-v4"
V3_OPERATION_ID = "hotel-match-received-nonanex-apply-1971-20260916-v3"
V3_RUN_ID = 35053671498
V3_RESULT_SHA256 = "70f8262b9541829084e47f9a115e0b7d26cf9446628eaa73e9469cdc0775c32a"
TRIPLES = {
    ("625557015", "46462", 3406),
    ("625746958", "2000044235", 65881),
    ("610167368", "2000084551", 445),
    ("610175991", "135480", 295),
    ("610181893", "2000033706", 364),
    ("610108009", "2000038699", 5536),
    ("625076472", "203605", 1389),
    ("651351140", "1509", 434),
    ("675591202", "98", 1963),
    ("616084480", "732", 9226),
    ("610182727", "2000069328", 306),
    ("610187541", "2000043229", 76320),
    ("610133381", "2000061191", 65659),
    ("610226529", "2000081109", 38552),
    ("616120383", "2000023262", 30637),
    ("616574495", "2000062517", 28507),
    ("617029970", "10064", 1090),
    ("625380715", "145106", 1319),
    ("632834906", "2000041086", 476),
    ("640012445", "9299", 380),
}

INSERT_TAIL = r'''
    usort($out,static fn($a,$b)=>($b['frequency']<=>$a['frequency'])?:strcmp($a['supplier_namespace'].'|'.$a['native_hotel_id'],$b['supplier_namespace'].'|'.$b['native_hotel_id']));ksort($counts);arsort($reasons);$phase='insert_recheck';
    $valid=[];$writeHolds=[];
    foreach($out as $r){
        if($r['route']!=='guard_passed_prepared')continue;
        if($r['supplier_namespace']!=='operator_115'){$writeHolds[]=['native_hotel_id'=>$r['native_hotel_id'],'reason'=>'unexpected_namespace'];continue;}
        $q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='operator_115' AND external_hotel_id=? FOR UPDATE");$q->execute([$r['native_hotel_id']]);$cur=$q->fetch(PDO::FETCH_ASSOC);
        if($cur){$writeHolds[]=['native_hotel_id'=>$r['native_hotel_id'],'reason'=>'native_appeared_since_missing_snapshot','current_status'=>$cur['decision_status'],'current_local_hotel_id'=>$cur['local_hotel_id']===null?null:(int)$cur['local_hotel_id']];continue;}
        $ev=['schema'=>'received-nonanex-exact-missing-native/1','operation_id'=>HM_OP,'source_operation_id'=>'__PARENT_OP__','source_run_id'=>__PARENT_RUN__,'source_result_sha256'=>'__PARENT_RESULT__','source_report_sha256'=>'__REPORT_SHA__','v3_operation_id'=>'__V3_OP__','v3_run_id'=>__V3_RUN__,'v3_result_sha256'=>'__V3_RESULT__','source'=>['supplier_namespace'=>'operator_115','external_hotel_id'=>$r['native_hotel_id'],'andromeda_hotel_id'=>$r['andromeda_hotel_id'],'country_id'=>(int)$r['country_id'],'hotel_name'=>$r['hotel_name'],'original_name'=>$r['original_name'],'town'=>$r['town'],'request_sha256'=>$r['request_sha256'],'response_sha256'=>$r['response_sha256'],'frequency'=>(int)$r['frequency']],'decision'=>['local_hotel_id'=>(int)$r['local_hotel_id'],'decision_status'=>'accepted','support'=>$r['support']??[],'matched_forms'=>$r['matched_forms']??[],'countrywide_ids'=>$r['countrywide_ids']??[],'distances_km'=>$r['distances_km']??[],'rule'=>'server_current_countrywide_exact_primary_or_former_alias_plus_anchor_geo_manual_occupancy_qualifier_numeric_coordinate_guards']];
        $ev['__ACCEPT_KEY__']=['operation_id'=>HM_OP,'source_sha'=>$sha,'parent_operation_id'=>'__PARENT_OP__','parent_run_id'=>__PARENT_RUN__,'parent_result_sha256'=>'__PARENT_RESULT__','v3_result_sha256'=>'__V3_RESULT__','supplier_namespace'=>'operator_115','native_hotel_id'=>$r['native_hotel_id'],'andromeda_hotel_id'=>$r['andromeda_hotel_id'],'target_local_hotel_id'=>(int)$r['local_hotel_id'],'request_sha256'=>$r['request_sha256'],'response_sha256'=>$r['response_sha256'],'server_current_revalidated'=>true,'insert_only_missing_native'=>true];
        $json=json_encode($ev,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$new=hash('sha256',$json);
        $valid[]=['native_hotel_id'=>$r['native_hotel_id'],'andromeda_hotel_id'=>$r['andromeda_hotel_id'],'local_hotel_id'=>(int)$r['local_hotel_id'],'catalog_sha256'=>'__PARENT_RESULT__','new_evidence_sha256'=>$new,'evidence_json'=>$json,'frequency'=>(int)$r['frequency']];
    }
    $targets=[];foreach($valid as $w){if(isset($targets[$w['local_hotel_id']]))throw new RuntimeException('duplicate_valid_target');$targets[$w['local_hotel_id']]=true;}
    $checkpoint=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'validated_before_commit','parent_operation_id'=>'__PARENT_OP__','parent_run_id'=>__PARENT_RUN__,'parent_result_sha256'=>'__PARENT_RESULT__','v3_operation_id'=>'__V3_OP__','v3_run_id'=>__V3_RUN__,'v3_result_sha256'=>'__V3_RESULT__','plan_count'=>count($plan),'guard_route_counts'=>$counts,'guard_reason_counts'=>$reasons,'planned_inserts'=>array_map(static function($x){unset($x['evidence_json']);return $x;},$valid),'write_holds'=>$writeHolds,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
    hm_write($dir,'precommit.json',$checkpoint);
    /* UPDATE andromeda_hotel_identities SET intentionally not used: this operation is insert-only. */
    $ins=$db->prepare('INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES(?,?,?,?,?,?,?)');
    foreach($valid as $w){$ins->execute(['operator_115',$w['native_hotel_id'],$w['local_hotel_id'],'accepted',$w['catalog_sha256'],$w['new_evidence_sha256'],$w['evidence_json']]);if($ins->rowCount()!==1)throw new RuntimeException('native_insert_count');}
    $db->commit();$committed=true;$phase='readback';$readback=[];
    foreach($valid as $w){$q=$db->prepare("SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='operator_115' AND external_hotel_id=?");$q->execute([$w['native_hotel_id']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);if(count($rows)!==1)throw new RuntimeException('readback_count');$cur=$rows[0];$ev=json_decode((string)$cur['evidence_json'],true);
        if($cur['supplier_namespace']!=='operator_115'||$cur['external_hotel_id']!==$w['native_hotel_id']||$cur['decision_status']!=='accepted'||(int)$cur['local_hotel_id']!==$w['local_hotel_id']||$cur['catalog_sha256']!==$w['catalog_sha256']||$cur['evidence_sha256']!==$w['new_evidence_sha256']||hash('sha256',(string)$cur['evidence_json'])!==$w['new_evidence_sha256']||!is_array($ev)||(($ev['__ACCEPT_KEY__']['operation_id']??'')!==HM_OP))throw new RuntimeException('post_commit_readback_mismatch');
        $readback[]=['supplier_namespace'=>'operator_115','native_hotel_id'=>$w['native_hotel_id'],'andromeda_hotel_id'=>$w['andromeda_hotel_id'],'local_hotel_id'=>(int)$cur['local_hotel_id'],'decision_status'=>$cur['decision_status'],'evidence_sha256'=>$cur['evidence_sha256'],'frequency'=>$w['frequency']];
    }
    $phase='receipt';$result=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_committed','schema'=>'hotel-match-received-nonanex-insert/1','server_current'=>true,'transaction'=>'SERIALIZABLE identities locked before classification/insert','parent_operation_id'=>'__PARENT_OP__','parent_run_id'=>__PARENT_RUN__,'parent_result_sha256'=>'__PARENT_RESULT__','v3_operation_id'=>'__V3_OP__','v3_run_id'=>__V3_RUN__,'v3_result_sha256'=>'__V3_RESULT__','plan_count'=>count($plan),'guard_route_counts'=>$counts,'guard_reason_counts'=>$reasons,'guard_passed_frequency'=>$passedFreq,'written'=>count($readback),'written_frequency'=>array_sum(array_column($readback,'frequency')),'post_commit_readback'=>$readback,'write_holds'=>$writeHolds,'database_writes'=>count($readback),'mapping_writes'=>count($readback),'supplier_calls'=>0,'tourvisor_calls'=>0,'operator_5_writes'=>0,'no_replay'=>true];
    $digest=hm_write($dir,'result.json',$result);hm_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_committed','result_sha256'=>$digest,'readback_verified'=>true,'written'=>count($readback),'database_writes'=>count($readback),'mapping_writes'=>count($readback),'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);echo json_encode(['plan_count'=>count($plan),'guard_route_counts'=>$counts,'written'=>count($readback),'written_frequency'=>$result['written_frequency'],'write_holds'=>count($writeHolds)],JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$state=$committed?'unknown_after_commit':'rolled_back';$digest=hm_write($dir,'failure.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>$state,'phase'=>$phase,'error_class'=>get_class($e),'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);hm_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>$state,'failure_sha256'=>$digest,'readback_verified'=>false,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);fwrite(STDERR,'NONANEX_INSERT_FAILED:'.$phase."\n");exit(2);}
'''


def assert_missing_snapshot() -> None:
    raw = base.parent.REPORT.read_bytes()
    if hashlib.sha256(raw).hexdigest() != base.parent.REPORT_SHA256:
        raise SystemExit("report_digest_mismatch")
    report = json.loads(raw)
    found = set()
    for c in report.get("candidates", []):
        f = c.get("fact") or {}
        a = c.get("current_anchor") or {}
        key = (str(f.get("native_hotel_id", "")), str(f.get("andromeda_hotel_id", "")), a.get("local_hotel_id"))
        if key not in TRIPLES:
            continue
        if str(f.get("operator_key")) != "115" or c.get("current_native") is not None or c.get("dependency_reasons") or c.get("review_signals") or c.get("inherited_identity_holds"):
            raise SystemExit(f"missing_snapshot_contract:{key}")
        found.add(key)
    if found != TRIPLES:
        raise SystemExit(f"missing_snapshot_count:{len(found)}")


def build() -> str:
    assert_missing_snapshot()
    base.OPERATION_ID = OPERATION_ID
    base.TRIPLES = TRIPLES
    base.NEW_TAIL = (INSERT_TAIL
        .replace("__REPORT_SHA__", base.parent.REPORT_SHA256)
        .replace("__V3_OP__", V3_OPERATION_ID)
        .replace("__V3_RUN__", str(V3_RUN_ID))
        .replace("__V3_RESULT__", V3_RESULT_SHA256))
    s = base.build()
    marker = "$r['response_sha256']=$p['response_sha256'];$out[]=$r;"
    replacement = "$r['response_sha256']=$p['response_sha256'];$r['hotel_name']=$p['hotel_name'];$r['original_name']=$p['original_name'];$r['town']=$p['town'];$r['country_id']=$p['country_id'];$out[]=$r;"
    if s.count(marker) != 1:
        raise SystemExit("source_fields_marker_changed")
    s = s.replace(marker, replacement, 1)
    if OPERATION_ID not in s or V3_RESULT_SHA256 not in s:
        raise SystemExit("v4_provenance_missing")
    if "$up=$db->prepare" in s or "conditional_write_mismatch" in s:
        raise SystemExit("update_writer_leaked")
    if s.count("INSERT INTO andromeda_hotel_identities") != 1:
        raise SystemExit("insert_writer_count")
    if "native_appeared_since_missing_snapshot" not in s or "insert_only_missing_native" not in s:
        raise SystemExit("missing_insert_guard")
    return s


if __name__ == "__main__":
    s = build()
    Path(sys.argv[1]).write_text(s)
    print(json.dumps({
        "operation_id": OPERATION_ID,
        "parent_operation_id": base.PARENT_OPERATION_ID,
        "parent_run_id": base.PARENT_RUN_ID,
        "parent_result_sha256": base.PARENT_RESULT_SHA256,
        "v3_operation_id": V3_OPERATION_ID,
        "v3_run_id": V3_RUN_ID,
        "v3_result_sha256": V3_RESULT_SHA256,
        "planned_count": 20,
        "planned_frequency": 167,
        "bundle_sha256": hashlib.sha256(s.encode()).hexdigest(),
        "maximum_database_writes": 20,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "insert_only": True,
    }, sort_keys=True))