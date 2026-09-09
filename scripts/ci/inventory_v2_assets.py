#!/usr/bin/env python3
"""Inventory V2 browser assets against the canonical bundle manifest.

This is an audit/refactor helper, not a runtime loader. It deliberately does not
classify non-manifest assets as dead: absence from the active bundle only means
that further runtime/CI/deploy/compatibility consumer proof is required.
"""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
V2 = ROOT / "v2"
MANIFEST = V2 / "bundle-manifest-v1.php"
ASSET_SUFFIXES = {".css", ".js"}


def load_manifest() -> dict[str, list[str]]:
    php = (
        f"require {json.dumps(str(MANIFEST))}; "
        "$m=v2_bundle_manifest(); "
        "echo json_encode($m, JSON_UNESCAPED_SLASHES);"
    )
    proc = subprocess.run(
        ["php", "-r", php],
        cwd=ROOT,
        check=False,
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        raise RuntimeError(f"cannot evaluate {MANIFEST.relative_to(ROOT)}: {proc.stderr.strip()}")
    try:
        data = json.loads(proc.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"manifest did not return valid JSON: {exc}") from exc
    if not isinstance(data, dict):
        raise RuntimeError("manifest root must be an object")
    result: dict[str, list[str]] = {}
    for kind in ("css", "js"):
        values = data.get(kind)
        if not isinstance(values, list) or not all(isinstance(item, str) for item in values):
            raise RuntimeError(f"manifest key {kind!r} must be a string list")
        result[kind] = values
    return result


def repository_assets() -> list[str]:
    assets: list[str] = []
    for path in V2.rglob("*"):
        if path.is_file() and path.suffix.lower() in ASSET_SUFFIXES:
            assets.append(path.relative_to(V2).as_posix())
    return sorted(assets)


def git_tracked_paths_containing(needle: str) -> set[str]:
    """Return tracked repository files containing a literal string.

    Exit code 1 from git grep means no match and is not an error. The helper is
    intentionally repository-wide so workflow/deploy/test/compatibility consumers
    are visible alongside runtime references.
    """

    proc = subprocess.run(
        ["git", "grep", "-l", "-F", "--", needle],
        cwd=ROOT,
        check=False,
        capture_output=True,
        text=True,
    )
    if proc.returncode == 1:
        return set()
    if proc.returncode != 0:
        raise RuntimeError(f"git grep failed for {needle!r}: {proc.stderr.strip()}")
    return {line.strip() for line in proc.stdout.splitlines() if line.strip()}


def repository_consumers(asset: str) -> list[str]:
    """Map textual repository consumers for one V2 asset.

    Search both the V2-relative asset path and basename because current flat V2
    loaders/workflows commonly refer to browser files by basename. Results are
    evidence for classification, not proof of runtime use or deadness.
    """

    basename = Path(asset).name
    needles = {asset, f"v2/{asset}", basename}
    consumers: set[str] = set()
    for needle in needles:
        consumers.update(git_tracked_paths_containing(needle))

    own_path = f"v2/{asset}"
    consumers.discard(own_path)
    return sorted(consumers)


def build_report() -> tuple[dict[str, Any], list[str]]:
    manifest = load_manifest()
    all_assets = repository_assets()
    active = manifest["css"] + manifest["js"]
    active_set = set(active)
    errors: list[str] = []

    if len(active) != len(active_set):
        seen: set[str] = set()
        duplicates = sorted(item for item in active if item in seen or seen.add(item))
        errors.append(f"duplicate manifest entries: {duplicates}")

    for kind, entries in manifest.items():
        expected_suffix = f".{kind}"
        for entry in entries:
            if Path(entry).suffix.lower() != expected_suffix:
                errors.append(f"manifest {kind} entry has wrong extension: {entry}")
            if entry.startswith("/") or ".." in Path(entry).parts:
                errors.append(f"manifest entry is not a safe relative path: {entry}")
            if not (V2 / entry).is_file():
                errors.append(f"manifest entry is missing from v2/: {entry}")

    non_manifest = sorted(asset for asset in all_assets if asset not in active_set)
    non_manifest_consumers = {asset: repository_consumers(asset) for asset in non_manifest}
    referenced_non_manifest = sum(1 for consumers in non_manifest_consumers.values() if consumers)

    report = {
        "schema_version": 2,
        "source_of_truth": "v2/bundle-manifest-v1.php",
        "classification_rule": {
            "active": "listed in the canonical bundle manifest",
            "non_manifest": (
                "not in the active browser bundle; requires runtime/CI/deploy/compatibility "
                "consumer proof before any deprecated/dead classification"
            ),
            "consumer_reference": (
                "tracked repository file containing the asset basename/path; evidence only, "
                "not proof that the reference is an active runtime consumer"
            ),
        },
        "counts": {
            "repository_browser_assets": len(all_assets),
            "active_css": len(manifest["css"]),
            "active_js": len(manifest["js"]),
            "active_total": len(active_set),
            "non_manifest_total": len(non_manifest),
            "non_manifest_with_repository_references": referenced_non_manifest,
            "non_manifest_without_repository_references": len(non_manifest) - referenced_non_manifest,
        },
        "active": {
            "css": manifest["css"],
            "js": manifest["js"],
        },
        "non_manifest": [
            {
                "asset": asset,
                "repository_consumers": non_manifest_consumers[asset],
            }
            for asset in non_manifest
        ],
    }
    return report, errors


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--json", action="store_true", help="print the full inventory as JSON")
    parser.add_argument(
        "--strict-non-manifest",
        action="store_true",
        help="fail when any JS/CSS asset is outside the active manifest (normally audit-only)",
    )
    args = parser.parse_args()

    try:
        report, errors = build_report()
    except Exception as exc:  # audit tool: keep failure output concise for CI/log use
        print(f"V2_ASSET_INVENTORY_FAIL: {exc}", file=sys.stderr)
        return 2

    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
    else:
        counts = report["counts"]
        print(
            "V2_ASSET_INVENTORY_OK "
            f"repo={counts['repository_browser_assets']} "
            f"active_css={counts['active_css']} active_js={counts['active_js']} "
            f"active_total={counts['active_total']} non_manifest={counts['non_manifest_total']} "
            f"referenced_non_manifest={counts['non_manifest_with_repository_references']} "
            f"unreferenced_non_manifest={counts['non_manifest_without_repository_references']}"
        )
        for item in report["non_manifest"]:
            consumers = item["repository_consumers"]
            suffix = f" consumers={','.join(consumers)}" if consumers else " consumers=-"
            print(f"NON_MANIFEST {item['asset']}{suffix}")

    if errors:
        for error in errors:
            print(f"V2_ASSET_INVENTORY_ERROR {error}", file=sys.stderr)
        return 1
    if args.strict_non_manifest and report["non_manifest"]:
        print("V2_ASSET_INVENTORY_ERROR non-manifest assets require classification", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
