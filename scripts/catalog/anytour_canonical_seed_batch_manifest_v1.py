#!/usr/bin/env python3
"""Validate one immutable provider-only AnyTour canonical seed manifest.

This is an operations-boundary validator only. It performs no DB/network I/O and
never creates/accepts hotel identities. The source snapshot is intentionally pinned
for v1 so a later MATCH snapshot requires a successor contract rather than silent
reuse.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import pathlib
import re
from typing import Any

SCHEMA_VERSION = 1
SOURCE_OPERATION = "hotel-match-missing-canonical-priority-1971-20260917-v2"
SOURCE_RUN = 35255169735
SOURCE_PAYLOAD_SHA256 = "e403b2473125185ce52918f01b9d5df0ffaef4cfdfff0c5114bbdaaed2a1c2de"
SELECTION = "ready_rows where priority_tier=provider_only"
MAX_BATCH = 1000
KEYS = {
    "schema_version", "operation_id", "selection", "source_operation", "source_run",
    "source_payload_sha256", "base_release", "claim_comment_id", "batch_index",
    "batch_total", "expected_count", "tourvisor_hotel_ids",
}
OP_RE = re.compile(r"\Aanytour-canonical-provider-only-seed-2690-[0-9]{8}-b[0-9]{3}-v[0-9]+\Z")
SHA40_RE = re.compile(r"\A[a-f0-9]{40}\Z")
SHA64_RE = re.compile(r"\A[a-f0-9]{64}\Z")
FILE_RE = re.compile(r"\Aanytour_provider_only_seed_manifest_v1_b[0-9]{3}\.json\Z")


def require(ok: bool, code: str) -> None:
    if not ok:
        raise ValueError(code)


def validate_bytes(raw: bytes, filename: str) -> dict[str, Any]:
    require(FILE_RE.fullmatch(filename) is not None, "manifest_filename")
    try:
        value = json.loads(raw)
    except Exception as exc:
        raise ValueError("manifest_json") from exc
    require(isinstance(value, dict), "manifest_object")
    require(set(value) == KEYS, "manifest_keys")
    require(value["schema_version"] == SCHEMA_VERSION, "schema_version")
    require(value["selection"] == SELECTION, "selection")
    require(value["source_operation"] == SOURCE_OPERATION, "source_operation")
    require(value["source_run"] == SOURCE_RUN, "source_run")
    require(value["source_payload_sha256"] == SOURCE_PAYLOAD_SHA256, "source_payload_sha256")
    require(isinstance(value["operation_id"], str) and OP_RE.fullmatch(value["operation_id"]) is not None, "operation_id")
    require(isinstance(value["base_release"], str) and SHA40_RE.fullmatch(value["base_release"]) is not None, "base_release")
    require(isinstance(value["claim_comment_id"], int) and not isinstance(value["claim_comment_id"], bool) and value["claim_comment_id"] > 0, "claim_comment_id")
    require(isinstance(value["batch_index"], int) and not isinstance(value["batch_index"], bool), "batch_index")
    require(isinstance(value["batch_total"], int) and not isinstance(value["batch_total"], bool), "batch_total")
    require(1 <= value["batch_index"] <= value["batch_total"] <= 99, "batch_range")
    expected = value["expected_count"]
    ids = value["tourvisor_hotel_ids"]
    require(isinstance(expected, int) and not isinstance(expected, bool) and 1 <= expected <= MAX_BATCH, "expected_count")
    require(isinstance(ids, list) and len(ids) == expected, "ids_count")
    require(all(isinstance(item, int) and not isinstance(item, bool) and item > 0 for item in ids), "ids_shape")
    require(len(set(ids)) == len(ids), "ids_duplicate")
    require(SHA64_RE.fullmatch(value["source_payload_sha256"]) is not None, "source_payload_sha_shape")
    batch_from_op = int(re.search(r"-b([0-9]{3})-v", value["operation_id"]).group(1))
    require(batch_from_op == value["batch_index"], "operation_batch_mismatch")
    return {
        "schema_version": SCHEMA_VERSION,
        "manifest_sha256": hashlib.sha256(raw).hexdigest(),
        "operation_id": value["operation_id"],
        "base_release": value["base_release"],
        "claim_comment_id": value["claim_comment_id"],
        "batch_index": value["batch_index"],
        "batch_total": value["batch_total"],
        "expected_count": expected,
        "tourvisor_hotel_ids": ids,
        "source_operation": SOURCE_OPERATION,
        "source_run": SOURCE_RUN,
        "source_payload_sha256": SOURCE_PAYLOAD_SHA256,
        "selection": SELECTION,
    }


def validate_path(path: pathlib.Path) -> dict[str, Any]:
    require(path.is_file() and not path.is_symlink(), "manifest_file")
    return validate_bytes(path.read_bytes(), path.name)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True, type=pathlib.Path)
    args = parser.parse_args()
    result = validate_path(args.manifest)
    print(json.dumps(result, ensure_ascii=False, sort_keys=True, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
