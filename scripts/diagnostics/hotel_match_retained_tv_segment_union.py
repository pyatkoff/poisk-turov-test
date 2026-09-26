"""Compose retained TV segments locally; reuse existing proof and collision rules.

No network, server, DB, subprocess, supplier client or identity writes. Historical
CURRENT labels remain dated evidence and never authorize a new acceptance.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import re
import zipfile
from collections import Counter, defaultdict
from pathlib import Path
import hotel_match_retained_candidate_review as core

SEGMENTS = {'first450': {'file': 'match_first450_v20.zip', 'artifact_id': 10857072001, 'zip_sha256': 'f0e02e4f991e14509d530b01733ad1a046878c8bfa6a79c467139dc829c02cda', 'result_sha256': '43a71d55d649ccbece446e3a8a36fc6df59e141215c1de6085b0821c23642545', 'receipt_sha256': '74a4a89014a41490125e6a27472e6e28ba73d7087b3f1b188e1b009822a9c2bd', 'operation': 'hotel-match-live30-common4-historical-hold-current-1971-20260925-v20', 'state': 'completed_read_only_current_audit', 'source_sha': 'ba415d91f5afa435716e008855ae2d050603c734', 'row_count': 283, 'children': {'hotel-match-live30-common4-acquire-1971-20260923-o0-n100-v1': '1d10e02a1a541a242b7466b3eab99887203c005ee270469f2c351179a3387faa', 'hotel-match-live30-common4-acquire-1971-20260923-o100-n100-v1': 'e78c23bce102bfb07dd45bcef82f65c88585160747f68c0222dfd82a1f929f82', 'hotel-match-live30-common4-acquire-1971-20260923-o200-n100-v1': '6fe13e366ab5fd6b130389fafd0c769b3bc80ce676b46a82f9d402f7296179f4', 'hotel-match-live30-common4-acquire-1971-20260923-o300-n80-v1': 'c6aa57404a3261ed0d9e82d93fba522cd326e000be26b5bf42abd319e28e9257', 'hotel-match-live30-common4-acquire-1971-20260923-o380-n40-v1': '109b819ab7845c9e50242e607d275e1c4dc1c5b6e6851960596a3f3aa01748d4', 'hotel-match-live30-common4-acquire-1971-20260923-o420-n30-v1': '6186f7441a2f9af365117927c6f98c1c8afd0d5c5db23a77c66304a46d965572'}}, 'c0c30': {'file': 'match_c0_c30_v11.zip', 'artifact_id': 10775291892, 'zip_sha256': 'f458ba0c645c7f12c400f9997e4d1bd985d3e78dbe6b5e20564130f4b83bd190', 'result_sha256': 'bec4c11bcb9099a0e8ad61c1d0afce81236e73ab96e8137057e38828765502a2', 'receipt_sha256': 'b7125f2ab87e8ca15de1d4a16d5031a9ec35ff31e1ec9ac444b81ac5ea944627', 'operation': 'hotel-match-common4-continuation-current-1971-20260923-v11', 'state': 'completed_read_only_continuation_current', 'source_sha': 'c96cb2bf481727b7cf35ffa544f9238087e32496', 'row_count': 13, 'children': {'hotel-match-live30-common4-continuation-acquire-1971-20260923-c0-n30-v1': '6f1d77803798db25fce9b8eada487a465de018b5d2d64e5df7e06195c4debd48', 'hotel-match-live30-common4-continuation-acquire-1971-20260923-c30-n5-v1': '2636d1223179fd6ec48f4e1352525e321aa4a6de9b22b90cc9ddf5559a16fd84'}}}


def load_segment(path: Path, kind: str) -> dict:
    spec = SEGMENTS[kind]
    core.require(path.is_file() and not path.is_symlink(), "segment_file")
    core.require(path.stat().st_size <= 2 * 1024 * 1024, "segment_zip_cap")
    core.require(hashlib.sha256(path.read_bytes()).hexdigest() == spec["zip_sha256"], "segment_zip_hash")
    values = []
    with zipfile.ZipFile(path) as archive:
        for member, pin in (("result.json", "result_sha256"), ("receipt.json", "receipt_sha256")):
            core.require(archive.namelist().count(member) == 1, "segment_member_count")
            core.require(archive.getinfo(member).file_size <= 8 * 1024 * 1024, "segment_member_cap")
            raw = archive.read(member)
            core.require(hashlib.sha256(raw).hexdigest() == spec[pin], "segment_member_hash")
            values.append(json.loads(raw, object_pairs_hook=core.unique_object))
    result, receipt = values
    core.require(receipt.get("result_sha256") == spec["result_sha256"] and
                 receipt.get("readback_verified") is True, "segment_receipt")
    for field in ("operation", "state"):
        core.require(receipt.get(field) == result.get(field) == spec[field], "segment_" + field)
    segment_edges(result, kind)
    return result


def segment_edges(result: dict, kind: str) -> list[dict]:
    spec = SEGMENTS[kind]
    for field in ("operation", "state", "source_sha"):
        core.require(result.get(field) == spec[field], "segment_" + field)
    core.require(len(result.get("rows", [])) == spec["row_count"], "segment_rows")
    children = result.get("children", [])
    core.require(len(children) == len(spec["children"]) and
                 {c["operation"]: c["result_sha256"] for c in children} == spec["children"], "segment_children")
    for field in ("provider_http_calls", "supplier_calls", "database_writes", "mapping_writes"):
        core.require(type(result.get(field)) is int and result[field] == 0, "segment_zero_" + field)
    core.require(result.get("safe_to_write_now") is False, "segment_safety")
    namespaces = {13: "anex", 18: "bgoperator", **core.BRIDGES}
    edges = []
    for index, row in enumerate(result["rows"]):
        operator = row["operator_id"]
        core.require(type(operator) is int and namespaces.get(operator) == row["supplier_namespace"], "segment_namespace")
        local = int(core.identifier(row["tv_hotel_id"]))
        native = core.identifier(row["external_hotel_id"])
        core.require(int(core.identifier(row["catalog_hotel"]["id"])) == local, "segment_target_binding")
        core.require(spec["children"].get(row["source_operation"]) == row["source_result_sha256"], "segment_child_hash")
        core.require(row.get("safe_to_write_now") is False, "segment_row_safety")
        for key in ("operator_link_sha256", "tour_id_sha256", "search_id_sha256"):
            core.require(isinstance(row.get(key), str) and re.fullmatch(r"[0-9a-f]{64}", row[key]) is not None, "segment_proof_hash")
        edges.append({"tv_hotel_id": local, "operator_id": operator, "namespace": row["supplier_namespace"],
                      "positive_native_candidates": [native], "operator_link_sha256": row["operator_link_sha256"],
                      "tour_id_sha256": row["tour_id_sha256"], "source_result_sha256": row["source_result_sha256"],
                      "provenance": {"archive_result_sha256": spec["result_sha256"], "row_path": "/rows/" + str(index),
                                     "row_canonical_sha256": core.digest(row)}})
    return edges


def compose(tv: dict, samo: dict, bg: dict, tail: dict, early: dict, history: dict,
            segments: dict[str, dict]) -> dict:
    core.require(set(segments) == set(SEGMENTS), "segment_set")
    before = core.analyse_expanded(tv, samo, bg, tail, history, early)
    previous = {r["local_hotel_id"]: r for r in before["candidates_with_proven_tv_lanes"]}
    edges = [{**e, "provenance": {"archive_result_sha256": core.PINS["tv"][1],
              "row_path": "/single_native_edges/" + str(i), "row_canonical_sha256": core.digest(e)}}
             for i, e in enumerate(tv["single_native_edges"])]
    edges += core.tail_edges(tail) + core.tail_edges(early, early=True)
    scope = {d["local_hotel_id"] for d in samo["dossiers"]}
    segment_counts = {}
    for kind, document in sorted(segments.items()):
        extra = segment_edges(document, kind)
        segment_counts[kind] = {"rows": len(extra), "overlap_rows": sum(e["tv_hotel_id"] in scope for e in extra),
                               "overlap_hotels": len({e["tv_hotel_id"] for e in extra} & scope)}
        edges += extra
    indexes = core.expanded_indexes(edges, samo["dossiers"])
    risks, anchors = defaultdict(set), defaultdict(set)
    for row in history["risks"]:
        risks[(row["local_hotel_id"], row["andromeda_catalog_id"])].update(row["historical_hold_reasons"])
    for row in before["candidates_with_proven_tv_lanes"]:
        risks[(row["local_hotel_id"], row["andromeda_catalog_id"])].update(row["historical_review_reasons"])
    for document in (tail, early, *segments.values()):
        for row in document["rows"]:
            for anchor in row.get("anchors", []):
                if anchor.get("supplier_namespace") == "andromeda_catalog" and anchor.get("decision_status") == "accepted":
                    anchors[core.identifier(anchor["external_hotel_id"])].add(int(core.identifier(anchor["local_hotel_id"])))
    for collision in core.analyse(tv, samo, bg)["strict_candidate_catalog_collisions"]:
        for local in collision["local_hotel_ids"]:
            risks[(local, collision["catalog_id"])].add("v65_selected_catalog_multiple_targets")
    rows, buckets = [], defaultdict(list)
    for dossier in samo["dossiers"]:
        local, best = dossier["local_hotel_id"], 0
        for candidate in dossier["candidates"]:
            proof = core.expanded_proofs(local, candidate, edges, indexes)
            best = max(best, len(proof))
            if not proof:
                continue
            catalog = core.identifier(candidate["catalog_id"])
            reasons = set(risks[(local, catalog)])
            if anchors[catalog] - {local}:
                reasons.add("segment_historical_source_other_target")
            # One exact operator is sufficient; lane count does not split the queue.
            disposition = "historical_review" if reasons else "current_review"
            rows.append({"local_hotel_id": local, "hotel_name": dossier["hotel_name"], "andromeda_catalog_id": catalog,
                         "candidate_names": candidate["names"], "independent_tv_lane_count": len(proof),
                         "proven_lanes": proof, "historical_review_reasons": sorted(reasons), "disposition": disposition,
                         "dossier_sha256": core.digest(dossier), "candidate_sha256": core.digest(candidate),
                         "original_status": dossier["status"], "safe_to_write_now": False})
        buckets[str(best)].append(local)
    current = {r["local_hotel_id"] for r in rows}
    return {"schema": "match_retained175_tv_segment_union_v2", "mode": "offline_evidence_only_not_acceptance",
            "input_count": len(scope), "candidate_pairs_reviewed": sum(len(d["candidates"]) for d in samo["dossiers"]),
            "input_hashes": {**before["input_hashes_zip_result_receipt"], **{k: {f: v[f] for f in ("artifact_id", "zip_sha256", "result_sha256", "receipt_sha256")} for k, v in SEGMENTS.items()}},
            "history_summary_sha256": core.HISTORY_SHA, "segment_counts": segment_counts,
            "tv_input_rows": len(edges), "tv_unique_edges": len({(e["tv_hotel_id"], e["namespace"], core.identifier(e["positive_native_candidates"][0])) for e in edges}),
            "tv_unique_hotels_with_evidence": len({e["tv_hotel_id"] for e in edges}),
            "before_proven_candidate_hotels": len(previous), "proven_candidate_hotels": len(current),
            "new_candidate_ids": sorted(current - set(previous)), "lost_candidate_ids": sorted(set(previous) - current),
            "independent_operator_distribution": {k: len(v) for k, v in sorted(buckets.items())},
            "dossier_ids_by_exact_independent_tv_lane_count": {k: sorted(v) for k, v in sorted(buckets.items())},
            "review_disposition_counts": dict(sorted(Counter(r["disposition"] for r in rows).items())),
            "required_exact_operator_lanes": 1, "second_operator_required": False,
            "candidates": rows, "current_validation_performed": False, "safe_to_write_now": False,
            "provider_http_calls": 0, "database_reads": 0, "database_writes": 0, "mapping_writes": 0,
            "accepted_mapping_count": 0, "zero_proof_means": "no_proof_in_these_archives_not_global_absence",
            "scope_limit": "v63+r1/r2+c35/c135+first450+c0/c30 retained evidence, not the full catalogue or fresh DB",
            "historical_risks_preserved_not_cleared": True,
            "execution_restriction": "v66_server_workflow_denied_no_retry_or_alternate_executor"}


def run(input_dir: Path, history_path: Path) -> dict:
    values = [core.load_archive(input_dir / name, kind) for kind, name in (
        ("tv", "match_v63_secondary.zip"), ("samo", "match_v65_result.zip"), ("bg", "match_bg_dictionary_v5.zip"),
        ("tv_tail", "match_tv_tail_r1_r2.zip"), ("tv_early", "match_tv_c35_c135.zip"))]
    segments = {k: load_segment(input_dir / spec["file"], k) for k, spec in SEGMENTS.items()}
    return compose(*values, core.load_history(history_path), segments)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    for option in ("input-dir", "history-summary", "output"):
        parser.add_argument("--" + option, required=True, type=Path)
    args = parser.parse_args()
    result = run(args.input_dir, args.history_summary)
    with args.output.open("x", encoding="utf-8") as output:
        json.dump(result, output, ensure_ascii=False, sort_keys=True, indent=2)
        output.write("\n")
    print(json.dumps({"proof_candidates": result["proven_candidate_hotels"], "new_candidate_ids": result["new_candidate_ids"],
                      "dispositions": result["review_disposition_counts"], "report_sha256": hashlib.sha256(args.output.read_bytes()).hexdigest()}))


if __name__ == "__main__":
    main()
