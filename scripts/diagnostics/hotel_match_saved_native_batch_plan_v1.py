#!/usr/bin/env python3
"""Plan all saved SAMO/TV native-ID candidates without DB, HTTP or acceptance.

Input: the existing MATCH unresolved SAMO and TV nontriple CSV exports. Names,
prices and ranks never create a pair. Uniqueness is only within the supplied
snapshots; every candidate still needs raw proof and CURRENT writer checks.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import io
import json
import re
from collections import Counter, defaultdict
from pathlib import Path

LANES = ("anex", "bg", "funsun", "intourist")
PROTECTED_CATALOG_IDS = frozenset({"2000086118"})
MAX_BYTES = 64 * 1024 * 1024
MAX_ROWS = 50_000
ID = re.compile(r"[1-9][0-9]{0,31}\Z")
HASH = re.compile(r"[a-f0-9]{64}\Z")
CURRENT_CHECKS = [
    "raw_samo_proof", "independent_tv_operator_proof", "global_native_uniqueness",
    "canonical_local_target", "source_history_and_revision", "manual_and_exclusions",
    "source_and_target_occupancy", "active_country_category_geography",
    "durable_reservation_and_no_replay", "transaction_and_post_commit_readback",
]


def ids(value: str) -> tuple[list[str], bool]:
    """Keep identifier strings exact; malformed tokens remain a visible HOLD."""
    if not value:
        return [], False
    tokens = value.split("|")
    return sorted({n for n in tokens if ID.fullmatch(n)}, key=int), any(
        not ID.fullmatch(n) for n in tokens
    )


def tri_bool(value: str) -> bool | None:
    return {"True": True, "true": True, "1": True,
            "False": False, "false": False, "0": False}.get(value)


def load_csv(path: Path, role: str) -> tuple[list[dict], dict]:
    if path.is_symlink() or not path.is_file() or not 0 < path.stat().st_size <= MAX_BYTES:
        raise ValueError("input_file_size_or_type")
    raw = path.read_bytes()
    if len(raw) > MAX_BYTES:
        raise ValueError("input_file_size_or_type")
    reader = csv.DictReader(io.StringIO(raw.decode("utf-8-sig"), newline=""))
    required = {"samo_catalog_id", "source_decision_status_v77", "source_local_hotel_id_v77",
                "source_catalog_sha256", "source_evidence_sha256"} if role == "samo" else {
                    "tv_hotel_id", "has_samo_mapping", "samo_catalog_ids_accepted"}
    required |= {f"{role}_{lane}_native_ids" for lane in LANES}
    if not required <= set(reader.fieldnames or []):
        raise ValueError("input_columns_missing")
    rows = []
    for index, row in enumerate(reader):
        if len(rows) >= MAX_ROWS or None in row or any(v is None for v in row.values()):
            raise ValueError("input_row_limit_or_shape")
        rows.append(dict(row, _csv_record=index + 2))
    return rows, {"name": path.name, "sha256": hashlib.sha256(raw).hexdigest(),
                  "bytes": len(raw), "row_count": len(rows)}


def plan(samo: list[dict], tv: list[dict]) -> dict:
    # Each lane retains its own namespace. BG's owner-requested prefix rule
    # selects candidates only and never manufactures proof or write authority.
    source_index = {lane: defaultdict(set) for lane in LANES}
    target_index = {lane: defaultdict(set) for lane in LANES}
    source_ids = Counter(r.get("samo_catalog_id", "") for r in samo)
    tv_rows = defaultdict(list)
    target_errors = defaultdict(set)
    row_facts = []
    for row in tv:
        target = row.get("tv_hotel_id", "")
        if not ID.fullmatch(target):
            raise ValueError("invalid_tv_hotel_id")
        tv_rows[target].append(row)
        for lane in LANES:
            native, bad = ids(row.get(f"tv_{lane}_native_ids", ""))
            if bad:
                target_errors[target].add("malformed_tv_native_id")
            if len(native) > 1:
                target_errors[target].add("multiple_tv_native_ids_same_operator")
            for n in native:
                target_index[lane][n].add(target)
    for row in samo:
        catalog = row.get("samo_catalog_id", "")
        if not ID.fullmatch(catalog):
            raise ValueError("invalid_samo_catalog_id")
        native_by_lane, errors = {}, []
        for lane in LANES:
            native, bad = ids(row.get(f"samo_{lane}_native_ids", ""))
            native_by_lane[lane] = native
            if bad:
                errors.append("malformed_samo_native_id")
            if len(native) > 1:
                errors.append("multiple_samo_native_ids_same_operator")
            for n in native:
                source_index[lane][n].add(catalog)
        row_facts.append((row, native_by_lane, errors))

    out = []
    for row, native_by_lane, errors in row_facts:
        catalog = row["samo_catalog_id"]
        reasons = list(errors)
        if source_ids[catalog] != 1:
            reasons.append("duplicate_samo_catalog_rows")
        if row.get("source_decision_status_v77") != "pending":
            reasons.append("source_decision_protected_or_unknown")
        if row.get("source_local_hotel_id_v77") or row.get("current_accepted_locals_in_frontier_v77"):
            reasons.append("source_already_mapped")
        for field in ("source_catalog_sha256", "source_evidence_sha256"):
            if not HASH.fullmatch(row.get(field, "")):
                reasons.append("source_digest_missing_or_invalid")
        if catalog in PROTECTED_CATALOG_IDS:
            reasons.append("protected_catalog_id")
        hits = []
        for lane in LANES:
            for n in native_by_lane[lane]:
                key = "102" + n if lane == "bg" else n
                targets = target_index[lane].get(key, set())
                if len(source_index[lane][n]) != 1:
                    reasons.append("native_multiple_samo_sources_in_inputs")
                if len(targets) > 1:
                    reasons.append("native_multiple_tv_targets_in_inputs")
                for target in sorted(targets, key=int):
                    target_records = tv_rows[target]
                    hits.append({
                        "operator": lane, "samo_native_id": n, "tv_native_id": key,
                        "tv_hotel_id": target,
                        "rule": "owner_bg_102_prefix_candidate" if lane == "bg" else "exact_same_operator_native_candidate",
                        "source_csv_record": row.get("_csv_record"),
                        "target_csv_records": [r.get("_csv_record") for r in target_records],
                        "source_raw_evidence_refs": row.get(f"{lane}_evidence_refs", ""),
                        "tv_known_exact_in_snapshot": all(
                            tri_bool(r.get(f"{lane}_known_exact", "")) is True for r in target_records),
                    })
        targets = sorted({h["tv_hotel_id"] for h in hits}, key=int)
        if len(targets) > 1:
            reasons.append("operator_targets_disagree")
        occupied = []
        target_names = []
        for target in targets:
            records = tv_rows[target]
            if len(records) != 1:
                reasons.append("duplicate_tv_hotel_rows")
            reasons.extend(target_errors[target])
            for record in records:
                target_names.append({"tv_hotel_id": target, "name": record.get("hotel_name", ""),
                                     "country": record.get("country", ""), "region": record.get("region", "")})
                flag = tri_bool(record.get("has_samo_mapping", ""))
                if flag is None:
                    reasons.append("target_occupancy_unknown")
                if flag is True or record.get("samo_catalog_ids_accepted", ""):
                    occupied.append({"tv_hotel_id": target,
                                     "accepted_samo_ids_raw": record.get("samo_catalog_ids_accepted", "")})
        if occupied:
            reasons.append("target_existing_samo_mapping")
        local_anchors = []
        for lane in LANES:
            anchors, bad = ids(row.get(f"linked_local_ids_via_{lane}_native", ""))
            if bad:
                reasons.append("malformed_saved_local_anchor")
            for local in anchors:
                local_anchors.append({"operator": lane, "local_hotel_id": local,
                                      "source_csv_record": row.get("_csv_record")})
            if anchors and not native_by_lane[lane]:
                reasons.append("local_anchor_without_source_native")
        local_ids = sorted({a["local_hotel_id"] for a in local_anchors}, key=int)
        if len(local_ids) > 1:
            reasons.append("saved_local_anchors_disagree")
        reasons = sorted(set(reasons))
        out.append({
            "samo_catalog_id": catalog, "source_names": row.get("hotel_names_seen", ""),
            "source_csv_record": row.get("_csv_record"),
            "observed_offers_raw": row.get("observed_offers", ""),
            "source_catalog_sha256": row.get("source_catalog_sha256", ""),
            "source_evidence_sha256": row.get("source_evidence_sha256", ""),
            "native_by_operator": native_by_lane, "candidate_tv_ids": targets,
            "target_names": target_names, "native_matches": hits,
            # These are independent local IDs, never relabelled as TV hotel IDs.
            "saved_local_anchors": local_anchors, "candidate_local_ids": local_ids,
            "existing_target_mappings": occupied, "reasons": reasons,
            "safe_to_write_now": False,
        })
    # Check collisions over the entire input, including held/occupied sources.
    # Never let projection, priority or batch caps hide a competing catalog ID.
    target_sources = defaultdict(set)
    for row in out:
        for target in row["candidate_tv_ids"]:
            target_sources[target].add(row["samo_catalog_id"])
    for row in out:
        if any(len(target_sources[t]) > 1 for t in row["candidate_tv_ids"]):
            row["reasons"] = sorted(set(row["reasons"] + ["target_multiple_samo_candidates_in_inputs"]))
        reasons = row["reasons"]
        if "protected_catalog_id" in reasons:
            state = "protected"
        elif any(r in reasons for r in (
                "operator_targets_disagree", "native_multiple_samo_sources_in_inputs",
                "native_multiple_tv_targets_in_inputs", "target_multiple_samo_candidates_in_inputs")):
            state = "conflict_review"
        elif reasons:
            state = "occupied_review" if "target_existing_samo_mapping" in reasons else "source_or_evidence_review"
        elif row["candidate_local_ids"] and not row["candidate_tv_ids"]:
            state = "saved_local_anchor_current_checks_required"
        elif not any(row["native_by_operator"].values()):
            state = "missing_native_id"
        elif not row["candidate_tv_ids"]:
            state = "no_target_in_saved_slice"
        else:
            state = "candidate_current_checks_required"
        row["state"] = state
        row["required_before_acceptance"] = CURRENT_CHECKS if (
            row["candidate_tv_ids"] or row["candidate_local_ids"]) else []
    out.sort(key=lambda row: (row["state"], int(row["samo_catalog_id"]), row.get("source_csv_record") or 0))
    counts = Counter(r["state"] for r in out)
    hits = Counter(h["operator"] for r in out for h in r["native_matches"])
    return {
        "schema": "hotel-match-saved-native-batch-plan/1", "state": "completed_saved_only_plan",
        "provider_http_calls": 0, "database_reads": 0, "database_writes": 0, "mapping_writes": 0,
        "safe_to_write_now": False,
        "scope": {"samo_rows": len(samo), "tv_rows": len(tv),
                  "uniqueness": "supplied_snapshot_only", "fresh_current_census": False},
        "counts": dict(sorted(counts.items())), "operator_native_match_counts": dict(sorted(hits.items())),
        "unique_target_candidates": sum(len(r["candidate_tv_ids"]) == 1 for r in out),
        "required_before_acceptance": CURRENT_CHECKS, "rows": out,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--samo-csv", type=Path, required=True)
    parser.add_argument("--tv-csv", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    samo, source = load_csv(args.samo_csv, "samo")
    tv, target = load_csv(args.tv_csv, "tv")
    result = plan(samo, tv)
    result["inputs"] = {"samo": source, "tv": target}
    # Exclusive output never overwrites an earlier checkpoint or receipt.
    with args.output.open("x", encoding="utf-8") as handle:
        json.dump(result, handle, ensure_ascii=False, indent=2)
        handle.write("\n")
    print(json.dumps({key: value for key, value in result.items() if key != "rows"}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
