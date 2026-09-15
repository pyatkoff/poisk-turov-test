#!/usr/bin/env python3
"""Offline coordinate-first evidence for the disjoint cold core8 residual. No network/DB/write authority."""
import collections
import hashlib
import json
import math
import re
import sys
import unicodedata
import zipfile
from difflib import SequenceMatcher
from pathlib import Path

ARTIFACT_SHA256 = "6da39881ac362b1ad433bc6613baf6f97bdc6c396d89ffa49eec517e5edaa2fc"
RESULT_SHA256 = "f135bb42d40b0f3134309b24f5bdffcca8f2e62fa9511d36dfa14c04d96ccd1b"
DOSSIER_SHA256 = "9291e4d74a1c74ac78b17a2ec0e7f8722c019b35183525453c68e83ec515ab3d"
GENERIC = {"hotel", "hotels", "resort", "resorts", "spa", "отель", "the", "and", "by"}
QUALIFIERS = {"annex", "beach", "garden", "gardens", "north", "south"}

def forms(value):
    value = unicodedata.normalize("NFKC", str(value or "")).casefold()
    out = []
    for part in re.split(r"\b(?:ex|former|formerly)\b\.?", value):
        toks = [t for t in re.findall(r"[^\W_]+", part) if t not in GENERIC]
        text = " ".join(toks).strip()
        if text:
            out.append(text)
    return out

def numeric_family(text):
    return {t for t in text.split() if any(ch.isdigit() for ch in t)}

def qualifier_family(text):
    return set(text.split()) & QUALIFIERS

def haversine_km(a, b):
    lat1, lon1, lat2, lon2 = map(math.radians, [a[0], a[1], b[0], b[1]])
    v = math.sin((lat2-lat1)/2)**2 + math.cos(lat1)*math.cos(lat2)*math.sin((lon2-lon1)/2)**2
    return 6371.0 * 2.0 * math.asin(math.sqrt(min(1.0, max(0.0, v))))

def valid_points(row):
    seen = set()
    out = []
    for point in row.get("points") or []:
        if not isinstance(point, dict):
            continue
        try:
            lat = float(point["latitude"])
            lon = float(point["longitude"])
        except (KeyError, TypeError, ValueError):
            continue
        if not (-90 <= lat <= 90 and -180 <= lon <= 180):
            continue
        key = (round(lat, 7), round(lon, 7))
        if key not in seen:
            seen.add(key)
            out.append((lat, lon))
    return out

def best_name_support(src_names, local_forms):
    src_forms = {f for name in src_names for f in forms(name)}
    loc_forms = {f for name in local_forms for f in forms(name)}
    best = None
    for a in src_forms:
        ta = set(a.split())
        for b in loc_forms:
            tb = set(b.split())
            if qualifier_family(a) != qualifier_family(b):
                continue
            if numeric_family(a) != numeric_family(b):
                continue
            common = len(ta & tb)
            score = SequenceMatcher(None, a, b, autojunk=False).ratio()
            exact = a == b
            semantic = exact or (score >= 0.78 and common >= 2) or (score >= 0.90 and common >= 1)
            if not semantic:
                continue
            item = (score, common, exact, a, b)
            if best is None or item[:3] > best[:3]:
                best = item
    if best is None:
        return None
    score, common, exact, a, b = best
    return {"score": score, "common_tokens": common, "exact_form": exact, "source_form": a, "local_form": b}

def geo_guard(row, hotel):
    anchors = row.get("geo_anchors") or []
    if not anchors:
        return {"status": "no_subcountry_geo", "anchors": 0}
    mismatches = []
    for anchor in anchors:
        scope = anchor.get("scope")
        field = "region_id" if scope == "region" else "subregion_id" if scope == "subregion" else None
        if not field:
            mismatches.append({"scope": scope, "reason": "unknown_scope"})
            continue
        try:
            expected = int(anchor.get("scope_id") or 0)
            actual = int(hotel.get(field) or 0)
        except (TypeError, ValueError):
            mismatches.append({"scope": scope, "reason": "invalid_scope_id"})
            continue
        if expected != actual:
            mismatches.append({"scope": scope, "expected": expected, "actual": actual})
    return {"status": "match" if not mismatches else "conflict", "anchors": len(anchors), "mismatches": mismatches}

def analyze(result, excluded_ids):
    rows = [row for route in result["routes"].values() for row in route]
    assert len(rows) == 1399
    excluded_ids = {str(x) for x in excluded_ids}
    cold = [row for row in rows if str(row["external_hotel_id"]) not in excluded_ids]
    assert len(excluded_ids) == 156
    assert len(cold) == 1243

    locals_by_country = collections.defaultdict(list)
    alias_forms = result["local_alias_forms"]
    for key, hotel in result["local_hotels"].items():
        try:
            lat = float(hotel["latitude"])
            lon = float(hotel["longitude"])
            country = int(hotel["country_id"])
        except (KeyError, TypeError, ValueError):
            continue
        if not (-90 <= lat <= 90 and -180 <= lon <= 180):
            continue
        names = alias_forms.get(str(key), []) or [hotel.get("name", "")]
        locals_by_country[country].append((str(key), hotel, (lat, lon), names))

    reason_counts = collections.Counter()
    dossiers = []
    coordinate_rows = 0
    for row in cold:
        points = valid_points(row)
        if not points:
            reason_counts["no_saved_coordinate"] += 1
            continue
        coordinate_rows += 1
        country = int(row["country_id"])
        possible = []
        for key, hotel, coord, local_names in locals_by_country.get(country, []):
            if any(abs(coord[0]-p[0]) > 0.03 or abs(coord[1]-p[1]) > 0.03 for p in points):
                continue
            distances = [haversine_km(p, coord) for p in points]
            max_distance = max(distances)
            if max_distance > 1.0:
                continue
            support = best_name_support(row.get("names") or [], local_names)
            if support is None:
                continue
            geo = geo_guard(row, hotel)
            if geo["status"] == "conflict":
                continue
            possible.append({
                "local_id": int(key),
                "local": hotel,
                "max_distance_km": max_distance,
                "min_distance_km": min(distances),
                "all_distances_km": distances,
                "name_support": support,
                "geo_guard": geo,
            })
        if not possible:
            reason_counts["coordinate_present_no_supported_local_within_1km"] += 1
            continue
        possible.sort(key=lambda x: (x["max_distance_km"], -x["name_support"]["score"], x["local_id"]))
        best = possible[0]
        second = possible[1] if len(possible) > 1 else None
        separated = second is None or second["max_distance_km"] >= max(0.5, best["max_distance_km"] * 3.0)
        strong = separated and best["name_support"]["score"] >= 0.82
        status = "coordinate_strong_prepared" if strong else "coordinate_support_prepared"
        reason_counts[status] += 1
        dossiers.append({
            "state": status,
            "auto_accept": False,
            "source": row,
            "local_id": best["local_id"],
            "local": best["local"],
            "max_distance_km": best["max_distance_km"],
            "min_distance_km": best["min_distance_km"],
            "all_distances_km": best["all_distances_km"],
            "name_support": best["name_support"],
            "geo_guard": best["geo_guard"],
            "runner_up": None if second is None else {
                "local_id": second["local_id"],
                "max_distance_km": second["max_distance_km"],
                "name_score": second["name_support"]["score"],
            },
            "runner_up_separated": separated,
            "holds": [
                "CURRENT_transaction_revalidation_required",
                "manual_exclusion_conflict_occupancy_guard_required",
                "primary_identity_revalidation_required",
            ] + (["no_independent_subcountry_geography"] if best["geo_guard"]["status"] == "no_subcountry_geo" else []),
        })

    dossiers.sort(key=lambda d: (-int(d["source"].get("frequency") or 0), 0 if d["state"] == "coordinate_strong_prepared" else 1, d["max_distance_km"], str(d["source"]["external_hotel_id"])))
    return {
        "schema": "hotel-match-core8-cold-coordinate-evidence/1",
        "state": "prepared_not_safe",
        "auto_accept": False,
        "database_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "source_operation": result["operation_id"],
        "source_sha": result["source_sha"],
        "source_run_id": 34965710062,
        "source_artifact_id": 10394524643,
        "source_artifact_sha256": ARTIFACT_SHA256,
        "input_residual_rows": len(rows),
        "excluded_active_residual_guard_ids": len(excluded_ids),
        "examined_cold_rows": len(cold),
        "cold_rows_with_saved_coordinates": coordinate_rows,
        "prepared_coordinate_dossiers": len(dossiers),
        "strong_prepared_coordinate_dossiers": sum(d["state"] == "coordinate_strong_prepared" for d in dossiers),
        "support_prepared_coordinate_dossiers": sum(d["state"] == "coordinate_support_prepared" for d in dossiers),
        "reason_counts": dict(reason_counts),
        "countries": dict(collections.Counter(str(d["source"]["country_id"]) for d in dossiers)),
        "top_by_frequency": dossiers[:100],
        "dossiers": dossiers,
        "write_boundary": "none; separate NEW CURRENT guard/write claim only after manual/exclusion/conflict/occupancy/current-identity revalidation",
    }

def load_inputs(zip_path, dossier_path):
    raw = Path(zip_path).read_bytes()
    assert hashlib.sha256(raw).hexdigest() == ARTIFACT_SHA256
    with zipfile.ZipFile(zip_path) as zf:
        result_raw = zf.read("server/result.json")
        receipt = json.loads(zf.read("server/receipt.json"))
        result = json.loads(result_raw)
        assert hashlib.sha256(result_raw).hexdigest() == receipt["result_sha256"] == RESULT_SHA256
        assert result["state"] == receipt["state"] == "completed_read_only"
        assert receipt["readback_verified"]
        assert result["db_writes"] == result["mapping_writes"] == result["supplier_calls"] == 0
        assert zf.read("reservation.json") == zf.read("server/reservation.json")
    dossier_raw = Path(dossier_path).read_bytes()
    assert hashlib.sha256(dossier_raw).hexdigest() == DOSSIER_SHA256
    dossier = json.loads(dossier_raw)
    excluded = {str(x["source"]["external_hotel_id"]) for x in dossier["candidates"]}
    assert len(excluded) == dossier["name_candidates_not_safe"] == 156
    return result, excluded

def main():
    if len(sys.argv) != 4:
        raise SystemExit("usage: script CURRENT.zip residual-names.json output.json")
    result, excluded = load_inputs(sys.argv[1], sys.argv[2])
    report = analyze(result, excluded)
    Path(sys.argv[3]).write_text(json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n")
    summary = {k: report[k] for k in [
        "examined_cold_rows", "cold_rows_with_saved_coordinates", "prepared_coordinate_dossiers",
        "strong_prepared_coordinate_dossiers", "support_prepared_coordinate_dossiers", "countries", "reason_counts"
    ]}
    print(json.dumps(summary, ensure_ascii=False, sort_keys=True))

if __name__ == "__main__":
    main()
