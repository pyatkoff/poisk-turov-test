#!/usr/bin/env python3
"""Offline compact/token-order identity rescue for the disjoint 1243-row cold residual."""
import collections
import json
import sys
from pathlib import Path
import hotel_match_core8_cold_coordinate_evidence as base

def compact_key(form):
    return "".join(form.split())

def sorted_key(form):
    return " ".join(sorted(form.split()))

def analyze(result, excluded_ids):
    rows = [row for route in result["routes"].values() for row in route]
    assert len(rows) == 1399
    excluded_ids = {str(x) for x in excluded_ids}
    cold = [row for row in rows if str(row["external_hotel_id"]) not in excluded_ids]
    assert len(excluded_ids) == 156 and len(cold) == 1243

    compact_idx = collections.defaultdict(lambda: collections.defaultdict(list))
    sorted_idx = collections.defaultdict(lambda: collections.defaultdict(list))
    for key, hotel in result["local_hotels"].items():
        cid = int(hotel["country_id"])
        local_forms = {f for name in (result["local_alias_forms"].get(str(key), []) or [hotel.get("name", "")]) for f in base.forms(name)}
        for form in local_forms:
            compact_idx[cid][compact_key(form)].append((str(key), form))
            sorted_idx[cid][sorted_key(form)].append((str(key), form))

    dossiers = []
    reasons = collections.Counter()
    for row in cold:
        cid = int(row["country_id"])
        src_forms = {f for name in row.get("names") or [] for f in base.forms(name)}
        if not src_forms:
            reasons["no_source_name_form"] += 1
            continue
        matches = collections.defaultdict(list)
        for src in src_forms:
            for key, loc in compact_idx[cid].get(compact_key(src), []):
                if base.qualifier_family(src) != base.qualifier_family(loc) or base.numeric_family(src) != base.numeric_family(loc):
                    continue
                matches[key].append(("compact_exact", src, loc))
            for key, loc in sorted_idx[cid].get(sorted_key(src), []):
                if base.qualifier_family(src) != base.qualifier_family(loc) or base.numeric_family(src) != base.numeric_family(loc):
                    continue
                matches[key].append(("token_order_exact", src, loc))
        if not matches:
            reasons["no_compact_or_token_order_exact_match"] += 1
            continue
        if len(matches) != 1:
            reasons["country_wide_ambiguous_local_targets"] += 1
            continue
        key = next(iter(matches))
        hotel = result["local_hotels"][key]
        geo = base.geo_guard(row, hotel)
        if geo["status"] == "conflict":
            reasons["provider_geo_conflict"] += 1
            continue
        evidence = sorted(set(matches[key]))
        kinds = {x[0] for x in evidence}
        state = "compact_unique_prepared" if "compact_exact" in kinds else "token_order_unique_prepared"
        reasons[state] += 1
        dossiers.append({
            "state": state,
            "auto_accept": False,
            "source": row,
            "local_id": int(key),
            "local": hotel,
            "evidence": [{"kind": kind, "source_form": src, "local_form": loc} for kind, src, loc in evidence],
            "geo_guard": geo,
            "holds": [
                "CURRENT_transaction_revalidation_required",
                "manual_exclusion_conflict_occupancy_guard_required",
                "primary_identity_revalidation_required",
            ] + (["no_independent_subcountry_geography"] if geo["status"] == "no_subcountry_geo" else []),
        })
    dossiers.sort(key=lambda d: (-int(d["source"].get("frequency") or 0), 0 if d["state"] == "compact_unique_prepared" else 1, str(d["source"]["external_hotel_id"])))
    return {
        "schema": "hotel-match-core8-cold-compact-evidence/1",
        "state": "prepared_not_safe",
        "auto_accept": False,
        "database_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "source_operation": result["operation_id"],
        "source_sha": result["source_sha"],
        "source_run_id": 34965710062,
        "source_artifact_id": 10394524643,
        "source_artifact_sha256": base.ARTIFACT_SHA256,
        "input_residual_rows": len(rows),
        "excluded_active_residual_guard_ids": len(excluded_ids),
        "examined_cold_rows": len(cold),
        "prepared_identity_dossiers": len(dossiers),
        "compact_unique_prepared": sum(d["state"] == "compact_unique_prepared" for d in dossiers),
        "token_order_unique_prepared": sum(d["state"] == "token_order_unique_prepared" for d in dossiers),
        "reason_counts": dict(reasons),
        "countries": dict(collections.Counter(str(d["source"]["country_id"]) for d in dossiers)),
        "top_by_frequency": dossiers[:100],
        "dossiers": dossiers,
        "write_boundary": "none; separate NEW CURRENT guard/write claim required",
    }

def main():
    if len(sys.argv) != 4:
        raise SystemExit("usage: script CURRENT.zip residual-names.json output.json")
    result, excluded = base.load_inputs(sys.argv[1], sys.argv[2])
    report = analyze(result, excluded)
    Path(sys.argv[3]).write_text(json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n")
    print(json.dumps({k: report[k] for k in ["examined_cold_rows","prepared_identity_dossiers","compact_unique_prepared","token_order_unique_prepared","countries","reason_counts"]}, ensure_ascii=False, sort_keys=True))

if __name__ == "__main__":
    main()
