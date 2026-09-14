#!/usr/bin/env python3
"""Merge immutable MATCH manual queue with latest native ANEX hotelCode CURRENT review.

Offline evidence-only transformer: no DB, network, supplier, Tourvisor or mapping writes.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
from collections import Counter
from pathlib import Path
from typing import Any

EXTRA_FIELDS = [
    "native_anex_hotelcode_confirmed",
    "native_anex_page_url",
    "native_anex_photo_source_run",
    "photo_current_status",
    "photo_current_candidate_local_id",
    "photo_current_search_count",
]


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8"))


def merge(manual_doc: dict[str, Any], photo_doc: dict[str, Any]) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    rows = [dict(r) for r in manual_doc.get("rows", [])]
    photo_rows = photo_doc.get("rows", [])
    photo = {str(int(r["anex_hotel_id"])): r for r in photo_rows if int(r.get("anex_hotel_id") or 0) > 0}
    enriched = 0
    weight = 0
    status_counts: Counter[str] = Counter()
    for row in rows:
        row.update({k: None for k in EXTRA_FIELDS})
        row["native_anex_hotelcode_confirmed"] = False
        if row.get("provider") != "anex":
            continue
        p = photo.get(str(row.get("external_hotel_id")))
        if not p:
            continue
        evidence = p.get("photo_evidence") or {}
        hotelcode = int(evidence.get("hotelcode") or 0)
        source_id = int(row.get("external_hotel_id") or 0)
        if hotelcode != source_id:
            raise RuntimeError(f"native_hotelcode_contract:{source_id}:{hotelcode}")
        row["native_anex_hotelcode_confirmed"] = True
        row["native_anex_page_url"] = evidence.get("page_url")
        row["native_anex_photo_source_run"] = evidence.get("source_run")
        row["photo_current_status"] = p.get("status")
        review = p.get("review") or {}
        target = review.get("target") or review.get("bridge_target") or {}
        row["photo_current_candidate_local_id"] = target.get("local_hotel_id")
        row["photo_current_search_count"] = int(row.get("search_count") or 0)
        enriched += 1
        weight += int(row.get("search_count") or 0)
        status_counts[str(p.get("status") or "unknown")] += 1
    rows.sort(key=lambda r: (not bool(r.get("native_anex_hotelcode_confirmed")), int(r.get("rank") or 10**9)))
    for i, row in enumerate(rows, 1):
        row["enriched_rank"] = i
    summary = {
        "queue_count": len(rows),
        "photo_review_evidence": int((photo_doc.get("counts") or {}).get("evidence") or 0),
        "photo_review_ready": int((photo_doc.get("counts") or {}).get("ready") or 0),
        "photo_review_needs_extra": int((photo_doc.get("counts") or {}).get("needs_extra") or 0),
        "photo_review_hard_conflict": int((photo_doc.get("counts") or {}).get("hard_conflict") or 0),
        "manual_rows_with_native_hotelcode": enriched,
        "manual_rows_with_native_hotelcode_search_weight": weight,
        "manual_native_status_counts": dict(sorted(status_counts.items())),
    }
    return rows, summary


def self_test() -> None:
    manual = {"rows": [{"rank": 1, "provider": "anex", "external_hotel_id": "42", "search_count": 7}, {"rank": 2, "provider": "andromeda", "external_hotel_id": "x", "search_count": 9}]}
    photo = {"counts": {"evidence": 1, "ready": 0, "needs_extra": 1, "hard_conflict": 0}, "rows": [{"anex_hotel_id": 42, "status": "needs_extra", "photo_evidence": {"hotelcode": 42, "page_url": "https://anextour.ru/tours/x/y", "source_run": 1}, "review": {}}]}
    rows, s = merge(manual, photo)
    assert len(rows) == 2 and rows[0]["native_anex_hotelcode_confirmed"] is True
    assert s["manual_rows_with_native_hotelcode"] == 1 and s["manual_rows_with_native_hotelcode_search_weight"] == 7
    print("anex-photo-manual-queue-merge self-test: PASS")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--self-test", action="store_true")
    ap.add_argument("--manual")
    ap.add_argument("--photo")
    ap.add_argument("--out")
    ap.add_argument("--operation-id")
    args = ap.parse_args()
    if args.self_test:
        self_test(); return 0
    if not all([args.manual, args.photo, args.out, args.operation_id]):
        raise SystemExit("required arguments missing")
    manual_path, photo_path, out = Path(args.manual), Path(args.photo), Path(args.out)
    out.mkdir(parents=True, exist_ok=False)
    manual = load(manual_path); photo = load(photo_path)
    rows, summary = merge(manual, photo)
    qdoc = {"schema": "match_manual_live_queue_photo_enriched_v2", "rows": rows}
    qpath = out / "queue-photo-enriched.json"
    qpath.write_text(json.dumps(qdoc, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")
    fieldnames = list(rows[0].keys()) if rows else []
    cpath = out / "queue-photo-enriched.csv"
    with cpath.open("w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=fieldnames, extrasaction="ignore")
        w.writeheader(); w.writerows(rows)
    result = {
        "status": "completed_read_only",
        "operation_id": args.operation_id,
        "manual_source_sha256": sha256(manual_path),
        "photo_source_sha256": sha256(photo_path),
        **summary,
        "queue_json_sha256": sha256(qpath),
        "queue_csv_sha256": sha256(cpath),
        "database_writes": 0,
        "mapping_writes": 0,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
        "no_replay": True,
    }
    (out / "result.json").write_text(json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(summary, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
