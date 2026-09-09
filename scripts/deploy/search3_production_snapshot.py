#!/usr/bin/env python3
"""Create and verify a server-local rollback snapshot for an AnyTour release.

The snapshot contains only explicitly inventoried regular files.  It is designed
to stay on the production host: configuration and secret contents are never
printed and must not be uploaded as CI artifacts.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import stat
import sys
import tempfile
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath


SCHEMA_VERSION = 1


class SnapshotError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise SnapshotError(message)


def safe_relative(raw: str) -> str:
    value = raw.strip()
    path = PurePosixPath(value)
    if not value or value.startswith("/") or path.is_absolute():
        fail("inventory path must be non-empty and relative")
    if any(part in ("", ".", "..") for part in path.parts):
        fail("inventory path contains an unsafe segment")
    return path.as_posix()


def read_inventory(path: Path) -> list[str]:
    if not path.is_file() or path.is_symlink():
        fail("inventory must be a regular file")
    result: list[str] = []
    seen: set[str] = set()
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        relative = safe_relative(line)
        if relative in seen:
            fail(f"duplicate inventory path: {relative}")
        seen.add(relative)
        result.append(relative)
    if not result:
        fail("inventory is empty")
    return result


def reject_symlink_components(root: Path, relative: str) -> Path:
    current = root
    for part in PurePosixPath(relative).parts:
        current = current / part
        try:
            info = current.lstat()
        except FileNotFoundError:
            fail(f"inventory path is missing: {relative}")
        if stat.S_ISLNK(info.st_mode):
            fail(f"symlink is not allowed in inventory path: {relative}")
    if not current.is_file():
        fail(f"inventory path is not a regular file: {relative}")
    return current


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def within(child: Path, parent: Path) -> bool:
    try:
        child.resolve().relative_to(parent.resolve())
        return True
    except ValueError:
        return False


def snapshot(root: Path, backup_dir: Path, inventory: Path, snapshot_id: str, source_sha: str) -> Path:
    root = root.resolve()
    backup_dir = backup_dir.resolve()
    if not root.is_dir() or root.is_symlink():
        fail("root must be a real directory")
    if within(backup_dir, root):
        fail("backup directory must be outside the served root")
    paths = read_inventory(inventory)
    files = [(relative, reject_symlink_components(root, relative)) for relative in paths]

    safe_id = safe_relative(snapshot_id)
    if "/" in safe_id:
        fail("snapshot id must be one path segment")
    backup_dir.mkdir(mode=0o700, parents=True, exist_ok=True)
    os.chmod(backup_dir, 0o700)
    destination = backup_dir / safe_id
    if destination.exists():
        fail("snapshot destination already exists")

    temporary = Path(tempfile.mkdtemp(prefix=f".{safe_id}-", dir=backup_dir))
    os.chmod(temporary, 0o700)
    payload = temporary / "payload"
    payload.mkdir(mode=0o700)
    entries: list[dict[str, object]] = []
    try:
        for relative, source in files:
            target = payload.joinpath(*PurePosixPath(relative).parts)
            target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            shutil.copyfile(source, target, follow_symlinks=False)
            mode = stat.S_IMODE(source.stat(follow_symlinks=False).st_mode)
            os.chmod(target, mode)
            entries.append({
                "path": relative,
                "size": source.stat(follow_symlinks=False).st_size,
                "mode": f"{mode:04o}",
                "sha256": sha256_file(source),
            })
        manifest = {
            "schema_version": SCHEMA_VERSION,
            "snapshot_id": safe_id,
            "source_sha": source_sha,
            "created_at_utc": datetime.now(timezone.utc).replace(microsecond=0).isoformat(),
            "file_count": len(entries),
            "files": entries,
        }
        manifest_path = temporary / "manifest.json"
        manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        os.chmod(manifest_path, 0o600)
        verify(temporary)
        temporary.rename(destination)
    except Exception:
        shutil.rmtree(temporary, ignore_errors=True)
        raise
    return destination


def load_manifest(snapshot_dir: Path) -> dict[str, object]:
    manifest_path = snapshot_dir / "manifest.json"
    if not manifest_path.is_file() or manifest_path.is_symlink():
        fail("snapshot manifest is missing or unsafe")
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        fail(f"snapshot manifest is invalid: {exc}")
    if manifest.get("schema_version") != SCHEMA_VERSION:
        fail("unsupported snapshot schema")
    files = manifest.get("files")
    if not isinstance(files, list) or manifest.get("file_count") != len(files):
        fail("snapshot manifest file count is invalid")
    return manifest


def verify(snapshot_dir: Path) -> dict[str, object]:
    snapshot_dir = snapshot_dir.resolve()
    if not snapshot_dir.is_dir() or snapshot_dir.is_symlink():
        fail("snapshot directory is missing or unsafe")
    manifest = load_manifest(snapshot_dir)
    payload = snapshot_dir / "payload"
    if not payload.is_dir() or payload.is_symlink():
        fail("snapshot payload is missing or unsafe")
    expected: set[str] = set()
    for raw_entry in manifest["files"]:
        if not isinstance(raw_entry, dict):
            fail("snapshot manifest entry is invalid")
        relative = safe_relative(str(raw_entry.get("path", "")))
        if relative in expected:
            fail(f"duplicate snapshot path: {relative}")
        expected.add(relative)
        source = reject_symlink_components(payload, relative)
        mode = f"{stat.S_IMODE(source.stat(follow_symlinks=False).st_mode):04o}"
        if source.stat(follow_symlinks=False).st_size != raw_entry.get("size"):
            fail(f"snapshot size mismatch: {relative}")
        if mode != raw_entry.get("mode"):
            fail(f"snapshot mode mismatch: {relative}")
        if sha256_file(source) != raw_entry.get("sha256"):
            fail(f"snapshot hash mismatch: {relative}")
    actual = {
        path.relative_to(payload).as_posix()
        for path in payload.rglob("*")
        if path.is_file() or path.is_symlink()
    }
    if actual != expected:
        fail("snapshot payload contains missing or unexpected files")
    return manifest


def restore(snapshot_dir: Path, target_root: Path) -> None:
    manifest = verify(snapshot_dir)
    if target_root.exists():
        if target_root.is_symlink() or not target_root.is_dir():
            fail("restore target must be a real directory")
        if any(target_root.iterdir()):
            fail("restore target must be empty")
    else:
        target_root.mkdir(mode=0o700, parents=True)
    payload = snapshot_dir.resolve() / "payload"
    for raw_entry in manifest["files"]:
        relative = safe_relative(str(raw_entry["path"]))
        source = reject_symlink_components(payload, relative)
        target = target_root.joinpath(*PurePosixPath(relative).parts)
        target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        shutil.copyfile(source, target, follow_symlinks=False)
        os.chmod(target, int(str(raw_entry["mode"]), 8))
    for raw_entry in manifest["files"]:
        relative = safe_relative(str(raw_entry["path"]))
        restored = reject_symlink_components(target_root, relative)
        if sha256_file(restored) != raw_entry["sha256"]:
            fail(f"restored hash mismatch: {relative}")


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser(description=__doc__)
    subparsers = result.add_subparsers(dest="command", required=True)
    create = subparsers.add_parser("snapshot")
    create.add_argument("--root", type=Path, required=True)
    create.add_argument("--backup-dir", type=Path, required=True)
    create.add_argument("--inventory", type=Path, required=True)
    create.add_argument("--snapshot-id", required=True)
    create.add_argument("--source-sha", required=True)
    check = subparsers.add_parser("verify")
    check.add_argument("--snapshot-dir", type=Path, required=True)
    extract = subparsers.add_parser("restore")
    extract.add_argument("--snapshot-dir", type=Path, required=True)
    extract.add_argument("--target-root", type=Path, required=True)
    return result


def main() -> int:
    args = parser().parse_args()
    try:
        if args.command == "snapshot":
            destination = snapshot(args.root, args.backup_dir, args.inventory, args.snapshot_id, args.source_sha)
            manifest = verify(destination)
            print(json.dumps({"status": "snapshot_verified", "snapshot_id": manifest["snapshot_id"], "source_sha": manifest["source_sha"], "file_count": manifest["file_count"]}))
        elif args.command == "verify":
            manifest = verify(args.snapshot_dir)
            print(json.dumps({"status": "snapshot_verified", "snapshot_id": manifest["snapshot_id"], "source_sha": manifest["source_sha"], "file_count": manifest["file_count"]}))
        else:
            restore(args.snapshot_dir, args.target_root)
            manifest = load_manifest(args.snapshot_dir.resolve())
            print(json.dumps({"status": "restore_drill_verified", "snapshot_id": manifest["snapshot_id"], "source_sha": manifest["source_sha"], "file_count": manifest["file_count"]}))
        return 0
    except SnapshotError as exc:
        print(f"ROLLBACK_SNAPSHOT_ERROR: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
