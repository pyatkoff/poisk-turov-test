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
        if a != b:
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

# A discovery score is not identity authority. Check CURRENT primary names as
# retained in the saved census, not only the alias which won fuzzy retrieval.
QUALIFIERS.add("posh")


def primary_qualifiers(key: str) -> set[str]:
    singular = {"adults": "adult", "apart": "apartment", "apartments": "apartment",
                "gardens": "garden", "islands": "island", "suites": "suite", "villas": "villa"}
    tokens = {singular.get(token, token) for token in key.split()}
    return tokens & (QUALIFIERS | {"apartment"})


def primary_name(value: object) -> str:
    text = re.split(r"\b(?:ex\.?|former(?:ly)?)\b", str(value or ""), maxsplit=1, flags=re.I)[0]
    return identity_key(text)


def numeric_star_label(value: object) -> int | None:
    """starKey is a dictionary ID; only an explicit 1..5 label is numeric."""
    if isinstance(value, bool):
        return None
    match = re.fullmatch(r"\s*([1-5])\s*(?:\*|★)?\s*", str(value or ""))
    return int(match[1]) if match else None


def primary_recheck(census: dict, rows: list[dict], provider: str) -> list[dict]:
    """Necessary prechecks only; never a resolver, rejection writer or approval."""
    locals_ = {int(r["id"]): r for r in census["local"]}
    sources_ = {str(r["external_hotel_id"]): r for r in census["andromeda"]}
    observed = collections.defaultdict(list)
    for row in census.get("anex_observations", []):
        observed[str(row["anex_hotel_id"])].append(row)
    protected = {str(r["anex_hotel_id"]) for r in
                 census.get("anex_mappings", []) + census.get("anex_decisions", [])}
    excluded = {(str(r["anex_hotel_id"]), int(r.get("catalog_hotel_id", r.get("local_hotel_id", 0))))
                for r in census.get("anex_exclusions", [])}
    result = []
    for candidate in rows:
        external = str(candidate["external_hotel_id" if provider == "andromeda" else "anex_hotel_id"])
        local_id = int(candidate["proposed_local_id"])
        local = locals_.get(local_id, {})
        reasons, details = set(), []
        if not local or int(local.get("is_active") or 0) != 1:
            reasons.add("target_missing_or_inactive")
        if candidate.get("country") not in CORE8 or local.get("country_class") != candidate.get("country"):
            reasons.add("country_scope_conflict")
        labels = []
        if provider == "andromeda":
            source_row = sources_.get(external, {})
            sources = source_row.get("sources") or []
            if (source_row.get("decision_status") != "pending"
                    or source_row.get("local_hotel_id") is not None):
                reasons.add("source_not_unmapped_pending")
            if source_row.get("country_class") != candidate.get("country"):
                reasons.add("country_scope_conflict")
            # Do not choose an agreeable lName instead of the supplier primary.
            names = [s.get("name") or s.get("lName") for s in sources]
            labels = [s.get("star") for s in sources]
            if not labels or any(numeric_star_label(label) is None for label in labels):
                reasons.add("star_label_requires_semantics")
            target_star = numeric_star_label(local.get("category"))
            if target_star is None:
                reasons.add("target_category_requires_semantics")
            for label in labels:
                star = numeric_star_label(label)
                if star is not None and target_star is not None and abs(star - target_star) > 1:
                    reasons.add("star_label_difference")
        elif provider == "anex":
            names = [s.get("hotel_name") for s in observed.get(external, [])]
            if external in protected or (external, local_id) in excluded:
                reasons.add("protected_mapping_decision_or_pair")
            if any(int(s.get("country_id") or 0) != int(local.get("country_id") or 0)
                   for s in observed.get(external, [])):
                reasons.add("country_scope_conflict")
        else:
            raise ValueError("UNSUPPORTED_PROVIDER")
        target_primary = primary_name(local.get("name"))
        names = sorted({str(n).strip() for n in names if n and str(n).strip()})
        if not names or not target_primary:
            reasons.add("primary_name_missing")
        target_keys = variants(local.get("name"))
        # An exact FULL supplier primary matching an explicit target former name
        # is valid alias evidence. An agreeable lName cannot obtain this exemption.
        exact_primary_alias = bool(names) and all(primary_name(n) in target_keys for n in names)
        for name in names:
            source_primary = primary_name(name)
            left, right = primary_qualifiers(source_primary), primary_qualifiers(target_primary)
            compact_equal = source_primary.replace(" ", "") == target_primary.replace(" ", "")
            if left != right and not exact_primary_alias and not compact_equal:
                reasons.add("primary_qualifier_difference")
                details.append({"source_primary": source_primary, "target_primary": target_primary,
                                "source_only": sorted(left - right), "target_only": sorted(right - left)})
        pair = candidate.get("matched_name_pair")
        if pair and len(pair) == 2 and not exact_primary_alias:
            left, right = (set(identity_key(p).split()) for p in pair)
            if left and right and (left < right or right < left):
                # WRatio's partial/token subset score can hide an omitted wing,
                # hotel-specific noun or geography. Independent evidence may
                # resolve it; it is not a permanent mismatch/manual decision.
                reasons.add("non_generic_subset_requires_evidence")
                details.append({"matched_name_pair": pair, "omitted_tokens": sorted(left ^ right)})
        result.append({"provider": provider, "external_hotel_id": external,
                       "proposed_local_id": local_id, "country": candidate.get("country"),
                       "source_primary_names": names, "target_name": local.get("name"),
                       "source_star_labels": sorted({str(x) for x in labels}),
                       "reason_codes": sorted(reasons), "details": details,
                       "status": "held_for_independent_evidence" if reasons else "prechecks_passed_not_accepted",
                       "not_write_authority": True})
    return sorted(result, key=lambda r: (r["provider"], r["external_hotel_id"], r["proposed_local_id"]))


def apply_primary_review(census: dict, previous: dict, extension: dict) -> dict:
    """Recheck an entire prepared cohort; retain every held row and its lineage."""
    if previous["source_census_sha256"] != extension["source_census_sha256"]:
        raise ValueError("PREPARED_CENSUS_MISMATCH")
    previous_rows = previous["rows"]
    extension_rows = extension["andromeda_extension_rows"]
    live_rows = extension["live_anex_bridge_rows"]
    if len(previous_rows) != int(previous["selected_count"]):
        raise ValueError("PREPARED_COUNT_MISMATCH")
    if extension["andromeda_extension_sha256"] != canonical_sha(extension_rows):
        raise ValueError("EXTENSION_DIGEST_MISMATCH")
    if extension["live_anex_bridge_sha256"] != canonical_sha(live_rows):
        raise ValueError("LIVE_BRIDGE_DIGEST_MISMATCH")
    ids = [str(r["external_hotel_id"]) for r in previous_rows + extension_rows]
    if len(ids) != len(set(ids)):
        raise ValueError("DUPLICATE_PREPARED_SOURCE")
    checked = primary_recheck(census, previous_rows + extension_rows, "andromeda")
    checked += primary_recheck(census, live_rows, "anex")
    held = [r for r in checked if r["reason_codes"]]
    eligible = {(r["provider"], r["external_hotel_id"]) for r in checked if not r["reason_codes"]}
    out = dict(extension)
    out.update({
        "schema": "hotel-match-post156-parent-geo-extension/2",
        "status": "prepared_only", "not_write_authority": True,
        "database_writes": 0, "mapping_writes": 0, "supplier_calls": 0, "tourvisor_calls": 0,
        "previous_prepared_count": len(previous_rows),
        "previous_retained_rows": [r for r in previous_rows if ("andromeda", str(r["external_hotel_id"])) in eligible],
        "andromeda_extension_rows": [r for r in extension_rows if ("andromeda", str(r["external_hotel_id"])) in eligible],
        "live_anex_bridge_rows": [r for r in live_rows if ("anex", str(r["anex_hotel_id"])) in eligible],
        "primary_recheck_rows": checked,
        "primary_recheck_examined": len(checked),
        "primary_recheck_held": len(held),
        "primary_recheck_reason_counts": dict(sorted(collections.Counter(code for r in held for code in r["reason_codes"]).items())),
        "primary_recheck_sha256": canonical_sha(checked),
        "pre_recheck_extension_sha256": extension["andromeda_extension_sha256"],
        "pre_recheck_live_bridge_sha256": extension["live_anex_bridge_sha256"],
        "source_snapshot_utc": census.get("generated_at_utc"),
        "independent_evidence_required": "Held means unresolved, not no-match/manual. Passing these checks is not acceptance; current country/name/geo/coordinates/manual/exclusion/conflict/mapping and full ranking checks remain mandatory in the same future guarded transaction.",
    })
    retained = out["andromeda_extension_rows"]
    out["previous_retained_count"] = len(out["previous_retained_rows"])
    out["andromeda_extension_count"] = len(retained)
    out["live_anex_bridge_count"] = len(out["live_anex_bridge_rows"])
    out["consolidated_prioritized_queue_count"] = len(eligible)
    out["andromeda_extension_sha256"] = canonical_sha(retained)
    out["live_anex_bridge_sha256"] = canonical_sha(out["live_anex_bridge_rows"])
    out["andromeda_extension_country_counts"] = dict(sorted(collections.Counter(r["country"] for r in retained).items()))
    out["andromeda_extension_relation_counts"] = dict(sorted(collections.Counter(r["name_relation"] for r in retained).items()))
    out["andromeda_extension_current_anex_bridge_count"] = sum(bool(r["current_anex_tv_bridge"]) for r in retained)
    out["live_anex_search_weight"] = sum(r["search_count"] for r in out["live_anex_bridge_rows"])
    out["policy"] = dict(out.get("policy", {}),
                         primary_qualifiers="all supplier primary names; one-sided difference held even if alias matches",
                         subset_names="non-generic lost tokens need independent evidence",
                         star_semantics="explicit 1..5 label only; never starKey as count",
                         coordinate_policy="not completed by this offline precheck; no override permitted")
    return out

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
            source_star = numeric_star_label(source.get("star"))
        except (TypeError, ValueError):
            source_star = None
        try:
            target_star = numeric_star_label(local.get("category"))
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
    report = {
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
    return apply_primary_review(census, previous_review, report)

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--census", required=True)
    parser.add_argument("--previous-review", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--prepared-extension", help="Recheck preserved full extension instead of reranking")
    args = parser.parse_args()
    census = json.loads(Path(args.census).read_text(encoding="utf-8"))
    previous = json.loads(Path(args.previous_review).read_text(encoding="utf-8"))
    if hashlib.sha256(Path(args.census).read_bytes()).hexdigest() != previous["source_census_sha256"]:
        raise ValueError("CENSUS_DIGEST_MISMATCH")
    if args.prepared_extension:
        extension = json.loads(Path(args.prepared_extension).read_text(encoding="utf-8"))
        report = apply_primary_review(census, previous, extension)
    else:
        report = build_report(census, previous)
    with Path(args.output).open("x", encoding="utf-8") as output:
        output.write(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + "\n")
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
