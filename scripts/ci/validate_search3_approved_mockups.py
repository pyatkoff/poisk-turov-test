#!/usr/bin/env python3
"""Validate the private, checksum-addressed Search3 design baseline metadata."""

from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "docs/project/search3-approved-mockups.json"
EXPECTED_COLLECTION_SHA = "cbecdac4080b7a7a541a3b9b5de4a4f8448203717728f7ca7afa4ea6373f45b8"
EXPECTED_ARCHIVE_SHA = "475e9b8f5c40e49c038c880e064c3977b34292ae402d60beae5496494b083699"
SHA256 = re.compile(r"^[0-9a-f]{64}$")


def validate(path: Path = MANIFEST) -> list[str]:
    errors: list[str] = []
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        return [f"mockup manifest unreadable: {exc}"]

    if data.get("schema_version") != 1:
        errors.append("schema_version must stay 1")
    if data.get("status") != "CHECKSUM_ADDRESSED_PRIVATE_EXPORTS_AVAILABLE_COMPARISON_PENDING":
        errors.append("mockup status must stay comparison-pending")
    if data.get("pixels_vendored_in_repository") is not False:
        errors.append("approved pixels must not be vendored")
    if data.get("public_redistribution_authorized") is not False:
        errors.append("public redistribution must remain disabled")
    if data.get("owner_visual_approval") is not False:
        errors.append("metadata alone cannot claim owner visual approval")
    if data.get("comparison_status") != "PENDING":
        errors.append("comparison must remain pending until visual evidence closes it")

    archive = data.get("archive") or {}
    if archive != {
        "filename": "Search3-final-8-mockups.zip",
        "size_bytes": 10693495,
        "sha256": EXPECTED_ARCHIVE_SHA,
        "entry_count": 8,
    }:
        errors.append("approved archive identity drift")

    exports = data.get("exports") or []
    if [entry.get("layout_id") for entry in exports if isinstance(entry, dict)] != list(range(1, 9)):
        errors.append("approved exports must contain ordered layout_id 1..8")
    if len(exports) != 8:
        errors.append("approved exports must contain exactly eight entries")
    for entry in exports:
        if not isinstance(entry, dict):
            errors.append("approved export entry must be an object")
            continue
        if not SHA256.fullmatch(str(entry.get("sha256", ""))):
            errors.append(f"invalid SHA-256 for layout {entry.get('layout_id')}")
        for key in ("width", "height", "size_bytes"):
            if not isinstance(entry.get(key), int) or entry[key] <= 0:
                errors.append(f"invalid {key} for layout {entry.get('layout_id')}")

    if sum(int(entry.get("size_bytes", 0)) for entry in exports if isinstance(entry, dict)) != 11830930:
        errors.append("approved export byte total drift")
    canonical = json.dumps(exports, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    collection_sha = hashlib.sha256(canonical).hexdigest()
    if collection_sha != EXPECTED_COLLECTION_SHA:
        errors.append("approved export collection hash drift")
    if data.get("exports_manifest_sha256") != EXPECTED_COLLECTION_SHA:
        errors.append("recorded approved export collection hash drift")
    if data.get("exports_total_size_bytes") != 11830930:
        errors.append("recorded approved export byte total drift")
    return errors


if __name__ == "__main__":
    problems = validate(Path(sys.argv[1]) if len(sys.argv) > 1 else MANIFEST)
    if problems:
        print("SEARCH3_APPROVED_MOCKUPS_INVALID")
        for problem in problems:
            print(f"- {problem}")
        raise SystemExit(1)
    print("SEARCH3_APPROVED_MOCKUPS_OK layouts=8 pixels=private comparison=pending")
