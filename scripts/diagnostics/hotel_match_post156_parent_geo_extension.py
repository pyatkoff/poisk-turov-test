#!/usr/bin/env python3
"""
MATCH #1971 read-only parent-geography extension for the post-156 census.

This tool NEVER writes mapping/DB state and NEVER performs supplier/Tourvisor calls.
It consumes a saved census + the prior prepared review and emits an evidence queue.
Any acceptance must re-read CURRENT DB and recompute the evidence in the same
guarded transaction with a new operation id/reservation/receipt/readback.
"""
from __future__ import annotations

import argparse
import collections
import hashlib
import json
import re
import unicodedata
from pathlib import Path

from rapidfuzz import fuzz, process

GENERIC = {
    "and", "ex", "former", "formerly", "hotel", "hotels", "otel",
    "resort", "resorts", "spa", "the",
}
QUALIFIERS = {
    "adult", "adults", "annex", "apart", "apartments", "aqua", "bay",
    "beach", "beachfront", "boutique", "central", "city", "club", "east",
    "family", "garden", "grand", "harem", "island", "marina", "north",
    "only", "palace", "park", "royal", "south", "suite", "suites", "villa",
    "villas", "waterpark", "west", "wing",
}
QUALIFIER_GROUPS = (
    {"north", "south", "east", "west"},
    {"beach", "garden", "marina", "city", "island", "bay", "central"},
    {"annex", "wing"},
    {"adult", "adults", "family"},
    {"suite", "suites", "villa", "villas", "apart", "apartments"},
)
CORE8 = {"egypt", "turkey", "thailand", "uae", "vietnam", "srilanka", "maldives", "cuba"}

def norm(value: object) -> str:
    text = str(value or "").strip().lower()
    text = unicodedata.normalize("NFKD", text)
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    text = text.replace("&", " and ")
    text = re.sub(r"['’`]", "", text)
    text = re.sub(r"[^a-z0-9а-яё]+", " ", text, flags=re.I)
    return re.sub(r"\s+", " ", text).strip()

def identity_key(value: object) -> str:
    return " ".join(tok for tok in norm(value).split() if tok not in GENERIC)

def variants(value: object) -> set[str]:
    text = str(value or "")
    out = {identity_key(text)}
    current = re.split(r"\(\s*(?:ex\.?|former(?:ly)?)[^)]*?\)", text, maxsplit=1, flags=re.I)
    if current and current[0].strip():
        out.add(identity_key(current[0]))
    current = re.split(r"\b(?:ex\.?|former(?:ly)?)\b", text, maxsplit=1, flags=re.I)
    if current and current[0].strip():
        out.add(identity_key(current[0]))
    for match in re.finditer(r"\(\s*(?:ex\.?|former(?:ly)?)\s*[:\-]?\s*([^)]{2,})\)", text, re.I):
        out.add(identity_key(match.group(1)))
    return {item for item in out if item}

def qualifiers(key: str) -> set[str]:
    return set(key.split()) & QUALIFIERS

def qualifier_compatible(left: set[str], right: set[str]) -> bool:
    for group in QUALIFIER_GROUPS:
        a, b = left & group, right & group
        if a and b and a != b:
            return False
    return True

def canonical_sha(rows: object) -> str:
    payload = json.dumps(rows, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    return hashlib.sha256(payload).hexdigest()

def _best_local(source_keys, choices, name_lids, limit=50):
    scores = collections.defaultdict(float)
    pairs = {}
    for source_key in sorted(source_keys):
        for target_key, score, _idx in process.extract(
            source_key, choices, scorer=fuzz.WRatio, limit=limit, score_cutoff=75
        ):
            for local_id in name_lids[target_key]:
                if score > scores[local_id]:
                    scores[local_id] = float(score)
                    pairs[local_id] = (source_key, target_key)
    if not scores:
        return None
    ranked = sorted(scores.items(), key=lambda item: (-item[1], item[0]))
    best_local, best_score = ranked[0]
    second_score = ranked[1][1] if len(ranked) > 1 else 0.0
    return best_local, best_score, second_score, best_score - second_score, pairs[best_local]

def build_report(census: dict, previous_review: dict) -> dict:
    local_by_id = {int(row["id"]): row for row in census["local"]}
    aliases_by_id = collections.defaultdict(list)
    for row in census["aliases"]:
        aliases_by_id[int(row["hotel_id"])].append(row["alias"])

    name_lids_by_country = {}
    choices_by_country = {}
    for country in CORE8:
        name_lids = collections.defaultdict(set)
        for local_id, local in local_by_id.items():
            if local.get("country_class") != country:
                continue
            for name in [local.get("name")] + aliases_by_id.get(local_id, []):
                for key in variants(name):
                    name_lids[key].add(local_id)
        name_lids_by_country[country] = name_lids
        choices_by_country[country] = sorted(name_lids)

    # Learn provider townKey -> AnyTour/Tourvisor parent region only from already
    # CURRENT-accepted Andromeda identities in the saved census.
    town_region = collections.defaultdict(collections.Counter)
    accepted_andromeda_names = collections.defaultdict(lambda: collections.defaultdict(set))
    for row in census["andromeda"]:
        if row.get("decision_status") != "accepted" or not row.get("local_hotel_id"):
            continue
        local_id = int(row["local_hotel_id"])
        local = local_by_id.get(local_id)
        if not local:
            continue
        for source in row.get("sources") or []:
            town_key = source.get("townKey")
            if town_key is not None:
                town_region[(row.get("country_class"), str(town_key))][local.get("region_id")] += 1
            for name in (source.get("name"), source.get("lName")):
                for key in variants(name):
                    accepted_andromeda_names[row.get("country_class")][key].add(local_id)

    active_anex_targets = set()
    active_anex_ids = set()
    for row in census["anex_mappings"]:
        if int(row.get("enabled") or 0) != 1:
            continue
        active_anex_ids.add(str(row["anex_hotel_id"]))
        active_anex_targets.add(int(row["catalog_hotel_id"]))

    previous_ids = {str(row["external_hotel_id"]) for row in previous_review["rows"]}
    previous_targets = {int(row["proposed_local_id"]) for row in previous_review["rows"]}

    provisional = []
    for row in census["andromeda"]:
        if row.get("decision_status") != "pending" or row.get("country_class") not in CORE8:
            continue
        external_id = str(row["external_hotel_id"])
        if external_id in previous_ids:
            continue
        sources = row.get("sources") or []
        if not sources:
            continue
        source = sources[0]
        source_keys = set()
        for name in (source.get("name"), source.get("lName")):
            source_keys.update(variants(name))
        if not source_keys:
            continue
        result = _best_local(
            source_keys,
            choices_by_country[row["country_class"]],
            name_lids_by_country[row["country_class"]],
        )
        if not result:
            continue
        local_id, best, second, margin, name_pair = result
        if local_id in previous_targets:
            continue
        local = local_by_id[local_id]
        town_key = source.get("townKey")
        region_counts = town_region.get((row["country_class"], str(town_key)), collections.Counter())
        town_n = sum(region_counts.values())
        region_support = region_counts.get(local.get("region_id"), 0)
        region_ratio = (region_support / town_n) if town_n else 0.0
        try:
            source_star = int(source.get("starKey")) if source.get("starKey") is not None else None
        except (TypeError, ValueError):
            source_star = None
        try:
            target_star = int(local.get("category")) if local.get("category") is not None else None
        except (TypeError, ValueError):
            target_star = None
        star_difference = abs(source_star - target_star) if source_star is not None and target_star is not None else None

        source_q, target_q = qualifiers(name_pair[0]), qualifiers(name_pair[1])
        if not (
            best >= 95
            and margin >= 8
            and town_n >= 5
            and region_ratio >= 0.90
            and len(name_pair[0].split()) >= 2
            and (star_difference is None or star_difference <= 1)
            and qualifier_compatible(source_q, target_q)
        ):
            continue

        if name_pair[0] == name_pair[1]:
            relation = "exact_normalized_or_former_alias"
        elif sorted(name_pair[0].split()) == sorted(name_pair[1].split()):
            relation = "same_tokens_reordered"
        elif best >= 98:
            relation = "near_exact_spelling"
        else:
            relation = "strong_fuzzy_parent_geo"

        provisional.append({
            "external_hotel_id": external_id,
            "country": row["country_class"],
            "source_name": source.get("name"),
            "source_town": source.get("town"),
            "source_town_key": town_key,
            "proposed_local_id": local_id,
            "target_name": local.get("name"),
            "target_region": local.get("region_name"),
            "target_subregion": local.get("subregion_name"),
            "best_score": round(best, 3),
            "second_score": round(second, 3),
            "margin": round(margin, 3),
            "matched_name_pair": list(name_pair),
            "name_relation": relation,
            "town_accepted_support": town_n,
            "target_region_support": region_support,
            "target_region_ratio": round(region_ratio, 6),
            "star_difference": star_difference,
            "current_anex_tv_bridge": local_id in active_anex_targets,
            "status": "needs_current_same_transaction_recheck",
        })

    # Fail closed if more than one newly selected pending row points at the same target.
    counts = collections.Counter(row["proposed_local_id"] for row in provisional)
    extension = sorted(
        (row for row in provisional if counts[row["proposed_local_id"]] == 1),
        key=lambda row: (row["country"], row["external_hotel_id"]),
    )

    decision_ids = {str(row["anex_hotel_id"]) for row in census.get("anex_decisions", [])}
    country_id_to_class = {}
    for local in local_by_id.values():
        if local.get("country_class") in {"egypt", "turkey"}:
            country_id_to_class[int(local["country_id"])] = local["country_class"]

    live_bridge = []
    for observation in census.get("anex_observations", []):
        anex_id = str(observation["anex_hotel_id"])
        if (
            anex_id in active_anex_ids
            or anex_id in decision_ids
            or observation.get("last_catalog_hotel_id") is not None
        ):
            continue
        country = country_id_to_class.get(int(observation["country_id"]))
        if country not in {"egypt", "turkey"}:
            continue
        matched = collections.defaultdict(set)
        for key in variants(observation.get("hotel_name")):
            for local_id in accepted_andromeda_names[country].get(key, set()):
                matched[local_id].add(key)
        if len(matched) != 1:
            continue
        local_id = next(iter(matched))
        local = local_by_id[local_id]
        live_bridge.append({
            "anex_hotel_id": anex_id,
            "country": country,
            "hotel_name": observation.get("hotel_name"),
            "search_count": int(observation.get("search_count") or 0),
            "proposed_local_id": local_id,
            "target_name": local.get("name"),
            "matched_keys": sorted(matched[local_id]),
            "evidence": "exact normalized live ANEX name -> CURRENT accepted Andromeda source name",
            "status": "needs_tourvisor_anex_hotelcode_or_equivalent_independent_recheck",
        })
    live_bridge.sort(key=lambda row: (-row["search_count"], row["anex_hotel_id"]))

    existing_count = int(previous_review["selected_count"])
    return {
        "schema": "hotel-match-post156-parent-geo-extension/1",
        "status": "prepared_only",
        "not_write_authority": True,
        "no_replay": True,
        "source_census_operation": census["operation_id"],
        "source_census_sha256": previous_review["source_census_sha256"],
        "previous_candidate_sha256": previous_review["candidate_sha256"],
        "previous_prepared_count": existing_count,
        "andromeda_extension_count": len(extension),
        "live_anex_bridge_count": len(live_bridge),
        "consolidated_prioritized_queue_count": existing_count + len(extension) + len(live_bridge),
        "andromeda_extension_country_counts": dict(sorted(collections.Counter(row["country"] for row in extension).items())),
        "andromeda_extension_relation_counts": dict(sorted(collections.Counter(row["name_relation"] for row in extension).items())),
        "andromeda_extension_current_anex_bridge_count": sum(bool(row["current_anex_tv_bridge"]) for row in extension),
        "live_anex_search_weight": sum(row["search_count"] for row in live_bridge),
        "policy": {
            "andromeda_status": "pending only",
            "country": "core8 only",
            "previous_101": "disjoint by external id and target",
            "name_best_min": 95,
            "name_margin_min": 8,
            "provider_town_accepted_support_min": 5,
            "target_parent_region_ratio_min": 0.90,
            "significant_name_tokens_min": 2,
            "star_difference_max_when_numeric": 1,
            "duplicate_new_target": "fail_closed",
            "qualifier_conflict": "fail_closed for meaningful qualifier groups",
            "coordinate_policy": "saved Andromeda source coordinates absent; no coordinate override permitted",
            "future_acceptance": "must recompute CURRENT evidence inside guarded transaction; package is not write authority",
            "live_anex_bridge": "exact accepted-Andromeda source-name bridge is evidence only; complete Tourvisor->ANEX hotelCode ladder before acceptance when available",
        },
        "andromeda_extension_sha256": canonical_sha(extension),
        "live_anex_bridge_sha256": canonical_sha(live_bridge),
        "andromeda_extension_rows": extension,
        "live_anex_bridge_rows": live_bridge,
        "database_writes": 0,
        "mapping_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
    }

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--census", required=True)
    parser.add_argument("--previous-review", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    census = json.loads(Path(args.census).read_text(encoding="utf-8"))
    previous = json.loads(Path(args.previous_review).read_text(encoding="utf-8"))
    report = build_report(census, previous)
    Path(args.output).write_text(
        json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    print(json.dumps({
        "status": report["status"],
        "previous_prepared_count": report["previous_prepared_count"],
        "andromeda_extension_count": report["andromeda_extension_count"],
        "live_anex_bridge_count": report["live_anex_bridge_count"],
        "consolidated_prioritized_queue_count": report["consolidated_prioritized_queue_count"],
        "andromeda_extension_sha256": report["andromeda_extension_sha256"],
        "live_anex_bridge_sha256": report["live_anex_bridge_sha256"],
    }, sort_keys=True))
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
