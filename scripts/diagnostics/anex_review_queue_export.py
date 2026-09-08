#!/usr/bin/env python3
"""Split the final ANEX geo checkpoint into bounded manual-work queues."""

import argparse
import csv
import json
from pathlib import Path


ERROR_REASONS = {"details_unavailable", "catalog_unavailable"}


def compact(row):
    xml = row.get("xml") if isinstance(row.get("xml"), dict) else {}
    api = row.get("api") if isinstance(row.get("api"), dict) else {}
    candidates = row.get("candidates") if isinstance(row.get("candidates"), list) else []
    top = candidates[0] if candidates and isinstance(candidates[0], dict) else {}
    return {
        "anex_hotel_id": row["external_id"],
        "status": row["status"],
        "reason": row.get("reason", ""),
        "anex_name": api.get("name") or xml.get("name") or "",
        "country": api.get("country") or "",
        "town": api.get("town") or "",
        "candidate_count": len(candidates),
        "top_catalog_hotel_id": top.get("id"),
        "top_candidate_name": top.get("name", ""),
        "top_score": top.get("score"),
        "top_distance_m": top.get("distance_m"),
    }


def split_queues(checkpoint):
    if checkpoint.get("schema_version") != 2 or not isinstance(checkpoint.get("rows"), list):
        raise ValueError("unsupported checkpoint")
    rows = checkpoint["rows"]
    identifiers = [row.get("external_id") for row in rows]
    if any(type(identifier) is not int or identifier <= 0 for identifier in identifiers):
        raise ValueError("invalid ANEX hotel id")
    if len(identifiers) != len(set(identifiers)):
        raise ValueError("duplicate ANEX hotel id")
    result = {"review": [], "errors": [], "unmatched": []}
    for row in sorted(rows, key=lambda item: item["external_id"]):
        status = row.get("status")
        reason = row.get("reason")
        if status == "unmatched":
            result["unmatched"].append(compact(row))
        elif status == "review" and reason in ERROR_REASONS:
            result["errors"].append(compact(row))
        elif status == "review":
            result["review"].append(compact(row))
        elif status != "strong_candidate":
            raise ValueError("invalid automated status")
    return result


def write_queue(directory, name, rows):
    payload = {"schema_version": 1, "policy": "manual_review_only", "queue": name,
               "count": len(rows), "rows": rows}
    (directory / f"anex-{name}-queue.json").write_text(
        json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    fields = list(compact({"external_id": 1, "status": "review"}).keys())
    with (directory / f"anex-{name}-queue.csv").open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)


def export(checkpoint_path, output_dir):
    checkpoint = json.loads(Path(checkpoint_path).read_text(encoding="utf-8"))
    queues = split_queues(checkpoint)
    directory = Path(output_dir)
    directory.mkdir(parents=True, exist_ok=True)
    for name, rows in queues.items():
        write_queue(directory, name, rows)
    summary = {"status": "exported", "processed_total": len(checkpoint["rows"]),
               "queues": {name: len(rows) for name, rows in queues.items()},
               "strong_candidates_not_applied": checkpoint.get("counts", {}).get("strong_candidate", 0)}
    (directory / "anex-final-queue-summary.json").write_text(
        json.dumps(summary, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return summary


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--checkpoint", required=True)
    parser.add_argument("--output-dir", required=True)
    args = parser.parse_args()
    try:
        print(json.dumps(export(args.checkpoint, args.output_dir), sort_keys=True))
        return 0
    except (OSError, ValueError, KeyError, json.JSONDecodeError):
        print('{"status":"queue_export_failed"}')
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
