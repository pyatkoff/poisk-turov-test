#!/usr/bin/env python3
"""Build the exact reviewed Tourvisor meal mapping manifest from immutable CURRENT evidence."""
from __future__ import annotations

import hashlib
import json
import pathlib
import sys
from collections import Counter

OPERATION = "local-stay-meal-map-tourvisor-2690-20260917-v1"
CURRENT_RESULT_SHA256 = "766f6e7843af19730c1d8515898f072a46b797c33275af91755a70e2b3d2d5ae"
CURRENT_ARTIFACT_ID = 10485327353
CURRENT_OPERATION = "local-stay-evidence-current-2690-20260917-v3"
TOURVISOR_DOCS = "https://support.tourvisor.ru/rasshirennye-nastroiki/57-vstavka-koda-modulya-s-parametrami.html"
TARGETS = {
    "3": "breakfast",
    "4": "half-board",
    "7": "all-inclusive",
    "9": "ultra-all-inclusive",
}
EXPECTED_ALL = {"2": 37, "3": 81, "4": 12, "7": 112, "9": 46}
EXPECTED_SELECTED = {"3": 81, "4": 12, "7": 112, "9": 46}
EXPECTED_ROWS = 251


def compact(value: object) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"), sort_keys=True)


def die(message: str) -> "NoReturn":
    raise SystemExit(message)


def main() -> int:
    if len(sys.argv) != 3:
        die("usage: anytour_stay_meal_map_tourvisor_manifest.py CURRENT_RESULT_JSON NEW_MANIFEST_JSON")
    source = pathlib.Path(sys.argv[1])
    target = pathlib.Path(sys.argv[2])
    if source.is_symlink() or not source.is_file() or target.exists() or target.is_symlink():
        die("expected regular source and exclusive NEW manifest path")
    raw = source.read_bytes()
    if hashlib.sha256(raw).hexdigest() != CURRENT_RESULT_SHA256:
        die("CURRENT evidence digest mismatch")
    data = json.loads(raw)
    if not isinstance(data, dict):
        die("CURRENT evidence object required")
    if data.get("status") != "current_read_only_evidence_verified" or data.get("operation") != CURRENT_OPERATION:
        die("wrong CURRENT evidence operation/status")
    if any(data.get(k) != 0 for k in ("writes", "supplierCalls", "automaticAccepts", "publicFileWrites")):
        die("CURRENT evidence is not side-effect-free")
    inventory = data.get("inventory")
    if not isinstance(inventory, dict) or inventory.get("source") != "saved_db_only" or inventory.get("namespace") != "legacy_catalog":
        die("wrong CURRENT inventory source")
    if any(inventory.get(k) != 0 for k in ("writes", "supplierCalls", "automaticAccepts")):
        die("CURRENT inventory is not side-effect-free")
    meals = inventory.get("mealCandidates")
    if not isinstance(meals, list) or len(meals) != sum(EXPECTED_ALL.values()):
        die("unexpected CURRENT meal candidate count")
    all_counts: Counter[str] = Counter()
    selected_counts: Counter[str] = Counter()
    rows: list[dict] = []
    seen: set[str] = set()
    for row in meals:
        if not isinstance(row, dict) or row.get("decisionState") != "unmapped":
            die("all CURRENT meal candidates must remain unmapped")
        scope = row.get("scope")
        ref = row.get("reference")
        if not isinstance(scope, dict) or not isinstance(ref, dict):
            die("invalid CURRENT meal scope/reference")
        if scope.get("namespace") != "legacy_catalog" or ref.get("kind") != "meal" or ref.get("keyKind") != "code":
            die("unexpected CURRENT meal scope/reference kind")
        external = str(ref.get("externalKey", ""))
        all_counts[external] += 1
        if external not in TARGETS:
            continue
        if row.get("sampleLabel") is not None:
            die("documented batch intentionally does not depend on saved meal labels")
        key = compact([scope, ref])
        if key in seen:
            die("duplicate exact source decision")
        seen.add(key)
        selected_counts[external] += 1
        source_sha = row.get("sourceSha256")
        hotel_id = row.get("hotelId")
        if not isinstance(source_sha, str) or len(source_sha) != 64 or any(c not in "0123456789abcdef" for c in source_sha):
            die("invalid source digest")
        if not isinstance(hotel_id, int) or hotel_id < 1:
            die("invalid canonical hotel id")
        target_code = TARGETS[external]
        provenance = {
            "artifactId": CURRENT_ARTIFACT_ID,
            "currentResultSha256": CURRENT_RESULT_SHA256,
            "tourvisorDocs": TOURVISOR_DOCS,
            "scope": scope,
            "reference": ref,
            "hotelId": hotel_id,
            "sourceSha256": source_sha,
            "targetCode": target_code,
        }
        evidence_sha = hashlib.sha256(compact(provenance).encode("utf-8")).hexdigest()
        evidence_ref = (
            f"stay-v3:{CURRENT_RESULT_SHA256};"
            f"tourvisor-tv-meal:{external}->{target_code}"
        )
        if len(evidence_ref.encode("utf-8")) > 255:
            die("evidence reference too long")
        rows.append(
            {
                "scope": scope,
                "reference": ref,
                "hotelId": hotel_id,
                "sourceSha256": source_sha,
                "target": {"code": target_code},
                "evidence": {
                    "ref": evidence_ref,
                    "sha256": evidence_sha,
                    "reviewedBy": "pyatkoff",
                },
            }
        )
    if dict(sorted(all_counts.items())) != EXPECTED_ALL:
        die(f"unexpected CURRENT meal-id distribution: {dict(sorted(all_counts.items()))}")
    if dict(sorted(selected_counts.items())) != EXPECTED_SELECTED or len(rows) != EXPECTED_ROWS:
        die("reviewed selected distribution mismatch")
    rows.sort(key=lambda row: compact([row["scope"], row["reference"]]))
    manifest = {"version": 1, "operation": OPERATION, "rows": rows}
    encoded = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    target.parent.mkdir(parents=True, exist_ok=True)
    with target.open("xb") as handle:
        handle.write(encoded)
        handle.flush()
    digest = hashlib.sha256(encoded).hexdigest()
    print(
        json.dumps(
            {
                "operation": OPERATION,
                "rows": len(rows),
                "counts": dict(sorted(selected_counts.items())),
                "manifestSha256": digest,
                "currentResultSha256": CURRENT_RESULT_SHA256,
                "artifactId": CURRENT_ARTIFACT_ID,
            },
            ensure_ascii=False,
            sort_keys=True,
        )
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
