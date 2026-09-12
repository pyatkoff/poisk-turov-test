#!/usr/bin/env python3
from __future__ import annotations
import argparse, collections, hashlib, json, re, unicodedata
from pathlib import Path

GENERIC = {"hotel", "hotels", "otel", "resort", "resorts", "spa", "the"}

def norm(value: object) -> str:
    s = str(value or "").strip().lower().replace("&", " and ")
    s = unicodedata.normalize("NFKD", s)
    s = "".join(ch for ch in s if not unicodedata.combining(ch))
    s = re.sub(r"['’`]", "", s)
    s = re.sub(r"[^a-z0-9а-яё]+", " ", s, flags=re.I)
    return " ".join(tok for tok in s.split() if tok not in GENERIC)

def variants(value: object) -> set[str]:
    text = str(value or "")
    out = {norm(text)}
    current = re.split(r"\b(?:ex\.?|former(?:ly)?)\b", text, maxsplit=1, flags=re.I)
    if current and current[0].strip(): out.add(norm(current[0]))
    for m in re.finditer(r"\(\s*(?:ex\.?|former(?:ly)?)\s*[:\-]?\s*([^)]{2,})\)", text, re.I):
        out.add(norm(m.group(1)))
    return {x for x in out if x}

def sha_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def place_compatible(row: dict, gap: dict) -> bool:
    src = {norm(x) for x in row.get("source_places", []) if x}
    tgt = {norm(gap.get("region")), norm(gap.get("subregion"))}
    src.discard(""); tgt.discard("")
    return bool(src & tgt)

def build(review: dict, *, source_sha256: str) -> dict:
    gaps = review["third_link_gaps"]
    gap_by_id = {int(g["local_hotel_id"]): g for g in gaps}
    index: dict[tuple[int, str, str], set[int]] = collections.defaultdict(set)
    for g in gaps:
        for key in variants(g.get("name")):
            index[(int(g["country_id"]), str(g["type"]), key)].add(int(g["local_hotel_id"]))

    rows = []
    for row in review["buckets"]["needs_extra_evidence"]:
        provider = str(row["provider"])
        target_type = "anex_tv_only" if provider == "andromeda" else "andromeda_tv_only"
        matches: dict[int, set[str]] = collections.defaultdict(set)
        for source_name in row.get("source_names", []):
            for key in variants(source_name):
                for local_id in index.get((int(row["country_id"]), target_type, key), set()):
                    matches[local_id].add(key)
        if len(matches) != 1:
            continue
        local_id = next(iter(matches))
        gap = gap_by_id[local_id]
        keys = sorted(matches[local_id])
        max_tokens = max((len(k.split()) for k in keys), default=0)
        direct_geo = place_compatible(row, gap)
        target_in_candidates = local_id in {int(x) for x in row.get("candidate_ids", [])}
        low_information = max_tokens < 2 and not (direct_geo and target_in_candidates)
        rows.append({
            "provider": provider,
            "external_id": str(row["external_id"]),
            "country_id": int(row["country_id"]),
            "proposed_local_id": local_id,
            "target_gap_type": target_type,
            "source_reason": row["reason"],
            "source_names": row.get("source_names", []),
            "source_places": row.get("source_places", []),
            "target_name": gap.get("name"),
            "target_region": gap.get("region"),
            "target_subregion": gap.get("subregion"),
            "matched_keys": keys,
            "search_count": int(row.get("search_count") or 0),
            "direct_geo": direct_geo,
            "target_in_source_candidates": target_in_candidates,
            "max_significant_tokens": max_tokens,
            "pre_duplicate_status": "needs_extra_evidence" if low_information else "current_recheck_candidate",
        })

    duplicate_targets = collections.Counter((r["provider"], r["proposed_local_id"]) for r in rows if r["pre_duplicate_status"] == "current_recheck_candidate")
    selected, held = [], []
    for r in rows:
        reasons=[]
        if r["pre_duplicate_status"] != "current_recheck_candidate": reasons.append("low_information_identity")
        if duplicate_targets[(r["provider"], r["proposed_local_id"])] > 1:
            reasons.append("same_provider_duplicate_target")
        out = dict(r)
        out.pop("pre_duplicate_status", None)
        out["status"] = "prepared_current_recheck_not_accepted" if not reasons else "held_for_independent_evidence"
        out["hold_reasons"] = reasons
        (selected if not reasons else held).append(out)

    selected.sort(key=lambda r: (r["provider"], r["country_id"], r["external_id"]))
    held.sort(key=lambda r: (r["provider"], r["country_id"], r["external_id"]))
    all_rows = selected + held

    def count_by(seq, key_fn):
        c=collections.Counter(key_fn(x) for x in seq)
        return {str(k): v for k,v in sorted(c.items(), key=lambda z: str(z[0]))}

    return {
        "schema": "hotel-match-identity-graph-mass-review/1",
        "status": "prepared_only",
        "not_write_authority": True,
        "source_operation_id": review.get("operation_id"),
        "source_review_sha256": source_sha256,
        "source_counts": review.get("counts", {}),
        "source_coverage": review.get("coverage", {}),
        "graph_scope": {
            "third_link_gaps_examined": len(gaps),
            "needs_extra_rows_examined": len(review["buckets"]["needs_extra_evidence"]),
            "anex_tv_only_targets": sum(g.get("type") == "anex_tv_only" for g in gaps),
            "andromeda_tv_only_targets": sum(g.get("type") == "andromeda_tv_only" for g in gaps),
        },
        "result_counts": {
            "unique_exact_graph_bridges": len(all_rows),
            "prepared_current_recheck": len(selected),
            "held_for_independent_evidence": len(held),
            "prepared_andromeda": sum(r["provider"] == "andromeda" for r in selected),
            "prepared_anex": sum(r["provider"] == "anex" for r in selected),
            "held_andromeda": sum(r["provider"] == "andromeda" for r in held),
            "held_anex": sum(r["provider"] == "anex" for r in held),
            "prepared_live_anex_search_weight": sum(r["search_count"] for r in selected if r["provider"] == "anex"),
        },
        "prepared_by_country_id": count_by(selected, lambda r: r["country_id"]),
        "prepared_by_source_reason": count_by(selected, lambda r: r["provider"] + ":" + r["source_reason"]),
        "held_reason_counts": count_by([{"reason": reason} for r in held for reason in r["hold_reasons"]], lambda r: r["reason"]),
        "policy": {
            "graph_spine": "AnyTour/Tourvisor local_hotel_id",
            "andromeda_direction": "pending Andromeda -> current ANEX+Tourvisor-only target",
            "anex_direction": "unresolved ANEX -> current Andromeda+Tourvisor-only target",
            "normalization": "exact normalized/current-or-former alias; ignore HOTEL/RESORT/SPA only",
            "meaningful_qualifiers": "preserved; no generic blanket deletion",
            "one_token": "held unless direct geography AND target already appears among provider candidates",
            "duplicate_target": "same-provider duplicate proposed local fails closed",
            "stars": "signal only; numeric star mismatch may be bridged by independent provider identity but is never sole proof",
            "coordinates": ">5km remains future CURRENT hard block; this immutable artifact does not authorize override",
            "future_write": "recompute from CURRENT DB in one NEW immutable transaction; preserve mappings/manual/exclusions/conflicts/occupancy; per-row post-COMMIT readback",
        },
        "prepared_rows": selected,
        "held_rows": held,
        "database_writes": 0,
        "mapping_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
    }

def main() -> int:
    p=argparse.ArgumentParser()
    p.add_argument("--review", required=True)
    p.add_argument("--output", required=True)
    a=p.parse_args()
    path=Path(a.review); raw=path.read_bytes(); review=json.loads(raw)
    result=build(review, source_sha256=sha_bytes(raw))
    with Path(a.output).open("x", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False, indent=2, sort_keys=True); f.write("\n")
    print(json.dumps(result["result_counts"], sort_keys=True))
    return 0
if __name__ == "__main__": raise SystemExit(main())
