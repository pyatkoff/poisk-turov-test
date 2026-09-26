"""Offline evidence review of sealed v63/v65/BG archives; never a resolver/writer.

All 175 dossiers are examined, including review/HOLD. Two operators observed in
SAMO alone are not two cross-source proofs. BG prefix patterns and direct-ANEX
numeric equality are diagnostics only. No network, subprocess or database I/O.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import zipfile
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

PINS = {
    "tv": ("0c26f52918f3cd9bdf1c6e55cae63b7428f46902e7558f5b48c3884cf4aefd44",
           "dc4ad9e16b5f800a363f406965e318b6eb69bdb2ea77eb0b856cfe4c010fce9c",
           "e7d3f1f72c55ee65d1612bf8fffbfc5f26475be657f7ff8bf4903c7ec46a5618"),
    "samo": ("033d372e5aeb4d495b9fe542c148c2d4eb080889b5eb5ca08a5642837e2dbaf8",
             "42829f8f7a7988f3f033bfd8e377758b537ccb95c3ace9a09d953191bfe17810",
             "46792a26c166c597602814afc217e08e07709d2ed3aa6941ab291a418e28c626"),
    "bg": ("0f3f82ba6716155bbfc74a44c7b90df7bca88a944864450b73b1b8893b73a3bf",
           "b52da9feaa87caea3800b6434fbcaf75d7fc21ff8742824074cef02632758964",
           "c9b5c0a9c6b24a0258f6be2510343b63a81ab4ca2f7aa9c504b2829ecf75fa06"),
}
CHILD_HASHES = {
    "hotel-match-live234-tv-secondary-1971-20260923-o0-n78-v1": "fb8cb7d6acbcc921aa1d6f8a1399190e4b0fb2e418d23c9ac0c84c6e470c20e4",
    "hotel-match-live234-tv-secondary-1971-20260923-o78-n78-v1": "28f9dae4037d5e296751616d8cdb7ae753382d215ee628d2a3d1c4c29a935988",
    "hotel-match-live234-tv-secondary-1971-20260923-o156-n78-v1": "0b396a1132ab21a1a7ad21eb777be988a8d1fbd432912b3bef19b45940dcc4de",
}
BRIDGES = {25: "operator_315", 43: "operator_342"}
NS = ("operator_5", "operator_115", "operator_315", "operator_342")


def require(ok: bool, reason: str) -> None:
    if not ok:
        raise ValueError(reason)


def digest(value: Any) -> str:
    raw = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(raw).hexdigest()


def identifier(value: Any) -> str:
    require(type(value) in (int, str), "id_type")
    text = str(value)
    require(re.fullmatch(r"[1-9][0-9]{0,21}", text) is not None, "id_shape")
    return text


def ids(values: list) -> list[str]:
    return sorted({identifier(v) for v in values}, key=lambda x: (len(x), x))


def unique_object(pairs: list) -> dict:
    result = {}
    for key, value in pairs:
        require(key not in result, "duplicate_json_key")
        result[key] = value
    return result


def load_archive(path: Path, kind: str) -> dict:
    require(not path.is_symlink() and path.is_file(), "input_file")
    require(path.stat().st_size <= 2 * 1024 * 1024, "zip_cap")
    raw = path.read_bytes()
    require(hashlib.sha256(raw).hexdigest() == PINS[kind][0], "zip_hash")
    with zipfile.ZipFile(path) as archive:
        values = []
        for member, expected in zip(("result.json", "receipt.json"), PINS[kind][1:]):
            require(archive.namelist().count(member) == 1, "member_count")
            require(archive.getinfo(member).file_size <= 8 * 1024 * 1024, "member_cap")
            content = archive.read(member)
            require(hashlib.sha256(content).hexdigest() == expected, "member_hash")
            values.append(json.loads(content, object_pairs_hook=unique_object))
    result, receipt = values
    require(receipt["result_sha256"] == PINS[kind][1], "receipt_binding")
    require(result["operation"] == receipt["operation"], "operation_binding")
    require(result["state"] == receipt["state"], "state_binding")
    require(result["mapping_writes"] == result["database_writes"] == 0, "source_writes")
    if kind in ("tv", "samo"):
        require(receipt.get("readback_verified") is True, "source_readback")
    return result


def validate_tv(edges: list[dict]) -> None:
    require(len(edges) == 48, "tv_edge_count")
    for edge in edges:
        require(edge["state"] == "detail_identity_verified", "tv_state")
        require(edge["link_state"] == "captured_single_native", "tv_link_state")
        require(len(ids(edge["positive_native_candidates"])) == 1, "tv_native")
        expected = "bgoperator" if edge["operator_id"] == 18 else BRIDGES.get(edge["operator_id"])
        require(expected is not None and edge["namespace"] == expected, "tv_namespace")
        require(CHILD_HASHES.get(edge["source_child"]) == edge["source_result_sha256"], "child_hash")
        for field in ("operator_link_sha256", "tour_id_sha256"):
            require(re.fullmatch(r"[0-9a-f]{64}", edge[field]) is not None, "edge_hash")


def support(direct: list, operator: list) -> str:
    a, b = ids(direct), ids(operator)
    if len(a) > 1 or len(b) > 1:
        return "support_collision"
    if not a or not b:
        return "none"
    return "support_equal" if a == b else "support_different"


def exact_proofs(local: int, candidate: dict, edges: list, native_catalog: dict) -> list:
    """Only proven provider-local bridges count. No name/price/BG/ANEX shortcut."""
    out = {}
    for edge in edges:
        ns = BRIDGES.get(edge["operator_id"])
        if ns is None or edge["tv_hotel_id"] != local:
            continue
        a = ids(edge["positive_native_candidates"])
        b = ids(candidate["lanes"][ns]["native_ids"])
        if len(a) != 1 or a != b:
            continue
        targets = {e["tv_hotel_id"] for e in edges if BRIDGES.get(e["operator_id"]) == ns
                   and a[0] in ids(e["positive_native_candidates"])}
        if len(targets) != 1 or len(native_catalog[(ns, a[0])]) != 1:
            continue
        out[ns] = {"namespace": ns, "native_id": a[0],
                   "tv_link_sha256": edge["operator_link_sha256"],
                   "tv_tour_sha256": edge["tour_id_sha256"],
                   "tv_child_result_sha256": edge["source_result_sha256"]}
    return [out[ns] for ns in sorted(out)]


def bg_evidence(local: int, candidate: dict, edges: list, dictionary: dict) -> dict:
    full = ids([v for e in edges if e["tv_hotel_id"] == local and e["operator_id"] == 18
                for v in e["positive_native_candidates"]])
    native = ids(candidate["lanes"]["operator_115"]["native_ids"])
    # The observed character pattern is not a conversion rule or a proof lane.
    pattern = len(full) == len(native) == 1 and full[0] == "102" + native[0]
    rows = [row for key in full for row in dictionary.get(key, [])]
    return {"tv_full_ids": full, "samo_original_ids": native,
            "prefix_pattern_observed_not_authority": pattern,
            "exact_full_key_dictionary_rows": rows, "namespace_binding_proven": False}


def analyse(tv: dict, samo: dict, bg: dict) -> dict:
    edges = tv["single_native_edges"]
    validate_tv(edges)
    dossiers = samo["dossiers"]
    require(len(dossiers) == 175 and len({d["local_hotel_id"] for d in dossiers}) == 175, "dossier_count")
    native_catalog, strict_targets, dictionary = defaultdict(set), defaultdict(set), defaultdict(list)
    for dossier in dossiers:
        for candidate in dossier["candidates"]:
            for ns in NS:
                for native in ids(candidate["lanes"][ns]["native_ids"]):
                    native_catalog[(ns, native)].add(candidate["catalog_id"])
        if dossier["status"] == "strict_multi_lane_candidate":
            strict_targets[dossier["strict_catalog_id"]].add(dossier["local_hotel_id"])
    for row in bg["rows"]:
        for item in row["dictionary_rows"]:
            safe = {k: item[k] for k in ("key", "name", "countryKey", "cityKey", "stars")}
            safe["row_sha256"] = digest(item)
            if safe not in dictionary[safe["key"]]:
                dictionary[safe["key"]].append(safe)
    buckets, candidates_with_proof, bg_selected = defaultdict(list), [], []
    support_corrections = []
    for dossier in dossiers:
        local, best = dossier["local_hotel_id"], 0
        for candidate in dossier["candidates"]:
            proof = exact_proofs(local, candidate, edges, native_catalog)
            best = max(best, len(proof))
            normalized = support(dossier["direct_anex_ids"], candidate["lanes"]["operator_5"]["native_ids"])
            if normalized != candidate["direct_anex_support"]:
                support_corrections.append([local, candidate["catalog_id"], normalized])
            if proof:
                candidates_with_proof.append({"local_hotel_id": local, "hotel_name": dossier["hotel_name"],
                    "andromeda_catalog_id": candidate["catalog_id"], "candidate_names": candidate["names"],
                    "original_status": dossier["status"], "independent_tv_lane_count": len(proof),
                    "proven_lanes": proof, "direct_anex_support_not_authority": normalized,
                    "dossier_sha256": digest(dossier), "candidate_sha256": digest(candidate),
                    "next_gate": "CURRENT_occupancy_manual_evidence_and_name_review", "safe_to_write_now": False})
            if dossier["status"] == "strict_multi_lane_candidate" and candidate["catalog_id"] == dossier["strict_catalog_id"]:
                info = bg_evidence(local, candidate, edges, dictionary)
                if info["tv_full_ids"]:
                    bg_selected.append({"local_hotel_id": local, **info})
        buckets[str(best)].append(local)
    collisions = [{"catalog_id": cid, "local_hotel_ids": sorted(targets)}
                  for cid, targets in sorted(strict_targets.items()) if len(targets) > 1]
    return {"schema": "match_retained175_review_v1", "mode": "offline_evidence_only",
        "input_hashes_zip_result_receipt": PINS, "input_count": 175,
        "original_status_counts": dict(sorted(Counter(d["status"] for d in dossiers).items())),
        "dossier_ids_by_exact_independent_tv_lane_count": {k: sorted(v) for k, v in sorted(buckets.items())},
        "candidates_with_proven_tv_lanes": candidates_with_proof,
        "strict_candidate_catalog_collisions": collisions,
        "selected_legacy_bg_review": bg_selected,
        "normalized_direct_anex_support_corrections_not_identity": support_corrections,
        "provider_http_calls": 0, "database_reads": 0, "database_writes": 0, "mapping_writes": 0,
        "safe_to_write_now": False, "current_validation_performed": False}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    for field in ("tv", "samo", "bg", "output"):
        parser.add_argument("--" + field, required=True, type=Path)
    args = parser.parse_args()
    result = analyse(*(load_archive(getattr(args, key), key) for key in ("tv", "samo", "bg")))
    # Never overwrite a prior report or follow an output symlink.
    with args.output.open("x", encoding="utf-8") as output:
        json.dump(result, output, ensure_ascii=False, sort_keys=True, indent=2)
        output.write("\n")
    print(json.dumps({"input_count": result["input_count"], "proven_candidates": len(result["candidates_with_proven_tv_lanes"]),
                      "report_sha256": hashlib.sha256(args.output.read_bytes()).hexdigest(), "mapping_writes": 0}))


if __name__ == "__main__":
    main()
