#!/usr/bin/env python3
"""Build bounded ANEX price-evidence batches without accepting hotel identities."""

import argparse
import datetime as dt
import hashlib
import json
import math
from pathlib import Path

BLOCKED_REASONS = {
    "coordinate_conflict",
    "country_conflict",
    "details_unavailable",
    "hotel_section_difference",
    "supplier_identity_unverified",
}
CHECKPOINT_STATUSES = {"offer_seen", "no_offer", "probe_unavailable"}


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def load_object(path):
    value = json.loads(Path(path).read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise ValueError("expected JSON object")
    return value


def completed_ids(checkpoint):
    if checkpoint is None:
        return set()
    if (not isinstance(checkpoint, dict) or checkpoint.get("schema_version") != 1
            or checkpoint.get("mode") != "price_evidence_checkpoint"
            or checkpoint.get("decision_policy") != "diagnostic_only"
            or not isinstance(checkpoint.get("rows"), list)):
        raise ValueError("invalid price evidence checkpoint")
    result = set()
    for row in checkpoint["rows"]:
        identifier = row.get("external_id") if isinstance(row, dict) else None
        if (type(identifier) is not int or not 1 <= identifier <= 999_999_999
                or identifier in result or row.get("status") not in CHECKPOINT_STATUSES):
            raise ValueError("invalid price evidence checkpoint row")
        result.add(identifier)
    counts = checkpoint.get("counts")
    if counts is not None:
        if not isinstance(counts, dict):
            raise ValueError("invalid price evidence checkpoint counts")
        actual = {"completed_ids": len(result)}
        actual.update({status: sum(row["status"] == status for row in checkpoint["rows"])
                       for status in CHECKPOINT_STATUSES})
        actual["checked_ids"] = actual["offer_seen"] + actual["no_offer"]
        actual["deferred_ids"] = actual["probe_unavailable"]
        if any(key in counts and (type(counts[key]) is not int or counts[key] != value)
               for key, value in actual.items()):
            raise ValueError("inconsistent price evidence checkpoint counts")
    return result


def bounded_text(value, limit=200):
    if not isinstance(value, str):
        return ""
    value = " ".join(value.split())
    return value[:limit]


def observed_offer_destinations(checkpoint):
    """Positive observations only; no observation never excludes a destination."""
    completed_ids(checkpoint)
    destinations = set()
    for row in checkpoint["rows"] if checkpoint is not None else []:
        if row["status"] == "offer_seen":
            destination = bounded_text(row.get("destination"))
            if not destination:
                raise ValueError("offer observation missing destination")
            destinations.add(destination)
    return destinations


def compact_candidates(candidates):
    compact = []
    for candidate in candidates[:3]:
        if (not isinstance(candidate, dict) or type(candidate.get("id")) is not int
                or not 1 <= candidate["id"] <= 999_999_999):
            continue
        compact.append({
            "catalog_hotel_id": candidate["id"],
            "name": bounded_text(candidate.get("name")),
            "country": bounded_text(candidate.get("country")),
            "region": bounded_text(candidate.get("region")),
            "town": bounded_text(candidate.get("town")),
            "score": candidate.get("score") if type(candidate.get("score")) in (int, float) else None,
            "distance_m": candidate.get("distance_m") if type(candidate.get("distance_m")) in (int, float) else None,
        })
    return compact


def exact_candidate(row):
    """Validate the stored exact-rule proposal, without accepting its identity."""
    identifier = row.get("catalog_hotel_id")
    candidates = row.get("candidates")
    country = bounded_text(row.get("country"))
    if (type(identifier) is not int or not 1 <= identifier <= 999_999_999
            or not isinstance(candidates, list) or not country):
        return None
    selected = [candidate for candidate in candidates
                if isinstance(candidate, dict) and candidate.get("id") == identifier]
    if len(selected) != 1:
        return None
    candidate = selected[0]
    score = candidate.get("score")
    if (type(score) not in (int, float) or not math.isfinite(score) or score < 1.4
            or bounded_text(candidate.get("country")).casefold() != country.casefold()):
        return None
    return candidate


def build_queue(catalog, geo, batch_size=30, generated_at=None, completed=None,
                sales_first=False, observed_destinations=None):
    if not 1 <= batch_size <= 30:
        raise ValueError("batch size must be between 1 and 30")
    completed = set() if completed is None else set(completed)
    if any(type(value) is not int or not 1 <= value <= 999_999_999 for value in completed):
        raise ValueError("invalid completed ids")
    observed_destinations = set() if observed_destinations is None else set(observed_destinations)
    if any(not isinstance(value, str) or not bounded_text(value)
           for value in observed_destinations):
        raise ValueError("invalid observed destinations")
    observed = {bounded_text(value).casefold() for value in observed_destinations}
    matches = catalog.get("matches")
    rows = geo.get("rows")
    if not isinstance(matches, list) or not isinstance(rows, list):
        raise ValueError("invalid matching report")
    catalog_by_id = {
        row.get("external_id"): row for row in matches
        if isinstance(row, dict) and type(row.get("external_id")) is int
    }
    eligible = []
    excluded = {}
    seen = set()
    skipped_completed = set()
    for row in rows:
        if (not isinstance(row, dict) or row.get("status") not in
                ({"review", "strong_candidate"} if sales_first else {"review"})):
            continue
        external_id = row.get("external_id")
        if type(external_id) is int and external_id in completed:
            skipped_completed.add(external_id)
            continue
        reason = row.get("reason", "")
        api = row.get("api")
        relation = row.get("api_xml_relation")
        candidates = row.get("candidates")
        if reason in BLOCKED_REASONS:
            excluded[reason] = excluded.get(reason, 0) + 1
            continue
        if (type(external_id) is not int or not 1 <= external_id <= 999_999_999 or external_id in seen
                or not isinstance(api, dict) or api.get("id") != external_id
                or relation != "same_record" or not isinstance(candidates, list)
                or not candidates):
            excluded["incomplete_evidence"] = excluded.get("incomplete_evidence", 0) + 1
            continue
        base = catalog_by_id.get(external_id, {})
        destination = bounded_text(api.get("country") or base.get("country"))
        if not destination:
            excluded["missing_destination"] = excluded.get("missing_destination", 0) + 1
            continue
        compact = compact_candidates(candidates)
        if not compact:
            excluded["incomplete_evidence"] = excluded.get("incomplete_evidence", 0) + 1
            continue
        seen.add(external_id)
        eligible.append({
            "external_id": external_id,
            "destination": destination,
            "supplier_name": bounded_text(api.get("name") or base.get("name")),
            "supplier_region": bounded_text(api.get("region")),
            "supplier_town": bounded_text(api.get("town") or base.get("town")),
            "reason": reason,
            "candidates": compact,
            "matching_status": row["status"],
            "decision_policy": "diagnostic_only",
        })
    if sales_first:
        # A later enrichment record, including a conflict or unmatched result,
        # takes precedence over a catalog proposal for the same supplier ID.
        geo_ids = {row.get("external_id") for row in rows
                   if isinstance(row, dict) and type(row.get("external_id")) is int}
        catalog_id_counts = {}
        for row in matches:
            if isinstance(row, dict) and type(row.get("external_id")) is int:
                identifier = row["external_id"]
                catalog_id_counts[identifier] = catalog_id_counts.get(identifier, 0) + 1
        for row in matches:
            if not isinstance(row, dict) or row.get("status") != "verified_auto":
                continue
            external_id = row.get("external_id")
            if type(external_id) is int and external_id in completed:
                skipped_completed.add(external_id)
                continue
            if type(external_id) is int and external_id in geo_ids:
                continue
            candidate = exact_candidate(row)
            if (type(external_id) is not int or not 1 <= external_id <= 999_999_999
                    or catalog_id_counts.get(external_id) != 1 or external_id in seen
                    or candidate is None):
                excluded["incomplete_exact_evidence"] = excluded.get("incomplete_exact_evidence", 0) + 1
                continue
            seen.add(external_id)
            eligible.append({
                "external_id": external_id,
                "destination": bounded_text(row["country"]),
                "supplier_name": bounded_text(row.get("name")),
                "supplier_region": "",
                "supplier_town": bounded_text(row.get("town")),
                "reason": "catalog_exact_rule",
                "candidates": compact_candidates([candidate]),
                "matching_status": "verified_auto",
                "decision_policy": "diagnostic_only",
            })
    priority = {"competing_candidates": 0, "insufficient_independent_evidence": 1,
                "candidate_limit_reached": 2}
    eligible.sort(key=lambda item: (
        0 if sales_first and item["destination"].casefold() in observed else 1,
        item["destination"].casefold(),
        {"verified_auto": 0, "strong_candidate": 1, "review": 2}.get(item["matching_status"], 9)
        if sales_first else 0,
        priority.get(item["reason"], 9),
        -(item["candidates"][0]["score"] or 0),
        item["candidates"][0]["distance_m"] if item["candidates"][0]["distance_m"] is not None else 10**12,
        item["external_id"],
    ))
    batches = []
    by_destination = {}
    for item in eligible:
        by_destination.setdefault(item["destination"], []).append(item)
    for destination in by_destination:
        items = by_destination[destination]
        for offset in range(0, len(items), batch_size):
            chunk = items[offset:offset + batch_size]
            batches.append({
                "destination": destination,
                "hotel_ids": [item["external_id"] for item in chunk],
                "entries": chunk,
            })
    return {
        "schema_version": 1,
        "generated_at": generated_at or dt.datetime.now(dt.timezone.utc).isoformat(),
        "mode": "price_evidence_queue",
        "decision_policy": "diagnostic_only",
        "priority_policy": "sales_first" if sales_first else "review_only",
        "observed_offer_destinations": sorted(observed_destinations, key=str.casefold)
        if sales_first else [],
        "counts": {
            "eligible": len(eligible),
            "already_checked": len(skipped_completed),
            "checkpoint_completed_ids": len(completed),
            "queued_by_matching_status": {
                status: sum(item["matching_status"] == status for item in eligible)
                for status in sorted({item["matching_status"] for item in eligible})
            },
            "excluded": sum(excluded.values()),
            "batches": len(batches),
            "queued_ids": sum(len(batch["hotel_ids"]) for batch in batches),
            "excluded_by_reason": dict(sorted(excluded.items())),
        },
        "batches": batches,
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--catalog", required=True)
    parser.add_argument("--geo", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--checkpoint")
    parser.add_argument("--batch-size", type=int, default=30)
    parser.add_argument("--sales-first", action="store_true",
                        help="Prioritize observed sale destinations and exact/strong proposals; diagnostic only")
    args = parser.parse_args()
    if args.sales_first and (not args.checkpoint or not Path(args.checkpoint).is_file()):
        parser.error("--sales-first requires an existing restored price checkpoint")
    catalog = load_object(args.catalog)
    geo = load_object(args.geo)
    checkpoint = (load_object(args.checkpoint)
                  if args.checkpoint and Path(args.checkpoint).exists() else None)
    result = build_queue(catalog, geo, args.batch_size, completed=completed_ids(checkpoint),
                         sales_first=args.sales_first,
                         observed_destinations=observed_offer_destinations(checkpoint)
                         if args.sales_first else None)
    result["sources"] = {
        "catalog_sha256": digest(args.catalog),
        "geo_sha256": digest(args.geo),
    }
    if args.checkpoint and Path(args.checkpoint).exists():
        result["sources"]["checkpoint_sha256"] = digest(args.checkpoint)
    target = Path(args.output)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(result["counts"], ensure_ascii=False, sort_keys=True))


if __name__ == "__main__":
    main()
