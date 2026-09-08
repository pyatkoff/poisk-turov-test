#!/usr/bin/env python3
"""Build bounded ANEX price-evidence batches without accepting hotel identities."""

import argparse
import datetime as dt
import hashlib
import json
from pathlib import Path

BLOCKED_REASONS = {
    "coordinate_conflict",
    "country_conflict",
    "details_unavailable",
    "hotel_section_difference",
    "supplier_identity_unverified",
}


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def load_object(path):
    value = json.loads(Path(path).read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise ValueError("expected JSON object")
    return value


def bounded_text(value, limit=200):
    if not isinstance(value, str):
        return ""
    value = " ".join(value.split())
    return value[:limit]


def build_queue(catalog, geo, batch_size=30, generated_at=None):
    if not 1 <= batch_size <= 30:
        raise ValueError("batch size must be between 1 and 30")
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
    for row in rows:
        if not isinstance(row, dict) or row.get("status") != "review":
            continue
        reason = row.get("reason", "")
        external_id = row.get("external_id")
        api = row.get("api")
        relation = row.get("api_xml_relation")
        candidates = row.get("candidates")
        if reason in BLOCKED_REASONS:
            excluded[reason] = excluded.get(reason, 0) + 1
            continue
        if (type(external_id) is not int or external_id <= 0 or external_id in seen
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
        compact = []
        for candidate in candidates[:3]:
            if not isinstance(candidate, dict) or type(candidate.get("id")) is not int:
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
        })
    priority = {"competing_candidates": 0, "insufficient_independent_evidence": 1,
                "candidate_limit_reached": 2}
    eligible.sort(key=lambda item: (
        item["destination"].casefold(),
        priority.get(item["reason"], 9),
        -(item["candidates"][0]["score"] or 0),
        item["candidates"][0]["distance_m"] if item["candidates"][0]["distance_m"] is not None else 10**12,
        item["external_id"],
    ))
    batches = []
    by_destination = {}
    for item in eligible:
        by_destination.setdefault(item["destination"], []).append(item)
    for destination in sorted(by_destination, key=str.casefold):
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
        "counts": {
            "eligible": len(eligible),
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
    parser.add_argument("--batch-size", type=int, default=30)
    args = parser.parse_args()
    catalog = load_object(args.catalog)
    geo = load_object(args.geo)
    result = build_queue(catalog, geo, args.batch_size)
    result["sources"] = {
        "catalog_sha256": digest(args.catalog),
        "geo_sha256": digest(args.geo),
    }
    target = Path(args.output)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(result["counts"], ensure_ascii=False, sort_keys=True))


if __name__ == "__main__":
    main()
